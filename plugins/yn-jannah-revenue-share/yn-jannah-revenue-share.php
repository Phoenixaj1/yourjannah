<?php
/**
 * Plugin Name: YourJannah — Revenue Share
 * Description: 5% of charitable donations credited back to the donor's masjid as passive revenue.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_REVENUE_SHARE_VERSION', '1.0.0' );
define( 'YNJ_REVENUE_SHARE_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_REVENUE_SHARE_DIR . 'inc/class-ynj-revenue-share.php';

    // Listen for successful donations → record 5% share
    add_action( 'ynj_donation_succeeded', [ 'YNJ_Revenue_Share', 'on_donation_succeeded' ], 10, 2 );

    // Register mosque dashboard section
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        if ( isset( $groups['finance'] ) ) {
            $groups['finance']['items'][] = [
                'key'   => 'revenue-share',
                'icon'  => '💰',
                'label' => 'Revenue Share',
            ];
        } else {
            $groups['finance'] = [
                'label' => 'Finance',
                'items' => [ [
                    'key'   => 'revenue-share',
                    'icon'  => '💰',
                    'label' => 'Revenue Share',
                ] ],
            ];
        }
        return $groups;
    } );

    // WP Admin
    if ( is_admin() ) {
        require_once YNJ_REVENUE_SHARE_DIR . 'inc/class-ynj-revenue-share-admin.php';
        YNJ_Revenue_Share_Admin::init();
    }
}, 10 );
