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

        try {
            $stripe = YNJ_Stripe::client();

            $line_item = [
                'price_data' => [
                    'currency'     => 'gbp',
                    'unit_amount'  => $amount_pence,
                    'recurring'    => [ 'interval' => 'month' ],
                    'product_data' => [
                        'name'        => $description,
                        'description' => 'Sadaqah Jariyah — Helping mosques across the UK',
                    ],
                ],
                'quantity' => 1,
            ];

            $session_params = [
                'payment_method_types' => [ 'card' ],
                'mode'                 => 'subscription',
                'line_items'           => [ $line_item ],
                'success_url'          => home_url( '/sponsor-yourjannah?payment=success' ),
                'cancel_url'           => home_url( '/sponsor-yourjannah?payment=cancelled' ),
                'customer_email'       => $email,
                'metadata'             => [
                    'type'    => 'platform_sponsor',
                    'tier'    => $tier,
                    'name'    => $name,
                    'recurring' => 'monthly',
                ],
            ];

            $session = $stripe->checkout->sessions->create( $session_params );

            return new \WP_REST_Response( [
                'ok'           => true,
                'checkout_url' => $session->url,
            ] );

        } catch ( \Exception $e ) {
            return new \WP_REST_Response( [
                'ok'    => false,
                'error' => 'Checkout error: ' . $e->getMessage(),
            ], 500 );
        }
    }
}
