<?php
/**
 * Revenue Share WP Admin Page.
 *
 * @package YNJ_Revenue_Share
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Revenue_Share_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_submenu_page(
            'yn-jannah',
            'Revenue Share',
            'Revenue Share',
            'manage_options',
            'ynj-revenue-share',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        // Handle payout
        if ( isset( $_POST['ynj_payout_mosque'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'ynj_revenue_payout' ) ) {
            $mosque_id = absint( $_POST['ynj_payout_mosque'] );
            $ref = sanitize_text_field( $_POST['ynj_payout_ref'] ?? '' );
            $payout_id = YNJ_Revenue_Share::create_payout( $mosque_id, 'bank_transfer', $ref );
            if ( $payout_id ) {
                echo '<div class="notice notice-success"><p>Payout #' . $payout_id . ' processed successfully.</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>No pending balance to pay out.</p></div>';
            }
        }

        $stats    = YNJ_Revenue_Share::get_platform_stats();
        $balances = YNJ_Revenue_Share::get_all_balances();
        ?>
        <div class="wrap">
            <h1>Revenue Share — 5% to Mosques</h1>
            <p>When congregation members donate to charitable causes, 5% is credited back to their masjid.</p>

            <div style="display:flex;gap:16px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#287e61;">&pound;<?php echo number_format( $stats['total_shared_pence'] / 100, 2 ); ?></div>
                    <div style="font-size:12px;color:#666;">Total Shared</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#d97706;">&pound;<?php echo number_format( $stats['total_pending_pence'] / 100, 2 ); ?></div>
                    <div style="font-size:12px;color:#666;">Pending Payout</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#16a34a;">&pound;<?php echo number_format( $stats['total_paid_pence'] / 100, 2 ); ?></div>
                    <div style="font-size:12px;color:#666;">Paid Out</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo (int) $stats['total_donations']; ?></div>
                    <div style="font-size:12px;color:#666;">Donations</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo (int) $stats['mosques_earning']; ?></div>
                    <div style="font-size:12px;color:#666;">Mosques Earning</div>
                </div>
            </div>

            <h2>Mosque Balances</h2>
            <?php if ( empty( $balances ) ) : ?>
                <p style="color:#666;">No revenue shares recorded yet. Shares are created when charitable donations succeed.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Mosque</th>
                        <th>Pending Balance</th>
                        <th>Lifetime Earned</th>
                        <th>Total Shares</th>
                        <th>Unique Donors</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $balances as $b ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $b->mosque_name ?: 'Mosque #' . $b->mosque_id ); ?></strong></td>
                        <td style="font-weight:700;color:<?php echo $b->pending_pence > 0 ? '#d97706' : '#666'; ?>;">
                            &pound;<?php echo number_format( $b->pending_pence / 100, 2 ); ?>
                        </td>
                        <td>&pound;<?php echo number_format( $b->lifetime_pence / 100, 2 ); ?></td>
                        <td><?php echo (int) $b->total_shares; ?></td>
                        <td><?php echo (int) $b->unique_donors; ?></td>
                        <td>
                            <?php if ( $b->pending_pence > 0 ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'ynj_revenue_payout' ); ?>
                                <input type="hidden" name="ynj_payout_mosque" value="<?php echo (int) $b->mosque_id; ?>">
                                <input type="text" name="ynj_payout_ref" placeholder="Payment ref" style="width:120px;font-size:12px;">
                                <button type="submit" class="button button-small" onclick="return confirm('Process payout of £<?php echo number_format( $b->pending_pence / 100, 2 ); ?> to <?php echo esc_attr( $b->mosque_name ); ?>?');">
                                    Pay Out
                                </button>
                            </form>
                            <?php else : ?>
                            <span style="color:#999;">No pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
