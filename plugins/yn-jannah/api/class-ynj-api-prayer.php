<?php
/**
 * YourJannah — REST API: Prayer time endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Prayer {

    const NS = 'ynj/v1';

    /**
     * Register prayer time routes.
     */
    public static function register() {

        // GET /mosques/{id}/prayers?date=YYYY-MM-DD
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/prayers', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_daily' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/prayers/week?start=YYYY-MM-DD
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/prayers/week', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_week' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/jumuah
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/jumuah', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_jumuah' ],
            'permission_callback' => '__return_true',
        ]);
    }

    // ================================================================
    // HANDLERS
    // ================================================================

    /**
     * GET /mosques/{id}/prayers — Daily prayer times.
     */
    public static function get_daily( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $date      = sanitize_text_field( $request->get_param( 'date' ) ?: date( 'Y-m-d' ) );

        if ( ! self::validate_mosque( $mosque_id ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.' ], 400 );
        }

        $times = YNJ_Prayer::get_times( $mosque_id, $date );

        return new \WP_REST_Response( [
            'ok'        => true,
            'mosque_id' => $mosque_id,
            'date'      => $date,
            'times'     => $times,
        ] );
    }

    /**
     * GET /mosques/{id}/prayers/week — 7 days of prayer times.
     */
    public static function get_week( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $start     = sanitize_text_field( $request->get_param( 'start' ) ?: date( 'Y-m-d' ) );

        if ( ! self::validate_mosque( $mosque_id ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.' ], 400 );
        }

        $days = [];
        for ( $i = 0; $i < 7; $i++ ) {
            $date = date( 'Y-m-d', strtotime( $start . " +{$i} days" ) );
            $days[] = [
                'date'  => $date,
                'times' => YNJ_Prayer::get_times( $mosque_id, $date ),
            ];
        }

        return new \WP_REST_Response( [
            'ok'        => true,
            'mosque_id' => $mosque_id,
            'start'     => $start,
            'days'      => $days,
        ] );
    }

    /**
     * GET /mosques/{id}/jumuah — Jumu'ah prayer slots.
     */
    public static function get_jumuah( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );

        if ( ! self::validate_mosque( $mosque_id ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'jumuah_times' );

        $slots = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, slot_name, khutbah_time, salah_time, language, enabled
             FROM $table
             WHERE mosque_id = %d AND enabled = 1
             ORDER BY salah_time ASC",
            $mosque_id
        ) );

        $result = array_map( function( $row ) {
            return [
                'id'           => (int) $row->id,
                'slot_name'    => $row->slot_name,
                'khutbah_time' => $row->khutbah_time,
                'salah_time'   => $row->salah_time,
                'language'     => $row->language,
            ];
        }, $slots );

        return new \WP_REST_Response( [
            'ok'        => true,
            'mosque_id' => $mosque_id,
            'slots'     => $result,
        ] );
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Check if a mosque exists and is active.
     */
    private static function validate_mosque( $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND status = 'active'",
            $mosque_id
        ) );
    }
}
