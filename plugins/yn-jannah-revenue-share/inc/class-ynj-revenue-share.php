<?php
/**
 * Revenue Share Data Layer.
 *
 * When a charitable donation succeeds, 5% is credited to the donor's masjid.
 *
 * @package YNJ_Revenue_Share
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Revenue_Share {

    const SHARE_PERCENT = 5;

    /**
     * Hook handler: called when any donation succeeds.
     *
     * @param int    $donation_id  Donation row ID.
     * @param object $donation     Full donation row.
     */
    public static function on_donation_succeeded( $donation_id, $donation ) {
        $mosque_id    = (int) ( $donation->mosque_id ?? 0 );
        $amount_pence = (int) ( $donation->amount_pence ?? 0 );

        if ( ! $mosque_id || $amount_pence < 100 ) return;

        // Only share on charitable/sadaqah donations (not direct masjid fund donations)
        $shareable_types = [ 'sadaqah', 'most_needed', 'orphans', 'education', 'water', 'food', 'emergency', 'masjid_build' ];
        $fund_type = $donation->fund_type ?? '';
        if ( ! in_array( $fund_type, $shareable_types, true ) ) return;

        // Prevent duplicate shares
        global $wpdb;
        $table = YNJ_DB::table( 'revenue_shares' );
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE donation_id = %d", $donation_id
        ) );
        if ( $exists ) return;

        $share_pence = (int) floor( $amount_pence * self::SHARE_PERCENT / 100 );
        if ( $share_pence < 1 ) return;

        // Resolve donor's user ID if possible
        $donor_user_id = 0;
        $donor_email = $donation->donor_email ?? '';
        if ( $donor_email ) {
            $ut = YNJ_DB::table( 'users' );
            $donor_user_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $ut WHERE email = %s LIMIT 1", $donor_email
            ) );
        }

        $wpdb->insert( $table, [
            'mosque_id'             => $mosque_id,
            'donation_id'           => $donation_id,
            'donor_user_id'         => $donor_user_id,
            'donation_amount_pence' => $amount_pence,
            'share_amount_pence'    => $share_pence,
            'cause'                 => $fund_type,
            'status'                => 'pending',
        ] );

        error_log( "[YNJ Revenue Share] Recorded {$share_pence}p for mosque #{$mosque_id} from donation #{$donation_id}" );
    }

    /**
     * Get pending balance for a mosque (unpaid shares).
     */
    public static function get_mosque_balance( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'revenue_shares' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(share_amount_pence), 0) FROM $t WHERE mosque_id = %d AND status = 'pending'",
            absint( $mosque_id )
        ) );
    }

    /**
     * Get earnings breakdown for a mosque.
     */
    public static function get_mosque_earnings( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'revenue_shares' );
        $mid = absint( $mosque_id );

        $lifetime = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(share_amount_pence), 0) FROM $t WHERE mosque_id = %d", $mid
        ) );

        $this_month = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(share_amount_pence), 0) FROM $t WHERE mosque_id = %d AND created_at >= %s",
            $mid, date( 'Y-m-01' )
        ) );

        $by_cause = $wpdb->get_results( $wpdb->prepare(
            "SELECT cause, SUM(share_amount_pence) AS total_pence, COUNT(*) AS donation_count
             FROM $t WHERE mosque_id = %d GROUP BY cause ORDER BY total_pence DESC",
            $mid
        ) ) ?: [];

        $donation_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t WHERE mosque_id = %d", $mid
        ) );

        return [
            'lifetime_pence'  => $lifetime,
            'this_month_pence' => $this_month,
            'donation_count'  => $donation_count,
            'by_cause'        => $by_cause,
            'pending_pence'   => self::get_mosque_balance( $mid ),
        ];
    }

    /**
     * Get unique donor count for a mosque's revenue shares.
     */
    public static function get_mosque_donors( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'revenue_shares' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT donor_user_id) FROM $t WHERE mosque_id = %d AND donor_user_id > 0",
            absint( $mosque_id )
        ) );
    }

    /**
     * Get all mosque balances (admin view).
     */
    public static function get_all_balances() {
        global $wpdb;
        $t  = YNJ_DB::table( 'revenue_shares' );
        $mt = YNJ_DB::table( 'mosques' );

        return $wpdb->get_results(
            "SELECT rs.mosque_id, m.name AS mosque_name,
                    SUM(CASE WHEN rs.status = 'pending' THEN rs.share_amount_pence ELSE 0 END) AS pending_pence,
                    SUM(rs.share_amount_pence) AS lifetime_pence,
                    COUNT(*) AS total_shares,
                    COUNT(DISTINCT rs.donor_user_id) AS unique_donors
             FROM $t rs
             LEFT JOIN $mt m ON m.id = rs.mosque_id
             GROUP BY rs.mosque_id
             ORDER BY pending_pence DESC"
        ) ?: [];
    }

    /**
     * Create a payout — batch mark pending shares as paid.
     */
    public static function create_payout( $mosque_id, $method = 'bank_transfer', $reference = '' ) {
        global $wpdb;
        $st = YNJ_DB::table( 'revenue_shares' );
        $pt = YNJ_DB::table( 'revenue_payouts' );
        $mid = absint( $mosque_id );

        $pending = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, share_amount_pence FROM $st WHERE mosque_id = %d AND status = 'pending'", $mid
        ) );

        if ( empty( $pending ) ) return false;

        $total_pence = 0;
        $ids = [];
        foreach ( $pending as $s ) {
            $total_pence += (int) $s->share_amount_pence;
            $ids[] = (int) $s->id;
        }

        // Create payout record
        $wpdb->insert( $pt, [
            'mosque_id'         => $mid,
            'amount_pence'      => $total_pence,
            'shares_count'      => count( $ids ),
            'payment_method'    => sanitize_text_field( $method ),
            'payment_reference' => sanitize_text_field( $reference ),
            'status'            => 'completed',
            'completed_at'      => current_time( 'mysql' ),
        ] );
        $payout_id = (int) $wpdb->insert_id;

        // Mark shares as paid
        $id_list = implode( ',', $ids );
        $wpdb->query( "UPDATE $st SET status = 'paid', paid_at = NOW(), payout_id = $payout_id WHERE id IN ($id_list)" );

        return $payout_id;
    }

    /**
     * Get payout history for a mosque.
     */
    public static function get_payouts( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'revenue_payouts' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE mosque_id = %d ORDER BY created_at DESC LIMIT 50",
            absint( $mosque_id )
        ) ) ?: [];
    }

    /**
     * Get platform-wide stats.
     */
    public static function get_platform_stats() {
        global $wpdb;
        $t = YNJ_DB::table( 'revenue_shares' );
        return [
            'total_shared_pence'   => (int) $wpdb->get_var( "SELECT COALESCE(SUM(share_amount_pence), 0) FROM $t" ),
            'total_pending_pence'  => (int) $wpdb->get_var( "SELECT COALESCE(SUM(share_amount_pence), 0) FROM $t WHERE status = 'pending'" ),
            'total_paid_pence'     => (int) $wpdb->get_var( "SELECT COALESCE(SUM(share_amount_pence), 0) FROM $t WHERE status = 'paid'" ),
            'total_donations'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ),
            'mosques_earning'      => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT mosque_id) FROM $t" ),
        ];
    }
}
