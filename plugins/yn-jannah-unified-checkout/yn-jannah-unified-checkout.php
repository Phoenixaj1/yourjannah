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
        register_rest_route( 'ynj/v1', '/unified-checkout/mosque-transactions', [
            'methods'  => 'GET',
            'callback' => function( $request ) {
                $mosque_id = absint( $request->get_param( 'mosque_id' ) );
                if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false ], 400 );
                global $wpdb;
                $t = YNJ_DB::table( 'transactions' );
                $txns = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM $t WHERE mosque_id = %d AND status = 'succeeded' ORDER BY completed_at DESC LIMIT 100", $mosque_id
                ) ) ?: [];
                $total = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(total_pence),0) FROM $t WHERE mosque_id = %d AND status = 'succeeded'", $mosque_id
                ) );
                $count = count( $txns );
                return new \WP_REST_Response( [ 'ok' => true, 'transactions' => $txns, 'total_pence' => $total, 'count' => $count ] );
            },
            'permission_callback' => function() { return is_user_logged_in(); },
        ] );
    } );

    // WP Admin moved to yn-jannah-transactions plugin
}, 10 );

// ── Cash Payment Mode (admin-controlled, site-wide) ──
// Toggle from WP Admin: Settings > General > YourJannah Cash Mode
// Or via URL: ?ynj_cash_mode=on / off (admin only)
add_action( 'init', function() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) return;
    $mode = sanitize_text_field( $_GET['ynj_cash_mode'] ?? '' );
    if ( $mode === 'on' ) {
        update_option( 'ynj_cash_payment_mode', 1 );
        wp_safe_redirect( remove_query_arg( 'ynj_cash_mode' ) );
        exit;
    } elseif ( $mode === 'off' ) {
        delete_option( 'ynj_cash_payment_mode' );
        wp_safe_redirect( remove_query_arg( 'ynj_cash_mode' ) );
        exit;
    }
    // Also support old test mode URL for backwards compat
    $test = sanitize_text_field( $_GET['ynj_test_mode'] ?? '' );
    if ( $test === 'on' ) { update_option( 'ynj_cash_payment_mode', 1 ); wp_safe_redirect( remove_query_arg( 'ynj_test_mode' ) ); exit; }
    if ( $test === 'off' ) { delete_option( 'ynj_cash_payment_mode' ); wp_safe_redirect( remove_query_arg( 'ynj_test_mode' ) ); exit; }
} );

// ── Show cash mode banner ──
add_action( 'wp_footer', function() {
    if ( ! get_option( 'ynj_cash_payment_mode' ) ) return;
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) return;
    echo '<div style="position:fixed;top:40px;left:50%;transform:translateX(-50%);z-index:99999;background:#f59e0b;color:#1a1a2e;padding:8px 24px;border-radius:20px;font-size:12px;font-weight:700;box-shadow:0 4px 12px rgba(245,158,11,.3);">💵 CASH MODE ON — all payments bypass Stripe — <a href="?ynj_cash_mode=off" style="color:#92400e;text-decoration:underline;font-weight:800;">Turn Off</a></div>';
} );

// ── One-time: create masjid admin test user ──
add_action( 'init', function() {
    if ( get_option( 'ynj_test_admin_created' ) ) return;
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    $email = 'masjidadmin@yourjannah.com';
    if ( email_exists( $email ) ) {
        update_option( 'ynj_test_admin_created', 1 );
        return;
    }

    $wp_user_id = wp_create_user( 'masjidadmin', 'MasjidAdmin2026!', $email );
    if ( is_wp_error( $wp_user_id ) ) return;

    $user = new \WP_User( $wp_user_id );
    $user->set_role( 'ynj_mosque_admin' );
    wp_update_user( [ 'ID' => $wp_user_id, 'display_name' => 'Masjid Admin (Test)', 'first_name' => 'Masjid', 'last_name' => 'Admin' ] );

    // Create YNJ user record
    global $wpdb;
    $wpdb->insert( YNJ_DB::table( 'users' ), [
        'name'  => 'Masjid Admin (Test)',
        'email' => $email,
        'pin'   => wp_hash_password( '1234' ),
    ] );
    $ynj_uid = (int) $wpdb->insert_id;
    if ( $ynj_uid ) {
        update_user_meta( $wp_user_id, 'ynj_user_id', $ynj_uid );
        // Link to all mosques as admin
        $mosques = $wpdb->get_results( "SELECT id FROM " . YNJ_DB::table( 'mosques' ) . " LIMIT 50" );
        $admin_table = YNJ_DB::table( 'mosque_admins' );
        foreach ( $mosques as $m ) {
            $wpdb->replace( $admin_table, [
                'mosque_id' => (int) $m->id,
                'user_id'   => $ynj_uid,
                'role'      => 'admin',
            ] );
        }
        // Set first mosque as favourite
        if ( ! empty( $mosques ) ) {
            $wpdb->update( YNJ_DB::table( 'users' ), [ 'favourite_mosque_id' => (int) $mosques[0]->id ], [ 'id' => $ynj_uid ] );
        }
    }

    update_option( 'ynj_test_admin_created', 1 );
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
