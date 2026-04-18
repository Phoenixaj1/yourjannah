<?php
/**
 * Community Engagement — Mosque Leagues, Badges, Who's Here, Congregation Points.
 *
 * Mosque leagues: size-tiered competition (small/medium/large mosques compete fairly).
 * Badges: personal achievements earned through ibadah consistency.
 * Who's here: anonymous check-in count showing how many people are at the mosque now.
 * Congregation points: collective ibadah display broken down by category.
 *
 * @package YourJannah
 * @since   3.9.9
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ================================================================
// MASJID LEVELS — XP-based progression from dhikr
// ================================================================

/**
 * Get masjid level based on total dhikr count.
 * Each level needs more dhikr — creates the "so close to next level" feeling.
 *
 * @param int $total_dhikr  Total dhikr count for this masjid
 * @return array { level, name, icon, current_xp, next_xp, xp_pct, next_name, next_icon }
 */
function ynj_get_masjid_level( $total_dhikr ) {
    $levels = [
        [ 'level' => 1,  'name' => 'Seedling',       'icon' => '&#x1F331;', 'xp' => 0 ],
        [ 'level' => 2,  'name' => 'Sprout',          'icon' => '&#x1F33F;', 'xp' => 25 ],     // ~1 week
        [ 'level' => 3,  'name' => 'Rising Star',     'icon' => '&#x1F31F;', 'xp' => 75 ],     // ~2 weeks
        [ 'level' => 4,  'name' => 'Shining Light',   'icon' => '&#x2728;',  'xp' => 150 ],    // ~1 month
        [ 'level' => 5,  'name' => 'Blessed',         'icon' => '&#x1F54C;', 'xp' => 300 ],    // ~2 months
        [ 'level' => 6,  'name' => 'Radiant',         'icon' => '&#x1F4AB;', 'xp' => 600 ],    // ~4 months
        [ 'level' => 7,  'name' => 'Luminous',        'icon' => '&#x1F320;', 'xp' => 1200 ],   // ~8 months
        [ 'level' => 8,  'name' => 'Majestic',        'icon' => '&#x1F451;', 'xp' => 2500 ],   // ~1.5 years
        [ 'level' => 9,  'name' => 'Glorious',        'icon' => '&#x1F3C6;', 'xp' => 5000 ],   // ~3 years
        [ 'level' => 10, 'name' => 'Heavenly',        'icon' => '&#x1F30D;', 'xp' => 10000 ],  // legendary
    ];

    $current = $levels[0];
    $next = $levels[1] ?? null;

    for ( $i = count( $levels ) - 1; $i >= 0; $i-- ) {
        if ( $total_dhikr >= $levels[ $i ]['xp'] ) {
            $current = $levels[ $i ];
            $next = $levels[ $i + 1 ] ?? null;
            break;
        }
    }

    $xp_in_level = $total_dhikr - $current['xp'];
    $xp_for_next = $next ? ( $next['xp'] - $current['xp'] ) : 1;
    $pct = $next ? min( 100, round( $xp_in_level / $xp_for_next * 100 ) ) : 100;
    $remaining = $next ? ( $next['xp'] - $total_dhikr ) : 0;

    return [
        'level'      => $current['level'],
        'name'       => $current['name'],
        'icon'       => $current['icon'],
        'total_xp'   => $total_dhikr,
        'current_xp' => $xp_in_level,
        'next_xp'    => $xp_for_next,
        'xp_pct'     => $pct,
        'remaining'  => $remaining,
        'next_name'  => $next ? $next['name'] : null,
        'next_icon'  => $next ? $next['icon'] : null,
        'max_level'  => ! $next,
    ];
}

// ================================================================
// MOSQUE LEAGUES — Size-tiered competition
// ================================================================

/**
 * Get the league tier for a mosque based on member count.
 *
 * @param int $member_count Active subscribers
 * @return array { key, name, icon, min, max }
 */
