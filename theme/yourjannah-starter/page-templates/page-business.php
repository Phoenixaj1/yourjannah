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

// Pre-load ALL data server-side — zero API calls for primary data
$mosque      = ynj_get_mosque( $slug );
$mosque_id   = $mosque ? (int) $mosque->id : 0;
$mosque_name = $mosque ? $mosque->name : __( 'Your Masjid', 'yourjannah' );
$businesses  = [];
$services    = [];
if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $biz_table = YNJ_DB::table( 'businesses' );
    $svc_table = YNJ_DB::table( 'services' );
    $businesses = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, business_name, owner_name, category, description, phone, email, website, logo_url, address, postcode, monthly_fee_pence, featured_position
         FROM $biz_table WHERE mosque_id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY monthly_fee_pence DESC, business_name ASC LIMIT 50", $mosque_id
    ) ) ?: [];
    $services = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, provider_name, phone, email, service_type, description, hourly_rate_pence, area_covered
         FROM $svc_table WHERE mosque_id = %d AND status = 'active'
         ORDER BY monthly_fee_pence DESC, provider_name ASC LIMIT 50", $mosque_id
    ) ) ?: [];
}
?>

<main class="ynj-main">
    <h2 style="font-size:18px;font-weight:700;margin-bottom:12px;"><?php echo esc_html( $mosque_name ); ?> — <?php esc_html_e( 'Business Directory', 'yourjannah' ); ?></h2>

    <!-- Search bar -->
    <div style="display:flex;gap:8px;margin-bottom:14px;">
        <div style="flex:1;position:relative;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6b8fa3" stroke-width="2" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="biz-global-search" type="text" placeholder="<?php esc_attr_e( 'Search businesses & professionals...', 'yourjannah' ); ?>" style="width:100%;padding:12px 12px 12px 38px;border:1px solid rgba(0,0,0,.1);border-radius:12px;font-size:14px;font-family:inherit;outline:none;background:rgba(255,255,255,.9);" autocomplete="off" oninput="globalSearch()">
        </div>
        <select id="biz-area" style="padding:10px 12px;border-radius:12px;border:1px solid rgba(0,0,0,.1);font-size:12px;font-weight:600;background:rgba(255,255,255,.9);cursor:pointer;" onchange="globalSearch()">
            <option value="local"><?php esc_html_e( 'This Masjid', 'yourjannah' ); ?></option>
            <option value="5"><?php esc_html_e( '5 miles', 'yourjannah' ); ?></option>
            <option value="10"><?php esc_html_e( '10 miles', 'yourjannah' ); ?></option>
            <option value="25"><?php esc_html_e( '25 miles', 'yourjannah' ); ?></option>
            <option value="all"><?php esc_html_e( 'Nationwide', 'yourjannah' ); ?></option>
        </select>
    </div>

    <!-- CTA Banner -->
    <div style="background:linear-gradient(135deg,#00ADEF,#0369a1);border-radius:14px;padding:14px 20px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h3 style="color:#fff;font-size:14px;font-weight:700;margin-bottom:2px;"><?php esc_html_e( 'Support Your Masjid', 'yourjannah' ); ?></h3>
            <p style="color:rgba(255,255,255,.8);font-size:11px;"><?php esc_html_e( 'List your business or services — proceeds fund the masjid', 'yourjannah' ); ?></p>
        </div>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors/join' ) ); ?>" style="display:inline-flex;align-items:center;gap:4px;padding:8px 14px;border-radius:10px;background:#fff;color:#00ADEF;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;">⭐ <?php esc_html_e( 'List Now', 'yourjannah' ); ?></a>
    </div>

    <!-- Sponsors Section -->
    <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">⭐ <?php esc_html_e( 'Masjid Sponsors', 'yourjannah' ); ?></h3>
    <div id="biz-sponsors" class="ynj-sponsors-grid">
    <?php if ( empty( $businesses ) ) : ?>
        <p class="ynj-text-muted"><?php esc_html_e( 'No sponsors found.', 'yourjannah' ); ?></p>
    <?php else : foreach ( $businesses as $i => $b ) :
        $rank = $i + 1;
        $tierClass = $rank <= 3 ? ' ynj-biz--' . ( $rank === 1 ? 'premium' : ( $rank === 2 ? 'featured' : 'standard' ) ) : '';
        $tierLabel = $rank <= 3 ? [ 'Premium', 'Featured', 'Standard' ][ $rank - 1 ] : '';
        $medal = $rank <= 3 ? [ "\xF0\x9F\xA5\x87", "\xF0\x9F\xA5\x88", "\xF0\x9F\xA5\x89" ][ $rank - 1 ] : '';
        $initial = strtoupper( substr( $b->business_name ?: '?', 0, 1 ) );
    ?>
        <div class="ynj-biz-card<?php echo $tierClass; ?>">
            <?php if ( $tierLabel ) : ?><div class="ynj-biz-tier"><?php echo $medal . ' ' . $tierLabel; ?> Sponsor</div><?php endif; ?>
            <div class="ynj-biz-header"><div class="ynj-biz-logo"><?php echo esc_html( $initial ); ?></div><div class="ynj-biz-info"><h3 class="ynj-biz-name"><?php echo esc_html( $b->business_name ); ?></h3><span class="ynj-biz-cat"><?php echo esc_html( $b->category ); ?></span></div></div>
            <?php if ( $b->description ) : ?><p class="ynj-biz-desc"><?php echo esc_html( mb_strimwidth( $b->description, 0, 120, '...' ) ); ?></p><?php endif; ?>
            <div class="ynj-biz-actions">
                <?php if ( $b->phone ) : ?><a href="tel:<?php echo esc_attr( $b->phone ); ?>" class="ynj-biz-btn">Call</a><?php endif; ?>
                <?php if ( $b->website ) : ?><a href="<?php echo esc_url( $b->website ); ?>" target="_blank" rel="noopener" class="ynj-biz-btn ynj-biz-btn--outline">Website</a><?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>

    <!-- Divider -->
    <div style="border-top:1px solid #e0e8ed;margin:20px 0;"></div>

    <!-- People / Services Directory -->
    <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">🤝 <?php esc_html_e( 'Local Professionals', 'yourjannah' ); ?></h3>

    <div id="biz-services">
    <?php if ( empty( $services ) ) : ?>
        <p class="ynj-text-muted"><?php esc_html_e( 'No professionals listed yet.', 'yourjannah' ); ?></p>
    <?php else : foreach ( $services as $s ) : ?>
        <div class="ynj-svc-card">
            <div class="ynj-svc-card__body">
                <h4><?php echo esc_html( $s->provider_name ); ?></h4>
                <span class="ynj-badge"><?php echo esc_html( $s->service_type ); ?></span>
                <p class="ynj-text-muted"><?php echo esc_html( mb_strimwidth( $s->description ?: '', 0, 80, '...' ) ); ?></p>
                <?php if ( $s->phone ) : ?><a href="tel:<?php echo esc_attr( $s->phone ); ?>" class="ynj-svc-card__phone"><?php echo esc_html( $s->phone ); ?></a><?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>
