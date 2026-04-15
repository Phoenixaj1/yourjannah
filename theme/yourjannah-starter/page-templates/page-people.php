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

    <div id="local-svc-list"><p class="ynj-text-muted">Loading&hellip;</p></div>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API = ynjData.restUrl;
    let mosqueId = null;
    let mosqueLat = null, mosqueLng = null;
    let localSvcs = [];
    let nearbySvcs = [];
    let nearbyLoaded = false;

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

    function renderList() {
        const list = document.getElementById('local-svc-list');
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        const all = radius > 0 ? localSvcs.concat(nearbySvcs) : localSvcs;
        if (!all.length) { list.innerHTML = '<p class="ynj-text-muted">No services listed yet.</p>'; return; }
        list.innerHTML = all.map(s => renderCard(s, !!s.mosque_name)).join('');
    }

    // Load mosque services
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            mosqueId = m.id; mosqueLat = m.latitude; mosqueLng = m.longitude;
        })
        .then(() => fetch(API + 'mosques/' + slug + '/directory'))
        .then(r => r.json())
        .then(data => {
            localSvcs = data.services || [];
            renderList();
        })
        .catch(() => {
            document.getElementById('local-svc-list').innerHTML = '<p class="ynj-text-muted">Could not load services.</p>';
        });

    // Radius change
    window.onRadiusChange = function() {
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        if (radius === 0) { nearbySvcs = []; renderList(); return; }
        if (nearbyLoaded) { renderList(); return; }
        if (!mosqueLat) { renderList(); return; }

        document.getElementById('local-svc-list').innerHTML = '<p class="ynj-text-muted">Loading nearby services...</p>';
        const radiusKm = radius === 9999 ? 9999 : radius * 1.609;
        fetch(API + 'mosques/nearest?lat=' + mosqueLat + '&lng=' + mosqueLng + '&limit=10&radius_km=' + radiusKm)
            .then(r => r.json())
            .then(data => {
                const mosques = (data.mosques || []).filter(m => m.slug !== slug);
                return Promise.all(mosques.slice(0,8).map(m =>
                    fetch(API + 'mosques/' + m.slug + '/directory').then(r => r.json())
                        .then(d => (d.services||[]).map(s => Object.assign(s, {mosque_name:m.name, mosque_city:m.city||'', distance_km:m.distance})))
                        .catch(() => [])
                ));
            })
            .then(results => { nearbySvcs = (results||[]).flat(); nearbyLoaded = true; renderList(); })
            .catch(() => { nearbyLoaded = true; renderList(); });
    };
})();
</script>
<?php get_footer(); ?>
