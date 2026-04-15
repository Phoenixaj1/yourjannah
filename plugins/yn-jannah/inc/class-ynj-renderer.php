<?php
/**
 * YNJ_Renderer — Renders the congregation-facing PWA pages for YourJannah.
 *
 * Outputs complete standalone HTML pages (no WordPress theme dependency).
 * Mobile-first design, 500px max-width portrait, Jannah (heaven) cyan blue theme.
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_Renderer {

    /** Brand colours — Jannah (heaven) theme. */
    const COLOR_PRIMARY    = '#0a1628';
    const COLOR_ACCENT     = '#00ADEF';
    const COLOR_BG         = '#e8f4f8';
    const COLOR_SURFACE    = '#FFFFFF';
    const COLOR_TEXT       = '#0a1628';
    const COLOR_TEXT_MUTED = '#6b8fa3';

    /* ================================================================== */
    /*  PAGE: Home                                                        */
    /* ================================================================== */

    public static function render_home(): void {
        $vapid = class_exists( 'YNJ_Push' ) ? YNJ_Push::get_public_key() : '';

        self::page_head( 'YourJannah — Your Mosque Community', 'Prayer times, travel estimates, announcements and events from your local mosque.' );
        ?>

        <header class="ynj-header">
            <div class="ynj-header__inner">
                <div class="ynj-logo">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="14" fill="#287e61"/><path d="M14 4c-1.5 3-5 5-5 9a5 5 0 0010 0c0-4-3.5-6-5-9z" fill="#fff" opacity=".9"/></svg>
                    <span>YourJannah</span>
                </div>
                <div class="ynj-header__right">
                    <button class="ynj-gps-btn" id="gps-btn" type="button" title="Detect my location">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                    </button>
                    <button class="ynj-mosque-selector" id="mosque-selector" type="button">
                        <span id="mosque-name">Finding mosque&hellip;</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- Mosque selector dropdown (hidden by default) -->
        <div class="ynj-dropdown" id="mosque-dropdown" style="display:none;">
            <div class="ynj-dropdown__inner">
                <input class="ynj-dropdown__search" id="mosque-search" type="text" placeholder="Search mosques&hellip;" autocomplete="off">
                <div class="ynj-dropdown__list" id="mosque-list"></div>
            </div>
        </div>

        <main class="ynj-main">

            <!-- Section 1: Next Prayer Hero -->
            <section class="ynj-card ynj-card--hero" id="next-prayer-card">
                <p class="ynj-label" id="next-prayer-label">Next Prayer</p>
                <h2 class="ynj-hero-prayer" id="next-prayer-name">&nbsp;</h2>
                <p class="ynj-hero-time" id="next-prayer-time">&nbsp;</p>
                <div class="ynj-countdown" id="next-prayer-countdown">--:--:--</div>
                <div class="ynj-hero-travel" id="hero-travel" style="display:none;">
                    <div class="ynj-leave-by" id="leave-by">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span id="leave-by-text">Leave by --:--</span>
                    </div>
                    <span class="ynj-travel-dist" id="travel-dist"></span>
                </div>
                <a class="ynj-btn ynj-btn--navigate" id="navigate-btn" href="#" target="_blank" rel="noopener" style="display:none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                    Navigate to Masjid
                </a>
            </section>

            <!-- Section 2: View Full Timetable link -->
            <a class="ynj-timetable-link" id="timetable-link" href="#">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                View Full Timetable
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </a>

            <!-- Section 3: Feed Timeline -->
            <section class="ynj-card" id="feed-card">
                <h3 class="ynj-card__title">What&rsquo;s Happening</h3>
                <div class="ynj-feed" id="feed-list">
                    <p class="ynj-text-muted">Loading&hellip;</p>
                </div>
            </section>

            <!-- Section 4: Push Subscribe -->
            <section class="ynj-card ynj-card--subscribe" id="subscribe-card">
                <button class="ynj-btn ynj-btn--outline" id="subscribe-btn" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                    Get Prayer Reminders
                </button>
                <p class="ynj-subscribe-status" id="subscribe-status"></p>
            </section>

        </main>

        <?php self::render_bottom_nav( 'home' ); ?>

        <script>
        (function(){
            'use strict';

            const API = '/wp-json/ynj/v1';
            const VAPID = '<?php echo esc_js( $vapid ); ?>';

            let mosqueSlug = localStorage.getItem('ynj_mosque_slug');
            let mosqueData = null;
            let prayerTimes = null;
            let userLat = null, userLng = null;
            let travelMinutes = null;
            let nearbyMosques = [];

            /* ---- Init ---- */
            function init() {
                registerSW();
                setupMosqueSelector();
                setupGpsButton();

                // Try GPS automatically
                requestGps();
            }

            function requestGps() {
                if (!('geolocation' in navigator)) {
                    if (mosqueSlug) loadSavedMosque();
                    else showSearchPrompt();
                    return;
                }

                const gpsBtn = document.getElementById('gps-btn');
                gpsBtn.classList.add('ynj-gps-btn--loading');
                document.getElementById('mosque-name').textContent = 'Locating...';

                navigator.geolocation.getCurrentPosition(
                    pos => { gpsBtn.classList.remove('ynj-gps-btn--loading'); onGeo(pos); },
                    () => { gpsBtn.classList.remove('ynj-gps-btn--loading'); onGeoError(); },
                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
                );
            }

            function loadSavedMosque() {
                loadMosque(mosqueSlug);
                loadFeed(mosqueSlug);
                updateNavLinks(mosqueSlug);
                document.getElementById('timetable-link').href = `/mosque/${mosqueSlug}/prayers`;
            }

            function showSearchPrompt() {
                document.getElementById('mosque-name').textContent = 'Search mosque';
                // Open the dropdown so user can search
                document.getElementById('mosque-dropdown').style.display = '';
                document.getElementById('mosque-search').focus();
            }

            function setupGpsButton() {
                document.getElementById('gps-btn').addEventListener('click', () => {
                    requestGps();
                });
            }

            /* ---- GPS ---- */
            function onGeo(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;

                fetch(`${API}/mosques/nearest?lat=${userLat}&lng=${userLng}&limit=5`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok || !data.mosques || !data.mosques.length) {
                            fetchAladhan(userLat, userLng);
                            return;
                        }
                        nearbyMosques = data.mosques;
                        populateDropdown(nearbyMosques);

                        // Auto-select saved mosque or nearest
                        const saved = mosqueSlug;
                        const match = saved ? nearbyMosques.find(m => m.slug === saved) : null;
                        const chosen = match || nearbyMosques[0];
                        selectMosque(chosen.slug, chosen.name, chosen.latitude, chosen.longitude, chosen.distance);
                    })
                    .catch(() => {
                        if (mosqueSlug) loadMosque(mosqueSlug);
                    });
            }

            function onGeoError() {
                if (mosqueSlug) {
                    loadSavedMosque();
                } else {
                    showSearchPrompt();
                }
            }

            /* ---- Mosque Selection ---- */
            function selectMosque(slug, name, lat, lng, distKm) {
                mosqueSlug = slug;
                localStorage.setItem('ynj_mosque_slug', slug);
                document.getElementById('mosque-name').textContent = name || slug;
                updateNavLinks(slug);

                // Timetable link
                document.getElementById('timetable-link').href = `/mosque/${slug}/prayers`;

                // Travel & navigate
                if (userLat != null && lat && lng) {
                    const km = distKm || haversine(userLat, userLng, lat, lng);
                    travelMinutes = Math.max(1, Math.round(km * 12)); // ~5km/h walking
                    const distText = km < 1 ? `${Math.round(km*1000)}m` : `${km.toFixed(1)}km`;
                    document.getElementById('travel-dist').textContent = `${distText} · ~${travelMinutes} min walk`;
                    document.getElementById('hero-travel').style.display = '';
                    document.getElementById('navigate-btn').style.display = '';
                    document.getElementById('navigate-btn').href =
                        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=walking`;
                    updateLeaveBy();
                }

                // Fetch prayer times
                loadMosque(slug);

                // Fetch feed
                loadFeed(slug);
            }

            function loadMosque(slug) {
                fetch(`${API}/mosques/${slug}`)
                    .then(r => r.json())
                    .then(resp => {
                        const m = resp.mosque || resp;
                        mosqueData = m;
                        document.getElementById('mosque-name').textContent = m.name || slug;

                        if (m.prayer_times && !m.prayer_times.error) {
                            setPrayerTimes(m.prayer_times);
                        } else if (m.latitude && m.longitude) {
                            fetchAladhan(m.latitude, m.longitude);
                        } else if (userLat) {
                            fetchAladhan(userLat, userLng);
                        }

                        // If we didn't get travel from GPS, try from mosque coords
                        if (userLat && !travelMinutes && m.latitude && m.longitude) {
                            const km = haversine(userLat, userLng, m.latitude, m.longitude);
                            travelMinutes = Math.max(1, Math.round(km * 12));
                            const distText = km < 1 ? `${Math.round(km*1000)}m` : `${km.toFixed(1)}km`;
                            document.getElementById('travel-dist').textContent = `${distText} · ~${travelMinutes} min walk`;
                            document.getElementById('hero-travel').style.display = '';
                            document.getElementById('navigate-btn').style.display = '';
                            document.getElementById('navigate-btn').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=walking`;
                            updateLeaveBy();
                        }
                    })
                    .catch(() => {});
            }

            /* ---- Aladhan Fallback ---- */
            function fetchAladhan(lat, lng) {
                const ts = Math.floor(Date.now() / 1000);
                fetch(`https://api.aladhan.com/v1/timings/${ts}?latitude=${lat}&longitude=${lng}&method=2`)
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.data && d.data.timings) {
                            const t = d.data.timings;
                            const strip = s => (s||'').replace(/\s*\(.*\)/,'');
                            setPrayerTimes({
                                fajr: strip(t.Fajr), sunrise: strip(t.Sunrise), dhuhr: strip(t.Dhuhr),
                                asr: strip(t.Asr), maghrib: strip(t.Maghrib), isha: strip(t.Isha)
                            });
                        }
                    })
                    .catch(() => {});
            }

            /* ---- Prayer Times ---- */
            function setPrayerTimes(times) {
                prayerTimes = {};
                ['fajr','sunrise','dhuhr','asr','maghrib','isha'].forEach(p => {
                    if (times[p]) {
                        // Normalize to HH:MM
                        prayerTimes[p] = String(times[p]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                    }
                });
                updateCountdown();
                setInterval(updateCountdown, 1000);
            }

            function updateCountdown() {
                if (!prayerTimes) return;
                const now = new Date();
                const prayers = ['fajr','dhuhr','asr','maghrib','isha'];
                let next = null, nextName = null, nextTime = null;

                for (const p of prayers) {
                    if (!prayerTimes[p]) continue;
                    const [h,m] = prayerTimes[p].split(':').map(Number);
                    const t = new Date(now); t.setHours(h,m,0,0);
                    if (t > now) { next = t; nextName = p; nextTime = prayerTimes[p]; break; }
                }

                if (!next) {
                    document.getElementById('next-prayer-countdown').textContent = '--:--:--';
                    document.getElementById('next-prayer-name').textContent = 'All prayers completed';
                    document.getElementById('next-prayer-time').textContent = 'See you at Fajr tomorrow';
                    document.getElementById('next-prayer-label').textContent = '';
                    return;
                }

                const diff = Math.max(0, Math.floor((next - now) / 1000));
                const hh = String(Math.floor(diff / 3600)).padStart(2,'0');
                const mm = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
                const ss = String(diff % 60).padStart(2,'0');

                const label = nextName.charAt(0).toUpperCase() + nextName.slice(1);
                document.getElementById('next-prayer-countdown').textContent = `${hh}:${mm}:${ss}`;
                document.getElementById('next-prayer-name').textContent = label;
                document.getElementById('next-prayer-time').textContent = nextTime;
                document.getElementById('next-prayer-label').textContent = 'Next Prayer';
                updateLeaveBy();
            }

            function updateLeaveBy() {
                if (!prayerTimes || !travelMinutes) return;
                const now = new Date();
                const prayers = ['fajr','dhuhr','asr','maghrib','isha'];
                for (const p of prayers) {
                    if (!prayerTimes[p]) continue;
                    const [h,m] = prayerTimes[p].split(':').map(Number);
                    const t = new Date(now); t.setHours(h,m,0,0);
                    if (t > now) {
                        const leave = new Date(t.getTime() - travelMinutes * 60000);
                        const lh = String(leave.getHours()).padStart(2,'0');
                        const lm = String(leave.getMinutes()).padStart(2,'0');
                        document.getElementById('leave-by-text').textContent = `Leave by ${lh}:${lm}`;
                        return;
                    }
                }
            }

            /* ---- Feed ---- */
            function loadFeed(slug) {
                const feedEl = document.getElementById('feed-list');
                Promise.all([
                    fetch(`${API}/mosques/${slug}/announcements`).then(r => r.json()).catch(() => ({announcements:[]})),
                    fetch(`${API}/mosques/${slug}/events?upcoming=1`).then(r => r.json()).catch(() => ({events:[]}))
                ]).then(([aData, eData]) => {
                    const items = [];
                    (aData.announcements || []).forEach(a => {
                        items.push({
                            type: 'announcement',
                            title: a.title,
                            body: a.body,
                            date: a.published_at || '',
                            pinned: a.pinned
                        });
                    });
                    (eData.events || []).forEach(e => {
                        const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                        items.push({
                            type: 'event',
                            title: e.title,
                            body: e.description || '',
                            date: e.event_date || '',
                            time: time,
                            location: e.location || ''
                        });
                    });

                    // Pinned first, then by date
                    items.sort((a,b) => {
                        if (a.pinned && !b.pinned) return -1;
                        if (!a.pinned && b.pinned) return 1;
                        return (b.date||'').localeCompare(a.date||'');
                    });

                    if (!items.length) {
                        feedEl.innerHTML = '<p class="ynj-text-muted">No announcements or events yet.</p>';
                        return;
                    }

                    feedEl.innerHTML = items.map(item => {
                        const badge = item.type === 'event'
                            ? '<span class="ynj-badge ynj-badge--event">Event</span>'
                            : (item.pinned ? '<span class="ynj-badge ynj-badge--pinned">Pinned</span>' : '');
                        const meta = item.type === 'event'
                            ? `<span class="ynj-feed-meta">${item.date}${item.time ? ' · '+item.time : ''}${item.location ? ' · '+item.location : ''}</span>`
                            : `<span class="ynj-feed-meta">${timeAgo(item.date)}</span>`;
                        const snippet = item.body.length > 120 ? item.body.slice(0,120)+'...' : item.body;
                        return `<div class="ynj-feed-item">
                            <div class="ynj-feed-item__head">${badge}<h4>${item.title}</h4></div>
                            <p class="ynj-feed-item__body">${snippet}</p>
                            ${meta}
                        </div>`;
                    }).join('');
                });
            }

            function timeAgo(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr.replace(' ','T'));
                const diff = Math.floor((Date.now() - d.getTime()) / 1000);
                if (diff < 60) return 'Just now';
                if (diff < 3600) return Math.floor(diff/60) + 'm ago';
                if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
                if (diff < 604800) return Math.floor(diff/86400) + 'd ago';
                return dateStr.split(' ')[0];
            }

            /* ---- Mosque Selector ---- */
            function setupMosqueSelector() {
                const btn = document.getElementById('mosque-selector');
                const dd = document.getElementById('mosque-dropdown');
                const search = document.getElementById('mosque-search');

                btn.addEventListener('click', () => {
                    const visible = dd.style.display !== 'none';
                    dd.style.display = visible ? 'none' : '';
                    if (!visible) search.focus();
                });

                // Close on outside click
                document.addEventListener('click', e => {
                    if (!dd.contains(e.target) && !btn.contains(e.target)) {
                        dd.style.display = 'none';
                    }
                });

                // Search
                let debounce;
                search.addEventListener('input', () => {
                    clearTimeout(debounce);
                    const q = search.value.trim();
                    if (q.length < 2) {
                        populateDropdown(nearbyMosques);
                        return;
                    }
                    debounce = setTimeout(() => {
                        fetch(`${API}/mosques/search?q=${encodeURIComponent(q)}&limit=10`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.ok && data.mosques) populateDropdown(data.mosques);
                            })
                            .catch(() => {});
                    }, 300);
                });
            }

            function populateDropdown(mosques) {
                const list = document.getElementById('mosque-list');
                if (!mosques.length) {
                    list.innerHTML = '<p class="ynj-text-muted" style="padding:12px;">No mosques found.</p>';
                    return;
                }
                list.innerHTML = mosques.map(m => {
                    const dist = m.distance != null ? ` · ${m.distance < 1 ? Math.round(m.distance*1000)+'m' : m.distance.toFixed(1)+'km'}` : '';
                    const active = m.slug === mosqueSlug ? ' ynj-dropdown__item--active' : '';
                    return `<button class="ynj-dropdown__item${active}" data-slug="${m.slug}" data-name="${m.name}" data-lat="${m.latitude||''}" data-lng="${m.longitude||''}" data-dist="${m.distance||0}">
                        <strong>${m.name}</strong>
                        <span class="ynj-text-muted">${m.city || ''}${m.postcode ? ' '+m.postcode : ''}${dist}</span>
                    </button>`;
                }).join('');

                // Bind clicks
                list.querySelectorAll('.ynj-dropdown__item').forEach(btn => {
                    btn.addEventListener('click', () => {
                        selectMosque(
                            btn.dataset.slug,
                            btn.dataset.name,
                            parseFloat(btn.dataset.lat) || null,
                            parseFloat(btn.dataset.lng) || null,
                            parseFloat(btn.dataset.dist) || null
                        );
                        document.getElementById('mosque-dropdown').style.display = 'none';
                    });
                });
            }

            /* ---- Nav ---- */
            function updateNavLinks(slug) {
                document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                    el.href = el.dataset.navMosque.replace('{slug}', slug);
                });
            }

            /* ---- Helpers ---- */
            function haversine(lat1,lng1,lat2,lng2) {
                const R = 6371;
                const dLat = (lat2-lat1)*Math.PI/180;
                const dLng = (lng2-lng1)*Math.PI/180;
                const a = Math.sin(dLat/2)*Math.sin(dLat/2) +
                    Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*
                    Math.sin(dLng/2)*Math.sin(dLng/2);
                return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            }

            /* ---- Push Subscribe ---- */
            const subBtn = document.getElementById('subscribe-btn');
            const subStatus = document.getElementById('subscribe-status');
            subBtn.addEventListener('click', async () => {
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    subStatus.textContent = 'Not supported in this browser.';
                    return;
                }
                if (!VAPID) { subStatus.textContent = 'Not configured.'; return; }
                try {
                    subBtn.disabled = true;
                    subBtn.textContent = 'Requesting permission...';
                    const perm = await Notification.requestPermission();
                    if (perm !== 'granted') {
                        subStatus.textContent = 'Permission denied.';
                        subBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg> Get Prayer Reminders';
                        subBtn.disabled = false;
                        return;
                    }
                    const reg = await navigator.serviceWorker.ready;
                    const sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlB64(VAPID)
                    });
                    const json = sub.toJSON();
                    await fetch(`${API}/subscribe`, {
                        method: 'POST',
                        headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({
                            mosque_slug: mosqueSlug,
                            endpoint: json.endpoint,
                            p256dh: json.keys.p256dh,
                            auth: json.keys.auth
                        })
                    });
                    subBtn.textContent = 'Subscribed';
                    subStatus.textContent = 'You will get prayer reminders and announcements.';
                    subStatus.style.color = '#287e61';
                } catch(e) {
                    subStatus.textContent = 'Failed: ' + e.message;
                    subBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg> Get Prayer Reminders';
                    subBtn.disabled = false;
                }
            });

            function registerSW() {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('/sw.js', {scope:'/'}).catch(() => {});
                }
            }

            function urlB64(b64) {
                const p = '='.repeat((4 - b64.length % 4) % 4);
                const raw = atob((b64+p).replace(/-/g,'+').replace(/_/g,'/'));
                const arr = new Uint8Array(raw.length);
                for (let i=0;i<raw.length;i++) arr[i]=raw.charCodeAt(i);
                return arr;
            }

            init();
        })();
        </script>

        </body>
        </html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Mosque Profile                                              */
    /* ================================================================== */

    public static function render_mosque( string $slug ): void {
        self::page_head( 'Mosque — YourJannah', 'Your mosque community on YourJannah.' );
        ?>

        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>YourJannah</span></div>
            </div>
        </header>

        <main class="ynj-main" id="mosque-profile">
            <section class="ynj-card ynj-card--hero">
                <h1 class="ynj-mosque-name" id="mp-name">Loading&hellip;</h1>
                <p class="ynj-text-muted" id="mp-address" style="color:rgba(255,255,255,.7);"></p>
            </section>

            <section class="ynj-card" id="mp-prayer-card">
                <h3 class="ynj-card__title">Prayer Times</h3>
                <div class="ynj-prayer-grid" id="mp-prayer-grid"></div>
            </section>

            <section class="ynj-card" id="mp-announcements">
                <h3 class="ynj-card__title">Announcements</h3>
                <div id="mp-feed" class="ynj-feed"><p class="ynj-text-muted">No announcements yet.</p></div>
            </section>

            <section class="ynj-card" id="mp-events">
                <h3 class="ynj-card__title">Upcoming Events</h3>
                <div id="mp-events-list" class="ynj-feed"><p class="ynj-text-muted">No upcoming events.</p></div>
            </section>
        </main>

        <?php self::render_bottom_nav( 'home', $slug ); ?>

        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });

            fetch(`/wp-json/ynj/v1/mosques/${slug}`)
                .then(r => r.json())
                .then(resp => {
                    const data = resp.mosque || resp;
                    if (!data) return;
                    document.getElementById('mp-name').textContent = data.name || slug;
                    document.getElementById('mp-address').textContent = data.address || '';
                    if (data.prayer_times && !data.prayer_times.error) {
                        const grid = document.getElementById('mp-prayer-grid');
                        grid.innerHTML = '';
                        ['fajr','sunrise','dhuhr','asr','maghrib','isha'].forEach(p => {
                            if (!data.prayer_times[p]) return;
                            const t = String(data.prayer_times[p]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                            grid.innerHTML += `<div class="ynj-prayer-row"><span class="ynj-prayer-row__name">${p.charAt(0).toUpperCase()+p.slice(1)}</span><span class="ynj-prayer-row__time">${t}</span></div>`;
                        });
                    }
                })
                .catch(() => { document.getElementById('mp-name').textContent = 'Mosque not found'; });

            fetch(`/wp-json/ynj/v1/mosques/${slug}/announcements`)
                .then(r => r.json())
                .then(data => {
                    if (data.announcements && data.announcements.length) {
                        document.getElementById('mp-feed').innerHTML = data.announcements.map(a =>
                            `<div class="ynj-feed-item"><h4>${a.title}</h4><p>${a.body}</p><time>${a.published_at||''}</time></div>`
                        ).join('');
                    }
                }).catch(() => {});

            fetch(`/wp-json/ynj/v1/mosques/${slug}/events?upcoming=1`)
                .then(r => r.json())
                .then(data => {
                    if (data.events && data.events.length) {
                        document.getElementById('mp-events-list').innerHTML = data.events.map(e => {
                            const t = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                            return `<div class="ynj-feed-item"><h4>${e.title}</h4><p>${e.event_date||''} · ${t}</p></div>`;
                        }).join('');
                    }
                }).catch(() => {});
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Prayers                                                     */
    /* ================================================================== */

    public static function render_prayers( string $slug ): void {
        self::page_head( 'Prayer Times — YourJannah', 'Full prayer timetable for your mosque.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Prayer Times</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <section class="ynj-card">
                <h2 class="ynj-card__title" id="pt-mosque-name">Loading&hellip;</h2>
                <div class="ynj-prayer-grid ynj-prayer-grid--full" id="pt-grid"></div>
            </section>
        </main>
        <?php self::render_bottom_nav( 'home', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });
            fetch(`/wp-json/ynj/v1/mosques/${slug}`)
                .then(r => r.json())
                .then(resp => {
                    const data = resp.mosque || resp;
                    document.getElementById('pt-mosque-name').textContent = data.name || slug;
                    if (data.prayer_times && !data.prayer_times.error) {
                        const grid = document.getElementById('pt-grid');
                        grid.innerHTML = '';
                        const labels = {fajr:'Fajr',sunrise:'Sunrise',dhuhr:'Dhuhr',asr:'Asr',maghrib:'Maghrib',isha:'Isha'};
                        Object.entries(labels).forEach(([k,v]) => {
                            if (!data.prayer_times[k]) return;
                            const t = String(data.prayer_times[k]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                            const jk = data.prayer_times[k+'_jamat'] || '';
                            const jt = jk ? String(jk).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'') : '';
                            grid.innerHTML += `<div class="ynj-prayer-row"><span class="ynj-prayer-row__name">${v}</span><span class="ynj-prayer-row__time">${t}${jt ? ' <small style="color:#6b8fa3">Jam: '+jt+'</small>' : ''}</span></div>`;
                        });
                    } else if (data.latitude && data.longitude) {
                        const ts = Math.floor(Date.now()/1000);
                        fetch(`https://api.aladhan.com/v1/timings/${ts}?latitude=${data.latitude}&longitude=${data.longitude}&method=2`)
                            .then(r=>r.json()).then(d=>{
                                if(!d||!d.data||!d.data.timings)return;
                                const grid=document.getElementById('pt-grid');grid.innerHTML='';
                                const labels={Fajr:'Fajr',Sunrise:'Sunrise',Dhuhr:'Dhuhr',Asr:'Asr',Maghrib:'Maghrib',Isha:'Isha'};
                                Object.entries(labels).forEach(([ak,v])=>{
                                    const t=(d.data.timings[ak]||'').replace(/\s*\(.*\)/,'');
                                    grid.innerHTML+=`<div class="ynj-prayer-row"><span class="ynj-prayer-row__name">${v}</span><span class="ynj-prayer-row__time">${t}</span></div>`;
                                });
                            }).catch(()=>{});
                    }
                });
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Services                                                    */
    /* ================================================================== */

    public static function render_services( string $slug ): void {
        self::page_head( 'Services — YourJannah', 'Masjid services and local professionals.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Services</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <!-- Masjid Services (free/official) -->
            <section class="ynj-card" id="masjid-services">
                <h2 class="ynj-card__title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px;"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                    Masjid Services
                </h2>
                <div id="masjid-svc-list" class="ynj-svc-grid"><p class="ynj-text-muted">Loading&hellip;</p></div>
            </section>

            <!-- Professional Services (paid community) -->
            <section class="ynj-card" id="pro-services">
                <h2 class="ynj-card__title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Local Professionals
                </h2>
                <p class="ynj-text-muted" style="margin-bottom:12px;">Community members offering their services</p>
                <div id="pro-svc-list" class="ynj-feed"><p class="ynj-text-muted">Loading&hellip;</p></div>
            </section>
        </main>
        <?php self::render_bottom_nav( 'services', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });

            fetch(`/wp-json/ynj/v1/mosques/${slug}/directory`)
                .then(r => r.json())
                .then(data => {
                    // Masjid services (free — monthly_fee_pence <= 0 or service_type matches known masjid services)
                    const all = data.services || [];
                    const masjid = all.filter(s => !s.hourly_rate_pence || s.hourly_rate_pence === 0);
                    const pro = all.filter(s => s.hourly_rate_pence && s.hourly_rate_pence > 0);

                    // If no clear split, use all as masjid if < 5, otherwise split at fee boundary
                    const masjidList = document.getElementById('masjid-svc-list');
                    const proList = document.getElementById('pro-svc-list');

                    if (all.length === 0) {
                        masjidList.innerHTML = '<p class="ynj-text-muted">No masjid services listed yet.</p>';
                        proList.innerHTML = '<p class="ynj-text-muted">No professional services listed yet.</p>';
                        return;
                    }

                    // For now, show ALL services in masjid section (the seed data doesn't distinguish yet)
                    // The masjid admin will mark their own services
                    const svcIcons = {
                        'Imam / Scholar': '🕌', 'Quran Teacher': '📖', 'Arabic Tutor': '📚',
                        'Counselling': '🤝', 'Nikah': '💍', 'Funeral': '🕊️', 'Janazah': '🕊️'
                    };

                    masjidList.innerHTML = all.map(s => {
                        const icon = svcIcons[s.service_type] || '✦';
                        return `<div class="ynj-svc-card">
                            <div class="ynj-svc-card__icon">${icon}</div>
                            <div class="ynj-svc-card__body">
                                <h4>${s.provider_name}</h4>
                                <span class="ynj-badge">${s.service_type}</span>
                                <p class="ynj-text-muted">${s.description || ''}</p>
                                ${s.phone ? `<a href="tel:${s.phone}" class="ynj-svc-card__phone">${s.phone}</a>` : ''}
                                ${s.area_covered ? `<span class="ynj-text-muted" style="font-size:11px;">${s.area_covered}</span>` : ''}
                            </div>
                        </div>`;
                    }).join('');

                    // Hide pro section if no separate pro services
                    if (pro.length === 0) {
                        document.getElementById('pro-services').style.display = 'none';
                    }
                })
                .catch(() => {
                    document.getElementById('masjid-svc-list').innerHTML = '<p class="ynj-text-muted">Could not load services.</p>';
                });
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Sponsors (Business Leaderboard)                             */
    /* ================================================================== */

    public static function render_sponsors( string $slug ): void {
        self::page_head( 'Sponsors — YourJannah', 'Businesses supporting your masjid.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Sponsors</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <section class="ynj-card">
                <h2 class="ynj-card__title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Masjid Sponsors
                </h2>
                <p class="ynj-text-muted" style="margin-bottom:16px;">Businesses supporting your masjid</p>
                <div id="sponsor-list"><p class="ynj-text-muted">Loading&hellip;</p></div>
            </section>

            <div style="text-align:center;padding:20px;">
                <p class="ynj-text-muted" style="margin-bottom:12px;">Want to support your local masjid?</p>
                <a href="#" class="ynj-btn" onclick="alert('Contact the mosque to become a sponsor.');return false;">Become a Sponsor</a>
            </div>
        </main>
        <?php self::render_bottom_nav( 'sponsors', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });

            fetch(`/wp-json/ynj/v1/mosques/${slug}/directory`)
                .then(r => r.json())
                .then(data => {
                    const biz = data.businesses || [];
                    const list = document.getElementById('sponsor-list');

                    if (!biz.length) {
                        list.innerHTML = '<p class="ynj-text-muted">No sponsors yet. Be the first to support your masjid!</p>';
                        return;
                    }

                    list.innerHTML = biz.map((b, i) => {
                        const rank = i + 1;
                        let medalClass = '';
                        if (rank === 1) medalClass = ' ynj-sponsor--gold';
                        else if (rank === 2) medalClass = ' ynj-sponsor--silver';
                        else if (rank === 3) medalClass = ' ynj-sponsor--bronze';

                        return `<div class="ynj-sponsor${medalClass}">
                            <div class="ynj-sponsor__rank">${rank <= 3 ? ['🥇','🥈','🥉'][rank-1] : '#'+rank}</div>
                            <div class="ynj-sponsor__body">
                                <h4>${b.business_name}</h4>
                                <span class="ynj-badge">${b.category}</span>
                                ${b.description ? `<p class="ynj-text-muted" style="margin-top:4px;">${b.description.length > 100 ? b.description.slice(0,100)+'...' : b.description}</p>` : ''}
                                <div class="ynj-sponsor__actions">
                                    ${b.phone ? `<a href="tel:${b.phone}">${b.phone}</a>` : ''}
                                    ${b.website ? `<a href="${b.website}" target="_blank" rel="noopener">Website</a>` : ''}
                                </div>
                            </div>
                        </div>`;
                    }).join('');
                })
                .catch(() => {
                    document.getElementById('sponsor-list').innerHTML = '<p class="ynj-text-muted">Could not load sponsors.</p>';
                });
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Events                                                      */
    /* ================================================================== */

    public static function render_events( string $slug ): void {
        self::page_head( 'Events — YourJannah', 'Upcoming events at your mosque.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Events</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <section class="ynj-card" id="events-list">
                <h2 class="ynj-card__title" id="ev-mosque-name">Loading&hellip;</h2>
                <div id="ev-feed" class="ynj-feed"><p class="ynj-text-muted">Loading events&hellip;</p></div>
            </section>
        </main>
        <?php self::render_bottom_nav( 'more', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });
            fetch(`/wp-json/ynj/v1/mosques/${slug}`).then(r=>r.json()).then(resp=>{
                const m = resp.mosque||resp;
                document.getElementById('ev-mosque-name').textContent = m.name||slug;
            }).catch(()=>{});
            fetch(`/wp-json/ynj/v1/mosques/${slug}/events?upcoming=1`).then(r=>r.json()).then(data=>{
                const feed = document.getElementById('ev-feed');
                if (data.events && data.events.length) {
                    feed.innerHTML = data.events.map(e => {
                        const t = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                        return `<div class="ynj-feed-item">
                            <h4>${e.title}</h4>
                            <p class="ynj-text-muted">${e.event_date||''} · ${t}${e.location ? ' · '+e.location : ''}</p>
                            ${e.description ? `<p>${e.description}</p>` : ''}
                        </div>`;
                    }).join('');
                } else { feed.innerHTML = '<p class="ynj-text-muted">No upcoming events.</p>'; }
            }).catch(()=>{ document.getElementById('ev-feed').innerHTML = '<p class="ynj-text-muted">Could not load events.</p>'; });
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Donate                                                      */
    /* ================================================================== */

    public static function render_donate( string $slug ): void {
        self::page_head( 'Donate — YourJannah', '100% of your donation reaches your masjid.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Donate</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <section class="ynj-card ynj-card--hero" style="text-align:center;">
                <h2 id="dn-mosque-name" style="margin-bottom:8px;">Your Masjid</h2>
                <p style="opacity:.8;margin-bottom:20px;">100% of your donation reaches your masjid. Zero platform fees.</p>
                <div class="ynj-donate-badge">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#287e61" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                    <span>Verified &middot; Secure &middot; Direct</span>
                </div>
            </section>
            <section class="ynj-card" id="dn-embed-card">
                <div id="dn-iframe-wrap" style="min-height:400px;">
                    <p class="ynj-text-muted" style="text-align:center;padding:40px 0;">Loading donation page&hellip;</p>
                </div>
            </section>
        </main>
        <?php self::render_bottom_nav( 'donate', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });
            fetch(`/wp-json/ynj/v1/mosques/${slug}`).then(r=>r.json()).then(resp=>{
                const data = resp.mosque||resp;
                document.getElementById('dn-mosque-name').textContent = data.name||'Your Masjid';
                const dfmSlug = data.dfm_slug||slug;
                const wrap = document.getElementById('dn-iframe-wrap');
                if (data.dfm_slug) {
                    const iframe = document.createElement('iframe');
                    iframe.src = `https://donationformasjid.com/${dfmSlug}?embed=1`;
                    iframe.style.cssText = 'width:100%;min-height:500px;border:none;border-radius:12px;';
                    iframe.setAttribute('loading','lazy');
                    iframe.setAttribute('title','Donate to '+(data.name||'mosque'));
                    wrap.innerHTML = '';
                    wrap.appendChild(iframe);
                } else {
                    wrap.innerHTML = `<a href="https://donationformasjid.com/${dfmSlug}" target="_blank" rel="noopener" class="ynj-btn" style="display:block;text-align:center;margin:40px auto;">Donate on DonationForMasjid</a>`;
                }
            }).catch(()=>{
                document.getElementById('dn-iframe-wrap').innerHTML = '<p class="ynj-text-muted" style="text-align:center;">Could not load donation page.</p>';
            });
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: More                                                        */
    /* ================================================================== */

    public static function render_directory( string $slug ): void {
        self::page_head( 'More — YourJannah', 'Room bookings, events, and more.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>More</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <section class="ynj-card">
                <h2 class="ynj-card__title">Quick Links</h2>
                <div class="ynj-more-grid">
                    <a href="/mosque/<?php echo esc_attr( $slug ); ?>/events" class="ynj-more-item">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>All Events</span>
                    </a>
                    <a href="/mosque/<?php echo esc_attr( $slug ); ?>/prayers" class="ynj-more-item">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span>Full Timetable</span>
                    </a>
                    <a href="/mosque/<?php echo esc_attr( $slug ); ?>" class="ynj-more-item">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
                        <span>Mosque Profile</span>
                    </a>
                    <a href="#" class="ynj-more-item" onclick="alert('Room bookings coming soon.');return false;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        <span>Room Bookings</span>
                    </a>
                </div>
            </section>
        </main>
        <?php self::render_bottom_nav( 'more', $slug ); ?>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  404 Page                                                          */
    /* ================================================================== */

    public static function render_404(): void {
        self::page_head( 'Not Found — YourJannah', 'Page not found.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <div class="ynj-logo">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="14" fill="#287e61"/><path d="M14 4c-1.5 3-5 5-5 9a5 5 0 0010 0c0-4-3.5-6-5-9z" fill="#fff" opacity=".9"/></svg>
                    <span>YourJannah</span>
                </div>
            </div>
        </header>
        <main class="ynj-main" style="text-align:center;padding:60px 20px;">
            <h1 style="font-size:48px;margin-bottom:12px;">404</h1>
            <p class="ynj-text-muted" style="margin-bottom:24px;">This page could not be found.</p>
            <a href="/" class="ynj-btn">Go Home</a>
        </main>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  SHARED: Page Head (HTML + CSS)                                    */
    /* ================================================================== */

    public static function page_head( string $title, string $desc = '' ): void {
        ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="<?php echo self::COLOR_ACCENT; ?>">
<?php if ( $desc ) : ?>
<meta name="description" content="<?php echo esc_attr( $desc ); ?>">
<?php endif; ?>
<title><?php echo esc_html( $title ); ?></title>
<link rel="manifest" href="/manifest.json">
<link rel="icon" type="image/png" sizes="192x192" href="/wp-content/plugins/yn-jannah/assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="/wp-content/plugins/yn-jannah/assets/icons/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{-webkit-text-size-adjust:100%;scroll-behavior:smooth;}
body{
    font-family:'Inter',system-ui,-apple-system,sans-serif;
    font-size:15px;line-height:1.55;
    color:<?php echo self::COLOR_TEXT; ?>;
    background:linear-gradient(180deg,#e8f4f8 0%,#d4eef6 30%,#c5e8f4 60%,#e0f2f8 100%);
    background-attachment:fixed;
    min-height:100vh;min-height:100dvh;
    padding-bottom:72px;
    -webkit-font-smoothing:antialiased;
}
a{color:<?php echo self::COLOR_ACCENT; ?>;text-decoration:none;}
img,svg{display:block;max-width:100%;}

/* Layout */
.ynj-main{max-width:500px;margin:0 auto;padding:12px 16px 24px;}

/* Header */
.ynj-header{
    background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 50%,#00ADEF 100%);
    color:#fff;position:sticky;top:0;z-index:100;
    padding:0 16px;padding-top:env(safe-area-inset-top,0);
}
.ynj-header__inner{max-width:500px;margin:0 auto;display:flex;align-items:center;gap:12px;min-height:56px;}
.ynj-logo{display:flex;align-items:center;gap:8px;font-weight:700;font-size:17px;white-space:nowrap;}
.ynj-back{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;transition:background .15s;}
.ynj-back:active{background:rgba(255,255,255,.15);}

/* Header right group */
.ynj-header__right{margin-left:auto;display:flex;align-items:center;gap:8px;}

/* GPS Button */
.ynj-gps-btn{
    display:flex;align-items:center;justify-content:center;
    width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.25);
    background:rgba(255,255,255,.15);color:#fff;cursor:pointer;transition:all .2s;
    flex-shrink:0;
}
.ynj-gps-btn:active{background:rgba(255,255,255,.3);transform:scale(.95);}
.ynj-gps-btn.ynj-gps-btn--loading{animation:gpsPulse 1.2s ease-in-out infinite;}
.ynj-gps-btn svg{display:block;}
@keyframes gpsPulse{0%,100%{opacity:1}50%{opacity:.4}}

/* Mosque Selector */
.ynj-mosque-selector{
    display:flex;align-items:center;gap:6px;
    background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
    border-radius:10px;padding:6px 12px;color:#fff;font-size:13px;font-weight:500;
    cursor:pointer;max-width:55%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.ynj-mosque-selector svg{flex-shrink:0;opacity:.7;}
.ynj-dropdown{
    position:fixed;top:56px;left:0;right:0;z-index:150;
    background:rgba(255,255,255,.97);backdrop-filter:blur(16px);
    -webkit-backdrop-filter:blur(16px);
    border-bottom:1px solid #e0e0e0;
    box-shadow:0 8px 32px rgba(0,0,0,.12);
}
.ynj-dropdown__inner{max-width:500px;margin:0 auto;padding:12px 16px;}
.ynj-dropdown__search{
    width:100%;padding:10px 14px;border:1px solid #e0e0e0;border-radius:10px;
    font-size:14px;outline:none;margin-bottom:8px;
}
.ynj-dropdown__search:focus{border-color:<?php echo self::COLOR_ACCENT; ?>;}
.ynj-dropdown__list{max-height:300px;overflow-y:auto;}
.ynj-dropdown__item{
    display:flex;flex-direction:column;gap:2px;width:100%;
    padding:10px 12px;border:none;background:none;text-align:left;
    border-bottom:1px solid #f0f0f0;cursor:pointer;font-size:14px;
}
.ynj-dropdown__item:last-child{border-bottom:none;}
.ynj-dropdown__item:active{background:#f0f8ff;}
.ynj-dropdown__item--active{background:#e8f7ff;border-left:3px solid <?php echo self::COLOR_ACCENT; ?>;}

/* Cards */
.ynj-card{
    background:rgba(255,255,255,.85);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    border-radius:18px;padding:20px;margin-bottom:14px;
    box-shadow:0 2px 12px rgba(0,173,239,.08);border:1px solid rgba(255,255,255,.6);
}
.ynj-card--hero{
    background:linear-gradient(180deg,#0a1628 0%,#1a3a5c 40%,#00ADEF 80%,#7dd3fc 100%);
    color:#fff;text-align:center;padding:28px 20px 24px;border-radius:18px;
    position:relative;overflow:hidden;
}
.ynj-card--hero::before{
    content:'';position:absolute;top:40%;left:-20%;width:140%;height:60%;
    background:radial-gradient(ellipse,rgba(255,255,255,.15) 0%,transparent 70%);
    animation:clouds 8s ease-in-out infinite alternate;
}
@keyframes clouds{0%{transform:translateX(-5%) translateY(0)}100%{transform:translateX(5%) translateY(-8px)}}
.ynj-card__title{font-size:15px;font-weight:600;margin-bottom:14px;color:<?php echo self::COLOR_TEXT; ?>;}

/* Hero card elements */
.ynj-label{font-size:11px;text-transform:uppercase;letter-spacing:1.5px;opacity:.7;}
.ynj-hero-prayer{font-size:24px;font-weight:700;margin:6px 0 2px;position:relative;z-index:1;}
.ynj-hero-time{font-size:16px;font-weight:500;opacity:.85;position:relative;z-index:1;}
.ynj-countdown{font-size:38px;font-weight:700;letter-spacing:2px;margin:8px 0;font-variant-numeric:tabular-nums;position:relative;z-index:1;}
.ynj-hero-travel{
    display:flex;align-items:center;justify-content:center;gap:16px;
    margin:10px 0 16px;font-size:13px;opacity:.85;position:relative;z-index:1;
}
.ynj-leave-by{display:flex;align-items:center;gap:4px;font-weight:600;}
.ynj-leave-by svg{display:inline;}
.ynj-travel-dist{opacity:.7;}

/* Navigate button */
.ynj-btn--navigate{
    display:inline-flex;align-items:center;gap:8px;
    background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);
    color:#fff;border-radius:12px;padding:12px 24px;font-size:14px;font-weight:700;
    cursor:pointer;transition:all .2s;position:relative;z-index:1;text-decoration:none;
    box-shadow:none;
}
.ynj-btn--navigate:active{background:rgba(255,255,255,.3);transform:scale(.97);}
.ynj-btn--navigate svg{display:inline;}

/* Timetable link */
.ynj-timetable-link{
    display:flex;align-items:center;justify-content:center;gap:8px;
    background:rgba(255,255,255,.7);border:1px solid rgba(0,173,239,.2);
    border-radius:12px;padding:12px;margin-bottom:14px;
    font-size:13px;font-weight:600;color:<?php echo self::COLOR_ACCENT; ?>;
    text-decoration:none;
}
.ynj-timetable-link svg{display:inline;}
.ynj-timetable-link:active{background:rgba(0,173,239,.08);}

/* Prayer Grid */
.ynj-prayer-grid{display:flex;flex-direction:column;gap:1px;}
.ynj-prayer-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0ec;}
.ynj-prayer-row:last-child{border-bottom:none;}
.ynj-prayer-row__name{font-weight:500;font-size:14px;}
.ynj-prayer-row__time{font-weight:600;font-size:14px;font-variant-numeric:tabular-nums;color:<?php echo self::COLOR_PRIMARY; ?>;}
.ynj-prayer-row--placeholder .ynj-prayer-row__time{color:#ccc;}

/* Badges */
.ynj-badge{
    display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
    padding:2px 8px;border-radius:6px;background:#e8f4f8;color:<?php echo self::COLOR_ACCENT; ?>;
}
.ynj-badge--event{background:#fef3c7;color:#92400e;}
.ynj-badge--pinned{background:#dcfce7;color:#166534;}

/* Feed */
.ynj-feed{display:flex;flex-direction:column;gap:2px;}
.ynj-feed-item{padding:14px 0;border-bottom:1px solid #f0f0ec;}
.ynj-feed-item:last-child{border-bottom:none;}
.ynj-feed-item__head{display:flex;align-items:center;gap:8px;margin-bottom:4px;}
.ynj-feed-item__head h4{font-size:14px;font-weight:600;}
.ynj-feed-item__body{font-size:13px;color:#555;margin-bottom:6px;line-height:1.4;}
.ynj-feed-meta{font-size:11px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}
.ynj-feed-item time{font-size:12px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}

/* Service Cards */
.ynj-svc-grid{display:flex;flex-direction:column;gap:12px;}
.ynj-svc-card{
    display:flex;gap:14px;padding:14px;background:#f8fbfc;border-radius:14px;
    border:1px solid #eef4f7;
}
.ynj-svc-card__icon{font-size:28px;flex-shrink:0;width:44px;text-align:center;line-height:44px;}
.ynj-svc-card__body{flex:1;min-width:0;}
.ynj-svc-card__body h4{font-size:14px;font-weight:600;margin-bottom:4px;}
.ynj-svc-card__body .ynj-badge{margin-bottom:6px;}
.ynj-svc-card__body p{margin-bottom:6px;}
.ynj-svc-card__phone{font-weight:600;font-size:13px;color:<?php echo self::COLOR_ACCENT; ?>;}

/* Sponsor Leaderboard */
.ynj-sponsor{
    display:flex;gap:14px;padding:16px 0;border-bottom:1px solid #f0f0ec;align-items:flex-start;
}
.ynj-sponsor:last-child{border-bottom:none;}
.ynj-sponsor__rank{
    flex-shrink:0;width:40px;text-align:center;font-size:18px;font-weight:700;
    color:<?php echo self::COLOR_TEXT_MUTED; ?>;padding-top:2px;
}
.ynj-sponsor--gold .ynj-sponsor__rank{font-size:24px;}
.ynj-sponsor--silver .ynj-sponsor__rank{font-size:22px;}
.ynj-sponsor--bronze .ynj-sponsor__rank{font-size:20px;}
.ynj-sponsor__body{flex:1;min-width:0;}
.ynj-sponsor__body h4{font-size:15px;font-weight:600;margin-bottom:4px;}
.ynj-sponsor__actions{display:flex;gap:16px;margin-top:8px;font-size:13px;font-weight:500;}
.ynj-sponsor__actions a{color:<?php echo self::COLOR_ACCENT; ?>;}

/* More page grid */
.ynj-more-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.ynj-more-item{
    display:flex;flex-direction:column;align-items:center;gap:8px;
    padding:20px 12px;background:#f8fbfc;border-radius:14px;border:1px solid #eef4f7;
    text-decoration:none;color:<?php echo self::COLOR_TEXT; ?>;font-size:13px;font-weight:500;
    text-align:center;
}
.ynj-more-item:active{background:#e8f4f8;}
.ynj-more-item svg{display:block;}

/* Subscribe */
.ynj-card--subscribe{text-align:center;padding:16px 20px;}
.ynj-subscribe-status{font-size:12px;margin-top:8px;min-height:16px;}

/* Buttons */
.ynj-btn{
    display:inline-flex;align-items:center;gap:8px;
    background:linear-gradient(135deg,#00ADEF,#0090d0);color:#fff;border:none;border-radius:12px;
    padding:14px 28px;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;
    -webkit-tap-highlight-color:transparent;box-shadow:0 4px 14px rgba(0,173,239,.3);text-decoration:none;
}
.ynj-btn:active{transform:scale(.97);}
.ynj-btn:disabled{opacity:.5;cursor:default;}
.ynj-btn--outline{
    background:transparent;color:<?php echo self::COLOR_ACCENT; ?>;
    border:1.5px solid <?php echo self::COLOR_ACCENT; ?>;box-shadow:none;
    padding:10px 20px;font-size:13px;
}
.ynj-btn--outline:active{background:rgba(0,173,239,.06);}

/* Donate */
.ynj-donate-badge{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:rgba(255,255,255,.85);margin-top:12px;}

/* Text helpers */
.ynj-text-muted{font-size:13px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}
.ynj-mosque-name{font-size:22px;font-weight:700;}

/* Bottom Nav */
.ynj-nav{
    position:fixed;bottom:0;left:0;right:0;
    background:<?php echo self::COLOR_SURFACE; ?>;border-top:1px solid #e5e5e0;
    z-index:200;padding-bottom:env(safe-area-inset-bottom,0);
}
.ynj-nav__inner{max-width:500px;margin:0 auto;display:flex;justify-content:space-around;padding:6px 0 4px;}
.ynj-nav__item{
    display:flex;flex-direction:column;align-items:center;gap:2px;
    padding:4px 8px;font-size:10px;font-weight:500;
    color:<?php echo self::COLOR_TEXT_MUTED; ?>;text-decoration:none;
    transition:color .15s;-webkit-tap-highlight-color:transparent;
}
.ynj-nav__item--active{color:<?php echo self::COLOR_ACCENT; ?>;}
.ynj-nav__item svg{width:22px;height:22px;}
</style>
</head>
<body>
        <?php
    }

    /* ================================================================== */
    /*  SHARED: Bottom Navigation                                         */
    /* ================================================================== */

    public static function render_bottom_nav( string $active = 'home', string $slug = '' ): void {
        $tabs = [
            'home' => [
                'label' => 'Home',
                'href'  => '/',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>',
            ],
            'services' => [
                'label' => 'Services',
                'href'  => '/mosque/{slug}/services',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg>',
                'mosque' => true,
            ],
            'sponsors' => [
                'label' => 'Sponsors',
                'href'  => '/mosque/{slug}/sponsors',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
                'mosque' => true,
            ],
            'more' => [
                'label' => 'More',
                'href'  => '/mosque/{slug}/directory',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
                'mosque' => true,
            ],
        ];

        echo '<nav class="ynj-nav"><div class="ynj-nav__inner">';

        foreach ( $tabs as $key => $tab ) {
            $is_active = ( $key === $active );
            $class     = 'ynj-nav__item' . ( $is_active ? ' ynj-nav__item--active' : '' );
            $href      = $tab['href'];
            $attrs     = '';

            if ( ! empty( $tab['mosque'] ) ) {
                $attrs = ' data-nav-mosque="' . esc_attr( $href ) . '"';
                if ( $slug ) {
                    $href = str_replace( '{slug}', esc_attr( $slug ), $href );
                } else {
                    $href = '#';
                }
            }

            printf(
                '<a class="%s" href="%s"%s>%s<span>%s</span></a>',
                esc_attr( $class ),
                esc_attr( $href ),
                $attrs,
                $tab['icon'],
                esc_html( $tab['label'] )
            );
        }

        echo '</div></nav>';
    }
}
