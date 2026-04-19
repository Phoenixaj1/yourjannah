<?php
/**
 * Youth Activities Admin.
 * @package YNJ_Youth
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Youth_Admin {
    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Youth', 'Youth', 'manage_options', 'ynj-youth', [ 'YNJ_Youth_Admin', 'page_list' ], 'dashicons-groups', 42 );
        } );
    }

    public static function page_list() {
        echo '<div class="wrap"><h1>⚽ Youth Activities</h1>';
        $table = new YNJ_Youth_List();
        $table->prepare_items();
        echo '<form method="get"><input type="hidden" name="page" value="ynj-youth">';
        $table->display();
        echo '</form></div>';
    }
}

class YNJ_Youth_List extends WP_List_Table {
    public function __construct() {
        parent::__construct( [ 'singular' => 'activity', 'plural' => 'activities', 'ajax' => false ] );
    }
    public function get_columns() {
        return [ 'title' => 'Activity', 'category' => 'Category', 'age_group' => 'Ages', 'day' => 'Day', 'mosque_name' => 'Mosque', 'date' => 'Added' ];
    }
    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY name" );
        $sel = absint( $_GET['mosque_id'] ?? 0 );
        echo '<div class="alignleft actions"><select name="mosque_id"><option value="">All Mosques</option>';
        foreach ( $mosques as $m ) { printf( '<option value="%d"%s>%s</option>', $m->id, $sel === (int) $m->id ? ' selected' : '', esc_html( $m->name ) ); }
        echo '</select>'; submit_button( 'Filter', '', 'filter_action', false ); echo '</div>';
    }
    public function prepare_items() {
        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        $result = YNJ_Youth::get_activities( $mosque_id, [ 'limit' => 50, 'page' => $this->get_pagenum() ] );
        $this->items = $result['activities'];
        $this->set_pagination_args( [ 'total_items' => $result['total'], 'per_page' => 50 ] );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }
    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'title': return '<strong>' . esc_html( $item['title'] ) . '</strong>';
            case 'category': return $item['cat_icon'] . ' ' . esc_html( $item['cat_label'] );
            case 'age_group': return esc_html( $item['age_group'] ?: '—' );
            case 'day': return esc_html( $item['day'] ?: '—' );
            case 'mosque_name': return esc_html( $item['mosque_name'] ?: '' );
            case 'date': return esc_html( $item['date'] );
        }
    }
}
