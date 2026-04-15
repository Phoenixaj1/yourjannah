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
                <nav class="ynj-header__nav" style="display:none;" id="desktop-nav">
                    <a href="/" class="ynj-hn--active">Home</a>
                    <a href="#" id="dn-fundraise" data-nav-mosque="/mosque/{slug}/fundraising">Fundraise</a>
                    <a href="#" id="dn-sponsors" data-nav-mosque="/mosque/{slug}/sponsors">Sponsors</a>
                    <a href="#" id="dn-services" data-nav-mosque="/mosque/{slug}/services">Services</a>
                    <a href="#" id="dn-rooms" data-nav-mosque="/mosque/{slug}/rooms">Rooms</a>
                    <a href="/profile">My Account</a>
                </nav>
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
          <div class="ynj-desktop-grid">
            <div class="ynj-desktop-grid__left">

            <!-- Sponsor Ticker -->
            <div class="ynj-ticker" id="sponsor-ticker" style="display:none;">
                <span class="ynj-ticker__label">⭐ Sponsors</span>
                <div class="ynj-ticker__track">
                    <div class="ynj-ticker__slide" id="ticker-content"></div>
                </div>
            </div>

            <!-- Travel Settings (inline, compact) -->
            <div class="ynj-travel-settings" id="travel-settings" style="display:none;">
                <div class="ynj-travel-settings__row">
                    <select id="mode-select" class="ynj-ts-select" onchange="onModeChange()">
                        <option value="walk">🚶 Walk</option>
                        <option value="drive">🚗 Drive</option>
                        <option value="bike">🚲 Cycle</option>
                    </select>
                    <select id="buffer-select" class="ynj-ts-select" onchange="onBufferChange()">
                        <option value="0">No buffer</option>
                        <option value="5">+5 min wudhu</option>
                        <option value="10" selected>+10 min wudhu</option>
                        <option value="15">+15 min prep</option>
                        <option value="20">+20 min prep</option>
                    </select>
                </div>
            </div>

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
                <div class="ynj-nav-buttons" id="nav-buttons" style="display:none;">
                    <a class="ynj-btn ynj-btn--navigate" id="navigate-walk" href="#" target="_blank" rel="noopener">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="2"/><path d="M10 22l2-7 3 3v7M14 13l2-3-3-3-2 4"/></svg>
                        Walk
                    </a>
                    <a class="ynj-btn ynj-btn--navigate" id="navigate-drive" href="#" target="_blank" rel="noopener">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17h14M7 11l2-5h6l2 5M4 17v-3a1 1 0 011-1h14a1 1 0 011 1v3"/><circle cx="7.5" cy="17" r="1.5"/><circle cx="16.5" cy="17" r="1.5"/></svg>
                        Drive
                    </a>
                </div>
            </section>

            <!-- Hadith motivation -->
            <p class="ynj-hadith" id="hadith-line">
                <em>&ldquo;Prayer in congregation is twenty-seven times more virtuous than prayer offered alone.&rdquo;</em>
                <span>— Sahih al-Bukhari 645</span>
            </p>

            <!-- Donate button -->
            <a class="ynj-donate-btn" id="donate-btn" href="#" target="_blank" rel="noopener" style="display:none;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                Donate to Masjid
            </a>

            <!-- Section 2: View Full Timetable link -->
            <a class="ynj-timetable-link" id="timetable-link" href="#">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                View Full Timetable
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </a>

            </div><!-- end left column -->
            <div class="ynj-desktop-grid__right">

            <!-- Section 3: Feed -->
            <section id="feed-section">
                <div class="ynj-feed-tabs">
                    <button class="ynj-feed-tab ynj-feed-tab--active" id="tab-local" onclick="switchFeedTab('local')">Your Mosque</button>
                    <button class="ynj-feed-tab" id="tab-wider" onclick="switchFeedTab('wider')">Nearby Events</button>
                </div>
                <div id="feed-local">
                    <div class="ynj-filter-chips" id="local-filters">
                        <button class="ynj-chip ynj-chip--active" data-filter="all" onclick="filterLocal('all')">All</button>
                        <button class="ynj-chip" data-filter="announcements" onclick="filterLocal('announcements')">📢 Updates</button>
                        <button class="ynj-chip" data-filter="talk" onclick="filterLocal('talk')">🎤 Talks</button>
                        <button class="ynj-chip" data-filter="class,course,halaqa" onclick="filterLocal('class,course,halaqa')">📖 Classes</button>
                        <button class="ynj-chip" data-filter="youth,kids,children" onclick="filterLocal('youth,kids,children')">👦 Youth</button>
                        <button class="ynj-chip" data-filter="sisters" onclick="filterLocal('sisters')">👩 Sisters</button>
                        <button class="ynj-chip" data-filter="sports,competition" onclick="filterLocal('sports,competition')">⚽ Sports</button>
                        <button class="ynj-chip" data-filter="community,iftar,fundraiser" onclick="filterLocal('community,iftar,fundraiser')">🤝 Community</button>
                    </div>
                    <div id="local-feed-list">
                        <p class="ynj-text-muted" style="padding:16px;text-align:center;">Loading&hellip;</p>
                    </div>
                </div>
                <div id="feed-wider" style="display:none;">
                    <div class="ynj-filter-chips" id="event-filters">
                        <button class="ynj-chip ynj-chip--active" data-filter="all" onclick="filterEvents('all')">All</button>
                        <button class="ynj-chip" data-filter="talk" onclick="filterEvents('talk')">🎤 Talks</button>
                        <button class="ynj-chip" data-filter="class,course,halaqa" onclick="filterEvents('class,course,halaqa')">📖 Classes</button>
                        <button class="ynj-chip" data-filter="youth,kids,children" onclick="filterEvents('youth,kids,children')">👦 Youth</button>
                        <button class="ynj-chip" data-filter="sisters" onclick="filterEvents('sisters')">👩 Sisters</button>
                        <button class="ynj-chip" data-filter="sports,competition" onclick="filterEvents('sports,competition')">⚽ Sports</button>
                        <button class="ynj-chip" data-filter="community,iftar,fundraiser" onclick="filterEvents('community,iftar,fundraiser')">🤝 Community</button>
                        <button class="ynj-chip" data-filter="workshop" onclick="filterEvents('workshop')">🛠️ Workshop</button>
                    </div>
                    <div id="wider-events-list">
                        <p class="ynj-text-muted" style="padding:16px;text-align:center;">Tap "Nearby Events" to discover what's happening.</p>
                    </div>
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

            </div><!-- end right column -->
          </div><!-- end desktop grid -->
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
            let jamatTimes = {};
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
            let mosqueLat = null, mosqueLng = null;

            function selectMosque(slug, name, lat, lng, distKm) {
                mosqueSlug = slug;
                mosqueLat = lat; mosqueLng = lng;
                localStorage.setItem('ynj_mosque_slug', slug);
                document.getElementById('mosque-name').textContent = name || slug;
                updateNavLinks(slug);

                // Timetable link
                document.getElementById('timetable-link').href = `/mosque/${slug}/prayers`;

                // Travel & navigate
                if (lat && lng) {
                    document.getElementById('nav-buttons').style.display = '';
                    document.getElementById('navigate-walk').href =
                        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=walking`;
                    document.getElementById('navigate-drive').href =
                        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;

                    if (userLat != null) {
                        calcTravelFromCoords(lat, lng);
                    } else {
                        showPostcodePrompt(lat, lng);
                    }
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

                        // Sponsor ticker
                        fetch(`${API}/mosques/${slug}/directory`)
                            .then(r => r.json())
                            .then(dirData => {
                                const biz = dirData.businesses || [];
                                if (biz.length) {
                                    const ticker = document.getElementById('sponsor-ticker');
                                    const content = document.getElementById('ticker-content');
                                    // Duplicate items for seamless loop
                                    const items = biz.map((b, i) =>
                                        `<a href="/mosque/${slug}/sponsors" class="ynj-ticker__item"><span class="ynj-ticker__rank">#${i+1}</span>${b.business_name}<span class="ynj-ticker__cat">${b.category}</span></a>`
                                    ).join('');
                                    content.innerHTML = items + items; // duplicate for seamless scroll
                                    ticker.style.display = '';
                                    // Adjust speed based on content width
                                    const width = content.scrollWidth / 2;
                                    const speed = Math.max(10, Math.round(width / 40));
                                    content.style.animationDuration = speed + 's';
                                }
                            })
                            .catch(() => {});

                        // Donate button — link to DonationForMasjid
                        const dfmSlug = m.dfm_slug || m.slug || slug;
                        const donateBtn = document.getElementById('donate-btn');
                        donateBtn.href = `https://donationformasjid.com/${dfmSlug}`;
                        donateBtn.style.display = '';

                        // Always show navigate if we have mosque coords
                        if (m.latitude && m.longitude) {
                            mosqueLat = m.latitude; mosqueLng = m.longitude;
                            document.getElementById('nav-buttons').style.display = '';
                            document.getElementById('navigate-walk').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=walking`;
                            document.getElementById('navigate-drive').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=driving`;

                            if (userLat) {
                                document.getElementById('mode-toggle').style.display = '';
                                calcTravelFromCoords(m.latitude, m.longitude);
                            } else {
                                // No GPS yet — show postcode prompt in travel area
                                showPostcodePrompt(m.latitude, m.longitude);
                            }
                        }

                        if (m.prayer_times && !m.prayer_times.error) {
                            // Store jamat times separately
                            ['fajr_jamat','dhuhr_jamat','asr_jamat','maghrib_jamat','isha_jamat'].forEach(k => {
                                if (m.prayer_times[k]) {
                                    jamatTimes[k] = String(m.prayer_times[k]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                                }
                            });
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
                            document.getElementById('nav-buttons').style.display = '';
                            document.getElementById('navigate-walk').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=walking`;
                            document.getElementById('navigate-drive').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=driving`;
                            mosqueLat = m.latitude; mosqueLng = m.longitude;
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
                let next = null, nextName = null, nextTime = null, nextJamat = null;

                for (const p of prayers) {
                    if (!prayerTimes[p]) continue;
                    const [h,m] = prayerTimes[p].split(':').map(Number);
                    const t = new Date(now); t.setHours(h,m,0,0);
                    if (t > now) {
                        next = t; nextName = p; nextTime = prayerTimes[p];
                        nextJamat = jamatTimes[p+'_jamat'] || null;
                        break;
                    }
                }

                const hero = document.getElementById('next-prayer-card');

                if (!next) {
                    document.getElementById('next-prayer-countdown').textContent = '--:--:--';
                    document.getElementById('next-prayer-name').textContent = 'All prayers completed';
                    document.getElementById('next-prayer-time').textContent = 'See you at Fajr tomorrow';
                    document.getElementById('next-prayer-label').textContent = '';
                    hero.classList.remove('ynj-hero--urgent','ynj-hero--critical');
                    return;
                }

                const diff = Math.max(0, Math.floor((next - now) / 1000));
                const diffMin = Math.floor(diff / 60);
                const hh = String(Math.floor(diff / 3600)).padStart(2,'0');
                const mm = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
                const ss = String(diff % 60).padStart(2,'0');

                const label = nextName.charAt(0).toUpperCase() + nextName.slice(1);
                document.getElementById('next-prayer-countdown').textContent = `${hh}:${mm}:${ss}`;
                document.getElementById('next-prayer-name').textContent = label;

                // Show adhan + jamat time
                const timeDisplay = nextJamat ? `Adhan ${nextTime} · Jamat ${nextJamat}` : nextTime;
                document.getElementById('next-prayer-time').textContent = timeDisplay;
                document.getElementById('next-prayer-label').textContent = 'Next Prayer';

                // Urgency based on leave-by time (jamat - travel - buffer)
                hero.classList.remove('ynj-hero--urgent','ynj-hero--critical');
                if (travelMinutes) {
                    const totalLead = travelMinutes + bufferMinutes;
                    // Calculate minutes until we need to leave (using jamat time)
                    const jamatTime = jamatTimes[nextName+'_jamat'] || nextTime;
                    const [jh,jm] = jamatTime.split(':').map(Number);
                    const jt = new Date(now); jt.setHours(jh,jm,0,0);
                    const minsUntilLeave = Math.floor((jt - now) / 60000) - totalLead;

                    if (minsUntilLeave <= 0) {
                        hero.classList.add('ynj-hero--critical');
                        document.getElementById('leave-by-text').textContent = '🚨 LEAVE NOW';
                    } else if (minsUntilLeave <= 10) {
                        hero.classList.add('ynj-hero--urgent');
                    }
                }

                updateLeaveBy();
            }

            function updateLeaveBy() {
                if (!prayerTimes || !travelMinutes) return;
                const now = new Date();
                const prayers = ['fajr','dhuhr','asr','maghrib','isha'];
                for (const p of prayers) {
                    if (!prayerTimes[p]) continue;

                    // Use JAMAT time if available, otherwise adhan
                    const targetTime = jamatTimes[p+'_jamat'] || prayerTimes[p];
                    const [h,m] = targetTime.split(':').map(Number);
                    const t = new Date(now); t.setHours(h,m,0,0);

                    if (t > now) {
                        // Leave time = jamat time - travel time - buffer (wudhu/prep)
                        const totalLeadMin = travelMinutes + bufferMinutes;
                        const leave = new Date(t.getTime() - totalLeadMin * 60000);
                        const lh = String(leave.getHours()).padStart(2,'0');
                        const lm = String(leave.getMinutes()).padStart(2,'0');

                        const parts = [`Leave by ${lh}:${lm}`];
                        if (bufferMinutes > 0) parts.push(`(inc. ${bufferMinutes}min prep)`);
                        document.getElementById('leave-by-text').textContent = parts.join(' ');
                        return;
                    }
                }
            }

            /* ---- Feed ---- */
            let widerFeedLoaded = false;

            window.switchFeedTab = function(tab) {
                document.getElementById('tab-local').classList.toggle('ynj-feed-tab--active', tab==='local');
                document.getElementById('tab-wider').classList.toggle('ynj-feed-tab--active', tab==='wider');
                document.getElementById('feed-local').style.display = tab==='local' ? '' : 'none';
                document.getElementById('feed-wider').style.display = tab==='wider' ? '' : 'none';
                if (tab==='wider' && !widerFeedLoaded) loadWiderFeed();
            };

            const eventTypeIcons = {
                'talk':'🎤','class':'📖','course':'🎓','workshop':'🛠️','community':'🤝',
                'sports':'⚽','competition':'🏆','youth':'👦','kids':'🧒','children':'🧒',
                'sisters':'👩','fundraiser':'💰','iftar':'🍽️','eid':'🌙','quran':'📖',
                'halaqa':'📚','nikah':'💍','janazah':'🕊️','other':'📌'
            };

            function renderFeedCard(item) {
                const cardClass = item.pinned ? 'ynj-feed-card--pinned' : (item.type==='event' ? 'ynj-feed-card--event' : 'ynj-feed-card--announcement');

                let badge;
                if (item.type === 'event') {
                    const et = (item.event_type||'').toLowerCase();
                    const icon = eventTypeIcons[et] || '📅';
                    const label = (item.event_type||'Event').charAt(0).toUpperCase() + (item.event_type||'event').slice(1);
                    badge = `<span class="ynj-badge ynj-badge--event">${icon} ${label}</span>`;
                } else {
                    badge = item.pinned ? '<span class="ynj-badge ynj-badge--pinned">📌 Pinned</span>' : '<span class="ynj-badge">📢 Update</span>';
                }

                const snippet = (item.body||'').length > 80 ? item.body.slice(0,80)+'...' : (item.body||'');
                const meta = [];
                if (item.type === 'event') {
                    if (item.date) meta.push(`<span>📅 ${item.date}</span>`);
                    if (item.time) meta.push(`<span>🕐 ${item.time}</span>`);
                    if (item.location) meta.push(`<span>📍 ${item.location}</span>`);
                    if (item.event_id && item.mosque_slug) meta.push(`<a href="/mosque/${item.mosque_slug}/events/${item.event_id}" style="color:#00ADEF;font-weight:600;">RSVP →</a>`);
                } else {
                    if (item.date) meta.push(`<span>${timeAgo(item.date)}</span>`);
                }
                const mosqueTag = item.mosque_name ? `<div class="ynj-feed-card__mosque">🕌 ${item.mosque_name}</div>` : '';
                return `<div class="ynj-feed-card ${cardClass}">
                    <div class="ynj-feed-card__top">${badge}<h4>${item.title}</h4></div>
                    ${snippet ? `<div class="ynj-feed-card__body">${snippet}</div>` : ''}
                    <div class="ynj-feed-card__meta">${meta.join(' ')}</div>
                    ${mosqueTag}
                </div>`;
            }

            let allLocalItems = [];

            function loadFeed(slug) {
                Promise.all([
                    fetch(`${API}/mosques/${slug}/announcements`).then(r => r.json()).catch(() => ({announcements:[]})),
                    fetch(`${API}/mosques/${slug}/events?upcoming=1`).then(r => r.json()).catch(() => ({events:[]}))
                ]).then(([aData, eData]) => {
                    allLocalItems = [];
                    (aData.announcements || []).forEach(a => {
                        allLocalItems.push({ type:'announcement', title:a.title, body:a.body, date:a.published_at||'', pinned:a.pinned });
                    });
                    (eData.events || []).forEach(e => {
                        const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                        allLocalItems.push({ type:'event', title:e.title, body:e.description||'', date:e.event_date||'', time:time, location:e.location||'', event_id:e.id, mosque_slug:slug, event_type:e.event_type||'' });
                    });
                    allLocalItems.sort((a,b) => { if(a.pinned&&!b.pinned)return -1; if(!a.pinned&&b.pinned)return 1; return (b.date||'').localeCompare(a.date||''); });
                    renderLocalFeed('all');
                });
            }

            function renderLocalFeed(filter) {
                const el = document.getElementById('local-feed-list');
                let items = allLocalItems;

                if (filter === 'announcements') {
                    items = items.filter(i => i.type === 'announcement');
                } else if (filter && filter !== 'all') {
                    const types = filter.split(',');
                    items = items.filter(i => i.type === 'event' && types.includes((i.event_type||'').toLowerCase()));
                }

                if (!items.length) {
                    el.innerHTML = filter === 'all'
                        ? '<p class="ynj-text-muted" style="padding:12px;text-align:center;">No announcements or events yet.</p>'
                        : '<p class="ynj-text-muted" style="padding:12px;text-align:center;">Nothing matching this filter.</p>';
                    return;
                }
                el.innerHTML = '<div class="ynj-feed">' + items.map(renderFeedCard).join('') + '</div>';
            }

            window.filterLocal = function(filter) {
                document.querySelectorAll('#local-filters .ynj-chip').forEach(c => {
                    c.classList.toggle('ynj-chip--active', c.dataset.filter === filter);
                });
                renderLocalFeed(filter);
            };

            let allWiderEvents = [];

            function loadWiderFeed() {
                widerFeedLoaded = true;
                const el = document.getElementById('wider-events-list');
                const lat = userLat || mosqueLat;
                const lng = userLng || mosqueLng;
                if (!lat) { el.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Location needed to find nearby events.</p>'; return; }

                el.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Finding events near you...</p>';

                fetch(`${API}/mosques/nearest?lat=${lat}&lng=${lng}&limit=10`)
                    .then(r => r.json())
                    .then(data => {
                        const mosques = (data.mosques||[]).filter(m => m.slug !== mosqueSlug);
                        if (!mosques.length) { el.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">No nearby mosques found.</p>'; return; }

                        const fetches = mosques.slice(0,8).map(m =>
                            fetch(`${API}/mosques/${m.slug}/events?upcoming=1`).then(r=>r.json())
                                .then(d => (d.events||[]).map(e => ({...e, mosque_name:m.name, mosque_slug:m.slug, distance:m.distance})))
                                .catch(() => [])
                        );
                        return Promise.all(fetches);
                    })
                    .then(results => {
                        if (!results) return;
                        allWiderEvents = results.flat().sort((a,b) => (a.event_date||'').localeCompare(b.event_date||''));
                        renderWiderEvents('all');
                    })
                    .catch(() => { el.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Could not load.</p>'; });
            }

            function renderWiderEvents(filter) {
                const el = document.getElementById('wider-events-list');
                let events = allWiderEvents;

                if (filter && filter !== 'all') {
                    const types = filter.split(',');
                    events = events.filter(e => types.includes((e.event_type||'').toLowerCase()));
                }

                if (!events.length) {
                    el.innerHTML = filter === 'all'
                        ? '<p class="ynj-text-muted" style="padding:16px;text-align:center;">No upcoming events at nearby mosques.</p>'
                        : '<p class="ynj-text-muted" style="padding:16px;text-align:center;">No events matching this filter. Try "All".</p>';
                    return;
                }

                el.innerHTML = '<div class="ynj-feed">' + events.map(e => {
                    const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                    return renderFeedCard({
                        type:'event', title:e.title, body:e.description||'', date:e.event_date||'',
                        time:time, location:e.location||'', event_id:e.id, mosque_slug:e.mosque_slug,
                        event_type: e.event_type||'',
                        mosque_name: e.mosque_name + (e.distance ? ` · ${e.distance < 1.6 ? (e.distance*0.621).toFixed(1)+'mi' : Math.round(e.distance*0.621)+'mi'}` : '')
                    });
                }).join('') + '</div>';
            }

            window.filterEvents = function(filter) {
                document.querySelectorAll('#event-filters .ynj-chip').forEach(c => {
                    c.classList.toggle('ynj-chip--active', c.dataset.filter === filter);
                });
                renderWiderEvents(filter);
            };

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

            /* ---- Travel Calculation ---- */
            let travelMode = localStorage.getItem('ynj_travel_mode') || 'walk';
            let bufferMinutes = parseInt(localStorage.getItem('ynj_buffer_min') || '10');
            let distanceKm = 0;

            // Speeds: walk ~5km/h, drive ~30km/h urban, bike ~15km/h
            const modeSpeed = { walk: 12, drive: 2, bike: 4 }; // min per km
            const modeLabel = { walk: 'walk', drive: 'drive', bike: 'cycle' };

            function calcTravelFromCoords(mLat, mLng) {
                distanceKm = haversine(userLat, userLng, mLat, mLng);
                recalcTravel();
            }

            function recalcTravel() {
                if (!distanceKm) return;
                const mi = distanceKm * 0.621;
                travelMinutes = Math.max(1, Math.round(distanceKm * (modeSpeed[travelMode] || 12)));
                const distText = mi < 0.5 ? `${Math.round(distanceKm*1000)}m` : `${mi.toFixed(1)} mi`;
                document.getElementById('travel-dist').textContent = `${distText} · ~${travelMinutes} min ${modeLabel[travelMode]}`;
                document.getElementById('hero-travel').style.display = '';
                document.getElementById('travel-settings').style.display = '';

                // Sync dropdowns
                const modeEl = document.getElementById('mode-select');
                const bufEl = document.getElementById('buffer-select');
                if (modeEl) modeEl.value = travelMode;
                if (bufEl) bufEl.value = bufferMinutes;

                updateLeaveBy();
            }

            window.onModeChange = function() {
                travelMode = document.getElementById('mode-select').value;
                localStorage.setItem('ynj_travel_mode', travelMode);
                recalcTravel();
            };

            window.onBufferChange = function() {
                bufferMinutes = parseInt(document.getElementById('buffer-select').value) || 0;
                localStorage.setItem('ynj_buffer_min', bufferMinutes);
                updateLeaveBy();
            };

            function showPostcodePrompt(mLat, mLng) {
                // Check localStorage for saved postcode first
                const savedPC = localStorage.getItem('ynj_user_postcode');
                if (savedPC) {
                    geocodePostcode(savedPC, mLat, mLng);
                    return;
                }

                const travelEl = document.getElementById('hero-travel');
                travelEl.style.display = '';
                travelEl.innerHTML = `
                    <div style="display:flex;align-items:center;gap:8px;width:100%;">
                        <input type="text" id="pc-input" placeholder="Enter your postcode"
                            style="flex:1;padding:8px 12px;border:1px solid rgba(255,255,255,.4);border-radius:8px;
                            background:rgba(255,255,255,.15);color:#fff;font-size:13px;font-family:inherit;
                            outline:none;text-transform:uppercase;max-width:140px;"
                            maxlength="8">
                        <button onclick="submitPostcode()"
                            style="padding:8px 14px;border:1px solid rgba(255,255,255,.4);border-radius:8px;
                            background:rgba(255,255,255,.2);color:#fff;font-size:13px;font-weight:600;
                            cursor:pointer;white-space:nowrap;">
                            Calculate
                        </button>
                    </div>`;
                // Focus the input
                setTimeout(() => { const inp = document.getElementById('pc-input'); if(inp) inp.focus(); }, 100);
            }

            window.submitPostcode = function() {
                const inp = document.getElementById('pc-input');
                if (!inp) return;
                const pc = inp.value.trim().replace(/\s+/g, '');
                if (pc.length < 3) return;
                localStorage.setItem('ynj_user_postcode', pc);
                inp.disabled = true;
                geocodePostcode(pc, mosqueLat, mosqueLng);
            };

            function geocodePostcode(pc, mLat, mLng) {
                const travelEl = document.getElementById('hero-travel');
                travelEl.style.display = '';
                travelEl.innerHTML = '<span style="font-size:13px;opacity:.7;">Calculating travel time...</span>';

                fetch(`https://api.postcodes.io/postcodes/${encodeURIComponent(pc.replace(/\s/g,''))}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.result && data.result.latitude) {
                            userLat = data.result.latitude;
                            userLng = data.result.longitude;
                            // Restore the travel display elements
                            travelEl.innerHTML = `
                                <div class="ynj-leave-by" id="leave-by">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                    <span id="leave-by-text">Calculating...</span>
                                </div>
                                <span class="ynj-travel-dist" id="travel-dist"></span>`;
                            calcTravelFromCoords(mLat, mLng);
                        } else {
                            travelEl.innerHTML = '<span style="font-size:12px;opacity:.7;">Postcode not found. <a href="#" onclick="localStorage.removeItem(\'ynj_user_postcode\');location.reload();return false;" style="color:#fff;text-decoration:underline;">Try again</a></span>';
                        }
                    })
                    .catch(() => {
                        travelEl.innerHTML = '<span style="font-size:12px;opacity:.7;">Could not look up postcode. <a href="#" onclick="localStorage.removeItem(\'ynj_user_postcode\');location.reload();return false;" style="color:#fff;text-decoration:underline;">Try again</a></span>';
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
        <style>
        .ynj-tt-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;}
        .ynj-tt-nav{display:flex;align-items:center;gap:12px;}
        .ynj-tt-nav button{background:none;border:1px solid #ddd;border-radius:8px;padding:6px 12px;font-size:13px;font-weight:600;cursor:pointer;color:<?php echo self::COLOR_TEXT; ?>;}
        .ynj-tt-nav button:active{background:#f0f8ff;}
        .ynj-tt-month{font-size:15px;font-weight:700;min-width:120px;text-align:center;}
        .ynj-tt-print{background:none;border:1px solid <?php echo self::COLOR_ACCENT; ?>;color:<?php echo self::COLOR_ACCENT; ?>;border-radius:8px;padding:6px 14px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;}
        .ynj-tt-table{width:100%;border-collapse:collapse;font-size:11px;line-height:1.3;}
        .ynj-tt-table th{background:#f0f8fc;padding:6px 4px;font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;text-align:center;position:sticky;top:0;border-bottom:2px solid #e0e8ed;}
        .ynj-tt-table td{padding:5px 3px;text-align:center;border-bottom:1px solid #f0f0ec;font-variant-numeric:tabular-nums;}
        .ynj-tt-table tr.ynj-tt-today{background:#e8f7ff;font-weight:600;}
        .ynj-tt-table tr.ynj-tt-fri{background:#fef9ee;}
        .ynj-tt-table .ynj-tt-jamat{color:<?php echo self::COLOR_ACCENT; ?>;font-weight:600;}
        .ynj-tt-table .ynj-tt-day{text-align:left;font-weight:500;white-space:nowrap;}
        .ynj-tt-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -20px;padding:0 20px;}
        @media print{
            .ynj-header,.ynj-nav,.ynj-tt-print,.ynj-timetable-link,.ynj-card--subscribe{display:none!important;}
            body{background:#fff!important;padding:0!important;}
            .ynj-main{max-width:100%!important;padding:0!important;}
            .ynj-card{box-shadow:none!important;border:none!important;background:#fff!important;padding:10px!important;}
            .ynj-tt-table{font-size:10px;}
            .ynj-print-header{display:block!important;text-align:center;margin-bottom:12px;}
        }
        .ynj-print-header{display:none;}
        </style>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Prayer Times</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <div class="ynj-print-header">
                <h1 id="print-mosque" style="font-size:20px;font-weight:900;margin-bottom:4px;"></h1>
                <p id="print-month" style="font-size:14px;color:#666;"></p>
            </div>

            <!-- Today's Times -->
            <section class="ynj-card" id="today-card">
                <h2 class="ynj-card__title" id="pt-mosque-name">Loading&hellip;</h2>
                <div id="today-grid" class="ynj-prayer-grid"></div>
            </section>

            <!-- Jumu'ah Times -->
            <section class="ynj-card" id="jumuah-card" style="display:none;">
                <h3 class="ynj-card__title">Jumu'ah (Friday Prayer)</h3>
                <div id="jumuah-list"></div>
            </section>

            <!-- Eid Times -->
            <section class="ynj-card" id="eid-card" style="display:none;">
                <h3 class="ynj-card__title">Eid Prayer Times</h3>
                <div id="eid-list"></div>
            </section>

            <!-- Monthly Timetable -->
            <section class="ynj-card">
                <div class="ynj-tt-header">
                    <div class="ynj-tt-nav">
                        <button onclick="changeMonth(-1)">&#9664;</button>
                        <span class="ynj-tt-month" id="month-label">Loading...</span>
                        <button onclick="changeMonth(1)">&#9654;</button>
                    </div>
                    <button class="ynj-tt-print" onclick="window.print()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print
                    </button>
                </div>
                <div class="ynj-tt-scroll">
                    <table class="ynj-tt-table" id="month-table">
                        <thead>
                            <tr>
                                <th>Date</th><th>Day</th>
                                <th>Fajr</th><th>F.Jam</th>
                                <th>Rise</th>
                                <th>Dhuhr</th><th>D.Jam</th>
                                <th>Asr</th><th>A.Jam</th>
                                <th>Magh</th><th>M.Jam</th>
                                <th>Isha</th><th>I.Jam</th>
                            </tr>
                        </thead>
                        <tbody id="month-body"><tr><td colspan="13" style="padding:20px;color:#999;">Loading timetable...</td></tr></tbody>
                    </table>
                </div>
            </section>
        </main>
        <?php self::render_bottom_nav( 'home', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            const API = '/wp-json/ynj/v1';
            let currentMonth = new Date().toISOString().slice(0,7); // YYYY-MM
            let mosqueName = '';

            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });

            const T = s => s ? String(s).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'') : '—';

            // Load mosque + today's times
            fetch(`${API}/mosques/${slug}`)
                .then(r => r.json())
                .then(resp => {
                    const m = resp.mosque || resp;
                    mosqueName = m.name || slug;
                    document.getElementById('pt-mosque-name').textContent = mosqueName;
                    document.getElementById('print-mosque').textContent = mosqueName;

                    if (m.prayer_times && !m.prayer_times.error) {
                        const pt = m.prayer_times;
                        const labels = [['fajr','Fajr'],['sunrise','Sunrise'],['dhuhr','Dhuhr'],['asr','Asr'],['maghrib','Maghrib'],['isha','Isha']];
                        document.getElementById('today-grid').innerHTML = labels.map(([k,v]) => {
                            const adhan = T(pt[k]);
                            const jamat = pt[k+'_jamat'] ? T(pt[k+'_jamat']) : '';
                            return `<div class="ynj-prayer-row">
                                <span class="ynj-prayer-row__name">${v}</span>
                                <span class="ynj-prayer-row__time">${adhan}${jamat ? ' <span style="color:#00ADEF;font-size:12px;">Jam: '+jamat+'</span>' : ''}</span>
                            </div>`;
                        }).join('');
                    }
                });

            // Load Jumu'ah
            fetch(`${API}/mosques/${slug}/jumuah`)
                .then(r => r.json())
                .then(data => {
                    const slots = data.slots || [];
                    if (!slots.length) return;
                    document.getElementById('jumuah-card').style.display = '';
                    document.getElementById('jumuah-list').innerHTML = `
                        <table class="ynj-tt-table" style="font-size:13px;">
                            <thead><tr><th style="text-align:left;">Slot</th><th>Khutbah</th><th>Salah</th><th>Language</th></tr></thead>
                            <tbody>${slots.map(s => `<tr>
                                <td style="text-align:left;font-weight:600;">${s.slot_name}</td>
                                <td>${T(s.khutbah_time)}</td>
                                <td class="ynj-tt-jamat">${T(s.salah_time)}</td>
                                <td>${s.language||'—'}</td>
                            </tr>`).join('')}</tbody>
                        </table>`;
                });

            // Load Eid
            fetch(`${API}/mosques/${slug}/eid?year=${new Date().getFullYear()}`)
                .then(r => r.json())
                .then(data => {
                    const eids = data.eid_times || [];
                    if (!eids.length) return;
                    document.getElementById('eid-card').style.display = '';
                    const grouped = {};
                    eids.forEach(e => { (grouped[e.eid_type] = grouped[e.eid_type] || []).push(e); });
                    let html = '';
                    for (const [type, slots] of Object.entries(grouped)) {
                        const label = type === 'eid_ul_fitr' ? 'Eid ul-Fitr' : 'Eid ul-Adha';
                        html += `<h4 style="font-size:14px;font-weight:700;margin:12px 0 8px;">${label}</h4>`;
                        html += slots.map(s => `<div class="ynj-prayer-row">
                            <span class="ynj-prayer-row__name">${s.slot_name}</span>
                            <span class="ynj-prayer-row__time">${T(s.salah_time)}</span>
                            ${s.location_notes ? `<span class="ynj-text-muted" style="font-size:11px;display:block;">${s.location_notes}</span>` : ''}
                        </div>`).join('');
                    }
                    document.getElementById('eid-list').innerHTML = html;
                });

            // Load monthly timetable
            window.changeMonth = function(delta) {
                const [y, m] = currentMonth.split('-').map(Number);
                const d = new Date(y, m - 1 + delta, 1);
                currentMonth = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
                loadMonth();
            };

            function loadMonth() {
                const [y,m] = currentMonth.split('-');
                const months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
                document.getElementById('month-label').textContent = months[parseInt(m)] + ' ' + y;
                document.getElementById('print-month').textContent = 'Prayer Timetable — ' + months[parseInt(m)] + ' ' + y;
                document.getElementById('month-body').innerHTML = '<tr><td colspan="13" style="padding:20px;color:#999;">Loading...</td></tr>';

                fetch(`${API}/mosques/${slug}/prayers/month?month=${currentMonth}`)
                    .then(r => r.json())
                    .then(data => {
                        const days = data.days || [];
                        const today = new Date().toISOString().split('T')[0];
                        document.getElementById('month-body').innerHTML = days.map(d => {
                            const isToday = d.date === today;
                            const isFri = d.day === 'Fri';
                            const cls = isToday ? ' class="ynj-tt-today"' : (isFri ? ' class="ynj-tt-fri"' : '');
                            const dd = d.date.split('-')[2];
                            return `<tr${cls}>
                                <td class="ynj-tt-day">${dd}</td><td>${d.day}</td>
                                <td>${T(d.fajr)}</td><td class="ynj-tt-jamat">${T(d.fajr_jamat)}</td>
                                <td>${T(d.sunrise)}</td>
                                <td>${T(d.dhuhr)}</td><td class="ynj-tt-jamat">${T(d.dhuhr_jamat)}</td>
                                <td>${T(d.asr)}</td><td class="ynj-tt-jamat">${T(d.asr_jamat)}</td>
                                <td>${T(d.maghrib)}</td><td class="ynj-tt-jamat">${T(d.maghrib_jamat)}</td>
                                <td>${T(d.isha)}</td><td class="ynj-tt-jamat">${T(d.isha_jamat)}</td>
                            </tr>`;
                        }).join('');
                    })
                    .catch(() => {
                        document.getElementById('month-body').innerHTML = '<tr><td colspan="13" style="padding:20px;color:#999;">Failed to load.</td></tr>';
                    });
            }

            loadMonth();
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
        self::page_head( 'Services — YourJannah', 'Find Muslim professionals and masjid services near you.' );
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
            <!-- Search Bar -->
            <div class="ynj-search-bar">
                <input class="ynj-search-bar__input" id="svc-search" type="text" placeholder="Find a service (e.g. web developer, solicitor)..." autocomplete="off">
                <div class="ynj-search-bar__filters">
                    <select id="svc-type" class="ynj-search-bar__select">
                        <option value="">All Types</option>
                        <option>Imam / Scholar</option><option>Quran Teacher</option><option>Arabic Tutor</option>
                        <option>Counselling</option><option>Legal Services</option><option>Accounting</option>
                        <option>Web Development</option><option>SEO</option><option>Digital Marketing</option>
                        <option>IT Support</option><option>Graphic Design</option><option>Photography</option>
                        <option>Tutoring</option><option>Driving Instructor</option><option>Plumbing</option>
                        <option>Electrician</option><option>Cleaning</option><option>Catering</option>
                        <option>Financial Advice</option><option>Translation</option><option>Other</option>
                    </select>
                    <select id="svc-radius" class="ynj-search-bar__select">
                        <option value="0">My Mosque</option>
                        <option value="5">Within 5 miles</option>
                        <option value="10">Within 10 miles</option>
                        <option value="25">Within 25 miles</option>
                        <option value="50">Within 50 miles</option>
                        <option value="9999">Nationwide</option>
                    </select>
                </div>
            </div>

            <!-- Your Mosque Services -->
            <section class="ynj-card" id="local-services">
                <h2 class="ynj-card__title" id="local-title">Your Mosque</h2>
                <div id="local-svc-list" class="ynj-svc-grid"><p class="ynj-text-muted">Loading&hellip;</p></div>
            </section>

            <!-- Wider Community Results (hidden until search) -->
            <section class="ynj-card" id="community-services" style="display:none;">
                <h2 class="ynj-card__title" id="community-title">Wider Community</h2>
                <div id="community-svc-list" class="ynj-svc-grid"></div>
            </section>

            <!-- List your service CTA -->
            <div style="text-align:center;padding:16px 0;">
                <p class="ynj-text-muted" style="margin-bottom:8px;">Are you a professional? Get found by your community.</p>
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>/services/join" class="ynj-btn ynj-btn--outline">List Your Service — £10/mo</a>
            </div>
        </main>
        <?php self::render_bottom_nav( 'services', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            const API = '/wp-json/ynj/v1';
            let mosqueId = null;
            let userLat = null, userLng = null;

            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });

            const svcIcons = {
                'Imam / Scholar':'🕌','Quran Teacher':'📖','Arabic Tutor':'📚',
                'Counselling':'🤝','Legal Services':'⚖️','Accounting':'📊',
                'Web Development':'💻','SEO':'🔍','Digital Marketing':'📱',
                'IT Support':'🖥️','Graphic Design':'🎨','Photography':'📷',
                'Tutoring':'📚','Financial Advice':'💰','Catering':'🍽️',
                'Nikah':'💍','Funeral':'🕊️','Janazah':'🕊️','Translation':'🌐'
            };

            function renderCard(s, showMosque) {
                const icon = svcIcons[s.service_type] || '✦';
                const dist = s.distance_km != null && s.distance_km < 9000 ? `<span class="ynj-text-muted" style="font-size:11px;">📍 ${s.distance_km < 1.6 ? Math.round(s.distance_km*0.621*10)/10 + ' mi' : Math.round(s.distance_km*0.621) + ' mi'}</span>` : '';
                const mosque = showMosque && s.mosque_name ? `<span class="ynj-text-muted" style="font-size:11px;">🕌 ${s.mosque_name}${s.mosque_city ? ', '+s.mosque_city : ''}</span>` : '';
                return `<div class="ynj-svc-card">
                    <div class="ynj-svc-card__icon">${icon}</div>
                    <div class="ynj-svc-card__body">
                        <h4>${s.provider_name}</h4>
                        <span class="ynj-badge">${s.service_type}</span>
                        <p class="ynj-text-muted">${(s.description||'').length > 100 ? s.description.slice(0,100)+'...' : s.description||''}</p>
                        ${s.phone ? `<a href="tel:${s.phone}" class="ynj-svc-card__phone">${s.phone}</a>` : ''}
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">${dist}${mosque}${s.area_covered ? `<span class="ynj-text-muted" style="font-size:11px;">🗺️ ${s.area_covered}</span>` : ''}</div>
                    </div>
                </div>`;
            }

            // Try to get user location for distance calculations
            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(p => {
                    userLat = p.coords.latitude; userLng = p.coords.longitude;
                }, () => {}, {timeout:5000, maximumAge:300000});
            }

            // Load local mosque services
            fetch(`${API}/mosques/${slug}`)
                .then(r => r.json())
                .then(resp => {
                    const m = resp.mosque || resp;
                    mosqueId = m.id;
                    document.getElementById('local-title').textContent = m.name || 'Your Mosque';
                    if (!userLat && m.latitude) { userLat = m.latitude; userLng = m.longitude; }
                })
                .then(() => fetch(`${API}/mosques/${slug}/directory`))
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('local-svc-list');
                    const svcs = data.services || [];
                    if (!svcs.length) { list.innerHTML = '<p class="ynj-text-muted">No services listed at this mosque yet.</p>'; return; }
                    list.innerHTML = svcs.map(s => renderCard(s, false)).join('');
                })
                .catch(() => {
                    document.getElementById('local-svc-list').innerHTML = '<p class="ynj-text-muted">Could not load services.</p>';
                });

            // Search handlers
            let debounce;
            function doSearch() {
                clearTimeout(debounce);
                debounce = setTimeout(executeSearch, 300);
            }

            document.getElementById('svc-search').addEventListener('input', doSearch);
            document.getElementById('svc-type').addEventListener('change', doSearch);
            document.getElementById('svc-radius').addEventListener('change', doSearch);

            function executeSearch() {
                const q = document.getElementById('svc-search').value.trim();
                const type = document.getElementById('svc-type').value;
                const radiusMi = parseInt(document.getElementById('svc-radius').value);

                // If "My Mosque" selected and no search query, just show local
                if (radiusMi === 0 && !q && !type) {
                    document.getElementById('community-services').style.display = 'none';
                    return;
                }

                const radiusKm = radiusMi === 0 ? 0 : (radiusMi === 9999 ? 9999 : radiusMi * 1.609);
                const communityEl = document.getElementById('community-services');
                const communityList = document.getElementById('community-svc-list');

                communityEl.style.display = '';
                communityList.innerHTML = '<p class="ynj-text-muted">Searching...</p>';

                const params = new URLSearchParams();
                if (q) params.set('q', q);
                if (type) params.set('type', type);
                if (userLat) { params.set('lat', userLat); params.set('lng', userLng); }
                if (radiusKm > 0) params.set('radius_km', radiusKm);
                if (mosqueId) params.set('mosque_id', mosqueId);
                params.set('per_page', '30');

                fetch(`${API}/services/search?${params}`)
                    .then(r => r.json())
                    .then(data => {
                        const svcs = data.services || [];
                        // Filter out services from current mosque (already shown above)
                        const community = radiusMi === 0 ? svcs : svcs.filter(s => s.mosque_id !== mosqueId);

                        document.getElementById('community-title').textContent =
                            radiusMi === 9999 ? `Nationwide (${data.total} found)` :
                            radiusMi === 0 ? `Your Mosque (${svcs.length} found)` :
                            `Within ${radiusMi} miles (${data.total} found)`;

                        if (!community.length && !svcs.length) {
                            communityList.innerHTML = '<p class="ynj-text-muted">No services found. Try widening your search radius.</p>';
                        } else {
                            const toShow = radiusMi === 0 ? svcs : community;
                            communityList.innerHTML = toShow.map(s => renderCard(s, true)).join('');
                        }
                    })
                    .catch(() => {
                        communityList.innerHTML = '<p class="ynj-text-muted">Search failed. Try again.</p>';
                    });
            }
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Fundraising                                                 */
    /* ================================================================== */

    public static function render_fundraising( string $slug ): void {
        self::page_head( 'Fundraising — YourJannah', 'Support your masjid\'s fundraising campaigns.' );
        ?>
        <style>
        .ynj-campaign{background:rgba(255,255,255,.85);backdrop-filter:blur(8px);border-radius:16px;padding:0;margin-bottom:14px;overflow:hidden;border:1px solid rgba(255,255,255,.6);box-shadow:0 2px 12px rgba(0,0,0,.04);}
        .ynj-campaign__img{width:100%;height:140px;object-fit:cover;background:#e8f4f8;display:flex;align-items:center;justify-content:center;font-size:48px;}
        .ynj-campaign__body{padding:16px;}
        .ynj-campaign__body h3{font-size:16px;font-weight:700;margin-bottom:4px;}
        .ynj-campaign__body p{font-size:13px;color:#555;margin-bottom:12px;line-height:1.4;}
        .ynj-progress{height:10px;background:#e8f0f4;border-radius:5px;overflow:hidden;margin-bottom:8px;}
        .ynj-progress__bar{height:100%;border-radius:5px;background:linear-gradient(90deg,#00ADEF,#16a34a);transition:width .6s ease;}
        .ynj-campaign__stats{display:flex;justify-content:space-between;font-size:12px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;margin-bottom:12px;}
        .ynj-campaign__stats strong{color:<?php echo self::COLOR_TEXT; ?>;font-size:14px;}
        .ynj-campaign__cat{display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:6px;background:#e8f4f8;color:<?php echo self::COLOR_ACCENT; ?>;margin-bottom:8px;}
        </style>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span id="fr-header">Fundraising</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <div id="campaigns-list">
                <p class="ynj-text-muted" style="text-align:center;padding:20px;">Loading campaigns...</p>
            </div>
        </main>
        <?php self::render_bottom_nav( 'fundraising', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });

            const catIcons = {
                'general':'🕌','welfare':'🤲','expansion':'🏗️','renovation':'🔨',
                'education':'📖','youth':'👦','sisters':'👩','emergency':'🚨',
                'equipment':'🛠️','roof':'🏠','heating':'🔥','parking':'🅿️'
            };

            // Get mosque name
            fetch(`/wp-json/ynj/v1/mosques/${slug}`)
                .then(r => r.json())
                .then(resp => {
                    const m = resp.mosque || resp;
                    document.getElementById('fr-header').textContent = (m.name || 'Your Masjid') + ' Fundraising';
                })
                .catch(() => {});

            fetch(`/wp-json/ynj/v1/mosques/${slug}/campaigns`)
                .then(r => r.json())
                .then(data => {
                    const campaigns = data.campaigns || [];
                    const el = document.getElementById('campaigns-list');

                    if (!campaigns.length) {
                        el.innerHTML = '<div class="ynj-card" style="text-align:center;padding:40px 20px;"><div style="font-size:48px;margin-bottom:12px;">🕌</div><h3 style="margin-bottom:8px;">No Active Campaigns</h3><p class="ynj-text-muted">Your masjid has no fundraising campaigns right now. Check back soon.</p></div>';
                        return;
                    }

                    el.innerHTML = campaigns.map(c => {
                        const icon = catIcons[c.category] || '🕌';
                        const target = c.target_pence > 0 ? '£' + (c.target_pence/100).toLocaleString() : '';
                        const raised = '£' + (c.raised_pence/100).toLocaleString();
                        const pct = c.percentage || 0;
                        const donors = c.donor_count || 0;
                        const snippet = (c.description||'').length > 120 ? c.description.slice(0,120)+'...' : (c.description||'');
                        // Build donate URL: DFM link with campaign ref for tracking
                        const campaignRef = c.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                        let donateUrl = c.dfm_link || `https://donationformasjid.com/${slug}`;
                        // Append fund parameter so DFM can track which campaign this is for
                        const separator = donateUrl.includes('?') ? '&' : '?';
                        donateUrl += `${separator}fund=${encodeURIComponent(c.category)}&campaign_ref=${encodeURIComponent(campaignRef)}&campaign_id=${c.id}`;
                        const donateTarget = ' target="_blank" rel="noopener"';

                        const isRecurring = c.recurring || ['welfare','general'].includes(c.category);
                        const recurBadge = isRecurring
                            ? '<span style="display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px;background:#dbeafe;color:#1e40af;margin-left:6px;">🔄 Monthly</span>'
                            : '';
                        const targetLabel = isRecurring ? '/month' : '';

                        return `<div class="ynj-campaign">
                            <div class="ynj-campaign__img">${icon}</div>
                            <div class="ynj-campaign__body">
                                <span class="ynj-campaign__cat">${c.category}</span>${recurBadge}
                                <h3>${c.title}</h3>
                                <p>${snippet}</p>
                                ${target ? `<div class="ynj-progress"><div class="ynj-progress__bar" style="width:${pct}%"></div></div>` : ''}
                                <div class="ynj-campaign__stats">
                                    <div><strong>${raised}</strong> raised${target ? ' of '+target+targetLabel : ''}</div>
                                    <div><strong>${donors}</strong> donors</div>
                                    ${pct ? `<div><strong>${pct}%</strong></div>` : ''}
                                </div>
                                <a href="${donateUrl}"${donateTarget} class="ynj-btn" style="width:100%;justify-content:center;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                                    ${isRecurring ? 'Donate Monthly' : 'Donate Now'}
                                </a>
                            </div>
                        </div>`;
                    }).join('');
                })
                .catch(() => {
                    document.getElementById('campaigns-list').innerHTML = '<p class="ynj-text-muted" style="text-align:center;padding:20px;">Could not load campaigns.</p>';
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
        self::page_head( 'Sponsors — YourJannah', 'Muslim businesses supporting your community.' );
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
            <!-- Search Bar -->
            <div class="ynj-search-bar">
                <input class="ynj-search-bar__input" id="biz-search" type="text" placeholder="Find a business (e.g. restaurant, solicitor)..." autocomplete="off">
                <div class="ynj-search-bar__filters">
                    <select id="biz-category" class="ynj-search-bar__select">
                        <option value="">All Categories</option>
                        <option>Restaurant</option><option>Grocery</option><option>Butcher</option>
                        <option>Clothing</option><option>Books & Gifts</option><option>Health</option>
                        <option>Legal</option><option>Finance</option><option>Insurance</option>
                        <option>Travel</option><option>Education</option><option>Automotive</option>
                        <option>Catering</option><option>Property</option><option>Technology</option>
                    </select>
                    <select id="biz-radius" class="ynj-search-bar__select">
                        <option value="0">My Mosque</option>
                        <option value="5">Within 5 miles</option>
                        <option value="10">Within 10 miles</option>
                        <option value="25">Within 25 miles</option>
                        <option value="9999">Nationwide</option>
                    </select>
                </div>
            </div>

            <!-- Your Mosque Sponsors -->
            <section class="ynj-card" id="local-sponsors">
                <h2 class="ynj-card__title" id="local-biz-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="display:inline;vertical-align:-3px;margin-right:6px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Your Masjid Sponsors
                </h2>
                <div id="local-biz-list"><p class="ynj-text-muted">Loading&hellip;</p></div>
            </section>

            <!-- Wider Community (hidden until search) -->
            <section class="ynj-card" id="community-sponsors" style="display:none;">
                <h2 class="ynj-card__title" id="community-biz-title">Nearby Businesses</h2>
                <div id="community-biz-list"></div>
            </section>

            <div style="text-align:center;padding:16px 0;">
                <p class="ynj-text-muted" style="margin-bottom:8px;">Want to support your local masjid?</p>
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>/sponsors/join" class="ynj-btn">Become a Sponsor</a>
            </div>
        </main>
        <?php self::render_bottom_nav( 'sponsors', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            const API = '/wp-json/ynj/v1';
            let mosqueId = null, userLat = null, userLng = null;

            document.querySelectorAll('[data-nav-mosque]').forEach(el => {
                el.href = el.dataset.navMosque.replace('{slug}', slug);
            });

            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(p => {
                    userLat = p.coords.latitude; userLng = p.coords.longitude;
                }, () => {}, {timeout:5000, maximumAge:300000});
            }

            function renderBiz(b, rank, showMosque) {
                const dist = b.distance_km != null && b.distance_km < 9000 ? `📍 ${b.distance_km < 1.6 ? (b.distance_km*0.621).toFixed(1)+' mi' : Math.round(b.distance_km*0.621)+' mi'}` : '';
                const mosque = showMosque && b.mosque_name ? `🕌 ${b.mosque_name}` : '';
                const medal = rank && rank <= 3 ? ['🥇','🥈','🥉'][rank-1] : (rank ? '#'+rank : '');
                return `<div class="ynj-sponsor${rank<=3?' ynj-sponsor--'+(rank===1?'gold':rank===2?'silver':'bronze'):''}">
                    ${medal ? `<div class="ynj-sponsor__rank">${medal}</div>` : ''}
                    <div class="ynj-sponsor__body">
                        <h4>${b.business_name}</h4>
                        <span class="ynj-badge">${b.category}</span>
                        ${b.description ? `<p class="ynj-text-muted" style="margin-top:4px;">${b.description.length>100?b.description.slice(0,100)+'...':b.description}</p>` : ''}
                        <div class="ynj-sponsor__actions">
                            ${b.phone ? `<a href="tel:${b.phone}">${b.phone}</a>` : ''}
                            ${b.website ? `<a href="${b.website}" target="_blank" rel="noopener">Website</a>` : ''}
                        </div>
                        ${dist||mosque ? `<div style="display:flex;gap:12px;margin-top:6px;font-size:11px;color:#6b8fa3;">${dist ? `<span>${dist}</span>` : ''}${mosque ? `<span>${mosque}</span>` : ''}</div>` : ''}
                    </div>
                </div>`;
            }

            // Load local mosque sponsors
            fetch(`${API}/mosques/${slug}`)
                .then(r => r.json())
                .then(resp => { const m = resp.mosque||resp; mosqueId = m.id; if (!userLat && m.latitude) { userLat = m.latitude; userLng = m.longitude; } })
                .then(() => fetch(`${API}/mosques/${slug}/directory`))
                .then(r => r.json())
                .then(data => {
                    const biz = data.businesses || [];
                    const list = document.getElementById('local-biz-list');
                    if (!biz.length) { list.innerHTML = '<p class="ynj-text-muted">No sponsors yet. Be the first!</p>'; return; }
                    list.innerHTML = biz.map((b,i) => renderBiz(b, i+1, false)).join('');
                })
                .catch(() => { document.getElementById('local-biz-list').innerHTML = '<p class="ynj-text-muted">Could not load.</p>'; });

            // Search
            let debounce;
            function doSearch() { clearTimeout(debounce); debounce = setTimeout(executeSearch, 300); }
            document.getElementById('biz-search').addEventListener('input', doSearch);
            document.getElementById('biz-category').addEventListener('change', doSearch);
            document.getElementById('biz-radius').addEventListener('change', doSearch);

            function executeSearch() {
                const q = document.getElementById('biz-search').value.trim();
                const cat = document.getElementById('biz-category').value;
                const radiusMi = parseInt(document.getElementById('biz-radius').value);
                if (radiusMi === 0 && !q && !cat) { document.getElementById('community-sponsors').style.display = 'none'; return; }

                const el = document.getElementById('community-sponsors');
                const list = document.getElementById('community-biz-list');
                el.style.display = '';
                list.innerHTML = '<p class="ynj-text-muted">Searching...</p>';

                const params = new URLSearchParams();
                if (q) params.set('q', q);
                if (cat) params.set('category', cat);
                if (userLat) { params.set('lat', userLat); params.set('lng', userLng); }
                if (radiusMi > 0) params.set('radius_km', radiusMi === 9999 ? 9999 : radiusMi * 1.609);
                if (mosqueId) params.set('mosque_id', mosqueId);

                fetch(`${API}/businesses/search?${params}`)
                    .then(r => r.json())
                    .then(data => {
                        const biz = (data.businesses || []).filter(b => b.mosque_id !== mosqueId);
                        document.getElementById('community-biz-title').textContent =
                            radiusMi === 9999 ? `Nationwide (${data.total} found)` :
                            `Within ${radiusMi} miles (${data.total} found)`;
                        list.innerHTML = biz.length ? biz.map((b,i) => renderBiz(b, null, true)).join('')
                            : '<p class="ynj-text-muted">No businesses found. Try widening your search.</p>';
                    })
                    .catch(() => { list.innerHTML = '<p class="ynj-text-muted">Search failed.</p>'; });
            }
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
                    <a href="/mosque/<?php echo esc_attr( $slug ); ?>/rooms" class="ynj-more-item">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        <span>Room Bookings</span>
                    </a>
                    <a href="/mosque/<?php echo esc_attr( $slug ); ?>/contact" class="ynj-more-item">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
                        <span>Contact Mosque</span>
                    </a>
                    <a href="/profile" class="ynj-more-item">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>My Account</span>
                    </a>
                    <a href="/dashboard" class="ynj-more-item">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo self::COLOR_ACCENT; ?>" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        <span>Mosque Admin</span>
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
    /*  PAGE: Sponsor Signup                                              */
    /* ================================================================== */

    public static function render_sponsor_signup( string $slug ): void {
        $stripe_pk = YNJ_Stripe::public_key();
        self::page_head( 'Become a Sponsor — YourJannah', 'Support your local masjid with a business sponsorship.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>/sponsors" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Become a Sponsor</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <?php if ( isset( $_GET['payment'] ) && $_GET['payment'] === 'success' ) : ?>
                <section class="ynj-card" style="text-align:center;padding:40px 20px;">
                    <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
                    <h2 style="margin-bottom:8px;">You're a Sponsor!</h2>
                    <p class="ynj-text-muted">Your business listing is now live. Thank you for supporting your local masjid.</p>
                    <a href="/mosque/<?php echo esc_attr( $slug ); ?>/sponsors" class="ynj-btn" style="margin-top:20px;">View Sponsors</a>
                </section>
            <?php else : ?>
                <section class="ynj-card">
                    <h2 class="ynj-card__title">Business Details</h2>
                    <form id="sponsor-form" class="ynj-form">
                        <div class="ynj-field"><label>Business Name *</label><input type="text" name="business_name" required></div>
                        <div class="ynj-field"><label>Your Name</label><input type="text" name="owner_name"></div>
                        <div class="ynj-field"><label>Category *</label>
                            <select name="category" required>
                                <option value="">Select category...</option>
                                <option>Restaurant</option><option>Grocery</option><option>Butcher</option>
                                <option>Clothing</option><option>Books & Gifts</option><option>Health</option>
                                <option>Legal</option><option>Finance</option><option>Insurance</option>
                                <option>Travel</option><option>Education</option><option>Automotive</option>
                                <option>Catering</option><option>Property</option><option>Technology</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="ynj-field"><label>Description</label><textarea name="description" rows="3" placeholder="What does your business do?"></textarea></div>
                        <div class="ynj-field-row">
                            <div class="ynj-field"><label>Phone *</label><input type="tel" name="phone" required></div>
                            <div class="ynj-field"><label>Email</label><input type="email" name="email"></div>
                        </div>
                        <div class="ynj-field"><label>Website</label><input type="url" name="website" placeholder="https://"></div>
                        <div class="ynj-field"><label>Address</label><input type="text" name="address"></div>
                        <div class="ynj-field"><label>Postcode</label><input type="text" name="postcode"></div>
                    </form>
                </section>

                <section class="ynj-card">
                    <h2 class="ynj-card__title">Choose Your Plan</h2>
                    <div class="ynj-tier-grid">
                        <label class="ynj-tier">
                            <input type="radio" name="tier" value="standard" checked>
                            <div class="ynj-tier__body">
                                <div class="ynj-tier__price">&pound;30<span>/mo</span></div>
                                <div class="ynj-tier__name">Standard</div>
                                <p class="ynj-text-muted">Listed in the sponsors section</p>
                            </div>
                        </label>
                        <label class="ynj-tier">
                            <input type="radio" name="tier" value="featured">
                            <div class="ynj-tier__body">
                                <div class="ynj-tier__price">&pound;50<span>/mo</span></div>
                                <div class="ynj-tier__name">Featured</div>
                                <p class="ynj-text-muted">Higher placement + badge</p>
                            </div>
                        </label>
                        <label class="ynj-tier">
                            <input type="radio" name="tier" value="premium">
                            <div class="ynj-tier__body">
                                <div class="ynj-tier__price">&pound;100<span>/mo</span></div>
                                <div class="ynj-tier__name">Premium</div>
                                <p class="ynj-text-muted">Top placement + featured card</p>
                            </div>
                        </label>
                    </div>
                </section>

                <button class="ynj-btn" id="submit-sponsor" style="width:100%;justify-content:center;margin-bottom:20px;" type="button">
                    Continue to Payment
                </button>
                <p class="ynj-text-muted" style="text-align:center;" id="sponsor-error"></p>
            <?php endif; ?>
        </main>
        <?php self::render_bottom_nav( 'sponsors', $slug ); ?>
        <?php if ( ! isset( $_GET['payment'] ) ) : ?>
        <script>
        document.getElementById('submit-sponsor').addEventListener('click', async function() {
            const btn = this;
            const form = document.getElementById('sponsor-form');
            const name = form.querySelector('[name="business_name"]').value.trim();
            const phone = form.querySelector('[name="phone"]').value.trim();
            if (!name || !phone) { document.getElementById('sponsor-error').textContent = 'Business name and phone are required.'; return; }

            btn.disabled = true; btn.textContent = 'Processing...';
            const tier = document.querySelector('input[name="tier"]:checked').value;

            const body = {
                mosque_slug: <?php echo wp_json_encode( $slug ); ?>,
                business_name: name,
                owner_name: form.querySelector('[name="owner_name"]').value.trim(),
                category: form.querySelector('[name="category"]').value,
                description: form.querySelector('[name="description"]').value.trim(),
                phone: phone,
                email: form.querySelector('[name="email"]').value.trim(),
                website: form.querySelector('[name="website"]').value.trim(),
                address: form.querySelector('[name="address"]').value.trim(),
                postcode: form.querySelector('[name="postcode"]').value.trim(),
                tier: tier
            };

            try {
                const resp = await fetch('/wp-json/ynj/v1/stripe/checkout/business', {
                    method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
                });
                const data = await resp.json();
                if (data.ok && data.checkout_url) {
                    window.location.href = data.checkout_url;
                } else {
                    document.getElementById('sponsor-error').textContent = data.error || 'Something went wrong.';
                    btn.disabled = false; btn.textContent = 'Continue to Payment';
                }
            } catch(e) {
                document.getElementById('sponsor-error').textContent = 'Network error. Please try again.';
                btn.disabled = false; btn.textContent = 'Continue to Payment';
            }
        });
        </script>
        <?php endif; ?>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Service Signup                                              */
    /* ================================================================== */

    public static function render_service_signup( string $slug ): void {
        self::page_head( 'List Your Service — YourJannah', 'Advertise your professional service to the local Muslim community.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>/services" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>List Your Service</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <?php if ( isset( $_GET['payment'] ) && $_GET['payment'] === 'success' ) : ?>
                <section class="ynj-card" style="text-align:center;padding:40px 20px;">
                    <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
                    <h2 style="margin-bottom:8px;">You're Listed!</h2>
                    <p class="ynj-text-muted">Your service listing is now live. Local Muslims can find you through the app.</p>
                    <a href="/mosque/<?php echo esc_attr( $slug ); ?>/services" class="ynj-btn" style="margin-top:20px;">View Services</a>
                </section>
            <?php else : ?>
                <section class="ynj-card">
                    <h2 class="ynj-card__title">Your Service Details</h2>
                    <p class="ynj-text-muted" style="margin-bottom:16px;">&pound;10/month — reach local Muslims through the YourJannah app.</p>
                    <form id="service-form" class="ynj-form">
                        <div class="ynj-field"><label>Your Name / Business Name *</label><input type="text" name="provider_name" required></div>
                        <div class="ynj-field"><label>Service Type *</label>
                            <select name="service_type" required>
                                <option value="">Select type...</option>
                                <option>Web Development</option><option>SEO</option><option>Digital Marketing</option>
                                <option>Legal Services</option><option>Accounting</option><option>Financial Advice</option>
                                <option>IT Support</option><option>Graphic Design</option><option>Photography</option>
                                <option>Tutoring</option><option>Counselling</option><option>Translation</option>
                                <option>Driving Instructor</option><option>Plumbing</option><option>Electrician</option>
                                <option>Cleaning</option><option>Catering</option><option>Other</option>
                            </select>
                        </div>
                        <div class="ynj-field"><label>Description *</label><textarea name="description" rows="3" required placeholder="What service do you offer?"></textarea></div>
                        <div class="ynj-field-row">
                            <div class="ynj-field"><label>Phone *</label><input type="tel" name="phone" required></div>
                            <div class="ynj-field"><label>Email</label><input type="email" name="email"></div>
                        </div>
                        <div class="ynj-field"><label>Area Covered</label><input type="text" name="area_covered" placeholder="e.g. London, Birmingham, Remote"></div>
                    </form>
                </section>

                <button class="ynj-btn" id="submit-service" style="width:100%;justify-content:center;margin-bottom:20px;" type="button">
                    Pay &pound;10/mo &amp; Go Live
                </button>
                <p class="ynj-text-muted" style="text-align:center;" id="service-error"></p>
            <?php endif; ?>
        </main>
        <?php self::render_bottom_nav( 'services', $slug ); ?>
        <?php if ( ! isset( $_GET['payment'] ) ) : ?>
        <script>
        document.getElementById('submit-service').addEventListener('click', async function() {
            const btn = this; const form = document.getElementById('service-form');
            const name = form.querySelector('[name="provider_name"]').value.trim();
            const type = form.querySelector('[name="service_type"]').value;
            const desc = form.querySelector('[name="description"]').value.trim();
            const phone = form.querySelector('[name="phone"]').value.trim();
            if (!name || !type || !desc || !phone) { document.getElementById('service-error').textContent = 'Please fill in all required fields.'; return; }

            btn.disabled = true; btn.textContent = 'Processing...';
            try {
                const resp = await fetch('/wp-json/ynj/v1/stripe/checkout/service', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        mosque_slug: <?php echo wp_json_encode( $slug ); ?>,
                        provider_name: name, service_type: type, description: desc,
                        phone: phone, email: form.querySelector('[name="email"]').value.trim(),
                        area_covered: form.querySelector('[name="area_covered"]').value.trim()
                    })
                });
                const data = await resp.json();
                if (data.ok && data.checkout_url) { window.location.href = data.checkout_url; }
                else { document.getElementById('service-error').textContent = data.error || 'Something went wrong.'; btn.disabled = false; btn.textContent = 'Pay £10/mo & Go Live'; }
            } catch(e) { document.getElementById('service-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Pay £10/mo & Go Live'; }
        });
        </script>
        <?php endif; ?>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Room Booking                                                */
    /* ================================================================== */

    public static function render_rooms( string $slug ): void {
        self::page_head( 'Room Booking — YourJannah', 'Book a room at your local mosque.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>/directory" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Room Booking</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <?php if ( isset( $_GET['payment'] ) && $_GET['payment'] === 'success' ) : ?>
                <section class="ynj-card" style="text-align:center;padding:40px 20px;">
                    <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
                    <h2 style="margin-bottom:8px;">Room Booked!</h2>
                    <p class="ynj-text-muted">Your booking is confirmed. You'll receive details at your email.</p>
                </section>
            <?php else : ?>
                <div id="rooms-list"><p class="ynj-text-muted">Loading rooms...</p></div>

                <!-- Booking modal (hidden) -->
                <div class="ynj-modal" id="booking-modal" style="display:none;">
                    <div class="ynj-modal__content">
                        <h3 id="modal-room-name">Book Room</h3>
                        <form id="room-booking-form" class="ynj-form">
                            <input type="hidden" name="room_id" id="modal-room-id">
                            <div class="ynj-field"><label>Date *</label><input type="date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="ynj-field-row">
                                <div class="ynj-field"><label>Start Time *</label><input type="time" name="start_time" required></div>
                                <div class="ynj-field"><label>End Time *</label><input type="time" name="end_time" required></div>
                            </div>
                            <div class="ynj-field"><label>Your Name *</label><input type="text" name="user_name" required></div>
                            <div class="ynj-field-row">
                                <div class="ynj-field"><label>Email *</label><input type="email" name="user_email" required></div>
                                <div class="ynj-field"><label>Phone</label><input type="tel" name="user_phone"></div>
                            </div>
                            <div class="ynj-field"><label>Notes</label><textarea name="notes" rows="2" placeholder="Purpose of booking"></textarea></div>
                        </form>
                        <p id="modal-price" class="ynj-text-muted" style="margin:12px 0;"></p>
                        <div style="display:flex;gap:8px;">
                            <button class="ynj-btn" id="modal-submit" type="button" style="flex:1;justify-content:center;">Book Now</button>
                            <button class="ynj-btn ynj-btn--outline" type="button" onclick="document.getElementById('booking-modal').style.display='none'">Cancel</button>
                        </div>
                        <p class="ynj-text-muted" id="modal-error" style="margin-top:8px;"></p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
        <?php self::render_bottom_nav( 'more', $slug ); ?>
        <?php if ( ! isset( $_GET['payment'] ) ) : ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            let roomsData = [];

            fetch(`/wp-json/ynj/v1/mosques/${slug}/directory`)
                .then(r => r.json())
                .then(() => {
                    // Rooms aren't in directory endpoint — need admin API. Use a direct fetch.
                    // For now, use the mosque profile which we can extend.
                    // Actually rooms need their own endpoint. Let me fetch via ID.
                });

            // Fetch rooms via mosque profile + admin API workaround
            // We need a public rooms endpoint — for now fetch mosque then use ID
            fetch(`/wp-json/ynj/v1/mosques/${slug}`)
                .then(r => r.json())
                .then(resp => {
                    const m = resp.mosque || resp;
                    // Fetch rooms (they're only in admin API currently — we'll use the bookings endpoint context)
                    // TODO: Add public rooms endpoint. For now, render from seeded data.
                    return fetch(`/wp-json/ynj/v1/mosques/${m.id}/rooms`);
                })
                .then(r => r.ok ? r.json() : { rooms: [] })
                .then(data => {
                    roomsData = data.rooms || [];
                    renderRooms(roomsData);
                })
                .catch(() => {
                    document.getElementById('rooms-list').innerHTML = '<p class="ynj-text-muted">Could not load rooms.</p>';
                });

            function renderRooms(rooms) {
                const el = document.getElementById('rooms-list');
                if (!rooms.length) { el.innerHTML = '<p class="ynj-text-muted">No rooms available for booking.</p>'; return; }

                el.innerHTML = rooms.map(r => {
                    const hourly = r.hourly_rate_pence > 0 ? `£${(r.hourly_rate_pence/100).toFixed(0)}/hr` : 'Free';
                    const daily = r.daily_rate_pence > 0 ? `£${(r.daily_rate_pence/100).toFixed(0)}/day` : '';
                    return `<div class="ynj-card ynj-room-card">
                        <h3 style="font-size:16px;font-weight:600;margin-bottom:4px;">${r.name}</h3>
                        <p class="ynj-text-muted" style="margin-bottom:8px;">${r.description || ''}</p>
                        <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
                            <span class="ynj-badge">Capacity: ${r.capacity}</span>
                            <span style="font-weight:600;color:#00ADEF;">${hourly}</span>
                            ${daily ? `<span class="ynj-text-muted">${daily}</span>` : ''}
                        </div>
                        <button class="ynj-btn ynj-btn--outline" onclick="openBooking(${r.id}, '${r.name.replace(/'/g,"\\'")}', ${r.hourly_rate_pence})">Book This Room</button>
                    </div>`;
                }).join('');
            }

            window.openBooking = function(roomId, roomName, hourlyRate) {
                document.getElementById('modal-room-id').value = roomId;
                document.getElementById('modal-room-name').textContent = 'Book: ' + roomName;
                document.getElementById('modal-price').textContent = hourlyRate > 0
                    ? `Rate: £${(hourlyRate/100).toFixed(0)}/hour — payment via Stripe`
                    : 'This room is free to book.';
                document.getElementById('booking-modal').style.display = '';
                document.getElementById('modal-error').textContent = '';
            };

            document.getElementById('modal-submit').addEventListener('click', async function() {
                const btn = this;
                const form = document.getElementById('room-booking-form');
                const roomId = form.querySelector('[name="room_id"]').value;
                const date = form.querySelector('[name="booking_date"]').value;
                const start = form.querySelector('[name="start_time"]').value;
                const end = form.querySelector('[name="end_time"]').value;
                const name = form.querySelector('[name="user_name"]').value.trim();
                const email = form.querySelector('[name="user_email"]').value.trim();

                if (!date || !start || !end || !name || !email) {
                    document.getElementById('modal-error').textContent = 'Please fill in all required fields.';
                    return;
                }

                // Calculate hours
                const [sh,sm] = start.split(':').map(Number);
                const [eh,em] = end.split(':').map(Number);
                const hours = Math.max(1, Math.ceil(((eh*60+em) - (sh*60+sm)) / 60));

                btn.disabled = true; btn.textContent = 'Processing...';

                try {
                    const resp = await fetch('/wp-json/ynj/v1/stripe/checkout/room', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({
                            room_id: parseInt(roomId), hours: hours,
                            booking_date: date, start_time: start+':00', end_time: end+':00',
                            user_name: name, user_email: email,
                            user_phone: form.querySelector('[name="user_phone"]').value.trim(),
                            notes: form.querySelector('[name="notes"]').value.trim()
                        })
                    });
                    const data = await resp.json();
                    if (data.ok && data.checkout_url) { window.location.href = data.checkout_url; }
                    else if (data.ok && data.free) { window.location.href = `/mosque/${slug}/rooms?payment=success`; }
                    else { document.getElementById('modal-error').textContent = data.error || 'Booking failed.'; btn.disabled = false; btn.textContent = 'Book Now'; }
                } catch(e) { document.getElementById('modal-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Book Now'; }
            });
        })();
        </script>
        <?php endif; ?>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Event Detail + RSVP                                         */
    /* ================================================================== */

    public static function render_event_detail( string $slug, int $event_id ): void {
        self::page_head( 'Event — YourJannah', 'Event details and booking.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>/events" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Event</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <section class="ynj-card" id="event-detail">
                <p class="ynj-text-muted">Loading event...</p>
            </section>

            <section class="ynj-card" id="rsvp-section" style="display:none;">
                <h3 class="ynj-card__title" id="rsvp-title">RSVP</h3>
                <form id="rsvp-form" class="ynj-form">
                    <div class="ynj-field"><label>Your Name *</label><input type="text" name="user_name" required></div>
                    <div class="ynj-field-row">
                        <div class="ynj-field"><label>Email *</label><input type="email" name="user_email" required></div>
                        <div class="ynj-field"><label>Phone</label><input type="tel" name="user_phone"></div>
                    </div>
                </form>
                <button class="ynj-btn" id="rsvp-btn" type="button" style="width:100%;justify-content:center;margin-top:12px;">RSVP — Free</button>
                <p class="ynj-text-muted" id="rsvp-error" style="margin-top:8px;"></p>
            </section>

            <section class="ynj-card" id="rsvp-success" style="display:none;text-align:center;padding:30px 20px;">
                <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
                <h3>You're In!</h3>
                <p class="ynj-text-muted" id="rsvp-success-msg">See you there.</p>
            </section>
        </main>
        <?php self::render_bottom_nav( 'more', $slug ); ?>
        <script>
        (function(){
            const slug = <?php echo wp_json_encode( $slug ); ?>;
            const eventId = <?php echo (int) $event_id; ?>;
            let eventData = null;

            fetch(`/wp-json/ynj/v1/events/${eventId}`)
                .then(r => r.json())
                .then(resp => {
                    if (!resp.ok || !resp.event) {
                        document.getElementById('event-detail').innerHTML = '<p class="ynj-text-muted">Event not found.</p>';
                        return;
                    }
                    eventData = resp.event;
                    const e = eventData;
                    const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                    const endTime = e.end_time ? String(e.end_time).replace(/:\d{2}$/,'') : '';
                    const price = e.ticket_price_pence > 0 ? `£${(e.ticket_price_pence/100).toFixed(2)}` : 'Free';
                    const spots = e.spots_remaining !== null ? `${e.spots_remaining} spots remaining` : 'Unlimited capacity';

                    document.getElementById('event-detail').innerHTML = `
                        <span class="ynj-badge ynj-badge--event">${e.event_type || 'Event'}</span>
                        <h2 style="font-size:20px;font-weight:700;margin:8px 0 4px;">${e.title}</h2>
                        <div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;font-size:13px;color:#6b8fa3;">
                            <span>📅 ${e.event_date}</span>
                            <span>🕐 ${time}${endTime ? ' — '+endTime : ''}</span>
                            ${e.location ? `<span>📍 ${e.location}</span>` : ''}
                        </div>
                        <p style="margin:12px 0;line-height:1.6;">${e.description || ''}</p>
                        <div style="display:flex;gap:16px;margin-top:16px;">
                            <span class="ynj-badge">${price}</span>
                            <span class="ynj-text-muted">${spots}</span>
                        </div>
                    `;

                    // Show RSVP section
                    if (e.spots_remaining === 0) {
                        document.getElementById('rsvp-section').style.display = '';
                        document.getElementById('rsvp-section').innerHTML = '<p style="text-align:center;font-weight:600;color:#dc2626;">This event is fully booked.</p>';
                    } else {
                        document.getElementById('rsvp-section').style.display = '';
                        const btnText = e.ticket_price_pence > 0 ? `Buy Ticket — ${price}` : 'RSVP — Free';
                        document.getElementById('rsvp-btn').textContent = btnText;
                        document.getElementById('rsvp-title').textContent = e.ticket_price_pence > 0 ? 'Buy Ticket' : 'RSVP';
                    }
                })
                .catch(() => {
                    document.getElementById('event-detail').innerHTML = '<p class="ynj-text-muted">Could not load event.</p>';
                });

            document.getElementById('rsvp-btn').addEventListener('click', async function() {
                const btn = this; const form = document.getElementById('rsvp-form');
                const name = form.querySelector('[name="user_name"]').value.trim();
                const email = form.querySelector('[name="user_email"]').value.trim();
                if (!name || !email) { document.getElementById('rsvp-error').textContent = 'Name and email required.'; return; }

                btn.disabled = true; btn.textContent = 'Processing...';
                try {
                    const resp = await fetch('/wp-json/ynj/v1/stripe/checkout/event', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({
                            event_id: eventId, user_name: name, user_email: email,
                            user_phone: form.querySelector('[name="user_phone"]').value.trim()
                        })
                    });
                    const data = await resp.json();
                    if (data.ok && data.checkout_url) { window.location.href = data.checkout_url; }
                    else if (data.ok && data.free) {
                        document.getElementById('rsvp-section').style.display = 'none';
                        document.getElementById('rsvp-success').style.display = '';
                        document.getElementById('rsvp-success-msg').textContent = data.message || 'See you there!';
                    }
                    else { document.getElementById('rsvp-error').textContent = data.error || 'Failed.'; btn.disabled = false; btn.textContent = 'Try Again'; }
                } catch(e) { document.getElementById('rsvp-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Try Again'; }
            });
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Contact / Enquiry                                           */
    /* ================================================================== */

    public static function render_contact( string $slug ): void {
        self::page_head( 'Contact — YourJannah', 'Send an enquiry to your local mosque.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>/directory" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Contact Mosque</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <section class="ynj-card" id="contact-form-card">
                <h2 class="ynj-card__title">Send an Enquiry</h2>
                <form id="contact-form" class="ynj-form">
                    <div class="ynj-field"><label>Your Name *</label><input type="text" name="name" required></div>
                    <div class="ynj-field-row">
                        <div class="ynj-field"><label>Email *</label><input type="email" name="email" required></div>
                        <div class="ynj-field"><label>Phone</label><input type="tel" name="phone"></div>
                    </div>
                    <div class="ynj-field"><label>Enquiry Type</label>
                        <select name="type">
                            <option value="general">General</option>
                            <option value="nikah">Nikah / Marriage</option>
                            <option value="janazah">Funeral / Janazah</option>
                            <option value="room_booking">Room Booking</option>
                            <option value="classes">Classes / Education</option>
                            <option value="volunteer">Volunteering</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="ynj-field"><label>Subject</label><input type="text" name="subject" placeholder="Brief subject line"></div>
                    <div class="ynj-field"><label>Message *</label><textarea name="message" rows="5" required placeholder="Your message to the mosque..."></textarea></div>
                </form>
                <button class="ynj-btn" id="submit-contact" type="button" style="width:100%;justify-content:center;margin-top:12px;">Send Enquiry</button>
                <p class="ynj-text-muted" id="contact-error" style="margin-top:8px;"></p>
            </section>

            <section class="ynj-card" id="contact-success" style="display:none;text-align:center;padding:40px 20px;">
                <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
                <h2 style="margin-bottom:8px;">Enquiry Sent</h2>
                <p class="ynj-text-muted">The mosque will respond to your email. Jazakallah khayr.</p>
                <a href="/mosque/<?php echo esc_attr( $slug ); ?>" class="ynj-btn" style="margin-top:20px;">Back to Mosque</a>
            </section>
        </main>
        <?php self::render_bottom_nav( 'more', $slug ); ?>
        <script>
        document.getElementById('submit-contact').addEventListener('click', async function() {
            const btn = this; const form = document.getElementById('contact-form');
            const name = form.querySelector('[name="name"]').value.trim();
            const email = form.querySelector('[name="email"]').value.trim();
            const message = form.querySelector('[name="message"]').value.trim();
            if (!name || !email || !message) { document.getElementById('contact-error').textContent = 'Name, email, and message required.'; return; }

            btn.disabled = true; btn.textContent = 'Sending...';
            try {
                const resp = await fetch('/wp-json/ynj/v1/enquiries', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        mosque_slug: <?php echo wp_json_encode( $slug ); ?>,
                        name: name, email: email, message: message,
                        phone: form.querySelector('[name="phone"]').value.trim(),
                        type: form.querySelector('[name="type"]').value,
                        subject: form.querySelector('[name="subject"]').value.trim()
                    })
                });
                const data = await resp.json();
                if (data.ok) {
                    document.getElementById('contact-form-card').style.display = 'none';
                    document.getElementById('contact-success').style.display = '';
                } else { document.getElementById('contact-error').textContent = data.error || 'Failed to send.'; btn.disabled = false; btn.textContent = 'Send Enquiry'; }
            } catch(e) { document.getElementById('contact-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Send Enquiry'; }
        });
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Live Events                                                 */
    /* ================================================================== */

    public static function render_live_events(): void {
        self::page_head( 'Live Events — YourJannah', 'Watch live Islamic events and talks.' );
        ?>
        <style>
        .ynj-live-card{background:rgba(255,255,255,.9);border-radius:16px;overflow:hidden;margin-bottom:16px;border:1px solid rgba(255,255,255,.6);box-shadow:0 2px 12px rgba(0,0,0,.06);}
        .ynj-live-card--live{border:2px solid #dc2626;}
        .ynj-live-badge{display:inline-flex;align-items:center;gap:4px;background:#dc2626;color:#fff;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
        .ynj-live-badge::before{content:'';width:8px;height:8px;background:#fff;border-radius:50%;animation:livePulse 1.5s ease-in-out infinite;}
        @keyframes livePulse{0%,100%{opacity:1}50%{opacity:.3}}
        .ynj-live-badge--upcoming{background:#f59e0b;}
        .ynj-live-badge--upcoming::before{display:none;}
        .ynj-live-card__video{width:100%;aspect-ratio:16/9;background:#000;display:flex;align-items:center;justify-content:center;position:relative;}
        .ynj-live-card__video iframe{width:100%;height:100%;border:none;}
        .ynj-live-card__body{padding:16px;}
        .ynj-live-card__body h3{font-size:16px;font-weight:700;margin:8px 0 4px;}
        .ynj-live-card__meta{display:flex;flex-wrap:wrap;gap:8px;font-size:12px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;margin-bottom:12px;}
        .ynj-live-card__mosque{font-size:12px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;margin-bottom:8px;}
        .ynj-donate-inline{display:flex;gap:8px;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid #f0f0ec;}
        .ynj-donate-inline select,.ynj-donate-inline input{padding:8px 12px;border:1px solid #e0e8ed;border-radius:8px;font-size:13px;font-family:inherit;}
        .ynj-donate-inline select{width:auto;}
        @media(min-width:900px){.ynj-live-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}.ynj-live-card--featured{grid-column:1/-1;}.ynj-live-card--featured .ynj-live-card__video{aspect-ratio:21/9;}}
        </style>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Live Events</span></div>
            </div>
        </header>
        <main class="ynj-main">
            <div class="ynj-feed-tabs" style="margin-bottom:16px;">
                <button class="ynj-feed-tab ynj-feed-tab--active" id="lt-all" onclick="filterLive('all')">All</button>
                <button class="ynj-feed-tab" id="lt-live" onclick="filterLive('live')">🔴 Live Now</button>
                <button class="ynj-feed-tab" id="lt-upcoming" onclick="filterLive('upcoming')">📅 Upcoming</button>
            </div>
            <div class="ynj-live-grid" id="live-list">
                <p class="ynj-text-muted" style="padding:20px;text-align:center;">Loading live events...</p>
            </div>
        </main>
        <?php self::render_bottom_nav( 'home' ); ?>
        <script>
        (function(){
            const API = '/wp-json/ynj/v1';
            let allEvents = [];

            function getYoutubeEmbed(url) {
                if (!url) return '';
                const m = url.match(/(?:youtube\.com\/(?:watch\?v=|live\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
                return m ? `<iframe src="https://www.youtube.com/embed/${m[1]}?autoplay=0" allow="autoplay;encrypted-media" allowfullscreen></iframe>` : `<a href="${url}" target="_blank" rel="noopener" style="color:#fff;font-size:14px;">▶ Open Live Stream</a>`;
            }

            function renderCard(e, i) {
                const isLive = e.is_live;
                const badge = isLive
                    ? '<span class="ynj-live-badge">LIVE</span>'
                    : '<span class="ynj-live-badge ynj-live-badge--upcoming">UPCOMING</span>';
                const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                const featured = i === 0 && isLive ? ' ynj-live-card--featured' : '';
                const video = isLive && e.live_url ? getYoutubeEmbed(e.live_url) : '<div style="color:#999;font-size:14px;">📅 ' + (e.event_date||'') + ' at ' + time + '</div>';

                const donTarget = e.donation_target_pence > 0 ? '£' + (e.donation_target_pence/100).toLocaleString() : '';
                const donRaised = e.donation_raised_pence > 0 ? '£' + (e.donation_raised_pence/100).toLocaleString() : '£0';
                const donPct = e.donation_target_pence > 0 ? Math.min(100, Math.round(e.donation_raised_pence / e.donation_target_pence * 100)) : 0;

                const donateSection = `<div class="ynj-donate-inline">
                    <select id="don-amt-${e.id}">
                        <option value="500">£5</option><option value="1000">£10</option>
                        <option value="2000" selected>£20</option><option value="5000">£50</option>
                        <option value="10000">£100</option>
                    </select>
                    <button class="ynj-btn" style="padding:8px 16px;font-size:13px;" onclick="donateToEvent(${e.id})">
                        ❤️ Donate
                    </button>
                    <span style="font-size:11px;color:#6b8fa3;margin-left:auto;">${donRaised} raised${donTarget ? ' / '+donTarget : ''} · ${e.donation_count} donors</span>
                </div>`;

                return `<div class="ynj-live-card${isLive?' ynj-live-card--live':''}${featured}">
                    <div class="ynj-live-card__video">${video}</div>
                    <div class="ynj-live-card__body">
                        ${badge}
                        <h3>${e.title}</h3>
                        <div class="ynj-live-card__mosque">🕌 ${e.mosque_name||''} ${e.mosque_city ? '· '+e.mosque_city : ''}</div>
                        <div class="ynj-live-card__meta">
                            ${e.event_date ? '<span>📅 '+e.event_date+'</span>' : ''}
                            ${time ? '<span>🕐 '+time+'</span>' : ''}
                            ${e.event_type ? '<span>'+e.event_type+'</span>' : ''}
                            ${e.registered_count > 0 ? '<span>👥 '+e.registered_count+' watching</span>' : ''}
                        </div>
                        <p style="font-size:13px;color:#555;margin-bottom:8px;">${(e.description||'').slice(0,120)}${(e.description||'').length>120?'...':''}</p>
                        ${!isLive && e.live_url ? '<a href="'+e.live_url+'" target="_blank" rel="noopener" class="ynj-btn ynj-btn--outline" style="width:100%;justify-content:center;margin-bottom:8px;">🔔 Set Reminder</a>' : ''}
                        ${isLive && e.live_url ? '<a href="'+e.live_url+'" target="_blank" rel="noopener" class="ynj-btn" style="width:100%;justify-content:center;margin-bottom:8px;background:#dc2626;">▶ Watch Live</a>' : ''}
                        ${donateSection}
                    </div>
                </div>`;
            }

            fetch(`${API}/events/live`)
                .then(r => r.json())
                .then(data => {
                    allEvents = data.events || [];
                    renderEvents('all');
                })
                .catch(() => {
                    document.getElementById('live-list').innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;">Could not load live events.</p>';
                });

            function renderEvents(filter) {
                const el = document.getElementById('live-list');
                let events = allEvents;
                if (filter === 'live') events = events.filter(e => e.is_live);
                if (filter === 'upcoming') events = events.filter(e => !e.is_live);

                if (!events.length) {
                    el.innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;">' +
                        (filter === 'live' ? 'No events are live right now. Check back soon!' : 'No upcoming online events.') + '</p>';
                    return;
                }
                el.innerHTML = events.map((e, i) => renderCard(e, i)).join('');
            }

            window.filterLive = function(f) {
                ['all','live','upcoming'].forEach(t => {
                    document.getElementById('lt-'+t).classList.toggle('ynj-feed-tab--active', t===f);
                });
                renderEvents(f);
            };

            window.donateToEvent = function(eventId) {
                const amt = document.getElementById('don-amt-'+eventId).value;
                fetch(`${API}/events/${eventId}/donate`, {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({amount_pence: parseInt(amt)})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok && data.checkout_url) window.location.href = data.checkout_url;
                    else alert(data.error || 'Could not process donation.');
                })
                .catch(() => alert('Network error.'));
            };
        })();
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Login                                                       */
    /* ================================================================== */

    public static function render_login(): void {
        self::page_head( 'Login — YourJannah', 'Sign in to your YourJannah account.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Sign In</span></div>
            </div>
        </header>
        <main class="ynj-main" style="padding-top:24px;">
            <section class="ynj-card" style="text-align:center;padding:32px 20px 20px;">
                <div style="font-size:36px;margin-bottom:8px;">🕌</div>
                <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;">Welcome Back</h2>
                <p class="ynj-text-muted" style="margin-bottom:24px;">Sign in to see your bookings and get personalised prayer reminders.</p>
            </section>
            <section class="ynj-card">
                <form id="login-form" class="ynj-form">
                    <div class="ynj-field"><label>Email</label><input type="email" name="email" required placeholder="your@email.com"></div>
                    <div class="ynj-field"><label>Password</label><input type="password" name="password" required placeholder="Min 6 characters"></div>
                </form>
                <button class="ynj-btn" id="login-btn" type="button" style="width:100%;justify-content:center;margin-top:16px;">Sign In</button>
                <p class="ynj-text-muted" id="login-error" style="margin-top:8px;text-align:center;"></p>
                <p style="text-align:center;margin-top:16px;font-size:13px;">Don't have an account? <a href="/register" style="font-weight:700;">Create one</a></p>
            </section>
        </main>
        <script>
        document.getElementById('login-btn').addEventListener('click', async function() {
            const btn = this; const form = document.getElementById('login-form');
            const email = form.querySelector('[name="email"]').value.trim();
            const password = form.querySelector('[name="password"]').value;
            if (!email || !password) { document.getElementById('login-error').textContent = 'Email and password required.'; return; }
            btn.disabled = true; btn.textContent = 'Signing in...';
            try {
                const resp = await fetch('/wp-json/ynj/v1/auth/login', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({email, password})
                });
                const data = await resp.json();
                if (data.ok && data.token) {
                    localStorage.setItem('ynj_user_token', data.token);
                    if (data.user) localStorage.setItem('ynj_user', JSON.stringify(data.user));
                    window.location.href = '/profile';
                } else {
                    document.getElementById('login-error').textContent = data.error || 'Login failed.';
                    btn.disabled = false; btn.textContent = 'Sign In';
                }
            } catch(e) { document.getElementById('login-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Sign In'; }
        });
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: Register                                                    */
    /* ================================================================== */

    public static function render_register(): void {
        self::page_head( 'Create Account — YourJannah', 'Join YourJannah to get prayer reminders and book events.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>Create Account</span></div>
            </div>
        </header>
        <main class="ynj-main" style="padding-top:24px;">
            <section class="ynj-card" style="text-align:center;padding:32px 20px 20px;">
                <div style="font-size:36px;margin-bottom:8px;">🕌</div>
                <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;">Join YourJannah</h2>
                <p class="ynj-text-muted" style="margin-bottom:8px;">Get personalised prayer reminders, save your mosque, and manage your bookings.</p>
            </section>
            <section class="ynj-card">
                <form id="reg-form" class="ynj-form">
                    <div class="ynj-field"><label>Your Name *</label><input type="text" name="name" required placeholder="Full name"></div>
                    <div class="ynj-field"><label>Email *</label><input type="email" name="email" required placeholder="your@email.com"></div>
                    <div class="ynj-field"><label>Phone</label><input type="tel" name="phone" placeholder="07xxx xxxxxx"></div>
                    <div class="ynj-field"><label>Password *</label><input type="password" name="password" required placeholder="Min 6 characters"></div>
                </form>
                <button class="ynj-btn" id="reg-btn" type="button" style="width:100%;justify-content:center;margin-top:16px;">Create Account</button>
                <p class="ynj-text-muted" id="reg-error" style="margin-top:8px;text-align:center;"></p>
                <p style="text-align:center;margin-top:16px;font-size:13px;">Already have an account? <a href="/login" style="font-weight:700;">Sign in</a></p>
            </section>
        </main>
        <script>
        document.getElementById('reg-btn').addEventListener('click', async function() {
            const btn = this; const form = document.getElementById('reg-form');
            const name = form.querySelector('[name="name"]').value.trim();
            const email = form.querySelector('[name="email"]').value.trim();
            const password = form.querySelector('[name="password"]').value;
            if (!name || !email || !password) { document.getElementById('reg-error').textContent = 'Name, email, and password required.'; return; }
            if (password.length < 6) { document.getElementById('reg-error').textContent = 'Password must be at least 6 characters.'; return; }
            btn.disabled = true; btn.textContent = 'Creating account...';
            try {
                const resp = await fetch('/wp-json/ynj/v1/auth/register', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({name, email, password, phone: form.querySelector('[name="phone"]').value.trim()})
                });
                const data = await resp.json();
                if (data.ok && data.token) {
                    localStorage.setItem('ynj_user_token', data.token);
                    window.location.href = '/profile';
                } else {
                    document.getElementById('reg-error').textContent = data.error || 'Registration failed.';
                    btn.disabled = false; btn.textContent = 'Create Account';
                }
            } catch(e) { document.getElementById('reg-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Create Account'; }
        });
        </script>
        </body></html>
        <?php
        exit;
    }

    /* ================================================================== */
    /*  PAGE: User Profile                                                */
    /* ================================================================== */

    public static function render_profile(): void {
        self::page_head( 'My Account — YourJannah', 'Manage your profile, bookings, and preferences.' );
        ?>
        <header class="ynj-header">
            <div class="ynj-header__inner">
                <a href="/" class="ynj-back" aria-label="Back">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div class="ynj-logo"><span>My Account</span></div>
            </div>
        </header>
        <main class="ynj-main" id="profile-main">
            <p class="ynj-text-muted" style="text-align:center;padding:40px 0;">Loading...</p>
        </main>
        <?php self::render_bottom_nav( 'more' ); ?>
        <script>
        (function(){
            const API = '/wp-json/ynj/v1';
            const token = localStorage.getItem('ynj_user_token');

            if (!token) { window.location.href = '/login'; return; }

            const headers = {'Content-Type':'application/json','Authorization':'Bearer '+token};

            async function load() {
                const main = document.getElementById('profile-main');

                // Fetch profile
                const profileResp = await fetch(`${API}/auth/me`, {headers}).then(r=>r.json()).catch(()=>({ok:false}));
                if (!profileResp.ok) { localStorage.removeItem('ynj_user_token'); window.location.href = '/login'; return; }
                const user = profileResp.user;

                // Fetch bookings
                const bookingsResp = await fetch(`${API}/auth/bookings`, {headers}).then(r=>r.json()).catch(()=>({bookings:[]}));
                const bookings = bookingsResp.bookings || [];

                main.innerHTML = `
                    <section class="ynj-card">
                        <div style="text-align:center;margin-bottom:16px;">
                            <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#00ADEF,#0090d0);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;margin:0 auto 8px;">${user.name.charAt(0).toUpperCase()}</div>
                            <h2 style="font-size:18px;font-weight:700;">${user.name}</h2>
                            <p class="ynj-text-muted">${user.email}</p>
                        </div>
                    </section>

                    <section class="ynj-card">
                        <h3 class="ynj-card__title">Prayer Preferences</h3>
                        <form id="pref-form" class="ynj-form">
                            <div class="ynj-field-row">
                                <div class="ynj-field">
                                    <label>Travel Mode</label>
                                    <select name="travel_mode">
                                        <option value="walk" ${user.travel_mode==='walk'?'selected':''}>Walking</option>
                                        <option value="drive" ${user.travel_mode==='drive'?'selected':''}>Driving</option>
                                    </select>
                                </div>
                                <div class="ynj-field">
                                    <label>Travel Time (min)</label>
                                    <input type="number" name="travel_minutes" value="${user.travel_minutes||''}" placeholder="e.g. 15" min="0" max="120">
                                </div>
                            </div>
                            <div class="ynj-field">
                                <label>Alert Before Prayer (minutes)</label>
                                <select name="alert_before_minutes">
                                    <option value="10" ${user.alert_before_minutes===10?'selected':''}>10 minutes</option>
                                    <option value="15" ${user.alert_before_minutes===15?'selected':''}>15 minutes</option>
                                    <option value="20" ${user.alert_before_minutes===20?'selected':''}>20 minutes (default)</option>
                                    <option value="30" ${user.alert_before_minutes===30?'selected':''}>30 minutes</option>
                                    <option value="45" ${user.alert_before_minutes===45?'selected':''}>45 minutes</option>
                                </select>
                            </div>
                        </form>
                        <button class="ynj-btn ynj-btn--outline" id="save-prefs" type="button" style="width:100%;justify-content:center;">Save Preferences</button>
                    </section>

                    <section class="ynj-card">
                        <h3 class="ynj-card__title">My Bookings (${bookings.length})</h3>
                        <div class="ynj-feed" id="bookings-list">
                            ${bookings.length ? bookings.map(b => {
                                const badge = b.status==='confirmed'?'green':(b.status==='pending'||b.status==='pending_payment'?'yellow':'red');
                                const title = b.type==='event' ? (b.event_title||'Event') : (b.room_name||'Room');
                                const time = b.start_time ? b.start_time.substring(0,5) : '';
                                return `<div class="ynj-feed-item">
                                    <div class="ynj-feed-item__head">
                                        <span class="ynj-badge ynj-badge--${b.type==='event'?'event':''}"">${b.type==='event'?'Event':'Room'}</span>
                                        <h4>${title}</h4>
                                    </div>
                                    <span class="ynj-feed-meta">${b.booking_date||''} · ${time}${b.mosque_name ? ' · '+b.mosque_name : ''}</span>
                                    <span class="ynj-badge" style="margin-top:4px;background:${badge==='green'?'#dcfce7':badge==='yellow'?'#fef3c7':'#fee2e2'};color:${badge==='green'?'#166534':badge==='yellow'?'#92400e':'#991b1b'}">${b.status}</span>
                                </div>`;
                            }).join('') : '<p class="ynj-text-muted">No bookings yet. Browse events and rooms to get started.</p>'}
                        </div>
                    </section>

                    <div style="text-align:center;padding:16px 0;">
                        <button class="ynj-btn ynj-btn--outline" onclick="localStorage.removeItem('ynj_user_token');localStorage.removeItem('ynj_user');window.location.href='/';" style="color:#dc2626;border-color:#dc2626;">Logout</button>
                    </div>
                `;

                // Save preferences handler
                document.getElementById('save-prefs').addEventListener('click', async function() {
                    const btn = this; const form = document.getElementById('pref-form');
                    btn.disabled = true; btn.textContent = 'Saving...';
                    const resp = await fetch(`${API}/auth/me`, {
                        method: 'PUT', headers,
                        body: JSON.stringify({
                            travel_mode: form.querySelector('[name="travel_mode"]').value,
                            travel_minutes: parseInt(form.querySelector('[name="travel_minutes"]').value) || 0,
                            alert_before_minutes: parseInt(form.querySelector('[name="alert_before_minutes"]').value) || 20,
                        })
                    }).then(r=>r.json());
                    btn.disabled = false; btn.textContent = 'Save Preferences';
                    if (resp.ok) { btn.textContent = 'Saved ✓'; setTimeout(()=>{ btn.textContent = 'Save Preferences'; }, 2000); }
                });
            }

            load();
        })();
        </script>
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
@media(min-width:900px){.ynj-main{max-width:1100px;padding:20px 24px;}}
@media(min-width:1200px){.ynj-main{max-width:1280px;}}

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

/* Sponsor Ticker */
.ynj-ticker{
    display:flex;align-items:center;gap:8px;
    background:rgba(255,255,255,.7);border:1px solid rgba(0,173,239,.12);
    border-radius:10px;padding:8px 12px;margin-bottom:10px;overflow:hidden;
}
.ynj-ticker__label{font-size:10px;font-weight:700;color:#f59e0b;white-space:nowrap;flex-shrink:0;}
.ynj-ticker__track{flex:1;overflow:hidden;position:relative;height:18px;}
.ynj-ticker__slide{
    display:flex;gap:24px;white-space:nowrap;font-size:12px;font-weight:600;
    color:<?php echo self::COLOR_TEXT; ?>;position:absolute;top:0;left:0;
    animation:tickerScroll 20s linear infinite;
}
.ynj-ticker__slide a{color:<?php echo self::COLOR_TEXT; ?>;text-decoration:none;}
.ynj-ticker__slide a:hover{color:<?php echo self::COLOR_ACCENT; ?>;}
.ynj-ticker__item{display:inline-flex;align-items:center;gap:6px;}
.ynj-ticker__item .ynj-ticker__rank{color:#f59e0b;font-size:11px;}
.ynj-ticker__item .ynj-ticker__cat{color:<?php echo self::COLOR_TEXT_MUTED; ?>;font-size:10px;font-weight:500;}
@keyframes tickerScroll{
    0%{transform:translateX(0)}
    100%{transform:translateX(-50%)}
}

/* Travel Settings */
.ynj-travel-settings{margin-bottom:10px;}
.ynj-travel-settings__row{display:flex;gap:8px;}
.ynj-ts-select{
    flex:1;padding:8px 10px;border:1px solid #e0e8ed;border-radius:10px;
    font-size:13px;font-family:inherit;background:#fff;color:<?php echo self::COLOR_TEXT; ?>;
    outline:none;cursor:pointer;appearance:none;-webkit-appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b8fa3' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 10px center;padding-right:28px;
}

/* Navigate buttons */
.ynj-nav-buttons{display:flex;gap:10px;justify-content:center;position:relative;z-index:1;}
.ynj-btn--navigate{
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);
    color:#fff;border-radius:12px;padding:10px 20px;font-size:13px;font-weight:700;
    cursor:pointer;transition:all .2s;text-decoration:none;box-shadow:none;
}
.ynj-btn--navigate:active{background:rgba(255,255,255,.3);transform:scale(.97);}
.ynj-btn--navigate svg{display:inline;}

/* Urgency states */
.ynj-hero--urgent{background:linear-gradient(180deg,#92400e 0%,#d97706 40%,#f59e0b 100%) !important;}
.ynj-hero--critical{background:linear-gradient(180deg,#991b1b 0%,#dc2626 40%,#ef4444 100%) !important;}
.ynj-hero--critical .ynj-leave-by{animation:urgencyPulse 1s ease-in-out infinite;}
@keyframes urgencyPulse{0%,100%{opacity:1}50%{opacity:.5}}

/* Donate button */
.ynj-donate-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;
    background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;
    border-radius:14px;padding:14px;margin-bottom:10px;
    font-size:15px;font-weight:700;text-decoration:none;
    box-shadow:0 4px 14px rgba(22,163,74,.25);transition:all .2s;
}
.ynj-donate-btn:active{transform:scale(.97);}
.ynj-donate-btn svg{display:inline;}

/* Hadith */
.ynj-hadith{text-align:center;padding:8px 16px;font-size:12px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;line-height:1.5;margin-bottom:10px;}
.ynj-hadith em{display:block;font-style:italic;color:<?php echo self::COLOR_TEXT; ?>;margin-bottom:2px;}
.ynj-hadith span{font-size:10px;opacity:.7;}

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

/* Filter Chips */
.ynj-filter-chips{display:flex;gap:6px;overflow-x:auto;-webkit-overflow-scrolling:touch;padding:4px 0 12px;scrollbar-width:none;}
.ynj-filter-chips::-webkit-scrollbar{display:none;}
.ynj-chip{
    white-space:nowrap;padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;
    border:1px solid #e0e8ed;background:#fff;color:<?php echo self::COLOR_TEXT; ?>;
    cursor:pointer;font-family:inherit;transition:all .15s;flex-shrink:0;
}
.ynj-chip--active{background:<?php echo self::COLOR_ACCENT; ?>;color:#fff;border-color:<?php echo self::COLOR_ACCENT; ?>;}
.ynj-chip:active{transform:scale(.95);}

/* Feed Tabs */
.ynj-feed-tabs{display:flex;gap:0;margin-bottom:12px;background:rgba(255,255,255,.6);border-radius:12px;padding:3px;border:1px solid rgba(0,173,239,.1);}
.ynj-feed-tab{flex:1;padding:9px 8px;border:none;background:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;color:<?php echo self::COLOR_TEXT_MUTED; ?>;font-family:inherit;transition:all .15s;}
.ynj-feed-tab--active{background:<?php echo self::COLOR_ACCENT; ?>;color:#fff;box-shadow:0 2px 8px rgba(0,173,239,.25);}

/* Feed Cards */
.ynj-feed{display:flex;flex-direction:column;gap:10px;}
.ynj-feed-card{
    background:rgba(255,255,255,.85);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
    border-radius:14px;padding:14px 16px;border:1px solid rgba(255,255,255,.6);
    box-shadow:0 1px 6px rgba(0,0,0,.04);
}
.ynj-feed-card--event{border-left:3px solid #f59e0b;}
.ynj-feed-card--announcement{border-left:3px solid <?php echo self::COLOR_ACCENT; ?>;}
.ynj-feed-card--pinned{border-left:3px solid #16a34a;}
.ynj-feed-card__top{display:flex;align-items:center;gap:8px;margin-bottom:6px;}
.ynj-feed-card__top h4{font-size:14px;font-weight:600;flex:1;min-width:0;}
.ynj-feed-card__body{font-size:13px;color:#555;line-height:1.45;margin-bottom:6px;}
.ynj-feed-card__meta{display:flex;flex-wrap:wrap;gap:8px;font-size:11px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}
.ynj-feed-card__mosque{font-size:11px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;margin-top:4px;}

/* Legacy feed (other pages) */
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

/* Search Bar */
.ynj-search-bar{margin-bottom:14px;}
.ynj-search-bar__input{
    width:100%;padding:12px 16px;border:2px solid #e0e8ed;border-radius:14px;
    font-size:15px;font-family:inherit;outline:none;background:#fff;
    transition:border-color .15s;
}
.ynj-search-bar__input:focus{border-color:<?php echo self::COLOR_ACCENT; ?>;}
.ynj-search-bar__input::placeholder{color:#a0b4c0;}
.ynj-search-bar__filters{display:flex;gap:8px;margin-top:8px;}
.ynj-search-bar__select{
    flex:1;padding:8px 12px;border:1px solid #e0e8ed;border-radius:10px;
    font-size:13px;font-family:inherit;background:#fff;color:<?php echo self::COLOR_TEXT; ?>;
    outline:none;cursor:pointer;
}
.ynj-search-bar__select:focus{border-color:<?php echo self::COLOR_ACCENT; ?>;}

/* Forms */
.ynj-form{display:flex;flex-direction:column;gap:14px;}
.ynj-field{display:flex;flex-direction:column;gap:4px;}
.ynj-field label{font-size:12px;font-weight:600;color:<?php echo self::COLOR_TEXT_MUTED; ?>;text-transform:uppercase;letter-spacing:.3px;}
.ynj-field input,.ynj-field select,.ynj-field textarea{
    padding:10px 14px;border:1px solid #e0e0e0;border-radius:10px;font-size:14px;
    font-family:inherit;outline:none;transition:border-color .15s;background:#fff;width:100%;
}
.ynj-field input:focus,.ynj-field select:focus,.ynj-field textarea:focus{border-color:<?php echo self::COLOR_ACCENT; ?>;}
.ynj-field textarea{resize:vertical;}
.ynj-field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

/* Tier Selector */
.ynj-tier-grid{display:flex;flex-direction:column;gap:10px;}
.ynj-tier{display:flex;cursor:pointer;}
.ynj-tier input{display:none;}
.ynj-tier__body{
    flex:1;padding:16px;border:2px solid #e0e0e0;border-radius:14px;
    transition:all .2s;background:#fff;
}
.ynj-tier input:checked + .ynj-tier__body{border-color:<?php echo self::COLOR_ACCENT; ?>;background:#e8f7ff;box-shadow:0 0 0 1px <?php echo self::COLOR_ACCENT; ?>;}
.ynj-tier__price{font-size:24px;font-weight:700;color:<?php echo self::COLOR_PRIMARY; ?>;}
.ynj-tier__price span{font-size:14px;font-weight:400;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}
.ynj-tier__name{font-size:14px;font-weight:600;margin:4px 0 2px;}

/* Modal */
.ynj-modal{
    position:fixed;top:0;left:0;right:0;bottom:0;z-index:300;
    background:rgba(0,0,0,.5);display:flex;align-items:flex-end;justify-content:center;
    padding:0 0 env(safe-area-inset-bottom,0);
}
.ynj-modal__content{
    background:#fff;border-radius:18px 18px 0 0;padding:24px 20px 32px;
    width:100%;max-width:500px;max-height:85vh;overflow-y:auto;
}
.ynj-modal__content h3{font-size:18px;font-weight:700;margin-bottom:16px;}

/* Text helpers */
.ynj-text-muted{font-size:13px;color:<?php echo self::COLOR_TEXT_MUTED; ?>;}
.ynj-mosque-name{font-size:22px;font-weight:700;}

/* Desktop Grid */
.ynj-desktop-grid{display:flex;flex-direction:column;}
.ynj-desktop-grid__left,.ynj-desktop-grid__right{width:100%;}

/* Desktop Responsive */
@media(min-width:900px){
    .ynj-main{max-width:1100px;padding:20px 24px;}
    .ynj-header__inner{max-width:1100px;}
    .ynj-dropdown__inner{max-width:1100px;}
    .ynj-desktop-grid{flex-direction:row;gap:24px;align-items:flex-start;}
    .ynj-desktop-grid__left{width:380px;flex-shrink:0;position:sticky;top:72px;}
    .ynj-desktop-grid__right{flex:1;min-width:0;}
    .ynj-card--hero{padding:24px 20px 20px;}
    .ynj-countdown{font-size:32px;}
    .ynj-hero-prayer{font-size:20px;}
    .ynj-nav{display:none;}
    body{padding-bottom:0;}
    /* Desktop top nav inside header */
    .ynj-header__nav{display:flex !important;align-items:center;gap:4px;margin-left:24px;}
    .ynj-header__nav a{color:rgba(255,255,255,.7);font-size:12px;font-weight:600;text-decoration:none;padding:6px 12px;border-radius:8px;transition:all .15s;white-space:nowrap;}
    .ynj-header__nav a:hover,.ynj-header__nav a.ynj-hn--active{color:#fff;background:rgba(255,255,255,.15);}
    .ynj-feed-card{padding:16px 20px;}
    .ynj-svc-card{padding:16px 20px;}
}

@media(min-width:1200px){
    .ynj-main{max-width:1280px;}
    .ynj-header__inner{max-width:1280px;}
    .ynj-desktop-grid__left{width:420px;}
}

/* Sub-page desktop layouts */
@media(min-width:900px){
    /* Campaigns: 2-column grid */
    .ynj-campaign{display:inline-block;width:calc(50% - 8px);vertical-align:top;margin-right:12px;}
    .ynj-campaign:nth-child(2n){margin-right:0;}
    /* Services: wider cards */
    .ynj-svc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    /* Sponsors: wider layout */
    .ynj-sponsor{padding:20px 0;}
    /* More page: 3-column */
    .ynj-more-grid{grid-template-columns:1fr 1fr 1fr;}
    /* Room cards: 2-column */
    .ynj-room-card+.ynj-room-card{margin-top:0;}
    /* Prayer timetable: wider */
    .ynj-tt-scroll{overflow-x:visible;margin:0;}
    .ynj-tt-table{font-size:13px;}
    /* Back button + title wider */
    .ynj-back{margin-right:8px;}
}

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
<script>
// Auto-inject desktop nav into every page header
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth < 900) return;
    var header = document.querySelector('.ynj-header__inner');
    if (!header || document.getElementById('desktop-nav')) return;
    var slug = localStorage.getItem('ynj_mosque_slug') || '';
    var nav = document.createElement('nav');
    nav.className = 'ynj-header__nav';
    nav.id = 'desktop-nav-auto';
    nav.style.display = 'flex';
    var path = location.pathname;
    var links = [
        ['/', 'Home'], ['/live', '🔴 Live'],
        ['/mosque/'+slug+'/fundraising', 'Fundraise'],
        ['/mosque/'+slug+'/sponsors', 'Sponsors'], ['/mosque/'+slug+'/services', 'Services'],
        ['/mosque/'+slug+'/rooms', 'Rooms'], ['/profile', 'My Account']
    ];
    nav.innerHTML = links.map(function(l) {
        var active = (l[0] === '/' && path === '/') || (l[0] !== '/' && path.indexOf(l[0]) === 0);
        return '<a href="'+l[0]+'"'+(active?' class="ynj-hn--active"':'')+'>'+l[1]+'</a>';
    }).join('');
    var right = header.querySelector('.ynj-header__right') || header.querySelector('.ynj-logo');
    if (right && right.nextSibling) header.insertBefore(nav, right);
    else header.appendChild(nav);
});
</script>
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
            'fundraising' => [
                'label' => 'Fundraise',
                'href'  => '/mosque/{slug}/fundraising',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>',
                'mosque' => true,
            ],
            'sponsors' => [
                'label' => 'Sponsors',
                'href'  => '/mosque/{slug}/sponsors',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
                'mosque' => true,
            ],
            'services' => [
                'label' => 'Services',
                'href'  => '/mosque/{slug}/services',
                'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg>',
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
