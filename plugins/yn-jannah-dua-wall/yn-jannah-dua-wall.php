<?php
/**
 * Plugin Name: YourJannah — Dua Wall
 * Description: Community dua wall — share prayers, make dua for others, strengthen your masjid community through collective supplication.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_DUA_WALL_VERSION', '1.0.0' );
define( 'YNJ_DUA_WALL_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_DUA_WALL_DIR . 'inc/class-ynj-dua-wall.php';

    if ( is_admin() ) {
        require_once YNJ_DUA_WALL_DIR . 'inc/class-ynj-dua-wall-admin.php';
        YNJ_Dua_Wall_Admin::init();
    }

    // Register dashboard section
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        if ( isset( $groups['engage'] ) ) {
            $groups['engage']['items'][] = [
                'key'      => 'dua-wall',
                'icon'     => '🤲',
                'label'    => 'Dua Wall',
                'template' => YNJ_DUA_WALL_DIR . 'templates/dashboard-dua-wall.php',
            ];
        }
        return $groups;
    } );

    // Seed the first dua on activation
    register_activation_hook( __FILE__, [ 'YNJ_Dua_Wall', 'seed' ] );

}, 10 );
