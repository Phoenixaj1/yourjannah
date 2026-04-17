<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>

<!-- Theme system: disabled for now — needs proper per-component redesign -->
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
    <span>🕌 <?php esc_html_e( 'Fall in love with your Masjid & Community', 'yourjannah' ); ?></span>
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

            // Get nearby mosques (single query, used for pre-populating modal)
            $nearby_mosques = [];
            if ( class_exists( 'YNJ_DB' ) && $mosque && $mosque->latitude ) {
                global $wpdb;
                $mt = YNJ_DB::table( 'mosques' );
                $nearby_mosques = $wpdb->get_results( $wpdb->prepare(
                    "SELECT slug, name, city, postcode,
                            ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
                     FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
                     ORDER BY distance ASC LIMIT 5",
                    $mosque->latitude, $mosque->longitude, $mosque->latitude
                ) ) ?: [];
            }
            ?>
            <!-- Mosque selector pill — opens JS modal -->
            <button type="button" class="ynj-mosque-pill" id="mosque-selector" onclick="window.ynjOpenMosqueModal&&window.ynjOpenMosqueModal()">
                <span class="ynj-mosque-pill__gps" id="gps-btn" title="<?php esc_attr_e( 'Use GPS', 'yourjannah' ); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                </span>
                <span class="ynj-mosque-pill__name" id="mosque-name"><?php echo esc_html( $mosque_name ?: __( 'Select Mosque', 'yourjannah' ) ); ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6;flex-shrink:0;margin-right:8px;"><path d="M6 9l6 6 6-6"/></svg>
            </button>
        </div>
    </div>
</header>

<!-- Mosque selector modal (JS-driven) -->
<div class="ynj-mosque-modal" id="ynj-mosque-modal" style="display:none">
    <div class="ynj-mosque-modal__overlay"></div>
    <div class="ynj-mosque-modal__box">
        <button type="button" class="ynj-mosque-modal__close">&times;</button>
        <h3 class="ynj-mosque-modal__title">🕌 <?php esc_html_e( 'Find Your Mosque', 'yourjannah' ); ?></h3>
        <p class="ynj-mosque-modal__subtitle"><?php esc_html_e( 'Select a mosque near you or search by name.', 'yourjannah' ); ?></p>

        <div class="ynj-mosque-modal__search">
            <input type="text" id="ynj-mosque-search" placeholder="<?php esc_attr_e( 'Search by name, city, postcode...', 'yourjannah' ); ?>" autocomplete="off">
        </div>

        <button type="button" class="ynj-mosque-modal__gps" id="ynj-mosque-gps">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
            <span id="ynj-mosque-gps-text"><?php esc_html_e( 'Use my location', 'yourjannah' ); ?></span>
        </button>

        <div class="ynj-mosque-modal__list" id="ynj-mosque-list">
            <!-- Populated by JS -->
        </div>

        <a href="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" class="ynj-mosque-modal__browse"><?php esc_html_e( 'Browse All Mosques →', 'yourjannah' ); ?></a>
    </div>
</div>
<script>
window.ynjNearbyMosques = <?php echo json_encode( array_map( function( $m ) {
    return [
        'slug'     => $m->slug,
        'name'     => $m->name,
        'city'     => $m->city ?? '',
        'postcode' => $m->postcode ?? '',
        'distance' => isset( $m->distance ) ? round( (float) $m->distance, 1 ) : null,
    ];
}, $nearby_mosques ) ); ?>;

