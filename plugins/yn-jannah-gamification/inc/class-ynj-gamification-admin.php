<?php
/**
 * YourJannah Gamification — WP Admin pages.
 *
 * Top-level "Gamification" menu with sub-pages:
 * - Leaderboard (top 50 mosques by dhikr)
 * - Points Log  (recent 100 point awards)
 * - Badges      (users who earned badges)
 *
 * Read-only dashboard views — no edit forms needed.
 *
 * @package YNJ_Gamification
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Gamification_Admin {

    /** Boot admin hooks. */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
    }

    /* ==============================================================
     *  MENU REGISTRATION
     * ============================================================ */

    public static function register_menus() {
        add_menu_page(
            'Gamification',
            'Gamification',
            'manage_options',
            'ynj-gamification',
            [ __CLASS__, 'page_leaderboard' ],
            'dashicons-awards',
            33
        );

        add_submenu_page(
            'ynj-gamification',
            'Leaderboard',
            'Leaderboard',
            'manage_options',
            'ynj-gamification',
            [ __CLASS__, 'page_leaderboard' ]
        );

        add_submenu_page(
            'ynj-gamification',
            'Points Log',
            'Points Log',
            'manage_options',
            'ynj-points-log',
            [ __CLASS__, 'page_points_log' ]
        );

        add_submenu_page(
            'ynj-gamification',
            'Badges',
            'Badges',
            'manage_options',
            'ynj-badges',
            [ __CLASS__, 'page_badges' ]
        );
    }

    /* ==============================================================
     *  PAGE: LEADERBOARD — top 50 mosques by dhikr this week
     * ============================================================ */

    public static function page_leaderboard() {
        $table = new YNJ_Leaderboard_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Mosque Leaderboard</h1>';
        echo '<p class="description">Top 50 mosques by dhikr count this week.</p>';
        echo '<hr class="wp-header-end">';

        $table->display();
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: POINTS LOG — recent 100 point awards
     * ============================================================ */

    public static function page_points_log() {
        $table = new YNJ_Points_Log_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Points Log</h1>';
        echo '<p class="description">Last 100 point awards across all mosques.</p>';
        echo '<hr class="wp-header-end">';

        $table->display();
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: BADGES — users who earned badges
     * ============================================================ */

    public static function page_badges() {
        $table = new YNJ_Badges_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>Badges</h1>';
        echo '<p class="description">Users who have earned badges.</p>';
        echo '<hr class="wp-header-end">';

        $table->display();
        echo '</div>';
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

    /* ==============================================================
     *  HELPER: user display name lookup cache
     * ============================================================ */

    private static $user_names = null;

    public static function get_user_display( $user_id ) {
        global $wpdb;

        if ( self::$user_names === null ) {
            self::$user_names = [];
        }

        $user_id = (int) $user_id;
        if ( ! isset( self::$user_names[ $user_id ] ) ) {
            $name = $wpdb->get_var( $wpdb->prepare(
                "SELECT display_name FROM " . YNJ_DB::table( 'users' ) . " WHERE wp_user_id = %d",
                $user_id
            ) );
            if ( ! $name ) {
                $wp_user = get_userdata( $user_id );
                $name = $wp_user ? $wp_user->display_name : '(#' . $user_id . ')';
            }
            self::$user_names[ $user_id ] = $name;
        }

        return self::$user_names[ $user_id ];
    }
}


/* =======================================================================
 *  WP_List_Table: LEADERBOARD
 * ===================================================================== */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Leaderboard_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'mosque',
            'plural'   => 'mosques',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'rank'        => 'Rank',
            'name'        => 'Mosque Name',
            'city'        => 'City',
            'members'     => 'Members',
            'dhikr_week'  => 'Dhikr This Week',
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $mt    = YNJ_DB::table( 'mosques' );
        $sub   = YNJ_DB::table( 'user_subscriptions' );
        $ib    = YNJ_DB::table( 'ibadah_logs' );
        $since = date( 'Y-m-d', strtotime( '-7 days' ) );

        $this->items = $wpdb->get_results(
            "SELECT m.id, m.name, m.city,
                    COALESCE(s.cnt, 0) AS members,
                    COALESCE(dk.dhikr_count, 0) AS dhikr_week
             FROM $mt m
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS cnt
                 FROM $sub WHERE status = 'active' GROUP BY mosque_id
             ) s ON s.mosque_id = m.id
             LEFT JOIN (
                 SELECT mosque_id, COUNT(*) AS dhikr_count
                 FROM $ib WHERE dhikr = 1 AND log_date >= '$since' GROUP BY mosque_id
             ) dk ON dk.mosque_id = m.id
             WHERE m.status = 'active'
             ORDER BY dhikr_week DESC
             LIMIT 50"
        ) ?: [];

        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    public function column_default( $item, $column_name ) {
        static $rank = 0;
        switch ( $column_name ) {
            case 'rank':       return ++$rank;
            case 'name':       return esc_html( $item->name );
            case 'city':       return esc_html( $item->city ?: '--' );
            case 'members':    return number_format( (int) $item->members );
            case 'dhikr_week': return number_format( (int) $item->dhikr_week );
            default:           return '';
        }
    }

    public function no_items() {
        echo 'No mosque data found.';
    }
}


