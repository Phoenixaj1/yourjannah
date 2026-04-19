<?php
/**
 * Patrons Admin — analytics + patron list.
 * @package YNJ_Patrons
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Patrons_Admin {

    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Patrons', 'Patrons', 'manage_options', 'ynj-patrons', [ 'YNJ_Patrons_Admin', 'page_analytics' ], 'dashicons-awards', 33 );
            add_submenu_page( 'ynj-patrons', 'All Patrons', 'All Patrons', 'manage_options', 'ynj-patrons-list', [ 'YNJ_Patrons_Admin', 'page_list' ] );
            add_submenu_page( 'ynj-patrons', 'Cross-Mosque', 'Cross-Mosque', 'manage_options', 'ynj-patrons-compare', [ 'YNJ_Patrons_Admin', 'page_compare' ] );
        } );
    }

    public static function page_analytics() {
        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY name" );

        echo '<div class="wrap"><h1>Patron Analytics</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="ynj-patrons">';
        echo '<select name="mosque_id" onchange="this.form.submit()"><option value="">Select a Mosque</option>';
        foreach ( $mosques as $m ) {
            printf( '<option value="%d"%s>%s</option>', $m->id, $mosque_id === (int) $m->id ? ' selected' : '', esc_html( $m->name ) );
        }
        echo '</select></form>';

        if ( ! $mosque_id ) { echo '<p>Select a mosque to view analytics.</p></div>'; return; }

        $a = YNJ_Patrons_Data::get_analytics( $mosque_id );

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">';
        self::stat_card( $a['active_patrons'], 'Active Patrons', '#287e61' );
        self::stat_card( '&pound;' . $a['mrr_formatted'], 'Monthly Revenue', '#f59e0b' );
        self::stat_card( $a['penetration_pct'] . '%', 'of Congregation', '#7c3aed' );
        self::stat_card( '&pound;' . $a['avg_formatted'], 'Average / Patron', '#00ADEF' );
        echo '</div>';

        if ( ! empty( $a['tiers'] ) ) {
            echo '<table class="widefat striped"><thead><tr><th>Tier</th><th>Patrons</th><th>Revenue/mo</th></tr></thead><tbody>';
            foreach ( $a['tiers'] as $t ) {
                echo '<tr><td><strong>' . esc_html( ucfirst( $t->tier ?: 'Standard' ) ) . '</strong></td>';
                echo '<td>' . (int) $t->count . '</td>';
                echo '<td>&pound;' . number_format( (int) $t->revenue_pence / 100, 2 ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    public static function page_list() {
        $table = new YNJ_Patrons_List();
        $table->prepare_items();
        echo '<div class="wrap"><h1>All Patrons</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="ynj-patrons-list">';
        $table->search_box( 'Search', 's' );
        $table->display();
        echo '</form></div>';
    }

    public static function page_compare() {
        $data = YNJ_Patrons_Data::get_cross_mosque_analytics();
        echo '<div class="wrap"><h1>Cross-Mosque Comparison</h1>';
        echo '<table class="widefat striped"><thead><tr><th>Mosque</th><th>City</th><th>Members</th><th>Patrons</th><th>Penetration</th><th>MRR</th></tr></thead><tbody>';
        foreach ( $data as $r ) {
            echo '<tr><td><strong>' . esc_html( $r->name ) . '</strong></td>';
            echo '<td>' . esc_html( $r->city ?: '' ) . '</td>';
            echo '<td>' . (int) $r->member_count . '</td>';
            echo '<td>' . (int) $r->patron_count . '</td>';
            echo '<td>' . (float) $r->penetration_pct . '%</td>';
            echo '<td style="font-weight:700;">&pound;' . number_format( (int) $r->mrr_pence / 100, 2 ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function stat_card( $value, $label, $color ) {
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;">';
        echo '<div style="font-size:28px;font-weight:900;color:' . $color . ';">' . $value . '</div>';
        echo '<div style="font-size:12px;color:#666;">' . esc_html( $label ) . '</div></div>';
    }
}

class YNJ_Patrons_List extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'patron', 'plural' => 'patrons', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'user_name'   => 'Name',
            'user_email'  => 'Email',
            'mosque_name' => 'Mosque',
            'tier'        => 'Tier',
            'amount'      => 'Amount/mo',
            'status'      => 'Status',
            'created_at'  => 'Started',
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
        $result = YNJ_Patrons_Data::get_all_patrons( [
            'limit'     => 50,
            'offset'    => ( $this->get_pagenum() - 1 ) * 50,
            'mosque_id' => absint( $_GET['mosque_id'] ?? 0 ),
            'search'    => sanitize_text_field( $_GET['s'] ?? '' ),
        ] );
        $this->items = $result['patrons'];
        $this->set_pagination_args( [ 'total_items' => $result['total'], 'per_page' => 50 ] );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'user_name':  return esc_html( $item->user_name ?: '(no name)' );
            case 'user_email': return esc_html( $item->user_email ?: '' );
            case 'mosque_name': return esc_html( $item->mosque_name ?: '' );
            case 'tier':       return '<strong>' . esc_html( ucfirst( $item->tier ?: 'supporter' ) ) . '</strong>';
            case 'amount':     return '&pound;' . number_format( (int) $item->amount_pence / 100, 2 );
            case 'status':
                $c = $item->status === 'active' ? '#287e61' : '#dc2626';
                return '<span style="color:' . $c . ';font-weight:600;">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'created_at': return esc_html( $item->created_at ?? '' );
        }
    }
}
