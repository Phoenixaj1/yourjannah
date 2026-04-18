<?php
/**
 * Directory Data Layer — PHP-first database access.
 *
 * All directory queries go through this class.
 * Templates call these methods directly — no REST round-trips.
 *
 * @package YNJ_Directory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Directory {

    /**
     * Get active businesses for a mosque.
     *
     * @param int   $mosque_id
     * @param array $args { category, limit, offset, order_by }
     * @return array
     */
    public static function get_businesses( $mosque_id, $args = [] ) {
        global $wpdb;
        $t = YNJ_DB::table( 'businesses' );

        $limit    = (int) ( $args['limit'] ?? 50 );
        $offset   = (int) ( $args['offset'] ?? 0 );
        $category = $args['category'] ?? '';
        $status   = $args['status'] ?? 'active';

        $where = $wpdb->prepare( "WHERE mosque_id = %d AND status = %s", $mosque_id, $status );
        if ( $status === 'active' ) {
            $where .= " AND (expires_at IS NULL OR expires_at > NOW())";
        }
        if ( $category ) {
            $where .= $wpdb->prepare( " AND category = %s", $category );
        }

        $order = "ORDER BY monthly_fee_pence DESC, business_name ASC";

        return $wpdb->get_results(
            "SELECT id, mosque_id, business_name, owner_name, category, description,
                    phone, email, website, logo_url, address, postcode,
                    monthly_fee_pence, featured_position, show_phone, show_whatsapp,
                    show_email, show_website, verified, status, created_at
             FROM $t $where $order LIMIT $limit OFFSET $offset"
        ) ?: [];
    }

    /**
     * Get active services for a mosque.
     */
    public static function get_services( $mosque_id, $args = [] ) {
        global $wpdb;
        $t = YNJ_DB::table( 'services' );

        $limit = (int) ( $args['limit'] ?? 50 );
        $type  = $args['service_type'] ?? '';

        $where = $wpdb->prepare( "WHERE mosque_id = %d AND status = 'active'", $mosque_id );
        if ( $type ) {
            $where .= $wpdb->prepare( " AND service_type = %s", $type );
        }

        return $wpdb->get_results(
            "SELECT id, mosque_id, provider_name, phone, email, service_type,
                    description, hourly_rate_pence, area_covered,
                    show_phone, show_whatsapp, show_email, created_at
             FROM $t $where ORDER BY provider_name ASC LIMIT $limit"
        ) ?: [];
    }

    /**
     * Get a single business by ID.
     */
    public static function get_business( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'businesses' ) . " WHERE id = %d", (int) $id
        ) );
    }

    /**
     * Get a single service by ID.
     */
    public static function get_service( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'services' ) . " WHERE id = %d", (int) $id
        ) );
    }

    /**
     * Get combined directory (businesses + services) for a mosque.
     * Used by the directory page for initial PHP render.
     */
    public static function get_directory( $mosque_id ) {
        return [
            'businesses' => self::get_businesses( $mosque_id ),
            'services'   => self::get_services( $mosque_id ),
        ];
    }

    /**
     * Submit a new business listing (pending approval).
     */
    public static function submit_business( $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'businesses' );

        $result = $wpdb->insert( $t, [
            'mosque_id'     => (int) $data['mosque_id'],
            'user_id'       => (int) ( $data['user_id'] ?? 0 ),
            'business_name' => sanitize_text_field( $data['business_name'] ),
            'owner_name'    => sanitize_text_field( $data['owner_name'] ?? '' ),
            'category'      => sanitize_text_field( $data['category'] ?? '' ),
            'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
            'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
            'email'         => sanitize_email( $data['email'] ?? '' ),
            'website'       => esc_url_raw( $data['website'] ?? '' ),
            'address'       => sanitize_text_field( $data['address'] ?? '' ),
            'postcode'      => sanitize_text_field( $data['postcode'] ?? '' ),
            'show_phone'    => (int) ( $data['show_phone'] ?? 1 ),
            'show_whatsapp' => (int) ( $data['show_whatsapp'] ?? 0 ),
            'show_email'    => (int) ( $data['show_email'] ?? 1 ),
            'show_website'  => (int) ( $data['show_website'] ?? 1 ),
            'status'        => 'pending',
        ] );

        if ( ! $result ) return false;

        $id = $wpdb->insert_id;
        do_action( 'ynj_new_sponsor', $id, $data );
        return $id;
    }

    /**
     * Submit a new service listing (pending approval).
     */
    public static function submit_service( $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'services' );

        $result = $wpdb->insert( $t, [
            'mosque_id'       => (int) $data['mosque_id'],
            'user_id'         => (int) ( $data['user_id'] ?? 0 ),
            'provider_name'   => sanitize_text_field( $data['provider_name'] ),
            'phone'           => sanitize_text_field( $data['phone'] ?? '' ),
            'email'           => sanitize_email( $data['email'] ?? '' ),
            'service_type'    => sanitize_text_field( $data['service_type'] ?? '' ),
            'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
            'hourly_rate_pence' => (int) ( $data['hourly_rate_pence'] ?? 0 ),
            'area_covered'    => sanitize_text_field( $data['area_covered'] ?? '' ),
            'show_phone'      => (int) ( $data['show_phone'] ?? 1 ),
            'show_whatsapp'   => (int) ( $data['show_whatsapp'] ?? 0 ),
            'show_email'      => (int) ( $data['show_email'] ?? 1 ),
            'status'          => 'pending',
        ] );

        if ( ! $result ) return false;

        $id = $wpdb->insert_id;
        do_action( 'ynj_new_service_listing', $id, $data );
        return $id;
    }

    /**
     * Admin: update business status (approve/reject/feature).
     */
    public static function update_business( $id, $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'businesses' );

        $allowed = [ 'status', 'verified', 'featured_position', 'monthly_fee_pence', 'expires_at' ];
        $update = [];
        foreach ( $allowed as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $update[ $key ] = $data[ $key ];
            }
        }
        if ( empty( $update ) ) return false;

        return $wpdb->update( $t, $update, [ 'id' => (int) $id ] );
    }

    /**
     * Cross-mosque search for businesses.
     */
    public static function search_businesses( $query, $args = [] ) {
        global $wpdb;
        $bt = YNJ_DB::table( 'businesses' );
        $mt = YNJ_DB::table( 'mosques' );

        $limit = (int) ( $args['limit'] ?? 20 );
        $lat   = isset( $args['lat'] ) ? (float) $args['lat'] : null;
        $lng   = isset( $args['lng'] ) ? (float) $args['lng'] : null;

        $distance_select = '';
        $distance_order  = 'b.business_name ASC';
        $join = "LEFT JOIN $mt m ON m.id = b.mosque_id";

        if ( $lat && $lng ) {
            $distance_select = $wpdb->prepare(
                ", ( 6371 * acos( cos(radians(%f)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(m.latitude)) )) AS distance",
                $lat, $lng, $lat
            );
            $distance_order = 'distance ASC';
        }

        $like = '%' . $wpdb->esc_like( $query ) . '%';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.id, b.business_name, b.category, b.description, b.phone, b.email,
                    b.website, b.logo_url, b.address, b.postcode, b.verified,
                    b.show_phone, b.show_whatsapp, b.show_email, b.show_website,
                    m.name AS mosque_name, m.city AS mosque_city
                    $distance_select
             FROM $bt b $join
             WHERE b.status = 'active' AND (b.expires_at IS NULL OR b.expires_at > NOW())
               AND (b.business_name LIKE %s OR b.description LIKE %s OR b.category LIKE %s)
             ORDER BY $distance_order LIMIT %d",
            $like, $like, $like, $limit
        ) ) ?: [];
    }

    /**
     * Cross-mosque search for services.
     */
    public static function search_services( $query, $args = [] ) {
        global $wpdb;
        $st = YNJ_DB::table( 'services' );
        $mt = YNJ_DB::table( 'mosques' );

        $limit = (int) ( $args['limit'] ?? 20 );
        $lat   = isset( $args['lat'] ) ? (float) $args['lat'] : null;
        $lng   = isset( $args['lng'] ) ? (float) $args['lng'] : null;

        $distance_select = '';
        $distance_order  = 's.provider_name ASC';
        $join = "LEFT JOIN $mt m ON m.id = s.mosque_id";

        if ( $lat && $lng ) {
            $distance_select = $wpdb->prepare(
                ", ( 6371 * acos( cos(radians(%f)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(m.latitude)) )) AS distance",
                $lat, $lng, $lat
            );
            $distance_order = 'distance ASC';
        }

        $like = '%' . $wpdb->esc_like( $query ) . '%';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.provider_name, s.service_type, s.description, s.phone, s.email,
                    s.hourly_rate_pence, s.area_covered,
                    s.show_phone, s.show_whatsapp, s.show_email,
                    m.name AS mosque_name, m.city AS mosque_city
                    $distance_select
             FROM $st s $join
             WHERE s.status = 'active'
               AND (s.provider_name LIKE %s OR s.description LIKE %s OR s.service_type LIKE %s)
             ORDER BY $distance_order LIMIT %d",
            $like, $like, $like, $limit
        ) ) ?: [];
    }

    /**
     * Get enquiries for a mosque.
     */
    public static function get_enquiries( $mosque_id, $args = [] ) {
        global $wpdb;
        $t = YNJ_DB::table( 'enquiries' );

        $status = $args['status'] ?? '';
        $limit  = (int) ( $args['limit'] ?? 50 );

        $where = $wpdb->prepare( "WHERE mosque_id = %d", $mosque_id );
        if ( $status ) {
            $where .= $wpdb->prepare( " AND status = %s", $status );
        }

        return $wpdb->get_results(
            "SELECT * FROM $t $where ORDER BY created_at DESC LIMIT $limit"
        ) ?: [];
    }

    /**
     * Submit a contact enquiry.
     */
    public static function submit_enquiry( $data ) {
        global $wpdb;

        // Rate limit: 3 per minute per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rate_key = 'ynj_enquiry_' . md5( $ip );
        $count = (int) get_transient( $rate_key );
        if ( $count >= 3 ) return new WP_Error( 'rate_limited', 'Too many enquiries. Try again in a minute.', [ 'status' => 429 ] );
        set_transient( $rate_key, $count + 1, 60 );

        $result = $wpdb->insert( YNJ_DB::table( 'enquiries' ), [
            'mosque_id' => (int) $data['mosque_id'],
            'name'      => sanitize_text_field( $data['name'] ),
            'email'     => sanitize_email( $data['email'] ),
            'phone'     => sanitize_text_field( $data['phone'] ?? '' ),
            'subject'   => sanitize_text_field( $data['subject'] ?? '' ),
            'message'   => sanitize_textarea_field( $data['message'] ),
            'type'      => sanitize_text_field( $data['type'] ?? 'general' ),
            'status'    => 'new',
        ] );

        if ( $result ) {
            do_action( 'ynj_new_enquiry', $wpdb->insert_id, $data );
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update enquiry status.
     */
    public static function update_enquiry( $id, $status, $admin_notes = '' ) {
        global $wpdb;
        $update = [ 'status' => $status ];
        if ( $status === 'replied' ) $update['replied_at'] = current_time( 'mysql' );
        if ( $admin_notes ) $update['admin_notes'] = sanitize_textarea_field( $admin_notes );

        return $wpdb->update( YNJ_DB::table( 'enquiries' ), $update, [ 'id' => (int) $id ] );
    }

    /**
     * Count businesses by category for a mosque.
     */
    public static function count_by_category( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'businesses' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT category, COUNT(*) AS count FROM $t
             WHERE mosque_id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())
             GROUP BY category ORDER BY count DESC",
            $mosque_id
        ) ) ?: [];
    }

    /**
     * Get featured/sponsored businesses (highest fee, verified).
     */
    public static function get_featured( $mosque_id, $limit = 5 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'businesses' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, business_name, category, logo_url, website
             FROM $t WHERE mosque_id = %d AND status = 'active' AND verified = 1
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY monthly_fee_pence DESC, featured_position ASC LIMIT %d",
            $mosque_id, $limit
        ) ) ?: [];
    }
}
