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

        // --- Membership Endpoints ---

        // POST /auth/join-mosque — Join a mosque as a member
        register_rest_route( self::NS, '/auth/join-mosque', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'join_mosque' ],
            'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
        ] );

        // POST /auth/leave-mosque — Leave a mosque
        register_rest_route( self::NS, '/auth/leave-mosque', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'leave_mosque' ],
            'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
        ] );

        // PUT /auth/primary-mosque — Change primary mosque
        register_rest_route( self::NS, '/auth/primary-mosque', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'set_primary' ],
            'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
        ] );

        // GET /auth/my-mosques — List joined mosques
        register_rest_route( self::NS, '/auth/my-mosques', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'my_mosques' ],
            'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
        ] );

        // GET /mosques/{id}/member-count — Public member count
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/member-count', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'member_count' ],
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
                'is_member'            => (bool) ( $s->is_member ?? false ),
                'is_primary'           => (bool) ( $s->is_primary ?? false ),
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

        // If they were a member, decrement count and clear membership
        $sub = $wpdb->get_row( $wpdb->prepare(
            "SELECT is_member FROM $t WHERE user_id = %d AND mosque_id = %d AND status = 'active'",
            $user->id, $mosque_id
        ) );

        $wpdb->update( $t, [ 'status' => 'unsubscribed', 'is_member' => 0, 'is_primary' => 0 ], [
            'user_id'  => $user->id,
            'mosque_id' => $mosque_id,
        ] );

        if ( $sub && $sub->is_member ) {
            $mt = YNJ_DB::table( 'mosques' );
            $wpdb->query( $wpdb->prepare(
                "UPDATE $mt SET member_count = GREATEST(member_count - 1, 0) WHERE id = %d", $mosque_id
            ) );
        }

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
    // JOIN MOSQUE (Free Membership)
    // ================================================================

    /**
     * Resolve the WP user ID and ynj_user_id from request context.
     * Works with both WP cookie auth and bearer token fallback.
     */
    private static function resolve_user( \WP_REST_Request $r ): array {
        // Try WP cookie first
        $wp_user_id = get_current_user_id();

        // Fallback: if bearer token auth set _ynj_user
        $ynj_user = $r->get_param( '_ynj_user' );
        if ( ! $wp_user_id && $ynj_user ) {
            // Look up WP user by ynj email
            $wp = get_user_by( 'email', $ynj_user->email ?? '' );
            $wp_user_id = $wp ? $wp->ID : 0;
        }

        if ( ! $wp_user_id ) {
            return [ 0, 0 ];
        }

        global $wpdb;
        $ut = YNJ_DB::table( 'users' );
        $ynj_user_id = (int) get_user_meta( $wp_user_id, 'ynj_user_id', true );

        if ( ! $ynj_user_id ) {
            // Auto-create ynj_users row from WP user
            $wp_user = get_userdata( $wp_user_id );
            $wpdb->insert( $ut, [
                'name'  => $wp_user->display_name,
                'email' => $wp_user->user_email,
            ] );
            $ynj_user_id = (int) $wpdb->insert_id;
            update_user_meta( $wp_user_id, 'ynj_user_id', $ynj_user_id );
        }

        return [ $wp_user_id, $ynj_user_id ];
    }

    public static function join_mosque( \WP_REST_Request $r ) {
        list( $wp_user_id, $ynj_user_id ) = self::resolve_user( $r );
        if ( ! $wp_user_id || ! $ynj_user_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not authenticated.' ], 401 );
        }

        $d = $r->get_json_params();
        $mosque_id = absint( $d['mosque_id'] ?? 0 );
        if ( ! $mosque_id && ! empty( $d['mosque_slug'] ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $d['mosque_slug'] );
        }
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id or mosque_slug required.' ], 400 );
        }

        global $wpdb;
        $st  = YNJ_DB::table( 'user_subscriptions' );
        $mt  = YNJ_DB::table( 'mosques' );
        $ut  = YNJ_DB::table( 'users' );

        // Check if already a member
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status, is_member FROM $st WHERE user_id = %d AND mosque_id = %d",
            $ynj_user_id, $mosque_id
        ) );

        if ( $existing && $existing->status === 'active' && $existing->is_member ) {
            return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Already a member.', 'already' => true ] );
        }

        // Check if this is the user's first mosque (will be primary)
        $member_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $st WHERE user_id = %d AND is_member = 1 AND status = 'active'",
            $ynj_user_id
        ) );
        $set_primary = ( $member_count === 0 ) || ! empty( $d['set_primary'] );

        // If setting as primary, clear existing primary
        if ( $set_primary ) {
            $wpdb->update( $st,
                [ 'is_primary' => 0 ],
                [ 'user_id' => $ynj_user_id, 'is_primary' => 1 ]
            );
        }

        $was_already_member = ( $existing && $existing->is_member );

        if ( $existing ) {
            // Reactivate / upgrade to member
            $wpdb->update( $st, [
                'status'        => 'active',
                'is_member'     => 1,
                'is_primary'    => $set_primary ? 1 : 0,
                'subscribed_at' => current_time( 'mysql', true ),
            ], [ 'id' => $existing->id ] );
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
                'is_primary'           => $set_primary ? 1 : 0,
                'status'               => 'active',
            ] );
        }

        // Update favourite_mosque_id if primary
        if ( $set_primary ) {
            $wpdb->update( $ut, [ 'favourite_mosque_id' => $mosque_id ], [ 'id' => $ynj_user_id ] );
            update_user_meta( $wp_user_id, 'ynj_favourite_mosque_id', $mosque_id );
        }

        // Increment cached member_count on mosque (only if not already counted)
        if ( ! $was_already_member ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE $mt SET member_count = member_count + 1 WHERE id = %d", $mosque_id
            ) );
        }

        $mosque_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mt WHERE id = %d", $mosque_id ) );

        return new \WP_REST_Response( [
            'ok'         => true,
            'message'    => 'Welcome to ' . ( $mosque_name ?: 'the mosque' ) . '!',
            'is_primary' => $set_primary,
        ], 201 );
    }

    // ================================================================
    // LEAVE MOSQUE
    // ================================================================

    public static function leave_mosque( \WP_REST_Request $r ) {
        list( $wp_user_id, $ynj_user_id ) = self::resolve_user( $r );
        if ( ! $wp_user_id || ! $ynj_user_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not authenticated.' ], 401 );
        }

        $d = $r->get_json_params();
        $mosque_id = absint( $d['mosque_id'] ?? 0 );
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id required.' ], 400 );
        }

        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        $mt = YNJ_DB::table( 'mosques' );

        // Check membership
        $sub = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, is_primary FROM $st WHERE user_id = %d AND mosque_id = %d AND is_member = 1 AND status = 'active'",
            $ynj_user_id, $mosque_id
        ) );

        if ( ! $sub ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not a member of this mosque.' ], 404 );
        }

        // Remove membership (keep subscription for notifications if they want)
        $wpdb->update( $st, [ 'is_member' => 0, 'is_primary' => 0 ], [ 'id' => $sub->id ] );

        // Decrement cached count
        $wpdb->query( $wpdb->prepare(
            "UPDATE $mt SET member_count = GREATEST(member_count - 1, 0) WHERE id = %d", $mosque_id
        ) );

        // If was primary, auto-assign next mosque as primary
        if ( $sub->is_primary ) {
            $next = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, mosque_id FROM $st WHERE user_id = %d AND is_member = 1 AND status = 'active' ORDER BY subscribed_at ASC LIMIT 1",
                $ynj_user_id
            ) );
            if ( $next ) {
                $wpdb->update( $st, [ 'is_primary' => 1 ], [ 'id' => $next->id ] );
                $wpdb->update( YNJ_DB::table( 'users' ), [ 'favourite_mosque_id' => $next->mosque_id ], [ 'id' => $ynj_user_id ] );
                update_user_meta( $wp_user_id, 'ynj_favourite_mosque_id', $next->mosque_id );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . YNJ_DB::table( 'users' ) . " SET favourite_mosque_id = NULL WHERE id = %d",
                    $ynj_user_id
                ) );
                delete_user_meta( $wp_user_id, 'ynj_favourite_mosque_id' );
            }
        }

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Left mosque.' ] );
    }

    // ================================================================
    // SET PRIMARY MOSQUE
    // ================================================================

    public static function set_primary( \WP_REST_Request $r ) {
        list( $wp_user_id, $ynj_user_id ) = self::resolve_user( $r );
        if ( ! $wp_user_id || ! $ynj_user_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not authenticated.' ], 401 );
        }

        $d = $r->get_json_params();
        $mosque_id = absint( $d['mosque_id'] ?? 0 );
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id required.' ], 400 );
        }

        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );

        // Check they're a member of this mosque
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $st WHERE user_id = %d AND mosque_id = %d AND is_member = 1 AND status = 'active'",
            $ynj_user_id, $mosque_id
        ) );

        if ( ! $exists ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Must join this mosque first.' ], 400 );
        }

        // Clear all primary flags for this user
        $wpdb->update( $st, [ 'is_primary' => 0 ], [ 'user_id' => $ynj_user_id, 'is_primary' => 1 ] );

        // Set new primary
        $wpdb->update( $st, [ 'is_primary' => 1 ], [ 'id' => $exists ] );

        // Update favourite_mosque_id
        $wpdb->update( YNJ_DB::table( 'users' ), [ 'favourite_mosque_id' => $mosque_id ], [ 'id' => $ynj_user_id ] );
        update_user_meta( $wp_user_id, 'ynj_favourite_mosque_id', $mosque_id );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Primary mosque updated.' ] );
    }

    // ================================================================
    // MY MOSQUES (all joined mosques)
    // ================================================================

    public static function my_mosques( \WP_REST_Request $r ) {
        list( $wp_user_id, $ynj_user_id ) = self::resolve_user( $r );
        if ( ! $wp_user_id || ! $ynj_user_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not authenticated.' ], 401 );
        }

        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        $mt = YNJ_DB::table( 'mosques' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.mosque_id, s.is_primary, s.subscribed_at,
                    m.name, m.slug, m.city, m.postcode, m.logo_url, m.member_count
             FROM $st s
             INNER JOIN $mt m ON m.id = s.mosque_id
             WHERE s.user_id = %d AND s.is_member = 1 AND s.status = 'active'
             ORDER BY s.is_primary DESC, s.subscribed_at ASC",
            $ynj_user_id
        ) );

        $mosques = array_map( function( $row ) {
            return [
                'mosque_id'    => (int) $row->mosque_id,
                'name'         => $row->name,
                'slug'         => $row->slug,
                'city'         => $row->city,
                'postcode'     => $row->postcode,
                'logo_url'     => $row->logo_url,
                'member_count' => (int) $row->member_count,
                'is_primary'   => (bool) $row->is_primary,
                'joined_at'    => $row->subscribed_at,
            ];
        }, $rows );

        return new \WP_REST_Response( [ 'ok' => true, 'mosques' => $mosques ] );
    }

    // ================================================================
    // PUBLIC: MEMBER COUNT
    // ================================================================

    public static function member_count( \WP_REST_Request $r ) {
        $mosque_id = absint( $r->get_param( 'id' ) );

        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT member_count FROM $mt WHERE id = %d", $mosque_id
        ) );

        return new \WP_REST_Response( [ 'ok' => true, 'count' => $count ] );
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
