<?php
/**
 * Template: Dashboard Page
 *
 * Temporary wrapper that loads the old dashboard SPA until WP Admin migration.
 *
 * @package YourJannah
 */

get_header();

if ( class_exists( 'YNJ_Dashboard' ) ) {
    YNJ_Dashboard::render();
} else {
    ?>
    <main class="ynj-main" style="text-align:center;padding:60px 20px;">
        <div style="font-size:48px;margin-bottom:12px;">&#x1F3DB;</div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;"><?php esc_html_e( 'Mosque Admin Dashboard', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted" style="margin-bottom:20px;"><?php esc_html_e( 'The dashboard module is not available. Please contact support.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ynj-btn"><?php esc_html_e( 'Back to Home', 'yourjannah' ); ?></a>
    </main>
    <?php
}

get_footer();
?>
