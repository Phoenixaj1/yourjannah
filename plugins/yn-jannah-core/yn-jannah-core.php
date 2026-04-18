<?php
/**
 * Plugin Name: YourJannah — Core
 * Description: Foundation: database, auth, roles, Stripe SDK, cache, user/media API, pool ledger, social auth.
 * Version:     1.0.0
 * Author:      YourNiyyah
 *
 * All domain plugins depend on this. Must be activated FIRST.
 * Defines YNJ_CORE_ACTIVE so domain plugins can check for it.
 *
 * @package YNJ_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_CORE_VERSION', '1.0.0' );
define( 'YNJ_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'YNJ_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'YNJ_CORE_ACTIVE', true );

// Table prefix — shared across all plugins
if ( ! defined( 'YNJ_TABLE_PREFIX' ) ) {
    global $wpdb;
    define( 'YNJ_TABLE_PREFIX', $wpdb->prefix . 'ynj_' );
}

// ── Load core classes (priority 5 — before all domain plugins) ──
add_action( 'plugins_loaded', function() {

    // Infrastructure (no dependencies)
    if ( ! class_exists( 'YNJ_DB' ) ) {
        require_once YNJ_CORE_DIR . 'inc/class-ynj-db.php';
    }
    if ( ! class_exists( 'YNJ_Cache' ) ) {
        require_once YNJ_CORE_DIR . 'inc/class-ynj-cache.php';
    }

    // Stripe SDK
    if ( ! class_exists( 'YNJ_Stripe' ) ) {
        require_once YNJ_CORE_DIR . 'inc/class-ynj-stripe.php';
    }

    // Auth
    if ( ! class_exists( 'YNJ_Auth' ) ) {
        require_once YNJ_CORE_DIR . 'inc/class-ynj-auth.php';
    }
    if ( ! class_exists( 'YNJ_WP_Auth' ) ) {
        require_once YNJ_CORE_DIR . 'inc/class-ynj-wp-auth.php';
    }
    if ( ! class_exists( 'YNJ_Social_Auth' ) ) {
        require_once YNJ_CORE_DIR . 'inc/class-ynj-social-auth.php';
    }

    // Financial
    if ( ! class_exists( 'YNJ_Pool_Ledger' ) ) {
        require_once YNJ_CORE_DIR . 'inc/class-ynj-pool-ledger.php';
    }

    // REST API endpoints
    if ( ! class_exists( 'YNJ_API_User' ) ) {
        require_once YNJ_CORE_DIR . 'api/class-ynj-api-user.php';
    }
    if ( ! class_exists( 'YNJ_API_Media' ) ) {
        require_once YNJ_CORE_DIR . 'api/class-ynj-api-media.php';
    }
    if ( ! class_exists( 'YNJ_API_Subscriptions' ) ) {
        require_once YNJ_CORE_DIR . 'api/class-ynj-api-subscriptions.php';
    }

    // Init social auth rewrite rules
    if ( method_exists( 'YNJ_Social_Auth', 'init' ) ) {
        YNJ_Social_Auth::init();
    }

}, 5 ); // Priority 5 — loads BEFORE monolith (10) and all domain plugins

// ── Theme helper functions (moved from theme functions.php) ──
if ( ! function_exists( 'ynj_mosque_slug' ) ) {
    function ynj_mosque_slug() {
        $slug = get_query_var( 'ynj_mosque_slug', '' );
        if ( ! $slug && isset( $_COOKIE['ynj_mosque_slug'] ) ) {
            $slug = sanitize_title( $_COOKIE['ynj_mosque_slug'] );
        }
        return $slug ?: 'yourniyyah-masjid';
    }
}

if ( ! function_exists( 'ynj_get_mosque' ) ) {
    function ynj_get_mosque( $slug ) {
        if ( ! $slug || ! class_exists( 'YNJ_DB' ) ) return null;
        $cache_key = 'mosque_' . sanitize_key( $slug );
        $cached = YNJ_Cache::get( $cache_key );
        if ( $cached ) return $cached;
        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE slug = %s AND status IN ('active','unclaimed')",
            $slug
        ) );
        if ( $mosque ) YNJ_Cache::set( $cache_key, $mosque, 300 );
        return $mosque;
    }
}

if ( ! function_exists( 'ynj_get_mosque_by_id' ) ) {
    function ynj_get_mosque_by_id( $id ) {
        if ( ! $id || ! class_exists( 'YNJ_DB' ) ) return null;
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d",
            (int) $id
        ) );
    }
}

// ── Activation hook: install roles ──
register_activation_hook( __FILE__, function() {
    if ( class_exists( 'YNJ_WP_Auth' ) && method_exists( 'YNJ_WP_Auth', 'install_roles' ) ) {
        YNJ_WP_Auth::install_roles();
    }
} );
