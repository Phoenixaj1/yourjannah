<?php
/**
 * YourJannah Donations — WP Admin Pages
 *
 * Platform-level admin views for donations, campaigns, patrons, and fund types
 * across all mosques. Uses WP_List_Table for consistent WordPress admin UX.
 *
 * @package YNJ_Donations
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// ────────────────────────────────────────────────────────────────────
// MENU REGISTRATION
// ────────────────────────────────────────────────────────────────────

class YNJ_Donations_Admin {

    /**
     * Boot admin hooks.
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    /**
     * Register top-level Donations menu and sub-pages.
     */
    public static function register_menu() {
        add_menu_page(
            'Donations',
            'Donations',
            'manage_options',
            'ynj-donations',
            [ __CLASS__, 'page_donations' ],
            'dashicons-money-alt',
            31
        );

        add_submenu_page( 'ynj-donations', 'All Donations', 'All Donations', 'manage_options', 'ynj-donations',     [ __CLASS__, 'page_donations' ] );
        add_submenu_page( 'ynj-donations', 'Campaigns',     'Campaigns',     'manage_options', 'ynj-campaigns',     [ __CLASS__, 'page_campaigns' ] );
        add_submenu_page( 'ynj-donations', 'Patrons',       'Patrons',       'manage_options', 'ynj-patrons',       [ __CLASS__, 'page_patrons' ] );
        add_submenu_page( 'ynj-donations', 'Fund Types',    'Fund Types',    'manage_options', 'ynj-fund-types',    [ __CLASS__, 'page_fund_types' ] );
    }

    // ────────────────────────────────────────────────────────────────
    // HELPER: format pence → £
    // ────────────────────────────────────────────────────────────────

    /**
     * Format an integer pence value as a GBP string.
     *
     * @param  int|string $pence
     * @return string
     */
    public static function pence_to_pounds( $pence ) {
        return '&pound;' . number_format( (int) $pence / 100, 2 );
    }

    // ────────────────────────────────────────────────────────────────
    // PAGE: DONATIONS (read-only — immutable financial records)
    // ────────────────────────────────────────────────────────────────

    public static function page_donations() {
        $table = new YNJ_Donations_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Donations</h1>
            <hr class="wp-header-end">
            <?php $table->views(); ?>
            <form method="get">
                <input type="hidden" name="page" value="ynj-donations" />
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    // ────────────────────────────────────────────────────────────────
    // PAGE: CAMPAIGNS
    // ────────────────────────────────────────────────────────────────

    public static function page_campaigns() {
        // Handle edit form submission
        if ( isset( $_POST['ynj_campaign_save'] ) && check_admin_referer( 'ynj_campaign_edit' ) ) {
            self::handle_campaign_save();
        }

        // Handle inline actions (activate / pause)
        if ( isset( $_GET['ynj_action'] ) && isset( $_GET['id'] ) && check_admin_referer( 'ynj_campaign_action' ) ) {
            self::handle_campaign_action();
        }

        // Show edit form?
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            self::render_campaign_edit_form( absint( $_GET['id'] ) );
            return;
        }

        $table = new YNJ_Campaigns_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Campaigns</h1>
            <hr class="wp-header-end">
            <form method="get">
                <input type="hidden" name="page" value="ynj-campaigns" />
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Save campaign edit form.
     */
    private static function handle_campaign_save() {
        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );
        $id    = absint( $_POST['campaign_id'] ?? 0 );

        if ( ! $id ) return;

        $update = [
            'title'        => sanitize_text_field( $_POST['title'] ?? '' ),
            'description'  => wp_kses_post( $_POST['description'] ?? '' ),
            'target_pence' => absint( round( floatval( $_POST['target_pounds'] ?? 0 ) * 100 ) ),
            'image_url'    => esc_url_raw( $_POST['image_url'] ?? '' ),
            'status'       => sanitize_text_field( $_POST['status'] ?? 'active' ),
        ];

        $wpdb->update( $table, $update, [ 'id' => $id ] );

        echo '<div class="notice notice-success is-dismissible"><p>Campaign updated.</p></div>';
    }

    /**
     * Handle activate / pause inline actions.
     */
    private static function handle_campaign_action() {
        global $wpdb;
        $table  = YNJ_DB::table( 'campaigns' );
        $id     = absint( $_GET['id'] );
        $action = sanitize_text_field( $_GET['ynj_action'] );

        if ( $action === 'activate' ) {
            $wpdb->update( $table, [ 'status' => 'active' ], [ 'id' => $id ] );
            echo '<div class="notice notice-success is-dismissible"><p>Campaign activated.</p></div>';
        } elseif ( $action === 'pause' ) {
            $wpdb->update( $table, [ 'status' => 'paused' ], [ 'id' => $id ] );
            echo '<div class="notice notice-warning is-dismissible"><p>Campaign paused.</p></div>';
        }
    }

    /**
     * Render the campaign edit form.
     */
    private static function render_campaign_edit_form( $id ) {
        global $wpdb;
        $table    = YNJ_DB::table( 'campaigns' );
        $campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        if ( ! $campaign ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Campaign not found.</p></div></div>';
            return;
        }

        $mosque_table = YNJ_DB::table( 'mosques' );
        $mosque_name  = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mosque_table WHERE id = %d", $campaign->mosque_id ) );
        ?>
        <div class="wrap">
            <h1>Edit Campaign #<?php echo (int) $campaign->id; ?></h1>
            <p>Mosque: <strong><?php echo esc_html( $mosque_name ?: 'Unknown' ); ?></strong> (ID <?php echo (int) $campaign->mosque_id; ?>)</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ynj-campaigns' ) ); ?>">
                <?php wp_nonce_field( 'ynj_campaign_edit' ); ?>
                <input type="hidden" name="campaign_id" value="<?php echo (int) $campaign->id; ?>" />
                <table class="form-table">
                    <tr>
                        <th><label for="title">Title</label></th>
                        <td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $campaign->title ); ?>" required /></td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $campaign->description ?? '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="target_pounds">Target (&pound;)</label></th>
                        <td><input type="number" name="target_pounds" id="target_pounds" step="0.01" min="0" class="regular-text" value="<?php echo esc_attr( number_format( (int) $campaign->target_pence / 100, 2, '.', '' ) ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="image_url">Image URL</label></th>
                        <td><input type="url" name="image_url" id="image_url" class="large-text" value="<?php echo esc_attr( $campaign->image_url ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="status">Status</label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected( $campaign->status, 'active' ); ?>>Active</option>
                                <option value="paused" <?php selected( $campaign->status, 'paused' ); ?>>Paused</option>
                                <option value="completed" <?php selected( $campaign->status, 'completed' ); ?>>Completed</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Campaign', 'primary', 'ynj_campaign_save' ); ?>
            </form>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-campaigns' ) ); ?>">&larr; Back to Campaigns</a></p>
        </div>
        <?php
    }

    // ────────────────────────────────────────────────────────────────
    // PAGE: PATRONS
    // ────────────────────────────────────────────────────────────────

    public static function page_patrons() {
        // Handle cancel action
        if ( isset( $_GET['ynj_action'] ) && $_GET['ynj_action'] === 'cancel' && isset( $_GET['id'] ) && check_admin_referer( 'ynj_patron_action' ) ) {
            $patron_id = absint( $_GET['id'] );
            YNJ_Donations::cancel_patron( $patron_id );
            echo '<div class="notice notice-warning is-dismissible"><p>Patron membership cancelled.</p></div>';
        }

        // Show single patron view?
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'view' && isset( $_GET['id'] ) ) {
            self::render_patron_view( absint( $_GET['id'] ) );
            return;
        }

        $table = new YNJ_Patrons_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Patrons</h1>
            <hr class="wp-header-end">
            <form method="get">
                <input type="hidden" name="page" value="ynj-patrons" />
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Single patron detail view.
     */
    private static function render_patron_view( $id ) {
        global $wpdb;
        $table  = YNJ_DB::table( 'patrons' );
        $patron = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        if ( ! $patron ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Patron not found.</p></div></div>';
            return;
        }

        $mosque_table = YNJ_DB::table( 'mosques' );
        $mosque_name  = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mosque_table WHERE id = %d", $patron->mosque_id ) );
        ?>
        <div class="wrap">
            <h1>Patron #<?php echo (int) $patron->id; ?></h1>
            <table class="form-table">
                <tr><th>User</th><td><?php echo esc_html( $patron->user_name ); ?> (<?php echo esc_html( $patron->user_email ); ?>)</td></tr>
                <tr><th>Mosque</th><td><?php echo esc_html( $mosque_name ?: 'Unknown' ); ?> (ID <?php echo (int) $patron->mosque_id; ?>)</td></tr>
                <tr><th>Tier</th><td><?php echo esc_html( ucfirst( $patron->tier ) ); ?></td></tr>
                <tr><th>Amount</th><td><?php echo self::pence_to_pounds( $patron->amount_pence ); ?>/month</td></tr>
                <tr><th>Status</th><td><?php echo esc_html( ucfirst( $patron->status ) ); ?></td></tr>
                <tr><th>Stripe Customer</th><td><code><?php echo esc_html( $patron->stripe_customer_id ?? '—' ); ?></code></td></tr>
                <tr><th>Stripe Subscription</th><td><code><?php echo esc_html( $patron->stripe_subscription_id ?? '—' ); ?></code></td></tr>
                <tr><th>Started</th><td><?php echo $patron->started_at ? esc_html( date( 'j M Y H:i', strtotime( $patron->started_at ) ) ) : '—'; ?></td></tr>
                <tr><th>Created</th><td><?php echo esc_html( date( 'j M Y H:i', strtotime( $patron->created_at ) ) ); ?></td></tr>
                <?php if ( ! empty( $patron->cancelled_at ) ) : ?>
                <tr><th>Cancelled</th><td><?php echo esc_html( date( 'j M Y H:i', strtotime( $patron->cancelled_at ) ) ); ?></td></tr>
                <?php endif; ?>
            </table>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-patrons' ) ); ?>">&larr; Back to Patrons</a></p>
        </div>
        <?php
    }

    // ────────────────────────────────────────────────────────────────
    // PAGE: FUND TYPES
    // ────────────────────────────────────────────────────────────────

    public static function page_fund_types() {
        // Handle edit form submission
        if ( isset( $_POST['ynj_fund_save'] ) && check_admin_referer( 'ynj_fund_edit' ) ) {
            self::handle_fund_save();
        }

        // Handle deactivate action
        if ( isset( $_GET['ynj_action'] ) && $_GET['ynj_action'] === 'deactivate' && isset( $_GET['id'] ) && check_admin_referer( 'ynj_fund_action' ) ) {
            global $wpdb;
            $table   = YNJ_DB::table( 'mosque_funds' );
            $fund_id = absint( $_GET['id'] );
            $fund    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $fund_id ) );

            if ( $fund ) {
                if ( $fund->is_default ) {
                    echo '<div class="notice notice-error is-dismissible"><p>Cannot deactivate the default fund.</p></div>';
                } else {
                    $wpdb->update( $table, [ 'is_active' => 0 ], [ 'id' => $fund_id ] );
                    echo '<div class="notice notice-warning is-dismissible"><p>Fund type deactivated.</p></div>';
                }
            }
        }

        // Show edit form?
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            self::render_fund_edit_form( absint( $_GET['id'] ) );
            return;
        }

        $table = new YNJ_Fund_Types_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Fund Types</h1>
            <hr class="wp-header-end">
            <form method="get">
                <input type="hidden" name="page" value="ynj-fund-types" />
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Save fund type edit form.
     */
    private static function handle_fund_save() {
        global $wpdb;
        $table   = YNJ_DB::table( 'mosque_funds' );
        $fund_id = absint( $_POST['fund_id'] ?? 0 );

        if ( ! $fund_id ) return;

        $fund = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $fund_id ) );
        if ( ! $fund ) return;

        $update = [
            'label'        => sanitize_text_field( $_POST['label'] ?? '' ),
            'description'  => sanitize_text_field( $_POST['description'] ?? '' ),
        ];

        $wpdb->update( $table, $update, [ 'id' => $fund_id ] );

        echo '<div class="notice notice-success is-dismissible"><p>Fund type updated.</p></div>';
    }

    /**
     * Render fund type edit form.
     */
    private static function render_fund_edit_form( $id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'mosque_funds' );
        $fund  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        if ( ! $fund ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Fund type not found.</p></div></div>';
            return;
        }

        $mosque_table = YNJ_DB::table( 'mosques' );
        $mosque_name  = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mosque_table WHERE id = %d", $fund->mosque_id ) );
        ?>
        <div class="wrap">
            <h1>Edit Fund Type #<?php echo (int) $fund->id; ?></h1>
            <p>Mosque: <strong><?php echo esc_html( $mosque_name ?: 'Unknown' ); ?></strong> (ID <?php echo (int) $fund->mosque_id; ?>)</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=ynj-fund-types' ) ); ?>">
                <?php wp_nonce_field( 'ynj_fund_edit' ); ?>
                <input type="hidden" name="fund_id" value="<?php echo (int) $fund->id; ?>" />
                <table class="form-table">
                    <tr>
                        <th><label for="label">Name</label></th>
                        <td><input type="text" name="label" id="label" class="regular-text" value="<?php echo esc_attr( $fund->label ); ?>" required /></td>
                    </tr>
                    <tr>
                        <th>Slug</th>
                        <td><code><?php echo esc_html( $fund->slug ); ?></code> <span class="description">(read-only)</span></td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $fund->description ?? '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Raised</th>
                        <td><?php echo self::pence_to_pounds( $fund->raised_pence ); ?> <span class="description">(auto-calculated from donations)</span></td>
                    </tr>
                </table>
                <?php submit_button( 'Save Fund Type', 'primary', 'ynj_fund_save' ); ?>
            </form>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ynj-fund-types' ) ); ?>">&larr; Back to Fund Types</a></p>
        </div>
        <?php
    }
}


