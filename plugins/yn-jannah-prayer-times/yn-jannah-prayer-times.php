<?php
/**
 * Plugin Name: YourJannah — Prayer Times
 * Description: Prayer times management — multi-masjid comparison, pattern analysis, admin editing.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_PRAYER_TIMES_VERSION', '1.0.0' );
define( 'YNJ_PRAYER_TIMES_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;
    require_once YNJ_PRAYER_TIMES_DIR . 'inc/class-ynj-prayer-times-data.php';
    if ( is_admin() ) {
        require_once YNJ_PRAYER_TIMES_DIR . 'inc/class-ynj-prayer-times-admin.php';
        YNJ_Prayer_Times_Admin::init();
    }
}, 10 );
