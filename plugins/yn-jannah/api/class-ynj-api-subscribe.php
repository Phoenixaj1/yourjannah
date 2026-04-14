<?php
/**
 * YourJannah — REST API: Subscriber endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Subscribe {

    const NS = 'ynj/v1';

    /**
     * Register subscriber routes.
     */
    public static function register() {

        // POST /subscribe
        register_rest_route( self::NS, '/subscribe', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'subscribe' ],
            'permission_callback' => '__return_true',
        ]);

        // POST /unsubscribe
        register_rest_route( self::NS, '/unsubscribe', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'unsubscribe' ],
            'permission_callback' => '__return_true',
        ]);
    }

    // ================================================================
    // HANDLERS
    // ================================================================

    /**
     * POST /subscribe — Subscribe to a mosque. Rate limited 3/min.
     * Uses INSERT IGNORE for unique mosque+email constraint.
     */
    public static function subscribe( \WP_REST_Request $request ) {
        $ip = self::get_ip();
        if ( ! self::rate_limit( 'subscribe_' . $ip, 3 ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Too many requests. Please wait.' ], 429 );
        }

        $data = $request->get_json_params();

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        $email     = sanitize_email( $data['email'] ?? '' );

        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id is required.' ], 400 );
        }

        if ( ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Valid email is required.' ], 400 );
        }

        // Verify mosque exists
        global $wpdb;
        $mosque_table = YNJ_DB::table( 'mosques' );
        $mosque_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $mosque_table WHERE id = %d AND status = 'active'",
            $mosque_id
        ) );

        if ( ! $mosque_exists ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $table = YNJ_DB::table( 'subscribers' );

        // Check if already subscribed (and possibly re-subscribe)
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM $table WHERE mosque_id = %d AND email = %s",
            $mosque_id, $email
        ) );

        if ( $existing ) {
            if ( $existing->status === 'active' ) {
                // Already subscribed — update push keys if provided
                $push_update = [];
                if ( ! empty( $data['push_endpoint'] ) ) $push_update['push_endpoint'] = sanitize_text_field( $data['push_endpoint'] );
                if ( ! empty( $data['push_p256dh'] ) )   $push_update['push_p256dh']   = sanitize_text_field( $data['push_p256dh'] );
                if ( ! empty( $data['push_auth'] ) )      $push_update['push_auth']     = sanitize_text_field( $data['push_auth'] );
                if ( ! empty( $push_update ) ) {
                    $push_update['last_active_at'] = current_time( 'mysql' );
                    $wpdb->update( $table, $push_update, [ 'id' => $existing->id ] );
                }

                return new \WP_REST_Response( [
                    'ok'      => true,
                    'message' => 'Already subscribed. Push details updated.',
                ] );
            }

            // Re-subscribe
            $wpdb->update( $table, [
                'status'         => 'active',
                'name'           => sanitize_text_field( $data['name'] ?? '' ),
                'push_endpoint'  => sanitize_text_field( $data['push_endpoint'] ?? '' ),
                'push_p256dh'    => sanitize_text_field( $data['push_p256dh'] ?? '' ),
                'push_auth'      => sanitize_text_field( $data['push_auth'] ?? '' ),
                'subscribed_at'  => current_time( 'mysql' ),
                'last_active_at' => current_time( 'mysql' ),
            ], [ 'id' => $existing->id ] );

            return new \WP_REST_Response( [
                'ok'      => true,
                'message' => 'Re-subscribed successfully.',
            ], 201 );
        }

        // New subscription — INSERT IGNORE handles race conditions
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $table
             (mosque_id, email, name, push_endpoint, push_p256dh, push_auth, status, subscribed_at, last_active_at)
             VALUES (%d, %s, %s, %s, %s, %s, 'active', %s, %s)",
            $mosque_id,
            $email,
            sanitize_text_field( $data['name'] ?? '' ),
            sanitize_text_field( $data['push_endpoint'] ?? '' ),
            sanitize_text_field( $data['push_p256dh'] ?? '' ),
            sanitize_text_field( $data['push_auth'] ?? '' ),
            current_time( 'mysql' ),
            current_time( 'mysql' )
        ) );

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Subscribed successfully.',
        ], 201 );
    }

    /**
     * POST /unsubscribe — Unsubscribe from a mosque.
     */
    public static function unsubscribe( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $email     = sanitize_email( $data['email'] ?? '' );
        $mosque_id = absint( $data['mosque_id'] ?? 0 );

        if ( ! is_email( $email ) || ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'email and mosque_id are required.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'subscribers' );

        $updated = $wpdb->update(
            $table,
            [ 'status' => 'unsubscribed' ],
            [ 'mosque_id' => $mosque_id, 'email' => $email ]
        );

        if ( $updated === false || $updated === 0 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Subscriber not found.' ], 404 );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Unsubscribed successfully.',
        ] );
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private static function get_ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            return trim( $parts[0] );
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private static function rate_limit( $key, $max_per_minute ) {
        $transient = 'ynj_rl_' . md5( $key );
        $count     = (int) get_transient( $transient );

        if ( $count >= $max_per_minute ) {
            return false;
        }

        set_transient( $transient, $count + 1, 60 );
        return true;
    }
}