/* =======================================================================
 *  WP_List_Table: POINTS LOG
 * ===================================================================== */

class YNJ_Points_Log_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'point',
            'plural'   => 'points',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'user'       => 'User',
            'mosque'     => 'Mosque',
            'action'     => 'Action',
            'points'     => 'Points',
            'created_at' => 'Date',
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $pt = YNJ_DB::table( 'points' );

        $this->items = $wpdb->get_results(
            "SELECT user_id, mosque_id, action, points, created_at
             FROM $pt
             ORDER BY created_at DESC
             LIMIT 100"
        ) ?: [];

        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'user':       return esc_html( YNJ_Gamification_Admin::get_user_display( $item->user_id ) );
            case 'mosque':     return esc_html( YNJ_Gamification_Admin::get_mosque_name( $item->mosque_id ) );
            case 'action':     return esc_html( str_replace( '_', ' ', ucfirst( $item->action ) ) );
            case 'points':     return (int) $item->points;
            case 'created_at': return $item->created_at ? date( 'Y-m-d H:i', strtotime( $item->created_at ) ) : '--';
            default:           return '';
        }
    }

    public function no_items() {
        echo 'No point awards found.';
    }
}


/* =======================================================================
 *  WP_List_Table: BADGES
 * ===================================================================== */

class YNJ_Badges_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'badge',
            'plural'   => 'badges',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'user'       => 'User',
            'badge_name' => 'Badge Name',
            'badge_icon' => 'Badge Icon',
            'mosque'     => 'Mosque',
            'earned_at'  => 'Earned Date',
        ];
    }

    public function get_sortable_columns() {
        return [
            'badge_name' => [ 'badge_name', false ],
            'earned_at'  => [ 'earned_at', true ],
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $bt       = YNJ_DB::table( 'user_badges' );
        $per_page = 20;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $orderby = sanitize_sql_orderby( $_GET['orderby'] ?? 'earned_at' ) ?: 'earned_at';
        $allowed = [ 'badge_name', 'earned_at' ];
        if ( ! in_array( $orderby, $allowed, true ) ) $orderby = 'earned_at';
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        $this->items = $wpdb->get_results(
            "SELECT user_id, mosque_id, badge_key, badge_name, badge_icon, earned_at
             FROM $bt
             ORDER BY $orderby $order
             LIMIT $per_page OFFSET $offset"
        ) ?: [];

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $bt" );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'user':       return esc_html( YNJ_Gamification_Admin::get_user_display( $item->user_id ) );
            case 'badge_name': return esc_html( $item->badge_name );
            case 'badge_icon': return $item->badge_icon;
            case 'mosque':     return esc_html( YNJ_Gamification_Admin::get_mosque_name( $item->mosque_id ) );
            case 'earned_at':  return $item->earned_at ? date( 'Y-m-d H:i', strtotime( $item->earned_at ) ) : '--';
            default:           return '';
        }
    }

    public function no_items() {
        echo 'No badges earned yet.';
    }
}
