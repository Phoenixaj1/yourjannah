<?php
/**
 * Member HUD — Masjid identity, XP bar, streak, points, dhikr button.
 *
 * Expects $data array from YNJ_HUD::get_data().
 *
 * @package YNJ_HUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="ynj-hud-wrap">
<div class="ynj-hud" id="ynj-hud">

    <!-- Row 1: Masjid identity + XP bar -->
    <div class="ynj-hud__row1">
        <a href="<?php echo esc_url( $data['mosque_url'] ); ?>" class="ynj-hud__masjid">
            <span class="ynj-hud__tier-icon"><?php echo $data['level'] ? $data['level']['icon'] : '&#x1F54C;'; ?></span>
            <span class="ynj-hud__masjid-name"><?php echo $data['mosque'] ? esc_html( $data['mosque']->name ) : esc_html__( 'Select Masjid', 'yourjannah' ); ?></span>
            <?php if ( $data['level'] ) : ?>
            <span class="ynj-hud__tier-name">Lv<?php echo (int) $data['level']['level']; ?></span>
            <?php endif; ?>
        </a>
        <?php if ( $data['rank'] > 0 ) : ?>
        <button type="button" class="ynj-hud__league-btn" id="hud-rank" onclick="ynjHudLeagueToggle()">
            <span class="ynj-hud__rank-badge">#<?php echo (int) $data['rank']; ?></span>
            <span class="ynj-hud__league-label"><?php esc_html_e( 'League', 'yourjannah' ); ?></span>
        </button>
        <?php endif; ?>

        <?php if ( $data['mosque'] && $data['level'] ) : ?>
        <div class="ynj-hud__xp">
            <div class="ynj-hud__xp-bar" title="<?php printf( esc_attr__( '%d / %d to next level', 'yourjannah' ), $data['level']['current_xp'], $data['level']['next_xp'] ); ?>">
                <div class="ynj-hud__xp-fill" style="width:<?php echo (int) $data['level']['xp_pct']; ?>%"></div>
            </div>
            <span class="ynj-hud__xp-text">
                <?php if ( $data['level']['remaining'] > 0 ) : ?>
                    <?php echo number_format( $data['level']['remaining'] ); ?> <?php esc_html_e( 'to', 'yourjannah' ); ?> <?php echo $data['level']['next_icon']; ?>
                <?php else : ?>
                    <?php esc_html_e( 'MAX', 'yourjannah' ); ?>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Row 2: Actions -->
    <div class="ynj-hud__row2">
        <!-- Streak -->
        <div class="ynj-hud__stat<?php echo $data['streak'] >= 3 ? ' ynj-hud__stat--glow' : ''; ?>">
            <span class="ynj-hud__stat-icon">&#x1F525;</span>
            <span class="ynj-hud__stat-num" id="hud-streak"><?php echo (int) $data['streak']; ?></span>
            <span class="ynj-hud__stat-label"><?php esc_html_e( 'streak', 'yourjannah' ); ?></span>
        </div>

        <!-- Points -->
        <div class="ynj-hud__stat">
            <span class="ynj-hud__stat-icon">&#x2B50;</span>
            <span class="ynj-hud__stat-num" id="hud-pts-num"><?php echo number_format( $data['points'] ); ?></span>
            <span class="ynj-hud__stat-label"><?php esc_html_e( 'pts', 'yourjannah' ); ?></span>
        </div>

        <!-- Members -->
        <?php if ( $data['mosque'] ) : ?>
        <div class="ynj-hud__stat">
            <span class="ynj-hud__stat-icon">&#x1F465;</span>
            <span class="ynj-hud__stat-num"><?php echo (int) $data['members']; ?></span>
            <span class="ynj-hud__stat-label"><?php esc_html_e( 'members', 'yourjannah' ); ?></span>
        </div>
        <?php endif; ?>

        <!-- Quick Dhikr -->
        <?php if ( ! empty( $data['five_dhikr'] ) ) : ?>
        <button type="button" class="ynj-hud__dhikr<?php echo $data['all_done'] ? ' ynj-hud__dhikr--done' : ''; ?>" id="hud-dhikr-btn" onclick="ynjHudDhikrToggle()">
            <?php if ( $data['all_done'] ) : ?>
                &#x2705; <span>5/5</span>
            <?php elseif ( $data['done_count'] > 0 ) : ?>
                &#x1F4FF; <span><?php echo $data['done_count']; ?>/5</span>
            <?php else : ?>
                &#x1F4FF; <span><?php esc_html_e( 'Say Dhikr', 'yourjannah' ); ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>

        <!-- Info -->
        <button type="button" class="ynj-hud__info-btn" onclick="ynjHudInfoToggle()" title="<?php esc_attr_e( 'How it works', 'yourjannah' ); ?>">?</button>

        <!-- Profile -->
        <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="ynj-hud__profile" title="<?php echo esc_attr( $data['name'] ); ?>">
            <span class="ynj-hud__avatar"><?php echo esc_html( $data['initial'] ); ?></span>
        </a>
    </div>
</div>
</div>