</main>

<script>
(function(){
    var slug = <?php echo wp_json_encode( $slug ); ?>;
    var API = ynjData.restUrl;
    // Pre-loaded from PHP — instant, no API calls
    var allSvcs = <?php echo wp_json_encode( array_map( function( $s ) {
        return [
            'id'               => (int) $s->id,
            'provider_name'    => $s->provider_name,
            'phone'            => $s->phone,
            'email'            => $s->email,
            'service_type'     => $s->service_type,
            'description'      => $s->description,
            'hourly_rate_pence'=> (int) $s->hourly_rate_pence,
            'area_covered'     => $s->area_covered,
        ];
    }, $services ) ); ?>;
    var allBiz = <?php echo wp_json_encode( array_map( function( $b ) {
        return [
            'id'            => (int) $b->id,
            'business_name' => $b->business_name,
            'owner_name'    => $b->owner_name,
            'category'      => $b->category,
            'description'   => $b->description,
            'phone'         => $b->phone,
            'email'         => $b->email,
            'website'       => $b->website,
            'logo_url'      => $b->logo_url,
            'address'       => $b->address,
            'postcode'      => $b->postcode,
        ];
    }, $businesses ) ); ?>;

    function renderBiz(biz) {
        var el = document.getElementById('biz-sponsors');
        if(!biz.length){ el.innerHTML = '<p class="ynj-text-muted">No sponsors found.</p>'; return; }
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
    }

    // Render instantly from PHP pre-loaded data — no API calls
    // (HTML already rendered server-side, JS arrays ready for client-side search filtering)

    var svcIcons = {'Imam / Scholar':'\ud83d\udd4c','Quran Teacher':'\ud83d\udcd6','Counselling':'\ud83e\udd1d','Legal Services':'\u2696\ufe0f','Accounting':'\ud83d\udcca','Web Development':'\ud83d\udcbb','Tutoring':'\ud83d\udcda','Catering':'\ud83c\udf7d\ufe0f','Photography':'\ud83d\udcf7','Plumbing':'\ud83d\udd27','Electrician':'\u26a1','Cleaning':'\ud83e\uddf9'};

    function renderSvcs(svcs){
        var el = document.getElementById('biz-services');
        if(!svcs.length){ el.innerHTML = '<p class="ynj-text-muted">No professionals listed yet.</p>'; return; }
        el.innerHTML = svcs.map(function(s){
            var icon = svcIcons[s.service_type] || '\u2726';
            return '<div class="ynj-svc-card"><div class="ynj-svc-card__icon">' + icon + '</div><div class="ynj-svc-card__body"><h4>' + s.provider_name + '</h4><span class="ynj-badge">' + s.service_type + '</span><p class="ynj-text-muted">' + ((s.description||'').slice(0,80)) + '</p>' + (s.phone ? '<a href="tel:' + s.phone + '" class="ynj-svc-card__phone">' + s.phone + '</a>' : '') + '</div></div>';
        }).join('');
    }

    // Global search — filters both sponsors and professionals
    window.globalSearch = function(){
        var q = (document.getElementById('biz-global-search').value || '').toLowerCase().trim();
        var area = document.getElementById('biz-area').value;

        // Filter sponsors
        if(q){
            var filteredBiz = allBiz.filter(function(b){ return (b.business_name||'').toLowerCase().indexOf(q)>=0 || (b.category||'').toLowerCase().indexOf(q)>=0 || (b.description||'').toLowerCase().indexOf(q)>=0; });
            renderBiz(filteredBiz);
        } else {
            renderBiz(allBiz);
        }

        // Filter services
        if(q){
            var filteredSvcs = allSvcs.filter(function(s){ return (s.provider_name||'').toLowerCase().indexOf(q)>=0 || (s.service_type||'').toLowerCase().indexOf(q)>=0 || (s.description||'').toLowerCase().indexOf(q)>=0; });
            renderSvcs(filteredSvcs);
        } else {
            renderSvcs(allSvcs);
        }

        // TODO: if area !== 'local', fetch from nearby mosques via radius API
    };

    window.searchSvc = function(){
        var q = (document.getElementById('biz-global-search')?.value || '').toLowerCase().trim();
        if(!q){ renderSvcs(allSvcs); return; }
        var filtered = allSvcs.filter(function(s){
            return (s.provider_name||'').toLowerCase().indexOf(q)>=0 || (s.service_type||'').toLowerCase().indexOf(q)>=0 || (s.description||'').toLowerCase().indexOf(q)>=0;
        });
        renderSvcs(filtered);
    };
})();
</script>
<?php get_footer(); ?>
