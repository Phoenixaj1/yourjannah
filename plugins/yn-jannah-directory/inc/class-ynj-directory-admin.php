<?php
/**
 * Directory WP Admin Pages — Businesses, Services, Enquiries.
 *
 * Registers top-level "Directory" menu with three sub-pages,
 * each using WP_List_Table for listing + inline edit forms.
 *
 * @package YNJ_Directory
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Directory_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
    }

    /**
     * Register admin menu pages.
     */
    public static function register_menus() {
        add_menu_page(
            'Directory',
            'Directory',
            'manage_options',
            'ynj-directory',
            [ __CLASS__, 'page_businesses' ],
            'dashicons-store',
            58
        );

        add_submenu_page(
            'ynj-directory',
            'Businesses',
            'Businesses',
            'manage_options',
            'ynj-directory',
            [ __CLASS__, 'page_businesses' ]
        );

        add_submenu_page(
            'ynj-directory',
            'Services',
            'Services',
            'manage_options',
            'ynj-directory-services',
            [ __CLASS__, 'page_services' ]
        );

        add_submenu_page(
            'ynj-directory',
            'Enquiries',
            'Enquiries',
            'manage_options',
            'ynj-directory-enquiries',
            [ __CLASS__, 'page_enquiries' ]
        );
    }

    // ================================================================
    // ACTION HANDLER — processes POST/GET actions before output
    // ================================================================

    public static function handle_actions() {

        // --- Business actions ---
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ynj-directory' ) {
            self::handle_business_actions();
        }

        // --- Service actions ---
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ynj-directory-services' ) {
            self::handle_service_actions();
        }

        // --- Enquiry actions ---
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ynj-directory-enquiries' ) {
            self::handle_enquiry_actions();
        }
    }

    private static function handle_business_actions() {
        global $wpdb;
        $table = YNJ_DB::table( 'businesses' );

        // Single row actions (approve / reject / delete)
        if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
            $action = sanitize_text_field( $_GET['action'] );
            $id     = absint( $_GET['id'] );
            $nonce  = sanitize_text_field( $_GET['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'ynj_biz_action_' . $id ) ) {
                wp_die( 'Security check failed.' );
            }

            switch ( $action ) {
                case 'approve':
                    $wpdb->update( $table, [ 'status' => 'active' ], [ 'id' => $id ] );
                    self::redirect_with_notice( 'ynj-directory', 'Business approved.' );
                    break;
                case 'reject':
                    $wpdb->update( $table, [ 'status' => 'rejected' ], [ 'id' => $id ] );
                    self::redirect_with_notice( 'ynj-directory', 'Business rejected.' );
                    break;
                case 'delete':
                    $wpdb->delete( $table, [ 'id' => $id ] );
                    self::redirect_with_notice( 'ynj-directory', 'Business deleted.' );
                    break;
            }
        }

        // Bulk actions
        if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bulk-businesses' ) ) {
            $bulk = sanitize_text_field( $_POST['action'] ?? '' );
            if ( $bulk === '-1' ) {
                $bulk = sanitize_text_field( $_POST['action2'] ?? '' );
            }
            $ids = array_map( 'absint', $_POST['business_ids'] ?? [] );

            if ( ! empty( $ids ) && in_array( $bulk, [ 'bulk-approve', 'bulk-reject' ], true ) ) {
                $status = $bulk === 'bulk-approve' ? 'active' : 'rejected';
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE $table SET status = %s WHERE id IN ($placeholders)",
                    array_merge( [ $status ], $ids )
                ) );
                $count = count( $ids );
                self::redirect_with_notice( 'ynj-directory', "$count business(es) updated to $status." );
            }
        }

        // Save edited business
        if ( isset( $_POST['ynj_save_business'] ) && isset( $_POST['_wpnonce'] ) ) {
            $id = absint( $_POST['business_id'] );
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ynj_edit_business_' . $id ) ) {
                wp_die( 'Security check failed.' );
            }

            $wpdb->update( $table, [
                'business_name'     => sanitize_text_field( $_POST['business_name'] ),
                'owner_name'        => sanitize_text_field( $_POST['owner_name'] ),
                'category'          => sanitize_text_field( $_POST['category'] ),
                'description'       => sanitize_textarea_field( $_POST['description'] ),
                'phone'             => sanitize_text_field( $_POST['phone'] ),
                'email'             => sanitize_email( $_POST['email'] ),
                'website'           => esc_url_raw( $_POST['website'] ),
                'address'           => sanitize_text_field( $_POST['address'] ),
                'postcode'          => sanitize_text_field( $_POST['postcode'] ),
                'monthly_fee_pence' => absint( $_POST['monthly_fee_pence'] ),
                'featured_position' => absint( $_POST['featured_position'] ),
                'verified'          => isset( $_POST['verified'] ) ? 1 : 0,
                'status'            => sanitize_text_field( $_POST['status'] ),
                'show_phone'        => isset( $_POST['show_phone'] ) ? 1 : 0,
                'show_whatsapp'     => isset( $_POST['show_whatsapp'] ) ? 1 : 0,
                'show_email'        => isset( $_POST['show_email'] ) ? 1 : 0,
                'show_website'      => isset( $_POST['show_website'] ) ? 1 : 0,
            ], [ 'id' => $id ] );

            self::redirect_with_notice( 'ynj-directory', 'Business updated.' );
        }
    }

    private static function handle_service_actions() {
        global $wpdb;
        $table = YNJ_DB::table( 'services' );

        // Single row actions
        if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
            $action = sanitize_text_field( $_GET['action'] );
            $id     = absint( $_GET['id'] );
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ynj_svc_action_' . $id ) ) {
                wp_die( 'Security check failed.' );
            }

            switch ( $action ) {
                case 'approve':
                    $wpdb->update( $table, [ 'status' => 'active' ], [ 'id' => $id ] );
                    self::redirect_with_notice( 'ynj-directory-services', 'Service approved.' );
                    break;
                case 'reject':
                    $wpdb->update( $table, [ 'status' => 'rejected' ], [ 'id' => $id ] );
                    self::redirect_with_notice( 'ynj-directory-services', 'Service rejected.' );
                    break;
            }
        }

        // Save edited service
        if ( isset( $_POST['ynj_save_service'] ) && isset( $_POST['_wpnonce'] ) ) {
            $id = absint( $_POST['service_id'] );
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ynj_edit_service_' . $id ) ) {
                wp_die( 'Security check failed.' );
            }

            $wpdb->update( $table, [
                'provider_name'     => sanitize_text_field( $_POST['provider_name'] ),
                'phone'             => sanitize_text_field( $_POST['phone'] ),
                'email'             => sanitize_email( $_POST['email'] ),
                'service_type'      => sanitize_text_field( $_POST['service_type'] ),
                'description'       => sanitize_textarea_field( $_POST['description'] ),
                'hourly_rate_pence' => absint( $_POST['hourly_rate_pence'] ),
                'area_covered'      => sanitize_text_field( $_POST['area_covered'] ),
                'status'            => sanitize_text_field( $_POST['status'] ),
                'show_phone'        => isset( $_POST['show_phone'] ) ? 1 : 0,
                'show_whatsapp'     => isset( $_POST['show_whatsapp'] ) ? 1 : 0,
                'show_email'        => isset( $_POST['show_email'] ) ? 1 : 0,
            ], [ 'id' => $id ] );

            self::redirect_with_notice( 'ynj-directory-services', 'Service updated.' );
        }
    }

    private static function handle_enquiry_actions() {
        global $wpdb;
        $table = YNJ_DB::table( 'enquiries' );

        if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
            $action = sanitize_text_field( $_GET['action'] );
            $id     = absint( $_GET['id'] );
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ynj_enq_action_' . $id ) ) {
                wp_die( 'Security check failed.' );
            }

            switch ( $action ) {
                case 'mark_read':
                    $wpdb->update( $table, [ 'status' => 'read' ], [ 'id' => $id ] );
                    self::redirect_with_notice( 'ynj-directory-enquiries', 'Enquiry marked as read.' );
                    break;
                case 'archive':
                    $wpdb->update( $table, [ 'status' => 'archived' ], [ 'id' => $id ] );
                    self::redirect_with_notice( 'ynj-directory-enquiries', 'Enquiry archived.' );
                    break;
                case 'reply':
                    // Reply shows a form — handled in page render, not here
                    break;
            }
        }

        // Save reply
        if ( isset( $_POST['ynj_save_reply'] ) && isset( $_POST['_wpnonce'] ) ) {
            $id = absint( $_POST['enquiry_id'] );
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ynj_reply_enquiry_' . $id ) ) {
                wp_die( 'Security check failed.' );
            }

            $admin_notes = sanitize_textarea_field( $_POST['admin_notes'] );
            YNJ_Directory::update_enquiry( $id, 'replied', $admin_notes );
            self::redirect_with_notice( 'ynj-directory-enquiries', 'Reply saved.' );
        }
    }

    /**
     * Redirect back to list page with a transient notice.
     */
    private static function redirect_with_notice( $page, $message ) {
        set_transient( 'ynj_dir_notice', $message, 30 );
        wp_safe_redirect( admin_url( "admin.php?page=$page" ) );
        exit;
    }

    /**
     * Render transient admin notice if present.
     */
    private static function render_notice() {
        $msg = get_transient( 'ynj_dir_notice' );
        if ( $msg ) {
            delete_transient( 'ynj_dir_notice' );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
        }
    }

    // ================================================================
    // PAGE RENDERERS
    // ================================================================

    public static function page_businesses() {
        // Edit form
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            self::render_edit_business( absint( $_GET['id'] ) );
            return;
        }

        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $list = new YNJ_Business_List_Table();
        $list->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Businesses</h1>';
        self::render_notice();

        // Status filter
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-directory" />';
        $current_status = sanitize_text_field( $_GET['status'] ?? '' );
        echo '<div class="tablenav top"><div class="alignleft actions">';
        echo '<select name="status">';
        echo '<option value="">All Statuses</option>';
        foreach ( [ 'pending', 'active', 'rejected', 'expired' ] as $s ) {
            $sel = selected( $current_status, $s, false );
            echo "<option value=\"$s\" $sel>" . ucfirst( $s ) . "</option>";
        }
        echo '</select>';
        submit_button( 'Filter', 'secondary', 'filter_action', false );
        echo '</div></div>';
        echo '</form>';

        echo '<form method="post">';
        $list->display();
        echo '</form>';
        echo '</div>';
    }

    public static function page_services() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            self::render_edit_service( absint( $_GET['id'] ) );
            return;
        }

        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $list = new YNJ_Service_List_Table();
        $list->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Services</h1>';
        self::render_notice();

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-directory-services" />';
        $current_status = sanitize_text_field( $_GET['status'] ?? '' );
        echo '<div class="tablenav top"><div class="alignleft actions">';
        echo '<select name="status">';
        echo '<option value="">All Statuses</option>';
        foreach ( [ 'pending', 'active', 'rejected' ] as $s ) {
            $sel = selected( $current_status, $s, false );
            echo "<option value=\"$s\" $sel>" . ucfirst( $s ) . "</option>";
        }
        echo '</select>';
        submit_button( 'Filter', 'secondary', 'filter_action', false );
        echo '</div></div>';
        echo '</form>';

        echo '<form method="post">';
        $list->display();
        echo '</form>';
        echo '</div>';
    }

    public static function page_enquiries() {
        // Reply form
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'reply' && isset( $_GET['id'] ) ) {
            self::render_reply_enquiry( absint( $_GET['id'] ) );
            return;
        }

        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $list = new YNJ_Enquiry_List_Table();
        $list->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Enquiries</h1>';
        self::render_notice();

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-directory-enquiries" />';
        $current_status = sanitize_text_field( $_GET['status'] ?? '' );
        echo '<div class="tablenav top"><div class="alignleft actions">';
        echo '<select name="status">';
        echo '<option value="">All Statuses</option>';
        foreach ( [ 'new', 'read', 'replied', 'archived' ] as $s ) {
            $sel = selected( $current_status, $s, false );
            echo "<option value=\"$s\" $sel>" . ucfirst( $s ) . "</option>";
        }
        echo '</select>';
        submit_button( 'Filter', 'secondary', 'filter_action', false );
        echo '</div></div>';
        echo '</form>';

        echo '<form method="post">';
        $list->display();
        echo '</form>';
        echo '</div>';
    }

    // ================================================================
    // EDIT FORMS
    // ================================================================

    private static function render_edit_business( $id ) {
        global $wpdb;
        $biz = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'businesses' ) . " WHERE id = %d", $id
        ) );
        if ( ! $biz ) {
            wp_die( 'Business not found.' );
        }

        $mosque = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $biz->mosque_id
        ) );

        echo '<div class="wrap">';
        echo '<h1>Edit Business #' . (int) $biz->id . '</h1>';
        self::render_notice();
        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=ynj-directory' ) ) . '">';
        wp_nonce_field( 'ynj_edit_business_' . $biz->id );
        echo '<input type="hidden" name="business_id" value="' . (int) $biz->id . '" />';

        echo '<table class="form-table">';

        echo '<tr><th>Mosque</th><td>' . esc_html( $mosque ?: 'ID: ' . $biz->mosque_id ) . '</td></tr>';

        $text_fields = [
            'business_name' => 'Business Name',
            'owner_name'    => 'Owner Name',
            'category'      => 'Category',
            'phone'         => 'Phone',
            'email'         => 'Email',
            'website'       => 'Website',
            'address'       => 'Address',
            'postcode'      => 'Postcode',
        ];
        foreach ( $text_fields as $field => $label ) {
            $val = esc_attr( $biz->$field ?? '' );
            echo "<tr><th><label for=\"$field\">$label</label></th>";
            echo "<td><input type=\"text\" id=\"$field\" name=\"$field\" value=\"$val\" class=\"regular-text\" /></td></tr>";
        }

        echo '<tr><th><label for="description">Description</label></th>';
        echo '<td><textarea id="description" name="description" rows="5" class="large-text">' . esc_textarea( $biz->description ) . '</textarea></td></tr>';

        echo '<tr><th><label for="monthly_fee_pence">Monthly Fee (pence)</label></th>';
        echo '<td><input type="number" id="monthly_fee_pence" name="monthly_fee_pence" value="' . (int) $biz->monthly_fee_pence . '" min="0" /></td></tr>';

        echo '<tr><th><label for="featured_position">Featured Position</label></th>';
        echo '<td><input type="number" id="featured_position" name="featured_position" value="' . (int) $biz->featured_position . '" min="0" /></td></tr>';

        echo '<tr><th><label for="status">Status</label></th><td><select id="status" name="status">';
        foreach ( [ 'pending', 'active', 'rejected', 'expired' ] as $s ) {
            $sel = selected( $biz->status, $s, false );
            echo "<option value=\"$s\" $sel>" . ucfirst( $s ) . "</option>";
        }
        echo '</select></td></tr>';

        $checkboxes = [
            'verified'      => 'Verified',
            'show_phone'    => 'Show Phone',
            'show_whatsapp' => 'Show WhatsApp',
            'show_email'    => 'Show Email',
            'show_website'  => 'Show Website',
        ];
        foreach ( $checkboxes as $field => $label ) {
            $checked = checked( (int) ( $biz->$field ?? 0 ), 1, false );
            echo "<tr><th>$label</th><td><label><input type=\"checkbox\" name=\"$field\" value=\"1\" $checked /> Yes</label></td></tr>";
        }

        echo '</table>';
        submit_button( 'Save Business', 'primary', 'ynj_save_business' );
        echo '</form></div>';
    }

    private static function render_edit_service( $id ) {
        global $wpdb;
        $svc = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'services' ) . " WHERE id = %d", $id
        ) );
        if ( ! $svc ) {
            wp_die( 'Service not found.' );
        }

        $mosque = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $svc->mosque_id
        ) );

        echo '<div class="wrap">';
        echo '<h1>Edit Service #' . (int) $svc->id . '</h1>';
        self::render_notice();
        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=ynj-directory-services' ) ) . '">';
        wp_nonce_field( 'ynj_edit_service_' . $svc->id );
        echo '<input type="hidden" name="service_id" value="' . (int) $svc->id . '" />';

        echo '<table class="form-table">';
        echo '<tr><th>Mosque</th><td>' . esc_html( $mosque ?: 'ID: ' . $svc->mosque_id ) . '</td></tr>';

        $text_fields = [
            'provider_name' => 'Provider Name',
            'phone'         => 'Phone',
            'email'         => 'Email',
            'service_type'  => 'Service Type',
            'area_covered'  => 'Area Covered',
        ];
        foreach ( $text_fields as $field => $label ) {
            $val = esc_attr( $svc->$field ?? '' );
            echo "<tr><th><label for=\"$field\">$label</label></th>";
            echo "<td><input type=\"text\" id=\"$field\" name=\"$field\" value=\"$val\" class=\"regular-text\" /></td></tr>";
        }

        echo '<tr><th><label for="description">Description</label></th>';
        echo '<td><textarea id="description" name="description" rows="5" class="large-text">' . esc_textarea( $svc->description ) . '</textarea></td></tr>';

        echo '<tr><th><label for="hourly_rate_pence">Hourly Rate (pence)</label></th>';
        echo '<td><input type="number" id="hourly_rate_pence" name="hourly_rate_pence" value="' . (int) $svc->hourly_rate_pence . '" min="0" /></td></tr>';

        echo '<tr><th><label for="status">Status</label></th><td><select id="status" name="status">';
        foreach ( [ 'pending', 'active', 'rejected' ] as $s ) {
            $sel = selected( $svc->status, $s, false );
            echo "<option value=\"$s\" $sel>" . ucfirst( $s ) . "</option>";
        }
        echo '</select></td></tr>';

        $checkboxes = [
            'show_phone'    => 'Show Phone',
            'show_whatsapp' => 'Show WhatsApp',
            'show_email'    => 'Show Email',
        ];
        foreach ( $checkboxes as $field => $label ) {
            $checked = checked( (int) ( $svc->$field ?? 0 ), 1, false );
            echo "<tr><th>$label</th><td><label><input type=\"checkbox\" name=\"$field\" value=\"1\" $checked /> Yes</label></td></tr>";
        }

        echo '</table>';
        submit_button( 'Save Service', 'primary', 'ynj_save_service' );
        echo '</form></div>';
    }

    private static function render_reply_enquiry( $id ) {
        global $wpdb;
        $enq = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'enquiries' ) . " WHERE id = %d", $id
        ) );
        if ( ! $enq ) {
            wp_die( 'Enquiry not found.' );
        }

        $mosque = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $enq->mosque_id
        ) );

        echo '<div class="wrap">';
        echo '<h1>Reply to Enquiry #' . (int) $enq->id . '</h1>';
        self::render_notice();

        echo '<table class="form-table">';
        echo '<tr><th>Mosque</th><td>' . esc_html( $mosque ?: 'ID: ' . $enq->mosque_id ) . '</td></tr>';
        echo '<tr><th>Name</th><td>' . esc_html( $enq->name ) . '</td></tr>';
        echo '<tr><th>Email</th><td>' . esc_html( $enq->email ) . '</td></tr>';
        echo '<tr><th>Phone</th><td>' . esc_html( $enq->phone ) . '</td></tr>';
        echo '<tr><th>Subject</th><td>' . esc_html( $enq->subject ) . '</td></tr>';
        echo '<tr><th>Type</th><td>' . esc_html( $enq->type ) . '</td></tr>';
        echo '<tr><th>Status</th><td>' . esc_html( $enq->status ) . '</td></tr>';
        echo '<tr><th>Message</th><td>' . nl2br( esc_html( $enq->message ) ) . '</td></tr>';
        echo '<tr><th>Created</th><td>' . esc_html( $enq->created_at ) . '</td></tr>';
        if ( $enq->replied_at ) {
            echo '<tr><th>Replied At</th><td>' . esc_html( $enq->replied_at ) . '</td></tr>';
        }
        echo '</table>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=ynj-directory-enquiries' ) ) . '">';
        wp_nonce_field( 'ynj_reply_enquiry_' . $enq->id );
        echo '<input type="hidden" name="enquiry_id" value="' . (int) $enq->id . '" />';
        echo '<h2>Admin Notes / Reply</h2>';
        echo '<textarea name="admin_notes" rows="6" class="large-text">' . esc_textarea( $enq->admin_notes ?? '' ) . '</textarea>';
        echo '<p class="description">Save internal notes about this enquiry. Status will be set to "replied".</p>';
        submit_button( 'Save Reply', 'primary', 'ynj_save_reply' );
        echo '</form></div>';
    }
}