// ════════════════════════════════════════════════════════════════════
// WP_List_Table: DONATIONS
// ════════════════════════════════════════════════════════════════════

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class YNJ_Donations_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'donation',
            'plural'   => 'donations',
            'ajax'     => false,
        ] );
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table('mosques') . " WHERE status IN ('active','unclaimed') ORDER BY name" );
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

    public function get_columns() {
        return [
            'id'            => 'ID',
            'donor_name'    => 'Donor Name',
            'donor_email'   => 'Email',
            'mosque'        => 'Mosque',
            'amount_pence'  => 'Amount',
            'fund_type'     => 'Fund Type',
            'status'        => 'Status',
            'stripe_id'     => 'Stripe ID',
            'created_at'    => 'Date',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'           => [ 'id', true ],
            'amount_pence' => [ 'amount_pence', false ],
            'created_at'   => [ 'created_at', false ],
        ];
    }

    /**
     * Status filter views.
     */
    protected function get_views() {
        global $wpdb;
        $table   = YNJ_DB::table( 'donations' );
        $current = sanitize_text_field( $_GET['status'] ?? '' );
        $base    = admin_url( 'admin.php?page=ynj-donations' );

        $count_all       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $count_succeeded = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'succeeded'" );
        $count_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );
        $count_failed    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'failed'" );

        $views = [];
        $views['all']       = '<a href="' . esc_url( $base ) . '"' . ( ! $current ? ' class="current"' : '' ) . '>All <span class="count">(' . number_format( $count_all ) . ')</span></a>';
        $views['succeeded'] = '<a href="' . esc_url( add_query_arg( 'status', 'succeeded', $base ) ) . '"' . ( $current === 'succeeded' ? ' class="current"' : '' ) . '>Succeeded <span class="count">(' . number_format( $count_succeeded ) . ')</span></a>';
        $views['pending']   = '<a href="' . esc_url( add_query_arg( 'status', 'pending', $base ) ) . '"' . ( $current === 'pending' ? ' class="current"' : '' ) . '>Pending <span class="count">(' . number_format( $count_pending ) . ')</span></a>';
        $views['failed']    = '<a href="' . esc_url( add_query_arg( 'status', 'failed', $base ) ) . '"' . ( $current === 'failed' ? ' class="current"' : '' ) . '>Failed <span class="count">(' . number_format( $count_failed ) . ')</span></a>';

        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $dt = YNJ_DB::table( 'donations' );
        $mt = YNJ_DB::table( 'mosques' );

        $per_page = 50;
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset   = ( $paged - 1 ) * $per_page;

        // Status filter
        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $where = '1=1';
        $params = [];

        if ( in_array( $status_filter, [ 'succeeded', 'pending', 'failed' ], true ) ) {
            $where .= ' AND d.status = %s';
            $params[] = $status_filter;
        }

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= ' AND d.mosque_id = %d';
            $params[] = absint( $_GET['mosque_id'] );
        }

        // Sorting
        $allowed_orderby = [ 'id', 'amount_pence', 'created_at' ];
        $orderby = in_array( $_GET['orderby'] ?? '', $allowed_orderby, true ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
        $order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        // Count
        $count_sql = "SELECT COUNT(*) FROM $dt d WHERE $where";
        $total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

        // Query with mosque name join
        $select_sql = "SELECT d.*, m.name AS mosque_name
                       FROM $dt d
                       LEFT JOIN $mt m ON d.mosque_id = m.id
                       WHERE $where
                       ORDER BY d.$orderby $order
                       LIMIT %d OFFSET %d";

        $query_params = array_merge( $params, [ $per_page, $offset ] );
        $this->items = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_params ) );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return (int) $item->id;

            case 'donor_name':
                return esc_html( $item->donor_name ?: '—' );

            case 'donor_email':
                return esc_html( $item->donor_email );

            case 'mosque':
                return esc_html( $item->mosque_name ?: 'ID ' . (int) $item->mosque_id );

            case 'amount_pence':
                $label = YNJ_Donations_Admin::pence_to_pounds( $item->amount_pence );
                if ( ! empty( $item->is_recurring ) ) {
                    $freq = esc_html( $item->frequency ?? 'month' );
                    $label .= ' <small style="color:#6b7280">/' . $freq . '</small>';
                }
                return $label;

            case 'fund_type':
                return esc_html( ucfirst( str_replace( '-', ' ', $item->fund_type ?? '—' ) ) );

            case 'status':
                $colors = [
                    'succeeded' => 'background:#dcfce7;color:#166534',
                    'pending'   => 'background:#fef3c7;color:#92400e',
                    'failed'    => 'background:#fee2e2;color:#991b1b',
                ];
                $style = $colors[ $item->status ] ?? 'background:#f3f4f6;color:#374151';
                return '<span style="' . esc_attr( $style ) . ';padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700">' . esc_html( ucfirst( $item->status ) ) . '</span>';

            case 'stripe_id':
                $pi = $item->stripe_payment_intent ?? '';
                if ( ! $pi ) return '—';
                return '<code style="font-size:11px">' . esc_html( $pi ) . '</code>';

            case 'created_at':
                return esc_html( date( 'j M Y H:i', strtotime( $item->created_at ) ) );

            default:
                return '';
        }
    }

    /**
     * No bulk actions — donations are immutable.
     */
    protected function get_bulk_actions() {
        return [];
    }
}


