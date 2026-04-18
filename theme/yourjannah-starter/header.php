<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>

<!-- Theme system: disabled for now — needs proper per-component redesign -->
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
// ════════════════════════════════════════════════════════
// RPG-STYLE HUD — Gamified status bar on every page
// Shows: Masjid + Points + League Rank + Streak + Quick Dhikr + Profile
// ════════════════════════════════════════════════════════
$_ynj_bar_status = 'guest';
$_ynj_bar_name   = '';
$_ynj_bar_slug   = get_query_var( 'ynj_mosque_slug', '' );
$_hud_points     = 0;
$_hud_rank       = 0;
$_hud_tier       = [ 'key' => 'emerging', 'name' => 'Emerging', 'icon' => '&#x1F331;' ];
$_hud_streak     = 0;
$_hud_mosque     = null;
$_hud_mosque_slug = '';
$_hud_initial    = '?';
$_hud_dhikr      = null;
$_hud_dhikr_done = false;

if ( is_user_logged_in() ) {
    $_ynj_bar_status = 'member';
    $_ynj_bar_name   = wp_get_current_user()->display_name;
    $_hud_initial    = strtoupper( mb_substr( $_ynj_bar_name ?: 'U', 0, 1 ) );
    $_wp_uid  = get_current_user_id();
    $_ynj_uid = (int) get_user_meta( $_wp_uid, 'ynj_user_id', true );

    if ( $_ynj_uid && class_exists( 'YNJ_DB' ) ) {
        global $wpdb;

        // Total points
        $_hud_points = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT total_points FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $_ynj_uid
        ) );

        // Favourite mosque
        $fav_id = (int) get_user_meta( $_wp_uid, 'ynj_favourite_mosque_id', true );
        if ( ! $fav_id ) {
            $fav_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT favourite_mosque_id FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $_ynj_uid
            ) );
        }
        if ( $fav_id ) {
            $_hud_mosque = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, name, slug, city FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $fav_id
            ) );
            if ( $_hud_mosque ) $_hud_mosque_slug = $_hud_mosque->slug;
        }

        // League rank (lightweight — just this mosque's rank)
        if ( $_hud_mosque && function_exists( 'ynj_get_league_standings' ) ) {
            $_hud_league = ynj_get_league_standings( (int) $_hud_mosque->id, $_hud_mosque->city ?? null, 7 );
            $_hud_rank = $_hud_league['rank'];
            $_hud_tier = $_hud_league['tier'];
        }

        // Masjid community streak
        if ( $_hud_mosque ) {
            $_ib_hud_t = YNJ_DB::table( 'ibadah_logs' );
            $_ms_dates_hud = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT log_date FROM $_ib_hud_t WHERE mosque_id = %d AND dhikr = 1 ORDER BY log_date DESC LIMIT 120",
                (int) $_hud_mosque->id
            ) );
            $expected = date( 'Y-m-d' );
            foreach ( $_ms_dates_hud as $d ) {
                if ( $d === $expected ) { $_hud_streak++; $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) ); }
                elseif ( $_hud_streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) { $_hud_streak = 1; $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) ); }
                else break;
            }
        }

        // Today's dhikr (for quick popup)
        if ( class_exists( 'YNJ_API_Points' ) ) {
            $adhkar_list = YNJ_API_Points::get_weekly_adhkar();
            $didx = (int) date( 'z' ) % max( 1, count( $adhkar_list ) );
            $_hud_dhikr = $adhkar_list[ $didx ];
            $_hud_dhikr_done = (bool) get_transient( 'ynj_dhikr_' . $_ynj_uid . '_' . date( 'Y-m-d' ) );
        }
    }
}
?>

<?php if ( $_ynj_bar_status === 'guest' ) : ?>
<!-- ── Guest bar ── -->
<div class="ynj-hud ynj-hud--guest">
    <span class="ynj-hud__msg">&#x1F54C; <?php esc_html_e( 'Fall in love with your Masjid & Community', 'yourjannah' ); ?></span>
    <div class="ynj-hud__actions">
        <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="ynj-hud__link"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/register' ) ); ?>" class="ynj-hud__cta">&#x2728; <?php esc_html_e( 'Join Free', 'yourjannah' ); ?></a>
    </div>