function ynj_get_league_tier( $member_count ) {
    $tiers = [
        [ 'key' => 'rising_star','name' => 'Rising Star', 'icon' => '🌟', 'min' => 0,   'max' => 25  ],
        [ 'key' => 'growing',    'name' => 'Growing',    'icon' => '🌿', 'min' => 26,  'max' => 100 ],
        [ 'key' => 'established','name' => 'Established','icon' => '🌳', 'min' => 101, 'max' => 500 ],
        [ 'key' => 'flagship',   'name' => 'Flagship',   'icon' => '🏆', 'min' => 501, 'max' => 999999 ],
    ];
    foreach ( $tiers as $t ) {
        if ( $member_count >= $t['min'] && $member_count <= $t['max'] ) return $t;
    }
    return $tiers[0];
}

/**
 * Get mosque league standings — ranked by PURE DHIKR / REMEMBRANCE counts.
 *
 * Leagues are ranked by total remembrances (dhikr) per member.
 * Every La ilaha illallah, SubhanAllah, Alhamdulillah, and Shukr counts.
 * Normalised per member so small mosques can compete fairly.
 *
 * @param int    $mosque_id
 * @param string $city        Optional city filter (null = national league)
 * @param int    $days        Period (default 7 = this week)
 * @return array { rank, total, score, per_member, tier, top_mosques[], breakdown }
 */
function ynj_get_league_standings( $mosque_id, $city = null, $days = 7 ) {
    global $wpdb;
    $mt    = YNJ_DB::table( 'mosques' );
    $sub   = YNJ_DB::table( 'user_subscriptions' );
    $ib    = YNJ_DB::table( 'ibadah_logs' );
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

    // Get this mosque's member count + tier
    $my_members = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND status = 'active'", $mosque_id
    ) );
    $my_tier = ynj_get_league_tier( $my_members );

    $city_clause = $city ? $wpdb->prepare( " AND m.city = %s", $city ) : '';

    // Pure dhikr-based scoring: count remembrances per mosque, normalised per member
    $mosques_in_tier = $wpdb->get_results(
        "SELECT m.id, m.name, m.slug, m.city,
                COALESCE(s.cnt, 0) AS members,
                COALESCE(dk.dhikr_count, 0) AS dhikr_count,
                COALESCE(dk.dhikr_count, 0) AS raw_score,
                CASE WHEN COALESCE(s.cnt, 0) > 0
                     THEN ROUND( COALESCE(dk.dhikr_count, 0) / s.cnt, 1 )
                     ELSE 0 END AS per_member
         FROM $mt m
         LEFT JOIN (
             SELECT mosque_id, COUNT(*) AS cnt
             FROM $sub WHERE status = 'active' GROUP BY mosque_id
         ) s ON s.mosque_id = m.id
         LEFT JOIN (
             SELECT mosque_id, COUNT(*) AS dhikr_count
             FROM $ib WHERE dhikr = 1 AND log_date >= '$since' GROUP BY mosque_id
         ) dk ON dk.mosque_id = m.id
         WHERE m.status = 'active'
           AND COALESCE(s.cnt, 0) BETWEEN {$my_tier['min']} AND {$my_tier['max']}
           {$city_clause}
         HAVING raw_score > 0
         ORDER BY per_member DESC
         LIMIT 50"
    );

    // Find our rank
    $rank = 0;
    $my_score = 0;
    $my_per_member = 0;
    $my_breakdown = [];
    foreach ( $mosques_in_tier as $i => $m ) {
        if ( (int) $m->id === $mosque_id ) {
            $rank = $i + 1;
            $my_score = (int) $m->raw_score;
            $my_per_member = (float) $m->per_member;
            $my_breakdown = [
                'dhikr_count' => (int) $m->dhikr_count,
            ];
            break;
        }
    }

    return [
        'rank'       => $rank,
        'total'      => count( $mosques_in_tier ),
        'score'      => $my_score,
        'per_member' => $my_per_member,
        'members'    => $my_members,
        'tier'       => $my_tier,
        'top_5'      => array_slice( $mosques_in_tier, 0, 5 ),
        'breakdown'  => $my_breakdown,
    ];
}

// ================================================================
// BADGES — Personal achievements
// ================================================================

/**
 * All available badges with unlock criteria.
 */
