<?php
/**
 * YNJ_Platform_Admin — WP Admin pages for central platform management.
 *
 * Provides admin pages for:
 * - Platform dashboard (overview stats)
 * - Mosque management (list, approve, suspend)
 * - Member management (list, filter, bulk actions)
 * - Messaging (email/push to segments)
 * - Revenue tracking
 *
 * @package YourJannah
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Platform_Admin {

    /**
     * Register admin menus.
     */
    public static function register() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menus' ] );
        add_action( 'ynj_user_registered', [ __CLASS__, 'notify_new_registration' ], 10, 2 );
    }

    /**
     * Add admin menu pages.
     */
    public static function add_menus() {
        // Only show platform admin to administrators
        if ( ! current_user_can( 'manage_options' ) ) return;

        add_menu_page(
            'YourJannah Platform',
            'YJ Platform',
            'manage_options',
            'ynj-platform',
            [ __CLASS__, 'page_dashboard' ],
            'dashicons-building',
            4
        );

        add_submenu_page( 'ynj-platform', 'Platform Dashboard', 'Dashboard', 'manage_options', 'ynj-platform', [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'ynj-platform', 'All Mosques', 'Mosques', 'manage_options', 'ynj-mosques', [ __CLASS__, 'page_mosques' ] );
        add_submenu_page( 'ynj-platform', 'All Members', 'Members', 'manage_options', 'ynj-members', [ __CLASS__, 'page_members' ] );
        add_submenu_page( 'ynj-platform', 'Messaging', 'Messaging', 'manage_options', 'ynj-messaging', [ __CLASS__, 'page_messaging' ] );
        add_submenu_page( 'ynj-platform', 'Revenue', 'Revenue', 'manage_options', 'ynj-revenue', [ __CLASS__, 'page_revenue' ] );
        add_submenu_page( 'ynj-platform', 'Pipeline', '🎯 Pipeline', 'manage_options', 'ynj-pipeline', [ __CLASS__, 'page_pipeline' ] );
        add_submenu_page( 'ynj-platform', 'Pool Payouts', '💰 Payouts', 'manage_options', 'ynj-pool-payouts', [ __CLASS__, 'page_pool_payouts' ] );
        add_submenu_page( 'ynj-platform', 'Earnings', '📊 Earnings', 'manage_options', 'ynj-earnings', [ __CLASS__, 'page_earnings' ] );
        add_submenu_page( 'ynj-platform', 'Mosque Performance', '🕌 Performance', 'manage_options', 'ynj-performance', [ __CLASS__, 'page_performance' ] );
        add_submenu_page( 'ynj-platform', 'Support Tickets', '🎫 Help Desk', 'manage_options', 'ynj-helpdesk', [ __CLASS__, 'page_helpdesk' ] );
        add_submenu_page( 'ynj-platform', 'Enquiries', 'Enquiries', 'manage_options', 'ynj-platform-enquiries', [ __CLASS__, 'page_enquiries' ] );
    }

    // ================================================================
    // DASHBOARD
    // ================================================================

    public static function page_dashboard() {
        global $wpdb;

        $mosques    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed')" );
        $unclaimed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status = 'unclaimed'" );
        $users      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'users' ) . " WHERE status = 'active'" );
        $wp_users   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->users" );
        $subs       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'user_subscriptions' ) . " WHERE status = 'active'" );
        $patrons    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'patrons' ) . " WHERE status = 'active'" );
        $patron_rev = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence),0) FROM " . YNJ_DB::table( 'patrons' ) . " WHERE status = 'active'" );
        $events     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'events' ) . " WHERE status = 'published'" );
        $enquiries  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'enquiries' ) . " WHERE status = 'new'" );

        ?>
        <div class="wrap">
            <h1>🕌 YourJannah Platform Dashboard</h1>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0;">
                <?php
                $stats = [
                    [ 'num' => $mosques,    'label' => 'Total Mosques (' . $unclaimed . ' unclaimed)', 'color' => '#16a34a' ],
                    [ 'num' => $users,      'label' => 'Members',           'color' => '#0369a1' ],
                    [ 'num' => $subs,       'label' => 'Subscriptions',     'color' => '#7c3aed' ],
                    [ 'num' => $patrons,    'label' => 'Active Patrons',    'color' => '#d97706' ],
                    [ 'num' => number_format( $patron_rev / 100, 0 ), 'label' => 'Monthly Patron Rev (£)', 'color' => '#16a34a' ],
                    [ 'num' => $events,     'label' => 'Published Events',  'color' => '#0369a1' ],
                    [ 'num' => $enquiries,  'label' => 'New Enquiries',     'color' => '#dc2626' ],
                    [ 'num' => $wp_users,   'label' => 'WP Users Total',    'color' => '#6b7280' ],
                ];
                foreach ( $stats as $s ) :
                ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:<?php echo esc_attr( $s['color'] ); ?>;"><?php echo esc_html( $s['num'] ); ?></div>
                    <div style="font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-top:4px;"><?php echo esc_html( $s['label'] ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php
            // Recent registrations
            $pending_regs = get_option( 'ynj_pending_registrations', [] );
            $recent_regs = array_filter( $pending_regs, function( $r ) {
                return strtotime( $r['time'] ?? '' ) > strtotime( '-7 days' );
            } );
            $recent_count = count( $recent_regs );
            if ( $recent_count > 0 ) :
            ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid #f59e0b;border-radius:8px;padding:20px;margin-bottom:16px;">
                <h3 style="margin:0 0 12px;">🔔 <?php echo esc_html( $recent_count ); ?> new registration<?php echo $recent_count !== 1 ? 's' : ''; ?> this week</h3>
                <table class="wp-list-table widefat striped" style="margin:0;">
                    <thead><tr><th>Name</th><th>Email</th><th>Mosque</th><th>When</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ( array_reverse( array_slice( $recent_regs, -10 ) ) as $r ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $r['user_name'] ?? '' ); ?></strong></td>
                        <td><?php echo esc_html( $r['user_email'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $r['mosque_name'] ?? '' ); ?></td>
                        <td><?php echo esc_html( human_time_diff( strtotime( $r['time'] ?? '' ) ) ); ?> ago</td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'ynj_assign_admin', '_ynj_assign_nonce' ); ?>
                                <input type="hidden" name="assign_mosque_id" value="<?php echo (int) ( $r['mosque_id'] ?? 0 ); ?>">
                                <input type="hidden" name="assign_email" value="<?php echo esc_attr( $r['user_email'] ?? '' ); ?>">
                                <button type="submit" name="ynj_assign_admin" class="button button-small button-primary"
                                        onclick="return confirm('Make <?php echo esc_js( $r['user_name'] ?? '' ); ?> admin for <?php echo esc_js( $r['mosque_name'] ?? '' ); ?>?');">
                                    Make Admin
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
                    <h3>Quick Actions</h3>
                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-mosques' ) ); ?>" class="button">View All Mosques</a></p>
                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-members' ) ); ?>" class="button">View All Members</a></p>
                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-messaging' ) ); ?>" class="button button-primary">Send Message</a></p>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
                    <h3>System Status</h3>
                    <p>DB Schema: <strong><?php echo esc_html( get_option( 'ynj_db_version', 'unknown' ) ); ?></strong></p>
                    <p>Stripe: <strong><?php echo YNJ_Stripe::is_configured() ? '✅ Configured' : '❌ Not configured'; ?></strong></p>
                    <p>VAPID: <strong><?php echo get_option( 'ynj_vapid_public' ) ? '✅ Active' : '❌ Missing'; ?></strong></p>
                    <p>WP Users: <strong><?php echo esc_html( $wp_users ); ?></strong> | Custom Users: <strong><?php echo esc_html( $users ); ?></strong></p>
                </div>
            </div>
        </div>
        <?php
    }

    // ================================================================
    // MOSQUES
    // ================================================================

    public static function page_mosques() {
        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        $sub_table = YNJ_DB::table( 'user_subscriptions' );

        // Handle assign admin
        if ( isset( $_POST['ynj_assign_admin'] ) && wp_verify_nonce( $_POST['_ynj_assign_nonce'] ?? '', 'ynj_assign_admin' ) ) {
            $assign_mosque_id = (int) ( $_POST['assign_mosque_id'] ?? 0 );
            $assign_email     = sanitize_email( $_POST['assign_email'] ?? '' );
            if ( $assign_mosque_id && is_email( $assign_email ) ) {
                $mosque_row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT name FROM $table WHERE id = %d", $assign_mosque_id
                ) );
                if ( $mosque_row ) {
                    $result = YNJ_WP_Auth::invite_admin( $assign_mosque_id, $assign_email, $mosque_row->name );
                    if ( is_wp_error( $result ) ) {
                        echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                    } else {
                        $wpdb->update( $table, [ 'status' => 'active', 'admin_email' => $assign_email ], [ 'id' => $assign_mosque_id ] );
                        echo '<div class="notice notice-success"><p>Admin assigned: ' . esc_html( $assign_email ) . ' for ' . esc_html( $mosque_row->name ) . '</p></div>';
                    }
                }
            }
        }
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page = 50;
        $offset = ( $paged - 1 ) * $per_page;

        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $where = "m.status IN ('active','unclaimed')";
        if ( $status_filter === 'active' ) $where = "m.status = 'active'";
        elseif ( $status_filter === 'unclaimed' ) $where = "m.status = 'unclaimed'";
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (m.name LIKE %s OR m.city LIKE %s OR m.postcode LIKE %s)", $like, $like, $like );
        }

        // Single query with LEFT JOIN for member count — no N+1
        $mosques = $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, IFNULL(sub.cnt, 0) AS member_count
             FROM $table m
             LEFT JOIN (SELECT mosque_id, COUNT(*) as cnt FROM $sub_table GROUP BY mosque_id) sub ON sub.mosque_id = m.id
             WHERE $where
             ORDER BY m.created_at DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table m WHERE $where" );
        $total_pages = ceil( $total / $per_page );
        ?>
        <div class="wrap">
            <h1>🕌 All Mosques (<?php echo esc_html( $total ); ?>)</h1>
            <?php
            $count_all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status IN ('active','unclaimed')" );
            $count_active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'active'" );
            $count_unclaimed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'unclaimed'" );
            ?>
            <ul class="subsubsub" style="margin:8px 0 16px;">
                <li><a href="<?php echo esc_url( add_query_arg( ['status'=>'','paged'=>1] ) ); ?>" <?php echo !$status_filter ? 'class="current"' : ''; ?>>All <span class="count">(<?php echo $count_all; ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url( add_query_arg( ['status'=>'active','paged'=>1] ) ); ?>" <?php echo $status_filter==='active' ? 'class="current"' : ''; ?>>Active <span class="count">(<?php echo $count_active; ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url( add_query_arg( ['status'=>'unclaimed','paged'=>1] ) ); ?>" <?php echo $status_filter==='unclaimed' ? 'class="current"' : ''; ?>>Unclaimed <span class="count">(<?php echo $count_unclaimed; ?>)</span></a></li>
            </ul>
            <form method="get" style="margin:0 0 16px;">
                <input type="hidden" name="page" value="ynj-mosques">
                <?php if ($status_filter) : ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search mosques..." class="regular-text">
                <?php submit_button( 'Search', 'secondary', '', false ); ?>
            </form>
            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html( $total ); ?> items</span>
                <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                    <?php if ( $i === $paged ) : ?><span class="tablenav-pages-navspan button disabled"><?php echo $i; ?></span>
                    <?php else : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div></div>
            <?php endif; ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr><th>Name</th><th>City</th><th>Postcode</th><th>Admin Email</th><th>Members</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $mosques as $m ) :
                    $members = (int) $m->member_count;
                ?>
                <tr>
                    <td><strong><a href="<?php echo esc_url( home_url( '/mosque/' . $m->slug ) ); ?>" target="_blank"><?php echo esc_html( $m->name ); ?></a></strong></td>
                    <td><?php echo esc_html( $m->city ); ?></td>
                    <td><?php echo esc_html( $m->postcode ); ?></td>
                    <td><a href="mailto:<?php echo esc_attr( $m->admin_email ); ?>"><?php echo esc_html( $m->admin_email ); ?></a></td>
                    <td><?php echo esc_html( $members ); ?></td>
                    <td><span style="color:<?php echo $m->status === 'active' ? '#16a34a' : '#dc2626'; ?>;"><?php echo esc_html( $m->status ); ?></span></td>
                    <td><?php echo esc_html( date( 'j M Y', strtotime( $m->created_at ) ) ); ?></td>
                    <td>
                        <form method="post" style="display:flex;gap:4px;align-items:center;">
                            <?php wp_nonce_field( 'ynj_assign_admin', '_ynj_assign_nonce' ); ?>
                            <input type="hidden" name="assign_mosque_id" value="<?php echo (int) $m->id; ?>">
                            <input type="email" name="assign_email" placeholder="admin@email.com" required
                                   style="width:180px;padding:4px 8px;font-size:12px;">
                            <button type="submit" name="ynj_assign_admin" class="button button-small button-primary">
                                Assign Admin
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ================================================================
    // MEMBERS
    // ================================================================

    public static function page_members() {
        global $wpdb;

        // Handle make admin
        if ( isset( $_POST['ynj_make_admin'] ) && wp_verify_nonce( $_POST['_ynj_make_admin_nonce'] ?? '', 'ynj_make_admin' ) ) {
            $target_email     = sanitize_email( $_POST['target_email'] ?? '' );
            $target_mosque_id = (int) ( $_POST['target_mosque_id'] ?? 0 );
            if ( is_email( $target_email ) && $target_mosque_id ) {
                $wp_user = get_user_by( 'email', $target_email );
                if ( $wp_user ) {
                    $wp_user->add_role( 'ynj_mosque_admin' );
                    update_user_meta( $wp_user->ID, 'ynj_mosque_id', $target_mosque_id );
                    $ids = get_user_meta( $wp_user->ID, 'ynj_mosque_ids', true ) ?: [];
                    if ( ! in_array( $target_mosque_id, $ids, true ) ) {
                        $ids[] = $target_mosque_id;
                        update_user_meta( $wp_user->ID, 'ynj_mosque_ids', $ids );
                    }
                    echo '<div class="notice notice-success"><p>User ' . esc_html( $target_email ) . ' promoted to mosque admin.</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>No WordPress user found with email ' . esc_html( $target_email ) . '. They need to register first.</p></div>';
                }
            }
        }

        $table = YNJ_DB::table( 'users' );
        $mosque_filter = absint( $_GET['mosque_id'] ?? 0 );
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page = 50;
        $offset = ( $paged - 1 ) * $per_page;

        $where = "u.status = 'active'";
        if ( $mosque_filter ) {
            $where .= $wpdb->prepare( " AND u.favourite_mosque_id = %d", $mosque_filter );
        }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (u.name LIKE %s OR u.email LIKE %s)", $like, $like );
        }

        $mt = YNJ_DB::table( 'mosques' );
        $users = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.*, m.name AS mosque_name FROM $table u LEFT JOIN $mt m ON m.id = u.favourite_mosque_id WHERE $where ORDER BY u.created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table u WHERE $where" );
        $total_pages = ceil( $total / $per_page );

        // Mosque filter dropdown
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status = 'active' ORDER BY name LIMIT 1000" );
        ?>
        <div class="wrap">
            <h1>👥 All Members (<?php echo esc_html( $total ); ?>)</h1>
            <form method="get" style="margin:16px 0;display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="page" value="ynj-members">
                <select name="mosque_id">
                    <option value="">All Mosques</option>
                    <?php foreach ( $mosques as $m ) : ?>
                    <option value="<?php echo esc_attr( $m->id ); ?>" <?php selected( $mosque_filter, $m->id ); ?>><?php echo esc_html( $m->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search members..." class="regular-text">
                <?php submit_button( 'Filter', 'secondary', '', false ); ?>
            </form>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Mosque</th><th>Verified</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $users as $u ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $u->name ); ?></strong></td>
                    <td><?php echo esc_html( $u->email ); ?></td>
                    <td><?php echo esc_html( $u->phone ); ?></td>
                    <td><?php echo esc_html( $u->mosque_name ?: '—' ); ?></td>
                    <td><?php echo $u->verified_congregation ? '✅' : '—'; ?></td>
                    <td><?php echo esc_html( date( 'j M Y', strtotime( $u->created_at ) ) ); ?></td>
                    <td>
                        <?php if ( $u->favourite_mosque_id ) : ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Make <?php echo esc_js( $u->name ); ?> an admin for <?php echo esc_js( $u->mosque_name ); ?>?');">
                            <?php wp_nonce_field( 'ynj_make_admin', '_ynj_make_admin_nonce' ); ?>
                            <input type="hidden" name="target_email" value="<?php echo esc_attr( $u->email ); ?>">
                            <input type="hidden" name="target_mosque_id" value="<?php echo (int) $u->favourite_mosque_id; ?>">
                            <button type="submit" name="ynj_make_admin" class="button button-small">Make Admin</button>
                        </form>
                        <?php else : ?>
                        <span style="color:#999;font-size:11px;">No mosque</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html( $total ); ?> members</span>
                <?php for ( $i = 1; $i <= min( $total_pages, 20 ); $i++ ) : ?>
                    <?php if ( $i === $paged ) : ?><span class="tablenav-pages-navspan button disabled"><?php echo $i; ?></span>
                    <?php else : ?><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ( $total_pages > 20 ) : ?><span>...</span><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages ) ); ?>"><?php echo $total_pages; ?></a><?php endif; ?>
            </div></div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ================================================================
    // MESSAGING
    // ================================================================

    public static function page_messaging() {
        // Handle send
        if ( isset( $_POST['ynj_send_message'] ) && wp_verify_nonce( $_POST['_ynj_msg_nonce'] ?? '', 'ynj_send_message' ) ) {
            $segment = sanitize_text_field( $_POST['segment'] ?? 'all' );
            $mosque_id = absint( $_POST['mosque_id'] ?? 0 );
            $subject = sanitize_text_field( $_POST['subject'] ?? '' );
            $body = wp_kses_post( $_POST['body'] ?? '' );
            $method = sanitize_text_field( $_POST['method'] ?? 'email' );

            if ( $subject && $body ) {
                $sent = self::send_message( $segment, $mosque_id, $subject, $body, $method );
                echo '<div class="notice notice-success"><p>Message sent to ' . esc_html( $sent ) . ' recipients.</p></div>';
            }
        }

        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status = 'active' ORDER BY name" );
        ?>
        <div class="wrap">
            <h1>📨 Messaging</h1>
            <form method="post" style="max-width:700px;margin-top:20px;">
                <?php wp_nonce_field( 'ynj_send_message', '_ynj_msg_nonce' ); ?>
                <input type="hidden" name="ynj_send_message" value="1">

                <table class="form-table">
                    <tr><th>Segment</th><td>
                        <select name="segment">
                            <option value="all">All Members</option>
                            <option value="mosque">Specific Mosque</option>
                            <option value="patrons">All Patrons</option>
                        </select>
                    </td></tr>
                    <tr><th>Mosque (if specific)</th><td>
                        <select name="mosque_id">
                            <option value="">Select...</option>
                            <?php foreach ( $mosques as $m ) : ?>
                            <option value="<?php echo esc_attr( $m->id ); ?>"><?php echo esc_html( $m->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td></tr>
                    <tr><th>Method</th><td>
                        <select name="method">
                            <option value="email">Email (wp_mail)</option>
                            <option value="push">Push Notification</option>
                        </select>
                    </td></tr>
                    <tr><th>Subject</th><td><input type="text" name="subject" class="large-text" required></td></tr>
                    <tr><th>Body</th><td><textarea name="body" rows="6" class="large-text" required></textarea></td></tr>
                </table>

                <?php submit_button( 'Send Message', 'primary' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Send message to a segment.
     */
    private static function send_message( $segment, $mosque_id, $subject, $body, $method ) {
        global $wpdb;
        $ut = YNJ_DB::table( 'users' );
        $pt = YNJ_DB::table( 'patrons' );
        $st = YNJ_DB::table( 'user_subscriptions' );

        // Build the base query — we'll batch with LIMIT/OFFSET
        if ( $segment === 'all' ) {
            $base_query = "SELECT name, email, push_endpoint, push_p256dh, push_auth FROM $ut WHERE status = 'active' AND email != ''";
        } elseif ( $segment === 'mosque' && $mosque_id ) {
            $base_query = $wpdb->prepare(
                "SELECT u.name, u.email, u.push_endpoint, u.push_p256dh, u.push_auth
                 FROM $st s INNER JOIN $ut u ON u.id = s.user_id
                 WHERE s.mosque_id = %d AND u.status = 'active'",
                $mosque_id
            );
        } elseif ( $segment === 'patrons' ) {
            $base_query = "SELECT u.name, u.email, u.push_endpoint, u.push_p256dh, u.push_auth
                 FROM $pt p INNER JOIN $ut u ON u.email = p.user_email
                 WHERE p.status = 'active' AND u.status = 'active'";
        } else {
            return 0;
        }

        // HTML email template (built once)
        $html_tpl = '<div style="font-family:Inter,system-ui,sans-serif;max-width:600px;margin:0 auto;">'
            . '<div style="background:linear-gradient(135deg,#0a1628,#00ADEF);color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center;">'
            . '<h2 style="margin:0;">YourJannah</h2></div>'
            . '<div style="background:#fff;border:1px solid #e5e5e5;border-top:none;padding:24px;border-radius:0 0 12px 12px;">'
            . '<h3>' . esc_html( $subject ) . '</h3>'
            . wp_kses_post( $body )
            . '</div></div>';

        $push_payload = wp_json_encode( [
            'title' => $subject,
            'body'  => wp_strip_all_tags( $body ),
            'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
            'url'   => '/',
        ] );

        // Process in batches of 200 to avoid memory issues
        $batch_size = 200;
        $offset = 0;
        $count = 0;
        $content_type_set = false;

        do {
            $batch = $wpdb->get_results( $base_query . $wpdb->prepare( " LIMIT %d OFFSET %d", $batch_size, $offset ) );

            foreach ( $batch as $r ) {
                if ( $method === 'push' && $r->push_endpoint ) {
                    YNJ_Push::send_push( $r->push_endpoint, $r->push_p256dh, $r->push_auth, $push_payload );
                    $count++;
                } elseif ( $method === 'email' && is_email( $r->email ) ) {
                    if ( ! $content_type_set ) {
                        add_filter( 'wp_mail_content_type', [ __CLASS__, '_html_content_type' ] );
                        $content_type_set = true;
                    }
                    wp_mail( $r->email, $subject . ' — YourJannah', $html_tpl );
                    $count++;
                }
            }

            $offset += $batch_size;
        } while ( count( $batch ) === $batch_size );

        if ( $content_type_set ) {
            remove_filter( 'wp_mail_content_type', [ __CLASS__, '_html_content_type' ] );
        }

        return $count;
    }

    public static function _html_content_type() {
        return 'text/html';
    }

    // ================================================================
    // REVENUE
    // ================================================================

    public static function page_revenue() {
        global $wpdb;
        $pt = YNJ_DB::table( 'patrons' );
        $mt = YNJ_DB::table( 'mosques' );

        $patron_by_mosque = $wpdb->get_results(
            "SELECT m.name, COUNT(p.id) AS count, SUM(p.amount_pence) AS total_pence
             FROM $pt p INNER JOIN $mt m ON m.id = p.mosque_id
             WHERE p.status = 'active'
             GROUP BY p.mosque_id ORDER BY total_pence DESC"
        );

        $total_patron = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence),0) FROM $pt WHERE status = 'active'" );
        ?>
        <div class="wrap">
            <h1>💰 Revenue</h1>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin:20px 0;">
                <h2 style="margin:0;">Total Monthly Patron Revenue: <span style="color:#16a34a;">£<?php echo number_format( $total_patron / 100, 2 ); ?></span></h2>
                <p style="color:#6b7280;">Projected yearly: £<?php echo number_format( $total_patron * 12 / 100, 2 ); ?></p>
            </div>

            <h3>Patron Revenue by Mosque</h3>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Mosque</th><th>Patrons</th><th>Monthly Revenue</th></tr></thead>
                <tbody>
                <?php foreach ( $patron_by_mosque as $r ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $r->name ); ?></strong></td>
                    <td><?php echo esc_html( $r->count ); ?></td>
                    <td>£<?php echo number_format( $r->total_pence / 100, 2 ); ?>/mo</td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $patron_by_mosque ) ) : ?>
                <tr><td colspan="3">No patron revenue yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ================================================================
    // POOL PAYOUTS — DFM-style fund distribution tracking
    // ================================================================

    public static function page_pool_payouts() {
        global $wpdb;

        // Handle payout action
        if ( isset( $_POST['ynj_record_payout'] ) && wp_verify_nonce( $_POST['_ynj_payout_nonce'] ?? '', 'ynj_payout' ) ) {
            $mosque_id = (int) ( $_POST['mosque_id'] ?? 0 );
            $bank_ref  = sanitize_text_field( $_POST['bank_reference'] ?? '' );
            $notes     = sanitize_text_field( $_POST['notes'] ?? '' );
            if ( $mosque_id ) {
                $payout_id = YNJ_Pool_Ledger::record_payout( [
                    'mosque_id'      => $mosque_id,
                    'bank_reference' => $bank_ref,
                    'notes'          => $notes,
                    'method'         => 'bank_transfer',
                ] );
                if ( $payout_id ) {
                    echo '<div class="notice notice-success"><p>Payout recorded (#' . esc_html( $payout_id ) . ')</p></div>';
                }
            }
        }

        $balances = YNJ_Pool_Ledger::get_outstanding_balances();
        $payouts  = YNJ_Pool_Ledger::get_payouts( 30 );
        $summary  = YNJ_Pool_Ledger::get_platform_summary();
        ?>
        <div class="wrap">
            <h1>💰 Pool Payouts — Mosque Fund Distribution</h1>
            <p style="color:#666;">Revenue split: 90% to mosque, 10% YourJannah platform fee. Payouts tracked here.</p>

            <!-- Summary Cards -->
            <div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
                <div style="flex:1;min-width:180px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Total Received</div>
                    <div style="font-size:28px;font-weight:800;color:#1a1a2e;">£<?php echo number_format( ( $summary->total_gross ?? 0 ) / 100, 2 ); ?></div>
                </div>
                <div style="flex:1;min-width:180px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Platform Revenue (10%)</div>
                    <div style="font-size:28px;font-weight:800;color:#16a34a;">£<?php echo number_format( ( $summary->total_platform_revenue ?? 0 ) / 100, 2 ); ?></div>
                </div>
                <div style="flex:1;min-width:180px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Owed to Mosques (90%)</div>
                    <div style="font-size:28px;font-weight:800;color:#00ADEF;">£<?php echo number_format( ( $summary->total_owed_mosques ?? 0 ) / 100, 2 ); ?></div>
                </div>
                <div style="flex:1;min-width:180px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Transactions</div>
                    <div style="font-size:28px;font-weight:800;color:#1a1a2e;"><?php echo (int) ( $summary->total_entries ?? 0 ); ?></div>
                </div>
            </div>

            <!-- Outstanding Balances -->
            <h2 style="margin-top:30px;">Outstanding Balances</h2>
            <?php if ( empty( $balances ) ) : ?>
                <p style="color:#6b7280;">No payments recorded yet. Balances will appear here once Stripe payments come through.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th>Mosque</th>
                        <th style="text-align:right;">Payments</th>
                        <th style="text-align:right;">Gross</th>
                        <th style="text-align:right;">Platform Fee</th>
                        <th style="text-align:right;">Net Owed</th>
                        <th style="text-align:right;">Paid Out</th>
                        <th style="text-align:right;">Outstanding</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $balances as $b ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $b->mosque_name ); ?></strong></td>
                        <td style="text-align:right;"><?php echo (int) $b->entry_count; ?></td>
                        <td style="text-align:right;">£<?php echo number_format( $b->total_gross / 100, 2 ); ?></td>
                        <td style="text-align:right;">£<?php echo number_format( $b->total_platform_fee / 100, 2 ); ?></td>
                        <td style="text-align:right;">£<?php echo number_format( $b->total_net_owed / 100, 2 ); ?></td>
                        <td style="text-align:right;">£<?php echo number_format( $b->total_paid_out / 100, 2 ); ?></td>
                        <td style="text-align:right;font-weight:700;color:<?php echo $b->outstanding > 0 ? '#dc2626' : '#16a34a'; ?>;">
                            £<?php echo number_format( $b->outstanding / 100, 2 ); ?>
                        </td>
                        <td>
                            <?php if ( $b->outstanding > 0 ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'ynj_payout', '_ynj_payout_nonce' ); ?>
                                <input type="hidden" name="mosque_id" value="<?php echo (int) $b->mosque_id; ?>">
                                <input type="text" name="bank_reference" placeholder="Bank ref" style="width:100px;font-size:12px;padding:4px 8px;">
                                <input type="text" name="notes" placeholder="Notes" style="width:100px;font-size:12px;padding:4px 8px;">
                                <button type="submit" name="ynj_record_payout" class="button button-small" onclick="return confirm('Record payout of £<?php echo number_format( $b->outstanding / 100, 2 ); ?> to <?php echo esc_js( $b->mosque_name ); ?>?');">
                                    Pay £<?php echo number_format( $b->outstanding / 100, 2 ); ?>
                                </button>
                            </form>
                            <?php else : ?>
                            <span style="color:#16a34a;font-size:12px;">✓ Settled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Payout History -->
            <h2 style="margin-top:30px;">Payout History</h2>
            <?php if ( empty( $payouts ) ) : ?>
                <p style="color:#6b7280;">No payouts recorded yet.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Mosque</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Method</th>
                        <th>Bank Ref</th>
                        <th>Entries</th>
                        <th>Covers</th>
                        <th>Status</th>
                        <th>Sent</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $payouts as $po ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $po->payout_ref ); ?></code></td>
                        <td><?php echo esc_html( $po->mosque_name ); ?></td>
                        <td style="text-align:right;font-weight:700;">£<?php echo number_format( $po->amount_pence / 100, 2 ); ?></td>
                        <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $po->method ) ) ); ?></td>
                        <td><?php echo esc_html( $po->bank_reference ); ?></td>
                        <td><?php echo (int) $po->entries_count; ?></td>
                        <td style="font-size:11px;"><?php echo $po->covers_from ? esc_html( substr( $po->covers_from, 0, 10 ) . ' → ' . substr( $po->covers_to, 0, 10 ) ) : '—'; ?></td>
                        <td><span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:<?php echo $po->status === 'sent' ? '#dcfce7' : '#f3f4f6'; ?>;color:<?php echo $po->status === 'sent' ? '#166534' : '#374151'; ?>;"><?php echo esc_html( ucfirst( $po->status ) ); ?></span></td>
                        <td style="font-size:11px;"><?php echo esc_html( $po->sent_at ? substr( $po->sent_at, 0, 16 ) : '—' ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ================================================================
    // EARNINGS — Platform revenue (5% fee) + pricing reference
    // ================================================================

    public static function page_earnings() {
        global $wpdb;
        $lt = YNJ_DB::table( 'pool_ledger' );
        $pt = YNJ_DB::table( 'patrons' );
        $bt = YNJ_DB::table( 'businesses' );
        $st = YNJ_DB::table( 'services' );
        $dt = YNJ_DB::table( 'donations' );

        // Platform summary from ledger
        $summary = $wpdb->get_row( "SELECT COUNT(*) AS total_txns, COALESCE(SUM(gross_pence),0) AS total_gross, COALESCE(SUM(platform_fee_pence),0) AS total_platform, COALESCE(SUM(net_to_mosque_pence),0) AS total_mosque FROM $lt WHERE entry_type IN ('payment','recurring')" );

        // Monthly breakdown (last 6 months)
        $monthly = $wpdb->get_results( "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS txns, SUM(gross_pence) AS gross, SUM(platform_fee_pence) AS platform FROM $lt WHERE entry_type IN ('payment','recurring') GROUP BY month ORDER BY month DESC LIMIT 6" ) ?: [];

        // Active subscriptions revenue
        $patron_mrr = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence),0) FROM $pt WHERE status='active'" );
        $biz_mrr = (int) $wpdb->get_var( "SELECT COALESCE(SUM(monthly_fee_pence),0) FROM $bt WHERE status='active'" );
        $svc_mrr = (int) $wpdb->get_var( "SELECT COALESCE(SUM(monthly_fee_pence),0) FROM $st WHERE status='active'" );
        $total_mrr = $patron_mrr + $biz_mrr + $svc_mrr;
        $platform_mrr = (int) round( $total_mrr * 5 / 100 );

        // Donation totals
        $donation_total = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE status='succeeded'" );
        $donation_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt WHERE status='succeeded'" );
        ?>
        <div class="wrap">
            <h1>📊 Platform Earnings</h1>
            <p style="color:#666;">YourJannah takes 5% platform fee. 95% goes to the mosque.</p>

            <!-- MRR Cards -->
            <div style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
                <div style="flex:1;min-width:160px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Your 5% (Monthly)</div>
                    <div style="font-size:28px;font-weight:800;color:#16a34a;">£<?php echo number_format( $platform_mrr / 100, 2 ); ?></div>
                </div>
                <div style="flex:1;min-width:160px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Total MRR (All Subs)</div>
                    <div style="font-size:28px;font-weight:800;color:#1a1a2e;">£<?php echo number_format( $total_mrr / 100, 2 ); ?></div>
                </div>
                <div style="flex:1;min-width:160px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Total Donations</div>
                    <div style="font-size:28px;font-weight:800;color:#00ADEF;">£<?php echo number_format( $donation_total / 100, 2 ); ?></div>
                    <div style="font-size:11px;color:#6b7280;"><?php echo $donation_count; ?> donations</div>
                </div>
                <div style="flex:1;min-width:160px;padding:20px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
                    <div style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:600;">Lifetime Platform Rev</div>
                    <div style="font-size:28px;font-weight:800;color:#7c3aed;">£<?php echo number_format( ( $summary->total_platform ?? 0 ) / 100, 2 ); ?></div>
                </div>
            </div>

            <!-- MRR Breakdown -->
            <h2 style="margin-top:30px;">MRR Breakdown</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead><tr><th>Product</th><th style="text-align:right;">Active</th><th style="text-align:right;">Gross MRR</th><th style="text-align:right;">Your 5%</th></tr></thead>
                <tbody>
                    <tr><td>🏅 Patron Memberships (£5–£50/mo)</td><td style="text-align:right;"><?php echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt WHERE status='active'" ); ?></td><td style="text-align:right;">£<?php echo number_format( $patron_mrr / 100, 2 ); ?></td><td style="text-align:right;color:#16a34a;font-weight:700;">£<?php echo number_format( $patron_mrr * 5 / 10000, 2 ); ?></td></tr>
                    <tr><td>⭐ Business Sponsors (£30–£100/mo)</td><td style="text-align:right;"><?php echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bt WHERE status='active'" ); ?></td><td style="text-align:right;">£<?php echo number_format( $biz_mrr / 100, 2 ); ?></td><td style="text-align:right;color:#16a34a;font-weight:700;">£<?php echo number_format( $biz_mrr * 5 / 10000, 2 ); ?></td></tr>
                    <tr><td>🤝 Service Listings (£10/mo)</td><td style="text-align:right;"><?php echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM $st WHERE status='active'" ); ?></td><td style="text-align:right;">£<?php echo number_format( $svc_mrr / 100, 2 ); ?></td><td style="text-align:right;color:#16a34a;font-weight:700;">£<?php echo number_format( $svc_mrr * 5 / 10000, 2 ); ?></td></tr>
                    <tr style="font-weight:700;background:#f0fdf4;"><td>Total</td><td></td><td style="text-align:right;">£<?php echo number_format( $total_mrr / 100, 2 ); ?></td><td style="text-align:right;color:#16a34a;">£<?php echo number_format( $platform_mrr / 100, 2 ); ?></td></tr>
                </tbody>
            </table>

            <!-- Pricing Reference -->
            <h2 style="margin-top:30px;">💷 Pricing Reference</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead><tr><th>Product</th><th>Price</th><th>Mosque Gets</th><th>YJ Gets (5%)</th></tr></thead>
                <tbody>
                    <tr><td>🏅 Bronze Patron</td><td>£5/mo</td><td>£4.75</td><td>£0.25</td></tr>
                    <tr><td>🏅 Silver Patron</td><td>£10/mo</td><td>£9.50</td><td>£0.50</td></tr>
                    <tr><td>🏅 Gold Patron</td><td>£20/mo</td><td>£19.00</td><td>£1.00</td></tr>
                    <tr><td>🏅 Platinum Patron</td><td>£50/mo</td><td>£47.50</td><td>£2.50</td></tr>
                    <tr><td>⭐ Business Sponsor (Standard)</td><td>£30/mo</td><td>£28.50</td><td>£1.50</td></tr>
                    <tr><td>⭐ Business Sponsor (Featured)</td><td>£50/mo</td><td>£47.50</td><td>£2.50</td></tr>
                    <tr><td>⭐ Business Sponsor (Premium)</td><td>£100/mo</td><td>£95.00</td><td>£5.00</td></tr>
                    <tr><td>🤝 Service Listing</td><td>£10/mo</td><td>£9.50</td><td>£0.50</td></tr>
                    <tr><td>💝 Niyyah Bar Donation</td><td>Variable</td><td>95%</td><td>5%</td></tr>
                </tbody>
            </table>

            <!-- Monthly History -->
            <?php if ( $monthly ) : ?>
            <h2 style="margin-top:30px;">Monthly History</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
                <thead><tr><th>Month</th><th style="text-align:right;">Transactions</th><th style="text-align:right;">Gross</th><th style="text-align:right;">Your 5%</th></tr></thead>
                <tbody>
                <?php foreach ( $monthly as $m ) : ?>
                    <tr><td><?php echo esc_html( $m->month ); ?></td><td style="text-align:right;"><?php echo (int) $m->txns; ?></td><td style="text-align:right;">£<?php echo number_format( $m->gross / 100, 2 ); ?></td><td style="text-align:right;font-weight:700;color:#16a34a;">£<?php echo number_format( $m->platform / 100, 2 ); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ================================================================
    // MOSQUE PERFORMANCE — Which mosques are earning, uploading, active
    // ================================================================

    public static function page_performance() {
        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $pt = YNJ_DB::table( 'patrons' );
        $bt = YNJ_DB::table( 'businesses' );
        $st = YNJ_DB::table( 'services' );
        $sub = YNJ_DB::table( 'subscribers' );
        $ann = YNJ_DB::table( 'announcements' );
        $ev = YNJ_DB::table( 'events' );
        $it = YNJ_DB::table( 'email_imports' );
        $dt = YNJ_DB::table( 'donations' );

        $mosques = $wpdb->get_results(
            "SELECT m.id, m.name, m.slug, m.status, m.city, m.postcode,
                (SELECT COUNT(*) FROM $pt WHERE mosque_id = m.id AND status='active') AS patrons,
                (SELECT COALESCE(SUM(amount_pence),0) FROM $pt WHERE mosque_id = m.id AND status='active') AS patron_mrr,
                (SELECT COUNT(*) FROM $bt WHERE mosque_id = m.id AND status='active') AS sponsors,
                (SELECT COUNT(*) FROM $st WHERE mosque_id = m.id AND status='active') AS services,
                (SELECT COUNT(*) FROM $sub WHERE mosque_id = m.id AND status='active') AS subscribers,
                (SELECT COUNT(*) FROM $ann WHERE mosque_id = m.id) AS announcements,
                (SELECT COUNT(*) FROM $ev WHERE mosque_id = m.id) AS events,
                (SELECT COALESCE(SUM(imported),0) FROM $it WHERE mosque_id = m.id) AS imported_contacts,
                (SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE mosque_id = m.id AND status='succeeded') AS donation_total
             FROM $mt m WHERE m.status IN ('active','unclaimed')
             ORDER BY patron_mrr DESC, subscribers DESC
             LIMIT 100"
        ) ?: [];
        ?>
        <div class="wrap">
            <h1>🕌 Mosque Performance</h1>
            <p style="color:#666;">Which mosques are earning, active, and may need marketing help.</p>

            <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th>Mosque</th>
                        <th>Status</th>
                        <th style="text-align:right;">Patrons</th>
                        <th style="text-align:right;">MRR</th>
                        <th style="text-align:right;">Sponsors</th>
                        <th style="text-align:right;">Subscribers</th>
                        <th style="text-align:right;">Imported</th>
                        <th style="text-align:right;">Events</th>
                        <th style="text-align:right;">Donations</th>
                        <th>Health</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $mosques as $m ) :
                    $mrr = (int) $m->patron_mrr;
                    $score = (int) $m->patrons + (int) $m->sponsors + (int) $m->subscribers + (int) $m->events + ( $m->imported_contacts > 0 ? 1 : 0 );
                    if ( $score >= 5 ) { $health = '🟢 Active'; $health_color = '#16a34a'; }
                    elseif ( $score >= 2 ) { $health = '🟡 Growing'; $health_color = '#ca8a04'; }
                    else { $health = '🔴 Needs Help'; $health_color = '#dc2626'; }
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $m->name ); ?></strong><br><span style="font-size:11px;color:#6b7280;"><?php echo esc_html( implode( ', ', array_filter( [ $m->city, $m->postcode ] ) ) ); ?></span></td>
                        <td><span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:<?php echo $m->status === 'active' ? '#dcfce7' : '#fef3c7'; ?>;color:<?php echo $m->status === 'active' ? '#166534' : '#92400e'; ?>;"><?php echo esc_html( ucfirst( $m->status ) ); ?></span></td>
                        <td style="text-align:right;"><?php echo (int) $m->patrons; ?></td>
                        <td style="text-align:right;font-weight:700;">£<?php echo number_format( $mrr / 100, 0 ); ?></td>
                        <td style="text-align:right;"><?php echo (int) $m->sponsors; ?></td>
                        <td style="text-align:right;"><?php echo (int) $m->subscribers; ?></td>
                        <td style="text-align:right;"><?php echo $m->imported_contacts > 0 ? number_format( $m->imported_contacts ) : '<span style="color:#dc2626;">0</span>'; ?></td>
                        <td style="text-align:right;"><?php echo (int) $m->events; ?></td>
                        <td style="text-align:right;">£<?php echo number_format( (int) $m->donation_total / 100, 0 ); ?></td>
                        <td style="font-weight:600;color:<?php echo $health_color; ?>;"><?php echo $health; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ================================================================
    // HELP DESK — Support tickets from mosques
    // ================================================================

    public static function page_helpdesk() {
        global $wpdb;
        $tt = YNJ_DB::table( 'support_tickets' );
        $mt = YNJ_DB::table( 'mosques' );

        // Handle reply
        if ( isset( $_POST['ynj_reply_ticket'] ) && wp_verify_nonce( $_POST['_ynj_ticket_nonce'] ?? '', 'ynj_ticket_reply' ) ) {
            $ticket_id = (int) ( $_POST['ticket_id'] ?? 0 );
            $reply = sanitize_textarea_field( $_POST['admin_reply'] ?? '' );
            $new_status = sanitize_text_field( $_POST['new_status'] ?? 'open' );
            if ( $ticket_id && $reply ) {
                $update = [ 'admin_reply' => $reply, 'status' => $new_status, 'replied_at' => current_time( 'mysql' ) ];
                if ( $new_status === 'resolved' ) $update['resolved_at'] = current_time( 'mysql' );
                $wpdb->update( $tt, $update, [ 'id' => $ticket_id ] );
                echo '<div class="notice notice-success"><p>Reply saved.</p></div>';
            }
        }

        // Stats
        $open = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tt WHERE status = 'open'" );
        $in_progress = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tt WHERE status = 'in_progress'" );
        $resolved = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tt WHERE status = 'resolved'" );

        // Filter
        $filter = sanitize_text_field( $_GET['status'] ?? '' );
        $where = $filter ? $wpdb->prepare( "WHERE t.status = %s", $filter ) : "WHERE t.status IN ('open','in_progress')";

        $tickets = $wpdb->get_results(
            "SELECT t.*, m.name AS mosque_name, m.slug AS mosque_slug
             FROM $tt t LEFT JOIN $mt m ON m.id = t.mosque_id
             $where ORDER BY FIELD(t.priority,'urgent','high','normal','low'), t.created_at DESC LIMIT 50"
        ) ?: [];
        ?>
        <div class="wrap">
            <h1>🎫 Help Desk — Support Tickets</h1>

            <!-- Stats -->
            <div style="display:flex;gap:12px;margin:16px 0;">
                <a href="?page=ynj-helpdesk&status=open" style="padding:10px 20px;background:<?php echo $filter === 'open' || !$filter ? '#dc2626' : '#fff'; ?>;color:<?php echo $filter === 'open' || !$filter ? '#fff' : '#dc2626'; ?>;border:1px solid #dc2626;border-radius:8px;text-decoration:none;font-weight:700;">🔴 Open (<?php echo $open; ?>)</a>
                <a href="?page=ynj-helpdesk&status=in_progress" style="padding:10px 20px;background:<?php echo $filter === 'in_progress' ? '#ca8a04' : '#fff'; ?>;color:<?php echo $filter === 'in_progress' ? '#fff' : '#ca8a04'; ?>;border:1px solid #ca8a04;border-radius:8px;text-decoration:none;font-weight:700;">🟡 In Progress (<?php echo $in_progress; ?>)</a>
                <a href="?page=ynj-helpdesk&status=resolved" style="padding:10px 20px;background:<?php echo $filter === 'resolved' ? '#16a34a' : '#fff'; ?>;color:<?php echo $filter === 'resolved' ? '#fff' : '#16a34a'; ?>;border:1px solid #16a34a;border-radius:8px;text-decoration:none;font-weight:700;">✅ Resolved (<?php echo $resolved; ?>)</a>
                <a href="?page=ynj-helpdesk" style="padding:10px 20px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;text-decoration:none;color:#374151;font-weight:600;">All</a>
            </div>

            <?php if ( empty( $tickets ) ) : ?>
                <div style="text-align:center;padding:40px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;"><p style="color:#6b7280;">No tickets<?php echo $filter ? ' with status "' . esc_html( $filter ) . '"' : ''; ?>.</p></div>
            <?php else : ?>
            <?php foreach ( $tickets as $t ) :
                $pri_colors = [ 'urgent' => '#dc2626', 'high' => '#ea580c', 'normal' => '#2563eb', 'low' => '#6b7280' ];
                $pri_color = $pri_colors[ $t->priority ] ?? '#6b7280';
                $status_bg = [ 'open' => '#fee2e2', 'in_progress' => '#fef3c7', 'resolved' => '#dcfce7' ];
                $status_fg = [ 'open' => '#991b1b', 'in_progress' => '#92400e', 'resolved' => '#166534' ];
            ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid <?php echo $pri_color; ?>;border-radius:8px;padding:16px 20px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                        <div>
                            <strong style="font-size:15px;">#<?php echo $t->id; ?> — <?php echo esc_html( $t->subject ); ?></strong>
                            <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                                🕌 <?php echo esc_html( $t->mosque_name ?: 'Unknown' ); ?>
                                · <?php echo esc_html( $t->category ); ?>
                                · by <?php echo esc_html( $t->created_by ); ?>
                                · <?php echo esc_html( $t->created_at ); ?>
                            </div>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <span style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;background:<?php echo $status_bg[ $t->status ] ?? '#f3f4f6'; ?>;color:<?php echo $status_fg[ $t->status ] ?? '#374151'; ?>;"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $t->status ) ) ); ?></span>
                            <span style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;color:<?php echo $pri_color; ?>;border:1px solid <?php echo $pri_color; ?>;"><?php echo esc_html( ucfirst( $t->priority ) ); ?></span>
                        </div>
                    </div>
                    <p style="font-size:14px;color:#333;line-height:1.6;margin:8px 0;"><?php echo nl2br( esc_html( $t->body ) ); ?></p>

                    <?php if ( $t->admin_reply ) : ?>
                    <div style="background:#f0f9ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin:8px 0;">
                        <strong style="font-size:12px;color:#1e40af;">Your Reply (<?php echo esc_html( $t->replied_at ); ?>):</strong>
                        <p style="font-size:13px;color:#1e40af;margin:4px 0 0;"><?php echo nl2br( esc_html( $t->admin_reply ) ); ?></p>
                    </div>
                    <?php endif; ?>

                    <form method="post" style="margin-top:10px;display:flex;gap:8px;align-items:end;">
                        <?php wp_nonce_field( 'ynj_ticket_reply', '_ynj_ticket_nonce' ); ?>
                        <input type="hidden" name="ticket_id" value="<?php echo (int) $t->id; ?>">
                        <textarea name="admin_reply" rows="2" placeholder="Reply..." style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;"><?php echo esc_textarea( $t->admin_reply ?? '' ); ?></textarea>
                        <select name="new_status" style="padding:8px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                            <option value="open" <?php selected( $t->status, 'open' ); ?>>Open</option>
                            <option value="in_progress" <?php selected( $t->status, 'in_progress' ); ?>>In Progress</option>
                            <option value="resolved" <?php selected( $t->status, 'resolved' ); ?>>Resolved</option>
                        </select>
                        <button type="submit" name="ynj_reply_ticket" class="button button-primary">Reply</button>
                    </form>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php
    }

    // ================================================================
    // ENQUIRIES
    // ================================================================

    public static function page_enquiries() {
        global $wpdb;
        $et = YNJ_DB::table( 'enquiries' );
        $mt = YNJ_DB::table( 'mosques' );
        $enquiries = $wpdb->get_results(
            "SELECT e.*, m.name AS mosque_name FROM $et e LEFT JOIN $mt m ON m.id = e.mosque_id ORDER BY e.created_at DESC LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>📬 All Enquiries (<?php echo count( $enquiries ); ?>)</h1>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>From</th><th>Subject</th><th>Mosque</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ( $enquiries as $e ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $e->name ); ?></strong><br><small><?php echo esc_html( $e->email ); ?></small></td>
                    <td><?php echo esc_html( $e->subject ); ?></td>
                    <td><?php echo esc_html( $e->mosque_name ?: '—' ); ?></td>
                    <td><?php echo esc_html( $e->type ); ?></td>
                    <td><span style="color:<?php echo $e->status === 'new' ? '#d97706' : '#16a34a'; ?>;"><?php echo esc_html( $e->status ); ?></span></td>
                    <td><?php echo esc_html( date( 'j M Y H:i', strtotime( $e->created_at ) ) ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ================================================================
    // PIPELINE — Unclaimed mosques ranked by demand (patrons + intentions)
    // ================================================================

    public static function page_pipeline() {
        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $pt = YNJ_DB::table( 'patrons' );
        $it = YNJ_DB::table( 'patron_intentions' );

        // Summary stats
        $unclaimed_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $mt WHERE status = 'unclaimed'" );
        $active_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $mt WHERE status = 'active'" );

        $patron_stats = $wpdb->get_row(
            "SELECT COUNT(*) AS total, COALESCE(SUM(p.amount_pence),0) AS monthly_pence
             FROM $pt p
             JOIN $mt m ON m.id = p.mosque_id
             WHERE p.status = 'active' AND m.status = 'unclaimed'"
        );

        $intention_stats = $wpdb->get_row(
            "SELECT COUNT(*) AS total, COALESCE(SUM(i.amount_pence),0) AS total_pence
             FROM $it i
             JOIN $mt m ON m.id = i.mosque_id
             WHERE i.status = 'active' AND m.status = 'unclaimed'"
        );

        $vt = YNJ_DB::table( 'mosque_views' );

        // Pipeline: unclaimed mosques ranked by demand (views + patrons + intentions)
        $pipeline = $wpdb->get_results(
            "SELECT m.id, m.name, m.city, m.postcode, m.slug,
                    COALESCE(pc.patron_count, 0) AS patron_count,
                    COALESCE(pc.patron_revenue, 0) AS patron_revenue,
                    COALESCE(ic.intention_count, 0) AS intention_count,
                    COALESCE(ic.intention_pence, 0) AS intention_pence,
                    COALESCE(vc.total_views, 0) AS total_views,
                    COALESCE(vc.views_7d, 0) AS views_7d,
                    (COALESCE(pc.patron_count, 0) * 10 + COALESCE(ic.intention_count, 0) * 5 + COALESCE(vc.views_7d, 0)) AS demand_score
             FROM $mt m
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS patron_count, SUM(amount_pence) AS patron_revenue
                 FROM $pt WHERE status = 'active' GROUP BY mosque_id
             ) pc ON pc.mosque_id = m.id
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS intention_count, SUM(amount_pence) AS intention_pence
                 FROM $it WHERE status = 'active' GROUP BY mosque_id
             ) ic ON ic.mosque_id = m.id
             LEFT JOIN (
                 SELECT mosque_id,
                        SUM(view_count) AS total_views,
                        SUM(CASE WHEN view_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN view_count ELSE 0 END) AS views_7d
                 FROM $vt GROUP BY mosque_id
             ) vc ON vc.mosque_id = m.id
             WHERE m.status = 'unclaimed'
             HAVING demand_score > 0
             ORDER BY demand_score DESC
             LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>🎯 Sales Pipeline — Unclaimed Mosques</h1>
            <p>Mosques with the highest demand from patrons and intentions. Approach these first.</p>

            <div style="display:flex;gap:16px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#0369a1;"><?php echo number_format( $unclaimed_count ); ?></div>
                    <div style="font-size:12px;color:#6b7280;">Unclaimed Mosques</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#166534;"><?php echo number_format( $active_count ); ?></div>
                    <div style="font-size:12px;color:#6b7280;">Active (Claimed)</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#00ADEF;"><?php echo (int) $patron_stats->total; ?></div>
                    <div style="font-size:12px;color:#6b7280;">Paying Patrons (unclaimed)</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#7c3aed;"><?php echo (int) $intention_stats->total; ?></div>
                    <div style="font-size:12px;color:#6b7280;">Intentions</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px 24px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#b45309;">&pound;<?php echo number_format( ( (int) $patron_stats->monthly_pence + (int) $intention_stats->total_pence ) / 100 ); ?></div>
                    <div style="font-size:12px;color:#6b7280;">Potential Monthly Revenue</div>
                </div>
            </div>

            <?php if ( $pipeline ) : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th>Mosque</th>
                        <th>City</th>
                        <th style="text-align:center;">Views (7d)</th>
                        <th style="text-align:center;">Total Views</th>
                        <th style="text-align:center;">Patrons</th>
                        <th style="text-align:center;">Intentions</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:center;">Score</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $pipeline as $row ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $row->name ); ?></strong><br><span style="font-size:11px;color:#6b7280;"><?php echo esc_html( $row->postcode ); ?></span></td>
                        <td><?php echo esc_html( $row->city ); ?></td>
                        <td style="text-align:center;font-weight:700;color:#0369a1;"><?php echo number_format( (int) $row->views_7d ); ?></td>
                        <td style="text-align:center;"><?php echo number_format( (int) $row->total_views ); ?></td>
                        <td style="text-align:center;"><?php echo (int) $row->patron_count; ?></td>
                        <td style="text-align:center;"><?php echo (int) $row->intention_count; ?></td>
                        <td style="text-align:right;">&pound;<?php echo number_format( ( (int) $row->patron_revenue + (int) $row->intention_pence ) / 100 ); ?>/mo</td>
                        <td style="text-align:center;"><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:700;background:<?php echo (int) $row->demand_score > 50 ? '#dcfce7' : ( (int) $row->demand_score > 10 ? '#fef3c7' : '#f0f0f0' ); ?>;color:<?php echo (int) $row->demand_score > 50 ? '#166534' : ( (int) $row->demand_score > 10 ? '#92400e' : '#6b7280' ); ?>;"><?php echo (int) $row->demand_score; ?></span></td>
                        <td><a href="<?php echo esc_url( home_url( '/mosque/' . $row->slug ) ); ?>" target="_blank">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:40px;text-align:center;margin-top:16px;">
                <p style="font-size:16px;color:#6b7280;">No demand yet. Run ads to start collecting patron signups and intentions for unclaimed mosques.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ================================================================
    // REGISTRATION NOTIFICATION
    // ================================================================

    /**
     * Store a notification when a new user registers and selects a mosque.
     */
    public static function notify_new_registration( $wp_user_id, $data ) {
        $mosque_slug = sanitize_title( $data['mosque_slug'] ?? '' );
        if ( ! $mosque_slug ) return;

        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE slug = %s",
            $mosque_slug
        ) );
        if ( ! $mosque ) return;

        $wp_user  = get_userdata( $wp_user_id );
        $existing = get_option( 'ynj_pending_registrations', [] );
        $existing[] = [
            'user_id'    => $wp_user_id,
            'user_name'  => $wp_user->display_name,
            'user_email' => $wp_user->user_email,
            'mosque_id'  => $mosque->id,
            'mosque_name'=> $mosque->name,
            'time'       => current_time( 'mysql' ),
        ];
        // Keep only last 100
        $existing = array_slice( $existing, -100 );
        update_option( 'ynj_pending_registrations', $existing, false );
    }
}
