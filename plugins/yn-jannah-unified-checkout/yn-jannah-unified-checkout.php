<?php
/**
 * Plugin Name: YourJannah — Unified Checkout
 * Description: Single checkout page for all payment types — donations, patrons, sponsors, tips, room bookings, class fees. Stripe Elements inline.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Stripe)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_UC_VERSION', '1.0.0' );
define( 'YNJ_UC_DIR', plugin_dir_path( __FILE__ ) );
define( 'YNJ_UC_URL', plugin_dir_url( __FILE__ ) );

// ── Rewrite rule: /checkout/ → custom template ──
add_action( 'init', function() {
    add_rewrite_rule( '^checkout/?$', 'index.php?ynj_checkout=1', 'top' );
    add_rewrite_tag( '%ynj_checkout%', '1' );
} );

// Flush rewrites on activation
register_activation_hook( __FILE__, function() {
    add_rewrite_rule( '^checkout/?$', 'index.php?ynj_checkout=1', 'top' );
    flush_rewrite_rules();
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_UC_DIR . 'inc/class-ynj-uc-api.php';

    // REST endpoints
    add_action( 'rest_api_init', function() {
        register_rest_route( 'ynj/v1', '/unified-checkout/create-intent', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_UC_API', 'create_intent' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'ynj/v1', '/unified-checkout/confirm', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_UC_API', 'confirm' ],
            'permission_callback' => '__return_true',
        ] );
    } );

    // WP Admin
    if ( is_admin() ) {
        require_once YNJ_UC_DIR . 'inc/class-ynj-uc-admin.php';
        YNJ_UC_Admin::init();
    }
}, 10 );

// ── Render checkout page ──
add_action( 'template_redirect', function() {
    if ( get_query_var( 'ynj_checkout' ) ) {
        require_once YNJ_UC_DIR . 'inc/class-ynj-uc-page.php';
        YNJ_UC_Page::render();
        exit;
    }
} );
