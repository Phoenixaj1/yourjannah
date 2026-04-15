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
        add_submenu_page( 'ynj-platform', 'Enquiries', 'Enquiries', 'manage_options', 'ynj-enquiries', [ __CLASS__, 'page_enquiries' ] );
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
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $where = "status = 'active'";
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (name LIKE %s OR city LIKE %s OR postcode LIKE %s)", $like, $like, $like );
        }
        $mosques = $wpdb->get_results( "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT 200" );

        // Get member counts per mosque
        $user_table = YNJ_DB::table( 'users' );
        ?>
        <div class="wrap">
            <h1>🕌 All Mosques (<?php echo count( $mosques ); ?>)</h1>
            <form method="get" style="margin:16px 0;">
                <input type="hidden" name="page" value="ynj-mosques">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search mosques..." class="regular-text">
                <?php submit_button( 'Search', 'secondary', '', false ); ?>
            </form>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr><th>Name</th><th>City</th><th>Postcode</th><th>Admin Email</th><th>Members</th><th>Status</th><th>Created</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $mosques as $m ) :
                    $members = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM " . YNJ_DB::table( 'user_subscriptions' ) . " WHERE mosque_id = %d AND status = 'active'",
                        $m->id
                    ) );
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

        $where = "u.status = 'active'";
        if ( $mosque_filter ) {
            $where .= $wpdb->prepare( " AND u.favourite_mosque_id = %d", $mosque_filter );
        }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( " AND (u.name LIKE %s OR u.email LIKE %s)", $like, $like );
        }

        $mt = YNJ_DB::table( 'mosques' );
        $users = $wpdb->get_results(
            "SELECT u.*, m.name AS mosque_name FROM $table u LEFT JOIN $mt m ON m.id = u.favourite_mosque_id WHERE $where ORDER BY u.created_at DESC LIMIT 200"
        );

        // Mosque filter dropdown
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status = 'active' ORDER BY name" );
        ?>
        <div class="wrap">
            <h1>👥 All Members (<?php echo count( $users ); ?>)</h1>
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

        $recipients = [];

        if ( $segment === 'all' ) {
            $recipients = $wpdb->get_results( "SELECT name, email, push_endpoint, push_p256dh, push_auth FROM $ut WHERE status = 'active' AND email != ''" );
        } elseif ( $segment === 'mosque' && $mosque_id ) {
            $st = YNJ_DB::table( 'user_subscriptions' );
            $recipients = $wpdb->get_results( $wpdb->prepare(
                "SELECT u.name, u.email, u.push_endpoint, u.push_p256dh, u.push_auth
                 FROM $st s INNER JOIN $ut u ON u.id = s.user_id
                 WHERE s.mosque_id = %d AND s.status = 'active' AND u.status = 'active'",
                $mosque_id
            ) );
        } elseif ( $segment === 'patrons' ) {
            $recipients = $wpdb->get_results(
                "SELECT u.name, u.email, u.push_endpoint, u.push_p256dh, u.push_auth
                 FROM $pt p INNER JOIN $ut u ON u.email = p.user_email
                 WHERE p.status = 'active' AND u.status = 'active'"
            );
        }

        $count = 0;
        foreach ( $recipients as $r ) {
            if ( $method === 'push' && $r->push_endpoint ) {
                $payload = wp_json_encode( [
                    'title' => $subject,
                    'body'  => wp_strip_all_tags( $body ),
                    'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
                    'url'   => '/',
                ] );
                YNJ_Push::send_push( $r->push_endpoint, $r->push_p256dh, $r->push_auth, $payload );
                $count++;
            } elseif ( $method === 'email' && is_email( $r->email ) ) {
                $html = '<div style="font-family:Inter,system-ui,sans-serif;max-width:600px;margin:0 auto;">'
                    . '<div style="background:linear-gradient(135deg,#0a1628,#00ADEF);color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center;">'
                    . '<h2 style="margin:0;">YourJannah</h2></div>'
                    . '<div style="background:#fff;border:1px solid #e5e5e5;border-top:none;padding:24px;border-radius:0 0 12px 12px;">'
                    . '<h3>' . esc_html( $subject ) . '</h3>'
                    . wp_kses_post( $body )
                    . '</div></div>';

                add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
                wp_mail( $r->email, $subject . ' — YourJannah', $html );
                remove_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
                $count++;
            }
        }

        return $count;
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
}
