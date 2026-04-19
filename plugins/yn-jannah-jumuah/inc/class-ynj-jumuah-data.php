<?php
/**
 * Jumuah Times Data Layer.
 * @package YNJ_Jumuah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Jumuah_Data {

    public static function get_times( $mosque_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'jumuah_times' ) . " WHERE mosque_id = %d ORDER BY slot_order ASC",
            (int) $mosque_id
        ) ) ?: [];
    }

    public static function get_all_mosques_jumuah() {
        global $wpdb;
        $jt = YNJ_DB::table( 'jumuah_times' );
        $mt = YNJ_DB::table( 'mosques' );

        return $wpdb->get_results(
            "SELECT j.*, m.name AS mosque_name, m.city, m.postcode, m.slug
             FROM $jt j
             JOIN $mt m ON m.id = j.mosque_id
             WHERE m.status IN ('active','unclaimed')
             ORDER BY m.name ASC, j.slot_order ASC"
        ) ?: [];
    }

    public static function create_slot( $mosque_id, $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'jumuah_times' );
        $max_order = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(slot_order) FROM $t WHERE mosque_id = %d", (int) $mosque_id ) );

        return $wpdb->insert( $t, [
            'mosque_id'    => (int) $mosque_id,
            'slot_order'   => $max_order + 1,
            'khutbah_time' => sanitize_text_field( $data['khutbah_time'] ?? '' ),
            'salah_time'   => sanitize_text_field( $data['salah_time'] ?? '' ),
            'language'     => sanitize_text_field( $data['language'] ?? 'English' ),
            'notes'        => sanitize_text_field( $data['notes'] ?? '' ),
        ] );
    }

    public static function update_slot( $id, $data ) {
        global $wpdb;
        $update = [];
        foreach ( [ 'khutbah_time', 'salah_time', 'language', 'notes', 'slot_order' ] as $k ) {
            if ( isset( $data[ $k ] ) ) $update[ $k ] = sanitize_text_field( $data[ $k ] );
        }
        if ( empty( $update ) ) return false;
        return $wpdb->update( YNJ_DB::table( 'jumuah_times' ), $update, [ 'id' => (int) $id ] );
    }

    public static function delete_slot( $id ) {
        global $wpdb;
        return $wpdb->delete( YNJ_DB::table( 'jumuah_times' ), [ 'id' => (int) $id ] );
    }

    public static function search( $query ) {
        global $wpdb;
        $jt = YNJ_DB::table( 'jumuah_times' );
        $mt = YNJ_DB::table( 'mosques' );
        $like = '%' . $wpdb->esc_like( sanitize_text_field( $query ) ) . '%';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT j.*, m.name AS mosque_name, m.city, m.postcode, m.slug
             FROM $jt j JOIN $mt m ON m.id = j.mosque_id
             WHERE m.status IN ('active','unclaimed') AND (m.name LIKE %s OR m.city LIKE %s OR m.postcode LIKE %s)
             ORDER BY m.name ASC, j.slot_order ASC",
            $like, $like, $like
        ) ) ?: [];
    }
}
