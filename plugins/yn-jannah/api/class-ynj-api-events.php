<?php
/**
 * YourJannah — REST API: Event endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Events {

    const NS = 'ynj/v1';

    /**
     * Register event routes.
     */
    public static function register() {

        // GET /mosques/{id}/events?upcoming=1
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/events', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_public' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{slug}/events — slug-based convenience route
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z][a-zA-Z0-9_-]*)/events', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // POST /admin/events
        register_rest_route( self::NS, '/admin/events', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // PUT /admin/events/{id}
        register_rest_route( self::NS, '/admin/events/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // DELETE /admin/events/{id}
        register_rest_route( self::NS, '/admin/events/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);
    }

    // ================================================================
    // HANDLERS
    // ================================================================

    /**
     * GET /mosques/{slug}/events — Resolve slug to ID and delegate.
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
     * GET /mosques/{id}/events — Public event listing.
     */
    public static function list_public( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $upcoming  = $request->get_param( 'upcoming' );
        $page      = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page  = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 100 );
        $offset    = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'events' );

        $where = $wpdb->prepare( "mosque_id = %d AND status = 'published'", $mosque_id );

        if ( $upcoming ) {
            $where .= $wpdb->prepare( " AND event_date >= %s", date( 'Y-m-d' ) );
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY event_date ASC, start_time ASC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        $events = array_map( [ __CLASS__, 'format' ], $results );

        return new \WP_REST_Response( [
            'ok'       => true,
            'events'   => $events,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ] );
    }

    /**
     * POST /admin/events — Create event.
     */
    public static function create( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $data   = $request->get_json_params();

        $title = sanitize_text_field( $data['title'] ?? '' );
        if ( empty( $title ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Title is required.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'events' );

        $insert = [
            'mosque_id'        => (int) $mosque->id,
            'title'            => $title,
            'description'      => wp_kses_post( $data['description'] ?? '' ),
            'image_url'        => esc_url_raw( $data['image_url'] ?? '' ),
            'event_date'       => sanitize_text_field( $data['event_date'] ?? '' ),
            'start_time'       => sanitize_text_field( $data['start_time'] ?? '' ),
            'end_time'         => sanitize_text_field( $data['end_time'] ?? '' ),
            'location'         => sanitize_text_field( $data['location'] ?? '' ),
            'event_type'       => sanitize_text_field( $data['event_type'] ?? '' ),
            'max_capacity'     => absint( $data['max_capacity'] ?? 0 ),
            'requires_booking' => absint( $data['requires_booking'] ?? 0 ),
            'ticket_price_pence' => absint( $data['ticket_price_pence'] ?? 0 ),
            'status'           => sanitize_text_field( $data['status'] ?? 'draft' ),
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create event.' ], 500 );
        }

        $event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        return new \WP_REST_Response( [
            'ok'    => true,
            'event' => self::format( $event ),
        ], 201 );
    }

    /**
     * PUT /admin/events/{id} — Update event.
     */
    public static function update( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'events' );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $id, (int) $mosque->id
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event not found.' ], 404 );
        }

        $allowed = [
            'title', 'description', 'image_url', 'event_date', 'start_time', 'end_time',
            'location', 'event_type', 'max_capacity', 'requires_booking', 'ticket_price_pence', 'status',
        ];

        $update = [];
        foreach ( $allowed as $key ) {
            if ( ! isset( $data[ $key ] ) ) continue;

            switch ( $key ) {
                case 'description':
                    $update[ $key ] = wp_kses_post( $data[ $key ] );
                    break;
                case 'image_url':
                    $update[ $key ] = esc_url_raw( $data[ $key ] );
                    break;
                case 'max_capacity':
                case 'requires_booking':
                case 'ticket_price_pence':
                    $update[ $key ] = absint( $data[ $key ] );
                    break;
                default:
                    $update[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => $id ] );
        }

        $event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        return new \WP_REST_Response( [
            'ok'    => true,
            'event' => self::format( $event ),
        ] );
    }

    /**
     * DELETE /admin/events/{id} — Delete event.
     */
    public static function delete( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );

        global $wpdb;
        $table = YNJ_DB::table( 'events' );

        $deleted = $wpdb->delete( $table, [
            'id'        => $id,
            'mosque_id' => (int) $mosque->id,
        ] );

        if ( ! $deleted ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event not found.' ], 404 );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Event deleted.' ] );
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Format an event row for API response.
     */
    private static function format( $row ) {
        return [
            'id'                 => (int) $row->id,
            'mosque_id'          => (int) $row->mosque_id,
            'title'              => $row->title,
            'description'        => $row->description,
            'image_url'          => $row->image_url,
            'event_date'         => $row->event_date,
            'start_time'         => $row->start_time,
            'end_time'           => $row->end_time,
            'location'           => $row->location,
            'event_type'         => $row->event_type,
            'max_capacity'       => (int) $row->max_capacity,
            'registered_count'   => (int) $row->registered_count,
            'requires_booking'   => (bool) $row->requires_booking,
            'ticket_price_pence' => (int) $row->ticket_price_pence,
            'status'             => $row->status,
            'created_at'         => $row->created_at,
        ];
    }
}
