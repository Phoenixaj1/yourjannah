<?php
/**
 * YourJannah — REST API: Congregation member endpoints.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_User {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /auth/check-email — check if email exists (for unified auth flow)
        register_rest_route( self::NS, '/auth/check-email', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_check_email' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /auth/set-pin — set PIN for existing users migrating from password
        register_rest_route( self::NS, '/auth/set-pin', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_set_pin' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /auth/register — user signup
        register_rest_route( self::NS, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /auth/login — user login
        register_rest_route( self::NS, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /auth/me — get user profile
        register_rest_route( self::NS, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_profile' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // PUT /auth/me — update profile
        register_rest_route( self::NS, '/auth/me', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update_profile' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // GET /auth/bookings — my bookings
        register_rest_route( self::NS, '/auth/bookings', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'my_bookings' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // POST /auth/verify-congregation — GPS verify as mosque member
        register_rest_route( self::NS, '/auth/verify-congregation', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'verify_congregation' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // POST /auth/push — save push subscription
        register_rest_route( self::NS, '/auth/push', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'save_push' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // POST /auth/forgot-password — request reset email
        register_rest_route( self::NS, '/auth/forgot-password', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'forgot_password' ],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
            ],
        ] );

        // POST /auth/reset-password — reset with key
        register_rest_route( self::NS, '/auth/reset-password', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'reset_password' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * POST /auth/check-email — Does this email already have an account?
     * Returns {exists: true/false} so the frontend knows whether to show
     * "Enter PIN" (existing) or "Create PIN" (new user).
     */
    public static function handle_check_email( \WP_REST_Request $request ) {
        $data  = $request->get_json_params();
        $email = sanitize_email( $data['email'] ?? '' );

        if ( ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Valid email required.' ], 400 );
        }

        global $wpdb;
        $exists = false;

        // Check ynj_users table
        $has_pin = false;
        if ( class_exists( 'YNJ_DB' ) ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, password_hash FROM " . YNJ_DB::table( 'users' ) . " WHERE email = %s AND status = 'active' LIMIT 1", $email
            ) );
            $exists = (bool) $row;
            // PIN hashes are short bcrypt of 4-6 digit numbers; old passwords start with 'YJ_' or are longer
            // We detect PIN by checking if the hash verifies against a 4-6 digit pattern
            // Simpler: check if a pin_set flag transient exists, or just check hash length
            if ( $row && $row->password_hash ) {
                // If password_hash exists, user has SOME credential set
                // We'll consider it a PIN if it was set via the PIN flow (stored in usermeta)
                $has_pin = (bool) get_user_meta(
                    (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_email = %s LIMIT 1", $email ) ),
                    'ynj_has_pin', true
                );
            }
        }

        // Also check WP users
        if ( ! $exists ) {
            $exists = (bool) get_user_by( 'email', $email );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'exists' => $exists, 'has_pin' => $has_pin ] );
    }

    /**
     * POST /auth/set-pin — Set PIN for existing user (migrating from password).
     * Verifies email exists, sets new PIN hash, returns token.
     */
    public static function handle_set_pin( \WP_REST_Request $request ) {
        $data  = $request->get_json_params();
        $email = sanitize_email( $data['email'] ?? '' );
        $pin   = $data['pin'] ?? '';

        if ( ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Valid email required.' ], 400 );
        }

        $pin = preg_replace( '/\D/', '', $pin );
        if ( strlen( $pin ) < 4 || strlen( $pin ) > 6 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'PIN must be 4-6 digits.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'users' );
        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND status = 'active' LIMIT 1", $email
        ) );

        if ( ! $user ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Account not found.' ], 404 );
        }

        // Update password_hash with PIN hash
        $pin_hash = password_hash( $pin, PASSWORD_DEFAULT );
        $token = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );

        $wpdb->update( $table, [
            'password_hash' => $pin_hash,
            'token_hash'    => $token_hash,
            'token_last_used' => current_time( 'mysql', true ),
        ], [ 'id' => $user->id ] );

        // Mark as having PIN set (for check-email detection)
        $wp_user = get_user_by( 'email', $email );
        if ( $wp_user ) {
            update_user_meta( $wp_user->ID, 'ynj_has_pin', 1 );
            // Also update WP password so WP-native auth works too
            wp_set_password( $pin, $wp_user->ID );
            // Set session
            wp_set_auth_cookie( $wp_user->ID, true );
        }

        return new \WP_REST_Response( [
            'ok'         => true,
            'token'      => $token,
            'wp_user_id' => $wp_user ? $wp_user->ID : 0,
            'message'    => 'PIN set successfully.',
        ] );
    }

    public static function handle_register( \WP_REST_Request $request ) {
        $data   = $request->get_json_params();
        $result = YNJ_WP_Auth::register_congregation( $data );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], $status );
        }

        // Mark as having PIN if registered with one
        if ( ! empty( $data['pin'] ) ) {
            $wp_user = get_user_by( 'email', sanitize_email( $data['email'] ?? '' ) );
            if ( $wp_user ) update_user_meta( $wp_user->ID, 'ynj_has_pin', 1 );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'token'   => $result['token'],
            'user'    => [
                'id'   => $result['user_id'],
                'name' => sanitize_text_field( $data['name'] ?? '' ),
                'email' => sanitize_email( $data['email'] ?? '' ),
            ],
            'wp_user_id' => $result['wp_user_id'] ?? 0,
            'message' => 'Account created. Welcome to YourJannah!',
        ], 201 );
    }

    public static function handle_login( \WP_REST_Request $request ) {
        $data   = $request->get_json_params();
        // Accept PIN or password
        $credential = $data['pin'] ?? $data['password'] ?? '';
        $result = YNJ_WP_Auth::login_congregation( $data['email'] ?? '', $credential );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], $status );
        }

        return new \WP_REST_Response( [
            'ok'    => true,
            'token' => $result['token'],
            'user'  => $result['user'],
        ] );
    }

    public static function get_profile( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );

        // Get favourite mosque name
        $mosque_name = null;
        if ( $user->favourite_mosque_id ) {
            global $wpdb;
            $mosque_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d",
                $user->favourite_mosque_id
            ) );
        }

        $profile = YNJ_User_Auth::format_user( $user );
        $profile['favourite_mosque_name'] = $mosque_name;

        return new \WP_REST_Response( [ 'ok' => true, 'user' => $profile ] );
    }

    public static function update_profile( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        $update = [];
        if ( isset( $data['name'] ) )                 $update['name']                 = sanitize_text_field( $data['name'] );
        if ( isset( $data['phone'] ) )                $update['phone']                = sanitize_text_field( $data['phone'] );
        if ( isset( $data['favourite_mosque_id'] ) )  $update['favourite_mosque_id']  = absint( $data['favourite_mosque_id'] ) ?: null;
        if ( isset( $data['travel_mode'] ) )          $update['travel_mode']          = in_array( $data['travel_mode'], [ 'walk', 'drive' ] ) ? $data['travel_mode'] : 'walk';
        if ( isset( $data['travel_minutes'] ) )       $update['travel_minutes']       = max( 0, min( 120, absint( $data['travel_minutes'] ) ) );
        if ( isset( $data['alert_before_minutes'] ) ) $update['alert_before_minutes'] = max( 5, min( 60, absint( $data['alert_before_minutes'] ) ) );

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => $user->id ] );
        }

        // Refresh
        $fresh = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $user->id ) );

        return new \WP_REST_Response( [
            'ok'   => true,
            'user' => YNJ_User_Auth::format_user( $fresh ),
            'message' => 'Profile updated.',
        ] );
    }

    public static function my_bookings( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );

        global $wpdb;
        $book_table   = YNJ_DB::table( 'bookings' );
        $event_table  = YNJ_DB::table( 'events' );
        $room_table   = YNJ_DB::table( 'rooms' );
        $mosque_table = YNJ_DB::table( 'mosques' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*,
                    e.title AS event_title, e.event_date AS event_date_raw, e.start_time AS event_start,
                    r.name AS room_name,
                    m.name AS mosque_name, m.slug AS mosque_slug
             FROM $book_table b
             LEFT JOIN $event_table e ON e.id = b.event_id
             LEFT JOIN $room_table r ON r.id = b.room_id
             LEFT JOIN $mosque_table m ON m.id = b.mosque_id
             WHERE b.user_email = %s
             ORDER BY b.created_at DESC
             LIMIT 50",
            $user->email
        ) );

        $bookings = array_map( function( $b ) {
            return [
                'id'           => (int) $b->id,
                'type'         => $b->event_id ? 'event' : 'room',
                'event_title'  => $b->event_title,
                'room_name'    => $b->room_name,
                'mosque_name'  => $b->mosque_name,
                'mosque_slug'  => $b->mosque_slug,
                'booking_date' => $b->booking_date,
                'start_time'   => $b->start_time,
                'end_time'     => $b->end_time,
                'status'       => $b->status,
                'notes'        => $b->notes,
                'created_at'   => $b->created_at,
            ];
        }, $results );

        return new \WP_REST_Response( [ 'ok' => true, 'bookings' => $bookings ] );
    }

    /**
     * POST /auth/verify-congregation — GPS-verify user is near their favourite mosque.
     * Must be within 500m of the mosque to verify.
     */
    public static function verify_congregation( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        $lat = (float) ( $data['lat'] ?? 0 );
        $lng = (float) ( $data['lng'] ?? 0 );

        if ( ! $lat || ! $lng ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'GPS coordinates required.' ], 400 );
        }

        $mosque_id = $user->favourite_mosque_id;
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Set a favourite mosque first.' ], 400 );
        }

        // Get mosque coordinates
        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT latitude, longitude, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d",
            $mosque_id
        ) );

        if ( ! $mosque || ! $mosque->latitude ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque location not available.' ], 400 );
        }

        // Calculate distance using haversine
        $earth_r = 6371000; // metres
        $dLat = deg2rad( $lat - (float) $mosque->latitude );
        $dLng = deg2rad( $lng - (float) $mosque->longitude );
        $a = sin( $dLat / 2 ) * sin( $dLat / 2 ) +
            cos( deg2rad( (float) $mosque->latitude ) ) * cos( deg2rad( $lat ) ) *
            sin( $dLng / 2 ) * sin( $dLng / 2 );
        $distance_m = $earth_r * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        $distance_km = $distance_m / 1000;
        $distance_mi = $distance_km * 0.621;

        // Verification tiers based on distance
        // Within 10 miles = verified local congregation
        // Within 50 miles = verified regional
        // Beyond 50 miles = not verified (too far to be a regular attendee)
        $verified_level = 0;
        if ( $distance_mi <= 10 ) {
            $verified_level = 2; // confirmed local
        } elseif ( $distance_mi <= 50 ) {
            $verified_level = 1; // regional
        }

        if ( $verified_level === 0 ) {
            return new \WP_REST_Response( [
                'ok'       => false,
                'error'    => 'You appear to be ' . round( $distance_mi ) . ' miles away. Congregation verification requires being within 10 miles of the mosque.',
                'distance_miles' => round( $distance_mi, 1 ),
            ], 400 );
        }

        // Mark as verified
        $users_table = YNJ_DB::table( 'users' );
        $wpdb->update( $users_table, [
            'verified_congregation' => $verified_level,
            'verified_at'           => current_time( 'mysql', true ),
            'verified_lat'          => $lat,
            'verified_lng'          => $lng,
        ], [ 'id' => $user->id ] );

        $level_label = $verified_level === 2 ? 'Local congregation member' : 'Regional member';

        return new \WP_REST_Response( [
            'ok'             => true,
            'verified'       => true,
            'verified_level' => $verified_level,
            'mosque'         => $mosque->name,
            'distance_miles' => round( $distance_mi, 1 ),
            'message'        => "Verified as $level_label of " . $mosque->name,
        ] );
    }

    public static function forgot_password( \WP_REST_Request $request ) {
        $data  = $request->get_json_params();
        $email = sanitize_email( $data['email'] ?? '' );

        if ( ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Valid email required.' ], 400 );
        }

        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            // Don't reveal if email exists
            return new \WP_REST_Response( [ 'ok' => true, 'message' => 'If an account exists, a reset link has been sent.' ] );
        }

        // Generate reset key
        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Could not generate reset link.' ], 500 );
        }

        // Send email
        $reset_url = home_url( '/reset-password?key=' . $key . '&email=' . rawurlencode( $email ) );
        $subject   = 'Reset Your Password — YourJannah';
        $body      = '<div style="font-family:Inter,system-ui,sans-serif;max-width:500px;margin:0 auto;padding:20px;">'
            . '<div style="background:linear-gradient(135deg,#0a1628,#00ADEF);color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center;">'
            . '<h2 style="margin:0;">YourJannah</h2></div>'
            . '<div style="background:#fff;border:1px solid #e5e5e5;border-top:none;padding:24px;border-radius:0 0 12px 12px;">'
            . '<h3>Password Reset</h3>'
            . '<p>Click the button below to reset your password:</p>'
            . '<a href="' . esc_url( $reset_url ) . '" style="display:inline-block;background:#00ADEF;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;margin:16px 0;">Reset Password</a>'
            . '<p style="font-size:13px;color:#999;">This link expires in 24 hours. If you didn\'t request this, ignore this email.</p>'
            . '</div></div>';

        $html_type = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $html_type );
        wp_mail( $email, $subject, $body );
        remove_filter( 'wp_mail_content_type', $html_type );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'If an account exists, a reset link has been sent.' ] );
    }

    public static function reset_password( \WP_REST_Request $request ) {
        $data     = $request->get_json_params();
        $email    = sanitize_email( $data['email'] ?? '' );
        $key      = sanitize_text_field( $data['key'] ?? '' );
        $password = $data['password'] ?? '';

        if ( ! $email || ! $key || strlen( $password ) < 6 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Email, key, and new password (6+ chars) required.' ], 400 );
        }

        $wp_user = get_user_by( 'email', $email );
        $login   = $wp_user ? $wp_user->user_login : '';
        $user    = check_password_reset_key( $key, $login );

        if ( is_wp_error( $user ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid or expired reset link.' ], 400 );
        }

        wp_set_password( $password, $user->ID );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Password reset. You can now sign in.' ] );
    }

    public static function save_push( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        $wpdb->update( $table, [
            'push_endpoint' => sanitize_text_field( $data['endpoint'] ?? '' ),
            'push_p256dh'   => sanitize_text_field( $data['p256dh'] ?? '' ),
            'push_auth'     => sanitize_text_field( $data['auth'] ?? '' ),
        ], [ 'id' => $user->id ] );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Push subscription saved.' ] );
    }
}
