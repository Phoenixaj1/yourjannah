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
        [ 'key' => 'emerging',   'name' => 'Emerging',   'icon' => '🌱', 'min' => 0,   'max' => 25  ],
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
 * Get mosque league standings — ranked against same-tier mosques.
 *
 * Score is based on REAL ENGAGEMENT (not ibadah — that's private):
 *   - Page views on mosque page (mosque_views)
 *   - Content reactions (likes, duas, interested on announcements/events)
 *   - RSVPs / event bookings
 *   - New subscriber joins
 *   - GPS check-ins (physical attendance)
 *   - Content posted by admin (announcements + events)
 *
 * All normalised PER MEMBER so a 50-person mosque can beat a 5000-person one.
 *
 * @param int    $mosque_id
 * @param string $city        Optional city filter (null = national league)
 * @param int    $days        Period (default 7 = this week)
 * @return array { rank, total, score, per_member, tier, top_mosques[], breakdown }
 */
function ynj_get_league_standings( $mosque_id, $city = null, $days = 7 ) {
    global $wpdb;
    $mt   = YNJ_DB::table( 'mosques' );
    $sub  = YNJ_DB::table( 'user_subscriptions' );
    $mv   = YNJ_DB::table( 'mosque_views' );
    $cv   = YNJ_DB::table( 'content_views' );
    $rt   = YNJ_DB::table( 'reactions' );
    $bk   = YNJ_DB::table( 'bookings' );
    $pt   = YNJ_DB::table( 'points' );
    $at   = YNJ_DB::table( 'announcements' );
    $ev   = YNJ_DB::table( 'events' );
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

    // Get this mosque's member count + tier
    $my_members = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND status = 'active'", $mosque_id
    ) );
    $my_tier = ynj_get_league_tier( $my_members );

    // Build league table for all mosques in same tier
    // Score components (weighted):
    //   Page views × 1
    //   Content views × 1
    //   Reactions × 3 (high-value engagement)
    //   RSVPs/bookings × 5 (commitment action)
    //   Check-ins × 5 (physical attendance)
    //   New subscribers × 10 (growth)
    //   Content posted × 8 (admin activity)
    $city_clause = $city ? $wpdb->prepare( " AND m.city = %s", $city ) : '';

    $mosques_in_tier = $wpdb->get_results(
        "SELECT m.id, m.name, m.slug, m.city,
                COALESCE(s.cnt, 0) AS members,
                COALESCE(pg.page_views, 0) AS page_views,
                COALESCE(cv_agg.content_views, 0) AS content_views,
                COALESCE(rx.reaction_count, 0) AS reactions,
                COALESCE(bk_agg.rsvps, 0) AS rsvps,
                COALESCE(ci.checkins, 0) AS checkins,
                COALESCE(ns.new_subs, 0) AS new_subs,
                COALESCE(cp.posts, 0) AS content_posted,
                (
                    COALESCE(pg.page_views, 0) * 1 +
                    COALESCE(cv_agg.content_views, 0) * 1 +
                    COALESCE(rx.reaction_count, 0) * 3 +
                    COALESCE(bk_agg.rsvps, 0) * 5 +
                    COALESCE(ci.checkins, 0) * 5 +
                    COALESCE(ns.new_subs, 0) * 10 +
                    COALESCE(cp.posts, 0) * 8
                ) AS raw_score,
                CASE WHEN COALESCE(s.cnt, 0) > 0 THEN ROUND(
                    (
                        COALESCE(pg.page_views, 0) * 1 +
                        COALESCE(cv_agg.content_views, 0) * 1 +
                        COALESCE(rx.reaction_count, 0) * 3 +
                        COALESCE(bk_agg.rsvps, 0) * 5 +
                        COALESCE(ci.checkins, 0) * 5 +
                        COALESCE(ns.new_subs, 0) * 10 +
                        COALESCE(cp.posts, 0) * 8
                    ) / s.cnt, 1
                ) ELSE 0 END AS per_member
         FROM $mt m
         LEFT JOIN (SELECT mosque_id, COUNT(*) AS cnt FROM $sub WHERE status = 'active' GROUP BY mosque_id) s ON s.mosque_id = m.id
         LEFT JOIN (SELECT mosque_id, SUM(view_count) AS page_views FROM $mv WHERE view_date >= '$since' GROUP BY mosque_id) pg ON pg.mosque_id = m.id
         LEFT JOIN (SELECT mosque_id, SUM(view_count) AS content_views FROM $cv WHERE view_date >= '$since' GROUP BY mosque_id) cv_agg ON cv_agg.mosque_id = m.id
         LEFT JOIN (SELECT c.mosque_id, COUNT(*) AS reaction_count FROM $rt r JOIN $at c ON c.id = r.content_id AND r.content_type = 'announcement' WHERE r.created_at >= '$since' GROUP BY c.mosque_id) rx ON rx.mosque_id = m.id
         LEFT JOIN (SELECT mosque_id, COUNT(*) AS rsvps FROM $bk WHERE created_at >= '$since' GROUP BY mosque_id) bk_agg ON bk_agg.mosque_id = m.id
         LEFT JOIN (SELECT mosque_id, COUNT(*) AS checkins FROM $pt WHERE action = 'check_in' AND created_at >= '$since' GROUP BY mosque_id) ci ON ci.mosque_id = m.id
         LEFT JOIN (SELECT mosque_id, COUNT(*) AS new_subs FROM $sub WHERE subscribed_at >= '$since' GROUP BY mosque_id) ns ON ns.mosque_id = m.id
         LEFT JOIN (
             SELECT mosque_id, COUNT(*) AS posts FROM (
                 SELECT mosque_id FROM $at WHERE published_at >= '$since' AND status = 'published'
                 UNION ALL
                 SELECT mosque_id FROM $ev WHERE created_at >= '$since' AND status = 'published'
             ) combined GROUP BY mosque_id
         ) cp ON cp.mosque_id = m.id
         WHERE m.status = 'active'
           AND COALESCE(s.cnt, 0) BETWEEN {$my_tier['min']} AND {$my_tier['max']}
           {$city_clause}
         HAVING raw_score > 0
         ORDER BY per_member DESC
         LIMIT 50"
    );

    // Find our rank + breakdown
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
                'page_views'     => (int) $m->page_views,
                'content_views'  => (int) $m->content_views,
                'reactions'      => (int) $m->reactions,
                'rsvps'          => (int) $m->rsvps,
                'checkins'       => (int) $m->checkins,
                'new_subs'       => (int) $m->new_subs,
                'content_posted' => (int) $m->content_posted,
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
        // Prayer badges
        [ 'key' => 'first_prayer',    'name' => 'First Step',       'icon' => '🤲', 'desc' => 'Log your first prayer',              'check' => 'prayers >= 1' ],
        [ 'key' => 'all_five',        'name' => 'Complete Day',     'icon' => '✨', 'desc' => 'Log all 5 prayers in one day',        'check' => 'all_five >= 1' ],
        [ 'key' => 'prayer_week',     'name' => 'Devoted Week',     'icon' => '🌟', 'desc' => 'Log prayers 7 days in a row',         'check' => 'streak >= 7' ],
        [ 'key' => 'prayer_month',    'name' => 'Steadfast',        'icon' => '💎', 'desc' => 'Log prayers 30 days in a row',        'check' => 'streak >= 30' ],
        [ 'key' => 'prayer_100',      'name' => 'Century',          'icon' => '💯', 'desc' => 'Log 100 total prayers',               'check' => 'prayers >= 100' ],
        [ 'key' => 'prayer_500',      'name' => 'Mumin',            'icon' => '🕌', 'desc' => 'Log 500 total prayers',               'check' => 'prayers >= 500' ],

        // Quran badges
        [ 'key' => 'quran_first',     'name' => 'First Page',       'icon' => '📖', 'desc' => 'Read your first page of Quran',       'check' => 'quran >= 1' ],
        [ 'key' => 'quran_juz',       'name' => 'Juz Complete',     'icon' => '📗', 'desc' => 'Read 20 pages (1 Juz)',               'check' => 'quran >= 20' ],
        [ 'key' => 'quran_100',       'name' => 'Quran Explorer',   'icon' => '📚', 'desc' => 'Read 100 pages total',                'check' => 'quran >= 100' ],

        // Habit badges
        [ 'key' => 'dhikr_7',         'name' => 'Remembrance',      'icon' => '📿', 'desc' => 'Log dhikr 7 days',                    'check' => 'dhikr_days >= 7' ],
        [ 'key' => 'fasting_3',       'name' => 'Sunnah Faster',    'icon' => '🌙', 'desc' => 'Log 3 voluntary fasts',               'check' => 'fasting_days >= 3' ],
        [ 'key' => 'charity_5',       'name' => 'Generous Heart',   'icon' => '💝', 'desc' => 'Log charity 5 times',                 'check' => 'charity_days >= 5' ],
        [ 'key' => 'good_deeds_10',   'name' => 'Doer of Good',     'icon' => '⭐', 'desc' => 'Log 10 good deeds',                   'check' => 'good_deeds >= 10' ],

        // Community badges
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
