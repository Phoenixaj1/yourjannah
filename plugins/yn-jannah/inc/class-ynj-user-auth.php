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
     * Accepts either PIN (4-6 digits, preferred) or legacy password.
     */
    public static function register( $data ) {
        $name     = sanitize_text_field( $data['name'] ?? '' );
        $email    = sanitize_email( $data['email'] ?? '' );
        $pin      = $data['pin'] ?? '';
        $password = $data['password'] ?? ''; // Legacy fallback
        $phone    = sanitize_text_field( $data['phone'] ?? '' );

        if ( empty( $name ) || ! is_email( $email ) ) {
            return [ 'ok' => false, 'error' => 'Name and valid email are required.' ];
        }

        // PIN-based auth (preferred)
        if ( $pin ) {
            $pin = preg_replace( '/\D/', '', $pin ); // Strip non-digits
            if ( strlen( $pin ) < 4 || strlen( $pin ) > 6 ) {
                return [ 'ok' => false, 'error' => 'PIN must be 4-6 digits.' ];
            }
            $password = $pin; // Store PIN hash in password_hash column
        } elseif ( strlen( $password ) < 4 ) {
            return [ 'ok' => false, 'error' => 'PIN must be at least 4 digits.' ];
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
        $token_hash = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );

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
     * Accepts PIN (preferred) or legacy password.
     */
    public static function login( $data ) {
        $email    = sanitize_email( $data['email'] ?? '' );
        $pin      = $data['pin'] ?? '';
        $password = $data['password'] ?? ''; // Legacy fallback

        // Accept either PIN or password
        $credential = $pin ?: $password;

        if ( ! is_email( $email ) || empty( $credential ) ) {
            return [ 'ok' => false, 'error' => 'Email and PIN are required.' ];
        }

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s AND status = 'active' LIMIT 1", $email
        ) );

        if ( ! $user || ! password_verify( $credential, $user->password_hash ) ) {
            return [ 'ok' => false, 'error' => 'Invalid email or PIN.' ];
        }

        // Generate new token
        $token      = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );

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

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        // Try both salts (new system uses 'ynj_user_salt_2024', legacy uses wp_salt)
        $salts = [ 'ynj_user_salt_2024', wp_salt( 'auth' ) ];
        $user = null;
        foreach ( $salts as $salt ) {
            $token_hash = hash_hmac( 'sha256', $token, $salt );
            $user = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table WHERE token_hash = %s AND status = 'active' LIMIT 1",
                $token_hash
            ) );
            if ( $user ) break;
        }

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
        // Try Bearer token first
        $header = $request->get_header( 'authorization' );
        if ( $header && preg_match( '/^Bearer\s+(.+)$/i', $header, $matches ) ) {
            $user = self::verify_token( $matches[1] );
            if ( $user ) {
                $request->set_param( '_ynj_user', $user );
                return true;
            }
        }

        // Fallback: WP cookie auth (for PIN-logged-in users)
        $wp_user_id = get_current_user_id();
        if ( $wp_user_id ) {
            $ynj_user_id = (int) get_user_meta( $wp_user_id, 'ynj_user_id', true );
            if ( $ynj_user_id ) {
                global $wpdb;
                $user = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d AND status = 'active'", $ynj_user_id
                ) );
                if ( $user ) {
                    $request->set_param( '_ynj_user', $user );
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Format a user for API response (no sensitive fields).
     */
    public static function format_user( $user ) {
        $data = [
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

        // Attach patron status if user has an active membership
        global $wpdb;
        $patron_table = YNJ_DB::table( 'patrons' );
        $patron = $wpdb->get_row( $wpdb->prepare(
            "SELECT tier, amount_pence, started_at, mosque_id FROM $patron_table WHERE user_id = %d AND status = 'active' ORDER BY amount_pence DESC LIMIT 1",
            $user->id
        ) );
        if ( $patron ) {
            $data['patron'] = [
                'tier'         => $patron->tier,
                'amount_pence' => (int) $patron->amount_pence,
                'mosque_id'    => (int) $patron->mosque_id,
                'started_at'   => $patron->started_at,
            ];
        }

        return $data;
    }
}
