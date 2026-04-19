<?php
/**
 * Plugin Name: YourJannah — Community Marketplace
 * Description: Gumtree-style community listings — questions, items for sale, services, help requests. Masjid-approved, cross-community searchable.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_MARKETPLACE_VERSION', '1.0.0' );
define( 'YNJ_MARKETPLACE_DIR', plugin_dir_path( __FILE__ ) );

// Register custom post type
add_action( 'init', function() {
    register_post_type( 'ynj_listing', [
        'labels' => [
            'name' => 'Community Listings',
            'singular_name' => 'Listing',
        ],
        'public'       => false,
        'show_ui'      => false,
        'supports'     => [ 'title', 'editor' ],
        'has_archive'  => false,
    ] );
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_MARKETPLACE_DIR . 'inc/class-ynj-marketplace.php';

    if ( is_admin() ) {
        require_once YNJ_MARKETPLACE_DIR . 'inc/class-ynj-marketplace-admin.php';
        YNJ_Marketplace_Admin::init();
    }

    // Dashboard section
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        if ( isset( $groups['engage'] ) ) {
            $groups['engage']['items'][] = [
                'key'   => 'marketplace',
                'icon'  => '🏪',
                'label' => 'Community Board',
            ];
        }
        return $groups;
    } );
}, 10 );
