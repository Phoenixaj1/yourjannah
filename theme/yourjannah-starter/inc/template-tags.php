<?php
/**
 * Template Tags — Reusable helper functions for YourJannah templates.
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get the current mosque slug from the URL.
 * Defined in functions.php but included here for reference.
 */
if ( ! function_exists( 'ynj_mosque_slug' ) ) {
    function ynj_mosque_slug() {
        return sanitize_title( get_query_var( 'ynj_mosque_slug', '' ) );
    }
}

/**
 * Get mosque data by slug.
 * Defined in functions.php (includes 'unclaimed' status).
 * Removed duplicate here to avoid shadowing the correct version.
 */

/**
 * Check if YourJannah plugin is active.
 */
if ( ! function_exists( 'ynj_plugin_active' ) ) {
    function ynj_plugin_active() {
        return class_exists( 'YNJ_DB' );
    }
}

/**
 * Get the REST API base URL (without trailing slash).
 */
function ynj_api_url() {
    return rest_url( 'ynj/v1' );
}

/**
 * Output a mosque page URL.
 */
function ynj_mosque_url( $slug, $page = '' ) {
    $url = home_url( '/mosque/' . $slug );
    if ( $page ) $url .= '/' . $page;
    return $url;
}

/**
 * Get formatted copyright text with dynamic year.
 */
function ynj_copyright() {
    $text = class_exists( 'YNJ_Theme_Admin' )
        ? YNJ_Theme_Admin::get( 'footer_copyright' )
        : '&copy; {year} YourJannah';
    return str_replace( '{year}', date( 'Y' ), $text );
}

/**
 * Get a random hadith from settings.
 */
function ynj_random_hadith() {
    if ( class_exists( 'YNJ_Theme_Admin' ) ) {
        return YNJ_Theme_Admin::get_random_hadith();
    }
    return [
        'text'   => 'Prayer in congregation is twenty-seven times more virtuous than prayer offered alone.',
        'source' => 'Sahih al-Bukhari 645',
    ];
}

/**
 * Check if the current user is a mosque admin.
 */
function ynj_is_mosque_admin() {
    return current_user_can( 'ynj_manage_mosque' );
}

/**
 * Get the mosque ID for the current admin user.
 */
function ynj_current_admin_mosque_id() {
    return (int) get_user_meta( get_current_user_id(), 'ynj_mosque_id', true );
}
