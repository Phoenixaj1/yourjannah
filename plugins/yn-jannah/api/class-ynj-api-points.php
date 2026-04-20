<?php
/**
 * YourJannah Points API — Gamification system.
 *
 * Point values:
 *   check_in     = 500 pts (GPS verified, max 1/2hrs/mosque)
 *   check_in_jumuah = 2000 pts (Friday check-in bonus)
 *   event_rsvp   = 25 pts
 *   donation     = 50 pts
 *   class_enrol  = 20 pts
 *   volunteer    = 30 pts
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Points {

    const NS = 'ynj/v1';

    const POINTS = [
        'check_in'         => 500,
        'check_in_jumuah'  => 2000,
        'event_rsvp'       => 25,
        'donation'         => 50,
        'class_enrol'      => 20,
        'volunteer'        => 30,
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

        // GET /ibadah/dhikr — today's Sunnah remembrance + rotating weekly adhkar
        register_rest_route( self::NS, '/ibadah/dhikr', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'dhikr_today' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /aura — city/country dhikr totals for guests (geo-detected)
        register_rest_route( self::NS, '/aura', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'aura_stats' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /aura/nearby — find nearest city with dhikr data from lat/lng
        register_rest_route( self::NS, '/aura/nearby', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'aura_nearby' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /ibadah/dhikr — say "Ameen" / "I've said it" to earn points
        register_rest_route( self::NS, '/ibadah/dhikr', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'dhikr_ameen' ],
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

        // Award points — Jumu'ah (Friday) gets 2000 pts, other days 500
        $is_jumuah = ( (int) date( 'w' ) === 5 );
        $action = $is_jumuah ? 'check_in_jumuah' : 'check_in';
        $label = $is_jumuah ? "Jumu'ah check-in at " . $mosque->name : 'Checked in at ' . $mosque->name;
        $pts = self::award( $user->id, (int) $mosque->id, $action, null, $label );

        // Get updated total
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT total_points FROM $user_table WHERE id = %d", $user->id
        ) );

        $msg = $is_jumuah
            ? "Jumu'ah Mubarak! +" . number_format( $pts ) . " points!"
            : '+' . number_format( $pts ) . ' points! Checked in at ' . $mosque->name;

        return new \WP_REST_Response( [
            'ok'      => true,
            'points'  => $pts,
            'total'   => $total,
            'jumuah'  => $is_jumuah,
            'message' => $msg,
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

        $prayed_at_mosque = (int) ( $request->get_param( 'prayed_at_mosque' ) ? 1 : 0 );

        $data = [
            'user_id'          => $ynj_uid,
            'mosque_id'        => $mosque_id,
            'log_date'         => $today,
            'fajr'             => (int) ( $request->get_param( 'fajr' ) ? 1 : 0 ),
            'dhuhr'            => (int) ( $request->get_param( 'dhuhr' ) ? 1 : 0 ),
            'asr'              => (int) ( $request->get_param( 'asr' ) ? 1 : 0 ),
            'maghrib'          => (int) ( $request->get_param( 'maghrib' ) ? 1 : 0 ),
            'isha'             => (int) ( $request->get_param( 'isha' ) ? 1 : 0 ),
            'quran_pages'      => max( 0, min( 100, (int) $request->get_param( 'quran_pages' ) ) ),
            'dhikr'            => (int) ( $request->get_param( 'dhikr' ) ? 1 : 0 ),
            'fasting'          => (int) ( $request->get_param( 'fasting' ) ? 1 : 0 ),
            'charity'          => (int) ( $request->get_param( 'charity' ) ? 1 : 0 ),
            'good_deed'        => sanitize_text_field( $request->get_param( 'good_deed' ) ?: '' ),
            'prayed_at_mosque' => $prayed_at_mosque,
        ];

        // Calculate points — 27x multiplier for mosque prayers (Hadith)
        $pts = 0;
        $prayer_count = $data['fajr'] + $data['dhuhr'] + $data['asr'] + $data['maghrib'] + $data['isha'];
        $prayer_multiplier = $prayed_at_mosque ? 27 : 1;    // 27x reward for praying at mosque
        $pts += $prayer_count * 2 * $prayer_multiplier;      // 2 pts × multiplier per prayer
        if ( $prayer_count === 5 ) $pts += 15 * $prayer_multiplier; // All 5 bonus × multiplier
        $pts += $data['quran_pages'] * 5;                    // 5 pts per page
        if ( $data['dhikr'] )   $pts += 3;
        if ( $data['fasting'] ) $pts += 10;
        if ( $data['charity'] ) $pts += 5;
        if ( $data['good_deed'] ) $pts += 5;

        // Variable reward — random bonus (Duolingo-style surprise)
        $surprise_bonus = 0;
        if ( $prayer_count >= 3 && mt_rand( 1, 5 ) === 1 ) { // 20% chance when 3+ prayers
            $surprise_bonus = [ 10, 15, 20, 25, 50 ][ mt_rand( 0, 4 ) ];
            $pts += $surprise_bonus;
        }

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

        // Check for new badges
        $new_badges = [];
        if ( function_exists( 'ynj_check_badges' ) ) {
            $new_badges = ynj_check_badges( $ynj_uid, $mosque_id );
        }

        // Jumu'ah streak (consecutive Fridays with check-in)
        $jumuah_streak = 0;
        if ( date( 'N' ) == 5 && $prayed_at_mosque ) { // Friday + at mosque
            $friday_checkins = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT log_date FROM $table WHERE user_id = %d AND prayed_at_mosque = 1 AND DAYOFWEEK(log_date) = 6 ORDER BY log_date DESC LIMIT 52",
                $ynj_uid
            ) ); // DAYOFWEEK 6 = Friday in MySQL
            $expected_friday = date( 'Y-m-d' );
            foreach ( $friday_checkins as $fd ) {
                if ( $fd === $expected_friday ) { $jumuah_streak++; $expected_friday = date( 'Y-m-d', strtotime( "$expected_friday -7 days" ) ); }
                elseif ( $jumuah_streak === 0 ) continue;
                else break;
            }
        }

        return new \WP_REST_Response( [
            'ok'              => true,
            'points_today'    => $pts,
            'streak'          => $streak,
            'total_points'    => $total,
            'at_mosque'       => $prayed_at_mosque,
            'multiplier'      => $prayed_at_mosque ? 27 : 1,
            'surprise_bonus'  => $surprise_bonus,
            'jumuah_streak'   => $jumuah_streak,
            'new_badges'      => array_map( function( $b ) { return [ 'name' => $b['name'], 'icon' => $b['icon'], 'desc' => $b['desc'] ]; }, $new_badges ),
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

        // Fajr streak (consecutive days with Fajr)
        $fajr_dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT log_date FROM $table WHERE user_id = %d AND fajr = 1 ORDER BY log_date DESC LIMIT 120", $ynj_uid
        ) );
        $fajr_streak = 0;
        $expected = $today;
        foreach ( $fajr_dates as $d ) {
            if ( $d === $expected ) { $fajr_streak++; $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) ); }
            elseif ( $fajr_streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) { $fajr_streak = 1; $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) ); }
            else break;
        }

        // Jumu'ah streak (consecutive Fridays at mosque)
        $friday_dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT log_date FROM $table WHERE user_id = %d AND prayed_at_mosque = 1 AND DAYOFWEEK(log_date) = 6 ORDER BY log_date DESC LIMIT 52", $ynj_uid
        ) );
        $jumuah_streak = 0;
        $last_friday = ( date( 'N' ) == 5 ) ? $today : date( 'Y-m-d', strtotime( 'last friday' ) );
        $exp_fri = $last_friday;
        foreach ( $friday_dates as $fd ) {
            if ( $fd === $exp_fri ) { $jumuah_streak++; $exp_fri = date( 'Y-m-d', strtotime( "$exp_fri -7 days" ) ); }
            elseif ( $jumuah_streak === 0 ) continue;
            else break;
        }

        // Heatmap (last 35 days)
        $heatmap_since = date( 'Y-m-d', strtotime( '-34 days' ) );
        $heatmap_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT log_date, points_earned FROM $table WHERE user_id = %d AND log_date >= %s ORDER BY log_date ASC", $ynj_uid, $heatmap_since
        ) ) ?: [];
        $heatmap = [];
        foreach ( $heatmap_rows as $hr ) $heatmap[] = [ 'date' => $hr->log_date, 'points' => (int) $hr->points_earned ];

        return new \WP_REST_Response( [
            'ok'            => true,
            'today'         => $today_log ? [
                'fajr' => (int) $today_log->fajr, 'dhuhr' => (int) $today_log->dhuhr,
                'asr' => (int) $today_log->asr, 'maghrib' => (int) $today_log->maghrib,
                'isha' => (int) $today_log->isha, 'quran_pages' => (int) $today_log->quran_pages,
                'dhikr' => (int) $today_log->dhikr, 'fasting' => (int) $today_log->fasting,
                'charity' => (int) $today_log->charity, 'good_deed' => $today_log->good_deed,
                'prayed_at_mosque' => (int) ( $today_log->prayed_at_mosque ?? 0 ),
                'points' => (int) $today_log->points_earned,
            ] : null,
            'streak'        => $streak,
            'fajr_streak'   => $fajr_streak,
            'jumuah_streak' => $jumuah_streak,
            'week'          => [
                'prayers' => (int) $week->prayers,
                'pages'   => (int) $week->pages,
                'points'  => (int) $week->points,
                'days'    => (int) $week->days_logged,
            ],
            'badges'  => function_exists( 'ynj_get_user_badges' ) ? ynj_get_user_badges( $ynj_uid ) : [],
            'heatmap' => $heatmap,
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

    // ================================================================
    // SUNNAH REMEMBRANCES — Rotating weekly dhikr from Hadith
    // ================================================================

    /**
     * Curated Sunnah adhkar — rotates weekly. Each one draws the user
     * closer to Allah SWT through remembrance. They just tap "Ameen"
     * or "I've said it" to earn points for themselves AND their masjid.
     */
    public static function get_weekly_adhkar() {
        return [
            // ── TAWHEED — The most magnificent words ──
            [
                'arabic'      => 'لَا إِلٰهَ إِلَّا ٱللّٰهُ وَحْدَهُ لَا شَرِيكَ لَهُ، لَهُ ٱلْمُلْكُ وَلَهُ ٱلْحَمْدُ وَهُوَ عَلَىٰ كُلِّ شَيْءٍ قَدِيرٌ',
                'english'     => 'There is no god but Allah, alone, without partner. His is the dominion and His is the praise, and He is over all things capable.',
                'source'      => 'Sahih Muslim 2693',
                'reward'      => 'The GREATEST words. 100 good deeds written, 100 sins erased, protection from Shaytan until evening',
                'category'    => 'tawheed',
                'points'      => 100,
                'action_text' => "La ilaha illallah",
                'tier'        => 'legendary',
            ],
            // ── TASBEEH — Light on the tongue, heavy on the Scale ──
            [
                'arabic'      => 'سُبْحَانَ ٱللّٰهِ وَبِحَمْدِهِ، سُبْحَانَ ٱللّٰهِ ٱلْعَظِيمِ',
                'english'     => 'Glory be to Allah and His is the praise. Glory be to Allah, the Magnificent.',
                'source'      => 'Sahih al-Bukhari 6406',
                'reward'      => 'Two words: light on the tongue, heavy on the Scale, beloved to the Most Merciful',
                'category'    => 'tasbeeh',
                'points'      => 75,
                'action_text' => "SubhanAllah",
                'tier'        => 'epic',
            ],
            // ── SALAWAT — 10x blessings returned ──
            [
                'arabic'      => 'اللَّهُمَّ صَلِّ عَلَىٰ مُحَمَّدٍ وَعَلَىٰ آلِ مُحَمَّدٍ',
                'english'     => 'O Allah, send peace and blessings upon Muhammad and the family of Muhammad.',
                'source'      => 'Sahih al-Bukhari 3370',
                'reward'      => 'Whoever sends salawat once, Allah sends 10 blessings upon them. 10x return guaranteed by Allah',
                'category'    => 'salawat',
                'points'      => 75,
                'action_text' => "Allahumma Salli",
                'tier'        => 'epic',
            ],
            // ── ISTIGHFAR — Door to relief ──
            [
                'arabic'      => 'أَسْتَغْفِرُ ٱللّٰهَ ٱلْعَظِيمَ ٱلَّذِي لَا إِلٰهَ إِلَّا هُوَ ٱلْحَيُّ ٱلْقَيُّومُ وَأَتُوبُ إِلَيْهِ',
                'english'     => 'I seek forgiveness from Allah, the Magnificent, there is no god but Him, the Living, the Sustainer, and I repent to Him.',
                'source'      => 'Abu Dawud 1517, Tirmidhi 3577',
                'reward'      => 'Allah forgives whoever says this even if they fled from battle. The key that opens every locked door',
                'category'    => 'istighfar',
                'points'      => 75,
                'action_text' => "Astaghfirullah",
                'tier'        => 'epic',
            ],
            // ── HAWQALA — Treasure of Jannah ──
            [
                'arabic'      => 'لَا حَوْلَ وَلَا قُوَّةَ إِلَّا بِٱللّٰهِ',
                'english'     => 'There is no power nor strength except with Allah.',
                'source'      => 'Sahih al-Bukhari 4205',
                'reward'      => 'A treasure from the treasures of Paradise. The Prophet (PBUH) said: Guard this treasure',
                'category'    => 'hawqala',
                'points'      => 75,
                'action_text' => "I've said it",
                'tier'        => 'epic',
            ],
            // ── THE FOUR BELOVED WORDS ──
            [
                'arabic'      => 'سُبْحَانَ ٱللّٰهِ، وَٱلْحَمْدُ لِلّٰهِ، وَلَا إِلٰهَ إِلَّا ٱللّٰهُ، وَٱللّٰهُ أَكْبَرُ',
                'english'     => 'Glory be to Allah, praise be to Allah, there is no god but Allah, Allah is the Greatest.',
                'source'      => 'Sahih Muslim 2137',
                'reward'      => 'More beloved to the Prophet (PBUH) than EVERYTHING the sun rises upon. More beloved than the entire dunya',
                'category'    => 'tasbeeh',
                'points'      => 100,
                'action_text' => "SubhanAllah wal Hamdulillah",
                'tier'        => 'legendary',
            ],
            // ── TAWAKKUL — Shield of the believers ──
            [
                'arabic'      => 'حَسْبُنَا ٱللّٰهُ وَنِعْمَ ٱلْوَكِيلُ',
                'english'     => 'Allah is sufficient for us, and He is the best disposer of affairs.',
                'source'      => 'Sahih al-Bukhari 4563',
                'reward'      => 'Said by Ibrahim (AS) when thrown into fire. Said by Muhammad (PBUH) when facing entire armies. The ultimate shield',
                'category'    => 'tawakkul',
                'points'      => 75,
                'action_text' => "HasbunAllah",
                'tier'        => 'epic',
            ],
            // ── DUA — The weapon of the believer ──
            [
                'arabic'      => 'رَبَّنَا آتِنَا فِي ٱلدُّنْيَا حَسَنَةً وَفِي ٱلْآخِرَةِ حَسَنَةً وَقِنَا عَذَابَ ٱلنَّارِ',
                'english'     => 'Our Lord, give us good in this world and good in the Hereafter, and protect us from the torment of the Fire.',
                'source'      => 'Quran 2:201',
                'reward'      => 'The most frequent dua of the Prophet (PBUH). He made this dua more than any other',
                'category'    => 'dua',
                'points'      => 75,
                'action_text' => 'Ameen',
                'tier'        => 'epic',
            ],
            // ── MORNING PROTECTION ──
            [
                'arabic'      => 'بِسْمِ ٱللّٰهِ ٱلَّذِي لَا يَضُرُّ مَعَ ٱسْمِهِ شَيْءٌ فِي ٱلْأَرْضِ وَلَا فِي ٱلسَّمَاءِ وَهُوَ ٱلسَّمِيعُ ٱلْعَلِيمُ',
                'english'     => 'In the Name of Allah, with Whose Name nothing on earth or in the heavens can cause harm, and He is the All-Hearing, All-Knowing.',
                'source'      => 'Abu Dawud 5088, Tirmidhi 3388',
                'reward'      => 'Said 3 times in morning: NOTHING will harm you until evening. Complete divine protection',
                'category'    => 'protection',
                'points'      => 75,
                'action_text' => "Bismillah",
                'tier'        => 'epic',
            ],
            // ── EVENING PEACE ──
            [
                'arabic'      => 'أَعُوذُ بِكَلِمَاتِ ٱللّٰهِ ٱلتَّامَّاتِ مِنْ شَرِّ مَا خَلَقَ',
                'english'     => 'I seek refuge in the perfect words of Allah from the evil of what He has created.',
                'source'      => 'Sahih Muslim 2708',
                'reward'      => 'Said at evening: nothing will harm you that night. The words of Allah are your shield',
                'category'    => 'protection',
                'points'      => 75,
                'action_text' => "A'udhu billah",
                'tier'        => 'epic',
            ],
            // ── CONTENTMENT — The secret to happiness ──
            [
                'arabic'      => 'رَضِيتُ بِٱللّٰهِ رَبًّا، وَبِٱلْإِسْلَامِ دِينًا، وَبِمُحَمَّدٍ صَلَّى ٱللّٰهُ عَلَيْهِ وَسَلَّمَ نَبِيًّا',
                'english'     => 'I am pleased with Allah as my Lord, Islam as my religion, and Muhammad (PBUH) as my Prophet.',
                'source'      => 'Abu Dawud 5072',
                'reward'      => 'Whoever says this: Paradise becomes OBLIGATORY for them. Guaranteed by the Prophet (PBUH)',
                'category'    => 'contentment',
                'points'      => 100,
                'action_text' => "Raditu Billah",
                'tier'        => 'legendary',
            ],
            // ── GRATITUDE OVERFLOWING ──
            [
                'arabic'      => 'ٱلْحَمْدُ لِلّٰهِ حَمْدًا كَثِيرًا طَيِّبًا مُبَارَكًا فِيهِ',
                'english'     => 'All praise is due to Allah, abundant, pure and blessed praise.',
                'source'      => 'Sahih al-Bukhari 5294',
                'reward'      => 'When a man said this, the Prophet (PBUH) said: The angels competed to write it first',
                'category'    => 'hamd',
                'points'      => 75,
                'action_text' => "Alhamdulillah",
                'tier'        => 'epic',
            ],
            // ── POWER OF ALLAH ──
            [
                'arabic'      => 'سُبْحَانَ ٱللّٰهِ وَبِحَمْدِهِ عَدَدَ خَلْقِهِ وَرِضَا نَفْسِهِ وَزِنَةَ عَرْشِهِ وَمِدَادَ كَلِمَاتِهِ',
                'english'     => 'Glory be to Allah and His is the praise, as many times as the number of His creation, as pleases Him, as weighs His Throne, and as much as the ink of His words.',
                'source'      => 'Sahih Muslim 2726',
                'reward'      => 'Said once: equals the reward of hours of regular tasbeeh. The Prophet (PBUH) taught this to multiply reward infinitely',
                'category'    => 'tasbeeh',
                'points'      => 100,
                'action_text' => "SubhanAllah",
                'tier'        => 'legendary',
            ],
            // ── SAFETY DUA ──
            [
                'arabic'      => 'اللَّهُمَّ إِنِّي أَسْأَلُكَ ٱلْعَافِيَةَ فِي ٱلدُّنْيَا وَٱلْآخِرَةِ',
                'english'     => 'O Allah, I ask You for well-being in this world and the Hereafter.',
                'source'      => 'Ibn Majah 3871',
                'reward'      => 'The Prophet (PBUH) said: After certainty of faith, no one is given anything better than well-being',
                'category'    => 'dua',
                'points'      => 75,
                'action_text' => 'Ameen',
                'tier'        => 'epic',
            ],
        ];
    }

    /**
     * Get today's 5 dhikr for the user.
     * La ilaha illallah is ALWAYS #1 (the greatest words).
     * The other 4 rotate daily from the pool.
     */
    public static function get_todays_five() {
        $adhkar = self::get_weekly_adhkar();
        $day_num = (int) date( 'z' );

        // #1 is ALWAYS La ilaha illallah (index 0 in pool)
        $five = [];
        $five[0] = $adhkar[0]; // Tawheed — always first
        $five[0]['index'] = 0;

        // #2-5 rotate from the rest of the pool (skip index 0)
        $rest = array_slice( $adhkar, 1 );
        $rest_count = count( $rest );
        $used = [];
        for ( $i = 1; $i < 5; $i++ ) {
            $idx = ( $day_num + $i * 3 ) % $rest_count;
            // Avoid duplicates
            while ( in_array( $idx, $used, true ) ) $idx = ( $idx + 1 ) % $rest_count;
            $used[] = $idx;
            $d = $rest[ $idx ];
            $d['index'] = $i;
            $five[ $i ] = $d;
        }
        return $five;
    }

    /**
     * GET /ibadah/dhikr — Get today's 5 remembrances with completion status.
     */
    public static function dhikr_today( \WP_REST_Request $request ) {
        $five = self::get_todays_five();
        $done = [];

        if ( is_user_logged_in() ) {
            $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            if ( $ynj_uid ) {
                for ( $i = 0; $i < 5; $i++ ) {
                    $done[ $i ] = (bool) get_transient( 'ynj_dhikr_' . $ynj_uid . '_' . date( 'Y-m-d' ) . '_' . $i );
                }
            }
        }

        $items = [];
        $done_count = 0;
        foreach ( $five as $i => $d ) {
            $is_done = $done[ $i ] ?? false;
            if ( $is_done ) $done_count++;
            $items[] = [
                'index'       => $i,
                'arabic'      => $d['arabic'],
                'english'     => $d['english'],
                'source'      => $d['source'],
                'reward'      => $d['reward'],
                'category'    => $d['category'],
                'points'      => $d['points'],
                'action_text' => $d['action_text'],
                'tier'        => $d['tier'] ?? 'epic',
                'done'        => $is_done,
            ];
        }

        $total_possible = array_sum( array_column( $five, 'points' ) );

        return new \WP_REST_Response( [
            'ok'             => true,
            'items'          => $items,
            'done_count'     => $done_count,
            'total_possible' => $total_possible,
        ] );
    }

    /**
     * POST /ibadah/dhikr — Complete a dhikr. UNLIMITED — no daily cap.
     * First 5 unique dhikr earn full points + all-5 bonus (200 pts).
     * Repeats earn full points too. 1-minute cooldown prevents spam.
     */
    public static function dhikr_ameen( \WP_REST_Request $request ) {
        $wp_uid  = get_current_user_id();
        $ynj_uid = (int) get_user_meta( $wp_uid, 'ynj_user_id', true );
        if ( ! $ynj_uid ) return new \WP_REST_Response( [ 'ok' => false ], 400 );

        $index = (int) $request->get_param( 'index' );
        if ( $index < 0 ) $index = 0;
        if ( $index > 4 ) $index = $index % 5; // Wrap around for repeats

        // 1-minute cooldown per dhikr index (can do all 5 quickly, but can't spam same one)
        $cooldown_key = 'ynj_dhikr_cd_' . $ynj_uid . '_' . $index;
        if ( get_transient( $cooldown_key ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Please wait a moment between dhikr.', 'cooldown' => true ] );
        }

        $today = date( 'Y-m-d' );
        $five = self::get_todays_five();
        $dhikr = $five[ $index ] ?? $five[0];
        $pts = $dhikr['points'];

        // Get mosque
        $mosque_id = (int) get_user_meta( $wp_uid, 'ynj_mosque_id', true );
        if ( ! $mosque_id ) {
            global $wpdb;
            $mosque_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT favourite_mosque_id FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid
            ) );
        }

        global $wpdb;
        $ib_table = YNJ_DB::table( 'ibadah_logs' );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $ib_table WHERE user_id = %d AND log_date = %s", $ynj_uid, $today
        ) );
        if ( $existing ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE $ib_table SET dhikr = 1, points_earned = points_earned + %d WHERE id = %d", $pts, $existing
            ) );
        } else {
            $wpdb->insert( $ib_table, [
                'user_id' => $ynj_uid, 'mosque_id' => $mosque_id, 'log_date' => $today,
                'dhikr' => 1, 'points_earned' => $pts,
            ] );
        }

        // Update user total
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . YNJ_DB::table( 'users' ) . " SET total_points = total_points + %d WHERE id = %d", $pts, $ynj_uid
        ) );

        // Set 1-minute cooldown
        set_transient( $cooldown_key, 1, 60 );

        // Track first completion of each of the 5 daily dhikr (persistent via user meta)
        $wp_uid = get_current_user_id();
        $meta_key = 'ynj_dhikr_done_' . $today;
        $done_arr = $wp_uid ? json_decode( get_user_meta( $wp_uid, $meta_key, true ) ?: '[]', true ) : [];
        if ( ! is_array( $done_arr ) ) $done_arr = [];
        $is_first = ! in_array( $index, $done_arr );
        if ( $is_first && $wp_uid ) {
            $done_arr[] = $index;
            update_user_meta( $wp_uid, $meta_key, wp_json_encode( array_unique( $done_arr ) ) );
        }

        // Also set transient for backward compat
        $first_key = 'ynj_dhikr_' . $ynj_uid . '_' . $today . '_' . $index;
        if ( $is_first ) set_transient( $first_key, 1, DAY_IN_SECONDS );

        // Count how many of 5 unique dhikr are done today
        $done_count = count( array_unique( $done_arr ) );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT total_points FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid
        ) );

        // Bonus for completing all 5 unique dhikr (once per day)
        $all_five_bonus = 0;
        if ( $done_count === 5 ) {
            $bonus_key = 'ynj_dhikr5_' . $ynj_uid . '_' . $today;
            if ( ! get_transient( $bonus_key ) ) {
                $all_five_bonus = 200;
                $wpdb->query( $wpdb->prepare(
                    "UPDATE " . YNJ_DB::table( 'users' ) . " SET total_points = total_points + %d WHERE id = %d", $all_five_bonus, $ynj_uid
                ) );
                $total += $all_five_bonus;
                set_transient( $bonus_key, 1, DAY_IN_SECONDS );
            }
        }

        return new \WP_REST_Response( [
            'ok'             => true,
            'points'         => $pts,
            'total'          => $total,
            'done_count'     => $done_count,
            'all_five_bonus' => $all_five_bonus,
            'is_repeat'      => ! $is_first,
            'message'        => '+' . $pts . ' points for your remembrance of Allah',
        ] );
    }

    /**
     * Award welcome bonus to a new user (50 pts + first remembrance).
     * Called once when user first visits their ibadah page.
     */
    public static function award_welcome_bonus( $ynj_uid, $mosque_id = 0 ) {
        $key = 'ynj_welcome_' . $ynj_uid;
        if ( get_transient( $key ) ) return 0; // Already awarded

        global $wpdb;
        $pts = 50;

        // Award points
        $wpdb->insert( YNJ_DB::table( 'points' ), [
            'user_id'     => $ynj_uid,
            'mosque_id'   => $mosque_id,
            'action'      => 'welcome_bonus',
            'points'      => $pts,
            'description' => 'Welcome to YourJannah! La ilaha illallah',
        ] );

        $wpdb->query( $wpdb->prepare(
            "UPDATE " . YNJ_DB::table( 'users' ) . " SET total_points = total_points + %d WHERE id = %d", $pts, $ynj_uid
        ) );

        set_transient( $key, 1, 365 * DAY_IN_SECONDS );
        return $pts;
    }

    // ================================================================
    // AURA — City/country-level dhikr stats for guests
    // ================================================================

    /**
     * GET /aura?city=London — Get dhikr totals for a city.
     * Also accepts ?country=UK for country-level fallback.
     */
    public static function aura_stats( \WP_REST_Request $request ) {
        $city    = sanitize_text_field( $request->get_param( 'city' ) ?: '' );
        $country = sanitize_text_field( $request->get_param( 'country' ) ?: '' );

        if ( ! $city && ! $country ) {
            // Try to detect from IP
            $city = self::detect_city_from_ip();
        }

        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $ib = YNJ_DB::table( 'ibadah_logs' );

        // Find matching mosques
        $where = '';
        $label = '';
        if ( $city ) {
            $where = $wpdb->prepare( "m.city = %s", $city );
            $label = $city;
        } elseif ( $country ) {
            $where = $wpdb->prepare( "m.country = %s", $country );
            $label = $country;
        } else {
            // Global fallback
            $where = '1=1';
            $label = 'Global';
        }

        $stats = $wpdb->get_row(
            "SELECT COUNT(DISTINCT m.id) AS masjid_count,
                    COALESCE(SUM(dk.cnt), 0) AS total_dhikr,
                    COALESCE(SUM(dk.today_cnt), 0) AS today_dhikr,
                    COALESCE(SUM(dk.members), 0) AS total_members
             FROM $mt m
             LEFT JOIN (
                 SELECT mosque_id,
                        COUNT(*) AS cnt,
                        SUM(CASE WHEN log_date = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
                        COUNT(DISTINCT user_id) AS members
                 FROM $ib WHERE dhikr = 1 GROUP BY mosque_id
             ) dk ON dk.mosque_id = m.id
             WHERE m.status IN ('active','unclaimed') AND $where"
        );

        // Top 3 masjids in this area
        $top_masjids = $wpdb->get_results(
            "SELECT m.name, m.slug, COALESCE(dk.cnt, 0) AS dhikr_count
             FROM $mt m
             LEFT JOIN (SELECT mosque_id, COUNT(*) AS cnt FROM $ib WHERE dhikr = 1 GROUP BY mosque_id) dk ON dk.mosque_id = m.id
             WHERE m.status IN ('active','unclaimed') AND $where
             ORDER BY dhikr_count DESC LIMIT 3"
        ) ?: [];

        return new \WP_REST_Response( [
            'ok'           => true,
            'location'     => $label,
            'total_dhikr'  => (int) $stats->total_dhikr,
            'today_dhikr'  => (int) $stats->today_dhikr,
            'masjid_count' => (int) $stats->masjid_count,
            'members'      => (int) $stats->total_members,
            'top_masjids'  => array_map( function( $m ) {
                return [ 'name' => $m->name, 'slug' => $m->slug, 'dhikr' => (int) $m->dhikr_count ];
            }, $top_masjids ),
        ] );
    }

    /**
     * GET /aura/nearby?lat=51.5&lng=-0.1 — Find nearest city with dhikr data.
     */
    public static function aura_nearby( \WP_REST_Request $request ) {
        $lat = floatval( $request->get_param( 'lat' ) );
        $lng = floatval( $request->get_param( 'lng' ) );

        if ( ! $lat || ! $lng ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'lat/lng required' ], 400 );
        }

        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );

        // Find nearest mosque to get its city
        $nearest = $wpdb->get_row( $wpdb->prepare(
            "SELECT city, country,
                    ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
             FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
             ORDER BY distance ASC LIMIT 1",
            $lat, $lng, $lat
        ) );

        if ( ! $nearest || ! $nearest->city ) {
            return new \WP_REST_Response( [ 'ok' => true, 'city' => '', 'country' => $nearest->country ?? '' ] );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'city'    => $nearest->city,
            'country' => $nearest->country ?? '',
        ] );
    }

    /**
     * Detect city from visitor IP using ip-api.com (free, no key needed).
     * Cached per IP for 1 hour.
     */
    private static function detect_city_from_ip() {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = explode( ',', $ip )[0]; // First IP if multiple
        $ip = trim( $ip );

        if ( ! $ip || $ip === '127.0.0.1' || $ip === '::1' ) return '';

        $cache_key = 'ynj_ip_city_' . md5( $ip );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $response = wp_remote_get( "http://ip-api.com/json/{$ip}?fields=city,country", [ 'timeout' => 2 ] );
        if ( is_wp_error( $response ) ) return '';

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $city = $data['city'] ?? '';

        set_transient( $cache_key, $city, HOUR_IN_SECONDS );
        return $city;
    }
}
