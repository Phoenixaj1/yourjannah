<?php
/**
 * YourJannah — REST API: User subscription endpoints.
 *
 * Users can subscribe to multiple mosques with per-mosque notification preferences.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Subscriptions {

    const NS = 'ynj/v1';

    public static function register() {

        // GET /auth/subscriptions — List user's mosque subscriptions
        register_rest_route( self::NS, '/auth/subscriptions', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'list_subs' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // POST /auth/subscriptions — Subscribe to a mosque
        register_rest_route( self::NS, '/auth/subscriptions', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'subscribe' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // PUT /auth/subscriptions/(?P<mosque_id>\d+) — Update notification preferences
        register_rest_route( self::NS, '/auth/subscriptions/(?P<mosque_id>\d+)', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'update_prefs' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // DELETE /auth/subscriptions/(?P<mosque_id>\d+) — Unsubscribe from a mosque
        register_rest_route( self::NS, '/auth/subscriptions/(?P<mosque_id>\d+)', [
            'methods' => 'DELETE', 'callback' => [ __CLASS__, 'unsubscribe' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // GET /mosques/{id}/subscriber-count — Public subscriber count
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/subscriber-count', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'public_count' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ================================================================
    // LIST SUBSCRIPTIONS
    // ================================================================

    public static function list_subs( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );

        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        $mt = YNJ_DB::table( 'mosques' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city
             FROM $st s
             INNER JOIN $mt m ON m.id = s.mosque_id
             WHERE s.user_id = %d AND s.status = 'active'
             ORDER BY s.subscribed_at DESC",
            $user->id
        ) );

        $subs = array_map( function( $s ) {
            return [
                'mosque_id'            => (int) $s->mosque_id,
                'mosque_name'          => $s->mosque_name,
                'mosque_slug'          => $s->mosque_slug,
                'mosque_city'          => $s->mosque_city,
                'notify_events'        => (bool) $s->notify_events,
                'notify_classes'       => (bool) $s->notify_classes,
                'notify_announcements' => (bool) $s->notify_announcements,
                'notify_fundraising'   => (bool) $s->notify_fundraising,
                'notify_live'          => (bool) $s->notify_live,
                'subscribed_at'        => $s->subscribed_at,
            ];
        }, $rows );

        return new \WP_REST_Response( [ 'ok' => true, 'subscriptions' => $subs ] );
    }

    // ================================================================
    // SUBSCRIBE
    // ================================================================

    public static function subscribe( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );
        $d = $r->get_json_params();

        $mosque_id = absint( $d['mosque_id'] ?? 0 );
        if ( ! $mosque_id && ! empty( $d['mosque_slug'] ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $d['mosque_slug'] );
        }
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id or mosque_slug required.' ], 400 );
        }

        global $wpdb;
        $t = YNJ_DB::table( 'user_subscriptions' );

        // Check existing
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM $t WHERE user_id = %d AND mosque_id = %d",
            $user->id, $mosque_id
        ) );

        if ( $existing && $existing->status === 'active' ) {
            return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Already subscribed.', 'already' => true ] );
        }

        if ( $existing ) {
            // Reactivate
            $wpdb->update( $t, [
                'status'        => 'active',
                'subscribed_at' => current_time( 'mysql', true ),
            ], [ 'id' => $existing->id ] );
        } else {
            $wpdb->insert( $t, [
                'user_id'              => (int) $user->id,
                'mosque_id'            => $mosque_id,
                'notify_events'        => absint( $d['notify_events'] ?? 1 ),
                'notify_classes'       => absint( $d['notify_classes'] ?? 1 ),
                'notify_announcements' => absint( $d['notify_announcements'] ?? 1 ),
                'notify_fundraising'   => absint( $d['notify_fundraising'] ?? 0 ),
                'notify_live'          => absint( $d['notify_live'] ?? 1 ),
                'status'               => 'active',
            ] );
        }

        // Also set as favourite if user has none
        $ut = YNJ_DB::table( 'users' );
        if ( ! $user->favourite_mosque_id ) {
            $wpdb->update( $ut, [ 'favourite_mosque_id' => $mosque_id ], [ 'id' => $user->id ] );
        }

        // Get mosque name for response
        $mosque_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) );

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Subscribed to ' . ( $mosque_name ?: 'mosque' ) . '!',
        ], 201 );
    }

    // ================================================================
    // UPDATE PREFERENCES
    // ================================================================

    public static function update_prefs( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );
        $mosque_id = absint( $r->get_param( 'mosque_id' ) );
        $d = $r->get_json_params();

        global $wpdb;
        $t = YNJ_DB::table( 'user_subscriptions' );

        $update = [];
        $pref_fields = [ 'notify_events', 'notify_classes', 'notify_announcements', 'notify_fundraising', 'notify_live' ];
        foreach ( $pref_fields as $f ) {
            if ( isset( $d[$f] ) ) $update[$f] = absint( $d[$f] ) ? 1 : 0;
        }

        if ( empty( $update ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'No preferences to update.' ], 400 );
        }

        $wpdb->update( $t, $update, [ 'user_id' => $user->id, 'mosque_id' => $mosque_id, 'status' => 'active' ] );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Notification preferences updated.' ] );
    }

    // ================================================================
    // UNSUBSCRIBE
    // ================================================================

    public static function unsubscribe( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );
        $mosque_id = absint( $r->get_param( 'mosque_id' ) );

        global $wpdb;
        $t = YNJ_DB::table( 'user_subscriptions' );

        $wpdb->update( $t, [ 'status' => 'unsubscribed' ], [
            'user_id'  => $user->id,
            'mosque_id' => $mosque_id,
        ] );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Unsubscribed.' ] );
    }

    // ================================================================
    // PUBLIC: SUBSCRIBER COUNT
    // ================================================================

    public static function public_count( \WP_REST_Request $r ) {
        $mosque_id = absint( $r->get_param( 'id' ) );

        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        $anon = YNJ_DB::table( 'subscribers' );

        $user_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $st WHERE mosque_id = %d AND status = 'active'", $mosque_id
        ) );
        $anon_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $anon WHERE mosque_id = %d AND status = 'active'", $mosque_id
        ) );

        return new \WP_REST_Response( [
            'ok'    => true,
            'total' => $user_count + $anon_count,
            'users' => $user_count,
        ] );
    }

    // ================================================================
    // HELPER: Get users subscribed to a mosque who want a specific notification type
    // ================================================================

    /**
     * Get all user IDs subscribed to a mosque who have a specific notification preference enabled.
     *
     * @param int    $mosque_id  Mosque ID
     * @param string $notify_type  Column name: notify_events, notify_classes, notify_announcements, notify_live, notify_fundraising
     * @return array  Array of user rows with push_endpoint, push_p256dh, push_auth
     */
    public static function get_subscribers_for( int $mosque_id, string $notify_type ): array {
        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        $ut = YNJ_DB::table( 'users' );

        $allowed = [ 'notify_events', 'notify_classes', 'notify_announcements', 'notify_fundraising', 'notify_live' ];
        if ( ! in_array( $notify_type, $allowed, true ) ) return [];

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT u.id, u.name, u.push_endpoint, u.push_p256dh, u.push_auth
             FROM $st s
             INNER JOIN $ut u ON u.id = s.user_id
             WHERE s.mosque_id = %d
               AND s.status = 'active'
               AND s.$notify_type = 1
               AND u.push_endpoint != ''
               AND u.status = 'active'",
            $mosque_id
        ) ) ?: [];
    }
}
