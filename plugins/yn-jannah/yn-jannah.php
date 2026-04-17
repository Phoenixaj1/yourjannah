<?php
/**
 * Plugin Name: YourJannah
 * Description: Mosque community platform — prayer times, announcements, events, bookings, business directory, donations.
 * Version: 1.0.0
 * Author: YourNiyyah
 */
if (!defined('ABSPATH')) exit;

define('YNJ_VERSION', '2.4.0');
define('YNJ_DIR', plugin_dir_path(__FILE__));
define('YNJ_URL', plugin_dir_url(__FILE__));
define('YNJ_TABLE_PREFIX', 'ynj_');

// One-time demo seeder (run via /wp-admin/admin.php?ynj_seed_extra=1)
if ( is_admin() && file_exists( YNJ_DIR . 'seed-demo-extra.php' ) ) {
    require_once YNJ_DIR . 'seed-demo-extra.php';
}

// Autoloader
spl_autoload_register(function($class) {
    $map = [
        'YNJ_DB'              => 'inc/class-ynj-db.php',
        'YNJ_Auth'            => 'inc/class-ynj-auth.php',
        'YNJ_Prayer'          => 'inc/class-ynj-prayer.php',
        'YNJ_Push'            => 'inc/class-ynj-push.php',
        // Archived — replaced by yourjannah-starter theme templates
        // 'YNJ_Router'       => '_archive/class-ynj-router.php',
        // 'YNJ_Renderer'     => '_archive/class-ynj-renderer.php',
        'YNJ_Admin'           => 'inc/class-ynj-admin.php',
        'YNJ_Dashboard'       => 'dashboard/class-ynj-dashboard.php',
        'YNJ_API_Mosques'     => 'api/class-ynj-api-mosques.php',
        'YNJ_API_Prayer'      => 'api/class-ynj-api-prayer.php',
        'YNJ_API_Announcements' => 'api/class-ynj-api-announcements.php',
        'YNJ_API_Events'      => 'api/class-ynj-api-events.php',
        'YNJ_API_Bookings'    => 'api/class-ynj-api-bookings.php',
        'YNJ_API_Directory'   => 'api/class-ynj-api-directory.php',
        'YNJ_API_Subscribe'   => 'api/class-ynj-api-subscribe.php',
        'YNJ_API_Admin'       => 'api/class-ynj-api-admin.php',
        'YNJ_Stripe'          => 'inc/class-ynj-stripe.php',
        'YNJ_API_Stripe'      => 'api/class-ynj-api-stripe.php',
        'YNJ_API_Search'      => 'api/class-ynj-api-search.php',
        'YNJ_Cron'            => 'inc/class-ynj-cron.php',
        'YNJ_Notify'          => 'inc/class-ynj-notify.php',
        'YNJ_User_Auth'       => 'inc/class-ynj-user-auth.php',
        'YNJ_API_User'        => 'api/class-ynj-api-user.php',
        'YNJ_API_Campaigns'   => 'api/class-ynj-api-campaigns.php',
        'YNJ_API_DFM_Webhook' => 'api/class-ynj-api-dfm-webhook.php',
        'YNJ_API_Classes'     => 'api/class-ynj-api-classes.php',
        'YNJ_API_Patrons'     => 'api/class-ynj-api-patrons.php',
        'YNJ_API_Madrassah'   => 'api/class-ynj-api-madrassah.php',
        'YNJ_API_Subscriptions'    => 'api/class-ynj-api-subscriptions.php',
        'YNJ_API_Masjid_Services'  => 'api/class-ynj-api-masjid-services.php',
        'YNJ_WP_Auth'              => 'inc/class-ynj-wp-auth.php',
        'YNJ_Platform_Admin'       => 'inc/class-ynj-platform-admin.php',
        'YNJ_API_Media'            => 'api/class-ynj-api-media.php',
        'YNJ_Cache'                => 'inc/class-ynj-cache.php',
        'YNJ_API_Points'           => 'api/class-ynj-api-points.php',
        'YNJ_API_Intentions'       => 'api/class-ynj-api-intentions.php',
        'YNJ_API_Sponsor_YJ'       => 'api/class-ynj-api-sponsor-yj.php',
        'YNJ_Pool_Ledger'          => 'inc/class-ynj-pool-ledger.php',
        'YNJ_API_Donations'        => 'api/class-ynj-api-donations.php',
    ];
    if (isset($map[$class])) {
        require_once YNJ_DIR . $map[$class];
    }
});

