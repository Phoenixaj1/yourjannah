<?php
/**
 * Plugin Name: YourJannah — Directory
 * Description: Muslim business directory, service provider listings, enquiry management, cross-mosque search.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Auth)
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

    // Load API classes only if monolith hasn't loaded them
    if ( ! class_exists( 'YNJ_API_Directory' ) ) {
        require_once YNJ_DIRECTORY_DIR . 'api/class-ynj-api-directory.php';
    }
    if ( ! class_exists( 'YNJ_API_Search' ) ) {
        require_once YNJ_DIRECTORY_DIR . 'api/class-ynj-api-search.php';
    }

}, 10 );