// ════════════════════════════════════════════════════════════════════
// WP_List_Table: CAMPAIGNS
// ════════════════════════════════════════════════════════════════════

class YNJ_Campaigns_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'campaign',
            'plural'   => 'campaigns',
            'ajax'     => false,
        ] );
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table('mosques') . " WHERE status IN ('active','unclaimed') ORDER BY name" );
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

    public function get_columns() {
        return [
            'id'           => 'ID',
            'title'        => 'Title',
            'mosque'       => 'Mosque',
            'target_pence' => 'Target',
            'raised_pence' => 'Raised',
            'status'       => 'Status',
            'created_at'   => 'Created',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'         => [ 'id', true ],
            'created_at' => [ 'created_at', false ],
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $ct = YNJ_DB::table( 'campaigns' );
        $mt = YNJ_DB::table( 'mosques' );

        $per_page = 50;
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset   = ( $paged - 1 ) * $per_page;

        $where  = '1=1';
        $params = [];

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= ' AND c.mosque_id = %d';
            $params[] = absint( $_GET['mosque_id'] );
        }

        $allowed_orderby = [ 'id', 'created_at' ];
        $orderby = in_array( $_GET['orderby'] ?? '', $allowed_orderby, true ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
        $order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $count_sql = "SELECT COUNT(*) FROM $ct c WHERE $where";
        $total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

        $select_sql = "SELECT c.*, m.name AS mosque_name
             FROM $ct c
             LEFT JOIN $mt m ON c.mosque_id = m.id
             WHERE $where
             ORDER BY c.$orderby $order
             LIMIT %d OFFSET %d";

        $query_params = array_merge( $params, [ $per_page, $offset ] );
        $this->items = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_params ) );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return (int) $item->id;

            case 'title':
                $edit_url = admin_url( 'admin.php?page=ynj-campaigns&action=edit&id=' . (int) $item->id );
                return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->title ) . '</a></strong>'
                     . $this->row_actions( $this->get_row_actions( $item ) );

            case 'mosque':
                return esc_html( $item->mosque_name ?: 'ID ' . (int) $item->mosque_id );

            case 'target_pence':
                return YNJ_Donations_Admin::pence_to_pounds( $item->target_pence );

            case 'raised_pence':
                $raised = (int) ( $item->raised_pence ?? 0 );
                $target = (int) $item->target_pence;
                $pct    = $target > 0 ? round( ( $raised / $target ) * 100 ) : 0;
                return YNJ_Donations_Admin::pence_to_pounds( $raised ) . ' <small style="color:#6b7280">(' . $pct . '%)</small>';

            case 'status':
                $colors = [
                    'active'    => 'background:#dcfce7;color:#166534',
                    'paused'    => 'background:#fef3c7;color:#92400e',
                    'completed' => 'background:#dbeafe;color:#1e40af',
                ];
                $style = $colors[ $item->status ] ?? 'background:#f3f4f6;color:#374151';
                return '<span style="' . esc_attr( $style ) . ';padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700">' . esc_html( ucfirst( $item->status ) ) . '</span>';

            case 'created_at':
                return esc_html( date( 'j M Y', strtotime( $item->created_at ) ) );

            default:
                return '';
        }
    }

    /**
     * Row actions for campaigns: Edit, Activate, Pause.
     */
    private function get_row_actions( $item ) {
        $edit_url = admin_url( 'admin.php?page=ynj-campaigns&action=edit&id=' . (int) $item->id );

        $actions = [
            'edit' => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
        ];

        if ( $item->status !== 'active' ) {
            $activate_url = wp_nonce_url(
                admin_url( 'admin.php?page=ynj-campaigns&ynj_action=activate&id=' . (int) $item->id ),
                'ynj_campaign_action'
            );
            $actions['activate'] = '<a href="' . esc_url( $activate_url ) . '">Activate</a>';
        }

        if ( $item->status === 'active' ) {
            $pause_url = wp_nonce_url(
                admin_url( 'admin.php?page=ynj-campaigns&ynj_action=pause&id=' . (int) $item->id ),
                'ynj_campaign_action'
            );
            $actions['pause'] = '<a href="' . esc_url( $pause_url ) . '" style="color:#b45309">Pause</a>';
        }

        return $actions;
    }

    protected function get_bulk_actions() {
        return [];
    }
}


