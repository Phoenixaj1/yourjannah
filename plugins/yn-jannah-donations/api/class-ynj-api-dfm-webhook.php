<?php
/**
 * YourJannah — DonationForMasjid Webhook Receiver.
 *
 * Receives donation events from DFM and auto-updates campaign progress.
 * Endpoint: POST /ynj/v1/dfm/webhook
 *
 * Expected payload:
 * {
 *   "event": "donation.completed",
 *   "mosque_slug": "east-london-mosque",
 *   "amount_pence": 5000,
 *   "fund_type": "general|welfare|roof|expansion|...",
 *   "campaign_ref": "new-roof-fund",  // optional — match by slug
 *   "donor_email": "ahmed@test.com",  // optional
 *   "recurring": false,
 *   "secret": "shared_webhook_secret"
 * }
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_DFM_Webhook {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /dfm/webhook — receive donation events
        register_rest_route( self::NS, '/dfm/webhook', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle incoming DFM webhook.
     */
    public static function handle( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        // Verify shared secret
        $expected_secret = get_option( 'ynj_dfm_webhook_secret', '' );
        $received_secret = $data['secret'] ?? $request->get_header( 'x-dfm-secret' ) ?? '';

        if ( ! $expected_secret || $received_secret !== $expected_secret ) {
            error_log( '[YNJ DFM Webhook] Invalid secret.' );
            return new \WP_REST_Response( [ 'error' => 'Invalid secret.' ], 401 );
        }

        $event = $data['event'] ?? '';

        if ( $event === 'donation.completed' ) {
            return self::on_donation( $data );
        }

        // Unknown event — acknowledge
        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Event ignored.' ] );
    }

    /**
     * Process a completed donation.
     */
    private static function on_donation( $data ) {
        $mosque_slug  = sanitize_text_field( $data['mosque_slug'] ?? '' );
        $amount_pence = absint( $data['amount_pence'] ?? 0 );
        $fund_type    = sanitize_text_field( $data['fund_type'] ?? '' );
        $campaign_ref = sanitize_text_field( $data['campaign_ref'] ?? '' );

        if ( ! $mosque_slug || ! $amount_pence ) {
            return new \WP_REST_Response( [ 'error' => 'mosque_slug and amount_pence required.' ], 400 );
        }

        // Resolve mosque
        $mosque_id = YNJ_DB::resolve_slug( $mosque_slug );
        if ( ! $mosque_id ) {
            // Try matching by dfm_slug
            global $wpdb;
            $mosque_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . YNJ_DB::table( 'mosques' ) . " WHERE dfm_slug = %s LIMIT 1",
                $mosque_slug
            ) );
        }

        if ( ! $mosque_id ) {
            error_log( "[YNJ DFM Webhook] Mosque not found: $mosque_slug" );
            return new \WP_REST_Response( [ 'error' => 'Mosque not found.' ], 404 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );

        // Try to match a campaign
        $campaign = null;

        // 1. Match by campaign_ref (slug-like reference)
        if ( $campaign_ref ) {
            $campaign = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM $table WHERE mosque_id = %d AND status = 'active'
                 AND LOWER(REPLACE(title, ' ', '-')) = %s LIMIT 1",
                $mosque_id, strtolower( $campaign_ref )
            ) );
        }

        // 2. Match by fund_type → category
        if ( ! $campaign && $fund_type ) {
            $campaign = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM $table WHERE mosque_id = %d AND status = 'active'
                 AND category = %s ORDER BY created_at DESC LIMIT 1",
                $mosque_id, $fund_type
            ) );
        }

        // 3. Fallback: match the most recent 'general' campaign
        if ( ! $campaign ) {
            $campaign = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM $table WHERE mosque_id = %d AND status = 'active'
                 ORDER BY category = 'general' DESC, created_at DESC LIMIT 1",
                $mosque_id
            ) );
        }

        if ( ! $campaign ) {
            error_log( "[YNJ DFM Webhook] No campaign found for mosque $mosque_id, fund: $fund_type" );
            return new \WP_REST_Response( [
                'ok'      => true,
                'matched' => false,
                'message' => 'Donation received but no matching campaign found.',
            ] );
        }

        // Update campaign: increment raised_pence and donor_count
        $wpdb->query( $wpdb->prepare(
            "UPDATE $table SET raised_pence = raised_pence + %d, donor_count = donor_count + 1 WHERE id = %d",
            $amount_pence, $campaign->id
        ) );

        error_log( "[YNJ DFM Webhook] Campaign #{$campaign->id} updated: +£" . number_format( $amount_pence / 100, 2 ) );

        return new \WP_REST_Response( [
            'ok'          => true,
            'matched'     => true,
            'campaign_id' => (int) $campaign->id,
            'added_pence' => $amount_pence,
        ] );
    }
}
