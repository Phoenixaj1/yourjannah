<?php
/**
 * YourJannah Mosques — WP Admin pages.
 *
 * Registers top-level "Mosques" menu with sub-pages for listing, editing,
 * prayer times, and view stats.  Uses WP_List_Table for the main list view.
 *
 * @package YNJ_Mosques
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Mosques_Admin {

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
            'Mosques',
            'Mosques',
            'manage_options',
            'ynj-mosques',
            [ __CLASS__, 'page_mosques' ],
            'dashicons-building',
            28
        );

        add_submenu_page(
            'ynj-mosques',
            'All Mosques',
            'All Mosques',
            'manage_options',
            'ynj-mosques',
            [ __CLASS__, 'page_mosques' ]
        );

        add_submenu_page(
            'ynj-mosques',
            'Prayer Times',
            'Prayer Times',
            'manage_options',
            'ynj-mosque-prayer-times',
            [ __CLASS__, 'page_prayer_times' ]
        );

        add_submenu_page(
            'ynj-mosques',
            'View Stats',
            'View Stats',
            'manage_options',
            'ynj-mosque-stats',
            [ __CLASS__, 'page_stats' ]
        );

        /* Hidden pages (no menu entry) — edit form. */
        add_submenu_page(
            null,
            'Edit Mosque',
            'Edit Mosque',
            'manage_options',
            'ynj-mosque-edit',
            [ __CLASS__, 'page_mosque_edit' ]
        );
    }

    /* ==============================================================
     *  ACTION HANDLER (saves)
     * ============================================================ */

    public static function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        /* ---- Mosque save ---- */
        if ( isset( $_POST['ynj_mosque_save'] ) ) {
            check_admin_referer( 'ynj_mosque_save', 'ynj_mosque_nonce' );
            self::save_mosque();
            return;
        }

        /* ---- Prayer times save ---- */
        if ( isset( $_POST['ynj_prayer_save'] ) ) {
            check_admin_referer( 'ynj_prayer_save', 'ynj_prayer_nonce' );
            self::save_prayer_times();
            return;
        }
    }

    /* ==============================================================
     *  MOSQUE SAVE
     * ============================================================ */

    private static function save_mosque() {
        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        $id    = absint( $_POST['mosque_id'] ?? 0 );

        $data = [
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'slug'        => sanitize_title( $_POST['slug'] ?? '' ),
            'city'        => sanitize_text_field( $_POST['city'] ?? '' ),
            'postcode'    => sanitize_text_field( $_POST['postcode'] ?? '' ),
            'address'     => sanitize_text_field( $_POST['address'] ?? '' ),
            'latitude'    => ! empty( $_POST['latitude'] )  ? (float) $_POST['latitude']  : null,
            'longitude'   => ! empty( $_POST['longitude'] ) ? (float) $_POST['longitude'] : null,
            'admin_email' => sanitize_email( $_POST['admin_email'] ?? '' ),
            'phone'       => sanitize_text_field( $_POST['phone'] ?? '' ),
            'website'     => esc_url_raw( $_POST['website'] ?? '' ),
            'description' => wp_kses_post( $_POST['description'] ?? '' ),
            'status'      => sanitize_text_field( $_POST['status'] ?? 'active' ),
            'theme'       => sanitize_text_field( $_POST['theme'] ?? 'minimal' ),
        ];

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
            $msg = 'updated';
        } else {
            $wpdb->insert( $table, $data );
            $id  = (int) $wpdb->insert_id;
            $msg = 'created';
        }

        wp_safe_redirect( admin_url( 'admin.php?page=ynj-mosques&msg=' . $msg ) );
        exit;
    }

    /* ==============================================================
     *  PRAYER TIMES SAVE
     * ============================================================ */

    private static function save_prayer_times() {
        $mosque_id = absint( $_POST['mosque_id'] ?? 0 );
        $date      = sanitize_text_field( $_POST['prayer_date'] ?? '' );

        if ( ! $mosque_id || ! $date ) {
            wp_safe_redirect( admin_url( 'admin.php?page=ynj-mosque-prayer-times&msg=error' ) );
            exit;
        }

        $times = [];
        $allowed = [ 'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha',
                     'fajr_jamat', 'dhuhr_jamat', 'asr_jamat', 'maghrib_jamat', 'isha_jamat' ];

        foreach ( $allowed as $key ) {
            if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
                $times[ $key ] = sanitize_text_field( $_POST[ $key ] );
            }
        }

        YNJ_Mosques::update_prayer_times( $mosque_id, $date, $times );

        wp_safe_redirect( admin_url( 'admin.php?page=ynj-mosque-prayer-times&mosque_id=' . $mosque_id . '&prayer_date=' . $date . '&msg=saved' ) );
        exit;
    }

    /* ==============================================================
     *  PAGE: MOSQUES LIST
     * ============================================================ */

    public static function page_mosques() {
        $table = new YNJ_Mosques_List_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Mosques</h1>';
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ynj-mosque-edit' ) ) . '" class="page-title-action">Add New</a>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="ynj-mosques">';
        $table->search_box( 'Search Mosques', 'ynj-mosque-search' );
        $table->display();
        echo '</form>';
        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: EDIT MOSQUE FORM
     * ============================================================ */

    public static function page_mosque_edit() {
        global $wpdb;

        $id     = absint( $_GET['id'] ?? 0 );
        $mosque = null;

        if ( $id ) {
            $mosque = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $id
            ) );
            if ( ! $mosque ) {
                wp_die( 'Mosque not found.' );
            }
        }

        $v = function( $field, $default = '' ) use ( $mosque ) {
            return $mosque ? esc_attr( $mosque->$field ?? $default ) : esc_attr( $default );
        };

        echo '<div class="wrap">';
        echo '<h1>' . ( $id ? 'Edit Mosque' : 'Add New Mosque' ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        wp_nonce_field( 'ynj_mosque_save', 'ynj_mosque_nonce' );
        echo '<input type="hidden" name="mosque_id" value="' . $id . '">';

        echo '<table class="form-table">';

        // Name
        echo '<tr><th><label for="name">Name</label></th><td>';
        echo '<input type="text" name="name" id="name" class="regular-text" required value="' . $v( 'name' ) . '"></td></tr>';

        // Slug
        echo '<tr><th><label for="slug">Slug</label></th><td>';
        echo '<input type="text" name="slug" id="slug" class="regular-text" required value="' . $v( 'slug' ) . '">';
        echo '<p class="description">URL-safe identifier. Auto-generated from name if left blank on create.</p></td></tr>';

        // City
        echo '<tr><th><label for="city">City</label></th><td>';
        echo '<input type="text" name="city" id="city" class="regular-text" value="' . $v( 'city' ) . '"></td></tr>';

        // Postcode
        echo '<tr><th><label for="postcode">Postcode</label></th><td>';
        echo '<input type="text" name="postcode" id="postcode" class="regular-text" value="' . $v( 'postcode' ) . '"></td></tr>';

        // Address
        echo '<tr><th><label for="address">Address</label></th><td>';
        echo '<input type="text" name="address" id="address" class="large-text" value="' . $v( 'address' ) . '"></td></tr>';

        // Latitude
        echo '<tr><th><label for="latitude">Latitude</label></th><td>';
        echo '<input type="text" name="latitude" id="latitude" class="regular-text" value="' . $v( 'latitude' ) . '">';
        echo '<p class="description">Decimal format, e.g. 51.5074000</p></td></tr>';

        // Longitude
        echo '<tr><th><label for="longitude">Longitude</label></th><td>';
        echo '<input type="text" name="longitude" id="longitude" class="regular-text" value="' . $v( 'longitude' ) . '">';
        echo '<p class="description">Decimal format, e.g. -0.1278000</p></td></tr>';

        // Admin Email
        echo '<tr><th><label for="admin_email">Admin Email</label></th><td>';
        echo '<input type="email" name="admin_email" id="admin_email" class="regular-text" value="' . $v( 'admin_email' ) . '"></td></tr>';

        // Phone
        echo '<tr><th><label for="phone">Phone</label></th><td>';
        echo '<input type="text" name="phone" id="phone" class="regular-text" value="' . $v( 'phone' ) . '"></td></tr>';

        // Website
        echo '<tr><th><label for="website">Website</label></th><td>';
        echo '<input type="url" name="website" id="website" class="regular-text" value="' . $v( 'website' ) . '"></td></tr>';

        // Description
        echo '<tr><th><label for="description">Description</label></th><td>';
        echo '<textarea name="description" id="description" rows="5" class="large-text">' . ( $mosque ? esc_textarea( $mosque->description ) : '' ) . '</textarea></td></tr>';

        // Theme
        echo '<tr><th><label for="theme">Theme</label></th><td>';
        echo '<select name="theme" id="theme">';
        $themes = [ 'minimal' => 'Minimal', 'classic' => 'Classic', 'modern' => 'Modern', 'dark' => 'Dark' ];
        foreach ( $themes as $val => $label ) {
            $sel = ( $mosque && ( $mosque->theme ?? 'minimal' ) === $val ) ? ' selected' : ( ! $mosque && $val === 'minimal' ? ' selected' : '' );
            echo '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        // Status
        echo '<tr><th><label for="status">Status</label></th><td>';
        echo '<select name="status" id="status">';
        $statuses = [ 'active' => 'Active', 'unclaimed' => 'Unclaimed', 'claimed' => 'Claimed', 'inactive' => 'Inactive' ];
        foreach ( $statuses as $val => $label ) {
            $sel = ( $mosque && $mosque->status === $val ) ? ' selected' : ( ! $mosque && $val === 'active' ? ' selected' : '' );
            echo '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        // Members + Views (read-only, edit only)
        if ( $id && $mosque ) {
            $members = YNJ_Mosques::get_member_count( $id );
            $views   = YNJ_Mosques::get_view_count( $id, 7 );

            echo '<tr><th>Members</th><td><strong>' . number_format( $members ) . '</strong></td></tr>';
            echo '<tr><th>Views (7 days)</th><td><strong>' . number_format( $views ) . '</strong>';
            echo ' &mdash; <a href="' . esc_url( admin_url( 'admin.php?page=ynj-mosque-stats&mosque_id=' . $id ) ) . '">View full stats</a></td></tr>';
            echo '<tr><th>Created</th><td>' . esc_html( $mosque->created_at ) . '</td></tr>';
        }

        echo '</table>';

        submit_button( $id ? 'Update Mosque' : 'Create Mosque', 'primary', 'ynj_mosque_save' );

        echo '</form></div>';
    }

    /* ==============================================================
     *  PAGE: PRAYER TIMES
     * ============================================================ */

    public static function page_prayer_times() {
        global $wpdb;

        $mosques   = $wpdb->get_results(
            "SELECT id, name, city FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
        );

        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );
        $date      = sanitize_text_field( $_GET['prayer_date'] ?? date( 'Y-m-d' ) );
        $times     = null;

        if ( $mosque_id ) {
            $times = YNJ_Mosques::get_prayer_times( $mosque_id, $date );
        }

        echo '<div class="wrap">';
        echo '<h1>Prayer Times</h1>';
        self::admin_notices();
        echo '<hr class="wp-header-end">';

        /* --- Mosque + Date selector --- */
        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="ynj-mosque-prayer-times">';

        echo '<label for="mosque_id"><strong>Mosque:</strong></label> ';
        echo '<select name="mosque_id" id="mosque_id" style="min-width:300px;" onchange="this.form.submit()">';
        echo '<option value="">-- Select Mosque --</option>';
        foreach ( $mosques as $m ) {
            $sel = ( (int) $m->id === $mosque_id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $m->id ) . '"' . $sel . '>' . esc_html( $m->name ) . ' (' . esc_html( $m->city ) . ')</option>';
        }
        echo '</select>';

        echo ' &nbsp; <label for="prayer_date"><strong>Date:</strong></label> ';
        echo '<input type="date" name="prayer_date" id="prayer_date" value="' . esc_attr( $date ) . '" onchange="this.form.submit()">';

        echo ' &nbsp; <button type="submit" class="button">Load</button>';
        echo '</form>';

        /* --- Prayer times edit form --- */
        if ( $mosque_id ) {
            $mosque = YNJ_Mosques::get_by_id( $mosque_id );
            if ( ! $mosque ) {
                echo '<div class="notice notice-error"><p>Mosque not found.</p></div>';
                echo '</div>';
                return;
            }

            echo '<h2>' . esc_html( $mosque->name ) . ' &mdash; ' . esc_html( $date ) . '</h2>';

            $tv = function( $field ) use ( $times ) {
                return ( $times && ! empty( $times->$field ) ) ? esc_attr( substr( $times->$field, 0, 5 ) ) : '';
            };

            echo '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
            wp_nonce_field( 'ynj_prayer_save', 'ynj_prayer_nonce' );
            echo '<input type="hidden" name="mosque_id" value="' . $mosque_id . '">';
            echo '<input type="hidden" name="prayer_date" value="' . esc_attr( $date ) . '">';

            echo '<table class="form-table">';
            echo '<tr><th></th><td><strong>Adhan Time</strong></td><td><strong>Jama\'at Time</strong></td></tr>';

            $prayers = [
                'fajr'    => 'Fajr',
                'sunrise' => 'Sunrise',
                'dhuhr'   => 'Dhuhr',
                'asr'     => 'Asr',
                'maghrib' => 'Maghrib',
                'isha'    => 'Isha',
            ];

            foreach ( $prayers as $key => $label ) {
                echo '<tr><th><label>' . $label . '</label></th>';
                echo '<td><input type="time" name="' . $key . '" value="' . $tv( $key ) . '"></td>';
                if ( $key !== 'sunrise' ) {
                    echo '<td><input type="time" name="' . $key . '_jamat" value="' . $tv( $key . '_jamat' ) . '"></td>';
                } else {
                    echo '<td>&mdash;</td>';
                }
                echo '</tr>';
            }

            echo '</table>';

            submit_button( 'Save Prayer Times', 'primary', 'ynj_prayer_save' );
            echo '</form>';
        } else {
            echo '<p>Select a mosque above to view and edit prayer times.</p>';
        }

        echo '</div>';
    }

    /* ==============================================================
     *  PAGE: VIEW STATS
     * ============================================================ */

    public static function page_stats() {
        global $wpdb;

        $mosques = $wpdb->get_results(
            "SELECT id, name, city FROM " . YNJ_DB::table( 'mosques' ) . " ORDER BY name ASC"
        );

        $mosque_id = absint( $_GET['mosque_id'] ?? 0 );

        echo '<div class="wrap">';
        echo '<h1>Mosque View Stats</h1>';
        echo '<hr class="wp-header-end">';

        /* --- Mosque selector --- */
        echo '<form method="get" style="margin-bottom:20px;">';
        echo '<input type="hidden" name="page" value="ynj-mosque-stats">';

        echo '<label for="mosque_id"><strong>Mosque:</strong></label> ';
        echo '<select name="mosque_id" id="mosque_id" style="min-width:300px;" onchange="this.form.submit()">';
        echo '<option value="">-- Select Mosque --</option>';
        foreach ( $mosques as $m ) {
            $sel = ( (int) $m->id === $mosque_id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $m->id ) . '"' . $sel . '>' . esc_html( $m->name ) . ' (' . esc_html( $m->city ) . ')</option>';
        }
        echo '</select>';
        echo ' &nbsp; <button type="submit" class="button">Load</button>';
        echo '</form>';

        /* --- Stats table --- */
        if ( $mosque_id ) {
            $mosque = YNJ_Mosques::get_by_id( $mosque_id );
            if ( ! $mosque ) {
                echo '<div class="notice notice-error"><p>Mosque not found.</p></div>';
                echo '</div>';
                return;
            }

            $members = YNJ_Mosques::get_member_count( $mosque_id );
            $views_7 = YNJ_Mosques::get_view_count( $mosque_id, 7 );
            $views_30 = YNJ_Mosques::get_view_count( $mosque_id, 30 );

            echo '<h2>' . esc_html( $mosque->name ) . '</h2>';

            echo '<table class="widefat" style="max-width:400px; margin-bottom:20px;">';
            echo '<tbody>';
            echo '<tr><td><strong>Status</strong></td><td>' . esc_html( ucfirst( $mosque->status ) ) . '</td></tr>';
            echo '<tr><td><strong>Members</strong></td><td>' . number_format( $members ) . '</td></tr>';
            echo '<tr><td><strong>Views (7 days)</strong></td><td>' . number_format( $views_7 ) . '</td></tr>';
            echo '<tr><td><strong>Views (30 days)</strong></td><td>' . number_format( $views_30 ) . '</td></tr>';
            echo '</tbody></table>';

            /* --- Daily breakdown for last 30 days --- */
            $t_views = YNJ_DB::table( 'mosque_views' );
            $since   = date( 'Y-m-d', strtotime( '-30 days' ) );

            $daily = $wpdb->get_results( $wpdb->prepare(
                "SELECT view_date, SUM(view_count) AS views
                 FROM $t_views WHERE mosque_id = %d AND view_date >= %s
                 GROUP BY view_date ORDER BY view_date DESC",
                $mosque_id, $since
            ) );

            echo '<h3>Daily Views (Last 30 Days)</h3>';

            if ( empty( $daily ) ) {
                echo '<p>No view data recorded for this period.</p>';
            } else {
                echo '<table class="widefat striped" style="max-width:400px;">';
                echo '<thead><tr><th>Date</th><th style="text-align:right;">Views</th></tr></thead>';
                echo '<tbody>';
                foreach ( $daily as $row ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( $row->view_date ) . '</td>';
                    echo '<td style="text-align:right;">' . number_format( (int) $row->views ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        } else {
            echo '<p>Select a mosque above to view stats.</p>';
        }

        echo '</div>';
    }

    /* ==============================================================
     *  ADMIN NOTICES
     * ============================================================ */

    private static function admin_notices() {
        $msg = sanitize_text_field( $_GET['msg'] ?? '' );
        if ( ! $msg ) return;

        $map = [
            'created' => [ 'success', 'Mosque created.' ],
            'updated' => [ 'success', 'Mosque updated.' ],
            'deleted' => [ 'success', 'Mosque deleted.' ],
            'saved'   => [ 'success', 'Prayer times saved.' ],
            'error'   => [ 'error',   'Something went wrong.' ],
        ];

        if ( isset( $map[ $msg ] ) ) {
            echo '<div class="notice notice-' . $map[ $msg ][0] . ' is-dismissible"><p>' . esc_html( $map[ $msg ][1] ) . '</p></div>';
        }
    }

    /* ==============================================================
     *  HELPER: Get mosque name by ID (for list tables)
     * ============================================================ */

    public static function get_mosque_name( $mosque_id ) {
        $mosque = YNJ_Mosques::get_by_id( $mosque_id );
        return $mosque ? $mosque->name : '(unknown)';
    }
}


/* ==================================================================
 *  WP_LIST_TABLE: Mosques
 * ================================================================ */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Mosques_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'mosque',
            'plural'   => 'mosques',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'id'          => 'ID',
            'name'        => 'Name',
            'city'        => 'City',
            'postcode'    => 'Postcode',
            'status'      => 'Status',
            'members'     => 'Members',
            'views_7d'    => 'Weekly Views',
            'admin_email' => 'Admin Email',
            'created_at'  => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'name'       => [ 'name', false ],
            'city'       => [ 'city', false ],
            'status'     => [ 'status', false ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    protected function get_views() {
        global $wpdb;
        $t       = YNJ_DB::table( 'mosques' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );
        $base    = admin_url( 'admin.php?page=ynj-mosques' );

        $total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
        $active    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'active'" );
        $unclaimed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'unclaimed'" );
        $claimed   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'claimed'" );
        $inactive  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'inactive'" );

        $views = [];
        $views['all']       = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . $total . ')</span></a>';
        $views['active']    = '<a href="' . esc_url( $base . '&status=active' ) . '"' . ( $current === 'active' ? ' class="current"' : '' ) . '>Active <span class="count">(' . $active . ')</span></a>';
        $views['unclaimed'] = '<a href="' . esc_url( $base . '&status=unclaimed' ) . '"' . ( $current === 'unclaimed' ? ' class="current"' : '' ) . '>Unclaimed <span class="count">(' . $unclaimed . ')</span></a>';
        $views['claimed']   = '<a href="' . esc_url( $base . '&status=claimed' ) . '"' . ( $current === 'claimed' ? ' class="current"' : '' ) . '>Claimed <span class="count">(' . $claimed . ')</span></a>';

        if ( $inactive ) {
            $views['inactive'] = '<a href="' . esc_url( $base . '&status=inactive' ) . '"' . ( $current === 'inactive' ? ' class="current"' : '' ) . '>Inactive <span class="count">(' . $inactive . ')</span></a>';
        }

        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $t = YNJ_DB::table( 'mosques' );

        $per_page = 25;
        $page     = $this->get_pagenum();
        $offset   = ( $page - 1 ) * $per_page;

        $where = [];
        $args  = [];

        /* Status filter */
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        if ( $status && in_array( $status, [ 'active', 'unclaimed', 'claimed', 'inactive' ], true ) ) {
            $where[] = 'status = %s';
            $args[]  = $status;
        }

        /* Search */
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        if ( $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '(name LIKE %s OR city LIKE %s OR postcode LIKE %s OR admin_email LIKE %s)';
            $args[]  = $like;
            $args[]  = $like;
            $args[]  = $like;
            $args[]  = $like;
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        /* Sorting */
        $allowed_orderby = [ 'id', 'name', 'city', 'status', 'created_at' ];
        $orderby = sanitize_text_field( $_GET['orderby'] ?? 'id' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'id';
        }
        $order = ( strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';

        /* Count */
        $count_sql = "SELECT COUNT(*) FROM $t $where_sql";
        $total     = $args ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) ) : (int) $wpdb->get_var( $count_sql );

        /* Fetch */
        $sql = "SELECT * FROM $t $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $all_args   = array_merge( $args, [ $per_page, $offset ] );
        $this->items = $wpdb->get_results( $wpdb->prepare( $sql, ...$all_args ) );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );
    }

    public function column_default( $item, $col ) {
        switch ( $col ) {
            case 'id':
                return (int) $item->id;

            case 'name':
                $edit_url = admin_url( 'admin.php?page=ynj-mosque-edit&id=' . (int) $item->id );
                $actions  = [
                    'edit' => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
                    'view' => '<a href="' . esc_url( home_url( '/mosque/' . $item->slug . '/' ) ) . '" target="_blank">View on Site</a>',
                ];
                return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->name ) . '</a></strong>'
                     . $this->row_actions( $actions );

            case 'city':
                return esc_html( $item->city );

            case 'postcode':
                return esc_html( $item->postcode );

            case 'status':
                $colors = [
                    'active'    => '#00a32a',
                    'unclaimed' => '#dba617',
                    'claimed'   => '#2271b1',
                    'inactive'  => '#a7aaad',
                ];
                $color = $colors[ $item->status ] ?? '#72777c';
                return '<span style="color:' . $color . '; font-weight:600;">' . esc_html( ucfirst( $item->status ) ) . '</span>';

            case 'members':
                return number_format( YNJ_Mosques::get_member_count( (int) $item->id ) );

            case 'views_7d':
                return number_format( YNJ_Mosques::get_view_count( (int) $item->id, 7 ) );

            case 'admin_email':
                return esc_html( $item->admin_email );

            case 'created_at':
                return esc_html( date( 'j M Y', strtotime( $item->created_at ) ) );

            default:
                return '';
        }
    }

    public function no_items() {
        echo 'No mosques found.';
    }
}