// ════════════════════════════════════════════════════════════════════
// WP_List_Table: PATRONS
// ════════════════════════════════════════════════════════════════════

class YNJ_Patrons_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'patron',
            'plural'   => 'patrons',
            'ajax'     => false,
        ] );
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table('mosques') . " WHERE status IN ('active','unclaimed') ORDER BY name" );
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

    public function get_columns() {
        return [
            'id'                     => 'ID',
            'user_name'              => 'User Name',
            'user_email'             => 'Email',
            'mosque'                 => 'Mosque',
            'tier'                   => 'Tier',
            'amount_pence'           => 'Amount',
            'status'                 => 'Status',
            'started_at'             => 'Started',
            'stripe_subscription_id' => 'Stripe Sub ID',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'           => [ 'id', true ],
            'amount_pence' => [ 'amount_pence', false ],
            'started_at'   => [ 'started_at', false ],
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $pt = YNJ_DB::table( 'patrons' );
        $mt = YNJ_DB::table( 'mosques' );

        $per_page = 50;
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset   = ( $paged - 1 ) * $per_page;

        $where  = '1=1';
        $params = [];

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= ' AND p.mosque_id = %d';
            $params[] = absint( $_GET['mosque_id'] );
        }

        $allowed_orderby = [ 'id', 'amount_pence', 'started_at' ];
        $orderby = in_array( $_GET['orderby'] ?? '', $allowed_orderby, true ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
        $order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $count_sql = "SELECT COUNT(*) FROM $pt p WHERE $where";
        $total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

        $select_sql = "SELECT p.*, m.name AS mosque_name
             FROM $pt p
             LEFT JOIN $mt m ON p.mosque_id = m.id
             WHERE $where
             ORDER BY p.$orderby $order
             LIMIT %d OFFSET %d";

        $query_params = array_merge( $params, [ $per_page, $offset ] );
        $this->items = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_params ) );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return (int) $item->id;

            case 'user_name':
                $view_url = admin_url( 'admin.php?page=ynj-patrons&action=view&id=' . (int) $item->id );
                return '<strong><a href="' . esc_url( $view_url ) . '">' . esc_html( $item->user_name ?: '—' ) . '</a></strong>'
                     . $this->row_actions( $this->get_row_actions( $item ) );

            case 'user_email':
                return esc_html( $item->user_email );

            case 'mosque':
                return esc_html( $item->mosque_name ?: 'ID ' . (int) $item->mosque_id );

            case 'tier':
                return esc_html( ucfirst( $item->tier ?? '—' ) );

            case 'amount_pence':
                return YNJ_Donations_Admin::pence_to_pounds( $item->amount_pence ) . '<small style="color:#6b7280">/month</small>';

            case 'status':
                $colors = [
                    'active'          => 'background:#dcfce7;color:#166534',
                    'pending_payment' => 'background:#fef3c7;color:#92400e',
                    'cancelled'       => 'background:#fee2e2;color:#991b1b',
                ];
                $style = $colors[ $item->status ] ?? 'background:#f3f4f6;color:#374151';
                $label = str_replace( '_', ' ', ucfirst( $item->status ) );
                return '<span style="' . esc_attr( $style ) . ';padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700">' . esc_html( $label ) . '</span>';

            case 'started_at':
                return $item->started_at ? esc_html( date( 'j M Y', strtotime( $item->started_at ) ) ) : '—';

            case 'stripe_subscription_id':
                $sub = $item->stripe_subscription_id ?? '';
                if ( ! $sub ) return '—';
                return '<code style="font-size:11px">' . esc_html( $sub ) . '</code>';

            default:
                return '';
        }
    }

    /**
     * Row actions for patrons: View, Cancel.
     */
    private function get_row_actions( $item ) {
        $view_url = admin_url( 'admin.php?page=ynj-patrons&action=view&id=' . (int) $item->id );

        $actions = [
            'view' => '<a href="' . esc_url( $view_url ) . '">View</a>',
        ];

        if ( $item->status === 'active' ) {
            $cancel_url = wp_nonce_url(
                admin_url( 'admin.php?page=ynj-patrons&ynj_action=cancel&id=' . (int) $item->id ),
                'ynj_patron_action'
            );
            $actions['cancel'] = '<a href="' . esc_url( $cancel_url ) . '" style="color:#dc2626" onclick="return confirm(\'Cancel this patron membership?\')">Cancel</a>';
        }

        return $actions;
    }

    protected function get_bulk_actions() {
        return [];
    }
}


