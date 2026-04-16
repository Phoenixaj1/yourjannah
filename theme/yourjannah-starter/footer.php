<?php
/**
 * Footer template — mobile bottom navigation + wp_footer()
 *
 * @package YourJannah
 */
?>

<?php if ( has_nav_menu( 'mobile' ) ) : ?>
<nav class="ynj-nav">
    <div class="ynj-nav__inner">
        <?php wp_nav_menu( [
            'theme_location' => 'mobile',
            'container'      => false,
            'menu_class'     => '',
            'depth'          => 1,
            'fallback_cb'    => 'ynj_default_mobile_nav',
            'walker'         => new YNJ_Mobile_Nav_Walker(),
        ] ); ?>
    </div>
</nav>
<?php else : ?>
<nav class="ynj-nav">
    <div class="ynj-nav__inner">
        <?php ynj_default_mobile_nav(); ?>
    </div>
</nav>
<?php endif; ?>

<!-- Mosque dropdown now in header.php via <details> element -->
<div class="ynj-dropdown" id="mosque-dropdown" style="display:none;">
    <div class="ynj-dropdown__inner">
        <form method="get" action="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" style="display:flex;gap:8px;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
            <input class="ynj-dropdown__search" name="q" type="text" placeholder="<?php esc_attr_e( 'Search mosques...', 'yourjannah' ); ?>" autocomplete="off" style="flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;">
            <button type="submit" style="padding:10px 16px;border:none;border-radius:10px;background:#00ADEF;color:#fff;font-weight:700;font-size:13px;cursor:pointer;"><?php esc_html_e( 'Search', 'yourjannah' ); ?></button>
        </form>
        <div class="ynj-dropdown__list" id="mosque-list">
            <p style="padding:8px 16px 4px;font-size:11px;font-weight:700;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;">📍 <?php esc_html_e( 'Nearby', 'yourjannah' ); ?></p>
            <?php
            $slug_for_dd = ynj_mosque_slug();
            $mosque_for_dd = ynj_get_mosque( $slug_for_dd );
            $dd_nearby = [];
            if ( $mosque_for_dd && $mosque_for_dd->latitude && class_exists( 'YNJ_DB' ) ) {
                global $wpdb;
                $mt = YNJ_DB::table( 'mosques' );
                $dd_nearby = $wpdb->get_results( $wpdb->prepare(
                    "SELECT slug, name, city, postcode,
                            ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
                     FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
                     ORDER BY distance ASC LIMIT 5",
                    $mosque_for_dd->latitude, $mosque_for_dd->longitude, $mosque_for_dd->latitude
                ) ) ?: [];
            }
            foreach ( $dd_nearby as $nm ) :
                $dist = isset( $nm->distance ) ? number_format( (float) $nm->distance, 1 ) . 'km' : '';
            ?>
            <a href="<?php echo esc_url( home_url( '/?ynj_select=' . $nm->slug ) ); ?>" style="display:block;padding:12px 16px;text-decoration:none;color:#0a1628;border-bottom:1px solid #f0f0f0;">
                <strong style="font-size:14px;display:block;"><?php echo esc_html( $nm->name ); ?></strong>
                <span style="font-size:11px;color:#6b8fa3;"><?php echo esc_html( implode( ', ', array_filter( [ $nm->city, $nm->postcode ] ) ) ); ?><?php if ( $dist ) echo ' · ' . esc_html( $dist ); ?></span>
            </a>
            <?php endforeach; ?>
            <a href="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" style="display:block;padding:12px 16px;text-align:center;font-size:13px;font-weight:700;color:#00ADEF;text-decoration:none;"><?php esc_html_e( 'Browse All Mosques →', 'yourjannah' ); ?></a>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
<?php

/**
 * Default mobile nav when no WP menu is assigned.
 * This ensures the bottom nav works out of the box.
 */
