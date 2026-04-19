<?php
/**
 * Plugin Name: YourJannah — Gamification
 * Description: Points, streaks, masjid levels, leagues, badges, milestones, and head-to-head challenges.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core plugin for YNJ_DB)
 *
 * @package YNJ_Gamification
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_GAMIFICATION_VERSION', '1.0.0' );
define( 'YNJ_GAMIFICATION_DIR', plugin_dir_path( __FILE__ ) );

// ── Require core plugin ──
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>YourJannah Gamification</strong> requires the <strong>YourJannah</strong> core plugin.</p></div>';
        } );
        return;
    }

    // Load classes
    require_once YNJ_GAMIFICATION_DIR . 'inc/class-ynj-levels.php';
    require_once YNJ_GAMIFICATION_DIR . 'inc/class-ynj-leagues.php';
    require_once YNJ_GAMIFICATION_DIR . 'inc/class-ynj-streaks.php';
    require_once YNJ_GAMIFICATION_DIR . 'inc/class-ynj-badges.php';

    if ( ! class_exists( 'YNJ_API_Points' ) ) {
        require_once YNJ_GAMIFICATION_DIR . 'api/class-ynj-api-points.php';
    }

    // ── Register global helper functions (backward compat) ──
    // These wrap the class methods so existing code keeps working.

    if ( ! function_exists( 'ynj_get_masjid_level' ) ) {
        function ynj_get_masjid_level( $total_dhikr ) {
            return YNJ_Levels::get_masjid_level( $total_dhikr );
        }
    }

    if ( ! function_exists( 'ynj_get_league_tier' ) ) {
        function ynj_get_league_tier( $member_count ) {
            return YNJ_Leagues::get_tier( $member_count );
        }
    }

    if ( ! function_exists( 'ynj_get_league_standings' ) ) {
        function ynj_get_league_standings( $mosque_id, $city = null, $days = 7 ) {
            return YNJ_Leagues::get_standings( $mosque_id, $city, $days );
        }
    }

    if ( ! function_exists( 'ynj_get_badge_definitions' ) ) {
        function ynj_get_badge_definitions() {
            return YNJ_Badges::definitions();
        }
    }

    if ( ! function_exists( 'ynj_check_badges' ) ) {
        function ynj_check_badges( $user_id, $mosque_id ) {
            return YNJ_Badges::check( $user_id, $mosque_id );
        }
    }

    if ( ! function_exists( 'ynj_get_user_badges' ) ) {
        function ynj_get_user_badges( $user_id ) {
            return YNJ_Badges::get_user_badges( $user_id );
        }
    }

    if ( ! function_exists( 'ynj_whos_at_masjid' ) ) {
        function ynj_whos_at_masjid( $mosque_id, $hours = 2 ) {
            return YNJ_Leagues::whos_at_masjid( $mosque_id, $hours );
        }
    }

    if ( ! function_exists( 'ynj_personal_impact' ) ) {
        function ynj_personal_impact( $user_id, $mosque_id, $days = 7 ) {
            return YNJ_Leagues::personal_impact( $user_id, $mosque_id, $days );
        }
    }

    if ( ! function_exists( 'ynj_get_h2h_challenge' ) ) {
        function ynj_get_h2h_challenge( $mosque_id ) {
            return YNJ_Leagues::get_h2h_challenge( $mosque_id );
        }
    }

    if ( ! function_exists( 'ynj_generate_h2h_challenges' ) ) {
        function ynj_generate_h2h_challenges() {
            return YNJ_Leagues::generate_h2h_challenges();
        }
    }

    if ( ! function_exists( 'ynj_fajr_counter' ) ) {
        function ynj_fajr_counter( $mosque_id ) {
            return YNJ_Streaks::fajr_counter( $mosque_id );
        }
    }

    if ( ! function_exists( 'ynj_check_milestones' ) ) {
        function ynj_check_milestones( $mosque_id ) {
            return YNJ_Badges::check_milestones( $mosque_id );
        }
    }

    if ( ! function_exists( 'ynj_get_latest_milestone' ) ) {
        function ynj_get_latest_milestone( $mosque_id ) {
            return YNJ_Badges::get_latest_milestone( $mosque_id );
        }
    }

    if ( ! function_exists( 'ynj_get_congregation_points' ) ) {
        function ynj_get_congregation_points( $mosque_id, $days = 7 ) {
            return YNJ_Leagues::get_congregation_points( $mosque_id, $days );
        }
    }

    // ── Provide gamification data to HUD via filter ──
    add_filter( 'ynj_hud_gamification_data', [ 'YNJ_Levels', 'filter_hud_data' ], 10, 3 );

    // WP Admin pages.
    if ( is_admin() ) {
        require_once YNJ_GAMIFICATION_DIR . 'inc/class-ynj-gamification-admin.php';
        YNJ_Gamification_Admin::init();
    }

}, 15 ); // priority 15 = after yn-jannah loads at 10
