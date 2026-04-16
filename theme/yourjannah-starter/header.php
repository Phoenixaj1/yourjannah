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
            ?>
            <!-- GPS + Mosque selector fused -->
            <div class="ynj-mosque-pill" id="mosque-selector">
                <button class="ynj-mosque-pill__gps" id="gps-btn" type="button" title="<?php esc_attr_e( 'Detect my location', 'yourjannah' ); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                </button>
                <span class="ynj-mosque-pill__name" id="mosque-name"><?php echo esc_html( $mosque_name ?: __( 'Finding...', 'yourjannah' ) ); ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6;flex-shrink:0;"><path d="M6 9l6 6 6-6"/></svg>
            </div>

            <!-- Radius badge -->
            <select id="ynj-radius" class="ynj-radius-badge" onchange="if(typeof onRadiusChange==='function')onRadiusChange()">
                <option value="0" selected>+0m</option>
                <option value="5">+5mi</option>
                <option value="10">+10mi</option>
                <option value="25">+25mi</option>
                <option value="9999">All</option>
            </select>
        </div>
    </div>
</header>
