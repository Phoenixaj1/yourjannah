<?php
/**
 * People Admin — WP Admin pages for managing community members.
 *
 * @package YNJ_People
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_People_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
    }

    public static function register_menus() {
        add_menu_page( 'People', 'People', 'manage_options', 'ynj-people', [ __CLASS__, 'page_members' ], 'dashicons-groups', 35 );
        add_submenu_page( 'ynj-people', 'Member Detail', 'Member Detail', 'manage_options', 'ynj-people-detail', [ __CLASS__, 'page_detail' ] );
        // Hide the detail page from menu
        remove_submenu_page( 'ynj-people', 'ynj-people-detail' );
    }

    public static function page_members() {
        $table = new YNJ_Members_List_Table();
        $table->prepare_items();
        $stats = YNJ_People::get_stats( absint( $_GET['mosque_id'] ?? 0 ) );

        echo '<div class="wrap">';
        echo '<h1>People <span class="title-count">' . number_format( $stats['total'] ) . ' members</span>';
        if ( $stats['new_week'] > 0 ) {
            echo ' <span style="color:#287e61;font-size:13px;font-weight:normal;">(+' . $stats['new_week'] . ' this week)</span>';
        }
        echo '</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="ynj-people">';
        $table->search_box( 'Search Members', 'member-search' );
        $table->display();
        echo '</form></div>';
    }

    public static function page_detail() {
        $user_id = absint( $_GET['user_id'] ?? 0 );
        $member = YNJ_People::get_member( $user_id );
        if ( ! $member ) {
            echo '<div class="wrap"><h1>Member Not Found</h1><p><a href="' . admin_url( 'admin.php?page=ynj-people' ) . '">&larr; Back to People</a></p></div>';
            return;
        }

        echo '<div class="wrap">';
        echo '<h1><a href="' . admin_url( 'admin.php?page=ynj-people' ) . '">&larr;</a> ' . esc_html( $member->display_name ?: 'Member #' . $member->id ) . '</h1>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Email</th><td>' . esc_html( $member->email ) . '</td></tr>';
        echo '<tr><th>Points</th><td>' . number_format( (int) $member->total_points ) . '</td></tr>';
        echo '<tr><th>Joined</th><td>' . esc_html( $member->created_at ) . '</td></tr>';
        echo '</tbody></table>';

        if ( ! empty( $member->subscriptions ) ) {
            echo '<h2>Mosque Subscriptions</h2>';
            echo '<table class="widefat striped"><thead><tr><th>Mosque</th><th>City</th><th>Status</th><th>Joined</th></tr></thead><tbody>';
            foreach ( $member->subscriptions as $sub ) {
                echo '<tr>';
                echo '<td><strong>' . esc_html( $sub->mosque_name ) . '</strong></td>';
                echo '<td>' . esc_html( $sub->mosque_city ?? '' ) . '</td>';
                echo '<td>' . esc_html( $sub->status ) . '</td>';
                echo '<td>' . esc_html( $sub->created_at ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}

class YNJ_Members_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'member', 'plural' => 'members', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'id'           => 'ID',
            'display_name' => 'Name',
            'email'        => 'Email',
            'mosques'      => 'Mosque(s)',
            'total_points' => 'Points',
            'created_at'   => 'Joined',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'           => [ 'id', true ],
            'display_name' => [ 'display_name', false ],
            'total_points' => [ 'total_points', false ],
            'created_at'   => [ 'created_at', false ],
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
        $page = $this->get_pagenum();
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $mosque = absint( $_GET['mosque_id'] ?? 0 );

        $result = YNJ_People::get_all_members( [
            'limit'     => $per_page,
            'offset'    => ( $page - 1 ) * $per_page,
            'search'    => $search,
            'mosque_id' => $mosque,
        ] );

        $this->items = $result['members'];
        $this->set_pagination_args( [
            'total_items' => $result['total'],
            'per_page'    => $per_page,
            'total_pages' => ceil( $result['total'] / $per_page ),
        ] );
        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'id': return (int) $item->id;
            case 'display_name':
                $name = esc_html( $item->display_name ?: '(no name)' );
                $url = admin_url( 'admin.php?page=ynj-people-detail&user_id=' . $item->id );
                return '<a href="' . esc_url( $url ) . '"><strong>' . $name . '</strong></a>';
            case 'email': return esc_html( $item->email );
            case 'mosques': return esc_html( $item->mosques ?: '(none)' );
            case 'total_points': return number_format( (int) $item->total_points );
            case 'created_at': return esc_html( $item->created_at );
        }
    }
}
