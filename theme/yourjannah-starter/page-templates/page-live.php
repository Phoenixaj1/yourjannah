<?php
/**
 * Template: Live Events Page
 *
 * Live events across mosques with filter tabs, YouTube embeds, inline donations.
 *
 * @package YourJannah
 */

get_header();
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
.ynj-live-card__meta{display:flex;flex-wrap:wrap;gap:8px;font-size:12px;color:#6b8fa3;margin-bottom:12px;}
.ynj-live-card__mosque{font-size:12px;color:#6b8fa3;margin-bottom:8px;}
.ynj-donate-inline{display:flex;gap:8px;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid #f0f0ec;}
.ynj-donate-inline select,.ynj-donate-inline input{padding:8px 12px;border:1px solid #e0e8ed;border-radius:8px;font-size:13px;font-family:inherit;}
.ynj-donate-inline select{width:auto;}
@media(min-width:900px){.ynj-live-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}.ynj-live-card--featured{grid-column:1/-1;}.ynj-live-card--featured .ynj-live-card__video{aspect-ratio:21/9;}}
</style>

<main class="ynj-main">
    <div class="ynj-feed-tabs" style="margin-bottom:16px;">
        <button class="ynj-feed-tab ynj-feed-tab--active" id="lt-all" onclick="filterLive('all')"><?php esc_html_e( 'All', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" id="lt-live" onclick="filterLive('live')">🔴 <?php esc_html_e( 'Live Now', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" id="lt-upcoming" onclick="filterLive('upcoming')">📅 <?php esc_html_e( 'Upcoming', 'yourjannah' ); ?></button>
    </div>
    <div class="ynj-live-grid" id="live-list">
        <p class="ynj-text-muted" style="padding:20px;text-align:center;">Loading live events...</p>
    </div>
</main>

<script>
(function(){
    const API = ynjData.restUrl;
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

    fetch(API + 'events/live')
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
        fetch(API + 'events/' + eventId + '/donate', {
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
<?php get_footer(); ?>