// ════════════════════════════════════════════════════════════════════
// WP_List_Table: FUND TYPES
// ════════════════════════════════════════════════════════════════════

class YNJ_Fund_Types_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'fund_type',
            'plural'   => 'fund_types',
            'ajax'     => false,
        ] );
    }

    public function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        global $wpdb;
        $mosques = $wpdb->get_results( "SELECT id, name FROM " . YNJ_DB::table('mosques') . " WHERE status IN ('active','unclaimed') ORDER BY name" );
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

    public function get_columns() {
        return [
            'id'           => 'ID',
            'mosque'       => 'Mosque',
            'label'        => 'Name',
            'description'  => 'Description',
            'raised_pence' => 'Raised',
            'is_active'    => 'Status',
        ];
    }

    public function get_sortable_columns() {
        return [
            'id'           => [ 'id', true ],
            'raised_pence' => [ 'raised_pence', false ],
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $ft = YNJ_DB::table( 'mosque_funds' );
        $mt = YNJ_DB::table( 'mosques' );

        $per_page = 50;
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset   = ( $paged - 1 ) * $per_page;

        $where  = '1=1';
        $params = [];

        if ( ! empty( $_GET['mosque_id'] ) ) {
            $where .= ' AND f.mosque_id = %d';
            $params[] = absint( $_GET['mosque_id'] );
        }

        $allowed_orderby = [ 'id', 'raised_pence' ];
        $orderby = in_array( $_GET['orderby'] ?? '', $allowed_orderby, true ) ? sanitize_text_field( $_GET['orderby'] ) : 'id';
        $order   = ( isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $count_sql = "SELECT COUNT(*) FROM $ft f WHERE $where";
        $total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

        $select_sql = "SELECT f.*, m.name AS mosque_name
             FROM $ft f
             LEFT JOIN $mt m ON f.mosque_id = m.id
             WHERE $where
             ORDER BY f.$orderby $order
             LIMIT %d OFFSET %d";

        $query_params = array_merge( $params, [ $per_page, $offset ] );
        $this->items = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_params ) );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                return (int) $item->id;

            case 'mosque':
                return esc_html( $item->mosque_name ?: 'ID ' . (int) $item->mosque_id );

            case 'label':
                $edit_url = admin_url( 'admin.php?page=ynj-fund-types&action=edit&id=' . (int) $item->id );
                return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->label ) . '</a></strong>'
                     . '<br><code style="font-size:11px">' . esc_html( $item->slug ) . '</code>'
                     . $this->row_actions( $this->get_row_actions( $item ) );

            case 'description':
                $desc = $item->description ?? '';
                return esc_html( mb_strimwidth( $desc, 0, 80, '...' ) );

            case 'raised_pence':
                return YNJ_Donations_Admin::pence_to_pounds( $item->raised_pence ?? 0 );

            case 'is_active':
                if ( (int) $item->is_active ) {
                    return '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700">Active</span>';
                }
                return '<span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700">Inactive</span>';

            default:
                return '';
        }
    }

    /**
     * Row actions for fund types: Edit, Deactivate.
     */
    private function get_row_actions( $item ) {
        $edit_url = admin_url( 'admin.php?page=ynj-fund-types&action=edit&id=' . (int) $item->id );

        $actions = [
            'edit' => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
        ];

        if ( (int) $item->is_active && ! (int) $item->is_default ) {
            $deactivate_url = wp_nonce_url(
                admin_url( 'admin.php?page=ynj-fund-types&ynj_action=deactivate&id=' . (int) $item->id ),
                'ynj_fund_action'
            );
            $actions['deactivate'] = '<a href="' . esc_url( $deactivate_url ) . '" style="color:#dc2626" onclick="return confirm(\'Deactivate this fund type?\')">Deactivate</a>';
        }

        return $actions;
    }

    protected function get_bulk_actions() {
        return [];
    }
}