// Install DB + roles on activation
register_activation_hook(__FILE__, ['YNJ_DB', 'install']);
register_activation_hook(__FILE__, ['YNJ_WP_Auth', 'install_roles']);
register_deactivation_hook(__FILE__, ['YNJ_WP_Auth', 'remove_roles']);

// Ensure roles exist (in case plugin was updated without deactivation/reactivation)
add_action('init', function() {
    if ( ! get_role( 'ynj_mosque_admin' ) ) {
        YNJ_WP_Auth::install_roles();
    }
    // Auto-configure Stripe keys on first load
    YNJ_Stripe::auto_configure();
    // Run DB migrations if schema version changed (once only, not every page load)
    $db_ver = get_option( 'ynj_db_version', '' );
    if ( $db_ver !== YNJ_DB::SCHEMA_VERSION && ! get_transient( 'ynj_db_migrating' ) ) {
        set_transient( 'ynj_db_migrating', 1, 60 ); // Prevent concurrent migrations
        YNJ_DB::install();
        delete_transient( 'ynj_db_migrating' );
    }
}, 5);

// AJAX handler to set WP auth cookie (called after JS login/register)
add_action('wp_ajax_nopriv_ynj_set_session', function() {
    $wp_user_id = absint($_POST['wp_user_id'] ?? 0);
    if ($wp_user_id && get_userdata($wp_user_id)) {
        wp_set_auth_cookie($wp_user_id, true);
    }
    wp_send_json(['ok' => true]);
});
add_action('wp_ajax_ynj_set_session', function() {
    wp_send_json(['ok' => true]); // Already logged in
});

// Configure wp_mail — force SMTP via localhost (Cloudways Postfix)
add_filter('wp_mail_from', function() { return 'noreply@yourjannah.com'; });
add_filter('wp_mail_from_name', function() { return 'YourJannah'; });
add_action('phpmailer_init', function($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'localhost';
    $phpmailer->Port = 25;
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPAutoTLS = false;
});

// Test email endpoint — works without admin login via secret key
add_action('init', function() {
    if (isset($_GET['ynj_test_email']) && isset($_GET['key']) && $_GET['key'] === 'ynj_mail_test_2026') {
        $to = sanitize_email($_GET['ynj_test_email']);
        if (!$to) wp_die('Provide email: ?ynj_test_email=you@email.com&key=ynj_mail_test_2026');
        $sent = wp_mail($to, 'YourJannah Test Email', "Assalamu Alaikum!\n\nThis is a test email from YourJannah.\nIf you received this, email sending is working.\n\nTime: " . current_time('mysql') . "\n\nYourJannah Team");
        if ($sent) {
            wp_die("✅ Email sent to $to. Check inbox + spam folder.");
        } else {
            global $phpmailer;
            $error = '';
            if (isset($phpmailer) && is_object($phpmailer)) {
                $error = $phpmailer->ErrorInfo;
            }
            wp_die("❌ wp_mail() failed. Error: " . ($error ?: 'Unknown') . "<br>Last PHP error: " . print_r(error_get_last(), true));
        }
    }
});

