        (function(){
            'use strict';

            // Only run on homepage — bail if prayer card doesn't exist
            if (!document.getElementById('next-prayer-card')) return;

            const API = ynjData.restUrl.replace(/\/+$/, '');
            const VAPID = ynjData.vapidKey;

            let mosqueSlug = localStorage.getItem('ynj_mosque_slug');
            // Use PHP pre-loaded mosque data if available (instant, no API)
            let mosqueData = (ynjData.mosque && ynjData.mosque.slug) ? ynjData.mosque : null;
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
                loadPoints();

                var savedSlug = mosqueSlug || localStorage.getItem('ynj_mosque_slug');
                var today = new Date().toDateString();
                var cacheDate = localStorage.getItem('ynj_cache_date');
                var isCacheFresh = (cacheDate === today);

                // Try to load from today's cache first (instant)
                var cachedPrayers = null;
                var cachedFeed = null;
                try { cachedPrayers = JSON.parse(localStorage.getItem('ynj_cached_prayers')); } catch(e) {}
                try { cachedFeed = JSON.parse(localStorage.getItem('ynj_cached_feed')); } catch(e) {}

                if (savedSlug) {
                    mosqueSlug = savedSlug;
                    var cachedName = localStorage.getItem('ynj_mosque_name') || savedSlug;
                    document.getElementById('mosque-name').textContent = cachedName;
                    updateNavLinks(savedSlug);
                    var ttLink = document.getElementById('timetable-link');
                    if (ttLink) ttLink.href = '/mosque/' + savedSlug + '/prayers';

                    // Mosque-name headings set instantly by theme.js from localStorage

                    if (isCacheFresh && cachedPrayers) {
                        // Use today's cached prayer times — instant render
                        setPrayerTimes(cachedPrayers);
                    }

                    if (isCacheFresh && cachedFeed) {
                        // Use today's cached feed — instant render
                        allFeedItems = cachedFeed;
                        renderFeed();
                    }

                    // If PHP pre-loaded this mosque, use it directly (no API calls)
                    if (mosqueData && mosqueData.slug === savedSlug) {
                        // Use pre-loaded data — skip loadMosque API call
                        loadMosque(savedSlug);
                    } else {
                        // Different mosque or no preload — fetch from API
                        loadMosque(savedSlug);
                    }
                    // Only load feed if not using pre-loaded data
                    if (!(window.ynjPreloaded && window.ynjPreloaded.mosqueId)) {
                        loadFeed(savedSlug, isCacheFresh && cachedFeed);
                    } else {
                        loadFeed(savedSlug, true); // use pre-loaded, don't show loading
                    }
                }

                // First visit or stale cache: full GPS → detect mosque
                if (!savedSlug || !isCacheFresh) {
                    requestGps();
                } else {
                    // Fresh cache: just get position silently for travel times
                    getPositionForTravel();
                }
            }

            function showGreeting() {
                var el = document.getElementById('ynj-greeting');
                if (!el) return;
                var user = null;
                try { user = JSON.parse(localStorage.getItem('ynj_user')); } catch(e) {}
                var hasMosque = !!localStorage.getItem('ynj_mosque_slug');

                if (!hasMosque) {
                    // First-visit onboarding
                    el.style.display = '';
                    el.innerHTML = '<div class="ynj-card" style="text-align:center;padding:20px;"><h3 style="font-size:16px;font-weight:700;margin-bottom:4px;">Welcome to YourJannah</h3><p class="ynj-text-muted" style="margin-bottom:12px;">Your mosque community app — prayer times, events, donate, and more.</p><button class="ynj-btn" style="justify-content:center;" onclick="document.getElementById(\'mosque-dropdown\').style.display=\'\';document.getElementById(\'mosque-search\').focus();">🕌 Find Your Mosque</button></div>';
                } else if (user && user.name) {
                    // Logged-in greeting with patron badge if active
                    var patronBadge = (user.patron && user.patron.tier) ? ' <span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;vertical-align:middle;">🏅 Patron</span>' : '';
                    el.style.display = '';
                    el.innerHTML = '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0 4px;"><span style="font-size:14px;font-weight:600;">Assalamu alaikum, ' + (user.name.split(' ')[0]) + patronBadge + '</span><a href="/profile" style="font-size:12px;font-weight:600;">My Profile →</a></div>';
                }
            }

            // Load points — use PHP pre-loaded data if available
            function loadPoints() {
                var card = document.getElementById('ynj-points-card');
                var pre = window.ynjPreloaded;

                // Try pre-loaded data first (PHP, instant)
                if (pre && pre.points && pre.points.ok) {
                    if (card) card.style.display = '';
                    var el = document.getElementById('points-total');
                    if (el) el.textContent = pre.points.total || 0;
                    return;
                }

                // Fallback to API for token-only users
                var token = localStorage.getItem('ynj_user_token');
                if (!token && !document.cookie.match(/wordpress_logged_in/)) return;
                if (card) card.style.display = '';

                var headers = {};
                if (token) headers['Authorization'] = 'Bearer ' + token;
                fetch(API + '/points/me', { credentials: 'same-origin', headers: headers })
                    .then(function(r) { return r.ok ? r.json() : null; })
                    .then(function(d) {
                        if (d && d.ok) {
                            var el = document.getElementById('points-total');
                            if (el) el.textContent = d.total || 0;
                        }
                    })
                    .catch(function(){});
            }

            window.doCheckIn = function() {
                var token = localStorage.getItem('ynj_user_token');
                // Allow check-in with WP cookie auth even without JS token
                if (!mosqueSlug) { alert('Select a mosque first.'); return; }

                var btn = document.getElementById('checkin-btn');
                btn.disabled = true; btn.textContent = 'Checking in...';

                if (!('geolocation' in navigator)) { btn.textContent = 'GPS not available'; btn.disabled = false; return; }

                navigator.geolocation.getCurrentPosition(function(pos) {
                    var headers = { 'Content-Type': 'application/json' };
                    if (token) headers['Authorization'] = 'Bearer ' + token;
                    fetch(API + '/points/checkin', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: headers,
                        body: JSON.stringify({ mosque_slug: mosqueSlug, lat: pos.coords.latitude, lng: pos.coords.longitude })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.ok) {
                            btn.textContent = '✅ ' + d.message;
                            btn.style.background = '#166534';
                            var el = document.getElementById('points-total');
                            if (el) el.textContent = d.total;
                        } else {
                            btn.textContent = '❌ ' + (d.error || 'Check-in failed');
                            btn.style.background = '#991b1b';
                            btn.disabled = false;
                            setTimeout(function() { btn.textContent = '📍 Check In'; btn.style.background = ''; }, 5000);
                        }
                    })
                    .catch(function(e) { btn.textContent = '❌ Network error'; btn.style.background = '#991b1b'; btn.disabled = false; setTimeout(function(){ btn.textContent = '📍 Check In'; btn.style.background = ''; }, 5000); });
                }, function() {
                    btn.textContent = 'Enable GPS to check in';
                    btn.disabled = false;
                    setTimeout(function() { btn.textContent = '📍 Check In'; }, 3000);
                }, { timeout: 8000 });
            };

            function requestGps() {
                if (!('geolocation' in navigator)) {
                    if (mosqueSlug) loadSavedMosque();
                    else showSearchPrompt();
                    return;
                }

                const gpsBtn = document.getElementById('gps-btn');
                gpsBtn.classList.add('ynj-gps-btn--loading');
                // Only show "Locating..." if no mosque is cached
                if (!mosqueSlug) {
                    document.getElementById('mosque-name').textContent = 'Locating...';
                }

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
                // Open the mosque modal so user can search
                var modal = document.getElementById('ynj-mosque-modal');
                if (modal) modal.classList.add('ynj-mosque-modal--open');
            }

            // Silent GPS — just get position for travel calc, don't change mosque
            function getPositionForTravel() {
                if (!('geolocation' in navigator)) return;
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        userLat = pos.coords.latitude;
                        userLng = pos.coords.longitude;
                        // Try cached mosque coords first, then mosqueData from API
                        var mLat = null, mLng = null;
                        try {
                            var cached = JSON.parse(localStorage.getItem('ynj_cached_mosque'));
                            if (cached && cached.lat && cached.lng) { mLat = cached.lat; mLng = cached.lng; }
                        } catch(e) {}
                        // Fallback to mosqueData if loaded from API
                        if (!mLat && mosqueData && mosqueData.latitude) { mLat = mosqueData.latitude; mLng = mosqueData.longitude; }
                        // Fallback to mosqueLat/Lng if set from selectMosque
                        if (!mLat && mosqueLat) { mLat = mosqueLat; mLng = mosqueLng; }

                        if (mLat && mLng) {
                            mosqueLat = mLat; mosqueLng = mLng;
                            calcTravelFromCoords(mLat, mLng);
                            document.getElementById('nav-buttons').style.display = '';
                            document.getElementById('hero-gps-prompt').style.display = 'none';
                            document.getElementById('navigate-walk').href =
                                'https://www.google.com/maps/dir/?api=1&destination=' + mLat + ',' + mLng + '&travelmode=walking';
                            document.getElementById('navigate-drive').href =
                                'https://www.google.com/maps/dir/?api=1&destination=' + mLat + ',' + mLng + '&travelmode=driving';
                        }
                    },
                    function() { /* GPS denied — no travel times, that's fine */ },
                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
                );
            }

            function setupGpsButton() {
                // GPS button handled by inline onclick in header.php (ynjGpsFind)
                // Same behaviour on all pages — opens dropdown with nearby mosques
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

                        // Auto-load: select saved mosque or nearest
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
                var displayName = name || slug;
                localStorage.setItem('ynj_mosque_slug', slug);
                localStorage.setItem('ynj_mosque_name', displayName);
                // Cache mosque for instant load next time
                try { localStorage.setItem('ynj_cached_mosque', JSON.stringify({slug:slug,name:name,lat:lat,lng:lng})); } catch(e){}
                // Clear stale daily cache so fresh data loads for new mosque
                localStorage.removeItem('ynj_cache_date');
                localStorage.removeItem('ynj_cached_prayers');
                localStorage.removeItem('ynj_cached_feed');

                // Update ALL headings immediately — no waiting for API
                document.getElementById('mosque-name').textContent = displayName;
                var pb = document.getElementById('patron-bar-text');
                if (pb) pb.textContent = 'Become a Patron of ' + displayName;
                var fh = document.getElementById('feed-heading');
                if (fh) fh.textContent = "What's Happening at " + displayName;
                var npl = document.getElementById('next-prayer-label');
                if (npl) npl.textContent = 'Next Prayer at ' + displayName;
                var sh = document.getElementById('cta-sponsor-help');
                if (sh) sh.textContent = 'Funds go to supporting ' + displayName;
                var svh = document.getElementById('cta-services-help');
                if (svh) svh.textContent = 'Proceeds help fund ' + displayName;

                updateNavLinks(slug);

                // Timetable link
                document.getElementById('timetable-link').href = `/mosque/${slug}/prayers`;

                // Travel & navigate
                if (lat && lng) {
                    document.getElementById('nav-buttons').style.display = ''; document.getElementById('hero-gps-prompt').style.display = 'none';
                    document.getElementById('navigate-walk').href =
                        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=walking`;
                    document.getElementById('navigate-drive').href =
                        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;

                    if (userLat != null) {
                        calcTravelFromCoords(lat, lng);
                    } else {
                        /* GPS will provide location — no postcode needed */
                    }
                }

                // Clear old mosque data immediately when switching
                var tickerEl = document.getElementById('sponsor-ticker');
                if (tickerEl) tickerEl.style.display = 'none';
                var jumuahEl = document.getElementById('jumuah-card');
                if (jumuahEl) jumuahEl.style.display = 'none';
                allFeedItems = [];
                var feedEl = document.getElementById('feed-list');
                if (feedEl) feedEl.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Loading...</p>';

                // Fetch fresh data for new mosque
                loadMosque(slug);

                // Fetch feed
                loadFeed(slug);
            }

            function loadMosque(slug) {
                // Use pre-loaded mosque data if available (skip API call)
                var preM = (ynjData.mosque && ynjData.mosque.slug === slug) ? ynjData.mosque : null;
                var fetchP = preM ? Promise.resolve({mosque: preM}) : fetch(`${API}/mosques/${slug}`).then(r => r.json());

                fetchP.then(resp => {
                        const m = resp.mosque || resp;
                        mosqueData = m;
                        document.getElementById('mosque-name').textContent = m.name || slug;

                        // Track page view (fire-and-forget for demand analytics)
                        if (m.id) {
                            var src = (userLat && userLng) ? 'gps' : 'page';
                            fetch(`${API}/mosques/${m.id}/view`, {
                                method: 'POST',
                                headers: {'Content-Type':'application/json'},
                                body: JSON.stringify({source: src})
                            }).catch(function(){});
                        }

                        // Update all headings + CTAs with mosque name
                        var mName = m.name || 'the masjid';
                        var sh = document.getElementById('cta-sponsor-help');
                        var svh = document.getElementById('cta-services-help');
                        if (sh) sh.textContent = 'Funds go to supporting ' + mName;
                        if (svh) svh.textContent = 'Proceeds help fund ' + mName;
                        var pb = document.getElementById('patron-bar-text');
                        if (pb) pb.textContent = 'Become a Patron of ' + mName;
                        var fh = document.getElementById('feed-heading');
                        if (fh) fh.textContent = "What's Happening at " + mName;

                        // Jumu'ah times — use PHP pre-loaded data if available
                        var jumuahData = (window.ynjPreloaded && window.ynjPreloaded.jumuah) || null;
                        if (jumuahData) {
                            renderJumuah(jumuahData);
                        } else {
                            fetch(`${API}/mosques/${slug}/jumuah`)
                                .then(r => r.ok ? r.json() : {slots:[]})
                                .then(jData => { renderJumuah(jData.slots || []); })
                                .catch(function(){});
                        }

                        function renderJumuah(slots) {
                            if (!slots || !slots.length) return;
                            var el = document.getElementById('jumuah-slots');
                            el.innerHTML = slots.map(function(s) {
                                var khutbah = s.khutbah_time ? String(s.khutbah_time).substring(0,5) : '';
                                var salah = s.salah_time ? String(s.salah_time).substring(0,5) : '';
                                var lang = s.language ? '<div class="ynj-jumuah-slot__lang">' + s.language + '</div>' : '';
                                return '<div class="ynj-jumuah-slot"><div><div class="ynj-jumuah-slot__name">' + (s.slot_name || 'Jumu\'ah') + '</div>' + lang + '</div><div class="ynj-jumuah-slot__times">' + (khutbah ? 'Khutbah ' + khutbah + ' · ' : '') + 'Salah ' + salah + '</div></div>';
                            }).join('');
                            document.getElementById('jumuah-card').style.display = '';
                        }

                        // Sponsor ticker — use PHP pre-loaded data if available
                        var sponsorData = (window.ynjPreloaded && window.ynjPreloaded.sponsors) || null;
                        if (sponsorData) {
                            renderTicker(sponsorData);
                        } else {
                            fetch(`${API}/mosques/${slug}/directory`)
                                .then(r => r.json())
                                .then(dirData => { renderTicker(dirData.businesses || []); })
                                .catch(() => {});
                        }

                        function renderTicker(biz) {
                            if (!biz || !biz.length) return;
                            const ticker = document.getElementById('sponsor-ticker');
                            const content = document.getElementById('ticker-content');
                            const items = biz.map((b, i) =>
                                `<a href="/mosque/${slug}/sponsors" class="ynj-ticker__item"><span class="ynj-ticker__rank">#${i+1}</span>${b.business_name}<span class="ynj-ticker__cat">${b.category}</span></a>`
                            ).join('');
                            content.innerHTML = items + items;
                            ticker.style.display = '';
                            const width = content.scrollWidth / 2;
                            const speed = Math.max(10, Math.round(width / 40));
                            content.style.animationDuration = speed + 's';
                        }

                        // Donate button — link to DonationForMasjid
                        const dfmSlug = m.dfm_slug || m.slug || slug;
                        const donateBtn = document.getElementById('donate-btn');
                        donateBtn.href = `https://donationformasjid.com/${dfmSlug}`;
                        donateBtn.target = '_blank'; donateBtn.rel = 'noopener';

                        // Always show navigate if we have mosque coords
                        if (m.latitude && m.longitude) {
                            mosqueLat = m.latitude; mosqueLng = m.longitude;
                            document.getElementById('nav-buttons').style.display = ''; document.getElementById('hero-gps-prompt').style.display = 'none';
                            document.getElementById('navigate-walk').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=walking`;
                            document.getElementById('navigate-drive').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=driving`;

                            if (userLat) {
                                calcTravelFromCoords(m.latitude, m.longitude);
                            }
                        }

                        // Store jamat overrides if present
                        if (m.prayer_times) {
                            ['fajr_jamat','dhuhr_jamat','asr_jamat','maghrib_jamat','isha_jamat'].forEach(k => {
                                if (m.prayer_times[k]) {
                                    jamatTimes[k] = String(m.prayer_times[k]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                                }
                            });
                        }

                        // Always fetch from Aladhan (reliable from browser)
                        // Use stored times only if Aladhan fails
                        const lat = m.latitude || userLat;
                        const lng = m.longitude || userLng;
                        if (lat && lng) {
                            fetchAladhan(lat, lng);
                        } else {
                            // No coords at all — try stored times as last resort
                            const hasAdhan = m.prayer_times && m.prayer_times.fajr && m.prayer_times.maghrib && !m.prayer_times.error;
                            if (hasAdhan) setPrayerTimes(m.prayer_times);
                        }

                        // Calculate travel if GPS position available
                        if (userLat && m.latitude && m.longitude) {
                            mosqueLat = m.latitude; mosqueLng = m.longitude;
                            calcTravelFromCoords(m.latitude, m.longitude);
                            document.getElementById('nav-buttons').style.display = '';
                            document.getElementById('hero-gps-prompt').style.display = 'none';
                            document.getElementById('navigate-walk').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=walking`;
                            document.getElementById('navigate-drive').href =
                                `https://www.google.com/maps/dir/?api=1&destination=${m.latitude},${m.longitude}&travelmode=driving`;
                        }
                        // Cache mosque coords for getPositionForTravel
                        if (m.latitude && m.longitude) {
                            try { localStorage.setItem('ynj_cached_mosque', JSON.stringify({slug:m.slug||mosqueSlug,name:m.name,lat:m.latitude,lng:m.longitude})); } catch(e){}
                        }
                    })
                    .catch(() => {});
            }

            /* ---- Aladhan Fallback ---- */
            let aladhanAttempts = 0;
            function fetchAladhan(lat, lng) {
                aladhanAttempts++;
                const ts = Math.floor(Date.now() / 1000);
                fetch(`https://api.aladhan.com/v1/timings/${ts}?latitude=${lat}&longitude=${lng}&method=2`)
                    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                    .then(d => {
                        if (d && d.data && d.data.timings) {
                            const t = d.data.timings;
                            const strip = s => (s||'').replace(/\s*\(.*\)/,'');
                            setPrayerTimes({
                                fajr: strip(t.Fajr), sunrise: strip(t.Sunrise), dhuhr: strip(t.Dhuhr),
                                asr: strip(t.Asr), maghrib: strip(t.Maghrib), isha: strip(t.Isha)
                            });
                            // Ramadan detection from Hijri date
                            if (d.data.date && d.data.date.hijri) {
                                var hijri = d.data.date.hijri;
                                var month = parseInt(hijri.month && hijri.month.number || 0);
                                if (month === 9) { // Ramadan
                                    showRamadanMode(strip(t.Fajr), strip(t.Maghrib));
                                }
                            }
                        }
                    })
                    .catch(function(err) {
                        console.warn('Aladhan fetch failed (attempt ' + aladhanAttempts + '):', err);
                        // Retry once after 2s
                        if (aladhanAttempts < 2) {
                            setTimeout(function(){ fetchAladhan(lat, lng); }, 2000);
                        } else {
                            // Last resort: try stored mosque prayer times
                            if (mosqueData && mosqueData.prayer_times && mosqueData.prayer_times.fajr) {
                                setPrayerTimes(mosqueData.prayer_times);
                            }
                        }
                    });
            }

            /* ---- Ramadan Mode ---- */
            function showRamadanMode(fajrTime, maghribTime) {
                var el = document.getElementById('ramadan-banner');
                if (!el) return;
                el.style.display = '';
                el.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;">' +
                    '<div><strong style="font-size:14px;">🌙 Ramadan Mubarak</strong></div>' +
                    '<div style="display:flex;gap:16px;font-size:13px;">' +
                    '<span>Suhoor ends <strong>' + fajrTime + '</strong></span>' +
                    '<span>Iftar at <strong>' + maghribTime + '</strong></span>' +
                    '</div></div>';
            }

            /* ---- Prayer Times ---- */
            var countdownInterval = null;
            function setPrayerTimes(times) {
                prayerTimes = {};
                ['fajr','sunrise','dhuhr','asr','maghrib','isha'].forEach(p => {
                    if (times[p]) {
                        var raw = String(times[p]).replace(/\s*\(.*\)/,'');
                        prayerTimes[p] = raw.replace(/^(\d{1,2}:\d{2}):\d{2}$/, '$1');
                    }
                });
                // Cache prayer times for today
                try {
                    localStorage.setItem('ynj_cached_prayers', JSON.stringify(prayerTimes));
                    localStorage.setItem('ynj_cache_date', new Date().toDateString());
                } catch(e) {}
                updateCountdown();
                if (!countdownInterval) countdownInterval = setInterval(updateCountdown, 1000);
                renderPrayerOverview();
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
                    document.getElementById('next-prayer-label').textContent = mosqueData && mosqueData.name ? mosqueData.name : '';
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
                var prayerLabel = mosqueData && mosqueData.name ? 'Next Prayer at ' + mosqueData.name : 'Next Prayer';
                document.getElementById('next-prayer-label').textContent = prayerLabel;

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
                        var wt = document.getElementById('leave-by-walk-text');
                        var dt = document.getElementById('leave-by-drive-text');
                        if(wt) wt.textContent = '🚨 LEAVE NOW';
                        if(dt) dt.textContent = '🚨 LEAVE NOW';
                    } else if (minsUntilLeave <= 10) {
                        hero.classList.add('ynj-hero--urgent');
                    }
                }

                updateLeaveBy();
            }

            function updateLeaveBy() {
                if (!prayerTimes || !distanceKm) return;
                const now = new Date();
                const prayers = ['fajr','dhuhr','asr','maghrib','isha'];
                for (const p of prayers) {
                    if (!prayerTimes[p]) continue;
                    const targetTime = jamatTimes[p+'_jamat'] || prayerTimes[p];
                    const [h,m] = targetTime.split(':').map(Number);
                    const t = new Date(now); t.setHours(h,m,0,0);

                    if (t > now) {
                        // Walk: ~12 min/km + buffer
                        var walkMin = Math.max(1, Math.round(distanceKm * 12)) + bufferMinutes;
                        var walkLeave = new Date(t.getTime() - walkMin * 60000);
                        var wh = String(walkLeave.getHours()).padStart(2,'0');
                        var wm = String(walkLeave.getMinutes()).padStart(2,'0');

                        // Drive: ~2 min/km + buffer
                        var driveMin = Math.max(1, Math.round(distanceKm * 2)) + bufferMinutes;
                        var driveLeave = new Date(t.getTime() - driveMin * 60000);
                        var dh = String(driveLeave.getHours()).padStart(2,'0');
                        var dm = String(driveLeave.getMinutes()).padStart(2,'0');

                        var walkEl = document.getElementById('leave-by-walk-text');
                        var driveEl = document.getElementById('leave-by-drive-text');
                        if (walkEl) walkEl.textContent = 'Leave ' + wh + ':' + wm;
                        if (driveEl) driveEl.textContent = 'Leave ' + dh + ':' + dm;
                        document.getElementById('hero-travel').style.display = '';
                        return;
                    }
                }
            }

            /* ---- Feed ---- */
            let currentRadius = 0; // 0 = this masjid only
            let currentFeedFilter = 'all';

            // Per-type icon, badge colour, and card accent
            const typeConfig = {
                'talk':        {icon:'🎤', bg:'#fef3c7', fg:'#92400e', accent:'talk'},
                'halaqa':      {icon:'📚', bg:'#fef3c7', fg:'#92400e', accent:'talk'},
                'class':       {icon:'📖', bg:'#ede9fe', fg:'#7c3aed', accent:'class'},
                'course':      {icon:'🎓', bg:'#ede9fe', fg:'#7c3aed', accent:'class'},
                'workshop':    {icon:'🛠️', bg:'#fef3c7', fg:'#92400e', accent:'talk'},
                'community':   {icon:'🤝', bg:'#dbeafe', fg:'#1e40af', accent:'community'},
                'iftar':       {icon:'🍽️', bg:'#dbeafe', fg:'#1e40af', accent:'community'},
                'sports':      {icon:'⚽', bg:'#dcfce7', fg:'#166534', accent:'sports'},
                'competition': {icon:'🏆', bg:'#dcfce7', fg:'#166534', accent:'sports'},
                'youth':       {icon:'👦', bg:'#ffedd5', fg:'#c2410c', accent:'youth'},
                'kids':        {icon:'🧒', bg:'#ffedd5', fg:'#c2410c', accent:'youth'},
                'children':    {icon:'🧒', bg:'#ffedd5', fg:'#c2410c', accent:'youth'},
                'sisters':     {icon:'👩', bg:'#fce7f3', fg:'#be185d', accent:'sisters'},
                'fundraiser':  {icon:'💰', bg:'#dcfce7', fg:'#166534', accent:'fundraiser'},
                'eid':         {icon:'🌙', bg:'#fef3c7', fg:'#92400e', accent:'eid'},
                'quran':       {icon:'📖', bg:'#e8f4f8', fg:'#0369a1', accent:'quran'},
                'nikah':       {icon:'💍', bg:'#fce7f3', fg:'#be185d', accent:'sisters'},
                'janazah':     {icon:'🕊️', bg:'#f3f4f6', fg:'#4b5563', accent:'other'},
                'other':       {icon:'📌', bg:'#f3f4f6', fg:'#4b5563', accent:'other'},
            };

            function renderFeedCard(item) {
                let cardAccent = '', badge;

                if (item.type === 'live') {
                    cardAccent = 'ynj-fc--live';
                    badge = '<span class="ynj-badge" style="background:#fee2e2;color:#dc2626;">🔴 LIVE</span>';
                } else if (item.type === 'class') {
                    cardAccent = 'ynj-fc--class';
                    badge = `<span class="ynj-badge" style="background:#ede9fe;color:#7c3aed;">🎓 Class${item.price ? ' · '+item.price : ''}</span>`;
                } else if (item.type === 'event') {
                    const et = (item.event_type||'').toLowerCase();
                    const cfg = typeConfig[et] || {icon:'📅', bg:'#e8f4f8', fg:'#00ADEF', accent:'other'};
                    cardAccent = 'ynj-fc--' + cfg.accent;
                    const label = (item.event_type||'Event').charAt(0).toUpperCase() + (item.event_type||'event').slice(1);
                    badge = `<span class="ynj-badge" style="background:${cfg.bg};color:${cfg.fg};">${cfg.icon} ${label}</span>`;
                } else {
                    cardAccent = item.pinned ? 'ynj-fc--pinned' : 'ynj-fc--announcement';
                    badge = item.pinned ? '<span class="ynj-badge" style="background:#dcfce7;color:#166534;">📌 Pinned</span>' : '<span class="ynj-badge" style="background:#e8f4f8;color:#0369a1;">📢 Update</span>';
                }

                const snippet = (item.body||'').length > 80 ? item.body.slice(0,80)+'...' : (item.body||'');
                const meta = [];
                if (item.type === 'live') {
                    if (item.time) meta.push(`<span>🕐 ${item.time}</span>`);
                    if (item.ticket_price) meta.push(`<span style="font-weight:700;color:#0a1628;">🎟️ ${item.ticket_price}</span>`);
                    if (item.donation_target) meta.push(`<span>❤️ ${item.donation_target} target</span>`);
                    if (item.live_url) meta.push(`<a href="/live" style="color:#dc2626;font-weight:600;">Watch Live →</a>`);
                } else if (item.type === 'class') {
                    if (item.day_of_week) meta.push(`<span>📅 ${item.day_of_week}s</span>`);
                    if (item.time) meta.push(`<span>🕐 ${item.time}</span>`);
                    if (item.instructor) meta.push(`<span>👤 ${item.instructor}</span>`);
                    if (item.mosque_slug) meta.push(`<a href="/mosque/${item.mosque_slug}/classes" style="color:#00ADEF;font-weight:600;">Book →</a>`);
                } else if (item.type === 'event') {
                    if (item.time) meta.push(`<span>🕐 ${item.time}</span>`);
                    if (item.location) meta.push(`<span>📍 ${item.location}</span>`);
                    if (item.ticket_price) {
                        meta.push(`<span style="font-weight:700;color:#0a1628;">🎟️ ${item.ticket_price}</span>`);
                    }
                    if (item.event_id && item.mosque_slug) {
                        const label = item.ticket_price ? `Buy Ticket ${item.ticket_price} →` : 'RSVP Free →';
                        meta.push(`<a href="/mosque/${item.mosque_slug}/events/${item.event_id}" style="color:#00ADEF;font-weight:600;">${label}</a>`);
                    }
                } else {
                    if (item.date) meta.push(`<span>${timeAgo(item.date)}</span>`);
                }
                const mosqueTag = item.mosque_name ? `<div class="ynj-feed-card__mosque">🕌 ${item.mosque_name}</div>` : '';

                // Icon strip: big emoji + date
                let dateStrip = '';
                // Get the category emoji
                let stripEmoji = '📅';
                if (item.type === 'live') stripEmoji = '🔴';
                else if (item.type === 'class') stripEmoji = '🎓';
                else if (item.type === 'announcement') stripEmoji = item.pinned ? '📌' : '📢';
                else if (item.type === 'event') {
                    const et = (item.event_type||'').toLowerCase();
                    const cfg = typeConfig[et];
                    if (cfg) stripEmoji = cfg.icon;
                }

                const dateStr = item.date || item.start_date || '';
                if (dateStr && item.type !== 'announcement') {
                    const d = new Date(dateStr + 'T00:00:00');
                    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    dateStrip = `<div class="ynj-feed-card__date">
                        <span class="ynj-feed-card__date-emoji">${stripEmoji}</span>
                        <span class="ynj-feed-card__date-num">${d.getDate()}</span>
                        <span class="ynj-feed-card__date-month">${days[d.getDay()]} ${months[d.getMonth()]}</span>
                    </div>`;
                } else {
                    dateStrip = `<div class="ynj-feed-card__date">
                        <span class="ynj-feed-card__date-emoji">${stripEmoji}</span>
                    </div>`;
                }

                return `<div class="ynj-feed-card ${cardAccent}">
                    ${dateStrip}
                    <div class="ynj-feed-card__content">
                        <div class="ynj-feed-card__top">${badge}<h4>${item.title}</h4></div>
                        ${snippet ? `<div class="ynj-feed-card__body">${snippet}</div>` : ''}
                        <div class="ynj-feed-card__meta">${meta.join(' ')}${item.type !== 'announcement' && item.mosque_slug ? ` <a href="#" onclick="ynjWhatsApp('${item.title.replace(/'/g,"\\'")}','${ynjData.siteUrl}mosque/${item.mosque_slug}/events/${item.event_id||''}');return false;" style="color:#25D366;font-weight:700;">WhatsApp</a>` : ''}</div>
                        ${mosqueTag}
                    </div>
                </div>`;
            }

            let allFeedItems = [];
            let nearbyFeedItems = [];
            let nearbyLoaded = false;

            function loadFeed(slug, silent) {
                var feedEl = document.getElementById('feed-list');
                if (feedEl && !silent) feedEl.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Loading...</p>';

                // Use PHP pre-loaded data if available (instant, no API calls)
                var pre = window.ynjPreloaded;
                if (pre && pre.mosqueId && (pre.announcements || pre.events || pre.classes)) {
                    processFeedData(
                        { announcements: pre.announcements || [] },
                        { events: pre.events || [] },
                        { classes: pre.classes || [] },
                        slug, silent
                    );
                    return;
                }

                // Fallback: fetch from API (only when mosque changes dynamically)
                Promise.all([
                    fetch(`${API}/mosques/${slug}/announcements`).then(r => r.ok ? r.json() : {announcements:[]}).catch(() => ({announcements:[]})),
                    fetch(`${API}/mosques/${slug}/events?upcoming=1`).then(r => r.ok ? r.json() : {events:[]}).catch(() => ({events:[]})),
                    fetch(`${API}/mosques/${slug}/classes`).then(r => r.ok ? r.json() : {classes:[]}).catch(() => ({classes:[]}))
                ]).then(([aData, eData, cData]) => {
                    processFeedData(aData, eData, cData, slug, silent);
                }).catch(() => {});
            }

            function processFeedData(aData, eData, cData, slug, silent) {
                    allFeedItems = [];
                    (aData.announcements || []).forEach(a => {
                        allFeedItems.push({ type:'announcement', title:a.title, body:a.body, date:a.published_at||'', pinned:a.pinned });
                    });
                    (eData.events || []).forEach(e => {
                        const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                        const isLive = e.is_live && e.is_online;
                        const ticketPrice = e.ticket_price_pence > 0 ? '£'+(e.ticket_price_pence/100).toFixed(e.ticket_price_pence%100?2:0) : '';
                        allFeedItems.push({
                            type: isLive ? 'live' : 'event',
                            title: e.title, body:e.description||'', date:e.event_date||'', time:time,
                            location:e.location||'', event_id:e.id, mosque_slug:slug,
                            event_type: isLive ? 'live' : (e.event_type||''),
                            live_url: e.live_url||'',
                            ticket_price: ticketPrice,
                            donation_target: e.donation_target_pence > 0 ? '£'+(e.donation_target_pence/100).toLocaleString() : ''
                        });
                    });
                    (cData.classes || []).forEach(c => {
                        const time = c.start_time ? String(c.start_time).replace(/:\d{2}$/,'') : '';
                        const price = c.price_pence > 0 ? '£'+(c.price_pence/100) : 'Free';
                        allFeedItems.push({
                            type:'class', title:c.title, body:c.description||'',
                            date:c.start_date||'', time:time, location:c.location||'',
                            event_type:'class', mosque_slug:slug,
                            class_id:c.id, instructor:c.instructor_name||'', price:price,
                            day_of_week:c.day_of_week||''
                        });
                    });
                    sortFeedItems(allFeedItems);
                    // Cache feed for today
                    try { localStorage.setItem('ynj_cached_feed', JSON.stringify(allFeedItems)); } catch(e) {}
                    renderFeed();
            }

            function sortFeedItems(items) {
                items.sort((a,b) => {
                    if(a.pinned&&!b.pinned)return -1; if(!a.pinned&&b.pinned)return 1;
                    if(a.type==='live'&&b.type!=='live')return -1; if(a.type!=='live'&&b.type==='live')return 1;
                    if(a.type==='announcement'&&b.type!=='announcement')return -1;
                    if(a.type!=='announcement'&&b.type==='announcement')return 1;
                    if(a.type!=='announcement'&&b.type!=='announcement') return (a.date||'9').localeCompare(b.date||'9');
                    return (b.date||'').localeCompare(a.date||'');
                });
            }

            function renderFeed() {
                const el = document.getElementById('feed-list');
                // Combine local + nearby (if radius > 0)
                let items = currentRadius === 0 ? allFeedItems.slice() : allFeedItems.concat(nearbyFeedItems);
                if (currentRadius > 0) sortFeedItems(items);

                // Apply filter
                const filter = currentFeedFilter;
                if (filter === '_live') {
                    items = items.filter(i => i.type === 'live');
                } else if (filter === '_classes') {
                    items = items.filter(i => i.type === 'class');
                } else if (filter === 'announcements') {
                    items = items.filter(i => i.type === 'announcement');
                } else if (filter && filter !== 'all') {
                    const types = filter.split(',');
                    items = items.filter(i => i.type === 'event' && types.includes((i.event_type||'').toLowerCase()));
                }

                // Update count badge
                const countEl = document.getElementById('feed-count');
                if (countEl) countEl.textContent = items.length;

                if (!items.length) {
                    el.innerHTML = filter === 'all'
                        ? '<p class="ynj-text-muted" style="padding:12px;text-align:center;">No announcements or events yet.</p>'
                        : '<p class="ynj-text-muted" style="padding:12px;text-align:center;">Nothing matching this filter.</p>';
                    return;
                }
                // Interleave sponsor ads every 2 feed items (rotating on load)
                var sponsors = (window.ynjPreloaded && window.ynjPreloaded.sponsors) || [];
                var sponsorSlug = mosqueSlug || localStorage.getItem('ynj_mosque_slug') || '';
                var html = '';
                var adIdx = Math.floor(Math.random() * Math.max(1, sponsors.length)); // Random start for rotation
                for (var fi = 0; fi < items.length; fi++) {
                    html += renderFeedCard(items[fi]);
                    // Insert sponsor ad after every 2nd item
                    if (sponsors.length > 0 && (fi + 1) % 2 === 0) {
                        var sp = sponsors[adIdx % sponsors.length];
                        adIdx++;
                        html += '<div class="ynj-fc" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:14px;padding:14px;margin-bottom:10px;">' +
                            '<div style="display:flex;align-items:center;gap:10px;">' +
                                '<div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">⭐</div>' +
                                '<div style="flex:1;min-width:0;">' +
                                    '<div style="font-size:13px;font-weight:700;color:#92400e;">' + (sp.business_name || 'Sponsor') + '</div>' +
                                    '<div style="font-size:11px;color:#a16207;">' + (sp.category || 'Business') + '</div>' +
                                '</div>' +
                                '<a href="/mosque/' + sponsorSlug + '/sponsors" style="font-size:11px;font-weight:700;color:#92400e;text-decoration:none;white-space:nowrap;">View →</a>' +
                            '</div>' +
                            '<a href="/mosque/' + sponsorSlug + '/sponsors/join" style="display:block;text-align:center;margin-top:8px;padding:6px;border-radius:6px;background:rgba(245,158,11,.15);font-size:11px;font-weight:600;color:#92400e;text-decoration:none;">⭐ Advertise your business here — from £30/mo</a>' +
                        '</div>';
                    }
                }
                el.innerHTML = '<div class="ynj-feed">' + html + '</div>';
            }

            window.filterFeed = function(filter) {
                currentFeedFilter = filter;
                document.querySelectorAll('#feed-filters .ynj-chip').forEach(c => {
                    c.classList.toggle('ynj-chip--active', c.dataset.filter === filter);
                });
                renderFeed();
            };

            window.onRadiusChange = function() {
                const sel = document.getElementById('ynj-radius');
                currentRadius = parseInt(sel.value) || 0;
                if (currentRadius === 0) {
                    // Back to this masjid only
                    renderFeed();
                    return;
                }
                // Load nearby content if not already loaded
                if (!nearbyLoaded) {
                    loadNearbyFeed();
                } else {
                    renderFeed();
                }
            };

            function loadNearbyFeed() {
                const el = document.getElementById('feed-list');
                const lat = userLat || mosqueLat;
                const lng = userLng || mosqueLng;
                if (!lat) {
                    el.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Enable GPS or enter your postcode to discover nearby events.</p>';
                    return;
                }

                el.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Finding events near you...</p>';

                const radiusKm = currentRadius === 9999 ? 9999 : currentRadius * 1.609;
                fetch(`${API}/mosques/nearest?lat=${lat}&lng=${lng}&limit=15&radius_km=${radiusKm}`)
                    .then(r => r.json())
                    .then(data => {
                        const mosques = (data.mosques||[]).filter(m => m.slug !== mosqueSlug);
                        if (!mosques.length) {
                            nearbyFeedItems = [];
                            nearbyLoaded = true;
                            renderFeed();
                            return;
                        }

                        const eventFetches = mosques.slice(0,10).map(m =>
                            fetch(`${API}/mosques/${m.slug}/events?upcoming=1`).then(r=>r.json())
                                .then(d => (d.events||[]).map(e => ({...e, _src:'event', mosque_name:m.name, mosque_slug:m.slug, distance:m.distance})))
                                .catch(() => [])
                        );
                        const classFetches = mosques.slice(0,10).map(m =>
                            fetch(`${API}/mosques/${m.slug}/classes`).then(r=>r.json())
                                .then(d => (d.classes||[]).map(c => ({...c, _src:'class', mosque_name:m.name, mosque_slug:m.slug, distance:m.distance})))
                                .catch(() => [])
                        );
                        return Promise.all([...eventFetches, ...classFetches]);
                    })
                    .then(results => {
                        if (!results) { nearbyLoaded = true; renderFeed(); return; }
                        nearbyFeedItems = [];
                        results.flat().forEach(e => {
                            const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                            const distLabel = e.distance ? ` · ${e.distance < 1.6 ? (e.distance*0.621).toFixed(1)+'mi' : Math.round(e.distance*0.621)+'mi'}` : '';
                            const mosqueName = (e.mosque_name||'') + distLabel;

                            if (e._src === 'class') {
                                const price = e.price_pence > 0 ? '£'+(e.price_pence/100) : 'Free';
                                nearbyFeedItems.push({
                                    type:'class', title:e.title, body:e.description||'',
                                    date:e.start_date||'', time:time, location:e.location||'',
                                    event_type:'class', mosque_slug:e.mosque_slug,
                                    class_id:e.id, instructor:e.instructor_name||'', price:price,
                                    day_of_week:e.day_of_week||'', mosque_name:mosqueName
                                });
                            } else {
                                const isLive = e.is_live && e.is_online;
                                const ticketPrice = e.ticket_price_pence > 0 ? '£'+(e.ticket_price_pence/100).toFixed(e.ticket_price_pence%100?2:0) : '';
                                nearbyFeedItems.push({
                                    type: isLive ? 'live' : 'event',
                                    title:e.title, body:e.description||'', date:e.event_date||'',
                                    time:time, location:e.location||'', event_id:e.id, mosque_slug:e.mosque_slug,
                                    event_type: isLive ? 'live' : (e.event_type||''),
                                    live_url: e.live_url||'', mosque_name:mosqueName,
                                    ticket_price: ticketPrice,
                                    donation_target: e.donation_target_pence > 0 ? '£'+(e.donation_target_pence/100).toLocaleString() : ''
                                });
                            }
                        });
                        nearbyLoaded = true;
                        renderFeed();
                    })
                    .catch(() => {
                        nearbyLoaded = true;
                        renderFeed();
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
            // Mosque selector is now handled by theme.js modal (ynj-mosque-modal).
            // This function is kept as a no-op for the init() call.
            function setupMosqueSelector() {
                // Modal open/close/search handled by theme.js
            }

            function populateDropdown(mosques) {
                // No longer used — theme.js modal handles rendering
            }

            /* ---- Location: GPS only, no postcode ---- */

            /* ---- Prayer Overview ---- */
            function renderPrayerOverview() {
                if (!prayerTimes) return;
                const now = new Date();
                const prayers = ['fajr','dhuhr','asr','maghrib','isha'];
                const labels = {fajr:'Fajr',dhuhr:'Dhuhr',asr:'Asr',maghrib:'Maghrib',isha:'Isha'};
                let foundNext = false;

                const html = prayers.map(p => {
                    if (!prayerTimes[p]) return '';
                    const adhan = prayerTimes[p];
                    const jamat = jamatTimes[p+'_jamat'] || '';
                    const [h,m] = adhan.split(':').map(Number);
                    const t = new Date(now); t.setHours(h,m,0,0);
                    const isPast = t <= now;
                    const isNext = !isPast && !foundNext;
                    if (isNext) foundNext = true;

                    let leaveText = '';
                    if (!isPast && travelMinutes) {
                        const target = jamat || adhan;
                        const [th,tm] = target.split(':').map(Number);
                        const tt = new Date(now); tt.setHours(th,tm,0,0);
                        const leave = new Date(tt.getTime() - (travelMinutes + bufferMinutes) * 60000);
                        leaveText = `Leave ${String(leave.getHours()).padStart(2,'0')}:${String(leave.getMinutes()).padStart(2,'0')}`;
                    }

                    const cls = isNext ? ' ynj-po--next' : (isPast ? ' ynj-po--past' : '');
                    return `<div class="ynj-po${cls}">
                        <div class="ynj-po__name">${labels[p]}</div>
                        <div class="ynj-po__time">${adhan}</div>
                        ${jamat ? `<div class="ynj-po__jamat">Jam ${jamat}</div>` : ''}
                        ${leaveText ? `<div class="ynj-po__leave">${leaveText}</div>` : ''}
                    </div>`;
                }).join('');

                document.getElementById('prayer-overview-grid').innerHTML = html;
                document.getElementById('prayer-overview').style.display = '';
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
                // travel-dist removed
                document.getElementById('hero-travel').style.display = '';
                document.getElementById('travel-settings').style.display = '';

                const modeEl = document.getElementById('mode-select');
                const bufEl = document.getElementById('buffer-select');
                if (modeEl) modeEl.value = travelMode;
                if (bufEl) bufEl.value = bufferMinutes;

                updateLeaveBy();
                renderPrayerOverview();
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

            /* Postcode functions removed — GPS only */

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
            if (subBtn) subBtn.addEventListener('click', async () => {
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
                // SW registration handled globally by theme.js — no duplicate needed
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