</div>
<?php else : ?>
<?php
// ── Masjid XP data for progress bar ──
$_hud_dhikr_total = 0;
$_hud_members = 0;
$_hud_next_tier = null;
$_hud_xp_pct = 0;
if ( $_hud_mosque && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    // Total dhikr for this masjid (all time — the XP)
    $_hud_dhikr_total = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . YNJ_DB::table( 'ibadah_logs' ) . " WHERE mosque_id = %d AND dhikr = 1",
        (int) $_hud_mosque->id
    ) );
    $_hud_members = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . YNJ_DB::table( 'user_subscriptions' ) . " WHERE mosque_id = %d AND status = 'active'",
        (int) $_hud_mosque->id
    ) );
    // Next tier thresholds (based on member count)
    $all_tiers = [
        [ 'name' => 'Rising Star', 'icon' => '&#x1F31F;', 'max' => 25 ],
        [ 'name' => 'Growing',     'icon' => '&#x1F33F;', 'max' => 100 ],
        [ 'name' => 'Established', 'icon' => '&#x1F333;', 'max' => 500 ],
        [ 'name' => 'Flagship',    'icon' => '&#x1F3C6;', 'max' => 999999 ],
    ];
    foreach ( $all_tiers as $i => $t ) {
        if ( $_hud_members <= $t['max'] ) {
            if ( isset( $all_tiers[ $i + 1 ] ) ) {
                $_hud_next_tier = $all_tiers[ $i + 1 ];
                $_hud_xp_pct = min( 100, round( $_hud_members / $t['max'] * 100 ) );
            } else {
                $_hud_xp_pct = 100;
            }
            break;
        }
    }
}
$_hud_mosque_url = $_hud_mosque ? home_url( '/mosque/' . $_hud_mosque_slug ) : home_url( '/' );
$_hud_league_url = $_hud_mosque ? home_url( '/mosque/' . $_hud_mosque_slug . '#mosque-league-table' ) : '#';
?>
<!-- ════ MASJID HUD — Your community's status bar ════ -->
<div class="ynj-hud" id="ynj-hud">

    <!-- Row 1: Masjid identity + XP bar -->
    <div class="ynj-hud__row1">
        <a href="<?php echo esc_url( $_hud_mosque_url ); ?>" class="ynj-hud__masjid">
            <span class="ynj-hud__tier-icon"><?php echo $_hud_mosque ? $_hud_tier['icon'] : '&#x1F54C;'; ?></span>
            <span class="ynj-hud__masjid-name"><?php echo $_hud_mosque ? esc_html( $_hud_mosque->name ) : esc_html__( 'Select Masjid', 'yourjannah' ); ?></span>
            <?php if ( $_hud_mosque ) : ?>
            <span class="ynj-hud__tier-name"><?php echo esc_html( $_hud_tier['name'] ); ?></span>
            <?php if ( $_hud_rank > 0 ) : ?>
            <span class="ynj-hud__rank-badge" id="hud-rank" data-rank="<?php echo (int) $_hud_rank; ?>">#<?php echo (int) $_hud_rank; ?></span>
            <?php endif; ?>
            <?php endif; ?>
        </a>

        <?php if ( $_hud_mosque ) : ?>
        <div class="ynj-hud__xp">
            <div class="ynj-hud__xp-bar">
                <div class="ynj-hud__xp-fill" style="width:<?php echo (int) $_hud_xp_pct; ?>%"></div>
            </div>
            <span class="ynj-hud__xp-text"><?php echo number_format( $_hud_dhikr_total ); ?> <?php esc_html_e( 'dhikr', 'yourjannah' ); ?></span>
            <?php if ( $_hud_next_tier ) : ?>
            <span class="ynj-hud__xp-next"><?php echo $_hud_next_tier['icon']; ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 2: Actions -->
    <div class="ynj-hud__row2">
        <!-- Streak -->
        <div class="ynj-hud__stat<?php echo $_hud_streak >= 7 ? ' ynj-hud__stat--glow' : ''; ?>">
            <span class="ynj-hud__stat-icon">&#x1F525;</span>
            <span class="ynj-hud__stat-num" id="hud-streak"><?php echo (int) $_hud_streak; ?></span>
            <span class="ynj-hud__stat-label"><?php esc_html_e( 'streak', 'yourjannah' ); ?></span>
        </div>

        <!-- Points -->
        <div class="ynj-hud__stat">
            <span class="ynj-hud__stat-icon">&#x2B50;</span>
            <span class="ynj-hud__stat-num" id="hud-pts-num"><?php echo number_format( $_hud_points ); ?></span>
            <span class="ynj-hud__stat-label"><?php esc_html_e( 'pts', 'yourjannah' ); ?></span>
        </div>

        <!-- Members -->
        <?php if ( $_hud_mosque ) : ?>
        <div class="ynj-hud__stat">
            <span class="ynj-hud__stat-icon">&#x1F465;</span>
            <span class="ynj-hud__stat-num"><?php echo (int) $_hud_members; ?></span>
            <span class="ynj-hud__stat-label"><?php esc_html_e( 'members', 'yourjannah' ); ?></span>
        </div>
        <?php endif; ?>

        <!-- Quick Dhikr -->
        <?php if ( $_hud_dhikr ) : ?>
        <button type="button" class="ynj-hud__dhikr<?php echo $_hud_dhikr_done ? ' ynj-hud__dhikr--done' : ''; ?>" id="hud-dhikr-btn" onclick="ynjHudDhikrToggle()">
            <?php if ( $_hud_dhikr_done ) : ?>
                &#x2705; <span><?php esc_html_e( 'Done', 'yourjannah' ); ?></span>
            <?php else : ?>
                &#x1F4FF; <span><?php esc_html_e( 'Say Dhikr', 'yourjannah' ); ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>

        <!-- Profile -->
        <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="ynj-hud__profile" title="<?php echo esc_attr( $_ynj_bar_name ); ?>">
            <span class="ynj-hud__avatar"><?php echo esc_html( $_hud_initial ); ?></span>
        </a>
    </div>
</div>

<!-- ════ Quick Dhikr Popup (opens from HUD) ════ -->
<?php if ( $_hud_dhikr && ! $_hud_dhikr_done ) : ?>
<div class="ynj-hud-popup" id="hud-dhikr-popup" style="display:none;">
    <div class="ynj-hud-popup__card">
        <button type="button" class="ynj-hud-popup__close" onclick="ynjHudDhikrToggle()">&times;</button>
        <div class="ynj-hud-popup__label"><?php
            echo ( $_hud_dhikr['tier'] ?? '' ) === 'legendary'
                ? esc_html__( 'Legendary Remembrance', 'yourjannah' )
                : esc_html__( "Today's Remembrance", 'yourjannah' );
        ?></div>
        <div class="ynj-hud-popup__arabic" dir="rtl"><?php echo esc_html( $_hud_dhikr['arabic'] ); ?></div>
        <div class="ynj-hud-popup__english"><?php echo esc_html( $_hud_dhikr['english'] ); ?></div>
        <div class="ynj-hud-popup__reward">&#x2728; <?php echo esc_html( $_hud_dhikr['reward'] ); ?></div>
        <button type="button" class="ynj-hud-popup__ameen" id="hud-ameen-btn" onclick="ynjHudAmeen(this)">
            <?php echo esc_html( $_hud_dhikr['action_text'] ); ?>
            <span>+<?php echo (int) $_hud_dhikr['points']; ?> pts</span>
        </button>
        <?php if ( $_hud_mosque ) : ?>
        <div class="ynj-hud-popup__masjid">&#x1F54C; <?php printf( esc_html__( 'Elevates %s', 'yourjannah' ), esc_html( $_hud_mosque->name ) ); ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
/* ════════════════════════════════════════════════
   MASJID HUD — Your community's status bar
   Responsive: Desktop (2-row inline) → Mobile (stacked)
   ════════════════════════════════════════════════ */
