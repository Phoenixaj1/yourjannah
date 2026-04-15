<?php
/**
 * Template: Events Page
 *
 * Mosque events with Your Mosque / Nearby tabs, filter chips, 2-col grid.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<style>
.ynj-ev-card{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:16px;border:1px solid rgba(255,255,255,.6);overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04);transition:transform .15s;}
.ynj-ev-card:hover{transform:translateY(-1px);}
.ynj-ev-card--live{border:2px solid #dc2626;box-shadow:0 4px 20px rgba(220,38,38,.1);}
.ynj-ev-img{width:100%;height:140px;object-fit:cover;background:#e8f4f8;display:flex;align-items:center;justify-content:center;font-size:48px;}
.ynj-ev-body{padding:16px;}
.ynj-ev-badges{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;}
.ynj-ev-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:6px;}
.ynj-ev-badge--type{background:#e8f4f8;color:#00ADEF;}
.ynj-ev-badge--live{background:#fee2e2;color:#dc2626;animation:livePulse 2s ease-in-out infinite;}
.ynj-ev-badge--free{background:#dcfce7;color:#166534;}
.ynj-ev-badge--paid{background:#fef3c7;color:#92400e;}
.ynj-ev-badge--online{background:#ede9fe;color:#7c3aed;}
@keyframes livePulse{0%,100%{opacity:1}50%{opacity:.6}}
.ynj-ev-title{font-size:16px;font-weight:700;margin-bottom:6px;line-height:1.3;}
.ynj-ev-meta{display:flex;flex-direction:column;gap:4px;margin-bottom:10px;}
.ynj-ev-meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:#6b8fa3;}
.ynj-ev-meta-item svg{flex-shrink:0;width:14px;height:14px;color:#00ADEF;}
.ynj-ev-desc{font-size:13px;color:#555;line-height:1.5;margin-bottom:12px;}
.ynj-ev-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;}
.ynj-ev-capacity{font-size:12px;color:#6b8fa3;}
.ynj-ev-capacity-bar{height:4px;width:60px;background:#e5e7eb;border-radius:2px;display:inline-block;vertical-align:middle;margin-left:4px;}
.ynj-ev-capacity-bar span{display:block;height:100%;border-radius:2px;background:#00ADEF;}
.ynj-ev-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .15s;}
.ynj-ev-btn--primary{background:#00ADEF;color:#fff;}
.ynj-ev-btn--primary:hover{opacity:.9;}
.ynj-ev-btn--live{background:#dc2626;color:#fff;}
.ynj-ev-btn--outline{background:transparent;border:1px solid #ddd;color:#0a1628;}
.ynj-ev-empty{text-align:center;padding:40px 20px;}
.ynj-ev-empty div{font-size:48px;margin-bottom:12px;}
.ynj-ev-filter{display:flex;gap:6px;overflow-x:auto;padding:4px 0 14px;scrollbar-width:none;-webkit-overflow-scrolling:touch;max-width:100%;}
.ynj-ev-filter::-webkit-scrollbar{display:none;}
.ynj-ev-chip{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #ddd;background:#fff;color:#0a1628;cursor:pointer;white-space:nowrap;transition:all .15s;}
.ynj-ev-chip--active{background:#00ADEF;color:#fff;border-color:#00ADEF;}
#ev-feed{display:grid;grid-template-columns:1fr;gap:14px;}
@media(min-width:700px){#ev-feed{grid-template-columns:1fr 1fr;}}
.ynj-ev-mosque-tag{font-size:11px;color:#00ADEF;font-weight:600;margin-top:4px;}
.ynj-ev-empty{grid-column:1/-1;}
</style>

<main class="ynj-main">
    <h2 id="ev-title" style="font-size:18px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Events', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:12px;"><?php esc_html_e( 'Upcoming events', 'yourjannah' ); ?></p>

    <div class="ynj-ev-filter" id="ev-filters">
        <button class="ynj-ev-chip ynj-ev-chip--active" data-filter="all" onclick="filterEv('all')"><?php esc_html_e( 'All', 'yourjannah' ); ?></button>
        <button class="ynj-ev-chip" data-filter="_live" onclick="filterEv('_live')">🔴 <?php esc_html_e( 'Live', 'yourjannah' ); ?></button>
        <button class="ynj-ev-chip" data-filter="talk" onclick="filterEv('talk')">🎤 <?php esc_html_e( 'Talks', 'yourjannah' ); ?></button>
        <button class="ynj-ev-chip" data-filter="class" onclick="filterEv('class')">🎓 <?php esc_html_e( 'Classes', 'yourjannah' ); ?></button>
        <button class="ynj-ev-chip" data-filter="community,iftar" onclick="filterEv('community,iftar')">🤝 <?php esc_html_e( 'Community', 'yourjannah' ); ?></button>
        <button class="ynj-ev-chip" data-filter="youth,kids,children" onclick="filterEv('youth,kids,children')">👦 <?php esc_html_e( 'Youth', 'yourjannah' ); ?></button>
        <button class="ynj-ev-chip" data-filter="sisters" onclick="filterEv('sisters')">👩 <?php esc_html_e( 'Sisters', 'yourjannah' ); ?></button>
        <button class="ynj-ev-chip" data-filter="sports,competition" onclick="filterEv('sports,competition')">⚽ <?php esc_html_e( 'Sports', 'yourjannah' ); ?></button>
    </div>
    <div id="ev-feed"><p class="ynj-text-muted" style="text-align:center;padding:20px;">Loading events&hellip;</p></div>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;
    let allEvents = [];
    let nearbyEvents = [];
    let nearbyLoaded = false;
    let currentFilter = 'all';
    let mosqueLat = null, mosqueLng = null;

    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
        el.href = el.dataset.navMosque.replace('{slug}', slug);
    });

    const typeIcons = {
        'talk':'🎤','class':'📖','course':'🎓','workshop':'🛠️','community':'🤝',
        'sports':'⚽','competition':'🏆','youth':'👦','kids':'🧒','children':'🧒',
        'sisters':'👩','fundraiser':'💰','iftar':'🍽️','eid':'🌙','quran':'📖',
        'halaqa':'📚','nikah':'💍','janazah':'🕊️','other':'📌'
    };

    function fmtDate(d) {
        if (!d) return '';
        const dt = new Date(d + 'T00:00:00');
        const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return days[dt.getDay()] + ' ' + dt.getDate() + ' ' + months[dt.getMonth()];
    }

    function fmtTime(t) {
        return t ? String(t).replace(/:\d{2}$/, '') : '';
    }

    function renderEvent(e) {
        const icon = typeIcons[e.event_type] || typeIcons[e.type] || '📅';
        const isLive = e.is_live && e.live_url;
        const isFree = !e.ticket_price_pence || e.ticket_price_pence <= 0;
        const isOnline = e.is_online;
        const price = !isFree ? '£' + (e.ticket_price_pence / 100).toFixed(2) : '';
        const spotsLeft = e.max_capacity > 0 ? Math.max(0, e.max_capacity - (e.registered_count||0)) : null;
        const pctFull = e.max_capacity > 0 ? Math.min(100, Math.round(((e.registered_count||0) / e.max_capacity) * 100)) : 0;
        const snippet = (e.description||'').length > 150 ? e.description.slice(0,150)+'...' : (e.description||'');
        const detailUrl = <?php echo wp_json_encode( home_url( '/mosque/' ) ); ?> + slug + '/events/' + e.id;

        let badges = '';
        if (isLive) badges += '<span class="ynj-ev-badge ynj-ev-badge--live">🔴 LIVE NOW</span>';
        badges += '<span class="ynj-ev-badge ynj-ev-badge--type">' + icon + ' ' + (e.event_type || 'Event') + '</span>';
        if (isFree) badges += '<span class="ynj-ev-badge ynj-ev-badge--free">Free</span>';
        else badges += '<span class="ynj-ev-badge ynj-ev-badge--paid">' + price + '</span>';
        if (isOnline) badges += '<span class="ynj-ev-badge ynj-ev-badge--online">Online</span>';

        let cta = '';
        if (isLive) {
            cta = '<a href="' + detailUrl + '" class="ynj-ev-btn ynj-ev-btn--live">🔴 Watch Live</a>';
        } else if (e.requires_booking || !isFree) {
            cta = '<a href="' + detailUrl + '" class="ynj-ev-btn ynj-ev-btn--primary">' + (isFree ? 'RSVP Free' : 'Book ' + price) + '</a>';
        } else {
            cta = '<a href="' + detailUrl + '" class="ynj-ev-btn ynj-ev-btn--outline">View Details</a>';
        }

        let capacityHtml = '';
        if (spotsLeft !== null) {
            if (spotsLeft <= 0) {
                capacityHtml = '<span class="ynj-ev-capacity" style="color:#dc2626;font-weight:700;">SOLD OUT</span>';
            } else {
                capacityHtml = '<span class="ynj-ev-capacity">' + spotsLeft + ' spots left <span class="ynj-ev-capacity-bar"><span style="width:' + pctFull + '%"></span></span></span>';
            }
        }

        return '<div class="ynj-ev-card' + (isLive ? ' ynj-ev-card--live' : '') + '">' +
            '<div class="ynj-ev-body">' +
            '<div class="ynj-ev-badges">' + badges + '</div>' +
            '<h3 class="ynj-ev-title">' + e.title + '</h3>' +
            '<div class="ynj-ev-meta">' +
            '<div class="ynj-ev-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' + fmtDate(e.event_date) + '</div>' +
            (fmtTime(e.start_time) ? '<div class="ynj-ev-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>' + fmtTime(e.start_time) + (fmtTime(e.end_time) ? ' — ' + fmtTime(e.end_time) : '') + '</div>' : '') +
            (e.location ? '<div class="ynj-ev-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"/><path d="M12 2C7.6 2 4 5.4 4 9.5 4 14.3 12 22 12 22s8-7.7 8-12.5C20 5.4 16.4 2 12 2z"/></svg>' + e.location + '</div>' : '') +
            '</div>' +
            (snippet ? '<p class="ynj-ev-desc">' + snippet + '</p>' : '') +
            '<div class="ynj-ev-footer">' + cta + '<button class="ynj-ev-btn ynj-ev-btn--outline" onclick="ynjShare(\'' + e.title.replace(/'/g,"\\'") + '\',\'\',\'' + detailUrl + '\')">↗ Share</button>' + capacityHtml + '</div>' +
            '</div></div>';
    }

    function renderAll() {
        const feed = document.getElementById('ev-feed');
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        let combined = radius > 0 ? allEvents.concat(nearbyEvents) : allEvents;
        combined.sort((a,b) => (a.event_date||'').localeCompare(b.event_date||''));

        let filtered = combined;
        if (currentFilter !== 'all') {
            const types = currentFilter.split(',');
            if (currentFilter === '_live') {
                filtered = combined.filter(e => e.is_live && e.live_url);
            } else {
                filtered = combined.filter(e => types.includes(e.event_type));
            }
        }
        if (!filtered.length) {
            feed.innerHTML = '<div class="ynj-ev-empty"><div>📅</div><h3>No Events Found</h3><p class="ynj-text-muted">' + (currentFilter === 'all' ? 'No upcoming events at this mosque yet. Try widening the radius in the header or check back soon.' : 'No events match this filter. Try "All".') + '</p></div>';
            return;
        }
        feed.innerHTML = filtered.map(e => {
            let card = renderEvent(e);
            if (e._mosque_name) {
                const dist = e._distance ? ' · ' + (e._distance < 1.6 ? (e._distance*0.621).toFixed(1) : Math.round(e._distance*0.621)) + ' mi' : '';
                card = card.replace('</div></div>', '<div class="ynj-ev-mosque-tag">🕌 ' + e._mosque_name + dist + '</div></div></div>');
            }
            return card;
        }).join('');
    }

    window.filterEv = function(filter) {
        currentFilter = filter;
        document.querySelectorAll('.ynj-ev-chip').forEach(c => {
            c.classList.toggle('ynj-ev-chip--active', c.dataset.filter === filter);
        });
        renderAll();
    };

    // Load mosque info
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            mosqueLat = m.latitude; mosqueLng = m.longitude;
            document.getElementById('ev-title').textContent = (m.name || 'Your Mosque') + ' Events';
        }).catch(() => {});

    // Load events
    fetch(API + 'mosques/' + slug + '/events?upcoming=1')
        .then(r => r.json())
        .then(data => {
            allEvents = data.events || [];
            renderAll();
        })
        .catch(() => {
            document.getElementById('ev-feed').innerHTML = '<div class="ynj-ev-empty"><div>😕</div><h3>Could Not Load</h3><p class="ynj-text-muted">Please check your connection and try again.</p></div>';
        });

    // Radius change — load nearby events
    window.onRadiusChange = function() {
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        if (radius === 0) { nearbyEvents = []; renderAll(); return; }
        if (nearbyLoaded) { renderAll(); return; }
        if (!mosqueLat) { renderAll(); return; }

        document.getElementById('ev-feed').innerHTML = '<p class="ynj-text-muted" style="text-align:center;padding:20px;">Loading nearby events...</p>';
        const radiusKm = radius === 9999 ? 9999 : radius * 1.609;
        fetch(API + 'mosques/nearest?lat=' + mosqueLat + '&lng=' + mosqueLng + '&limit=10&radius_km=' + radiusKm)
            .then(r => r.json())
            .then(data => {
                const mosques = (data.mosques || []).filter(m => m.slug !== slug);
                return Promise.all(mosques.slice(0,8).map(m =>
                    fetch(API + 'mosques/' + m.slug + '/events?upcoming=1').then(r => r.json())
                        .then(d => (d.events||[]).map(e => Object.assign(e, {_mosque_name:m.name, _distance:m.distance})))
                        .catch(() => [])
                ));
            })
            .then(results => {
                nearbyEvents = (results||[]).flat();
                nearbyLoaded = true;
                renderAll();
            })
            .catch(() => { nearbyLoaded = true; renderAll(); });
    };
})();
</script>
<?php get_footer(); ?>
