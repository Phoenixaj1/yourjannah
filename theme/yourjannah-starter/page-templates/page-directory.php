<?php
/**
 * Template: Directory (Quick Links) Page
 *
 * Quick links grid to all mosque sections: classes, live, events, timetable, etc.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>

<main class="ynj-main">
    <section class="ynj-card">
        <h2 class="ynj-card__title"><?php esc_html_e( 'Quick Links', 'yourjannah' ); ?></h2>
        <div class="ynj-more-grid">
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/classes' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                <span><?php esc_html_e( 'Classes & Courses', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/live' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 010 8.49M7.76 16.24a6 6 0 010-8.49"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 19.07a10 10 0 010-14.14"/></svg>
                <span><?php esc_html_e( 'Live Events', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/events' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span><?php esc_html_e( 'All Events', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/prayers' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <span><?php esc_html_e( 'Full Timetable', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg>
                <span><?php esc_html_e( 'Services', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/rooms' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                <span><?php esc_html_e( 'Room Bookings', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/contact' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
                <span><?php esc_html_e( 'Contact Mosque', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span><?php esc_html_e( 'My Account', 'yourjannah' ); ?></span>
            </a>
            <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" class="ynj-more-item">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span><?php esc_html_e( 'Mosque Admin', 'yourjannah' ); ?></span>
            </a>
        </div>
    </section>
</main>
<?php get_footer(); ?>
