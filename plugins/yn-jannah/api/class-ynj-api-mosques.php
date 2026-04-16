<?php
/**
 * YourJannah — REST API: Mosque discovery endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Mosques {

    const NS = 'ynj/v1';

    /**
     * Register all mosque discovery routes.
     */
    public static function register() {

        // GET /mosques/nearest?lat=&lng=&limit=20
        register_rest_route( self::NS, '/mosques/nearest', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'nearest' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/search?q=&limit=20
        register_rest_route( self::NS, '/mosques/search', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'search' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{slug}
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_by_slug' ],
            'permission_callback' => '__return_true',
        ]);
    }

    // ================================================================
    // HANDLERS
    // ================================================================

    /**
     * GET /mosques/nearest — Haversine distance search.
     */
    public static function nearest( \WP_REST_Request $request ) {
        $lat   = (float) $request->get_param( 'lat' );
        $lng   = (float) $request->get_param( 'lng' );
        $limit = min( absint( $request->get_param( 'limit' ) ?: 20 ), 100 );

        if ( ! $lat || ! $lng ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'lat and lng are required.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, slug, city, postcode, address, latitude, longitude, logo_url, status,
                    ( 6371 * acos(
                        cos( radians(%f) ) * cos( radians(latitude) )
                        * cos( radians(longitude) - radians(%f) )
                        + sin( radians(%f) ) * sin( radians(latitude) )
                    )) AS distance
             FROM $table
             WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY distance ASC
             LIMIT %d",
            $lat, $lng, $lat, $limit
        ) );

        $mosques = array_map( function( $row ) {
            return [
                'id'        => (int) $row->id,
                'name'      => $row->name,
                'slug'      => $row->slug,
                'city'      => $row->city,
                'postcode'  => $row->postcode,
                'address'   => $row->address,
                'latitude'  => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'logo_url'  => $row->logo_url,
                'distance'  => round( (float) $row->distance, 2 ),
                'status'    => $row->status,
            ];
        }, $results );

        return new \WP_REST_Response( [ 'ok' => true, 'mosques' => $mosques ] );
    }

    /**
     * GET /mosques/search — LIKE search on name and postcode.
     */
    public static function search( \WP_REST_Request $request ) {
        $q     = sanitize_text_field( $request->get_param( 'q' ) ?? '' );
        $limit = min( absint( $request->get_param( 'limit' ) ?: 20 ), 100 );

        if ( empty( $q ) || strlen( $q ) < 2 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Search query must be at least 2 characters.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        $like  = '%' . $wpdb->esc_like( $q ) . '%';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, slug, city, postcode, address, latitude, longitude, logo_url
             FROM $table
             WHERE status IN ('active','unclaimed') AND ( name LIKE %s OR postcode LIKE %s )
             ORDER BY name ASC
             LIMIT %d",
            $like, $like, $limit
        ) );

        $mosques = array_map( function( $row ) {
            return [
                'id'        => (int) $row->id,
                'name'      => $row->name,
                'slug'      => $row->slug,
                'city'      => $row->city,
                'postcode'  => $row->postcode,
                'address'   => $row->address,
                'latitude'  => $row->latitude ? (float) $row->latitude : null,
                'longitude' => $row->longitude ? (float) $row->longitude : null,
                'logo_url'  => $row->logo_url,
            ];
        }, $results );

        return new \WP_REST_Response( [ 'ok' => true, 'mosques' => $mosques ] );
    }

    /**
     * GET /mosques/{slug} — Full mosque profile + today's prayer times.
     */
    public static function get_by_slug( \WP_REST_Request $request ) {
        $slug = sanitize_text_field( $request->get_param( 'slug' ) );

        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );

        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s AND status IN ('active','unclaimed') LIMIT 1",
            $slug
        ) );

        if ( ! $mosque ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $profile = [
            'id'                => (int) $mosque->id,
            'name'              => $mosque->name,
            'slug'              => $mosque->slug,
            'address'           => $mosque->address,
            'city'              => $mosque->city,
            'postcode'          => $mosque->postcode,
            'country'           => $mosque->country,
            'latitude'          => $mosque->latitude ? (float) $mosque->latitude : null,
            'longitude'         => $mosque->longitude ? (float) $mosque->longitude : null,
            'timezone'          => $mosque->timezone,
            'phone'             => $mosque->phone,
            'email'             => $mosque->email,
            'website'           => $mosque->website,
            'logo_url'          => $mosque->logo_url,
            'photo_url'         => $mosque->photo_url,
            'description'       => $mosque->description,
            'has_women_section' => (bool) $mosque->has_women_section,
            'has_wudu'          => (bool) $mosque->has_wudu,
            'has_parking'       => (bool) $mosque->has_parking,
            'capacity'          => (int) $mosque->capacity,
            'status'            => $mosque->status,
            'setup_complete'    => (bool) ( $mosque->setup_complete ?? false ),
        ];

        // Attach today's prayer times
        $profile['prayer_times'] = YNJ_Prayer::get_times( $mosque->id, date( 'Y-m-d' ) );

        return new \WP_REST_Response( [ 'ok' => true, 'mosque' => $profile ] );
    }
}