// ================================================================
// WP_LIST_TABLE SUBCLASSES
// ================================================================

/**
 * Businesses List Table
 */
class YNJ_Business_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'business',
            'plural'   => 'businesses',
            'ajax'     => false,
        ] );
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

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'id'            => 'ID',
            'business_name' => 'Business Name',
            'mosque'        => 'Mosque',
            'category'      => 'Category',
            'status'        => 'Status',
            'verified'      => 'Verified',
            'fee'           => 'Fee',
            'created_at'    => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'            => [ 'b.id', false ],
            'business_name' => [ 'b.business_name', false ],
            'status'        => [ 'b.status', false ],
            'fee'           => [ 'b.monthly_fee_pence', true ],
            'created_at'    => [ 'b.created_at', true ],
        ];
    }

    protected function get_bulk_actions() {
        return [
            'bulk-approve' => 'Approve',
            'bulk-reject'  => 'Reject',
        ];
    }

    protected function column_cb( $item ) {
        return '<input type="checkbox" name="business_ids[]" value="' . (int) $item->id . '" />';
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return (int) $item->id;
            case 'business_name':
                return esc_html( $item->business_name );
            case 'mosque':
                return esc_html( $item->mosque_name ?: 'ID: ' . $item->mosque_id );
            case 'category':
                return esc_html( $item->category ?: '—' );
            case 'status':
                $colors = [ 'active' => 'green', 'pending' => '#b26900', 'rejected' => 'red', 'expired' => '#999' ];
                $color  = $colors[ $item->status ] ?? '#333';
                return '<span style="color:' . $color . ';font-weight:600;">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'verified':
                return (int) $item->verified ? '<span style="color:green;">Yes</span>' : 'No';
            case 'fee':
                return '&pound;' . number_format( (int) $item->monthly_fee_pence / 100, 2 );
            case 'created_at':
                return esc_html( date( 'j M Y', strtotime( $item->created_at ) ) );
            default:
                return '';
        }
    }

    protected function column_business_name( $item ) {
        $edit_url    = admin_url( 'admin.php?page=ynj-directory&action=edit&id=' . (int) $item->id );
        $approve_url = wp_nonce_url( admin_url( 'admin.php?page=ynj-directory&action=approve&id=' . (int) $item->id ), 'ynj_biz_action_' . $item->id );
        $reject_url  = wp_nonce_url( admin_url( 'admin.php?page=ynj-directory&action=reject&id=' . (int) $item->id ), 'ynj_biz_action_' . $item->id );
        $delete_url  = wp_nonce_url( admin_url( 'admin.php?page=ynj-directory&action=delete&id=' . (int) $item->id ), 'ynj_biz_action_' . $item->id );

        $actions = [
            'edit'    => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
            'approve' => '<a href="' . esc_url( $approve_url ) . '">Approve</a>',
            'reject'  => '<a href="' . esc_url( $reject_url ) . '" style="color:#a00;">Reject</a>',
            'delete'  => '<a href="' . esc_url( $delete_url ) . '" style="color:#a00;" onclick="return confirm(\'Delete this business?\');">Delete</a>',
        ];

        return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->business_name ) . '</a></strong>' . $this->row_actions( $actions );
    }

    public function prepare_items() {
        global $wpdb;
        $bt = YNJ_DB::table( 'businesses' );
        $mt = YNJ_DB::table( 'mosques' );

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( $status ) {
            $where .= $wpdb->prepare( ' AND b.status = %s', $status );
        }

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= $wpdb->prepare( ' AND b.mosque_id = %d', absint( $_GET['mosque_id'] ) );
        }

        // Sortable
        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'b.created_at' ) ?: 'b.created_at';
        $order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bt b WHERE $where" );

        $this->items = $wpdb->get_results(
            "SELECT b.*, m.name AS mosque_name
             FROM $bt b
             LEFT JOIN $mt m ON m.id = b.mosque_id
             WHERE $where
             ORDER BY $orderby $order
             LIMIT $per_page OFFSET $offset"
        );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }
}

