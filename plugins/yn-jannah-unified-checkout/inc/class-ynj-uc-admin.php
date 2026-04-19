<?php
/**
 * Unified Checkout WP Admin — transactions list.
 * @package YNJ_Unified_Checkout
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_UC_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_submenu_page( 'yn-jannah', 'Transactions', 'Transactions', 'manage_options', 'ynj-transactions', [ __CLASS__, 'render_page' ] );
    }

    public static function render_page() {
        global $wpdb;
        $t  = YNJ_DB::table( 'transactions' );
        $mt = YNJ_DB::table( 'mosques' );

        // Stats
        $total_succeeded = (int) $wpdb->get_var( "SELECT COALESCE(SUM(total_pence), 0) FROM $t WHERE status = 'succeeded'" );
        $count_succeeded = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'succeeded'" );
        $count_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'pending'" );
        $count_today     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'succeeded' AND DATE(completed_at) = %s", date( 'Y-m-d' ) ) );

        // Recent transactions
        $txns = $wpdb->get_results(
            "SELECT t.*, m.name AS mosque_name FROM $t t LEFT JOIN $mt m ON m.id = t.mosque_id ORDER BY t.created_at DESC LIMIT 100"
        ) ?: [];
        ?>
        <div class="wrap">
            <h1>Transactions</h1>
            <p>All payments processed through the unified checkout.</p>

            <div style="display:flex;gap:16px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#287e61;">&pound;<?php echo number_format( $total_succeeded / 100, 2 ); ?></div>
                    <div style="font-size:12px;color:#666;">Total Revenue</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo $count_succeeded; ?></div>
                    <div style="font-size:12px;color:#666;">Completed</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#d97706;"><?php echo $count_pending; ?></div>
                    <div style="font-size:12px;color:#666;">Pending</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo $count_today; ?></div>
                    <div style="font-size:12px;color:#666;">Today</div>
                </div>
            </div>

            <?php if ( empty( $txns ) ) : ?>
                <p style="color:#666;">No transactions yet.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>ID</th><th>Type</th><th>Mosque</th><th>Donor</th><th>Amount</th><th>Tip</th><th>Total</th><th>Freq</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php foreach ( $txns as $tx ) : ?>
                    <tr>
                        <td><code style="font-size:10px;"><?php echo esc_html( substr( $tx->transaction_id, 0, 16 ) ); ?></code></td>
                        <td><?php echo esc_html( $tx->item_type ); ?></td>
                        <td><?php echo esc_html( $tx->mosque_name ?: '—' ); ?></td>
                        <td><?php echo esc_html( $tx->donor_email ); ?></td>
                        <td>&pound;<?php echo number_format( $tx->amount_pence / 100, 2 ); ?></td>
                        <td><?php echo $tx->tip_pence > 0 ? '£' . number_format( $tx->tip_pence / 100, 2 ) : '—'; ?></td>
                        <td><strong>&pound;<?php echo number_format( $tx->total_pence / 100, 2 ); ?></strong></td>
                        <td><?php echo esc_html( $tx->frequency ); ?></td>
                        <td><?php
                            if ( $tx->status === 'succeeded' ) echo '<span style="color:#16a34a;font-weight:700;">✓</span>';
                            elseif ( $tx->status === 'pending' ) echo '<span style="color:#d97706;">⏳</span>';
                            elseif ( $tx->status === 'failed' ) echo '<span style="color:#dc2626;">✗</span>';
                            else echo esc_html( $tx->status );
                        ?></td>
                        <td style="font-size:12px;"><?php echo esc_html( $tx->created_at ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