function ynj_default_mobile_nav() {
    $slug = ynj_mosque_slug() ?: 'yourniyyah-masjid';
    $tabs = [
        [ 'label' => 'Home',      'href' => '/',                                     'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>' ],
        [ 'label' => 'Masjid',    'href' => '/mosque/' . $slug . '/hub',               'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/></svg>' ],
        [ 'label' => 'Sponsors',  'href' => '/mosque/' . $slug . '/sponsors',        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="#f59e0b"/></svg>' ],
        [ 'label' => 'Fundraise', 'href' => '/mosque/' . $slug . '/fundraising',     'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" fill="#ef4444"/></svg>' ],
        [ 'label' => 'More',      'href' => '#',                                     'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>', 'is_more' => true ],
    ];

    $current_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );

    foreach ( $tabs as $tab ) {
        if ( ! empty( $tab['is_more'] ) ) {
            echo '<button class="ynj-nav__item" onclick="document.getElementById(\'ynj-more-drawer\').classList.toggle(\'open\')" type="button">' . $tab['icon'] . '<span>' . esc_html( $tab['label'] ) . '</span></button>';
            continue;
        }
        $is_active = ( $tab['href'] === '/' && $current_path === '/' ) ||
                     ( $tab['href'] !== '/' && str_starts_with( $current_path, $tab['href'] ) );
        $class = 'ynj-nav__item' . ( $is_active ? ' ynj-nav__item--active' : '' );
        printf(
            '<a class="%s" href="%s">%s<span>%s</span></a>',
            esc_attr( $class ),
            esc_url( home_url( $tab['href'] ) ),
            $tab['icon'],
            esc_html( $tab['label'] )
        );
    }

    // More drawer
    $more_links = [
        [ 'label' => 'Classes',    'href' => '/mosque/' . $slug . '/classes',       'icon' => '🎓' ],
        [ 'label' => 'Live',       'href' => '/live',                               'icon' => '📡' ],
        [ 'label' => 'Prayers',    'href' => '/mosque/' . $slug . '/prayers',       'icon' => '🕐' ],
        [ 'label' => 'Booking',    'href' => '/mosque/' . $slug . '/rooms',         'icon' => '🏠' ],
        [ 'label' => 'Masjid Info','href' => '/mosque/' . $slug,                    'icon' => '🕌' ],
        [ 'label' => 'Patron',     'href' => '/mosque/' . $slug . '/patron',        'icon' => '🏅' ],
        [ 'label' => 'Profile',    'href' => '/profile',                            'icon' => '👤' ],
        [ 'label' => 'Login',      'href' => '/login',                              'icon' => '🔑' ],
        [ 'label' => 'Sponsor YourJannah','href' => '/sponsor-yourjannah',           'icon' => '🤲' ],
    ];
    echo '<div class="ynj-more-drawer" id="ynj-more-drawer" onclick="if(event.target===this)this.classList.remove(\'open\')">';
    echo '<div class="ynj-more-drawer__sheet">';
    echo '<div class="ynj-more-drawer__handle"></div>';
    foreach ( $more_links as $link ) {
        printf(
            '<a class="ynj-more-drawer__link" href="%s"><span>%s</span>%s</a>',
            esc_url( home_url( $link['href'] ) ),
            $link['icon'],
            esc_html( $link['label'] )
        );
    }
    echo '</div></div>';
}

/**
 * Custom walker for mobile nav menu items (adds SVG icons).
 */
class YNJ_Mobile_Nav_Walker extends Walker_Nav_Menu {
    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes = implode( ' ', $item->classes ?? [] );
        $is_active = in_array( 'current-menu-item', $item->classes ?? [], true );
        $class = 'ynj-nav__item' . ( $is_active ? ' ynj-nav__item--active' : '' );

        // Use the description field for SVG icon (set via menu editor)
        $icon = $item->description ?: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>';

        $output .= sprintf(
            '<a class="%s" href="%s">%s<span>%s</span></a>',
            esc_attr( $class ),
            esc_url( $item->url ),
            $icon,
            esc_html( $item->title )
        );
    }
    public function end_el( &$output, $item, $depth = 0, $args = null ) {}
    public function start_lvl( &$output, $depth = 0, $args = null ) {}
    public function end_lvl( &$output, $depth = 0, $args = null ) {}
}
