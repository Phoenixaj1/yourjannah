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

        // GET /mosques/{slug}/prayers — slug-based convenience route
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/prayers', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_daily_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/prayers/week?start=YYYY-MM-DD
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/prayers/week', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_week' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/prayers/month?month=YYYY-MM
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/prayers/month', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_month' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{slug}/prayers/month — slug convenience
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/prayers/month', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_month_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/jumuah
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/jumuah', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_jumuah' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{slug}/jumuah — slug convenience
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/jumuah', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_jumuah_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/eid?year=2026
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/eid', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_eid' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{slug}/eid — slug convenience
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/eid', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_eid_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // PUT /admin/prayers/bulk — bulk set jamat times for multiple dates
        register_rest_route( self::NS, '/admin/prayers/bulk', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'bulk_set_jamat' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);
    }

    // ================================================================
    // HANDLERS
    // ================================================================

    /**
     * GET /mosques/{slug}/prayers — Resolve slug to ID and delegate.
     */
    public static function get_daily_by_slug( \WP_REST_Request $request ) {
        $slug      = sanitize_text_field( $request->get_param( 'slug' ) );
        $mosque_id = YNJ_DB::resolve_slug( $slug );

        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $request->set_param( 'id', $mosque_id );

        return self::get_daily( $request );
    }

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

    /**
     * GET /mosques/{id}/prayers/month — Full month of prayer times.
     */
    public static function get_month( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $month     = sanitize_text_field( $request->get_param( 'month' ) ?: date( 'Y-m' ) );

        if ( ! self::validate_mosque( $mosque_id ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Month format: YYYY-MM' ], 400 );
        }

        $days_in_month = (int) date( 't', strtotime( $month . '-01' ) );
        $days = [];

        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $date = $month . '-' . str_pad( $d, 2, '0', STR_PAD_LEFT );
            $times = YNJ_Prayer::get_times( $mosque_id, $date );

            if ( is_wp_error( $times ) ) {
                $days[] = [ 'date' => $date, 'day' => date( 'D', strtotime( $date ) ), 'error' => true ];
            } else {
                $days[] = [
                    'date'          => $date,
                    'day'           => date( 'D', strtotime( $date ) ),
                    'fajr'          => $times['fajr'] ?? null,
                    'sunrise'       => $times['sunrise'] ?? null,
                    'dhuhr'         => $times['dhuhr'] ?? null,
                    'asr'           => $times['asr'] ?? null,
                    'maghrib'       => $times['maghrib'] ?? null,
                    'isha'          => $times['isha'] ?? null,
                    'fajr_jamat'    => $times['fajr_jamat'] ?? null,
                    'dhuhr_jamat'   => $times['dhuhr_jamat'] ?? null,
                    'asr_jamat'     => $times['asr_jamat'] ?? null,
                    'maghrib_jamat' => $times['maghrib_jamat'] ?? null,
                    'isha_jamat'    => $times['isha_jamat'] ?? null,
                    'taraweeh'      => $times['taraweeh'] ?? null,
                    'source'        => $times['source'] ?? 'api',
                ];
            }
        }

        return new \WP_REST_Response( [
            'ok'        => true,
            'mosque_id' => $mosque_id,
            'month'     => $month,
            'days'      => $days,
        ] );
    }

    public static function get_month_by_slug( \WP_REST_Request $request ) {
        $mosque_id = YNJ_DB::resolve_slug( $request->get_param( 'slug' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        $request->set_param( 'id', $mosque_id );
        return self::get_month( $request );
    }

    /**
     * GET /mosques/{id}/eid — Eid prayer times for a year.
     */
    public static function get_eid( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $year      = absint( $request->get_param( 'year' ) ?: date( 'Y' ) );

        if ( ! self::validate_mosque( $mosque_id ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'eid_times' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, eid_type, year, slot_name, salah_time, location_notes
             FROM $table WHERE mosque_id = %d AND year = %d ORDER BY eid_type ASC, salah_time ASC",
            $mosque_id, $year
        ) );

        $eid_times = array_map( function( $r ) {
            return [
                'id'             => (int) $r->id,
                'eid_type'       => $r->eid_type,
                'year'           => (int) $r->year,
                'slot_name'      => $r->slot_name,
                'salah_time'     => $r->salah_time,
                'location_notes' => $r->location_notes,
            ];
        }, $results );

        return new \WP_REST_Response( [
            'ok'        => true,
            'mosque_id' => $mosque_id,
            'year'      => $year,
            'eid_times' => $eid_times,
        ] );
    }

    public static function get_eid_by_slug( \WP_REST_Request $request ) {
        $mosque_id = YNJ_DB::resolve_slug( $request->get_param( 'slug' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        $request->set_param( 'id', $mosque_id );
        return self::get_eid( $request );
    }

    public static function get_jumuah_by_slug( \WP_REST_Request $request ) {
        $mosque_id = YNJ_DB::resolve_slug( $request->get_param( 'slug' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        $request->set_param( 'id', $mosque_id );
        return self::get_jumuah( $request );
    }

    /**
     * PUT /admin/prayers/bulk — Set jamat times for multiple dates at once.
     */
    public static function bulk_set_jamat( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $data   = $request->get_json_params();
        $dates  = $data['dates'] ?? [];

        if ( empty( $dates ) || ! is_array( $dates ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'dates array required.' ], 400 );
        }

        $updated = 0;
        foreach ( $dates as $entry ) {
            $date  = sanitize_text_field( $entry['date'] ?? '' );
            $times = $entry['times'] ?? [];

            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || empty( $times ) ) continue;

            $result = YNJ_Prayer::set_jamat_times( (int) $mosque->id, $date, $times );
            if ( $result === true ) $updated++;
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'updated' => $updated,
            'message' => "{$updated} day(s) updated.",
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
