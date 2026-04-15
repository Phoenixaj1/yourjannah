<?php
/**
 * Template: People (Professional Services) Page
 *
 * Professional services directory with CTA banner, Your Mosque / Nearby tabs, search.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>

<main class="ynj-main">
    <!-- List your service CTA -->
    <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:14px;padding:18px 20px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="color:#fff;font-size:15px;font-weight:700;margin-bottom:2px;"><?php esc_html_e( 'Are you a professional?', 'yourjannah' ); ?></h3>
            <p style="color:rgba(255,255,255,.8);font-size:12px;"><?php esc_html_e( 'Get found by your community — proceeds support the masjid', 'yourjannah' ); ?></p>
        </div>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services/join' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:10px;background:#fff;color:#7c3aed;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;"><?php esc_html_e( 'List Yourself', 'yourjannah' ); ?></a>
    </div>

    <div class="ynj-feed-tabs" style="margin-bottom:12px;">
        <button class="ynj-feed-tab ynj-feed-tab--active" id="ppl-tab-local" onclick="switchPplTab('local')">🕌 <?php esc_html_e( 'Your Mosque', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" id="ppl-tab-nearby" onclick="switchPplTab('nearby')">📍 <?php esc_html_e( 'Nearby', 'yourjannah' ); ?></button>
    </div>

    <div id="ppl-local-panel">
        <div id="local-svc-list"><p class="ynj-text-muted">Loading&hellip;</p></div>
    </div>

    <div id="ppl-nearby-panel" style="display:none;">
        <div class="ynj-search-bar" style="margin-bottom:12px;">
            <input class="ynj-search-bar__input" id="svc-search" type="text" placeholder="<?php esc_attr_e( 'Find a professional (e.g. solicitor, plumber, tutor)...', 'yourjannah' ); ?>" autocomplete="off">
            <div class="ynj-search-bar__filters">
                <select id="svc-type" class="ynj-search-bar__select">
                    <option value=""><?php esc_html_e( 'All Types', 'yourjannah' ); ?></option>
                    <option>Legal Services</option><option>Accounting</option><option>Financial Advice</option>
                    <option>Web Development</option><option>SEO</option><option>Digital Marketing</option>
                    <option>IT Support</option><option>Graphic Design</option><option>Photography</option>
                    <option>Tutoring</option><option>Driving Instructor</option><option>Plumbing</option>
                    <option>Electrician</option><option>Cleaning</option><option>Catering</option>
                    <option>Translation</option><option>Counselling</option><option>Other</option>
                </select>
                <select id="svc-radius" class="ynj-search-bar__select">
                    <option value="10"><?php esc_html_e( 'Within 10 miles', 'yourjannah' ); ?></option>
                    <option value="25"><?php esc_html_e( 'Within 25 miles', 'yourjannah' ); ?></option>
                    <option value="50"><?php esc_html_e( 'Within 50 miles', 'yourjannah' ); ?></option>
                    <option value="9999"><?php esc_html_e( 'Nationwide', 'yourjannah' ); ?></option>
                </select>
            </div>
        </div>
        <div id="community-svc-list"><p class="ynj-text-muted" style="text-align:center;padding:16px;">Search for professionals in nearby communities</p></div>
    </div>

    <!-- Hidden sections for JS compat -->
    <section id="local-services" style="display:none;"><h2 id="local-title"></h2></section>
    <section id="community-services" style="display:none;"><h2 id="community-title"></h2></section>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API = ynjData.restUrl;
    let mosqueId = null;
    let userLat = null, userLng = null;

    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
        el.href = el.dataset.navMosque.replace('{slug}', slug);
    });

    let nearbyPplSearched = false;
    window.switchPplTab = function(tab) {
        document.getElementById('ppl-tab-local').classList.toggle('ynj-feed-tab--active', tab === 'local');
        document.getElementById('ppl-tab-nearby').classList.toggle('ynj-feed-tab--active', tab === 'nearby');
        document.getElementById('ppl-local-panel').style.display = tab === 'local' ? '' : 'none';
        document.getElementById('ppl-nearby-panel').style.display = tab === 'nearby' ? '' : 'none';
        if (tab === 'nearby' && !nearbyPplSearched) { nearbyPplSearched = true; executeSearch(); }
    };

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
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            mosqueId = m.id;
            document.getElementById('local-title').textContent = m.name || 'Your Mosque';
            if (!userLat && m.latitude) { userLat = m.latitude; userLng = m.longitude; }
        })
        .then(() => fetch(API + 'mosques/' + slug + '/directory'))
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
        const q = (document.getElementById('svc-search') || {}).value || '';
        const type = (document.getElementById('svc-type') || {}).value || '';
        const radiusEl = document.getElementById('svc-radius');
        const radiusMi = radiusEl ? parseInt(radiusEl.value) : 10;

        const radiusKm = radiusMi === 9999 ? 9999 : radiusMi * 1.609;
        const communityList = document.getElementById('community-svc-list');
        if (!communityList) return;

        communityList.innerHTML = '<p class="ynj-text-muted">Searching...</p>';

        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (type) params.set('type', type);
        if (userLat) { params.set('lat', userLat); params.set('lng', userLng); }
        if (radiusKm > 0) params.set('radius_km', radiusKm);
        if (mosqueId) params.set('mosque_id', mosqueId);
        params.set('per_page', '30');

        fetch(API + 'services/search?' + params)
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
<?php get_footer(); ?>
