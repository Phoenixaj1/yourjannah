<?php
/**
 * YourJannah — WP Admin pages.
 */
if (!defined('ABSPATH')) exit;

class YNJ_Admin {

    public static function register_menu() {
        add_menu_page('YourJannah', 'YourJannah', 'manage_options', 'yn-jannah', [__CLASS__, 'page_mosques'], 'dashicons-building', 30);
        add_submenu_page('yn-jannah', 'Mosques', 'Mosques', 'manage_options', 'yn-jannah', [__CLASS__, 'page_mosques']);
        add_submenu_page('yn-jannah', 'Announcements', 'Announcements', 'manage_options', 'ynj-announcements', [__CLASS__, 'page_announcements']);
        add_submenu_page('yn-jannah', 'Events', 'Events', 'manage_options', 'ynj-events', [__CLASS__, 'page_events']);
        add_submenu_page('yn-jannah', 'Businesses', 'Businesses', 'manage_options', 'ynj-businesses', [__CLASS__, 'page_businesses']);
        add_submenu_page('yn-jannah', 'Enquiries', 'Enquiries', 'manage_options', 'ynj-enquiries', [__CLASS__, 'page_enquiries']);
        add_submenu_page('yn-jannah', 'Settings', 'Settings', 'manage_options', 'ynj-settings', [__CLASS__, 'page_settings']);
        add_submenu_page('yn-jannah', 'Seed Data', 'Seed Data', 'manage_options', 'ynj-seed', [__CLASS__, 'page_seed']);
    }

    public static function page_mosques() {
        global $wpdb;
        $table = YNJ_DB::table('mosques');

        // Handle actions
        if (isset($_POST['ynj_action']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ynj_admin')) {
            $action = sanitize_text_field($_POST['ynj_action']);
            $mid = absint($_POST['mosque_id'] ?? 0);

            if ($action === 'approve' && $mid) {
                $wpdb->update($table, ['status' => 'active'], ['id' => $mid]);
                echo '<div class="notice notice-success"><p>Mosque approved.</p></div>';
            }
            if ($action === 'suspend' && $mid) {
                $wpdb->update($table, ['status' => 'suspended'], ['id' => $mid]);
                echo '<div class="notice notice-warning"><p>Mosque suspended.</p></div>';
            }
        }

        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($paged - 1) * $per_page;

        $where = "1=1";
        if ($status_filter === 'active') $where = "status = 'active'";
        elseif ($status_filter === 'unclaimed') $where = "status = 'unclaimed'";

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $mosques = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));
        $sub_table = YNJ_DB::table('subscribers');
        $total_pages = ceil($total / $per_page);

