<?php
/**
 * Template: Masjid Hub
 *
 * Combined view: room/service bookings at top, events + announcements feed below.
 * This is the "Masjid" tab in the bottom nav.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();

// Pre-load ALL data server-side — zero API calls for primary data
$mosque      = ynj_get_mosque( $slug );
$mosque_id   = $mosque ? (int) $mosque->id : 0;
$mosque_name = $mosque ? $mosque->name : __( 'Your Masjid', 'yourjannah' );
$announcements = [];
$hub_events    = [];
$hub_classes   = [];
if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $ann_table = YNJ_DB::table( 'announcements' );
    $ev_table  = YNJ_DB::table( 'events' );
    $cls_table = YNJ_DB::table( 'classes' );
    $announcements = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, body, type, pinned, status, published_at
         FROM $ann_table WHERE mosque_id = %d AND status = 'active'
         ORDER BY pinned DESC, published_at DESC LIMIT 50", $mosque_id
    ) ) ?: [];
    $hub_events = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, description, event_date, start_time, end_time, location, event_type, status
         FROM $ev_table WHERE mosque_id = %d AND status = 'active' AND event_date >= CURDATE()
         ORDER BY event_date ASC, start_time ASC LIMIT 50", $mosque_id
    ) ) ?: [];
    $hub_classes = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, description, day_of_week, start_time, location, category
         FROM $cls_table WHERE mosque_id = %d AND status = 'active'
         ORDER BY title ASC LIMIT 50", $mosque_id
    ) ) ?: [];
}
?>

<main class="ynj-main">
    <h2 id="hub-title" style="font-size:18px;font-weight:700;margin-bottom:14px;"><?php echo esc_html( $mosque_name ); ?></h2>

    <!-- Quick Book Section -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/rooms' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:18px 12px;background:#fff;border-radius:14px;border:1px solid rgba(0,0,0,.06);text-decoration:none;color:#0a1628;box-shadow:0 1px 4px rgba(0,0,0,.04);text-align:center;">
            <span style="font-size:28px;">🕌</span>
            <strong style="font-size:13px;"><?php esc_html_e( 'Book Services & Rooms', 'yourjannah' ); ?></strong>
            <span class="ynj-text-muted" style="font-size:11px;"><?php esc_html_e( 'Nikkah, funeral, rooms, counselling', 'yourjannah' ); ?></span>
        </a>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/contact' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:18px 12px;background:#fff;border-radius:14px;border:1px solid rgba(0,0,0,.06);text-decoration:none;color:#0a1628;box-shadow:0 1px 4px rgba(0,0,0,.04);text-align:center;">
            <span style="font-size:28px;">✉️</span>
            <strong style="font-size:13px;"><?php esc_html_e( 'Contact Masjid', 'yourjannah' ); ?></strong>
            <span class="ynj-text-muted" style="font-size:11px;"><?php esc_html_e( 'Send an enquiry or message', 'yourjannah' ); ?></span>
        </a>
    </div>

    <!-- Events + Announcements Feed -->
    <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;"><?php esc_html_e( "What's Happening", 'yourjannah' ); ?></h3>
    <div class="ynj-filter-chips" id="hub-filters">
        <button class="ynj-chip ynj-chip--active" data-filter="all" onclick="filterHub('all')">All</button>
        <button class="ynj-chip" data-filter="announcements" onclick="filterHub('announcements')">📢 Updates</button>
        <button class="ynj-chip" data-filter="events" onclick="filterHub('events')">📅 Events</button>
        <button class="ynj-chip" data-filter="classes" onclick="filterHub('classes')">🎓 Classes</button>
    </div>
    <div id="hub-feed">
    <?php if ( empty( $announcements ) && empty( $hub_events ) && empty( $hub_classes ) ) : ?>
        <p class="ynj-text-muted" style="padding:12px;text-align:center;"><?php esc_html_e( 'Nothing to show yet.', 'yourjannah' ); ?></p>
    <?php endif; ?>
    </div>
</main>

<script>
(function(){
    var slug = <?php echo wp_json_encode( $slug ); ?>;
    var API = ynjData.restUrl;
    var currentFilter = 'all';

    // Pre-loaded from PHP — instant, no API calls
    var allItems = [];
    <?php foreach ( $announcements as $a ) : ?>
    allItems.push({type:'announcement', title:<?php echo wp_json_encode( $a->title ); ?>, body:<?php echo wp_json_encode( $a->body ?: '' ); ?>, date:<?php echo wp_json_encode( $a->published_at ?: '' ); ?>, pinned:<?php echo $a->pinned ? 'true' : 'false'; ?>});
    <?php endforeach; ?>
    <?php foreach ( $hub_events as $e ) : ?>
    allItems.push({type:'event', title:<?php echo wp_json_encode( $e->title ); ?>, body:<?php echo wp_json_encode( $e->description ?: '' ); ?>, date:<?php echo wp_json_encode( $e->event_date ?: '' ); ?>, time:<?php echo wp_json_encode( $e->start_time ? preg_replace( '/:\d{2}$/', '', $e->start_time ) : '' ); ?>, location:<?php echo wp_json_encode( $e->location ?: '' ); ?>, event_type:<?php echo wp_json_encode( $e->event_type ?: '' ); ?>, event_id:<?php echo (int) $e->id; ?>});
    <?php endforeach; ?>
    <?php foreach ( $hub_classes as $c ) : ?>
    allItems.push({type:'class', title:<?php echo wp_json_encode( $c->title ); ?>, body:<?php echo wp_json_encode( $c->description ?: '' ); ?>, date:'', day_of_week:<?php echo wp_json_encode( $c->day_of_week ?: '' ); ?>, category:<?php echo wp_json_encode( $c->category ?: '' ); ?>});
    <?php endforeach; ?>

    // Sort: pinned first, then by date
    allItems.sort(function(a,b){
        if(a.pinned&&!b.pinned)return -1; if(!a.pinned&&b.pinned)return 1;
        return (a.date||'9').localeCompare(b.date||'9');
    });
    renderHub();

    function renderHub(){
        var el = document.getElementById('hub-feed');
        var items = allItems;
        if(currentFilter==='announcements') items = items.filter(function(i){return i.type==='announcement';});
        else if(currentFilter==='events') items = items.filter(function(i){return i.type==='event';});
        else if(currentFilter==='classes') items = items.filter(function(i){return i.type==='class';});

        if(!items.length){ el.innerHTML = '<p class="ynj-text-muted" style="padding:12px;text-align:center;">Nothing to show yet.</p>'; return; }

        el.innerHTML = '<div class="ynj-feed">' + items.map(function(item){
            var badge, bg;
            if(item.type==='announcement'){
                badge = item.pinned ? '<span class="ynj-badge" style="background:#dcfce7;color:#166534;">📌 Pinned</span>' : '<span class="ynj-badge" style="background:#e8f4f8;color:#0369a1;">📢 Update</span>';
                bg = item.pinned ? '#f0fdf4' : '#eff6ff';
            } else if(item.type==='class'){
                badge = '<span class="ynj-badge" style="background:#ede9fe;color:#7c3aed;">🎓 Class</span>';
                bg = '#f5f3ff';
            } else {
                var icons = {talk:'🎤',community:'🤝',sports:'⚽',youth:'👦',sisters:'👩',workshop:'🛠️'};
                var icon = icons[item.event_type] || '📅';
                badge = '<span class="ynj-badge" style="background:#fef3c7;color:#92400e;">' + icon + ' ' + (item.event_type||'Event') + '</span>';
                bg = '#fffbeb';
            }
            var snippet = (item.body||'').length > 80 ? item.body.slice(0,80)+'...' : (item.body||'');
            var meta = [];
            if(item.time) meta.push('<span>🕐 ' + item.time + '</span>');
            if(item.location) meta.push('<span>📍 ' + item.location + '</span>');
            if(item.day_of_week) meta.push('<span>📅 ' + item.day_of_week + 's</span>');
            if(item.event_id) meta.push('<a href="/mosque/' + slug + '/events/' + item.event_id + '" style="color:#00ADEF;font-weight:600;">View →</a>');

            return '<div class="ynj-feed-card" style="background:' + bg + '"><div class="ynj-feed-card__content"><div class="ynj-feed-card__top">' + badge + '<h4>' + item.title + '</h4></div>' + (snippet ? '<div class="ynj-feed-card__body">' + snippet + '</div>' : '') + (meta.length ? '<div class="ynj-feed-card__meta">' + meta.join(' ') + '</div>' : '') + '</div></div>';
        }).join('') + '</div>';
    }

    window.filterHub = function(f){
        currentFilter = f;
        document.querySelectorAll('#hub-filters .ynj-chip').forEach(function(c){c.classList.toggle('ynj-chip--active',c.dataset.filter===f);});
        renderHub();
    };
})();
</script>
<?php get_footer(); ?>
