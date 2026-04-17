<?php
/**
 * YourJannah Stripe Integration
 *
 * Handles Stripe API calls for business subscriptions,
 * service listings, room bookings, and event tickets.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Stripe {

    /** @var bool */
    private static $initialized = false;

    /**
     * Initialize the Stripe SDK.
     */
    public static function init() {
        if ( self::$initialized ) return;

        $autoload = YNJ_DIR . 'vendor/autoload.php';
        if ( file_exists( $autoload ) ) {
            require_once $autoload;
        }

        $sk = self::secret_key();
        if ( $sk ) {
            \Stripe\Stripe::setApiKey( $sk );
            \Stripe\Stripe::setApiVersion( '2024-12-18.acacia' );
        }

        self::$initialized = true;
    }

    /**
     * Get the Stripe secret key.
     * Priority: wp_option → PHP constant
     */
    public static function secret_key() {
        $key = get_option( 'ynj_stripe_secret_key', '' )
            ?: get_option( 'yn_checkout_stripe_sk', '' );
        if ( $key ) return $key;
        if ( defined( 'YNJ_STRIPE_SK' ) ) return YNJ_STRIPE_SK;
        return '';
    }

    /**
     * Get the Stripe publishable key.
     * Priority: wp_option → PHP constant
     */
    public static function public_key() {
        $key = get_option( 'ynj_stripe_public_key', '' )
            ?: get_option( 'yn_checkout_stripe_pk', '' );
        if ( $key ) return $key;
        if ( defined( 'YNJ_STRIPE_PK' ) ) return YNJ_STRIPE_PK;
        return '';
    }

    /**
     * Auto-configure Stripe keys on first load if not set.
     * Admin must configure keys via Settings > Stripe.
     */
    public static function auto_configure() {
        if ( get_option( 'ynj_stripe_auto_configured' ) ) return;
        if ( self::secret_key() && self::public_key() ) {
            update_option( 'ynj_stripe_auto_configured', '1' );
            return;
        }
        // Keys must be configured via admin settings — no hardcoded defaults.
    }

    /**
     * Get the webhook signing secret.
     */
    public static function webhook_secret() {
        return get_option( 'ynj_stripe_webhook_secret', '' );
    }

    /**
     * Check if Stripe is configured.
     */
    public static function is_configured() {
        return ! empty( self::secret_key() ) && ! empty( self::public_key() );
    }

    // ================================================================
    // CHECKOUT SESSIONS
    // ================================================================

    /**
     * Create a one-off Stripe Checkout session (room bookings, event tickets).
     *
     * @param string $type       Item type: 'room_booking' | 'event_ticket'
     * @param int    $item_id    Booking or event ID
     * @param int    $amount     Amount in pence
     * @param string $name       Line item description
     * @param string $success    Success redirect URL
     * @param string $cancel     Cancel redirect URL
     * @param array  $metadata   Extra metadata
     * @return \Stripe\Checkout\Session|WP_Error
     */
    public static function create_checkout( $type, $item_id, $amount, $name, $success, $cancel, $metadata = [] ) {
        self::init();

        if ( ! self::is_configured() ) {
            return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
        }

        try {
            $session = \Stripe\Checkout\Session::create( [
                'mode'        => 'payment',
                'currency'    => 'gbp',
                'line_items'  => [ [
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => (int) $amount,
                        'product_data' => [ 'name' => $name ],
                    ],
                    'quantity' => 1,
                ] ],
                'metadata'    => array_merge( [
                    'type'    => $type,
                    'item_id' => $item_id,
                ], $metadata ),
                'success_url' => $success,
                'cancel_url'  => $cancel,
            ] );

            return $session;
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( '[YNJ Stripe] Checkout error: ' . $e->getMessage() );
            return new WP_Error( 'stripe_error', $e->getMessage() );
        }
    }

    /**
     * Create a subscription Checkout session (business sponsors, professional services).
     *
     * @param string $type       'business_sponsor' | 'professional_service'
     * @param int    $item_id    Business or service ID
     * @param int    $amount     Monthly amount in pence
     * @param string $name       Subscription description
     * @param string $success    Success redirect URL
     * @param string $cancel     Cancel redirect URL
     * @param array  $metadata   Extra metadata
     * @return \Stripe\Checkout\Session|WP_Error
     */
    public static function create_subscription( $type, $item_id, $amount, $name, $success, $cancel, $metadata = [] ) {
        self::init();

        if ( ! self::is_configured() ) {
            return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
        }

        try {
            $params = [
                'mode'        => 'subscription',
                'currency'    => 'gbp',
                'line_items'  => [ [
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => (int) $amount,
                        'product_data' => [ 'name' => $name ],
                        'recurring'    => [ 'interval' => 'month' ],
                    ],
                    'quantity' => 1,
                ] ],
                'metadata'    => array_merge( [
                    'type'    => $type,
                    'item_id' => $item_id,
                ], $metadata ),
                'success_url' => $success,
                'cancel_url'  => $cancel,
            ];

            // Revenue note: 90% goes to mosque, 10% platform fee.
            // Currently all payments go to YourJannah Stripe account.
            // Mosque share paid out manually until Stripe Connect is set up.

            $session = \Stripe\Checkout\Session::create( $params );

            return $session;
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( '[YNJ Stripe] Subscription error: ' . $e->getMessage() );
            return new WP_Error( 'stripe_error', $e->getMessage() );
        }
    }

    /**
     * Create a patron membership subscription checkout.
     *
     * @param int    $patron_id  Patron record ID
     * @param int    $amount     Monthly amount in pence
     * @param string $tier       Tier label (supporter/guardian/champion)
     * @param string $mosque     Mosque name for line item
     * @param string $success    Success redirect URL
     * @param string $cancel     Cancel redirect URL
     * @param array  $metadata   Extra metadata
     * @return \Stripe\Checkout\Session|WP_Error
     */
    public static function create_patron_subscription( $patron_id, $amount, $tier, $mosque, $success, $cancel, $metadata = [] ) {
        self::init();

        if ( ! self::is_configured() ) {
            return new WP_Error( 'stripe_not_configured', 'Stripe is not configured.' );
        }

        $tier_labels = [
            'supporter' => 'Supporter',
            'guardian'  => 'Guardian',
            'champion'  => 'Champion',
        ];
        $label = ( $tier_labels[ $tier ] ?? ucfirst( $tier ) ) . ' Patron — ' . $mosque;

        try {
            $session = \Stripe\Checkout\Session::create( [
                'mode'        => 'subscription',
                'currency'    => 'gbp',
                'line_items'  => [ [
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => (int) $amount,
                        'product_data' => [ 'name' => $label ],
                        'recurring'    => [ 'interval' => 'month' ],
                    ],
                    'quantity' => 1,
                ] ],
                'metadata'    => array_merge( [
                    'type'      => 'patron_membership',
                    'item_id'   => $patron_id,
                ], $metadata ),
                'success_url' => $success,
                'cancel_url'  => $cancel,
            ] );

            return $session;
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( '[YNJ Stripe] Patron subscription error: ' . $e->getMessage() );
            return new WP_Error( 'stripe_error', $e->getMessage() );
        }
    }

    /**
     * Cancel a subscription.
     *
     * @param string $subscription_id  Stripe subscription ID
     * @return bool|WP_Error
     */
    public static function cancel_subscription( $subscription_id ) {
        self::init();

        try {
            $sub = \Stripe\Subscription::retrieve( $subscription_id );
            $sub->cancel();
            return true;
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( '[YNJ Stripe] Cancel error: ' . $e->getMessage() );
            return new WP_Error( 'stripe_error', $e->getMessage() );
        }
    }

    /**
     * Verify a webhook signature.
     *
     * @param string $payload   Raw request body
     * @param string $sig       Stripe-Signature header
     * @return \Stripe\Event|WP_Error
     */
    public static function verify_webhook( $payload, $sig ) {
        self::init();

        $secret = self::webhook_secret();
        if ( ! $secret ) {
            return new WP_Error( 'no_webhook_secret', 'Webhook secret not configured.' );
        }

        try {
            return \Stripe\Webhook::constructEvent( $payload, $sig, $secret );
        } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
            return new WP_Error( 'invalid_signature', 'Invalid webhook signature.' );
        } catch ( \Exception $e ) {
            return new WP_Error( 'webhook_error', $e->getMessage() );
        }
    }
}
