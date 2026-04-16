<?php
/**
 * YourJannah — REST API: Business & Service directory endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Directory {

    const NS = 'ynj/v1';

    /**
     * Register directory routes.
     */
    public static function register() {

        // GET /mosques/{slug}/directory — combined businesses + services by slug
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/directory', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'directory_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/businesses?category=&page=1
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/businesses', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_businesses' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{id}/services?type=&page=1
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/services', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_services' ],
            'permission_callback' => '__return_true',
        ]);

        // POST /businesses — Submit listing (pending approval)
        register_rest_route( self::NS, '/businesses', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'submit_business' ],
            'permission_callback' => '__return_true',
        ]);

        // POST /services — Submit listing (pending approval)
        register_rest_route( self::NS, '/services', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'submit_service' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /admin/businesses — List all businesses for mosque
        register_rest_route( self::NS, '/admin/businesses', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'admin_list_businesses' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // PUT /admin/businesses/{id} — Approve/reject
        register_rest_route( self::NS, '/admin/businesses/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'admin_update_business' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);
    }

    // ================================================================
    // PUBLIC HANDLERS
    // ================================================================

    /**
     * GET /mosques/{slug}/directory — Combined businesses + services by slug.
     */
    public static function directory_by_slug( \WP_REST_Request $request ) {
        $slug      = sanitize_text_field( $request->get_param( 'slug' ) );
        $mosque_id = YNJ_DB::resolve_slug( $slug );

        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $request->set_param( 'id', $mosque_id );

        $biz_response = self::list_businesses( $request );
        $svc_response = self::list_services( $request );

        $biz_data = $biz_response->get_data();
        $svc_data = $svc_response->get_data();

        return new \WP_REST_Response( [
            'ok'         => true,
            'businesses' => $biz_data['businesses'] ?? [],
            'services'   => $svc_data['services'] ?? [],
        ] );
    }

    /**
     * GET /mosques/{id}/businesses — Public business listing.
     * Ordered by monthly_fee_pence DESC (highest paying = most prominent), then name.
     */
    public static function list_businesses( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $category  = sanitize_text_field( $request->get_param( 'category' ) ?? '' );
        $page      = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page  = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 100 );
        $offset    = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'businesses' );

        $where = $wpdb->prepare(
            "mosque_id = %d AND status = 'active' AND ( expires_at IS NULL OR expires_at > NOW() )",
            $mosque_id
        );

        if ( ! empty( $category ) ) {
            $where .= $wpdb->prepare( " AND category = %s", $category );
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, business_name, owner_name, category, description, phone, email,
                    website, logo_url, address, postcode, monthly_fee_pence, featured_position
             FROM $table
             WHERE $where
             ORDER BY monthly_fee_pence DESC, business_name ASC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        $businesses = array_map( function( $row ) {
            return [
                'id'                => (int) $row->id,
                'business_name'     => $row->business_name,
                'owner_name'        => $row->owner_name,
                'category'          => $row->category,
                'description'       => $row->description,
                'phone'             => $row->phone,
                'email'             => $row->email,
                'website'           => $row->website,
                'logo_url'          => $row->logo_url,
                'address'           => $row->address,
                'postcode'          => $row->postcode,
                'featured'          => (int) $row->featured_position > 0,
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

    /**
     * GET /mosques/{id}/services — Public service listing.
     * Ordered by monthly_fee_pence DESC.
     */
    public static function list_services( \WP_REST_Request $request ) {
        $mosque_id    = absint( $request->get_param( 'id' ) );
        $service_type = sanitize_text_field( $request->get_param( 'type' ) ?? '' );
        $page         = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page     = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 100 );
        $offset       = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'services' );

        $where = $wpdb->prepare( "mosque_id = %d AND status = 'active'", $mosque_id );

        if ( ! empty( $service_type ) ) {
            $where .= $wpdb->prepare( " AND service_type = %s", $service_type );
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, provider_name, phone, email, service_type, description,
                    hourly_rate_pence, area_covered
             FROM $table
             WHERE $where
             ORDER BY monthly_fee_pence DESC, provider_name ASC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        $services = array_map( function( $row ) {
            return [
                'id'                => (int) $row->id,
                'provider_name'     => $row->provider_name,
                'phone'             => $row->phone,
                'email'             => $row->email,
                'service_type'      => $row->service_type,
                'description'       => $row->description,
                'hourly_rate_pence' => (int) $row->hourly_rate_pence,
                'area_covered'      => $row->area_covered,
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
     * POST /businesses — Submit a business listing (pending approval).
     */
    public static function submit_business( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $mosque_id     = absint( $data['mosque_id'] ?? 0 );
        $business_name = sanitize_text_field( $data['business_name'] ?? '' );
        $email         = sanitize_email( $data['email'] ?? '' );

        if ( ! $mosque_id || empty( $business_name ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id and business_name are required.' ], 400 );
        }

        // Resolve user_id from Bearer token (optional — anonymous submissions allowed).
        $user_id = 0;
        $auth_header = $request->get_header( 'Authorization' );
        if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
            if ( class_exists( 'YNJ_User_Auth' ) ) {
                $user = YNJ_User_Auth::verify_token( $matches[1] );
                if ( $user ) {
                    $user_id = (int) $user->id;
                }
            }
        }

        global $wpdb;
        $table = YNJ_DB::table( 'businesses' );

        $insert = [
            'mosque_id'     => $mosque_id,
            'user_id'       => $user_id,
            'business_name' => $business_name,
            'owner_name'    => sanitize_text_field( $data['owner_name'] ?? '' ),
            'category'      => sanitize_text_field( $data['category'] ?? '' ),
            'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
            'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
            'email'         => $email,
            'website'       => esc_url_raw( $data['website'] ?? '' ),
            'logo_url'      => esc_url_raw( $data['logo_url'] ?? '' ),
            'address'       => sanitize_text_field( $data['address'] ?? '' ),
            'postcode'      => sanitize_text_field( $data['postcode'] ?? '' ),
            'status'        => 'pending',
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to submit listing.' ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'id'      => $id,
            'message' => 'Business listing submitted for approval.',
        ], 201 );
    }

    /**
     * POST /services — Submit a service listing (pending approval).
     */
    public static function submit_service( \WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $mosque_id     = absint( $data['mosque_id'] ?? 0 );
        $provider_name = sanitize_text_field( $data['provider_name'] ?? '' );

        if ( ! $mosque_id || empty( $provider_name ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id and provider_name are required.' ], 400 );
        }

        // Resolve user_id from Bearer token (optional — anonymous submissions allowed).
        $user_id = 0;
        $auth_header = $request->get_header( 'Authorization' );
        if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
            if ( class_exists( 'YNJ_User_Auth' ) ) {
                $user = YNJ_User_Auth::verify_token( $matches[1] );
                if ( $user ) {
                    $user_id = (int) $user->id;
                }
            }
        }

        global $wpdb;
        $table = YNJ_DB::table( 'services' );

        $insert = [
            'mosque_id'      => $mosque_id,
            'user_id'        => $user_id,
            'provider_name'  => $provider_name,
            'phone'          => sanitize_text_field( $data['phone'] ?? '' ),
            'email'          => sanitize_email( $data['email'] ?? '' ),
            'service_type'   => sanitize_text_field( $data['service_type'] ?? '' ),
            'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
            'hourly_rate_pence' => absint( $data['hourly_rate_pence'] ?? 0 ),
            'area_covered'   => sanitize_text_field( $data['area_covered'] ?? '' ),
            'status'         => 'pending',
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to submit listing.' ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'id'      => $id,
            'message' => 'Service listing submitted for approval.',
        ], 201 );
    }

    // ================================================================
    // ADMIN HANDLERS
    // ================================================================

    /**
     * GET /admin/businesses — List all businesses for mosque (including pending).
     */
    public static function admin_list_businesses( \WP_REST_Request $request ) {
        $mosque   = $request->get_param( '_ynj_mosque' );
        $status   = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
        $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 100 );
        $offset   = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'businesses' );

        $where = $wpdb->prepare( "mosque_id = %d", (int) $mosque->id );

        if ( ! empty( $status ) ) {
            $where .= $wpdb->prepare( " AND status = %s", $status );
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        $businesses = array_map( function( $row ) {
            return [
                'id'                => (int) $row->id,
                'business_name'     => $row->business_name,
                'owner_name'        => $row->owner_name,
                'category'          => $row->category,
                'description'       => $row->description,
                'phone'             => $row->phone,
                'email'             => $row->email,
                'website'           => $row->website,
                'logo_url'          => $row->logo_url,
                'address'           => $row->address,
                'postcode'          => $row->postcode,
                'monthly_fee_pence' => (int) $row->monthly_fee_pence,
                'featured_position' => (int) $row->featured_position,
                'status'            => $row->status,
                'verified'          => (bool) $row->verified,
                'expires_at'        => $row->expires_at,
                'created_at'        => $row->created_at,
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

    /**
     * PUT /admin/businesses/{id} — Approve/reject business listing.
     */
    public static function admin_update_business( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'businesses' );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $id, (int) $mosque->id
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Business listing not found.' ], 404 );
        }

        $update = [];
        if ( isset( $data['status'] ) )            $update['status']            = sanitize_text_field( $data['status'] );
        if ( isset( $data['verified'] ) )           $update['verified']          = absint( $data['verified'] );
        if ( isset( $data['featured_position'] ) )  $update['featured_position'] = absint( $data['featured_position'] );
        if ( isset( $data['monthly_fee_pence'] ) )  $update['monthly_fee_pence'] = absint( $data['monthly_fee_pence'] );
        if ( isset( $data['expires_at'] ) )         $update['expires_at']        = sanitize_text_field( $data['expires_at'] );

        if ( empty( $update ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'No fields to update.' ], 400 );
        }

        $wpdb->update( $table, $update, [ 'id' => $id ] );

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Business listing updated.',
        ] );
    }
}
