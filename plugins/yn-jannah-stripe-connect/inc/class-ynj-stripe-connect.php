<?php
/**
 * Stripe Connect — OAuth onboarding, destination charges, platform fees.
 *
 * Flow:
 * 1. Mosque admin clicks "Connect Stripe" in dashboard
 * 2. Redirects to Stripe OAuth → mosque authorises YourJannah
 * 3. Callback saves stripe_account_id on the mosque record
 * 4. All future payments for that mosque use destination charges
 * 5. Platform fee (configurable %) taken as application_fee_amount
 *
 * @package YNJ_Stripe_Connect
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Stripe_Connect {

    /** Default platform fee percentage */
    const DEFAULT_FEE_PCT = 5;

    /**
     * Add stripe_account_id + platform_fee_pct columns to mosques table if missing.
     */
    public static function maybe_add_columns() {
        if ( get_option( 'ynj_sc_columns_added' ) ) return;
        global $wpdb;
        $t = YNJ_DB::table( 'mosques' );

        // Check if column exists
        $col = $wpdb->get_var( "SHOW COLUMNS FROM $t LIKE 'stripe_account_id'" );
        if ( ! $col ) {
            $wpdb->query( "ALTER TABLE $t ADD COLUMN stripe_account_id varchar(50) NOT NULL DEFAULT '' AFTER dfm_mosque_id" );
            $wpdb->query( "ALTER TABLE $t ADD COLUMN platform_fee_pct decimal(5,2) NOT NULL DEFAULT " . self::DEFAULT_FEE_PCT . " AFTER stripe_account_id" );
        }
        update_option( 'ynj_sc_columns_added', 1 );
    }

    /**
     * Register REST routes for Connect OAuth.
     */
    public static function register_routes() {
        $ns = 'ynj/v1';

        // GET /stripe-connect/url — Generate OAuth link for mosque admin
        register_rest_route( $ns, '/stripe-connect/url', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'get_connect_url' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ] );

        // GET /stripe-connect/callback — OAuth callback from Stripe
        register_rest_route( $ns, '/stripe-connect/callback', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'handle_callback' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /stripe-connect/disconnect — Remove Stripe from mosque
        register_rest_route( $ns, '/stripe-connect/disconnect', [
            'methods'  => 'POST',
            'callback' => [ __CLASS__, 'disconnect' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ] );

        // GET /stripe-connect/status — Check if mosque has Stripe connected
        register_rest_route( $ns, '/stripe-connect/status', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'get_status' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ] );
    }

    /**
     * Generate Stripe OAuth URL for mosque to connect their account.
     */
    public static function get_connect_url( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'mosque_id' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id required' ], 400 );

        $client_id = get_option( 'ynj_stripe_connect_client_id', '' );
        if ( ! $client_id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe Connect not configured. Set ynj_stripe_connect_client_id in settings.' ], 500 );

        $redirect = rest_url( 'ynj/v1/stripe-connect/callback' );
        $state = base64_encode( wp_json_encode( [ 'mosque_id' => $mosque_id, 'nonce' => wp_create_nonce( 'ynj_sc_' . $mosque_id ) ] ) );

        $url = 'https://connect.stripe.com/oauth/authorize?' . http_build_query( [
            'response_type' => 'code',
            'client_id'     => $client_id,
            'scope'         => 'read_write',
            'redirect_uri'  => $redirect,
            'state'         => $state,
            'stripe_user[business_type]' => 'non_profit',
        ] );

        return new \WP_REST_Response( [ 'ok' => true, 'url' => $url ] );
    }

    /**
     * Handle OAuth callback from Stripe.
     */
    public static function handle_callback( \WP_REST_Request $request ) {
        $code  = sanitize_text_field( $request->get_param( 'code' ) );
        $state = sanitize_text_field( $request->get_param( 'state' ) );
        $error = sanitize_text_field( $request->get_param( 'error' ) );

        if ( $error ) {
            wp_safe_redirect( home_url( '/dashboard#/settings?stripe=error&msg=' . urlencode( $error ) ) );
            exit;
        }

        $state_data = json_decode( base64_decode( $state ), true );
        $mosque_id  = (int) ( $state_data['mosque_id'] ?? 0 );

        if ( ! $mosque_id || ! $code ) {
            wp_safe_redirect( home_url( '/dashboard#/settings?stripe=error&msg=invalid_state' ) );
            exit;
        }

        // Exchange code for account ID
        YNJ_Stripe::init();
        try {
            $response = \Stripe\OAuth::token( [
                'grant_type' => 'authorization_code',
                'code'       => $code,
            ] );

            $account_id = $response->stripe_user_id ?? '';
            if ( ! $account_id ) throw new \Exception( 'No account ID returned' );

            // Save on mosque
            global $wpdb;
            $wpdb->update( YNJ_DB::table( 'mosques' ), [
                'stripe_account_id' => $account_id,
            ], [ 'id' => $mosque_id ] );

            error_log( "[YNJ Stripe Connect] Mosque #$mosque_id connected: $account_id" );

            wp_safe_redirect( home_url( '/dashboard#/settings?stripe=connected' ) );
            exit;

        } catch ( \Exception $e ) {
            error_log( '[YNJ Stripe Connect] OAuth error: ' . $e->getMessage() );
            wp_safe_redirect( home_url( '/dashboard#/settings?stripe=error&msg=' . urlencode( $e->getMessage() ) ) );
            exit;
        }
    }

    /**
     * Disconnect Stripe from a mosque.
     */
    public static function disconnect( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'mosque_id' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false ], 400 );

        global $wpdb;
        $wpdb->update( YNJ_DB::table( 'mosques' ), [ 'stripe_account_id' => '' ], [ 'id' => $mosque_id ] );

        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    /**
     * Get Stripe Connect status for a mosque.
     */
    public static function get_status( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'mosque_id' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false ], 400 );

        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT stripe_account_id, platform_fee_pct FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) );

        return new \WP_REST_Response( [
            'ok'        => true,
            'connected' => ! empty( $mosque->stripe_account_id ),
            'account_id' => $mosque->stripe_account_id ?? '',
            'fee_pct'   => (float) ( $mosque->platform_fee_pct ?? self::DEFAULT_FEE_PCT ),
        ] );
    }

    /**
     * Get the Stripe account ID for a mosque (or empty string for master account).
     */
    public static function get_account_id( $mosque_id ) {
        if ( ! $mosque_id ) return '';
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT stripe_account_id FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) ) ?: '';
    }

    /**
     * Get platform fee percentage for a mosque.
     */
    public static function get_fee_pct( $mosque_id ) {
        if ( ! $mosque_id ) return self::DEFAULT_FEE_PCT;
        global $wpdb;
        $pct = $wpdb->get_var( $wpdb->prepare(
            "SELECT platform_fee_pct FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) );
        return $pct !== null ? (float) $pct : self::DEFAULT_FEE_PCT;
    }

    /**
     * Calculate application fee in pence.
     */
    public static function calc_fee( $total_pence, $mosque_id ) {
        $pct = self::get_fee_pct( $mosque_id );
        return (int) round( $total_pence * $pct / 100 );
    }

    /**
     * Filter: inject Connect params into PaymentIntent/Subscription creation.
     *
     * Usage in UC API:
     *   $params = apply_filters( 'ynj_payment_params', $params, $mosque_id, $total_pence );
     */
    public static function inject_connect_params( $params, $mosque_id, $total_pence ) {
        $account_id = self::get_account_id( $mosque_id );
        if ( ! $account_id ) return $params; // No Connect — use master account

        $fee = self::calc_fee( $total_pence, $mosque_id );

        $params['application_fee_amount'] = $fee;
        $params['transfer_data'] = [ 'destination' => $account_id ];

        return $params;
    }
}
