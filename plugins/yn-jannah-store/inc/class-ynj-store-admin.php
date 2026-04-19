<?php
/**
 * Masjid Store WP Admin — view purchases, manage items.
 * @package YNJ_Store
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Store_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_submenu_page( 'yn-jannah', 'Store', 'Store', 'manage_options', 'ynj-store', [ __CLASS__, 'render_page' ] );
    }

    public static function render_page() {
        $items = YNJ_Store::get_items();

        // Get store purchases from transactions table
        global $wpdb;
        $t  = YNJ_DB::table( 'transactions' );
        $mt = YNJ_DB::table( 'mosques' );

        $total = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence), 0) FROM $t WHERE item_type = 'store' AND status = 'succeeded'" );
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE item_type = 'store' AND status = 'succeeded'" );
        $masjid_total = (int) floor( $total * YNJ_Store::MASJID_SHARE / 100 );

        $recent = $wpdb->get_results(
            "SELECT t.*, m.name AS mosque_name FROM $t t LEFT JOIN $mt m ON m.id = t.mosque_id
             WHERE t.item_type = 'store' ORDER BY t.created_at DESC LIMIT 50"
        ) ?: [];
        ?>
        <div class="wrap">
            <h1>Masjid Store — Community Shout-Outs</h1>
            <p>Community members purchase announcements. 95% goes to the masjid, 5% to YourJannah.</p>

            <div style="display:flex;gap:16px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#287e61;">&pound;<?php echo number_format( $total / 100, 2 ); ?></div>
                    <div style="font-size:12px;color:#666;">Total Sales</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#16a34a;">&pound;<?php echo number_format( $masjid_total / 100, 2 ); ?></div>
                    <div style="font-size:12px;color:#666;">To Masjids (95%)</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;"><?php echo $count; ?></div>
                    <div style="font-size:12px;color:#666;">Purchases</div>
                </div>
            </div>

            <h2>Available Items</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
                <thead><tr><th></th><th>Item</th><th>Description</th><th>Prices</th></tr></thead>
                <tbody>
                    <?php foreach ( $items as $key => $item ) : ?>
                    <tr>
                        <td style="font-size:24px;width:40px;"><?php echo $item['icon']; ?></td>
                        <td><strong><?php echo esc_html( $item['title'] ); ?></strong></td>
                        <td><?php echo esc_html( $item['description'] ); ?></td>
                        <td><?php echo implode( ', ', array_map( function( $p ) { return '£' . number_format( $p / 100, 2 ); }, $item['prices'] ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Recent Purchases</h2>
            <?php if ( empty( $recent ) ) : ?>
                <p style="color:#666;">No store purchases yet.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Item</th><th>Mosque</th><th>From</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ( $recent as $tx ) :
                        $item = $items[ $tx->fund_type ] ?? null;
                    ?>
                    <tr>
                        <td><?php echo $item ? $item['icon'] . ' ' . esc_html( $item['title'] ) : esc_html( $tx->fund_type ); ?></td>
                        <td><?php echo esc_html( $tx->mosque_name ?: '—' ); ?></td>
                        <td><?php echo esc_html( $tx->donor_name ?: $tx->donor_email ); ?></td>
                        <td>&pound;<?php echo number_format( $tx->amount_pence / 100, 2 ); ?></td>
                        <td><?php echo $tx->status === 'succeeded' ? '<span style="color:#16a34a;">✓</span>' : esc_html( $tx->status ); ?></td>
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
