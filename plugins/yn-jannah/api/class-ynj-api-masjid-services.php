<?php
/**
 * YourJannah — REST API: Masjid Services (mosque-offered bookable services).
 *
 * Things the mosque itself offers: nikkah, funeral, counselling, Quran classes,
 * room hire, catering, etc. Separate from professional service directory.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Masjid_Services {

    const NS = 'ynj/v1';

    /** Standard masjid service categories. */
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

    public static function register() {

        // Public: list masjid services for a mosque
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/masjid-services', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'list_public' ],
            'permission_callback' => '__return_true',
        ] );

        // Public: submit enquiry / booking request
        register_rest_route( self::NS, '/masjid-services/(?P<id>\d+)/enquire', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'submit_enquiry' ],
            'permission_callback' => '__return_true',
        ] );

        // Public: search across mosques
        register_rest_route( self::NS, '/masjid-services/search', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'search' ],
            'permission_callback' => '__return_true',
        ] );

        // Admin: CRUD
        register_rest_route( self::NS, '/admin/masjid-services', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_list' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/masjid-services', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'admin_create' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/masjid-services/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'admin_update' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/masjid-services/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [ __CLASS__, 'admin_delete' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // Admin: enquiries
        register_rest_route( self::NS, '/admin/masjid-service-enquiries', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_enquiries' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/masjid-service-enquiries/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'admin_update_enquiry' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // Categories list
        register_rest_route( self::NS, '/masjid-services/categories', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'list_categories' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ================================================================
    // PUBLIC
    // ================================================================

    public static function list_public( \WP_REST_Request $r ) {
        $mid = YNJ_DB::resolve_slug( $r->get_param( 'slug' ) );
        if ( ! $mid ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );

        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );
        $cat = sanitize_text_field( $r->get_param( 'category' ) ?? '' );

        $where = $wpdb->prepare( "mosque_id = %d AND status = 'active'", $mid );
        if ( $cat ) $where .= $wpdb->prepare( " AND category = %s", $cat );

        $rows = $wpdb->get_results( "SELECT * FROM $t WHERE $where ORDER BY sort_order ASC, title ASC" );

        return new \WP_REST_Response( [
            'ok'       => true,
            'services' => array_map( [ __CLASS__, 'fmt' ], $rows ),
        ] );
    }

    public static function submit_enquiry( \WP_REST_Request $r ) {
        $service_id = absint( $r->get_param( 'id' ) );
        $d = $r->get_json_params();

        $name  = sanitize_text_field( $d['name'] ?? '' );
        $email = sanitize_email( $d['email'] ?? '' );
        if ( ! $name || ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Name and email required.' ], 400 );
        }

        global $wpdb;
        $st = YNJ_DB::table( 'masjid_services' );
        $svc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $st WHERE id = %d AND status = 'active'", $service_id ) );
        if ( ! $svc ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Service not found.' ], 404 );

        $et = YNJ_DB::table( 'masjid_service_enquiries' );
        $wpdb->insert( $et, [
            'mosque_id'      => (int) $svc->mosque_id,
            'service_id'     => $service_id,
            'user_name'      => $name,
            'user_email'     => $email,
            'user_phone'     => sanitize_text_field( $d['phone'] ?? '' ),
            'preferred_date' => ! empty( $d['preferred_date'] ) ? sanitize_text_field( $d['preferred_date'] ) : null,
            'message'        => sanitize_textarea_field( $d['message'] ?? '' ),
            'status'         => 'pending',
        ] );

        // Notify mosque admin
        do_action( 'ynj_new_enquiry', (int) $svc->mosque_id, [
            'name'    => $name,
            'email'   => $email,
            'subject' => 'Booking: ' . $svc->title,
            'message' => sanitize_textarea_field( $d['message'] ?? '' ),
            'type'    => 'masjid_service',
        ] );

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Enquiry submitted. The mosque will contact you.',
        ], 201 );
    }

    public static function search( \WP_REST_Request $r ) {
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );
        $m = YNJ_DB::table( 'mosques' );

        $cat = sanitize_text_field( $r->get_param( 'category' ) ?? '' );
        $q   = sanitize_text_field( $r->get_param( 'q' ) ?? '' );
        $lat = (float) $r->get_param( 'lat' );
        $lng = (float) $r->get_param( 'lng' );
        $radius_km = (float) ( $r->get_param( 'radius_km' ) ?: 50 );

        $where = "s.status = 'active' AND m.status = 'active'";
        if ( $cat ) $where .= $wpdb->prepare( " AND s.category = %s", $cat );
        if ( $q && strlen( $q ) >= 2 ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where .= $wpdb->prepare( " AND (s.title LIKE %s OR s.description LIKE %s)", $like, $like );
        }

        $dist_sql = ( $lat && $lng ) ? $wpdb->prepare(
            "( 6371 * acos( cos(radians(%f)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(m.latitude)) ) )",
            $lat, $lng, $lat
        ) : '9999';

        if ( $lat && $lng && $radius_km < 9999 ) {
            $where .= " AND m.latitude IS NOT NULL AND $dist_sql <= $radius_km";
        }

        $order = ( $lat && $lng ) ? "$dist_sql ASC" : "s.title ASC";

        $rows = $wpdb->get_results(
            "SELECT s.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city, $dist_sql AS distance_km
             FROM $t s INNER JOIN $m m ON m.id = s.mosque_id
             WHERE $where ORDER BY $order LIMIT 50"
        );

        $services = array_map( function( $row ) {
            $f = self::fmt( $row );
            $f['mosque_name'] = $row->mosque_name;
            $f['mosque_slug'] = $row->mosque_slug;
            $f['mosque_city'] = $row->mosque_city;
            $f['distance_km'] = round( (float) $row->distance_km, 1 );
            return $f;
        }, $rows );

        return new \WP_REST_Response( [ 'ok' => true, 'services' => $services, 'total' => count( $services ) ] );
    }

    public static function list_categories( \WP_REST_Request $r ) {
        $cats = [];
        foreach ( self::CATEGORIES as $key => $val ) {
            $cats[] = [ 'key' => $key, 'label' => $val['label'] ];
        }
        return new \WP_REST_Response( [ 'ok' => true, 'categories' => $cats ] );
    }

    // ================================================================
    // ADMIN
    // ================================================================

    public static function admin_list( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'masjid_services' ) . " WHERE mosque_id = %d ORDER BY sort_order ASC, title ASC",
            $mosque->id
        ) );
        return new \WP_REST_Response( [ 'ok' => true, 'services' => array_map( [ __CLASS__, 'fmt' ], $rows ) ] );
    }

    public static function admin_create( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $d = $r->get_json_params();
        $title = sanitize_text_field( $d['title'] ?? '' );
        if ( ! $title ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Title required.' ], 400 );

        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'masjid_services' ), [
            'mosque_id'         => (int) $mosque->id,
            'title'             => $title,
            'category'          => sanitize_text_field( $d['category'] ?? 'general' ),
            'description'       => sanitize_textarea_field( $d['description'] ?? '' ),
            'price_pence'       => absint( $d['price_pence'] ?? 0 ),
            'price_label'       => sanitize_text_field( $d['price_label'] ?? '' ),
            'contact_phone'     => sanitize_text_field( $d['contact_phone'] ?? '' ),
            'contact_email'     => sanitize_email( $d['contact_email'] ?? '' ),
            'availability'      => sanitize_text_field( $d['availability'] ?? '' ),
            'requires_approval' => absint( $d['requires_approval'] ?? 1 ),
            'image_url'         => esc_url_raw( $d['image_url'] ?? '' ),
            'sort_order'        => absint( $d['sort_order'] ?? 0 ),
            'status'            => 'active',
        ] );
        return new \WP_REST_Response( [ 'ok' => true, 'id' => (int) $wpdb->insert_id ], 201 );
    }

    public static function admin_update( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $id = absint( $r->get_param( 'id' ) );
        $d = $r->get_json_params();
        global $wpdb;
        $t = YNJ_DB::table( 'masjid_services' );
        $allowed = [ 'title', 'category', 'description', 'price_pence', 'price_label', 'contact_phone', 'contact_email', 'availability', 'requires_approval', 'image_url', 'sort_order', 'status' ];
        $update = [];
        foreach ( $allowed as $k ) {
            if ( ! isset( $d[$k] ) ) continue;
            if ( $k === 'contact_email' ) $update[$k] = sanitize_email( $d[$k] );
            elseif ( $k === 'description' ) $update[$k] = sanitize_textarea_field( $d[$k] );
            elseif ( $k === 'image_url' ) $update[$k] = esc_url_raw( $d[$k] );
            elseif ( is_numeric( $d[$k] ) ) $update[$k] = absint( $d[$k] );
            else $update[$k] = sanitize_text_field( $d[$k] );
        }
        if ( $update ) $wpdb->update( $t, $update, [ 'id' => $id, 'mosque_id' => (int) $mosque->id ] );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function admin_delete( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $wpdb->delete( YNJ_DB::table( 'masjid_services' ), [ 'id' => absint( $r->get_param( 'id' ) ), 'mosque_id' => (int) $mosque->id ] );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function admin_enquiries( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $status = sanitize_text_field( $r->get_param( 'status' ) ?? '' );
        global $wpdb;
        $et = YNJ_DB::table( 'masjid_service_enquiries' );
        $st = YNJ_DB::table( 'masjid_services' );
        $where = $wpdb->prepare( "e.mosque_id = %d", $mosque->id );
        if ( $status ) $where .= $wpdb->prepare( " AND e.status = %s", $status );
        $rows = $wpdb->get_results(
            "SELECT e.*, s.title AS service_title, s.category AS service_category
             FROM $et e LEFT JOIN $st s ON s.id = e.service_id
             WHERE $where ORDER BY e.created_at DESC LIMIT 100"
        );
        $enquiries = array_map( function( $e ) {
            return [
                'id'             => (int) $e->id,
                'service_title'  => $e->service_title,
                'service_category' => $e->service_category,
                'user_name'      => $e->user_name,
                'user_email'     => $e->user_email,
                'user_phone'     => $e->user_phone,
                'preferred_date' => $e->preferred_date,
                'message'        => $e->message,
                'status'         => $e->status,
                'admin_notes'    => $e->admin_notes,
                'created_at'     => $e->created_at,
            ];
        }, $rows );
        return new \WP_REST_Response( [ 'ok' => true, 'enquiries' => $enquiries ] );
    }

    public static function admin_update_enquiry( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $id = absint( $r->get_param( 'id' ) );
        $d = $r->get_json_params();
        global $wpdb;
        $update = [];
        if ( isset( $d['status'] ) ) $update['status'] = sanitize_text_field( $d['status'] );
        if ( isset( $d['admin_notes'] ) ) $update['admin_notes'] = sanitize_textarea_field( $d['admin_notes'] );
        if ( $update ) $wpdb->update( YNJ_DB::table( 'masjid_service_enquiries' ), $update, [ 'id' => $id, 'mosque_id' => (int) $mosque->id ] );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    private static function fmt( $r ) {
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
        ];
    }
}
