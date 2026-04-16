<?php
/**
 * Template: Sponsors Page
 *
 * Sponsor listings with CTA banner, Your Mosque / Nearby tabs, search, business cards.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>

<main class="ynj-main">
    <!-- CTA Buttons -->
    <div style="display:flex;gap:8px;margin-bottom:14px;">
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors/join' ) ); ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:14px 16px;border-radius:12px;background:linear-gradient(135deg,#00ADEF,#0369a1);color:#fff;font-size:14px;font-weight:700;text-decoration:none;text-align:center;box-shadow:0 4px 12px rgba(0,173,239,.25);">⭐ <?php esc_html_e( 'Sponsor Your Masjid', 'yourjannah' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services/join' ) ); ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:14px 16px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;font-size:14px;font-weight:700;text-decoration:none;text-align:center;box-shadow:0 4px 12px rgba(124,58,237,.25);">🤝 <?php esc_html_e( 'List Your Service', 'yourjannah' ); ?></a>
    </div>

    <!-- Tabs: Sponsors / Services -->
    <div class="ynj-feed-tabs" style="margin-bottom:14px;">
        <button class="ynj-feed-tab ynj-feed-tab--active" id="biz-tab-sponsors" onclick="switchBizTab('sponsors')">⭐ <?php esc_html_e( 'Sponsors', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" id="biz-tab-services" onclick="switchBizTab('services')">🤝 <?php esc_html_e( 'Services', 'yourjannah' ); ?></button>
    </div>

    <div id="biz-panel-sponsors">
        <div id="local-biz-list" class="ynj-sponsors-grid"><p class="ynj-text-muted">Loading&hellip;</p></div>
    </div>

    <div id="biz-panel-services" style="display:none;">
        <div id="local-svc-list"><p class="ynj-text-muted">Loading&hellip;</p></div>
    </div>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API = ynjData.restUrl;
    let mosqueId = null;
    let mosqueLat = null, mosqueLng = null;
    let localBiz = [];
    let nearbyBiz = [];
    let nearbyLoaded = false;

    let svcLoaded = false;

    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
        el.href = el.dataset.navMosque.replace('{slug}', slug);
    });

    // Tab switching
    window.switchBizTab = function(tab) {
        document.getElementById('biz-tab-sponsors').classList.toggle('ynj-feed-tab--active', tab === 'sponsors');
        document.getElementById('biz-tab-services').classList.toggle('ynj-feed-tab--active', tab === 'services');
        document.getElementById('biz-panel-sponsors').style.display = tab === 'sponsors' ? '' : 'none';
        document.getElementById('biz-panel-services').style.display = tab === 'services' ? '' : 'none';
        if (tab === 'services' && !svcLoaded) loadServices();
    };

    // Services rendering
    const svcIcons = {'Imam / Scholar':'\ud83d\udd4c','Quran Teacher':'\ud83d\udcd6','Arabic Tutor':'\ud83d\udcda','Counselling':'\ud83e\udd1d','Legal Services':'\u2696\ufe0f','Accounting':'\ud83d\udcca','Web Development':'\ud83d\udcbb','Tutoring':'\ud83d\udcda','Catering':'\ud83c\udf7d\ufe0f','Photography':'\ud83d\udcf7','Plumbing':'\ud83d\udd27','Electrician':'\u26a1'};

    function renderSvcCard(s) {
        const icon = svcIcons[s.service_type] || '\u2726';
        return '<div class="ynj-svc-card"><div class="ynj-svc-card__icon">' + icon + '</div><div class="ynj-svc-card__body"><h4>' + s.provider_name + '</h4><span class="ynj-badge">' + s.service_type + '</span><p class="ynj-text-muted">' + ((s.description||'').slice(0,100)) + '</p>' + (s.phone ? '<a href="tel:' + s.phone + '" class="ynj-svc-card__phone">' + s.phone + '</a>' : '') + '</div></div>';
    }

    function loadServices() {
        fetch(API + 'mosques/' + slug + '/directory').then(r=>r.json()).then(data => {
            const svcs = data.services || [];
            const el = document.getElementById('local-svc-list');
            if (!svcs.length) { el.innerHTML = '<p class="ynj-text-muted">No services listed yet.</p>'; return; }
            el.innerHTML = svcs.map(s => renderSvcCard(s)).join('');
            svcLoaded = true;
        }).catch(() => { document.getElementById('local-svc-list').innerHTML = '<p class="ynj-text-muted">Could not load.</p>'; });
    }

    function renderBiz(b, rank, showMosque) {
        const dist = b.distance_km != null && b.distance_km < 9000 ? `${b.distance_km < 1.6 ? (b.distance_km*0.621).toFixed(1)+' mi' : Math.round(b.distance_km*0.621)+' mi'}` : '';
        const mosque = showMosque && b.mosque_name ? b.mosque_name : '';
        const medal = rank && rank <= 3 ? ['🥇','🥈','🥉'][rank-1] : '';
        const tierClass = rank <= 3 ? ' ynj-biz--'+(rank===1?'premium':rank===2?'featured':'standard') : '';
        const tierLabel = rank && rank <= 3 ? ['Premium','Featured','Standard'][rank-1] : '';
        const initial = (b.business_name||'?')[0].toUpperCase();
        const hasLogo = b.logo_url && b.logo_url.length > 5;

        return `<div class="ynj-biz-card${tierClass}">
            ${tierLabel ? `<div class="ynj-biz-tier">${medal} ${tierLabel} Sponsor</div>` : ''}
            <div class="ynj-biz-header">
                <div class="ynj-biz-logo">${hasLogo ? `<img src="${b.logo_url}" alt="${b.business_name}" onerror="this.parentNode.innerHTML='${initial}'">` : initial}</div>
                <div class="ynj-biz-info">
                    <h3 class="ynj-biz-name">${b.business_name}</h3>
                    <span class="ynj-biz-cat">${b.category}</span>
                </div>
            </div>
            ${b.description ? `<p class="ynj-biz-desc">${b.description.length>180?b.description.slice(0,180)+'...':b.description}</p>` : ''}
            <div class="ynj-biz-details">
                ${b.address || b.postcode ? `<div class="ynj-biz-detail"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"/><path d="M12 2C7.6 2 4 5.4 4 9.5 4 14.3 12 22 12 22s8-7.7 8-12.5C20 5.4 16.4 2 12 2z"/></svg><span>${[b.address, b.postcode].filter(Boolean).join(', ')}</span></div>` : ''}
                ${b.phone ? `<div class="ynj-biz-detail"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg><a href="tel:${b.phone}">${b.phone}</a></div>` : ''}
                ${b.email ? `<div class="ynj-biz-detail"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><a href="mailto:${b.email}">${b.email}</a></div>` : ''}
                ${dist ? `<div class="ynj-biz-detail"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>${dist} away</span></div>` : ''}
                ${mosque ? `<div class="ynj-biz-detail"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/></svg><span>${mosque}</span></div>` : ''}
            </div>
            <div class="ynj-biz-actions">
                ${b.phone ? `<a href="tel:${b.phone}" class="ynj-biz-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg> Call</a>` : ''}
                ${b.website ? `<a href="${b.website}" target="_blank" rel="noopener" class="ynj-biz-btn ynj-biz-btn--outline"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg> Website</a>` : ''}
                ${b.email ? `<a href="mailto:${b.email}" class="ynj-biz-btn ynj-biz-btn--outline"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Email</a>` : ''}
            </div>
        </div>`;
    }

    function renderList() {
        const list = document.getElementById('local-biz-list');
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        const all = radius > 0 ? localBiz.concat(nearbyBiz) : localBiz;
        if (!all.length) { list.innerHTML = '<p class="ynj-text-muted">No sponsors yet. Be the first!</p>'; return; }
        list.innerHTML = all.map((b,i) => renderBiz(b, radius > 0 ? null : i+1, !!b.mosque_name)).join('');
    }

    // Load mosque + sponsors in parallel for speed
    Promise.all([
        fetch(API + 'mosques/' + slug).then(r => r.json()),
        fetch(API + 'mosques/' + slug + '/directory').then(r => r.json())
    ]).then(function([mosqueResp, dirResp]) {
        const m = mosqueResp.mosque || mosqueResp;
        mosqueId = m.id; mosqueLat = m.latitude; mosqueLng = m.longitude;
        localBiz = dirResp.businesses || [];
        renderList();
    }).catch(function(err) {
        console.error('Sponsors load error:', err);
        document.getElementById('local-biz-list').innerHTML = '<p class="ynj-text-muted">No sponsors yet. Be the first to <a href="<?php echo esc_js( home_url( "/mosque/" ) ); ?>' + slug + '/sponsors/join">sponsor this masjid</a>!</p>';
    });

    // Radius change
    window.onRadiusChange = function() {
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        if (radius === 0) { nearbyBiz = []; renderList(); return; }
        if (nearbyLoaded) { renderList(); return; }
        if (!mosqueLat) { renderList(); return; }

        document.getElementById('local-biz-list').innerHTML = '<p class="ynj-text-muted">Loading nearby sponsors...</p>';
        const radiusKm = radius === 9999 ? 9999 : radius * 1.609;
        fetch(API + 'mosques/nearest?lat=' + mosqueLat + '&lng=' + mosqueLng + '&limit=10&radius_km=' + radiusKm)
            .then(r => r.json())
            .then(data => {
                const mosques = (data.mosques || []).filter(m => m.slug !== slug);
                return Promise.all(mosques.slice(0,8).map(m =>
                    fetch(API + 'mosques/' + m.slug + '/directory').then(r => r.json())
                        .then(d => (d.businesses||[]).map(b => Object.assign(b, {mosque_name:m.name, distance_km:m.distance})))
                        .catch(() => [])
                ));
            })
            .then(results => { nearbyBiz = (results||[]).flat(); nearbyLoaded = true; renderList(); })
            .catch(() => { nearbyLoaded = true; renderList(); });
    };
})();
</script>
<?php get_footer(); ?>
