<?php
/**
 * Plugin Name: YourJannah — Celebrations
 * Description: Share community celebrations — Quran memorisation, Hajj, marriage, new baby, revert stories, achievements.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_CELEBRATIONS_VERSION', '1.0.0' );
define( 'YNJ_CELEBRATIONS_DIR', plugin_dir_path( __FILE__ ) );

// Register custom post type
add_action( 'init', function() {
    register_post_type( 'ynj_celebration', [
        'labels' => [ 'name' => 'Celebrations', 'singular_name' => 'Celebration' ],
        'public'      => false,
        'show_ui'     => false,
        'supports'    => [ 'title', 'editor' ],
        'has_archive' => false,
    ] );
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_CELEBRATIONS_DIR . 'inc/class-ynj-celebrations.php';

    if ( is_admin() ) {
        require_once YNJ_CELEBRATIONS_DIR . 'inc/class-ynj-celebrations-admin.php';
        YNJ_Celebrations_Admin::init();
    }

    // Dashboard section
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        if ( isset( $groups['engage'] ) ) {
            $groups['engage']['items'][] = [
                'key'   => 'celebrations',
                'icon'  => '🎉',
                'label' => 'Celebrations',
            ];
        }
        return $groups;
    } );
}, 10 );
