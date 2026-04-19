<?php
/**
 * Check-ins Admin — most active members, stats.
 * @package YNJ_Checkins
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Checkins_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
    }

    public static function register_menus() {
        add_menu_page( 'Check-ins', 'Check-ins', 'manage_options', 'ynj-checkins', [ __CLASS__, 'page_checkins' ], 'dashicons-location', 37 );
    }

    public static function page_checkins() {
        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        $stats = YNJ_Checkins_Data::get_stats( $mosque_id );
        $top = YNJ_Checkins_Data::get_most_active( $mosque_id, 30 );

        echo '<div class="wrap"><h1>Check-ins</h1>';

        // Mosque filter
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY name" );
        echo '<form method="get" style="margin-bottom:16px;"><input type="hidden" name="page" value="ynj-checkins">';
        echo '<select name="mosque_id" onchange="this.form.submit()"><option value="">All Mosques</option>';
        foreach ( $mosques as $m ) {
            printf( '<option value="%d"%s>%s</option>', $m->id, $mosque_id === (int) $m->id ? ' selected' : '', esc_html( $m->name ) );
        }
        echo '</select></form>';

        // Stats cards
        echo '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">';
        foreach ( [
            [ 'Today', $stats['today'], '#287e61' ],
            [ 'This Week', $stats['this_week'], '#00ADEF' ],
            [ 'This Month', $stats['this_month'], '#7c3aed' ],
            [ 'All Time', $stats['total'], '#f59e0b' ],
        ] as $s ) {
            echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:16px;text-align:center;">';
            echo '<div style="font-size:28px;font-weight:900;color:' . $s[2] . ';">' . number_format( $s[1] ) . '</div>';
            echo '<div style="font-size:12px;color:#666;">' . $s[0] . '</div></div>';
        }
        echo '</div>';

        // Most active table
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;">';
        echo '<h2 style="margin-top:0;">Most Active Members (Last 30 Days)</h2>';

        if ( empty( $top ) ) {
            echo '<p style="color:#999;">No check-ins recorded.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>#</th><th>Name</th><th>Email</th><th>Mosque</th><th>Check-ins</th><th>Points Earned</th><th>Last Check-in</th>';
            echo '</tr></thead><tbody>';

            foreach ( $top as $i => $m ) {
                $rank = $i + 1;
                $medal = $rank <= 3 ? [ '🥇', '🥈', '🥉' ][ $rank - 1 ] : '#' . $rank;
                echo '<tr>';
                echo '<td style="font-weight:900;font-size:16px;">' . $medal . '</td>';
                echo '<td><strong>' . esc_html( $m->display_name ?: '(no name)' ) . '</strong></td>';
                echo '<td>' . esc_html( $m->email ?: '' ) . '</td>';
                echo '<td>' . esc_html( $m->mosque_name ?: '' ) . '</td>';
                echo '<td style="font-weight:700;color:#287e61;">' . (int) $m->checkin_count . '</td>';
                echo '<td>' . number_format( (int) $m->total_points ) . '</td>';
                echo '<td>' . esc_html( $m->last_checkin ?? '' ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';
    }
}
