<?php
/**
 * Masjid Levels — XP-based progression from dhikr.
 *
 * 10 levels from Seedling to Heavenly. Each level needs more dhikr.
 *
 * @package YNJ_Gamification
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Levels {

    /**
     * Level definitions.
     */
    private static $levels = [
        [ 'level' => 1,  'name' => 'Seedling',       'icon' => '&#x1F331;', 'xp' => 0 ],
        [ 'level' => 2,  'name' => 'Sprout',          'icon' => '&#x1F33F;', 'xp' => 25 ],
        [ 'level' => 3,  'name' => 'Rising Star',     'icon' => '&#x1F31F;', 'xp' => 75 ],
        [ 'level' => 4,  'name' => 'Shining Light',   'icon' => '&#x2728;',  'xp' => 150 ],
        [ 'level' => 5,  'name' => 'Blessed',         'icon' => '&#x1F54C;', 'xp' => 300 ],
        [ 'level' => 6,  'name' => 'Radiant',         'icon' => '&#x1F4AB;', 'xp' => 600 ],
        [ 'level' => 7,  'name' => 'Luminous',        'icon' => '&#x1F320;', 'xp' => 1200 ],
        [ 'level' => 8,  'name' => 'Majestic',        'icon' => '&#x1F451;', 'xp' => 2500 ],
        [ 'level' => 9,  'name' => 'Glorious',        'icon' => '&#x1F3C6;', 'xp' => 5000 ],
        [ 'level' => 10, 'name' => 'Heavenly',        'icon' => '&#x1F30D;', 'xp' => 10000 ],
    ];

    /**
     * Get masjid level based on total dhikr count.
     *
     * @param int $total_dhikr
     * @return array
     */
    public static function get_masjid_level( $total_dhikr ) {
        $levels = self::$levels;
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

    /**
     * Provide gamification data to the HUD via filter.
     *
     * @param array $data    Existing HUD data
     * @param int   $ynj_uid YNJ user ID
     * @param int   $mosque_id Favourite mosque ID
     * @return array
     */
    public static function filter_hud_data( $data, $ynj_uid, $mosque_id ) {
        if ( ! $mosque_id || ! class_exists( 'YNJ_DB' ) ) return $data;

        global $wpdb;

        // Total points
        $data['points'] = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT total_points FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid
        ) );

        // Total dhikr for level
        $dhikr_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'ibadah_logs' ) . " WHERE mosque_id = %d AND dhikr = 1",
            $mosque_id
        ) );

        // Level
        $data['level'] = self::get_masjid_level( $dhikr_total );
        $data['dhikr_total'] = $dhikr_total;

        // Members
        $data['members'] = 1 + (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'user_subscriptions' ) . " WHERE mosque_id = %d AND status = 'active'",
            $mosque_id
        ) );

        // League
        $data['league'] = YNJ_Leagues::get_standings( $mosque_id, null, 7 );
        $data['rank'] = $data['league']['rank'];

        // Streak
        $data['streak'] = YNJ_Streaks::get_mosque_streak( $mosque_id );

        // Today's 5 dhikr
        if ( class_exists( 'YNJ_API_Points' ) ) {
            $data['five_dhikr'] = YNJ_API_Points::get_todays_five();
        }

        // H2H challenge
        $data['h2h'] = YNJ_Leagues::get_h2h_challenge( $mosque_id );

        return $data;
    }
}
