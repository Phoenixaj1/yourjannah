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

        // POST /events/{id}/donate — donate to an event
        register_rest_route( self::NS, '/events/(?P<id>\d+)/donate', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'donate_to_event' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /events/live — live and upcoming online events across all mosques
        register_rest_route( self::NS, '/events/live', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_live' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /events/{id} — single event detail
        register_rest_route( self::NS, '/events/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_single' ],
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
     * POST /events/{id}/donate — Create Stripe checkout for event donation.
     */
    public static function donate_to_event( \WP_REST_Request $request ) {
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();
        $amount = absint( $data['amount_pence'] ?? 0 );

        if ( ! $amount || $amount < 100 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Minimum donation is £1.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'events' );
        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND status = 'published'", $id
        ) );
        if ( ! $event ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event not found.' ], 404 );

        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $event->mosque_id
        ) );
        $base = home_url( "/mosque/" . ( $mosque->slug ?? '' ) );

        $session = YNJ_Stripe::create_checkout(
            'event_donation',
            $id,
            $amount,
            'Donation: ' . $event->title,
            $base . '/events/' . $id . '?donated=1',
            $base . '/events/' . $id,
            [ 'mosque_id' => $event->mosque_id, 'event_id' => $id ]
        );

        if ( is_wp_error( $session ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $session->get_error_message() ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'           => true,
            'checkout_url' => $session->url,
        ] );
    }

    /**
     * GET /events/live — Live now + upcoming online events across all mosques.
     */
    public static function list_live( \WP_REST_Request $request ) {
        global $wpdb;
        $event_table  = YNJ_DB::table( 'events' );
        $mosque_table = YNJ_DB::table( 'mosques' );
        $today = date( 'Y-m-d' );

        // Live now: is_live = 1 AND is_online = 1
        // Upcoming: is_online = 1 AND event_date >= today
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city
             FROM $event_table e
             INNER JOIN $mosque_table m ON m.id = e.mosque_id
             WHERE e.status = 'published' AND e.is_online = 1
               AND ( e.is_live = 1 OR e.event_date >= %s )
             ORDER BY e.is_live DESC, e.event_date ASC, e.start_time ASC
             LIMIT 50",
            $today
        ) );

        $events = array_map( function( $r ) {
            $formatted = self::format( $r );
            $formatted['mosque_name'] = $r->mosque_name;
            $formatted['mosque_slug'] = $r->mosque_slug;
            $formatted['mosque_city'] = $r->mosque_city;
            return $formatted;
        }, $results );

        return new \WP_REST_Response( [ 'ok' => true, 'events' => $events ] );
    }

    /**
     * GET /events/{id} — Single event detail.
     */
    public static function get_single( \WP_REST_Request $request ) {
        $id = absint( $request->get_param( 'id' ) );

        global $wpdb;
        $table = YNJ_DB::table( 'events' );
        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND status = 'published'", $id
        ) );

        if ( ! $event ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event not found.' ], 404 );
        }

        $formatted = self::format( $event );
        $formatted['spots_remaining'] = $event->max_capacity > 0
            ? max( 0, $event->max_capacity - $event->registered_count )
            : null;

        // Get mosque name
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT name, slug FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $event->mosque_id
        ) );
        $formatted['mosque_name'] = $mosque->name ?? '';
        $formatted['mosque_slug'] = $mosque->slug ?? '';

        return new \WP_REST_Response( [ 'ok' => true, 'event' => $formatted ] );
    }

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
            'ticket_price_pence'    => absint( $data['ticket_price_pence'] ?? 0 ),
            'is_online'             => absint( $data['is_online'] ?? 0 ),
            'is_live'               => absint( $data['is_live'] ?? 0 ),
            'live_url'              => esc_url_raw( $data['live_url'] ?? '' ),
            'donation_target_pence' => absint( $data['donation_target_pence'] ?? 0 ),
            'status'                => sanitize_text_field( $data['status'] ?? 'draft' ),
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create event.' ], 500 );
        }

        $event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        // Notify subscribers if published
        $status = sanitize_text_field( $data['status'] ?? 'draft' );
        if ( $status === 'published' ) {
            do_action( 'ynj_new_event', (int) $mosque->id, [
                'title'      => $insert['title'],
                'event_date' => $insert['event_date'] ?? '',
                'event_type' => $insert['event_type'] ?? '',
                'event_id'   => $id,
            ] );
        }

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
            'location', 'event_type', 'max_capacity', 'requires_booking', 'ticket_price_pence',
            'is_online', 'is_live', 'live_url', 'donation_target_pence', 'status',
        ];

        $update = [];
        foreach ( $allowed as $key ) {
            if ( ! isset( $data[ $key ] ) ) continue;

            switch ( $key ) {
                case 'description':
                    $update[ $key ] = wp_kses_post( $data[ $key ] );
                    break;
                case 'image_url':
                case 'live_url':
                    $update[ $key ] = esc_url_raw( $data[ $key ] );
                    break;
                case 'max_capacity':
                case 'requires_booking':
                case 'ticket_price_pence':
                case 'is_online':
                case 'is_live':
                case 'donation_target_pence':
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
            'id'                    => (int) $row->id,
            'mosque_id'             => (int) $row->mosque_id,
            'title'                 => $row->title,
            'description'           => $row->description,
            'image_url'             => $row->image_url,
            'event_date'            => $row->event_date,
            'start_time'            => $row->start_time,
            'end_time'              => $row->end_time,
            'location'              => $row->location,
            'event_type'            => $row->event_type,
            'max_capacity'          => (int) $row->max_capacity,
            'registered_count'      => (int) $row->registered_count,
            'requires_booking'      => (bool) $row->requires_booking,
            'ticket_price_pence'    => (int) $row->ticket_price_pence,
            'is_online'             => (bool) ( $row->is_online ?? 0 ),
            'is_live'               => (bool) ( $row->is_live ?? 0 ),
            'live_url'              => $row->live_url ?? '',
            'live_started_at'       => $row->live_started_at ?? null,
            'donation_target_pence' => (int) ( $row->donation_target_pence ?? 0 ),
            'donation_raised_pence' => (int) ( $row->donation_raised_pence ?? 0 ),
            'donation_count'        => (int) ( $row->donation_count ?? 0 ),
            'status'                => $row->status,
            'created_at'            => $row->created_at,
        ];
    }
}
