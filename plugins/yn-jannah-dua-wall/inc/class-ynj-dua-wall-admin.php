<?php
/**
 * Dua Wall Admin — moderation, stats.
 * @package YNJ_Dua_Wall
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Dua_Wall_Admin {

    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Dua Wall', 'Dua Wall', 'manage_options', 'ynj-dua-wall', [ 'YNJ_Dua_Wall_Admin', 'page_duas' ], 'dashicons-heart', 38 );
        } );
        add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
    }

    public static function handle_actions() {
        if ( ! isset( $_GET['ynj_dua_action'] ) || ! isset( $_GET['dua_id'] ) ) return;
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ynj_dua_mod' ) ) return;

        $action = sanitize_text_field( $_GET['ynj_dua_action'] );
        $dua_id = absint( $_GET['dua_id'] );

        YNJ_Dua_Wall::moderate( $dua_id, $action );

        wp_redirect( admin_url( 'admin.php?page=ynj-dua-wall&moderated=1' ) );
        exit;
    }

    public static function page_duas() {
        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        $stats = YNJ_Dua_Wall::get_stats( $mosque_id );

        if ( ! empty( $_GET['moderated'] ) ) {
            echo '<div class="notice notice-success"><p>Dua moderated successfully.</p></div>';
        }

        echo '<div class="wrap"><h1>🤲 Dua Wall</h1>';

        // Stats
        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0;">';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#287e61;">' . $stats['total'] . '</div><div style="font-size:11px;color:#666;">Total Duas</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#7c3aed;">' . number_format( $stats['total_prayers'] ) . '</div><div style="font-size:11px;color:#666;">Prayers Made</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#f59e0b;">' . $stats['today'] . '</div><div style="font-size:11px;color:#666;">Today</div></div>';
        echo '</div>';

        $table = new YNJ_Dua_List_Table();
        $table->prepare_items();
        echo '<form method="get"><input type="hidden" name="page" value="ynj-dua-wall">';
        $table->display();
        echo '</form></div>';
    }
}

class YNJ_Dua_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'dua', 'plural' => 'duas', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'dua_text'      => 'Dua',
            'author_name'   => 'Author',
            'mosque_name'   => 'Mosque',
            'prayer_count'  => 'Prayers',
            'status'        => 'Status',
            'created_at'    => 'Date',
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
        $result = YNJ_Dua_Wall::get_all_duas( [
            'limit'     => 50,
            'offset'    => ( $this->get_pagenum() - 1 ) * 50,
            'mosque_id' => absint( $_GET['mosque_id'] ?? 0 ),
            'status'    => 'approved',
        ] );
        $this->items = $result['duas'];
        $this->set_pagination_args( [ 'total_items' => $result['total'], 'per_page' => 50 ] );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'dua_text':
                $text = esc_html( mb_strimwidth( $item->dua_text, 0, 120, '...' ) );
                $pin = $item->pinned ? ' 📌' : '';
                $actions = [
                    'pin'    => '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=ynj-dua-wall&ynj_dua_action=' . ( $item->pinned ? 'unpin' : 'pin' ) . '&dua_id=' . $item->id ), 'ynj_dua_mod' ) . '">' . ( $item->pinned ? 'Unpin' : 'Pin' ) . '</a>',
                    'delete' => '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=ynj-dua-wall&ynj_dua_action=delete&dua_id=' . $item->id ), 'ynj_dua_mod' ) . '" style="color:#dc2626;" onclick="return confirm(\'Delete this dua?\')">Delete</a>',
                ];
                return '<strong>' . $text . $pin . '</strong>' . $this->row_actions( $actions );
            case 'author_name': return esc_html( $item->is_anonymous ? 'Anonymous' : ( $item->author_name ?: 'YourJannah' ) );
            case 'mosque_name': return esc_html( $item->mosque_name ?: '' );
            case 'prayer_count': return '<strong style="color:#7c3aed;">' . (int) $item->prayer_count . '</strong> 🤲';
            case 'status':
                $colors = [ 'approved' => '#287e61', 'pending' => '#f59e0b', 'rejected' => '#dc2626' ];
                $c = $colors[ $item->status ] ?? '#666';
                return '<span style="color:' . $c . ';font-weight:600;">' . ucfirst( $item->status ) . '</span>';
            case 'created_at': return esc_html( $item->created_at ?? '' );
        }
    }
}
