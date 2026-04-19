<?php
/**
 * Mosques Data Layer — PHP-first database access for mosque profiles,
 * prayer times, jumuah/eid, geolocation search, view tracking.
 *
 * @package YNJ_Mosques
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Mosques {

    /**
     * Get mosque by slug (cached).
     */
    public static function get_by_slug( $slug ) {
        if ( ! $slug || ! class_exists( 'YNJ_DB' ) ) return null;
        $cache_key = 'mosque_' . sanitize_key( $slug );
        $cached = wp_cache_get( $cache_key, 'ynj' );
        if ( $cached ) return $cached;

        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE slug = %s AND status IN ('active','unclaimed')",
            sanitize_title( $slug )
        ) );
        if ( $mosque ) wp_cache_set( $cache_key, $mosque, 'ynj', 300 );
        return $mosque;
    }

    /**
     * Get mosque by ID (cached).
     */
    public static function get_by_id( $id ) {
        if ( ! $id || ! class_exists( 'YNJ_DB' ) ) return null;
        $cache_key = 'mosque_id_' . (int) $id;
        $cached = wp_cache_get( $cache_key, 'ynj' );
        if ( $cached ) return $cached;

        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", (int) $id
        ) );
        if ( $mosque ) wp_cache_set( $cache_key, $mosque, 'ynj', 300 );
        return $mosque;
    }

    /**
     * Search mosques by name, city, or postcode.
     */
    public static function search( $query, $limit = 10 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'mosques' );
        $like = '%' . $wpdb->esc_like( sanitize_text_field( $query ) ) . '%';
        $limit = min( absint( $limit ), 50 );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, slug, city, postcode, address, latitude, longitude
             FROM $t WHERE status IN ('active','unclaimed')
             AND (name LIKE %s OR city LIKE %s OR postcode LIKE %s)
             ORDER BY name ASC LIMIT %d",
            $like, $like, $like, $limit
        ) ) ?: [];
    }

    /**
     * Get nearest mosques by GPS coordinates (haversine).
     */
    public static function get_nearest( $lat, $lng, $limit = 5 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'mosques' );
        $lat = (float) $lat;
        $lng = (float) $lng;
        $limit = min( absint( $limit ), 20 );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, slug, city, postcode, address, latitude, longitude,
                    ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
             FROM $t WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
             ORDER BY distance ASC LIMIT %d",
            $lat, $lng, $lat, $limit
        ) ) ?: [];
    }

    /**
     * Get prayer times for a mosque on a given date.
     */
    public static function get_prayer_times( $mosque_id, $date = null ) {
        global $wpdb;
        $t = YNJ_DB::table( 'prayer_times' );
        $date = $date ?: date( 'Y-m-d' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $t WHERE mosque_id = %d AND date = %s",
            (int) $mosque_id, sanitize_text_field( $date )
        ) );
    }

    /**
     * Update prayer times for a mosque on a given date.
     */
    public static function update_prayer_times( $mosque_id, $date, $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'prayer_times' );
        $mosque_id = (int) $mosque_id;
        $date = sanitize_text_field( $date );

        $fields = [];
        $allowed = [ 'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha',
                     'fajr_jamat', 'dhuhr_jamat', 'asr_jamat', 'maghrib_jamat', 'isha_jamat' ];
        foreach ( $allowed as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $fields[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }
        if ( empty( $fields ) ) return false;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE mosque_id = %d AND date = %s", $mosque_id, $date
        ) );

        if ( $existing ) {
            return $wpdb->update( $t, $fields, [ 'id' => (int) $existing ] );
        } else {
            $fields['mosque_id'] = $mosque_id;
            $fields['date'] = $date;
            return $wpdb->insert( $t, $fields );
        }
    }

    /**
     * Get jumuah time slots for a mosque.
     */
    public static function get_jumuah_times( $mosque_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'jumuah_times' ) . " WHERE mosque_id = %d ORDER BY slot_order ASC",
            (int) $mosque_id
        ) ) ?: [];
    }

    /**
     * Get eid times for a mosque.
     */
    public static function get_eid_times( $mosque_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'eid_times' ) . " WHERE mosque_id = %d ORDER BY eid_date ASC",
            (int) $mosque_id
        ) ) ?: [];
    }

    /**
     * Track a page view for a mosque.
     */
    public static function track_view( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'mosque_views' );
        $today = date( 'Y-m-d' );
        $mosque_id = (int) $mosque_id;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE mosque_id = %d AND view_date = %s", $mosque_id, $today
        ) );

        if ( $existing ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE $t SET view_count = view_count + 1 WHERE id = %d", $existing
            ) );
        } else {
            $wpdb->insert( $t, [
                'mosque_id'  => $mosque_id,
                'view_date'  => $today,
                'view_count' => 1,
            ] );
        }
    }

    /**
     * Get view stats for a mosque.
     */
    public static function get_view_count( $mosque_id, $days = 7 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'mosque_views' );
        $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(view_count), 0) FROM $t WHERE mosque_id = %d AND view_date >= %s",
            (int) $mosque_id, $since
        ) );
    }

    /**
     * Get member count for a mosque.
     */
    public static function get_member_count( $mosque_id ) {
        global $wpdb;
        return 1 + (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'user_subscriptions' ) . " WHERE mosque_id = %d AND status = 'active'",
            (int) $mosque_id
        ) );
    }

    /**
     * Get a user's active subscription for a mosque.
     *
     * @param  int         $user_id    YNJ user ID.
     * @param  int         $mosque_id  Mosque ID.
     * @return object|null             Row with is_member, is_primary, etc. or null.
     */
    public static function get_user_subscription( $user_id, $mosque_id ) {
        global $wpdb;
        $st = YNJ_DB::table( 'user_subscriptions' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $st WHERE user_id = %d AND mosque_id = %d AND status = 'active'",
            absint( $user_id ), absint( $mosque_id )
        ) );
    }

    /**
     * Get all active mosques (for sitemap).
     */
    public static function get_all_active( $limit = 5000 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, slug, city, postcode, updated_at FROM " . YNJ_DB::table( 'mosques' ) . "
             WHERE status IN ('active','unclaimed') ORDER BY name ASC LIMIT %d",
            absint( $limit )
        ) ) ?: [];
    }
}