function ynj_get_badge_definitions() {
    return [
        // ── First steps — the door opens ──
        [ 'key' => 'first_dhikr',     'name' => 'Mubtadi (Beginner)',     'icon' => '🌟', 'desc' => 'Say your first remembrance',           'check' => 'dhikr_days >= 1' ],
        [ 'key' => 'dhikr_3',         'name' => 'Taalib (Seeker)',        'icon' => '✨', 'desc' => 'Remember Allah 3 days',                'check' => 'dhikr_days >= 3' ],
        [ 'key' => 'all_five',        'name' => 'Mukhlif (Devoted)',      'icon' => '🤲', 'desc' => 'Complete all 5 in one day',            'check' => 'all_five >= 1' ],
        [ 'key' => 'dhikr_7',         'name' => 'Dhakir (Rememberer)',    'icon' => '📿', 'desc' => 'Remember Allah 7 days',                'check' => 'dhikr_days >= 7' ],

        // ── Consistency — the path deepens ──
        [ 'key' => 'streak_3',        'name' => 'Murid (Aspirant)',       'icon' => '🕯️', 'desc' => '3 consecutive days',                   'check' => 'streak >= 3' ],
        [ 'key' => 'streak_7',        'name' => 'Sabir (Patient)',        'icon' => '🌿', 'desc' => '7 consecutive days',                   'check' => 'streak >= 7' ],
        [ 'key' => 'streak_14',       'name' => 'Mukhlis (Sincere)',      'icon' => '💎', 'desc' => '14 consecutive days',                  'check' => 'streak >= 14' ],
        [ 'key' => 'streak_30',       'name' => 'Mustaqim (Steadfast)',   'icon' => '🏔️', 'desc' => '30 consecutive days',                  'check' => 'streak >= 30' ],

        // ── Depth — drawing ever closer ──
        [ 'key' => 'dhikr_14',        'name' => 'Qarib (Near)',           'icon' => '🌙', 'desc' => 'Remember Allah 14 days',               'check' => 'dhikr_days >= 14' ],
        [ 'key' => 'dhikr_30',        'name' => 'Wali (Friend of Allah)','icon' => '☀️', 'desc' => 'Remember Allah 30 days',               'check' => 'dhikr_days >= 30' ],
        [ 'key' => 'dhikr_100',       'name' => 'Arif (Knower)',          'icon' => '🌕', 'desc' => 'Remember Allah 100 days',              'check' => 'dhikr_days >= 100' ],

        // ── Gratitude — the overflowing heart ──
        [ 'key' => 'charity_3',       'name' => 'Shakir (Grateful)',      'icon' => '💝', 'desc' => 'Express gratitude 3 times',            'check' => 'charity_days >= 3' ],
        [ 'key' => 'charity_10',      'name' => 'Shakur (Ever-Grateful)', 'icon' => '🌊', 'desc' => 'Express gratitude 10 times',           'check' => 'charity_days >= 10' ],

        // ── Community badges ──
        [ 'key' => 'checkin_first',   'name' => 'First Visit',      'icon' => '📍', 'desc' => 'Check in at your mosque',             'check' => 'checkins >= 1' ],
        [ 'key' => 'checkin_10',      'name' => 'Regular',          'icon' => '🏠', 'desc' => 'Check in 10 times',                   'check' => 'checkins >= 10' ],
        [ 'key' => 'checkin_50',      'name' => 'Pillar',           'icon' => '🏛️', 'desc' => 'Check in 50 times',                   'check' => 'checkins >= 50' ],
    ];
}

/**
 * Check and award any new badges for a user.
 *
 * @param int $user_id  YNJ user ID
 * @param int $mosque_id
 * @return array Newly earned badges
 */
