<?php
/**
 * Imam Messages Admin.
 * @package YNJ_Imam_Messages
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Imam_Messages_Admin {
    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Imam Messages', 'Imam Messages', 'manage_options', 'ynj-imam-messages', [ 'YNJ_Imam_Messages_Admin', 'page_list' ], 'dashicons-format-status', 41 );
        } );
    }

    public static function page_list() {
        echo '<div class="wrap"><h1>🕌 Imam Messages</h1>';
        $table = new YNJ_Imam_Messages_List();
        $table->prepare_items();
        echo '<form method="get"><input type="hidden" name="page" value="ynj-imam-messages">';
        $table->display();
        echo '</form></div>';
    }
}

class YNJ_Imam_Messages_List extends WP_List_Table {
    public function __construct() {
        parent::__construct( [ 'singular' => 'message', 'plural' => 'messages', 'ajax' => false ] );
    }
    public function get_columns() {
        return [ 'title' => 'Title', 'category' => 'Category', 'author_name' => 'Author', 'mosque_name' => 'Mosque', 'date' => 'Date' ];
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
        $args = [ 'post_type' => 'ynj_imam_message', 'posts_per_page' => 50, 'paged' => $this->get_pagenum(), 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ];
        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        if ( $mosque_id ) $args['meta_query'] = [ [ 'key' => '_ynj_mosque_id', 'value' => $mosque_id, 'type' => 'NUMERIC' ] ];
        $query = new \WP_Query( $args );
        $this->items = array_map( function( $p ) {
            $mid = (int) get_post_meta( $p->ID, '_ynj_mosque_id', true );
            global $wpdb;
            return (object) [
                'title' => $p->post_title, 'date' => $p->post_date,
                'author_name' => get_post_meta( $p->ID, '_ynj_author_name', true ) ?: 'Imam',
                'category' => get_post_meta( $p->ID, '_ynj_category', true ) ?: 'daily',
                'mosque_name' => $mid ? $wpdb->get_var( $wpdb->prepare( "SELECT name FROM " . YNJ_DB::table('mosques') . " WHERE id=%d", $mid ) ) : '',
            ];
        }, $query->posts );
        $this->set_pagination_args( [ 'total_items' => $query->found_posts, 'per_page' => 50 ] );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }
    public function column_default( $item, $col ) {
        $cats = YNJ_Imam_Messages::get_categories();
        switch ( $col ) {
            case 'title': return '<strong>' . esc_html( $item->title ) . '</strong>';
            case 'category': $c = $cats[ $item->category ] ?? $cats['daily']; return $c['icon'] . ' ' . $c['label'];
            case 'author_name': return esc_html( $item->author_name );
            case 'mosque_name': return esc_html( $item->mosque_name ?: '' );
            case 'date': return esc_html( $item->date );
        }
    }
}
