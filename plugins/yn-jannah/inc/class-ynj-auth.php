<?php
/**
 * YourJannah Authentication
 *
 * Handles mosque admin registration, login, and bearer-token authentication.
 *
 * @package YourJannah
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_Auth {

    /**
     * Register a new mosque and admin account.
     *
     * @param  array $data {
     *     @type string $name     Mosque name.
     *     @type string $email    Admin email.
     *     @type string $password Admin password.
     *     @type string $postcode Mosque postcode.
     *     @type string $city     Mosque city.
     *     @type string $address  Mosque address.
     * }
     * @return array|WP_Error  {ok, token, mosque_id, slug} on success.
     */
    public static function register( $data ) {
        global $wpdb;

        $name     = sanitize_text_field( $data['name'] ?? '' );
        $email    = sanitize_email( $data['email'] ?? '' );
        $password = $data['password'] ?? '';
        $postcode = sanitize_text_field( $data['postcode'] ?? '' );
        $city     = sanitize_text_field( $data['city'] ?? '' );
        $address  = sanitize_text_field( $data['address'] ?? '' );

        // Validate required fields.
        if ( empty( $name ) || empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', 'Name, email and password are required.', [ 'status' => 400 ] );
        }

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Please provide a valid email address.', [ 'status' => 400 ] );
        }

        if ( strlen( $password ) < 8 ) {
            return new WP_Error( 'weak_password', 'Password must be at least 8 characters.', [ 'status' => 400 ] );
        }

        // Rate limit registrations: 5 per minute per IP.
        if ( self::rate_limit( 'ynj_reg_' . self::get_ip(), 5 ) ) {
            return new WP_Error( 'rate_limited', 'Too many requests. Please try again later.', [ 'status' => 429 ] );
        }

        // Check for duplicate email.
        $table = YNJ_DB::table( 'mosques' );
        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE admin_email = %s LIMIT 1", $email ) // phpcs:ignore WordPress.DB.PreparedSQL
        );

        if ( $exists ) {
            return new WP_Error( 'email_exists', 'An account with this email already exists.', [ 'status' => 409 ] );
        }

        // Generate slug.
        $slug = sanitize_title( $name );
        $slug_exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug ) // phpcs:ignore WordPress.DB.PreparedSQL
        );

        if ( $slug_exists ) {
            $slug .= '-' . wp_rand( 1000, 9999 );
        }

        // Geocode postcode via postcodes.io.
        $latitude  = null;
        $longitude = null;

        if ( ! empty( $postcode ) ) {
            $geo = self::geocode_postcode( $postcode );
            if ( $geo ) {
                $latitude  = $geo['latitude'];
                $longitude = $geo['longitude'];
            }
        }

        // Hash password.
        $password_hash = password_hash( $password, PASSWORD_DEFAULT );

        // Insert mosque.
        $inserted = $wpdb->insert(
            $table,
            [
                'name'                => $name,
                'slug'                => $slug,
                'address'             => $address,
                'city'                => $city,
                'postcode'            => $postcode,
                'latitude'            => $latitude,
                'longitude'           => $longitude,
                'admin_email'         => $email,
                'admin_password_hash' => $password_hash,
                'status'              => 'active',
                'created_at'          => current_time( 'mysql', true ),
                'updated_at'          => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_error', 'Failed to create account. Please try again.', [ 'status' => 500 ] );
        }

        $mosque_id = (int) $wpdb->insert_id;

        // Generate bearer token.
        $token = self::generate_token( $mosque_id );

        return [
            'ok'        => true,
            'token'     => $token,
            'mosque_id' => $mosque_id,
            'slug'      => $slug,
        ];
    }

    /**
     * Authenticate a mosque admin.
     *
     * @param  string $email    Admin email.
     * @param  string $password Admin password.
     * @return array|WP_Error   {ok, token, mosque_id} on success.
     */
    public static function login( $email, $password ) {
        global $wpdb;

        $email = sanitize_email( $email );

        if ( empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', 'Email and password are required.', [ 'status' => 400 ] );
        }

        // Rate limit logins: 10 per minute per IP.
        if ( self::rate_limit( 'ynj_login_' . self::get_ip(), 10 ) ) {
            return new WP_Error( 'rate_limited', 'Too many login attempts. Please try again later.', [ 'status' => 429 ] );
        }

        $table  = YNJ_DB::table( 'mosques' );
        $mosque = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, admin_password_hash, status FROM {$table} WHERE admin_email = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
                $email
            )
        );

        if ( ! $mosque ) {
            return new WP_Error( 'invalid_credentials', 'Invalid email or password.', [ 'status' => 401 ] );
        }

        if ( $mosque->status !== 'active' ) {
            return new WP_Error( 'account_inactive', 'This account is not active.', [ 'status' => 403 ] );
        }

        if ( ! password_verify( $password, $mosque->admin_password_hash ) ) {
            return new WP_Error( 'invalid_credentials', 'Invalid email or password.', [ 'status' => 401 ] );
        }

        // Generate fresh token.
        $token = self::generate_token( (int) $mosque->id );

        return [
            'ok'        => true,
            'token'     => $token,
            'mosque_id' => (int) $mosque->id,
        ];
    }

    /**
     * Generate a bearer token for a mosque and store its hash.
     *
     * @param  int    $mosque_id  Mosque ID.
     * @return string             Plain-text bearer token.
     */
    public static function generate_token( $mosque_id ) {
        global $wpdb;

        // Generate 64-character random token.
        $token = bin2hex( random_bytes( 32 ) );
        $hash  = self::hash_token( $token );

        $table = YNJ_DB::table( 'mosques' );
        $wpdb->update(
            $table,
            [
                'admin_token_hash'      => $hash,
                'admin_token_last_used' => current_time( 'mysql', true ),
            ],
            [ 'id' => $mosque_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return $token;
    }

    /**
     * Verify a bearer token from the Authorization header.
     *
     * @param  WP_REST_Request $request  REST request object.
     * @return object|false              Mosque row on success, false on failure.
     */
    public static function verify_bearer( $request ) {
        global $wpdb;

        $auth_header = $request->get_header( 'Authorization' );

        if ( empty( $auth_header ) ) {
            return false;
        }

        // Extract token from "Bearer <token>".
        if ( stripos( $auth_header, 'Bearer ' ) !== 0 ) {
            return false;
        }

        $token = substr( $auth_header, 7 );

        if ( empty( $token ) || strlen( $token ) !== 64 ) {
            return false;
        }

        $hash  = self::hash_token( $token );
        $table = YNJ_DB::table( 'mosques' );

        $mosque = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE admin_token_hash = %s AND status = 'active' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
                $hash
            )
        );

        if ( ! $mosque ) {
            return false;
        }

        // Update last-used timestamp.
        $wpdb->update(
            $table,
            [ 'admin_token_last_used' => current_time( 'mysql', true ) ],
            [ 'id' => $mosque->id ],
            [ '%s' ],
            [ '%d' ]
        );

        return $mosque;
    }

    /**
     * Permission callback for REST routes requiring mosque authentication.
     *
     * Verifies the bearer token and injects the mosque object into the request
     * as `_ynj_mosque` for downstream handlers.
     *
     * @param  WP_REST_Request $request  REST request object.
     * @return bool|WP_Error
     */
    public static function bearer_check( $request ) {
        // Try old custom token first
        $mosque = self::verify_bearer( $request );

        if ( $mosque ) {
            $request->set_param( '_ynj_mosque', $mosque );
            return true;
        }

        // Fallback: try WP auth (application passwords, cookie+nonce, etc.)
        if ( class_exists( 'YNJ_WP_Auth' ) ) {
            return YNJ_WP_Auth::mosque_admin_check( $request );
        }

        return new WP_Error(
            'ynj_unauthorized',
            'Invalid or missing authentication token.',
            [ 'status' => 401 ]
        );
    }

    /**
     * Transient-based rate limiter.
     *
     * @param  string $key         Unique key for the action (e.g. 'ynj_login_127.0.0.1').
     * @param  int    $max_per_min Maximum requests allowed per minute.
     * @return bool                True if rate limit exceeded, false if allowed.
     */
    public static function rate_limit( $key, $max_per_min = 30 ) {
        $transient_key = 'ynj_rl_' . md5( $key );
        $count         = (int) get_transient( $transient_key );

        if ( $count >= $max_per_min ) {
            return true;
        }

        if ( $count === 0 ) {
            set_transient( $transient_key, 1, 60 );
        } else {
            set_transient( $transient_key, $count + 1, 60 );
        }

        return false;
    }

    /**
     * Get the client IP address.
     *
     * Checks X-Forwarded-For first for proxied environments.
     *
     * @return string
     */
    public static function get_ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            return trim( $ips[0] );
        }

        if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
        }

        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    }

    /**
     * Hash a bearer token using HMAC-SHA256.
     *
     * @param  string $token  Plain-text token.
     * @return string         64-character hex hash.
     */
    public static function hash_token( $token ) {
        return hash_hmac( 'sha256', $token, 'ynj_salt_2024' );
    }

    /**
     * Geocode a UK postcode using postcodes.io.
     *
     * @param  string     $postcode  UK postcode.
     * @return array|null            {latitude, longitude} or null on failure.
     */
    private static function geocode_postcode( $postcode ) {
        $postcode = rawurlencode( strtoupper( trim( $postcode ) ) );
        $url      = "https://api.postcodes.io/postcodes/{$postcode}";

        $response = wp_remote_get( $url, [
            'timeout' => 5,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['result']['latitude'] ) || empty( $body['result']['longitude'] ) ) {
            return null;
        }

        return [
            'latitude'  => (float) $body['result']['latitude'],
            'longitude' => (float) $body['result']['longitude'],
        ];
    }
}
