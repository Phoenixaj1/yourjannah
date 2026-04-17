<?php
/**
 * YNJ_Social_Auth — Google & Facebook OAuth for YourJannah.
 *
 * Handles server-side OAuth2 redirect flow:
 * 1. User clicks "Continue with Google/Facebook"
 * 2. Redirect to provider's consent screen
 * 3. Callback with authorization code
 * 4. Exchange code for tokens, get profile
 * 5. Find or create WP user → set auth cookie → redirect back
 *
 * @package YourJannah
 * @since   3.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Social_Auth {

    /**
     * Register rewrite rules and handle callbacks.
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_callback' ] );
    }

    /**
     * Add rewrite rules for OAuth callbacks.
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule( '^ynj-auth/(google|facebook)/callback/?$', 'index.php?ynj_social_auth=1&ynj_provider=$matches[1]', 'top' );
        add_rewrite_rule( '^ynj-auth/(google|facebook)/redirect/?$', 'index.php?ynj_social_redirect=1&ynj_provider=$matches[1]', 'top' );

        add_rewrite_tag( '%ynj_social_auth%', '([0-9]+)' );
        add_rewrite_tag( '%ynj_social_redirect%', '([0-9]+)' );
        add_rewrite_tag( '%ynj_provider%', '([a-z]+)' );

        // Ensure query vars are registered
        add_filter( 'query_vars', function( $vars ) {
            $vars[] = 'ynj_social_auth';
            $vars[] = 'ynj_social_redirect';
            $vars[] = 'ynj_provider';
            return $vars;
        } );
    }

    /**
     * Handle OAuth redirect and callback.
     */
    public static function handle_callback() {
        // Redirect TO provider
        if ( get_query_var( 'ynj_social_redirect' ) ) {
            $provider = get_query_var( 'ynj_provider' );
            $return_to = sanitize_text_field( $_GET['return_to'] ?? '/' );
            $mosque_slug = sanitize_text_field( $_GET['mosque_slug'] ?? '' );
            $join_mosque = sanitize_text_field( $_GET['join_mosque'] ?? '' );

            // Store state in transient
            $state = wp_generate_password( 32, false );
            set_transient( 'ynj_oauth_' . $state, [
                'provider'    => $provider,
                'return_to'   => $return_to,
                'mosque_slug' => $mosque_slug,
                'join_mosque' => $join_mosque,
            ], 600 ); // 10 min expiry

            if ( $provider === 'google' ) {
                self::redirect_to_google( $state );
            } elseif ( $provider === 'facebook' ) {
                self::redirect_to_facebook( $state );
            }
            exit;
        }

        // Callback FROM provider
        if ( ! get_query_var( 'ynj_social_auth' ) ) return;

        $provider = get_query_var( 'ynj_provider' );
        $code     = sanitize_text_field( $_GET['code'] ?? '' );
        $state    = sanitize_text_field( $_GET['state'] ?? '' );
        $error    = sanitize_text_field( $_GET['error'] ?? '' );

        if ( $error || ! $code || ! $state ) {
            wp_redirect( home_url( '/?login_error=oauth_denied' ) );
            exit;
        }

        // Verify state
        $oauth_data = get_transient( 'ynj_oauth_' . $state );
        if ( ! $oauth_data || $oauth_data['provider'] !== $provider ) {
            wp_redirect( home_url( '/?login_error=invalid_state' ) );
            exit;
        }
        delete_transient( 'ynj_oauth_' . $state );

        // Exchange code for user profile
        if ( $provider === 'google' ) {
            $profile = self::google_exchange( $code );
        } elseif ( $provider === 'facebook' ) {
            $profile = self::facebook_exchange( $code );
        } else {
            wp_redirect( home_url( '/?login_error=unknown_provider' ) );
            exit;
        }

        if ( ! $profile || empty( $profile['email'] ) ) {
            wp_redirect( home_url( '/?login_error=no_email' ) );
            exit;
        }

        // Find or create WP user
        $wp_user_id = self::find_or_create_user( $profile, $provider );
        if ( is_wp_error( $wp_user_id ) ) {
            wp_redirect( home_url( '/?login_error=create_failed' ) );
            exit;
        }

        // Log them in
        wp_set_auth_cookie( $wp_user_id, true );

        // Auto-join mosque if requested
        if ( ! empty( $oauth_data['join_mosque'] ) ) {
            self::auto_join_mosque( $wp_user_id, $oauth_data['join_mosque'] );
        }

        // Redirect back
        $return = $oauth_data['return_to'] ?: '/';
        wp_redirect( home_url( $return . ( strpos( $return, '?' ) !== false ? '&' : '?' ) . 'social_login=success' ) );
        exit;
    }

    // ================================================================
    // GOOGLE OAUTH
    // ================================================================

    private static function redirect_to_google( string $state ) {
        $client_id = get_option( 'ynj_google_client_id', '' );
        if ( ! $client_id ) {
            wp_redirect( home_url( '/?login_error=google_not_configured' ) );
            exit;
        }

        $params = http_build_query( [
            'client_id'     => $client_id,
            'redirect_uri'  => home_url( '/ynj-auth/google/callback' ),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ] );

        wp_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . $params );
    }

    private static function google_exchange( string $code ): ?array {
        $client_id     = get_option( 'ynj_google_client_id', '' );
        $client_secret = get_option( 'ynj_google_client_secret', '' );

        // Exchange code for tokens
        $token_response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => home_url( '/ynj-auth/google/callback' ),
                'grant_type'    => 'authorization_code',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $token_response ) ) return null;
        $tokens = json_decode( wp_remote_retrieve_body( $token_response ), true );
        if ( empty( $tokens['access_token'] ) ) return null;

        // Get user profile
        $profile_response = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', [
            'headers' => [ 'Authorization' => 'Bearer ' . $tokens['access_token'] ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $profile_response ) ) return null;
        $profile = json_decode( wp_remote_retrieve_body( $profile_response ), true );

        return [
            'email'       => sanitize_email( $profile['email'] ?? '' ),
            'name'        => sanitize_text_field( $profile['name'] ?? '' ),
            'first_name'  => sanitize_text_field( $profile['given_name'] ?? '' ),
            'last_name'   => sanitize_text_field( $profile['family_name'] ?? '' ),
            'avatar_url'  => esc_url_raw( $profile['picture'] ?? '' ),
            'provider_id' => sanitize_text_field( $profile['id'] ?? '' ),
        ];
    }

    // ================================================================
    // FACEBOOK OAUTH
    // ================================================================

    private static function redirect_to_facebook( string $state ) {
        $app_id = get_option( 'ynj_facebook_app_id', '' );
        if ( ! $app_id ) {
            wp_redirect( home_url( '/?login_error=facebook_not_configured' ) );
            exit;
        }

        $params = http_build_query( [
            'client_id'     => $app_id,
            'redirect_uri'  => home_url( '/ynj-auth/facebook/callback' ),
            'response_type' => 'code',
            'scope'         => 'email,public_profile',
            'state'         => $state,
        ] );

        wp_redirect( 'https://www.facebook.com/v19.0/dialog/oauth?' . $params );
    }

    private static function facebook_exchange( string $code ): ?array {
        $app_id     = get_option( 'ynj_facebook_app_id', '' );
        $app_secret = get_option( 'ynj_facebook_app_secret', '' );

        // Exchange code for access token
        $token_url = add_query_arg( [
            'client_id'     => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri'  => home_url( '/ynj-auth/facebook/callback' ),
            'code'          => $code,
        ], 'https://graph.facebook.com/v19.0/oauth/access_token' );

        $token_response = wp_remote_get( $token_url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $token_response ) ) return null;
        $tokens = json_decode( wp_remote_retrieve_body( $token_response ), true );
        if ( empty( $tokens['access_token'] ) ) return null;

        // Get user profile
        $profile_url = add_query_arg( [
            'fields'       => 'id,name,email,first_name,last_name,picture.type(large)',
            'access_token' => $tokens['access_token'],
        ], 'https://graph.facebook.com/v19.0/me' );

        $profile_response = wp_remote_get( $profile_url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $profile_response ) ) return null;
        $profile = json_decode( wp_remote_retrieve_body( $profile_response ), true );

        return [
            'email'       => sanitize_email( $profile['email'] ?? '' ),
            'name'        => sanitize_text_field( $profile['name'] ?? '' ),
            'first_name'  => sanitize_text_field( $profile['first_name'] ?? '' ),
            'last_name'   => sanitize_text_field( $profile['last_name'] ?? '' ),
            'avatar_url'  => esc_url_raw( $profile['picture']['data']['url'] ?? '' ),
            'provider_id' => sanitize_text_field( $profile['id'] ?? '' ),
        ];
    }

    // ================================================================
    // FIND OR CREATE USER
    // ================================================================

    private static function find_or_create_user( array $profile, string $provider ) {
        // Try existing WP user by email
        $existing = get_user_by( 'email', $profile['email'] );

        if ( $existing ) {
            // Link social auth info to existing account
            $ynj_user_id = (int) get_user_meta( $existing->ID, 'ynj_user_id', true );
            if ( $ynj_user_id ) {
                global $wpdb;
                $ut = YNJ_DB::table( 'users' );
                $wpdb->update( $ut, [
                    'auth_provider'    => $provider,
                    'auth_provider_id' => $profile['provider_id'],
                    'avatar_url'       => $profile['avatar_url'],
                ], [ 'id' => $ynj_user_id ] );
            }
            return $existing->ID;
        }

        // Create new WP user
        $username = sanitize_user( strtolower( str_replace( ' ', '', $profile['name'] ?: explode( '@', $profile['email'] )[0] ) ) );

        // Ensure unique username
        $base_username = $username;
        $counter = 1;
        while ( username_exists( $username ) ) {
            $username = $base_username . $counter;
            $counter++;
        }

        $password = wp_generate_password( 16, true );
        $wp_user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $profile['email'],
            'user_pass'    => $password,
            'display_name' => $profile['name'] ?: $username,
            'first_name'   => $profile['first_name'] ?: '',
            'last_name'    => $profile['last_name'] ?: '',
            'role'         => 'ynj_congregation',
        ] );

        if ( is_wp_error( $wp_user_id ) ) {
            return $wp_user_id;
        }

        // Create ynj_users record
        global $wpdb;
        $ut = YNJ_DB::table( 'users' );
        $wpdb->insert( $ut, [
            'name'             => $profile['name'] ?: $username,
            'email'            => $profile['email'],
            'auth_provider'    => $provider,
            'auth_provider_id' => $profile['provider_id'],
            'avatar_url'       => $profile['avatar_url'],
        ] );
        $ynj_user_id = (int) $wpdb->insert_id;

        // Link WP user to ynj_user
        update_user_meta( $wp_user_id, 'ynj_user_id', $ynj_user_id );

        return $wp_user_id;
    }

    // ================================================================
    // AUTO-JOIN MOSQUE AFTER SOCIAL LOGIN
    // ================================================================

    private static function auto_join_mosque( int $wp_user_id, string $mosque_slug ) {
        $mosque_id = (int) YNJ_DB::resolve_slug( $mosque_slug );
        if ( ! $mosque_id ) return;

        $ynj_user_id = (int) get_user_meta( $wp_user_id, 'ynj_user_id', true );
        if ( ! $ynj_user_id ) return;

        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        $mt = YNJ_DB::table( 'mosques' );
        $ut = YNJ_DB::table( 'users' );

        // Check if already a member
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $st WHERE user_id = %d AND mosque_id = %d AND is_member = 1 AND status = 'active'",
            $ynj_user_id, $mosque_id
        ) );
        if ( $existing ) return;

        // Check for existing subscription
        $sub = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $st WHERE user_id = %d AND mosque_id = %d",
            $ynj_user_id, $mosque_id
        ) );

        // Clear any existing primary first
        $wpdb->update( $st, [ 'is_primary' => 0 ], [ 'user_id' => $ynj_user_id, 'is_primary' => 1 ] );

        if ( $sub ) {
            // Only increment count if not already a member
            $was_member = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT is_member FROM $st WHERE id = %d", $sub->id
            ) );
            $wpdb->update( $st, [
                'status'     => 'active',
                'is_member'  => 1,
                'is_primary' => 1,
            ], [ 'id' => $sub->id ] );
            if ( ! $was_member ) {
                $wpdb->query( $wpdb->prepare( "UPDATE $mt SET member_count = member_count + 1 WHERE id = %d", $mosque_id ) );
            }
            $wpdb->update( $ut, [ 'favourite_mosque_id' => $mosque_id ], [ 'id' => $ynj_user_id ] );
            update_user_meta( $wp_user_id, 'ynj_favourite_mosque_id', $mosque_id );
            return;
        } else {
            $wpdb->insert( $st, [
                'user_id'              => $ynj_user_id,
                'mosque_id'            => $mosque_id,
                'notify_events'        => 1,
                'notify_classes'       => 1,
                'notify_announcements' => 1,
                'notify_fundraising'   => 0,
                'notify_live'          => 1,
                'is_member'            => 1,
                'is_primary'           => 1,
                'status'               => 'active',
            ] );
        }

        // Set favourite + increment count
        $wpdb->update( $ut, [ 'favourite_mosque_id' => $mosque_id ], [ 'id' => $ynj_user_id ] );
        update_user_meta( $wp_user_id, 'ynj_favourite_mosque_id', $mosque_id );
        $wpdb->query( $wpdb->prepare( "UPDATE $mt SET member_count = member_count + 1 WHERE id = %d", $mosque_id ) );
    }

    // ================================================================
    // SETTINGS HELPERS
    // ================================================================

    /**
     * Check if Google OAuth is configured.
     */
    public static function is_google_configured(): bool {
        return (bool) get_option( 'ynj_google_client_id', '' );
    }

    /**
     * Check if Facebook OAuth is configured.
     */
    public static function is_facebook_configured(): bool {
        return (bool) get_option( 'ynj_facebook_app_id', '' );
    }

    /**
     * Get the social login redirect URL for a provider.
     */
    public static function get_login_url( string $provider, string $return_to = '/', string $mosque_slug = '', string $join_mosque = '' ): string {
        $params = [ 'return_to' => $return_to ];
        if ( $mosque_slug ) $params['mosque_slug'] = $mosque_slug;
        if ( $join_mosque ) $params['join_mosque'] = $join_mosque;
        return home_url( '/ynj-auth/' . $provider . '/redirect?' . http_build_query( $params ) );
    }
}
