<?php
/**
 * People Data Layer — community members across mosques.
 *
 * @package YNJ_People
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_People {

    /**
     * Get members for a mosque.
     */
    public static function get_members( $mosque_id, $args = [] ) {
        global $wpdb;
        $ut  = YNJ_DB::table( 'users' );
        $st  = YNJ_DB::table( 'user_subscriptions' );
        $limit  = (int) ( $args['limit'] ?? 50 );
        $offset = (int) ( $args['offset'] ?? 0 );
        $search = $args['search'] ?? '';

        $where = $wpdb->prepare( "WHERE s.mosque_id = %d AND s.status = 'active'", (int) $mosque_id );
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
            $where .= $wpdb->prepare( " AND (u.display_name LIKE %s OR u.email LIKE %s)", $like, $like );
        }

        $rows = $wpdb->get_results(
            "SELECT u.id, u.display_name, u.email, u.total_points, u.created_at, s.created_at AS joined_at
             FROM $ut u
             JOIN $st s ON s.user_id = u.id
             $where ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset"
        ) ?: [];

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $ut u JOIN $st s ON s.user_id = u.id $where"
        );

        return [ 'members' => $rows, 'total' => $total ];
    }

    /**
     * Get all members across all mosques.
     */
    public static function get_all_members( $args = [] ) {
        global $wpdb;
        $ut = YNJ_DB::table( 'users' );
        $st = YNJ_DB::table( 'user_subscriptions' );
        $mt = YNJ_DB::table( 'mosques' );

        $limit    = (int) ( $args['limit'] ?? 50 );
        $offset   = (int) ( $args['offset'] ?? 0 );
        $search   = $args['search'] ?? '';
        $mosque   = (int) ( $args['mosque_id'] ?? 0 );

        $where = "WHERE 1=1";
        if ( $mosque ) {
            $where .= $wpdb->prepare( " AND u.id IN (SELECT user_id FROM $st WHERE mosque_id = %d AND status = 'active')", $mosque );
        }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
            $where .= $wpdb->prepare( " AND (u.display_name LIKE %s OR u.email LIKE %s)", $like, $like );
        }

        $rows = $wpdb->get_results(
            "SELECT u.id, u.display_name, u.email, u.total_points, u.created_at,
                    GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ') AS mosques
             FROM $ut u
             LEFT JOIN $st s ON s.user_id = u.id AND s.status = 'active'
             LEFT JOIN $mt m ON m.id = s.mosque_id
             $where
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT $limit OFFSET $offset"
        ) ?: [];

        $total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT u.id) FROM $ut u $where" );

        return [ 'members' => $rows, 'total' => $total ];
    }

    /**
     * Get single member with all subscriptions.
     */
    public static function get_member( $user_id ) {
        global $wpdb;
        $ut = YNJ_DB::table( 'users' );
        $st = YNJ_DB::table( 'user_subscriptions' );
        $mt = YNJ_DB::table( 'mosques' );

        $user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ut WHERE id = %d", (int) $user_id ) );
        if ( ! $user ) return null;

        $user->subscriptions = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, m.name AS mosque_name, m.city AS mosque_city
             FROM $st s LEFT JOIN $mt m ON m.id = s.mosque_id
             WHERE s.user_id = %d ORDER BY s.created_at DESC",
            (int) $user_id
        ) ) ?: [];

        return $user;
    }

    /**
     * Get member stats for a mosque.
     */
    public static function get_stats( $mosque_id = 0 ) {
        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        $week_ago = date( 'Y-m-d', strtotime( '-7 days' ) );

        $where = $mosque_id ? $wpdb->prepare( "AND mosque_id = %d", (int) $mosque_id ) : '';

        return [
            'total'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $st WHERE status = 'active' $where" ),
            'new_week' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $st WHERE status = 'active' $where AND created_at >= %s", $week_ago
            ) ),
        ];
    }
}
