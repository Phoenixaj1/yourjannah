<?php
/**
 * Template: Business Directory
 *
 * Sponsors at top (premium/featured/standard), then searchable
 * professionals directory below. Combined "Business" tab.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>

<main class="ynj-main">
    <!-- CTA Banner -->
    <div style="background:linear-gradient(135deg,#00ADEF,#0369a1);border-radius:14px;padding:18px 20px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="color:#fff;font-size:15px;font-weight:700;margin-bottom:2px;"><?php esc_html_e( 'Support Your Masjid', 'yourjannah' ); ?></h3>
            <p style="color:rgba(255,255,255,.8);font-size:12px;"><?php esc_html_e( 'List your business or services — proceeds fund the masjid', 'yourjannah' ); ?></p>
        </div>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors/join' ) ); ?>" style="display:inline-flex;align-items:center;gap:4px;padding:8px 14px;border-radius:10px;background:#fff;color:#00ADEF;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;">⭐ <?php esc_html_e( 'List Now', 'yourjannah' ); ?></a>
    </div>

    <!-- Sponsors Section -->
    <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">⭐ <?php esc_html_e( 'Masjid Sponsors', 'yourjannah' ); ?></h3>
    <div id="biz-sponsors" class="ynj-sponsors-grid"><p class="ynj-text-muted">Loading...</p></div>

    <!-- Divider -->
    <div style="border-top:1px solid #e0e8ed;margin:20px 0;"></div>

    <!-- People / Services Directory -->
    <h3 style="font-size:15px;font-weight:700;margin-bottom:4px;">🤝 <?php esc_html_e( 'Find Local Professionals', 'yourjannah' ); ?></h3>
    <p class="ynj-text-muted" style="margin-bottom:12px;"><?php esc_html_e( 'Trusted experts from your community', 'yourjannah' ); ?></p>

    <div class="ynj-search-bar" style="margin-bottom:14px;">
        <input class="ynj-search-bar__input" id="svc-search" type="text" placeholder="<?php esc_attr_e( 'Search (e.g. plumber, solicitor, tutor)...', 'yourjannah' ); ?>" autocomplete="off" oninput="searchSvc()">
    </div>

    <div id="biz-services"><p class="ynj-text-muted">Loading...</p></div>
</main>

<script>
(function(){
    var slug = <?php echo wp_json_encode( $slug ); ?>;
    var API = ynjData.restUrl;
    var allSvcs = [];

    // Load sponsors
    fetch(API + 'mosques/' + slug + '/directory').then(function(r){return r.json();}).then(function(data){
        var biz = data.businesses || [];
        var el = document.getElementById('biz-sponsors');
        if(!biz.length){ el.innerHTML = '<p class="ynj-text-muted">No sponsors yet. <a href="/mosque/' + slug + '/sponsors/join" style="font-weight:700;">Be the first!</a></p>'; return; }

        el.innerHTML = biz.map(function(b, i){
            var rank = i + 1;
            var tierClass = rank <= 3 ? (rank===1?'ynj-biz--premium':rank===2?'ynj-biz--featured':'ynj-biz--standard') : '';
            var tierLabel = rank <= 3 ? ['Premium','Featured','Standard'][rank-1] : '';
            var medal = rank <= 3 ? ['\ud83e\udd47','\ud83e\udd48','\ud83e\udd49'][rank-1] : '';
            var initial = (b.business_name||'?')[0].toUpperCase();

            return '<div class="ynj-biz-card ' + tierClass + '">' +
                (tierLabel ? '<div class="ynj-biz-tier">' + medal + ' ' + tierLabel + ' Sponsor</div>' : '') +
                '<div class="ynj-biz-header"><div class="ynj-biz-logo">' + initial + '</div><div class="ynj-biz-info"><h3 class="ynj-biz-name">' + b.business_name + '</h3><span class="ynj-biz-cat">' + b.category + '</span></div></div>' +
                (b.description ? '<p class="ynj-biz-desc">' + (b.description.length>120?b.description.slice(0,120)+'...':b.description) + '</p>' : '') +
                '<div class="ynj-biz-actions">' +
                (b.phone ? '<a href="tel:' + b.phone + '" class="ynj-biz-btn">Call</a>' : '') +
                (b.website ? '<a href="' + b.website + '" target="_blank" rel="noopener" class="ynj-biz-btn ynj-biz-btn--outline">Website</a>' : '') +
                '</div></div>';
        }).join('');

        // Load services
        allSvcs = data.services || [];
        renderSvcs(allSvcs);
    }).catch(function(){
        document.getElementById('biz-sponsors').innerHTML = '<p class="ynj-text-muted">Could not load.</p>';
    });

    var svcIcons = {'Imam / Scholar':'\ud83d\udd4c','Quran Teacher':'\ud83d\udcd6','Counselling':'\ud83e\udd1d','Legal Services':'\u2696\ufe0f','Accounting':'\ud83d\udcca','Web Development':'\ud83d\udcbb','Tutoring':'\ud83d\udcda','Catering':'\ud83c\udf7d\ufe0f','Photography':'\ud83d\udcf7','Plumbing':'\ud83d\udd27','Electrician':'\u26a1','Cleaning':'\ud83e\uddf9'};

    function renderSvcs(svcs){
        var el = document.getElementById('biz-services');
        if(!svcs.length){ el.innerHTML = '<p class="ynj-text-muted">No professionals listed yet.</p>'; return; }
        el.innerHTML = svcs.map(function(s){
            var icon = svcIcons[s.service_type] || '\u2726';
            return '<div class="ynj-svc-card"><div class="ynj-svc-card__icon">' + icon + '</div><div class="ynj-svc-card__body"><h4>' + s.provider_name + '</h4><span class="ynj-badge">' + s.service_type + '</span><p class="ynj-text-muted">' + ((s.description||'').slice(0,80)) + '</p>' + (s.phone ? '<a href="tel:' + s.phone + '" class="ynj-svc-card__phone">' + s.phone + '</a>' : '') + '</div></div>';
        }).join('');
    }

    window.searchSvc = function(){
        var q = (document.getElementById('svc-search').value || '').toLowerCase().trim();
        if(!q){ renderSvcs(allSvcs); return; }
        var filtered = allSvcs.filter(function(s){
            return (s.provider_name||'').toLowerCase().indexOf(q)>=0 || (s.service_type||'').toLowerCase().indexOf(q)>=0 || (s.description||'').toLowerCase().indexOf(q)>=0;
        });
        renderSvcs(filtered);
    };
})();
</script>
<?php get_footer(); ?>
