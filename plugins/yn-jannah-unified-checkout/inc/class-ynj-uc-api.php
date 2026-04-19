<?php
/**
 * Unified Checkout REST API.
 *
 * POST /unified-checkout/create-intent — Creates transaction + Stripe PaymentIntent
 * POST /unified-checkout/confirm       — Verifies payment succeeded
 *
 * @package YNJ_Unified_Checkout
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_UC_API {

    /**
     * POST /unified-checkout/create-intent
     *
     * Creates a transaction record and Stripe PaymentIntent.
     * Returns client_secret for frontend Stripe Elements confirmation.
     */
    public static function create_intent( \WP_REST_Request $request ) {
        $d = $request->get_json_params();

        // ── Validate ──
        $email = sanitize_email( $d['email'] ?? '' );
        if ( ! $email || ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Valid email required.' ], 400 );
        }

        $amount_pence = absint( $d['amount_pence'] ?? 0 );
        $tip_pence    = absint( $d['tip_pence'] ?? 0 );
        $total_pence  = $amount_pence + $tip_pence;

        if ( $amount_pence < 100 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Minimum amount is £1.' ], 400 );
        }

        $mosque_id  = absint( $d['mosque_id'] ?? 0 );
        $item_type  = sanitize_text_field( $d['item_type'] ?? 'donation' );
        $item_id    = absint( $d['item_id'] ?? 0 );
        $item_label = sanitize_text_field( $d['item_label'] ?? '' );
        $fund_type  = sanitize_text_field( $d['fund_type'] ?? 'general' );
        $frequency  = sanitize_text_field( $d['frequency'] ?? 'once' );
        $donor_name = sanitize_text_field( $d['name'] ?? '' );
        $phone      = sanitize_text_field( $d['phone'] ?? '' );
        $source     = sanitize_text_field( $d['source'] ?? 'checkout' );
        $items_json = ! empty( $d['items'] ) ? wp_json_encode( $d['items'] ) : null;
        $currency   = sanitize_text_field( $d['currency'] ?? 'gbp' );

        // Generate transaction ID
        $txn_id = 'ynj_' . bin2hex( random_bytes( 12 ) );

        // ── Create transaction record (pending) ──
        global $wpdb;
        $t = YNJ_DB::table( 'transactions' );
        $wpdb->insert( $t, [
            'transaction_id' => $txn_id,
            'mosque_id'      => $mosque_id,
            'donor_name'     => $donor_name,
            'donor_email'    => $email,
            'donor_phone'    => $phone,
            'item_type'      => $item_type,
            'item_id'        => $item_id,
            'item_label'     => $item_label ?: ucfirst( str_replace( '_', ' ', $item_type ) ),
            'amount_pence'   => $amount_pence,
            'tip_pence'      => $tip_pence,
            'total_pence'    => $total_pence,
            'currency'       => $currency,
            'frequency'      => $frequency,
            'fund_type'      => $fund_type,
            'items_json'     => $items_json,
            'status'         => 'pending',
            'source'         => $source,
        ] );
        $row_id = (int) $wpdb->insert_id;

        if ( ! $row_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create transaction.' ], 500 );
        }

        // ── Create Stripe PaymentIntent ──
        if ( ! class_exists( 'YNJ_Stripe' ) || ! method_exists( 'YNJ_Stripe', 'init' ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe not configured.' ], 500 );
        }
        YNJ_Stripe::init();

        // Get mosque name for description
        $mosque_name = '';
        if ( $mosque_id ) {
            $mt = YNJ_DB::table( 'mosques' );
            $mosque_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mt WHERE id = %d", $mosque_id ) ) ?: '';
        }

        $description = $item_label ?: ( ucfirst( str_replace( '_', ' ', $item_type ) ) . ( $mosque_name ? ' — ' . $mosque_name : '' ) );

        try {
            if ( $frequency !== 'once' ) {
                // Recurring: create Stripe Checkout Session (subscription mode)
                $interval_map = [ 'daily' => 'day', 'weekly' => 'week', 'monthly' => 'month' ];
                $interval = $interval_map[ $frequency ] ?? 'month';

                $session = \Stripe\Checkout\Session::create( [
                    'payment_method_types' => [ 'card' ],
                    'mode'                 => 'subscription',
                    'customer_email'       => $email,
                    'line_items'           => [ [
                        'price_data' => [
                            'currency'     => $currency,
                            'unit_amount'  => $total_pence,
                            'recurring'    => [ 'interval' => $interval ],
                            'product_data' => [ 'name' => $description ],
                        ],
                        'quantity' => 1,
                    ] ],
                    'metadata' => [
                        'transaction_id' => $txn_id,
                        'mosque_id'      => $mosque_id,
                        'item_type'      => $item_type,
                        'type'           => 'ynj_unified_checkout',
                    ],
                    'success_url' => home_url( '/checkout/?success=1&txn=' . $txn_id ),
                    'cancel_url'  => home_url( '/checkout/?cancelled=1' ),
                ] );

                $wpdb->update( $t, [
                    'stripe_session_id' => $session->id,
                ], [ 'id' => $row_id ] );

                return new \WP_REST_Response( [
                    'ok'             => true,
                    'mode'           => 'redirect',
                    'url'            => $session->url,
                    'transaction_id' => $txn_id,
                ] );
            }

            // One-off: create PaymentIntent (inline Stripe Elements)
            $pi = \Stripe\PaymentIntent::create( [
                'amount'               => $total_pence,
                'currency'             => $currency,
                'description'          => $description,
                'receipt_email'        => $email,
                'metadata'             => [
                    'transaction_id' => $txn_id,
                    'mosque_id'      => $mosque_id,
                    'item_type'      => $item_type,
                    'type'           => 'ynj_unified_checkout',
                ],
                'automatic_payment_methods' => [ 'enabled' => true ],
            ] );

            $wpdb->update( $t, [
                'stripe_payment_intent' => $pi->id,
            ], [ 'id' => $row_id ] );

            return new \WP_REST_Response( [
                'ok'             => true,
                'mode'           => 'elements',
                'client_secret'  => $pi->client_secret,
                'transaction_id' => $txn_id,
                'total_pence'    => $total_pence,
            ] );

        } catch ( \Exception $e ) {
            error_log( '[YNJ UC] Stripe error: ' . $e->getMessage() );
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Payment setup failed. Please try again.' ], 500 );
        }
    }

    /**
     * POST /unified-checkout/confirm
     *
     * Called after stripe.confirmPayment() succeeds on frontend.
     */
    public static function confirm( \WP_REST_Request $request ) {
        $d = $request->get_json_params();
        $txn_id = sanitize_text_field( $d['transaction_id'] ?? '' );
        $pi_id  = sanitize_text_field( $d['payment_intent_id'] ?? '' );

        if ( ! $txn_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'transaction_id required.' ], 400 );
        }

        global $wpdb;
        $t = YNJ_DB::table( 'transactions' );
        $txn = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE transaction_id = %s", $txn_id ) );

        if ( ! $txn ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Transaction not found.' ], 404 );
        }

        if ( $txn->status === 'succeeded' ) {
            return new \WP_REST_Response( [ 'ok' => true, 'already' => true ] );
        }

        // Verify with Stripe
        if ( $pi_id && class_exists( 'YNJ_Stripe' ) ) {
            YNJ_Stripe::init();
            try {
                $pi = \Stripe\PaymentIntent::retrieve( $pi_id );
                if ( $pi->status === 'succeeded' ) {
                    $wpdb->update( $t, [
                        'status'       => 'succeeded',
                        'completed_at' => current_time( 'mysql' ),
                    ], [ 'id' => $txn->id ] );

                    // Fire hook for downstream processing (revenue share, notifications, etc.)
                    do_action( 'ynj_unified_payment_succeeded', $txn->id, $txn );

                    // Also fire legacy hook if it's a donation type
                    if ( in_array( $txn->item_type, [ 'donation', 'sadaqah', 'platform_donate' ], true ) ) {
                        do_action( 'ynj_donation_succeeded', (int) $txn->item_id ?: (int) $txn->id, $txn );
                    }

                    return new \WP_REST_Response( [ 'ok' => true ] );
                }
            } catch ( \Exception $e ) {
                error_log( '[YNJ UC] Confirm error: ' . $e->getMessage() );
            }
        }

        return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Payment not confirmed yet.' ], 400 );
    }

    /**
     * Handle webhook for unified checkout transactions.
     * Called from the main Stripe webhook handler when metadata.type === 'ynj_unified_checkout'.
     */
    public static function on_webhook_succeeded( $txn_id_str ) {
        global $wpdb;
        $t = YNJ_DB::table( 'transactions' );
        $txn = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE transaction_id = %s", $txn_id_str ) );

        if ( ! $txn || $txn->status === 'succeeded' ) return;

        $wpdb->update( $t, [
            'status'       => 'succeeded',
            'completed_at' => current_time( 'mysql' ),
        ], [ 'id' => $txn->id ] );

        do_action( 'ynj_unified_payment_succeeded', $txn->id, $txn );

        if ( in_array( $txn->item_type, [ 'donation', 'sadaqah', 'platform_donate' ], true ) ) {
            do_action( 'ynj_donation_succeeded', (int) $txn->item_id ?: (int) $txn->id, $txn );
        }

        error_log( '[YNJ UC] Webhook confirmed transaction: ' . $txn_id_str );
    }
}
