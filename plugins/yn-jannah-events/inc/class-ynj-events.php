<?php
/**
 * YourJannah — Events data layer.
 *
 * PHP-first $wpdb wrapper for announcements, events, bookings and rooms.
 * Every public method sanitises inputs and uses $wpdb->prepare().
 *
 * @package YNJ_Events
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Events {

    // ================================================================
    //  ANNOUNCEMENTS
    // ================================================================

    /**
     * Get published announcements for a mosque (public view).
     *
     * @param  int   $mosque_id
     * @param  int   $page
     * @param  int   $per_page
     * @return array { announcements: array, total: int }
     */
    public static function get_announcements( $mosque_id, $page = 1, $per_page = 20 ) {
        global $wpdb;
        $table    = YNJ_DB::table( 'announcements' );
        $mosque_id = absint( $mosque_id );
        $per_page  = min( absint( $per_page ), 100 );
        $page      = max( 1, absint( $page ) );
        $offset    = ( $page - 1 ) * $per_page;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table
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

        return [
            'announcements' => array_map( [ __CLASS__, 'format_announcement' ], $rows ?: [] ),
            'total'         => $total,
        ];
    }

    /**
     * Get ALL announcements for a mosque (admin view, includes drafts).
     *
     * @param  int   $mosque_id
     * @param  int   $limit
     * @return array
     */
    public static function get_announcements_admin( $mosque_id, $limit = 200 ) {
        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table
             WHERE mosque_id = %d
             ORDER BY pinned DESC, published_at DESC
             LIMIT %d",
            absint( $mosque_id ), absint( $limit )
        ) );

        return array_map( [ __CLASS__, 'format_announcement' ], $rows ?: [] );
    }

    /**
     * Get a single announcement by ID.
     *
     * @param  int       $id
     * @return object|null
     */
    public static function get_announcement( $id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            absint( $id )
        ) );
    }

    /**
     * Create an announcement.
     *
     * @param  array    $data  Required: mosque_id, title, body.
     * @return int|false       Insert ID or false on failure.
     */
    public static function create_announcement( $data ) {
        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        $publish = ! empty( $data['publish'] );
        $status  = $publish ? 'published' : sanitize_text_field( $data['status'] ?? 'draft' );

        $insert = [
            'mosque_id'    => absint( $data['mosque_id'] ),
            'title'        => sanitize_text_field( $data['title'] ?? '' ),
            'body'         => wp_kses_post( $data['body'] ?? '' ),
            'image_url'    => esc_url_raw( $data['image_url'] ?? '' ),
            'type'         => sanitize_text_field( $data['type'] ?? 'general' ),
            'pinned'       => absint( $data['pinned'] ?? 0 ),
            'expires_at'   => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
            'status'       => $status,
            'published_at' => $publish ? current_time( 'mysql' ) : null,
        ];

        if ( isset( $data['author_user_id'] ) ) {
            $insert['author_user_id'] = absint( $data['author_user_id'] );
        }
        if ( isset( $data['author_role'] ) ) {
            $insert['author_role'] = sanitize_text_field( $data['author_role'] );
        }
        if ( isset( $data['scheduled_at'] ) ) {
            $insert['scheduled_at'] = sanitize_text_field( $data['scheduled_at'] );
        }
        if ( isset( $data['approval_status'] ) ) {
            $insert['approval_status'] = sanitize_text_field( $data['approval_status'] );
        }
        if ( isset( $data['published_at'] ) && ! $publish ) {
            $insert['published_at'] = sanitize_text_field( $data['published_at'] );
        }

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return false;
        }

        if ( $publish ) {
            /**
             * Fires when a new announcement is published.
             *
             * @param int   $mosque_id
             * @param array $context  { title, body, type, ann_id }
             */
            do_action( 'ynj_new_announcement', absint( $data['mosque_id'] ), [
                'title'  => $insert['title'],
                'body'   => wp_strip_all_tags( $insert['body'] ),
                'type'   => $insert['type'],
                'ann_id' => $id,
            ] );
        }

        return $id;
    }

    /**
     * Update an announcement.
     *
     * @param  int       $id
     * @param  int       $mosque_id  Ownership check.
     * @param  array     $data       Fields to update.
     * @return object|false          Updated row or false if not found.
     */
    public static function update_announcement( $id, $mosque_id, $data ) {
        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );
        $id        = absint( $id );
        $mosque_id = absint( $mosque_id );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $id, $mosque_id
        ) );

        if ( ! $existing ) {
            return false;
        }

        $update = [];
        if ( isset( $data['title'] ) )      $update['title']      = sanitize_text_field( $data['title'] );
        if ( isset( $data['body'] ) )        $update['body']       = wp_kses_post( $data['body'] );
        if ( isset( $data['image_url'] ) )   $update['image_url']  = esc_url_raw( $data['image_url'] );
        if ( isset( $data['type'] ) )        $update['type']       = sanitize_text_field( $data['type'] );
        if ( isset( $data['pinned'] ) )      $update['pinned']     = absint( $data['pinned'] );
        if ( isset( $data['expires_at'] ) )  $update['expires_at'] = sanitize_text_field( $data['expires_at'] );
        if ( isset( $data['status'] ) )      $update['status']     = sanitize_text_field( $data['status'] );

        // Transition to published: set published_at.
        if ( isset( $data['status'] ) && $data['status'] === 'published' && $existing->status !== 'published' ) {
            $update['published_at'] = current_time( 'mysql' );
        }

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => $id ] );
        }

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
    }

    /**
     * Delete an announcement.
     *
     * @param  int  $id
     * @param  int  $mosque_id  Ownership check.
     * @return bool
     */
    public static function delete_announcement( $id, $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'announcements' );

        return (bool) $wpdb->delete( $table, [
            'id'        => absint( $id ),
            'mosque_id' => absint( $mosque_id ),
        ] );
    }

    // ================================================================
    //  EVENTS
    // ================================================================

    /**
     * Get published events for a mosque (public view).
     *
     * @param  int   $mosque_id
     * @param  int   $page
     * @param  int   $per_page
     * @return array { events: array, total: int }
     */
    public static function get_events( $mosque_id, $page = 1, $per_page = 20 ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'events' );
        $mosque_id = absint( $mosque_id );
        $per_page  = min( absint( $per_page ), 100 );
        $page      = max( 1, absint( $page ) );
        $offset    = ( $page - 1 ) * $per_page;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table
             WHERE mosque_id = %d AND status = 'published'
             ORDER BY event_date ASC, start_time ASC
             LIMIT %d OFFSET %d",
            $mosque_id, $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE mosque_id = %d AND status = 'published'",
            $mosque_id
        ) );

        return [
            'events' => array_map( [ __CLASS__, 'format_event' ], $rows ?: [] ),
            'total'  => $total,
        ];
    }

    /**
     * Get upcoming published events for a mosque (event_date >= today).
     *
     * @param  int   $mosque_id
     * @param  int   $page
     * @param  int   $per_page
     * @return array { events: array, total: int }
     */
    public static function get_upcoming_events( $mosque_id, $page = 1, $per_page = 20 ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'events' );
        $mosque_id = absint( $mosque_id );
        $per_page  = min( absint( $per_page ), 100 );
        $page      = max( 1, absint( $page ) );
        $offset    = ( $page - 1 ) * $per_page;
        $today     = date( 'Y-m-d' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table
             WHERE mosque_id = %d AND status = 'published' AND event_date >= %s
             ORDER BY event_date ASC, start_time ASC
             LIMIT %d OFFSET %d",
            $mosque_id, $today, $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE mosque_id = %d AND status = 'published' AND event_date >= %s",
            $mosque_id, $today
        ) );

        return [
            'events' => array_map( [ __CLASS__, 'format_event' ], $rows ?: [] ),
            'total'  => $total,
        ];
    }

    /**
     * Get ALL events for a mosque (admin view, includes drafts).
     *
     * @param  int   $mosque_id
     * @param  int   $limit
     * @return array
     */
    public static function get_events_admin( $mosque_id, $limit = 200 ) {
        global $wpdb;
        $table = YNJ_DB::table( 'events' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table
             WHERE mosque_id = %d
             ORDER BY event_date DESC, start_time ASC
             LIMIT %d",
            absint( $mosque_id ), absint( $limit )
        ) );

        return array_map( [ __CLASS__, 'format_event' ], $rows ?: [] );
    }

    /**
     * Get a single event by ID.
     *
     * @param  int       $id
     * @return object|null
     */
    public static function get_event( $id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'events' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            absint( $id )
        ) );
    }

    /**
     * Create an event.
     *
     * @param  array    $data  Required: mosque_id, title.
     * @return int|false       Insert ID or false on failure.
     */
    public static function create_event( $data ) {
        global $wpdb;
        $table = YNJ_DB::table( 'events' );

        $status = sanitize_text_field( $data['status'] ?? 'draft' );

        $insert = [
            'mosque_id'             => absint( $data['mosque_id'] ),
            'title'                 => sanitize_text_field( $data['title'] ?? '' ),
            'description'           => wp_kses_post( $data['description'] ?? '' ),
            'image_url'             => esc_url_raw( $data['image_url'] ?? '' ),
            'event_date'            => sanitize_text_field( $data['event_date'] ?? '' ),
            'start_time'            => sanitize_text_field( $data['start_time'] ?? '' ),
            'end_time'              => sanitize_text_field( $data['end_time'] ?? '' ),
            'location'              => sanitize_text_field( $data['location'] ?? '' ),
            'event_type'            => sanitize_text_field( $data['event_type'] ?? '' ),
            'max_capacity'          => absint( $data['max_capacity'] ?? 0 ),
            'requires_booking'      => absint( $data['requires_booking'] ?? 0 ),
            'ticket_price_pence'    => absint( $data['ticket_price_pence'] ?? 0 ),
            'is_online'             => absint( $data['is_online'] ?? 0 ),
            'is_live'               => absint( $data['is_live'] ?? 0 ),
            'live_url'              => esc_url_raw( $data['live_url'] ?? '' ),
            'donation_target_pence' => absint( $data['donation_target_pence'] ?? 0 ),
            'needs_volunteers'      => absint( $data['needs_volunteers'] ?? 0 ),
            'volunteer_roles'       => sanitize_text_field( $data['volunteer_roles'] ?? '' ),
            'status'                => $status,
        ];

        if ( isset( $data['scheduled_at'] ) ) {
            $insert['scheduled_at'] = sanitize_text_field( $data['scheduled_at'] );
        }

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return false;
        }

        if ( $status === 'published' ) {
            /**
             * Fires when a new event is published.
             *
             * @param int   $mosque_id
             * @param array $context  { title, event_date, event_type, event_id }
             */
            do_action( 'ynj_new_event', absint( $data['mosque_id'] ), [
                'title'      => $insert['title'],
                'event_date' => $insert['event_date'],
                'event_type' => $insert['event_type'],
                'event_id'   => $id,
            ] );
        }

        return $id;
    }

    /**
     * Update an event.
     *
     * @param  int       $id
     * @param  int       $mosque_id  Ownership check.
     * @param  array     $data       Fields to update.
     * @return object|false          Updated row or false if not found.
     */
    public static function update_event( $id, $mosque_id, $data ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'events' );
        $id        = absint( $id );
        $mosque_id = absint( $mosque_id );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $id, $mosque_id
        ) );

        if ( ! $existing ) {
            return false;
        }

        $allowed = [
            'title', 'description', 'image_url', 'event_date', 'start_time', 'end_time',
            'location', 'event_type', 'max_capacity', 'requires_booking', 'ticket_price_pence',
            'is_online', 'is_live', 'live_url', 'donation_target_pence',
            'needs_volunteers', 'volunteer_roles', 'status',
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
                case 'needs_volunteers':
                    $update[ $key ] = absint( $data[ $key ] );
                    break;
                default:
                    $update[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => $id ] );
        }

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
    }

    /**
     * Delete an event (and its volunteers).
     *
     * @param  int  $id
     * @param  int  $mosque_id  Ownership check.
     * @return bool
     */
    public static function delete_event( $id, $mosque_id ) {
        global $wpdb;
        $id        = absint( $id );
        $mosque_id = absint( $mosque_id );

        $deleted = (bool) $wpdb->delete( YNJ_DB::table( 'events' ), [
            'id'        => $id,
            'mosque_id' => $mosque_id,
        ] );

        if ( $deleted ) {
            // Cascade: remove associated volunteers.
            $wpdb->delete( YNJ_DB::table( 'event_volunteers' ), [ 'event_id' => $id ] );
        }

        return $deleted;
    }

    /**
     * RSVP / register for an event (public).
     *
     * Creates a booking row and increments registered_count on the event.
     *
     * @param  int   $event_id
     * @param  array $user_data  { user_name, user_email, user_phone?, notes? }
     * @return int|WP_Error      Booking ID or error.
     */
    public static function rsvp( $event_id, $user_data ) {
        global $wpdb;
        $event_id = absint( $event_id );

        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'events' ) . " WHERE id = %d AND status = 'published'",
            $event_id
        ) );

        if ( ! $event ) {
            return new \WP_Error( 'not_found', 'Event not found.' );
        }

        if ( $event->max_capacity > 0 && $event->registered_count >= $event->max_capacity ) {
            return new \WP_Error( 'fully_booked', 'Event is fully booked.' );
        }

        $user_name  = sanitize_text_field( $user_data['user_name'] ?? '' );
        $user_email = sanitize_email( $user_data['user_email'] ?? '' );

        if ( empty( $user_name ) || ! is_email( $user_email ) ) {
            return new \WP_Error( 'validation', 'Name and valid email are required.' );
        }

        $booking_table = YNJ_DB::table( 'bookings' );

        $wpdb->insert( $booking_table, [
            'mosque_id'    => (int) $event->mosque_id,
            'event_id'     => $event_id,
            'user_name'    => $user_name,
            'user_email'   => $user_email,
            'user_phone'   => sanitize_text_field( $user_data['user_phone'] ?? '' ),
            'booking_date' => $event->event_date,
            'start_time'   => $event->start_time,
            'end_time'     => $event->end_time,
            'notes'        => sanitize_textarea_field( $user_data['notes'] ?? '' ),
            'status'       => 'confirmed',
        ] );

        $booking_id = (int) $wpdb->insert_id;

        if ( ! $booking_id ) {
            return new \WP_Error( 'db_error', 'Failed to create booking.' );
        }

        // Increment registered_count.
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . YNJ_DB::table( 'events' ) . " SET registered_count = registered_count + 1 WHERE id = %d",
            $event_id
        ) );

        return $booking_id;
    }

    /**
     * Volunteer sign-up for an event.
     *
     * @param  int   $event_id
     * @param  array $data  { name, email, phone?, role? }
     * @return int|WP_Error  Volunteer row ID or error.
     */
    public static function volunteer_signup( $event_id, $data ) {
        global $wpdb;
        $event_id = absint( $event_id );

        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'events' ) . " WHERE id = %d",
            $event_id
        ) );

        if ( ! $event || ! $event->needs_volunteers ) {
            return new \WP_Error( 'not_found', 'Event not found or not accepting volunteers.' );
        }

        $name  = sanitize_text_field( $data['name'] ?? '' );
        $email = sanitize_email( $data['email'] ?? '' );

        if ( ! $name || ! is_email( $email ) ) {
            return new \WP_Error( 'validation', 'Name and email required.' );
        }

        $wpdb->insert( YNJ_DB::table( 'event_volunteers' ), [
            'event_id'   => $event_id,
            'mosque_id'  => (int) $event->mosque_id,
            'user_name'  => $name,
            'user_email' => $email,
            'user_phone' => sanitize_text_field( $data['phone'] ?? '' ),
            'role'       => sanitize_text_field( $data['role'] ?? '' ),
        ] );

        $vol_id = (int) $wpdb->insert_id;

        if ( ! $vol_id ) {
            return new \WP_Error( 'db_error', 'Failed to register volunteer.' );
        }

        // Increment volunteer count.
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . YNJ_DB::table( 'events' ) . " SET volunteer_count = volunteer_count + 1 WHERE id = %d",
            $event_id
        ) );

        return $vol_id;
    }

    /**
     * Get live + upcoming online events across all mosques.
     *
     * @param  int   $limit
     * @return array
     */
    public static function get_live_events( $limit = 50 ) {
        global $wpdb;
        $event_table  = YNJ_DB::table( 'events' );
        $mosque_table = YNJ_DB::table( 'mosques' );
        $today        = date( 'Y-m-d' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, m.name AS mosque_name, m.slug AS mosque_slug, m.city AS mosque_city
             FROM $event_table e
             INNER JOIN $mosque_table m ON m.id = e.mosque_id
             WHERE e.status = 'published' AND e.is_online = 1
               AND ( e.is_live = 1 OR e.event_date >= %s )
             ORDER BY e.is_live DESC, e.event_date ASC, e.start_time ASC
             LIMIT %d",
            $today, absint( $limit )
        ) );

        return array_map( function( $r ) {
            $formatted = self::format_event( $r );
            $formatted['mosque_name'] = $r->mosque_name;
            $formatted['mosque_slug'] = $r->mosque_slug;
            $formatted['mosque_city'] = $r->mosque_city;
            return $formatted;
        }, $rows ?: [] );
    }

    // ================================================================
    //  BOOKINGS
    // ================================================================

    /**
     * Get bookings for a mosque (admin view).
     *
     * @param  int    $mosque_id
     * @param  array  $args  { type: event|room, status: string, page: int, per_page: int }
     * @return array  { bookings: array, total: int }
     */
    public static function get_bookings( $mosque_id, $args = [] ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'bookings' );
        $mosque_id = absint( $mosque_id );
        $type      = sanitize_text_field( $args['type'] ?? '' );
        $status    = sanitize_text_field( $args['status'] ?? '' );
        $per_page  = min( absint( $args['per_page'] ?? 20 ), 100 );
        $page      = max( 1, absint( $args['page'] ?? 1 ) );
        $offset    = ( $page - 1 ) * $per_page;

        $where = $wpdb->prepare( "mosque_id = %d", $mosque_id );

        if ( $type === 'event' ) {
            $where .= " AND event_id IS NOT NULL";
        } elseif ( $type === 'room' ) {
            $where .= " AND room_id IS NOT NULL";
        }

        if ( ! empty( $status ) ) {
            $where .= $wpdb->prepare( " AND status = %s", $status );
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        return [
            'bookings' => array_map( [ __CLASS__, 'format_booking' ], $rows ?: [] ),
            'total'    => $total,
        ];
    }

    /**
     * Create a booking (event RSVP or room hire).
     *
     * @param  array $data  Required: (event_id OR room_id), user_name, user_email.
     * @return int|WP_Error  Booking ID or error.
     */
    public static function create_booking( $data ) {
        global $wpdb;

        $event_id   = absint( $data['event_id'] ?? 0 );
        $room_id    = absint( $data['room_id'] ?? 0 );
        $user_name  = sanitize_text_field( $data['user_name'] ?? '' );
        $user_email = sanitize_email( $data['user_email'] ?? '' );

        if ( ! $event_id && ! $room_id ) {
            return new \WP_Error( 'validation', 'event_id or room_id is required.' );
        }

        if ( empty( $user_name ) || ! is_email( $user_email ) ) {
            return new \WP_Error( 'validation', 'Name and valid email are required.' );
        }

        // Resolve mosque_id and validate capacity.
        $mosque_id = 0;

        if ( $event_id ) {
            $event = $wpdb->get_row( $wpdb->prepare(
                "SELECT mosque_id, max_capacity, registered_count
                 FROM " . YNJ_DB::table( 'events' ) . "
                 WHERE id = %d AND status = 'published'",
                $event_id
            ) );
            if ( ! $event ) {
                return new \WP_Error( 'not_found', 'Event not found.' );
            }
            if ( $event->max_capacity > 0 && $event->registered_count >= $event->max_capacity ) {
                return new \WP_Error( 'fully_booked', 'Event is fully booked.' );
            }
            $mosque_id = (int) $event->mosque_id;
        } elseif ( $room_id ) {
            $room = $wpdb->get_row( $wpdb->prepare(
                "SELECT mosque_id FROM " . YNJ_DB::table( 'rooms' ) . " WHERE id = %d AND status = 'active'",
                $room_id
            ) );
            if ( ! $room ) {
                return new \WP_Error( 'not_found', 'Room not found.' );
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
            'user_phone'   => sanitize_text_field( $data['user_phone'] ?? '' ),
            'booking_date' => sanitize_text_field( $data['booking_date'] ?? date( 'Y-m-d' ) ),
            'start_time'   => sanitize_text_field( $data['start_time'] ?? '' ),
            'end_time'     => sanitize_text_field( $data['end_time'] ?? '' ),
            'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
            'status'       => 'pending',
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_Error( 'db_error', 'Failed to create booking.' );
        }

        // Increment registered_count for event bookings.
        if ( $event_id ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE " . YNJ_DB::table( 'events' ) . " SET registered_count = registered_count + 1 WHERE id = %d",
                $event_id
            ) );
        }

        /**
         * Fires when a new booking is created.
         *
         * @param int   $mosque_id
         * @param array $context
         */
        do_action( 'ynj_new_booking', $mosque_id, [
            'booking_id' => $id,
            'event_id'   => $event_id,
            'room_id'    => $room_id,
            'user_name'  => $user_name,
            'user_email' => $user_email,
        ] );

        return $id;
    }

    /**
     * Update booking status (confirm / cancel / pending).
     *
     * @param  int    $id
     * @param  int    $mosque_id  Ownership check.
     * @param  string $status     confirmed|cancelled|pending
     * @param  string $notes      Optional admin notes.
     * @return bool|WP_Error
     */
    public static function update_booking_status( $id, $mosque_id, $status, $notes = '' ) {
        global $wpdb;
        $id        = absint( $id );
        $mosque_id = absint( $mosque_id );
        $status    = sanitize_text_field( $status );

        $valid = [ 'confirmed', 'cancelled', 'pending' ];
        if ( ! in_array( $status, $valid, true ) ) {
            return new \WP_Error( 'validation', 'Invalid status. Use: confirmed, cancelled, pending.' );
        }

        $table    = YNJ_DB::table( 'bookings' );
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $id, $mosque_id
        ) );

        if ( ! $existing ) {
            return new \WP_Error( 'not_found', 'Booking not found.' );
        }

        $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ] );

        if ( ! empty( $notes ) ) {
            $wpdb->update( $table, [ 'notes' => sanitize_textarea_field( $notes ) ], [ 'id' => $id ] );
        }

        // Decrement registered_count when cancelling an event booking.
        if ( $status === 'cancelled' && $existing->status !== 'cancelled' && $existing->event_id ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE " . YNJ_DB::table( 'events' ) . " SET registered_count = GREATEST(0, registered_count - 1) WHERE id = %d",
                $existing->event_id
            ) );
        }

        /**
         * Fires when a booking status changes.
         *
         * @param int   $mosque_id
         * @param array $context
         */
        do_action( 'ynj_booking_status_changed', $mosque_id, [
            'booking_id'   => $id,
            'user_name'    => $existing->user_name,
            'user_email'   => $existing->user_email,
            'booking_date' => $existing->booking_date,
            'start_time'   => $existing->start_time,
            'end_time'     => $existing->end_time,
            'status'       => $status,
            'notes'        => $notes ?: $existing->notes,
        ] );

        return true;
    }

    // ================================================================
    //  ROOMS
    // ================================================================

    /**
     * Get active rooms for a mosque.
     *
     * @param  int   $mosque_id
     * @return array
     */
    public static function get_rooms( $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'rooms' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table
             WHERE mosque_id = %d AND status = 'active'
             ORDER BY name ASC",
            absint( $mosque_id )
        ) );

        return array_map( [ __CLASS__, 'format_room' ], $rows ?: [] );
    }

    /**
     * Get a single room by ID.
     *
     * @param  int       $id
     * @return object|null
     */
    public static function get_room( $id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'rooms' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            absint( $id )
        ) );
    }

    // ================================================================
    //  USER-SPECIFIC QUERIES
    // ================================================================

    /**
     * Get a user's bookings across all mosques.
     *
     * @param  string $email  User email.
     * @param  int    $limit  Max results.
     * @return array          Flat array of booking objects (with mosque_name, event_title, room_name).
     */
    public static function get_user_bookings( $email, $limit = 20 ) {
        global $wpdb;
        $bt = YNJ_DB::table( 'bookings' );
        $mt = YNJ_DB::table( 'mosques' );
        $et = YNJ_DB::table( 'events' );
        $rt = YNJ_DB::table( 'rooms' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, m.name AS mosque_name, e.title AS event_title, r.name AS room_name
             FROM $bt b LEFT JOIN $mt m ON m.id = b.mosque_id
             LEFT JOIN $et e ON e.id = b.event_id LEFT JOIN $rt r ON r.id = b.room_id
             WHERE b.user_email = %s ORDER BY b.created_at DESC LIMIT %d",
            sanitize_email( $email ), absint( $limit )
        ) ) ?: [];
    }

    // ================================================================
    //  FORMATTERS
    // ================================================================

    /**
     * Format an announcement row.
     *
     * @param  object $row
     * @return array
     */
    public static function format_announcement( $row ) {
        return [
            'id'              => (int) $row->id,
            'mosque_id'       => (int) $row->mosque_id,
            'title'           => $row->title,
            'body'            => $row->body,
            'image_url'       => $row->image_url,
            'type'            => $row->type,
            'pinned'          => (bool) $row->pinned,
            'push_sent'       => (bool) $row->push_sent,
            'expires_at'      => $row->expires_at,
            'status'          => $row->status,
            'published_at'    => $row->published_at,
            'created_at'      => $row->created_at,
        ];
    }

    /**
     * Format an event row.
     *
     * @param  object $row
     * @return array
     */
    public static function format_event( $row ) {
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
            'needs_volunteers'      => (bool) ( $row->needs_volunteers ?? 0 ),
            'volunteer_roles'       => $row->volunteer_roles ?? '',
            'volunteer_count'       => (int) ( $row->volunteer_count ?? 0 ),
            'donation_target_pence' => (int) ( $row->donation_target_pence ?? 0 ),
            'donation_raised_pence' => (int) ( $row->donation_raised_pence ?? 0 ),
            'donation_count'        => (int) ( $row->donation_count ?? 0 ),
            'status'                => $row->status,
            'created_at'            => $row->created_at,
        ];
    }

    /**
     * Format a booking row.
     *
     * @param  object $row
     * @return array
     */
    public static function format_booking( $row ) {
        return [
            'id'           => (int) $row->id,
            'mosque_id'    => (int) $row->mosque_id,
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
    }

    /**
     * Format a room row.
     *
     * @param  object $row
     * @return array
     */
    public static function format_room( $row ) {
        return [
            'id'                => (int) $row->id,
            'mosque_id'         => (int) $row->mosque_id,
            'name'              => $row->name,
            'description'       => $row->description,
            'capacity'          => (int) $row->capacity,
            'hourly_rate_pence' => (int) $row->hourly_rate_pence,
            'daily_rate_pence'  => (int) $row->daily_rate_pence,
            'photo_url'         => $row->photo_url,
            'status'            => $row->status,
            'created_at'        => $row->created_at,
        ];
    }
}
