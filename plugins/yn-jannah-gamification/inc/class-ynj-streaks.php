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
}
