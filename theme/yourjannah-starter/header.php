<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// ── Patron Status Bar (pure PHP, no JS) ──
$_ynj_bar_status = 'guest';
$_ynj_bar_name   = '';
$_ynj_bar_tier   = '';
$_ynj_bar_slug   = get_query_var( 'ynj_mosque_slug', '' );

if ( is_user_logged_in() ) {
    $_ynj_bar_status = 'member';
    $_ynj_bar_name   = wp_get_current_user()->display_name;
    $_wp_uid  = get_current_user_id();
    $_ynj_uid = (int) get_user_meta( $_wp_uid, 'ynj_user_id', true );
    if ( $_ynj_uid && class_exists( 'YNJ_DB' ) ) {
        global $wpdb;
        $_patron = $wpdb->get_row( $wpdb->prepare(
            "SELECT tier FROM " . YNJ_DB::table( 'patrons' ) . " WHERE user_id = %d AND status = 'active' ORDER BY amount_pence DESC LIMIT 1",
            $_ynj_uid
        ) );
        if ( $_patron ) {
            $_ynj_bar_status = 'patron';
            $_ynj_bar_tier   = $_patron->tier;
        }
    }
}

$_tier_labels = [ 'supporter' => 'Bronze', 'guardian' => 'Silver', 'champion' => 'Gold', 'platinum' => 'Platinum' ];
?>

<?php if ( $_ynj_bar_status === 'guest' ) : ?>
<div class="ynj-topbar ynj-topbar--guest">
    <span>🕌 <?php esc_html_e( 'Welcome to YourJannah', 'yourjannah' ); ?></span>
    <div class="ynj-topbar__actions">
        <a href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/register' ) ); ?>" class="ynj-topbar__cta"><?php esc_html_e( 'Join Free', 'yourjannah' ); ?></a>
    </div>
