<?php
/**
 * Plugin Name: YourJannah — Stripe Connect
 * Description: Mosque Stripe Connect onboarding, destination charges, platform fees. Mosques connect their own Stripe; we take a configurable cut.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Stripe)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_SC_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_SC_DIR . 'inc/class-ynj-stripe-connect.php';

    // Add stripe columns to mosques table on first run
    YNJ_Stripe_Connect::maybe_add_columns();

    // REST endpoints for Connect OAuth
    add_action( 'rest_api_init', [ 'YNJ_Stripe_Connect', 'register_routes' ] );

    // Filter: inject Stripe Connect params into payment creation
    add_filter( 'ynj_payment_params', [ 'YNJ_Stripe_Connect', 'inject_connect_params' ], 10, 3 );
}, 15 );
