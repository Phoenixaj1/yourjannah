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
    <!-- Become a Sponsor CTA -->
    <div style="background:linear-gradient(135deg,#00ADEF,#0369a1);border-radius:14px;padding:18px 20px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="color:#fff;font-size:15px;font-weight:700;margin-bottom:2px;"><?php esc_html_e( 'Support Your Masjid', 'yourjannah' ); ?></h3>
            <p style="color:rgba(255,255,255,.8);font-size:12px;"><?php esc_html_e( 'List your business and reach the community', 'yourjannah' ); ?></p>
        </div>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors/join' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:10px;background:#fff;color:#00ADEF;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;">⭐ <?php esc_html_e( 'Become a Sponsor', 'yourjannah' ); ?></a>
    </div>

    <div class="ynj-feed-tabs" style="margin-bottom:12px;">
        <button class="ynj-feed-tab ynj-feed-tab--active" id="sp-tab-local" onclick="switchSpTab('local')">🕌 <?php esc_html_e( 'Your Mosque', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" id="sp-tab-nearby" onclick="switchSpTab('nearby')">📍 <?php esc_html_e( 'Nearby', 'yourjannah' ); ?></button>
    </div>

    <div id="sp-local-panel">
        <div id="local-biz-list" class="ynj-sponsors-grid"><p class="ynj-text-muted">Loading&hellip;</p></div>
    </div>

    <div id="sp-nearby-panel" style="display:none;">
        <div class="ynj-search-bar" style="margin-bottom:12px;">
            <input class="ynj-search-bar__input" id="biz-search" type="text" placeholder="<?php esc_attr_e( 'Find a business (e.g. restaurant, solicitor)...', 'yourjannah' ); ?>" autocomplete="off">
            <div class="ynj-search-bar__filters">
                <select id="biz-category" class="ynj-search-bar__select">
                    <option value=""><?php esc_html_e( 'All Categories', 'yourjannah' ); ?></option>
                    <option>Restaurant</option><option>Grocery</option><option>Butcher</option>
                    <option>Clothing</option><option>Books &amp; Gifts</option><option>Health</option>
                    <option>Legal</option><option>Finance</option><option>Insurance</option>
                    <option>Travel</option><option>Education</option><option>Automotive</option>
                    <option>Catering</option><option>Property</option><option>Technology</option>
                </select>
                <select id="biz-radius" class="ynj-search-bar__select">
                    <option value="10"><?php esc_html_e( 'Within 10 miles', 'yourjannah' ); ?></option>
                    <option value="25"><?php esc_html_e( 'Within 25 miles', 'yourjannah' ); ?></option>
                    <option value="9999"><?php esc_html_e( 'Nationwide', 'yourjannah' ); ?></option>
                </select>
            </div>
        </div>
        <div id="community-biz-list"><p class="ynj-text-muted" style="text-align:center;padding:16px;">Search or browse sponsors from nearby mosques</p></div>
    </div>

    <!-- Hidden sections for JS compat -->
    <section class="ynj-card" id="local-sponsors" style="display:none;"></section>
    <section class="ynj-card" id="community-sponsors" style="display:none;">
        <h2 class="ynj-card__title" id="community-biz-title">Nearby Businesses</h2>
        <div id="community-biz-list" class="ynj-sponsors-grid"></div>
    </section>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API = ynjData.restUrl;
    let mosqueId = null, userLat = null, userLng = null;

    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
        el.href = el.dataset.navMosque.replace('{slug}', slug);
    });

    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(p => {
            userLat = p.coords.latitude; userLng = p.coords.longitude;
        }, () => {}, {timeout:5000, maximumAge:300000});
    }

    let nearbySearched = false;
    window.switchSpTab = function(tab) {
        document.getElementById('sp-tab-local').classList.toggle('ynj-feed-tab--active', tab === 'local');
        document.getElementById('sp-tab-nearby').classList.toggle('ynj-feed-tab--active', tab === 'nearby');
        document.getElementById('sp-local-panel').style.display = tab === 'local' ? '' : 'none';
        document.getElementById('sp-nearby-panel').style.display = tab === 'nearby' ? '' : 'none';
        if (tab === 'nearby' && !nearbySearched) { nearbySearched = true; executeSearch(); }
    };

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

    // Load local mosque sponsors
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => { const m = resp.mosque||resp; mosqueId = m.id; if (!userLat && m.latitude) { userLat = m.latitude; userLng = m.longitude; } })
        .then(() => fetch(API + 'mosques/' + slug + '/directory'))
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

        fetch(API + 'businesses/search?' + params)
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
<?php get_footer(); ?>
