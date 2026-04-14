<?php
/**
 * YourJannah — REST API: Booking endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Bookings {

    const NS = 'ynj/v1';

    /**
     * Register booking routes.
     */
    public static function register() {

        // POST /bookings — Public booking creation
        register_rest_route( self::NS, '/bookings', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /admin/bookings?type=event|room&status=
        register_rest_route( self::NS, '/admin/bookings', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_admin' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // PUT /admin/bookings/{id}
        register_rest_route( self::NS, '/admin/bookings/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update_status' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);
    }

    // ================================================================
    // HANDLERS
    // ================================================================

    /**
     * POST /bookings — Create a booking (public). Rate limited 5/min.
     */
    public static function create( \WP_REST_Request $request ) {
        $ip = self::get_ip();
        if ( ! self::rate_limit( 'booking_' . $ip, 5 ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Too many requests. Please wait.' ], 429 );
        }

        $data = $request->get_json_params();

        $event_id   = absint( $data['event_id'] ?? 0 );
        $room_id    = absint( $data['room_id'] ?? 0 );
        $user_name  = sanitize_text_field( $data['user_name'] ?? '' );
        $user_email = sanitize_email( $data['user_email'] ?? '' );
        $user_phone = sanitize_text_field( $data['user_phone'] ?? '' );

        if ( ! $event_id && ! $room_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'event_id or room_id is required.' ], 400 );
        }

        if ( empty( $user_name ) || ! is_email( $user_email ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Name and valid email are required.' ], 400 );
        }

        global $wpdb;

        // Resolve mosque_id from event or room
        $mosque_id = 0;
        if ( $event_id ) {
            $event_table = YNJ_DB::table( 'events' );
            $event = $wpdb->get_row( $wpdb->prepare(
                "SELECT mosque_id, max_capacity, registered_count FROM $event_table WHERE id = %d AND status = 'published'",
                $event_id
            ) );
            if ( ! $event ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event not found.' ], 404 );
            }
            if ( $event->max_capacity > 0 && $event->registered_count >= $event->max_capacity ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Event is fully booked.' ], 409 );
            }
            $mosque_id = (int) $event->mosque_id;
        } elseif ( $room_id ) {
            $room_table = YNJ_DB::table( 'rooms' );
            $room = $wpdb->get_row( $wpdb->prepare(
                "SELECT mosque_id FROM $room_table WHERE id = %d AND status = 'active'",
                $room_id
            ) );
            if ( ! $room ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Room not found.' ], 404 );
            }
            $mosque_id = (int) $room->mosque_id;
        }

        $table = YNJ_DB::table( 'bookings' );

        $insert = [
            'mosque_id'    => $mosque_id,
            'event_id'     => $event_id ?: null,
            'room_id'      => $room_id ?: null,
            'user_name'    => $user_name,
            'user_email'   => $user_email,
            'user_phone'   => $user_phone,
            'booking_date' => sanitize_text_field( $data['booking_date'] ?? date( 'Y-m-d' ) ),
            'start_time'   => sanitize_text_field( $data['start_time'] ?? '' ),
            'end_time'     => sanitize_text_field( $data['end_time'] ?? '' ),
            'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
            'status'       => 'pending',
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create booking.' ], 500 );
        }

        // Increment registered_count for events
        if ( $event_id ) {
            $event_table = YNJ_DB::table( 'events' );
            $wpdb->query( $wpdb->prepare(
                "UPDATE $event_table SET registered_count = registered_count + 1 WHERE id = %d",
                $event_id
            ) );
        }

        return new \WP_REST_Response( [
            'ok'         => true,
            'booking_id' => $id,
            'message'    => 'Booking submitted. You will receive a confirmation.',
        ], 201 );
    }

    /**
     * GET /admin/bookings — List bookings for mosque.
     */
    public static function list_admin( \WP_REST_Request $request ) {
        $mosque   = $request->get_param( '_ynj_mosque' );
        $type     = sanitize_text_field( $request->get_param( 'type' ) ?? '' );
        $status   = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
        $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 100 );
        $offset   = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'bookings' );

        $where = $wpdb->prepare( "mosque_id = %d", (int) $mosque->id );

        if ( $type === 'event' ) {
            $where .= " AND event_id IS NOT NULL";
        } elseif ( $type === 'room' ) {
            $where .= " AND room_id IS NOT NULL";
        }

        if ( ! empty( $status ) ) {
            $where .= $wpdb->prepare( " AND status = %s", $status );
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        $bookings = array_map( function( $row ) {
            return [
                'id'           => (int) $row->id,
                'event_id'     => $row->event_id ? (int) $row->event_id : null,
                'room_id'      => $row->room_id ? (int) $row->room_id : null,
                'user_name'    => $row->user_name,
                'user_email'   => $row->user_email,
                'user_phone'   => $row->user_phone,
                'booking_date' => $row->booking_date,
                'start_time'   => $row->start_time,
                'end_time'     => $row->end_time,
                'notes'        => $row->notes,
                'status'       => $row->status,
                'created_at'   => $row->created_at,
            ];
        }, $results );

        return new \WP_REST_Response( [
            'ok'       => true,
            'bookings' => $bookings,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ] );
    }

    /**
     * PUT /admin/bookings/{id} — Update booking status (confirm/cancel).
     */
    public static function update_status( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();

        $new_status = sanitize_text_field( $data['status'] ?? '' );
        if ( ! in_array( $new_status, [ 'confirmed', 'cancelled', 'pending' ], true ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid status. Use: confirmed, cancelled, pending.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'bookings' );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $id, (int) $mosque->id
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Booking not found.' ], 404 );
        }

        $wpdb->update( $table, [ 'status' => $new_status ], [ 'id' => $id ] );

        // If cancelling an event booking, decrement registered_count
        if ( $new_status === 'cancelled' && $existing->status !== 'cancelled' && $existing->event_id ) {
            $event_table = YNJ_DB::table( 'events' );
            $wpdb->query( $wpdb->prepare(
                "UPDATE $event_table SET registered_count = GREATEST(0, registered_count - 1) WHERE id = %d",
                $existing->event_id
            ) );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Booking status updated to ' . $new_status . '.',
        ] );
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Get client IP address.
     */
    private static function get_ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            return trim( $parts[0] );
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Transient-based rate limiter.
     */
    private static function rate_limit( $key, $max_per_minute ) {
        $transient = 'ynj_rl_' . md5( $key );
        $count     = (int) get_transient( $transient );

        if ( $count >= $max_per_minute ) {
            return false;
        }

        set_transient( $transient, $count + 1, 60 );
        return true;
    }
}
