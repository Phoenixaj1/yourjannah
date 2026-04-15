<?php
/**
 * YourJannah — REST API: Madrassah (Islamic school) endpoints.
 *
 * Admin: manage terms, students, attendance, reports, fees.
 * Parent: view children, attendance, reports, pay fees.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Madrassah {

    const NS = 'ynj/v1';

    public static function register() {

        // ── Admin endpoints (mosque admin auth) ──

        // Terms
        register_rest_route( self::NS, '/admin/madrassah/terms', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_terms' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/terms', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'admin_create_term' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/terms/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'admin_update_term' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/terms/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [ __CLASS__, 'admin_delete_term' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // Students
        register_rest_route( self::NS, '/admin/madrassah/students', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_students' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/students', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'admin_add_student' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/students/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [ __CLASS__, 'admin_update_student' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/students/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [ __CLASS__, 'admin_delete_student' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // Attendance
        register_rest_route( self::NS, '/admin/madrassah/attendance', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_attendance' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/attendance', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'admin_mark_attendance' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // Reports
        register_rest_route( self::NS, '/admin/madrassah/reports', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_reports' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/reports', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'admin_create_report' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // Fees
        register_rest_route( self::NS, '/admin/madrassah/fees', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_fees' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );
        register_rest_route( self::NS, '/admin/madrassah/fees/generate', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'admin_generate_fees' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // Dashboard stats
        register_rest_route( self::NS, '/admin/madrassah/stats', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'admin_stats' ],
            'permission_callback' => [ 'YNJ_Auth', 'check' ],
        ] );

        // ── Parent endpoints (user auth) ──

        register_rest_route( self::NS, '/madrassah/children', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'parent_children' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );
        register_rest_route( self::NS, '/madrassah/enrol', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'parent_enrol' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );
        register_rest_route( self::NS, '/madrassah/attendance/(?P<student_id>\d+)', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'parent_attendance' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );
        register_rest_route( self::NS, '/madrassah/reports/(?P<student_id>\d+)', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'parent_reports' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );
        register_rest_route( self::NS, '/madrassah/fees', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'parent_fees' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );
        register_rest_route( self::NS, '/madrassah/fees/(?P<fee_id>\d+)/pay', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'parent_pay_fee' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // Public: mosque madrassah info
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/madrassah', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'public_info' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ================================================================
    // ADMIN: TERMS
    // ================================================================

    public static function admin_terms( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'madrassah_terms' ) . " WHERE mosque_id = %d ORDER BY start_date DESC",
            $mosque->id
        ) );
        return new \WP_REST_Response( [ 'ok' => true, 'terms' => array_map( [ __CLASS__, 'fmt_term' ], $rows ) ] );
    }

    public static function admin_create_term( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $d = $r->get_json_params();

        $name = sanitize_text_field( $d['name'] ?? '' );
        $start = sanitize_text_field( $d['start_date'] ?? '' );
        $end = sanitize_text_field( $d['end_date'] ?? '' );

        if ( ! $name || ! $start || ! $end ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Name, start_date and end_date required.' ], 400 );
        }

        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'madrassah_terms' ), [
            'mosque_id'     => (int) $mosque->id,
            'name'          => $name,
            'start_date'    => $start,
            'end_date'      => $end,
            'fee_pence'     => absint( $d['fee_pence'] ?? 0 ),
            'fee_frequency' => sanitize_text_field( $d['fee_frequency'] ?? 'termly' ),
            'enrolment_open' => absint( $d['enrolment_open'] ?? 1 ),
            'status'        => 'active',
        ] );
        $id = (int) $wpdb->insert_id;

        return new \WP_REST_Response( [ 'ok' => true, 'id' => $id ], 201 );
    }

    public static function admin_update_term( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $id = absint( $r->get_param( 'id' ) );
        $d = $r->get_json_params();

        global $wpdb;
        $t = YNJ_DB::table( 'madrassah_terms' );

        $allowed = [ 'name', 'start_date', 'end_date', 'fee_pence', 'fee_frequency', 'enrolment_open', 'status' ];
        $update = [];
        foreach ( $allowed as $k ) {
            if ( isset( $d[$k] ) ) $update[$k] = is_numeric( $d[$k] ) ? absint( $d[$k] ) : sanitize_text_field( $d[$k] );
        }
        if ( $update ) $wpdb->update( $t, $update, [ 'id' => $id, 'mosque_id' => (int) $mosque->id ] );

        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function admin_delete_term( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $wpdb->delete( YNJ_DB::table( 'madrassah_terms' ), [ 'id' => absint( $r->get_param( 'id' ) ), 'mosque_id' => (int) $mosque->id ] );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    // ================================================================
    // ADMIN: STUDENTS
    // ================================================================

    public static function admin_students( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $t = YNJ_DB::table( 'madrassah_students' );
        $ct = YNJ_DB::table( 'classes' );

        $year_group = sanitize_text_field( $r->get_param( 'year_group' ) ?? '' );
        $class_id = absint( $r->get_param( 'class_id' ) ?? 0 );

        $where = $wpdb->prepare( "s.mosque_id = %d AND s.status = 'active'", $mosque->id );
        if ( $year_group ) $where .= $wpdb->prepare( " AND s.year_group = %s", $year_group );
        if ( $class_id ) $where .= $wpdb->prepare( " AND s.class_id = %d", $class_id );

        $rows = $wpdb->get_results(
            "SELECT s.*, c.title AS class_title FROM $t s LEFT JOIN $ct c ON c.id = s.class_id WHERE $where ORDER BY s.year_group ASC, s.child_name ASC"
        );

        return new \WP_REST_Response( [ 'ok' => true, 'students' => array_map( [ __CLASS__, 'fmt_student' ], $rows ) ] );
    }

    public static function admin_add_student( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $d = $r->get_json_params();

        $child_name = sanitize_text_field( $d['child_name'] ?? '' );
        $parent_name = sanitize_text_field( $d['parent_name'] ?? '' );
        $parent_email = sanitize_email( $d['parent_email'] ?? '' );

        if ( ! $child_name || ! $parent_name ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'child_name and parent_name required.' ], 400 );
        }

        // Try to find parent user by email
        $parent_user_id = 0;
        if ( $parent_email ) {
            global $wpdb;
            $parent_user_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . YNJ_DB::table( 'users' ) . " WHERE email = %s",
                $parent_email
            ) );
        }

        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'madrassah_students' ), [
            'mosque_id'         => (int) $mosque->id,
            'parent_user_id'    => $parent_user_id,
            'parent_name'       => $parent_name,
            'parent_email'      => $parent_email,
            'parent_phone'      => sanitize_text_field( $d['parent_phone'] ?? '' ),
            'child_name'        => $child_name,
            'child_dob'         => ! empty( $d['child_dob'] ) ? sanitize_text_field( $d['child_dob'] ) : null,
            'year_group'        => sanitize_text_field( $d['year_group'] ?? '' ),
            'class_id'          => absint( $d['class_id'] ?? 0 ) ?: null,
            'medical_notes'     => sanitize_textarea_field( $d['medical_notes'] ?? '' ),
            'emergency_contact' => sanitize_text_field( $d['emergency_contact'] ?? '' ),
            'emergency_phone'   => sanitize_text_field( $d['emergency_phone'] ?? '' ),
            'status'            => 'active',
        ] );

        return new \WP_REST_Response( [ 'ok' => true, 'id' => (int) $wpdb->insert_id ], 201 );
    }

    public static function admin_update_student( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $id = absint( $r->get_param( 'id' ) );
        $d = $r->get_json_params();

        global $wpdb;
        $t = YNJ_DB::table( 'madrassah_students' );

        $allowed = [ 'child_name', 'child_dob', 'year_group', 'class_id', 'parent_name', 'parent_email', 'parent_phone', 'medical_notes', 'emergency_contact', 'emergency_phone', 'status' ];
        $update = [];
        foreach ( $allowed as $k ) {
            if ( ! isset( $d[$k] ) ) continue;
            if ( $k === 'parent_email' ) $update[$k] = sanitize_email( $d[$k] );
            elseif ( $k === 'class_id' ) $update[$k] = absint( $d[$k] ) ?: null;
            elseif ( $k === 'medical_notes' ) $update[$k] = sanitize_textarea_field( $d[$k] );
            else $update[$k] = sanitize_text_field( $d[$k] );
        }

        if ( $update ) $wpdb->update( $t, $update, [ 'id' => $id, 'mosque_id' => (int) $mosque->id ] );

        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function admin_delete_student( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;
        $wpdb->update(
            YNJ_DB::table( 'madrassah_students' ),
            [ 'status' => 'withdrawn' ],
            [ 'id' => absint( $r->get_param( 'id' ) ), 'mosque_id' => (int) $mosque->id ]
        );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    // ================================================================
    // ADMIN: ATTENDANCE
    // ================================================================

    public static function admin_attendance( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $date = sanitize_text_field( $r->get_param( 'date' ) ?? date( 'Y-m-d' ) );
        $class_id = absint( $r->get_param( 'class_id' ) ?? 0 );

        global $wpdb;
        $st = YNJ_DB::table( 'madrassah_students' );
        $at = YNJ_DB::table( 'madrassah_attendance' );

        // Get all students (optionally filtered by class)
        $where = $wpdb->prepare( "s.mosque_id = %d AND s.status = 'active'", $mosque->id );
        if ( $class_id ) $where .= $wpdb->prepare( " AND s.class_id = %d", $class_id );

        $students = $wpdb->get_results(
            "SELECT s.id, s.child_name, s.year_group, s.class_id,
                    a.status AS att_status, a.notes AS att_notes
             FROM $st s
             LEFT JOIN $at a ON a.student_id = s.id AND a.attendance_date = '$date'" .
             ( $class_id ? " AND a.class_id = $class_id" : "" ) .
            " WHERE $where ORDER BY s.year_group ASC, s.child_name ASC"
        );

        $result = array_map( function( $s ) {
            return [
                'student_id' => (int) $s->id,
                'child_name' => $s->child_name,
                'year_group' => $s->year_group,
                'class_id'   => $s->class_id ? (int) $s->class_id : null,
                'status'     => $s->att_status ?? 'unmarked',
                'notes'      => $s->att_notes ?? '',
            ];
        }, $students );

        return new \WP_REST_Response( [ 'ok' => true, 'date' => $date, 'attendance' => $result ] );
    }

    public static function admin_mark_attendance( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $d = $r->get_json_params();

        $date = sanitize_text_field( $d['date'] ?? date( 'Y-m-d' ) );
        $records = $d['records'] ?? [];
        $class_id = absint( $d['class_id'] ?? 0 );

        if ( empty( $records ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'No records provided.' ], 400 );
        }

        global $wpdb;
        $at = YNJ_DB::table( 'madrassah_attendance' );
        $count = 0;

        foreach ( $records as $rec ) {
            $student_id = absint( $rec['student_id'] ?? 0 );
            $status = sanitize_text_field( $rec['status'] ?? 'present' );
            $notes = sanitize_text_field( $rec['notes'] ?? '' );

            if ( ! $student_id ) continue;
            if ( ! in_array( $status, [ 'present', 'absent', 'late', 'excused' ], true ) ) continue;

            // Upsert
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $at WHERE student_id = %d AND attendance_date = %s AND class_id = %d",
                $student_id, $date, $class_id
            ) );

            if ( $existing ) {
                $wpdb->update( $at, [
                    'status' => $status,
                    'notes'  => $notes,
                ], [ 'id' => $existing ] );
            } else {
                $wpdb->insert( $at, [
                    'student_id'      => $student_id,
                    'class_id'        => $class_id,
                    'attendance_date' => $date,
                    'status'          => $status,
                    'notes'           => $notes,
                    'marked_by'       => $mosque->admin_email ?? '',
                ] );
            }
            $count++;
        }

        return new \WP_REST_Response( [ 'ok' => true, 'marked' => $count ] );
    }

    // ================================================================
    // ADMIN: REPORTS
    // ================================================================

    public static function admin_reports( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $term_id = absint( $r->get_param( 'term_id' ) ?? 0 );
        $student_id = absint( $r->get_param( 'student_id' ) ?? 0 );

        global $wpdb;
        $rt = YNJ_DB::table( 'madrassah_reports' );
        $st = YNJ_DB::table( 'madrassah_students' );

        $where = $wpdb->prepare( "s.mosque_id = %d", $mosque->id );
        if ( $term_id ) $where .= $wpdb->prepare( " AND r.term_id = %d", $term_id );
        if ( $student_id ) $where .= $wpdb->prepare( " AND r.student_id = %d", $student_id );

        $rows = $wpdb->get_results(
            "SELECT r.*, s.child_name FROM $rt r INNER JOIN $st s ON s.id = r.student_id WHERE $where ORDER BY r.created_at DESC LIMIT 100"
        );

        $reports = array_map( function( $r ) {
            return [
                'id'             => (int) $r->id,
                'student_id'     => (int) $r->student_id,
                'child_name'     => $r->child_name,
                'term_id'        => (int) $r->term_id,
                'subject'        => $r->subject,
                'grade'          => $r->grade,
                'teacher_notes'  => $r->teacher_notes,
                'quran_progress' => $r->quran_progress,
                'behaviour'      => $r->behaviour,
                'created_by'     => $r->created_by,
                'created_at'     => $r->created_at,
            ];
        }, $rows );

        return new \WP_REST_Response( [ 'ok' => true, 'reports' => $reports ] );
    }

    public static function admin_create_report( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $d = $r->get_json_params();

        $student_id = absint( $d['student_id'] ?? 0 );
        $term_id = absint( $d['term_id'] ?? 0 );
        if ( ! $student_id || ! $term_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'student_id and term_id required.' ], 400 );
        }

        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'madrassah_reports' ), [
            'student_id'     => $student_id,
            'term_id'        => $term_id,
            'class_id'       => absint( $d['class_id'] ?? 0 ) ?: null,
            'subject'        => sanitize_text_field( $d['subject'] ?? 'General' ),
            'grade'          => sanitize_text_field( $d['grade'] ?? '' ),
            'teacher_notes'  => sanitize_textarea_field( $d['teacher_notes'] ?? '' ),
            'quran_progress' => sanitize_text_field( $d['quran_progress'] ?? '' ),
            'behaviour'      => in_array( $d['behaviour'] ?? '', [ 'excellent', 'good', 'satisfactory', 'needs_improvement' ], true )
                ? $d['behaviour'] : 'good',
            'created_by'     => $mosque->admin_email ?? '',
        ] );

        return new \WP_REST_Response( [ 'ok' => true, 'id' => (int) $wpdb->insert_id ], 201 );
    }

    // ================================================================
    // ADMIN: FEES
    // ================================================================

    public static function admin_fees( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $term_id = absint( $r->get_param( 'term_id' ) ?? 0 );
        $status = sanitize_text_field( $r->get_param( 'status' ) ?? '' );

        global $wpdb;
        $ft = YNJ_DB::table( 'madrassah_fees' );
        $st = YNJ_DB::table( 'madrassah_students' );
        $tt = YNJ_DB::table( 'madrassah_terms' );

        $where = $wpdb->prepare( "f.mosque_id = %d", $mosque->id );
        if ( $term_id ) $where .= $wpdb->prepare( " AND f.term_id = %d", $term_id );
        if ( $status ) $where .= $wpdb->prepare( " AND f.status = %s", $status );

        $rows = $wpdb->get_results(
            "SELECT f.*, s.child_name, s.parent_name, t.name AS term_name
             FROM $ft f
             INNER JOIN $st s ON s.id = f.student_id
             LEFT JOIN $tt t ON t.id = f.term_id
             WHERE $where ORDER BY f.status ASC, f.due_date ASC LIMIT 200"
        );

        $fees = array_map( function( $f ) {
            return [
                'id'           => (int) $f->id,
                'student_id'   => (int) $f->student_id,
                'child_name'   => $f->child_name,
                'parent_name'  => $f->parent_name,
                'term_name'    => $f->term_name,
                'amount_pence' => (int) $f->amount_pence,
                'status'       => $f->status,
                'due_date'     => $f->due_date,
                'paid_at'      => $f->paid_at,
            ];
        }, $rows );

        // Summary
        $summary = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN status='paid' THEN amount_pence ELSE 0 END) AS paid_pence,
                    SUM(CASE WHEN status='unpaid' THEN amount_pence ELSE 0 END) AS outstanding_pence
             FROM $ft WHERE mosque_id = %d" . ( $term_id ? " AND term_id = $term_id" : '' ),
            $mosque->id
        ) );

        return new \WP_REST_Response( [
            'ok'                => true,
            'fees'              => $fees,
            'total'             => (int) $summary->total,
            'paid_count'        => (int) $summary->paid_count,
            'paid_pence'        => (int) $summary->paid_pence,
            'outstanding_pence' => (int) $summary->outstanding_pence,
        ] );
    }

    public static function admin_generate_fees( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        $d = $r->get_json_params();

        $term_id = absint( $d['term_id'] ?? 0 );
        if ( ! $term_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'term_id required.' ], 400 );
        }

        global $wpdb;
        $term = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'madrassah_terms' ) . " WHERE id = %d AND mosque_id = %d",
            $term_id, $mosque->id
        ) );
        if ( ! $term ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Term not found.' ], 404 );

        $students = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, parent_user_id FROM " . YNJ_DB::table( 'madrassah_students' ) . " WHERE mosque_id = %d AND status = 'active'",
            $mosque->id
        ) );

        $ft = YNJ_DB::table( 'madrassah_fees' );
        $created = 0;

        foreach ( $students as $s ) {
            // Skip if fee already exists for this student+term
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $ft WHERE student_id = %d AND term_id = %d", $s->id, $term_id
            ) );
            if ( $exists ) continue;

            $wpdb->insert( $ft, [
                'mosque_id'       => (int) $mosque->id,
                'student_id'      => (int) $s->id,
                'term_id'         => $term_id,
                'parent_user_id'  => (int) $s->parent_user_id,
                'amount_pence'    => (int) $term->fee_pence,
                'status'          => 'unpaid',
                'due_date'        => $term->start_date,
            ] );
            $created++;
        }

        return new \WP_REST_Response( [ 'ok' => true, 'created' => $created, 'message' => "$created fee records generated." ] );
    }

    // ================================================================
    // ADMIN: STATS
    // ================================================================

    public static function admin_stats( \WP_REST_Request $r ) {
        $mosque = $r->get_param( '_ynj_mosque' );
        global $wpdb;

        $total_students = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'madrassah_students' ) . " WHERE mosque_id = %d AND status = 'active'",
            $mosque->id
        ) );

        $today = date( 'Y-m-d' );
        $present_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'madrassah_attendance' ) . " a
             INNER JOIN " . YNJ_DB::table( 'madrassah_students' ) . " s ON s.id = a.student_id
             WHERE s.mosque_id = %d AND a.attendance_date = %s AND a.status = 'present'",
            $mosque->id, $today
        ) );

        $unpaid_fees = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'madrassah_fees' ) . " WHERE mosque_id = %d AND status = 'unpaid'",
            $mosque->id
        ) );

        $current_term = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'madrassah_terms' ) . " WHERE mosque_id = %d AND start_date <= %s AND end_date >= %s AND status = 'active' ORDER BY start_date DESC LIMIT 1",
            $mosque->id, $today, $today
        ) );

        $year_groups = $wpdb->get_results( $wpdb->prepare(
            "SELECT year_group, COUNT(*) AS count FROM " . YNJ_DB::table( 'madrassah_students' ) . " WHERE mosque_id = %d AND status = 'active' AND year_group != '' GROUP BY year_group ORDER BY year_group",
            $mosque->id
        ) );

        return new \WP_REST_Response( [
            'ok'              => true,
            'total_students'  => $total_students,
            'present_today'   => $present_today,
            'unpaid_fees'     => $unpaid_fees,
            'current_term'    => $current_term ? self::fmt_term( $current_term ) : null,
            'year_groups'     => $year_groups,
        ] );
    }

    // ================================================================
    // PARENT: CHILDREN & DATA
    // ================================================================

    public static function parent_children( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );

        global $wpdb;
        $st = YNJ_DB::table( 'madrassah_students' );
        $mt = YNJ_DB::table( 'mosques' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*, m.name AS mosque_name, m.slug AS mosque_slug
             FROM $st s INNER JOIN $mt m ON m.id = s.mosque_id
             WHERE (s.parent_user_id = %d OR s.parent_email = %s) AND s.status = 'active'
             ORDER BY s.child_name",
            $user->id, $user->email
        ) );

        return new \WP_REST_Response( [ 'ok' => true, 'children' => array_map( [ __CLASS__, 'fmt_student' ], $rows ) ] );
    }

    public static function parent_enrol( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );
        $d = $r->get_json_params();

        $mosque_id = absint( $d['mosque_id'] ?? 0 );
        if ( ! $mosque_id && ! empty( $d['mosque_slug'] ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $d['mosque_slug'] );
        }

        $child_name = sanitize_text_field( $d['child_name'] ?? '' );
        if ( ! $mosque_id || ! $child_name ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque and child_name required.' ], 400 );
        }

        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'madrassah_students' ), [
            'mosque_id'         => $mosque_id,
            'parent_user_id'    => (int) $user->id,
            'parent_name'       => $user->name,
            'parent_email'      => $user->email,
            'parent_phone'      => $user->phone,
            'child_name'        => $child_name,
            'child_dob'         => ! empty( $d['child_dob'] ) ? sanitize_text_field( $d['child_dob'] ) : null,
            'year_group'        => sanitize_text_field( $d['year_group'] ?? '' ),
            'medical_notes'     => sanitize_textarea_field( $d['medical_notes'] ?? '' ),
            'emergency_contact' => sanitize_text_field( $d['emergency_contact'] ?? '' ),
            'emergency_phone'   => sanitize_text_field( $d['emergency_phone'] ?? '' ),
            'status'            => 'active',
        ] );

        return new \WP_REST_Response( [ 'ok' => true, 'id' => (int) $wpdb->insert_id, 'message' => 'Child enrolled in madrassah.' ], 201 );
    }

    public static function parent_attendance( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );
        $student_id = absint( $r->get_param( 'student_id' ) );

        // Verify parent owns this student
        global $wpdb;
        $st = YNJ_DB::table( 'madrassah_students' );
        $owns = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $st WHERE id = %d AND (parent_user_id = %d OR parent_email = %s)",
            $student_id, $user->id, $user->email
        ) );
        if ( ! $owns ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not found.' ], 404 );

        $at = YNJ_DB::table( 'madrassah_attendance' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT attendance_date, status, notes FROM $at WHERE student_id = %d ORDER BY attendance_date DESC LIMIT 90",
            $student_id
        ) );

        // Stats
        $total = count( $rows );
        $present = count( array_filter( $rows, function( $r ) { return $r->status === 'present'; } ) );
        $pct = $total > 0 ? round( $present / $total * 100 ) : 0;

        return new \WP_REST_Response( [
            'ok' => true,
            'records' => $rows,
            'total_days' => $total,
            'present_days' => $present,
            'attendance_pct' => $pct,
        ] );
    }

    public static function parent_reports( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );
        $student_id = absint( $r->get_param( 'student_id' ) );

        global $wpdb;
        $st = YNJ_DB::table( 'madrassah_students' );
        $owns = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $st WHERE id = %d AND (parent_user_id = %d OR parent_email = %s)",
            $student_id, $user->id, $user->email
        ) );
        if ( ! $owns ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not found.' ], 404 );

        $rt = YNJ_DB::table( 'madrassah_reports' );
        $tt = YNJ_DB::table( 'madrassah_terms' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, t.name AS term_name FROM $rt r LEFT JOIN $tt t ON t.id = r.term_id WHERE r.student_id = %d ORDER BY r.created_at DESC",
            $student_id
        ) );

        $reports = array_map( function( $r ) {
            return [
                'id'             => (int) $r->id,
                'term_name'      => $r->term_name,
                'subject'        => $r->subject,
                'grade'          => $r->grade,
                'teacher_notes'  => $r->teacher_notes,
                'quran_progress' => $r->quran_progress,
                'behaviour'      => $r->behaviour,
                'created_at'     => $r->created_at,
            ];
        }, $rows );

        return new \WP_REST_Response( [ 'ok' => true, 'reports' => $reports ] );
    }

    // ================================================================
    // PARENT: FEES
    // ================================================================

    public static function parent_fees( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );

        global $wpdb;
        $ft = YNJ_DB::table( 'madrassah_fees' );
        $st = YNJ_DB::table( 'madrassah_students' );
        $tt = YNJ_DB::table( 'madrassah_terms' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT f.*, s.child_name, t.name AS term_name
             FROM $ft f
             INNER JOIN $st s ON s.id = f.student_id
             LEFT JOIN $tt t ON t.id = f.term_id
             WHERE f.parent_user_id = %d OR s.parent_email = %s
             ORDER BY f.status ASC, f.due_date DESC LIMIT 50",
            $user->id, $user->email
        ) );

        $fees = array_map( function( $f ) {
            return [
                'id'           => (int) $f->id,
                'child_name'   => $f->child_name,
                'term_name'    => $f->term_name,
                'amount_pence' => (int) $f->amount_pence,
                'status'       => $f->status,
                'due_date'     => $f->due_date,
                'paid_at'      => $f->paid_at,
            ];
        }, $rows );

        return new \WP_REST_Response( [ 'ok' => true, 'fees' => $fees ] );
    }

    public static function parent_pay_fee( \WP_REST_Request $r ) {
        $user = $r->get_param( '_ynj_user' );
        $fee_id = absint( $r->get_param( 'fee_id' ) );

        global $wpdb;
        $ft = YNJ_DB::table( 'madrassah_fees' );
        $st = YNJ_DB::table( 'madrassah_students' );

        $fee = $wpdb->get_row( $wpdb->prepare(
            "SELECT f.*, s.child_name, s.mosque_id, s.parent_email
             FROM $ft f INNER JOIN $st s ON s.id = f.student_id
             WHERE f.id = %d AND f.status = 'unpaid'
             AND (f.parent_user_id = %d OR s.parent_email = %s)",
            $fee_id, $user->id, $user->email
        ) );

        if ( ! $fee ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Fee not found or already paid.' ], 404 );
        }

        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $fee->mosque_id
        ) );
        $base = home_url( "/mosque/" . ( $mosque->slug ?? '' ) );

        $term = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM " . YNJ_DB::table( 'madrassah_terms' ) . " WHERE id = %d", $fee->term_id
        ) );

        $session = YNJ_Stripe::create_checkout(
            'madrassah_fee',
            $fee_id,
            $fee->amount_pence,
            'Madrassah Fee: ' . $fee->child_name . ' — ' . ( $term ?: 'Term' ),
            $base . '/madrassah?payment=success',
            $base . '/madrassah?payment=cancelled',
            [ 'mosque_id' => $fee->mosque_id, 'student_id' => $fee->student_id, 'term_id' => $fee->term_id ]
        );

        if ( is_wp_error( $session ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        // Save session ID
        $wpdb->update( $ft, [ 'stripe_session_id' => $session->id ], [ 'id' => $fee_id ] );

        return new \WP_REST_Response( [
            'ok'           => true,
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
        ] );
    }

    // ================================================================
    // PUBLIC: MOSQUE MADRASSAH INFO
    // ================================================================

    public static function public_info( \WP_REST_Request $r ) {
        $mid = YNJ_DB::resolve_slug( $r->get_param( 'slug' ) );
        if ( ! $mid ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );

        global $wpdb;
        $today = date( 'Y-m-d' );

        // Current/upcoming terms
        $terms = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'madrassah_terms' ) . " WHERE mosque_id = %d AND end_date >= %s AND status = 'active' ORDER BY start_date ASC",
            $mid, $today
        ) );

        $student_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'madrassah_students' ) . " WHERE mosque_id = %d AND status = 'active'",
            $mid
        ) );

        // Madrassah classes (category = 'madrassah' or class_type = 'madrassah')
        $classes = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, instructor_name, schedule_text, day_of_week, start_time, end_time, max_capacity, enrolled_count, year_group
             FROM " . YNJ_DB::table( 'classes' ) . "
             WHERE mosque_id = %d AND status = 'active' AND (category = 'madrassah' OR class_type = 'madrassah')
             ORDER BY title",
            $mid
        ) );

        return new \WP_REST_Response( [
            'ok'            => true,
            'terms'         => array_map( [ __CLASS__, 'fmt_term' ], $terms ),
            'student_count' => $student_count,
            'classes'       => $classes,
            'enrolment_open' => ! empty( $terms ) && $terms[0]->enrolment_open,
        ] );
    }

    // ================================================================
    // FORMATTERS
    // ================================================================

    private static function fmt_term( $t ) {
        return [
            'id'             => (int) $t->id,
            'name'           => $t->name,
            'start_date'     => $t->start_date,
            'end_date'       => $t->end_date,
            'fee_pence'      => (int) $t->fee_pence,
            'fee_frequency'  => $t->fee_frequency,
            'enrolment_open' => (bool) $t->enrolment_open,
            'status'         => $t->status,
        ];
    }

    private static function fmt_student( $s ) {
        return [
            'id'                => (int) $s->id,
            'mosque_id'         => (int) $s->mosque_id,
            'child_name'        => $s->child_name,
            'child_dob'         => $s->child_dob ?? null,
            'year_group'        => $s->year_group,
            'class_id'          => $s->class_id ? (int) $s->class_id : null,
            'class_title'       => $s->class_title ?? null,
            'parent_name'       => $s->parent_name,
            'parent_email'      => $s->parent_email,
            'parent_phone'      => $s->parent_phone,
            'mosque_name'       => $s->mosque_name ?? null,
            'mosque_slug'       => $s->mosque_slug ?? null,
            'medical_notes'     => $s->medical_notes ?? '',
            'emergency_contact' => $s->emergency_contact,
            'emergency_phone'   => $s->emergency_phone,
            'status'            => $s->status,
            'enrolled_at'       => $s->enrolled_at,
        ];
    }
}
