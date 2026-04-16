<?php
/**
 * YourJannah — REST API: Patron Intention (pledge) endpoints.
 *
 * Handles "Make Your Intention" flow for unclaimed mosques.
 * Users pledge to become patrons — when mosque claims, they get notified.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Intentions {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /intentions — Submit a patron intention (public, no auth required)
        register_rest_route( self::NS, '/intentions', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /mosques/{id}/intentions — Public count of intentions for a mosque
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/intentions', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'count' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * POST /intentions — Create a patron intention/pledge.
     */
    public static function create( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        if ( ! $mosque_id && ! empty( $data['mosque_slug'] ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $data['mosque_slug'] );
        }

        $name  = sanitize_text_field( $data['name'] ?? '' );
        $email = sanitize_email( $data['email'] ?? '' );
        $phone = sanitize_text_field( $data['phone'] ?? '' );
        $tier  = sanitize_text_field( $data['tier'] ?? 'supporter' );

        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 400 );
        }
        if ( ! $name || ! $email ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Name and email are required.' ], 400 );
        }
        if ( ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid email address.' ], 400 );
        }

        // Rate limit: max 5 intentions per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $transient_key = 'ynj_intent_' . md5( $ip );
        $count = (int) get_transient( $transient_key );
        if ( $count >= 5 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Too many requests. Please try again later.' ], 429 );
        }
        set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );

        // Tier amounts
        $tier_amounts = [
            'supporter' => 500,
            'guardian'  => 1000,
            'champion'  => 2000,
            'platinum'  => 5000,
        ];
        $amount = $tier_amounts[ $tier ] ?? 500;

        global $wpdb;
        $table = YNJ_DB::table( 'patron_intentions' );

        // Check if this email already has an active intention for this mosque
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table WHERE mosque_id = %d AND email = %s AND status = 'active'",
            $mosque_id, $email
        ) );

        if ( $existing ) {
            // Update existing intention with new tier
            $wpdb->update( $table, [
                'name'         => $name,
                'phone'        => $phone,
                'tier'         => $tier,
                'amount_pence' => $amount,
            ], [ 'id' => $existing->id ] );
        } else {
            $wpdb->insert( $table, [
                'mosque_id'    => $mosque_id,
                'name'         => $name,
                'email'        => $email,
                'phone'        => $phone,
                'tier'         => $tier,
                'amount_pence' => $amount,
                'status'       => 'active',
            ] );
        }

        // Get total count for this mosque
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE mosque_id = %d AND status = 'active'",
            $mosque_id
        ) );

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Your intention has been recorded. We\'ll notify you when this mosque joins YourJannah.',
            'total'   => $total,
        ] );
    }

    /**
     * GET /mosques/{id}/intentions — Public count + summary.
     */
    public static function count( \WP_REST_Request $request ) {
        $mosque_id = (int) $request->get_param( 'id' );

        global $wpdb;
        $table = YNJ_DB::table( 'patron_intentions' );

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(amount_pence), 0) AS total_pence
             FROM $table WHERE mosque_id = %d AND status = 'active'",
            $mosque_id
        ) );

        return new \WP_REST_Response( [
            'ok'          => true,
            'total'       => (int) $stats->total,
            'total_pence' => (int) $stats->total_pence,
        ] );
    }
}
