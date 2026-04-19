<?php
/**
 * Plugin Name: YourJannah — Masjid Store
 * Description: Digital community shout-outs — Jumuah Mubarak, Eid Mubarak, Khatam announcements. 95% goes to masjid.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_STORE_VERSION', '1.0.0' );
define( 'YNJ_STORE_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_STORE_DIR . 'inc/class-ynj-store.php';

    // When a unified checkout payment succeeds for a store item, auto-post the announcement
    add_action( 'ynj_unified_payment_succeeded', [ 'YNJ_Store', 'on_payment_succeeded' ], 10, 2 );

    // WP Admin
    if ( is_admin() ) {
        require_once YNJ_STORE_DIR . 'inc/class-ynj-store-admin.php';
        YNJ_Store_Admin::init();
    }
}, 10 );
