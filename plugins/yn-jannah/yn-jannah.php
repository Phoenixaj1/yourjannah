<?php
/**
 * Plugin Name: YourJannah
 * Description: Mosque community platform — prayer times, announcements, events, bookings, business directory, donations.
 * Version: 1.0.0
 * Author: YourNiyyah
 */
if (!defined('ABSPATH')) exit;

define('YNJ_VERSION', '1.0.0');
define('YNJ_DIR', plugin_dir_path(__FILE__));
define('YNJ_URL', plugin_dir_url(__FILE__));
define('YNJ_TABLE_PREFIX', 'ynj_');

// Autoloader
spl_autoload_register(function($class) {
    $map = [
        'YNJ_DB'              => 'inc/class-ynj-db.php',
        'YNJ_Auth'            => 'inc/class-ynj-auth.php',
        'YNJ_Prayer'          => 'inc/class-ynj-prayer.php',
        'YNJ_Push'            => 'inc/class-ynj-push.php',
        'YNJ_Router'          => 'inc/class-ynj-router.php',
        'YNJ_Renderer'        => 'inc/class-ynj-renderer.php',
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
}, 5);

// Auto-upgrade DB on version change
add_action('admin_init', function() {
    $installed = get_option('ynj_db_version', '');
    if ($installed !== YNJ_VERSION) {
        YNJ_DB::install();
        update_option('ynj_db_version', YNJ_VERSION);
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
});

// Admin menus
if (is_admin()) {
    add_action('admin_menu', ['YNJ_Admin', 'register_menu']);
    YNJ_Platform_Admin::register();
}

// Frontend routing (handles yourjannah.com domain)
add_action('template_redirect', ['YNJ_Router', 'handle'], 1);

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

// Prayer reminder cron
add_action('ynj_prayer_reminder_cron', ['YNJ_Cron', 'check_prayers']);
register_activation_hook(__FILE__, ['YNJ_Cron', 'schedule']);
register_deactivation_hook(__FILE__, ['YNJ_Cron', 'unschedule']);

// Admin email notifications
add_action('ynj_new_enquiry', ['YNJ_Notify', 'on_enquiry'], 10, 2);
add_action('ynj_new_booking', ['YNJ_Notify', 'on_booking'], 10, 2);
add_action('ynj_new_sponsor', ['YNJ_Notify', 'on_sponsor'], 10, 2);
add_action('ynj_new_service_listing', ['YNJ_Notify', 'on_service_listing'], 10, 2);
add_action('ynj_payment_received', ['YNJ_Notify', 'on_payment'], 10, 3);
add_action('ynj_new_patron', ['YNJ_Notify', 'on_patron'], 10, 2);

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
