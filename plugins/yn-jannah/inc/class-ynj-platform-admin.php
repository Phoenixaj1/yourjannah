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
        add_submenu_page( 'ynj-platform', 'Enquiries', 'Enquiries', 'manage_options', 'ynj-platform-enquiries', [ __CLASS__, 'page_enquiries' ] );
    }

    // ================================================================
    // DASHBOARD
    // ================================================================

    public static function page_dashboard() {
        global $wpdb;

        $mosques    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status = 'active'" );
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
                    [ 'num' => $mosques,    'label' => 'Active Mosques',    'color' => '#16a34a' ],
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
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page = 50;
        $offset = ( $paged - 1 ) * $per_page;

        $where = "m.status = 'active'";
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
            <form method="get" style="margin:16px 0;">
                <input type="hidden" name="page" value="ynj-mosques">
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
                    <tr><th>Name</th><th>City</th><th>Postcode</th><th>Admin Email</th><th>Members</th><th>Status</th><th>Created</th></tr>
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
                    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Mosque</th><th>Verified</th><th>Joined</th></tr>
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

        // Pipeline: unclaimed mosques ranked by demand
        $pipeline = $wpdb->get_results(
            "SELECT m.id, m.name, m.city, m.postcode, m.slug,
                    COALESCE(pc.patron_count, 0) AS patron_count,
                    COALESCE(pc.patron_revenue, 0) AS patron_revenue,
                    COALESCE(ic.intention_count, 0) AS intention_count,
                    COALESCE(ic.intention_pence, 0) AS intention_pence,
                    (COALESCE(pc.patron_count, 0) + COALESCE(ic.intention_count, 0)) AS total_demand
             FROM $mt m
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS patron_count, SUM(amount_pence) AS patron_revenue
                 FROM $pt WHERE status = 'active' GROUP BY mosque_id
             ) pc ON pc.mosque_id = m.id
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS intention_count, SUM(amount_pence) AS intention_pence
                 FROM $it WHERE status = 'active' GROUP BY mosque_id
             ) ic ON ic.mosque_id = m.id
             WHERE m.status = 'unclaimed'
             HAVING total_demand > 0
             ORDER BY total_demand DESC
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
                        <th>Postcode</th>
                        <th style="text-align:center;">Paying Patrons</th>
                        <th style="text-align:center;">Intentions</th>
                        <th style="text-align:right;">Monthly Revenue</th>
                        <th style="text-align:right;">Potential</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $pipeline as $row ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $row->name ); ?></strong></td>
                        <td><?php echo esc_html( $row->city ); ?></td>
                        <td><?php echo esc_html( $row->postcode ); ?></td>
                        <td style="text-align:center;"><?php echo (int) $row->patron_count; ?></td>
                        <td style="text-align:center;"><?php echo (int) $row->intention_count; ?></td>
                        <td style="text-align:right;">&pound;<?php echo number_format( (int) $row->patron_revenue / 100 ); ?>/mo</td>
                        <td style="text-align:right;">&pound;<?php echo number_format( ( (int) $row->patron_revenue + (int) $row->intention_pence ) / 100 ); ?>/mo</td>
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
}
