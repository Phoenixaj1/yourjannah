<?php
/**
 * Badges & Milestones — personal achievements + mosque milestones.
 *
 * @package YNJ_Gamification
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Badges {

    /**
     * All available badge definitions.
     */
    public static function definitions() {
        return [
            [ 'key' => 'first_dhikr',     'name' => 'Mubtadi (Beginner)',     'icon' => "\xF0\x9F\x8C\x9F", 'desc' => 'Say your first remembrance',           'check' => 'dhikr_days >= 1' ],
            [ 'key' => 'dhikr_3',         'name' => 'Taalib (Seeker)',        'icon' => "\xE2\x9C\xA8",     'desc' => 'Remember Allah 3 days',                'check' => 'dhikr_days >= 3' ],
            [ 'key' => 'all_five',        'name' => 'Mukhlif (Devoted)',      'icon' => "\xF0\x9F\xA4\xB2", 'desc' => 'Complete all 5 in one day',            'check' => 'all_five >= 1' ],
            [ 'key' => 'dhikr_7',         'name' => 'Dhakir (Rememberer)',    'icon' => "\xF0\x9F\x93\xBF", 'desc' => 'Remember Allah 7 days',                'check' => 'dhikr_days >= 7' ],
            [ 'key' => 'streak_3',        'name' => 'Murid (Aspirant)',       'icon' => "\xF0\x9F\x95\xAF\xEF\xB8\x8F", 'desc' => '3 consecutive days',       'check' => 'streak >= 3' ],
            [ 'key' => 'streak_7',        'name' => 'Sabir (Patient)',        'icon' => "\xF0\x9F\x8C\xBF", 'desc' => '7 consecutive days',                   'check' => 'streak >= 7' ],
            [ 'key' => 'streak_14',       'name' => 'Mukhlis (Sincere)',      'icon' => "\xF0\x9F\x92\x8E", 'desc' => '14 consecutive days',                  'check' => 'streak >= 14' ],
            [ 'key' => 'streak_30',       'name' => 'Mustaqim (Steadfast)',   'icon' => "\xF0\x9F\x8F\x94\xEF\xB8\x8F", 'desc' => '30 consecutive days',     'check' => 'streak >= 30' ],
            [ 'key' => 'dhikr_14',        'name' => 'Qarib (Near)',           'icon' => "\xF0\x9F\x8C\x99", 'desc' => 'Remember Allah 14 days',               'check' => 'dhikr_days >= 14' ],
            [ 'key' => 'dhikr_30',        'name' => 'Wali (Friend of Allah)','icon' => "\xE2\x98\x80\xEF\xB8\x8F", 'desc' => 'Remember Allah 30 days',       'check' => 'dhikr_days >= 30' ],
            [ 'key' => 'dhikr_100',       'name' => 'Arif (Knower)',          'icon' => "\xF0\x9F\x8C\x95", 'desc' => 'Remember Allah 100 days',              'check' => 'dhikr_days >= 100' ],
            [ 'key' => 'charity_3',       'name' => 'Shakir (Grateful)',      'icon' => "\xF0\x9F\x92\x9D", 'desc' => 'Express gratitude 3 times',            'check' => 'charity_days >= 3' ],
            [ 'key' => 'charity_10',      'name' => 'Shakur (Ever-Grateful)', 'icon' => "\xF0\x9F\x8C\x8A", 'desc' => 'Express gratitude 10 times',           'check' => 'charity_days >= 10' ],
            [ 'key' => 'checkin_first',   'name' => 'First Visit',      'icon' => "\xF0\x9F\x93\x8D", 'desc' => 'Check in at your mosque',             'check' => 'checkins >= 1' ],
            [ 'key' => 'checkin_10',      'name' => 'Regular',          'icon' => "\xF0\x9F\x8F\xA0", 'desc' => 'Check in 10 times',                   'check' => 'checkins >= 10' ],
            [ 'key' => 'checkin_50',      'name' => 'Pillar',           'icon' => "\xF0\x9F\x8F\x9B\xEF\xB8\x8F", 'desc' => 'Check in 50 times',     'check' => 'checkins >= 50' ],
        ];
    }

    /**
     * Check and award any new badges for a user.
     */
    public static function check( $user_id, $mosque_id ) {
        global $wpdb;
        $ib  = YNJ_DB::table( 'ibadah_logs' );
        $pt  = YNJ_DB::table( 'points' );
        $bt  = YNJ_DB::table( 'user_badges' );

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

        $existing = $wpdb->get_col( $wpdb->prepare( "SELECT badge_key FROM $bt WHERE user_id = %d", $user_id ) );
        $new_badges = [];

        foreach ( self::definitions() as $badge ) {
            if ( in_array( $badge['key'], $existing, true ) ) continue;
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
    public static function get_user_badges( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT badge_key, badge_name, badge_icon, earned_at FROM " . YNJ_DB::table( 'user_badges' ) . " WHERE user_id = %d ORDER BY earned_at ASC",
            $user_id
        ) ) ?: [];
    }

    /**
     * Check and return new milestones for a mosque.
     */
    public static function check_milestones( $mosque_id ) {
        global $wpdb;
        $mt  = YNJ_DB::table( 'milestones' );
        $ib  = YNJ_DB::table( 'ibadah_logs' );
        $sub = YNJ_DB::table( 'user_subscriptions' );
        $dt  = YNJ_DB::table( 'donations' );

        $total_prayers       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) FROM $ib WHERE mosque_id = %d", $mosque_id ) );
        $total_pages         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(quran_pages),0) FROM $ib WHERE mosque_id = %d", $mosque_id ) );
        $total_members       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND status = 'active'", $mosque_id ) );
        $total_donations_p   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE mosque_id = %d AND status = 'succeeded'", $mosque_id ) );

        $defs = [
            [ 'key' => 'prayers_100',    'icon' => "\xF0\x9F\xA4\xB2", 'label' => '100 prayers logged',     'check' => $total_prayers,     'threshold' => 100 ],
            [ 'key' => 'prayers_500',    'icon' => "\xF0\x9F\xA4\xB2", 'label' => '500 prayers logged',     'check' => $total_prayers,     'threshold' => 500 ],
            [ 'key' => 'prayers_1000',   'icon' => "\xE2\x9C\xA8",     'label' => '1,000 prayers logged',   'check' => $total_prayers,     'threshold' => 1000 ],
            [ 'key' => 'prayers_5000',   'icon' => "\xF0\x9F\x8C\x9F", 'label' => '5,000 prayers logged',   'check' => $total_prayers,     'threshold' => 5000 ],
            [ 'key' => 'prayers_10000',  'icon' => "\xF0\x9F\x92\x8E", 'label' => '10,000 prayers logged',  'check' => $total_prayers,     'threshold' => 10000 ],
            [ 'key' => 'quran_100',      'icon' => "\xF0\x9F\x93\x96", 'label' => '100 Quran pages read',   'check' => $total_pages,       'threshold' => 100 ],
            [ 'key' => 'quran_500',      'icon' => "\xF0\x9F\x93\x97", 'label' => '500 Quran pages read',   'check' => $total_pages,       'threshold' => 500 ],
            [ 'key' => 'quran_1000',     'icon' => "\xF0\x9F\x93\x9A", 'label' => '1,000 Quran pages read', 'check' => $total_pages,       'threshold' => 1000 ],
            [ 'key' => 'members_10',     'icon' => "\xF0\x9F\x91\xA5", 'label' => '10 members joined',      'check' => $total_members,     'threshold' => 10 ],
            [ 'key' => 'members_50',     'icon' => "\xF0\x9F\x91\xA5", 'label' => '50 members joined',      'check' => $total_members,     'threshold' => 50 ],
            [ 'key' => 'members_100',    'icon' => "\xF0\x9F\x8E\x8A", 'label' => '100 members joined',     'check' => $total_members,     'threshold' => 100 ],
            [ 'key' => 'members_500',    'icon' => "\xF0\x9F\x8F\x86", 'label' => '500 members joined',     'check' => $total_members,     'threshold' => 500 ],
            [ 'key' => 'donations_1000', 'icon' => "\xC2\xA3",         'label' => 'First \xC2\xA31,000',    'check' => $total_donations_p, 'threshold' => 100000 ],
            [ 'key' => 'donations_5000', 'icon' => "\xF0\x9F\x92\xB0", 'label' => '\xC2\xA35,000 donated',  'check' => $total_donations_p, 'threshold' => 500000 ],
        ];

        $existing = $wpdb->get_col( $wpdb->prepare( "SELECT milestone_key FROM $mt WHERE mosque_id = %d", $mosque_id ) );
        $new = [];

        foreach ( $defs as $ms ) {
            if ( in_array( $ms['key'], $existing, true ) ) continue;
            if ( $ms['check'] >= $ms['threshold'] ) {
                $wpdb->insert( $mt, [
                    'mosque_id'       => $mosque_id,
                    'milestone_key'   => $ms['key'],
                    'milestone_value' => $ms['threshold'],
                ] );
                $new[] = $ms;
            }
        }

        return $new;
    }

    /**
     * Get the latest milestone reached.
     */
    public static function get_latest_milestone( $mosque_id ) {
        global $wpdb;
        $mt = YNJ_DB::table( 'milestones' );
        $latest = $wpdb->get_row( $wpdb->prepare(
            "SELECT milestone_key, milestone_value, reached_at FROM $mt WHERE mosque_id = %d ORDER BY reached_at DESC LIMIT 1",
            $mosque_id
        ) );
        if ( ! $latest ) return null;

        $all_defs = [
            'prayers_100' => [ 'icon' => "\xF0\x9F\xA4\xB2", 'label' => '100 prayers' ], 'prayers_500' => [ 'icon' => "\xF0\x9F\xA4\xB2", 'label' => '500 prayers' ],
            'prayers_1000' => [ 'icon' => "\xE2\x9C\xA8", 'label' => '1,000 prayers' ], 'prayers_5000' => [ 'icon' => "\xF0\x9F\x8C\x9F", 'label' => '5,000 prayers' ],
            'prayers_10000' => [ 'icon' => "\xF0\x9F\x92\x8E", 'label' => '10,000 prayers' ],
            'quran_100' => [ 'icon' => "\xF0\x9F\x93\x96", 'label' => '100 Quran pages' ], 'quran_500' => [ 'icon' => "\xF0\x9F\x93\x97", 'label' => '500 Quran pages' ],
            'quran_1000' => [ 'icon' => "\xF0\x9F\x93\x9A", 'label' => '1,000 Quran pages' ],
            'members_10' => [ 'icon' => "\xF0\x9F\x91\xA5", 'label' => '10 members' ], 'members_50' => [ 'icon' => "\xF0\x9F\x91\xA5", 'label' => '50 members' ],
            'members_100' => [ 'icon' => "\xF0\x9F\x8E\x8A", 'label' => '100 members' ], 'members_500' => [ 'icon' => "\xF0\x9F\x8F\x86", 'label' => '500 members' ],
            'donations_1000' => [ 'icon' => "\xC2\xA3", 'label' => '\xC2\xA31,000 donated' ], 'donations_5000' => [ 'icon' => "\xF0\x9F\x92\xB0", 'label' => '\xC2\xA35,000 donated' ],
        ];

        $info = $all_defs[ $latest->milestone_key ] ?? [ 'icon' => "\xF0\x9F\x8E\x89", 'label' => 'Milestone' ];
        return [
            'icon'  => $info['icon'],
            'label' => $info['label'],
            'ago'   => human_time_diff( strtotime( $latest->reached_at ) ),
        ];
    }
}