// Auto-upgrade DB on version change
add_action('admin_init', function() {
    $installed = get_option('ynj_db_version', '');
    if ($installed !== YNJ_VERSION || isset($_GET['ynj_force_db'])) {
        YNJ_DB::install();
        update_option('ynj_db_version', YNJ_VERSION);
        if (isset($_GET['ynj_force_db'])) {
            wp_die('DB upgrade complete. Tables created/updated. <a href="' . admin_url() . '">Back to admin</a>');
        }
    }
    // Admin user already created — code removed to avoid slow init
    // Create test user for patron testing
    if (isset($_GET['ynj_create_test_user'])) {
        $email = 'test@yourjannah.com';
        $pass = 'test1234';
        if (!email_exists($email)) {
            $uid = wp_create_user($email, $pass, $email);
            wp_update_user(['ID' => $uid, 'display_name' => 'Test User', 'first_name' => 'Test']);
            $u = new \WP_User($uid);
            $u->add_role('ynj_congregation');
            // Create ynj_users record
            global $wpdb;
            $wpdb->insert(YNJ_DB::table('users'), [
                'name' => 'Test User', 'email' => $email, 'phone' => '',
                'password_hash' => wp_hash_password($pass), 'status' => 'active',
            ]);
            $ynj_id = $wpdb->insert_id;
            update_user_meta($uid, 'ynj_user_id', $ynj_id);
            wp_die("Test user created: $email / $pass (WP ID: $uid, YNJ ID: $ynj_id). <a href='" . admin_url() . "'>Back</a>");
        } else {
            wp_die("User $email already exists. Password: $pass. <a href='" . admin_url() . "'>Back</a>");
        }
    }
    // Fix duplicate jumuah/announcements/events from double-seed
    if (isset($_GET['ynj_fix_dupes'])) {
        global $wpdb;
        $p = $wpdb->prefix . 'ynj_';
        $fixed = 0;
        foreach (['jumuah_times', 'announcements', 'events', 'businesses', 'services', 'rooms', 'subscribers', 'enquiries'] as $t) {
            // Delete duplicates keeping lowest ID
            $dupes = $wpdb->query("DELETE t1 FROM {$p}{$t} t1 INNER JOIN {$p}{$t} t2 WHERE t1.id > t2.id AND t1.mosque_id = t2.mosque_id AND t1." . ($t === 'jumuah_times' ? 'slot_name' : ($t === 'businesses' ? 'business_name' : ($t === 'services' ? 'provider_name' : ($t === 'rooms' ? 'name' : ($t === 'subscribers' ? 'email' : 'title'))))) . " = t2." . ($t === 'jumuah_times' ? 'slot_name' : ($t === 'businesses' ? 'business_name' : ($t === 'services' ? 'provider_name' : ($t === 'rooms' ? 'name' : ($t === 'subscribers' ? 'email' : 'title'))))));
            if ($dupes) $fixed += $dupes;
        }
        wp_die("Removed $fixed duplicate rows. <a href='" . admin_url() . "'>Back</a>");
    }
    // Fix seeded mosques to unclaimed (one-time fix)
    if (isset($_GET['ynj_fix_unclaimed'])) {
        global $wpdb;
        $t = $wpdb->prefix . 'ynj_mosques';
        // Set all mosques without an admin to 'unclaimed' (except the demo mosque)
        $updated = $wpdb->query("UPDATE $t SET status = 'unclaimed' WHERE admin_email = '' AND admin_token_hash = '' AND setup_complete = 0 AND id > 1");
        wp_die("Fixed $updated mosques to 'unclaimed'. <a href='" . admin_url() . "'>Back</a>");
    }
    // Seed full demo data (admin only)
    if (isset($_GET['ynj_seed_full'])) {
        require_once YNJ_DIR . 'seed-full.php';
        echo '</pre><p><a href="' . admin_url() . '">Back to admin</a></p>';
        exit;
    }
    // Seed mosques trigger (admin only)
    if (isset($_GET['ynj_seed_mosques'])) {
        require_once YNJ_DIR . 'seed-import-mosques.php';
        echo '</pre><p><a href="' . admin_url() . '">Back to admin</a></p>';
        exit;
    }
});

// Register REST API routes
add_action('rest_api_init', function() {
    YNJ_API_Mosques::register();
    YNJ_API_Prayer::register();
    YNJ_API_Announcements::register();
    YNJ_API_Events::register();
    YNJ_API_Bookings::register();
    YNJ_API_Directory::register();
    YNJ_API_Subscribe::register();
    YNJ_API_Admin::register();
    YNJ_API_Stripe::register();
    YNJ_API_Search::register();
    YNJ_API_User::register();
    YNJ_API_Campaigns::register();
    YNJ_API_DFM_Webhook::register();
    YNJ_API_Classes::register();
    YNJ_API_Patrons::register();
    YNJ_API_Madrassah::register();
    YNJ_API_Subscriptions::register();
    YNJ_API_Masjid_Services::register();
    YNJ_API_Media::register();
    YNJ_API_Points::register();
    YNJ_API_Intentions::register();
    YNJ_API_Sponsor_YJ::register();
    YNJ_API_Donations::register();
});

// Admin menus
if (is_admin()) {
    add_action('admin_menu', ['YNJ_Admin', 'register_menu']);
    YNJ_Platform_Admin::register();
}

