<?php
/**
 * Checkout Data Layer — donation flows, recurring giving, tips, analytics.
 *
 * Donation types:
 * 1. One-off donation to a mosque (any amount, any fund)
 * 2. Recurring donation (daily/weekly/monthly to a mosque)
 * 3. Charitable cause donation (distributed through YourNiyyah to where most needed)
 * 4. Platform tip (support YourJannah if you love the platform)
 *
 * @package YNJ_Checkout
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Checkout {

    /**
     * Donation frequencies.
     */
    public static function get_frequencies() {
        return [
            'once'    => [ 'label' => 'One-time',  'icon' => '💝', 'stripe_interval' => null ],
            'daily'   => [ 'label' => 'Daily',     'icon' => '☀️', 'stripe_interval' => 'day' ],
            'weekly'  => [ 'label' => 'Weekly',    'icon' => '📅', 'stripe_interval' => 'week' ],
            'monthly' => [ 'label' => 'Monthly',   'icon' => '🗓️', 'stripe_interval' => 'month' ],
        ];
    }

    /**
     * Suggested amounts.
     */
    public static function get_suggested_amounts() {
        return [ 500, 1000, 2000, 5000, 10000 ]; // pence
    }

    /**
     * Charitable causes (distributed by YourNiyyah).
     */
    public static function get_causes() {
        return [
            'most_needed'   => [ 'label' => 'Where Most Needed',    'icon' => '🌍' ],
            'orphans'       => [ 'label' => 'Orphan Sponsorship',   'icon' => '👶' ],
            'education'     => [ 'label' => 'Islamic Education',    'icon' => '📚' ],
            'water'         => [ 'label' => 'Water Wells',          'icon' => '💧' ],
            'food'          => [ 'label' => 'Food Aid',             'icon' => '🍞' ],
            'emergency'     => [ 'label' => 'Emergency Relief',     'icon' => '🚨' ],
            'masjid_build'  => [ 'label' => 'Build a Masjid',       'icon' => '🕌' ],
        ];
    }

    /**
     * API: Create a one-off donation checkout.
     */
    public static function api_donate( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $amount_pence = absint( $data['amount_pence'] ?? 0 );
        $mosque_id    = absint( $data['mosque_id'] ?? 0 );
        $fund_type    = sanitize_text_field( $data['fund_type'] ?? 'general' );
        $donor_name   = sanitize_text_field( $data['name'] ?? '' );
        $donor_email  = sanitize_email( $data['email'] ?? '' );
        $cause        = sanitize_text_field( $data['cause'] ?? '' );

        if ( $amount_pence < 100 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Minimum donation is £1.' ], 400 );
        }

        // Record in DB
        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'donations' ), [
            'mosque_id'    => $mosque_id,
            'donor_name'   => $donor_name,
            'donor_email'  => $donor_email,
            'amount_pence' => $amount_pence,
            'fund_type'    => $fund_type ?: ( $cause ?: 'general' ),
            'status'       => 'pending',
            'payment_type' => 'one_off',
        ] );
        $donation_id = $wpdb->insert_id;

        // Create Stripe checkout
        if ( class_exists( 'YNJ_Stripe' ) && method_exists( 'YNJ_Stripe', 'init' ) ) {
            YNJ_Stripe::init();

            try {
                $mosque_name = $mosque_id ? $wpdb->get_var( $wpdb->prepare(
                    "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
                ) ) : 'YourJannah';

                $description = $cause
                    ? 'Charitable donation — ' . ( self::get_causes()[ $cause ]['label'] ?? $cause )
                    : 'Donation to ' . ( $mosque_name ?: 'YourJannah' );

                $session = \Stripe\Checkout\Session::create( [
                    'payment_method_types' => [ 'card' ],
                    'mode'                 => 'payment',
                    'line_items'           => [ [
                        'price_data' => [
                            'currency'     => 'gbp',
                            'unit_amount'  => $amount_pence,
                            'product_data' => [ 'name' => $description ],
                        ],
                        'quantity' => 1,
                    ] ],
                    'success_url' => home_url( '/donate/thank-you?donation_id=' . $donation_id ),
                    'cancel_url'  => home_url( '/donate?cancelled=1' ),
                    'metadata'    => [
                        'donation_id' => $donation_id,
                        'mosque_id'   => $mosque_id,
                        'fund_type'   => $fund_type,
                        'cause'       => $cause,
                        'type'        => 'mosque_donation',
                    ],
                ] );

                return new \WP_REST_Response( [ 'ok' => true, 'checkout_url' => $session->url ] );
            } catch ( \Exception $e ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => $e->getMessage() ], 500 );
            }
        }

        return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe not configured.' ], 500 );
    }

    /**
     * API: Create recurring donation checkout.
     */
    public static function api_recurring( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $amount_pence = absint( $data['amount_pence'] ?? 0 );
        $mosque_id    = absint( $data['mosque_id'] ?? 0 );
        $frequency    = sanitize_text_field( $data['frequency'] ?? 'monthly' );
        $donor_email  = sanitize_email( $data['email'] ?? '' );

        $freqs = self::get_frequencies();
        if ( ! isset( $freqs[ $frequency ] ) || ! $freqs[ $frequency ]['stripe_interval'] ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid frequency.' ], 400 );
        }
        if ( $amount_pence < 100 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Minimum is £1.' ], 400 );
        }

        if ( class_exists( 'YNJ_Stripe' ) ) {
            YNJ_Stripe::init();
            try {
                global $wpdb;
                $mosque_name = $mosque_id ? $wpdb->get_var( $wpdb->prepare(
                    "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
                ) ) : 'YourJannah';

                $session = \Stripe\Checkout\Session::create( [
                    'payment_method_types' => [ 'card' ],
                    'mode'                 => 'subscription',
                    'line_items'           => [ [
                        'price_data' => [
                            'currency'   => 'gbp',
                            'unit_amount' => $amount_pence,
                            'recurring'  => [ 'interval' => $freqs[ $frequency ]['stripe_interval'] ],
                            'product_data' => [ 'name' => $freqs[ $frequency ]['label'] . ' donation to ' . ( $mosque_name ?: 'YourJannah' ) ],
                        ],
                        'quantity' => 1,
                    ] ],
                    'success_url' => home_url( '/donate/thank-you?recurring=1' ),
                    'cancel_url'  => home_url( '/donate?cancelled=1' ),
                    'metadata'    => [
                        'mosque_id' => $mosque_id,
                        'frequency' => $frequency,
                        'type'      => 'recurring_donation',
                    ],
                ] );

                return new \WP_REST_Response( [ 'ok' => true, 'checkout_url' => $session->url ] );
            } catch ( \Exception $e ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => $e->getMessage() ], 500 );
            }
        }

        return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe not configured.' ], 500 );
    }

    /**
     * API: Platform tip (support YourJannah).
     */
    public static function api_tip( \WP_REST_Request $request ) {
        $data = $request->get_json_params();
        $amount_pence = absint( $data['amount_pence'] ?? 0 );

        if ( $amount_pence < 100 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Minimum tip is £1.' ], 400 );
        }

        if ( class_exists( 'YNJ_Stripe' ) ) {
            YNJ_Stripe::init();
            try {
                $session = \Stripe\Checkout\Session::create( [
                    'payment_method_types' => [ 'card' ],
                    'mode'                 => 'payment',
                    'line_items'           => [ [
                        'price_data' => [
                            'currency'     => 'gbp',
                            'unit_amount'  => $amount_pence,
                            'product_data' => [ 'name' => 'Tip for YourJannah — JazakAllah Khayr 💚' ],
                        ],
                        'quantity' => 1,
                    ] ],
                    'success_url' => home_url( '/?tip=jazakallah' ),
                    'cancel_url'  => home_url( '/' ),
                    'metadata'    => [ 'type' => 'platform_tip' ],
                ] );

                return new \WP_REST_Response( [ 'ok' => true, 'checkout_url' => $session->url ] );
            } catch ( \Exception $e ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => $e->getMessage() ], 500 );
            }
        }

        return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe not configured.' ], 500 );
    }

    /**
     * API: Get checkout analytics.
     */
    public static function api_analytics( \WP_REST_Request $request ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'donations' );

        $total_raised = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE status = 'succeeded'" );
        $total_donors = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT donor_email) FROM $dt WHERE status = 'succeeded'" );
        $total_donations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt WHERE status = 'succeeded'" );
        $this_month = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE status = 'succeeded' AND created_at >= %s",
            date( 'Y-m-01' )
        ) );

        return new \WP_REST_Response( [
            'ok' => true,
            'total_raised_pence'  => $total_raised,
            'total_raised'        => number_format( $total_raised / 100, 2 ),
            'total_donors'        => $total_donors,
            'total_donations'     => $total_donations,
            'this_month_pence'    => $this_month,
            'this_month'          => number_format( $this_month / 100, 2 ),
        ] );
    }
}
