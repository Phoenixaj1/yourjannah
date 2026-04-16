<?php
/**
 * YourJannah Points API — Gamification system.
 *
 * Point values:
 *   check_in     = 10 pts (GPS verified, max 1/day/mosque)
 *   event_rsvp   = 25 pts
 *   donation     = 50 pts
 *   class_enrol  = 20 pts
 *   volunteer    = 30 pts
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Points {

    const NS = 'ynj/v1';

    const POINTS = [
        'check_in'    => 10,
        'event_rsvp'  => 25,
        'donation'    => 50,
        'class_enrol' => 20,
        'volunteer'   => 30,
    ];

    public static function register() {
        // POST /points/checkin — GPS-verified mosque check-in
        register_rest_route( self::NS, '/points/checkin', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkin' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /points/me — my points summary
        register_rest_route( self::NS, '/points/me', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'my_points' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /mosques/{slug}/leaderboard — top 10 for a mosque
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/leaderboard', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'leaderboard' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Award points to a user.
     */
    public static function award( $user_id, $mosque_id, $action, $ref_id = null, $description = '' ) {
        if ( ! isset( self::POINTS[ $action ] ) || ! $user_id ) return 0;

        $pts = self::POINTS[ $action ];

        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'points' ), [
            'user_id'     => $user_id,
            'mosque_id'   => $mosque_id,
            'action'      => $action,
            'points'      => $pts,
            'ref_id'      => $ref_id,
            'description' => sanitize_text_field( $description ),
        ] );

        // Update cached total
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . YNJ_DB::table( 'users' ) . " SET total_points = total_points + %d WHERE id = %d",
            $pts, $user_id
        ) );

        return $pts;
    }

    /**
     * POST /points/checkin — GPS-verified check-in.
     * Requires auth token + lat/lng within 200m of mosque.
     */
    public static function checkin( \WP_REST_Request $request ) {
        $data  = $request->get_json_params();
        $token = str_replace( 'Bearer ', '', $request->get_header( 'Authorization' ) ?? '' );

        global $wpdb;
        $user_table = YNJ_DB::table( 'users' );
        $user = null;

        // Try token auth first
        if ( $token ) {
            $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );
            $user = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, name FROM $user_table WHERE token_hash = %s AND status = 'active'",
                $token_hash
            ) );
        }

        // Fallback: WP cookie auth — auto-link or create ynj_user
        if ( ! $user && is_user_logged_in() ) {
            $wp_uid = get_current_user_id();
            $ynj_uid = (int) get_user_meta( $wp_uid, 'ynj_user_id', true );
            if ( $ynj_uid ) {
                $user = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM $user_table WHERE id = %d", $ynj_uid ) );
            }
            if ( ! $user ) {
                $wp_user = wp_get_current_user();
                $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM $user_table WHERE email = %s LIMIT 1", $wp_user->user_email ) );
                if ( $existing ) {
                    $user = $existing;
                    update_user_meta( $wp_uid, 'ynj_user_id', (int) $existing->id );
                } else {
                    $new_token = bin2hex( random_bytes( 32 ) );
                    $new_hash = hash_hmac( 'sha256', $new_token, 'ynj_user_salt_2024' );
                    $wpdb->insert( $user_table, [
                        'name' => $wp_user->display_name, 'email' => $wp_user->user_email,
                        'password_hash' => '', 'token_hash' => $new_hash, 'status' => 'active',
                    ] );
                    $new_id = (int) $wpdb->insert_id;
                    update_user_meta( $wp_uid, 'ynj_user_id', $new_id );
                    $user = (object) [ 'id' => $new_id, 'name' => $wp_user->display_name ];
                }
            }
        }

        if ( ! $user ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Login required to check in.' ], 401 );
        }

        $mosque_slug = sanitize_title( $data['mosque_slug'] ?? '' );
        $user_lat    = floatval( $data['lat'] ?? 0 );
        $user_lng    = floatval( $data['lng'] ?? 0 );

        if ( ! $mosque_slug || ! $user_lat || ! $user_lng ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Location and mosque required.' ], 400 );
        }

        // Get mosque (active or unclaimed — users can check in at any listed mosque)
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, latitude, longitude FROM " . YNJ_DB::table( 'mosques' ) . " WHERE slug = %s AND status IN ('active','unclaimed')",
            $mosque_slug
        ) );

        if ( ! $mosque || ! $mosque->latitude ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        // Verify GPS proximity (within 200m)
        $distance_m = self::haversine_m( $user_lat, $user_lng, (float) $mosque->latitude, (float) $mosque->longitude );
        if ( $distance_m > 200 ) {
            return new \WP_REST_Response( [
                'ok'       => false,
                'error'    => 'You need to be at the mosque to check in. You are ' . round( $distance_m ) . 'm away.',
                'distance' => round( $distance_m ),
            ], 400 );
        }

        // Check: 2-hour gap between check-ins at same mosque
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'points' ) . "
             WHERE user_id = %d AND mosque_id = %d AND action = 'check_in' AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)",
            $user->id, $mosque->id
        ) );

        if ( $recent > 0 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'You checked in recently. Next check-in available in 2 hours.' ], 429 );
        }

        // Award points
        $pts = self::award( $user->id, (int) $mosque->id, 'check_in', null, 'Checked in at ' . $mosque->name );

        // Get updated total
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT total_points FROM $user_table WHERE id = %d", $user->id
        ) );

        return new \WP_REST_Response( [
            'ok'      => true,
            'points'  => $pts,
            'total'   => $total,
            'message' => '+' . $pts . ' points! Checked in at ' . $mosque->name,
        ] );
    }

    /**
     * GET /points/me — user's points summary.
     */
    public static function my_points( \WP_REST_Request $request ) {
        $token = str_replace( 'Bearer ', '', $request->get_header( 'Authorization' ) ?? '' );

        global $wpdb;
        $user_table = YNJ_DB::table( 'users' );
        $user = null;

        if ( $token ) {
            $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );
            $user = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, name, total_points FROM $user_table WHERE token_hash = %s AND status = 'active'",
                $token_hash
            ) );
        }

        // Fallback: WP cookie auth
        if ( ! $user && is_user_logged_in() ) {
            $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            if ( $ynj_uid ) {
                $user = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, total_points FROM $user_table WHERE id = %d", $ynj_uid ) );
            }
        }

        if ( ! $user ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Login required.' ], 401 );
        }

        $points_table = YNJ_DB::table( 'points' );

        // Recent activity
        $recent = $wpdb->get_results( $wpdb->prepare(
            "SELECT action, points, description, created_at FROM $points_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
            $user->id
        ) );

        // Breakdown by action
        $breakdown = $wpdb->get_results( $wpdb->prepare(
            "SELECT action, SUM(points) as total, COUNT(*) as count FROM $points_table WHERE user_id = %d GROUP BY action",
            $user->id
        ) );

        // Streak: consecutive days with check-ins
        $streak = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT DATE(created_at)) FROM $points_table
             WHERE user_id = %d AND action = 'check_in' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user->id
        ) );

        return new \WP_REST_Response( [
            'ok'        => true,
            'total'     => (int) $user->total_points,
            'streak'    => $streak,
            'breakdown' => $breakdown,
            'recent'    => $recent,
        ] );
    }

    /**
     * GET /mosques/{slug}/leaderboard — top 10 for a mosque.
     */
    public static function leaderboard( \WP_REST_Request $request ) {
        $slug = sanitize_title( $request->get_param( 'slug' ) );

        global $wpdb;
        $mosque_id = YNJ_DB::resolve_slug( $slug );
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $points_table = YNJ_DB::table( 'points' );
        $user_table   = YNJ_DB::table( 'users' );

        $leaders = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.name, SUM(p.points) AS total_points, COUNT(DISTINCT DATE(p.created_at)) AS active_days
             FROM $points_table p
             INNER JOIN $user_table u ON u.id = p.user_id
             WHERE p.mosque_id = %d
             GROUP BY p.user_id
             ORDER BY total_points DESC
             LIMIT 10",
            $mosque_id
        ) );

        return new \WP_REST_Response( [
            'ok'          => true,
            'leaderboard' => array_map( function( $row, $i ) {
                return [
                    'rank'        => $i + 1,
                    'name'        => $row->name,
                    'points'      => (int) $row->total_points,
                    'active_days' => (int) $row->active_days,
                ];
            }, $leaders, array_keys( $leaders ) ),
        ] );
    }

    /**
     * Haversine distance in meters.
     */
    private static function haversine_m( $lat1, $lng1, $lat2, $lng2 ) {
        $R = 6371000;
        $dLat = deg2rad( $lat2 - $lat1 );
        $dLng = deg2rad( $lng2 - $lng1 );
        $a = sin( $dLat / 2 ) * sin( $dLat / 2 ) +
             cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
             sin( $dLng / 2 ) * sin( $dLng / 2 );
        return $R * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
    }
}