// Frontend routing — now handled by yourjannah-starter theme templates
// Old router archived to _archive/class-ynj-router.php

// Serve /sw.js — create the physical file if it doesn't exist
add_action('init', function() {
    // Check if sw.js needs serving via PHP (Nginx might block non-existent .js files)
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    if ($uri === '/sw.js' || $uri === '/sw.js/') {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, must-revalidate');
        readfile(YNJ_DIR . 'assets/js/sw.js');
        exit;
    }
}, 1);

// Also create physical sw.js at web root on admin load
add_action('admin_init', function() {
    $root_sw = ABSPATH . 'sw.js';
    $source_sw = YNJ_DIR . 'assets/js/sw.js';
    if (file_exists($source_sw) && (!file_exists($root_sw) || filemtime($source_sw) > filemtime($root_sw))) {
        @copy($source_sw, $root_sw);
    }
}, 5);

// Serve SW via REST API as ultimate fallback
add_action('rest_api_init', function() {
    register_rest_route('ynj/v1', '/sw', [
        'methods' => 'GET',
        'callback' => function() {
            header('Content-Type: application/javascript');
            header('Service-Worker-Allowed: /');
            header('Cache-Control: no-cache, must-revalidate');
            readfile(YNJ_DIR . 'assets/js/sw.js');
            exit;
        },
        'permission_callback' => '__return_true',
    ]);
});

// Serve /.well-known/assetlinks.json for Android TWA verification
add_action('init', function() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/.well-known/assetlinks.json') !== false) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo '[{"relation":["delegate_permission/common.handle_all_urls"],"target":{"namespace":"android_app","package_name":"com.yourjannah.app","sha256_cert_fingerprints":["5E:26:90:48:E0:77:EB:8F:92:66:6B:98:E6:CD:7A:91:2C:D3:27:95:DA:3E:95:30:35:A4:5A:2D:03:F6:C3:C9"]}}]';
        exit;
    }
}, 1);

// PWA headers
add_action('wp_head', function() {
    echo '<link rel="manifest" href="' . YNJ_URL . 'manifest.json">' . "\n";
    echo '<meta name="theme-color" content="#287e61">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
});

// Register VAPID keys on first run
add_action('init', function() {
    if (!get_option('ynj_vapid_public')) {
        YNJ_Push::generate_vapid_keys();
    }
});

// Prayer reminder cron — auto-schedule if not already running
add_action('ynj_prayer_reminder_cron', ['YNJ_Cron', 'check_prayers']);
register_activation_hook(__FILE__, ['YNJ_Cron', 'schedule']);
register_deactivation_hook(__FILE__, ['YNJ_Cron', 'unschedule']);
add_action('init', function() {
    if ( ! wp_next_scheduled( 'ynj_prayer_reminder_cron' ) ) {
        YNJ_Cron::schedule();
    }
}, 20);

// Admin email notifications
add_action('ynj_new_enquiry', ['YNJ_Notify', 'on_enquiry'], 10, 2);
add_action('ynj_new_booking', ['YNJ_Notify', 'on_booking'], 10, 2);
add_action('ynj_new_sponsor', ['YNJ_Notify', 'on_sponsor'], 10, 2);
add_action('ynj_new_service_listing', ['YNJ_Notify', 'on_service_listing'], 10, 2);
add_action('ynj_payment_received', ['YNJ_Notify', 'on_payment'], 10, 3);
add_action('ynj_new_patron', ['YNJ_Notify', 'on_patron'], 10, 2);
add_action('ynj_booking_status_changed', ['YNJ_Notify', 'on_booking_status_changed'], 10, 2);

// Push notifications to subscribed users on new content
add_action('ynj_new_announcement', function($mosque_id, $data) {
    $subs = YNJ_API_Subscriptions::get_subscribers_for($mosque_id, 'notify_announcements');
    if (empty($subs)) return;
    $payload = wp_json_encode([
        'title' => $data['title'] ?? 'New Announcement',
        'body'  => $data['body'] ?? '',
        'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        'url'   => '/',
    ]);
    foreach ($subs as $u) {
        YNJ_Push::send_push($u->push_endpoint, $u->push_p256dh, $u->push_auth, $payload);
    }
}, 10, 2);

