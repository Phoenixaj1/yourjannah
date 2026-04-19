<?php
/**
 * Community Streaks — consecutive days of dhikr at a mosque.
 *
 * @package YNJ_Gamification
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Streaks {

    /**
     * Get the community streak for a mosque.
     *
     * @param int $mosque_id
     * @return int Number of consecutive days
     */
    public static function get_mosque_streak( $mosque_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );

        $dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT log_date FROM $ib WHERE mosque_id = %d AND dhikr = 1 ORDER BY log_date DESC LIMIT 120",
            $mosque_id
        ) );

        $streak = 0;
        $expected = date( 'Y-m-d' );
        foreach ( $dates as $d ) {
            if ( $d === $expected ) {
                $streak++;
                $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) );
            } elseif ( $streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
                $streak = 1;
                $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) );
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get a user's personal dhikr streak (consecutive days).
     *
     * @param  int $user_id  YNJ user ID.
     * @return int           Number of consecutive days.
     */
    public static function get_user_streak( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );

        $dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT log_date FROM $ib WHERE user_id = %d AND dhikr = 1 ORDER BY log_date DESC LIMIT 120",
            absint( $user_id )
        ) );

        $streak = 0;
        $expected = date( 'Y-m-d' );
        foreach ( $dates as $d ) {
            if ( $d === $expected ) {
                $streak++;
                $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) );
            } elseif ( $streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
                $streak = 1;
                $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) );
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get a user's general ibadah streak (any prayer or dhikr).
     */
    public static function get_user_ibadah_streak( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT log_date FROM $ib WHERE user_id = %d AND (fajr=1 OR dhuhr=1 OR asr=1 OR maghrib=1 OR isha=1 OR dhikr=1) ORDER BY log_date DESC LIMIT 120",
            absint( $user_id )
        ) );
        return self::_calc_streak( $dates );
    }

    /**
     * Get a user's Fajr-specific streak.
     */
    public static function get_user_fajr_streak( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT log_date FROM $ib WHERE user_id = %d AND fajr = 1 ORDER BY log_date DESC LIMIT 120",
            absint( $user_id )
        ) );
        return self::_calc_streak( $dates );
    }

    /**
     * Get a user's Jumu'ah streak (consecutive Fridays at mosque).
     */
    public static function get_user_jumuah_streak( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $dates = $wpdb->get_col( $wpdb->prepare(
            "SELECT log_date FROM $ib WHERE user_id = %d AND prayed_at_mosque = 1 AND DAYOFWEEK(log_date) = 6 ORDER BY log_date DESC LIMIT 52",
            absint( $user_id )
        ) );
        $streak = 0;
        $today = date( 'Y-m-d' );
        $last_friday = date( 'N' ) == 5 ? $today : date( 'Y-m-d', strtotime( 'last friday' ) );
        $expected = $last_friday;
        foreach ( $dates as $fd ) {
            if ( $fd === $expected ) { $streak++; $expected = date( 'Y-m-d', strtotime( "$expected -7 days" ) ); }
            elseif ( $streak === 0 ) continue;
            else break;
        }
        return $streak;
    }

    /**
     * Get today's ibadah log for a user.
     */
    public static function get_user_ibadah_today( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT fajr, dhuhr, asr, maghrib, isha, quran_pages, dhikr, fasting, charity, good_deed, prayed_at_mosque, points_earned
             FROM $ib WHERE user_id = %d AND log_date = %s",
            absint( $user_id ), date( 'Y-m-d' )
        ) );
    }

    /**
     * Get weekly stats for a user (current week Mon-Sun).
     */
    public static function get_user_week_stats( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers, COALESCE(SUM(quran_pages),0) AS pages,
                    COALESCE(SUM(points_earned),0) AS points, COUNT(*) AS days_logged
             FROM $ib WHERE user_id = %d AND log_date >= %s",
            absint( $user_id ), $week_start
        ) );
        return $row ? [
            'prayers' => (int) $row->prayers, 'pages' => (int) $row->pages,
            'points'  => (int) $row->points,  'days'  => (int) $row->days_logged,
        ] : [ 'prayers' => 0, 'pages' => 0, 'points' => 0, 'days' => 0 ];
    }

    /**
     * Get 7-day log for streak grid (Mon-Sun of this week).
     */
    public static function get_user_7day_log( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT log_date, (fajr+dhuhr+asr+maghrib+isha) AS prayers, points_earned
             FROM $ib WHERE user_id = %d AND log_date >= %s ORDER BY log_date ASC",
            absint( $user_id ), $week_start
        ) ) ?: [];
    }

    /**
     * Get heatmap data (last N days).
     */
    public static function get_user_heatmap( $user_id, $days = 35 ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $since = date( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT log_date, points_earned FROM $ib WHERE user_id = %d AND log_date >= %s ORDER BY log_date ASC",
            absint( $user_id ), $since
        ) ) ?: [];
    }

    /**
     * Get lifetime ibadah totals for badge progress.
     */
    public static function get_user_ibadah_totals( $user_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers, COALESCE(SUM(quran_pages),0) AS quran,
                    COALESCE(SUM(dhikr),0) AS dhikr_days, COALESCE(SUM(fasting),0) AS fasting_days,
                    COALESCE(SUM(charity),0) AS charity_days,
                    COUNT(DISTINCT CASE WHEN good_deed != '' THEN log_date END) AS good_deeds,
                    COUNT(DISTINCT CASE WHEN fajr+dhuhr+asr+maghrib+isha = 5 THEN log_date END) AS all_five
             FROM $ib WHERE user_id = %d",
            absint( $user_id )
        ) );
    }

    /**
     * Get masjid-wide dhikr stats.
     */
    public static function get_masjid_dhikr_stats( $mosque_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        $mosque_id = absint( $mosque_id );
        return [
            'total' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $ib WHERE mosque_id = %d AND dhikr = 1", $mosque_id
            ) ),
            'today' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM $ib WHERE mosque_id = %d AND dhikr = 1 AND log_date = %s",
                $mosque_id, date( 'Y-m-d' )
            ) ),
        ];
    }

    /**
     * Get count of people who logged Fajr today.
     */
    public static function fajr_counter( $mosque_id ) {
        global $wpdb;
        $ib = YNJ_DB::table( 'ibadah_logs' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $ib WHERE mosque_id = %d AND log_date = CURDATE() AND fajr = 1",
            $mosque_id
        ) );
    }

    /**
     * Helper: calculate consecutive-day streak from date list.
     */
    private static function _calc_streak( $dates ) {
        $streak = 0;
        $expected = date( 'Y-m-d' );
        foreach ( $dates as $d ) {
            if ( $d === $expected ) {
                $streak++;
                $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) );
            } elseif ( $streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
                $streak = 1;
                $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) );
            } else {
                break;
            }
        }
        return $streak;
    }
}
