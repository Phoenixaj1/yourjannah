        (function(){
            'use strict';

            const API = ynjData.restUrl.replace(/\/+$/, '');
            const VAPID = ynjData.vapidKey;

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
                    document.getElementById('nav-buttons').style.display = ''; document.getElementById('hero-gps-prompt').style.display = 'none';
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
                            document.getElementById('nav-buttons').style.display = ''; document.getElementById('hero-gps-prompt').style.display = 'none';
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

                        // Store jamat overrides if present
                        if (m.prayer_times) {
                            ['fajr_jamat','dhuhr_jamat','asr_jamat','maghrib_jamat','isha_jamat'].forEach(k => {
                                if (m.prayer_times[k]) {
                                    jamatTimes[k] = String(m.prayer_times[k]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                                }
                            });
                        }

                        // Check if we have actual adhan times (not just jamat overrides)
                        const hasAdhan = m.prayer_times && m.prayer_times.fajr && m.prayer_times.maghrib && !m.prayer_times.error;
                        if (hasAdhan) {
                            setPrayerTimes(m.prayer_times);
                        } else {
                            // Always fallback to client-side Aladhan (browser CAN reach it even if server can't)
                            const lat = m.latitude || userLat;
                            const lng = m.longitude || userLng;
                            if (lat && lng) fetchAladhan(lat, lng);
                        }

                        // If we didn't get travel from GPS, try from mosque coords
                        if (userLat && !travelMinutes && m.latitude && m.longitude) {
                            const km = haversine(userLat, userLng, m.latitude, m.longitude);
                            travelMinutes = Math.max(1, Math.round(km * 12));
                            const distText = km < 1 ? `${Math.round(km*1000)}m` : `${km.toFixed(1)}km`;
                            document.getElementById('travel-dist').textContent = `${distText} · ~${travelMinutes} min walk`;
                            document.getElementById('hero-travel').style.display = '';
                            document.getElementById('nav-buttons').style.display = ''; document.getElementById('hero-gps-prompt').style.display = 'none';
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
                        prayerTimes[p] = String(times[p]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                    }
                });
                updateCountdown();
                setInterval(updateCountdown, 1000);
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
                let cardClass, badge;

                if (item.type === 'live') {
                    cardClass = 'ynj-feed-card--event';
                    badge = '<span class="ynj-badge" style="background:#fee2e2;color:#dc2626;">🔴 LIVE</span>';
                } else if (item.type === 'class') {
                    cardClass = 'ynj-feed-card--event';
                    badge = `<span class="ynj-badge" style="background:#ede9fe;color:#7c3aed;">🎓 Class${item.price ? ' · '+item.price : ''}</span>`;
                } else if (item.type === 'event') {
                    cardClass = 'ynj-feed-card--event';
                    const et = (item.event_type||'').toLowerCase();
                    const icon = eventTypeIcons[et] || '📅';
                    const label = (item.event_type||'Event').charAt(0).toUpperCase() + (item.event_type||'event').slice(1);
                    badge = `<span class="ynj-badge ynj-badge--event">${icon} ${label}</span>`;
                } else {
                    cardClass = item.pinned ? 'ynj-feed-card--pinned' : 'ynj-feed-card--announcement';
                    badge = item.pinned ? '<span class="ynj-badge ynj-badge--pinned">📌 Pinned</span>' : '<span class="ynj-badge">📢 Update</span>';
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

                // Calendar date strip
                let dateStrip = '';
                const dateStr = item.date || item.start_date || '';
                if (dateStr && item.type !== 'announcement') {
                    const d = new Date(dateStr + 'T00:00:00');
                    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    dateStrip = `<div class="ynj-feed-card__date">
                        <span class="ynj-feed-card__date-day">${days[d.getDay()]}</span>
                        <span class="ynj-feed-card__date-num">${d.getDate()}</span>
                        <span class="ynj-feed-card__date-month">${months[d.getMonth()]}</span>
                    </div>`;
                } else if (item.type === 'announcement') {
                    dateStrip = `<div class="ynj-feed-card__date" style="background:${item.pinned ? '#dcfce7' : '#e8f4f8'};">
                        <span style="font-size:18px;">${item.pinned ? '📌' : '📢'}</span>
                    </div>`;
                }

                const liveClass = item.type === 'live' ? ' ynj-feed-card--live' : '';
                const classClass = item.type === 'class' ? ' ynj-feed-card--class' : '';

                return `<div class="ynj-feed-card ${cardClass}${liveClass}${classClass}">
                    ${dateStrip}
                    <div class="ynj-feed-card__content">
                        <div class="ynj-feed-card__top">${badge}<h4>${item.title}</h4></div>
                        ${snippet ? `<div class="ynj-feed-card__body">${snippet}</div>` : ''}
                        <div class="ynj-feed-card__meta">${meta.join(' ')}</div>
                        ${mosqueTag}
                    </div>
                </div>`;
            }

            let allLocalItems = [];

            function loadFeed(slug) {
                Promise.all([
                    fetch(`${API}/mosques/${slug}/announcements`).then(r => r.json()).catch(() => ({announcements:[]})),
                    fetch(`${API}/mosques/${slug}/events?upcoming=1`).then(r => r.json()).catch(() => ({events:[]})),
                    fetch(`${API}/mosques/${slug}/classes`).then(r => r.json()).catch(() => ({classes:[]}))
                ]).then(([aData, eData, cData]) => {
                    allLocalItems = [];
                    (aData.announcements || []).forEach(a => {
                        allLocalItems.push({ type:'announcement', title:a.title, body:a.body, date:a.published_at||'', pinned:a.pinned });
                    });
                    (eData.events || []).forEach(e => {
                        const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                        const isLive = e.is_live && e.is_online;
                        const ticketPrice = e.ticket_price_pence > 0 ? '£'+(e.ticket_price_pence/100).toFixed(e.ticket_price_pence%100?2:0) : '';
                        allLocalItems.push({
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
                        allLocalItems.push({
                            type:'class', title:c.title, body:c.description||'',
                            date:c.start_date||'', time:time, location:c.location||'',
                            event_type:'class', mosque_slug:slug,
                            class_id:c.id, instructor:c.instructor_name||'', price:price,
                            day_of_week:c.day_of_week||''
                        });
                    });
                    allLocalItems.sort((a,b) => {
                        // Pinned announcements always first
                        if(a.pinned&&!b.pinned)return -1; if(!a.pinned&&b.pinned)return 1;
                        // Live events next
                        if(a.type==='live'&&b.type!=='live')return -1; if(a.type!=='live'&&b.type==='live')return 1;
                        // Announcements after live but before events
                        if(a.type==='announcement'&&b.type!=='announcement')return -1;
                        if(a.type!=='announcement'&&b.type==='announcement')return 1;
                        // Events and classes: nearest date first (ascending)
                        if(a.type!=='announcement'&&b.type!=='announcement') return (a.date||'9').localeCompare(b.date||'9');
                        // Announcements: newest first (descending)
                        return (b.date||'').localeCompare(a.date||'');
                    });
                    renderLocalFeed('all');
                });
            }

            function renderLocalFeed(filter) {
                const el = document.getElementById('local-feed-list');
                let items = allLocalItems;

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

                        // Fetch events AND classes from nearby mosques
                        const eventFetches = mosques.slice(0,8).map(m =>
                            fetch(`${API}/mosques/${m.slug}/events?upcoming=1`).then(r=>r.json())
                                .then(d => (d.events||[]).map(e => ({...e, _type:'event', mosque_name:m.name, mosque_slug:m.slug, distance:m.distance})))
                                .catch(() => [])
                        );
                        const classFetches = mosques.slice(0,8).map(m =>
                            fetch(`${API}/mosques/${m.slug}/classes`).then(r=>r.json())
                                .then(d => (d.classes||[]).map(c => ({...c, _type:'class', mosque_name:m.name, mosque_slug:m.slug, distance:m.distance})))
                                .catch(() => [])
                        );
                        return Promise.all([...eventFetches, ...classFetches]);
                    })
                    .then(results => {
                        if (!results) return;
                        allWiderEvents = results.flat().sort((a,b) => {
                            // Live first, then by date
                            if (a.is_live && !b.is_live) return -1;
                            if (!a.is_live && b.is_live) return 1;
                            return ((a.event_date||a.start_date)||'').localeCompare((b.event_date||b.start_date)||'');
                        });
                        renderWiderEvents('all');
                    })
                    .catch(() => { el.innerHTML = '<p class="ynj-text-muted" style="padding:16px;text-align:center;">Could not load.</p>'; });
            }

            function renderWiderEvents(filter) {
                const el = document.getElementById('wider-events-list');
                let events = allWiderEvents;

                if (filter && filter !== 'all') {
                    const types = filter.split(',');
                    events = events.filter(e => {
                        if (e._type === 'class') return types.includes('class');
                        return types.includes((e.event_type||'').toLowerCase());
                    });
                }

                if (!events.length) {
                    el.innerHTML = filter === 'all'
                        ? '<p class="ynj-text-muted" style="padding:16px;text-align:center;">No upcoming events at nearby mosques.</p>'
                        : '<p class="ynj-text-muted" style="padding:16px;text-align:center;">No events matching this filter. Try "All".</p>';
                    return;
                }

                el.innerHTML = '<div class="ynj-feed">' + events.map(e => {
                    const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
                    const distLabel = e.distance ? ` · ${e.distance < 1.6 ? (e.distance*0.621).toFixed(1)+'mi' : Math.round(e.distance*0.621)+'mi'}` : '';
                    const mosqueName = e.mosque_name + distLabel;

                    if (e._type === 'class') {
                        const price = e.price_pence > 0 ? '£'+(e.price_pence/100) : 'Free';
                        return renderFeedCard({
                            type:'class', title:e.title, body:e.description||'',
                            date:e.start_date||'', time:time, location:e.location||'',
                            event_type:'class', mosque_slug:e.mosque_slug,
                            class_id:e.id, instructor:e.instructor_name||'', price:price,
                            day_of_week:e.day_of_week||'', mosque_name:mosqueName
                        });
                    }

                    const isLive = e.is_live && e.is_online;
                    const ticketPrice = e.ticket_price_pence > 0 ? '£'+(e.ticket_price_pence/100).toFixed(e.ticket_price_pence%100?2:0) : '';
                    return renderFeedCard({
                        type: isLive ? 'live' : 'event',
                        title:e.title, body:e.description||'', date:e.event_date||'',
                        time:time, location:e.location||'', event_id:e.id, mosque_slug:e.mosque_slug,
                        event_type: isLive ? 'live' : (e.event_type||''),
                        live_url: e.live_url||'', mosque_name: mosqueName,
                        ticket_price: ticketPrice,
                        donation_target: e.donation_target_pence > 0 ? '£'+(e.donation_target_pence/100).toLocaleString() : ''
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

            /* ---- Location Bar ---- */
            (function initLocationBar() {
                const saved = localStorage.getItem('ynj_user_postcode');
                const input = document.getElementById('location-postcode');
                if (saved) input.value = saved;
                if (userLat) {
                    // GPS active — show as detected
                    input.placeholder = 'GPS detected — or enter postcode';
                }
            })();

            window.updatePostcode = function() {
                const pc = document.getElementById('location-postcode').value.trim().replace(/\s+/g, '');
                if (pc.length < 3) return;
                localStorage.setItem('ynj_user_postcode', pc);
                document.getElementById('location-update').textContent = '...';
                geocodePostcode(pc, mosqueLat, mosqueLng);
                setTimeout(() => { document.getElementById('location-update').textContent = 'Update'; }, 2000);
            };

            // Allow enter key
            document.getElementById('location-postcode').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') updatePostcode();
            });

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
                document.getElementById('travel-dist').textContent = `${distText} · ~${travelMinutes} min ${modeLabel[travelMode]}`;
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
