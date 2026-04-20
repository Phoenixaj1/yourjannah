<?php
/**
 * Plugin Name: YourJannah — Prayer Times Scraper
 * Description: Scrapes mosque websites for timetable PDFs, uses Claude AI to extract prayer times, auto-saves to DB.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_SCRAPER_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_SCRAPER_DIR . 'inc/class-ynj-prayer-scraper.php';
    require_once YNJ_SCRAPER_DIR . 'inc/class-ynj-mosque-enricher.php';

    // WP Admin page
    if ( is_admin() ) {
        add_action( 'admin_menu', function() {
            add_submenu_page( 'yn-jannah', 'Prayer Scraper', 'Prayer Scraper', 'manage_options', 'ynj-prayer-scraper', [ 'YNJ_Prayer_Scraper', 'render_admin_page' ] );
        } );
    }

    // REST endpoints
    add_action( 'rest_api_init', function() {
        register_rest_route( 'ynj/v1', '/scraper/process-mosque', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Prayer_Scraper', 'api_process_mosque' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );
        register_rest_route( 'ynj/v1', '/scraper/batch-start', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Prayer_Scraper', 'api_batch_start' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );
        // Mosque enricher: OpenStreetMap import
        register_rest_route( 'ynj/v1', '/scraper/import-uk-mosques', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Mosque_Enricher', 'api_import_all_uk' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );
        register_rest_route( 'ynj/v1', '/scraper/search-location', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Mosque_Enricher', 'api_search_location' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );
    } );
}, 10 );
