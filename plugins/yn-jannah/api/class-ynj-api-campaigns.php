<?php
/**
 * YourJannah — REST API: Fundraising campaign endpoints.
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Campaigns {

    const NS = 'ynj/v1';

    public static function register() {

        // GET /mosques/{slug}/campaigns — public listing
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/campaigns', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_by_slug' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /mosques/{id}/campaigns
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/campaigns', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_public' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /admin/campaigns — create
        register_rest_route( self::NS, '/admin/campaigns', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );

        // PUT /admin/campaigns/{id}
        register_rest_route( self::NS, '/admin/campaigns/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );

        // DELETE /admin/campaigns/{id}
        register_rest_route( self::NS, '/admin/campaigns/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );
    }

    public static function list_by_slug( \WP_REST_Request $request ) {
        $mosque_id = YNJ_DB::resolve_slug( $request->get_param( 'slug' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        $request->set_param( 'id', $mosque_id );
        return self::list_public( $request );
    }

    public static function list_public( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );

        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE mosque_id = %d AND status = 'active' ORDER BY created_at DESC",
            $mosque_id
        ) );

        $campaigns = array_map( [ __CLASS__, 'format' ], $results );

        return new \WP_REST_Response( [ 'ok' => true, 'campaigns' => $campaigns ] );
    }

    public static function create( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $data   = $request->get_json_params();

        $title = sanitize_text_field( $data['title'] ?? '' );
        if ( ! $title ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Title required.' ], 400 );

        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );

        $wpdb->insert( $table, [
            'mosque_id'    => (int) $mosque->id,
            'title'        => $title,
            'description'  => wp_kses_post( $data['description'] ?? '' ),
            'image_url'    => esc_url_raw( $data['image_url'] ?? '' ),
            'target_pence' => absint( $data['target_pence'] ?? 0 ),
            'category'     => sanitize_text_field( $data['category'] ?? 'general' ),
            'dfm_link'     => esc_url_raw( $data['dfm_link'] ?? '' ),
            'recurring'    => absint( $data['recurring'] ?? 0 ),
            'recurring_interval' => sanitize_text_field( $data['recurring_interval'] ?? '' ),
            'status'       => 'active',
            'start_date'   => sanitize_text_field( $data['start_date'] ?? date( 'Y-m-d' ) ),
            'end_date'     => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
        ] );

        $id = (int) $wpdb->insert_id;
        if ( ! $id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed.' ], 500 );

        return new \WP_REST_Response( [ 'ok' => true, 'id' => $id, 'message' => 'Campaign created.' ], 201 );
    }

    public static function update( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND mosque_id = %d", $id, (int) $mosque->id
        ) );
        if ( ! $existing ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not found.' ], 404 );

        $update = [];
        if ( isset( $data['title'] ) )        $update['title']        = sanitize_text_field( $data['title'] );
        if ( isset( $data['description'] ) )  $update['description']  = wp_kses_post( $data['description'] );
        if ( isset( $data['target_pence'] ) ) $update['target_pence'] = absint( $data['target_pence'] );
        if ( isset( $data['raised_pence'] ) ) $update['raised_pence'] = absint( $data['raised_pence'] );
        if ( isset( $data['donor_count'] ) )  $update['donor_count']  = absint( $data['donor_count'] );
        if ( isset( $data['category'] ) )     $update['category']     = sanitize_text_field( $data['category'] );
        if ( isset( $data['dfm_link'] ) )     $update['dfm_link']     = esc_url_raw( $data['dfm_link'] );
        if ( isset( $data['status'] ) )       $update['status']       = sanitize_text_field( $data['status'] );
        if ( isset( $data['recurring'] ) )    $update['recurring']    = absint( $data['recurring'] );
        if ( isset( $data['recurring_interval'] ) ) $update['recurring_interval'] = sanitize_text_field( $data['recurring_interval'] );
        if ( isset( $data['end_date'] ) )     $update['end_date']     = sanitize_text_field( $data['end_date'] );

        if ( ! empty( $update ) ) $wpdb->update( $table, $update, [ 'id' => $id ] );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Updated.' ] );
    }

    public static function delete( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );

        global $wpdb;
        $deleted = $wpdb->delete( YNJ_DB::table( 'campaigns' ), [
            'id' => $id, 'mosque_id' => (int) $mosque->id,
        ] );
        if ( ! $deleted ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not found.' ], 404 );
        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Deleted.' ] );
    }

    private static function format( $r ) {
        $target = (int) $r->target_pence;
        $raised = (int) $r->raised_pence;
        return [
            'id'           => (int) $r->id,
            'title'        => $r->title,
            'description'  => $r->description,
            'image_url'    => $r->image_url,
            'target_pence' => $target,
            'raised_pence' => $raised,
            'donor_count'  => (int) $r->donor_count,
            'percentage'   => $target > 0 ? min( 100, round( $raised / $target * 100 ) ) : 0,
            'category'     => $r->category,
            'dfm_link'     => $r->dfm_link,
            'recurring'    => (bool) ( $r->recurring ?? 0 ),
            'recurring_interval' => $r->recurring_interval ?? '',
            'status'       => $r->status,
            'start_date'   => $r->start_date,
            'end_date'     => $r->end_date,
        ];
    }
}
