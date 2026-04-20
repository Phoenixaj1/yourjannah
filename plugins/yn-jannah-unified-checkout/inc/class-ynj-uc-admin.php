<?php
/**
 * Unified Checkout WP Admin — transactions dashboard with type filtering.
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

    private static function type_label( $type ) {
        $labels = [
            'donation'             => [ '💝', 'Donation' ],
            'sadaqah'              => [ '💰', 'Sadaqah' ],
            'patron'               => [ '🏅', 'Patron' ],
            'store'                => [ '💬', 'Superchat' ],
            'event_ticket'         => [ '🎫', 'Event Ticket' ],
            'event_donation'       => [ '❤️', 'Event Donation' ],
            'room_booking'         => [ '🏠', 'Room Booking' ],
            'class_enrolment'      => [ '📚', 'Class' ],
            'business_sponsor'     => [ '⭐', 'Sponsor' ],
            'sponsor'              => [ '⭐', 'Sponsor' ],
            'professional_service' => [ '🔧', 'Service' ],
            'service'              => [ '🔧', 'Service' ],
            'tip'                  => [ '🤲', 'Platform Tip' ],
            'platform_donate'      => [ '🤲', 'Platform' ],
            'multi'                => [ '📦', 'Multi-item' ],
        ];
        $l = $labels[ $type ] ?? [ '📋', ucfirst( str_replace( '_', ' ', $type ?: 'unknown' ) ) ];
        return '<span style="white-space:nowrap;">' . $l[0] . ' ' . esc_html( $l[1] ) . '</span>';
    }

    public static function render_page() {
        global $wpdb;
        $t  = YNJ_DB::table( 'transactions' );
        $mt = YNJ_DB::table( 'mosques' );

        // Filter by type
        $filter_type = sanitize_text_field( $_GET['type'] ?? '' );
        $where = '';
        if ( $filter_type ) {
            $where = $wpdb->prepare( " AND t.item_type = %s", $filter_type );
        }

        // Stats
        $total_succeeded = (int) $wpdb->get_var( "SELECT COALESCE(SUM(total_pence), 0) FROM $t WHERE status = 'succeeded'" );
        $count_succeeded = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'succeeded'" );
        $count_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'pending'" );
        $count_today     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'succeeded' AND DATE(completed_at) = %s", date( 'Y-m-d' ) ) );

        // Type breakdown
        $type_counts = $wpdb->get_results( "SELECT item_type, COUNT(*) as cnt, SUM(CASE WHEN status='succeeded' THEN total_pence ELSE 0 END) as revenue FROM $t GROUP BY item_type ORDER BY cnt DESC" ) ?: [];

        // Transactions
        $txns = $wpdb->get_results(
            "SELECT t.*, m.name AS mosque_name FROM $t t LEFT JOIN $mt m ON m.id = t.mosque_id WHERE 1=1 $where ORDER BY t.created_at DESC LIMIT 200"
        ) ?: [];
        ?>
        <div class="wrap">
            <h1>💰 Transactions</h1>
            <p>All payments processed through YourJannah. <?php if ( get_option( 'ynj_cash_payment_mode' ) ) : ?><span style="background:#f59e0b;color:#1a1a2e;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;">💵 CASH MODE ON</span><?php endif; ?></p>

            <!-- Stats -->
            <div style="display:flex;gap:12px;margin:20px 0;flex-wrap:wrap;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px 24px;text-align:center;min-width:120px;">
                    <div style="font-size:28px;font-weight:800;color:#287e61;">&pound;<?php echo number_format( $total_succeeded / 100, 2 ); ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Total Revenue</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px 24px;text-align:center;min-width:100px;">
                    <div style="font-size:28px;font-weight:800;color:#16a34a;"><?php echo $count_succeeded; ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Completed</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px 24px;text-align:center;min-width:100px;">
                    <div style="font-size:28px;font-weight:800;color:#d97706;"><?php echo $count_pending; ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Pending</div>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px 24px;text-align:center;min-width:100px;">
                    <div style="font-size:28px;font-weight:800;"><?php echo $count_today; ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Today</div>
                </div>
            </div>

            <!-- Type filters -->
            <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
                <a href="<?php echo admin_url( 'admin.php?page=ynj-transactions' ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo ! $filter_type ? 'background:#287e61;color:#fff;' : 'background:#f3f4f6;color:#333;'; ?>">All (<?php echo $count_succeeded + $count_pending; ?>)</a>
                <?php foreach ( $type_counts as $tc ) : ?>
                <a href="<?php echo admin_url( 'admin.php?page=ynj-transactions&type=' . urlencode( $tc->item_type ) ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo $filter_type === $tc->item_type ? 'background:#287e61;color:#fff;' : 'background:#f3f4f6;color:#333;'; ?>"><?php echo self::type_label( $tc->item_type ); ?> (<?php echo (int) $tc->cnt; ?>)</a>
                <?php endforeach; ?>
            </div>

            <!-- Table -->
            <?php if ( empty( $txns ) ) : ?>
                <p style="color:#666;">No transactions<?php echo $filter_type ? ' of type "' . esc_html( $filter_type ) . '"' : ''; ?>.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="border-radius:10px;overflow:hidden;">
                <thead>
                    <tr>
                        <th style="width:60px;">Type</th>
                        <th>Label</th>
                        <th>Mosque</th>
                        <th>Donor</th>
                        <th style="width:80px;">Amount</th>
                        <th style="width:80px;">Total</th>
                        <th style="width:70px;">Freq</th>
                        <th style="width:70px;">Method</th>
                        <th style="width:50px;">Status</th>
                        <th style="width:130px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $txns as $tx ) :
                        $is_cash = strpos( $tx->stripe_payment_intent ?? '', 'test_' ) === 0;
                        $freq_label = $tx->frequency === 'once' ? '—' : ucfirst( $tx->frequency );
                    ?>
                    <tr>
                        <td><?php echo self::type_label( $tx->item_type ); ?></td>
                        <td>
                            <strong style="font-size:13px;"><?php echo esc_html( $tx->item_label ?: '—' ); ?></strong>
                            <div style="font-size:10px;color:#999;"><?php echo esc_html( substr( $tx->transaction_id, 0, 20 ) ); ?></div>
                        </td>
                        <td style="font-size:12px;"><?php echo esc_html( $tx->mosque_name ?: '—' ); ?></td>
                        <td>
                            <div style="font-size:12px;"><?php echo esc_html( $tx->donor_name ?: '—' ); ?></div>
                            <div style="font-size:10px;color:#999;"><?php echo esc_html( $tx->donor_email ); ?></div>
                        </td>
                        <td>&pound;<?php echo number_format( $tx->amount_pence / 100, 2 ); ?></td>
                        <td><strong>&pound;<?php echo number_format( $tx->total_pence / 100, 2 ); ?></strong></td>
                        <td style="font-size:11px;"><?php echo esc_html( $freq_label ); ?></td>
                        <td>
                            <?php if ( $is_cash ) : ?>
                                <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;">💵 Cash</span>
                            <?php else : ?>
                                <span style="background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;">💳 Stripe</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $tx->status === 'succeeded' ) : ?>
                                <span style="color:#16a34a;font-weight:700;">✓</span>
                            <?php elseif ( $tx->status === 'pending' ) : ?>
                                <span style="color:#d97706;">⏳</span>
                            <?php elseif ( $tx->status === 'failed' ) : ?>
                                <span style="color:#dc2626;">✗</span>
                            <?php else : ?>
                                <?php echo esc_html( $tx->status ); ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:11px;color:#666;"><?php echo esc_html( $tx->completed_at ?: $tx->created_at ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
