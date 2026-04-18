<?php
/**
 * Mosque Leagues — Size-tiered competition, H2H challenges, congregation stats.
 *
 * @package YNJ_Gamification
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Leagues {

    /**
     * Get the league tier for a mosque based on member count.
     */
    public static function get_tier( $member_count ) {
        $tiers = [
            [ 'key' => 'rising_star','name' => 'Rising Star', 'icon' => "\xF0\x9F\x8C\x9F", 'min' => 0,   'max' => 25  ],
            [ 'key' => 'growing',    'name' => 'Growing',    'icon' => "\xF0\x9F\x8C\xBF", 'min' => 26,  'max' => 100 ],
            [ 'key' => 'established','name' => 'Established','icon' => "\xF0\x9F\x8C\xB3", 'min' => 101, 'max' => 500 ],
            [ 'key' => 'flagship',   'name' => 'Flagship',   'icon' => "\xF0\x9F\x8F\x86", 'min' => 501, 'max' => 999999 ],
        ];
        foreach ( $tiers as $t ) {
            if ( $member_count >= $t['min'] && $member_count <= $t['max'] ) return $t;
        }
        return $tiers[0];
    }

    /**
     * Get mosque league standings — ranked by dhikr per member.
     */
    public static function get_standings( $mosque_id, $city = null, $days = 7 ) {
        global $wpdb;
        $mt    = YNJ_DB::table( 'mosques' );
        $sub   = YNJ_DB::table( 'user_subscriptions' );
        $ib    = YNJ_DB::table( 'ibadah_logs' );
        $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $my_members = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND status = 'active'", $mosque_id
        ) );
        $my_tier = self::get_tier( $my_members );

        $city_clause = $city ? $wpdb->prepare( " AND m.city = %s", $city ) : '';

        $mosques_in_tier = $wpdb->get_results(
            "SELECT m.id, m.name, m.slug, m.city,
                    COALESCE(s.cnt, 0) AS members,
                    COALESCE(dk.dhikr_count, 0) AS dhikr_count,
                    COALESCE(dk.dhikr_count, 0) AS raw_score,
                    CASE WHEN COALESCE(s.cnt, 0) > 0
                         THEN ROUND( COALESCE(dk.dhikr_count, 0) / s.cnt, 1 )
                         ELSE 0 END AS per_member
             FROM $mt m
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS cnt
                 FROM $sub WHERE status = 'active' GROUP BY mosque_id
             ) s ON s.mosque_id = m.id
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS dhikr_count
                 FROM $ib WHERE dhikr = 1 AND log_date >= '$since' GROUP BY mosque_id
             ) dk ON dk.mosque_id = m.id
             WHERE m.status = 'active'
               AND COALESCE(s.cnt, 0) BETWEEN {$my_tier['min']} AND {$my_tier['max']}
               {$city_clause}
             HAVING raw_score > 0
             ORDER BY per_member DESC
             LIMIT 50"
        );

        $rank = 0;
        $my_score = 0;
        $my_per_member = 0;
        $my_breakdown = [];
        foreach ( $mosques_in_tier as $i => $m ) {
            if ( (int) $m->id === $mosque_id ) {
                $rank = $i + 1;
                $my_score = (int) $m->raw_score;
                $my_per_member = (float) $m->per_member;
                $my_breakdown = [ 'dhikr_count' => (int) $m->dhikr_count ];
                break;
            }
        }

        return [
            'rank'       => $rank,
            'total'      => count( $mosques_in_tier ),
            'score'      => $my_score,
            'per_member' => $my_per_member,
            'members'    => $my_members,
            'tier'       => $my_tier,
            'top_5'      => array_slice( $mosques_in_tier, 0, 5 ),
            'breakdown'  => $my_breakdown,
        ];
    }

    /**
     * Get the current H2H challenge for a mosque.
     */
    public static function get_h2h_challenge( $mosque_id ) {
        global $wpdb;
        $h2h = YNJ_DB::table( 'h2h_challenges' );
        $mt  = YNJ_DB::table( 'mosques' );
        $today = date( 'Y-m-d' );

        $challenge = $wpdb->get_row( $wpdb->prepare(
            "SELECT h.*, ma.name AS mosque_a_name, mb.name AS mosque_b_name
             FROM $h2h h
             JOIN $mt ma ON ma.id = h.mosque_a_id
             JOIN $mt mb ON mb.id = h.mosque_b_id
             WHERE (h.mosque_a_id = %d OR h.mosque_b_id = %d) AND h.status = 'active' AND h.end_date >= %s
             ORDER BY h.id DESC LIMIT 1",
            $mosque_id, $mosque_id, $today
        ) );

        if ( ! $challenge ) return null;

        $is_a = ( (int) $challenge->mosque_a_id === $mosque_id );
        $my_score    = $is_a ? (int) $challenge->mosque_a_score : (int) $challenge->mosque_b_score;
        $their_score = $is_a ? (int) $challenge->mosque_b_score : (int) $challenge->mosque_a_score;
        $opponent    = $is_a ? $challenge->mosque_b_name : $challenge->mosque_a_name;
        $days_left   = max( 0, (int) ( ( strtotime( $challenge->end_date ) - time() ) / DAY_IN_SECONDS ) + 1 );

        return [
            'id'          => (int) $challenge->id,
            'opponent'    => $opponent,
            'my_score'    => $my_score,
            'their_score' => $their_score,
            'winning'     => $my_score > $their_score,
            'tied'        => $my_score === $their_score,
            'days_left'   => $days_left,
            'type'        => $challenge->challenge_type,
        ];
    }

    /**
     * Generate weekly H2H challenges — called by cron on Mondays.
     */
    public static function generate_h2h_challenges() {
        global $wpdb;
        $h2h = YNJ_DB::table( 'h2h_challenges' );
        $mt  = YNJ_DB::table( 'mosques' );
        $sub = YNJ_DB::table( 'user_subscriptions' );

        $week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
        $week_end   = date( 'Y-m-d', strtotime( 'Sunday this week' ) );

        $wpdb->query( $wpdb->prepare(
            "UPDATE $h2h SET status = 'completed' WHERE status = 'active' AND end_date < %s", date( 'Y-m-d' )
        ) );

        $mosques = $wpdb->get_results(
            "SELECT m.id, m.name, COALESCE(s.cnt, 0) AS members
             FROM $mt m
             LEFT JOIN (SELECT mosque_id, COUNT(*) AS cnt FROM $sub WHERE status = 'active' GROUP BY mosque_id) s ON s.mosque_id = m.id
             LEFT JOIN $h2h h ON (h.mosque_a_id = m.id OR h.mosque_b_id = m.id) AND h.start_date = '$week_start'
             WHERE m.status = 'active' AND h.id IS NULL
             ORDER BY COALESCE(s.cnt, 0) ASC"
        );

        $tiers = [];
        foreach ( $mosques as $m ) {
            $tier = self::get_tier( (int) $m->members );
            $tiers[ $tier['key'] ][] = $m;
        }

        $created = 0;
        foreach ( $tiers as $tier_mosques ) {
            shuffle( $tier_mosques );
            for ( $i = 0; $i + 1 < count( $tier_mosques ); $i += 2 ) {
                $wpdb->insert( $h2h, [
                    'mosque_a_id'    => (int) $tier_mosques[ $i ]->id,
                    'mosque_b_id'    => (int) $tier_mosques[ $i + 1 ]->id,
                    'challenge_type' => 'engagement',
                    'mosque_a_score' => 0,
                    'mosque_b_score' => 0,
                    'start_date'     => $week_start,
                    'end_date'       => $week_end,
                    'status'         => 'active',
                ] );
                $created++;
            }
        }

        if ( $created > 0 ) {
            error_log( "[YNJ-Gamification] Generated {$created} H2H challenges." );
        }
    }

    /**
     * Who's at the masjid — anonymous check-in counter.
     */
    public static function whos_at_masjid( $mosque_id, $hours = 2 ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'points' );
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $pt WHERE mosque_id = %d AND action = 'check_in' AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $mosque_id, $hours
        ) );
        return [ 'count' => $count ];
    }

    /**
     * Personal impact score — what % of mosque's ibadah this user contributed.
     */
    public static function personal_impact( $user_id, $mosque_id, $days = 7 ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $my_pts = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(points_earned),0) FROM $ib WHERE user_id = %d AND mosque_id = %d AND log_date >= %s",
            $user_id, $mosque_id, $since
        ) );
        $total_pts = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(points_earned),0) FROM $ib WHERE mosque_id = %d AND log_date >= %s",
            $mosque_id, $since
        ) );

        return [
            'my_points'    => $my_pts,
            'total_points' => $total_pts,
            'percentage'   => $total_pts > 0 ? round( $my_pts / $total_pts * 100, 1 ) : 0,
        ];
    }

    /**
     * Get congregation ibadah breakdown.
     */
    public static function get_congregation_points( $mosque_id, $days = 7 ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(fajr),0) AS fajr, COALESCE(SUM(dhuhr),0) AS dhuhr,
                    COALESCE(SUM(asr),0) AS asr, COALESCE(SUM(maghrib),0) AS maghrib,
                    COALESCE(SUM(isha),0) AS isha,
                    COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS total_prayers,
                    COALESCE(SUM(quran_pages),0) AS quran_pages,
                    COALESCE(SUM(dhikr),0) AS dhikr_days,
                    COALESCE(SUM(fasting),0) AS fasting_days,
                    COALESCE(SUM(charity),0) AS charity_days,
                    COUNT(DISTINCT CASE WHEN good_deed != '' THEN id END) AS good_deeds,
                    COALESCE(SUM(points_earned),0) AS total_points,
                    COUNT(DISTINCT user_id) AS active_members
             FROM $ib WHERE mosque_id = %d AND log_date >= %s",
            $mosque_id, $since
        ) );

        return [
            'prayers'   => [
                'total'   => (int) $stats->total_prayers,
                'fajr'    => (int) $stats->fajr,
                'dhuhr'   => (int) $stats->dhuhr,
                'asr'     => (int) $stats->asr,
                'maghrib' => (int) $stats->maghrib,
                'isha'    => (int) $stats->isha,
            ],
            'quran_pages'    => (int) $stats->quran_pages,
            'dhikr_days'     => (int) $stats->dhikr_days,
            'fasting_days'   => (int) $stats->fasting_days,
            'charity_days'   => (int) $stats->charity_days,
            'good_deeds'     => (int) $stats->good_deeds,
            'total_points'   => (int) $stats->total_points,
            'active_members' => (int) $stats->active_members,
        ];
    }
}
