<?php
/**
 * Prayer Times Data Layer — multi-masjid queries.
 * @package YNJ_Prayer_Times
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Prayer_Times_Data {

    public static function get_times( $mosque_id, $date = null ) {
        global $wpdb;
        $date = $date ?: date( 'Y-m-d' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'prayer_times' ) . " WHERE mosque_id = %d AND date = %s",
            (int) $mosque_id, sanitize_text_field( $date )
        ) );
    }

    public static function get_all_mosques_times( $date = null ) {
        global $wpdb;
        $pt = YNJ_DB::table( 'prayer_times' );
        $mt = YNJ_DB::table( 'mosques' );
        $date = $date ?: date( 'Y-m-d' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT m.id, m.name, m.city,
                    p.fajr, p.sunrise, p.dhuhr, p.asr, p.maghrib, p.isha,
                    p.fajr_jamat, p.dhuhr_jamat, p.asr_jamat, p.maghrib_jamat, p.isha_jamat
             FROM $mt m
             LEFT JOIN $pt p ON p.mosque_id = m.id AND p.date = %s
             WHERE m.status IN ('active','unclaimed')
             ORDER BY m.name ASC",
            $date
        ) ) ?: [];
    }

    public static function update_times( $mosque_id, $date, $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'prayer_times' );
        $fields = [];
        foreach ( [ 'fajr','sunrise','dhuhr','asr','maghrib','isha','fajr_jamat','dhuhr_jamat','asr_jamat','maghrib_jamat','isha_jamat' ] as $k ) {
            if ( isset( $data[ $k ] ) ) $fields[ $k ] = sanitize_text_field( $data[ $k ] );
        }
        if ( empty( $fields ) ) return false;

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE mosque_id = %d AND date = %s", (int) $mosque_id, $date ) );
        if ( $exists ) {
            return $wpdb->update( $t, $fields, [ 'id' => (int) $exists ] );
        } else {
            $fields['mosque_id'] = (int) $mosque_id;
            $fields['date'] = $date;
            return $wpdb->insert( $t, $fields );
        }
    }

    /**
     * Get patterns — which mosques have similar prayer times.
     */
    public static function get_patterns( $date = null ) {
        $all = self::get_all_mosques_times( $date );
        $patterns = [ 'fajr' => [], 'dhuhr' => [], 'asr' => [], 'maghrib' => [], 'isha' => [] ];
        foreach ( $all as $m ) {
            foreach ( $patterns as $prayer => &$times ) {
                $t = $m->$prayer ?? '';
                if ( $t ) {
                    $rounded = substr( $t, 0, 5 ); // HH:MM
                    $times[ $rounded ][] = $m->name;
                }
            }
        }
        return $patterns;
    }
}
