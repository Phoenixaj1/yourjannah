<?php
/**
 * YourJannah Engagement — WP Admin pages.
 *
 * Registers top-level "Engagement" menu with sub-pages for
 * Dua Wall, Gratitude, and Reactions summary.
 *
 * @package YNJ_Engagement
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Engagement_Admin {

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
            'Engagement',
            'Engagement',
            'manage_options',
            'ynj-engagement',
            [ __CLASS__, 'page_dua_wall' ],
            'dashicons-heart',
            32
        );

        add_submenu_page(
            'ynj-engagement',
            'Dua Wall',
            'Dua Wall',
            'manage_options',
            'ynj-engagement',
            [ __CLASS__, 'page_dua_wall' ]
        );

        add_submenu_page(
            'ynj-engagement',
            'Gratitude',
            'Gratitude',
            'manage_options',
            'ynj-engagement-gratitude',
            [ __CLASS__, 'page_gratitude' ]
        );

        add_submenu_page(
            'ynj-engagement',
            'Reactions',
            'Reactions',
            'manage_options',
            'ynj-engagement-reactions',
            [ __CLASS__, 'page_reactions' ]
        );
    }

    /* ==============================================================
     *  ACTION HANDLER (deletes / moderation)
     * ============================================================ */

    public static function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $action = sanitize_text_field( $_GET['ynj_action'] ?? '' );
        if ( ! $action ) return;

        $id = absint( $_GET['id'] ?? 0 );
        if ( ! $id ) return;

        check_admin_referer( 'ynj_action_' . $action . '_' . $id );

        global $wpdb;

        switch ( $action ) {

            /* -- Dua Wall -- */
            case 'dua_delete':
                $wpdb->delete( YNJ_DB::table( 'dua_requests' ), [ 'id' => $id ] );
                $wpdb->delete( YNJ_DB::table( 'dua_responses' ), [ 'dua_request_id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-engagement&msg=deleted' ) );
                exit;

            /* -- Gratitude -- */
            case 'gratitude_delete':
                $wpdb->delete( YNJ_DB::table( 'gratitude_posts' ), [ 'id' => $id ] );
                wp_safe_redirect( admin_url( 'admin.php?page=ynj-engagement-gratitude&msg=deleted' ) );
                exit;
        }
    }

    /* ==============================================================
     *  PAGE: DUA WALL
     * ============================================================ */

    public static function page_dua_wall() {
        $table = new YNJ_Dua_Wall_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Dua Wall</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-engagement">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: GRATITUDE
     * ============================================================ */

    public static function page_gratitude() {
        $table = new YNJ_Gratitude_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Gratitude Posts</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-engagement-gratitude">';
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: REACTIONS SUMMARY
     * ============================================================ */

    public static function page_reactions() {
        global $wpdb;
        $rt = YNJ_DB::table( 'reactions' );

        // Summary by content_type with reaction counts
        $summary = $wpdb->get_results(
            "SELECT content_type,
                    SUM( reaction = 'like' )       AS likes,
                    SUM( reaction = 'dua' )        AS dua,
                    SUM( reaction = 'interested' ) AS interested,
                    SUM( reaction = 'share' )       AS shares,
                    COUNT(*)                        AS total
             FROM $rt
             GROUP BY content_type
             ORDER BY total DESC"
        );

        // Last 7 days breakdown
        $seven_days = $wpdb->get_results( $wpdb->prepare(
            "SELECT content_type,
                    SUM( reaction = 'like' )       AS likes,
                    SUM( reaction = 'dua' )        AS dua,
                    SUM( reaction = 'interested' ) AS interested,
                    SUM( reaction = 'share' )       AS shares,
                    COUNT(*)                        AS total
             FROM $rt
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY content_type
             ORDER BY total DESC",
            7
        ) );

        echo '<div class="wrap">';
        echo '<h1>Reactions Summary</h1>';
        echo '<hr class="wp-header-end">';

        // All-time table
        echo '<h2>All Time</h2>';
        self::render_reactions_table( $summary );

        // Last 7 days table
        echo '<h2>Last 7 Days</h2>';
        self::render_reactions_table( $seven_days );

        echo '</div>';
    }

    /** Render a reactions summary table. */
    private static function render_reactions_table( $rows ) {
        echo '<table class="widefat fixed striped" style="max-width:700px;">';
        echo '<thead><tr>';
        echo '<th>Content Type</th><th>Like</th><th>Dua</th><th>Interested</th><th>Share</th><th>Total</th>';
        echo '</tr></thead><tbody>';

        if ( $rows ) {
            foreach ( $rows as $row ) {
                echo '<tr>';
                echo '<td>' . esc_html( ucfirst( $row->content_type ) ) . '</td>';
                echo '<td>' . (int) $row->likes . '</td>';
                echo '<td>' . (int) $row->dua . '</td>';
                echo '<td>' . (int) $row->interested . '</td>';
                echo '<td>' . (int) $row->shares . '</td>';
                echo '<td><strong>' . (int) $row->total . '</strong></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6">No reactions recorded.</td></tr>';
        }

        echo '</tbody></table>';
    }

    /* ==============================================================
     *  ADMIN NOTICES
     * ============================================================ */

    private static function admin_notices() {
        $msg = sanitize_text_field( $_GET['msg'] ?? '' );
        if ( ! $msg ) return;

        $messages = [
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
 *  WP_List_Table: DUA WALL
 * ===================================================================== */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Dua_Wall_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'dua',
            'plural'   => 'duas',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'           => 'ID',
            'user_id'      => 'User ID',
            'mosque'       => 'Mosque',
            'request_text' => 'Dua Text',
            'dua_count'    => 'Prayers',
            'status'       => 'Status',
            'created_at'   => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'dua_count'  => [ 'dua_count', false ],
            'status'     => [ 'status', false ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table    = YNJ_DB::table( 'dua_requests' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'dua_count', 'status', 'created_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) $orderby = 'id';
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $this->items = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY $orderby $order LIMIT $per_page OFFSET $offset"
        );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

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
            case 'user_id':    return (int) $item->user_id;
            case 'mosque':     return esc_html( YNJ_Engagement_Admin::get_mosque_name( $item->mosque_id ) );
            case 'dua_count':  return (int) $item->dua_count;
            case 'status':     return '<span class="ynj-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'created_at': return $item->created_at ? date( 'Y-m-d H:i', strtotime( $item->created_at ) ) : '--';
            default:           return '';
        }
    }

    public function column_request_text( $item ) {
        $truncated = mb_strlen( $item->request_text ) > 80
            ? mb_substr( $item->request_text, 0, 80 ) . '...'
            : $item->request_text;

        $actions = [];

        // View Full — shows full text via JS alert (lightweight, no extra page needed)
        $full_text = esc_attr( $item->request_text );
        $actions['view'] = '<a href="#" onclick="alert(this.dataset.text); return false;" data-text="' . $full_text . '">View Full</a>';

        $actions['delete'] = '<a href="' . esc_url( wp_nonce_url(
            admin_url( 'admin.php?page=ynj-engagement&ynj_action=dua_delete&id=' . $item->id ),
            'ynj_action_dua_delete_' . $item->id
        ) ) . '" onclick="return confirm(\'Delete this dua request and all its prayer responses?\');" class="submitdelete">Delete</a>';

        return esc_html( $truncated ) . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No dua requests found.';
    }
}


/* =======================================================================
 *  WP_List_Table: GRATITUDE
 * ===================================================================== */

class YNJ_Gratitude_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'gratitude',
            'plural'   => 'gratitude_posts',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'         => 'ID',
            'user_id'    => 'User ID',
            'mosque'     => 'Mosque',
            'message'    => 'Message',
            'created_at' => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table    = YNJ_DB::table( 'gratitude_posts' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'id' ) ?: 'id';
        $allowed = [ 'id', 'created_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) $orderby = 'id';
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $this->items = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY $orderby $order LIMIT $per_page OFFSET $offset"
        );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

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
            case 'user_id':    return (int) $item->user_id;
            case 'mosque':     return esc_html( YNJ_Engagement_Admin::get_mosque_name( $item->mosque_id ) );
            case 'created_at': return $item->created_at ? date( 'Y-m-d H:i', strtotime( $item->created_at ) ) : '--';
            default:           return '';
        }
    }

    public function column_message( $item ) {
        $truncated = mb_strlen( $item->message ) > 80
            ? mb_substr( $item->message, 0, 80 ) . '...'
            : $item->message;

        $actions = [];

        $actions['delete'] = '<a href="' . esc_url( wp_nonce_url(
            admin_url( 'admin.php?page=ynj-engagement-gratitude&ynj_action=gratitude_delete&id=' . $item->id ),
            'ynj_action_gratitude_delete_' . $item->id
        ) ) . '" onclick="return confirm(\'Delete this gratitude post?\');" class="submitdelete">Delete</a>';

        return esc_html( $truncated ) . $this->row_actions( $actions );
    }

    public function no_items() {
        echo 'No gratitude posts found.';
    }
}