/* Mosque modal — inline for guaranteed reliability (no SW cache dependency) */
(function(){
    var modal = document.getElementById('ynj-mosque-modal');
    if (!modal) return;
    var overlay  = modal.querySelector('.ynj-mosque-modal__overlay');
    var closeBtn = modal.querySelector('.ynj-mosque-modal__close');
    var searchIn = document.getElementById('ynj-mosque-search');
    var gpsBtn   = document.getElementById('ynj-mosque-gps');
    var gpsTxt   = document.getElementById('ynj-mosque-gps-text');
    var listEl   = document.getElementById('ynj-mosque-list');
    var searchTimer = null;
    var gpsTriggered = false;
    var restUrl  = '<?php echo esc_url_raw( rest_url( 'ynj/v1/' ) ); ?>';

    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (searchIn) searchIn.value = '';
        var pre = window.ynjNearbyMosques || [];
        if (pre.length) { renderList(pre, true); }
        else { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Tap "Use my location" to find nearby mosques</div>'; }
        if (!gpsTriggered && !pre.length) triggerGps();
        setTimeout(function(){ if (searchIn) searchIn.focus(); }, 200);
    }
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    function selectMosque(slug, name) {
        localStorage.setItem('ynj_mosque_slug', slug);
        localStorage.setItem('ynj_mosque_name', name);
        localStorage.removeItem('ynj_cache_date');
        localStorage.removeItem('ynj_cached_prayers');
        localStorage.removeItem('ynj_cached_feed');
        window.location.href = '/mosque/' + slug;
    }
    function renderList(mosques, label) {
        if (!mosques || !mosques.length) { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found.</div>'; return; }
        var h = label ? '<p class="ynj-mosque-modal__label">\uD83D\uDCCD Nearby</p>' : '';
        mosques.forEach(function(m){
            var meta = [m.city, m.postcode].filter(Boolean).join(', ');
            if (m.distance != null) meta += (meta ? ' \u00B7 ' : '') + m.distance + 'km';
            h += '<button type="button" class="ynj-mosque-modal__item" data-s="'+(m.slug||'')+'" data-n="'+(m.name||'').replace(/"/g,'&quot;')+'">' +
                '<span class="ynj-mosque-modal__item-name">'+(m.name||'')+'</span>' +
                '<span class="ynj-mosque-modal__item-meta">'+meta+'</span></button>';
        });
        listEl.innerHTML = h;
        listEl.querySelectorAll('.ynj-mosque-modal__item').forEach(function(b){
            b.addEventListener('click', function(){ selectMosque(this.dataset.s, this.dataset.n); });
        });
    }
    function triggerGps() {
        if (!('geolocation' in navigator)) { if (gpsTxt) gpsTxt.textContent = 'GPS not available'; return; }
        gpsTriggered = true;
        if (gpsBtn) gpsBtn.classList.add('ynj-mosque-modal__gps--loading');
        if (gpsTxt) gpsTxt.textContent = 'Locating...';
        listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Finding nearby mosques...</div>';
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                if (gpsBtn) gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                if (gpsTxt) gpsTxt.textContent = 'Use my location';
                fetch(restUrl + 'mosques/nearest?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude + '&limit=5')
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.ok && d.mosques && d.mosques.length) {
                            renderList(d.mosques.map(function(m){ return { slug:m.slug, name:m.name, city:m.city||'', postcode:m.postcode||'', distance:m.distance?parseFloat(m.distance).toFixed(1):null }; }), true);
                        } else { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found nearby. Try searching.</div>'; }
                    })
                    .catch(function(){ listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Could not load mosques. Try searching.</div>'; });
            },
            function() {
                if (gpsBtn) gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                if (gpsTxt) gpsTxt.textContent = 'Location denied';
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Location denied. Search by name instead.</div>';
            },
            { timeout: 8000, maximumAge: 300000 }
        );
    }

    /* Global function — called by onclick on pill button */
    window.ynjOpenMosqueModal = openModal;

    /* Close handlers */
    if (overlay) overlay.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (gpsBtn) gpsBtn.addEventListener('click', triggerGps);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.style.display !== 'none') closeModal(); });

    /* Search */
    if (searchIn) {
        searchIn.addEventListener('input', function(){
            var q = this.value.trim();
            if (q.length < 2) { var pre = window.ynjNearbyMosques || []; if (pre.length) renderList(pre, true); else listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Type to search...</div>'; return; }
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function(){
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Searching...</div>';
                fetch(restUrl + 'mosques/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r){ return r.json(); })
                    .then(function(d){ renderList((d.mosques||[]).map(function(m){ return { slug:m.slug, name:m.name, city:m.city||'', postcode:m.postcode||'', distance:m.distance?parseFloat(m.distance).toFixed(1):null }; }), false); })
                    .catch(function(){ listEl.innerHTML = '<div class="ynj-mosque-modal__empty" style="color:#dc2626;">Search failed.</div>'; });
            }, 300);
        });
    }
})();
</script>
