<?php
/**
 * Celebrations Admin — view and moderate community celebrations.
 * @package YNJ_Celebrations
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Celebrations_Admin {

    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Celebrations', 'Celebrations', 'manage_options', 'ynj-celebrations', [ 'YNJ_Celebrations_Admin', 'page_celebrations' ], 'dashicons-star-filled', 40 );
        } );
    }

    public static function page_celebrations() {
        $stats = YNJ_Celebrations::get_stats();
        $cats = YNJ_Celebrations::get_categories();

        echo '<div class="wrap"><h1>🎉 Celebrations</h1>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin:16px 0;">';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#287e61;">' . $stats['total'] . '</div><div style="font-size:11px;color:#666;">Total</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#f59e0b;">' . $stats['this_month'] . '</div><div style="font-size:11px;color:#666;">This Month</div></div>';
        foreach ( $stats['by_category'] as $ck => $count ) {
            $c = $cats[ $ck ] ?? $cats['other'];
            echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:20px;">' . $c['icon'] . '</div><div style="font-size:18px;font-weight:900;">' . $count . '</div><div style="font-size:10px;color:#666;">' . esc_html( $c['label'] ) . '</div></div>';
        }
        echo '</div>';

        $table = new YNJ_Celebrations_List_Table();
        $table->prepare_items();
        echo '<form method="get"><input type="hidden" name="page" value="ynj-celebrations">';
        $table->display();
        echo '</form></div>';
    }
}

class YNJ_Celebrations_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'celebration', 'plural' => 'celebrations', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'title'       => 'Title',
            'category'    => 'Category',
            'author_name' => 'Author',
            'mosque_name' => 'Mosque',
            'date'        => 'Date',
        ];
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY name" );
        $sel = absint( $_GET['mosque_id'] ?? 0 );
        echo '<div class="alignleft actions"><select name="mosque_id"><option value="">All Mosques</option>';
        foreach ( $mosques as $m ) {
            printf( '<option value="%d"%s>%s</option>', $m->id, $sel === (int) $m->id ? ' selected' : '', esc_html( $m->name ) );
        }
        echo '</select>';
        submit_button( 'Filter', '', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items() {
        $result = YNJ_Celebrations::get_celebrations( absint( $_GET['mosque_id'] ?? 0 ), [
            'limit' => 50,
            'page'  => $this->get_pagenum(),
        ] );
        $this->items = $result['celebrations'];
        $this->set_pagination_args( [ 'total_items' => $result['total'], 'per_page' => 50 ] );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'title':       return '<strong>' . esc_html( $item['title'] ) . '</strong>';
            case 'category':    return $item['cat_icon'] . ' ' . esc_html( $item['cat_label'] );
            case 'author_name': return esc_html( $item['author_name'] ?: '(anonymous)' );
            case 'mosque_name': return esc_html( $item['mosque_name'] ?: '' );
            case 'date':        return esc_html( $item['date'] );
        }
    }
}
