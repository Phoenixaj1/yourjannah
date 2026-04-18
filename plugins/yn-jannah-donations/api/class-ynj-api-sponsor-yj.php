<?php
/**
 * YourJannah — REST API: Sponsor YourJannah (platform sponsorship).
 *
 * Handles Stripe checkout for sponsoring the YourJannah project.
 * This is different from mosque business sponsors — this funds the platform itself.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Sponsor_YJ {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /sponsor-yj/checkout — Create Stripe checkout (public, no auth)
        register_rest_route( self::NS, '/sponsor-yj/checkout', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * POST /sponsor-yj/checkout — Create Stripe session for platform sponsorship.
     */
    public static function checkout( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $amount_pounds = max( 1, (int) ( $data['amount_pounds'] ?? 50 ) );
        $amount_pence  = $amount_pounds * 100;
        $tier          = sanitize_text_field( $data['tier'] ?? 'tier_50' );
        $name          = sanitize_text_field( $data['name'] ?? '' );
        $email         = sanitize_email( $data['email'] ?? '' );

        if ( ! $email ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Email is required.' ], 400 );
        }

        if ( ! class_exists( 'YNJ_Stripe' ) || ! YNJ_Stripe::is_configured() ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe is not configured.' ], 500 );
        }

        $tier_labels = [
            'seed'             => 'Seed',
            'grow'             => 'Growth',
            'sadaqah_jariyah'  => 'Sadaqah Jariyah',
            'custom'           => 'Custom',
        ];
        $tier_label = $tier_labels[ $tier ] ?? 'Sponsor';

        $description = "YourJannah Sponsorship — {$tier_label} (Monthly)";

        $secret = YNJ_Stripe::secret_key();
        if ( ! $secret ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe not configured.' ], 500 );
        }

        $post_fields = [
            'mode'                        => 'subscription',
            'success_url'                 => home_url( '/sponsor-yourjannah?payment=success' ),
            'cancel_url'                  => home_url( '/sponsor-yourjannah?payment=cancelled' ),
            'customer_email'              => $email,
            'payment_method_types[0]'     => 'card',
            'line_items[0][price_data][currency]'                      => 'gbp',
            'line_items[0][price_data][unit_amount]'                   => $amount_pence,
            'line_items[0][price_data][recurring][interval]'           => 'month',
            'line_items[0][price_data][product_data][name]'            => $description,
            'line_items[0][price_data][product_data][description]'     => 'Sadaqah Jariyah — Helping mosques across the UK',
            'line_items[0][quantity]'      => 1,
            'metadata[type]'              => 'platform_sponsor',
            'metadata[tier]'              => $tier,
            'metadata[name]'              => $name,
            'metadata[recurring]'         => 'monthly',
        ];

        $ch = curl_init( 'https://api.stripe.com/v1/checkout/sessions' );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $secret . ':',
            CURLOPT_POSTFIELDS     => http_build_query( $post_fields ),
            CURLOPT_TIMEOUT        => 15,
        ] );
        $response = curl_exec( $ch );
        curl_close( $ch );

        $session = json_decode( $response, true );
        if ( ! empty( $session['url'] ) ) {
            return new \WP_REST_Response( [
                'ok'           => true,
                'checkout_url' => $session['url'],
            ] );
        }

        return new \WP_REST_Response( [
            'ok'    => false,
            'error' => 'Could not create checkout session.',
        ], 500 );
    }
}
