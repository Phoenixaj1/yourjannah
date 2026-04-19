<?php
/**
 * Plugin Name: YourJannah — Checkout
 * Description: Donation checkout — daily/weekly/monthly giving, charitable causes, platform tips, analytics.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Stripe)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_CHECKOUT_VERSION', '1.0.0' );
define( 'YNJ_CHECKOUT_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_CHECKOUT_DIR . 'inc/class-ynj-checkout.php';

    if ( is_admin() ) {
        require_once YNJ_CHECKOUT_DIR . 'inc/class-ynj-checkout-admin.php';
        YNJ_Checkout_Admin::init();
    }

    // REST endpoints for checkout flows
    add_action( 'rest_api_init', function() {
        // Create a donation checkout session
        register_rest_route( 'ynj/v1', '/checkout/donate', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Checkout', 'api_donate' ],
            'permission_callback' => '__return_true',
        ] );

        // Create a recurring donation (daily/weekly/monthly)
        register_rest_route( 'ynj/v1', '/checkout/recurring', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Checkout', 'api_recurring' ],
            'permission_callback' => '__return_true',
        ] );

        // Platform tip
        register_rest_route( 'ynj/v1', '/checkout/tip', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Checkout', 'api_tip' ],
            'permission_callback' => '__return_true',
        ] );

        // Get checkout analytics
        register_rest_route( 'ynj/v1', '/checkout/analytics', [
            'methods'  => 'GET',
            'callback' => [ 'YNJ_Checkout', 'api_analytics' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );
    } );
}, 10 );
