<?php
/**
 * YourJannah Events — WP Admin pages.
 *
 * Registers top-level "Events" menu with sub-pages for Events, Announcements,
 * Bookings, and edit forms.  Uses WP_List_Table for list views.
 *
 * @package YNJ_Events
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Events_Admin {

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
            'Events',
            'Events',
            'manage_options',
            'ynj-events',
            [ __CLASS__, 'page_events' ],
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'ynj-events',
            'All Events',
            'All Events',
            'manage_options',
            'ynj-events',
            [ __CLASS__, 'page_events' ]
        );

        add_submenu_page(
            'ynj-events',
            'Announcements',
            'Announcements',
            'manage_options',
            'ynj-announcements',
            [ __CLASS__, 'page_announcements' ]
        );

        add_submenu_page(
            'ynj-events',
            'Bookings',
            'Bookings',
            'manage_options',
            'ynj-bookings',
            [ __CLASS__, 'page_bookings' ]
        );

        /* Hidden pages (no menu entry) — edit forms. */
        add_submenu_page(
            null,
            'Edit Event',
            'Edit Event',
            'manage_options',
            'ynj-event-edit',
            [ __CLASS__, 'page_event_edit' ]
        );

        add_submenu_page(
            null,
            'Edit Announcement',
            'Edit Announcement',
            'manage_options',
            'ynj-announcement-edit',
            [ __CLASS__, 'page_announcement_edit' ]
        );
    }

    /* ==============================================================
     *  ACTION HANDLER (saves, deletes, status changes)
     * ============================================================ */

    public static function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        /* ---- Event save ---- */
        if ( isset( $_POST['ynj_event_save'] ) ) {
            check_admin_referer( 'ynj_event_save', 'ynj_event_nonce' );
            self::save_event();
            return;
        }

        /* ---- Announcement save ---- */
        if ( isset( $_POST['ynj_announcement_save'] ) ) {
            check_admin_referer( 'ynj_announcement_save', 'ynj_announcement_nonce' );
            self::save_announcement();
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

            /* -- Events -- */
            case 'event_publish':
                $wpdb->update( YNJ_DB::table( 'events' ), [ 'status' => 'published' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-events&msg=updated' ) );
                exit;

            case 'event_draft':
                $wpdb->update( YNJ_DB::table( 'events' ), [ 'status' => 'draft' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-events&msg=updated' ) );
                exit;

            case 'event_delete':
                $wpdb->delete( YNJ_DB::table( 'events' ), [ 'id' => $id ] );
                $wpdb->delete( YNJ_DB::table( 'event_volunteers' ), [ 'event_id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-events&msg=deleted' ) );
                exit;

            /* -- Announcements -- */
            case 'ann_pin':
                $wpdb->update( YNJ_DB::table( 'announcements' ), [ 'pinned' => 1 ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-announcements&msg=updated' ) );
                exit;

            case 'ann_unpin':
                $wpdb->update( YNJ_DB::table( 'announcements' ), [ 'pinned' => 0 ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-announcements&msg=updated' ) );
                exit;

            case 'ann_delete':
                $wpdb->delete( YNJ_DB::table( 'announcements' ), [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-announcements&msg=deleted' ) );
                exit;

            /* -- Bookings -- */
            case 'booking_confirm':
                $wpdb->update( YNJ_DB::table( 'bookings' ), [ 'status' => 'confirmed' ], [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-bookings&msg=updated' ) );
                exit;

            case 'booking_cancel':
                $booking = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM " . YNJ_DB::table( 'bookings' ) . " WHERE id = %d", $id
                ) );
                $wpdb->update( YNJ_DB::table( 'bookings' ), [ 'status' => 'cancelled' ], [ 'id' => $id ] );
                if ( $booking && $booking->event_id && $booking->status !== 'cancelled' ) {
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE " . YNJ_DB::table( 'events' ) . " SET registered_count = GREATEST(0, registered_count - 1) WHERE id = %d",
                        $booking->event_id
                    ) );
                }
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-bookings&msg=updated' ) );
                exit;
        }
    }

    /* ==============================================================
     *  EVENT SAVE
     * ============================================================ */

    private static function save_event() {
        global $wpdb;
        $table = YNJ_DB::table( 'events' );
        $id    = absint( $_POST['event_id'] ?? 0 );

        $data = [
            'mosque_id'          => absint( $_POST['mosque_id'] ?? 0 ),
            'title'              => sanitize_text_field( $_POST['title'] ?? '' ),
            'description'        => wp_kses_post( $_POST['description'] ?? '' ),
            'event_date'         => sanitize_text_field( $_POST['event_date'] ?? '' ),
            'start_time'         => sanitize_text_field( $_POST['start_time'] ?? '' ),
            'end_time'           => sanitize_text_field( $_POST['end_time'] ?? '' ),
            'location'           => sanitize_text_field( $_POST['location'] ?? '' ),
            'image_url'          => esc_url_raw( $_POST['image_url'] ?? '' ),
            'event_type'         => sanitize_text_field( $_POST['event_type'] ?? '' ),
            'max_capacity'       => absint( $_POST['max_capacity'] ?? 0 ),
            'ticket_price_pence' => absint( $_POST['ticket_price_pence'] ?? 0 ),
            'requires_booking'   => absint( $_POST['requires_booking'] ?? 0 ),
            'is_online'          => absint( $_POST['is_online'] ?? 0 ),
            'needs_volunteers'   => absint( $_POST['needs_volunteers'] ?? 0 ),
            'volunteer_roles'    => sanitize_text_field( $_POST['volunteer_roles'] ?? '' ),
            'status'             => sanitize_text_field( $_POST['status'] ?? 'draft' ),
        ];

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
            $msg = 'updated';
        } else {
            $wpdb->insert( $table, $data );
            $id  = (int) $wpdb->insert_id;
            $msg = 'created';
        }

        wp_safe_redirect( admin_url( 'admin.php?page=ynj-events&msg=' . $msg ) );
        exit;
    }

    /* ==============================================================
     *  ANNOUNCEMENT SAVE
     * ============================================================ */

    private static function save_announcement() {
        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );
        $id    = absint( $_POST['announcement_id'] ?? 0 );

        $status = sanitize_text_field( $_POST['status'] ?? 'draft' );

        $data = [
            'mosque_id'  => absint( $_POST['mosque_id'] ?? 0 ),
            'title'      => sanitize_text_field( $_POST['title'] ?? '' ),
            'body'       => wp_kses_post( $_POST['body'] ?? '' ),
            'pinned'     => absint( $_POST['pinned'] ?? 0 ),
            'expires_at' => ! empty( $_POST['expires_at'] ) ? sanitize_text_field( $_POST['expires_at'] ) : null,
            'status'     => $status,
        ];

        // Set published_at when transitioning to published.
        if ( $status === 'published' ) {
            if ( $id ) {
                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT status FROM $table WHERE id = %d", $id
                ) );
                if ( $existing !== 'published' ) {
                    $data['published_at'] = current_time( 'mysql' );
                }
            } else {
                $data['published_at'] = current_time( 'mysql' );
            }
        }

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
            $msg = 'updated';
        } else {
            $wpdb->insert( $table, $data );
            $id  = (int) $wpdb->insert_id;
            $msg = 'created';
        }

        wp_safe_redirect( admin_url( 'admin.php?page=ynj-announcements&msg=' . $msg ) );
        exit;
    }

    /* ==============================================================
     *  PAGE: EVENTS LIST
     * ============================================================ */

    public static function page_events() {
        $table = new YNJ_Events_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Events</h1>';
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ynj-event-edit' ) ) . '" class="page-title-action">Add New</a>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-events">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: ANNOUNCEMENTS LIST
     * ============================================================ */

    public static function page_announcements() {
        $table = new YNJ_Announcements_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Announcements</h1>';
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ynj-announcement-edit' ) ) . '" class="page-title-action">Add New</a>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-announcements">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: BOOKINGS LIST
     * ============================================================ */

    public static function page_bookings() {
        $table = new YNJ_Bookings_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Bookings</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-bookings">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: EDIT EVENT FORM
     * ============================================================ */

    public static function page_event_edit() {
        global $wpdb;

        $id    = absint( $_GET['id'] ?? 0 );
        $event = null;

        if ( $id ) {
            $event = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . YNJ_DB::table( 'events' ) . " WHERE id = %d", $id
            ) );
            if ( ! $event ) {
                wp_die( 'Event not found.' );
            }
        }

        $mosques = $wpdb->get_results(
            "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
        );

        $v = function( $field, $default = '' ) use ( $event ) {
            return $event ? esc_attr( $event->$field ?? $default ) : esc_attr( $default );
        };

        echo '<div class="wrap">';
        echo '<h1>' . ( $id ? 'Edit Event' : 'Add New Event' ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        wp_nonce_field( 'ynj_event_save', 'ynj_event_nonce' );
        echo '<input type="hidden" name="event_id" value="' . $id . '">';

        echo '<table class="form-table">';

        // Mosque
        echo '<tr><th><label for="mosque_id">Mosque</label></th><td>';
        echo '<select name="mosque_id" id="mosque_id" class="regular-text" required>';
        echo '<option value="">-- Select --</option>';
        foreach ( $mosques as $m ) {
            $sel = ( $event && (int) $event->mosque_id === (int) $m->id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $m->id ) . '"' . $sel . '>' . esc_html( $m->name ) . '</option>';
        }
        echo '</select></td></tr>';

        // Title
        echo '<tr><th><label for="title">Title</label></th><td>';
        echo '<input type="text" name="title" id="title" class="regular-text" required value="' . $v( 'title' ) . '"></td></tr>';

        // Description
        echo '<tr><th><label for="description">Description</label></th><td>';
        echo '<textarea name="description" id="description" rows="6" class="large-text">' . ( $event ? esc_textarea( $event->description ) : '' ) . '</textarea></td></tr>';

        // Date
        echo '<tr><th><label for="event_date">Date</label></th><td>';
        echo '<input type="date" name="event_date" id="event_date" value="' . $v( 'event_date' ) . '"></td></tr>';

        // Start / End Time
        echo '<tr><th><label for="start_time">Start Time</label></th><td>';
        echo '<input type="time" name="start_time" id="start_time" value="' . $v( 'start_time' ) . '"></td></tr>';

        echo '<tr><th><label for="end_time">End Time</label></th><td>';
        echo '<input type="time" name="end_time" id="end_time" value="' . $v( 'end_time' ) . '"></td></tr>';

        // Location
        echo '<tr><th><label for="location">Location</label></th><td>';
        echo '<input type="text" name="location" id="location" class="regular-text" value="' . $v( 'location' ) . '"></td></tr>';

        // Image URL
        echo '<tr><th><label for="image_url">Image URL</label></th><td>';
        echo '<input type="url" name="image_url" id="image_url" class="regular-text" value="' . $v( 'image_url' ) . '"></td></tr>';

        // Event Type
        echo '<tr><th><label for="event_type">Event Type</label></th><td>';
        echo '<input type="text" name="event_type" id="event_type" class="regular-text" value="' . $v( 'event_type' ) . '">';
        echo '<p class="description">e.g. lecture, fundraiser, community, workshop</p></td></tr>';

        // Capacity
        echo '<tr><th><label for="max_capacity">Max Capacity</label></th><td>';
        echo '<input type="number" name="max_capacity" id="max_capacity" min="0" value="' . $v( 'max_capacity', '0' ) . '">';
        echo '<p class="description">0 = unlimited</p></td></tr>';

        // Ticket Price
        echo '<tr><th><label for="ticket_price_pence">Ticket Price (pence)</label></th><td>';
        echo '<input type="number" name="ticket_price_pence" id="ticket_price_pence" min="0" value="' . $v( 'ticket_price_pence', '0' ) . '">';
        echo '<p class="description">0 = free</p></td></tr>';

        // Checkboxes
        $chk = function( $field ) use ( $event ) {
            return ( $event && ! empty( $event->$field ) ) ? ' checked' : '';
        };

        echo '<tr><th>Options</th><td>';
        echo '<label><input type="checkbox" name="requires_booking" value="1"' . $chk( 'requires_booking' ) . '> Requires booking</label><br>';
        echo '<label><input type="checkbox" name="is_online" value="1"' . $chk( 'is_online' ) . '> Online event</label><br>';
        echo '<label><input type="checkbox" name="needs_volunteers" value="1"' . $chk( 'needs_volunteers' ) . '> Needs volunteers</label>';
        echo '</td></tr>';

        // Volunteer Roles
        echo '<tr><th><label for="volunteer_roles">Volunteer Roles</label></th><td>';
        echo '<input type="text" name="volunteer_roles" id="volunteer_roles" class="regular-text" value="' . $v( 'volunteer_roles' ) . '">';
        echo '<p class="description">Comma-separated, e.g. steward, food server, setup</p></td></tr>';

        // Status
        echo '<tr><th><label for="status">Status</label></th><td>';
        echo '<select name="status" id="status">';
        $statuses = [ 'draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled' ];
        foreach ( $statuses as $val => $label ) {
            $sel = ( $event && $event->status === $val ) ? ' selected' : ( ! $event && $val === 'draft' ? ' selected' : '' );
            echo '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
        }
        echo '</select></td></tr>';

        echo '</table>';

        submit_button( $id ? 'Update Event' : 'Create Event', 'primary', 'ynj_event_save' );

        echo '</form></div>';
    }

    /* ==============================================================
     *  PAGE: EDIT ANNOUNCEMENT FORM
     * ============================================================ */

    public static function page_announcement_edit() {
        global $wpdb;

        $id  = absint( $_GET['id'] ?? 0 );
        $ann = null;

        if ( $id ) {
            $ann = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . YNJ_DB::table( 'announcements' ) . " WHERE id = %d", $id
            ) );
            if ( ! $ann ) {
                wp_die( 'Announcement not found.' );
            }
        }

        $mosques = $wpdb->get_results(
            "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
        );

        $v = function( $field, $default = '' ) use ( $ann ) {
            return $ann ? esc_attr( $ann->$field ?? $default ) : esc_attr( $default );
        };

        echo '<div class="wrap">';
        echo '<h1>' . ( $id ? 'Edit Announcement' : 'Add New Announcement' ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        wp_nonce_field( 'ynj_announcement_save', 'ynj_announcement_nonce' );
        echo '<input type="hidden" name="announcement_id" value="' . $id . '">';

        echo '<table class="form-table">';

        // Mosque
        echo '<tr><th><label for="mosque_id">Mosque</label></th><td>';
        echo '<select name="mosque_id" id="mosque_id" class="regular-text" required>';
        echo '<option value="">-- Select --</option>';
        foreach ( $mosques as $m ) {
            $sel = ( $ann && (int) $ann->mosque_id === (int) $m->id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $m->id ) . '"' . $sel . '>' . esc_html( $m->name ) . '</option>';
        }
        echo '</select></td></tr>';

        // Title
        echo '<tr><th><label for="title">Title</label></th><td>';
        echo '<input type="text" name="title" id="title" class="regular-text" required value="' . $v( 'title' ) . '"></td></tr>';

        // Body
        echo '<tr><th><label for="body">Body</label></th><td>';
        echo '<textarea name="body" id="body" rows="8" class="large-text">' . ( $ann ? esc_textarea( $ann->body ) : '' ) . '</textarea></td></tr>';

        // Pinned
        echo '<tr><th>Pinned</th><td>';
        echo '<label><input type="checkbox" name="pinned" value="1"' . ( $ann && $ann->pinned ? ' checked' : '' ) . '> Pin this announcement</label>';
        echo '</td></tr>';

        // Expiry
        echo '<tr><th><label for="expires_at">Expires At</label></th><td>';
        echo '<input type="datetime-local" name="expires_at" id="expires_at" value="' . ( $ann && $ann->expires_at ? esc_attr( str_replace( ' ', 'T', substr( $ann->expires_at, 0, 16 ) ) ) : '' ) . '">';
        echo '<p class="description">Leave blank for no expiry</p></td></tr>';

        // Status
        echo '<tr><th><label for="status">Status</label></th><td>';
        echo '<select name="status" id="status">';
        $statuses = [ 'draft' => 'Draft', 'published' => 'Published' ];
        foreach ( $statuses as $val => $label ) {
            $sel = ( $ann && $ann->status === $val ) ? ' selected' : ( ! $ann && $val === 'draft' ? ' selected' : '' );
            echo '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
        }
        echo '</select></td></tr>';

        echo '</table>';

        submit_button( $id ? 'Update Announcement' : 'Create Announcement', 'primary', 'ynj_announcement_save' );

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
 *  WP_List_Table: EVENTS
 * ===================================================================== */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Events_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'event',
            'plural'   => 'events',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'               => 'ID',
            'title'            => 'Title',
            'mosque'           => 'Mosque',
            'event_date'       => 'Date',
            'location'         => 'Location',
            'status'           => 'Status',
            'registered_count' => 'RSVP Count',
            'created_at'       => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'title'      => [ 'title', false ],
            'event_date' => [ 'event_date', false ],
            'status'     => [ 'status', false ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'events' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );

        $total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $published = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'published'" );
        $draft     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'draft'" );
        $cancelled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'cancelled'" );

        $base = admin_url( 'admin.php?page=ynj-events' );

        $views = [];
        $views['all']       = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['published'] = '<a href="' . esc_url( $base . '&status=published' ) . '"' . ( $current === 'published' ? ' class="current"' : '' ) . '>Published <span class="count">(' . $published . ')</span></a>';
        $views['draft']     = '<a href="' . esc_url( $base . '&status=draft' ) . '"' . ( $current === 'draft' ? ' class="current"' : '' ) . '>Draft <span class="count">(' . $draft . ')</span></a>';
        $views['cancelled'] = '<a href="' . esc_url( $base . '&status=cancelled' ) . '"' . ( $current === 'cancelled' ? ' class="current"' : '' ) . '>Cancelled <span class="count">(' . $cancelled . ')</span></a>';

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
        $table    = YNJ_DB::table( 'events' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( in_array( $status, [ 'published', 'draft', 'cancelled' ], true ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status );
        }

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= $wpdb->prepare( ' AND mosque_id = %d', absint( $_GET['mosque_id'] ) );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'title', 'event_date', 'status', 'created_at' ];
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
            case 'id':               return $item->id;
            case 'mosque':           return esc_html( YNJ_Events_Admin::get_mosque_name( $item->mosque_id ) );
            case 'event_date':       return $item->event_date ?: '--';
            case 'location':         return esc_html( $item->location ?: '--' );
            case 'status':           return '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'registered_count': return (int) $item->registered_count;
            case 'created_at':       return $item->created_at ? date( 'Y-m-d', strtotime( $item->created_at ) ) : '--';
            default:                 return '';
        }
    }

    public function column_title( $item ) {
        $edit_url = admin_url( 'admin.php?page=ynj-event-edit&id=' . $item->id );

        $actions = [
            'edit' => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
        ];

        if ( $item->status !== 'published' ) {
            $actions['publish'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-events&ynj_action=event_publish&id=' . $item->id ),
                'ynj_action_event_publish_' . $item->id
            ) ) . '">Publish</a>';
        }

        if ( $item->status !== 'draft' ) {
            $actions['draft'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-events&ynj_action=event_draft&id=' . $item->id ),
                'ynj_action_event_draft_' . $item->id
            ) ) . '">Draft</a>';
        }

        $actions['delete'] = '<a href="' . esc_url( wp_nonce_url(
            admin_url( 'admin.php?page=ynj-events&ynj_action=event_delete&id=' . $item->id ),
            'ynj_action_event_delete_' . $item->id
        ) ) . '" onclick="return confirm(\'Delete this event?\');" class="submitdelete">Delete</a>';

        return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->title ) . '</a></strong>'
             . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No events found.';
    }
}


