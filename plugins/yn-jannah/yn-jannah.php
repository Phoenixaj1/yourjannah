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
    ];
    if (isset($map[$class])) {
        require_once YNJ_DIR . $map[$class];
    }
});

// Install DB on activation
register_activation_hook(__FILE__, ['YNJ_DB', 'install']);

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
});

// Admin menu
if (is_admin()) {
    add_action('admin_menu', ['YNJ_Admin', 'register_menu']);
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
// deploy trigger
