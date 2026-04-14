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

        $mosques = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
        $sub_table = YNJ_DB::table('subscribers');
        ?>
        <div class="wrap">
            <h1>YourJannah — Mosques (<?php echo count($mosques); ?>)</h1>
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
            update_option('ynj_dfm_domain', sanitize_text_field($_POST['dfm_domain'] ?? 'donationformasjid.com'));
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
}
