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

// Pre-load ALL data server-side — zero API calls needed
$mosque = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$businesses = [];
$services = [];
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
    <!-- CTA Buttons -->
    <div style="display:flex;gap:8px;margin-bottom:14px;">
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors/join' ) ); ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:14px 16px;border-radius:12px;background:linear-gradient(135deg,#00ADEF,#0369a1);color:#fff;font-size:14px;font-weight:700;text-decoration:none;text-align:center;box-shadow:0 4px 12px rgba(0,173,239,.25);">⭐ <?php esc_html_e( 'Sponsor Your Masjid', 'yourjannah' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services/join' ) ); ?>" style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:14px 16px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;font-size:14px;font-weight:700;text-decoration:none;text-align:center;box-shadow:0 4px 12px rgba(124,58,237,.25);">🤝 <?php esc_html_e( 'List Your Service', 'yourjannah' ); ?></a>
    </div>

    <!-- Search -->
    <div style="margin-bottom:12px;">
        <input type="text" id="biz-search" placeholder="<?php esc_attr_e( 'Search businesses, services, restaurants...', 'yourjannah' ); ?>" oninput="filterDir()" style="width:100%;padding:12px 16px;border:1px solid #d1d5db;border-radius:12px;font-size:14px;font-family:inherit;">
    </div>

    <!-- Tabs -->
    <div class="ynj-feed-tabs" style="margin-bottom:14px;">
        <button class="ynj-feed-tab ynj-feed-tab--active" data-tab="all" onclick="switchDirTab('all')">🏪 <?php esc_html_e( 'All', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" data-tab="food" onclick="switchDirTab('food')">🍽️ <?php esc_html_e( 'Halal Food', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" data-tab="business" onclick="switchDirTab('business')">⭐ <?php esc_html_e( 'Businesses', 'yourjannah' ); ?></button>
        <button class="ynj-feed-tab" data-tab="professional" onclick="switchDirTab('professional')">🤝 <?php esc_html_e( 'Professionals', 'yourjannah' ); ?></button>
    </div>

    <!-- Unified directory list -->
    <div id="dir-list" class="ynj-sponsors-grid">
    <?php
    $food_cats = [ 'Restaurant', 'Grocery', 'Butcher', 'Catering', 'Bakery', 'Cafe', 'Food' ];
    $has_items = false;

    // Render businesses
    foreach ( $businesses as $i => $b ) :
        $has_items = true;
        $cat = $b->category ?: '';
        $is_food = in_array( $cat, $food_cats, true );
        $dir_type = $is_food ? 'food' : 'business';
        $rank = $i + 1;
        $tierClass = $rank <= 3 ? ' ynj-biz--' . ( $rank === 1 ? 'premium' : ( $rank === 2 ? 'featured' : 'standard' ) ) : '';
        $tierLabel = $rank <= 3 ? [ '🥇 Premium', '🥈 Featured', '🥉 Standard' ][ $rank - 1 ] : '';
        $initial = strtoupper( substr( $b->business_name ?: '?', 0, 1 ) );
        $search_text = strtolower( $b->business_name . ' ' . $cat . ' ' . $b->description );
    ?>
        <div class="ynj-biz-card<?php echo $tierClass; ?>" data-dir-type="<?php echo $dir_type; ?>" data-search="<?php echo esc_attr( $search_text ); ?>">
            <?php if ( $tierLabel ) : ?><div class="ynj-biz-tier"><?php echo $tierLabel; ?> Sponsor</div><?php endif; ?>
            <div class="ynj-biz-header">
                <div class="ynj-biz-logo"><?php echo esc_html( $initial ); ?></div>
                <div class="ynj-biz-info">
                    <h3 class="ynj-biz-name"><?php echo esc_html( $b->business_name ); ?></h3>
                    <span class="ynj-biz-cat"><?php echo esc_html( $cat ); ?></span>
                </div>
            </div>
            <?php if ( $b->description ) : ?><p class="ynj-biz-desc"><?php echo esc_html( mb_strimwidth( $b->description, 0, 180, '...' ) ); ?></p><?php endif; ?>
            <div class="ynj-biz-details">
                <?php if ( $b->address || $b->postcode ) : ?><div class="ynj-biz-detail">📍 <?php echo esc_html( implode( ', ', array_filter( [ $b->address, $b->postcode ] ) ) ); ?></div><?php endif; ?>
                <?php if ( $b->phone ) : ?><div class="ynj-biz-detail"><a href="tel:<?php echo esc_attr( $b->phone ); ?>">📞 <?php echo esc_html( $b->phone ); ?></a></div><?php endif; ?>
            </div>
            <div class="ynj-biz-actions">
                <?php if ( $b->phone ) : ?><a href="tel:<?php echo esc_attr( $b->phone ); ?>" class="ynj-biz-btn">📞 Call</a><?php endif; ?>
                <?php if ( $b->website ) : ?><a href="<?php echo esc_url( $b->website ); ?>" target="_blank" rel="noopener" class="ynj-biz-btn ynj-biz-btn--outline">🌐 Website</a><?php endif; ?>
            </div>
        </div>
    <?php endforeach;

    // Render services as cards too
    foreach ( $services as $s ) :
        $has_items = true;
        $search_text = strtolower( $s->provider_name . ' ' . $s->service_type . ' ' . $s->description );
    ?>
        <div class="ynj-biz-card" data-dir-type="professional" data-search="<?php echo esc_attr( $search_text ); ?>">
            <div class="ynj-biz-header">
                <div class="ynj-biz-logo" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);"><?php echo esc_html( strtoupper( substr( $s->provider_name ?: '?', 0, 1 ) ) ); ?></div>
                <div class="ynj-biz-info">
                    <h3 class="ynj-biz-name"><?php echo esc_html( $s->provider_name ); ?></h3>
                    <span class="ynj-biz-cat"><?php echo esc_html( $s->service_type ); ?></span>
                </div>
            </div>
            <?php if ( $s->description ) : ?><p class="ynj-biz-desc"><?php echo esc_html( mb_strimwidth( $s->description, 0, 180, '...' ) ); ?></p><?php endif; ?>
            <div class="ynj-biz-details">
                <?php if ( $s->area_covered ) : ?><div class="ynj-biz-detail">📍 <?php echo esc_html( $s->area_covered ); ?></div><?php endif; ?>
                <?php if ( $s->phone ) : ?><div class="ynj-biz-detail"><a href="tel:<?php echo esc_attr( $s->phone ); ?>">📞 <?php echo esc_html( $s->phone ); ?></a></div><?php endif; ?>
            </div>
            <div class="ynj-biz-actions">
                <?php if ( $s->phone ) : ?><a href="tel:<?php echo esc_attr( $s->phone ); ?>" class="ynj-biz-btn">📞 Call</a><?php endif; ?>
                <?php if ( $s->email ) : ?><a href="mailto:<?php echo esc_attr( $s->email ); ?>" class="ynj-biz-btn ynj-biz-btn--outline">✉️ Email</a><?php endif; ?>
            </div>
        </div>
    <?php endforeach;

    if ( ! $has_items ) : ?>
        <p class="ynj-text-muted"><?php esc_html_e( 'No businesses or services listed yet. Be the first!', 'yourjannah' ); ?></p>
    <?php endif; ?>
    </div>
    <p id="dir-empty" class="ynj-text-muted" style="display:none;text-align:center;padding:20px;"><?php esc_html_e( 'No results found.', 'yourjannah' ); ?></p>
</main>

<script>
(function(){
    var activeTab = 'all';

    // Tab switching — pure client-side filtering of PHP-rendered cards
    window.switchDirTab = function(tab) {
        activeTab = tab;
        document.querySelectorAll('.ynj-feed-tab').forEach(function(btn) {
            btn.classList.toggle('ynj-feed-tab--active', btn.dataset.tab === tab);
        });
        filterDir();
    };

    // Search + tab filter
    window.filterDir = function() {
        var query = (document.getElementById('biz-search').value || '').toLowerCase();
        var cards = document.querySelectorAll('#dir-list .ynj-biz-card');
        var shown = 0;
        cards.forEach(function(card) {
            var type = card.dataset.dirType || '';
            var search = card.dataset.search || '';
            var matchTab = (activeTab === 'all' || type === activeTab);
            var matchSearch = (!query || search.indexOf(query) !== -1);
            var show = matchTab && matchSearch;
            card.style.display = show ? '' : 'none';
            if (show) shown++;
        });
        // Show/hide empty message
        var empty = document.getElementById('dir-empty');
        if (empty) empty.style.display = shown === 0 ? '' : 'none';
    };
})();
</script>
<?php get_footer(); ?>
