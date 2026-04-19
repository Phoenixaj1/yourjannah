<?php
/**
 * YourJannah Notifications — WP Admin pages.
 *
 * Registers top-level "Notifications" menu with sub-pages for
 * Sent Notifications, Broadcast, and Stats.
 *
 * @package YNJ_Notifications
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Notifications_Admin {

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
            'Notifications',
            'Notifications',
            'manage_options',
            'ynj-notifications',
            [ __CLASS__, 'page_sent' ],
            'dashicons-bell',
            31
        );

        add_submenu_page(
            'ynj-notifications',
            'Sent Notifications',
            'Sent Notifications',
            'manage_options',
            'ynj-notifications',
            [ __CLASS__, 'page_sent' ]
        );

        add_submenu_page(
            'ynj-notifications',
            'Broadcast',
            'Broadcast',
            'manage_options',
            'ynj-notifications-broadcast',
            [ __CLASS__, 'page_broadcast' ]
        );

        add_submenu_page(
            'ynj-notifications',
            'Stats',
            'Stats',
            'manage_options',
            'ynj-notifications-stats',
            [ __CLASS__, 'page_stats' ]
        );
    }

    /* ==============================================================
     *  ACTION HANDLER
     * ============================================================ */

    public static function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        /* ---- Broadcast send ---- */
        if ( isset( $_POST['ynj_broadcast_send'] ) ) {
            check_admin_referer( 'ynj_broadcast_send', 'ynj_broadcast_nonce' );
            self::do_broadcast();
            return;
        }
    }

    /* ==============================================================
     *  BROADCAST HANDLER
     * ============================================================ */

    private static function do_broadcast() {
        $mosque_id = absint( $_POST['mosque_id'] ?? 0 );
        $title     = sanitize_text_field( $_POST['broadcast_title'] ?? '' );
        $body      = sanitize_textarea_field( $_POST['broadcast_body'] ?? '' );

        if ( ! $mosque_id || ! $title || ! $body ) {
            wp_safe_redirect( admin_url( 'admin.php?page=ynj-notifications-broadcast&msg=missing' ) );
            exit;
        }

        $count = YNJ_Notifications::broadcast( $mosque_id, $title, $body );

        wp_safe_redirect( admin_url( 'admin.php?page=ynj-notifications-broadcast&msg=sent&count=' . $count ) );
        exit;
    }

    /* ==============================================================
     *  PAGE: SENT NOTIFICATIONS
     * ============================================================ */

    public static function page_sent() {
        $table = new YNJ_Sent_Notifications_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Sent Notifications</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-notifications">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: BROADCAST
     * ============================================================ */

    public static function page_broadcast() {
        global $wpdb;

        $mosques = $wpdb->get_results(
            "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
        );

        echo '<div class="wrap">';
        echo '<h1>Broadcast Notification</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        wp_nonce_field( 'ynj_broadcast_send', 'ynj_broadcast_nonce' );

        echo '<table class="form-table">';

        // Mosque
        echo '<tr><th><label for="mosque_id">Mosque</label></th><td>';
        echo '<select name="mosque_id" id="mosque_id" class="regular-text" required>';
        echo '<option value="">-- Select Mosque --</option>';
        foreach ( $mosques as $m ) {
            echo '<option value="' . esc_attr( $m->id ) . '">' . esc_html( $m->name ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">All active subscribers of this mosque will receive the notification.</p>';
        echo '</td></tr>';

        // Title
        echo '<tr><th><label for="broadcast_title">Title</label></th><td>';
        echo '<input type="text" name="broadcast_title" id="broadcast_title" class="regular-text" required>';
        echo '</td></tr>';

        // Body
        echo '<tr><th><label for="broadcast_body">Body</label></th><td>';
        echo '<textarea name="broadcast_body" id="broadcast_body" rows="6" class="large-text" required></textarea>';
        echo '</td></tr>';

        echo '</table>';

        submit_button( 'Send Broadcast', 'primary', 'ynj_broadcast_send' );

        echo '</form></div>';
    }

    /* ==============================================================
     *  PAGE: STATS
     * ============================================================ */

    public static function page_stats() {
        global $wpdb;
        $t = YNJ_DB::table( 'notifications' );
        $m = YNJ_DB::table( 'mosques' );

        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
        $unread = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE read_at IS NULL" );
        $read   = $total - $unread;

        // By type
        $by_type = $wpdb->get_results(
            "SELECT type, COUNT(*) AS cnt FROM $t GROUP BY type ORDER BY cnt DESC"
        );

        // By mosque (top 20)
        $by_mosque = $wpdb->get_results(
            "SELECT n.mosque_id, ms.name AS mosque_name, COUNT(*) AS cnt
             FROM $t n
             LEFT JOIN $m ms ON ms.id = n.mosque_id
             GROUP BY n.mosque_id
             ORDER BY cnt DESC
             LIMIT 20"
        );

        echo '<div class="wrap">';
        echo '<h1>Notification Stats</h1>';
        echo '<hr class="wp-header-end">';

        // Summary cards
        echo '<div style="display:flex;gap:20px;margin:20px 0;">';
        self::stat_card( 'Total Sent', $total );
        self::stat_card( 'Read', $read );
        self::stat_card( 'Unread', $unread );
        echo '</div>';

        // By type table
        echo '<h2>By Type</h2>';
        echo '<table class="widefat fixed striped" style="max-width:500px;">';
        echo '<thead><tr><th>Type</th><th>Count</th></tr></thead><tbody>';
        if ( $by_type ) {
            foreach ( $by_type as $row ) {
                echo '<tr><td>' . esc_html( ucfirst( $row->type ) ) . '</td><td>' . (int) $row->cnt . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="2">No data.</td></tr>';
        }
        echo '</tbody></table>';

        // By mosque table
        echo '<h2>By Mosque (Top 20)</h2>';
        echo '<table class="widefat fixed striped" style="max-width:500px;">';
        echo '<thead><tr><th>Mosque</th><th>Count</th></tr></thead><tbody>';
        if ( $by_mosque ) {
            foreach ( $by_mosque as $row ) {
                $name = $row->mosque_name ?: '(#' . $row->mosque_id . ')';
                echo '<tr><td>' . esc_html( $name ) . '</td><td>' . (int) $row->cnt . '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="2">No data.</td></tr>';
        }
        echo '</tbody></table>';

        echo '</div>';
    }

    /** Render a stat card. */
    private static function stat_card( $label, $value ) {
        echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:20px 30px;text-align:center;min-width:120px;">';
        echo '<div style="font-size:28px;font-weight:700;color:#1d2327;">' . esc_html( number_format_i18n( $value ) ) . '</div>';
        echo '<div style="color:#50575e;margin-top:4px;">' . esc_html( $label ) . '</div>';
        echo '</div>';
    }

    /* ==============================================================
     *  ADMIN NOTICES
     * ============================================================ */

    private static function admin_notices() {
        $msg = sanitize_text_field( $_GET['msg'] ?? '' );
        if ( ! $msg ) return;

        $messages = [
            'sent'    => 'Broadcast sent to ' . absint( $_GET['count'] ?? 0 ) . ' subscribers.',
            'missing' => 'Please fill in all required fields.',
        ];

        if ( isset( $messages[ $msg ] ) ) {
            $type = $msg === 'missing' ? 'error' : 'success';
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
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
 *  WP_List_Table: SENT NOTIFICATIONS
 * ===================================================================== */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Sent_Notifications_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'notification',
            'plural'   => 'notifications',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'         => 'ID',
            'title'      => 'Title',
            'mosque'     => 'Mosque',
            'user_id'    => 'User ID',
            'type'       => 'Type',
            'status'     => 'Read/Unread',
            'created_at' => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'title'      => [ 'title', false ],
            'type'       => [ 'type', false ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    /** Type + mosque filter tabs. */
    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;

        $current_type = sanitize_text_field( $_GET['filter_type'] ?? '' );

        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table('mosques') . " WHERE status IN ('active','unclaimed') ORDER BY name" );
        $sel_mosque = absint( $_GET['mosque_id'] ?? 0 );

        echo '<div class="alignleft actions">';
        echo '<select name="filter_type">';
        echo '<option value="">All Types</option>';
        $types = [ 'announcement', 'event', 'broadcast', 'general' ];
        foreach ( $types as $t ) {
            $sel = ( $current_type === $t ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $t ) . '"' . $sel . '>' . esc_html( ucfirst( $t ) ) . '</option>';
        }
        echo '</select>';
        echo '<select name="mosque_id"><option value="">All Mosques</option>';
        foreach ( $mosques as $m ) {
            printf( '<option value="%d"%s>%s</option>', $m->id, $sel_mosque === (int) $m->id ? ' selected' : '', esc_html( $m->name ) );
        }
        echo '</select>';
        submit_button( 'Filter', '', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;
        $table    = YNJ_DB::table( 'notifications' );
        $per_page = 200;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where  = '1=1';
        $params = [];

        $filter_type = sanitize_text_field( $_GET['filter_type'] ?? '' );
        if ( in_array( $filter_type, [ 'announcement', 'event', 'broadcast', 'general' ], true ) ) {
            $where   .= ' AND type = %s';
            $params[] = $filter_type;
        }

        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        if ( $mosque_id ) {
            $where   .= ' AND mosque_id = %d';
            $params[] = $mosque_id;
        }

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'title', 'type', 'created_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) $orderby = 'id';
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";

        if ( $params ) {
            $this->items = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $where", ...$params ) );
        } else {
            $this->items = $wpdb->get_results( $sql );
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );
        }

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
            case 'title':      return esc_html( $item->title );
            case 'mosque':     return esc_html( YNJ_Notifications_Admin::get_mosque_name( $item->mosque_id ) );
            case 'user_id':    return (int) $item->user_id;
            case 'type':       return '<code>' . esc_html( $item->type ) . '</code>';
            case 'status':     return $item->read_at ? '<span style="color:#00a32a;">Read</span>' : '<strong>Unread</strong>';
            case 'created_at': return $item->created_at ? date( 'Y-m-d H:i', strtotime( $item->created_at ) ) : '--';
            default:           return '';
        }
    }

    public function no_items() {
        echo 'No notifications found.';
    }
}