</div>
<?php elseif ( $_ynj_bar_status === 'member' ) : ?>
<div class="ynj-topbar ynj-topbar--member">
    <span>👋 <?php printf( esc_html__( 'Salam, %s', 'yourjannah' ), esc_html( explode( ' ', $_ynj_bar_name )[0] ) ); ?> · <strong><?php esc_html_e( 'Free Member', 'yourjannah' ); ?></strong></span>
    <div class="ynj-topbar__actions">
        <a href="<?php echo esc_url( home_url( '/mosque/' . ( $_ynj_bar_slug ?: 'yourniyyah-masjid' ) . '/patron' ) ); ?>" class="ynj-topbar__cta"><?php esc_html_e( 'Become a Patron →', 'yourjannah' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>"><?php esc_html_e( 'My Account', 'yourjannah' ); ?></a>
    </div>
</div>
<?php else : ?>
<div class="ynj-topbar ynj-topbar--patron">
    <span>🏅 <?php echo esc_html( explode( ' ', $_ynj_bar_name )[0] ); ?> · <strong><?php echo esc_html( $_tier_labels[ $_ynj_bar_tier ] ?? ucfirst( $_ynj_bar_tier ) ); ?> <?php esc_html_e( 'Patron', 'yourjannah' ); ?></strong></span>
    <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" style="font-size:11px;color:rgba(255,255,255,.9);text-decoration:none;"><?php esc_html_e( 'My Account', 'yourjannah' ); ?></a>
</div>
<?php endif; ?>

<header class="ynj-header">
    <div class="ynj-header__inner">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ynj-logo">
            <img src="<?php echo esc_url( YNJ_THEME_URI . '/assets/icons/logo2.png' ); ?>" alt="<?php bloginfo( 'name' ); ?>" style="height:36px;width:auto;">
        </a>

        <?php if ( has_nav_menu( 'primary' ) ) : ?>
            <?php wp_nav_menu( [
                'theme_location' => 'primary',
                'container'      => 'nav',
                'container_class' => 'ynj-header__nav',
                'container_id'   => 'desktop-nav',
                'menu_class'     => '',
                'depth'          => 1,
                'fallback_cb'    => false,
            ] ); ?>
        <?php endif; ?>

        <div class="ynj-header__right">
            <?php
            $mosque_slug = ynj_mosque_slug();
            $mosque = ynj_get_mosque( $mosque_slug );
            $mosque_name = $mosque ? $mosque->name : '';

            // Get nearby mosques for dropdown (PHP — always works)
            $nearby_mosques = [];
            if ( class_exists( 'YNJ_DB' ) && $mosque && $mosque->latitude ) {
                global $wpdb;
                $mt = YNJ_DB::table( 'mosques' );
                $nearby_mosques = $wpdb->get_results( $wpdb->prepare(
                    "SELECT slug, name, city, postcode,
                            ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
                     FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL AND id != %d
                     ORDER BY distance ASC LIMIT 10",
                    $mosque->latitude, $mosque->longitude, $mosque->latitude, $mosque->id
                ) ) ?: [];
            }
            ?>
            <?php
            // Pre-load nearby mosques in PHP for the dropdown
            $nearby_for_dropdown = [];
            if ( $mosque && $mosque->latitude && class_exists( 'YNJ_DB' ) ) {
                global $wpdb;
                $mt = YNJ_DB::table( 'mosques' );
                $nearby_for_dropdown = $wpdb->get_results( $wpdb->prepare(
                    "SELECT slug, name, city, postcode,
                            ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
                     FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
                     ORDER BY distance ASC LIMIT 5",
                    $mosque->latitude, $mosque->longitude, $mosque->latitude
                ) ) ?: [];
            }
            ?>
            <?php
            // Get nearby mosques for modal
            $dd_nearby = [];
            if ( $mosque && $mosque->latitude && class_exists( 'YNJ_DB' ) ) {
                global $wpdb;
                $mt = YNJ_DB::table( 'mosques' );
                $dd_nearby = $wpdb->get_results( $wpdb->prepare(
                    "SELECT slug, name, city, postcode,
                            ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
                     FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
                     ORDER BY distance ASC LIMIT 5",
                    $mosque->latitude, $mosque->longitude, $mosque->latitude
                ) ) ?: [];
            }
            ?>
            <!-- Mosque selector pill — triggers modal via CSS :target -->
            <a href="#mosque-modal" class="ynj-mosque-pill" id="mosque-selector" style="text-decoration:none;color:#fff;">
                <span style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                </span>
                <span class="ynj-mosque-pill__name" id="mosque-name"><?php echo esc_html( $mosque_name ?: __( 'Select Mosque', 'yourjannah' ) ); ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6;flex-shrink:0;"><path d="M6 9l6 6 6-6"/></svg>
            </a>

            <!-- Mosque modal (CSS :target, no JS) -->
            <div class="ynj-modal" id="mosque-modal">
                <a href="#" class="ynj-modal__overlay"></a>
                <div class="ynj-modal__box">
                    <a href="#" class="ynj-modal__close">&times;</a>
                    <h3 style="font-size:18px;font-weight:800;margin-bottom:4px;">🕌 <?php esc_html_e( 'Find Your Mosque', 'yourjannah' ); ?></h3>
                    <p style="font-size:12px;color:#6b8fa3;margin-bottom:14px;"><?php esc_html_e( 'Select a mosque near you or search by name.', 'yourjannah' ); ?></p>

                    <form method="get" action="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" style="display:flex;gap:6px;margin-bottom:14px;">
                        <input type="text" name="q" placeholder="<?php esc_attr_e( 'Search by name, city, postcode...', 'yourjannah' ); ?>" style="flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;">
                        <button type="submit" style="padding:10px 16px;border:none;border-radius:10px;background:#00ADEF;color:#fff;font-weight:700;font-size:13px;cursor:pointer;"><?php esc_html_e( 'Search', 'yourjannah' ); ?></button>
                    </form>

                    <?php if ( $dd_nearby ) : ?>
                    <p style="font-size:11px;font-weight:700;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">📍 <?php esc_html_e( 'Nearby', 'yourjannah' ); ?></p>
                    <?php foreach ( $dd_nearby as $nm ) :
                        $dist = isset( $nm->distance ) ? number_format( (float) $nm->distance, 1 ) . 'km' : '';
                    ?>
                    <a href="<?php echo esc_url( home_url( '/?ynj_select=' . $nm->slug ) ); ?>" style="display:block;padding:12px;border-radius:10px;text-decoration:none;color:#0a1628;margin-bottom:4px;background:#f8fafc;border:1px solid #e5e7eb;">
                        <strong style="font-size:14px;"><?php echo esc_html( $nm->name ); ?></strong><br>
                        <span style="font-size:11px;color:#6b8fa3;"><?php echo esc_html( implode( ', ', array_filter( [ $nm->city, $nm->postcode ] ) ) ); ?><?php if ( $dist ) echo ' · ' . esc_html( $dist ); ?></span>
                    </a>
                    <?php endforeach; endif; ?>

                    <a href="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" style="display:block;text-align:center;padding:12px;font-size:13px;font-weight:700;color:#00ADEF;text-decoration:none;margin-top:8px;"><?php esc_html_e( 'Browse All Mosques →', 'yourjannah' ); ?></a>
                </div>
            </div>
        </div>
    </div>
</header>
