<?php
/**
 * Plugin Name: YourJannah — Notifications
 * Description: Push notifications (VAPID), in-app notifications, email notifications, prayer reminders, broadcasts.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 *
 * @package YNJ_Notifications
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_NOTIFICATIONS_VERSION', '1.0.0' );
define( 'YNJ_NOTIFICATIONS_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    // PHP data layer
    require_once YNJ_NOTIFICATIONS_DIR . 'inc/class-ynj-notifications.php';

    // Push + email sending
    if ( ! class_exists( 'YNJ_Push' ) ) {
        require_once YNJ_NOTIFICATIONS_DIR . 'inc/class-ynj-push.php';
    }
    if ( ! class_exists( 'YNJ_Notify' ) ) {
        require_once YNJ_NOTIFICATIONS_DIR . 'inc/class-ynj-notify.php';
    }
    if ( ! class_exists( 'YNJ_Interest_Notify' ) && file_exists( YNJ_NOTIFICATIONS_DIR . 'inc/class-ynj-interest-notify.php' ) ) {
        require_once YNJ_NOTIFICATIONS_DIR . 'inc/class-ynj-interest-notify.php';
    }

    // WP Admin pages.
    if ( is_admin() ) {
        require_once YNJ_NOTIFICATIONS_DIR . 'inc/class-ynj-notifications-admin.php';
        YNJ_Notifications_Admin::init();
    }

}, 10 );