function ynj_check_badges( $user_id, $mosque_id ) {
    global $wpdb;
    $ib  = YNJ_DB::table( 'ibadah_logs' );
    $pt  = YNJ_DB::table( 'points' );
    $bt  = YNJ_DB::table( 'user_badges' );

    // Gather stats
    $totals = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers,
                COALESCE(SUM(quran_pages),0) AS quran,
                COALESCE(SUM(dhikr),0) AS dhikr_days,
                COALESCE(SUM(fasting),0) AS fasting_days,
                COALESCE(SUM(charity),0) AS charity_days,
                COUNT(DISTINCT CASE WHEN good_deed != '' THEN log_date END) AS good_deeds,
                COUNT(DISTINCT CASE WHEN fajr+dhuhr+asr+maghrib+isha = 5 THEN log_date END) AS all_five
         FROM $ib WHERE user_id = %d", $user_id
    ) );

    $checkins = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $pt WHERE user_id = %d AND action = 'check_in'", $user_id
    ) );

    // Calculate streak
    $streak_dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT log_date FROM $ib WHERE user_id = %d AND (fajr=1 OR dhuhr=1 OR asr=1 OR maghrib=1 OR isha=1) ORDER BY log_date DESC LIMIT 120", $user_id
    ) );
    $streak = 0;
    $expected = date( 'Y-m-d' );
    foreach ( $streak_dates as $d ) {
        if ( $d === $expected ) { $streak++; $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) ); }
        elseif ( $streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) { $streak = 1; $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) ); }
        else break;
    }

    $stats = [
        'prayers'      => (int) $totals->prayers,
        'quran'        => (int) $totals->quran,
        'dhikr_days'   => (int) $totals->dhikr_days,
        'fasting_days' => (int) $totals->fasting_days,
        'charity_days' => (int) $totals->charity_days,
        'good_deeds'   => (int) $totals->good_deeds,
        'all_five'     => (int) $totals->all_five,
        'checkins'     => $checkins,
        'streak'       => $streak,
    ];

    // Check each badge
    $existing = $wpdb->get_col( $wpdb->prepare( "SELECT badge_key FROM $bt WHERE user_id = %d", $user_id ) );
    $new_badges = [];

    foreach ( ynj_get_badge_definitions() as $badge ) {
        if ( in_array( $badge['key'], $existing, true ) ) continue;

        // Parse check condition
        if ( preg_match( '/^(\w+)\s*>=\s*(\d+)$/', $badge['check'], $m ) ) {
            $field = $m[1];
            $threshold = (int) $m[2];
            if ( isset( $stats[ $field ] ) && $stats[ $field ] >= $threshold ) {
                $wpdb->insert( $bt, [
                    'user_id'    => $user_id,
                    'mosque_id'  => $mosque_id,
                    'badge_key'  => $badge['key'],
                    'badge_name' => $badge['name'],
                    'badge_icon' => $badge['icon'],
                ] );
                $new_badges[] = $badge;
            }
        }
    }

    return $new_badges;
}

/**
 * Get all earned badges for a user.
 */
function ynj_get_user_badges( $user_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT badge_key, badge_name, badge_icon, earned_at FROM " . YNJ_DB::table( 'user_badges' ) . " WHERE user_id = %d ORDER BY earned_at ASC",
        $user_id
    ) ) ?: [];
}

// ================================================================
// WHO'S AT THE MASJID — Anonymous check-in counter
// ================================================================

/**
 * Get anonymous count of people who checked in at a mosque recently.
 *
 * @param int $mosque_id
 * @param int $hours     Window (default 2 = last 2 hours)
 * @return array { count, prayer_name }
 */
function ynj_whos_at_masjid( $mosque_id, $hours = 2 ) {
    global $wpdb;
    $pt = YNJ_DB::table( 'points' );
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM $pt WHERE mosque_id = %d AND action = 'check_in' AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)",
        $mosque_id, $hours
    ) );

    return [ 'count' => $count ];
}

// ================================================================
// CONGREGATION POINTS DISPLAY — Collective ibadah breakdown
// ================================================================

// ================================================================
// PERSONAL IMPACT SCORE
// ================================================================

/**
 * Calculate what % of the mosque's total ibadah this user contributed.
 */
function ynj_personal_impact( $user_id, $mosque_id, $days = 7 ) {
    global $wpdb;
    $ib = YNJ_DB::table( 'ibadah_logs' );
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

    $my_pts = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(points_earned),0) FROM $ib WHERE user_id = %d AND mosque_id = %d AND log_date >= %s",
        $user_id, $mosque_id, $since
    ) );
    $total_pts = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(points_earned),0) FROM $ib WHERE mosque_id = %d AND log_date >= %s",
        $mosque_id, $since
    ) );

    $pct = $total_pts > 0 ? round( $my_pts / $total_pts * 100, 1 ) : 0;

    return [
        'my_points'    => $my_pts,
        'total_points' => $total_pts,
        'percentage'   => $pct,
    ];
}

// ================================================================
// HEAD-TO-HEAD MOSQUE CHALLENGES
// ================================================================

/**
 * Get the current head-to-head challenge for a mosque (if any).
 */
