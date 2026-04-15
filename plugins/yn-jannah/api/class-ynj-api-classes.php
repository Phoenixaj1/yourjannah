<?php
/**
 * YourJannah — REST API: Classes & Courses.
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Classes {

    const NS = 'ynj/v1';

    public static function register() {

        // Public
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/classes', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'list_by_slug' ], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/classes/(?P<id>\d+)', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'get_single' ], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/classes/browse', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'browse_all' ], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/classes/(?P<id>\d+)/enrol', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'enrol' ], 'permission_callback' => '__return_true',
        ] );

        // Sessions
        register_rest_route( self::NS, '/classes/(?P<id>\d+)/sessions', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'list_sessions' ], 'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/admin/classes/(?P<class_id>\d+)/sessions', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'add_session' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
        register_rest_route( self::NS, '/admin/sessions/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'update_session' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
        register_rest_route( self::NS, '/admin/sessions/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_session' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );

        // Admin
        register_rest_route( self::NS, '/admin/classes', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'create' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
        register_rest_route( self::NS, '/admin/classes', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_list' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
        register_rest_route( self::NS, '/admin/classes/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'update' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
        register_rest_route( self::NS, '/admin/classes/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
        register_rest_route( self::NS, '/admin/enrolments', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_enrolments' ], 'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
    }

    // ── Public ──

    public static function list_by_slug( \WP_REST_Request $r ) {
        $mid = YNJ_DB::resolve_slug( $r->get_param( 'slug' ) );
        if ( ! $mid ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        $r->set_param( 'mosque_id', $mid );
        return self::list_public( $r );
    }

    private static function list_public( \WP_REST_Request $r ) {
        global $wpdb;
        $t = YNJ_DB::table( 'classes' );
        $mid = absint( $r->get_param( 'mosque_id' ) );
        $cat = sanitize_text_field( $r->get_param( 'category' ) ?? '' );

        $where = $wpdb->prepare( "mosque_id = %d AND status = 'active'", $mid );
        if ( $cat ) $where .= $wpdb->prepare( " AND category = %s", $cat );

        $rows = $wpdb->get_results( "SELECT * FROM $t WHERE $where ORDER BY start_date ASC, title ASC" );
        return new \WP_REST_Response( [ 'ok' => true, 'classes' => array_map( [ __CLASS__, 'fmt' ], $rows ) ] );
    }

    public static function get_single( \WP_REST_Request $r ) {
        global $wpdb;
        $t = YNJ_DB::table( 'classes' );
        $m = YNJ_DB::table( 'mosques' );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city
             FROM $t c INNER JOIN $m m ON m.id = c.mosque_id
             WHERE c.id = %d AND c.status = 'active'",
            absint( $r->get_param( 'id' ) )
        ) );
        if ( ! $row ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Class not found.' ], 404 );
        $f = self::fmt( $row );
        $f['mosque_name'] = $row->mosque_name;
        $f['mosque_slug'] = $row->mosque_slug;
        $f['spots_remaining'] = $row->max_capacity > 0 ? max( 0, $row->max_capacity - $row->enrolled_count ) : null;

        // Include sessions/curriculum
        $sessions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'class_sessions' ) . " WHERE class_id = %d ORDER BY session_number ASC",
            $row->id
        ) );
        $f['sessions'] = array_map( function( $s ) {
            return [
                'id' => (int) $s->id, 'session_number' => (int) $s->session_number,
                'title' => $s->title, 'description' => $s->description,
                'session_date' => $s->session_date, 'start_time' => $s->start_time,
                'end_time' => $s->end_time, 'status' => $s->status,
                'recording_url' => $s->recording_url,
            ];
        }, $sessions );

        return new \WP_REST_Response( [ 'ok' => true, 'class' => $f ] );
    }

    public static function browse_all( \WP_REST_Request $r ) {
        global $wpdb;
        $t = YNJ_DB::table( 'classes' );
        $m = YNJ_DB::table( 'mosques' );
        $cat = sanitize_text_field( $r->get_param( 'category' ) ?? '' );
        $q   = sanitize_text_field( $r->get_param( 'q' ) ?? '' );
        $lat  = (float) $r->get_param( 'lat' );
        $lng  = (float) $r->get_param( 'lng' );
        $radius = (float) ( $r->get_param( 'radius_km' ) ?: 50 );
        $online = $r->get_param( 'online' );

        $where = "c.status = 'active' AND m.status = 'active'";
        if ( $cat ) $where .= $wpdb->prepare( " AND c.category = %s", $cat );
        if ( $q && strlen( $q ) >= 2 ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where .= $wpdb->prepare( " AND (c.title LIKE %s OR c.description LIKE %s OR c.instructor_name LIKE %s)", $like, $like, $like );
        }
        if ( $online ) $where .= " AND c.is_online = 1";

        $dist_sql = ( $lat && $lng ) ? $wpdb->prepare(
            "( 6371 * acos( cos(radians(%f)) * cos(radians(m.latitude)) * cos(radians(m.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(m.latitude)) ) )",
            $lat, $lng, $lat
        ) : '9999';

        if ( $lat && $lng && $radius < 9999 ) {
            $where .= " AND m.latitude IS NOT NULL AND $dist_sql <= $radius";
        }

        $order = ( $lat && $lng ) ? "$dist_sql ASC" : "c.start_date ASC";

        $rows = $wpdb->get_results(
            "SELECT c.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city, $dist_sql AS distance_km
             FROM $t c INNER JOIN $m m ON m.id = c.mosque_id
             WHERE $where ORDER BY $order LIMIT 50"
        );

        $classes = array_map( function( $row ) {
            $f = self::fmt( $row );
            $f['mosque_name'] = $row->mosque_name;
            $f['mosque_slug'] = $row->mosque_slug;
            $f['distance_km'] = round( (float) $row->distance_km, 1 );
            return $f;
        }, $rows );

        return new \WP_REST_Response( [ 'ok' => true, 'classes' => $classes ] );
    }

    public static function enrol( \WP_REST_Request $r ) {
        $id   = absint( $r->get_param( 'id' ) );
        $data = $r->get_json_params();

        global $wpdb;
        $t = YNJ_DB::table( 'classes' );
        $cls = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d AND status = 'active'", $id ) );
        if ( ! $cls ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Class not found.' ], 404 );

        if ( $cls->max_capacity > 0 && $cls->enrolled_count >= $cls->max_capacity ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Class is fully booked.' ], 409 );
        }

        $name  = sanitize_text_field( $data['user_name'] ?? '' );
        $email = sanitize_email( $data['user_email'] ?? '' );
        if ( ! $name || ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Name and email required.' ], 400 );
        }

        $et = YNJ_DB::table( 'enrolments' );

        // Check duplicate
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $et WHERE class_id = %d AND user_email = %s AND status IN ('confirmed','pending')",
            $id, $email
        ) );
        if ( $exists ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Already enrolled.' ], 409 );

        if ( $cls->price_pence <= 0 ) {
            // Free class — confirm immediately
            $wpdb->insert( $et, [
                'class_id' => $id, 'mosque_id' => $cls->mosque_id,
                'user_name' => $name, 'user_email' => $email,
                'user_phone' => sanitize_text_field( $data['user_phone'] ?? '' ),
                'amount_paid_pence' => 0, 'status' => 'confirmed',
            ] );
            $wpdb->query( $wpdb->prepare( "UPDATE $t SET enrolled_count = enrolled_count + 1 WHERE id = %d", $id ) );

            do_action( 'ynj_new_booking', (int) $cls->mosque_id, [
                'user_name' => $name, 'user_email' => $email, 'event_id' => null,
                'booking_date' => $cls->start_date, 'start_time' => $cls->start_time,
                'end_time' => $cls->end_time, 'notes' => 'Class: ' . $cls->title,
            ] );

            return new \WP_REST_Response( [ 'ok' => true, 'free' => true, 'message' => 'Enrolled! See you in class.' ], 201 );
        }

        // Paid class — Stripe checkout
        $wpdb->insert( $et, [
            'class_id' => $id, 'mosque_id' => $cls->mosque_id,
            'user_name' => $name, 'user_email' => $email,
            'user_phone' => sanitize_text_field( $data['user_phone'] ?? '' ),
            'amount_paid_pence' => $cls->price_pence, 'status' => 'pending',
        ] );
        $enrol_id = (int) $wpdb->insert_id;

        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $cls->mosque_id
        ) );
        $base = home_url( "/mosque/" . ( $mosque->slug ?? '' ) );

        $label = $cls->price_type === 'per_session' ? 'per session' : ( $cls->price_type === 'monthly' ? '/month' : '' );
        $session = YNJ_Stripe::create_checkout(
            'class_enrolment',
            $enrol_id,
            $cls->price_pence,
            $cls->title . ' — £' . number_format( $cls->price_pence / 100, 2 ) . ( $label ? " $label" : '' ),
            $base . '/classes?enrolled=1',
            $base . '/classes',
            [ 'mosque_id' => $cls->mosque_id, 'class_id' => $id ]
        );

        if ( is_wp_error( $session ) ) {
            $wpdb->delete( $et, [ 'id' => $enrol_id ] );
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'checkout_url' => $session->url, 'enrolment_id' => $enrol_id ] );
    }

    // ── Admin ──

    public static function create( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $d = $r->get_json_params();
        $title = sanitize_text_field( $d['title'] ?? '' );
        if ( ! $title ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Title required.' ], 400 );

        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'classes' ), [
            'mosque_id'       => (int) $mosque->id,
            'title'           => $title,
            'description'     => wp_kses_post( $d['description'] ?? '' ),
            'instructor_name' => sanitize_text_field( $d['instructor_name'] ?? '' ),
            'instructor_bio'  => sanitize_textarea_field( $d['instructor_bio'] ?? '' ),
            'category'        => sanitize_text_field( $d['category'] ?? '' ),
            'class_type'      => sanitize_text_field( $d['class_type'] ?? 'course' ),
            'schedule_text'   => sanitize_text_field( $d['schedule_text'] ?? '' ),
            'start_date'      => sanitize_text_field( $d['start_date'] ?? '' ),
            'end_date'        => ! empty( $d['end_date'] ) ? sanitize_text_field( $d['end_date'] ) : null,
            'day_of_week'     => sanitize_text_field( $d['day_of_week'] ?? '' ),
            'start_time'      => sanitize_text_field( $d['start_time'] ?? '' ),
            'end_time'        => sanitize_text_field( $d['end_time'] ?? '' ),
            'total_sessions'  => absint( $d['total_sessions'] ?? 1 ),
            'is_online'       => absint( $d['is_online'] ?? 0 ),
            'live_url'        => esc_url_raw( $d['live_url'] ?? '' ),
            'location'        => sanitize_text_field( $d['location'] ?? '' ),
            'max_capacity'    => absint( $d['max_capacity'] ?? 0 ),
            'price_pence'     => absint( $d['price_pence'] ?? 0 ),
            'price_type'      => sanitize_text_field( $d['price_type'] ?? 'one_off' ),
            'image_url'       => esc_url_raw( $d['image_url'] ?? '' ),
            'status'          => 'active',
        ] );
        $id = (int) $wpdb->insert_id;
        if ( ! $id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed.' ], 500 );
        return new \WP_REST_Response( [ 'ok' => true, 'id' => $id ], 201 );
    }

    public static function admin_list( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'classes' ) . " WHERE mosque_id = %d ORDER BY created_at DESC", (int) $mosque->id
        ) );
        return new \WP_REST_Response( [ 'ok' => true, 'classes' => array_map( [ __CLASS__, 'fmt' ], $rows ) ] );
    }

    public static function update( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $id = absint( $r->get_param( 'id' ) );
        $d = $r->get_json_params();
        global $wpdb;
        $t = YNJ_DB::table( 'classes' );
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE id=%d AND mosque_id=%d", $id, (int) $mosque->id ) );
        if ( ! $exists ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not found.' ], 404 );

        $allowed = ['title','description','instructor_name','instructor_bio','category','class_type','schedule_text','start_date','end_date','day_of_week','start_time','end_time','total_sessions','is_online','live_url','location','max_capacity','price_pence','price_type','image_url','status'];
        $update = [];
        foreach ( $allowed as $k ) { if ( isset( $d[$k] ) ) $update[$k] = is_numeric( $d[$k] ) ? absint( $d[$k] ) : sanitize_text_field( $d[$k] ); }
        if ( isset( $d['description'] ) ) $update['description'] = wp_kses_post( $d['description'] );
        if ( isset( $d['live_url'] ) || isset( $d['image_url'] ) ) { if ( isset( $d['live_url'] ) ) $update['live_url'] = esc_url_raw( $d['live_url'] ); if ( isset( $d['image_url'] ) ) $update['image_url'] = esc_url_raw( $d['image_url'] ); }
        if ( $update ) $wpdb->update( $t, $update, [ 'id' => $id ] );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function delete( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $id = absint( $r->get_param( 'id' ) );
        global $wpdb;
        $del = $wpdb->delete( YNJ_DB::table( 'classes' ), [ 'id' => $id, 'mosque_id' => (int) $mosque->id ] );
        return $del ? new \WP_REST_Response( [ 'ok' => true ] ) : new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not found.' ], 404 );
    }

    public static function admin_enrolments( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $et = YNJ_DB::table( 'enrolments' );
        $ct = YNJ_DB::table( 'classes' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, c.title AS class_title FROM $et e INNER JOIN $ct c ON c.id = e.class_id WHERE e.mosque_id = %d ORDER BY e.enrolled_at DESC LIMIT 100",
            (int) $mosque->id
        ) );
        $enrolments = array_map( function( $r ) {
            return [ 'id' => (int) $r->id, 'class_title' => $r->class_title, 'user_name' => $r->user_name, 'user_email' => $r->user_email, 'user_phone' => $r->user_phone, 'amount_paid_pence' => (int) $r->amount_paid_pence, 'status' => $r->status, 'enrolled_at' => $r->enrolled_at ];
        }, $rows );
        return new \WP_REST_Response( [ 'ok' => true, 'enrolments' => $enrolments ] );
    }

    // ── Sessions ──

    public static function list_sessions( \WP_REST_Request $r ) {
        global $wpdb;
        $t = YNJ_DB::table( 'class_sessions' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $t WHERE class_id = %d ORDER BY session_number ASC",
            absint( $r->get_param( 'id' ) )
        ) );
        $sessions = array_map( function( $s ) {
            return [
                'id'             => (int) $s->id,
                'session_number' => (int) $s->session_number,
                'title'          => $s->title,
                'description'    => $s->description,
                'session_date'   => $s->session_date,
                'start_time'     => $s->start_time,
                'end_time'       => $s->end_time,
                'is_online'      => (bool) $s->is_online,
                'live_url'       => $s->live_url,
                'recording_url'  => $s->recording_url,
                'status'         => $s->status,
            ];
        }, $rows );
        return new \WP_REST_Response( [ 'ok' => true, 'sessions' => $sessions ] );
    }

    public static function add_session( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $class_id = absint( $r->get_param( 'class_id' ) );
        $d = $r->get_json_params();

        global $wpdb;
        // Verify class belongs to mosque
        $cls = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . YNJ_DB::table( 'classes' ) . " WHERE id = %d AND mosque_id = %d",
            $class_id, (int) $mosque->id
        ) );
        if ( ! $cls ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Class not found.' ], 404 );

        // Auto-increment session number
        $next = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(MAX(session_number),0)+1 FROM " . YNJ_DB::table( 'class_sessions' ) . " WHERE class_id = %d",
            $class_id
        ) );

        $wpdb->insert( YNJ_DB::table( 'class_sessions' ), [
            'class_id'       => $class_id,
            'session_number' => absint( $d['session_number'] ?? $next ),
            'title'          => sanitize_text_field( $d['title'] ?? "Session $next" ),
            'description'    => sanitize_textarea_field( $d['description'] ?? '' ),
            'session_date'   => sanitize_text_field( $d['session_date'] ?? '' ),
            'start_time'     => sanitize_text_field( $d['start_time'] ?? '' ),
            'end_time'       => sanitize_text_field( $d['end_time'] ?? '' ),
            'is_online'      => absint( $d['is_online'] ?? 0 ),
            'live_url'       => esc_url_raw( $d['live_url'] ?? '' ),
            'status'         => 'scheduled',
        ] );

        // Update total_sessions on the class
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'class_sessions' ) . " WHERE class_id = %d", $class_id
        ) );
        $wpdb->update( YNJ_DB::table( 'classes' ), [ 'total_sessions' => $count ], [ 'id' => $class_id ] );

        return new \WP_REST_Response( [ 'ok' => true, 'id' => (int) $wpdb->insert_id ], 201 );
    }

    public static function update_session( \WP_REST_Request $r ) {
        $id = absint( $r->get_param( 'id' ) );
        $d  = $r->get_json_params();
        global $wpdb;
        $t = YNJ_DB::table( 'class_sessions' );
        $update = [];
        if ( isset( $d['title'] ) )          $update['title']         = sanitize_text_field( $d['title'] );
        if ( isset( $d['description'] ) )    $update['description']   = sanitize_textarea_field( $d['description'] );
        if ( isset( $d['session_date'] ) )   $update['session_date']  = sanitize_text_field( $d['session_date'] );
        if ( isset( $d['start_time'] ) )     $update['start_time']    = sanitize_text_field( $d['start_time'] );
        if ( isset( $d['end_time'] ) )       $update['end_time']      = sanitize_text_field( $d['end_time'] );
        if ( isset( $d['live_url'] ) )       $update['live_url']      = esc_url_raw( $d['live_url'] );
        if ( isset( $d['recording_url'] ) )  $update['recording_url'] = esc_url_raw( $d['recording_url'] );
        if ( isset( $d['status'] ) )         $update['status']        = sanitize_text_field( $d['status'] );
        if ( $update ) $wpdb->update( $t, $update, [ 'id' => $id ] );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function delete_session( \WP_REST_Request $r ) {
        global $wpdb;
        $t = YNJ_DB::table( 'class_sessions' );
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT class_id FROM $t WHERE id = %d", absint( $r->get_param( 'id' ) ) ) );
        $wpdb->delete( $t, [ 'id' => absint( $r->get_param( 'id' ) ) ] );
        // Update total_sessions
        if ( $session ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $t WHERE class_id = %d", $session->class_id
            ) );
            $wpdb->update( YNJ_DB::table( 'classes' ), [ 'total_sessions' => $count ], [ 'id' => $session->class_id ] );
        }
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    private static function fmt( $r ) {
        return [
            'id'              => (int) $r->id,
            'mosque_id'       => (int) $r->mosque_id,
            'title'           => $r->title,
            'description'     => $r->description,
            'instructor_name' => $r->instructor_name,
            'instructor_bio'  => $r->instructor_bio ?? '',
            'category'        => $r->category,
            'class_type'      => $r->class_type,
            'schedule_text'   => $r->schedule_text,
            'start_date'      => $r->start_date,
            'end_date'        => $r->end_date,
            'day_of_week'     => $r->day_of_week,
            'start_time'      => $r->start_time,
            'end_time'        => $r->end_time,
            'total_sessions'  => (int) $r->total_sessions,
            'is_online'       => (bool) ( $r->is_online ?? 0 ),
            'live_url'        => $r->live_url ?? '',
            'location'        => $r->location,
            'max_capacity'    => (int) $r->max_capacity,
            'enrolled_count'  => (int) $r->enrolled_count,
            'price_pence'     => (int) $r->price_pence,
            'price_type'      => $r->price_type,
            'image_url'       => $r->image_url ?? '',
            'status'          => $r->status,
        ];
    }
}
