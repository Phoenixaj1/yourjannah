<?php
/**
 * Plugin Name: YourJannah — Jumuah Times
 * Description: Jumuah prayer times — searchable, multi-masjid view, dashboard section.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_JUMUAH_VERSION', '1.0.0' );
define( 'YNJ_JUMUAH_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;
    require_once YNJ_JUMUAH_DIR . 'inc/class-ynj-jumuah-data.php';
    if ( is_admin() ) {
        require_once YNJ_JUMUAH_DIR . 'inc/class-ynj-jumuah-admin.php';
        YNJ_Jumuah_Admin::init();
    }

    // Register dashboard section for mosque admin
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        // Add Jumuah under MANAGE group
        if ( isset( $groups['manage'] ) ) {
            $groups['manage']['items'][] = [
                'key'      => 'jumuah',
                'icon'     => '🕌',
                'label'    => 'Jumuah Times',
                'template' => YNJ_JUMUAH_DIR . 'templates/dashboard-jumuah.php',
            ];
        }
        return $groups;
    } );

}, 10 );