/* =======================================================================
 *  WP_List_Table: ANNOUNCEMENTS
 * ===================================================================== */

class YNJ_Announcements_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'announcement',
            'plural'   => 'announcements',
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
            'id'           => 'ID',
            'title'        => 'Title',
            'mosque'       => 'Mosque',
            'pinned'       => 'Pinned',
            'status'       => 'Status',
            'published_at' => 'Published At',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'           => [ 'id', true ],
            'title'        => [ 'title', false ],
            'published_at' => [ 'published_at', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'announcements' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );

        $total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $published = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'published'" );
        $draft     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'draft'" );

        $base = admin_url( 'admin.php?page=ynj-announcements' );

        $views = [];
        $views['all']       = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['published'] = '<a href="' . esc_url( $base . '&status=published' ) . '"' . ( $current === 'published' ? ' class="current"' : '' ) . '>Published <span class="count">(' . $published . ')</span></a>';
        $views['draft']     = '<a href="' . esc_url( $base . '&status=draft' ) . '"' . ( $current === 'draft' ? ' class="current"' : '' ) . '>Draft <span class="count">(' . $draft . ')</span></a>';

        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $table    = YNJ_DB::table( 'announcements' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( in_array( $status, [ 'published', 'draft' ], true ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status );
        }

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= $wpdb->prepare( ' AND mosque_id = %d', absint( $_GET['mosque_id'] ) );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'title', 'published_at' ];
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
            case 'id':           return $item->id;
            case 'mosque':       return esc_html( YNJ_Events_Admin::get_mosque_name( $item->mosque_id ) );
            case 'pinned':       return $item->pinned ? '<strong>Yes</strong>' : 'No';
            case 'status':       return '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'published_at': return $item->published_at ?: '--';
            default:             return '';
        }
    }

    public function column_title( $item ) {
        $edit_url = admin_url( 'admin.php?page=ynj-announcement-edit&id=' . $item->id );

        $actions = [
            'edit' => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
        ];

        if ( $item->pinned ) {
            $actions['unpin'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-announcements&ynj_action=ann_unpin&id=' . $item->id ),
                'ynj_action_ann_unpin_' . $item->id
            ) ) . '">Unpin</a>';
        } else {
            $actions['pin'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-announcements&ynj_action=ann_pin&id=' . $item->id ),
                'ynj_action_ann_pin_' . $item->id
            ) ) . '">Pin</a>';
        }

        $actions['delete'] = '<a href="' . esc_url( wp_nonce_url(
            admin_url( 'admin.php?page=ynj-announcements&ynj_action=ann_delete&id=' . $item->id ),
            'ynj_action_ann_delete_' . $item->id
        ) ) . '" onclick="return confirm(\'Delete this announcement?\');" class="submitdelete">Delete</a>';

        return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->title ) . '</a></strong>'
             . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No announcements found.';
    }
}


