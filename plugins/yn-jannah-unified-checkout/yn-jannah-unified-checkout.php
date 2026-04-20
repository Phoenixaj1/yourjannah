<?php
/**
 * Plugin Name: YourJannah — Unified Checkout
 * Description: Single checkout page for all payment types — donations, patrons, sponsors, tips, room bookings, class fees. Stripe Elements inline.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Stripe)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_UC_VERSION', '3.0.0' );
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

// ── Test mode toggle: ?ynj_test_mode=on / off (admin only) ──
add_action( 'init', function() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) return;
    $mode = sanitize_text_field( $_GET['ynj_test_mode'] ?? '' );
    if ( $mode === 'on' ) {
        update_user_meta( get_current_user_id(), 'ynj_payment_test_mode', 1 );
        wp_safe_redirect( remove_query_arg( 'ynj_test_mode' ) );
        exit;
    } elseif ( $mode === 'off' ) {
        delete_user_meta( get_current_user_id(), 'ynj_payment_test_mode' );
        wp_safe_redirect( remove_query_arg( 'ynj_test_mode' ) );
        exit;
    }
} );

// ── Show test mode banner ──
add_action( 'wp_footer', function() {
    if ( ! is_user_logged_in() ) return;
    if ( ! get_user_meta( get_current_user_id(), 'ynj_payment_test_mode', true ) ) return;
    echo '<div style="position:fixed;top:40px;left:50%;transform:translateX(-50%);z-index:99999;background:#dc2626;color:#fff;padding:6px 20px;border-radius:20px;font-size:12px;font-weight:700;box-shadow:0 4px 12px rgba(220,38,38,.3);">⚠️ PAYMENT TEST MODE — <a href="?ynj_test_mode=off" style="color:#fca5a5;text-decoration:underline;">Disable</a></div>';
} );

// ── Enqueue basket script globally (needed on every page for HUD badge) ──
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'ynj-basket',
        YNJ_UC_URL . 'assets/js/ynj-basket.js',
        [],
        YNJ_UC_VERSION,
        true
    );
} );

// ── Render checkout page ──
add_action( 'template_redirect', function() {
    if ( get_query_var( 'ynj_checkout' ) ) {
        require_once YNJ_UC_DIR . 'inc/class-ynj-uc-page.php';
        YNJ_UC_Page::render();
        exit;
    }
} );