function ynj_get_h2h_challenge( $mosque_id ) {
    global $wpdb;
    $h2h = YNJ_DB::table( 'h2h_challenges' );
    $mt  = YNJ_DB::table( 'mosques' );
    $today = date( 'Y-m-d' );

    $challenge = $wpdb->get_row( $wpdb->prepare(
        "SELECT h.*,
                ma.name AS mosque_a_name, ma.slug AS mosque_a_slug,
                mb.name AS mosque_b_name, mb.slug AS mosque_b_slug
         FROM $h2h h
         JOIN $mt ma ON ma.id = h.mosque_a_id
         JOIN $mt mb ON mb.id = h.mosque_b_id
         WHERE (h.mosque_a_id = %d OR h.mosque_b_id = %d) AND h.status = 'active' AND h.end_date >= %s
         ORDER BY h.id DESC LIMIT 1",
        $mosque_id, $mosque_id, $today
    ) );

    if ( ! $challenge ) return null;

    $is_a = ( (int) $challenge->mosque_a_id === $mosque_id );
    $my_score   = $is_a ? (int) $challenge->mosque_a_score : (int) $challenge->mosque_b_score;
    $their_score = $is_a ? (int) $challenge->mosque_b_score : (int) $challenge->mosque_a_score;
    $opponent_name = $is_a ? $challenge->mosque_b_name : $challenge->mosque_a_name;
    $days_left = max( 0, (int) ( ( strtotime( $challenge->end_date ) - time() ) / DAY_IN_SECONDS ) + 1 );

    return [
        'id'             => (int) $challenge->id,
        'opponent'       => $opponent_name,
        'my_score'       => $my_score,
        'their_score'    => $their_score,
        'winning'        => $my_score > $their_score,
        'tied'           => $my_score === $their_score,
        'days_left'      => $days_left,
        'type'           => $challenge->challenge_type,
    ];
}

/**
 * Generate weekly head-to-head challenges — match mosques in same tier.
 * Called by cron on Mondays.
 */
function ynj_generate_h2h_challenges() {
    global $wpdb;
    $h2h = YNJ_DB::table( 'h2h_challenges' );
    $mt  = YNJ_DB::table( 'mosques' );
    $sub = YNJ_DB::table( 'user_subscriptions' );

    $week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
    $week_end   = date( 'Y-m-d', strtotime( 'Sunday this week' ) );

    // Mark expired challenges
    $wpdb->query( $wpdb->prepare(
        "UPDATE $h2h SET status = 'completed' WHERE status = 'active' AND end_date < %s", date( 'Y-m-d' )
    ) );

    // Get all active mosques with member counts, grouped by tier
    $mosques = $wpdb->get_results(
        "SELECT m.id, m.name, COALESCE(s.cnt, 0) AS members
         FROM $mt m
         LEFT JOIN (SELECT mosque_id, COUNT(*) AS cnt FROM $sub WHERE status = 'active' GROUP BY mosque_id) s ON s.mosque_id = m.id
         LEFT JOIN $h2h h ON (h.mosque_a_id = m.id OR h.mosque_b_id = m.id) AND h.start_date = '$week_start'
         WHERE m.status = 'active' AND h.id IS NULL
         ORDER BY COALESCE(s.cnt, 0) ASC"
    );

    // Group by tier
    $tiers = [];
    foreach ( $mosques as $m ) {
        $tier = ynj_get_league_tier( (int) $m->members );
        $tiers[ $tier['key'] ][] = $m;
    }

    $created = 0;
    foreach ( $tiers as $tier_key => $tier_mosques ) {
        // Shuffle and pair up
        shuffle( $tier_mosques );
        for ( $i = 0; $i + 1 < count( $tier_mosques ); $i += 2 ) {
            $wpdb->insert( $h2h, [
                'mosque_a_id'    => (int) $tier_mosques[ $i ]->id,
                'mosque_b_id'    => (int) $tier_mosques[ $i + 1 ]->id,
                'challenge_type' => 'engagement',
                'mosque_a_score' => 0,
                'mosque_b_score' => 0,
                'start_date'     => $week_start,
                'end_date'       => $week_end,
                'status'         => 'active',
            ] );
            $created++;
        }
    }

    if ( $created > 0 ) {
        error_log( "[YNJ] Generated {$created} head-to-head challenges for this week." );
    }
}

// ================================================================
// FAJR COUNTER — Who's awake for Fajr today
// ================================================================

