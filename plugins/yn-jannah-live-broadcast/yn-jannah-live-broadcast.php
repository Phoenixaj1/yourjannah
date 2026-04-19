<?php
/**
 * Plugin Name: YourJannah — Live Broadcast
 * Description: Masjid live streaming via YourJannah's YouTube channel — broadcaster roles, Go Live, auto-post to feed, FOMO notifications.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_BROADCAST_VERSION', '1.0.0' );
define( 'YNJ_BROADCAST_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_BROADCAST_DIR . 'inc/class-ynj-broadcast.php';

    // REST endpoints
    add_action( 'rest_api_init', function() {
        // Start a broadcast
        register_rest_route( 'ynj/v1', '/broadcast/start', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Broadcast', 'api_start' ],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ] );

        // End a broadcast
        register_rest_route( 'ynj/v1', '/broadcast/end', [
            'methods'  => 'POST',
            'callback' => [ 'YNJ_Broadcast', 'api_end' ],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ] );

        // Get current live stream for a mosque
        register_rest_route( 'ynj/v1', '/broadcast/live/(?P<mosque_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ 'YNJ_Broadcast', 'api_get_live' ],
            'permission_callback' => '__return_true',
        ] );

        // Get broadcast history for a mosque
        register_rest_route( 'ynj/v1', '/broadcast/history/(?P<mosque_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ 'YNJ_Broadcast', 'api_history' ],
            'permission_callback' => '__return_true',
        ] );

        // Manage broadcasters
        register_rest_route( 'ynj/v1', '/broadcast/broadcasters', [
            'methods'  => [ 'GET', 'POST', 'DELETE' ],
            'callback' => [ 'YNJ_Broadcast', 'api_broadcasters' ],
            'permission_callback' => function() {
                return is_user_logged_in() && (
                    current_user_can( 'manage_options' ) ||
                    in_array( 'ynj_mosque_admin', (array) wp_get_current_user()->roles )
                );
            },
        ] );
    } );

    // Dashboard section
    add_filter( 'ynj_dashboard_sections', function( $groups ) {
        if ( isset( $groups['engage'] ) ) {
            $groups['engage']['items'][] = [
                'key'   => 'broadcast',
                'icon'  => '📡',
                'label' => 'Broadcasting',
            ];
        }
        return $groups;
    } );

    // WP Admin
    if ( is_admin() ) {
        require_once YNJ_BROADCAST_DIR . 'inc/class-ynj-broadcast-admin.php';
        YNJ_Broadcast_Admin::init();
    }
}, 10 );
