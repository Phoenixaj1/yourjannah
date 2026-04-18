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

        // ── Ibadah tracker endpoints ──
        register_rest_route( self::NS, '/ibadah/log', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'ibadah_log' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ] );

        register_rest_route( self::NS, '/ibadah/me', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'ibadah_me' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ] );

        register_rest_route( self::NS, '/ibadah/community/(?P<mosque_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'ibadah_community' ],
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

    // ================================================================
    // IBADAH TRACKER
    // ================================================================

    /**
     * POST /ibadah/log — Log or update today's ibadah.
     * Calculates points, updates streak, contributes to community challenge.
     */
    public static function ibadah_log( \WP_REST_Request $request ) {
        $wp_uid  = get_current_user_id();
        $ynj_uid = (int) get_user_meta( $wp_uid, 'ynj_user_id', true );
        if ( ! $ynj_uid ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'User not found' ], 400 );
        }

        $mosque_id = (int) get_user_meta( $wp_uid, 'ynj_mosque_id', true );
        if ( ! $mosque_id ) {
            // Try favourite from ynj_users
            global $wpdb;
            $mosque_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT favourite_mosque_id FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid
            ) );
        }

        $today = date( 'Y-m-d' );
        $prayers = [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ];

        $data = [
            'user_id'     => $ynj_uid,
            'mosque_id'   => $mosque_id,
            'log_date'    => $today,
            'fajr'        => (int) ( $request->get_param( 'fajr' ) ? 1 : 0 ),
            'dhuhr'       => (int) ( $request->get_param( 'dhuhr' ) ? 1 : 0 ),
            'asr'         => (int) ( $request->get_param( 'asr' ) ? 1 : 0 ),
            'maghrib'     => (int) ( $request->get_param( 'maghrib' ) ? 1 : 0 ),
            'isha'        => (int) ( $request->get_param( 'isha' ) ? 1 : 0 ),
            'quran_pages' => max( 0, min( 100, (int) $request->get_param( 'quran_pages' ) ) ),
            'dhikr'       => (int) ( $request->get_param( 'dhikr' ) ? 1 : 0 ),
            'fasting'     => (int) ( $request->get_param( 'fasting' ) ? 1 : 0 ),
            'charity'     => (int) ( $request->get_param( 'charity' ) ? 1 : 0 ),
            'good_deed'   => sanitize_text_field( $request->get_param( 'good_deed' ) ?: '' ),
        ];

        // Calculate points
        $pts = 0;
        $prayer_count = $data['fajr'] + $data['dhuhr'] + $data['asr'] + $data['maghrib'] + $data['isha'];
        $pts += $prayer_count * 2;                           // 2 pts per prayer
        if ( $prayer_count === 5 ) $pts += 15;               // All 5 bonus
        $pts += $data['quran_pages'] * 5;                    // 5 pts per page
        if ( $data['dhikr'] )   $pts += 3;
        if ( $data['fasting'] ) $pts += 10;
        if ( $data['charity'] ) $pts += 5;
        if ( $data['good_deed'] ) $pts += 5;
        $data['points_earned'] = $pts;

        // UPSERT
        global $wpdb;
        $table = YNJ_DB::table( 'ibadah_logs' );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND log_date = %s", $ynj_uid, $today
        ) );

        $old_pts = 0;
        if ( $existing ) {
            $old_pts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT points_earned FROM $table WHERE id = %d", $existing ) );
            $wpdb->update( $table, $data, [ 'id' => $existing ] );
        } else {
            $wpdb->insert( $table, $data );
        }

        // Update user total points (delta)
        $delta = $pts - $old_pts;
        if ( $delta !== 0 ) {
            $ut = YNJ_DB::table( 'users' );
            $wpdb->query( $wpdb->prepare(
                "UPDATE $ut SET total_points = total_points + %d WHERE id = %d",
                $delta, $ynj_uid
            ) );
        }

        // Update community challenge progress
        if ( $mosque_id ) {
            $ct = YNJ_DB::table( 'community_challenges' );
            $active = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, challenge_type, target_value, current_value FROM $ct WHERE mosque_id = %d AND status = 'active' AND end_date >= %s LIMIT 1",
                $mosque_id, $today
            ) );
            if ( $active ) {
                // Recalculate from scratch for accuracy
                $since = $active->start_date ?? $today;
                $it = YNJ_DB::table( 'ibadah_logs' );
                $new_val = 0;
                if ( $active->challenge_type === 'prayers' ) {
                    $new_val = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) FROM $it WHERE mosque_id=%d AND log_date BETWEEN %s AND %s",
                        $mosque_id, $since, $active->end_date ?? $today
                    ) );
                } elseif ( $active->challenge_type === 'quran_pages' ) {
                    $new_val = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COALESCE(SUM(quran_pages),0) FROM $it WHERE mosque_id=%d AND log_date BETWEEN %s AND %s",
                        $mosque_id, $since, $active->end_date ?? $today
                    ) );
                } elseif ( $active->challenge_type === 'good_deeds' ) {
                    $new_val = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM $it WHERE mosque_id=%d AND good_deed != '' AND log_date BETWEEN %s AND %s",
                        $mosque_id, $since, $active->end_date ?? $today
                    ) );
                }
                $update_data = [ 'current_value' => $new_val ];
                if ( $new_val >= (int) $active->target_value ) {
                    $update_data['status'] = 'completed';
                }
                $wpdb->update( $ct, $update_data, [ 'id' => $active->id ] );
            }
        }

        // Calculate streak
        $streak = self::calculate_ibadah_streak( $ynj_uid );

        // Check streak milestones (award bonus points)
        $milestones = [ 7 => 50, 14 => 100, 30 => 250, 60 => 500, 90 => 1000 ];
        foreach ( $milestones as $days => $bonus ) {
            if ( $streak === $days ) {
                $milestone_key = 'ynj_streak_' . $ynj_uid . '_' . $days;
                if ( ! get_transient( $milestone_key ) ) {
                    set_transient( $milestone_key, 1, 365 * DAY_IN_SECONDS );
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE " . YNJ_DB::table( 'users' ) . " SET total_points = total_points + %d WHERE id = %d",
                        $bonus, $ynj_uid
                    ) );
                    $pts += $bonus;
                }
                break;
            }
        }

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT total_points FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid
        ) );

        return new \WP_REST_Response( [
            'ok'           => true,
            'points_today' => $pts,
            'streak'       => $streak,
            'total_points' => $total,
        ] );
    }

    /**
     * GET /ibadah/me — Get user's ibadah summary.
     */
    public static function ibadah_me( \WP_REST_Request $request ) {
        $wp_uid  = get_current_user_id();
        $ynj_uid = (int) get_user_meta( $wp_uid, 'ynj_user_id', true );
        if ( ! $ynj_uid ) {
            return new \WP_REST_Response( [ 'ok' => false ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'ibadah_logs' );
        $today = date( 'Y-m-d' );

        // Today's log
        $today_log = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND log_date = %s", $ynj_uid, $today
        ) );

        // This week totals
        $week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
        $week = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers, COALESCE(SUM(quran_pages),0) AS pages,
                    COALESCE(SUM(points_earned),0) AS points, COUNT(*) AS days_logged
             FROM $table WHERE user_id = %d AND log_date >= %s",
            $ynj_uid, $week_start
        ) );

        $streak = self::calculate_ibadah_streak( $ynj_uid );

        return new \WP_REST_Response( [
            'ok'      => true,
            'today'   => $today_log ? [
                'fajr' => (int) $today_log->fajr, 'dhuhr' => (int) $today_log->dhuhr,
                'asr' => (int) $today_log->asr, 'maghrib' => (int) $today_log->maghrib,
                'isha' => (int) $today_log->isha, 'quran_pages' => (int) $today_log->quran_pages,
                'dhikr' => (int) $today_log->dhikr, 'fasting' => (int) $today_log->fasting,
                'charity' => (int) $today_log->charity, 'good_deed' => $today_log->good_deed,
                'points' => (int) $today_log->points_earned,
            ] : null,
            'streak'  => $streak,
            'week'    => [
                'prayers' => (int) $week->prayers,
                'pages'   => (int) $week->pages,
                'points'  => (int) $week->points,
                'days'    => (int) $week->days_logged,
            ],
        ] );
    }

    /**
     * GET /ibadah/community/{mosque_id} — Anonymous aggregate stats.
     */
    public static function ibadah_community( \WP_REST_Request $request ) {
        $mosque_id = (int) $request->get_param( 'mosque_id' );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false ], 400 );

        global $wpdb;
        $table = YNJ_DB::table( 'ibadah_logs' );
        $week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
        $today = date( 'Y-m-d' );

        // This week aggregates
        $week = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers,
                    COALESCE(SUM(quran_pages),0) AS pages,
                    COALESCE(SUM(fasting),0) AS fasting_days,
                    COUNT(DISTINCT CASE WHEN good_deed != '' THEN id END) AS good_deeds,
                    COUNT(DISTINCT user_id) AS active_members
             FROM $table WHERE mosque_id = %d AND log_date >= %s",
            $mosque_id, $week_start
        ) );

        // Active today
        $active_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table WHERE mosque_id = %d AND log_date = %s",
            $mosque_id, $today
        ) );

        // Current challenge
        $ct = YNJ_DB::table( 'community_challenges' );
        $challenge = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $ct WHERE mosque_id = %d AND status = 'active' AND end_date >= %s ORDER BY id DESC LIMIT 1",
            $mosque_id, $today
        ) );

        $days_left = $challenge ? max( 0, (int) ( ( strtotime( $challenge->end_date ) - time() ) / DAY_IN_SECONDS ) + 1 ) : 0;

        return new \WP_REST_Response( [
            'ok'    => true,
            'week'  => [
                'prayers'      => (int) $week->prayers,
                'pages'        => (int) $week->pages,
                'fasting'      => (int) $week->fasting_days,
                'good_deeds'   => (int) $week->good_deeds,
                'active_members' => (int) $week->active_members,
            ],
            'active_today' => $active_today,
            'challenge'    => $challenge ? [
                'title'    => $challenge->title,
                'type'     => $challenge->challenge_type,
                'target'   => (int) $challenge->target_value,
                'current'  => (int) $challenge->current_value,
                'pct'      => (int) $challenge->target_value > 0 ? min( 100, round( (int) $challenge->current_value / (int) $challenge->target_value * 100 ) ) : 0,
                'days_left'=> $days_left,
                'status'   => $challenge->status,
            ] : null,
        ] );
    }

    /**
     * Calculate consecutive-day ibadah streak for a user.
     */
    private static function calculate_ibadah_streak( $user_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'ibadah_logs' );

        $dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT log_date FROM $table WHERE user_id = %d AND (fajr=1 OR dhuhr=1 OR asr=1 OR maghrib=1 OR isha=1) ORDER BY log_date DESC LIMIT 120",
            $user_id
        ) );

        if ( empty( $dates ) ) return 0;

        $streak   = 0;
        $expected = date( 'Y-m-d' ); // today

        foreach ( $dates as $d ) {
            if ( $d === $expected ) {
                $streak++;
                $expected = date( 'Y-m-d', strtotime( $expected . ' -1 day' ) );
            } elseif ( $streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
                // Allow starting from yesterday if not logged today yet
                $streak = 1;
                $expected = date( 'Y-m-d', strtotime( $d . ' -1 day' ) );
            } else {
                break;
            }
        }

        return $streak;
    }
}
