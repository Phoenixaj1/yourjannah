<?php
/**
 * Template: Dashboard Page
 *
 * The mosque admin dashboard is a standalone SPA that renders its own
 * full HTML page. We don't use get_header()/get_footer() here because
 * YNJ_Dashboard::render() outputs complete HTML and calls exit().
 *
 * @package YourJannah
 */

if ( class_exists( 'YNJ_Dashboard' ) ) {
    YNJ_Dashboard::render();
    // render() calls exit() internally
}

// Fallback if dashboard class not available
get_header();
?>
<main class="ynj-main" style="text-align:center;padding:60px 20px;">
    <div style="font-size:48px;margin-bottom:12px;">🏛</div>
    <h2><?php esc_html_e( 'Mosque Admin Dashboard', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:20px;"><?php esc_html_e( 'The dashboard module is not available.', 'yourjannah' ); ?></p>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ynj-btn"><?php esc_html_e( 'Back to Home', 'yourjannah' ); ?></a>
</main>
<?php
get_footer();
