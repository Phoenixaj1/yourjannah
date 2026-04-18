<?php
/**
 * Plugin Name: YourJannah — Events
 * Description: Announcements, events, bookings, room management, volunteer tracking.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Auth)
 *
 * @package YNJ_Events
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_EVENTS_VERSION', '1.0.0' );
define( 'YNJ_EVENTS_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    // Data layer.
    if ( ! class_exists( 'YNJ_Events' ) ) {
        require_once YNJ_EVENTS_DIR . 'inc/class-ynj-events.php';
    }

    // REST API controllers.
    if ( ! class_exists( 'YNJ_API_Announcements' ) ) {
        require_once YNJ_EVENTS_DIR . 'api/class-ynj-api-announcements.php';
    }
    if ( ! class_exists( 'YNJ_API_Events' ) ) {
        require_once YNJ_EVENTS_DIR . 'api/class-ynj-api-events.php';
    }
    if ( ! class_exists( 'YNJ_API_Bookings' ) ) {
        require_once YNJ_EVENTS_DIR . 'api/class-ynj-api-bookings.php';
    }

    // WP Admin pages.
    if ( is_admin() ) {
        require_once YNJ_EVENTS_DIR . 'inc/class-ynj-events-admin.php';
        YNJ_Events_Admin::init();
    }

}, 10 );
