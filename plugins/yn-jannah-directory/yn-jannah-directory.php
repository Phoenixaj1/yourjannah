<?php
/**
 * Plugin Name: YourJannah — Directory
 * Description: Muslim business directory, service provider listings, enquiry management, cross-mosque search.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 *
 * @package YNJ_Directory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_DIRECTORY_VERSION', '1.0.0' );
define( 'YNJ_DIRECTORY_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>YourJannah Directory</strong> requires the <strong>YourJannah</strong> core plugin.</p></div>';
        } );
        return;
    }

    // PHP data layer (direct DB — used by templates)
    require_once YNJ_DIRECTORY_DIR . 'inc/class-ynj-directory.php';

    // REST API (only for async operations — submissions, admin CRUD)
    if ( ! class_exists( 'YNJ_API_Directory' ) ) {
        require_once YNJ_DIRECTORY_DIR . 'api/class-ynj-api-directory.php';
    }
    if ( ! class_exists( 'YNJ_API_Search' ) ) {
        require_once YNJ_DIRECTORY_DIR . 'api/class-ynj-api-search.php';
    }

    // WP Admin pages (businesses, services, enquiries)
    if ( is_admin() ) {
        require_once YNJ_DIRECTORY_DIR . 'inc/class-ynj-directory-admin.php';
        YNJ_Directory_Admin::init();
    }

}, 10 );
