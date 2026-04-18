<?php
/**
 * Plugin Name: YourJannah — Services
 * Description: Bookable mosque services (nikkah, funeral, etc.), service enquiries.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Auth)
 *
 * @package YNJ_Services
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_SERVICES_VERSION', '1.0.0' );
define( 'YNJ_SERVICES_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>YourJannah Services</strong> requires the <strong>YourJannah</strong> core plugin.</p></div>';
        } );
        return;
    }

    if ( ! class_exists( 'YNJ_API_Masjid_Services' ) ) {
        require_once YNJ_SERVICES_DIR . 'api/class-ynj-api-masjid-services.php';
    }

}, 10 );