/**
 * Services List Table
 */
class YNJ_Service_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'service',
            'plural'   => 'services',
            'ajax'     => false,
        ] );
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

    public function get_columns() {
        return [
            'id'            => 'ID',
            'provider_name' => 'Provider',
            'mosque'        => 'Mosque',
            'service_type'  => 'Type',
            'status'        => 'Status',
            'rate'          => 'Rate',
            'created_at'    => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'            => [ 's.id', false ],
            'provider_name' => [ 's.provider_name', false ],
            'status'        => [ 's.status', false ],
            'created_at'    => [ 's.created_at', true ],
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return (int) $item->id;
            case 'provider_name':
                return esc_html( $item->provider_name );
            case 'mosque':
                return esc_html( $item->mosque_name ?: 'ID: ' . $item->mosque_id );
            case 'service_type':
                return esc_html( $item->service_type ?: '—' );
            case 'status':
                $colors = [ 'active' => 'green', 'pending' => '#b26900', 'rejected' => 'red' ];
                $color  = $colors[ $item->status ] ?? '#333';
                return '<span style="color:' . $color . ';font-weight:600;">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'rate':
                $rate = (int) $item->hourly_rate_pence;
                return $rate ? '&pound;' . number_format( $rate / 100, 2 ) . '/hr' : '—';
            case 'created_at':
                return esc_html( date( 'j M Y', strtotime( $item->created_at ) ) );
            default:
                return '';
        }
    }

    protected function column_provider_name( $item ) {
        $edit_url    = admin_url( 'admin.php?page=ynj-directory-services&action=edit&id=' . (int) $item->id );
        $approve_url = wp_nonce_url( admin_url( 'admin.php?page=ynj-directory-services&action=approve&id=' . (int) $item->id ), 'ynj_svc_action_' . $item->id );
        $reject_url  = wp_nonce_url( admin_url( 'admin.php?page=ynj-directory-services&action=reject&id=' . (int) $item->id ), 'ynj_svc_action_' . $item->id );

        $actions = [
            'edit'    => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
            'approve' => '<a href="' . esc_url( $approve_url ) . '">Approve</a>',
            'reject'  => '<a href="' . esc_url( $reject_url ) . '" style="color:#a00;">Reject</a>',
        ];

        return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->provider_name ) . '</a></strong>' . $this->row_actions( $actions );
    }

    public function prepare_items() {
        global $wpdb;
        $st = YNJ_DB::table( 'services' );
        $mt = YNJ_DB::table( 'mosques' );

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( $status ) {
            $where .= $wpdb->prepare( ' AND s.status = %s', $status );
        }

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= $wpdb->prepare( ' AND s.mosque_id = %d', absint( $_GET['mosque_id'] ) );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 's.created_at' ) ?: 's.created_at';
        $order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $st s WHERE $where" );

        $this->items = $wpdb->get_results(
            "SELECT s.*, m.name AS mosque_name
             FROM $st s
             LEFT JOIN $mt m ON m.id = s.mosque_id
             WHERE $where
             ORDER BY $orderby $order
             LIMIT $per_page OFFSET $offset"
        );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }
}

