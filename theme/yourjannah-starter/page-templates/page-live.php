<?php
/**
 * Template: Live & Streams — Cross-Mosque Discovery
 *
 * Browse live events, upcoming talks, and recordings from ALL mosques.
 * Pure PHP data loading. No API calls for primary content.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_name = $mosque ? $mosque->name : '';

// ── Load ALL live/online events across ALL mosques ──
$live_now = [];
$upcoming = [];
$archive = [];
$today = date( 'Y-m-d' );
$now_time = current_time( 'H:i' );

if ( class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $et = YNJ_DB::table( 'events' );
    $mt = YNJ_DB::table( 'mosques' );

    // Live NOW — events marked is_live=1 and happening today
    $live_now = $wpdb->get_results(
        "SELECT e.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city
         FROM $et e JOIN $mt m ON m.id = e.mosque_id
         WHERE e.status = 'published' AND e.is_live = 1 AND e.is_online = 1
         AND e.event_date = '$today'
         ORDER BY e.start_time ASC LIMIT 20"
    ) ?: [];

    // Upcoming online events (next 30 days, across all mosques)
    $upcoming = $wpdb->get_results(
        "SELECT e.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city
         FROM $et e JOIN $mt m ON m.id = e.mosque_id
         WHERE e.status = 'published' AND (e.is_online = 1 OR e.live_url != '')
         AND e.event_date >= '$today' AND e.is_live = 0
         ORDER BY e.event_date ASC, e.start_time ASC LIMIT 50"
    ) ?: [];

    // Recent recordings (past 60 days, have recording_url)
    $archive = $wpdb->get_results(
        "SELECT e.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city
         FROM $et e JOIN $mt m ON m.id = e.mosque_id
         WHERE e.status = 'published' AND e.recording_url IS NOT NULL AND e.recording_url != ''
         AND e.event_date < '$today'
         ORDER BY e.event_date DESC LIMIT 30"
    ) ?: [];
}

// Search
$search = sanitize_text_field( $_GET['q'] ?? '' );
if ( $search ) {
    $search_lower = strtolower( $search );
    $filter_fn = function( $events ) use ( $search_lower ) {
        return array_filter( $events, function( $e ) use ( $search_lower ) {
            return stripos( $e->title ?? '', $search_lower ) !== false
                || stripos( $e->description ?? '', $search_lower ) !== false
                || stripos( $e->mosque_name ?? '', $search_lower ) !== false
                || stripos( $e->event_type ?? '', $search_lower ) !== false;
        } );
    };
    $live_now = $filter_fn( $live_now );
    $upcoming = $filter_fn( $upcoming );
    $archive = $filter_fn( $archive );
}

// Tab
$tab = sanitize_text_field( $_GET['tab'] ?? 'all' );

function ynj_live_embed_url( $url ) {
    if ( ! $url ) return '';
    if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|live\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return '';
}
?>

<style>
.ynj-live-hero{text-align:center;padding:20px 16px 12px;}
.ynj-live-search{display:flex;gap:8px;margin-bottom:14px;}
.ynj-live-search input{flex:1;padding:12px 16px;border:1px solid #d1d5db;border-radius:12px;font-size:14px;font-family:inherit;}
.ynj-live-tabs{display:flex;gap:0;margin-bottom:16px;background:rgba(255,255,255,.6);border-radius:12px;padding:3px;border:1px solid rgba(0,173,239,.1);}
.ynj-live-tab{flex:1;padding:9px 8px;border:none;background:none;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;color:#6b8fa3;font-family:inherit;text-align:center;text-decoration:none;}
.ynj-live-tab--active{background:#00ADEF;color:#fff;box-shadow:0 2px 8px rgba(0,173,239,.25);}
.ynj-live-grid{display:grid;gap:14px;}
@media(min-width:600px){.ynj-live-grid{grid-template-columns:1fr 1fr;}}
.ynj-lc{background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 2px 8px rgba(0,0,0,.04);}
.ynj-lc--live{border:2px solid #dc2626;box-shadow:0 4px 20px rgba(220,38,38,.15);}
.ynj-lc__video{width:100%;aspect-ratio:16/9;background:#0a1628;display:flex;align-items:center;justify-content:center;}
.ynj-lc__video iframe{width:100%;height:100%;border:none;}
.ynj-lc__video--placeholder{aspect-ratio:3/1;background:linear-gradient(135deg,#0a1628,#1a3a5c);}
.ynj-lc__body{padding:14px;}
.ynj-lc__badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.ynj-lc__badge--live{background:#dc2626;color:#fff;}
.ynj-lc__badge--live::before{content:'';width:6px;height:6px;background:#fff;border-radius:50%;animation:livePulse 1.5s ease-in-out infinite;}
.ynj-lc__badge--upcoming{background:#f59e0b;color:#fff;}
.ynj-lc__badge--archive{background:#6b7280;color:#fff;}
@keyframes livePulse{0%,100%{opacity:1}50%{opacity:.3}}
.ynj-lc__title{font-size:15px;font-weight:700;margin:6px 0 4px;}
.ynj-lc__mosque{font-size:12px;color:#00ADEF;font-weight:600;}
.ynj-lc__meta{font-size:12px;color:#6b8fa3;margin-top:4px;}
.ynj-lc__cta{display:block;text-align:center;padding:10px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;margin-top:10px;}
.ynj-lc__cta--live{background:#dc2626;color:#fff;}
.ynj-lc__cta--watch{background:#f3f4f6;color:#0a1628;}
.ynj-live-empty{text-align:center;padding:40px 20px;grid-column:1/-1;}
</style>

<main class="ynj-main">
    <div class="ynj-live-hero">
        <h1 style="font-size:22px;font-weight:800;margin-bottom:4px;">📡 <?php esc_html_e( 'Live & Streams', 'yourjannah' ); ?></h1>
        <p class="ynj-text-muted"><?php esc_html_e( 'Watch live talks, browse upcoming events, and catch up on recordings from mosques near you.', 'yourjannah' ); ?></p>
    </div>

    <!-- Search -->
    <form method="get" action="" class="ynj-live-search">
        <input type="text" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search talks, speakers, topics, mosques...', 'yourjannah' ); ?>">
        <button type="submit" class="ynj-btn" style="flex-shrink:0;">🔍</button>
    </form>

    <!-- Tabs -->
    <div class="ynj-live-tabs">
        <a class="ynj-live-tab<?php echo $tab === 'all' ? ' ynj-live-tab--active' : ''; ?>" href="?tab=all<?php echo $search ? '&q=' . urlencode( $search ) : ''; ?>">All</a>
        <a class="ynj-live-tab<?php echo $tab === 'live' ? ' ynj-live-tab--active' : ''; ?>" href="?tab=live<?php echo $search ? '&q=' . urlencode( $search ) : ''; ?>">🔴 Live Now<?php if ( count( $live_now ) ) echo ' (' . count( $live_now ) . ')'; ?></a>
        <a class="ynj-live-tab<?php echo $tab === 'upcoming' ? ' ynj-live-tab--active' : ''; ?>" href="?tab=upcoming<?php echo $search ? '&q=' . urlencode( $search ) : ''; ?>">📅 Upcoming<?php if ( count( $upcoming ) ) echo ' (' . count( $upcoming ) . ')'; ?></a>
        <a class="ynj-live-tab<?php echo $tab === 'archive' ? ' ynj-live-tab--active' : ''; ?>" href="?tab=archive<?php echo $search ? '&q=' . urlencode( $search ) : ''; ?>">📼 Recordings<?php if ( count( $archive ) ) echo ' (' . count( $archive ) . ')'; ?></a>
    </div>

    <!-- Live Now (always show if any are live) -->
    <?php if ( ( $tab === 'all' || $tab === 'live' ) && ! empty( $live_now ) ) : ?>
    <h2 style="font-size:16px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
        <span class="ynj-lc__badge ynj-lc__badge--live">LIVE</span> <?php esc_html_e( 'Happening Now', 'yourjannah' ); ?>
    </h2>
    <div class="ynj-live-grid" style="margin-bottom:20px;">
    <?php foreach ( $live_now as $e ) :
        $embed = ynj_live_embed_url( $e->live_url ?? '' );
    ?>
        <div class="ynj-lc ynj-lc--live">
            <?php if ( $embed ) : ?>
            <div class="ynj-lc__video"><iframe src="<?php echo esc_url( $embed ); ?>" allow="autoplay;encrypted-media" allowfullscreen loading="lazy"></iframe></div>
            <?php else : ?>
            <div class="ynj-lc__video" style="aspect-ratio:3/1;"><span style="color:rgba(255,255,255,.6);">🔴 Live Now</span></div>
            <?php endif; ?>
            <div class="ynj-lc__body">
                <span class="ynj-lc__badge ynj-lc__badge--live">LIVE</span>
                <h3 class="ynj-lc__title"><?php echo esc_html( $e->title ); ?></h3>
                <div class="ynj-lc__mosque">🕌 <?php echo esc_html( $e->mosque_name ); ?><?php if ( $e->mosque_city ) echo ' · ' . esc_html( $e->mosque_city ); ?></div>
                <div class="ynj-lc__meta">
                    <?php if ( $e->start_time ) echo '🕐 ' . esc_html( substr( $e->start_time, 0, 5 ) ); ?>
                    <?php if ( $e->event_type ) echo ' · ' . esc_html( ucfirst( $e->event_type ) ); ?>
                </div>
                <?php if ( $e->live_url ) : ?>
                <a href="<?php echo esc_url( $e->live_url ); ?>" target="_blank" rel="noopener" class="ynj-lc__cta ynj-lc__cta--live">▶ <?php esc_html_e( 'Watch Live', 'yourjannah' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Upcoming -->
    <?php if ( ( $tab === 'all' || $tab === 'upcoming' ) && ! empty( $upcoming ) ) : ?>
    <h2 style="font-size:16px;font-weight:700;margin-bottom:10px;">📅 <?php esc_html_e( 'Upcoming Streams', 'yourjannah' ); ?></h2>
    <div class="ynj-live-grid" style="margin-bottom:20px;">
    <?php foreach ( $upcoming as $e ) : ?>
        <div class="ynj-lc">
            <div class="ynj-lc__video ynj-lc__video--placeholder">
                <span style="color:rgba(255,255,255,.5);font-size:13px;">📅 <?php echo esc_html( date( 'D j M', strtotime( $e->event_date ) ) ); ?> · <?php echo $e->start_time ? esc_html( substr( $e->start_time, 0, 5 ) ) : ''; ?></span>
            </div>
            <div class="ynj-lc__body">
                <span class="ynj-lc__badge ynj-lc__badge--upcoming"><?php esc_html_e( 'Upcoming', 'yourjannah' ); ?></span>
                <h3 class="ynj-lc__title"><?php echo esc_html( $e->title ); ?></h3>
                <div class="ynj-lc__mosque">🕌 <?php echo esc_html( $e->mosque_name ); ?><?php if ( $e->mosque_city ) echo ' · ' . esc_html( $e->mosque_city ); ?></div>
                <div class="ynj-lc__meta">
                    📅 <?php echo esc_html( date( 'l j F', strtotime( $e->event_date ) ) ); ?>
                    <?php if ( $e->start_time ) echo ' · 🕐 ' . esc_html( substr( $e->start_time, 0, 5 ) ); ?>
                    <?php if ( $e->event_type ) echo ' · ' . esc_html( ucfirst( $e->event_type ) ); ?>
                </div>
                <?php if ( $e->description ) : ?>
                <p style="font-size:12px;color:#555;margin-top:6px;line-height:1.4;"><?php echo esc_html( mb_strimwidth( $e->description, 0, 100, '...' ) ); ?></p>
                <?php endif; ?>
                <a href="<?php echo esc_url( home_url( '/mosque/' . $e->mosque_slug . '/events/' . $e->id ) ); ?>" class="ynj-lc__cta ynj-lc__cta--watch">🔔 <?php esc_html_e( 'View Details', 'yourjannah' ); ?></a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recordings -->
    <?php if ( ( $tab === 'all' || $tab === 'archive' ) && ! empty( $archive ) ) : ?>
    <h2 style="font-size:16px;font-weight:700;margin-bottom:10px;">📼 <?php esc_html_e( 'Recordings', 'yourjannah' ); ?></h2>
    <div class="ynj-live-grid" style="margin-bottom:20px;">
    <?php foreach ( $archive as $e ) :
        $embed = ynj_live_embed_url( $e->recording_url ?? $e->live_url ?? '' );
    ?>
        <div class="ynj-lc">
            <?php if ( $embed ) : ?>
            <div class="ynj-lc__video"><iframe src="<?php echo esc_url( $embed ); ?>" allowfullscreen loading="lazy"></iframe></div>
            <?php else : ?>
            <div class="ynj-lc__video ynj-lc__video--placeholder"><span style="color:rgba(255,255,255,.5);">📼 <?php echo esc_html( date( 'j M Y', strtotime( $e->event_date ) ) ); ?></span></div>
            <?php endif; ?>
            <div class="ynj-lc__body">
                <span class="ynj-lc__badge ynj-lc__badge--archive"><?php esc_html_e( 'Recording', 'yourjannah' ); ?></span>
                <h3 class="ynj-lc__title"><?php echo esc_html( $e->title ); ?></h3>
                <div class="ynj-lc__mosque">🕌 <?php echo esc_html( $e->mosque_name ); ?></div>
                <div class="ynj-lc__meta"><?php echo esc_html( date( 'j M Y', strtotime( $e->event_date ) ) ); ?> · <?php echo esc_html( ucfirst( $e->event_type ?? '' ) ); ?></div>
                <?php $watch_url = $e->recording_url ?: $e->live_url; if ( $watch_url ) : ?>
                <a href="<?php echo esc_url( $watch_url ); ?>" target="_blank" rel="noopener" class="ynj-lc__cta ynj-lc__cta--watch">▶ <?php esc_html_e( 'Watch', 'yourjannah' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Empty states -->
    <?php if ( $tab === 'live' && empty( $live_now ) ) : ?>
    <div class="ynj-live-empty"><div style="font-size:48px;">🔴</div><h3><?php esc_html_e( 'Nothing Live Right Now', 'yourjannah' ); ?></h3><p class="ynj-text-muted"><?php esc_html_e( 'Check back during events or browse upcoming streams.', 'yourjannah' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $tab === 'upcoming' && empty( $upcoming ) ) : ?>
    <div class="ynj-live-empty"><div style="font-size:48px;">📅</div><h3><?php esc_html_e( 'No Upcoming Streams', 'yourjannah' ); ?></h3><p class="ynj-text-muted"><?php esc_html_e( 'Mosques can add live stream URLs to events.', 'yourjannah' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $tab === 'archive' && empty( $archive ) ) : ?>
    <div class="ynj-live-empty"><div style="font-size:48px;">📼</div><h3><?php esc_html_e( 'No Recordings Yet', 'yourjannah' ); ?></h3><p class="ynj-text-muted"><?php esc_html_e( 'Past events with recording URLs will appear here.', 'yourjannah' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $tab === 'all' && empty( $live_now ) && empty( $upcoming ) && empty( $archive ) ) : ?>
    <div class="ynj-live-empty"><div style="font-size:48px;">📡</div><h3><?php esc_html_e( 'No Streams Available', 'yourjannah' ); ?></h3><p class="ynj-text-muted"><?php echo $search ? esc_html( sprintf( __( 'No results for "%s". Try a different search.', 'yourjannah' ), $search ) ) : esc_html__( 'When mosques add live streams or recordings to their events, they\'ll appear here.', 'yourjannah' ); ?></p></div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