/**
 * Get count of people who logged Fajr today.
 */
function ynj_fajr_counter( $mosque_id ) {
    global $wpdb;
    $ib = YNJ_DB::table( 'ibadah_logs' );
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $ib WHERE mosque_id = %d AND log_date = CURDATE() AND fajr = 1",
        $mosque_id
    ) );
    return $count;
}

// ================================================================
// MILESTONE CELEBRATIONS
// ================================================================

/**
 * Check and return any new milestones reached by a mosque.
 * Returns milestones that were JUST reached (not previously recorded).
 */
function ynj_check_milestones( $mosque_id ) {
    global $wpdb;
    $mt = YNJ_DB::table( 'milestones' );
    $ib = YNJ_DB::table( 'ibadah_logs' );
    $sub = YNJ_DB::table( 'user_subscriptions' );
    $dt = YNJ_DB::table( 'donations' );

    // Get current totals
    $total_prayers = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) FROM $ib WHERE mosque_id = %d", $mosque_id
    ) );
    $total_pages = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(quran_pages),0) FROM $ib WHERE mosque_id = %d", $mosque_id
    ) );
    $total_members = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND status = 'active'", $mosque_id
    ) );
    $total_donations_pence = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE mosque_id = %d AND status = 'succeeded'", $mosque_id
    ) );

    $milestone_defs = [
        [ 'key' => 'prayers_100',    'label' => '100 prayers logged',          'icon' => '🤲', 'check' => $total_prayers,         'threshold' => 100 ],
        [ 'key' => 'prayers_500',    'label' => '500 prayers logged',          'icon' => '🤲', 'check' => $total_prayers,         'threshold' => 500 ],
        [ 'key' => 'prayers_1000',   'label' => '1,000 prayers logged',       'icon' => '✨', 'check' => $total_prayers,         'threshold' => 1000 ],
        [ 'key' => 'prayers_5000',   'label' => '5,000 prayers logged',       'icon' => '🌟', 'check' => $total_prayers,         'threshold' => 5000 ],
        [ 'key' => 'prayers_10000',  'label' => '10,000 prayers logged',      'icon' => '💎', 'check' => $total_prayers,         'threshold' => 10000 ],
        [ 'key' => 'quran_100',      'label' => '100 Quran pages read',       'icon' => '📖', 'check' => $total_pages,           'threshold' => 100 ],
        [ 'key' => 'quran_500',      'label' => '500 Quran pages read',       'icon' => '📗', 'check' => $total_pages,           'threshold' => 500 ],
        [ 'key' => 'quran_1000',     'label' => '1,000 Quran pages read',    'icon' => '📚', 'check' => $total_pages,           'threshold' => 1000 ],
        [ 'key' => 'members_10',     'label' => '10 members joined',          'icon' => '👥', 'check' => $total_members,         'threshold' => 10 ],
        [ 'key' => 'members_50',     'label' => '50 members joined',          'icon' => '👥', 'check' => $total_members,         'threshold' => 50 ],
        [ 'key' => 'members_100',    'label' => '100 members joined',         'icon' => '🎊', 'check' => $total_members,         'threshold' => 100 ],
        [ 'key' => 'members_500',    'label' => '500 members joined',         'icon' => '🏆', 'check' => $total_members,         'threshold' => 500 ],
        [ 'key' => 'donations_1000', 'label' => 'First £1,000 in donations', 'icon' => '💷', 'check' => $total_donations_pence, 'threshold' => 100000 ],
        [ 'key' => 'donations_5000', 'label' => '£5,000 in donations',       'icon' => '💰', 'check' => $total_donations_pence, 'threshold' => 500000 ],
    ];

    $existing = $wpdb->get_col( $wpdb->prepare( "SELECT milestone_key FROM $mt WHERE mosque_id = %d", $mosque_id ) );
    $new_milestones = [];

    foreach ( $milestone_defs as $ms ) {
        if ( in_array( $ms['key'], $existing, true ) ) continue;
        if ( $ms['check'] >= $ms['threshold'] ) {
            $wpdb->insert( $mt, [
                'mosque_id'      => $mosque_id,
                'milestone_key'  => $ms['key'],
                'milestone_value'=> $ms['threshold'],
            ] );
            $new_milestones[] = $ms;
        }
    }

    return $new_milestones;
}

