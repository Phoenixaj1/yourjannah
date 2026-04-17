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

            <?php if ( is_user_logged_in() ) : ?>
            <!-- Notification bell (logged-in users only) -->
            <style>
            .ynj-notif-bell{position:relative;display:inline-flex;align-items:center;margin-right:8px}
            .ynj-notif-bell__btn{background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;color:#333;position:relative;display:flex;align-items:center;justify-content:center;transition:background .2s}
            .ynj-notif-bell__btn:hover{background:rgba(0,0,0,.06)}
            .ynj-notif-badge{position:absolute;top:0;right:0;background:#e53e3e;color:#fff;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;border:2px solid #fff}
            .ynj-notif-panel{display:none;position:absolute;right:0;top:calc(100% + 6px);width:360px;max-height:420px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.15);z-index:9999;overflow:hidden}
            .ynj-notif-panel--open{display:block}
            .ynj-notif-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px 10px;border-bottom:1px solid #eee}
            .ynj-notif-header strong{font-size:16px;color:#1a1a1a}
            .ynj-notif-header a{font-size:12px;color:#0ea5e9;cursor:pointer;text-decoration:none}
            .ynj-notif-header a:hover{text-decoration:underline}
            .ynj-notif-list{overflow-y:auto;max-height:360px}
            .ynj-notif-item{display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid #f3f3f3;cursor:pointer;text-decoration:none;color:inherit;transition:background .15s}
            .ynj-notif-item:hover{background:#f7f7f7}
            .ynj-notif-item--unread{background:#f0f7ff}
            .ynj-notif-item--unread:hover{background:#e6f0fa}
            .ynj-notif-item__dot{flex-shrink:0;width:8px;height:8px;border-radius:50%;background:#0ea5e9;margin-top:6px}
            .ynj-notif-item__dot--read{background:transparent}
            .ynj-notif-item__body{flex:1;min-width:0}
            .ynj-notif-item__mosque{font-size:11px;color:#888;margin-bottom:2px}
            .ynj-notif-item__title{font-size:13px;font-weight:600;color:#1a1a1a;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .ynj-notif-item__text{font-size:12px;color:#555;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
            .ynj-notif-item__time{font-size:11px;color:#aaa;margin-top:3px}
            .ynj-notif-empty{padding:40px 16px;text-align:center;color:#999;font-size:13px}
            @media(max-width:600px){.ynj-notif-panel{width:280px;right:-40px}}
            </style>
            <div class="ynj-notif-bell" id="ynj-notif-bell">
                <button type="button" class="ynj-notif-bell__btn" id="ynj-notif-toggle" aria-label="Notifications">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span class="ynj-notif-badge" id="ynj-notif-badge" style="display:none">0</span>
                </button>
                <div class="ynj-notif-panel" id="ynj-notif-panel">
                    <div class="ynj-notif-header">
                        <strong>Notifications</strong>
                        <a id="ynj-notif-mark-all">Mark all read</a>
                    </div>
                    <div class="ynj-notif-list" id="ynj-notif-list">
                        <div class="ynj-notif-empty">Loading...</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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

<?php if ( is_user_logged_in() ) : ?>
<script>
/* Notification bell — inline for guaranteed reliability */
(function(){
    var nonce = '<?php echo wp_create_nonce( "wp_rest" ); ?>';
    var apiBase = '<?php echo esc_url_raw( rest_url( "ynj/v1/auth/" ) ); ?>';
    var badge = document.getElementById('ynj-notif-badge');
    var panel = document.getElementById('ynj-notif-panel');
    var toggle = document.getElementById('ynj-notif-toggle');
    var list = document.getElementById('ynj-notif-list');
    var markAllBtn = document.getElementById('ynj-notif-mark-all');
    var isOpen = false;
    var pollTimer = null;

    function apiFetch(path, opts) {
        opts = opts || {};
        var url = apiBase + path;
        var init = {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': nonce }
        };
        if (opts.body) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        return fetch(url, init).then(function(r){ return r.json(); });
    }

    function timeAgo(dateStr) {
        var now = Date.now();
        var then = new Date(dateStr.replace(/ /, 'T') + 'Z').getTime();
        var diff = Math.floor((now - then) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return new Date(then).toLocaleDateString();
    }

    function updateBadge(count) {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function fetchCount() {
        apiFetch('notifications/count').then(function(d) {
            if (d.ok) updateBadge(d.count);
        }).catch(function(){});
    }

    function renderNotifications(notifications) {
        if (!notifications || !notifications.length) {
            list.innerHTML = '<div class="ynj-notif-empty">No notifications yet.</div>';
            return;
        }
        var html = '';
        var shown = notifications.slice(0, 20);
        shown.forEach(function(n) {
            var unread = !n.is_read;
            html += '<a class="ynj-notif-item' + (unread ? ' ynj-notif-item--unread' : '') + '" '
                + 'href="' + (n.url || '#') + '" '
                + 'data-nid="' + n.id + '" '
                + 'onclick="window._ynjMarkRead(' + n.id + ')">'
                + '<span class="ynj-notif-item__dot' + (unread ? '' : ' ynj-notif-item__dot--read') + '"></span>'
                + '<span class="ynj-notif-item__body">'
                + (n.mosque_name ? '<span class="ynj-notif-item__mosque">' + n.mosque_name + '</span>' : '')
                + '<span class="ynj-notif-item__title">' + (n.title || '') + '</span>'
                + '<span class="ynj-notif-item__text">' + (n.body || '') + '</span>'
                + '<span class="ynj-notif-item__time">' + timeAgo(n.created_at) + '</span>'
                + '</span></a>';
        });
        list.innerHTML = html;
    }

    function fetchNotifications() {
        list.innerHTML = '<div class="ynj-notif-empty">Loading...</div>';
        apiFetch('notifications').then(function(d) {
            if (d.ok) {
                renderNotifications(d.notifications);
                updateBadge(d.unread_count);
            }
        }).catch(function() {
            list.innerHTML = '<div class="ynj-notif-empty" style="color:#dc2626;">Could not load notifications.</div>';
        });
    }

    function togglePanel() {
        isOpen = !isOpen;
        if (isOpen) {
            panel.classList.add('ynj-notif-panel--open');
            fetchNotifications();
        } else {
            panel.classList.remove('ynj-notif-panel--open');
        }
    }

    function markAllRead() {
        apiFetch('notifications/read', { method: 'POST', body: {} }).then(function(d) {
            if (d.ok) {
                updateBadge(0);
                // Update UI — remove unread styling
                var items = list.querySelectorAll('.ynj-notif-item--unread');
                items.forEach(function(el) {
                    el.classList.remove('ynj-notif-item--unread');
                    var dot = el.querySelector('.ynj-notif-item__dot');
                    if (dot) dot.classList.add('ynj-notif-item__dot--read');
                });
            }
        });
    }

    window._ynjMarkRead = function(nid) {
        apiFetch('notifications/read', { method: 'POST', body: { notification_id: nid } }).then(function(d) {
            if (d.ok) fetchCount();
        }).catch(function(){});
    };

    // Bind events
    if (toggle) toggle.addEventListener('click', function(e) { e.stopPropagation(); togglePanel(); });
    if (markAllBtn) markAllBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); markAllRead(); });

    // Close panel on outside click
    document.addEventListener('click', function(e) {
        if (isOpen && panel && !panel.contains(e.target) && toggle && !toggle.contains(e.target)) {
            isOpen = false;
            panel.classList.remove('ynj-notif-panel--open');
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isOpen) {
            isOpen = false;
            panel.classList.remove('ynj-notif-panel--open');
        }
    });

    // Initial count fetch + polling every 60s
    fetchCount();
    pollTimer = setInterval(fetchCount, 60000);
})();
</script>
<?php endif; ?>
