<?php
/**
 * Plugin Name: YourJannah — HUD
 * Description: The sticky header bar (guest + member), mosque selector modal, dhikr/league/info popups.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core plugin for YNJ_DB)
 *
 * @package YNJ_HUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_HUD_VERSION', '1.0.0' );
define( 'YNJ_HUD_DIR', plugin_dir_path( __FILE__ ) );
define( 'YNJ_HUD_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>YourJannah HUD</strong> requires the <strong>YourJannah</strong> core plugin.</p></div>';
        } );
        return;
    }

    require_once YNJ_HUD_DIR . 'inc/class-ynj-hud.php';

    // ── Render HUD immediately after <body> opens ──
    add_action( 'wp_body_open', [ 'YNJ_HUD', 'render' ], 5 );

    // ── Enqueue assets ──
    add_action( 'wp_enqueue_scripts', function() {
        wp_enqueue_style(
            'ynj-hud',
            YNJ_HUD_URL . 'assets/css/hud.css',
            [],
            YNJ_HUD_VERSION
        );

        wp_enqueue_script(
            'ynj-hud',
            YNJ_HUD_URL . 'assets/js/hud.js',
            [],
            YNJ_HUD_VERSION,
            true
        );

        wp_enqueue_script(
            'ynj-mosque-modal',
            YNJ_HUD_URL . 'assets/js/mosque-modal.js',
            [],
            YNJ_HUD_VERSION,
            true
        );

        // Pass HUD data to JS
        $hud_data = YNJ_HUD::get_js_data();
        wp_localize_script( 'ynj-hud', 'ynjHudData', $hud_data );
    } );

}, 20 ); // After core (10) and gamification (15)
