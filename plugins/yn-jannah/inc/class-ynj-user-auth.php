<?php
/**
 * YNJ_User_Auth — Congregation member authentication.
 *
 * Separate from mosque admin auth (YNJ_Auth).
 * Users can register, login, view bookings, save preferences.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_User_Auth {

    /**
     * Register a new congregation member.
     */
    public static function register( $data ) {
        $name     = sanitize_text_field( $data['name'] ?? '' );
        $email    = sanitize_email( $data['email'] ?? '' );
        $password = $data['password'] ?? '';
        $phone    = sanitize_text_field( $data['phone'] ?? '' );

        if ( empty( $name ) || ! is_email( $email ) ) {
            return [ 'ok' => false, 'error' => 'Name and valid email are required.' ];
        }

        if ( strlen( $password ) < 6 ) {
            return [ 'ok' => false, 'error' => 'Password must be at least 6 characters.' ];
        }

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        // Check duplicate
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE email = %s", $email
        ) );

        if ( $exists ) {
            return [ 'ok' => false, 'error' => 'An account with this email already exists.' ];
        }

        // Generate token
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );

        $wpdb->insert( $table, [
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'password_hash' => password_hash( $password, PASSWORD_DEFAULT ),
            'token_hash'    => $token_hash,
            'token_last_used' => current_time( 'mysql', true ),
            'status'        => 'active',
        ] );

        $user_id = (int) $wpdb->insert_id;
        if ( ! $user_id ) {
            return [ 'ok' => false, 'error' => 'Registration failed.' ];
        }

        return [
            'ok'      => true,
            'token'   => $token,
            'user_id' => $user_id,
        ];
    }

    /**
     * Login an existing user.
     */
    public static function login( $data ) {
        $email    = sanitize_email( $data['email'] ?? '' );
        $password = $data['password'] ?? '';

        if ( ! is_email( $email ) || empty( $password ) ) {
            return [ 'ok' => false, 'error' => 'Email and password are required.' ];
        }

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND status = 'active' LIMIT 1", $email
        ) );

        if ( ! $user || ! password_verify( $password, $user->password_hash ) ) {
            return [ 'ok' => false, 'error' => 'Invalid email or password.' ];
        }

        // Generate new token
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );

        $wpdb->update( $table, [
            'token_hash'      => $token_hash,
            'token_last_used' => current_time( 'mysql', true ),
        ], [ 'id' => $user->id ] );

        return [
            'ok'    => true,
            'token' => $token,
            'user'  => self::format_user( $user ),
        ];
    }

    /**
     * Verify a user bearer token.
     */
    public static function verify_token( $token ) {
        if ( empty( $token ) ) return null;

        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE token_hash = %s AND status = 'active' LIMIT 1",
            $token_hash
        ) );

        if ( ! $user ) return null;

        // Update last used
        $wpdb->update( $table, [
            'token_last_used' => current_time( 'mysql', true ),
        ], [ 'id' => $user->id ] );

        return $user;
    }

    /**
     * Permission callback for user-authenticated routes.
     */
    public static function user_check( \WP_REST_Request $request ) {
        $header = $request->get_header( 'authorization' );
        if ( ! $header || ! preg_match( '/^Bearer\s+(.+)$/i', $header, $matches ) ) {
            return false;
        }

        $user = self::verify_token( $matches[1] );
        if ( ! $user ) return false;

        $request->set_param( '_ynj_user', $user );
        return true;
    }

    /**
     * Format a user for API response (no sensitive fields).
     */
    public static function format_user( $user ) {
        return [
            'id'                   => (int) $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'phone'                => $user->phone,
            'favourite_mosque_id'  => $user->favourite_mosque_id ? (int) $user->favourite_mosque_id : null,
            'travel_mode'          => $user->travel_mode,
            'travel_minutes'       => (int) $user->travel_minutes,
            'alert_before_minutes'  => (int) $user->alert_before_minutes,
            'verified_congregation' => (bool) ( $user->verified_congregation ?? 0 ),
            'verified_at'           => $user->verified_at ?? null,
            'created_at'            => $user->created_at,
        ];
    }
}
