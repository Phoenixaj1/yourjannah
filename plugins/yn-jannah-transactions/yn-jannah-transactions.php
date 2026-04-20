<?php
/**
 * Plugin Name: YourJannah — Transactions
 * Description: Transaction dashboard — view, filter, and manage all payments across the platform.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_TXN_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    if ( is_admin() ) {
        require_once YNJ_TXN_DIR . 'inc/class-ynj-txn-admin.php';
        YNJ_Txn_Admin::init();
    }
}, 10 );
