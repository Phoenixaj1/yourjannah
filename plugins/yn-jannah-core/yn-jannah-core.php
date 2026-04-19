<?php
/**
 * Plugin Name: YourJannah — Core
 * Description: Foundation: database, auth, roles, Stripe SDK, cache, user/media API, pool ledger, social auth.
 * Version:     1.0.1
 * Author:      YourNiyyah
 *
 * All domain plugins depend on this. Must be activated FIRST.
 *
 * @package YNJ_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_CORE_VERSION', '1.0.1' );
define( 'YNJ_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'YNJ_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'YNJ_CORE_ACTIVE', true );

// NOTE: Do NOT define YNJ_TABLE_PREFIX here — the monolith defines it.
// Both use 'ynj_' as the suffix; YNJ_DB::table() prepends $wpdb->prefix.

// ── Load core classes (priority 5 — before all domain plugins) ──
add_action( 'plugins_loaded', function() {

    // Only load classes that the monolith hasn't already loaded.
    // WordPress loads plugins alphabetically, so yn-jannah-core loads
    // BEFORE yn-jannah. But the monolith registers its SPL autoloader
    // at file load time, so by the time this callback fires, the
    // autoloader is ready. class_exists() will trigger the autoloader
    // for any class not yet loaded.

    // We don't force-load anything — the monolith's autoloader handles
    // class loading on demand. The core plugin's role during coexistence
    // is simply to:
    // 1. Define YNJ_CORE_ACTIVE so domain plugins know core exists
    // 2. Provide helper functions (ynj_get_mosque, etc.)
    // 3. Eventually replace the monolith once all classes are migrated

}, 5 );

// ── Theme helper functions ──
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
        $cached = wp_cache_get( $cache_key, 'ynj' );
        if ( $cached ) return $cached;
        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE slug = %s AND status IN ('active','unclaimed')",
            $slug
        ) );
        if ( $mosque ) wp_cache_set( $cache_key, $mosque, 'ynj', 300 );
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
