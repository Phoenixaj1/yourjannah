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
            <?php if ( has_custom_logo() ) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <img src="<?php echo esc_url( YNJ_THEME_URI . '/assets/icons/logo.png' ); ?>" alt="<?php bloginfo( 'name' ); ?>" width="48" height="48">
            <?php endif; ?>
            <span><?php bloginfo( 'name' ); ?></span>
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
            // GPS button (always available)
            ?>
            <button class="ynj-gps-btn" id="gps-btn" type="button" title="<?php esc_attr_e( 'Detect my location', 'yourjannah' ); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
            </button>

            <?php
            // Mosque name (shown on all pages)
            $mosque_slug = ynj_mosque_slug();
            $mosque = ynj_get_mosque( $mosque_slug );
            $mosque_name = $mosque ? $mosque->name : '';
            ?>
            <a href="<?php echo $mosque_slug ? esc_url( home_url( '/mosque/' . $mosque_slug ) ) : '#'; ?>"
               class="ynj-mosque-selector" id="mosque-name-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.7;">
                    <path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/>
                </svg>
                <span id="mosque-name-text"><?php echo esc_html( $mosque_name ?: __( 'Select Mosque', 'yourjannah' ) ); ?></span>
            </a>
        </div>
    </div>
</header>
