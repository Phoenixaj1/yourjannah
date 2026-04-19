<?php
/**
 * Patrons Admin — analytics dashboard + patron list.
 *
 * @package YNJ_Patrons
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Patrons_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
    }

    public static function register_menus() {
        add_menu_page( 'Patrons', 'Patrons', 'manage_options', 'ynj-patrons', [ __CLASS__, 'page_analytics' ], 'dashicons-awards', 33 );
        add_submenu_page( 'ynj-patrons', 'Analytics', 'Analytics', 'manage_options', 'ynj-patrons', [ __CLASS__, 'page_analytics' ] );
        add_submenu_page( 'ynj-patrons', 'All Patrons', 'All Patrons', 'manage_options', 'ynj-patrons-list', [ __CLASS__, 'page_list' ] );
        add_submenu_page( 'ynj-patrons', 'Cross-Mosque', 'Cross-Mosque', 'manage_options', 'ynj-patrons-compare', [ __CLASS__, 'page_compare' ] );
    }

    public static function page_analytics() {
        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );

        echo '<div class="wrap"><h1>Patron Analytics</h1>';

        // Mosque selector
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY name" );
        echo '<form method="get"><input type="hidden" name="page" value="ynj-patrons">';
        echo '<select name="mosque_id" onchange="this.form.submit()">';
        echo '<option value="">Select a Mosque</option>';
        foreach ( $mosques as $m ) {
            printf( '<option value="%d"%s>%s</option>', $m->id, $mosque_id === (int) $m->id ? ' selected' : '', esc_html( $m->name ) );
        }
        echo '</select></form>';

        if ( ! $mosque_id ) {
            echo '<p>Select a mosque to view patron analytics.</p></div>';
            return;
        }

        $a = YNJ_Patrons::get_analytics( $mosque_id );

        // Stats cards
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">';

        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;">';
        echo '<div style="font-size:32px;font-weight:900;color:#287e61;">' . (int) $a['active_patrons'] . '</div>';
        echo '<div style="font-size:12px;color:#666;">Active Patrons</div></div>';

        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;">';
        echo '<div style="font-size:32px;font-weight:900;color:#f59e0b;">&pound;' . esc_html( $a['mrr_formatted'] ) . '</div>';
        echo '<div style="font-size:12px;color:#666;">Monthly Revenue</div></div>';

        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;">';
        echo '<div style="font-size:32px;font-weight:900;color:#7c3aed;">' . $a['penetration_pct'] . '%</div>';
        echo '<div style="font-size:12px;color:#666;">of Congregation</div>';
        echo '<div style="font-size:11px;color:#999;">' . $a['active_patrons'] . ' of ' . $a['congregation'] . ' members</div></div>';

        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;">';
        echo '<div style="font-size:32px;font-weight:900;color:#00ADEF;">&pound;' . esc_html( $a['avg_formatted'] ) . '</div>';
        echo '<div style="font-size:12px;color:#666;">Average / Patron</div></div>';

        echo '</div>';

        // Tier breakdown
        if ( ! empty( $a['tiers'] ) ) {
            echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:20px;">';
            echo '<h2 style="margin-top:0;">Tier Breakdown</h2>';
            echo '<table class="widefat striped"><thead><tr><th>Tier</th><th>Patrons</th><th>Revenue/mo</th><th>Share</th></tr></thead><tbody>';
            foreach ( $a['tiers'] as $t ) {
                $pct = $a['mrr_pence'] > 0 ? round( (int) $t->revenue_pence / $a['mrr_pence'] * 100, 1 ) : 0;
                $bar_color = '#287e61';
                if ( strtolower( $t->tier ) === 'gold' ) $bar_color = '#d4a017';
                elseif ( strtolower( $t->tier ) === 'silver' ) $bar_color = '#8e99a4';
                elseif ( strtolower( $t->tier ) === 'bronze' ) $bar_color = '#cd7f32';

                echo '<tr>';
                echo '<td><strong>' . esc_html( ucfirst( $t->tier ?: 'Standard' ) ) . '</strong></td>';
                echo '<td>' . (int) $t->count . '</td>';
                echo '<td>&pound;' . number_format( (int) $t->revenue_pence / 100, 2 ) . '</td>';
                echo '<td><div style="display:flex;align-items:center;gap:8px;">';
                echo '<div style="flex:1;height:12px;background:#f3f4f6;border-radius:6px;overflow:hidden;">';
                echo '<div style="width:' . $pct . '%;height:100%;background:' . $bar_color . ';border-radius:6px;"></div></div>';
                echo '<span style="font-weight:700;font-size:12px;">' . $pct . '%</span></div></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '</div>';
    }

    public static function page_list() {
        $table = new YNJ_Patrons_List_Table();
        $table->prepare_items();

        echo '<div class="wrap"><h1>All Patrons</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="ynj-patrons-list">';
        $table->search_box( 'Search', 'patron-search' );
        $table->display();
        echo '</form></div>';
    }

    public static function page_compare() {
        $data = YNJ_Patrons::get_cross_mosque_analytics();

        echo '<div class="wrap"><h1>Cross-Mosque Patron Comparison</h1>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Mosque</th><th>City</th><th>Members</th><th>Patrons</th><th>Penetration</th><th>MRR</th>';
        echo '</tr></thead><tbody>';

        foreach ( $data as $row ) {
            $pct = (float) $row->penetration_pct;
            $bar_color = $pct >= 10 ? '#287e61' : ( $pct >= 5 ? '#f59e0b' : '#dc2626' );

            echo '<tr>';
            echo '<td><strong>' . esc_html( $row->name ) . '</strong></td>';
            echo '<td>' . esc_html( $row->city ?: '' ) . '</td>';
            echo '<td>' . (int) $row->member_count . '</td>';
            echo '<td>' . (int) $row->patron_count . '</td>';
            echo '<td><div style="display:flex;align-items:center;gap:6px;">';
            echo '<div style="width:80px;height:10px;background:#f3f4f6;border-radius:5px;overflow:hidden;">';
            echo '<div style="width:' . min( 100, $pct * 2 ) . '%;height:100%;background:' . $bar_color . ';border-radius:5px;"></div></div>';
            echo '<span style="font-weight:700;font-size:12px;">' . $pct . '%</span></div></td>';
            echo '<td style="font-weight:700;">&pound;' . number_format( (int) $row->mrr_pence / 100, 2 ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}

class YNJ_Patrons_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'patron', 'plural' => 'patrons', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'display_name' => 'Name',
            'email'        => 'Email',
            'mosque_name'  => 'Mosque',
            'tier'         => 'Tier',
            'amount'       => 'Amount/mo',
            'status'       => 'Status',
            'created_at'   => 'Started',
        ];
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY name" );
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
        $per_page = 50;
        $result = YNJ_Patrons::get_all_patrons( [
            'limit'     => $per_page,
            'offset'    => ( $this->get_pagenum() - 1 ) * $per_page,
            'mosque_id' => absint( $_GET['mosque_id'] ?? 0 ),
            'search'    => sanitize_text_field( $_GET['s'] ?? '' ),
        ] );

        $this->items = $result['patrons'];
        $this->set_pagination_args( [
            'total_items' => $result['total'],
            'per_page'    => $per_page,
        ] );
        $this->_column_headers = [ $this->get_columns(), [], [] ];
    }

    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'display_name': return esc_html( $item->display_name ?: '(no name)' );
            case 'email': return esc_html( $item->email ?: '' );
            case 'mosque_name': return esc_html( $item->mosque_name ?: '' );
            case 'tier':
                $colors = [ 'gold' => '#d4a017', 'silver' => '#8e99a4', 'bronze' => '#cd7f32' ];
                $color = $colors[ strtolower( $item->tier ?? '' ) ] ?? '#666';
                return '<span style="font-weight:700;color:' . $color . ';">' . esc_html( ucfirst( $item->tier ?: 'Standard' ) ) . '</span>';
            case 'amount': return '&pound;' . number_format( (int) $item->amount_pence / 100, 2 );
            case 'status':
                $c = $item->status === 'active' ? '#287e61' : '#dc2626';
                return '<span style="color:' . $c . ';font-weight:600;">' . esc_html( ucfirst( $item->status ) ) . '</span>';
            case 'created_at': return esc_html( $item->created_at ?? '' );
        }
    }
}
