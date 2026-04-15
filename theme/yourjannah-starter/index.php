<?php
/**
 * Default template — fallback for any page.
 *
 * @package YourJannah
 */

get_header();
?>
<main class="ynj-main">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article class="ynj-card">
                <h2><?php the_title(); ?></h2>
                <?php the_content(); ?>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <div class="ynj-card" style="text-align:center;padding:40px 20px;">
            <h2><?php esc_html_e( 'Nothing found', 'yourjannah' ); ?></h2>
            <p class="ynj-text-muted"><?php esc_html_e( 'The page you are looking for does not exist.', 'yourjannah' ); ?></p>
        </div>
    <?php endif; ?>
</main>
<?php
get_footer();
