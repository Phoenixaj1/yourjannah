/**
 * YourJannah Theme — Main JavaScript
 *
 * Uses ynjData (localized via wp_localize_script) for:
 * - ynjData.restUrl   — REST API base URL
 * - ynjData.nonce     — WP REST nonce
 * - ynjData.siteUrl   — Site home URL
 * - ynjData.vapidKey  — VAPID public key for push
 * - ynjData.isLoggedIn — Whether user is logged in
 */

(function() {
    'use strict';

    // ================================================================
    // GPS DETECTION
    // ================================================================

    const gpsBtn = document.getElementById('gps-btn');
    if (gpsBtn) {
        gpsBtn.addEventListener('click', function() {
            if (!('geolocation' in navigator)) return;
            gpsBtn.classList.add('ynj-gps-btn--loading');

            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    gpsBtn.classList.remove('ynj-gps-btn--loading');
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;

                    // Find nearest mosque
                    fetch(ynjData.restUrl + 'mosques/nearest?lat=' + lat + '&lng=' + lng + '&limit=1')
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok && data.mosques && data.mosques[0]) {
                                const m = data.mosques[0];
                                localStorage.setItem('ynj_mosque_slug', m.slug);
                                localStorage.setItem('ynj_mosque_name', m.name);

                                // Update mosque name display
                                const nameEl = document.getElementById('mosque-name-text');
                                if (nameEl) nameEl.textContent = m.name;

                                // Update nav links
                                document.querySelectorAll('[data-mosque-link]').forEach(el => {
                                    el.href = el.dataset.mosqueLink.replace('{slug}', m.slug);
                                });
                            }
                        })
                        .catch(() => {});
                },
                function() {
                    gpsBtn.classList.remove('ynj-gps-btn--loading');
                },
                { timeout: 8000, maximumAge: 300000 }
            );
        });
    }

    // ================================================================
    // MOSQUE SLUG WIRING
    // ================================================================

    const slug = localStorage.getItem('ynj_mosque_slug') || '';
    if (slug) {
        // Update mosque name from cache
        const cachedName = localStorage.getItem('ynj_mosque_name');
        const nameEl = document.getElementById('mosque-name-text');
        if (nameEl && cachedName && nameEl.textContent === 'Select Mosque') {
            nameEl.textContent = cachedName;
        }

        // Wire up nav links that reference mosque slug
        document.querySelectorAll('[data-mosque-link]').forEach(el => {
            el.href = el.dataset.mosqueLink.replace('{slug}', slug);
        });
    }

    // ================================================================
    // SERVICE WORKER
    // ================================================================

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    }

})();
