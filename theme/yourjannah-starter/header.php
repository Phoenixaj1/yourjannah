<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>

<!-- Force unregister old service workers that may cache stale pages -->
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function(regs) {
        regs.forEach(function(r) { r.unregister(); });
    });
    caches.keys().then(function(names) {
        names.forEach(function(name) { caches.delete(name); });
    });
}
</script>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// ════════════════════════════════════════════════════════
// HUD is rendered by the yn-jannah-hud plugin via wp_body_open hook.
// Header nav bar below is the only thing this file handles.
// ════════════════════════════════════════════════════════

$mosque_slug = function_exists( 'ynj_mosque_slug' ) ? ynj_mosque_slug() : '';
$mosque      = function_exists( 'ynj_get_mosque' ) ? ynj_get_mosque( $mosque_slug ) : null;
$mosque_name = $mosque ? $mosque->name : '';
?>

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
            <?php if ( is_user_logged_in() ) : ?>
            <!-- Notification bell -->
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
                    <div style="padding:10px 16px;border-top:1px solid #eee;text-align:center;">
                        <a href="<?php echo esc_url( home_url( '/profile#interests' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#00ADEF;text-decoration:none;">&#x2699;&#xFE0F; Set Interests &amp; Radius</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Mosque selector pill -->
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

<?php if ( is_user_logged_in() ) : ?>
<script>
/* Notification bell — inline for guaranteed reliability */
(function(){
    var nonce = (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) ? wpApiSettings.nonce : '<?php echo wp_create_nonce( "wp_rest" ); ?>';
    var apiBase = '<?php echo esc_url_raw( rest_url( "ynj/v1/auth/" ) ); ?>';
    var badge = document.getElementById('ynj-notif-badge');
    var panel = document.getElementById('ynj-notif-panel');
    var toggle = document.getElementById('ynj-notif-toggle');
    var list = document.getElementById('ynj-notif-list');
    var markAllBtn = document.getElementById('ynj-notif-mark-all');
    var isOpen = false;

    function apiFetch(path, opts) {
        opts = opts || {};
        var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } };
        if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
        return fetch(apiBase + path, init).then(function(r){ return r.json(); });
    }

    function timeAgo(dateStr) {
        var diff = Math.floor((Date.now() - new Date(dateStr.replace(/ /, 'T') + 'Z').getTime()) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return new Date(dateStr.replace(/ /, 'T') + 'Z').toLocaleDateString();
    }

    function updateBadge(count) {
        if (!badge) return;
        if (count > 0) { badge.textContent = count > 99 ? '99+' : count; badge.style.display = 'flex'; }
        else { badge.style.display = 'none'; }
    }

    function loadNotifs() {
        apiFetch('notifications').then(function(d) {
            if (!d.ok) return;
            updateBadge(d.unread_count || 0);
            if (!d.notifications || !d.notifications.length) { list.innerHTML = '<div class="ynj-notif-empty">No notifications yet</div>'; return; }
            var h = '';
            d.notifications.forEach(function(n) {
                var unread = !n.read_at;
                h += '<a href="' + (n.url || '#') + '" class="ynj-notif-item' + (unread ? ' ynj-notif-item--unread' : '') + '" data-id="' + n.id + '">'
                   + '<div class="ynj-notif-item__dot' + (unread ? '' : ' ynj-notif-item__dot--read') + '"></div>'
                   + '<div class="ynj-notif-item__body">'
                   + (n.mosque_name ? '<div class="ynj-notif-item__mosque">' + n.mosque_name + '</div>' : '')
                   + '<div class="ynj-notif-item__title">' + (n.title || '') + '</div>'
                   + '<div class="ynj-notif-item__text">' + (n.body || '') + '</div>'
                   + '<div class="ynj-notif-item__time">' + timeAgo(n.created_at) + '</div>'
                   + '</div></a>';
            });
            list.innerHTML = h;
            list.querySelectorAll('.ynj-notif-item').forEach(function(el) {
                el.addEventListener('click', function() {
                    var id = this.dataset.id;
                    if (id) apiFetch('notifications/' + id + '/read', { method: 'POST' });
                });
            });
        });
    }

    if (toggle) toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen = !isOpen;
        panel.classList.toggle('ynj-notif-panel--open', isOpen);
        if (isOpen) loadNotifs();
    });
    document.addEventListener('click', function(e) {
        if (isOpen && !panel.contains(e.target) && e.target !== toggle) { isOpen = false; panel.classList.remove('ynj-notif-panel--open'); }
    });
    if (markAllBtn) markAllBtn.addEventListener('click', function() {
        apiFetch('notifications/read-all', { method: 'POST' }).then(function() { updateBadge(0); loadNotifs(); });
    });

    // Initial load + poll every 60s
    loadNotifs();
    setInterval(loadNotifs, 60000);
})();
</script>
<?php endif; ?>
