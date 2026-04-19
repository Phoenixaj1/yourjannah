<?php
/**
 * Patrons Data Layer.
 *
 * Uses direct columns from patrons table: user_name, user_email, tier, amount_pence, status.
 *
 * @package YNJ_Patrons
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Patrons_Data {

    public static function get_patrons( $mosque_id, $status = 'active' ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'patrons' );
        $where = $wpdb->prepare( "WHERE mosque_id = %d", (int) $mosque_id );
        if ( $status !== 'all' ) $where .= $wpdb->prepare( " AND status = %s", $status );
        return $wpdb->get_results( "SELECT * FROM $pt $where ORDER BY amount_pence DESC, created_at DESC" ) ?: [];
    }

    /**
     * Get a user's highest-value active patron record (with mosque name).
     */
    public static function get_user_patron( $user_id ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'patrons' );
        $mt = YNJ_DB::table( 'mosques' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT p.*, m.name AS mosque_name, m.slug AS mosque_slug FROM $pt p
             LEFT JOIN $mt m ON m.id = p.mosque_id
             WHERE p.user_id = %d AND p.status = 'active' ORDER BY p.amount_pence DESC LIMIT 1",
            absint( $user_id )
        ) );
    }

    public static function get_all_patrons( $args = [] ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'patrons' );
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
            $where .= $wpdb->prepare( " AND (p.user_name LIKE %s OR p.user_email LIKE %s)", $like, $like );
        }

        $rows = $wpdb->get_results(
            "SELECT p.*, m.name AS mosque_name FROM $pt p LEFT JOIN $mt m ON m.id = p.mosque_id $where ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset"
        ) ?: [];
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt p $where" );
        return [ 'patrons' => $rows, 'total' => $total ];
    }

    public static function get_analytics( $mosque_id ) {
        global $wpdb;
        $pt  = YNJ_DB::table( 'patrons' );
        $sub = YNJ_DB::table( 'user_subscriptions' );
        $mid = (int) $mosque_id;

        $active    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE mosque_id=%d AND status='active'", $mid ) );
        $mrr_pence = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_pence),0) FROM $pt WHERE mosque_id=%d AND status='active'", $mid ) );
        $congregation = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sub WHERE mosque_id=%d AND status='active'", $mid ) );
        $penetration  = $congregation > 0 ? round( $active / $congregation * 100, 1 ) : 0;
        $avg_pence    = $active > 0 ? round( $mrr_pence / $active ) : 0;

        $tiers = $wpdb->get_results( $wpdb->prepare(
            "SELECT tier, COUNT(*) AS count, SUM(amount_pence) AS revenue_pence FROM $pt WHERE mosque_id=%d AND status='active' GROUP BY tier ORDER BY revenue_pence DESC", $mid
        ) ) ?: [];

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

    public static function get_cross_mosque_analytics() {
        global $wpdb;
        $pt  = YNJ_DB::table( 'patrons' );
        $sub = YNJ_DB::table( 'user_subscriptions' );
        $mt  = YNJ_DB::table( 'mosques' );

        return $wpdb->get_results(
            "SELECT m.id, m.name, m.city,
                    COALESCE(p.patron_count,0) AS patron_count,
                    COALESCE(p.mrr_pence,0) AS mrr_pence,
                    COALESCE(s.member_count,0) AS member_count,
                    CASE WHEN COALESCE(s.member_count,0)>0 THEN ROUND(COALESCE(p.patron_count,0)/s.member_count*100,1) ELSE 0 END AS penetration_pct
             FROM $mt m
             LEFT JOIN (SELECT mosque_id, COUNT(*) AS patron_count, SUM(amount_pence) AS mrr_pence FROM $pt WHERE status='active' GROUP BY mosque_id) p ON p.mosque_id=m.id
             LEFT JOIN (SELECT mosque_id, COUNT(*) AS member_count FROM $sub WHERE status='active' GROUP BY mosque_id) s ON s.mosque_id=m.id
             WHERE m.status IN ('active','unclaimed') ORDER BY COALESCE(p.mrr_pence,0) DESC"
        ) ?: [];
    }
}
