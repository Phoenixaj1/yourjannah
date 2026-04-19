<?php
/**
 * Plugin Name: YourJannah — Engagement
 * Description: Dua wall, gratitude posts, content reactions, view tracking, milestones.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 *
 * @package YNJ_Engagement
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_ENGAGEMENT_VERSION', '1.0.0' );
define( 'YNJ_ENGAGEMENT_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    // Data layer
    require_once YNJ_ENGAGEMENT_DIR . 'inc/class-ynj-engagement.php';

    // API endpoints (when ready)
    // require_once YNJ_ENGAGEMENT_DIR . 'api/class-ynj-api-engagement.php';

    // WP Admin pages.
    if ( is_admin() ) {
        require_once YNJ_ENGAGEMENT_DIR . 'inc/class-ynj-engagement-admin.php';
        YNJ_Engagement_Admin::init();
    }

}, 10 );
