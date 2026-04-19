<?php
/**
 * Plugin Name: YourJannah — Mosques
 * Description: Mosque profiles, prayer times, jumuah/eid, sitemap, geolocation search, view tracking.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 *
 * @package YNJ_Mosques
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_MOSQUES_VERSION', '1.0.0' );
define( 'YNJ_MOSQUES_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    // PHP data layer
    require_once YNJ_MOSQUES_DIR . 'inc/class-ynj-mosques.php';

    // Prayer calculation
    if ( ! class_exists( 'YNJ_Prayer' ) ) {
        require_once YNJ_MOSQUES_DIR . 'inc/class-ynj-prayer.php';
    }

    // REST API
    if ( ! class_exists( 'YNJ_API_Mosques' ) ) {
        require_once YNJ_MOSQUES_DIR . 'api/class-ynj-api-mosques.php';
    }
    if ( ! class_exists( 'YNJ_API_Prayer' ) ) {
        require_once YNJ_MOSQUES_DIR . 'api/class-ynj-api-prayer.php';
    }

    // WP Admin pages.
    if ( is_admin() ) {
        require_once YNJ_MOSQUES_DIR . 'inc/class-ynj-mosques-admin.php';
        YNJ_Mosques_Admin::init();
    }

}, 10 );