        $count_all = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $count_active = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
        $count_unclaimed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'unclaimed'");
        ?>
        <div class="wrap">
            <h1>YourJannah — Mosques (<?php echo number_format($total); ?>)</h1>
            <ul class="subsubsub" style="margin:8px 0 16px;">
                <li><a href="<?php echo esc_url(add_query_arg(['status'=>'','paged'=>1])); ?>" <?php echo !$status_filter ? 'class="current"' : ''; ?>>All <span class="count">(<?php echo number_format($count_all); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url(add_query_arg(['status'=>'active','paged'=>1])); ?>" <?php echo $status_filter==='active' ? 'class="current"' : ''; ?>>Active <span class="count">(<?php echo $count_active; ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url(add_query_arg(['status'=>'unclaimed','paged'=>1])); ?>" <?php echo $status_filter==='unclaimed' ? 'class="current"' : ''; ?>>Unclaimed <span class="count">(<?php echo number_format($count_unclaimed); ?>)</span></a></li>
            </ul>
            <?php if ($total_pages > 1) : ?>
            <div class="tablenav"><div class="tablenav-pages">
                <span class="displaying-num"><?php echo number_format($total); ?> mosques</span>
                <?php if ($paged > 1) : ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">&laquo; Prev</a><?php endif; ?>
                <span class="tablenav-pages-navspan button disabled">Page <?php echo $paged; ?> of <?php echo $total_pages; ?></span>
                <?php if ($paged < $total_pages) : ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">Next &raquo;</a><?php endif; ?>
            </div></div>
            <?php endif; ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>City</th><th>Email</th><th>Subscribers</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($mosques as $m):
                        $subs = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sub_table WHERE mosque_id = %d AND status = 'active'", $m->id));
                    ?>
                    <tr>
                        <td><?php echo $m->id; ?></td>
                        <td><strong><?php echo esc_html($m->name); ?></strong><br><code style="font-size:11px"><?php echo esc_html($m->slug); ?></code></td>
                        <td><?php echo esc_html($m->city); ?>, <?php echo esc_html($m->postcode); ?></td>
                        <td style="font-size:12px"><?php echo esc_html($m->admin_email); ?></td>
                        <td><strong><?php echo $subs; ?></strong></td>
                        <td>
                            <?php if ($m->status === 'active'): ?>
                                <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700">Active</span>
                            <?php else: ?>
                                <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700"><?php echo esc_html(ucfirst($m->status)); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px"><?php echo date('j M Y', strtotime($m->created_at)); ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('ynj_admin'); ?>
                                <input type="hidden" name="mosque_id" value="<?php echo $m->id; ?>">
                                <?php if ($m->status !== 'active'): ?>
                                    <button type="submit" name="ynj_action" value="approve" class="button button-small button-primary">Approve</button>
                                <?php else: ?>
                                    <button type="submit" name="ynj_action" value="suspend" class="button button-small" onclick="return confirm('Suspend this mosque?')">Suspend</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_announcements() {
        global $wpdb;
        $table = YNJ_DB::table('announcements');
        $mosques = YNJ_DB::table('mosques');

        $rows = $wpdb->get_results(
            "SELECT a.*, m.name AS mosque_name FROM $table a
             LEFT JOIN $mosques m ON m.id = a.mosque_id
             ORDER BY a.created_at DESC LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>Announcements (<?php echo count($rows); ?>)</h1>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>ID</th><th>Mosque</th><th>Title</th><th>Type</th><th>Push</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo $r->id; ?></td>
                        <td><?php echo esc_html($r->mosque_name ?: '#' . $r->mosque_id); ?></td>
                        <td><strong><?php echo esc_html($r->title); ?></strong></td>
                        <td><span style="background:#e5e7eb;padding:2px 6px;border-radius:4px;font-size:11px"><?php echo esc_html($r->type); ?></span></td>
                        <td><?php echo $r->push_sent ? '<span style="color:green">&#x2705;</span>' : '—'; ?></td>
                        <td><?php echo esc_html($r->status); ?></td>
                        <td style="font-size:12px"><?php echo $r->published_at ? date('j M H:i', strtotime($r->published_at)) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_events() {
        global $wpdb;
        $table = YNJ_DB::table('events');
        $mosques = YNJ_DB::table('mosques');

        $rows = $wpdb->get_results(
            "SELECT e.*, m.name AS mosque_name FROM $table e
             LEFT JOIN $mosques m ON m.id = e.mosque_id
             ORDER BY e.event_date DESC LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>Events (<?php echo count($rows); ?>)</h1>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>ID</th><th>Mosque</th><th>Title</th><th>Date</th><th>Type</th><th>Capacity</th><th>Registered</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo $r->id; ?></td>
                        <td><?php echo esc_html($r->mosque_name); ?></td>
                        <td><strong><?php echo esc_html($r->title); ?></strong></td>
                        <td><?php echo esc_html($r->event_date); ?> <?php echo esc_html($r->start_time); ?></td>
                        <td><?php echo esc_html($r->event_type); ?></td>
                        <td><?php echo $r->max_capacity ?: 'Unlimited'; ?></td>
                        <td><?php echo $r->registered_count; ?></td>
                        <td><?php echo esc_html($r->status); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_businesses() {
        global $wpdb;
        $table = YNJ_DB::table('businesses');
        $mosques = YNJ_DB::table('mosques');

        // Handle approve/reject
        if (isset($_POST['ynj_biz_action']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ynj_admin')) {
            $action = sanitize_text_field($_POST['ynj_biz_action']);
            $bid = absint($_POST['biz_id'] ?? 0);
            if ($action === 'approve' && $bid) {
                $wpdb->update($table, ['status' => 'active', 'verified' => 1], ['id' => $bid]);
                echo '<div class="notice notice-success"><p>Business approved.</p></div>';
            }
            if ($action === 'reject' && $bid) {
                $wpdb->update($table, ['status' => 'rejected'], ['id' => $bid]);
                echo '<div class="notice notice-warning"><p>Business rejected.</p></div>';
            }
        }

        $rows = $wpdb->get_results(
            "SELECT b.*, m.name AS mosque_name FROM $table b
             LEFT JOIN $mosques m ON m.id = b.mosque_id
             ORDER BY b.monthly_fee_pence DESC, b.created_at DESC LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>Business Directory (<?php echo count($rows); ?>)</h1>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Business</th><th>Category</th><th>Near Mosque</th><th>Fee/month</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->business_name); ?></strong><br><span style="font-size:12px;color:#666"><?php echo esc_html($r->owner_name); ?> &middot; <?php echo esc_html($r->phone); ?></span></td>
                        <td><?php echo esc_html($r->category); ?></td>
                        <td><?php echo esc_html($r->mosque_name); ?></td>
                        <td>&pound;<?php echo number_format($r->monthly_fee_pence / 100); ?></td>
                        <td>
                            <?php if ($r->status === 'active'): ?>
                                <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-size:12px">Active</span>
                            <?php elseif ($r->status === 'pending'): ?>
                                <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:12px">Pending</span>
                            <?php else: ?>
                                <span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:12px"><?php echo esc_html(ucfirst($r->status)); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r->status === 'pending'): ?>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('ynj_admin'); ?>
                                <input type="hidden" name="biz_id" value="<?php echo $r->id; ?>">
                                <button type="submit" name="ynj_biz_action" value="approve" class="button button-small button-primary">Approve</button>
                                <button type="submit" name="ynj_biz_action" value="reject" class="button button-small">Reject</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_enquiries() {
        global $wpdb;
        $table = YNJ_DB::table('enquiries');
        $mosques = YNJ_DB::table('mosques');

        $rows = $wpdb->get_results(
            "SELECT e.*, m.name AS mosque_name FROM $table e
             LEFT JOIN $mosques m ON m.id = e.mosque_id
             ORDER BY e.created_at DESC LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>Enquiries (<?php echo count($rows); ?>)</h1>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>From</th><th>Mosque</th><th>Subject</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->name); ?></strong><br><span style="font-size:12px"><?php echo esc_html($r->email); ?></span></td>
                        <td><?php echo esc_html($r->mosque_name); ?></td>
                        <td><?php echo esc_html($r->subject); ?></td>
                        <td><?php echo esc_html($r->type); ?></td>
                        <td>
                            <?php if ($r->status === 'new'): ?>
                                <span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700">New</span>
                            <?php else: ?>
                                <span style="color:#999;font-size:12px"><?php echo esc_html(ucfirst($r->status)); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px"><?php echo date('j M H:i', strtotime($r->created_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function page_settings() {
        if (isset($_POST['ynj_save_settings']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ynj_settings')) {
            update_option('ynj_stripe_secret_key', sanitize_text_field($_POST['stripe_sk'] ?? ''));
            update_option('ynj_stripe_public_key', sanitize_text_field($_POST['stripe_pk'] ?? ''));
            update_option('ynj_stripe_webhook_secret', sanitize_text_field($_POST['stripe_wh'] ?? ''));
            update_option('ynj_dfm_domain', sanitize_text_field($_POST['dfm_domain'] ?? 'donationformasjid.com'));
            update_option('ynj_dfm_webhook_secret', sanitize_text_field($_POST['dfm_wh_secret'] ?? ''));
            update_option('ynj_aladhan_method', sanitize_text_field($_POST['aladhan_method'] ?? '2'));
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>YourJannah — Settings</h1>
            <form method="post">
                <?php wp_nonce_field('ynj_settings'); ?>

                <h2>Stripe (for Business Directory Payments)</h2>
                <table class="form-table">
                    <tr><th>Stripe Public Key</th><td><input type="text" name="stripe_pk" value="<?php echo esc_attr(get_option('ynj_stripe_public_key', '')); ?>" class="regular-text" placeholder="pk_live_..."></td></tr>
                    <tr><th>Stripe Secret Key</th><td><input type="password" name="stripe_sk" value="<?php echo esc_attr(get_option('ynj_stripe_secret_key', '')); ?>" class="regular-text" placeholder="sk_live_..."></td></tr>
                    <tr><th>Webhook Secret</th><td><input type="password" name="stripe_wh" value="<?php echo esc_attr(get_option('ynj_stripe_webhook_secret', '')); ?>" class="regular-text" placeholder="whsec_...">
                    <p class="description">Webhook URL: <code><?php echo home_url('/wp-json/ynj/v1/stripe/webhook'); ?></code></p></td></tr>
                    <tr><th>Status</th><td><?php
                        $configured = YNJ_Stripe::is_configured();
                        echo $configured
                            ? '<span style="color:green">&#x2705; Stripe configured</span>'
                            : '<span style="color:red">&#x274C; Not configured — set keys above or they will fall back to yourniyyah-checkout keys</span>';
                    ?></td></tr>
                </table>

                <h2>Prayer Times</h2>
                <table class="form-table">
                    <tr><th>Aladhan Calculation Method</th>
                    <td><select name="aladhan_method">
                        <?php
                        $methods = [1 => 'University of Islamic Sciences, Karachi', 2 => 'Islamic Society of North America (ISNA)', 3 => 'Muslim World League (MWL)', 4 => 'Umm al-Qura, Makkah', 5 => 'Egyptian General Authority', 7 => 'Institute of Geophysics, Tehran', 8 => 'Gulf Region', 9 => 'Kuwait', 10 => 'Qatar', 11 => 'Majlis Ugama Islam Singapura', 12 => 'UOIF (France)', 13 => 'Diyanet (Turkey)', 14 => 'Spiritual Administration of Muslims of Russia', 15 => 'Moonsighting Committee'];
                        $current = get_option('ynj_aladhan_method', '2');
                        foreach ($methods as $id => $name):
                        ?>
                            <option value="<?php echo $id; ?>" <?php selected($current, $id); ?>><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select></td></tr>
                </table>

                <h2>DonationForMasjid Integration</h2>
                <table class="form-table">
                    <tr><th>DFM Domain</th><td><input type="text" name="dfm_domain" value="<?php echo esc_attr(get_option('ynj_dfm_domain', 'donationformasjid.com')); ?>" class="regular-text"></td></tr>
                    <tr><th>DFM Webhook Secret</th><td><input type="password" name="dfm_wh_secret" value="<?php echo esc_attr(get_option('ynj_dfm_webhook_secret', '')); ?>" class="regular-text" placeholder="Shared secret for auto-sync">
                    <p class="description">Webhook URL: <code><?php echo home_url('/wp-json/ynj/v1/dfm/webhook'); ?></code><br>Set the same secret in DFM to auto-update fundraising progress.</p></td></tr>
                </table>

                <h2>Push Notifications (VAPID)</h2>
                <table class="form-table">
                    <tr><th>Public Key</th><td><code style="word-break:break-all"><?php echo esc_html(get_option('ynj_vapid_public', 'Not generated yet')); ?></code></td></tr>
                    <tr><th>Status</th><td><?php echo get_option('ynj_vapid_private') ? '<span style="color:green">&#x2705; Keys generated</span>' : '<span style="color:red">Not configured</span>'; ?></td></tr>
                </table>

                <?php submit_button('Save Settings', 'primary', 'ynj_save_settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Seed Data page — inserts dummy content for the test mosque.
     */
    public static function page_seed() {
        global $wpdb;

        if ( isset( $_POST['ynj_seed'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'ynj_seed' ) ) {
            $mosque_id = absint( $_POST['mosque_id'] ?? 1 );
            $seeded = [];

            // 1. Announcements
            $tbl = YNJ_DB::table( 'announcements' );
            $announcements = [
                [ 'Ramadan Timetable Available', 'The Ramadan timetable for this year is now available. Pick up your copy from the main entrance or check the Prayer Times section of the app.', 'general', 1 ],
                [ 'Mosque Renovation Update', 'Phase 2 of the wudu area renovation is complete. Jazakallah Khayr to all who donated. The new facilities are now open.', 'general', 0 ],
                [ 'Weekly Halaqa — Every Wednesday', 'Join us every Wednesday after Isha for our weekly halaqa series on Purification of the Heart with Shaykh Ahmed.', 'event', 0 ],
                [ 'Car Park Closure — Saturday', 'The mosque car park will be closed this Saturday 10am-2pm for resurfacing. Please use the secondary car park on Elm Street.', 'urgent', 1 ],
                [ 'Quran Classes for Children', 'New term of Quran classes starts next month. Ages 5-12, Saturdays 10am-12pm. Register at the office or call 0121 234 5678.', 'general', 0 ],
            ];
            foreach ( $announcements as $a ) {
                $wpdb->insert( $tbl, [
                    'mosque_id'    => $mosque_id,
                    'title'        => $a[0],
                    'body'         => $a[1],
                    'type'         => $a[2],
                    'pinned'       => $a[3],
                    'status'       => 'published',
                    'published_at' => current_time( 'mysql' ),
                ] );
            }
            $seeded[] = count( $announcements ) . ' announcements';

            // 2. Events
            $tbl = YNJ_DB::table( 'events' );
            $events = [
                [ 'Community Iftar', 'Open iftar for the community. All welcome. Please RSVP to help us cater.', date( 'Y-m-d', strtotime( '+3 days' ) ), '18:30:00', '20:00:00', 'Main Hall', 'community', 200 ],
                [ 'Sisters Circle', 'Monthly sisters gathering — discussion on Raising Muslim Children in the West.', date( 'Y-m-d', strtotime( '+7 days' ) ), '11:00:00', '13:00:00', 'Sisters Room', 'education', 50 ],
                [ 'Youth Football Tournament', 'Inter-mosque 5-a-side tournament. Ages 14-18. Register your team by this Friday.', date( 'Y-m-d', strtotime( '+14 days' ) ), '10:00:00', '16:00:00', 'Sports Ground', 'sports', 100 ],
                [ 'Eid Preparation Workshop', 'Learn to cook traditional Eid sweets and decorate your home. Materials provided.', date( 'Y-m-d', strtotime( '+21 days' ) ), '14:00:00', '17:00:00', 'Community Kitchen', 'workshop', 30 ],
                [ 'Marriage Seminar', 'A comprehensive seminar on Islamic marriage — rights, responsibilities, and communication.', date( 'Y-m-d', strtotime( '+30 days' ) ), '19:00:00', '21:00:00', 'Conference Room', 'education', 80 ],
            ];
            foreach ( $events as $e ) {
                $wpdb->insert( $tbl, [
                    'mosque_id'    => $mosque_id,
                    'title'        => $e[0],
                    'description'  => $e[1],
                    'event_date'   => $e[2],
                    'start_time'   => $e[3],
                    'end_time'     => $e[4],
                    'location'     => $e[5],
                    'event_type'   => $e[6],
                    'max_capacity' => $e[7],
                    'status'       => 'published',
                ] );
            }
            $seeded[] = count( $events ) . ' events';

            // 3. Jumu'ah Times
            $tbl = YNJ_DB::table( 'jumuah_times' );
            $jumuah = [
                [ 'First Jumu\'ah', '12:30:00', '13:00:00', 'English' ],
                [ 'Second Jumu\'ah', '13:30:00', '14:00:00', 'Arabic' ],
            ];
            foreach ( $jumuah as $j ) {
                $wpdb->insert( $tbl, [
                    'mosque_id'    => $mosque_id,
                    'slot_name'    => $j[0],
                    'khutbah_time' => $j[1],
                    'salah_time'   => $j[2],
                    'language'     => $j[3],
                    'enabled'      => 1,
                ] );
            }
            $seeded[] = count( $jumuah ) . ' Jumu\'ah slots';

            // 4. Rooms
            $tbl = YNJ_DB::table( 'rooms' );
            $rooms = [
                [ 'Main Hall', 'Large prayer hall suitable for events, lectures and community gatherings.', 500, 5000, 25000 ],
                [ 'Conference Room', 'Air-conditioned meeting room with projector. Seats 30.', 30, 2000, 10000 ],
                [ 'Community Kitchen', 'Fully equipped kitchen for events and catering.', 15, 1500, 8000 ],
                [ 'Sisters Room', 'Private room for sisters\' activities and study circles.', 40, 0, 0 ],
                [ 'Classroom A', 'Multi-purpose classroom for Quran classes and education.', 25, 1000, 5000 ],
            ];
            foreach ( $rooms as $r ) {
                $wpdb->insert( $tbl, [
                    'mosque_id'          => $mosque_id,
                    'name'               => $r[0],
                    'description'        => $r[1],
                    'capacity'           => $r[2],
                    'hourly_rate_pence'  => $r[3],
                    'daily_rate_pence'   => $r[4],
                    'status'             => 'active',
                ] );
            }
            $seeded[] = count( $rooms ) . ' rooms';

            // 5. Businesses
            $tbl = YNJ_DB::table( 'businesses' );
            $businesses = [
                [ 'Al-Madina Grocery', 'Ahmed Patel', 'Grocery', 'Halal groceries, spices, and international foods. Open 7 days.', '0121 234 5678', '10 High Street, B90 3AA' ],
                [ 'Barakah Bookstore', 'Fatima Khan', 'Books', 'Islamic books, gifts, and educational materials for all ages.', '0121 345 6789', '22 Station Road, B90 3AB' ],
                [ 'Noor Solicitors', 'Mohammed Ali', 'Legal', 'Specialising in Islamic wills, immigration, and family law.', '0121 456 7890', '5 Park Avenue, B90 3AC' ],
                [ 'Sabeel Dentistry', 'Dr. Aisha Rahman', 'Health', 'Family dental practice. Female dentist available. NHS & private.', '0121 567 8901', '15 Church Lane, B90 3AD' ],
                [ 'Crescent Catering', 'Hassan Mahmood', 'Catering', 'Halal catering for weddings, events, and corporate functions.', '07912 345678', '8 Industrial Estate, B90 3AE' ],
                [ 'Taqwa Tutoring', 'Zainab Hussain', 'Education', 'Maths, English, and Science tutoring. GCSE & A-Level specialists.', '07898 765432', 'Online & Home Visits' ],
            ];
            foreach ( $businesses as $b ) {
                $wpdb->insert( $tbl, [
                    'mosque_id'      => $mosque_id,
                    'business_name'  => $b[0],
                    'owner_name'     => $b[1],
                    'category'       => $b[2],
                    'description'    => $b[3],
                    'phone'          => $b[4],
                    'address'        => $b[5],
                    'monthly_fee_pence' => 3000,
                    'status'         => 'active',
                    'verified'       => 1,
                ] );
            }
            $seeded[] = count( $businesses ) . ' businesses';

            // 6. Services
            $tbl = YNJ_DB::table( 'services' );
            $services = [
                [ 'Imam Yusuf', 'Imam / Scholar', 'Nikah, funeral, counselling services. Available by appointment.', '07700 900001', 'Solihull & surrounding areas' ],
                [ 'Sister Khadijah', 'Quran Teacher', 'One-to-one Quran and tajweed lessons for sisters and children.', '07700 900002', 'Solihull, Birmingham' ],
                [ 'Brother Omar', 'Arabic Tutor', 'Arabic language classes for beginners and intermediate students.', '07700 900003', 'Online only' ],
                [ 'Islamic Counselling Service', 'Counselling', 'Confidential Islamic counselling and family mediation.', '07700 900004', 'West Midlands' ],
            ];
            foreach ( $services as $s ) {
                $wpdb->insert( $tbl, [
                    'mosque_id'      => $mosque_id,
                    'provider_name'  => $s[0],
                    'service_type'   => $s[1],
                    'description'    => $s[2],
                    'phone'          => $s[3],
                    'area_covered'   => $s[4],
                    'monthly_fee_pence' => 1000,
                    'status'         => 'active',
                ] );
            }
            $seeded[] = count( $services ) . ' services';

            echo '<div class="notice notice-success"><p><strong>Seeded:</strong> ' . implode( ', ', $seeded ) . ' for mosque #' . $mosque_id . '</p></div>';
        }

        // List mosques for selection
        $mosques = $wpdb->get_results( "SELECT id, name, slug FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY id ASC LIMIT 50" );
        ?>
        <div class="wrap">
            <h1>Seed Dummy Data</h1>
            <p>Insert realistic test data (announcements, events, Jumu'ah times, rooms, businesses, services) for a mosque.</p>

            <form method="post">
                <?php wp_nonce_field( 'ynj_seed' ); ?>

                <table class="form-table">
                    <tr>
                        <th>Target Mosque</th>
                        <td>
                            <select name="mosque_id">
                                <?php foreach ( $mosques as $m ): ?>
                                    <option value="<?php echo $m->id; ?>">
                                        #<?php echo $m->id; ?> — <?php echo esc_html( $m->name ); ?> (<?php echo esc_html( $m->slug ); ?>)
                                    </option>
                                <?php endforeach; ?>
                                <?php if ( empty( $mosques ) ): ?>
                                    <option value="1">No mosques found — will use ID 1</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Seed Dummy Data', 'primary', 'ynj_seed' ); ?>
            </form>

            <hr>
            <h2>Current Data Counts</h2>
            <table class="wp-list-table widefat striped" style="max-width:500px">
                <tbody>
                    <?php
                    $tables = [ 'mosques', 'announcements', 'events', 'jumuah_times', 'rooms', 'businesses', 'services', 'subscribers' ];
                    foreach ( $tables as $t ) {
                        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . YNJ_DB::table( $t ) );
                        echo '<tr><td><strong>' . esc_html( $t ) . '</strong></td><td>' . $count . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
