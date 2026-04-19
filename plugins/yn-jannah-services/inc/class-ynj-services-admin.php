<?php
/**
 * YourJannah Services — WP Admin pages.
 *
 * Top-level "Masjid Services" menu with list table,
 * "Service Enquiries" submenu, and edit service form.
 *
 * @package YNJ_Services
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Services_Admin {

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
            'Masjid Services',
            'Masjid Services',
            'manage_options',
            'ynj-services',
            [ __CLASS__, 'page_services' ],
            'dashicons-admin-tools',
            31
        );

        add_submenu_page(
            'ynj-services',
            'All Services',
            'All Services',
            'manage_options',
            'ynj-services',
            [ __CLASS__, 'page_services' ]
        );

        add_submenu_page(
            'ynj-services',
            'Service Enquiries',
            'Service Enquiries',
            'manage_options',
            'ynj-service-enquiries',
            [ __CLASS__, 'page_enquiries' ]
        );

        /* Hidden page — edit service form. */
        add_submenu_page(
            null,
            'Edit Service',
            'Edit Service',
            'manage_options',
            'ynj-service-edit',
            [ __CLASS__, 'page_service_edit' ]
        );
    }

    /* ==============================================================
     *  ACTION HANDLER
     * ============================================================ */

    public static function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        /* ---- Service save ---- */
        if ( isset( $_POST['ynj_service_save'] ) ) {
            check_admin_referer( 'ynj_service_save', 'ynj_service_nonce' );
            self::save_service();
            return;
        }

        /* ---- Inline actions from list tables ---- */
        $action = sanitize_text_field( $_GET['ynj_action'] ?? '' );
        if ( ! $action ) return;

        $id = absint( $_GET['id'] ?? 0 );
        if ( ! $id ) return;

        check_admin_referer( 'ynj_action_' . $action . '_' . $id );

        global $wpdb;

        switch ( $action ) {

            case 'service_activate':
                $wpdb->update( YNJ_DB::table( 'masjid_services' ), [ 'status' => 'active' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-services&msg=updated' ) );
                exit;

            case 'service_deactivate':
                $wpdb->update( YNJ_DB::table( 'masjid_services' ), [ 'status' => 'inactive' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-services&msg=updated' ) );
                exit;

            case 'enquiry_read':
                $wpdb->update( YNJ_DB::table( 'masjid_service_enquiries' ), [ 'status' => 'read' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-service-enquiries&msg=updated' ) );
                exit;

            case 'enquiry_replied':
                $wpdb->update( YNJ_DB::table( 'masjid_service_enquiries' ), [ 'status' => 'replied' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-service-enquiries&msg=updated' ) );
                exit;
        }
    }

    /* ==============================================================
     *  SERVICE SAVE
     * ============================================================ */

    private static function save_service() {
        global $wpdb;
        $table = YNJ_DB::table( 'masjid_services' );
        $id    = absint( $_POST['service_id'] ?? 0 );

        $data = [
            'mosque_id'         => absint( $_POST['mosque_id'] ?? 0 ),
            'title'             => sanitize_text_field( $_POST['title'] ?? '' ),
            'category'          => sanitize_text_field( $_POST['category'] ?? 'general' ),
            'description'       => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'price_pence'       => absint( $_POST['price_pence'] ?? 0 ),
            'price_label'       => sanitize_text_field( $_POST['price_label'] ?? '' ),
            'contact_phone'     => sanitize_text_field( $_POST['contact_phone'] ?? '' ),
            'contact_email'     => sanitize_email( $_POST['contact_email'] ?? '' ),
            'availability'      => sanitize_text_field( $_POST['availability'] ?? '' ),
            'requires_approval' => absint( $_POST['requires_approval'] ?? 0 ),
            'status'            => sanitize_text_field( $_POST['status'] ?? 'active' ),
        ];

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
            $msg = 'updated';
        } else {
            $wpdb->insert( $table, $data );
            $id  = (int) $wpdb->insert_id;
            $msg = 'created';
        }

        wp_safe_redirect( admin_url( 'admin.php?page=ynj-services&msg=' . $msg ) );
        exit;
    }

    /* ==============================================================
     *  PAGE: SERVICES LIST
     * ============================================================ */

    public static function page_services() {
        $table = new YNJ_Services_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Masjid Services</h1>';
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ynj-service-edit' ) ) . '" class="page-title-action">Add New</a>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-services">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: ENQUIRIES LIST
     * ============================================================ */

    public static function page_enquiries() {
        $table = new YNJ_Service_Enquiries_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Service Enquiries</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-service-enquiries">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: EDIT SERVICE FORM
     * ============================================================ */

    public static function page_service_edit() {
        global $wpdb;

        $id      = absint( $_GET['id'] ?? 0 );
        $service = null;

        if ( $id ) {
            $service = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . YNJ_DB::table( 'masjid_services' ) . " WHERE id = %d", $id
            ) );
            if ( ! $service ) {
                wp_die( 'Service not found.' );
            }
        }

        $mosques = $wpdb->get_results(
            "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
        );

        $categories = YNJ_Services::CATEGORIES;

        $v = function( $field, $default = '' ) use ( $service ) {
            return $service ? esc_attr( $service->$field ?? $default ) : esc_attr( $default );
        };

        echo '<div class="wrap">';
        echo '<h1>' . ( $id ? 'Edit Service' : 'Add New Service' ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        wp_nonce_field( 'ynj_service_save', 'ynj_service_nonce' );
        echo '<input type="hidden" name="service_id" value="' . $id . '">';

        echo '<table class="form-table">';

        // Mosque
        echo '<tr><th><label for="mosque_id">Mosque</label></th><td>';
        echo '<select name="mosque_id" id="mosque_id" class="regular-text" required>';
        echo '<option value="">-- Select --</option>';
        foreach ( $mosques as $m ) {
            $sel = ( $service && (int) $service->mosque_id === (int) $m->id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $m->id ) . '"' . $sel . '>' . esc_html( $m->name ) . '</option>';
        }
        echo '</select></td></tr>';

        // Title
        echo '<tr><th><label for="title">Title</label></th><td>';
        echo '<input type="text" name="title" id="title" class="regular-text" required value="' . $v( 'title' ) . '"></td></tr>';

        // Category
        echo '<tr><th><label for="category">Category</label></th><td>';
        echo '<select name="category" id="category" class="regular-text">';
        foreach ( $categories as $key => $cat ) {
            $sel = ( $service && $service->category === $key ) ? ' selected' : ( ! $service && $key === 'general' ? ' selected' : '' );
            echo '<option value="' . esc_attr( $key ) . '"' . $sel . '>' . esc_html( $cat['label'] ) . '</option>';
        }
        echo '</select></td></tr>';

        // Description
        echo '<tr><th><label for="description">Description</label></th><td>';
        echo '<textarea name="description" id="description" rows="6" class="large-text">' . ( $service ? esc_textarea( $service->description ) : '' ) . '</textarea></td></tr>';

        // Price (pence)
        echo '<tr><th><label for="price_pence">Price (pence)</label></th><td>';
        echo '<input type="number" name="price_pence" id="price_pence" min="0" value="' . $v( 'price_pence', '0' ) . '">';
        echo '<p class="description">0 = free / price on request</p></td></tr>';

        // Price Label
        echo '<tr><th><label for="price_label">Price Label</label></th><td>';
        echo '<input type="text" name="price_label" id="price_label" class="regular-text" value="' . $v( 'price_label' ) . '">';
        echo '<p class="description">e.g. "From &pound;50" or "Free"</p></td></tr>';

        // Contact Phone
        echo '<tr><th><label for="contact_phone">Contact Phone</label></th><td>';
        echo '<input type="text" name="contact_phone" id="contact_phone" class="regular-text" value="' . $v( 'contact_phone' ) . '"></td></tr>';

        // Contact Email
        echo '<tr><th><label for="contact_email">Contact Email</label></th><td>';
        echo '<input type="email" name="contact_email" id="contact_email" class="regular-text" value="' . $v( 'contact_email' ) . '"></td></tr>';

        // Availability
        echo '<tr><th><label for="availability">Availability</label></th><td>';
        echo '<input type="text" name="availability" id="availability" class="regular-text" value="' . $v( 'availability' ) . '">';
        echo '<p class="description">e.g. "Mon-Fri 9am-5pm" or "By appointment"</p></td></tr>';

        // Requires Approval
        echo '<tr><th>Requires Approval</th><td>';
        echo '<label><input type="checkbox" name="requires_approval" value="1"' . ( $service && ! empty( $service->requires_approval ) ? ' checked' : '' ) . '> Bookings require admin approval</label>';
        echo '</td></tr>';

        // Status
        echo '<tr><th><label for="status">Status</label></th><td>';
        echo '<select name="status" id="status">';
        $statuses = [ 'active' => 'Active', 'inactive' => 'Inactive' ];
        foreach ( $statuses as $val => $label ) {
            $sel = ( $service && $service->status === $val ) ? ' selected' : ( ! $service && $val === 'active' ? ' selected' : '' );
            echo '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
        }
        echo '</select></td></tr>';

        echo '</table>';

        submit_button( $id ? 'Update Service' : 'Create Service', 'primary', 'ynj_service_save' );

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
 *  WP_List_Table: SERVICES
 * ===================================================================== */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Services_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'service',
            'plural'   => 'services',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'              => 'ID',
            'title'           => 'Title',
            'mosque'          => 'Mosque',
            'category'        => 'Category',
            'price'           => 'Price',
            'status'          => 'Status',
            'enquiries_count' => 'Enquiries',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'       => [ 'id', true ],
            'title'    => [ 'title', false ],
            'category' => [ 'category', false ],
            'status'   => [ 'status', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'masjid_services' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );

        $total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $active   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'active'" );
        $inactive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'inactive'" );

        $base = admin_url( 'admin.php?page=ynj-services' );

        $views = [];
        $views['all']      = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['active']   = '<a href="' . esc_url( $base . '&status=active' ) . '"' . ( $current === 'active' ? ' class="current"' : '' ) . '>Active <span class="count">(' . $active . ')</span></a>';
        $views['inactive'] = '<a href="' . esc_url( $base . '&status=inactive' ) . '"' . ( $current === 'inactive' ? ' class="current"' : '' ) . '>Inactive <span class="count">(' . $inactive . ')</span></a>';

        return $views;
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table('mosques') . " WHERE status IN ('active','unclaimed') ORDER BY name" );
        $sel = absint( $_GET['mosque_id'] ?? 0 );
        echo '<div class="alignleft actions">';
        echo '<select name="mosque_id"><option value="">All Mosques</option>';
        foreach ( $mosques as $m ) {
            printf( '<option value="%d"%s>%s</option>', $m->id, $sel === (int) $m->id ? ' selected' : '', esc_html( $m->name ) );
        }
        echo '</select>';
        submit_button( 'Filter', '', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;
        $table    = YNJ_DB::table( 'masjid_services' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( in_array( $status, [ 'active', 'inactive' ], true ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status );
        }

        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        if ( $mosque_id ) {
            $where .= $wpdb->prepare( ' AND mosque_id = %d', $mosque_id );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'title', 'category', 'status' ];
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
            case 'id':       return $item->id;
            case 'mosque':   return esc_html( YNJ_Services_Admin::get_mosque_name( $item->mosque_id ) );
            case 'category':
                $cats = YNJ_Services::CATEGORIES;
                $key  = $item->category ?? 'general';
                return esc_html( $cats[ $key ]['label'] ?? ucfirst( $key ) );
            case 'price':
                if ( $item->price_label ) return esc_html( $item->price_label );
                if ( $item->price_pence > 0 ) return '&pound;' . number_format( $item->price_pence / 100, 2 );
                return 'Free';
            case 'status':
                return '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'enquiries_count':
                global $wpdb;
                $et = YNJ_DB::table( 'masjid_service_enquiries' );
                return (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM $et WHERE service_id = %d", $item->id
                ) );
            default: return '';
        }
    }

    public function column_title( $item ) {
        $edit_url = admin_url( 'admin.php?page=ynj-service-edit&id=' . $item->id );

        $actions = [
            'edit' => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
        ];

        if ( $item->status !== 'active' ) {
            $actions['activate'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-services&ynj_action=service_activate&id=' . $item->id ),
                'ynj_action_service_activate_' . $item->id
            ) ) . '">Activate</a>';
        }

        if ( $item->status !== 'inactive' ) {
            $actions['deactivate'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-services&ynj_action=service_deactivate&id=' . $item->id ),
                'ynj_action_service_deactivate_' . $item->id
            ) ) . '">Deactivate</a>';
        }

        return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->title ) . '</a></strong>'
             . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No services found.';
    }
}


/* =======================================================================
 *  WP_List_Table: SERVICE ENQUIRIES
 * ===================================================================== */

class YNJ_Service_Enquiries_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'enquiry',
            'plural'   => 'enquiries',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'         => 'ID',
            'user_name'  => 'Name',
            'user_email' => 'Email',
            'user_phone' => 'Phone',
            'service'    => 'Service',
            'mosque'     => 'Mosque',
            'status'     => 'Status',
            'created_at' => 'Date',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'user_name'  => [ 'user_name', false ],
            'status'     => [ 'status', false ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'masjid_service_enquiries' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );

        $total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );
        $read    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'read'" );
        $replied = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'replied'" );

        $base = admin_url( 'admin.php?page=ynj-service-enquiries' );

        $views = [];
        $views['all']     = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['pending'] = '<a href="' . esc_url( $base . '&status=pending' ) . '"' . ( $current === 'pending' ? ' class="current"' : '' ) . '>Pending <span class="count">(' . $pending . ')</span></a>';
        $views['read']    = '<a href="' . esc_url( $base . '&status=read' ) . '"' . ( $current === 'read' ? ' class="current"' : '' ) . '>Read <span class="count">(' . $read . ')</span></a>';
        $views['replied'] = '<a href="' . esc_url( $base . '&status=replied' ) . '"' . ( $current === 'replied' ? ' class="current"' : '' ) . '>Replied <span class="count">(' . $replied . ')</span></a>';

        return $views;
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table('mosques') . " WHERE status IN ('active','unclaimed') ORDER BY name" );
        $sel = absint( $_GET['mosque_id'] ?? 0 );
        echo '<div class="alignleft actions">';
        echo '<select name="mosque_id"><option value="">All Mosques</option>';
        foreach ( $mosques as $m ) {
            printf( '<option value="%d"%s>%s</option>', $m->id, $sel === (int) $m->id ? ' selected' : '', esc_html( $m->name ) );
        }
        echo '</select>';
        submit_button( 'Filter', '', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;
        $et       = YNJ_DB::table( 'masjid_service_enquiries' );
        $st       = YNJ_DB::table( 'masjid_services' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( in_array( $status, [ 'pending', 'read', 'replied' ], true ) ) {
            $where .= $wpdb->prepare( ' AND e.status = %s', $status );
        }

        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        if ( $mosque_id ) {
            $where .= $wpdb->prepare( ' AND e.mosque_id = %d', $mosque_id );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'user_name', 'status', 'created_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) $orderby = 'id';
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $this->items = $wpdb->get_results(
            "SELECT e.*, s.title AS service_title, s.category AS service_category
             FROM $et e
             LEFT JOIN $st s ON s.id = e.service_id
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
            case 'user_name':  return esc_html( $item->user_name );
            case 'user_email': return esc_html( $item->user_email );
            case 'user_phone': return esc_html( $item->user_phone ?: '--' );
            case 'service':    return esc_html( $item->service_title ?? '(#' . $item->service_id . ')' );
            case 'mosque':     return esc_html( YNJ_Services_Admin::get_mosque_name( $item->mosque_id ) );
            case 'created_at': return $item->created_at ? date( 'Y-m-d H:i', strtotime( $item->created_at ) ) : '--';
            default:           return '';
        }
    }

    public function column_status( $item ) {
        $label = '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';

        $actions = [];

        if ( $item->status === 'pending' ) {
            $actions['mark_read'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-service-enquiries&ynj_action=enquiry_read&id=' . $item->id ),
                'ynj_action_enquiry_read_' . $item->id
            ) ) . '">Mark Read</a>';
        }

        if ( $item->status !== 'replied' ) {
            $actions['reply'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-service-enquiries&ynj_action=enquiry_replied&id=' . $item->id ),
                'ynj_action_enquiry_replied_' . $item->id
            ) ) . '">Reply</a>';
        }

        return $label . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No enquiries found.';
    }
}
