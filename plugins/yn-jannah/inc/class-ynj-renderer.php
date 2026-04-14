<?php
/**
 * YNJ_Renderer — Renders the congregation-facing PWA pages for YourJannah.
 *
 * Outputs complete standalone HTML pages (no WordPress theme dependency).
 * Mobile-first design, 500px max-width portrait, dark green header, cream body.
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_Renderer {

    /** Brand colours — Jannah (heaven) theme: light cyan blues, ethereal, clouds. */
    const COLOR_PRIMARY    = '#0a1628';
    const COLOR_ACCENT     = '#00ADEF';
    const COLOR_BG         = '#e8f4f8';
    const COLOR_SURFACE    = '#FFFFFF';
    const COLOR_TEXT       = '#0a1628';
    const COLOR_TEXT_MUTED = '#6b8fa3';

    /* ================================================================== */
    /*  PAGE: Home                                                        */
    /* ================================================================== */

    /**
     * Render the home page — GPS detection, nearest mosque, prayer times,
     * push subscribe.
     */
    public static function render_home(): void {
        $vapid = class_exists( 'YNJ_Push' ) ? YNJ_Push::get_public_key() : '';

        self::page_head( 'YourJannah — Your Mosque Community', 'Prayer times, announcements, events and more from your local mosque.' );
        ?>

        <header class="ynj-header">
            <div class="ynj-header__inner">
                <div class="ynj-logo">
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="14" fill="#287e61"/><path d="M14 4c-1.5 3-5 5-5 9a5 5 0 0010 0c0-4-3.5-6-5-9z" fill="#fff" opacity=".9"/></svg>
                    <span>YourJannah</span>
                </div>
                <div id="mosque-name" class="ynj-header__mosque">Finding your mosque&hellip;</div>
            </div>
        </header>

        <main class="ynj-main">

            <!-- Next Prayer Countdown -->
            <section class="ynj-card ynj-card--hero" id="next-prayer-card">
                <p class="ynj-label" id="next-prayer-label">Next Prayer</p>
                <h2 class="ynj-countdown" id="next-prayer-countdown">--:--:--</h2>
                <p class="ynj-prayer-name" id="next-prayer-name">&nbsp;</p>
            </section>

            <!-- Today's Prayer Times -->
            <section class="ynj-card" id="prayer-times-card">
                <h3 class="ynj-card__title">Today&rsquo;s Prayer Times</h3>
                <div class="ynj-prayer-grid" id="prayer-grid">
                    <div class="ynj-prayer-row ynj-prayer-row--placeholder" data-prayer="fajr">
                        <span class="ynj-prayer-row__name">Fajr</span>
                        <span class="ynj-prayer-row__time">--:--</span>
                    </div>
                    <div class="ynj-prayer-row ynj-prayer-row--placeholder" data-prayer="sunrise">
                        <span class="ynj-prayer-row__name">Sunrise</span>
                        <span class="ynj-prayer-row__time">--:--</span>
                    </div>
                    <div class="ynj-prayer-row ynj-prayer-row--placeholder" data-prayer="dhuhr">
                        <span class="ynj-prayer-row__name">Dhuhr</span>
                        <span class="ynj-prayer-row__time">--:--</span>
                    </div>
                    <div class="ynj-prayer-row ynj-prayer-row--placeholder" data-prayer="asr">
                        <span class="ynj-prayer-row__name">Asr</span>
                        <span class="ynj-prayer-row__time">--:--</span>
                    </div>
                    <div class="ynj-prayer-row ynj-prayer-row--placeholder" data-prayer="maghrib">
                        <span class="ynj-prayer-row__name">Maghrib</span>
                        <span class="ynj-prayer-row__time">--:--</span>
                    </div>
                    <div class="ynj-prayer-row ynj-prayer-row--placeholder" data-prayer="isha">
                        <span class="ynj-prayer-row__name">Isha</span>
                        <span class="ynj-prayer-row__time">--:--</span>
                    </div>
                </div>
            </section>

            <!-- Jumu'ah -->
            <section class="ynj-card" id="jumuah-card" style="display:none;">
                <h3 class="ynj-card__title">Jumu&rsquo;ah</h3>
                <p id="jumuah-time" class="ynj-jumuah-time"></p>
            </section>

            <!-- Travel Time -->
            <section class="ynj-card ynj-card--travel" id="travel-card" style="display:none;">
                <div class="ynj-travel">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"/><path d="M12 2C7.6 2 4 5.4 4 9.5 4 14.3 12 22 12 22s8-7.7 8-12.5C20 5.4 16.4 2 12 2z"/></svg>
                    <span id="travel-text">Calculating&hellip;</span>
                </div>
            </section>

            <!-- Subscribe -->
            <section class="ynj-card ynj-card--subscribe" id="subscribe-card">
                <h3 class="ynj-card__title">Stay Connected</h3>
                <p class="ynj-text-muted">Get prayer time reminders and mosque announcements.</p>
                <button class="ynj-btn" id="subscribe-btn" type="button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                    Enable Notifications
                </button>
                <p class="ynj-subscribe-status" id="subscribe-status"></p>
            </section>

        </main>

        <?php self::render_bottom_nav( 'home' ); ?>

        <script>
        (function(){
            'use strict';

            const API_BASE = '/wp-json/ynj/v1';
            const VAPID_KEY = '<?php echo esc_js( $vapid ); ?>';

            let mosqueSlug = null;
            let prayerTimes = null;

            /* ---- GPS Detection ---- */
            function init() {
                if ('geolocation' in navigator) {
                    navigator.geolocation.getCurrentPosition(onGeoSuccess, onGeoError, {
                        enableHighAccuracy: false,
                        timeout: 8000,
                        maximumAge: 300000
                    });
                } else {
                    document.getElementById('mosque-name').textContent = 'Location not available';
                }
                registerSW();
            }

            function onGeoSuccess(pos) {
                const {latitude, longitude} = pos.coords;
                fetch(`${API_BASE}/mosques/nearest?lat=${latitude}&lng=${longitude}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.ok && data.mosques && data.mosques.length) {
                            const m = data.mosques[0];
                            mosqueSlug = m.slug;
                            document.getElementById('mosque-name').textContent = m.name || 'Your Mosque';
                            updateNavLinks(mosqueSlug);
                            if (m.prayer_times && m.prayer_times.fajr) {
                                setPrayerTimes(m.prayer_times);
                            } else {
                                // Fallback: fetch from Aladhan client-side
                                fetchAladhanClient(m.latitude || latitude, m.longitude || longitude);
                            }
                        } else if (data && data.slug) {
                            mosqueSlug = data.slug;
                            document.getElementById('mosque-name').textContent = data.name || 'Your Mosque';
                            updateNavLinks(mosqueSlug);
                            if (data.prayer_times && data.prayer_times.fajr) {
                                setPrayerTimes(data.prayer_times);
                            } else {
                                fetchAladhanClient(latitude, longitude);
                            }
                        } else {
                            // No mosque found — still show prayer times for location
                            fetchAladhanClient(latitude, longitude);
                        }
                    })
                    .catch(() => {
                        document.getElementById('mosque-name').textContent = 'Could not find nearby mosque';
                    });
            }

            function onGeoError() {
                document.getElementById('mosque-name').textContent = 'Enable location for your mosque';
            }

            /* ---- Client-side Aladhan fallback ---- */
            function fetchAladhanClient(lat, lng) {
                const ts = Math.floor(Date.now() / 1000);
                fetch(`https://api.aladhan.com/v1/timings/${ts}?latitude=${lat}&longitude=${lng}&method=2`)
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.data && d.data.timings) {
                            const t = d.data.timings;
                            setPrayerTimes({
                                fajr: t.Fajr, sunrise: t.Sunrise, dhuhr: t.Dhuhr,
                                asr: t.Asr, maghrib: t.Maghrib, isha: t.Isha
                            });
                        }
                    })
                    .catch(() => {});
            }

            /* ---- Prayer Times ---- */
            function setPrayerTimes(times) {
                prayerTimes = times;
                const prayers = ['fajr','sunrise','dhuhr','asr','maghrib','isha'];
                prayers.forEach(p => {
                    const row = document.querySelector(`[data-prayer="${p}"]`);
                    if (row && times[p]) {
                        row.querySelector('.ynj-prayer-row__time').textContent = times[p];
                        row.classList.remove('ynj-prayer-row--placeholder');
                    }
                });
                updateCountdown();
                setInterval(updateCountdown, 1000);
            }

            function updateCountdown() {
                if (!prayerTimes) return;
                const now = new Date();
                const prayers = ['fajr','dhuhr','asr','maghrib','isha'];
                let next = null;
                let nextName = null;

                for (const p of prayers) {
                    if (!prayerTimes[p]) continue;
                    const [h, m] = prayerTimes[p].split(':').map(Number);
                    const t = new Date(now);
                    t.setHours(h, m, 0, 0);
                    if (t > now) {
                        next = t;
                        nextName = p.charAt(0).toUpperCase() + p.slice(1);
                        break;
                    }
                }

                if (!next) {
                    // All prayers passed — show tomorrow's Fajr.
                    document.getElementById('next-prayer-countdown').textContent = '--:--:--';
                    document.getElementById('next-prayer-name').textContent = 'All prayers completed today';
                    document.getElementById('next-prayer-label').textContent = '';
                    return;
                }

                const diff = Math.max(0, Math.floor((next - now) / 1000));
                const hh = String(Math.floor(diff / 3600)).padStart(2, '0');
                const mm = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                const ss = String(diff % 60).padStart(2, '0');

                document.getElementById('next-prayer-countdown').textContent = `${hh}:${mm}:${ss}`;
                document.getElementById('next-prayer-name').textContent = nextName;
                document.getElementById('next-prayer-label').textContent = 'Next Prayer';
            }

            /* ---- Jumu'ah ---- */
            function showJumuah(time) {
                const card = document.getElementById('jumuah-card');
                card.style.display = '';
                document.getElementById('jumuah-time').textContent = time;
            }

            /* ---- Travel ---- */
            function showTravel(km) {
                const card = document.getElementById('travel-card');
                card.style.display = '';
                const mins = Math.round(km * 3); // rough walk estimate.
                let text = '';
                if (km < 1) {
                    text = `${Math.round(km * 1000)}m away · ~${mins} min walk`;
                } else {
                    text = `${km.toFixed(1)} km away · ~${mins} min walk`;
                }
                document.getElementById('travel-text').textContent = text;
            }

            /* ---- Nav links ---- */
            function updateNavLinks(slug) {
                document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                    el.href = el.dataset.navMosque.replace('{slug}', slug);
                });
            }

            /* ---- Push Subscribe ---- */
            const subBtn = document.getElementById('subscribe-btn');
            const subStatus = document.getElementById('subscribe-status');

            subBtn.addEventListener('click', async () => {
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    subStatus.textContent = 'Push notifications are not supported in this browser.';
                    return;
                }
                if (!VAPID_KEY) {
                    subStatus.textContent = 'Notification service not configured.';
                    return;
                }

                try {
                    subBtn.disabled = true;
                    subBtn.textContent = 'Requesting permission...';

                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        subStatus.textContent = 'Notification permission denied.';
                        subBtn.textContent = 'Enable Notifications';
                        subBtn.disabled = false;
                        return;
                    }

                    const reg = await navigator.serviceWorker.ready;
                    const sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_KEY)
                    });

                    const json = sub.toJSON();
                    await fetch(`${API_BASE}/subscribe`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            mosque_slug: mosqueSlug,
                            endpoint: json.endpoint,
                            p256dh: json.keys.p256dh,
                            auth: json.keys.auth
                        })
                    });

                    subBtn.textContent = 'Subscribed';
                    subStatus.textContent = 'You will receive prayer reminders and announcements.';
                    subStatus.style.color = '#287e61';

                } catch (err) {
                    subStatus.textContent = 'Failed to subscribe: ' + err.message;
                    subBtn.textContent = 'Enable Notifications';
                    subBtn.disabled = false;
                }
            });

            /* ---- Service Worker ---- */
            function registerSW() {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('/sw.js', {scope: '/'})
                        .catch(err => console.warn('SW registration failed:', err));
                }
            }

            /* ---- Helpers ---- */
            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const raw = atob(base64);
                const arr = new Uint8Array(raw.length);
                for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
                return arr;
            }

            /* ---- Boot ---- */
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
                <p class="ynj-text-muted" id="mp-address"></p>
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

        <?php self::render_bottom_nav( 'home' ); ?>

        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            fetch(`/wp-json/ynj/v1/mosque/${slug}`)
                .then(r => r.json())
                .then(data => {
                    if (!data) return;
                    document.getElementById('mp-name').textContent = data.name || slug;
                    document.getElementById('mp-address').textContent = data.address || '';

                    if (data.prayer_times) {
                        const grid = document.getElementById('mp-prayer-grid');
                        grid.innerHTML = '';
                        ['fajr','sunrise','dhuhr','asr','maghrib','isha'].forEach(p => {
                            if (!data.prayer_times[p]) return;
                            grid.innerHTML += `<div class="ynj-prayer-row"><span class="ynj-prayer-row__name">${p.charAt(0).toUpperCase()+p.slice(1)}</span><span class="ynj-prayer-row__time">${data.prayer_times[p]}</span></div>`;
                        });
                    }

                    if (data.announcements && data.announcements.length) {
                        const feed = document.getElementById('mp-feed');
                        feed.innerHTML = data.announcements.map(a =>
                            `<div class="ynj-feed-item"><h4>${a.title}</h4><p>${a.body}</p><time>${a.date}</time></div>`
                        ).join('');
                    }

                    if (data.events && data.events.length) {
                        const el = document.getElementById('mp-events-list');
                        el.innerHTML = data.events.map(e =>
                            `<div class="ynj-feed-item"><h4>${e.title}</h4><p>${e.date} · ${e.time}</p></div>`
                        ).join('');
                    }

                    // Update nav links.
                    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                        el.href = el.dataset.navMosque.replace('{slug}', slug);
                    });
                })
                .catch(err => {
                    document.getElementById('mp-name').textContent = 'Mosque not found';
                });
        })();
        </script>

        </body>
        </html>
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
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>" class="ynj-back" aria-label="Back">
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

            <section class="ynj-card" id="pt-monthly" style="display:none;">
                <h3 class="ynj-card__title">Monthly Timetable</h3>
                <div id="pt-monthly-grid"></div>
            </section>
        </main>

        <?php self::render_bottom_nav( 'prayers' ); ?>

        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            fetch(`/wp-json/ynj/v1/mosque/${slug}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('pt-mosque-name').textContent = data.name || slug;
                    if (data.prayer_times) {
                        const grid = document.getElementById('pt-grid');
                        grid.innerHTML = '';
                        const labels = {fajr:'Fajr',sunrise:'Sunrise',dhuhr:'Dhuhr',asr:'Asr',maghrib:'Maghrib',isha:'Isha'};
                        Object.entries(labels).forEach(([k,v]) => {
                            if (!data.prayer_times[k]) return;
                            const jamat = data.prayer_times[k+'_jamat'] || '';
                            grid.innerHTML += `<div class="ynj-prayer-row"><span class="ynj-prayer-row__name">${v}</span><span class="ynj-prayer-row__time">${data.prayer_times[k]}${jamat ? ' <small>Jam: '+jamat+'</small>' : ''}</span></div>`;
                        });
                    }
                    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                        el.href = el.dataset.navMosque.replace('{slug}', slug);
                    });
                });
        })();
        </script>

        </body>
        </html>
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
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>" class="ynj-back" aria-label="Back">
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

        <?php self::render_bottom_nav( 'events' ); ?>

        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            fetch(`/wp-json/ynj/v1/mosque/${slug}/events`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('ev-mosque-name').textContent = data.mosque_name || slug;
                    const feed = document.getElementById('ev-feed');
                    if (data.events && data.events.length) {
                        feed.innerHTML = data.events.map(e =>
                            `<div class="ynj-feed-item">
                                <h4>${e.title}</h4>
                                <p class="ynj-text-muted">${e.date} · ${e.time}</p>
                                ${e.description ? `<p>${e.description}</p>` : ''}
                            </div>`
                        ).join('');
                    } else {
                        feed.innerHTML = '<p class="ynj-text-muted">No upcoming events.</p>';
                    }
                    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                        el.href = el.dataset.navMosque.replace('{slug}', slug);
                    });
                })
                .catch(() => {
                    document.getElementById('ev-feed').innerHTML = '<p class="ynj-text-muted">Could not load events.</p>';
                });
        })();
        </script>

        </body>
        </html>
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
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Donate</span></div>
            </div>
        </header>

        <main class="ynj-main">
            <section class="ynj-card ynj-card--hero" style="text-align:center;">
                <h2 id="dn-mosque-name" style="margin-bottom:8px;">Your Masjid</h2>
                <p class="ynj-text-muted" style="margin-bottom:20px;">100% of your donation reaches your masjid. Zero platform fees.</p>
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

        <?php self::render_bottom_nav( 'donate' ); ?>

        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            fetch(`/wp-json/ynj/v1/mosque/${slug}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('dn-mosque-name').textContent = data.name || 'Your Masjid';
                    const dfmSlug = data.dfm_slug || slug;
                    const wrap = document.getElementById('dn-iframe-wrap');

                    if (data.dfm_slug) {
                        // Embed DonationForMasjid page.
                        const iframe = document.createElement('iframe');
                        iframe.src = `https://donationformasjid.com/${dfmSlug}?embed=1`;
                        iframe.style.cssText = 'width:100%;min-height:500px;border:none;border-radius:12px;';
                        iframe.setAttribute('loading', 'lazy');
                        iframe.setAttribute('title', 'Donate to ' + (data.name || 'mosque'));
                        wrap.innerHTML = '';
                        wrap.appendChild(iframe);
                    } else {
                        // Fallback link.
                        wrap.innerHTML = `<a href="https://donationformasjid.com/${dfmSlug}" target="_blank" rel="noopener" class="ynj-btn" style="display:block;text-align:center;margin:40px auto;">Donate on DonationForMasjid</a>`;
                    }

                    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                        el.href = el.dataset.navMosque.replace('{slug}', slug);
                    });
                })
                .catch(() => {
                    document.getElementById('dn-iframe-wrap').innerHTML = '<p class="ynj-text-muted" style="text-align:center;">Could not load donation page.</p>';
                });
        })();
        </script>

        </body>
        </html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Directory                                                   */
    /* ================================================================== */

    public static function render_directory( string $slug ): void {
        self::page_head( 'Directory — YourJannah', 'Local Muslim businesses and services near your mosque.' );
        ?>

        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Directory</span></div>
            </div>
        </header>

        <main class="ynj-main">
            <section class="ynj-card">
                <h2 class="ynj-card__title" id="dir-title">Local Directory</h2>
                <p class="ynj-text-muted" style="margin-bottom:16px;">Muslim businesses and services near your mosque.</p>
                <div id="dir-list" class="ynj-feed"><p class="ynj-text-muted">Loading&hellip;</p></div>
            </section>
        </main>

        <?php self::render_bottom_nav( 'more' ); ?>

        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            fetch(`/wp-json/ynj/v1/mosque/${slug}/directory`)
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('dir-list');
                    if (data.businesses && data.businesses.length) {
                        list.innerHTML = data.businesses.map(b =>
                            `<div class="ynj-feed-item ynj-dir-item">
                                <h4>${b.name}</h4>
                                <p class="ynj-text-muted">${b.category}</p>
                                ${b.phone ? `<p><a href="tel:${b.phone}">${b.phone}</a></p>` : ''}
                                ${b.address ? `<p class="ynj-text-muted">${b.address}</p>` : ''}
                            </div>`
                        ).join('');
                    } else {
                        list.innerHTML = '<p class="ynj-text-muted">No directory listings yet. Check back soon.</p>';
                    }
                    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                        el.href = el.dataset.navMosque.replace('{slug}', slug);
                    });
                })
                .catch(() => {
                    document.getElementById('dir-list').innerHTML = '<p class="ynj-text-muted">Could not load directory.</p>';
                });
        })();
        </script>

        </body>
        </html>
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

        <main class="ynj-main" style="text-align:center;padding-top:60px;">
            <h1 style="font-size:48px;color:<?php echo self::COLOR_PRIMARY; ?>;margin-bottom:12px;">404</h1>
            <p class="ynj-text-muted" style="margin-bottom:24px;">This page could not be found.</p>
            <a href="/" class="ynj-btn">Go Home</a>
        </main>

        </body>
        </html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    /**
     * Output the full HTML <head> section with meta tags, manifest, fonts,
     * and all inline CSS.
     *
     * @param string $title Page title.
     * @param string $desc  Meta description.
     */
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
/* ---- Reset ---- */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{-webkit-text-size-adjust:100%;scroll-behavior:smooth;}
body{
    font-family:'Inter',system-ui,-apple-system,sans-serif;
    font-size:15px;
    line-height:1.55;
    color:<?php echo self::COLOR_TEXT; ?>;
    background:linear-gradient(180deg,#e8f4f8 0%,#d4eef6 30%,#c5e8f4 60%,#e0f2f8 100%);
    background-attachment:fixed;
    min-height:100vh;
    min-height:100dvh;
    padding-bottom:72px;
    -webkit-font-smoothing:antialiased;
}
a{color:<?php echo self::COLOR_ACCENT; ?>;text-decoration:none;}
img,svg{display:block;max-width:100%;}

/* ---- Layout ---- */
.ynj-main{
    max-width:500px;
    margin:0 auto;
    padding:12px 16px 24px;
}

/* ---- Header ---- */
.ynj-header{
    background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 50%,#00ADEF 100%);
    color:#fff;
    position:sticky;
    top:0;
    z-index:100;
    padding:0 16px;
    padding-top:env(safe-area-inset-top,0);
}
.ynj-header__inner{
    max-width:500px;
    margin:0 auto;
    display:flex;
    align-items:center;
    gap:12px;
    min-height:56px;
}
.ynj-logo{
    display:flex;
    align-items:center;
    gap:8px;
    font-weight:700;
    font-size:17px;
    white-space:nowrap;
}
.ynj-header__mosque{
    font-size:13px;
    opacity:.85;
    margin-left:auto;
    text-align:right;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:55%;
}
.ynj-back{
    display:flex;
    align-items:center;
    justify-content:center;
    width:32px;
    height:32px;
    border-radius:8px;
    transition:background .15s;
}
.ynj-back:active{background:rgba(255,255,255,.15);}

/* ---- Cards ---- */
.ynj-card{
    background:rgba(255,255,255,.85);
    backdrop-filter:blur(12px);
    -webkit-backdrop-filter:blur(12px);
    border-radius:18px;
    padding:20px;
    margin-bottom:14px;
    box-shadow:0 2px 12px rgba(0,173,239,.08);
    border:1px solid rgba(255,255,255,.6);
}
.ynj-card--hero{
    background:linear-gradient(180deg,#0a1628 0%,#1a3a5c 40%,#00ADEF 80%,#7dd3fc 100%);
    color:#fff;
    text-align:center;
    padding:32px 20px;
    border-radius:18px;
    position:relative;
    overflow:hidden;
}
.ynj-card--hero::before{
    content:'';
    position:absolute;
    top:40%;left:-20%;width:140%;height:60%;
    background:radial-gradient(ellipse,rgba(255,255,255,.15) 0%,transparent 70%);
    animation:clouds 8s ease-in-out infinite alternate;
}
.ynj-card--hero::after{
    content:'';
    position:absolute;
    bottom:0;left:0;width:100%;height:30%;
    background:linear-gradient(to top,rgba(255,255,255,.1),transparent);
}
@keyframes clouds{
    0%{transform:translateX(-5%) translateY(0)}
    100%{transform:translateX(5%) translateY(-8px)}
}
.ynj-card--hero .ynj-label{font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.8;}
.ynj-card--hero .ynj-countdown{font-size:42px;font-weight:700;letter-spacing:2px;margin:8px 0 4px;font-variant-numeric:tabular-nums;}
.ynj-card--hero .ynj-prayer-name{font-size:16px;font-weight:600;opacity:.9;}
.ynj-card__title{font-size:15px;font-weight:600;margin-bottom:14px;color:<?php echo self::COLOR_TEXT; ?>;}

/* ---- Prayer Grid ---- */
.ynj-prayer-grid{display:flex;flex-direction:column;gap:1px;}
.ynj-prayer-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px 0;
    border-bottom:1px solid #f0f0ec;
}
.ynj-prayer-row:last-child{border-bottom:none;}
.ynj-prayer-row__name{font-weight:500;font-size:14px;}
.ynj-prayer-row__time{font-weight:600;font-size:14px;font-variant-numeric:tabular-nums;color:<?php echo self::COLOR_PRIMARY; ?>;}
.ynj-prayer-row--placeholder .ynj-prayer-row__time{color:#ccc;}

/* ---- Jumu'ah ---- */
.ynj-jumuah-time{font-size:20px;font-weight:700;color:<?php echo self::COLOR_ACCENT; ?>;}

/* ---- Travel ---- */
.ynj-card--travel{padding:14px 20px;}
.ynj-travel{display:flex;align-items:center;gap:8px;font-size:13px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}

/* ---- Subscribe ---- */
.ynj-card--subscribe{text-align:center;}
.ynj-card--subscribe .ynj-text-muted{margin-bottom:16px;}
.ynj-subscribe-status{font-size:12px;margin-top:10px;min-height:18px;}

/* ---- Button ---- */
.ynj-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:linear-gradient(135deg,#00ADEF,#0090d0);
    color:#fff;
    border:none;
    border-radius:12px;
    padding:14px 28px;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
    transition:all .2s;
    -webkit-tap-highlight-color:transparent;
    box-shadow:0 4px 14px rgba(0,173,239,.3);
}
.ynj-btn:active{transform:scale(.97);}
.ynj-btn:disabled{opacity:.5;cursor:default;}

/* ---- Feed ---- */
.ynj-feed{display:flex;flex-direction:column;gap:12px;}
.ynj-feed-item{padding:12px 0;border-bottom:1px solid #f0f0ec;}
.ynj-feed-item:last-child{border-bottom:none;}
.ynj-feed-item h4{font-size:14px;font-weight:600;margin-bottom:4px;}
.ynj-feed-item time{font-size:12px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}

/* ---- Donate ---- */
.ynj-donate-badge{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:rgba(255,255,255,.85);margin-top:12px;}

/* ---- Directory ---- */
.ynj-dir-item a{color:<?php echo self::COLOR_ACCENT; ?>;font-weight:500;}

/* ---- Text helpers ---- */
.ynj-text-muted{font-size:13px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}
.ynj-mosque-name{font-size:22px;font-weight:700;}

/* ---- Bottom Nav ---- */
.ynj-nav{
    position:fixed;
    bottom:0;
    left:0;
    right:0;
    background:<?php echo self::COLOR_SURFACE; ?>;
    border-top:1px solid #e5e5e0;
    z-index:200;
    padding-bottom:env(safe-area-inset-bottom,0);
}
.ynj-nav__inner{
    max-width:500px;
    margin:0 auto;
    display:flex;
    justify-content:space-around;
    padding:6px 0 4px;
}
.ynj-nav__item{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:2px;
    padding:4px 8px;
    font-size:10px;
    font-weight:500;
    color:<?php echo self::COLOR_TEXT_MUTED; ?>;
    text-decoration:none;
    transition:color .15s;
    -webkit-tap-highlight-color:transparent;
}
.ynj-nav__item--active{color:<?php echo self::COLOR_ACCENT; ?>;}
.ynj-nav__item svg{width:22px;height:22px;}
</style>
</head>
<body>
        <?php
    }

    /**
     * Render the 5-tab bottom navigation bar.
     *
     * @param string $active Active tab key: home|prayers|donate|events|more.
     */
    public static function render_bottom_nav( string $active = 'home' ): void {
        $tabs = [
            'home' => [
                'label' => 'Home',
                'href'  => '/',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>',
            ],
            'prayers' => [
                'label' => 'Prayers',
                'href'  => '/mosque/{slug}/prayers',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
                'mosque' => true,
            ],
            'donate' => [
                'label' => 'Donate',
                'href'  => '/mosque/{slug}/donate',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>',
                'mosque' => true,
            ],
            'events' => [
                'label' => 'Events',
                'href'  => '/mosque/{slug}/events',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
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
                // Default href until JS updates it.
                $href = '#';
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
