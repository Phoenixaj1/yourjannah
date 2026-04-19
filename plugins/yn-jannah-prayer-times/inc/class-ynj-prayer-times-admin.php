<?php
/**
 * Prayer Times Admin — multi-mosque comparison table.
 * @package YNJ_Prayer_Times
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Prayer_Times_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
    }

    public static function register_menus() {
        add_menu_page( 'Prayer Times', 'Prayer Times', 'manage_options', 'ynj-prayer-times', [ __CLASS__, 'page_comparison' ], 'dashicons-clock', 34 );
    }

    public static function page_comparison() {
        $date = sanitize_text_field( $_GET['date'] ?? date( 'Y-m-d' ) );
        $all = YNJ_Prayer_Times_Data::get_all_mosques_times( $date );

        echo '<div class="wrap"><h1>Prayer Times Comparison</h1>';
        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="ynj-prayer-times">';
        echo '<input type="date" name="date" value="' . esc_attr( $date ) . '" onchange="this.form.submit()" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;">';
        echo '</form>';

        if ( empty( $all ) ) {
            echo '<p>No prayer times data found for ' . esc_html( $date ) . '.</p></div>';
            return;
        }

        echo '<table class="widefat striped" style="font-size:13px;">';
        echo '<thead><tr>';
        echo '<th>Mosque</th><th>City</th>';
        echo '<th>Fajr</th><th>Sunrise</th><th>Dhuhr</th><th>Asr</th><th>Maghrib</th><th>Isha</th>';
        echo '<th style="color:#287e61;">Fajr J</th><th style="color:#287e61;">Dhuhr J</th><th style="color:#287e61;">Asr J</th><th style="color:#287e61;">Maghrib J</th><th style="color:#287e61;">Isha J</th>';
        echo '</tr></thead><tbody>';

        foreach ( $all as $m ) {
            echo '<tr>';
            echo '<td><strong>' . esc_html( $m->name ) . '</strong></td>';
            echo '<td>' . esc_html( $m->city ?: '' ) . '</td>';
            foreach ( [ 'fajr','sunrise','dhuhr','asr','maghrib','isha' ] as $p ) {
                $v = $m->$p ?? '';
                echo '<td>' . ( $v ? esc_html( substr( $v, 0, 5 ) ) : '<span style="color:#ccc;">—</span>' ) . '</td>';
            }
            foreach ( [ 'fajr_jamat','dhuhr_jamat','asr_jamat','maghrib_jamat','isha_jamat' ] as $j ) {
                $v = $m->$j ?? '';
                echo '<td style="color:#287e61;font-weight:600;">' . ( $v ? esc_html( substr( $v, 0, 5 ) ) : '<span style="color:#ccc;">—</span>' ) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
