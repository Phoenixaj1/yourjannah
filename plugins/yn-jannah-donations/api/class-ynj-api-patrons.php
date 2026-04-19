<?php
/**
 * YourJannah — REST API: Patron membership endpoints.
 *
 * Handles patron signup, status, cancellation, and admin listing.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Patrons {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /patrons/checkout — Create patron subscription checkout (user auth)
        register_rest_route( self::NS, '/patrons/checkout', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // GET /patrons/me — Get current user's patron status (user auth)
        register_rest_route( self::NS, '/patrons/me', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'my_patron' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // POST /patrons/cancel — Cancel patron subscription (user auth)
        register_rest_route( self::NS, '/patrons/cancel', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'cancel' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // GET /mosques/{id}/patrons — Public list of patrons for a mosque
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/patrons', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_public' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /admin/patrons — Admin: full patron list + revenue (mosque admin auth)
        register_rest_route( self::NS, '/admin/patrons', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'admin_list' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
    }

    // ================================================================
    // TIERS
    // ================================================================

    private static function tiers() {
        return [
            'supporter' => [ 'amount' => 500,   'label' => 'Bronze (£5/mo)' ],
            'guardian'  => [ 'amount' => 1000,  'label' => 'Silver (£10/mo)' ],
            'champion'  => [ 'amount' => 2000,  'label' => 'Gold (£20/mo)' ],
            'platinum'  => [ 'amount' => 5000,  'label' => 'Platinum (£50/mo)' ],
        ];
    }

    public static function get_tiers() {
        return self::tiers();
    }

    // ================================================================
    // CHECKOUT — create Stripe subscription
    // ================================================================

    public static function checkout( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        if ( ! $mosque_id && ! empty( $data['mosque_slug'] ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $data['mosque_slug'] );
        }

        $tier = sanitize_text_field( $data['tier'] ?? 'supporter' );
        $tiers = self::tiers();

        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id required.' ], 400 );
        }

        if ( ! isset( $tiers[ $tier ] ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid tier.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );

        // Check existing active patron
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status, stripe_subscription_id FROM $table WHERE mosque_id = %d AND user_id = %d",
            $mosque_id, $user->id
        ) );

        if ( $existing && $existing->status === 'active' ) {
            return new \WP_REST_Response( [
                'ok'    => false,
                'error' => 'You are already an active patron of this mosque. Cancel first to change tier.',
            ], 409 );
        }

        // Get mosque name for Stripe line item
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT name, slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d",
            $mosque_id
        ) );

        if ( ! $mosque ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $tier_config = $tiers[ $tier ];

        // Upsert patron record as pending
        if ( $existing ) {
            $wpdb->update( $table, [
                'tier'         => $tier,
                'amount_pence' => $tier_config['amount'],
                'status'       => 'pending_payment',
                'user_name'    => $user->name,
                'user_email'   => $user->email,
            ], [ 'id' => $existing->id ] );
            $patron_id = (int) $existing->id;
        } else {
            $wpdb->insert( $table, [
                'mosque_id'    => $mosque_id,
                'user_id'      => (int) $user->id,
                'user_name'    => $user->name,
                'user_email'   => $user->email,
                'tier'         => $tier,
                'amount_pence' => $tier_config['amount'],
                'status'       => 'pending_payment',
            ] );
            $patron_id = (int) $wpdb->insert_id;
        }

        if ( ! $patron_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create patron record.' ], 500 );
        }

        // Return cart_item for unified checkout
        return new \WP_REST_Response( [
            'ok'        => true,
            'patron_id' => $patron_id,
            'cart_item' => [
                'item_type'    => 'patron',
                'item_id'      => $patron_id,
                'item_label'   => $tier_config['label'] . ' — ' . $mosque->name,
                'mosque_id'    => $mosque_id,
                'mosque_name'  => $mosque->name,
                'amount_pence' => $tier_config['amount'],
                'fund_type'    => 'patron',
                'frequency'    => 'monthly',
                'meta'         => [ 'tier' => $tier, 'patron_id' => $patron_id ],
            ],
        ] );
    }

    // ================================================================
    // MY PATRON STATUS
    // ================================================================

    public static function my_patron( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );

        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );
        $mosque_table = YNJ_DB::table( 'mosques' );

        $patrons = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, m.name AS mosque_name, m.slug AS mosque_slug
             FROM $table p
             LEFT JOIN $mosque_table m ON m.id = p.mosque_id
             WHERE p.user_id = %d
             ORDER BY p.created_at DESC",
            $user->id
        ) );

        $result = array_map( function( $p ) {
            return [
                'id'          => (int) $p->id,
                'mosque_id'   => (int) $p->mosque_id,
                'mosque_name' => $p->mosque_name,
                'mosque_slug' => $p->mosque_slug,
                'tier'        => $p->tier,
                'amount_pence' => (int) $p->amount_pence,
                'status'      => $p->status,
                'started_at'  => $p->started_at,
                'created_at'  => $p->created_at,
            ];
        }, $patrons );

        return new \WP_REST_Response( [ 'ok' => true, 'patrons' => $result ] );
    }

    // ================================================================
    // CANCEL
    // ================================================================

    public static function cancel( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        $mosque_id = absint( $data['mosque_id'] ?? 0 );

        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );

        $patron = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND mosque_id = %d AND status = 'active'",
            $user->id, $mosque_id
        ) );

        if ( ! $patron ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'No active patron membership found.' ], 404 );
        }

        // Cancel Stripe subscription
        if ( $patron->stripe_subscription_id ) {
            $result = YNJ_Stripe::cancel_subscription( $patron->stripe_subscription_id );
            if ( is_wp_error( $result ) ) {
                error_log( '[YNJ Patrons] Stripe cancel error: ' . $result->get_error_message() );
            }
        }

        $wpdb->update( $table, [
            'status'       => 'cancelled',
            'cancelled_at' => current_time( 'mysql', true ),
        ], [ 'id' => $patron->id ] );

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Patron membership cancelled. Thank you for your support.',
        ] );
    }

    // ================================================================
    // PUBLIC LIST (patron wall)
    // ================================================================

    public static function list_public( \WP_REST_Request $request ) {
        $mosque_id = (int) $request->get_param( 'id' );

        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );

        $patrons = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_name, tier, started_at
             FROM $table
             WHERE mosque_id = %d AND status = 'active'
             ORDER BY amount_pence DESC, started_at ASC",
            $mosque_id
        ) );

        $result = array_map( function( $p ) {
            return [
                'name'       => $p->user_name,
                'tier'       => $p->tier,
                'started_at' => $p->started_at,
            ];
        }, $patrons );

        // Summary stats
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(amount_pence), 0) AS monthly_pence
             FROM $table
             WHERE mosque_id = %d AND status = 'active'",
            $mosque_id
        ) );

        return new \WP_REST_Response( [
            'ok'            => true,
            'patrons'       => $result,
            'total_patrons' => (int) $stats->total,
            'monthly_pence' => (int) $stats->monthly_pence,
        ] );
    }

    // ================================================================
    // ADMIN LIST
    // ================================================================

    public static function admin_list( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );

        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );

        $patrons = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE mosque_id = %d ORDER BY status ASC, amount_pence DESC, created_at DESC",
            $mosque->id
        ) );

        $result = array_map( function( $p ) {
            return [
                'id'           => (int) $p->id,
                'user_id'      => (int) $p->user_id,
                'user_name'    => $p->user_name,
                'user_email'   => $p->user_email,
                'tier'         => $p->tier,
                'amount_pence' => (int) $p->amount_pence,
                'status'       => $p->status,
                'started_at'   => $p->started_at,
                'cancelled_at' => $p->cancelled_at,
                'created_at'   => $p->created_at,
            ];
        }, $patrons );

        // Revenue stats
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(amount_pence), 0) AS monthly_pence
             FROM $table
             WHERE mosque_id = %d AND status = 'active'",
            $mosque->id
        ) );

        return new \WP_REST_Response( [
            'ok'            => true,
            'patrons'       => $result,
            'total_active'  => (int) $stats->total,
            'monthly_pence' => (int) $stats->monthly_pence,
        ] );
    }
}
