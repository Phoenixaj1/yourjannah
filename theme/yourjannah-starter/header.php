<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

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
