<?php
/**
 * Check-ins Data Layer — GPS check-ins, most active members, stats.
 * @package YNJ_Checkins
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Checkins_Data {

    const POINTS_NORMAL  = 500;
    const POINTS_JUMUAH  = 2000;

    /**
     * Get recent check-ins for a mosque.
     */
    public static function get_checkins( $mosque_id, $days = 7 ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'points' );
        $ut = YNJ_DB::table( 'users' );
        $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.id, p.user_id, p.points, p.created_at, u.display_name, u.email
             FROM $pt p LEFT JOIN $ut u ON u.id = p.user_id
             WHERE p.mosque_id = %d AND p.action = 'check_in' AND p.created_at >= %s
             ORDER BY p.created_at DESC LIMIT 100",
            (int) $mosque_id, $since
        ) ) ?: [];
    }

    /**
     * Get most active members by check-in count.
     */
    public static function get_most_active( $mosque_id = 0, $limit = 20, $days = 30 ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'points' );
        $ut = YNJ_DB::table( 'users' );
        $mt = YNJ_DB::table( 'mosques' );
        $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $mosque_where = $mosque_id ? $wpdb->prepare( " AND p.mosque_id = %d", (int) $mosque_id ) : '';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.user_id, u.display_name, u.email, m.name AS mosque_name,
                    COUNT(*) AS checkin_count, SUM(p.points) AS total_points,
                    MAX(p.created_at) AS last_checkin
             FROM $pt p
             LEFT JOIN $ut u ON u.id = p.user_id
             LEFT JOIN $mt m ON m.id = p.mosque_id
             WHERE p.action = 'check_in' AND p.created_at >= %s $mosque_where
             GROUP BY p.user_id, p.mosque_id
             ORDER BY checkin_count DESC
             LIMIT %d",
            $since, $limit
        ) ) ?: [];
    }

    /**
     * Get check-in stats for a mosque.
     */
    public static function get_stats( $mosque_id = 0 ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'points' );

        $mosque_where = $mosque_id ? $wpdb->prepare( " AND mosque_id = %d", (int) $mosque_id ) : '';

        return [
            'today'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt WHERE action = 'check_in' AND DATE(created_at) = CURDATE() $mosque_where" ),
            'this_week'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE action = 'check_in' AND created_at >= %s $mosque_where", date( 'Y-m-d', strtotime( '-7 days' ) ) ) ),
            'this_month' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE action = 'check_in' AND created_at >= %s $mosque_where", date( 'Y-m-d', strtotime( '-30 days' ) ) ) ),
            'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt WHERE action = 'check_in' $mosque_where" ),
        ];
    }

    /**
     * Get a user's total check-in count.
     */
    public static function get_user_checkin_count( $user_id ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'points' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $pt WHERE user_id = %d AND action = 'check_in'",
            absint( $user_id )
        ) );
    }

    /**
     * Record a check-in.
     */
    public static function record( $user_id, $mosque_id ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'points' );

        $is_friday = ( date( 'N' ) == 5 );
        $pts = $is_friday ? self::POINTS_JUMUAH : self::POINTS_NORMAL;

        $wpdb->insert( $pt, [
            'user_id'   => (int) $user_id,
            'mosque_id' => (int) $mosque_id,
            'action'    => 'check_in',
            'points'    => $pts,
        ] );

        // Update user total points
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . YNJ_DB::table( 'users' ) . " SET total_points = total_points + %d WHERE id = %d",
            $pts, (int) $user_id
        ) );

        do_action( 'ynj_user_checked_in', $user_id, $mosque_id, $pts );

        return [ 'points' => $pts, 'is_jumuah' => $is_friday ];
    }
}
