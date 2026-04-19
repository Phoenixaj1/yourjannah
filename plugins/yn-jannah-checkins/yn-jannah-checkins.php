<?php
/**
 * Plugin Name: YourJannah — Check-ins
 * Description: Mosque check-in system — GPS verified, gamification points, most active members.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_CHECKINS_VERSION', '1.0.0' );
define( 'YNJ_CHECKINS_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;
    require_once YNJ_CHECKINS_DIR . 'inc/class-ynj-checkins-data.php';
    if ( is_admin() ) {
        require_once YNJ_CHECKINS_DIR . 'inc/class-ynj-checkins-admin.php';
        YNJ_Checkins_Admin::init();
    }
}, 10 );
