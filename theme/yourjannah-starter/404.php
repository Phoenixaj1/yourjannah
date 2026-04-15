<?php
/**
 * 404 Not Found template.
 *
 * @package YourJannah
 */

get_header();
?>
<main class="ynj-main" style="text-align:center;padding:60px 20px;">
    <div style="font-size:64px;margin-bottom:16px;">🕌</div>
    <h1 style="font-size:28px;font-weight:800;margin-bottom:8px;"><?php esc_html_e( 'Page Not Found', 'yourjannah' ); ?></h1>
    <p class="ynj-text-muted" style="margin-bottom:24px;"><?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'yourjannah' ); ?></p>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ynj-btn" style="display:inline-flex;justify-content:center;">
        <?php esc_html_e( 'Go to Homepage', 'yourjannah' ); ?>
    </a>
</main>
<?php
get_footer();
