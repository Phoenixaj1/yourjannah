<?php
/**
 * Jumuah Admin — all mosques' jumuah times in one table.
 * @package YNJ_Jumuah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Jumuah_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
    }

    public static function register_menus() {
        add_menu_page( 'Jumuah Times', 'Jumuah', 'manage_options', 'ynj-jumuah', [ __CLASS__, 'page_jumuah' ], 'dashicons-megaphone', 36 );
    }

    public static function page_jumuah() {
        $all = YNJ_Jumuah_Data::get_all_mosques_jumuah();

        // Group by mosque
        $by_mosque = [];
        foreach ( $all as $j ) {
            $by_mosque[ $j->mosque_name ][] = $j;
        }

        echo '<div class="wrap"><h1>Jumuah Times — All Mosques</h1>';
        echo '<p>' . count( $by_mosque ) . ' mosques with Jumuah times configured.</p>';

        echo '<table class="widefat striped" style="font-size:13px;">';
        echo '<thead><tr><th>Mosque</th><th>City</th><th>Khutbah 1</th><th>Salah 1</th><th>Language</th><th>Khutbah 2</th><th>Salah 2</th><th>Language</th><th>Notes</th></tr></thead><tbody>';

        foreach ( $by_mosque as $name => $slots ) {
            echo '<tr>';
            echo '<td><strong>' . esc_html( $name ) . '</strong></td>';
            echo '<td>' . esc_html( $slots[0]->city ?? '' ) . '</td>';

            // Slot 1
            echo '<td style="font-weight:700;">' . esc_html( $slots[0]->khutbah_time ? substr( $slots[0]->khutbah_time, 0, 5 ) : '—' ) . '</td>';
            echo '<td style="font-weight:700;color:#287e61;">' . esc_html( $slots[0]->salah_time ? substr( $slots[0]->salah_time, 0, 5 ) : '—' ) . '</td>';
            echo '<td>' . esc_html( $slots[0]->language ?? '' ) . '</td>';

            // Slot 2
            if ( isset( $slots[1] ) ) {
                echo '<td style="font-weight:700;">' . esc_html( substr( $slots[1]->khutbah_time, 0, 5 ) ) . '</td>';
                echo '<td style="font-weight:700;color:#287e61;">' . esc_html( substr( $slots[1]->salah_time, 0, 5 ) ) . '</td>';
                echo '<td>' . esc_html( $slots[1]->language ?? '' ) . '</td>';
            } else {
                echo '<td><span style="color:#ccc;">—</span></td><td><span style="color:#ccc;">—</span></td><td></td>';
            }

            $notes = array_filter( array_map( function( $s ) { return $s->notes ?? ''; }, $slots ) );
            echo '<td style="font-size:11px;color:#666;">' . esc_html( implode( '; ', $notes ) ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