add_action('ynj_new_event', function($mosque_id, $data) {
    $subs = YNJ_API_Subscriptions::get_subscribers_for($mosque_id, 'notify_events');
    if (empty($subs)) return;
    global $wpdb;
    $mosque_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . YNJ_DB::table('mosques') . " WHERE id = %d", $mosque_id)) ?: '';
    $payload = wp_json_encode([
        'title' => 'New Event: ' . ($data['title'] ?? ''),
        'body'  => ($data['event_date'] ?? '') . ' at ' . $mosque_name,
        'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        'url'   => '/',
    ]);
    foreach ($subs as $u) {
        YNJ_Push::send_push($u->push_endpoint, $u->push_p256dh, $u->push_auth, $payload);
    }
}, 10, 2);

add_action('ynj_new_class', function($mosque_id, $data) {
    $subs = YNJ_API_Subscriptions::get_subscribers_for($mosque_id, 'notify_classes');
    if (empty($subs)) return;
    global $wpdb;
    $mosque_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . YNJ_DB::table('mosques') . " WHERE id = %d", $mosque_id)) ?: '';
    $payload = wp_json_encode([
        'title' => 'New Class: ' . ($data['title'] ?? ''),
        'body'  => 'at ' . $mosque_name,
        'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        'url'   => '/',
    ]);
    foreach ($subs as $u) {
        YNJ_Push::send_push($u->push_endpoint, $u->push_p256dh, $u->push_auth, $payload);
    }
}, 10, 2);

// Points: auto-award on booking/donation
add_action('ynj_new_booking', function($mosque_id, $data) {
    if (!empty($data['user_email'])) {
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . YNJ_DB::table('users') . " WHERE email = %s", $data['user_email']));
        if ($user) YNJ_API_Points::award($user->id, $mosque_id, 'event_rsvp', null, $data['notes'] ?? 'Event booking');
    }
}, 10, 2);

add_action('ynj_payment_received', function($mosque_id, $data, $type) {
    if (!empty($data['user_email']) && in_array($type, ['event_donation', 'patron_membership'])) {
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . YNJ_DB::table('users') . " WHERE email = %s", $data['user_email']));
        if ($user) YNJ_API_Points::award($user->id, $mosque_id, 'donation', null, 'Donation/patron payment');
    }
}, 10, 3);

// WP Site Health checks
add_filter('site_status_tests', function($tests) {
    $tests['direct']['ynj_db_version'] = [
        'label' => 'YourJannah Database',
        'test'  => function() {
            $installed = get_option('ynj_db_version', '');
            $expected = YNJ_DB::SCHEMA_VERSION;
            $pass = ($installed === $expected);
            return [
                'label'       => $pass ? 'YourJannah database is up to date' : 'YourJannah database needs updating',
                'status'      => $pass ? 'good' : 'critical',
                'badge'       => ['label' => 'YourJannah', 'color' => $pass ? 'blue' : 'red'],
                'description' => '<p>Database version: ' . esc_html($installed) . ' (expected: ' . esc_html($expected) . ')</p>',
                'test'        => 'ynj_db_version',
            ];
        },
    ];
    $tests['direct']['ynj_stripe'] = [
        'label' => 'YourJannah Stripe',
        'test'  => function() {
            $configured = YNJ_Stripe::is_configured();
            return [
                'label'       => $configured ? 'Stripe is configured' : 'Stripe is not configured',
                'status'      => $configured ? 'good' : 'recommended',
                'badge'       => ['label' => 'YourJannah', 'color' => 'blue'],
                'description' => '<p>Stripe payment processing ' . ($configured ? 'is active.' : 'is not set up. Paid bookings and subscriptions will not work.') . '</p>',
                'test'        => 'ynj_stripe',
            ];
        },
    ];
    $tests['direct']['ynj_vapid'] = [
        'label' => 'YourJannah Push Notifications',
        'test'  => function() {
            $key = get_option('ynj_vapid_public', '');
            return [
                'label'       => $key ? 'VAPID keys configured' : 'VAPID keys missing',
                'status'      => $key ? 'good' : 'recommended',
                'badge'       => ['label' => 'YourJannah', 'color' => 'blue'],
                'description' => '<p>Web Push notification ' . ($key ? 'keys are set up.' : 'keys are missing. Push notifications will not work.') . '</p>',
                'test'        => 'ynj_vapid',
            ];
        },
    ];
    return $tests;
});
// deploy trigger
