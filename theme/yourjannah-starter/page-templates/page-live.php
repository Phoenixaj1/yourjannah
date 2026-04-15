<?php
/**
 * Template: Live / Streams Page
 *
 * Mosque-scoped: Live now, upcoming streams, and past recordings.
 * Radius selector widens to nearby mosques.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<style>
.ynj-live-tabs{display:flex;gap:0;margin-bottom:16px;background:rgba(255,255,255,.6);border-radius:12px;padding:3px;border:1px solid rgba(0,173,239,.1);}
.ynj-live-tab{flex:1;padding:9px 8px;border:none;background:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;color:#6b8fa3;font-family:inherit;transition:all .15s;text-align:center;}
.ynj-live-tab--active{background:#00ADEF;color:#fff;box-shadow:0 2px 8px rgba(0,173,239,.25);}
.ynj-live-card{background:rgba(255,255,255,.92);border-radius:16px;overflow:hidden;margin-bottom:14px;border:1px solid rgba(0,0,0,.06);box-shadow:0 2px 10px rgba(0,0,0,.05);}
.ynj-live-card--live{border:2px solid #dc2626;background:#fff1f2;}
.ynj-live-card__video{width:100%;aspect-ratio:16/9;background:#0a1628;display:flex;align-items:center;justify-content:center;position:relative;}
.ynj-live-card__video iframe{width:100%;height:100%;border:none;}
.ynj-live-card__body{padding:16px;}
.ynj-live-card__body h3{font-size:16px;font-weight:700;margin:6px 0 4px;}
.ynj-live-card__meta{display:flex;flex-wrap:wrap;gap:8px;font-size:12px;color:#6b8fa3;margin-bottom:10px;}
.ynj-live-card__mosque{font-size:11px;color:#00ADEF;font-weight:600;margin-bottom:8px;}
.ynj-live-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.ynj-live-badge--live{background:#dc2626;color:#fff;}
.ynj-live-badge--live::before{content:'';width:8px;height:8px;background:#fff;border-radius:50%;animation:livePulse 1.5s ease-in-out infinite;}
.ynj-live-badge--upcoming{background:#f59e0b;color:#fff;}
.ynj-live-badge--archive{background:#6b7280;color:#fff;}
@keyframes livePulse{0%,100%{opacity:1}50%{opacity:.3}}
.ynj-live-empty{text-align:center;padding:40px 20px;}
.ynj-live-empty div{font-size:48px;margin-bottom:12px;}
@media(min-width:700px){.ynj-live-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}.ynj-live-card{margin-bottom:0;}}
</style>

<main class="ynj-main">
    <h2 id="live-title" style="font-size:18px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Live & Streams', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:14px;"><?php esc_html_e( 'Watch live, catch up on recordings, or see what\'s coming up', 'yourjannah' ); ?></p>

    <div class="ynj-live-tabs">
        <button class="ynj-live-tab ynj-live-tab--active" id="lt-all" onclick="filterLive('all')"><?php esc_html_e( 'All', 'yourjannah' ); ?></button>
        <button class="ynj-live-tab" id="lt-live" onclick="filterLive('live')">🔴 <?php esc_html_e( 'Live Now', 'yourjannah' ); ?></button>
        <button class="ynj-live-tab" id="lt-upcoming" onclick="filterLive('upcoming')">📅 <?php esc_html_e( 'Upcoming', 'yourjannah' ); ?></button>
        <button class="ynj-live-tab" id="lt-archive" onclick="filterLive('archive')">📼 <?php esc_html_e( 'Archive', 'yourjannah' ); ?></button>
    </div>

    <div class="ynj-live-grid" id="live-list">
        <p class="ynj-text-muted" style="padding:20px;text-align:center;grid-column:1/-1;"><?php esc_html_e( 'Loading...', 'yourjannah' ); ?></p>
    </div>
</main>

<script>
(function(){
    var slug = <?php echo wp_json_encode( $slug ); ?>;
    var API  = ynjData.restUrl;
    var allEvents = [];
    var nearbyEvents = [];
    var nearbyLoaded = false;
    var mosqueLat = null, mosqueLng = null;
    var today = new Date().toISOString().slice(0,10);

    function getEmbed(url) {
        if (!url) return '';
        var m = url.match(/(?:youtube\.com\/(?:watch\?v=|live\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        return m ? '<iframe src="https://www.youtube.com/embed/' + m[1] + '?autoplay=0" allow="autoplay;encrypted-media" allowfullscreen loading="lazy"></iframe>'
            : '<a href="' + url + '" target="_blank" rel="noopener" style="color:#fff;font-size:14px;text-decoration:underline;">▶ Open Stream</a>';
    }

    function classify(e) {
        if (e.is_live && e.is_online) return 'live';
        if ((e.is_online || e.live_url) && e.event_date >= today) return 'upcoming';
        if ((e.is_online || e.live_url) && e.event_date < today) return 'archive';
        if (e.event_date >= today) return 'upcoming';
        return 'archive';
    }

    function fmtDate(d) {
        if (!d) return '';
        var dt = new Date(d + 'T00:00:00');
        var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return days[dt.getDay()] + ' ' + dt.getDate() + ' ' + months[dt.getMonth()];
    }

    function fmtTime(t) { return t ? String(t).replace(/:\d{2}$/, '') : ''; }

    function renderCard(e) {
        var cls = classify(e);
        var badge = cls === 'live' ? '<span class="ynj-live-badge ynj-live-badge--live">LIVE</span>'
            : cls === 'upcoming' ? '<span class="ynj-live-badge ynj-live-badge--upcoming">Upcoming</span>'
            : '<span class="ynj-live-badge ynj-live-badge--archive">Recording</span>';

        var liveClass = cls === 'live' ? ' ynj-live-card--live' : '';
        var video = '';
        if (cls === 'live' && e.live_url) {
            video = '<div class="ynj-live-card__video">' + getEmbed(e.live_url) + '</div>';
        } else if (cls === 'archive' && e.live_url) {
            video = '<div class="ynj-live-card__video">' + getEmbed(e.live_url) + '</div>';
        } else {
            video = '<div class="ynj-live-card__video" style="aspect-ratio:3/1;background:linear-gradient(135deg,#0a1628,#1a3a5c);"><span style="color:rgba(255,255,255,.5);font-size:13px;">📅 ' + fmtDate(e.event_date) + ' · ' + fmtTime(e.start_time) + '</span></div>';
        }

        var time = fmtTime(e.start_time);
        var snippet = (e.description||'').length > 100 ? e.description.slice(0,100) + '...' : (e.description||'');
        var mosqueTag = e._mosque_name ? '<div class="ynj-live-card__mosque">🕌 ' + e._mosque_name + '</div>' : '';

        var cta = '';
        if (cls === 'live' && e.live_url) {
            cta = '<a href="' + e.live_url + '" target="_blank" rel="noopener" class="ynj-btn" style="width:100%;justify-content:center;background:#dc2626;">▶ Watch Live</a>';
        } else if (cls === 'archive' && e.live_url) {
            cta = '<a href="' + e.live_url + '" target="_blank" rel="noopener" class="ynj-btn ynj-btn--outline" style="width:100%;justify-content:center;">▶ Watch Recording</a>';
        } else if (cls === 'upcoming') {
            cta = '<a href="' + <?php echo wp_json_encode( home_url( '/mosque/' ) ); ?> + slug + '/events/' + e.id + '" class="ynj-btn ynj-btn--outline" style="width:100%;justify-content:center;">🔔 View Details</a>';
        }

        return '<div class="ynj-live-card' + liveClass + '">' +
            video +
            '<div class="ynj-live-card__body">' +
                badge +
                '<h3>' + e.title + '</h3>' +
                mosqueTag +
                '<div class="ynj-live-card__meta">' +
                    (e.event_date ? '<span>📅 ' + fmtDate(e.event_date) + '</span>' : '') +
                    (time ? '<span>🕐 ' + time + '</span>' : '') +
                    (e.event_type ? '<span>' + e.event_type + '</span>' : '') +
                '</div>' +
                (snippet ? '<p style="font-size:13px;color:#555;margin-bottom:10px;line-height:1.4;">' + snippet + '</p>' : '') +
                cta +
            '</div>' +
        '</div>';
    }

    function renderAll(filter) {
        var el = document.getElementById('live-list');
        var radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        var combined = radius > 0 ? allEvents.concat(nearbyEvents) : allEvents;

        // Only show events with online/live capability
        var streamable = combined.filter(function(e) { return e.is_online || e.live_url; });

        if (filter === 'live') streamable = streamable.filter(function(e) { return classify(e) === 'live'; });
        else if (filter === 'upcoming') streamable = streamable.filter(function(e) { return classify(e) === 'upcoming'; });
        else if (filter === 'archive') streamable = streamable.filter(function(e) { return classify(e) === 'archive'; });

        // Sort: live first, then upcoming by date asc, then archive by date desc
        streamable.sort(function(a,b) {
            var ca = classify(a), cb = classify(b);
            if (ca === 'live' && cb !== 'live') return -1;
            if (ca !== 'live' && cb === 'live') return 1;
            if (ca === 'upcoming' && cb === 'archive') return -1;
            if (ca === 'archive' && cb === 'upcoming') return 1;
            if (ca === 'archive' && cb === 'archive') return (b.event_date||'').localeCompare(a.event_date||'');
            return (a.event_date||'').localeCompare(b.event_date||'');
        });

        // Update tab counts
        var liveCount = combined.filter(function(e) { return (e.is_online || e.live_url) && classify(e) === 'live'; }).length;
        var upCount = combined.filter(function(e) { return (e.is_online || e.live_url) && classify(e) === 'upcoming'; }).length;
        var archCount = combined.filter(function(e) { return (e.is_online || e.live_url) && classify(e) === 'archive'; }).length;
        document.getElementById('lt-live').textContent = '🔴 Live' + (liveCount ? ' (' + liveCount + ')' : '');
        document.getElementById('lt-upcoming').textContent = '📅 Upcoming' + (upCount ? ' (' + upCount + ')' : '');
        document.getElementById('lt-archive').textContent = '📼 Archive' + (archCount ? ' (' + archCount + ')' : '');

        if (!streamable.length) {
            var msg = filter === 'live' ? 'Nothing live right now. Check back during events!'
                : filter === 'upcoming' ? 'No upcoming streams scheduled.'
                : filter === 'archive' ? 'No recordings available yet.'
                : 'No live events or recordings yet. The mosque can add live stream URLs to events.';
            el.innerHTML = '<div class="ynj-live-empty" style="grid-column:1/-1"><div>' + (filter === 'live' ? '🔴' : filter === 'archive' ? '📼' : '📡') + '</div><h3>' + (filter === 'live' ? 'Not Live' : filter === 'archive' ? 'No Recordings' : 'No Streams') + '</h3><p class="ynj-text-muted">' + msg + '</p></div>';
            return;
        }
        el.innerHTML = streamable.map(renderCard).join('');
    }

    window.filterLive = function(f) {
        ['all','live','upcoming','archive'].forEach(function(t) {
            document.getElementById('lt-'+t).classList.toggle('ynj-live-tab--active', t===f);
        });
        renderAll(f);
    };

    // Load mosque info
    fetch(API + 'mosques/' + slug)
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            var m = resp.mosque || resp;
            mosqueLat = m.latitude; mosqueLng = m.longitude;
            document.getElementById('live-title').textContent = (m.name || 'Your Mosque') + ' — Live & Streams';
        }).catch(function(){});

    // Load ALL events (no upcoming filter — we need past ones too for archive)
    fetch(API + 'mosques/' + slug + '/events?per_page=100')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            allEvents = data.events || [];
            renderAll('all');
        })
        .catch(function() {
            document.getElementById('live-list').innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;grid-column:1/-1;">Could not load.</p>';
        });

    // Radius change — load nearby
    window.onRadiusChange = function() {
        var radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        if (radius === 0) { nearbyEvents = []; renderAll('all'); return; }
        if (nearbyLoaded) { renderAll('all'); return; }
        if (!mosqueLat) { renderAll('all'); return; }

        document.getElementById('live-list').innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;grid-column:1/-1;">Loading nearby streams...</p>';
        var radiusKm = radius === 9999 ? 9999 : radius * 1.609;
        fetch(API + 'mosques/nearest?lat=' + mosqueLat + '&lng=' + mosqueLng + '&limit=10&radius_km=' + radiusKm)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var mosques = (data.mosques || []).filter(function(m) { return m.slug !== slug; });
                return Promise.all(mosques.slice(0,8).map(function(m) {
                    return fetch(API + 'mosques/' + m.slug + '/events?per_page=100').then(function(r) { return r.json(); })
                        .then(function(d) { return (d.events||[]).map(function(e) { e._mosque_name = m.name; return e; }); })
                        .catch(function() { return []; });
                }));
            })
            .then(function(results) {
                nearbyEvents = (results||[]).flat();
                nearbyLoaded = true;
                renderAll('all');
            })
            .catch(function() { nearbyLoaded = true; renderAll('all'); });
    };
})();
</script>
<?php get_footer(); ?>
