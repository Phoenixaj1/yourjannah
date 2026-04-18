<?php
/**
 * Shared Community Feature Cards
 *
 * Included by both front-page.php and page-mosque.php.
 * Requires: $mosque (object), $mosque_name (string), $slug (string) to be set.
 *
 * Renders: Score bar, section nav, ibadah tracker, community stats,
 *          congregation points, who's here, h2h challenge, impact score,
 *          league table, badges, fajr counter, milestones, dua wall, gratitude wall.
 *
 * @package YourJannah
 * @since   3.10.0
 */

if ( ! defined( 'ABSPATH' ) || ! $mosque ) return;

$_comm_mosque_id   = (int) $mosque->id;
$_comm_mosque_name = $mosque_name ?? $mosque->name;
$_comm_slug        = $slug ?? $mosque->slug;
$_comm_can_edit    = isset( $_ynj_can_edit ) ? $_ynj_can_edit : false;
?>

<!-- ═══ COMMUNITY SCORE BAR — Our Masjid Points ═══ -->
<?php if ( function_exists( 'ynj_get_league_standings' ) ) :
    $_ldata = ynj_get_league_standings( $_comm_mosque_id, $mosque->city ?? null, 7 );
    $_cp_bar = function_exists( 'ynj_get_congregation_points' ) ? ynj_get_congregation_points( $_comm_mosque_id, 7 ) : null;
    $_fajr_bar = function_exists( 'ynj_fajr_counter' ) ? ynj_fajr_counter( $_comm_mosque_id ) : 0;
?>
<a href="#mosque-league-table" style="display:block;text-decoration:none;background:linear-gradient(135deg,#78350f,#92400e);border-radius:14px;padding:14px 16px;margin-bottom:10px;color:#fff;">
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:24px;"><?php echo $_ldata['tier']['icon']; ?></span>
            <div>
                <div style="font-size:13px;font-weight:800;"><?php esc_html_e( 'Our Masjid', 'yourjannah' ); ?></div>
                <div style="font-size:11px;color:rgba(255,255,255,.6);"><?php echo esc_html( $_ldata['tier']['name'] ); ?> <?php esc_html_e( 'League', 'yourjannah' ); ?></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;font-size:12px;">
            <?php if ( $_cp_bar && $_cp_bar['prayers']['total'] > 0 ) : ?>
            <span style="text-align:center;"><strong style="font-size:16px;display:block;"><?php echo number_format( $_cp_bar['prayers']['total'] ); ?></strong><span style="font-size:9px;opacity:.6;"><?php esc_html_e( 'prayers', 'yourjannah' ); ?></span></span>
            <?php endif; ?>
            <?php if ( $_cp_bar && $_cp_bar['quran_pages'] > 0 ) : ?>
            <span style="text-align:center;"><strong style="font-size:16px;display:block;"><?php echo number_format( $_cp_bar['quran_pages'] ); ?></strong><span style="font-size:9px;opacity:.6;"><?php esc_html_e( 'pages', 'yourjannah' ); ?></span></span>
            <?php endif; ?>
            <?php if ( $_ldata['rank'] > 0 ) : ?>
            <span style="background:rgba(255,255,255,.15);padding:6px 10px;border-radius:8px;text-align:center;"><strong style="font-size:16px;display:block;">#<?php echo $_ldata['rank']; ?></strong><span style="font-size:9px;opacity:.6;"><?php esc_html_e( 'rank', 'yourjannah' ); ?></span></span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ( $_fajr_bar > 0 ) : ?>
    <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.1);font-size:12px;color:rgba(255,255,255,.7);">
        🌙 <?php printf( esc_html__( '%d people prayed Fajr today', 'yourjannah' ), $_fajr_bar ); ?>
    </div>
    <?php endif; ?>
</a>
<?php endif; ?>

<!-- Section Nav Chips -->
<div style="display:flex;gap:6px;overflow-x:auto;-webkit-overflow-scrolling:touch;padding:0 0 10px;scrollbar-width:none;" class="ynj-section-nav">
    <a href="#next-prayer-card" style="flex-shrink:0;padding:8px 14px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap;">🕐 Prayers</a>
    <a href="#feed-section" style="flex-shrink:0;padding:8px 14px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap;">📢 Feed</a>
    <?php if ( is_user_logged_in() ) : ?>
    <a href="#ibadah-tracker" style="flex-shrink:0;padding:8px 14px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap;">🤲 My Ibadah</a>
    <?php endif; ?>
    <a href="#dua-wall" style="flex-shrink:0;padding:8px 14px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap;">🤲 Dua Wall</a>
    <a href="#mosque-league-table" style="flex-shrink:0;padding:8px 14px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap;">🏆 League</a>
    <a href="#gratitude-wall" style="flex-shrink:0;padding:8px 14px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap;">💖 Gratitude</a>
</div>
<style>.ynj-section-nav::-webkit-scrollbar{display:none;}.ynj-section-nav a:active{background:#287e61;color:#fff;border-color:#287e61;}</style>
<script>
document.querySelectorAll('.ynj-section-nav a').forEach(function(link) {
    link.addEventListener('click', function(e) {
        var target = document.querySelector(this.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.querySelectorAll('.ynj-section-nav a').forEach(function(a){ a.style.background='#fff'; a.style.color='#374151'; a.style.borderColor='#e5e7eb'; });
            this.style.background='#287e61'; this.style.color='#fff'; this.style.borderColor='#287e61';
        }
    });
});
</script>
