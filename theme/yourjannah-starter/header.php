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

<script>
function ynjGpsFind(){
    if(!navigator.geolocation)return;
    var btn=document.getElementById('gps-btn');
    btn.classList.add('ynj-gps-btn--loading');
    navigator.geolocation.getCurrentPosition(function(p){
        btn.classList.remove('ynj-gps-btn--loading');
        fetch(ynjData.restUrl+'mosques/nearest?lat='+p.coords.latitude+'&lng='+p.coords.longitude+'&limit=5')
        .then(function(r){return r.json()})
        .then(function(d){
            if(!d.ok||!d.mosques||!d.mosques.length)return;
            var dd=document.getElementById('mosque-dropdown');
            var ml=document.getElementById('mosque-list');
            if(dd)dd.style.display='block';
            if(ml){
                ml.innerHTML=d.mosques.map(function(m){
                    var dist=m.distance?(' · '+m.distance.toFixed(1)+'km'):'';
                    var sub=[m.city,m.postcode].filter(Boolean).join(', ')+dist;
                    return '<button style="display:block;width:100%;text-align:left;padding:12px 16px;border:none;background:none;cursor:pointer;font-family:inherit;border-bottom:1px solid #f0f0f0;" onclick="localStorage.setItem(\'ynj_mosque_slug\',\''+m.slug+'\');localStorage.setItem(\'ynj_mosque_name\',\''+m.name.replace(/'/g,'')+'\');localStorage.removeItem(\'ynj_cache_date\');window.location.href=\'/mosque/'+m.slug+'\'"><strong style="font-size:14px;display:block;">'+m.name+'</strong><span style="font-size:11px;color:#6b8fa3;">'+sub+'</span></button>';
                }).join('');
            }
        });
    },function(){
        btn.classList.remove('ynj-gps-btn--loading');
    },{timeout:8000,maximumAge:300000});
}
</script>
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
                <button class="ynj-mosque-pill__gps" id="gps-btn" type="button" title="<?php esc_attr_e( 'Detect my location', 'yourjannah' ); ?>" onclick="ynjGpsFind()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                </button>
                <span class="ynj-mosque-pill__name" id="mosque-name" onclick="var d=document.getElementById('mosque-dropdown');if(d){d.style.display=d.style.display==='block'?'none':'block';var s=document.getElementById('mosque-search');if(s&&d.style.display==='block')s.focus();}" style="cursor:pointer;"><?php echo esc_html( $mosque_name ?: __( 'Finding...', 'yourjannah' ) ); ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6;flex-shrink:0;cursor:pointer;" onclick="var d=document.getElementById('mosque-dropdown');if(d){d.style.display=d.style.display==='block'?'none':'block';var s=document.getElementById('mosque-search');if(s&&d.style.display==='block')s.focus();}"><path d="M6 9l6 6 6-6"/></svg>
            </div>

            <!-- User account handled by top membership bar -->
        </div>
    </div>
</header>