.ynj-hud{background:linear-gradient(135deg,#0a1628 0%,#132742 100%);color:#fff;z-index:102;position:sticky;top:0;padding:6px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.ynj-hud--guest{display:flex;flex-wrap:nowrap;justify-content:space-between;background:#111827;border-bottom:2px solid #00ADEF;padding:8px 16px;}
.ynj-hud__msg{white-space:nowrap;font-size:12px;font-weight:600;}
.ynj-hud__actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.ynj-hud__link{color:rgba(255,255,255,.8);text-decoration:none;font-size:12px;}
.ynj-hud__cta{padding:5px 14px;border-radius:8px;background:linear-gradient(135deg,#287e61,#1a5c43);font-weight:700;font-size:12px;text-decoration:none;color:#fff !important;white-space:nowrap;}

/* Layout: Row 1 (masjid + xp) | Row 2 (stats + actions) */
.ynj-hud__row1{display:flex;align-items:center;gap:8px;flex:1;min-width:0;}
.ynj-hud__row2{display:flex;align-items:center;gap:6px;flex-shrink:0;}

/* ── Masjid Identity ── */
.ynj-hud__masjid{display:flex;align-items:center;gap:5px;padding:4px 10px;background:rgba(255,255,255,.07);border-radius:10px;text-decoration:none;color:#fff;flex-shrink:0;transition:background .2s;max-width:280px;}
.ynj-hud__masjid:hover{background:rgba(255,255,255,.12);}
.ynj-hud__tier-icon{font-size:16px;flex-shrink:0;}
.ynj-hud__masjid-name{font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ynj-hud__tier-name{font-size:10px;color:rgba(255,255,255,.45);white-space:nowrap;flex-shrink:0;}
.ynj-hud__rank-badge{font-size:11px;font-weight:900;color:#a78bfa;background:rgba(124,58,237,.2);padding:1px 6px;border-radius:6px;flex-shrink:0;}
.ynj-hud__rank-badge--up{animation:ynj-hud-rankup 1.2s ease-out;}
@keyframes ynj-hud-rankup{0%{transform:scale(1);}30%{transform:scale(1.5);background:rgba(245,158,11,.4);}100%{transform:scale(1);}}

/* ── XP Progress Bar ── */
.ynj-hud__xp{display:flex;align-items:center;gap:6px;flex:1;min-width:80px;max-width:200px;}
.ynj-hud__xp-bar{flex:1;height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;min-width:40px;}
.ynj-hud__xp-fill{height:100%;background:linear-gradient(90deg,#287e61,#34d399);border-radius:3px;transition:width 1s ease-out;box-shadow:0 0 6px rgba(40,126,97,.4);}
.ynj-hud__xp-text{font-size:10px;font-weight:700;color:#34d399;white-space:nowrap;}
.ynj-hud__xp-next{font-size:12px;flex-shrink:0;opacity:.5;}

/* ── Stats (streak, points, members) ── */
.ynj-hud__stat{display:flex;align-items:center;gap:3px;padding:3px 7px;border-radius:8px;background:rgba(255,255,255,.06);flex-shrink:0;}
.ynj-hud__stat--glow{background:rgba(245,158,11,.1);animation:ynj-hud-flame 2s ease-in-out infinite;}
@keyframes ynj-hud-flame{0%,100%{box-shadow:none;}50%{box-shadow:0 0 10px rgba(245,158,11,.25);}}
.ynj-hud__stat-icon{font-size:12px;flex-shrink:0;}
.ynj-hud__stat-num{font-size:12px;font-weight:800;color:#fbbf24;}
.ynj-hud__stat-label{font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.3px;}

/* ── Quick Dhikr Button ── */
.ynj-hud__dhikr{display:flex;align-items:center;gap:4px;padding:5px 12px;background:linear-gradient(135deg,#287e61,#1a5c43);border:none;border-radius:10px;color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;flex-shrink:0;transition:all .2s;box-shadow:0 2px 10px rgba(40,126,97,.3);}
.ynj-hud__dhikr:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(40,126,97,.4);}
.ynj-hud__dhikr:active{transform:scale(.95);}
.ynj-hud__dhikr--done{background:rgba(40,126,97,.15);box-shadow:none;cursor:default;}
.ynj-hud__dhikr--done:hover{transform:none;}

/* ── Profile Link ── */
.ynj-hud__profile{text-decoration:none;flex-shrink:0;}
.ynj-hud__avatar{display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.12);color:#fff;font-size:12px;font-weight:800;transition:background .2s;}
.ynj-hud__profile:hover .ynj-hud__avatar{background:rgba(255,255,255,.22);}

/* ── Responsive ── */
/* Tablet (768px) */
@media(max-width:768px){
    .ynj-hud{padding:5px 10px;gap:6px;}
    .ynj-hud__masjid{max-width:200px;padding:3px 8px;}
    .ynj-hud__masjid-name{font-size:11px;}
    .ynj-hud__tier-name{display:none;}
    .ynj-hud__xp{max-width:140px;}
    .ynj-hud__stat-label{display:none;}
}
/* Mobile (480px) */
@media(max-width:480px){
    .ynj-hud{padding:4px 8px;gap:4px;}
    .ynj-hud__row1{flex:1 1 100%;order:1;}
    .ynj-hud__row2{flex:1 1 100%;order:2;justify-content:space-between;}
    .ynj-hud__masjid{max-width:none;flex:1;}
    .ynj-hud__masjid-name{font-size:11px;}
    .ynj-hud__xp{flex:0 0 auto;min-width:60px;max-width:90px;}
    .ynj-hud__xp-text{font-size:9px;}
    .ynj-hud__stat{padding:2px 5px;}
    .ynj-hud__stat-num{font-size:11px;}
    .ynj-hud__dhikr{padding:4px 8px;font-size:11px;}
    .ynj-hud__avatar{width:24px;height:24px;font-size:10px;}
}

/* ── Quick Dhikr Popup ── */
.ynj-hud-popup{position:fixed;top:0;left:0;right:0;bottom:0;z-index:10003;display:flex;align-items:flex-start;justify-content:center;padding-top:60px;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);}
.ynj-hud-popup__card{background:#fff;border-radius:20px;padding:28px 24px;max-width:400px;width:calc(100% - 32px);box-shadow:0 20px 60px rgba(0,0,0,.3);animation:ynj-popup-in .3s ease-out;position:relative;}
@keyframes ynj-popup-in{from{opacity:0;transform:translateY(-20px) scale(.95);}to{opacity:1;transform:translateY(0) scale(1);}}
.ynj-hud-popup__close{position:absolute;top:12px;right:14px;background:none;border:none;font-size:24px;color:#999;cursor:pointer;padding:4px;line-height:1;}
.ynj-hud-popup__label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#92400e;margin-bottom:14px;text-align:center;}
.ynj-hud-popup__arabic{font-size:22px;line-height:1.8;color:#1a1a1a;margin-bottom:10px;text-align:center;font-family:'Amiri','Traditional Arabic',serif;}
.ynj-hud-popup__english{font-size:14px;color:#4a3728;line-height:1.5;margin-bottom:12px;text-align:center;font-style:italic;}
.ynj-hud-popup__reward{font-size:12px;color:#78350f;background:rgba(120,53,15,.05);border-radius:10px;padding:10px 14px;margin-bottom:16px;text-align:center;line-height:1.4;}
.ynj-hud-popup__ameen{display:block;width:100%;padding:18px;border:none;border-radius:14px;background:linear-gradient(135deg,#287e61,#1a5c43);color:#fff;font-size:20px;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 4px 20px rgba(40,126,97,.3);transition:all .2s;}
.ynj-hud-popup__ameen:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(40,126,97,.4);}
.ynj-hud-popup__ameen:active{transform:scale(.97);}
.ynj-hud-popup__ameen span{display:block;font-size:13px;font-weight:600;color:rgba(255,255,255,.7);margin-top:3px;}
.ynj-hud-popup__masjid{font-size:12px;color:#6b8fa3;text-align:center;margin-top:12px;}
@media(max-width:480px){
    .ynj-hud-popup{padding-top:40px;}
    .ynj-hud-popup__card{padding:20px 16px;}
    .ynj-hud-popup__arabic{font-size:18px;}
    .ynj-hud-popup__ameen{font-size:17px;padding:16px;}
}
</style>

<script>
(function(){
    /* ── Quick Dhikr popup toggle ── */
    window.ynjHudDhikrToggle = function() {
        var popup = document.getElementById('hud-dhikr-popup');
        if (!popup) return;
        popup.style.display = popup.style.display === 'none' ? 'flex' : 'none';
    };

    /* ── Confetti helper (duplicated for header — no dependency on profile page) ── */
    function hudConfetti(origin) {
        var rect = origin ? origin.getBoundingClientRect() : { left: innerWidth/2, top: innerHeight/2, width: 0 };
        var cx = rect.left + rect.width/2, cy = rect.top;
        var colors = ['#f59e0b','#287e61','#7c3aed','#00ADEF','#ef4444','#fbbf24','#34d399'];
        var emojis = ['\u2728','\u2B50','\uD83C\uDF1F','\uD83D\uDCAB','\u2764\uFE0F','\uD83D\uDE4F','\uD83C\uDF89'];
        for (var i = 0; i < 20; i++) {
            var p = document.createElement('div');
            var isE = Math.random() > .5;
            var angle = (Math.PI*2*i/20)+(Math.random()-.5);
            var vel = 50+Math.random()*70;
            var dx = Math.cos(angle)*vel, dy = Math.sin(angle)*vel - 30;
            p.textContent = isE ? emojis[Math.floor(Math.random()*emojis.length)] : '';
            p.style.cssText = 'position:fixed;left:'+cx+'px;top:'+cy+'px;z-index:10005;pointer-events:none;font-size:'+(isE?'14':'7')+'px;'
                + (isE ? '' : 'width:7px;height:7px;border-radius:50%;background:'+colors[Math.floor(Math.random()*colors.length)]+';')
                + 'transition:all '+(0.5+Math.random()*0.5)+'s cubic-bezier(.25,.46,.45,.94);opacity:1;';
            document.body.appendChild(p);
            requestAnimationFrame(function(el,x,y){return function(){el.style.transform='translate('+x+'px,'+y+'px) rotate('+(Math.random()*720-360)+'deg)';el.style.opacity='0';};}(p,dx,dy));
            setTimeout(function(el){return function(){el.remove();};}(p),1300);
        }
    }

    function hudFloatPts(text, origin) {
        var rect = origin ? origin.getBoundingClientRect() : {left:innerWidth/2,top:innerHeight/2,width:0};
        var el = document.createElement('div');
        el.textContent = text;
        el.style.cssText = 'position:fixed;left:'+(rect.left+rect.width/2)+'px;top:'+rect.top+'px;z-index:10006;pointer-events:none;font-size:22px;font-weight:900;color:#f59e0b;text-shadow:0 2px 8px rgba(0,0,0,.15);transform:translateX(-50%);';
        document.body.appendChild(el);
        requestAnimationFrame(function(){el.style.transition='all 1.2s ease-out';el.style.transform='translateX(-50%) translateY(-60px)';el.style.opacity='0';});
        setTimeout(function(){el.remove();},1400);
    }

    function hudAnimateCounter(el, newVal) {
        if (!el) return;
        var old = parseInt(el.textContent.replace(/,/g,''))||0;
        if (old===newVal) return;
        var diff=newVal-old, steps=Math.min(25,Math.abs(diff)), sv=diff/steps, cur=old, i=0;
        el.style.transition='transform .3s, color .3s';
        el.style.transform='scale(1.3)'; el.style.color='#fbbf24';
        var iv=setInterval(function(){i++;cur=i>=steps?newVal:Math.round(old+sv*i);el.textContent=cur.toLocaleString();if(i>=steps)clearInterval(iv);},30);
        setTimeout(function(){el.style.transform='';},400);
    }

    function hudToast(text, bg) {
        var t=document.createElement('div');
        t.style.cssText='position:fixed;bottom:80px;left:50%;z-index:10004;max-width:90%;padding:14px 24px;border-radius:14px;font-size:15px;font-weight:800;color:#fff;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.25);background:'+bg+';transform:translateX(-50%) translateY(20px) scale(.9);opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);';
        t.textContent=text;document.body.appendChild(t);
        requestAnimationFrame(function(){requestAnimationFrame(function(){t.style.transform='translateX(-50%) translateY(0) scale(1)';t.style.opacity='1';});});
        setTimeout(function(){t.style.transform='translateX(-50%) translateY(20px) scale(.9)';t.style.opacity='0';setTimeout(function(){t.remove();},400);},4000);
    }

    /* ── Say Ameen from HUD popup ── */
    window.ynjHudAmeen = function(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;animation:ynj-hud-flame .6s infinite;">\uD83D\uDE4F</span>';
        if (navigator.vibrate) navigator.vibrate(50);

        var nonce = typeof ynjData !== 'undefined' ? ynjData.nonce : '';
        fetch('/wp-json/ynj/v1/ibadah/dhikr', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            credentials: 'same-origin',
            body: JSON.stringify({})
        }).then(function(r){return r.json();}).then(function(d){
            if (d.ok && d.points > 0) {
                // 1. Confetti from the HUD dhikr button
                var hudBtn = document.getElementById('hud-dhikr-btn');
                hudConfetti(btn);
                setTimeout(function(){ hudConfetti(hudBtn || btn); }, 300);

                // 2. Float points
                hudFloatPts('+' + d.points + ' pts', btn);

                // 3. Animate HUD points counter
                hudAnimateCounter(document.getElementById('hud-pts-num'), d.total);

                // 4. Also update profile page hero if it exists
                var heroPts = document.getElementById('hero-pts');
                if (heroPts) hudAnimateCounter(heroPts, d.total);

                // 5. Replace popup content with success
                setTimeout(function(){
                    var card = btn.closest('.ynj-hud-popup__card');
                    if (card) {
                        card.innerHTML = '<div style="text-align:center;padding:20px 0;animation:ynj-popup-in .4s ease-out;">'
                            + '<div style="font-size:48px;margin-bottom:8px;">\u2705</div>'
                            + '<div style="font-size:18px;font-weight:800;color:#166534;margin-bottom:4px;"><?php echo esc_js( __( 'May Allah accept', 'yourjannah' ) ); ?></div>'
                            + '<div style="font-size:28px;font-weight:900;color:#f59e0b;">+' + d.points + ' pts</div>'
                            + '<?php if ( $_hud_mosque ) : ?><div style="font-size:12px;color:#287e61;margin-top:8px;"><?php echo esc_js( $_hud_mosque->name ); ?> elevated \u2728</div><?php endif; ?>'
                            + '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #eee;">'
                            + '<div style="font-size:12px;font-weight:700;color:#166534;margin-bottom:8px;">\uD83D\uDC9A <?php echo esc_js( __( 'Share the blessing', 'yourjannah' ) ); ?></div>'
                            + '<div style="display:flex;gap:8px;justify-content:center;">'
                            + '<a href="<?php echo esc_url( isset( $wa_url ) ? $wa_url : 'https://wa.me/?text=' . rawurlencode( 'Assalamu Alaikum! Say La ilaha illallah and elevate our masjid on YourJannah! ' . home_url( '/mosque/' . $_hud_mosque_slug ) ) ); ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;padding:10px 16px;background:#25D366;color:#fff;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;">\uD83D\uDCF1 WhatsApp</a>'
                            + '<button onclick="navigator.clipboard&&navigator.clipboard.writeText(\'<?php echo esc_js( home_url( '/mosque/' . $_hud_mosque_slug ) ); ?>\');this.textContent=\'\u2705 Copied!\'" style="padding:10px 16px;background:#0a1628;color:#fff;border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;">\uD83D\uDCCB Copy</button>'
                            + '</div></div></div>';
                    }
                    // Mark HUD button as done
                    if (hudBtn) { hudBtn.innerHTML = '\u2705'; hudBtn.classList.add('ynj-hud__dhikr--done'); }
                }, 700);

                // 6. Toast
                hudToast('\u2728 <?php echo esc_js( __( 'Your remembrance shakes the heavens!', 'yourjannah' ) ); ?>', 'linear-gradient(135deg,#287e61,#1a5c43)');
                if (navigator.vibrate) navigator.vibrate([50,100,50]);

                // 7. Auto-close popup after 8s
                setTimeout(function(){ var p = document.getElementById('hud-dhikr-popup'); if (p) p.style.display='none'; }, 8000);
            }
        }).catch(function(){ btn.disabled=false; btn.innerHTML='<?php echo esc_js( $_hud_dhikr ? $_hud_dhikr['action_text'] : 'Ameen' ); ?><span>+<?php echo (int) ( $_hud_dhikr ? $_hud_dhikr['points'] : 0 ); ?> pts</span>'; });
    };

    /* ── Rank-up detection (compare localStorage) ── */
    var storedRank = parseInt(localStorage.getItem('ynj_hud_rank') || '0');
    var currentRank = <?php echo (int) $_hud_rank; ?>;
    if (storedRank > 0 && currentRank > 0 && currentRank < storedRank) {
        // Rank improved! Celebrate!
        var rankEl = document.getElementById('hud-rank');
        if (rankEl) {
            rankEl.classList.add('ynj-hud__rank--up');
            hudConfetti(rankEl);
            hudToast('\uD83C\uDF89 <?php echo esc_js( $_hud_mosque ? $_hud_mosque->name : '' ); ?> <?php echo esc_js( __( 'ranked up!', 'yourjannah' ) ); ?> #' + currentRank, 'linear-gradient(135deg,#7c3aed,#5b21b6)');
        }
    }
    if (currentRank > 0) localStorage.setItem('ynj_hud_rank', currentRank);

    /* ── Close popup on backdrop click ── */
    var popupEl = document.getElementById('hud-dhikr-popup');
    if (popupEl) {
        popupEl.addEventListener('click', function(e) {
            if (e.target === popupEl) popupEl.style.display = 'none';
        });
    }
})();
</script>
<?php endif; ?>

<?php
// Email verification reminder banner
if ( is_user_logged_in() ) {
    $ynj_uid_verify = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
    if ( $ynj_uid_verify ) {
        global $wpdb;
        $verified = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT email_verified FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid_verify
        ) );
        if ( ! $verified ) : ?>
        <div style="background:#fef3c7;color:#92400e;padding:6px 16px;font-size:12px;text-align:center;">
            Please verify your email address. <a href="#" onclick="ynjResendVerify();return false;" style="color:#92400e;font-weight:700;text-decoration:underline;">Resend verification email</a>
        </div>
        <?php endif;
    }
}
?>

<header class="ynj-header">
    <div class="ynj-header__inner">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ynj-logo">
            <img src="<?php echo esc_url( YNJ_THEME_URI . '/assets/icons/logo2.png' ); ?>" alt="<?php bloginfo( 'name' ); ?>" style="height:36px;width:auto;">
        </a>

        <?php if ( has_nav_menu( 'primary' ) ) : ?>
            <?php wp_nav_menu( [
                'theme_location' => 'primary',
                'container'      => 'nav',
                'container_class' => 'ynj-header__nav',
                'container_id'   => 'desktop-nav',
                'menu_class'     => '',
                'depth'          => 1,
                'fallback_cb'    => false,
            ] ); ?>
        <?php endif; ?>

        <div class="ynj-header__right">
            <?php
            $mosque_slug = ynj_mosque_slug();
            $mosque = ynj_get_mosque( $mosque_slug );
            $mosque_name = $mosque ? $mosque->name : '';

            // Get nearby mosques (single query, used for pre-populating modal)
            $nearby_mosques = [];
            if ( class_exists( 'YNJ_DB' ) && $mosque && $mosque->latitude ) {
                global $wpdb;
                $mt = YNJ_DB::table( 'mosques' );
                $nearby_mosques = $wpdb->get_results( $wpdb->prepare(
                    "SELECT slug, name, city, postcode,
                            ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
                     FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
                     ORDER BY distance ASC LIMIT 5",
                    $mosque->latitude, $mosque->longitude, $mosque->latitude
                ) ) ?: [];
            }
            ?>

            <?php if ( is_user_logged_in() ) : ?>
            <!-- Notification bell (logged-in users only) -->
            <style>
            .ynj-notif-bell{position:relative;display:inline-flex;align-items:center;margin-right:8px}
            .ynj-notif-bell__btn{background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;color:#00ADEF;position:relative;display:flex;align-items:center;justify-content:center;transition:background .2s}
            .ynj-notif-bell__btn:hover{background:rgba(0,173,239,.1)}
            .ynj-notif-badge{position:absolute;top:0;right:0;background:#e53e3e;color:#fff;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;border:2px solid #fff}
            .ynj-notif-panel{display:none;position:absolute;right:0;top:calc(100% + 6px);width:360px;max-height:420px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.15);z-index:9999;overflow:hidden}
            .ynj-notif-panel--open{display:block}
            .ynj-notif-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px 10px;border-bottom:1px solid #eee}
            .ynj-notif-header strong{font-size:16px;color:#1a1a1a}
            .ynj-notif-header a{font-size:12px;color:#0ea5e9;cursor:pointer;text-decoration:none}
            .ynj-notif-header a:hover{text-decoration:underline}
            .ynj-notif-list{overflow-y:auto;max-height:360px}
            .ynj-notif-item{display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid #f3f3f3;cursor:pointer;text-decoration:none;color:inherit;transition:background .15s}
            .ynj-notif-item:hover{background:#f7f7f7}
            .ynj-notif-item--unread{background:#f0f7ff}
            .ynj-notif-item--unread:hover{background:#e6f0fa}
            .ynj-notif-item__dot{flex-shrink:0;width:8px;height:8px;border-radius:50%;background:#0ea5e9;margin-top:6px}
            .ynj-notif-item__dot--read{background:transparent}
            .ynj-notif-item__body{flex:1;min-width:0}
            .ynj-notif-item__mosque{font-size:11px;color:#888;margin-bottom:2px}
            .ynj-notif-item__title{font-size:13px;font-weight:600;color:#1a1a1a;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .ynj-notif-item__text{font-size:12px;color:#555;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
            .ynj-notif-item__time{font-size:11px;color:#aaa;margin-top:3px}
            .ynj-notif-empty{padding:40px 16px;text-align:center;color:#999;font-size:13px}
            @media(max-width:600px){.ynj-notif-panel{width:280px;right:-40px}}
            </style>
            <div class="ynj-notif-bell" id="ynj-notif-bell">
                <button type="button" class="ynj-notif-bell__btn" id="ynj-notif-toggle" aria-label="Notifications">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span class="ynj-notif-badge" id="ynj-notif-badge" style="display:none">0</span>
                </button>
                <div class="ynj-notif-panel" id="ynj-notif-panel">
                    <div class="ynj-notif-header">
                        <strong>Notifications</strong>
                        <a id="ynj-notif-mark-all">Mark all read</a>
                    </div>
                    <div class="ynj-notif-list" id="ynj-notif-list">
                        <div class="ynj-notif-empty">Loading...</div>
                    </div>
                    <div style="padding:10px 16px;border-top:1px solid #eee;text-align:center;">
                        <a href="<?php echo esc_url( home_url( '/profile#interests' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#00ADEF;text-decoration:none;">⚙️ Set Interests &amp; Radius</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- (My Ibadah button now in HUD top bar — dhikr button + streak) -->

            <!-- Mosque selector pill — opens JS modal -->
            <button type="button" class="ynj-mosque-pill" id="mosque-selector" onclick="window.ynjOpenMosqueModal&&window.ynjOpenMosqueModal()">
                <span class="ynj-mosque-pill__gps" id="gps-btn" title="<?php esc_attr_e( 'Use GPS', 'yourjannah' ); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                </span>
                <span class="ynj-mosque-pill__name" id="mosque-name"><?php echo esc_html( $mosque_name ?: __( 'Select Mosque', 'yourjannah' ) ); ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="opacity:.6;flex-shrink:0;margin-right:8px;"><path d="M6 9l6 6 6-6"/></svg>
            </button>
        </div>
    </div>
</header>

<!-- Mosque selector modal (JS-driven) -->
<div class="ynj-mosque-modal" id="ynj-mosque-modal" style="display:none">
    <div class="ynj-mosque-modal__overlay"></div>
    <div class="ynj-mosque-modal__box">
        <button type="button" class="ynj-mosque-modal__close">&times;</button>
        <h3 class="ynj-mosque-modal__title">🕌 <?php esc_html_e( 'Find Your Mosque', 'yourjannah' ); ?></h3>
        <p class="ynj-mosque-modal__subtitle"><?php esc_html_e( 'Select a mosque near you or search by name.', 'yourjannah' ); ?></p>

        <div class="ynj-mosque-modal__search">
            <input type="text" id="ynj-mosque-search" placeholder="<?php esc_attr_e( 'Search by name, city, postcode...', 'yourjannah' ); ?>" autocomplete="off">
        </div>

        <button type="button" class="ynj-mosque-modal__gps" id="ynj-mosque-gps">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
            <span id="ynj-mosque-gps-text"><?php esc_html_e( 'Use my location', 'yourjannah' ); ?></span>
        </button>

        <div class="ynj-mosque-modal__list" id="ynj-mosque-list">
            <!-- Populated by JS -->
        </div>

        <a href="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" class="ynj-mosque-modal__browse"><?php esc_html_e( 'Browse All Mosques →', 'yourjannah' ); ?></a>
    </div>
</div>
<script>
window.ynjNearbyMosques = <?php echo json_encode( array_map( function( $m ) {
    return [
        'slug'     => $m->slug,
        'name'     => $m->name,
        'city'     => $m->city ?? '',
        'postcode' => $m->postcode ?? '',
        'distance' => isset( $m->distance ) ? round( (float) $m->distance, 1 ) : null,
    ];
}, $nearby_mosques ) ); ?>;

/* Mosque modal — inline for guaranteed reliability (no SW cache dependency) */
(function(){
    var modal = document.getElementById('ynj-mosque-modal');
    if (!modal) return;
    var overlay  = modal.querySelector('.ynj-mosque-modal__overlay');
    var closeBtn = modal.querySelector('.ynj-mosque-modal__close');
    var searchIn = document.getElementById('ynj-mosque-search');
    var gpsBtn   = document.getElementById('ynj-mosque-gps');
    var gpsTxt   = document.getElementById('ynj-mosque-gps-text');
    var listEl   = document.getElementById('ynj-mosque-list');
    var searchTimer = null;
    var gpsTriggered = false;
    var restUrl  = '<?php echo esc_url_raw( rest_url( 'ynj/v1/' ) ); ?>';

    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (searchIn) searchIn.value = '';
        var pre = window.ynjNearbyMosques || [];
        if (pre.length) { renderList(pre, true); }
        else { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Tap "Use my location" to find nearby mosques</div>'; }
        if (!gpsTriggered && !pre.length) triggerGps();
        setTimeout(function(){ if (searchIn) searchIn.focus(); }, 200);
    }
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    function selectMosque(slug, name) {
        localStorage.setItem('ynj_mosque_slug', slug);
        localStorage.setItem('ynj_mosque_name', name);
        localStorage.removeItem('ynj_cache_date');
        localStorage.removeItem('ynj_cached_prayers');
        localStorage.removeItem('ynj_cached_feed');
        window.location.href = '/mosque/' + slug;
    }
    function renderList(mosques, label) {
        if (!mosques || !mosques.length) { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found.</div>'; return; }
        var h = label ? '<p class="ynj-mosque-modal__label">\uD83D\uDCCD Nearby</p>' : '';
        mosques.forEach(function(m){
            var meta = [m.city, m.postcode].filter(Boolean).join(', ');
            if (m.distance != null) meta += (meta ? ' \u00B7 ' : '') + m.distance + 'km';
            h += '<button type="button" class="ynj-mosque-modal__item" data-s="'+(m.slug||'')+'" data-n="'+(m.name||'').replace(/"/g,'&quot;')+'">' +
                '<span class="ynj-mosque-modal__item-name">'+(m.name||'')+'</span>' +
                '<span class="ynj-mosque-modal__item-meta">'+meta+'</span></button>';
        });
        listEl.innerHTML = h;
        listEl.querySelectorAll('.ynj-mosque-modal__item').forEach(function(b){
            b.addEventListener('click', function(){ selectMosque(this.dataset.s, this.dataset.n); });
        });
    }
    function triggerGps() {
        if (!('geolocation' in navigator)) { if (gpsTxt) gpsTxt.textContent = 'GPS not available'; return; }
        gpsTriggered = true;
        if (gpsBtn) gpsBtn.classList.add('ynj-mosque-modal__gps--loading');
        if (gpsTxt) gpsTxt.textContent = 'Locating...';
        listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Finding nearby mosques...</div>';
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                if (gpsBtn) gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                if (gpsTxt) gpsTxt.textContent = 'Use my location';
                fetch(restUrl + 'mosques/nearest?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude + '&limit=5')
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.ok && d.mosques && d.mosques.length) {
                            renderList(d.mosques.map(function(m){ return { slug:m.slug, name:m.name, city:m.city||'', postcode:m.postcode||'', distance:m.distance?parseFloat(m.distance).toFixed(1):null }; }), true);
                        } else { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found nearby. Try searching.</div>'; }
                    })
                    .catch(function(){ listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Could not load mosques. Try searching.</div>'; });
            },
            function() {
                if (gpsBtn) gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                if (gpsTxt) gpsTxt.textContent = 'Location denied';
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Location denied. Search by name instead.</div>';
            },
            { timeout: 8000, maximumAge: 300000 }
        );
    }

    /* Global function — called by onclick on pill button */
    window.ynjOpenMosqueModal = openModal;

    /* Close handlers */
    if (overlay) overlay.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (gpsBtn) gpsBtn.addEventListener('click', triggerGps);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.style.display !== 'none') closeModal(); });

    /* Search */
    if (searchIn) {
        searchIn.addEventListener('input', function(){
            var q = this.value.trim();
            if (q.length < 2) { var pre = window.ynjNearbyMosques || []; if (pre.length) renderList(pre, true); else listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Type to search...</div>'; return; }
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function(){
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Searching...</div>';
                fetch(restUrl + 'mosques/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r){ return r.json(); })
                    .then(function(d){ renderList((d.mosques||[]).map(function(m){ return { slug:m.slug, name:m.name, city:m.city||'', postcode:m.postcode||'', distance:m.distance?parseFloat(m.distance).toFixed(1):null }; }), false); })
                    .catch(function(){ listEl.innerHTML = '<div class="ynj-mosque-modal__empty" style="color:#dc2626;">Search failed.</div>'; });
            }, 300);
        });
    }
})();
</script>

<?php if ( is_user_logged_in() ) : ?>
<script>
/* Notification bell — inline for guaranteed reliability */
(function(){
    // Use wpApiSettings nonce if available (WordPress enqueues this), fallback to inline
    var nonce = (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) ? wpApiSettings.nonce : '<?php echo wp_create_nonce( "wp_rest" ); ?>';
    var apiBase = '<?php echo esc_url_raw( rest_url( "ynj/v1/auth/" ) ); ?>';
    var badge = document.getElementById('ynj-notif-badge');
    var panel = document.getElementById('ynj-notif-panel');
    var toggle = document.getElementById('ynj-notif-toggle');
    var list = document.getElementById('ynj-notif-list');
    var markAllBtn = document.getElementById('ynj-notif-mark-all');
    var isOpen = false;
    var pollTimer = null;

    function apiFetch(path, opts) {
        opts = opts || {};
        var url = apiBase + path;
        var init = {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': nonce }
        };
        if (opts.body) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        return fetch(url, init).then(function(r){ return r.json(); });
    }

    function timeAgo(dateStr) {
        var now = Date.now();
        var then = new Date(dateStr.replace(/ /, 'T') + 'Z').getTime();
        var diff = Math.floor((now - then) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return new Date(then).toLocaleDateString();
    }

    function updateBadge(count) {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function fetchCount() {
        apiFetch('notifications/count').then(function(d) {
            if (d.ok) updateBadge(d.count);
        }).catch(function(){});
    }

    function renderNotifications(notifications) {
        if (!notifications || !notifications.length) {
            list.innerHTML = '<div class="ynj-notif-empty">No notifications yet.</div>';
            return;
        }
        var html = '';
        var shown = notifications.slice(0, 20);
        shown.forEach(function(n) {
            var unread = !n.is_read;
            html += '<a class="ynj-notif-item' + (unread ? ' ynj-notif-item--unread' : '') + '" '
                + 'href="' + (n.url || '#') + '" '
                + 'data-nid="' + n.id + '" '
                + 'onclick="window._ynjMarkRead(' + n.id + ')">'
                + '<span class="ynj-notif-item__dot' + (unread ? '' : ' ynj-notif-item__dot--read') + '"></span>'
                + '<span class="ynj-notif-item__body">'
                + (n.mosque_name ? '<span class="ynj-notif-item__mosque">' + n.mosque_name + '</span>' : '')
                + '<span class="ynj-notif-item__title">' + (n.title || '') + '</span>'
                + '<span class="ynj-notif-item__text">' + (n.body || '') + '</span>'
                + '<span class="ynj-notif-item__time">' + timeAgo(n.created_at) + '</span>'
                + '</span></a>';
        });
        list.innerHTML = html;
    }

    function fetchNotifications() {
        list.innerHTML = '<div class="ynj-notif-empty">Loading...</div>';
        apiFetch('notifications').then(function(d) {
            if (d.ok) {
                renderNotifications(d.notifications);
                updateBadge(d.unread_count);
            }
        }).catch(function() {
            list.innerHTML = '<div class="ynj-notif-empty" style="color:#dc2626;">Could not load notifications.</div>';
        });
    }

    function togglePanel() {
        isOpen = !isOpen;
        if (isOpen) {
            panel.classList.add('ynj-notif-panel--open');
            fetchNotifications();
        } else {
            panel.classList.remove('ynj-notif-panel--open');
        }
    }

    function markAllRead() {
        apiFetch('notifications/read', { method: 'POST', body: {} }).then(function(d) {
            if (d.ok) {
                updateBadge(0);
                // Update UI — remove unread styling
                var items = list.querySelectorAll('.ynj-notif-item--unread');
                items.forEach(function(el) {
                    el.classList.remove('ynj-notif-item--unread');
                    var dot = el.querySelector('.ynj-notif-item__dot');
                    if (dot) dot.classList.add('ynj-notif-item__dot--read');
                });
            }
        });
    }

    window._ynjMarkRead = function(nid) {
        apiFetch('notifications/read', { method: 'POST', body: { notification_id: nid } }).then(function(d) {
            if (d.ok) fetchCount();
        }).catch(function(){});
    };

    // Bind events
    if (toggle) toggle.addEventListener('click', function(e) { e.stopPropagation(); togglePanel(); });
    if (markAllBtn) markAllBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); markAllRead(); });

    // Close panel on outside click
    document.addEventListener('click', function(e) {
        if (isOpen && panel && !panel.contains(e.target) && toggle && !toggle.contains(e.target)) {
            isOpen = false;
            panel.classList.remove('ynj-notif-panel--open');
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isOpen) {
            isOpen = false;
            panel.classList.remove('ynj-notif-panel--open');
        }
    });

    // Initial count fetch + polling every 60s
    fetchCount();
    pollTimer = setInterval(fetchCount, 60000);
})();

/* Resend email verification */
window.ynjResendVerify = function() {
    var nonce = (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) ? wpApiSettings.nonce : '<?php echo wp_create_nonce( "wp_rest" ); ?>';
    fetch('<?php echo esc_url_raw( rest_url( "ynj/v1/auth/resend-verify" ) ); ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.ok) {
            alert('Verification email sent! Please check your inbox.');
        } else {
            alert(d.message || 'Could not send verification email. Please try again later.');
        }
    })
    .catch(function(){ alert('Could not send verification email. Please try again later.'); });
};
</script>
<?php endif; ?>