/**
 * Get the latest milestone reached (for display).
 */
function ynj_get_latest_milestone( $mosque_id ) {
    global $wpdb;
    $mt = YNJ_DB::table( 'milestones' );
    $latest = $wpdb->get_row( $wpdb->prepare(
        "SELECT milestone_key, milestone_value, reached_at FROM $mt WHERE mosque_id = %d ORDER BY reached_at DESC LIMIT 1",
        $mosque_id
    ) );
    if ( ! $latest ) return null;

    // Map key to display info
    $all_defs = [
        'prayers_100' => [ 'icon' => '🤲', 'label' => '100 prayers' ], 'prayers_500' => [ 'icon' => '🤲', 'label' => '500 prayers' ],
        'prayers_1000' => [ 'icon' => '✨', 'label' => '1,000 prayers' ], 'prayers_5000' => [ 'icon' => '🌟', 'label' => '5,000 prayers' ],
        'prayers_10000' => [ 'icon' => '💎', 'label' => '10,000 prayers' ],
        'quran_100' => [ 'icon' => '📖', 'label' => '100 Quran pages' ], 'quran_500' => [ 'icon' => '📗', 'label' => '500 Quran pages' ],
        'quran_1000' => [ 'icon' => '📚', 'label' => '1,000 Quran pages' ],
        'members_10' => [ 'icon' => '👥', 'label' => '10 members' ], 'members_50' => [ 'icon' => '👥', 'label' => '50 members' ],
        'members_100' => [ 'icon' => '🎊', 'label' => '100 members' ], 'members_500' => [ 'icon' => '🏆', 'label' => '500 members' ],
        'donations_1000' => [ 'icon' => '💷', 'label' => '£1,000 donated' ], 'donations_5000' => [ 'icon' => '💰', 'label' => '£5,000 donated' ],
    ];

    $info = $all_defs[ $latest->milestone_key ] ?? [ 'icon' => '🎉', 'label' => 'Milestone' ];
    return [
        'icon'  => $info['icon'],
        'label' => $info['label'],
        'ago'   => human_time_diff( strtotime( $latest->reached_at ) ),
    ];
}

// ================================================================
// CONGREGATION POINTS DISPLAY
// ================================================================

/**
 * Get detailed congregation ibadah breakdown for display.
 *
 * @param int $mosque_id
 * @param int $days      Period (default 7)
 * @return array
 */
function ynj_get_congregation_points( $mosque_id, $days = 7 ) {
    global $wpdb;
    $ib = YNJ_DB::table( 'ibadah_logs' );
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

    $stats = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(fajr),0) AS fajr, COALESCE(SUM(dhuhr),0) AS dhuhr,
                COALESCE(SUM(asr),0) AS asr, COALESCE(SUM(maghrib),0) AS maghrib,
                COALESCE(SUM(isha),0) AS isha,
                COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS total_prayers,
                COALESCE(SUM(quran_pages),0) AS quran_pages,
                COALESCE(SUM(dhikr),0) AS dhikr_days,
                COALESCE(SUM(fasting),0) AS fasting_days,
                COALESCE(SUM(charity),0) AS charity_days,
                COUNT(DISTINCT CASE WHEN good_deed != '' THEN id END) AS good_deeds,
                COALESCE(SUM(points_earned),0) AS total_points,
                COUNT(DISTINCT user_id) AS active_members
         FROM $ib WHERE mosque_id = %d AND log_date >= %s",
        $mosque_id, $since
    ) );

    return [
        'prayers'   => [
            'total' => (int) $stats->total_prayers,
            'fajr'  => (int) $stats->fajr,
            'dhuhr' => (int) $stats->dhuhr,
            'asr'   => (int) $stats->asr,
            'maghrib' => (int) $stats->maghrib,
            'isha'  => (int) $stats->isha,
        ],
        'quran_pages'    => (int) $stats->quran_pages,
        'dhikr_days'     => (int) $stats->dhikr_days,
        'fasting_days'   => (int) $stats->fasting_days,
        'charity_days'   => (int) $stats->charity_days,
        'good_deeds'     => (int) $stats->good_deeds,
        'total_points'   => (int) $stats->total_points,
        'active_members' => (int) $stats->active_members,
    ];
}
