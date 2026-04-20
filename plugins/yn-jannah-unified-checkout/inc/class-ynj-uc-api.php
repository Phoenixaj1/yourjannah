<?php
/**
 * Unified Checkout REST API — v2: multi-item basket.
 *
 * POST /unified-checkout/create-intent — Creates transaction(s) + Stripe PaymentIntent/Session
 * POST /unified-checkout/confirm       — Verifies payment succeeded, fires per-item hooks
 *
 * @package YNJ_Unified_Checkout
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_UC_API {

    /**
     * Check if cash/test payment mode is active (site-wide).
     * Enable: update_option( 'ynj_cash_payment_mode', 1 )
     * Or visit: ?ynj_cash_mode=on
     */
    public static function is_test_mode() {
        return (bool) get_option( 'ynj_cash_payment_mode' );
    }

    /**
     * POST /unified-checkout/create-intent
     *
     * v2 payload:
     *   { email, name, tip_pence, items: [{ item_type, item_id, item_label, mosque_id, amount_pence, fund_type, frequency, meta }], source }
     *
     * Backwards compat: if 'items' is absent, treats legacy single-item fields as a single-item array.
     */
    public static function create_intent( \WP_REST_Request $request ) {
        $d = $request->get_json_params();

        // ── Validate email ──
        $email = sanitize_email( $d['email'] ?? '' );
        if ( ! $email || ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Valid email required.' ], 400 );
        }

        $donor_name = sanitize_text_field( $d['name'] ?? '' );
        $phone      = sanitize_text_field( $d['phone'] ?? '' );
        $tip_pence  = absint( $d['tip_pence'] ?? 0 );
        $source     = sanitize_text_field( $d['source'] ?? 'checkout' );
        $currency   = sanitize_text_field( $d['currency'] ?? 'gbp' );

        // ── Normalise items array ──
        $items = [];
        if ( ! empty( $d['items'] ) && is_array( $d['items'] ) ) {
            $items = $d['items'];
        } else {
            // Legacy single-item mode
            $items[] = [
                'item_type'    => sanitize_text_field( $d['item_type'] ?? 'donation' ),
                'item_id'      => absint( $d['item_id'] ?? 0 ),
                'item_label'   => sanitize_text_field( $d['item_label'] ?? '' ),
                'mosque_id'    => absint( $d['mosque_id'] ?? 0 ),
                'amount_pence' => absint( $d['amount_pence'] ?? 0 ),
                'fund_type'    => sanitize_text_field( $d['fund_type'] ?? 'general' ),
                'frequency'    => sanitize_text_field( $d['frequency'] ?? 'once' ),
                'meta'         => $d['items'] ?? null, // legacy: 'items' was used for message etc.
            ];
        }

        if ( empty( $items ) || count( $items ) > 20 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Between 1 and 20 items required.' ], 400 );
        }

        // ── Sanitise + validate each item ──
        $sanitised = [];
        $subtotal  = 0;
        foreach ( $items as $raw ) {
            $amt = absint( $raw['amount_pence'] ?? 0 );
            if ( $amt < 100 ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Each item must be at least £1.' ], 400 );
            }
            $sanitised[] = [
                'item_type'    => sanitize_text_field( $raw['item_type'] ?? 'donation' ),
                'item_id'      => absint( $raw['item_id'] ?? 0 ),
                'item_label'   => sanitize_text_field( $raw['item_label'] ?? '' ),
                'mosque_id'    => absint( $raw['mosque_id'] ?? 0 ),
                'amount_pence' => $amt,
                'fund_type'    => sanitize_text_field( $raw['fund_type'] ?? 'general' ),
                'frequency'    => sanitize_text_field( $raw['frequency'] ?? 'once' ),
                'meta'         => is_array( $raw['meta'] ?? null ) ? $raw['meta'] : [],
            ];
            $subtotal += $amt;
        }
        $items = $sanitised;

        $total_pence = $subtotal + $tip_pence;
        $item_count  = count( $items );

        // Determine primary item type and mosque
        $primary_type     = $item_count === 1 ? $items[0]['item_type'] : 'multi';
        $primary_mosque   = $items[0]['mosque_id'];
        $primary_label    = $item_count === 1 ? ( $items[0]['item_label'] ?: ucfirst( str_replace( '_', ' ', $items[0]['item_type'] ) ) ) : $item_count . ' items';

        // ── Provision domain records for items that need them ──
        foreach ( $items as &$item ) {
            if ( ! $item['item_id'] ) {
                $provisioned_id = self::provision_item( $item, $email, $donor_name, $phone );
                if ( $provisioned_id ) {
                    $item['item_id'] = $provisioned_id;
                }
            }
        }
        unset( $item );

        // ── Split by frequency ──
        $once_items     = [];
        $recurring_items = [];
        foreach ( $items as $it ) {
            if ( $it['frequency'] === 'once' || ! $it['frequency'] ) {
                $once_items[] = $it;
            } else {
                $recurring_items[] = $it;
            }
        }

        $once_total = 0;
        foreach ( $once_items as $it ) $once_total += $it['amount_pence'];

        $recur_total = 0;
        foreach ( $recurring_items as $it ) $recur_total += $it['amount_pence'];

        global $wpdb;
        $t = YNJ_DB::table( 'transactions' );

        // ── TEST MODE: skip Stripe, instantly succeed ──
        $test_mode = self::is_test_mode();
        if ( $test_mode ) {
            $txn_id = self::create_transaction( $t, $wpdb, [
                'mosque_id'    => $primary_mosque,
                'donor_name'   => $donor_name,
                'donor_email'  => $email,
                'donor_phone'  => $phone,
                'item_type'    => $primary_type,
                'item_id'      => $item_count === 1 ? $items[0]['item_id'] : 0,
                'item_label'   => $primary_label,
                'amount_pence' => $subtotal,
                'tip_pence'    => $tip_pence,
                'total_pence'  => $total_pence,
                'currency'     => $currency,
                'frequency'    => $item_count === 1 ? ( $items[0]['frequency'] ?? 'once' ) : 'once',
                'fund_type'    => $item_count === 1 ? $items[0]['fund_type'] : 'mixed',
                'items_json'   => wp_json_encode( $items ),
                'source'       => $source,
            ] );

            // Immediately mark as succeeded
            $wpdb->update( $t, [
                'status'                => 'succeeded',
                'completed_at'          => current_time( 'mysql' ),
                'stripe_payment_intent' => 'test_' . $txn_id,
            ], [ 'transaction_id' => $txn_id ] );

            // Fire hooks
            $txn = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE transaction_id = %s", $txn_id ) );
            if ( $txn ) self::fire_item_hooks( $txn );

            return new \WP_REST_Response( [
                'ok'             => true,
                'mode'           => 'test',
                'transaction_id' => $txn_id,
                'total_pence'    => $total_pence,
                'test_mode'      => true,
            ] );
        }

        // ── Stripe init ──
        if ( ! class_exists( 'YNJ_Stripe' ) || ! method_exists( 'YNJ_Stripe', 'init' ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe not configured.' ], 500 );
        }
        YNJ_Stripe::init();

        try {
            // ═══ ALL ONE-OFF ═══
            if ( ! empty( $once_items ) && empty( $recurring_items ) ) {
                $txn_id = self::create_transaction( $t, $wpdb, [
                    'mosque_id'    => $primary_mosque,
                    'donor_name'   => $donor_name,
                    'donor_email'  => $email,
                    'donor_phone'  => $phone,
                    'item_type'    => $primary_type,
                    'item_id'      => $item_count === 1 ? $items[0]['item_id'] : 0,
                    'item_label'   => $primary_label,
                    'amount_pence' => $subtotal,
                    'tip_pence'    => $tip_pence,
                    'total_pence'  => $total_pence,
                    'currency'     => $currency,
                    'frequency'    => 'once',
                    'fund_type'    => $item_count === 1 ? $items[0]['fund_type'] : 'mixed',
                    'items_json'   => wp_json_encode( $items ),
                    'source'       => $source,
                ] );

                $pi_params = [
                    'amount'                     => $total_pence,
                    'currency'                   => $currency,
                    'description'                => $primary_label,
                    'receipt_email'              => $email,
                    'metadata'                   => [
                        'transaction_id' => $txn_id,
                        'mosque_id'      => $primary_mosque,
                        'item_type'      => $primary_type,
                        'type'           => 'ynj_unified_checkout',
                    ],
                    'automatic_payment_methods'  => [ 'enabled' => true ],
                ];
                // Stripe Connect: add destination + fee if mosque has connected account
                $pi_params = apply_filters( 'ynj_payment_params', $pi_params, $primary_mosque, $total_pence );

                $pi = \Stripe\PaymentIntent::create( $pi_params );

                $wpdb->update( $t, [ 'stripe_payment_intent' => $pi->id ], [ 'transaction_id' => $txn_id ] );

                return new \WP_REST_Response( [
                    'ok'             => true,
                    'mode'           => 'elements',
                    'client_secret'  => $pi->client_secret,
                    'transaction_id' => $txn_id,
                    'total_pence'    => $total_pence,
                ] );
            }

            // ═══ ALL RECURRING (same frequency) ═══
            if ( empty( $once_items ) && ! empty( $recurring_items ) ) {
                $freq = $recurring_items[0]['frequency'];
                $recur_total_with_tip = $recur_total + $tip_pence;

                $txn_id = self::create_transaction( $t, $wpdb, [
                    'mosque_id'    => $primary_mosque,
                    'donor_name'   => $donor_name,
                    'donor_email'  => $email,
                    'donor_phone'  => $phone,
                    'item_type'    => $primary_type,
                    'item_id'      => $item_count === 1 ? $items[0]['item_id'] : 0,
                    'item_label'   => $primary_label,
                    'amount_pence' => $recur_total,
                    'tip_pence'    => $tip_pence,
                    'total_pence'  => $recur_total_with_tip,
                    'currency'     => $currency,
                    'frequency'    => $freq,
                    'fund_type'    => $item_count === 1 ? $items[0]['fund_type'] : 'mixed',
                    'items_json'   => wp_json_encode( $items ),
                    'source'       => $source,
                ] );

                $interval_map = [ 'daily' => 'day', 'weekly' => 'week', 'monthly' => 'month' ];
                $interval = $interval_map[ $freq ] ?? 'month';

                // Find or create Stripe customer
                $customers = \Stripe\Customer::search( [ 'query' => "email:'" . $email . "'" ] );
                if ( ! empty( $customers->data ) ) {
                    $customer = $customers->data[0];
                } else {
                    $customer = \Stripe\Customer::create( [
                        'email'    => $email,
                        'name'     => $donor_name ?: null,
                        'metadata' => [ 'source' => 'yourjannah_niyyah_bar' ],
                    ] );
                }

                // Create inline price + subscription with incomplete payment
                $price = \Stripe\Price::create( [
                    'unit_amount'  => $recur_total_with_tip,
                    'currency'     => $currency,
                    'recurring'    => [ 'interval' => $interval ],
                    'product_data' => [ 'name' => $primary_label ],
                ] );

                $sub_params = [
                    'customer'               => $customer->id,
                    'items'                  => [ [ 'price' => $price->id ] ],
                    'payment_behavior'       => 'default_incomplete',
                    'payment_settings'       => [ 'save_default_payment_method' => 'on_subscription' ],
                    'expand'                 => [ 'latest_invoice.payment_intent' ],
                    'metadata'               => [
                        'transaction_id' => $txn_id,
                        'mosque_id'      => $primary_mosque,
                        'item_type'      => $primary_type,
                        'type'           => 'ynj_unified_checkout',
                    ],
                ];
                // Stripe Connect: for subscriptions, use application_fee_percent + transfer_data on subscription
                if ( class_exists( 'YNJ_Stripe_Connect' ) ) {
                    $acct = YNJ_Stripe_Connect::get_account_id( $primary_mosque );
                    if ( $acct ) {
                        $sub_params['application_fee_percent'] = YNJ_Stripe_Connect::get_fee_pct( $primary_mosque );
                        $sub_params['transfer_data'] = [ 'destination' => $acct ];
                    }
                }

                $sub = \Stripe\Subscription::create( $sub_params );

                $client_secret = $sub->latest_invoice->payment_intent->client_secret ?? '';
                $pi_id = $sub->latest_invoice->payment_intent->id ?? '';

                $wpdb->update( $t, [
                    'stripe_payment_intent' => $pi_id,
                ], [ 'transaction_id' => $txn_id ] );

                return new \WP_REST_Response( [
                    'ok'             => true,
                    'mode'           => 'elements',
                    'client_secret'  => $client_secret,
                    'transaction_id' => $txn_id,
                    'total_pence'    => $recur_total_with_tip,
                ] );
            }

            // ═══ MIXED: one-off + recurring ═══
            $once_total_with_tip = $once_total + $tip_pence; // tip goes on one-off portion

            // Transaction for one-off items
            $txn_once = self::create_transaction( $t, $wpdb, [
                'mosque_id'    => $primary_mosque,
                'donor_name'   => $donor_name,
                'donor_email'  => $email,
                'donor_phone'  => $phone,
                'item_type'    => count( $once_items ) === 1 ? $once_items[0]['item_type'] : 'multi',
                'item_id'      => count( $once_items ) === 1 ? $once_items[0]['item_id'] : 0,
                'item_label'   => count( $once_items ) === 1 ? ( $once_items[0]['item_label'] ?: 'One-off items' ) : count( $once_items ) . ' one-off items',
                'amount_pence' => $once_total,
                'tip_pence'    => $tip_pence,
                'total_pence'  => $once_total_with_tip,
                'currency'     => $currency,
                'frequency'    => 'once',
                'fund_type'    => count( $once_items ) === 1 ? $once_items[0]['fund_type'] : 'mixed',
                'items_json'   => wp_json_encode( $once_items ),
                'source'       => $source,
            ] );

            $pi_params2 = [
                'amount'                     => $once_total_with_tip,
                'currency'                   => $currency,
                'description'                => 'YourJannah Checkout (one-off)',
                'receipt_email'              => $email,
                'metadata'                   => [
                    'transaction_id' => $txn_once,
                    'mosque_id'      => $primary_mosque,
                    'item_type'      => 'multi',
                    'type'           => 'ynj_unified_checkout',
                ],
                'automatic_payment_methods'  => [ 'enabled' => true ],
            ];
            $pi_params2 = apply_filters( 'ynj_payment_params', $pi_params2, $primary_mosque, $once_total_with_tip );

            $pi = \Stripe\PaymentIntent::create( $pi_params2 );

            $wpdb->update( $t, [ 'stripe_payment_intent' => $pi->id ], [ 'transaction_id' => $txn_once ] );

            // Transaction for recurring items
            $recur_freq = $recurring_items[0]['frequency'];
            $txn_recur = self::create_transaction( $t, $wpdb, [
                'mosque_id'    => $primary_mosque,
                'donor_name'   => $donor_name,
                'donor_email'  => $email,
                'donor_phone'  => $phone,
                'item_type'    => count( $recurring_items ) === 1 ? $recurring_items[0]['item_type'] : 'multi',
                'item_id'      => count( $recurring_items ) === 1 ? $recurring_items[0]['item_id'] : 0,
                'item_label'   => count( $recurring_items ) === 1 ? ( $recurring_items[0]['item_label'] ?: 'Recurring items' ) : count( $recurring_items ) . ' recurring items',
                'amount_pence' => $recur_total,
                'tip_pence'    => 0,
                'total_pence'  => $recur_total,
                'currency'     => $currency,
                'frequency'    => $recur_freq,
                'fund_type'    => count( $recurring_items ) === 1 ? $recurring_items[0]['fund_type'] : 'mixed',
                'items_json'   => wp_json_encode( $recurring_items ),
                'source'       => $source,
            ] );

            $interval_map = [ 'daily' => 'day', 'weekly' => 'week', 'monthly' => 'month' ];
            $interval = $interval_map[ $recur_freq ] ?? 'month';

            $session = \Stripe\Checkout\Session::create( [
                'payment_method_types' => [ 'card' ],
                'mode'                 => 'subscription',
                'customer_email'       => $email,
                'line_items'           => [ [
                    'price_data' => [
                        'currency'     => $currency,
                        'unit_amount'  => $recur_total,
                        'recurring'    => [ 'interval' => $interval ],
                        'product_data' => [ 'name' => count( $recurring_items ) === 1 ? $recurring_items[0]['item_label'] : 'Recurring donation' ],
                    ],
                    'quantity' => 1,
                ] ],
                'metadata' => [
                    'transaction_id' => $txn_recur,
                    'mosque_id'      => $primary_mosque,
                    'type'           => 'ynj_unified_checkout',
                ],
                'success_url' => home_url( '/checkout/?success=1&txn=' . $txn_recur ),
                'cancel_url'  => home_url( '/checkout/?cancelled=1' ),
            ] );

            $wpdb->update( $t, [ 'stripe_session_id' => $session->id ], [ 'transaction_id' => $txn_recur ] );

            return new \WP_REST_Response( [
                'ok'   => true,
                'mode' => 'split',
                'one_off' => [
                    'client_secret'  => $pi->client_secret,
                    'transaction_id' => $txn_once,
                    'total_pence'    => $once_total_with_tip,
                ],
                'recurring' => [
                    'url'            => $session->url,
                    'transaction_id' => $txn_recur,
                ],
            ] );

        } catch ( \Exception $e ) {
            error_log( '[YNJ UC] Stripe error: ' . $e->getMessage() );
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Payment setup failed. Please try again.' ], 500 );
        }
    }

    /**
     * Create a transaction row and return the transaction_id string.
     */
    private static function create_transaction( $table, $wpdb, $data ) {
        $txn_id = 'ynj_' . bin2hex( random_bytes( 12 ) );
        $data['transaction_id'] = $txn_id;
        $data['status'] = 'pending';
        $wpdb->insert( $table, $data );

        if ( ! $wpdb->insert_id ) {
            throw new \Exception( 'Failed to create transaction record.' );
        }

        return $txn_id;
    }

    /**
     * Provision a domain record for items that need one before payment.
     * Returns the item_id or 0 if no provisioning needed.
     */
    private static function provision_item( $item, $email, $name, $phone ) {
        global $wpdb;
        $type = $item['item_type'];
        $mosque_id = $item['mosque_id'];
        $meta = $item['meta'] ?? [];

        switch ( $type ) {
            case 'patron':
                $tier = $meta['tier'] ?? 'supporter';
                $tiers = [ 'supporter' => 500, 'guardian' => 1000, 'champion' => 2000, 'platinum' => 5000 ];
                $pt = YNJ_DB::table( 'patrons' );
                $uid = get_current_user_id() ?: 0;
                // Upsert: update existing record or create new
                $existing = $uid ? $wpdb->get_row( $wpdb->prepare(
                    "SELECT id FROM $pt WHERE mosque_id = %d AND user_id = %d", $mosque_id, $uid
                ) ) : null;
                if ( $existing ) {
                    $wpdb->update( $pt, [
                        'tier'         => $tier,
                        'amount_pence' => $tiers[ $tier ] ?? $item['amount_pence'],
                        'status'       => 'pending_payment',
                        'user_name'    => $name,
                        'user_email'   => $email,
                    ], [ 'id' => $existing->id ] );
                    return (int) $existing->id;
                }
                $wpdb->insert( $pt, [
                    'mosque_id'    => $mosque_id,
                    'user_id'      => $uid,
                    'user_name'    => $name,
                    'user_email'   => $email,
                    'tier'         => $tier,
                    'amount_pence' => $tiers[ $tier ] ?? $item['amount_pence'],
                    'status'       => 'pending_payment',
                ] );
                return (int) $wpdb->insert_id;

            case 'room_booking':
                $bt = YNJ_DB::table( 'bookings' );
                $wpdb->insert( $bt, [
                    'mosque_id'    => $mosque_id,
                    'room_id'      => absint( $meta['room_id'] ?? 0 ),
                    'user_name'    => $name,
                    'user_email'   => $email,
                    'user_phone'   => $phone,
                    'booking_date' => sanitize_text_field( $meta['booking_date'] ?? '' ),
                    'start_time'   => sanitize_text_field( $meta['start_time'] ?? '' ),
                    'end_time'     => sanitize_text_field( $meta['end_time'] ?? '' ),
                    'notes'        => sanitize_text_field( $meta['notes'] ?? '' ),
                    'status'       => 'pending_payment',
                ] );
                return (int) $wpdb->insert_id;

            case 'event_ticket':
                $bt = YNJ_DB::table( 'bookings' );
                $wpdb->insert( $bt, [
                    'mosque_id'    => $mosque_id,
                    'event_id'     => absint( $meta['event_id'] ?? 0 ),
                    'user_name'    => $name,
                    'user_email'   => $email,
                    'user_phone'   => $phone,
                    'booking_date' => sanitize_text_field( $meta['event_date'] ?? '' ),
                    'status'       => 'pending_payment',
                ] );
                return (int) $wpdb->insert_id;

            case 'class_enrolment':
                $et = YNJ_DB::table( 'enrolments' );
                $wpdb->insert( $et, [
                    'class_id'   => absint( $meta['class_id'] ?? 0 ),
                    'user_name'  => $name,
                    'user_email' => $email,
                    'user_phone' => $phone,
                    'status'     => 'pending_payment',
                ] );
                return (int) $wpdb->insert_id;

            case 'business_sponsor':
            case 'sponsor':
                $bt = YNJ_DB::table( 'businesses' );
                $wpdb->insert( $bt, [
                    'mosque_id'         => $mosque_id,
                    'business_name'     => sanitize_text_field( $meta['business_name'] ?? $item['item_label'] ),
                    'owner_name'        => $name,
                    'email'             => $email,
                    'phone'             => $phone,
                    'monthly_fee_pence' => $item['amount_pence'],
                    'status'            => 'pending_payment',
                ] );
                return (int) $wpdb->insert_id;

            case 'professional_service':
            case 'service':
                $st = YNJ_DB::table( 'services' );
                $wpdb->insert( $st, [
                    'mosque_id'         => $mosque_id,
                    'provider_name'     => $name,
                    'service_type'      => sanitize_text_field( $meta['service_type'] ?? '' ),
                    'email'             => $email,
                    'phone'             => $phone,
                    'monthly_fee_pence' => $item['amount_pence'],
                    'status'            => 'pending_payment',
                ] );
                return (int) $wpdb->insert_id;

            default:
                // donation, sadaqah, store, tip, event_donation — no pre-provisioning needed
                return 0;
        }
    }

    /**
     * POST /unified-checkout/confirm
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

                    // Re-fetch with updated status
                    $txn = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $txn->id ) );
                    self::fire_item_hooks( $txn );

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
     * Called from YNJ_API_Stripe when metadata.type === 'ynj_unified_checkout'.
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

        // Re-fetch
        $txn = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $txn->id ) );
        self::fire_item_hooks( $txn );

        error_log( '[YNJ UC] Webhook confirmed transaction: ' . $txn_id_str );
    }

    /**
     * Fire per-item hooks after payment succeeds.
     * Parses items_json, updates domain tables, fires hooks, records ledger.
     */
    private static function fire_item_hooks( $txn ) {
        if ( ! $txn ) return;

        // Fire the unified hook (store plugin listens to this)
        do_action( 'ynj_unified_payment_succeeded', $txn->id, $txn );

        // Parse items
        $items = [];
        if ( ! empty( $txn->items_json ) ) {
            $decoded = json_decode( $txn->items_json, true );
            if ( is_array( $decoded ) ) {
                // Check if it's a list of items or legacy metadata object
                if ( isset( $decoded[0] ) && isset( $decoded[0]['item_type'] ) ) {
                    $items = $decoded;
                }
            }
        }

        // If no items_json array, treat the transaction itself as a single item
        if ( empty( $items ) ) {
            $items[] = [
                'item_type'    => $txn->item_type,
                'item_id'      => (int) $txn->item_id,
                'item_label'   => $txn->item_label,
                'mosque_id'    => (int) $txn->mosque_id,
                'amount_pence' => (int) $txn->amount_pence,
                'fund_type'    => $txn->fund_type,
                'frequency'    => $txn->frequency,
            ];
        }

        global $wpdb;

        foreach ( $items as $item ) {
            $type      = $item['item_type'] ?? '';
            $item_id   = (int) ( $item['item_id'] ?? 0 );
            $mosque_id = (int) ( $item['mosque_id'] ?? $txn->mosque_id );
            $amount    = (int) ( $item['amount_pence'] ?? 0 );
            $fund_type = $item['fund_type'] ?? 'general';

            switch ( $type ) {
                case 'donation':
                case 'sadaqah':
                case 'platform_donate':
                    // Increment fund raised
                    if ( $mosque_id && $fund_type ) {
                        $ft = YNJ_DB::table( 'mosque_funds' );
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE $ft SET raised_pence = raised_pence + %d WHERE mosque_id = %d AND slug = %s",
                            $amount, $mosque_id, $fund_type
                        ) );
                    }
                    do_action( 'ynj_donation_succeeded', $item_id ?: (int) $txn->id, $txn );
                    break;

                case 'patron':
                    if ( $item_id ) {
                        $pt = YNJ_DB::table( 'patrons' );
                        $wpdb->update( $pt, [
                            'status'     => 'active',
                            'started_at' => current_time( 'mysql', true ),
                        ], [ 'id' => $item_id ] );

                        $patron = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $pt WHERE id = %d", $item_id ) );
                        if ( $patron ) {
                            do_action( 'ynj_new_patron', $mosque_id, [
                                'user_name'    => $patron->user_name,
                                'tier'         => $patron->tier,
                                'amount_pence' => (int) $patron->amount_pence,
                            ] );
                        }
                    }
                    break;

                case 'store':
                    // Store hook is handled by ynj_unified_payment_succeeded listener
                    break;

                case 'event_ticket':
                    if ( $item_id ) {
                        $wpdb->update( YNJ_DB::table( 'bookings' ), [ 'status' => 'confirmed' ], [ 'id' => $item_id ] );
                    }
                    break;

                case 'event_donation':
                    $event_id = (int) ( $item['meta']['event_id'] ?? $item_id );
                    if ( $event_id ) {
                        $et = YNJ_DB::table( 'events' );
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE $et SET donation_raised_pence = donation_raised_pence + %d, donation_count = donation_count + 1 WHERE id = %d",
                            $amount, $event_id
                        ) );
                    }
                    break;

                case 'room_booking':
                    if ( $item_id ) {
                        $wpdb->update( YNJ_DB::table( 'bookings' ), [ 'status' => 'confirmed' ], [ 'id' => $item_id ] );
                        do_action( 'ynj_new_booking', $mosque_id, [
                            'user_name'  => $txn->donor_name,
                            'user_email' => $txn->donor_email,
                            'room_id'    => absint( $item['meta']['room_id'] ?? 0 ),
                        ] );
                    }
                    break;

                case 'class_enrolment':
                    if ( $item_id ) {
                        $et = YNJ_DB::table( 'enrolments' );
                        $wpdb->update( $et, [ 'status' => 'confirmed' ], [ 'id' => $item_id ] );
                        $enrol = $wpdb->get_row( $wpdb->prepare( "SELECT class_id FROM $et WHERE id = %d", $item_id ) );
                        if ( $enrol ) {
                            $wpdb->query( $wpdb->prepare(
                                "UPDATE " . YNJ_DB::table( 'classes' ) . " SET enrolled_count = enrolled_count + 1 WHERE id = %d",
                                $enrol->class_id
                            ) );
                        }
                    }
                    break;

                case 'business_sponsor':
                case 'sponsor':
                    if ( $item_id ) {
                        $wpdb->update( YNJ_DB::table( 'businesses' ), [
                            'status'   => 'pending_review',
                            'verified' => 0,
                        ], [ 'id' => $item_id ] );
                        do_action( 'ynj_payment_received', $mosque_id, 'business_sponsor', $item_id );
                        // Mosque admin will review and approve via dashboard
                    }
                    break;

                case 'professional_service':
                case 'service':
                    if ( $item_id ) {
                        $wpdb->update( YNJ_DB::table( 'services' ), [ 'status' => 'active' ], [ 'id' => $item_id ] );
                        do_action( 'ynj_payment_received', $mosque_id, 'professional_service', $item_id );
                    }
                    break;
            }

            // Pool ledger entry for each item
            if ( $mosque_id && $amount > 0 && class_exists( 'YNJ_Pool_Ledger' ) ) {
                YNJ_Pool_Ledger::record( [
                    'mosque_id'              => $mosque_id,
                    'entry_type'             => ( $txn->frequency && $txn->frequency !== 'once' ) ? 'recurring' : 'payment',
                    'payment_type'           => $type,
                    'item_id'                => $item_id,
                    'gross_pence'            => $amount,
                    'stripe_payment_id'      => $txn->stripe_payment_intent ?? '',
                    'stripe_subscription_id' => '',
                    'payer_name'             => $txn->donor_name ?? '',
                    'payer_email'            => $txn->donor_email ?? '',
                    'description'            => $item['item_label'] ?? ucfirst( str_replace( '_', ' ', $type ) ),
                ] );
            }
        }

        // Send email receipt
        self::send_receipt( $txn );
    }

    /**
     * Send a simple email receipt after successful payment.
     */
    private static function send_receipt( $txn ) {
        if ( ! $txn || ! $txn->donor_email ) return;

        $is_cash = strpos( $txn->stripe_payment_intent ?? '', 'test_' ) === 0;
        $amount = '£' . number_format( $txn->total_pence / 100, 2 );
        $label = $txn->item_label ?: ucfirst( str_replace( '_', ' ', $txn->item_type ) );
        $freq = $txn->frequency && $txn->frequency !== 'once' ? ' (' . ucfirst( $txn->frequency ) . ')' : '';

        $mosque_name = '';
        if ( $txn->mosque_id ) {
            global $wpdb;
            $mosque_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $txn->mosque_id
            ) ) ?: '';
        }

        $subject = 'JazakAllah Khayr — ' . $label . ' ' . $amount;
        $body = "Assalamu Alaikum" . ( $txn->donor_name ? ' ' . $txn->donor_name : '' ) . ",\n\n";
        $body .= "Your contribution has been confirmed:\n\n";
        $body .= "  " . $label . $freq . "\n";
        $body .= "  Amount: " . $amount . "\n";
        if ( $mosque_name ) $body .= "  Masjid: " . $mosque_name . "\n";
        $body .= "  Transaction: " . $txn->transaction_id . "\n";
        $body .= "  Date: " . ( $txn->completed_at ?: $txn->created_at ) . "\n";
        if ( $is_cash ) $body .= "  Method: Cash\n";
        $body .= "\nMay Allah accept it from you and multiply your reward.\n\n";
        $body .= "— YourJannah\n";
        $body .= home_url( '/' );

        wp_mail( $txn->donor_email, $subject, $body );
    }
}
