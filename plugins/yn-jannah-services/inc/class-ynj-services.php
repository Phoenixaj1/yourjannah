<?php
/**
 * Masjid Services Data Layer -- PHP-first database access.
 *
 * All masjid-service queries go through this class.
 * Templates and API endpoints call these methods directly -- no REST round-trips.
 *
 * Tables: ynj_masjid_services, ynj_masjid_service_enquiries
 *
 * @package YNJ_Services
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Services {

    /** Standard masjid service categories with labels and icons. */
    const CATEGORIES = [
        'nikkah'       => [ 'label' => 'Nikkah / Marriage', 'icon' => "\xF0\x9F\x92\x8D" ],
        'funeral'      => [ 'label' => 'Funeral / Janazah', 'icon' => "\xF0\x9F\x95\x8A\xEF\xB8\x8F" ],
        'counselling'  => [ 'label' => 'Counselling',        'icon' => "\xF0\x9F\xA4\x9D" ],
        'quran'        => [ 'label' => 'Quran Classes',      'icon' => "\xF0\x9F\x93\x96" ],
        'revert'       => [ 'label' => 'Revert Support',     'icon' => "\xF0\x9F\x95\x8C" ],
        'ruqyah'       => [ 'label' => 'Ruqyah',             'icon' => "\xF0\x9F\xA4\xB2" ],
        'aqiqah'       => [ 'label' => 'Aqiqah',             'icon' => "\xF0\x9F\x90\x91" ],
        'circumcision' => [ 'label' => 'Circumcision',       'icon' => "\xF0\x9F\x8F\xA5" ],
        'walima'       => [ 'label' => 'Walima / Catering',  'icon' => "\xF0\x9F\x8D\xBD\xEF\xB8\x8F" ],
        'hire'         => [ 'label' => 'Venue / Hall Hire',  'icon' => "\xF0\x9F\x8F\xA0" ],
        'imam'         => [ 'label' => 'Imam Services',      'icon' => "\xF0\x9F\x95\x8C" ],
        'certificate'  => [ 'label' => 'Certificates',       'icon' => "\xF0\x9F\x93\x9C" ],
        'general'      => [ 'label' => 'General',            'icon' => "\xF0\x9F\x95\x8C" ],
    ];

    // ================================================================
    // SERVICES — READ
    // ================================================================

    /**
     * Get active services for a mosque.
     *
     * @param int   $mosque_id
     * @param array $args { category, status, limit, offset }
     * @return array
     */
    public static function get_services( $mosque_id, $args = [] ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );

        $limit    = absint( $args['limit'] ?? 50 );
        $offset   = absint( $args['offset'] ?? 0 );
        $category = sanitize_text_field( $args['category'] ?? '' );
        $status   = sanitize_text_field( $args['status'] ?? 'active' );

        $where = $wpdb->prepare( "WHERE mosque_id = %d AND status = %s", (int) $mosque_id, $status );
        if ( $category ) {
            $where .= $wpdb->prepare( " AND category = %s", $category );
        }

        $rows = $wpdb->get_results(
            "SELECT id, mosque_id, title, category, description,
                    price_pence, price_label, contact_phone, contact_email,
                    availability, requires_approval, image_url,
                    sort_order, status, created_at
             FROM $t $where
             ORDER BY sort_order ASC, title ASC
             LIMIT $limit OFFSET $offset"
        );

        return $rows ?: [];
    }

    /**
     * Get a single service by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function get_service( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'masjid_services' ) . " WHERE id = %d",
            absint( $id )
        ) );
    }

    // ================================================================
    // SERVICES — WRITE (ADMIN)
    // ================================================================

    /**
     * Create a new masjid service.
     *
     * @param array $data { mosque_id, title, category, description, ... }
     * @return int|false  Insert ID on success, false on failure.
     */
    public static function create_service( $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );

        $title = sanitize_text_field( $data['title'] ?? '' );
        if ( ! $title ) return false;

        $row = [
            'mosque_id'         => absint( $data['mosque_id'] ?? 0 ),
            'title'             => $title,
            'category'          => sanitize_text_field( $data['category'] ?? 'general' ),
            'description'       => sanitize_textarea_field( $data['description'] ?? '' ),
            'price_pence'       => absint( $data['price_pence'] ?? 0 ),
            'price_label'       => sanitize_text_field( $data['price_label'] ?? '' ),
            'contact_phone'     => sanitize_text_field( $data['contact_phone'] ?? '' ),
            'contact_email'     => sanitize_email( $data['contact_email'] ?? '' ),
            'availability'      => sanitize_text_field( $data['availability'] ?? '' ),
            'requires_approval' => absint( $data['requires_approval'] ?? 1 ),
            'image_url'         => esc_url_raw( $data['image_url'] ?? '' ),
            'sort_order'        => absint( $data['sort_order'] ?? 0 ),
            'status'            => 'active',
        ];

        $result = $wpdb->insert( $t, $row );
        if ( ! $result ) return false;

        $id = (int) $wpdb->insert_id;

        do_action( 'ynj_service_created', $id, $row );

        return $id;
    }

    /**
     * Update an existing masjid service.
     *
     * Only whitelisted columns are written.
     *
     * @param int   $id
     * @param array $data  Key-value pairs to update.
     * @return bool|int     Rows updated, or false on error.
     */
    public static function update_service( $id, $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );

        $allowed = [
            'title', 'category', 'description', 'price_pence', 'price_label',
            'contact_phone', 'contact_email', 'availability',
            'requires_approval', 'image_url', 'sort_order', 'status',
        ];

        $update = [];
        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $data ) ) continue;

            switch ( $key ) {
                case 'contact_email':
                    $update[ $key ] = sanitize_email( $data[ $key ] );
                    break;
                case 'description':
                    $update[ $key ] = sanitize_textarea_field( $data[ $key ] );
                    break;
                case 'image_url':
                    $update[ $key ] = esc_url_raw( $data[ $key ] );
                    break;
                case 'price_pence':
                case 'requires_approval':
                case 'sort_order':
                    $update[ $key ] = absint( $data[ $key ] );
                    break;
                default:
                    $update[ $key ] = sanitize_text_field( $data[ $key ] );
                    break;
            }
        }

        if ( empty( $update ) ) return false;

        $result = $wpdb->update( $t, $update, [ 'id' => absint( $id ) ] );

        if ( $result !== false ) {
            do_action( 'ynj_service_updated', (int) $id, $update );
        }

        return $result;
    }

    /**
     * Delete a masjid service.
     *
     * Scoped to mosque_id so admins can only delete their own services.
     *
     * @param int $id
     * @param int $mosque_id  Optional mosque scope.
     * @return bool|int        Rows deleted, or false on error.
     */
    public static function delete_service( $id, $mosque_id = 0 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );

        $where = [ 'id' => absint( $id ) ];
        if ( $mosque_id ) {
            $where['mosque_id'] = absint( $mosque_id );
        }

        $result = $wpdb->delete( $t, $where );

        if ( $result ) {
            do_action( 'ynj_service_deleted', (int) $id, (int) $mosque_id );
        }

        return $result;
    }

    // ================================================================
    // CATEGORIES
    // ================================================================

    /**
     * Get all service categories with labels and icons.
     *
     * @return array [ [ 'key' => 'nikkah', 'label' => '...', 'icon' => '...' ], ... ]
     */
    public static function get_categories() {
        $cats = [];
        foreach ( self::CATEGORIES as $key => $val ) {
            $cats[] = [
                'key'   => $key,
                'label' => $val['label'],
                'icon'  => $val['icon'],
            ];
        }
        return $cats;
    }

    // ================================================================
    // ENQUIRIES
    // ================================================================

    /**
     * Submit a public enquiry / booking request.
     *
     * Rate limited: 5 per minute per IP.
     *
     * @param array $data { service_id, name, email, phone, preferred_date, message }
     * @return int|WP_Error  Enquiry ID on success.
     */
    public static function submit_enquiry( $data ) {
        global $wpdb;

        // ---- Rate limit: 5/min per IP ----
        $ip       = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $rate_key = 'ynj_svc_enq_' . md5( $ip );
        $count    = (int) get_transient( $rate_key );
        if ( $count >= 5 ) {
            return new \WP_Error( 'rate_limited', 'Too many enquiries. Try again in a minute.', [ 'status' => 429 ] );
        }
        set_transient( $rate_key, $count + 1, 60 );

        // ---- Validate required fields ----
        $name  = sanitize_text_field( $data['name'] ?? '' );
        $email = sanitize_email( $data['email'] ?? '' );
        if ( ! $name || ! is_email( $email ) ) {
            return new \WP_Error( 'validation', 'Name and valid email are required.', [ 'status' => 400 ] );
        }

        // ---- Resolve service ----
        $service_id = absint( $data['service_id'] ?? 0 );
        $st = YNJ_DB::table( 'masjid_services' );
        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, mosque_id, title FROM $st WHERE id = %d AND status = 'active'",
            $service_id
        ) );
        if ( ! $service ) {
            return new \WP_Error( 'not_found', 'Service not found or inactive.', [ 'status' => 404 ] );
        }

        // ---- Insert enquiry ----
        $et = YNJ_DB::table( 'masjid_service_enquiries' );
        $row = [
            'mosque_id'      => (int) $service->mosque_id,
            'service_id'     => $service_id,
            'user_name'      => $name,
            'user_email'     => $email,
            'user_phone'     => sanitize_text_field( $data['phone'] ?? '' ),
            'preferred_date' => ! empty( $data['preferred_date'] ) ? sanitize_text_field( $data['preferred_date'] ) : null,
            'message'        => sanitize_textarea_field( $data['message'] ?? '' ),
            'status'         => 'pending',
            'admin_notes'    => '',
        ];

        $result = $wpdb->insert( $et, $row );
        if ( ! $result ) {
            return new \WP_Error( 'db_error', 'Could not save enquiry.', [ 'status' => 500 ] );
        }

        $enquiry_id = (int) $wpdb->insert_id;

        // ---- Notify mosque admin ----
        do_action( 'ynj_new_enquiry', (int) $service->mosque_id, [
            'name'    => $name,
            'email'   => $email,
            'subject' => 'Booking: ' . $service->title,
            'message' => sanitize_textarea_field( $data['message'] ?? '' ),
            'type'    => 'masjid_service',
        ] );

        do_action( 'ynj_service_enquiry_submitted', $enquiry_id, $row );

        return $enquiry_id;
    }

    /**
     * Get enquiries for a mosque (admin view).
     *
     * Joins to masjid_services for service_title and service_category.
     *
     * @param int   $mosque_id
     * @param array $args { status, limit, offset }
     * @return array
     */
    public static function get_enquiries( $mosque_id, $args = [] ) {
        global $wpdb;
        $et = YNJ_DB::table( 'masjid_service_enquiries' );
        $st = YNJ_DB::table( 'masjid_services' );

        $status = sanitize_text_field( $args['status'] ?? '' );
        $limit  = absint( $args['limit'] ?? 100 );
        $offset = absint( $args['offset'] ?? 0 );

        $where = $wpdb->prepare( "e.mosque_id = %d", (int) $mosque_id );
        if ( $status ) {
            $where .= $wpdb->prepare( " AND e.status = %s", $status );
        }

        return $wpdb->get_results(
            "SELECT e.id, e.mosque_id, e.service_id,
                    e.user_name, e.user_email, e.user_phone,
                    e.preferred_date, e.message, e.status, e.admin_notes,
                    e.created_at,
                    s.title AS service_title, s.category AS service_category
             FROM $et e
             LEFT JOIN $st s ON s.id = e.service_id
             WHERE $where
             ORDER BY e.created_at DESC
             LIMIT $limit OFFSET $offset"
        ) ?: [];
    }

    /**
     * Update an enquiry (admin respond / change status).
     *
     * Scoped to mosque_id for safety.
     *
     * @param int   $id
     * @param array $data { status, admin_notes }
     * @param int   $mosque_id  Optional mosque scope.
     * @return bool|int          Rows updated, or false on error.
     */
    public static function update_enquiry( $id, $data, $mosque_id = 0 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_service_enquiries' );

        $update = [];
        if ( isset( $data['status'] ) ) {
            $update['status'] = sanitize_text_field( $data['status'] );
        }
        if ( isset( $data['admin_notes'] ) ) {
            $update['admin_notes'] = sanitize_textarea_field( $data['admin_notes'] );
        }
        if ( empty( $update ) ) return false;

        $where = [ 'id' => absint( $id ) ];
        if ( $mosque_id ) {
            $where['mosque_id'] = absint( $mosque_id );
        }

        $result = $wpdb->update( $t, $update, $where );

        if ( $result !== false ) {
            do_action( 'ynj_service_enquiry_updated', (int) $id, $update );
        }

        return $result;
    }

    // ================================================================
    // SEARCH (cross-mosque, with optional geo)
    // ================================================================

    /**
     * Search services across all mosques.
     *
     * Supports text search (title + description), category filter,
     * and Haversine geo-distance ordering with radius cap.
     *
     * @param string     $query      Free-text search term (min 2 chars).
     * @param float|null $lat        User latitude (null to skip geo).
     * @param float|null $lng        User longitude.
     * @param array      $args       { category, radius_km, limit }
     * @return array
     */
    public static function search( $query = '', $lat = null, $lng = null, $args = [] ) {
        global $wpdb;
        $st = YNJ_DB::table( 'masjid_services' );
        $mt = YNJ_DB::table( 'mosques' );

        $category  = sanitize_text_field( $args['category'] ?? '' );
        $radius_km = (float) ( $args['radius_km'] ?? 50 );
        $limit     = absint( $args['limit'] ?? 50 );
        $query     = sanitize_text_field( $query );

        // ---- WHERE conditions ----
        $where = "s.status = 'active' AND m.status = 'active'";

        if ( $category ) {
            $where .= $wpdb->prepare( " AND s.category = %s", $category );
        }
        if ( $query && strlen( $query ) >= 2 ) {
            $like = '%' . $wpdb->esc_like( $query ) . '%';
            $where .= $wpdb->prepare( " AND (s.title LIKE %s OR s.description LIKE %s)", $like, $like );
        }

        // ---- Haversine distance expression ----
        $has_geo = ( $lat !== null && $lng !== null && (float) $lat && (float) $lng );

        if ( $has_geo ) {
            $dist_sql = $wpdb->prepare(
                "( 6371 * acos( cos(radians(%f)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(m.latitude)) ) )",
                (float) $lat, (float) $lng, (float) $lat
            );
        } else {
            $dist_sql = '9999';
        }

        // ---- Radius filter ----
        if ( $has_geo && $radius_km < 9999 ) {
            $where .= " AND m.latitude IS NOT NULL AND $dist_sql <= " . (float) $radius_km;
        }

        // ---- ORDER ----
        $order = $has_geo ? "$dist_sql ASC" : "s.title ASC";

        $rows = $wpdb->get_results(
            "SELECT s.id, s.mosque_id, s.title, s.category, s.description,
                    s.price_pence, s.price_label, s.contact_phone, s.contact_email,
                    s.availability, s.requires_approval, s.image_url,
                    s.sort_order, s.status, s.created_at,
                    m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city,
                    $dist_sql AS distance_km
             FROM $st s
             INNER JOIN $mt m ON m.id = s.mosque_id
             WHERE $where
             ORDER BY $order
             LIMIT $limit"
        );

        // Round distance for cleaner output.
        if ( $rows ) {
            foreach ( $rows as &$row ) {
                $row->distance_km = round( (float) $row->distance_km, 1 );
            }
            unset( $row );
        }

        return $rows ?: [];
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Count services by category for a mosque.
     *
     * @param int $mosque_id
     * @return array [ { category, count }, ... ]
     */
    public static function count_by_category( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT category, COUNT(*) AS count FROM $t
             WHERE mosque_id = %d AND status = 'active'
             GROUP BY category ORDER BY count DESC",
            (int) $mosque_id
        ) ) ?: [];
    }

    /**
     * Count pending enquiries for a mosque (dashboard badge).
     *
     * @param int $mosque_id
     * @return int
     */
    public static function count_pending_enquiries( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_service_enquiries' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t WHERE mosque_id = %d AND status = 'pending'",
            (int) $mosque_id
        ) );
    }

    /**
     * Format a service row for output (normalise types).
     *
     * @param object $r  Raw DB row.
     * @return array
     */
    public static function format( $r ) {
        return [
            'id'                => (int) $r->id,
            'mosque_id'         => (int) $r->mosque_id,
            'title'             => $r->title,
            'category'          => $r->category,
            'description'       => $r->description,
            'price_pence'       => (int) $r->price_pence,
            'price_label'       => $r->price_label,
            'contact_phone'     => $r->contact_phone,
            'contact_email'     => $r->contact_email,
            'availability'      => $r->availability,
            'requires_approval' => (bool) $r->requires_approval,
            'image_url'         => $r->image_url ?? '',
            'sort_order'        => (int) $r->sort_order,
            'status'            => $r->status,
            'created_at'        => $r->created_at,
        ];
    }

    /**
     * Format an enquiry row for output.
     *
     * @param object $e  Raw DB row (with joined service fields).
     * @return array
     */
    public static function format_enquiry( $e ) {
        return [
            'id'               => (int) $e->id,
            'service_id'       => (int) $e->service_id,
            'service_title'    => $e->service_title ?? '',
            'service_category' => $e->service_category ?? '',
            'user_name'        => $e->user_name,
            'user_email'       => $e->user_email,
            'user_phone'       => $e->user_phone,
            'preferred_date'   => $e->preferred_date,
            'message'          => $e->message,
            'status'           => $e->status,
            'admin_notes'      => $e->admin_notes,
            'created_at'       => $e->created_at,
        ];
    }
}
