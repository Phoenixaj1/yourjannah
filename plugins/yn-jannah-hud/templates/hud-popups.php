<?php
/**
 * HUD Popups — Dhikr, League Table, How It Works modals.
 *
 * Expects $data array from YNJ_HUD::get_data().
 *
 * @package YNJ_HUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- ════ Quick Dhikr Popup — ONE AT A TIME ════ -->
<?php if ( ! empty( $data['five_dhikr'] ) ) : ?>
<div class="ynj-hud-popup" id="hud-dhikr-popup" style="display:none;" onclick="if(event.target===this)ynjHudDhikrToggle()">
    <div class="ynj-hud-popup__card" id="hud-popup-card" style="max-width:380px;">
        <button type="button" class="ynj-hud-popup__close" onclick="ynjHudDhikrToggle()">&times;</button>

        <!-- Progress bar + counter -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div style="font-size:12px;font-weight:700;color:#6b8fa3;" id="dhikr-progress-text"><?php echo $data['done_count']; ?>/5</div>
            <div style="flex:1;margin:0 10px;height:6px;background:rgba(0,0,0,.06);border-radius:3px;overflow:hidden;">
                <div id="dhikr-progress-bar" style="height:100%;background:linear-gradient(90deg,#287e61,#34d399);border-radius:3px;transition:width .6s ease-out;width:<?php echo $data['done_count'] * 20; ?>%;"></div>
            </div>
            <div style="display:flex;align-items:center;gap:4px;">
                <span style="font-size:12px;">&#x2B50;</span>
                <span style="font-size:14px;font-weight:900;color:#92400e;" id="dhikr-pts-live"><?php echo number_format( $data['points'] ); ?></span>
            </div>
        </div>

        <!-- Single dhikr card (JS swaps content) -->
        <div id="dhikr-card">
            <?php if ( $data['all_done'] ) : ?>
            <div id="dhikr-complete" style="text-align:center;padding:24px 0;">
                <div style="font-size:40px;margin-bottom:8px;">&#x2705;</div>
                <div style="font-size:18px;font-weight:800;color:#166534;margin-bottom:6px;">Alhamdulillah</div>
                <div style="font-size:13px;color:#15803d;font-style:italic;line-height:1.6;">Truly, in the remembrance of Allah do hearts find rest.</div>
                <div style="font-size:10px;color:rgba(0,0,0,.3);margin-top:4px;">Quran 13:28</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Masjid context -->
        <?php if ( $data['mosque'] ) : ?>
        <div style="text-align:center;font-size:11px;color:#6b8fa3;margin-top:10px;font-style:italic;">
            <?php if ( $data['level'] ) : ?>
            <?php echo $data['level']['icon']; ?> <?php echo esc_html( $data['mosque']->name ); ?> &middot; Lv<?php echo (int) $data['level']['level']; ?>
            <?php else : ?>
            &#x1F54C; <?php echo esc_html( $data['mosque']->name ); ?>
            <?php endif; ?>
            <?php if ( $data['streak'] > 0 ) : ?>
            &middot; &#x1F525; <?php echo (int) $data['streak']; ?> day streak
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Dhikr data for JS -->
<script>
window._ynjDhikrData = <?php echo wp_json_encode( array_map( function( $hd, $i ) use ( $data ) {
    return [
        'index'       => $i,
        'arabic'      => $hd['arabic'],
        'english'     => $hd['english'],
        'reward'      => $hd['reward'],
        'source'      => $hd['source'],
        'action_text' => $hd['action_text'],
        'tier'        => $hd['tier'] ?? 'normal',
        'done'        => ! empty( $data['done_flags'][ $i ] ),
    ];
}, $data['five_dhikr'], array_keys( $data['five_dhikr'] ) ) ); ?>;
window._ynjDhikrDone = <?php echo (int) $data['done_count']; ?>;
window._ynjDhikrPts = <?php echo (int) $data['points']; ?>;
</script>
<?php endif; ?>

<!-- ════ LEAGUE TABLE MODAL ════ -->
<?php if ( $data['mosque'] && $data['league'] && $data['league']['rank'] > 0 ) :
    $_league_top = $data['league']['top_5'] ?? [];
?>
<div class="ynj-hud-popup" id="hud-league-popup" style="display:none;">
    <div class="ynj-hud-popup__card">
        <button type="button" class="ynj-hud-popup__close" onclick="ynjHudLeagueToggle()">&times;</button>

        <div style="text-align:center;padding-bottom:14px;border-bottom:1px solid rgba(0,0,0,.06);margin-bottom:14px;">
            <div style="font-size:28px;margin-bottom:4px;"><?php echo $data['level'] ? $data['level']['icon'] : '&#x1F54C;'; ?></div>
            <div style="font-size:16px;font-weight:800;color:#0a1628;"><?php echo esc_html( $data['mosque']->name ); ?></div>
            <div style="font-size:12px;color:#6b8fa3;">
                <?php if ( $data['mosque']->city ) echo esc_html( $data['mosque']->city ); ?>
                <?php if ( $data['level'] ) : ?> &middot; Lv<?php echo (int) $data['level']['level']; ?> <?php echo esc_html( $data['level']['name'] ); ?><?php endif; ?>
            </div>
            <div style="display:inline-block;margin-top:8px;padding:4px 16px;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;border-radius:10px;font-size:18px;font-weight:900;">
                #<?php echo (int) $data['league']['rank']; ?> <span style="font-size:12px;font-weight:600;opacity:.7;"><?php esc_html_e( 'of', 'yourjannah' ); ?> <?php echo (int) $data['league']['total']; ?></span>
            </div>
            <div style="font-size:10px;color:#6b8fa3;margin-top:4px;"><?php echo esc_html( $data['league']['tier']['name'] ); ?> <?php esc_html_e( 'League', 'yourjannah' ); ?> &middot; <?php esc_html_e( 'This week', 'yourjannah' ); ?></div>
        </div>

        <div style="margin-bottom:14px;">
            <div style="font-size:11px;font-weight:700;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?php esc_html_e( 'Leaderboard', 'yourjannah' ); ?></div>
            <?php foreach ( $_league_top as $li => $lm ) :
                $is_me = ( (int) $lm->id === (int) $data['mosque']->id );
                $rank_num = $li + 1;
                $rank_icons = [ 1 => '&#x1F947;', 2 => '&#x1F948;', 3 => '&#x1F949;' ];
            ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;margin-bottom:4px;<?php echo $is_me ? 'background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;' : 'background:#f9fafb;border:1px solid #f0f0f0;'; ?>">
                <span style="font-size:<?php echo $rank_num <= 3 ? '18' : '14'; ?>px;font-weight:900;width:28px;text-align:center;<?php echo $is_me ? 'color:#287e61;' : 'color:#6b8fa3;'; ?>">
                    <?php echo isset( $rank_icons[ $rank_num ] ) ? $rank_icons[ $rank_num ] : '#' . $rank_num; ?>
                </span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:<?php echo $is_me ? '800' : '600'; ?>;color:#0a1628;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( $lm->name ); ?></div>
                    <div style="font-size:10px;color:#6b8fa3;"><?php echo esc_html( $lm->city ?? '' ); ?> &middot; <?php echo (int) $lm->members; ?> <?php esc_html_e( 'members', 'yourjannah' ); ?></div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div style="font-size:14px;font-weight:800;color:<?php echo $is_me ? '#287e61' : '#0a1628'; ?>;"><?php echo number_format( (int) $lm->dhikr_count ); ?></div>
                    <div style="font-size:9px;color:#6b8fa3;"><?php esc_html_e( 'dhikr', 'yourjannah' ); ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ( $data['league']['rank'] > 5 ) : ?>
            <div style="text-align:center;padding:6px;font-size:11px;color:#6b8fa3;">&middot;&middot;&middot;</div>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;">
                <span style="font-size:14px;font-weight:900;width:28px;text-align:center;color:#287e61;">#<?php echo (int) $data['league']['rank']; ?></span>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:800;color:#0a1628;"><?php echo esc_html( $data['mosque']->name ); ?></div>
                    <div style="font-size:10px;color:#6b8fa3;"><?php echo esc_html( $data['mosque']->city ?? '' ); ?> &middot; <?php echo (int) $data['league']['members']; ?> <?php esc_html_e( 'members', 'yourjannah' ); ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:14px;font-weight:800;color:#287e61;"><?php echo number_format( (int) $data['league']['score'] ); ?></div>
                    <div style="font-size:9px;color:#6b8fa3;"><?php esc_html_e( 'dhikr', 'yourjannah' ); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( $data['h2h'] ) : ?>
        <div style="padding:12px;background:linear-gradient(135deg,#0a1628,#1a2a44);border-radius:14px;color:#fff;margin-bottom:10px;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.5);margin-bottom:8px;text-align:center;"><?php esc_html_e( 'This week\'s challenge', 'yourjannah' ); ?></div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="text-align:center;flex:1;">
                    <div style="font-size:22px;font-weight:900;color:<?php echo $data['h2h']['winning'] ? '#34d399' : '#fff'; ?>;"><?php echo (int) $data['h2h']['my_score']; ?></div>
                    <div style="font-size:10px;opacity:.6;"><?php echo esc_html( mb_strimwidth( $data['mosque']->name, 0, 16, '..' ) ); ?></div>
                </div>
                <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.4);padding:0 8px;">VS</div>
                <div style="text-align:center;flex:1;">
                    <div style="font-size:22px;font-weight:900;"><?php echo (int) $data['h2h']['their_score']; ?></div>
                    <div style="font-size:10px;opacity:.6;"><?php echo esc_html( mb_strimwidth( $data['h2h']['opponent'], 0, 16, '..' ) ); ?></div>
                </div>
            </div>
            <div style="text-align:center;margin-top:8px;font-size:11px;color:rgba(255,255,255,.5);"><?php echo (int) $data['h2h']['days_left']; ?> <?php esc_html_e( 'days left', 'yourjannah' ); ?></div>
        </div>
        <?php endif; ?>

        <div style="text-align:center;font-size:12px;color:#6b8fa3;font-style:italic;">
            <?php esc_html_e( 'Every dhikr you say lifts your masjid in the league', 'yourjannah' ); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════ HOW IT WORKS — Info Modal ════ -->
<div class="ynj-hud-popup" id="hud-info-popup" style="display:none;">
    <div class="ynj-hud-popup__card">
        <button type="button" class="ynj-hud-popup__close" onclick="ynjHudInfoToggle()">&times;</button>

        <div style="text-align:center;padding-bottom:14px;border-bottom:1px solid rgba(0,0,0,.06);margin-bottom:16px;">
            <div style="font-size:32px;margin-bottom:4px;">&#x1F54C;</div>
            <div style="font-size:18px;font-weight:800;color:#0a1628;"><?php esc_html_e( 'How YourJannah Works', 'yourjannah' ); ?></div>
            <div style="font-size:12px;color:#6b8fa3;font-style:italic;"><?php esc_html_e( 'A journey of remembrance for you and your masjid', 'yourjannah' ); ?></div>
        </div>

        <div style="max-height:60vh;overflow-y:auto;-webkit-overflow-scrolling:touch;">
            <div class="ynj-info-section"><div class="ynj-info-icon">&#x1F4FF;</div><div><div class="ynj-info-title"><?php esc_html_e( 'Daily Remembrance', 'yourjannah' ); ?></div><div class="ynj-info-desc"><?php esc_html_e( 'Each day you receive 5 Sunnah adhkar. Tap to say each one. La ilaha illallah is always first.', 'yourjannah' ); ?></div></div></div>
            <div class="ynj-info-section"><div class="ynj-info-icon">&#x2B50;</div><div><div class="ynj-info-title"><?php esc_html_e( 'Points', 'yourjannah' ); ?></div><div class="ynj-info-desc"><?php esc_html_e( 'Each dhikr earns 75-100 points. Complete all 5 for a 200-point bonus.', 'yourjannah' ); ?></div></div></div>
            <div class="ynj-info-section"><div class="ynj-info-icon"><?php echo $data['level'] ? $data['level']['icon'] : '&#x1F331;'; ?></div><div><div class="ynj-info-title"><?php esc_html_e( 'Masjid Levels', 'yourjannah' ); ?></div><div class="ynj-info-desc"><?php esc_html_e( 'Your masjid grows from Seedling to Heavenly. 10 levels.', 'yourjannah' ); ?></div><div class="ynj-info-levels"><span>&#x1F331; Seedling</span><span>&#x1F33F; Sprout</span><span>&#x1F31F; Rising Star</span><span>&#x2728; Shining Light</span><span>&#x1F54C; Blessed</span><span>&#x1F4AB; Radiant</span><span>&#x1F320; Luminous</span><span>&#x1F451; Majestic</span><span>&#x1F3C6; Glorious</span><span>&#x1F30D; Heavenly</span></div></div></div>
            <div class="ynj-info-section"><div class="ynj-info-icon">&#x1F3C6;</div><div><div class="ynj-info-title"><?php esc_html_e( 'Masjid League', 'yourjannah' ); ?></div><div class="ynj-info-desc"><?php esc_html_e( 'Mosques compete weekly by dhikr per member. Small mosques compete fairly.', 'yourjannah' ); ?></div></div></div>
            <div class="ynj-info-section"><div class="ynj-info-icon">&#x1F525;</div><div><div class="ynj-info-title"><?php esc_html_e( 'Community Streak', 'yourjannah' ); ?></div><div class="ynj-info-desc"><?php esc_html_e( 'The streak belongs to your masjid. Every day someone says dhikr, it continues.', 'yourjannah' ); ?></div></div></div>
            <div class="ynj-info-section"><div class="ynj-info-icon">&#x1F3C5;</div><div><div class="ynj-info-title"><?php esc_html_e( 'Badges', 'yourjannah' ); ?></div><div class="ynj-info-desc"><?php esc_html_e( 'Earn titles: Mubtadi, Taalib, Dhakir, Sabir, Mukhlis, and more.', 'yourjannah' ); ?></div></div></div>
            <div class="ynj-info-section"><div class="ynj-info-icon">&#x1F49A;</div><div><div class="ynj-info-title"><?php esc_html_e( 'Invite Others', 'yourjannah' ); ?></div><div class="ynj-info-desc"><?php esc_html_e( 'Every person who joins earns points for your masjid.', 'yourjannah' ); ?></div></div></div>
        </div>

        <div style="text-align:center;padding-top:14px;border-top:1px solid rgba(0,0,0,.06);margin-top:10px;">
            <div style="font-size:12px;color:#6b8fa3;font-style:italic;line-height:1.5;">
                <?php esc_html_e( '"Truly, in the remembrance of Allah do hearts find rest."', 'yourjannah' ); ?>
                <span style="opacity:.5;">&mdash; <?php esc_html_e( 'Quran 13:28', 'yourjannah' ); ?></span>
            </div>
        </div>
    </div>
</div>
