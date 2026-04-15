<?php
/**
 * YNJ_Router — Domain-based routing for yourjannah.com.
 *
 * Intercepts requests on the YourJannah domain and routes them to the
 * appropriate renderer or dashboard handler.
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_Router {

    /**
     * Register the router on template_redirect.
     */
    public static function init(): void {
        add_action( 'template_redirect', [ __CLASS__, 'handle' ], 1 );
    }

    /**
     * Main routing handler. Called on `template_redirect`.
     *
     * Checks if the current request is on the YourJannah domain and routes
     * to the correct renderer. Exits after output to prevent WordPress from
     * loading its own template.
     */
    public static function handle(): void {
        if ( ! self::is_yourjannah_domain() ) {
            return;
        }

        $path = self::get_request_path();

        // ---- Static assets served with correct content types ----

        if ( '/sw.js' === $path ) {
            self::serve_service_worker();
            return;
        }

        if ( '/manifest.json' === $path ) {
            self::serve_manifest();
            return;
        }

        // ---- Dashboard (SPA — all sub-paths handled client-side) ----

        if ( '/dashboard' === $path || str_starts_with( $path, '/dashboard/' ) ) {
            YNJ_Dashboard::render();
            exit;
        }

        // ---- Mosque sub-pages ----

        $mosque_routes = [
            // /mosque/{slug}/prayers
            '#^/mosque/([a-z0-9-]+)/prayers/?$#' => 'render_prayers',
            // /mosque/{slug}/events
            '#^/mosque/([a-z0-9-]+)/events/?$#'  => 'render_events',
            // /mosque/{slug}/donate
            '#^/mosque/([a-z0-9-]+)/donate/?$#'  => 'render_donate',
            // /mosque/{slug}/directory
            '#^/mosque/([a-z0-9-]+)/directory/?$#' => 'render_directory',
            // /mosque/{slug} (profile — must be last)
            '#^/mosque/([a-z0-9-]+)/?$#'          => 'render_mosque',
        ];

        foreach ( $mosque_routes as $pattern => $method ) {
            if ( preg_match( $pattern, $path, $matches ) ) {
                $slug = sanitize_title( $matches[1] );
                YNJ_Renderer::$method( $slug );
                exit;
            }
        }

        // ---- Home ----

        if ( '/' === $path || '' === $path ) {
            YNJ_Renderer::render_home();
            exit;
        }

        // ---- 404 fallback ----

        status_header( 404 );
        YNJ_Renderer::render_404();
        exit;
    }

    /* ------------------------------------------------------------------ */
    /*  Domain detection                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Check if the current request is on the YourJannah domain.
     *
     * Matches:
     *  - yourjannah.com / www.yourjannah.com (production)
     *  - *.yourjannah.com (subdomains)
     *  - localhost:8090 (local development)
     *
     * @return bool
     */
    private static function is_yourjannah_domain(): bool {
        $host = isset( $_SERVER['HTTP_HOST'] )
            ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) )
            : '';

        // Production domain.
        if ( str_contains( $host, 'yourjannah' ) ) {
            return true;
        }

        // Local dev.
        if ( str_starts_with( $host, 'localhost:8090' ) ) {
            return true;
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  Path parsing                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Get the clean request path (no query string, lowercase, trimmed).
     *
     * @return string
     */
    private static function get_request_path(): string {
        $uri  = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '/';

        $path = wp_parse_url( $uri, PHP_URL_PATH );
        $path = strtolower( trim( (string) $path ) );

        // Remove trailing slash except for root.
        if ( '/' !== $path ) {
            $path = rtrim( $path, '/' );
        }

        return $path;
    }

    /* ------------------------------------------------------------------ */
    /*  Static file handlers                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Serve the PWA service worker with the correct MIME type and
     * Service-Worker-Allowed header.
     */
    private static function serve_service_worker(): void {
        $file = YNJ_DIR . 'assets/js/sw.js';

        if ( ! file_exists( $file ) ) {
            status_header( 404 );
            exit;
        }

        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        echo file_get_contents( $file );
        exit;
    }

    /**
     * Serve the PWA manifest with the correct MIME type.
     */
    private static function serve_manifest(): void {
        $file = YNJ_DIR . 'manifest.json';

        if ( ! file_exists( $file ) ) {
            status_header( 404 );
            exit;
        }

        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'Cache-Control: public, max-age=86400' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        echo file_get_contents( $file );
        exit;
    }
}
