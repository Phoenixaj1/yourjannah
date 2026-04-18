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

    // Engagement endpoints will be extracted from api-mosques.php
    // For now, the monolith still handles dua/gratitude/reaction endpoints

}, 10 );
