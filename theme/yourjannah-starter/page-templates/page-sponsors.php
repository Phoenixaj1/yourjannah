<?php
/**
 * Template: Sponsors & Services Directory
 *
 * Improved: category filter, logos, verified badges, owner names,
 * pricing, clickable profile links, email display.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$businesses = [];
$services = [];
$all_categories = [];

if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $biz_table = YNJ_DB::table( 'businesses' );
    $svc_table = YNJ_DB::table( 'services' );
    $businesses = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, business_name, owner_name, category, description, phone, email, website, logo_url, address, postcode, monthly_fee_pence, featured_position, verified
         FROM $biz_table WHERE mosque_id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY monthly_fee_pence DESC, business_name ASC LIMIT 100", $mosque_id
    ) ) ?: [];
    $services = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, provider_name, phone, email, service_type, description, hourly_rate_pence, area_covered
         FROM $svc_table WHERE mosque_id = %d AND status = 'active'
         ORDER BY monthly_fee_pence DESC, provider_name ASC LIMIT 100", $mosque_id
    ) ) ?: [];

    // Collect unique categories
    foreach ( $businesses as $b ) {
        if ( $b->category && ! in_array( $b->category, $all_categories ) ) $all_categories[] = $b->category;
    }
    foreach ( $services as $s ) {
        if ( $s->service_type && ! in_array( $s->service_type, $all_categories ) ) $all_categories[] = $s->service_type;
    }
    sort( $all_categories );
}

$food_cats = [ 'Restaurant', 'Grocery', 'Butcher', 'Catering', 'Bakery', 'Cafe', 'Food' ];
?>

<main class="ynj-main">
    <p style="font-size:14px;font-weight:600;color:#0a1628;margin-bottom:12px;line-height:1.4;"><?php esc_html_e( 'These people and businesses support your masjid. Support them back.', 'yourjannah' ); ?></p>

    <!-- CTA Buttons -->
    <div style="display:flex;gap:8px;margin-bottom:14px;">
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors/join' ) ); ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:14px 16px;border-radius:12px;background:linear-gradient(135deg,#00ADEF,#0369a1);color:#fff;font-size:14px;font-weight:700;text-decoration:none;text-align:center;box-shadow:0 4px 12px rgba(0,173,239,.25);">⭐ <?php esc_html_e( 'Sponsor Your Masjid', 'yourjannah' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services/join' ) ); ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:14px 16px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;font-size:14px;font-weight:700;text-decoration:none;text-align:center;box-shadow:0 4px 12px rgba(124,58,237,.25);">🤝 <?php esc_html_e( 'List Your Service', 'yourjannah' ); ?></a>
    </div>

    <!-- Search + Category Filter -->
    <div style="display:flex;gap:8px;margin-bottom:12px;">
        <input type="text" id="biz-search" placeholder="<?php esc_attr_e( 'Search businesses, services...', 'yourjannah' ); ?>" oninput="filterDir()" style="flex:1;padding:12px 16px;border:1px solid #d1d5db;border-radius:12px;font-size:14px;font-family:inherit;min-width:0;">
        <select id="biz-category" onchange="filterDir()" style="padding:10px 12px;border:1px solid #d1d5db;border-radius:12px;font-size:13px;font-family:inherit;background:#fff;min-width:0;max-width:140px;">
            <option value=""><?php esc_html_e( 'All Categories', 'yourjannah' ); ?></option>
            <?php foreach ( $all_categories as $cat ) : ?>
            <option value="<?php echo esc_attr( strtolower( $cat ) ); ?>"><?php echo esc_html( $cat ); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Tabs -->
    <div class="ynj-feed-tabs" style="margin-bottom:14px;">
        <button class="ynj-feed-tab ynj-feed-tab--active" data-tab="all" onclick="switchDirTab('all')">🏪 <?php esc_html_e( 'All', 'yourjannah' ); ?> <span style="font-size:11px;opacity:.6">(<?php echo count( $businesses ) + count( $services ); ?>)</span></button>
        <button class="ynj-feed-tab" data-tab="food" onclick="switchDirTab('food')">🍽️ <?php esc_html_e( 'Halal Food', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" data-tab="business" onclick="switchDirTab('business')">⭐ <?php esc_html_e( 'Businesses', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" data-tab="professional" onclick="switchDirTab('professional')">🤝 <?php esc_html_e( 'Professionals', 'yourjannah' ); ?></button>
    </div>

    <!-- Directory List -->
    <div id="dir-list" class="ynj-sponsors-grid">
    <?php
    $has_items = false;

    // Render businesses
    foreach ( $businesses as $i => $b ) :
        $has_items = true;
        $cat = $b->category ?: '';
        $is_food = in_array( $cat, $food_cats, true );
        $dir_type = $is_food ? 'food' : 'business';
        $rank = $i + 1;
        $tier_class = '';
        $tier_label = '';
        if ( $b->featured_position == 2 || $rank === 1 ) { $tier_class = ' ynj-biz--premium'; $tier_label = '🥇 Premium'; }
        elseif ( $b->featured_position == 1 || $rank === 2 ) { $tier_class = ' ynj-biz--featured'; $tier_label = '🥈 Featured'; }
        elseif ( $rank <= 5 ) { $tier_class = ' ynj-biz--standard'; $tier_label = '🥉 Standard'; }
        $initial = strtoupper( substr( $b->business_name ?: '?', 0, 1 ) );
        $search_text = strtolower( $b->business_name . ' ' . $cat . ' ' . ( $b->description ?? '' ) . ' ' . ( $b->address ?? '' ) . ' ' . ( $b->postcode ?? '' ) );
        $detail_url = home_url( '/mosque/' . $slug . '/business/' . $b->id );
    ?>
        <div class="ynj-biz-card<?php echo $tier_class; ?>" data-dir-type="<?php echo $dir_type; ?>" data-category="<?php echo esc_attr( strtolower( $cat ) ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>" onclick="window.location.href='<?php echo esc_url( $detail_url ); ?>'" style="cursor:pointer;">
            <?php if ( $tier_label ) : ?><div class="ynj-biz-tier"><?php echo $tier_label; ?> Sponsor</div><?php endif; ?>
            <div class="ynj-biz-header">
                <?php if ( $b->logo_url ) : ?>
                <div class="ynj-biz-logo"><img src="<?php echo esc_url( $b->logo_url ); ?>" alt="<?php echo esc_attr( $b->business_name ); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:12px;"></div>
                <?php else : ?>
                <div class="ynj-biz-logo"><?php echo esc_html( $initial ); ?></div>
                <?php endif; ?>
                <div class="ynj-biz-info">
                    <h3 class="ynj-biz-name">
                        <?php echo esc_html( $b->business_name ); ?>
                        <?php if ( $b->verified ) : ?><span title="Verified" style="color:#16a34a;font-size:14px;">✓</span><?php endif; ?>
                    </h3>
                    <span class="ynj-biz-cat"><?php echo esc_html( $cat ); ?></span>
                    <?php if ( $b->owner_name ) : ?><span style="font-size:11px;color:#6b8fa3;display:block;margin-top:2px;">by <?php echo esc_html( $b->owner_name ); ?></span><?php endif; ?>
                </div>
            </div>
            <?php if ( $b->description ) : ?><p class="ynj-biz-desc"><?php echo esc_html( mb_strimwidth( $b->description, 0, 120, '...' ) ); ?></p><?php endif; ?>
            <div class="ynj-biz-details">
                <?php if ( $b->address || $b->postcode ) : ?><div class="ynj-biz-detail">📍 <?php echo esc_html( implode( ', ', array_filter( [ $b->address, $b->postcode ] ) ) ); ?></div><?php endif; ?>
            </div>
            <div class="ynj-biz-actions" onclick="event.stopPropagation();">
                <?php if ( $b->phone ) : ?><a href="tel:<?php echo esc_attr( $b->phone ); ?>" class="ynj-biz-btn">📞 Call</a><?php endif; ?>
                <?php if ( $b->email ) : ?><a href="mailto:<?php echo esc_attr( $b->email ); ?>" class="ynj-biz-btn ynj-biz-btn--outline">✉️ Email</a><?php endif; ?>
                <?php if ( $b->website ) : ?><a href="<?php echo esc_url( $b->website ); ?>" target="_blank" rel="noopener" class="ynj-biz-btn ynj-biz-btn--outline">🌐 Website</a><?php endif; ?>
            </div>
        </div>
    <?php endforeach;

    // Render services
    foreach ( $services as $s ) :
        $has_items = true;
        $search_text = strtolower( $s->provider_name . ' ' . $s->service_type . ' ' . ( $s->description ?? '' ) . ' ' . ( $s->area_covered ?? '' ) );
        $detail_url = home_url( '/mosque/' . $slug . '/service/' . $s->id );
        $rate = $s->hourly_rate_pence ? '£' . number_format( $s->hourly_rate_pence / 100, 0 ) . '/hr' : '';
    ?>
        <div class="ynj-biz-card" data-dir-type="professional" data-category="<?php echo esc_attr( strtolower( $s->service_type ) ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>" onclick="window.location.href='<?php echo esc_url( $detail_url ); ?>'" style="cursor:pointer;">
            <div class="ynj-biz-header">
                <div class="ynj-biz-logo" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);"><?php echo esc_html( strtoupper( substr( $s->provider_name ?: '?', 0, 1 ) ) ); ?></div>
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
    <?php endforeach;

    if ( ! $has_items ) : ?>
        <div style="text-align:center;padding:40px 20px;"><div style="font-size:40px;margin-bottom:12px;">🏪</div><p class="ynj-text-muted"><?php esc_html_e( 'No businesses or services listed yet. Be the first!', 'yourjannah' ); ?></p></div>
    <?php endif; ?>
    </div>
    <p id="dir-empty" class="ynj-text-muted" style="display:none;text-align:center;padding:20px;"><?php esc_html_e( 'No results found for your search.', 'yourjannah' ); ?></p>
</main>

<script>
(function(){
    var activeTab = 'all';

    window.switchDirTab = function(tab) {
        activeTab = tab;
        document.querySelectorAll('.ynj-feed-tab').forEach(function(btn) {
            btn.classList.toggle('ynj-feed-tab--active', btn.dataset.tab === tab);
        });
        filterDir();
    };

    window.filterDir = function() {
        var query = (document.getElementById('biz-search').value || '').toLowerCase();
        var catFilter = (document.getElementById('biz-category').value || '').toLowerCase();
        var cards = document.querySelectorAll('#dir-list .ynj-biz-card');
        var shown = 0;
        cards.forEach(function(card) {
            var type = card.dataset.dirType || '';
            var search = card.dataset.search || '';
            var cardCat = card.dataset.category || '';
            var matchTab = (activeTab === 'all' || type === activeTab);
            var matchSearch = (!query || search.indexOf(query) !== -1);
            var matchCat = (!catFilter || cardCat === catFilter);
            var show = matchTab && matchSearch && matchCat;
            card.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        var empty = document.getElementById('dir-empty');
        if (empty) empty.style.display = shown === 0 ? '' : 'none';
    };
})();
</script>
<?php get_footer(); ?>
