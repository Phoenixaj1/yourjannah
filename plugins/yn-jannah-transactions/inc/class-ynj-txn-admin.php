<?php
/**
 * Transaction Dashboard — full admin view with filtering, details, export.
 * @package YNJ_Transactions
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Txn_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        add_menu_page(
            'Transactions',
            'Transactions',
            'manage_options',
            'ynj-transactions',
            [ __CLASS__, 'render_page' ],
            'dashicons-money-alt',
            30
        );
    }

    private static $type_labels = [
        'donation'             => [ '💝', 'Donation',       '#dcfce7', '#166534' ],
        'sadaqah'              => [ '💰', 'Sadaqah',        '#dcfce7', '#166534' ],
        'patron'               => [ '🏅', 'Patron',         '#ede9fe', '#7c3aed' ],
        'store'                => [ '💬', 'Superchat',      '#fef3c7', '#92400e' ],
        'event_ticket'         => [ '🎫', 'Event Ticket',   '#dbeafe', '#1e40af' ],
        'event_donation'       => [ '❤️', 'Event Donation', '#fce7f3', '#9d174d' ],
        'room_booking'         => [ '🏠', 'Room Booking',   '#e0f2fe', '#0369a1' ],
        'class_enrolment'      => [ '📚', 'Class',          '#ede9fe', '#7c3aed' ],
        'business_sponsor'     => [ '⭐', 'Sponsor',        '#ffedd5', '#c2410c' ],
        'sponsor'              => [ '⭐', 'Sponsor',        '#ffedd5', '#c2410c' ],
        'professional_service' => [ '🔧', 'Service',        '#ccfbf1', '#0f766e' ],
        'service'              => [ '🔧', 'Service',        '#ccfbf1', '#0f766e' ],
        'tip'                  => [ '🤲', 'Platform Tip',   '#f3f4f6', '#4b5563' ],
        'platform_donate'      => [ '🤲', 'Platform',       '#f3f4f6', '#4b5563' ],
        'multi'                => [ '📦', 'Multi-item',     '#f3f4f6', '#4b5563' ],
    ];

    private static function type_badge( $type ) {
        $l = self::$type_labels[ $type ] ?? [ '📋', ucfirst( str_replace( '_', ' ', $type ?: 'unknown' ) ), '#f3f4f6', '#333' ];
        return '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;background:' . $l[2] . ';color:' . $l[3] . ';white-space:nowrap;">' . $l[0] . ' ' . esc_html( $l[1] ) . '</span>';
    }

    public static function render_page() {
        global $wpdb;
        $t  = YNJ_DB::table( 'transactions' );
        $mt = YNJ_DB::table( 'mosques' );

        $filter_type   = sanitize_text_field( $_GET['type'] ?? '' );
        $filter_status = sanitize_text_field( $_GET['status'] ?? '' );
        $filter_method = sanitize_text_field( $_GET['method'] ?? '' );
        $search        = sanitize_text_field( $_GET['s'] ?? '' );

        $where = ' WHERE 1=1';
        if ( $filter_type )   $where .= $wpdb->prepare( ' AND t.item_type = %s', $filter_type );
        if ( $filter_status ) $where .= $wpdb->prepare( ' AND t.status = %s', $filter_status );
        if ( $filter_method === 'cash' )   $where .= " AND t.stripe_payment_intent LIKE 'test_%'";
        if ( $filter_method === 'stripe' ) $where .= " AND (t.stripe_payment_intent NOT LIKE 'test_%' OR t.stripe_payment_intent IS NULL)";
        if ( $search ) $where .= $wpdb->prepare( ' AND (t.donor_email LIKE %s OR t.donor_name LIKE %s OR t.item_label LIKE %s OR t.transaction_id LIKE %s)', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%' );

        // Stats
        $total_revenue   = (int) $wpdb->get_var( "SELECT COALESCE(SUM(total_pence), 0) FROM $t WHERE status = 'succeeded'" );
        $count_all       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
        $count_succeeded = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'succeeded'" );
        $count_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'pending'" );
        $today_revenue   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total_pence), 0) FROM $t WHERE status = 'succeeded' AND DATE(completed_at) = %s", date( 'Y-m-d' ) ) );
        $count_today     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'succeeded' AND DATE(completed_at) = %s", date( 'Y-m-d' ) ) );
        $count_cash      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE stripe_payment_intent LIKE 'test_%'" );

        // Type breakdown
        $type_counts = $wpdb->get_results( "SELECT item_type, COUNT(*) as cnt, SUM(CASE WHEN status='succeeded' THEN total_pence ELSE 0 END) as revenue FROM $t GROUP BY item_type ORDER BY revenue DESC" ) ?: [];

        // Transactions
        $txns = $wpdb->get_results(
            "SELECT t.*, m.name AS mosque_name FROM $t t LEFT JOIN $mt m ON m.id = t.mosque_id $where ORDER BY t.created_at DESC LIMIT 200"
        ) ?: [];

        $base_url = admin_url( 'admin.php?page=ynj-transactions' );
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">💰 Transactions
                <?php if ( get_option( 'ynj_cash_payment_mode' ) ) : ?>
                <span style="background:#f59e0b;color:#1a1a2e;padding:4px 14px;border-radius:10px;font-size:12px;font-weight:700;">💵 CASH MODE ON</span>
                <?php endif; ?>
            </h1>
            <p style="color:#666;margin-bottom:20px;">All payments across YourJannah — donations, superchats, patrons, bookings, and more.</p>

            <!-- ═══ STATS ═══ -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:26px;font-weight:900;color:#287e61;">&pound;<?php echo number_format( $total_revenue / 100, 2 ); ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Total Revenue</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:26px;font-weight:900;color:#287e61;">&pound;<?php echo number_format( $today_revenue / 100, 2 ); ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Today (<?php echo $count_today; ?>)</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:26px;font-weight:900;color:#16a34a;"><?php echo $count_succeeded; ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Completed</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:26px;font-weight:900;color:#d97706;"><?php echo $count_pending; ?></div>
                    <div style="font-size:11px;color:#666;font-weight:600;">Pending</div>
                </div>
                <?php if ( $count_cash ) : ?>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:26px;font-weight:900;color:#92400e;"><?php echo $count_cash; ?></div>
                    <div style="font-size:11px;color:#92400e;font-weight:600;">💵 Cash (Test)</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ═══ TYPE BREAKDOWN ═══ -->
            <?php if ( $type_counts ) : ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:24px;">
                <h3 style="font-size:14px;font-weight:800;margin:0 0 12px;">Revenue by Type</h3>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php foreach ( $type_counts as $tc ) :
                        $l = self::$type_labels[ $tc->item_type ] ?? [ '📋', ucfirst( str_replace('_',' ',$tc->item_type) ), '#f3f4f6', '#333' ];
                    ?>
                    <div style="padding:10px 16px;background:<?php echo $l[2]; ?>;border-radius:10px;text-align:center;min-width:100px;">
                        <div style="font-size:16px;font-weight:900;color:<?php echo $l[3]; ?>;">&pound;<?php echo number_format( ( $tc->revenue ?? 0 ) / 100, 0 ); ?></div>
                        <div style="font-size:11px;font-weight:700;color:<?php echo $l[3]; ?>;opacity:.8;"><?php echo $l[0] . ' ' . esc_html( $l[1] ); ?> (<?php echo (int) $tc->cnt; ?>)</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══ FILTERS ═══ -->
            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
                <a href="<?php echo esc_url( $base_url ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo ! $filter_type && ! $filter_status && ! $filter_method ? 'background:#287e61;color:#fff;' : 'background:#f3f4f6;color:#333;'; ?>">All</a>

                <?php foreach ( $type_counts as $tc ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'type', $tc->item_type, $base_url ) ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo $filter_type === $tc->item_type ? 'background:#287e61;color:#fff;' : 'background:#f3f4f6;color:#333;'; ?>"><?php echo self::type_badge( $tc->item_type ); ?></a>
                <?php endforeach; ?>

                <span style="width:1px;height:24px;background:#ddd;margin:0 4px;"></span>

                <a href="<?php echo esc_url( add_query_arg( 'status', 'succeeded', $base_url ) ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo $filter_status === 'succeeded' ? 'background:#16a34a;color:#fff;' : 'background:#f3f4f6;color:#333;'; ?>">✓ Completed</a>
                <a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $base_url ) ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo $filter_status === 'pending' ? 'background:#d97706;color:#fff;' : 'background:#f3f4f6;color:#333;'; ?>">⏳ Pending</a>
                <a href="<?php echo esc_url( add_query_arg( 'method', 'cash', $base_url ) ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo $filter_method === 'cash' ? 'background:#f59e0b;color:#1a1a2e;' : 'background:#f3f4f6;color:#333;'; ?>">💵 Cash</a>
                <a href="<?php echo esc_url( add_query_arg( 'method', 'stripe', $base_url ) ); ?>" style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;<?php echo $filter_method === 'stripe' ? 'background:#7c3aed;color:#fff;' : 'background:#f3f4f6;color:#333;'; ?>">💳 Stripe</a>

                <form method="get" action="<?php echo admin_url( 'admin.php' ); ?>" style="margin-left:auto;display:flex;gap:6px;">
                    <input type="hidden" name="page" value="ynj-transactions">
                    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search email, name, label..." style="padding:6px 12px;border:1px solid #ddd;border-radius:8px;font-size:12px;width:200px;">
                    <button type="submit" style="padding:6px 14px;background:#287e61;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">Search</button>
                </form>
            </div>

            <!-- ═══ TABLE ═══ -->
            <?php if ( empty( $txns ) ) : ?>
                <div style="text-align:center;padding:40px;color:#999;font-size:14px;">No transactions found.</div>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
                <thead style="background:#f9fafb;">
                    <tr>
                        <th style="width:110px;">Type</th>
                        <th>Item</th>
                        <th style="width:140px;">Mosque</th>
                        <th style="width:170px;">Donor</th>
                        <th style="width:75px;">Amount</th>
                        <th style="width:75px;">Total</th>
                        <th style="width:65px;">Freq</th>
                        <th style="width:65px;">Method</th>
                        <th style="width:45px;">Status</th>
                        <th style="width:140px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $txns as $tx ) :
                        $is_cash = strpos( $tx->stripe_payment_intent ?? '', 'test_' ) === 0;
                    ?>
                    <tr>
                        <td><?php echo self::type_badge( $tx->item_type ); ?></td>
                        <td>
                            <strong style="font-size:13px;"><?php echo esc_html( $tx->item_label ?: '—' ); ?></strong>
                            <?php if ( $tx->fund_type && $tx->fund_type !== 'general' && $tx->fund_type !== 'mixed' ) : ?>
                                <span style="font-size:10px;color:#999;"> — <?php echo esc_html( $tx->fund_type ); ?></span>
                            <?php endif; ?>
                            <div style="font-size:10px;color:#bbb;font-family:monospace;"><?php echo esc_html( $tx->transaction_id ); ?></div>
                        </td>
                        <td style="font-size:12px;"><?php echo esc_html( $tx->mosque_name ?: '—' ); ?></td>
                        <td>
                            <div style="font-size:12px;font-weight:600;"><?php echo esc_html( $tx->donor_name ?: '—' ); ?></div>
                            <div style="font-size:10px;color:#999;"><?php echo esc_html( $tx->donor_email ); ?></div>
                            <?php if ( $tx->donor_phone ) : ?><div style="font-size:10px;color:#999;"><?php echo esc_html( $tx->donor_phone ); ?></div><?php endif; ?>
                        </td>
                        <td>&pound;<?php echo number_format( $tx->amount_pence / 100, 2 ); ?><?php if ( $tx->tip_pence > 0 ) : ?><div style="font-size:10px;color:#16a34a;">+£<?php echo number_format( $tx->tip_pence / 100, 2 ); ?> tip</div><?php endif; ?></td>
                        <td><strong>&pound;<?php echo number_format( $tx->total_pence / 100, 2 ); ?></strong></td>
                        <td style="font-size:11px;"><?php echo $tx->frequency === 'once' ? '<span style="color:#999;">—</span>' : '<span style="color:#7c3aed;font-weight:700;">' . esc_html( ucfirst( $tx->frequency ) ) . '</span>'; ?></td>
                        <td><?php echo $is_cash ? '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;">💵 Cash</span>' : '<span style="background:#ede9fe;color:#7c3aed;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;">💳</span>'; ?></td>
                        <td style="text-align:center;"><?php
                            if ( $tx->status === 'succeeded' ) echo '<span style="color:#16a34a;font-size:16px;" title="Succeeded">✓</span>';
                            elseif ( $tx->status === 'pending' ) echo '<span style="color:#d97706;font-size:14px;" title="Pending">⏳</span>';
                            elseif ( $tx->status === 'failed' ) echo '<span style="color:#dc2626;font-size:14px;" title="Failed">✗</span>';
                            else echo esc_html( $tx->status );
                        ?></td>
                        <td style="font-size:11px;color:#666;"><?php echo esc_html( date( 'j M Y H:i', strtotime( $tx->completed_at ?: $tx->created_at ) ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:11px;color:#999;margin-top:8px;">Showing latest 200 transactions.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}
