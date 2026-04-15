<?php
/**
 * YourJannah — REST API: Announcement endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Announcements {

    const NS = 'ynj/v1';

    /**
     * Register announcement routes.
     */
    public static function register() {

        // GET /mosques/{id}/announcements?page=1&per_page=20
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/announcements', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_public' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{slug}/announcements — slug-based convenience route
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/announcements', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /admin/announcements — all announcements including drafts
        register_rest_route( self::NS, '/admin/announcements', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_admin' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // POST /admin/announcements
        register_rest_route( self::NS, '/admin/announcements', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
            'args' => [
                'title'  => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'body'   => [ 'type' => 'string', 'required' => true ],
                'status' => [ 'type' => 'string', 'default' => 'draft', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ]);

        // PUT /admin/announcements/{id}
        register_rest_route( self::NS, '/admin/announcements/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // DELETE /admin/announcements/{id}
        register_rest_route( self::NS, '/admin/announcements/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);
    }

    // ================================================================
    // HANDLERS

    /**
     * GET /admin/announcements — All announcements for this mosque, including drafts.
     */
    public static function list_admin( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE mosque_id = %d ORDER BY pinned DESC, published_at DESC LIMIT 200",
            (int) $mosque->id
        ) );
        $announcements = array_map( function( $row ) {
            return [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'body'         => $row->body,
                'pinned'       => (bool) $row->pinned,
                'status'       => $row->status,
                'published_at' => $row->published_at,
                'expires_at'   => $row->expires_at ?? null,
            ];
        }, $results ?: [] );
        return new \WP_REST_Response( [ 'ok' => true, 'announcements' => $announcements ] );
    }
    // ================================================================

    /**
     * GET /mosques/{slug}/announcements — Resolve slug to ID and delegate.
     */
    public static function list_by_slug( \WP_REST_Request $request ) {
        $slug      = sanitize_text_field( $request->get_param( 'slug' ) );
        $mosque_id = YNJ_DB::resolve_slug( $slug );

        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $request->set_param( 'id', $mosque_id );

        return self::list_public( $request );
    }

    /**
     * GET /mosques/{id}/announcements — Public listing.
     * Excludes expired, ordered by pinned DESC, published_at DESC.
     */
    public static function list_public( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $page      = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page  = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 100 );
        $offset    = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, body, image_url, type, pinned, published_at
             FROM $table
             WHERE mosque_id = %d
               AND status = 'published'
               AND ( expires_at IS NULL OR expires_at > NOW() )
             ORDER BY pinned DESC, published_at DESC
             LIMIT %d OFFSET %d",
            $mosque_id, $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE mosque_id = %d
               AND status = 'published'
               AND ( expires_at IS NULL OR expires_at > NOW() )",
            $mosque_id
        ) );

        $announcements = array_map( function( $row ) {
            return [
                'id'           => (int) $row->id,
                'title'        => $row->title,
                'body'         => $row->body,
                'image_url'    => $row->image_url,
                'type'         => $row->type,
                'pinned'       => (bool) $row->pinned,
                'published_at' => $row->published_at,
            ];
        }, $results );

        return new \WP_REST_Response( [
            'ok'            => true,
            'announcements' => $announcements,
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $per_page,
        ] );
    }

    /**
     * POST /admin/announcements — Create announcement.
     */
    public static function create( \WP_REST_Request $request ) {
        $mosque  = $request->get_param( '_ynj_mosque' );
        $data    = $request->get_json_params();

        $title = sanitize_text_field( $data['title'] ?? '' );
        $body  = wp_kses_post( $data['body'] ?? '' );

        if ( empty( $title ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Title is required.' ], 400 );
        }

        $publish = ! empty( $data['publish'] );
        $status  = $publish ? 'published' : 'draft';

        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        $insert = [
            'mosque_id'    => (int) $mosque->id,
            'title'        => $title,
            'body'         => $body,
            'image_url'    => esc_url_raw( $data['image_url'] ?? '' ),
            'type'         => sanitize_text_field( $data['type'] ?? 'general' ),
            'pinned'       => absint( $data['pinned'] ?? 0 ),
            'expires_at'   => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
            'status'       => $status,
            'published_at' => $publish ? current_time( 'mysql' ) : null,
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create announcement.' ], 500 );
        }

        // Send push notification if publishing
        if ( $publish ) {
            YNJ_Push::send_to_mosque( (int) $mosque->id, $title, wp_strip_all_tags( $body ) );
            do_action( 'ynj_new_announcement', (int) $mosque->id, [
                'title' => $title,
                'body'  => wp_strip_all_tags( $body ),
                'type'  => sanitize_text_field( $data['type'] ?? 'general' ),
            ] );
        }

        $announcement = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        return new \WP_REST_Response( [
            'ok'           => true,
            'announcement' => self::format( $announcement ),
        ], 201 );
    }

    /**
     * PUT /admin/announcements/{id} — Update announcement.
     */
    public static function update( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        // Verify ownership
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $id, (int) $mosque->id
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Announcement not found.' ], 404 );
        }

        $update = [];
        if ( isset( $data['title'] ) )      $update['title']      = sanitize_text_field( $data['title'] );
        if ( isset( $data['body'] ) )        $update['body']       = wp_kses_post( $data['body'] );
        if ( isset( $data['image_url'] ) )   $update['image_url']  = esc_url_raw( $data['image_url'] );
        if ( isset( $data['type'] ) )        $update['type']       = sanitize_text_field( $data['type'] );
        if ( isset( $data['pinned'] ) )      $update['pinned']     = absint( $data['pinned'] );
        if ( isset( $data['expires_at'] ) )  $update['expires_at'] = sanitize_text_field( $data['expires_at'] );
        if ( isset( $data['status'] ) )      $update['status']     = sanitize_text_field( $data['status'] );

        // If transitioning to published, set published_at and trigger push
        if ( isset( $data['status'] ) && $data['status'] === 'published' && $existing->status !== 'published' ) {
            $update['published_at'] = current_time( 'mysql' );
        }

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => $id ] );
        }

        // Send push if explicitly requested
        if ( ! empty( $data['send_push'] ) && ( $existing->status === 'published' || ( $update['status'] ?? '' ) === 'published' ) ) {
            $title = $update['title'] ?? $existing->title;
            $body  = $update['body'] ?? $existing->body;
            YNJ_Push::send_to_mosque( (int) $mosque->id, $title, wp_strip_all_tags( $body ) );
            $wpdb->update( $table, [ 'push_sent' => 1, 'push_sent_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );
        }

        $announcement = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        return new \WP_REST_Response( [
            'ok'           => true,
            'announcement' => self::format( $announcement ),
        ] );
    }

    /**
     * DELETE /admin/announcements/{id} — Delete announcement.
     */
    public static function delete( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );

        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        $deleted = $wpdb->delete( $table, [
            'id'        => $id,
            'mosque_id' => (int) $mosque->id,
        ] );

        if ( ! $deleted ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Announcement not found.' ], 404 );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Announcement deleted.' ] );
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Format an announcement row for API response.
     */
    private static function format( $row ) {
        return [
            'id'           => (int) $row->id,
            'mosque_id'    => (int) $row->mosque_id,
            'title'        => $row->title,
            'body'         => $row->body,
            'image_url'    => $row->image_url,
            'type'         => $row->type,
            'pinned'       => (bool) $row->pinned,
            'push_sent'    => (bool) $row->push_sent,
            'expires_at'   => $row->expires_at,
            'status'       => $row->status,
            'published_at' => $row->published_at,
            'created_at'   => $row->created_at,
        ];
    }
}
