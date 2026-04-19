<?php
/**
 * Plugin Name: YourJannah — Madrassah
 * Description: Islamic school management — classes, enrollment, students, attendance, fees, reports.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Auth)
 *
 * @package YNJ_Madrassah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_MADRASSAH_VERSION', '1.0.0' );
define( 'YNJ_MADRASSAH_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    // PHP data layer
    require_once YNJ_MADRASSAH_DIR . 'inc/class-ynj-madrassah.php';

    // REST API
    if ( ! class_exists( 'YNJ_API_Madrassah' ) ) {
        require_once YNJ_MADRASSAH_DIR . 'api/class-ynj-api-madrassah.php';
    }
    if ( ! class_exists( 'YNJ_API_Classes' ) ) {
        require_once YNJ_MADRASSAH_DIR . 'api/class-ynj-api-classes.php';
    }

    // WP Admin pages.
    if ( is_admin() ) {
        require_once YNJ_MADRASSAH_DIR . 'inc/class-ynj-madrassah-admin.php';
        YNJ_Madrassah_Admin::init();
    }

}, 10 );
