<?php
/**
 * Plugin Name: YourJannah — Patrons
 * Description: Patron analytics — who's a patron, for which masjid, tier breakdowns, % congregation penetration.
 * Version:     1.0.1
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_PATRONS_VERSION', '1.0.1' );
define( 'YNJ_PATRONS_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_PATRONS_DIR . 'inc/class-ynj-patrons-data.php';

    if ( is_admin() ) {
        require_once YNJ_PATRONS_DIR . 'inc/class-ynj-patrons-admin.php';
    }
}, 10 );
