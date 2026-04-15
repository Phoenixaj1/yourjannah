<?php
/**
 * YourJannah — REST API: Cross-mosque search endpoints.
 *
 * Enables community-wide discovery of services and businesses
 * across all mosques, with haversine radius search.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Search {

    const NS = 'ynj/v1';

    public static function register() {

        // GET /services/search?q=&type=&lat=&lng=&radius_km=&mosque_id=&page=&per_page=
        register_rest_route( self::NS, '/services/search', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'search_services' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /businesses/search?q=&category=&lat=&lng=&radius_km=&mosque_id=&page=&per_page=
        register_rest_route( self::NS, '/businesses/search', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'search_businesses' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * GET /services/search — Search services across all mosques.
     *
     * Joins services → mosques for lat/lng, returns with distance.
     * If mosque_id is provided, those results appear first (local mosque priority).
     */
    public static function search_services( \WP_REST_Request $request ) {
        global $wpdb;

        $q         = sanitize_text_field( $request->get_param( 'q' ) ?? '' );
        $type      = sanitize_text_field( $request->get_param( 'type' ) ?? '' );
        $lat       = (float) $request->get_param( 'lat' );
        $lng       = (float) $request->get_param( 'lng' );
        $radius_km = (float) ( $request->get_param( 'radius_km' ) ?: 50 );
        $mosque_id = absint( $request->get_param( 'mosque_id' ) ?: 0 );
        $page      = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page  = min( absint( $request->get_param( 'per_page' ) ?: 30 ), 100 );
        $offset    = ( $page - 1 ) * $per_page;

        // Resolve mosque_slug to ID
        if ( ! $mosque_id && ! empty( $request->get_param( 'mosque_slug' ) ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $request->get_param( 'mosque_slug' ) );
        }

        $svc_table    = YNJ_DB::table( 'services' );
        $mosque_table = YNJ_DB::table( 'mosques' );

        // Build distance expression
        $has_coords  = $lat && $lng;
        $distance_sql = $has_coords
            ? $wpdb->prepare(
                "( 6371 * acos( cos( radians(%f) ) * cos( radians(m.latitude) ) * cos( radians(m.longitude) - radians(%f) ) + sin( radians(%f) ) * sin( radians(m.latitude) ) ) )",
                $lat, $lng, $lat
            )
            : '9999';

        // Build WHERE
        $where = "s.status = 'active' AND m.status = 'active' AND m.latitude IS NOT NULL";

        if ( $has_coords && $radius_km < 9999 ) {
            $where .= $wpdb->prepare(
                " AND ( 6371 * acos( cos( radians(%f) ) * cos( radians(m.latitude) ) * cos( radians(m.longitude) - radians(%f) ) + sin( radians(%f) ) * sin( radians(m.latitude) ) ) ) <= %f",
                $lat, $lng, $lat, $radius_km
            );
        }

        if ( $type ) {
            $where .= $wpdb->prepare( " AND s.service_type = %s", $type );
        }

        if ( $q && strlen( $q ) >= 2 ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where .= $wpdb->prepare( " AND ( s.provider_name LIKE %s OR s.description LIKE %s OR s.service_type LIKE %s )", $like, $like, $like );
        }

        // Order: local mosque first, then by distance
        $order = $has_coords ? "{$distance_sql} ASC" : 's.monthly_fee_pence DESC, s.provider_name ASC';
        if ( $mosque_id ) {
            $order = $wpdb->prepare( "(s.mosque_id = %d) DESC, ", $mosque_id ) . $order;
        }

        // Query
        $sql = "SELECT s.id, s.provider_name, s.service_type, s.description, s.phone, s.email,
                       s.area_covered, s.hourly_rate_pence, s.monthly_fee_pence, s.mosque_id,
                       m.name AS mosque_name, m.city AS mosque_city, m.slug AS mosque_slug,
                       {$distance_sql} AS distance_km
                FROM {$svc_table} s
                INNER JOIN {$mosque_table} m ON m.id = s.mosque_id
                WHERE {$where}
                ORDER BY {$order}
                LIMIT %d OFFSET %d";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ) );

        $total_sql = "SELECT COUNT(*) FROM {$svc_table} s INNER JOIN {$mosque_table} m ON m.id = s.mosque_id WHERE {$where}";
        $total = (int) $wpdb->get_var( $total_sql );

        $services = array_map( function( $r ) {
            return [
                'id'                => (int) $r->id,
                'provider_name'     => $r->provider_name,
                'service_type'      => $r->service_type,
                'description'       => $r->description,
                'phone'             => $r->phone,
                'email'             => $r->email,
                'area_covered'      => $r->area_covered,
                'hourly_rate_pence' => (int) $r->hourly_rate_pence,
                'mosque_id'         => (int) $r->mosque_id,
                'mosque_name'       => $r->mosque_name,
                'mosque_city'       => $r->mosque_city,
                'mosque_slug'       => $r->mosque_slug,
                'distance_km'       => round( (float) $r->distance_km, 1 ),
            ];
        }, $results );

        return new \WP_REST_Response( [
            'ok'       => true,
            'services' => $services,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ] );
    }

    /**
     * GET /businesses/search — Search businesses across all mosques.
     */
    public static function search_businesses( \WP_REST_Request $request ) {
        global $wpdb;

        $q         = sanitize_text_field( $request->get_param( 'q' ) ?? '' );
        $category  = sanitize_text_field( $request->get_param( 'category' ) ?? '' );
        $lat       = (float) $request->get_param( 'lat' );
        $lng       = (float) $request->get_param( 'lng' );
        $radius_km = (float) ( $request->get_param( 'radius_km' ) ?: 50 );
        $mosque_id = absint( $request->get_param( 'mosque_id' ) ?: 0 );
        $page      = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page  = min( absint( $request->get_param( 'per_page' ) ?: 30 ), 100 );
        $offset    = ( $page - 1 ) * $per_page;

        if ( ! $mosque_id && ! empty( $request->get_param( 'mosque_slug' ) ) ) {
            $mosque_id = (int) YNJ_DB::resolve_slug( $request->get_param( 'mosque_slug' ) );
        }

        $biz_table    = YNJ_DB::table( 'businesses' );
        $mosque_table = YNJ_DB::table( 'mosques' );

        $has_coords  = $lat && $lng;
        $distance_sql = $has_coords
            ? $wpdb->prepare(
                "( 6371 * acos( cos( radians(%f) ) * cos( radians(m.latitude) ) * cos( radians(m.longitude) - radians(%f) ) + sin( radians(%f) ) * sin( radians(m.latitude) ) ) )",
                $lat, $lng, $lat
            )
            : '9999';

        $where = "b.status = 'active' AND m.status = 'active' AND m.latitude IS NOT NULL";
        $where .= " AND ( b.expires_at IS NULL OR b.expires_at > NOW() )";

        if ( $has_coords && $radius_km < 9999 ) {
            $where .= $wpdb->prepare(
                " AND ( 6371 * acos( cos( radians(%f) ) * cos( radians(m.latitude) ) * cos( radians(m.longitude) - radians(%f) ) + sin( radians(%f) ) * sin( radians(m.latitude) ) ) ) <= %f",
                $lat, $lng, $lat, $radius_km
            );
        }

        if ( $category ) {
            $where .= $wpdb->prepare( " AND b.category = %s", $category );
        }

        if ( $q && strlen( $q ) >= 2 ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where .= $wpdb->prepare( " AND ( b.business_name LIKE %s OR b.description LIKE %s OR b.category LIKE %s )", $like, $like, $like );
        }

        $order = $has_coords ? "{$distance_sql} ASC" : 'b.monthly_fee_pence DESC, b.business_name ASC';
        if ( $mosque_id ) {
            $order = $wpdb->prepare( "(b.mosque_id = %d) DESC, ", $mosque_id ) . $order;
        }

        $sql = "SELECT b.id, b.business_name, b.owner_name, b.category, b.description, b.phone,
                       b.email, b.website, b.address, b.postcode, b.monthly_fee_pence,
                       b.featured_position, b.mosque_id,
                       m.name AS mosque_name, m.city AS mosque_city, m.slug AS mosque_slug,
                       {$distance_sql} AS distance_km
                FROM {$biz_table} b
                INNER JOIN {$mosque_table} m ON m.id = b.mosque_id
                WHERE {$where}
                ORDER BY {$order}
                LIMIT %d OFFSET %d";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ) );

        $total_sql = "SELECT COUNT(*) FROM {$biz_table} b INNER JOIN {$mosque_table} m ON m.id = b.mosque_id WHERE {$where}";
        $total = (int) $wpdb->get_var( $total_sql );

        $businesses = array_map( function( $r ) {
            return [
                'id'                => (int) $r->id,
                'business_name'     => $r->business_name,
                'owner_name'        => $r->owner_name,
                'category'          => $r->category,
                'description'       => $r->description,
                'phone'             => $r->phone,
                'email'             => $r->email,
                'website'           => $r->website,
                'address'           => $r->address,
                'postcode'          => $r->postcode,
                'featured'          => (int) $r->featured_position > 0,
                'mosque_id'         => (int) $r->mosque_id,
                'mosque_name'       => $r->mosque_name,
                'mosque_city'       => $r->mosque_city,
                'mosque_slug'       => $r->mosque_slug,
                'distance_km'       => round( (float) $r->distance_km, 1 ),
            ];
        }, $results );

        return new \WP_REST_Response( [
            'ok'         => true,
            'businesses' => $businesses,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
        ] );
    }
}
