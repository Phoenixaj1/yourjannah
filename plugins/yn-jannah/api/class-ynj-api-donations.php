<?php
/**
 * YourJannah Donation API
 *
 * REST endpoints for mosque donations via the floating niyyah bar.
 * One-off payments via PaymentIntent, recurring via Subscription.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Donations {

    const NS = 'ynj/v1';

    /** Fund types with labels */
    const FUND_TYPES = [
        'welfare'     => 'Community Welfare Fund',
        'general'     => 'General Donation',
        'imam'        => 'Imam & Staff Fund',
        'maintenance' => 'Mosque Maintenance',
        'education'   => 'Quran & Education',
        'youth'       => 'Youth & Family',
        'sadaqah'     => 'Sadaqah Jariyah',
    ];

    public static function register() {
        // POST /donate — one-off payment
        register_rest_route( self::NS, '/donate', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create_donation' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /donate/recurring — subscription
        register_rest_route( self::NS, '/donate/recurring', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create_recurring' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /donate/confirm — mark as succeeded
        register_rest_route( self::NS, '/donate/confirm', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'confirm_donation' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /mosques/{slug}/funds — public: list active funds for a mosque
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/funds', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_mosque_funds' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /admin/funds — admin: create a fund
        register_rest_route( self::NS, '/admin/funds', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'admin_create_fund' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );

        // PUT /admin/funds/{id} — admin: update a fund
        register_rest_route( self::NS, '/admin/funds/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'admin_update_fund' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );

        // DELETE /admin/funds/{id} — admin: deactivate a fund
        register_rest_route( self::NS, '/admin/funds/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'admin_delete_fund' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
    }

    /**
     * POST /donate — create one-off PaymentIntent.
     */
    public static function create_donation( \WP_REST_Request $request ) {
        if ( ! YNJ_Stripe::is_configured() ) {
            return new \WP_Error( 'stripe_not_configured', 'Stripe is not configured.', [ 'status' => 500 ] );
        }

        $mosque_id = self::resolve_mosque( $request );
        $amount    = (int) ( $request->get_param( 'amount_pence' ) ?: 0 );
        $email     = sanitize_email( $request->get_param( 'email' ) ?? '' );
        $fund      = sanitize_text_field( $request->get_param( 'fund_type' ) ?? 'welfare' );
        $name      = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
        $currency  = strtolower( sanitize_text_field( $request->get_param( 'currency' ) ?? 'gbp' ) );

        if ( $amount < 100 ) {
            return new \WP_Error( 'invalid_amount', 'Minimum donation is £1.', [ 'status' => 400 ] );
        }
        if ( ! $email || ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', 'Valid email required.', [ 'status' => 400 ] );
        }
        if ( ! $mosque_id ) {
            return new \WP_Error( 'invalid_mosque', 'Mosque not found.', [ 'status' => 400 ] );
        }

        // Get mosque name for description
        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $mosque_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mt WHERE id = %d", $mosque_id ) ) ?: 'Mosque';
        $fund_label = self::FUND_TYPES[ $fund ] ?? ucfirst( $fund );

        // Insert donation record
        $dt = YNJ_DB::table( 'donations' );
        $wpdb->insert( $dt, [
            'mosque_id'    => $mosque_id,
            'donor_name'   => $name,
            'donor_email'  => $email,
            'amount_pence' => $amount,
            'currency'     => $currency,
            'fund_type'    => $fund,
            'frequency'    => 'once',
            'is_recurring' => 0,
            'status'       => 'pending',
        ] );
        $donation_id = $wpdb->insert_id;
        if ( ! $donation_id ) {
            return new \WP_Error( 'db_error', 'Could not create donation record.', [ 'status' => 500 ] );
        }

        // Create Stripe PaymentIntent
        YNJ_Stripe::init();
        try {
            $pi = \Stripe\PaymentIntent::create( [
                'amount'               => $amount,
                'currency'             => $currency,
                'receipt_email'        => $email,
                'description'          => $fund_label . ' — ' . $mosque_name,
                'metadata'             => [
                    'type'        => 'mosque_donation',
                    'item_id'     => $donation_id,
                    'mosque_id'   => $mosque_id,
                    'fund_type'   => $fund,
                    'donor_email' => $email,
                ],
            ] );

            $wpdb->update( $dt, [
                'stripe_payment_intent' => $pi->id,
            ], [ 'id' => $donation_id ] );

            return new \WP_REST_Response( [
                'ok'            => true,
                'client_secret' => $pi->client_secret,
                'donation_id'   => $donation_id,
            ] );

        } catch ( \Exception $e ) {
            $wpdb->update( $dt, [ 'status' => 'failed' ], [ 'id' => $donation_id ] );
            return new \WP_Error( 'stripe_error', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * POST /donate/recurring — create Stripe Subscription.
     */
    public static function create_recurring( \WP_REST_Request $request ) {
        if ( ! YNJ_Stripe::is_configured() ) {
            return new \WP_Error( 'stripe_not_configured', 'Stripe is not configured.', [ 'status' => 500 ] );
        }

        $mosque_id = self::resolve_mosque( $request );
        $amount    = (int) ( $request->get_param( 'amount_pence' ) ?: 0 );
        $email     = sanitize_email( $request->get_param( 'email' ) ?? '' );
        $fund      = sanitize_text_field( $request->get_param( 'fund_type' ) ?? 'welfare' );
        $name      = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
        $currency  = strtolower( sanitize_text_field( $request->get_param( 'currency' ) ?? 'gbp' ) );
        $interval  = sanitize_text_field( $request->get_param( 'interval' ) ?? 'week' );

        if ( $amount < 100 ) {
            return new \WP_Error( 'invalid_amount', 'Minimum donation is £1.', [ 'status' => 400 ] );
        }
        if ( ! $email || ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', 'Valid email required.', [ 'status' => 400 ] );
        }
        if ( ! in_array( $interval, [ 'week', 'month' ], true ) ) {
            return new \WP_Error( 'invalid_interval', 'Interval must be week or month.', [ 'status' => 400 ] );
        }

        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $mosque_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mt WHERE id = %d", $mosque_id ) ) ?: 'Mosque';
        $fund_label = self::FUND_TYPES[ $fund ] ?? ucfirst( $fund );

        // Insert donation record
        $dt = YNJ_DB::table( 'donations' );
        $wpdb->insert( $dt, [
            'mosque_id'    => $mosque_id,
            'donor_name'   => $name,
            'donor_email'  => $email,
            'amount_pence' => $amount,
            'currency'     => $currency,
            'fund_type'    => $fund,
            'frequency'    => $interval,
            'is_recurring' => 1,
            'status'       => 'pending',
        ] );
        $donation_id = $wpdb->insert_id;

        YNJ_Stripe::init();
        try {
            // Find or create customer
            $customers = \Stripe\Customer::search( [ 'query' => "email:'{$email}'" ] );
            if ( ! empty( $customers->data ) ) {
                $customer = $customers->data[0];
            } else {
                $customer = \Stripe\Customer::create( [
                    'email' => $email,
                    'name'  => $name ?: null,
                    'metadata' => [ 'source' => 'yourjannah_niyyah_bar' ],
                ] );
            }

            // Create inline price
            $price = \Stripe\Price::create( [
                'unit_amount' => $amount,
                'currency'    => $currency,
                'recurring'   => [ 'interval' => $interval ],
                'product_data' => [
                    'name' => $fund_label . ' — ' . $mosque_name . ' (' . ( $interval === 'week' ? 'Weekly' : 'Monthly' ) . ')',
                ],
            ] );

            // Create subscription with incomplete payment
            $sub = \Stripe\Subscription::create( [
                'customer'               => $customer->id,
                'items'                  => [ [ 'price' => $price->id ] ],
                'payment_behavior'       => 'default_incomplete',
                'payment_settings'       => [ 'save_default_payment_method' => 'on_subscription' ],
                'expand'                 => [ 'latest_invoice.payment_intent' ],
                'metadata'               => [
                    'type'        => 'mosque_donation',
                    'item_id'     => $donation_id,
                    'mosque_id'   => $mosque_id,
                    'fund_type'   => $fund,
                    'donor_email' => $email,
                ],
            ] );

            $client_secret = $sub->latest_invoice->payment_intent->client_secret ?? '';

            $wpdb->update( $dt, [
                'stripe_customer_id'     => $customer->id,
                'stripe_subscription_id' => $sub->id,
                'stripe_payment_intent'  => $sub->latest_invoice->payment_intent->id ?? '',
            ], [ 'id' => $donation_id ] );

            return new \WP_REST_Response( [
                'ok'              => true,
                'client_secret'   => $client_secret,
                'donation_id'     => $donation_id,
                'subscription_id' => $sub->id,
            ] );

        } catch ( \Exception $e ) {
            $wpdb->update( $dt, [ 'status' => 'failed' ], [ 'id' => $donation_id ] );
            return new \WP_Error( 'stripe_error', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * POST /donate/confirm — mark donation as succeeded after Stripe verification.
     */
    public static function confirm_donation( \WP_REST_Request $request ) {
        $donation_id = (int) ( $request->get_param( 'donation_id' ) ?: 0 );
        if ( ! $donation_id ) {
            return new \WP_Error( 'invalid_id', 'Missing donation_id.', [ 'status' => 400 ] );
        }

        global $wpdb;
        $dt = YNJ_DB::table( 'donations' );
        $donation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $dt WHERE id = %d", $donation_id ) );
        if ( ! $donation ) {
            return new \WP_Error( 'not_found', 'Donation not found.', [ 'status' => 404 ] );
        }

        // Verify PaymentIntent status with Stripe before marking as succeeded
        $pi_id = $donation->stripe_payment_intent ?? '';
        if ( empty( $pi_id ) ) {
            return new \WP_Error( 'no_payment_intent', 'No PaymentIntent found for this donation.', [ 'status' => 400 ] );
        }

        $stripe_response = wp_remote_get( 'https://api.stripe.com/v1/payment_intents/' . $pi_id, [
            'headers' => [ 'Authorization' => 'Bearer ' . YNJ_Stripe::secret_key() ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $stripe_response ) ) {
            return new \WP_Error( 'stripe_error', 'Could not verify payment with Stripe.', [ 'status' => 502 ] );
        }

        $pi_data = json_decode( wp_remote_retrieve_body( $stripe_response ), true );
        if ( empty( $pi_data['status'] ) || $pi_data['status'] !== 'succeeded' ) {
            return new \WP_Error( 'payment_not_confirmed', 'Stripe PaymentIntent has not succeeded (status: ' . ( $pi_data['status'] ?? 'unknown' ) . ').', [ 'status' => 400 ] );
        }

        $wpdb->update( $dt, [ 'status' => 'succeeded' ], [ 'id' => $donation_id ] );

        // Record in pool ledger
        if ( class_exists( 'YNJ_Pool_Ledger' ) ) {
            $fund_label = self::FUND_TYPES[ $donation->fund_type ] ?? ucfirst( $donation->fund_type );
            YNJ_Pool_Ledger::record( [
                'mosque_id'              => (int) $donation->mosque_id,
                'entry_type'             => $donation->is_recurring ? 'recurring' : 'payment',
                'payment_type'           => 'donation',
                'item_id'                => $donation_id,
                'gross_pence'            => (int) $donation->amount_pence,
                'stripe_payment_id'      => $donation->stripe_payment_intent,
                'stripe_subscription_id' => $donation->stripe_subscription_id,
                'payer_name'             => $donation->donor_name,
                'payer_email'            => $donation->donor_email,
                'description'            => $fund_label . ( $donation->is_recurring ? ' (' . ucfirst( $donation->frequency ) . 'ly)' : '' ),
            ] );
        }

        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    // ================================================================
    // MOSQUE FUNDS — public + admin endpoints
    // ================================================================

    /**
     * GET /mosques/{slug}/funds — list active funds for a mosque.
     */
    public static function get_mosque_funds( \WP_REST_Request $request ) {
        $slug = sanitize_title( $request->get_param( 'slug' ) ?? '' );
        $mosque_id = (int) YNJ_DB::resolve_slug( $slug );
        if ( ! $mosque_id ) {
            return new \WP_Error( 'not_found', 'Mosque not found.', [ 'status' => 404 ] );
        }

        global $wpdb;
        $ft = YNJ_DB::table( 'mosque_funds' );
        $funds = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, slug, label, description, target_pence, raised_pence, is_default, sort_order
             FROM $ft WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
            $mosque_id
        ) );

        // If no funds exist yet, seed defaults and return them
        if ( empty( $funds ) ) {
            foreach ( YNJ_DB::default_fund_types() as $fund ) {
                $wpdb->insert( $ft, array_merge( $fund, [ 'mosque_id' => $mosque_id ] ) );
            }
            $funds = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, slug, label, description, target_pence, raised_pence, is_default, sort_order
                 FROM $ft WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
                $mosque_id
            ) );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'funds' => $funds ] );
    }

    /**
     * POST /admin/funds — create a custom fund (admin auth required).
     */
    public static function admin_create_fund( \WP_REST_Request $request ) {
        $auth = YNJ_Auth::bearer_check( $request );
        if ( is_wp_error( $auth ) ) return $auth;

        global $wpdb;
        $ft = YNJ_DB::table( 'mosque_funds' );

        $label = sanitize_text_field( $request->get_param( 'label' ) ?? '' );
        $slug  = sanitize_title( $request->get_param( 'slug' ) ?? $label );
        $desc  = sanitize_text_field( $request->get_param( 'description' ) ?? '' );
        $target = (int) ( $request->get_param( 'target_pence' ) ?? 0 );

        if ( ! $label ) {
            return new \WP_Error( 'missing_label', 'Fund label is required.', [ 'status' => 400 ] );
        }

        $max_order = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sort_order) FROM $ft WHERE mosque_id = %d", $auth->mosque_id
        ) );

        $wpdb->insert( $ft, [
            'mosque_id'    => $auth->mosque_id,
            'slug'         => $slug,
            'label'        => $label,
            'description'  => $desc,
            'target_pence' => $target,
            'is_default'   => 0,
            'sort_order'   => $max_order + 1,
        ] );

        return new \WP_REST_Response( [ 'ok' => true, 'fund_id' => $wpdb->insert_id ] );
    }

    /**
     * PUT /admin/funds/{id} — update a fund.
     */
    public static function admin_update_fund( \WP_REST_Request $request ) {
        $auth = YNJ_Auth::bearer_check( $request );
        if ( is_wp_error( $auth ) ) return $auth;

        $fund_id = (int) $request->get_param( 'id' );
        global $wpdb;
        $ft = YNJ_DB::table( 'mosque_funds' );

        $fund = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $ft WHERE id = %d AND mosque_id = %d", $fund_id, $auth->mosque_id
        ) );
        if ( ! $fund ) {
            return new \WP_Error( 'not_found', 'Fund not found.', [ 'status' => 404 ] );
        }

        $update = [];
        if ( $request->get_param( 'label' ) !== null ) $update['label'] = sanitize_text_field( $request->get_param( 'label' ) );
        if ( $request->get_param( 'description' ) !== null ) $update['description'] = sanitize_text_field( $request->get_param( 'description' ) );
        if ( $request->get_param( 'target_pence' ) !== null ) $update['target_pence'] = (int) $request->get_param( 'target_pence' );
        if ( $request->get_param( 'is_active' ) !== null ) $update['is_active'] = (int) $request->get_param( 'is_active' );
        if ( $request->get_param( 'sort_order' ) !== null ) $update['sort_order'] = (int) $request->get_param( 'sort_order' );

        if ( ! empty( $update ) ) {
            $wpdb->update( $ft, $update, [ 'id' => $fund_id ] );
        }

        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    /**
     * DELETE /admin/funds/{id} — deactivate a fund (can't delete General).
     */
    public static function admin_delete_fund( \WP_REST_Request $request ) {
        $auth = YNJ_Auth::bearer_check( $request );
        if ( is_wp_error( $auth ) ) return $auth;

        $fund_id = (int) $request->get_param( 'id' );
        global $wpdb;
        $ft = YNJ_DB::table( 'mosque_funds' );

        $fund = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $ft WHERE id = %d AND mosque_id = %d", $fund_id, $auth->mosque_id
        ) );
        if ( ! $fund ) {
            return new \WP_Error( 'not_found', 'Fund not found.', [ 'status' => 404 ] );
        }
        if ( $fund->is_default ) {
            return new \WP_Error( 'cannot_delete', 'Cannot remove the default General Donation fund.', [ 'status' => 400 ] );
        }

        $wpdb->update( $ft, [ 'is_active' => 0 ], [ 'id' => $fund_id ] );
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    /**
     * Resolve mosque_id from slug or id param.
     */
    private static function resolve_mosque( \WP_REST_Request $request ) {
        $mosque_id = (int) ( $request->get_param( 'mosque_id' ) ?: 0 );
        if ( $mosque_id ) return $mosque_id;

        $slug = sanitize_title( $request->get_param( 'mosque_slug' ) ?? '' );
        if ( $slug ) {
            return (int) YNJ_DB::resolve_slug( $slug );
        }
        return 0;
    }
}
