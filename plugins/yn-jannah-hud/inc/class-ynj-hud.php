<?php
/**
 * HUD Data Provider — gathers all data and renders templates.
 *
 * @package YNJ_HUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_HUD {

    /**
     * Render the full HUD — called via wp_body_open action.
     */
    public static function render() {
        $data = self::get_data();

        if ( $data['status'] === 'guest' ) {
            include YNJ_HUD_DIR . 'templates/hud-guest.php';
        } else {
            include YNJ_HUD_DIR . 'templates/hud-member.php';
            include YNJ_HUD_DIR . 'templates/hud-popups.php';
        }

        // Cart drawer (both guest + member)
        include YNJ_HUD_DIR . 'templates/cart-drawer.php';

        // Auth modal (guests only — blue onboard with GPS + PIN)
        include YNJ_HUD_DIR . 'templates/auth-modal.php';

        // Mosque selector modal (both guest + member)
        include YNJ_HUD_DIR . 'templates/mosque-modal.php';

        // Inline script to set --ynj-hud-h for sticky header positioning
        echo '<script>(function(){var h=document.querySelector(".ynj-hud-wrap");if(!h)return;var s=getComputedStyle(h);var t=(parseInt(s.top,10)||0)+h.offsetHeight;document.documentElement.style.setProperty("--ynj-hud-h",t+"px");})();</script>';
    }

    /**
     * Gather all HUD data.
     *
     * @return array
     */
    public static function get_data() {
        $data = [
            'status'      => 'guest',
            'name'        => '',
            'initial'     => '?',
            'points'      => 0,
            'rank'        => 0,
            'tier'        => [ 'key' => 'emerging', 'name' => 'Emerging', 'icon' => '&#x1F331;' ],
            'streak'      => 0,
            'mosque'      => null,
            'mosque_slug' => '',
            'mosque_url'  => home_url( '/' ),
            'league_url'  => '#',
            'level'       => null,
            'dhikr_total' => 0,
            'members'     => 0,
            'five_dhikr'  => [],
            'done_flags'  => [],
            'done_count'  => 0,
            'all_done'    => false,
            'league'      => null,
            'h2h'         => null,
        ];

        if ( ! is_user_logged_in() ) return $data;

        $data['status'] = 'member';
        $data['name']   = wp_get_current_user()->display_name;
        $data['initial'] = strtoupper( mb_substr( $data['name'] ?: 'U', 0, 1 ) );

        $wp_uid  = get_current_user_id();
        $ynj_uid = (int) get_user_meta( $wp_uid, 'ynj_user_id', true );

        if ( ! $ynj_uid || ! class_exists( 'YNJ_DB' ) ) return $data;

        global $wpdb;

        // Favourite mosque
        $fav_id = (int) get_user_meta( $wp_uid, 'ynj_favourite_mosque_id', true );
        if ( ! $fav_id ) {
            $fav_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT favourite_mosque_id FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid
            ) );
        }

        if ( $fav_id ) {
            $data['mosque'] = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, name, slug, city FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $fav_id
            ) );
            if ( $data['mosque'] ) {
                $data['mosque_slug'] = $data['mosque']->slug;
                $data['mosque_url']  = home_url( '/mosque/' . $data['mosque_slug'] );
                $data['league_url']  = home_url( '/mosque/' . $data['mosque_slug'] . '#mosque-league-table' );
            }
        }

        // ── Gamification data (via filter — provided by yn-jannah-gamification) ──
        $mosque_id = $data['mosque'] ? (int) $data['mosque']->id : 0;
        $gdata = apply_filters( 'ynj_hud_gamification_data', [], $ynj_uid, $mosque_id );

        if ( ! empty( $gdata ) ) {
            $data['points']      = $gdata['points'] ?? 0;
            $data['level']       = $gdata['level'] ?? null;
            $data['dhikr_total'] = $gdata['dhikr_total'] ?? 0;
            $data['members']     = $gdata['members'] ?? 0;
            $data['league']      = $gdata['league'] ?? null;
            $data['rank']        = $gdata['rank'] ?? 0;
            $data['streak']      = $gdata['streak'] ?? 0;
            $data['five_dhikr']  = $gdata['five_dhikr'] ?? [];
            $data['h2h']         = $gdata['h2h'] ?? null;

            if ( $data['league'] ) {
                $data['tier'] = $data['league']['tier'];
            }
        } else {
            // Fallback: minimal data without gamification plugin
            $data['points'] = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT total_points FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d", $ynj_uid
            ) );
        }

        // Dhikr done flags (for popup)
        if ( $ynj_uid && ! empty( $data['five_dhikr'] ) ) {
            for ( $i = 0; $i < 5; $i++ ) {
                $data['done_flags'][ $i ] = (bool) get_transient( 'ynj_dhikr_' . $ynj_uid . '_' . date( 'Y-m-d' ) . '_' . $i );
                if ( $data['done_flags'][ $i ] ) $data['done_count']++;
            }
            $data['all_done'] = $data['done_count'] >= 5;
        }

        return $data;
    }

    /**
     * Get data to pass to JavaScript via wp_localize_script.
     */
    public static function get_js_data() {
        $data = self::get_data();

        // First dhikr action text (for error fallback in JS)
        $dhikr_text  = 'Ameen';
        $dhikr_pts   = 0;
        if ( ! empty( $data['five_dhikr'] ) ) {
            $first = $data['five_dhikr'][0] ?? null;
            if ( $first ) {
                $dhikr_text = $first['action_text'] ?? 'Ameen';
                $dhikr_pts  = $first['points'] ?? 0;
            }
        }

        return [
            'restUrl'         => rest_url( 'ynj/v1/' ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'isLoggedIn'      => is_user_logged_in(),
            'currentRank'     => (int) $data['rank'],
            'mosqueName'      => $data['mosque'] ? $data['mosque']->name : '',
            'quranVerse'      => __( 'Truly, in the remembrance of Allah do hearts find rest.', 'yourjannah' ),
            'quranRef'        => __( 'Quran 13:28', 'yourjannah' ),
            'rankedUpText'    => __( 'ranked up!', 'yourjannah' ),
            'dhikrActionText' => $dhikr_text,
            'dhikrPoints'     => (int) $dhikr_pts,
        ];
    }
}
