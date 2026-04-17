<?php
/**
 * YourJannah Theme Functions
 *
 * Mosque community platform theme. Works with the yn-jannah plugin
 * for data/API, this theme handles all frontend rendering.
 *
 * @package YourJannah
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_THEME_VERSION', '3.9.5' );
define( 'YNJ_THEME_DIR', get_stylesheet_directory() );
define( 'YNJ_THEME_URI', get_stylesheet_directory_uri() );

// ================================================================
// INCLUDES
// ================================================================

require_once YNJ_THEME_DIR . '/inc/class-ynj-theme-admin.php';
require_once YNJ_THEME_DIR . '/inc/template-tags.php';

// Register admin settings page
if ( is_admin() ) {
    YNJ_Theme_Admin::register();
}

// ================================================================
// THEME SETUP
// ================================================================

add_action( 'after_setup_theme', function() {

    // Let WordPress manage the document title
    add_theme_support( 'title-tag' );

    // Featured images on posts/pages
    add_theme_support( 'post-thumbnails' );

    // Custom logo (for header)
    add_theme_support( 'custom-logo', [
        'height'      => 48,
        'width'       => 48,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    // HTML5 markup
    add_theme_support( 'html5', [
        'search-form', 'comment-form', 'comment-list',
        'gallery', 'caption', 'style', 'script',
    ] );

    // Block editor support
    add_theme_support( 'editor-styles' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'wp-block-styles' );

    // Custom image sizes for mosque content
    add_image_size( 'ynj-mosque-thumb', 800, 450, true );
    add_image_size( 'ynj-event-card', 680, 382, true );
    add_image_size( 'ynj-sponsor-logo', 200, 200, true );

    // Register navigation menus
    register_nav_menus( [
        'primary' => __( 'Header Navigation', 'yourjannah' ),
        'mobile'  => __( 'Mobile Bottom Navigation', 'yourjannah' ),
        'footer'  => __( 'Footer Navigation', 'yourjannah' ),
    ] );

} );

// ================================================================
// ENQUEUE ASSETS (Proper WordPress way)
// ================================================================

add_action( 'wp_enqueue_scripts', function() {

    // Google Fonts
    wp_enqueue_style(
        'ynj-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    // Main theme stylesheet
    wp_enqueue_style(
        'ynj-theme',
        YNJ_THEME_URI . '/assets/css/theme.css',
        [ 'ynj-google-fonts' ],
        YNJ_THEME_VERSION
    );

    // Modern theme overlay (loads AFTER theme.css, only when mosque has theme='modern')
    $_ynj_theme_slug = get_query_var( 'ynj_mosque_slug', '' );
    if ( ! $_ynj_theme_slug && isset( $_COOKIE['ynj_mosque_slug'] ) ) $_ynj_theme_slug = sanitize_title( $_COOKIE['ynj_mosque_slug'] );
    if ( ! $_ynj_theme_slug ) $_ynj_theme_slug = 'yourniyyah-masjid';
    $_ynj_theme_m = $_ynj_theme_slug ? ynj_get_mosque( $_ynj_theme_slug ) : null;
    if ( $_ynj_theme_m && ! empty( $_ynj_theme_m->theme ) && $_ynj_theme_m->theme === 'modern' ) {
        wp_enqueue_style(
            'ynj-theme-modern',
            YNJ_THEME_URI . '/assets/css/theme-modern.css',
            [ 'ynj-theme' ],
            YNJ_THEME_VERSION
        );
    }

    // Main theme script
    wp_enqueue_script(
        'ynj-theme',
        YNJ_THEME_URI . '/assets/js/theme.js',
        [],
        YNJ_THEME_VERSION,
        true // load in footer
    );

    // Pass data to JS (REST URL, nonce, site URL, VAPID key)
    $vapid = class_exists( 'YNJ_Push' ) ? YNJ_Push::get_public_key() : '';
    $mosque_slug = get_query_var( 'ynj_mosque_slug', '' );

    wp_localize_script( 'ynj-theme', 'ynjData', [
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'restUrl'    => rest_url( 'ynj/v1/' ),
        'nonce'      => wp_create_nonce( 'wp_rest' ),
        'siteUrl'    => home_url( '/' ),
        'themeUrl'   => YNJ_THEME_URI . '/',
        'vapidKey'   => $vapid,
        'mosqueSlug' => $mosque_slug,
        'isLoggedIn' => is_user_logged_in(),
        'userToken'  => '', // Set via localStorage on client
    ] );

    // Homepage script — on front page AND mosque profile pages (same layout)
    $request_path = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
    $is_homepage = ( $request_path === '' || is_front_page() ) && ! get_query_var( 'ynj_mosque_slug' ) && ! get_query_var( 'ynj_page_type' );
    $is_mosque_profile = ( get_query_var( 'ynj_page_type' ) === 'mosque_profile' );
    if ( $is_homepage || $is_mosque_profile ) {
        wp_enqueue_script(
            'ynj-homepage',
            YNJ_THEME_URI . '/assets/js/homepage.js',
            [ 'ynj-theme' ],
            YNJ_THEME_VERSION,
            true
        );
    }

} );

// ================================================================
// CUSTOM DOCUMENT TITLE
// ================================================================

add_filter( 'document_title_parts', function( $title ) {
    $title['site'] = 'YourJannah';
    return $title;
} );

add_filter( 'document_title_separator', function() {
    return '—';
} );

// ================================================================
// PWA SUPPORT
// ================================================================

// Prevent Varnish/page cache from caching HTML (dynamic membership bar)
add_action( 'send_headers', function() {
    if ( ! is_admin() ) {
        header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
        header( 'X-Varnish-Cache: BYPASS' );
    }
} );

add_action( 'wp_head', function() {
    $plugin_url = defined( 'YNJ_URL' ) ? YNJ_URL : '';
    if ( $plugin_url ) {
        echo '<link rel="manifest" href="' . esc_url( $plugin_url . 'manifest.json' ) . '">' . "\n";
    }
    echo '<meta name="theme-color" content="#0a1628">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="YourJannah">' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( ( defined( 'YNJ_URL' ) ? YNJ_URL : '' ) . 'assets/icons/icon-192.png' ) . '">' . "\n";

    // Output ynjData early in <head> so inline template scripts can use it
    $vapid = class_exists( 'YNJ_Push' ) ? YNJ_Push::get_public_key() : '';
    $mosque_slug = get_query_var( 'ynj_mosque_slug', '' );

    // Pre-load mosque data to avoid API call on every page load
    $mosque_data = null;
    if ( $mosque_slug && class_exists( 'YNJ_DB' ) ) {
        $mosque_obj = ynj_get_mosque( $mosque_slug );
        if ( $mosque_obj ) {
            $mosque_data = [
                'id'        => (int) $mosque_obj->id,
                'name'      => $mosque_obj->name,
                'slug'      => $mosque_obj->slug,
                'address'   => $mosque_obj->address,
                'city'      => $mosque_obj->city,
                'postcode'  => $mosque_obj->postcode,
                'latitude'  => $mosque_obj->latitude ? (float) $mosque_obj->latitude : null,
                'longitude' => $mosque_obj->longitude ? (float) $mosque_obj->longitude : null,
                'phone'     => $mosque_obj->phone,
                'website'   => $mosque_obj->website,
                'dfm_slug'      => $mosque_obj->dfm_slug ?? '',
                'prayer_times'  => isset( $mosque_obj->prayer_times ) ? ( is_string( $mosque_obj->prayer_times ) ? json_decode( $mosque_obj->prayer_times, true ) : $mosque_obj->prayer_times ) : null,
            ];
        }
    }

    $data = [
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'restUrl'    => rest_url( 'ynj/v1/' ),
        'nonce'      => wp_create_nonce( 'wp_rest' ),
        'siteUrl'    => home_url( '/' ),
        'themeUrl'   => YNJ_THEME_URI . '/',
        'vapidKey'   => $vapid,
        'mosqueSlug' => $mosque_slug,
        'mosque'     => $mosque_data,
        'isLoggedIn' => is_user_logged_in(),
        'userToken'  => '',
    ];
    echo '<script>var ynjData = ' . wp_json_encode( $data ) . ';</script>' . "\n";
}, 1 );

// ================================================================
// BODY CLASSES
// ================================================================

add_filter( 'body_class', function( $classes ) {
    // Add mosque slug if on a mosque page
    if ( get_query_var( 'ynj_mosque_slug' ) ) {
        $classes[] = 'ynj-mosque-page';
        $classes[] = 'ynj-mosque-' . sanitize_html_class( get_query_var( 'ynj_mosque_slug' ) );
    }
    return $classes;
} );

// ================================================================
// CUSTOM QUERY VARS
// ================================================================

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'ynj_mosque_slug';
    $vars[] = 'ynj_page_type';
    $vars[] = 'ynj_item_id';
    $vars[] = 'ynj_listing_type';
    return $vars;
} );

// ================================================================
// REWRITE RULES (instead of custom router)
// ================================================================

add_action( 'init', function() {

    // Mosque sub-pages: /mosque/{slug}/{page}
    $mosque_pages = [
        'events', 'classes', 'fundraising', 'sponsors', 'rooms',
        'services', 'patron', 'madrassah', 'contact', 'prayers',
        'donate', 'directory', 'people', 'hub', 'business', 'help',
    ];

    foreach ( $mosque_pages as $page ) {
        add_rewrite_rule(
            '^mosque/([^/]+)/' . $page . '/?$',
            'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=' . $page,
            'top'
        );
    }

    // Mosque event detail: /mosque/{slug}/events/{id}
    add_rewrite_rule(
        '^mosque/([^/]+)/events/(\d+)/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=event_detail&p=$matches[2]',
        'top'
    );

    // Business/service detail: /mosque/{slug}/business/{id}
    add_rewrite_rule(
        '^mosque/([^/]+)/business/(\d+)/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=business_detail&ynj_item_id=$matches[2]',
        'top'
    );
    add_rewrite_rule(
        '^mosque/([^/]+)/service/(\d+)/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=service_detail&ynj_item_id=$matches[2]',
        'top'
    );
    // Edit listing: /mosque/{slug}/business/{id}/edit
    add_rewrite_rule(
        '^mosque/([^/]+)/business/(\d+)/edit/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=listing_edit&ynj_item_id=$matches[2]&ynj_listing_type=business',
        'top'
    );
    add_rewrite_rule(
        '^mosque/([^/]+)/service/(\d+)/edit/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=listing_edit&ynj_item_id=$matches[2]&ynj_listing_type=service',
        'top'
    );

    // Sponsor join: /mosque/{slug}/sponsors/join
    add_rewrite_rule(
        '^mosque/([^/]+)/sponsors/join/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=sponsor_join',
        'top'
    );

    // Service listing: /mosque/{slug}/services/join
    add_rewrite_rule(
        '^mosque/([^/]+)/services/join/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=service_join',
        'top'
    );

    // Mosque profile: /mosque/{slug}
    add_rewrite_rule(
        '^mosque/([^/]+)/?$',
        'index.php?ynj_mosque_slug=$matches[1]&ynj_page_type=mosque_profile',
        'top'
    );

    // Static pages
    add_rewrite_rule( '^live/?$', 'index.php?ynj_page_type=live', 'top' );
    add_rewrite_rule( '^login/?$', 'index.php?ynj_page_type=login', 'top' );
    add_rewrite_rule( '^register/?$', 'index.php?ynj_page_type=register', 'top' );
    add_rewrite_rule( '^profile/?$', 'index.php?ynj_page_type=profile', 'top' );
    add_rewrite_rule( '^forgot-password/?$', 'index.php?ynj_page_type=forgot_password', 'top' );
    add_rewrite_rule( '^reset-password/?$', 'index.php?ynj_page_type=reset_password', 'top' );
    add_rewrite_rule( '^dashboard/?$', 'index.php?ynj_page_type=dashboard', 'top' );
    add_rewrite_rule( '^classes/?$', 'index.php?ynj_page_type=classes_browse', 'top' );
    add_rewrite_rule( '^sponsor-yourjannah/?$', 'index.php?ynj_page_type=sponsor_yourjannah', 'top' );
    add_rewrite_rule( '^change-mosque/?$', 'index.php?ynj_page_type=change_mosque', 'top' );
    add_rewrite_rule( '^appeals/?$', 'index.php?ynj_page_type=appeals', 'top' );

} );

// ================================================================
// TEMPLATE ROUTING
// ================================================================

add_filter( 'template_include', function( $template ) {
    $page_type = get_query_var( 'ynj_page_type' );
    $mosque_slug = get_query_var( 'ynj_mosque_slug' );

    if ( ! $page_type && ! $mosque_slug ) {
        return $template;
    }

    // Set 200 status for our custom routes
    status_header( 200 );

    // Map page types to template files
    $template_map = [
        'mosque_profile'  => 'page-templates/page-mosque.php',
        'events'          => 'page-templates/page-events.php',
        'classes'         => 'page-templates/page-classes.php',
        'fundraising'     => 'page-templates/page-fundraising.php',
        'sponsors'        => 'page-templates/page-sponsors.php',
        'rooms'           => 'page-templates/page-booking.php',
        'services'        => 'page-templates/page-people.php',
        'people'          => 'page-templates/page-people.php',
        'patron'          => 'page-templates/page-patron.php',
        'madrassah'       => 'page-templates/page-madrassah.php',
        'contact'         => 'page-templates/page-contact.php',
        'prayers'         => 'page-templates/page-prayers.php',
        'donate'          => 'page-templates/page-donate.php',
        'directory'       => 'page-templates/page-directory.php',
        'event_detail'    => 'page-templates/page-event-detail.php',
        'hub'             => 'page-templates/page-masjid-hub.php',
        'business'        => 'page-templates/page-business.php',
        'business_detail' => 'page-templates/page-business-detail.php',
        'service_detail'  => 'page-templates/page-business-detail.php',
        'listing_edit'    => 'page-templates/page-listing-edit.php',
        'help'            => 'page-templates/page-help.php',
        'sponsor_join'    => 'page-templates/page-sponsor-join.php',
        'service_join'    => 'page-templates/page-service-join.php',
        'live'            => 'page-templates/page-live.php',
        'login'           => 'page-templates/page-login.php',
        'register'        => 'page-templates/page-register.php',
        'profile'         => 'page-templates/page-profile.php',
        'forgot_password' => 'page-templates/page-forgot-password.php',
        'reset_password'  => 'page-templates/page-reset-password.php',
        'dashboard'       => 'page-templates/page-dashboard.php',
        'classes_browse'       => 'page-templates/page-classes-browse.php',
        'sponsor_yourjannah'   => 'page-templates/page-sponsor-yourjannah.php',
        'change_mosque'        => 'page-templates/page-change-mosque.php',
        'appeals'              => 'page-templates/page-appeals.php',
    ];

    if ( isset( $template_map[ $page_type ] ) ) {
        $custom = locate_template( $template_map[ $page_type ] );
        if ( $custom ) {
            // Make mosque slug available to templates
            set_query_var( 'ynj_mosque_slug', $mosque_slug );
            return $custom;
        }
    }

    return $template;
} );

// ================================================================
// DIGITAL ASSET LINKS (for Android TWA verification)
// ================================================================

add_action( 'init', function() {
    add_rewrite_rule( '^\.well-known/assetlinks\.json$', 'index.php?ynj_assetlinks=1', 'top' );
} );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'ynj_assetlinks';
    return $vars;
} );

add_action( 'template_redirect', function() {
    if ( get_query_var( 'ynj_assetlinks' ) ) {
        header( 'Content-Type: application/json' );
        echo '[{
      "relation": ["delegate_permission/common.handle_all_urls"],
      "target": {
        "namespace": "android_app",
        "package_name": "com.yourjannah.app",
        "sha256_cert_fingerprints": ["5E:26:90:48:E0:77:EB:8F:92:66:6B:98:E6:CD:7A:91:2C:D3:27:95:DA:3E:95:30:35:A4:5A:2D:03:F6:C3:C9"]
      }
    }]';
        exit;
    }
} );

// ================================================================
// FLUSH REWRITE RULES ON THEME SWITCH
// ================================================================

add_action( 'after_switch_theme', function() {
    flush_rewrite_rules();
} );

// Auto-flush rewrite rules when theme version changes (catches new routes on deploy)
add_action( 'init', function() {
    $stored = get_option( 'ynj_theme_rewrite_version', '' );
    if ( $stored !== YNJ_THEME_VERSION ) {
        flush_rewrite_rules();
        update_option( 'ynj_theme_rewrite_version', YNJ_THEME_VERSION );
    }
}, 999 );

// ================================================================
// HELPER: Get current mosque data
// ================================================================

function ynj_get_mosque( $slug = null ) {
    if ( ! $slug ) {
        $slug = get_query_var( 'ynj_mosque_slug' );
    }
    if ( ! $slug || ! class_exists( 'YNJ_DB' ) ) return null;

    $cache_key = 'ynj_mosque_' . $slug;
    $mosque = wp_cache_get( $cache_key, 'ynj' );
    if ( $mosque ) return $mosque;

    global $wpdb;
    $mosque = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE slug = %s AND status IN ('active','unclaimed')",
        $slug
    ) );

    if ( $mosque ) {
        wp_cache_set( $cache_key, $mosque, 'ynj', 300 );
    }

    return $mosque;
}

function ynj_get_mosque_by_id( $id ) {
    if ( ! $id || ! class_exists( 'YNJ_DB' ) ) return null;
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d",
        (int) $id
    ) );
}

// ================================================================
// HELPER: Mosque slug for templates
// ================================================================

function ynj_mosque_slug() {
    return sanitize_title( get_query_var( 'ynj_mosque_slug', '' ) );
}

// ================================================================
// HELPER: Check if plugin is active
// ================================================================

function ynj_plugin_active() {
    return class_exists( 'YNJ_DB' );
}
