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

        // POST /sponsor-yj/checkout — Create Stripe checkout for platform sponsorship
        register_rest_route( self::NS, '/sponsor-yj/checkout', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'checkout' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );
    }

    /**
     * POST /sponsor-yj/checkout — Create Stripe session for platform sponsorship.
     */
    public static function checkout( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        $amount_pounds = max( 1, (int) ( $data['amount_pounds'] ?? 10 ) );
        $amount_pence  = $amount_pounds * 100;
        $tier          = sanitize_text_field( $data['tier'] ?? 'seed' );
        $one_off       = ! empty( $data['one_off'] );

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

        $description = $one_off
            ? "YourJannah Sponsorship — {$tier_label} (One-off)"
            : "YourJannah Sponsorship — {$tier_label} (Monthly)";

        try {
            $stripe = YNJ_Stripe::client();

            $line_item = [
                'price_data' => [
                    'currency'     => 'gbp',
                    'unit_amount'  => $amount_pence,
                    'product_data' => [
                        'name'        => $description,
                        'description' => 'Sadaqah Jariyah — Helping mosques across the UK',
                    ],
                ],
                'quantity' => 1,
            ];

            if ( ! $one_off ) {
                $line_item['price_data']['recurring'] = [ 'interval' => 'month' ];
            }

            $session_params = [
                'payment_method_types' => [ 'card' ],
                'mode'                 => $one_off ? 'payment' : 'subscription',
                'line_items'           => [ $line_item ],
                'success_url'          => home_url( '/sponsor-yourjannah?payment=success' ),
                'cancel_url'           => home_url( '/sponsor-yourjannah?payment=cancelled' ),
                'customer_email'       => $user->email,
                'metadata'             => [
                    'type'    => 'platform_sponsor',
                    'tier'    => $tier,
                    'user_id' => $user->id,
                    'one_off' => $one_off ? '1' : '0',
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