/**
 * Enquiries List Table
 */
class YNJ_Enquiry_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'enquiry',
            'plural'   => 'enquiries',
            'ajax'     => false,
        ] );
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

    public function get_columns() {
        return [
            'id'         => 'ID',
            'name'       => 'Name',
            'email'      => 'Email',
            'mosque'     => 'Mosque',
            'subject'    => 'Subject',
            'type'       => 'Type',
            'status'     => 'Status',
            'created_at' => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'e.id', false ],
            'name'       => [ 'e.name', false ],
            'status'     => [ 'e.status', false ],
            'created_at' => [ 'e.created_at', true ],
        ];
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return (int) $item->id;
            case 'name':
                return esc_html( $item->name );
            case 'email':
                return esc_html( $item->email );
            case 'mosque':
                return esc_html( $item->mosque_name ?: 'ID: ' . $item->mosque_id );
            case 'subject':
                return esc_html( $item->subject ?: '—' );
            case 'type':
                return esc_html( ucfirst( $item->type ) );
            case 'status':
                $colors = [ 'new' => '#0073aa', 'read' => '#555', 'replied' => 'green', 'archived' => '#999' ];
                $color  = $colors[ $item->status ] ?? '#333';
                $bold   = $item->status === 'new' ? 'font-weight:700;' : '';
                return '<span style="color:' . $color . ';' . $bold . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'created_at':
                return esc_html( date( 'j M Y H:i', strtotime( $item->created_at ) ) );
            default:
                return '';
        }
    }

    protected function column_name( $item ) {
        $reply_url     = admin_url( 'admin.php?page=ynj-directory-enquiries&action=reply&id=' . (int) $item->id );
        $mark_read_url = wp_nonce_url( admin_url( 'admin.php?page=ynj-directory-enquiries&action=mark_read&id=' . (int) $item->id ), 'ynj_enq_action_' . $item->id );
        $archive_url   = wp_nonce_url( admin_url( 'admin.php?page=ynj-directory-enquiries&action=archive&id=' . (int) $item->id ), 'ynj_enq_action_' . $item->id );

        $actions = [
            'reply'     => '<a href="' . esc_url( $reply_url ) . '">Reply</a>',
            'mark_read' => '<a href="' . esc_url( $mark_read_url ) . '">Mark Read</a>',
            'archive'   => '<a href="' . esc_url( $archive_url ) . '">Archive</a>',
        ];

        $style = $item->status === 'new' ? ' style="font-weight:700;"' : '';
        return '<strong><a href="' . esc_url( $reply_url ) . '"' . $style . '>' . esc_html( $item->name ) . '</a></strong>' . $this->row_actions( $actions );
    }

    public function prepare_items() {
        global $wpdb;
        $et = YNJ_DB::table( 'enquiries' );
        $mt = YNJ_DB::table( 'mosques' );

        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( $status ) {
            $where .= $wpdb->prepare( ' AND e.status = %s', $status );
        }

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= $wpdb->prepare( ' AND e.mosque_id = %d', absint( $_GET['mosque_id'] ) );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'e.created_at' ) ?: 'e.created_at';
        $order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $et e WHERE $where" );

        $this->items = $wpdb->get_results(
            "SELECT e.*, m.name AS mosque_name
             FROM $et e
             LEFT JOIN $mt m ON m.id = e.mosque_id
             WHERE $where
             ORDER BY $orderby $order
             LIMIT $per_page OFFSET $offset"
        );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }
}
