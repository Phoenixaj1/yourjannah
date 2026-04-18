<?php
/**
 * Madrassah Data Layer — classes, sessions, enrolments.
 *
 * @package YNJ_Madrassah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Madrassah {

    /**
     * Get classes for a mosque.
     */
    public static function get_classes( $mosque_id, $args = [] ) {
        global $wpdb;
        $t = YNJ_DB::table( 'classes' );

        $status = $args['status'] ?? 'active';
        $limit  = (int) ( $args['limit'] ?? 50 );

        $where = $wpdb->prepare( "WHERE mosque_id = %d AND status = %s", $mosque_id, $status );

        return $wpdb->get_results(
            "SELECT * FROM $t $where ORDER BY title ASC LIMIT $limit"
        ) ?: [];
    }

    /**
     * Get a single class.
     */
    public static function get_class( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'classes' ) . " WHERE id = %d", (int) $id
        ) );
    }

    /**
     * Get upcoming classes across all mosques (browse).
     */
    public static function browse( $args = [] ) {
        global $wpdb;
        $ct = YNJ_DB::table( 'classes' );
        $mt = YNJ_DB::table( 'mosques' );

        $limit    = (int) ( $args['limit'] ?? 20 );
        $category = $args['category'] ?? '';
        $lat      = isset( $args['lat'] ) ? (float) $args['lat'] : null;
        $lng      = isset( $args['lng'] ) ? (float) $args['lng'] : null;

        $distance = '';
        $order    = 'c.title ASC';
        if ( $lat && $lng ) {
            $distance = $wpdb->prepare(
                ", ( 6371 * acos( cos(radians(%f)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(m.latitude)) )) AS distance",
                $lat, $lng, $lat
            );
            $order = 'distance ASC';
        }

        $cat_where = $category ? $wpdb->prepare( " AND c.category = %s", $category ) : '';

        return $wpdb->get_results(
            "SELECT c.*, m.name AS mosque_name, m.city AS mosque_city $distance
             FROM $ct c
             LEFT JOIN $mt m ON m.id = c.mosque_id
             WHERE c.status = 'active' $cat_where
             ORDER BY $order LIMIT $limit"
        ) ?: [];
    }

    /**
     * Create a class.
     */
    public static function create_class( $data ) {
        global $wpdb;
        $result = $wpdb->insert( YNJ_DB::table( 'classes' ), [
            'mosque_id'   => (int) $data['mosque_id'],
            'title'       => sanitize_text_field( $data['title'] ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'category'    => sanitize_text_field( $data['category'] ?? '' ),
            'teacher'     => sanitize_text_field( $data['teacher'] ?? '' ),
            'day_of_week' => sanitize_text_field( $data['day_of_week'] ?? '' ),
            'start_time'  => sanitize_text_field( $data['start_time'] ?? '' ),
            'end_time'    => sanitize_text_field( $data['end_time'] ?? '' ),
            'age_group'   => sanitize_text_field( $data['age_group'] ?? '' ),
            'gender'      => sanitize_text_field( $data['gender'] ?? 'mixed' ),
            'max_students'=> (int) ( $data['max_students'] ?? 0 ),
            'fee_pence'   => (int) ( $data['fee_pence'] ?? 0 ),
            'status'      => 'active',
        ] );

        if ( $result ) {
            do_action( 'ynj_new_class', $wpdb->insert_id, $data );
        }
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a class.
     */
    public static function update_class( $id, $data ) {
        global $wpdb;
        $allowed = [ 'title', 'description', 'category', 'teacher', 'day_of_week',
                     'start_time', 'end_time', 'age_group', 'gender', 'max_students',
                     'fee_pence', 'status' ];
        $update = [];
        foreach ( $allowed as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $update[ $key ] = is_int( $data[ $key ] ) ? $data[ $key ] : sanitize_text_field( $data[ $key ] );
            }
        }
        if ( empty( $update ) ) return false;
        return $wpdb->update( YNJ_DB::table( 'classes' ), $update, [ 'id' => (int) $id ] );
    }

    /**
     * Enrol a student in a class.
     */
    public static function enrol( $class_id, $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'enrolments' );

        // Check capacity
        $class = self::get_class( $class_id );
        if ( ! $class ) return new WP_Error( 'not_found', 'Class not found.' );

        if ( $class->max_students > 0 ) {
            $enrolled = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $t WHERE class_id = %d AND status = 'active'", $class_id
            ) );
            if ( $enrolled >= $class->max_students ) {
                return new WP_Error( 'full', 'This class is full.' );
            }
        }

        // Check duplicate
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE class_id = %d AND email = %s AND status = 'active'",
            $class_id, sanitize_email( $data['email'] )
        ) );
        if ( $exists ) return new WP_Error( 'duplicate', 'Already enrolled.' );

        $result = $wpdb->insert( $t, [
            'class_id'  => (int) $class_id,
            'mosque_id' => (int) $class->mosque_id,
            'name'      => sanitize_text_field( $data['name'] ),
            'email'     => sanitize_email( $data['email'] ),
            'phone'     => sanitize_text_field( $data['phone'] ?? '' ),
            'status'    => 'active',
        ] );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get sessions for a class.
     */
    public static function get_sessions( $class_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'class_sessions' ) . " WHERE class_id = %d ORDER BY session_date ASC",
            (int) $class_id
        ) ) ?: [];
    }

    /**
     * Get enrolments for a mosque (admin).
     */
    public static function get_enrolments( $mosque_id, $args = [] ) {
        global $wpdb;
        $et = YNJ_DB::table( 'enrolments' );
        $ct = YNJ_DB::table( 'classes' );

        $limit = (int) ( $args['limit'] ?? 50 );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, c.title AS class_title
             FROM $et e
             LEFT JOIN $ct c ON c.id = e.class_id
             WHERE e.mosque_id = %d
             ORDER BY e.created_at DESC LIMIT %d",
            $mosque_id, $limit
        ) ) ?: [];
    }

    /**
     * Get enrolment count for a class.
     */
    public static function enrolment_count( $class_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'enrolments' ) . " WHERE class_id = %d AND status = 'active'",
            (int) $class_id
        ) );
    }
}