/* =======================================================================
 *  WP_List_Table: BOOKINGS
 * ===================================================================== */

class YNJ_Bookings_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'booking',
            'plural'   => 'bookings',
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
            'id'           => 'ID',
            'user_name'    => 'Name',
            'user_email'   => 'Email',
            'type'         => 'Event / Room',
            'mosque'       => 'Mosque',
            'status'       => 'Status',
            'booking_date' => 'Date',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'           => [ 'id', true ],
            'user_name'    => [ 'user_name', false ],
            'booking_date' => [ 'booking_date', false ],
            'status'       => [ 'status', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'bookings' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );

        $total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );
        $confirmed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'confirmed'" );
        $cancelled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'cancelled'" );

        $base = admin_url( 'admin.php?page=ynj-bookings' );

        $views = [];
        $views['all']       = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['pending']   = '<a href="' . esc_url( $base . '&status=pending' ) . '"' . ( $current === 'pending' ? ' class="current"' : '' ) . '>Pending <span class="count">(' . $pending . ')</span></a>';
        $views['confirmed'] = '<a href="' . esc_url( $base . '&status=confirmed' ) . '"' . ( $current === 'confirmed' ? ' class="current"' : '' ) . '>Confirmed <span class="count">(' . $confirmed . ')</span></a>';
        $views['cancelled'] = '<a href="' . esc_url( $base . '&status=cancelled' ) . '"' . ( $current === 'cancelled' ? ' class="current"' : '' ) . '>Cancelled <span class="count">(' . $cancelled . ')</span></a>';

        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $table    = YNJ_DB::table( 'bookings' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( in_array( $status, [ 'pending', 'confirmed', 'cancelled' ], true ) ) {
            $where .= $wpdb->prepare( ' AND status = %s', $status );
        }

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= $wpdb->prepare( ' AND mosque_id = %d', absint( $_GET['mosque_id'] ) );
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'user_name', 'booking_date', 'status' ];
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
            case 'id':           return $item->id;
            case 'user_name':    return esc_html( $item->user_name );
            case 'user_email':   return esc_html( $item->user_email );
            case 'mosque':       return esc_html( YNJ_Events_Admin::get_mosque_name( $item->mosque_id ) );
            case 'booking_date': return $item->booking_date ?: '--';
            default:             return '';
        }
    }

    public function column_type( $item ) {
        if ( $item->event_id ) {
            global $wpdb;
            $title = $wpdb->get_var( $wpdb->prepare(
                "SELECT title FROM " . YNJ_DB::table( 'events' ) . " WHERE id = %d", $item->event_id
            ) );
            return 'Event: ' . esc_html( $title ?: '#' . $item->event_id );
        }
        if ( $item->room_id ) {
            global $wpdb;
            $name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM " . YNJ_DB::table( 'rooms' ) . " WHERE id = %d", $item->room_id
            ) );
            return 'Room: ' . esc_html( $name ?: '#' . $item->room_id );
        }
        return '--';
    }

    public function column_status( $item ) {
        $label = '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';

        $actions = [];

        if ( $item->status !== 'confirmed' ) {
            $actions['confirm'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-bookings&ynj_action=booking_confirm&id=' . $item->id ),
                'ynj_action_booking_confirm_' . $item->id
            ) ) . '">Confirm</a>';
        }

        if ( $item->status !== 'cancelled' ) {
            $actions['cancel'] = '<a href="' . esc_url( wp_nonce_url(
                admin_url( 'admin.php?page=ynj-bookings&ynj_action=booking_cancel&id=' . $item->id ),
                'ynj_action_booking_cancel_' . $item->id
            ) ) . '" onclick="return confirm(\'Cancel this booking?\');">Cancel</a>';
        }

        return $label . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No bookings found.';
    }
}
