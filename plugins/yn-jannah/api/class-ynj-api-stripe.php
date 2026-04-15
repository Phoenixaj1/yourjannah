<?php
/**
 * YourJannah — REST API: Stripe endpoints.
 * Handles webhook events and checkout session creation.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Stripe {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /stripe/webhook — Stripe webhook handler
        register_rest_route( self::NS, '/stripe/webhook', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_webhook' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /stripe/checkout/business — Create business sponsor checkout
        register_rest_route( self::NS, '/stripe/checkout/business', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_business' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /stripe/checkout/service — Create professional service checkout
        register_rest_route( self::NS, '/stripe/checkout/service', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_service' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /stripe/checkout/room — Create room booking checkout
        register_rest_route( self::NS, '/stripe/checkout/room', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_room' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /stripe/checkout/event — Create event ticket checkout
        register_rest_route( self::NS, '/stripe/checkout/event', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_event' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /stripe/checkout/class — Create class enrolment checkout
        register_rest_route( self::NS, '/stripe/checkout/class', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout_class' ],
            'permission_callback' => '__return_true',
        ] );
    }

    // ================================================================
    // WEBHOOK HANDLER
    // ================================================================

    public static function handle_webhook( \WP_REST_Request $request ) {
        $payload = $request->get_body();
        $sig     = $request->get_header( 'stripe-signature' );

        if ( ! $sig ) {
            return new \WP_REST_Response( [ 'error' => 'Missing signature.' ], 400 );
        }

        $event = YNJ_Stripe::verify_webhook( $payload, $sig );
        if ( is_wp_error( $event ) ) {
            error_log( '[YNJ Webhook] Verification failed: ' . $event->get_error_message() );
            return new \WP_REST_Response( [ 'error' => $event->get_error_message() ], 400 );
        }

        $type = $event->type;
        error_log( "[YNJ Webhook] Event: $type" );

        switch ( $type ) {
            case 'checkout.session.completed':
                self::on_checkout_completed( $event->data->object );
                break;

            case 'invoice.paid':
                self::on_invoice_paid( $event->data->object );
                break;

            case 'invoice.payment_failed':
                self::on_invoice_failed( $event->data->object );
                break;

            case 'customer.subscription.deleted':
                self::on_subscription_deleted( $event->data->object );
                break;
        }

        return new \WP_REST_Response( [ 'received' => true ] );
    }

    /**
     * Handle successful checkout — activate the business/service/booking.
     */
    private static function on_checkout_completed( $session ) {
        $meta = $session->metadata ?? (object) [];
        $type = $meta->type ?? '';
        $item_id = (int) ( $meta->item_id ?? 0 );

        if ( ! $type || ! $item_id ) return;

        global $wpdb;

        switch ( $type ) {
            case 'business_sponsor':
                $table = YNJ_DB::table( 'businesses' );
                $wpdb->update( $table, [
                    'status'                 => 'active',
                    'verified'               => 1,
                    'stripe_customer_id'     => $session->customer ?? '',
                    'stripe_subscription_id' => $session->subscription ?? '',
                    'expires_at'             => null, // Active subscription, no expiry
                ], [ 'id' => $item_id ] );
                error_log( "[YNJ Webhook] Business #$item_id activated." );
                $biz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $item_id ) );
                if ( $biz ) do_action( 'ynj_payment_received', (int) $biz->mosque_id, 'business_sponsor', $item_id );
                break;

            case 'professional_service':
                $table = YNJ_DB::table( 'services' );
                $wpdb->update( $table, [
                    'status'                 => 'active',
                    'stripe_subscription_id' => $session->subscription ?? '',
                ], [ 'id' => $item_id ] );
                error_log( "[YNJ Webhook] Service #$item_id activated." );
                $svc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $item_id ) );
                if ( $svc ) do_action( 'ynj_payment_received', (int) $svc->mosque_id, 'professional_service', $item_id );
                break;

            case 'room_booking':
                $table = YNJ_DB::table( 'bookings' );
                $wpdb->update( $table, [
                    'status' => 'confirmed',
                ], [ 'id' => $item_id ] );
                error_log( "[YNJ Webhook] Room booking #$item_id confirmed." );
                break;

            case 'event_ticket':
                $table = YNJ_DB::table( 'bookings' );
                $wpdb->update( $table, [
                    'status' => 'confirmed',
                ], [ 'id' => $item_id ] );
                error_log( "[YNJ Webhook] Event booking #$item_id confirmed." );
                break;

            case 'class_enrolment':
                $et = YNJ_DB::table( 'enrolments' );
                $wpdb->update( $et, [ 'status' => 'confirmed' ], [ 'id' => $item_id ] );
                // Increment enrolled_count
                $enrol = $wpdb->get_row( $wpdb->prepare( "SELECT class_id FROM $et WHERE id = %d", $item_id ) );
                if ( $enrol ) {
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE " . YNJ_DB::table( 'classes' ) . " SET enrolled_count = enrolled_count + 1 WHERE id = %d",
                        $enrol->class_id
                    ) );
                }
                error_log( "[YNJ Webhook] Class enrolment #$item_id confirmed." );
                break;

            case 'event_donation':
                $table = YNJ_DB::table( 'events' );
                $amount = (int) ( $session->amount_total ?? 0 );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE $table SET donation_raised_pence = donation_raised_pence + %d, donation_count = donation_count + 1 WHERE id = %d",
                    $amount, $item_id
                ) );
                error_log( "[YNJ Webhook] Event #$item_id donation: +£" . number_format( $amount / 100, 2 ) );
                break;

            case 'madrassah_fee':
                $ft = YNJ_DB::table( 'madrassah_fees' );
                $wpdb->update( $ft, [
                    'status'                => 'paid',
                    'paid_at'               => current_time( 'mysql', true ),
                    'stripe_payment_intent' => $session->payment_intent ?? '',
                ], [ 'id' => $item_id ] );
                error_log( "[YNJ Webhook] Madrassah fee #$item_id paid." );
                break;

            case 'patron_membership':
                $pt = YNJ_DB::table( 'patrons' );
                $wpdb->update( $pt, [
                    'status'                 => 'active',
                    'stripe_customer_id'     => $session->customer ?? '',
                    'stripe_subscription_id' => $session->subscription ?? '',
                    'started_at'             => current_time( 'mysql', true ),
                ], [ 'id' => $item_id ] );
                error_log( "[YNJ Webhook] Patron #$item_id activated." );
                $patron = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $pt WHERE id = %d", $item_id ) );
                if ( $patron ) {
                    do_action( 'ynj_new_patron', (int) $patron->mosque_id, [
                        'user_name'    => $patron->user_name,
                        'tier'         => $patron->tier,
                        'amount_pence' => (int) $patron->amount_pence,
                    ] );
                }
                break;
        }
    }

    /**
     * Handle recurring invoice paid — keep subscription active.
     */
    private static function on_invoice_paid( $invoice ) {
        $sub_id = $invoice->subscription ?? '';
        if ( ! $sub_id ) return;

        global $wpdb;

        // Check businesses
        $biz_table = YNJ_DB::table( 'businesses' );
        $biz = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $biz_table WHERE stripe_subscription_id = %s", $sub_id
        ) );
        if ( $biz ) {
            $wpdb->update( $biz_table, [ 'status' => 'active', 'expires_at' => null ], [ 'id' => $biz->id ] );
            return;
        }

        // Check services
        $svc_table = YNJ_DB::table( 'services' );
        $svc = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $svc_table WHERE stripe_subscription_id = %s", $sub_id
        ) );
        if ( $svc ) {
            $wpdb->update( $svc_table, [ 'status' => 'active' ], [ 'id' => $svc->id ] );
            return;
        }

        // Check patrons
        $patron_table = YNJ_DB::table( 'patrons' );
        $patron = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $patron_table WHERE stripe_subscription_id = %s", $sub_id
        ) );
        if ( $patron ) {
            $wpdb->update( $patron_table, [ 'status' => 'active' ], [ 'id' => $patron->id ] );
        }
    }

    /**
     * Handle failed invoice — flag the listing.
     */
    private static function on_invoice_failed( $invoice ) {
        $sub_id = $invoice->subscription ?? '';
        if ( ! $sub_id ) return;

        global $wpdb;

        $biz_table = YNJ_DB::table( 'businesses' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE $biz_table SET status = 'payment_failed' WHERE stripe_subscription_id = %s", $sub_id
        ) );

        $svc_table = YNJ_DB::table( 'services' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE $svc_table SET status = 'payment_failed' WHERE stripe_subscription_id = %s", $sub_id
        ) );

        $patron_table = YNJ_DB::table( 'patrons' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE $patron_table SET status = 'payment_failed' WHERE stripe_subscription_id = %s", $sub_id
        ) );
    }

    /**
     * Handle subscription cancellation — deactivate listing.
     */
    private static function on_subscription_deleted( $subscription ) {
        $sub_id = $subscription->id ?? '';
        if ( ! $sub_id ) return;

        global $wpdb;

        $biz_table = YNJ_DB::table( 'businesses' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE $biz_table SET status = 'expired' WHERE stripe_subscription_id = %s", $sub_id
        ) );

        $svc_table = YNJ_DB::table( 'services' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE $svc_table SET status = 'expired' WHERE stripe_subscription_id = %s", $sub_id
        ) );

        $patron_table = YNJ_DB::table( 'patrons' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE $patron_table SET status = 'cancelled', cancelled_at = %s WHERE stripe_subscription_id = %s",
            current_time( 'mysql', true ), $sub_id
        ) );
    }

    // ================================================================
    // CHECKOUT SESSION CREATORS
    // ================================================================

    /**
     * POST /stripe/checkout/business — Create business sponsor subscription checkout.
     */
    public static function checkout_business( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        if ( ! $mosque_id && ! empty( $data['mosque_slug'] ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $data['mosque_slug'] );
        }

        $name     = sanitize_text_field( $data['business_name'] ?? '' );
        $category = sanitize_text_field( $data['category'] ?? '' );
        $tier     = sanitize_text_field( $data['tier'] ?? 'standard' ); // standard|featured|premium

        if ( ! $mosque_id || ! $name ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque and business_name required.' ], 400 );
        }

        $tiers = [
            'standard' => [ 'amount' => 3000, 'label' => 'Standard Sponsor (£30/mo)', 'position' => 0 ],
            'featured' => [ 'amount' => 5000, 'label' => 'Featured Sponsor (£50/mo)', 'position' => 1 ],
            'premium'  => [ 'amount' => 10000, 'label' => 'Premium Sponsor (£100/mo)', 'position' => 2 ],
        ];

        $tier_config = $tiers[ $tier ] ?? $tiers['standard'];

        // Insert business as pending
        global $wpdb;
        $table = YNJ_DB::table( 'businesses' );

        $wpdb->insert( $table, [
            'mosque_id'         => $mosque_id,
            'business_name'     => $name,
            'owner_name'        => sanitize_text_field( $data['owner_name'] ?? '' ),
            'category'          => $category,
            'description'       => sanitize_textarea_field( $data['description'] ?? '' ),
            'phone'             => sanitize_text_field( $data['phone'] ?? '' ),
            'email'             => sanitize_email( $data['email'] ?? '' ),
            'website'           => esc_url_raw( $data['website'] ?? '' ),
            'address'           => sanitize_text_field( $data['address'] ?? '' ),
            'postcode'          => sanitize_text_field( $data['postcode'] ?? '' ),
            'monthly_fee_pence' => $tier_config['amount'],
            'featured_position' => $tier_config['position'],
            'status'            => 'pending_payment',
        ] );

        $biz_id = (int) $wpdb->insert_id;
        if ( ! $biz_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create listing.' ], 500 );
        }

        // Notify mosque admin
        do_action( 'ynj_new_sponsor', $mosque_id, [
            'business_name'    => $name,
            'category'         => $category,
            'tier'             => $tier,
            'monthly_fee_pence' => $tier_config['amount'],
            'phone'            => sanitize_text_field( $data['phone'] ?? '' ),
        ] );

        // Get mosque slug for redirect URLs
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) );
        $base = home_url( "/mosque/" . ( $mosque->slug ?? '' ) );

        $session = YNJ_Stripe::create_subscription(
            'business_sponsor',
            $biz_id,
            $tier_config['amount'],
            $tier_config['label'] . ' — ' . $name,
            $base . '/sponsors?payment=success',
            $base . '/sponsors?payment=cancelled',
            [ 'mosque_id' => $mosque_id, 'tier' => $tier ]
        );

        if ( is_wp_error( $session ) ) {
            // Clean up the pending record
            $wpdb->delete( $table, [ 'id' => $biz_id ] );
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'           => true,
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
            'business_id'  => $biz_id,
        ] );
    }

    /**
     * POST /stripe/checkout/service — Create professional service subscription checkout.
     */
    public static function checkout_service( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        if ( ! $mosque_id && ! empty( $data['mosque_slug'] ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $data['mosque_slug'] );
        }

        $provider = sanitize_text_field( $data['provider_name'] ?? '' );
        $type     = sanitize_text_field( $data['service_type'] ?? '' );

        if ( ! $mosque_id || ! $provider || ! $type ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque, provider_name, and service_type required.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'services' );

        $wpdb->insert( $table, [
            'mosque_id'        => $mosque_id,
            'provider_name'    => $provider,
            'service_type'     => $type,
            'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
            'phone'            => sanitize_text_field( $data['phone'] ?? '' ),
            'email'            => sanitize_email( $data['email'] ?? '' ),
            'area_covered'     => sanitize_text_field( $data['area_covered'] ?? '' ),
            'monthly_fee_pence' => 1000,
            'status'           => 'pending_payment',
        ] );

        $svc_id = (int) $wpdb->insert_id;
        if ( ! $svc_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create listing.' ], 500 );
        }

        // Notify mosque admin
        do_action( 'ynj_new_service_listing', $mosque_id, [
            'provider_name' => $provider,
            'service_type'  => $type,
            'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
            'area_covered'  => sanitize_text_field( $data['area_covered'] ?? '' ),
        ] );

        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) );
        $base = home_url( "/mosque/" . ( $mosque->slug ?? '' ) );

        $session = YNJ_Stripe::create_subscription(
            'professional_service',
            $svc_id,
            1000,
            'Professional Service Listing (£10/mo) — ' . $provider,
            $base . '/services?payment=success',
            $base . '/services?payment=cancelled',
            [ 'mosque_id' => $mosque_id ]
        );

        if ( is_wp_error( $session ) ) {
            $wpdb->delete( $table, [ 'id' => $svc_id ] );
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'           => true,
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
            'service_id'   => $svc_id,
        ] );
    }

    /**
     * POST /stripe/checkout/room — Create room booking checkout.
     */
    public static function checkout_room( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $room_id = absint( $data['room_id'] ?? 0 );
        if ( ! $room_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'room_id required.' ], 400 );
        }

        global $wpdb;
        $room_table = YNJ_DB::table( 'rooms' );
        $room = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $room_table WHERE id = %d AND status = 'active'", $room_id
        ) );

        if ( ! $room ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Room not found.' ], 404 );
        }

        // Calculate price
        $hours = absint( $data['hours'] ?? 1 );
        $amount = $room->hourly_rate_pence * $hours;

        if ( $amount <= 0 ) {
            // Free room — create booking directly
            $book_table = YNJ_DB::table( 'bookings' );
            $wpdb->insert( $book_table, [
                'mosque_id'    => $room->mosque_id,
                'room_id'      => $room_id,
                'user_name'    => sanitize_text_field( $data['user_name'] ?? '' ),
                'user_email'   => sanitize_email( $data['user_email'] ?? '' ),
                'user_phone'   => sanitize_text_field( $data['user_phone'] ?? '' ),
                'booking_date' => sanitize_text_field( $data['booking_date'] ?? '' ),
                'start_time'   => sanitize_text_field( $data['start_time'] ?? '' ),
                'end_time'     => sanitize_text_field( $data['end_time'] ?? '' ),
                'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
                'status'       => 'confirmed',
            ] );

            // Notify mosque admin of new booking
            do_action( 'ynj_new_booking', (int) $room->mosque_id, [
                'user_name'    => sanitize_text_field( $data['user_name'] ?? '' ),
                'user_email'   => sanitize_email( $data['user_email'] ?? '' ),
                'room_id'      => $room_id,
                'booking_date' => sanitize_text_field( $data['booking_date'] ?? '' ),
                'start_time'   => sanitize_text_field( $data['start_time'] ?? '' ),
                'end_time'     => sanitize_text_field( $data['end_time'] ?? '' ),
                'notes'        => $room->name . ' (free room booking)',
            ] );

            return new \WP_REST_Response( [
                'ok'         => true,
                'free'       => true,
                'booking_id' => (int) $wpdb->insert_id,
                'message'    => 'Room booked successfully (free).',
            ], 201 );
        }

        // Paid room — create pending booking + Stripe checkout
        $book_table = YNJ_DB::table( 'bookings' );
        $wpdb->insert( $book_table, [
            'mosque_id'    => $room->mosque_id,
            'room_id'      => $room_id,
            'user_name'    => sanitize_text_field( $data['user_name'] ?? '' ),
            'user_email'   => sanitize_email( $data['user_email'] ?? '' ),
            'user_phone'   => sanitize_text_field( $data['user_phone'] ?? '' ),
            'booking_date' => sanitize_text_field( $data['booking_date'] ?? '' ),
            'start_time'   => sanitize_text_field( $data['start_time'] ?? '' ),
            'end_time'     => sanitize_text_field( $data['end_time'] ?? '' ),
            'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
            'status'       => 'pending_payment',
        ] );

        $booking_id = (int) $wpdb->insert_id;

        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $room->mosque_id
        ) );
        $base = home_url( "/mosque/" . ( $mosque->slug ?? '' ) );

        $session = YNJ_Stripe::create_checkout(
            'room_booking',
            $booking_id,
            $amount,
            $room->name . ' — ' . $hours . 'hr' . ( $hours > 1 ? 's' : '' ),
            $base . '/rooms?payment=success',
            $base . '/rooms?payment=cancelled',
            [ 'mosque_id' => $room->mosque_id, 'room_id' => $room_id ]
        );

        if ( is_wp_error( $session ) ) {
            $wpdb->delete( $book_table, [ 'id' => $booking_id ] );
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'           => true,
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
            'booking_id'   => $booking_id,
        ] );
    }

    /**
     * POST /stripe/checkout/event — Create event ticket checkout.
     */
    public static function checkout_event( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $event_id = absint( $data['event_id'] ?? 0 );
        if ( ! $event_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'event_id required.' ], 400 );
        }

        global $wpdb;
        $event_table = YNJ_DB::table( 'events' );
        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $event_table WHERE id = %d AND status = 'published'", $event_id
        ) );

        if ( ! $event ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event not found.' ], 404 );
        }

        // Check capacity
        if ( $event->max_capacity > 0 && $event->registered_count >= $event->max_capacity ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event is fully booked.' ], 409 );
        }

        // Create booking
        $book_table = YNJ_DB::table( 'bookings' );
        $wpdb->insert( $book_table, [
            'mosque_id'    => $event->mosque_id,
            'event_id'     => $event_id,
            'user_name'    => sanitize_text_field( $data['user_name'] ?? '' ),
            'user_email'   => sanitize_email( $data['user_email'] ?? '' ),
            'user_phone'   => sanitize_text_field( $data['user_phone'] ?? '' ),
            'booking_date' => $event->event_date,
            'start_time'   => $event->start_time,
            'end_time'     => $event->end_time,
            'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
            'status'       => $event->ticket_price_pence > 0 ? 'pending_payment' : 'confirmed',
        ] );

        $booking_id = (int) $wpdb->insert_id;

        // Increment registered count
        $wpdb->query( $wpdb->prepare(
            "UPDATE $event_table SET registered_count = registered_count + 1 WHERE id = %d", $event_id
        ) );

        if ( $event->ticket_price_pence <= 0 ) {
            // Notify mosque admin
            do_action( 'ynj_new_booking', (int) $event->mosque_id, [
                'user_name'    => sanitize_text_field( $data['user_name'] ?? '' ),
                'user_email'   => sanitize_email( $data['user_email'] ?? '' ),
                'event_id'     => $event_id,
                'booking_date' => $event->event_date,
                'start_time'   => $event->start_time,
                'end_time'     => $event->end_time,
                'notes'        => $event->title,
            ] );

            return new \WP_REST_Response( [
                'ok'         => true,
                'free'       => true,
                'booking_id' => $booking_id,
                'message'    => 'RSVP confirmed! See you there.',
            ], 201 );
        }

        // Paid event — Stripe checkout
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $event->mosque_id
        ) );
        $base = home_url( "/mosque/" . ( $mosque->slug ?? '' ) );

        $session = YNJ_Stripe::create_checkout(
            'event_ticket',
            $booking_id,
            $event->ticket_price_pence,
            'Event Ticket: ' . $event->title,
            $base . '/events?payment=success',
            $base . '/events?payment=cancelled',
            [ 'mosque_id' => $event->mosque_id, 'event_id' => $event_id ]
        );

        if ( is_wp_error( $session ) ) {
            // Don't delete booking, just leave as pending
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'           => true,
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
            'booking_id'   => $booking_id,
        ] );
    }

    /**
     * POST /stripe/checkout/class — Create class enrolment checkout.
     */
    public static function checkout_class( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $class_id = absint( $data['class_id'] ?? 0 );
        if ( ! $class_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'class_id required.' ], 400 );
        }

        global $wpdb;
        $class_table = YNJ_DB::table( 'classes' );
        $class = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $class_table WHERE id = %d AND status = 'published'", $class_id
        ) );

        if ( ! $class ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Class not found.' ], 404 );
        }

        // Check capacity
        if ( $class->max_capacity > 0 && $class->enrolled_count >= $class->max_capacity ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Class is full.' ], 409 );
        }

        $user_name  = sanitize_text_field( $data['user_name'] ?? '' );
        $user_email = sanitize_email( $data['user_email'] ?? '' );

        if ( ! $user_name || ! is_email( $user_email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Name and valid email required.' ], 400 );
        }

        // Create enrolment record
        $enrol_table = YNJ_DB::table( 'enrolments' );
        $wpdb->insert( $enrol_table, [
            'class_id'   => $class_id,
            'user_name'  => $user_name,
            'user_email' => $user_email,
            'user_phone' => sanitize_text_field( $data['user_phone'] ?? '' ),
            'status'     => $class->price_pence > 0 ? 'pending_payment' : 'confirmed',
        ] );
        $enrolment_id = (int) $wpdb->insert_id;

        // Increment enrolled count
        $wpdb->query( $wpdb->prepare(
            "UPDATE $class_table SET enrolled_count = enrolled_count + 1 WHERE id = %d", $class_id
        ) );

        if ( $class->price_pence <= 0 ) {
            return new \WP_REST_Response( [
                'ok'           => true,
                'free'         => true,
                'enrolment_id' => $enrolment_id,
            ] );
        }

        // Get mosque for Stripe account
        $mosque_table = YNJ_DB::table( 'mosques' );
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $mosque_table WHERE id = %d", $class->mosque_id
        ) );

        $price_label = $class->price_type === 'per_session' ? '/session' : ( $class->price_type === 'monthly' ? '/month' : '' );
        $success_url = home_url( '/mosque/' . ( $mosque->slug ?? '' ) . '/classes?enrolled=1' );
        $cancel_url  = home_url( '/mosque/' . ( $mosque->slug ?? '' ) . '/classes' );

        $session = YNJ_Stripe::create_checkout(
            $class->price_pence,
            $class->title . ' — Class Enrolment' . $price_label,
            $success_url,
            $cancel_url,
            [
                'type'          => 'class_enrolment',
                'class_id'      => (string) $class_id,
                'enrolment_id'  => (string) $enrolment_id,
                'mosque_id'     => (string) $class->mosque_id,
                'user_email'    => $user_email,
            ]
        );

        if ( is_wp_error( $session ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'           => true,
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
            'enrolment_id' => $enrolment_id,
        ] );
    }
}
