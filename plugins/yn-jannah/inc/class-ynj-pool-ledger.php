<?php
/**
 * YourJannah Pool Ledger
 *
 * Immutable financial record of all payments flowing through the platform.
 * Tracks what's owed to each mosque and records payouts.
 *
 * Revenue split: 90% mosque, 10% YourJannah platform fee.
 * Mosque share paid out via bank transfer (tracked in pool_payouts table).
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Pool_Ledger {

    /** Platform fee percentage */
    const PLATFORM_FEE_PCT = 10;

    /**
     * Record a payment in the pool ledger.
     *
     * @param array $data Ledger entry data.
     * @return int|false  Inserted row ID or false on failure.
     */
    public static function record( $data ) {
        global $wpdb;

        $mosque_id    = (int) ( $data['mosque_id'] ?? 0 );
        $gross        = (int) ( $data['gross_pence'] ?? 0 );
        $payment_type = sanitize_text_field( $data['payment_type'] ?? '' );

        if ( ! $mosque_id || ! $gross || ! $payment_type ) return false;

        // Calculate 90/10 split
        $platform_fee = (int) round( $gross * self::PLATFORM_FEE_PCT / 100 );
        $net_mosque   = $gross - $platform_fee;

        $row = [
            'entry_ref'              => self::generate_ref(),
            'mosque_id'              => $mosque_id,
            'entry_type'             => sanitize_text_field( $data['entry_type'] ?? 'payment' ),
            'payment_type'           => $payment_type,
            'item_id'                => (int) ( $data['item_id'] ?? 0 ),
            'gross_pence'            => $gross,
            'platform_fee_pence'     => $platform_fee,
            'net_to_mosque_pence'    => $net_mosque,
            'currency'               => sanitize_text_field( $data['currency'] ?? 'gbp' ),
            'stripe_payment_id'      => sanitize_text_field( $data['stripe_payment_id'] ?? '' ),
            'stripe_subscription_id' => sanitize_text_field( $data['stripe_subscription_id'] ?? '' ),
            'payer_name'             => sanitize_text_field( $data['payer_name'] ?? '' ),
            'payer_email'            => sanitize_email( $data['payer_email'] ?? '' ),
            'description'            => sanitize_text_field( $data['description'] ?? '' ),
        ];

        $wpdb->insert( YNJ_DB::table( 'pool_ledger' ), $row );
        return $wpdb->insert_id ?: false;
    }

    /**
     * Generate unique entry reference.
     */
    private static function generate_ref() {
        return 'YNJ-' . strtoupper( wp_generate_password( 8, false ) );
    }

    /**
     * Get outstanding balances per mosque (what's owed, what's paid out).
     *
     * @return array Array of mosque balance objects.
     */
    public static function get_outstanding_balances() {
        global $wpdb;
        $lt = YNJ_DB::table( 'pool_ledger' );
        $pt = YNJ_DB::table( 'pool_payouts' );
        $mt = YNJ_DB::table( 'mosques' );

        return $wpdb->get_results(
            "SELECT
                l.mosque_id,
                m.name AS mosque_name,
                m.slug AS mosque_slug,
                COUNT(l.id) AS entry_count,
                SUM(l.gross_pence) AS total_gross,
                SUM(l.platform_fee_pence) AS total_platform_fee,
                SUM(l.net_to_mosque_pence) AS total_net_owed,
                COALESCE(po.total_paid, 0) AS total_paid_out,
                SUM(l.net_to_mosque_pence) - COALESCE(po.total_paid, 0) AS outstanding
             FROM $lt l
             JOIN $mt m ON m.id = l.mosque_id
             LEFT JOIN (
                SELECT mosque_id, SUM(amount_pence) AS total_paid
                FROM $pt WHERE status IN ('sent','confirmed')
                GROUP BY mosque_id
             ) po ON po.mosque_id = l.mosque_id
             WHERE l.entry_type IN ('payment','recurring')
             GROUP BY l.mosque_id
             HAVING outstanding > 0 OR total_paid_out > 0
             ORDER BY outstanding DESC"
        ) ?: [];
    }

    /**
     * Get unpaid ledger entry IDs for a mosque.
     */
    public static function get_unpaid_entry_ids( $mosque_id ) {
        global $wpdb;
        $lt = YNJ_DB::table( 'pool_ledger' );
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM $lt WHERE mosque_id = %d AND payout_id IS NULL AND entry_type IN ('payment','recurring') ORDER BY created_at ASC",
            $mosque_id
        ) );
    }

    /**
     * Link ledger entries to a payout.
     */
    public static function link_to_payout( $entry_ids, $payout_id ) {
        if ( empty( $entry_ids ) ) return;
        global $wpdb;
        $lt  = YNJ_DB::table( 'pool_ledger' );
        $ids = implode( ',', array_map( 'intval', $entry_ids ) );
        $wpdb->query( $wpdb->prepare(
            "UPDATE $lt SET payout_id = %d WHERE id IN ($ids)",
            $payout_id
        ) );
    }

    /**
     * Record a payout to a mosque.
     *
     * @param array $data Payout data.
     * @return int|false  Payout ID or false.
     */
    public static function record_payout( $data ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'pool_payouts' );

        $mosque_id = (int) ( $data['mosque_id'] ?? 0 );
        if ( ! $mosque_id ) return false;

        $entry_ids = self::get_unpaid_entry_ids( $mosque_id );
        if ( empty( $entry_ids ) ) return false;

        // Sum unpaid entries
        $lt = YNJ_DB::table( 'pool_ledger' );
        $ids_str = implode( ',', array_map( 'intval', $entry_ids ) );
        $sum = (int) $wpdb->get_var( "SELECT SUM(net_to_mosque_pence) FROM $lt WHERE id IN ($ids_str)" );

        $dates = $wpdb->get_row( "SELECT MIN(created_at) AS covers_from, MAX(created_at) AS covers_to FROM $lt WHERE id IN ($ids_str)" );

        $payout_ref = 'PO-' . strtoupper( wp_generate_password( 8, false ) );

        $wpdb->insert( $pt, [
            'payout_ref'     => $payout_ref,
            'mosque_id'      => $mosque_id,
            'amount_pence'   => $sum,
            'currency'       => 'gbp',
            'method'         => sanitize_text_field( $data['method'] ?? 'bank_transfer' ),
            'bank_reference' => sanitize_text_field( $data['bank_reference'] ?? '' ),
            'entries_count'  => count( $entry_ids ),
            'covers_from'    => $dates->covers_from ?? null,
            'covers_to'      => $dates->covers_to ?? null,
            'status'         => 'sent',
            'notes'          => sanitize_text_field( $data['notes'] ?? '' ),
            'sent_at'        => current_time( 'mysql', true ),
            'created_by'     => get_current_user_id(),
        ] );

        $payout_id = $wpdb->insert_id;
        if ( $payout_id ) {
            self::link_to_payout( $entry_ids, $payout_id );
        }

        return $payout_id ?: false;
    }

    /**
     * Get payout history.
     */
    public static function get_payouts( $limit = 50 ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'pool_payouts' );
        $mt = YNJ_DB::table( 'mosques' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, m.name AS mosque_name, m.slug AS mosque_slug
             FROM $pt p
             JOIN $mt m ON m.id = p.mosque_id
             ORDER BY p.created_at DESC
             LIMIT %d",
            $limit
        ) ) ?: [];
    }

    /**
     * Get platform revenue summary.
     */
    public static function get_platform_summary() {
        global $wpdb;
        $lt = YNJ_DB::table( 'pool_ledger' );
        return $wpdb->get_row(
            "SELECT
                COUNT(*) AS total_entries,
                COALESCE(SUM(gross_pence), 0) AS total_gross,
                COALESCE(SUM(platform_fee_pence), 0) AS total_platform_revenue,
                COALESCE(SUM(net_to_mosque_pence), 0) AS total_owed_mosques
             FROM $lt
             WHERE entry_type IN ('payment','recurring')"
        );
    }
}
