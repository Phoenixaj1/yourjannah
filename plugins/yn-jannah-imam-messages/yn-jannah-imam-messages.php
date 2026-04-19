<?php
/**
 * Plugin Name: YourJannah — Imam Messages
 * Description: Daily messages from the Imam — high visibility, front-end posting for authorised roles.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_IMAM_MESSAGES_VERSION', '1.0.0' );
define( 'YNJ_IMAM_MESSAGES_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'init', function() {
    register_post_type( 'ynj_imam_message', [
        'labels'   => [ 'name' => 'Imam Messages', 'singular_name' => 'Imam Message' ],
        'public'   => false,
        'show_ui'  => false,
        'supports' => [ 'title', 'editor' ],
    ] );
} );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_IMAM_MESSAGES_DIR . 'inc/class-ynj-imam-messages.php';

    if ( is_admin() ) {
        require_once YNJ_IMAM_MESSAGES_DIR . 'inc/class-ynj-imam-messages-admin.php';
        YNJ_Imam_Messages_Admin::init();
    }

    // Dashboard section
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        if ( isset( $groups['engage'] ) ) {
            // Add at the TOP of engage (most important)
            array_unshift( $groups['engage']['items'], [
                'key'   => 'imam-messages',
                'icon'  => '🕌',
                'label' => 'Imam Messages',
            ] );
        }
        return $groups;
    } );

    // REST endpoint for front-end posting
    add_action( 'rest_api_init', function() {
        register_rest_route( 'ynj/v1', '/imam-messages', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Imam_Messages', 'api_create' ],
            'permission_callback' => function() {
                return is_user_logged_in() && (
                    current_user_can( 'manage_options' ) ||
                    in_array( 'ynj_mosque_admin', (array) wp_get_current_user()->roles ) ||
                    in_array( 'ynj_imam', (array) wp_get_current_user()->roles )
                );
            },
        ] );
        register_rest_route( 'ynj/v1', '/imam-messages', [
            'methods'  => 'GET',
            'callback' => [ 'YNJ_Imam_Messages', 'api_list' ],
            'permission_callback' => '__return_true',
        ] );
    } );

}, 10 );
