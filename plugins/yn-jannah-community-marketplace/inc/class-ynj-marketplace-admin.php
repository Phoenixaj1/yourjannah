<?php
/**
 * Marketplace Admin — moderation + listing management.
 * @package YNJ_Marketplace
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Marketplace_Admin {

    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Community', 'Community', 'manage_options', 'ynj-marketplace', [ 'YNJ_Marketplace_Admin', 'page_listings' ], 'dashicons-store', 39 );
        } );
        add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
    }

    public static function handle_actions() {
        if ( ! isset( $_GET['ynj_listing_action'], $_GET['listing_id'] ) ) return;
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ynj_listing_mod' ) ) return;

        $action = sanitize_text_field( $_GET['ynj_listing_action'] );
        $id     = absint( $_GET['listing_id'] );

        if ( $action === 'approve' ) YNJ_Marketplace::approve( $id );
        elseif ( $action === 'reject' ) YNJ_Marketplace::reject( $id );

        wp_redirect( admin_url( 'admin.php?page=ynj-marketplace&moderated=1' ) );
        exit;
    }

    public static function page_listings() {
        $stats = YNJ_Marketplace::get_stats();

        if ( ! empty( $_GET['moderated'] ) ) {
            echo '<div class="notice notice-success"><p>Listing moderated.</p></div>';
        }

        echo '<div class="wrap"><h1>🏪 Community Marketplace</h1>';

        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0;">';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#f59e0b;">' . $stats['pending'] . '</div><div style="font-size:11px;color:#666;">Pending Approval</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#287e61;">' . $stats['approved'] . '</div><div style="font-size:11px;color:#666;">Live Listings</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;text-align:center;"><div style="font-size:24px;font-weight:900;color:#00ADEF;">' . $stats['total'] . '</div><div style="font-size:11px;color:#666;">Total</div></div>';
        echo '</div>';

        $table = new YNJ_Marketplace_List_Table();
        $table->prepare_items();
        echo '<form method="get"><input type="hidden" name="page" value="ynj-marketplace">';
        $table->search_box( 'Search Listings', 's' );
        $table->display();
        echo '</form></div>';
    }
}

class YNJ_Marketplace_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'listing', 'plural' => 'listings', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'title'       => 'Title',
            'category'    => 'Category',
            'author_name' => 'Author',
            'mosque_name' => 'Mosque',
            'price'       => 'Price',
            'status'      => 'Status',
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

        $cats = YNJ_Marketplace::get_categories();
        $cat_sel = sanitize_text_field( $_GET['category'] ?? '' );
        echo '<select name="category"><option value="">All Categories</option>';
        foreach ( $cats as $key => $c ) {
            printf( '<option value="%s"%s>%s %s</option>', $key, $cat_sel === $key ? ' selected' : '', $c['icon'], $c['label'] );
        }
        echo '</select>';

        submit_button( 'Filter', '', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items() {
        $status = sanitize_text_field( $_GET['post_status'] ?? 'any' );
        if ( $status === 'any' ) $status = [ 'publish', 'pending' ];

        $result = YNJ_Marketplace::get_listings( [
            'limit'     => 50,
            'page'      => $this->get_pagenum(),
            'mosque_id' => absint( $_GET['mosque_id'] ?? 0 ),
            'category'  => sanitize_text_field( $_GET['category'] ?? '' ),
            'search'    => sanitize_text_field( $_GET['s'] ?? '' ),
            'status'    => $status,
        ] );
        $this->items = $result['listings'];
        $this->set_pagination_args( [ 'total_items' => $result['total'], 'per_page' => 50 ] );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'title':
                $actions = [];
                if ( $item['status'] === 'pending' ) {
                    $actions['approve'] = '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=ynj-marketplace&ynj_listing_action=approve&listing_id=' . $item['id'] ), 'ynj_listing_mod' ) . '" style="color:#287e61;">Approve</a>';
                    $actions['reject'] = '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=ynj-marketplace&ynj_listing_action=reject&listing_id=' . $item['id'] ), 'ynj_listing_mod' ) . '" style="color:#dc2626;">Reject</a>';
                } else {
                    $actions['reject'] = '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=ynj-marketplace&ynj_listing_action=reject&listing_id=' . $item['id'] ), 'ynj_listing_mod' ) . '" style="color:#dc2626;">Remove</a>';
                }
                return '<strong>' . esc_html( $item['title'] ) . '</strong>' . $this->row_actions( $actions );
            case 'category': return $item['cat_icon'] . ' ' . esc_html( $item['cat_label'] );
            case 'author_name': return esc_html( $item['author_name'] ?: '(anonymous)' );
            case 'mosque_name': return esc_html( $item['mosque_name'] ?: '' );
            case 'price': return $item['price'] ? esc_html( $item['price'] ) : '<span style="color:#ccc;">—</span>';
            case 'status':
                $colors = [ 'publish' => '#287e61', 'pending' => '#f59e0b', 'trash' => '#dc2626' ];
                $labels = [ 'publish' => 'Live', 'pending' => 'Pending', 'trash' => 'Rejected' ];
                $c = $colors[ $item['status'] ] ?? '#666';
                $l = $labels[ $item['status'] ] ?? $item['status'];
                return '<span style="color:' . $c . ';font-weight:600;">' . $l . '</span>';
            case 'date': return esc_html( $item['date'] );
        }
    }
}
