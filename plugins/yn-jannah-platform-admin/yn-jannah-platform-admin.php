<?php
/**
 * Plugin Name: YourJannah — Platform Admin
 * Description: Super-admin WP dashboard, support tickets, CSV imports, seed tools.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_PLATFORM_ADMIN_VERSION', '1.0.0' );
define( 'YNJ_PLATFORM_ADMIN_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    if ( ! class_exists( 'YNJ_Platform_Admin' ) ) {
        require_once YNJ_PLATFORM_ADMIN_DIR . 'inc/class-ynj-platform-admin.php';
    }
}, 10 );
