<?php
/**
 * Plugin Name: YourJannah — Youth
 * Description: Youth activities — sports, talks, classes, trips. Dedicated section for young Muslims.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_YOUTH_VERSION', '1.0.0' );
define( 'YNJ_YOUTH_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'init', function() {
    register_post_type( 'ynj_youth_activity', [
        'labels'   => [ 'name' => 'Youth Activities', 'singular_name' => 'Youth Activity' ],
        'public'   => false,
        'show_ui'  => false,
        'supports' => [ 'title', 'editor' ],
    ] );
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_YOUTH_DIR . 'inc/class-ynj-youth.php';

    if ( is_admin() ) {
        require_once YNJ_YOUTH_DIR . 'inc/class-ynj-youth-admin.php';
        YNJ_Youth_Admin::init();
    }

    // Dashboard section under ENGAGE
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        if ( isset( $groups['engage'] ) ) {
            $groups['engage']['items'][] = [
                'key'   => 'youth',
                'icon'  => '⚽',
                'label' => 'Youth Activities',
            ];
        }
        return $groups;
    } );

}, 10 );
