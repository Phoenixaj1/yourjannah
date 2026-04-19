<?php
/**
 * Plugin Name: YourJannah — People
 * Description: Community member management — view, search, and manage members across mosques.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_PEOPLE_VERSION', '1.0.0' );
define( 'YNJ_PEOPLE_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_PEOPLE_DIR . 'inc/class-ynj-people.php';

    if ( is_admin() ) {
        require_once YNJ_PEOPLE_DIR . 'inc/class-ynj-people-admin.php';
        YNJ_People_Admin::init();
    }
}, 10 );
