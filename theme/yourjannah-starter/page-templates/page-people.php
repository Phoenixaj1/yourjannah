<?php
/**
 * Template: People — Professional Services Directory
 *
 * Searchable directory with category filter + radius search for nearby mosques.
 * PHP-rendered local results, JS for radius expansion.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$mosque_name = $mosque ? $mosque->name : '';
$mosque_lat = $mosque ? (float) $mosque->latitude : 0;
$mosque_lng = $mosque ? (float) $mosque->longitude : 0;

// Load services
$services = [];
$all_types = [];
if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $svc_table = YNJ_DB::table( 'services' );
    $services = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, provider_name, phone, email, service_type, description, hourly_rate_pence, area_covered
         FROM $svc_table WHERE mosque_id = %d AND status = 'active'
         ORDER BY provider_name ASC LIMIT 100", $mosque_id
    ) ) ?: [];
    foreach ( $services as $s ) {
        if ( $s->service_type && ! in_array( $s->service_type, $all_types ) ) $all_types[] = $s->service_type;
    }
    sort( $all_types );
}

// Search + filter
$search = strtolower( sanitize_text_field( $_GET['q'] ?? '' ) );
$cat_filter = strtolower( sanitize_text_field( $_GET['type'] ?? '' ) );
if ( $search || $cat_filter ) {
    $services = array_filter( $services, function( $s ) use ( $search, $cat_filter ) {
        if ( $cat_filter && strtolower( $s->service_type ) !== $cat_filter ) return false;
        if ( $search ) {
            $haystack = strtolower( $s->provider_name . ' ' . $s->service_type . ' ' . $s->description . ' ' . $s->area_covered );
            if ( strpos( $haystack, $search ) === false ) return false;
        }
        return true;
    } );
}
?>

<main class="ynj-main">
    <!-- CTA Banner -->
    <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:14px;padding:18px 20px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
            <h2 style="color:#fff;font-size:16px;font-weight:700;margin:0 0 2px;"><?php esc_html_e( 'Local Professionals', 'yourjannah' ); ?></h2>
            <p style="color:rgba(255,255,255,.8);font-size:12px;margin:0;"><?php esc_html_e( 'Find trusted professionals in your community — proceeds support the masjid', 'yourjannah' ); ?></p>
        </div>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services/join' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:10px;background:#fff;color:#7c3aed;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;">🤝 <?php esc_html_e( 'List Yourself', 'yourjannah' ); ?></a>
    </div>

    <!-- Search + Category + Radius -->
    <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
        <input type="text" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by name, skill, area...', 'yourjannah' ); ?>" style="flex:2;min-width:140px;padding:11px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;">
        <select name="type" style="flex:1;min-width:120px;padding:11px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:13px;font-family:inherit;background:#fff;">
            <option value=""><?php esc_html_e( 'All Types', 'yourjannah' ); ?></option>
            <?php foreach ( $all_types as $t ) : ?>
            <option value="<?php echo esc_attr( strtolower( $t ) ); ?>" <?php selected( $cat_filter, strtolower( $t ) ); ?>><?php echo esc_html( $t ); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="ynj-btn" style="flex-shrink:0;">🔍</button>
    </form>

    <!-- Radius selector -->
    <div style="display:flex;gap:6px;margin-bottom:14px;overflow-x:auto;">
        <button type="button" class="ynj-feed-tab ynj-feed-tab--active" id="rad-0" onclick="setRadius(0)">🕌 <?php echo esc_html( $mosque_name ?: __( 'This Mosque', 'yourjannah' ) ); ?></button>
        <button type="button" class="ynj-feed-tab" id="rad-5" onclick="setRadius(5)">📍 5 miles</button>
        <button type="button" class="ynj-feed-tab" id="rad-10" onclick="setRadius(10)">📍 10 miles</button>
        <button type="button" class="ynj-feed-tab" id="rad-25" onclick="setRadius(25)">📍 25 miles</button>
        <button type="button" class="ynj-feed-tab" id="rad-9999" onclick="setRadius(9999)">🌍 <?php esc_html_e( 'Nationwide', 'yourjannah' ); ?></button>
    </div>

    <!-- Results count -->
    <p style="font-size:12px;color:#6b8fa3;margin-bottom:10px;" id="results-count"><?php echo count( $services ); ?> <?php esc_html_e( 'professionals found', 'yourjannah' ); ?></p>

    <!-- Service listings -->
    <div id="svc-list">
    <?php if ( empty( $services ) ) : ?>
        <div style="text-align:center;padding:40px 20px;">
            <div style="font-size:40px;margin-bottom:12px;">🤝</div>
            <h3><?php esc_html_e( 'No professionals found', 'yourjannah' ); ?></h3>
            <p class="ynj-text-muted"><?php echo $search ? esc_html( sprintf( __( 'No results for "%s". Try a different search.', 'yourjannah' ), $search ) ) : esc_html__( 'No services listed yet. Be the first!', 'yourjannah' ); ?></p>
        </div>
    <?php else : ?>
    <?php foreach ( $services as $s ) :
        $rate = $s->hourly_rate_pence ? '£' . number_format( $s->hourly_rate_pence / 100, 0 ) . '/hr' : '';
        $initial = strtoupper( substr( $s->provider_name ?: '?', 0, 1 ) );
        $detail_url = home_url( '/mosque/' . $slug . '/service/' . $s->id );
    ?>
        <div class="ynj-biz-card" onclick="window.location.href='<?php echo esc_url( $detail_url ); ?>'" style="cursor:pointer;margin-bottom:8px;" data-search="<?php echo esc_attr( strtolower( $s->provider_name . ' ' . $s->service_type . ' ' . $s->description . ' ' . $s->area_covered ) ); ?>" data-type="<?php echo esc_attr( strtolower( $s->service_type ) ); ?>">
            <div class="ynj-biz-header">
                <div class="ynj-biz-logo" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);"><?php echo esc_html( $initial ); ?></div>
                <div class="ynj-biz-info">
                    <h3 class="ynj-biz-name"><?php echo esc_html( $s->provider_name ); ?></h3>
                    <span class="ynj-biz-cat" style="background:#ede9fe;color:#7c3aed;"><?php echo esc_html( $s->service_type ); ?></span>
                    <?php if ( $rate ) : ?><span style="font-size:12px;font-weight:700;color:#16a34a;display:block;margin-top:2px;"><?php echo esc_html( $rate ); ?></span><?php endif; ?>
                </div>
            </div>
            <?php if ( $s->description ) : ?><p class="ynj-biz-desc"><?php echo esc_html( mb_strimwidth( $s->description, 0, 120, '...' ) ); ?></p><?php endif; ?>
            <div class="ynj-biz-details">
                <?php if ( $s->area_covered ) : ?><div class="ynj-biz-detail">📍 <?php echo esc_html( $s->area_covered ); ?></div><?php endif; ?>
            </div>
            <div class="ynj-biz-actions" onclick="event.stopPropagation();">
                <?php if ( $s->phone ) : ?><a href="tel:<?php echo esc_attr( $s->phone ); ?>" class="ynj-biz-btn">📞 Call</a><?php endif; ?>
                <?php if ( $s->email ) : ?><a href="mailto:<?php echo esc_attr( $s->email ); ?>" class="ynj-biz-btn ynj-biz-btn--outline">✉️ Email</a><?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
    </div>

    <!-- Nearby results (loaded via JS when radius > 0) -->
    <div id="nearby-svc-list" style="display:none;">
        <h3 style="font-size:14px;font-weight:700;margin:16px 0 8px;">🕌 <?php esc_html_e( 'From Nearby Mosques', 'yourjannah' ); ?></h3>
        <div id="nearby-svc-cards"></div>
    </div>
</main>

<script>
(function(){
    var slug = <?php echo wp_json_encode( $slug ); ?>;
    var API = ynjData.restUrl;
    var lat = <?php echo $mosque_lat ?: 'null'; ?>;
    var lng = <?php echo $mosque_lng ?: 'null'; ?>;
    var nearbyLoaded = {};
    var currentRadius = 0;

    window.setRadius = function(r) {
        currentRadius = r;
        [0,5,10,25,9999].forEach(function(v) {
            var el = document.getElementById('rad-'+v);
            if (el) el.classList.toggle('ynj-feed-tab--active', v === r);
        });

        var nearbySection = document.getElementById('nearby-svc-list');
        var nearbyCards = document.getElementById('nearby-svc-cards');

        if (r === 0) {
            nearbySection.style.display = 'none';
            return;
        }
        if (!lat) { nearbyCards.innerHTML = '<p class="ynj-text-muted">Location not available.</p>'; nearbySection.style.display = ''; return; }

        if (nearbyLoaded[r]) {
            nearbySection.style.display = nearbyLoaded[r].length ? '' : 'none';
            return;
        }

        nearbyCards.innerHTML = '<p class="ynj-text-muted">Loading nearby professionals...</p>';
        nearbySection.style.display = '';

        var radiusKm = r === 9999 ? 9999 : r * 1.609;
        fetch(API + 'mosques/nearest?lat=' + lat + '&lng=' + lng + '&limit=15&radius_km=' + radiusKm)
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                var mosques = (data.mosques || []).filter(function(m) { return m.slug !== slug; });
                return Promise.all(mosques.slice(0,10).map(function(m) {
                    return fetch(API + 'mosques/' + m.id + '/services?per_page=50').then(function(r) { return r.json(); })
                        .then(function(d) {
                            return (d.services || []).map(function(s) {
                                s._mosque = m.name + (m.city ? ', ' + m.city : '');
                                s._dist = m.distance ? (m.distance < 1.6 ? (m.distance * 0.621).toFixed(1) + ' mi' : Math.round(m.distance * 0.621) + ' mi') : '';
                                s._slug = m.slug;
                                return s;
                            });
                        }).catch(function() { return []; });
                }));
            })
            .then(function(results) {
                var all = [].concat.apply([], results || []);
                nearbyLoaded[r] = all;
                if (!all.length) {
                    nearbyCards.innerHTML = '<p class="ynj-text-muted">No professionals found in this radius.</p>';
                    return;
                }
                nearbyCards.innerHTML = all.map(function(s) {
                    var initial = (s.provider_name || '?')[0].toUpperCase();
                    var rate = s.hourly_rate_pence > 0 ? '<span style="font-size:12px;font-weight:700;color:#16a34a;">\u00a3' + (s.hourly_rate_pence/100) + '/hr</span>' : '';
                    return '<div class="ynj-biz-card" onclick="window.location.href=\'/mosque/' + s._slug + '/service/' + s.id + '\'" style="cursor:pointer;margin-bottom:8px;">' +
                        '<div class="ynj-biz-header">' +
                            '<div class="ynj-biz-logo" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">' + initial + '</div>' +
                            '<div class="ynj-biz-info">' +
                                '<h3 class="ynj-biz-name">' + s.provider_name + '</h3>' +
                                '<span class="ynj-biz-cat" style="background:#ede9fe;color:#7c3aed;">' + s.service_type + '</span>' +
                                rate +
                            '</div>' +
                        '</div>' +
                        (s.description ? '<p class="ynj-biz-desc">' + (s.description.length > 100 ? s.description.slice(0,100) + '...' : s.description) + '</p>' : '') +
                        '<div class="ynj-biz-details">' +
                            '<div class="ynj-biz-detail">\uD83D\uDD4C ' + s._mosque + (s._dist ? ' \u00B7 ' + s._dist : '') + '</div>' +
                        '</div>' +
                        '<div class="ynj-biz-actions" onclick="event.stopPropagation();">' +
                            (s.phone ? '<a href="tel:' + s.phone + '" class="ynj-biz-btn">\uD83D\uDCDE Call</a>' : '') +
                            (s.email ? '<a href="mailto:' + s.email + '" class="ynj-biz-btn ynj-biz-btn--outline">\u2709\uFE0F Email</a>' : '') +
                        '</div>' +
                    '</div>';
                }).join('');
                document.getElementById('results-count').textContent = all.length + ' more from nearby mosques';
            })
            .catch(function() { nearbyCards.innerHTML = '<p class="ynj-text-muted">Could not load nearby services.</p>'; });
    };
})();
</script>
<?php get_footer(); ?>
