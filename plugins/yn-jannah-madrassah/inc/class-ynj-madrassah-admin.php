<?php
/**
 * YourJannah Madrassah — WP Admin pages.
 *
 * Top-level "Madrassah" menu with list table for classes,
 * "Enrolments" submenu, and edit class form.
 *
 * @package YNJ_Madrassah
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Madrassah_Admin {

    /** Boot admin hooks. */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
    }

    /* ==============================================================
     *  MENU REGISTRATION
     * ============================================================ */

    public static function register_menus() {
        add_menu_page(
            'Madrassah',
            'Madrassah',
            'manage_options',
            'ynj-madrassah',
            [ __CLASS__, 'page_classes' ],
            'dashicons-welcome-learn-more',
            32
        );

        add_submenu_page(
            'ynj-madrassah',
            'All Classes',
            'All Classes',
            'manage_options',
            'ynj-madrassah',
            [ __CLASS__, 'page_classes' ]
        );

        add_submenu_page(
            'ynj-madrassah',
            'Enrolments',
            'Enrolments',
            'manage_options',
            'ynj-enrolments',
            [ __CLASS__, 'page_enrolments' ]
        );

        /* Hidden page — edit class form. */
        add_submenu_page(
            null,
            'Edit Class',
            'Edit Class',
            'manage_options',
            'ynj-class-edit',
            [ __CLASS__, 'page_class_edit' ]
        );
    }

    /* ==============================================================
     *  ACTION HANDLER
     * ============================================================ */

    public static function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        /* ---- Class save ---- */
        if ( isset( $_POST['ynj_class_save'] ) ) {
            check_admin_referer( 'ynj_class_save', 'ynj_class_nonce' );
            self::save_class();
            return;
        }

        /* ---- Inline actions ---- */
        $action = sanitize_text_field( $_GET['ynj_action'] ?? '' );
        if ( ! $action ) return;

        $id = absint( $_GET['id'] ?? 0 );
        if ( ! $id ) return;

        check_admin_referer( 'ynj_action_' . $action . '_' . $id );

        global $wpdb;

        switch ( $action ) {

            case 'class_activate':
                $wpdb->update( YNJ_DB::table( 'classes' ), [ 'status' => 'active' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-madrassah&msg=updated' ) );
                exit;

            case 'class_deactivate':
                $wpdb->update( YNJ_DB::table( 'classes' ), [ 'status' => 'inactive' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-madrassah&msg=updated' ) );
                exit;

            case 'enrolment_remove':
                $wpdb->update( YNJ_DB::table( 'enrolments' ), [ 'status' => 'removed' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-enrolments&msg=updated' ) );
                exit;
        }
    }

    /* ==============================================================
     *  CLASS SAVE
     * ============================================================ */

    private static function save_class() {
        global $wpdb;
        $table = YNJ_DB::table( 'classes' );
        $id    = absint( $_POST['class_id'] ?? 0 );

        $data = [
            'mosque_id'    => absint( $_POST['mosque_id'] ?? 0 ),
            'title'        => sanitize_text_field( $_POST['title'] ?? '' ),
            'teacher'      => sanitize_text_field( $_POST['teacher'] ?? '' ),
            'day_of_week'  => sanitize_text_field( $_POST['day_of_week'] ?? '' ),
            'start_time'   => sanitize_text_field( $_POST['start_time'] ?? '' ),
            'end_time'     => sanitize_text_field( $_POST['end_time'] ?? '' ),
            'age_group'    => sanitize_text_field( $_POST['age_group'] ?? '' ),
            'gender'       => sanitize_text_field( $_POST['gender'] ?? 'mixed' ),
            'max_students' => absint( $_POST['max_students'] ?? 0 ),
            'fee_pence'    => absint( $_POST['fee_pence'] ?? 0 ),
            'description'  => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'status'       => sanitize_text_field( $_POST['status'] ?? 'active' ),
        ];

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
            $msg = 'updated';
        } else {
            $wpdb->insert( $table, $data );
            $id  = (int) $wpdb->insert_id;
            $msg = 'created';
        }

        wp_safe_redirect( admin_url( 'admin.php?page=ynj-madrassah&msg=' . $msg ) );
        exit;
    }

    /* ==============================================================
     *  PAGE: CLASSES LIST
     * ============================================================ */

    public static function page_classes() {
        $table = new YNJ_Classes_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Madrassah Classes</h1>';
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ynj-class-edit' ) ) . '" class="page-title-action">Add New</a>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-madrassah">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: ENROLMENTS LIST
     * ============================================================ */

    public static function page_enrolments() {
        $table = new YNJ_Enrolments_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Enrolments</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-enrolments">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: EDIT CLASS FORM
     * ============================================================ */

    public static function page_class_edit() {
        global $wpdb;

        $id    = absint( $_GET['id'] ?? 0 );
        $class = null;

        if ( $id ) {
            $class = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . YNJ_DB::table( 'classes' ) . " WHERE id = %d", $id
            ) );
            if ( ! $class ) {
                wp_die( 'Class not found.' );
            }
        }

        $mosques = $wpdb->get_results(
            "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
        );

        $v = function( $field, $default = '' ) use ( $class ) {
            return $class ? esc_attr( $class->$field ?? $default ) : esc_attr( $default );
        };

        echo '<div class="wrap">';
        echo '<h1>' . ( $id ? 'Edit Class' : 'Add New Class' ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        wp_nonce_field( 'ynj_class_save', 'ynj_class_nonce' );
        echo '<input type="hidden" name="class_id" value="' . $id . '">';

        echo '<table class="form-table">';

        // Mosque
        echo '<tr><th><label for="mosque_id">Mosque</label></th><td>';
        echo '<select name="mosque_id" id="mosque_id" class="regular-text" required>';
        echo '<option value="">-- Select --</option>';
        foreach ( $mosques as $m ) {
            $sel = ( $class && (int) $class->mosque_id === (int) $m->id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $m->id ) . '"' . $sel . '>' . esc_html( $m->name ) . '</option>';
        }
        echo '</select></td></tr>';

        // Title
        echo '<tr><th><label for="title">Title</label></th><td>';
        echo '<input type="text" name="title" id="title" class="regular-text" required value="' . $v( 'title' ) . '"></td></tr>';

        // Teacher
        echo '<tr><th><label for="teacher">Teacher</label></th><td>';
        echo '<input type="text" name="teacher" id="teacher" class="regular-text" value="' . $v( 'teacher' ) . '"></td></tr>';

        // Day of Week
        echo '<tr><th><label for="day_of_week">Day</label></th><td>';
        echo '<select name="day_of_week" id="day_of_week" class="regular-text">';
        $days = [ '' => '-- Select --', 'Monday' => 'Monday', 'Tuesday' => 'Tuesday', 'Wednesday' => 'Wednesday', 'Thursday' => 'Thursday', 'Friday' => 'Friday', 'Saturday' => 'Saturday', 'Sunday' => 'Sunday' ];
        foreach ( $days as $val => $label ) {
            $sel = ( $class && $class->day_of_week === $val ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        // Start / End Time
        echo '<tr><th><label for="start_time">Start Time</label></th><td>';
        echo '<input type="time" name="start_time" id="start_time" value="' . $v( 'start_time' ) . '"></td></tr>';

        echo '<tr><th><label for="end_time">End Time</label></th><td>';
        echo '<input type="time" name="end_time" id="end_time" value="' . $v( 'end_time' ) . '"></td></tr>';

        // Age Group
        echo '<tr><th><label for="age_group">Age Group</label></th><td>';
        echo '<input type="text" name="age_group" id="age_group" class="regular-text" value="' . $v( 'age_group' ) . '">';
        echo '<p class="description">e.g. "5-10", "11-16", "Adults"</p></td></tr>';

        // Gender
        echo '<tr><th><label for="gender">Gender</label></th><td>';
        echo '<select name="gender" id="gender" class="regular-text">';
        $genders = [ 'mixed' => 'Mixed', 'male' => 'Male Only', 'female' => 'Female Only' ];
        foreach ( $genders as $val => $label ) {
            $sel = ( $class && $class->gender === $val ) ? ' selected' : ( ! $class && $val === 'mixed' ? ' selected' : '' );
            echo '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        // Max Students
        echo '<tr><th><label for="max_students">Max Students</label></th><td>';
        echo '<input type="number" name="max_students" id="max_students" min="0" value="' . $v( 'max_students', '0' ) . '">';
        echo '<p class="description">0 = unlimited</p></td></tr>';

        // Fee
        echo '<tr><th><label for="fee_pence">Fee (pence)</label></th><td>';
        echo '<input type="number" name="fee_pence" id="fee_pence" min="0" value="' . $v( 'fee_pence', '0' ) . '">';
        echo '<p class="description">0 = free</p></td></tr>';

        // Description
        echo '<tr><th><label for="description">Description</label></th><td>';
        echo '<textarea name="description" id="description" rows="4" class="large-text">' . ( $class ? esc_textarea( $class->description ) : '' ) . '</textarea></td></tr>';

        // Status
        echo '<tr><th><label for="status">Status</label></th><td>';
        echo '<select name="status" id="status">';
        $statuses = [ 'active' => 'Active', 'inactive' => 'Inactive' ];
        foreach ( $statuses as $val => $label ) {
            $sel = ( $class && $class->status === $val ) ? ' selected' : ( ! $class && $val === 'active' ? ' selected' : '' );
            echo '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
        }
        echo '</select></td></tr>';

        echo '</table>';

        submit_button( $id ? 'Update Class' : 'Create Class', 'primary', 'ynj_class_save' );

        echo '</form></div>';
    }

    /* ==============================================================
     *  ADMIN NOTICES
     * ============================================================ */

    private static function admin_notices() {
        $msg = sanitize_text_field( $_GET['msg'] ?? '' );
        if ( ! $msg ) return;

        $messages = [
            'updated' => 'Item updated.',
            'created' => 'Item created.',
            'deleted' => 'Item deleted.',
        ];

        if ( isset( $messages[ $msg ] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
        }
    }

    /* ==============================================================
     *  HELPER: mosque name lookup cache
     * ============================================================ */

    private static $mosque_names = null;

    public static function get_mosque_name( $mosque_id ) {
        global $wpdb;

        if ( self::$mosque_names === null ) {
            $rows = $wpdb->get_results(
                "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
            );
            self::$mosque_names = [];
            foreach ( $rows as $r ) {
                self::$mosque_names[ (int) $r->id ] = $r->name;
            }
        }

        return self::$mosque_names[ (int) $mosque_id ] ?? '(#' . $mosque_id . ')';
    }
}


/* =======================================================================
 *  WP_List_Table: CLASSES
 * ===================================================================== */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Classes_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'class',
            'plural'   => 'classes',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'           => 'ID',
            'title'        => 'Class Title',
            'mosque'       => 'Mosque',
            'teacher'      => 'Teacher',
            'day_of_week'  => 'Day',
            'time'         => 'Time',
            'students'     => 'Students/Max',
            'fee'          => 'Fee',
            'status'       => 'Status',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'          => [ 'id', true ],
            'title'       => [ 'title', false ],
            'teacher'     => [ 'teacher', false ],
            'day_of_week' => [ 'day_of_week', false ],
            'status'      => [ 'status', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'classes' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );

        $total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $active   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'active'" );
        $inactive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'inactive'" );

        $base = admin_url( 'admin.php?page=ynj-madrassah' );

        $views = [];
        $views['all']      = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['active']   = '<a href="' . esc_url( $base . '&status=active' ) . '"' . ( $current === 'active' ? ' class="current"' : '' ) . '>Active <span class="count">(' . $active . ')</span></a>';
        $views['inactive'] = '<a href="' . esc_url( $base . '&status=inactive' ) . '"' . ( $current === 'inactive' ? ' class="current"' : '' ) . '>Inactive <span class="count">(' . $inactive . ')</span></a>';

        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $table    = YNJ_DB::table( 'classes' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( in_array( $status, [ 'active', 'inactive' ], true ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'title', 'teacher', 'day_of_week', 'status' ];
        if ( ! in_array( $orderby, $allowed, true ) ) $orderby = 'id';
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $this->items = $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT $per_page OFFSET $offset"
        );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':          return $item->id;
            case 'mosque':      return esc_html( YNJ_Madrassah_Admin::get_mosque_name( $item->mosque_id ) );
            case 'teacher':     return esc_html( $item->teacher ?: '--' );
            case 'day_of_week': return esc_html( $item->day_of_week ?: '--' );
            case 'time':
                if ( $item->start_time && $item->end_time ) {
                    return esc_html( $item->start_time . ' - ' . $item->end_time );
                }
                return $item->start_time ? esc_html( $item->start_time ) : '--';
            case 'students':
                $enrolled = YNJ_Madrassah::enrolment_count( $item->id );
                $max = (int) $item->max_students;
                return $enrolled . '/' . ( $max > 0 ? $max : '&infin;' );
            case 'fee':
                if ( (int) $item->fee_pence > 0 ) return '&pound;' . number_format( $item->fee_pence / 100, 2 );
                return 'Free';
            case 'status':
                return '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            default: return '';
        }
    }

    public function column_title( $item ) {
        $edit_url       = admin_url( 'admin.php?page=ynj-class-edit&id=' . $item->id );
        $enrolments_url = admin_url( 'admin.php?page=ynj-enrolments&class_id=' . $item->id );

        $actions = [
            'edit'       => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
            'enrolments' => '<a href="' . esc_url( $enrolments_url ) . '">View Enrolments</a>',
        ];

        if ( $item->status !== 'active' ) {
            $actions['activate'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-madrassah&ynj_action=class_activate&id=' . $item->id ),
                'ynj_action_class_activate_' . $item->id
            ) ) . '">Activate</a>';
        }

        if ( $item->status !== 'inactive' ) {
            $actions['deactivate'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-madrassah&ynj_action=class_deactivate&id=' . $item->id ),
                'ynj_action_class_deactivate_' . $item->id
            ) ) . '">Deactivate</a>';
        }

        return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->title ) . '</a></strong>'
             . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No classes found.';
    }
}


/* =======================================================================
 *  WP_List_Table: ENROLMENTS
 * ===================================================================== */

class YNJ_Enrolments_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'enrolment',
            'plural'   => 'enrolments',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'         => 'ID',
            'name'       => 'Student Name',
            'email'      => 'Email',
            'class'      => 'Class',
            'mosque'     => 'Mosque',
            'status'     => 'Status',
            'created_at' => 'Date',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'name'       => [ 'name', false ],
            'status'     => [ 'status', false ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'enrolments' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );

        $class_filter = absint( $_GET['class_id'] ?? 0 );
        $filter_sql   = $class_filter ? $wpdb->prepare( " AND class_id = %d", $class_filter ) : '';

        $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE 1=1 $filter_sql" );
        $active  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'active' $filter_sql" );
        $removed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'removed' $filter_sql" );

        $base = admin_url( 'admin.php?page=ynj-enrolments' );
        if ( $class_filter ) $base .= '&class_id=' . $class_filter;

        $views = [];
        $views['all']     = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['active']  = '<a href="' . esc_url( $base . '&status=active' ) . '"' . ( $current === 'active' ? ' class="current"' : '' ) . '>Active <span class="count">(' . $active . ')</span></a>';
        $views['removed'] = '<a href="' . esc_url( $base . '&status=removed' ) . '"' . ( $current === 'removed' ? ' class="current"' : '' ) . '>Removed <span class="count">(' . $removed . ')</span></a>';

        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $et       = YNJ_DB::table( 'enrolments' );
        $ct       = YNJ_DB::table( 'classes' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( in_array( $status, [ 'active', 'removed' ], true ) ) {
            $where .= $wpdb->prepare( ' AND e.status = %s', $status );
        }

        $class_filter = absint( $_GET['class_id'] ?? 0 );
        if ( $class_filter ) {
            $where .= $wpdb->prepare( ' AND e.class_id = %d', $class_filter );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'name', 'status', 'created_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) $orderby = 'id';
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $this->items = $wpdb->get_results(
            "SELECT e.*, c.title AS class_title
             FROM $et e
             LEFT JOIN $ct c ON c.id = e.class_id
             WHERE $where
             ORDER BY e.$orderby $order
             LIMIT $per_page OFFSET $offset"
        );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $et e WHERE $where"
        );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':         return $item->id;
            case 'name':       return esc_html( $item->name );
            case 'email':      return esc_html( $item->email );
            case 'class':      return esc_html( $item->class_title ?? '(#' . $item->class_id . ')' );
            case 'mosque':     return esc_html( YNJ_Madrassah_Admin::get_mosque_name( $item->mosque_id ) );
            case 'created_at': return $item->created_at ? date( 'Y-m-d H:i', strtotime( $item->created_at ) ) : '--';
            default:           return '';
        }
    }

    public function column_status( $item ) {
        $label = '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';

        $actions = [];

        if ( $item->status === 'active' ) {
            $actions['remove'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-enrolments&ynj_action=enrolment_remove&id=' . $item->id ),
                'ynj_action_enrolment_remove_' . $item->id
            ) ) . '" onclick="return confirm(\'Remove this enrolment?\');">Remove</a>';
        }

        return $label . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No enrolments found.';
    }
}
