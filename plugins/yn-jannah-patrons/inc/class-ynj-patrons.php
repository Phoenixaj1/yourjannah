<?php
/**
 * Patrons Data Layer — analytics, tier breakdowns, congregation penetration.
 *
 * @package YNJ_Patrons
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Patrons {

    /**
     * Get patrons for a mosque.
     */
    public static function get_patrons( $mosque_id, $status = 'active' ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'patrons' );
        $ut = YNJ_DB::table( 'users' );

        $where = $wpdb->prepare( "WHERE p.mosque_id = %d", (int) $mosque_id );
        if ( $status !== 'all' ) {
            $where .= $wpdb->prepare( " AND p.status = %s", $status );
        }

        return $wpdb->get_results(
            "SELECT p.*, u.display_name, u.email
             FROM $pt p LEFT JOIN $ut u ON u.id = p.user_id
             $where ORDER BY p.amount_pence DESC, p.created_at DESC"
        ) ?: [];
    }

    /**
     * Get all patrons across all mosques.
     */
    public static function get_all_patrons( $args = [] ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'patrons' );
        $ut = YNJ_DB::table( 'users' );
        $mt = YNJ_DB::table( 'mosques' );

        $limit  = (int) ( $args['limit'] ?? 50 );
        $offset = (int) ( $args['offset'] ?? 0 );
        $status = $args['status'] ?? 'active';
        $mosque = (int) ( $args['mosque_id'] ?? 0 );
        $search = $args['search'] ?? '';

        $where = "WHERE 1=1";
        if ( $status !== 'all' ) $where .= $wpdb->prepare( " AND p.status = %s", $status );
        if ( $mosque ) $where .= $wpdb->prepare( " AND p.mosque_id = %d", $mosque );
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
            $where .= $wpdb->prepare( " AND (u.display_name LIKE %s OR u.email LIKE %s)", $like, $like );
        }

        $rows = $wpdb->get_results(
            "SELECT p.*, u.display_name, u.email, m.name AS mosque_name
             FROM $pt p
             LEFT JOIN $ut u ON u.id = p.user_id
             LEFT JOIN $mt m ON m.id = p.mosque_id
             $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset"
        ) ?: [];

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $pt p LEFT JOIN $ut u ON u.id = p.user_id $where"
        );

        return [ 'patrons' => $rows, 'total' => $total ];
    }

    /**
     * Get patron analytics for a single mosque.
     */
    public static function get_analytics( $mosque_id ) {
        global $wpdb;
        $pt  = YNJ_DB::table( 'patrons' );
        $sub = YNJ_DB::table( 'user_subscriptions' );

        $mosque_id = (int) $mosque_id;

        // Active patrons
        $active = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $pt WHERE mosque_id = %d AND status = 'active'", $mosque_id
        ) );

        // Monthly revenue
        $mrr_pence = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount_pence), 0) FROM $pt WHERE mosque_id = %d AND status = 'active'", $mosque_id
        ) );

        // Total congregation (subscribers)
        $congregation = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND status = 'active'", $mosque_id
        ) );

        // Penetration %
        $penetration = $congregation > 0 ? round( $active / $congregation * 100, 1 ) : 0;

        // Tier breakdown
        $tiers = $wpdb->get_results( $wpdb->prepare(
            "SELECT tier, COUNT(*) AS count, SUM(amount_pence) AS revenue_pence
             FROM $pt WHERE mosque_id = %d AND status = 'active' GROUP BY tier ORDER BY revenue_pence DESC",
            $mosque_id
        ) ) ?: [];

        // Average amount
        $avg_pence = $active > 0 ? round( $mrr_pence / $active ) : 0;

        return [
            'active_patrons'  => $active,
            'mrr_pence'       => $mrr_pence,
            'mrr_formatted'   => number_format( $mrr_pence / 100, 2 ),
            'congregation'    => $congregation,
            'penetration_pct' => $penetration,
            'avg_pence'       => $avg_pence,
            'avg_formatted'   => number_format( $avg_pence / 100, 2 ),
            'tiers'           => $tiers,
        ];
    }

    /**
     * Get cross-mosque analytics — all mosques compared.
     */
    public static function get_cross_mosque_analytics() {
        global $wpdb;
        $pt  = YNJ_DB::table( 'patrons' );
        $sub = YNJ_DB::table( 'user_subscriptions' );
        $mt  = YNJ_DB::table( 'mosques' );

        return $wpdb->get_results(
            "SELECT m.id, m.name, m.city,
                    COALESCE(p.patron_count, 0) AS patron_count,
                    COALESCE(p.mrr_pence, 0) AS mrr_pence,
                    COALESCE(s.member_count, 0) AS member_count,
                    CASE WHEN COALESCE(s.member_count, 0) > 0
                         THEN ROUND(COALESCE(p.patron_count, 0) / s.member_count * 100, 1)
                         ELSE 0 END AS penetration_pct
             FROM $mt m
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS patron_count, SUM(amount_pence) AS mrr_pence
                 FROM $pt WHERE status = 'active' GROUP BY mosque_id
             ) p ON p.mosque_id = m.id
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS member_count
                 FROM $sub WHERE status = 'active' GROUP BY mosque_id
             ) s ON s.mosque_id = m.id
             WHERE m.status IN ('active','unclaimed')
             ORDER BY COALESCE(p.mrr_pence, 0) DESC"
        ) ?: [];
    }

    /**
     * Single patron check.
     */
    public static function is_patron( $user_id, $mosque_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . YNJ_DB::table( 'patrons' ) . " WHERE user_id = %d AND mosque_id = %d AND status = 'active'",
            (int) $user_id, (int) $mosque_id
        ) );
    }
}
