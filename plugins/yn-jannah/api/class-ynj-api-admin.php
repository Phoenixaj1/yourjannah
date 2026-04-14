<?php
/**
 * YourJannah — REST API: Admin/Dashboard endpoints.
 * Namespace: ynj/v1
 *
 * Handles mosque registration, login, profile management, prayer time
 * overrides, Jumu'ah slots, subscribers, enquiries, and rooms.
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Admin {

    const NS = 'ynj/v1';

    /**
     * Register all admin routes.
     */
    public static function register() {

        // --- Auth (public) ---
        register_rest_route( self::NS, '/admin/register', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_register' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( self::NS, '/admin/login', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_login' ],
            'permission_callback' => '__return_true',
        ]);

        // --- Profile (bearer) ---
        register_rest_route( self::NS, '/admin/me', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_profile' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        register_rest_route( self::NS, '/admin/me', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update_profile' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // --- Prayer management (bearer) ---
        register_rest_route( self::NS, '/admin/prayers', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'set_prayers' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // --- Jumu'ah slots (bearer) ---
        register_rest_route( self::NS, '/admin/jumuah', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'add_jumuah' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        register_rest_route( self::NS, '/admin/jumuah/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete_jumuah' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // --- Subscribers (bearer) ---
        register_rest_route( self::NS, '/admin/subscribers', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_subscribers' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // --- Enquiries (bearer) ---
        register_rest_route( self::NS, '/admin/enquiries', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_enquiries' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        register_rest_route( self::NS, '/admin/enquiries/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update_enquiry' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        // --- Rooms (bearer) ---
        register_rest_route( self::NS, '/admin/rooms', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'add_room' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        register_rest_route( self::NS, '/admin/rooms/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update_room' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        register_rest_route( self::NS, '/admin/rooms/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete_room' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);

        register_rest_route( self::NS, '/admin/rooms', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_rooms' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ]);
    }

    // ================================================================
    // AUTH HANDLERS
    // ================================================================

    /**
     * POST /admin/register — Mosque registration. Rate limited 3/min.
     */
    public static function handle_register( \WP_REST_Request $request ) {
        $ip = self::get_ip();
        if ( ! self::rate_limit( 'register_' . $ip, 3 ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Too many requests. Try again later.' ], 429 );
        }

        $data   = $request->get_json_params();
        $result = YNJ_Auth::register( $data );

        if ( ! $result['ok'] ) {
            return new \WP_REST_Response( $result, 400 );
        }

        // Auto-geocode postcode on registration
        if ( ! empty( $data['postcode'] ) && ! empty( $result['mosque_id'] ) ) {
            self::geocode_postcode( $result['mosque_id'], $data['postcode'] );
        }

        return new \WP_REST_Response( [
            'ok'        => true,
            'token'     => $result['token'],
            'mosque_id' => $result['mosque_id'],
            'slug'      => $result['slug'] ?? '',
            'message'   => 'Mosque registered successfully. Welcome to YourJannah!',
        ], 201 );
    }

    /**
     * POST /admin/login — Email + password login. Rate limited 10/min.
     */
    public static function handle_login( \WP_REST_Request $request ) {
        $ip = self::get_ip();
        if ( ! self::rate_limit( 'login_' . $ip, 10 ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Too many login attempts. Try again later.' ], 429 );
        }

        $data   = $request->get_json_params();
        $result = YNJ_Auth::login( $data );

        if ( ! $result['ok'] ) {
            $status = ( $result['error'] ?? '' ) === 'Invalid email or password.' ? 401 : 400;
            return new \WP_REST_Response( $result, $status );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'token'   => $result['token'],
            'mosque'  => $result['mosque'] ?? null,
        ] );
    }

    // ================================================================
    // PROFILE HANDLERS
    // ================================================================

    /**
     * GET /admin/me — Return mosque profile.
     */
    public static function get_profile( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );

        return new \WP_REST_Response( [
            'ok'     => true,
            'mosque' => self::format_profile( $mosque ),
        ] );
    }

    /**
     * PUT /admin/me — Update mosque profile. Auto-geocodes if postcode changed.
     */
    public static function update_profile( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );

        $allowed = [
            'name', 'address', 'city', 'postcode', 'country', 'timezone',
            'phone', 'email', 'website', 'logo_url', 'photo_url', 'description',
            'has_women_section', 'has_wudu', 'has_parking', 'capacity',
        ];

        $update = [];
        foreach ( $allowed as $key ) {
            if ( ! isset( $data[ $key ] ) ) continue;

            if ( in_array( $key, [ 'has_women_section', 'has_wudu', 'has_parking', 'capacity' ], true ) ) {
                $update[ $key ] = absint( $data[ $key ] );
            } elseif ( in_array( $key, [ 'logo_url', 'photo_url', 'website' ], true ) ) {
                $update[ $key ] = esc_url_raw( $data[ $key ] );
            } elseif ( $key === 'description' ) {
                $update[ $key ] = wp_kses_post( $data[ $key ] );
            } elseif ( $key === 'email' ) {
                $update[ $key ] = sanitize_email( $data[ $key ] );
            } else {
                $update[ $key ] = sanitize_text_field( $data[ $key ] );
            }
        }

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => (int) $mosque->id ] );
        }

        // Auto-geocode postcode if changed
        if ( isset( $data['postcode'] ) && ! empty( $data['postcode'] ) ) {
            self::geocode_postcode( (int) $mosque->id, $data['postcode'] );
        }

        // Reload fresh data
        $fresh = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $mosque->id ) );

        return new \WP_REST_Response( [
            'ok'      => true,
            'mosque'  => self::format_profile( $fresh ),
            'message' => 'Profile updated.',
        ] );
    }

    // ================================================================
    // PRAYER HANDLERS
    // ================================================================

    /**
     * PUT /admin/prayers — Set/override jamat times for a date.
     */
    public static function set_prayers( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $data   = $request->get_json_params();

        $date = sanitize_text_field( $data['date'] ?? '' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Valid date (YYYY-MM-DD) is required.' ], 400 );
        }

        $times = $data['times'] ?? [];
        if ( empty( $times ) || ! is_array( $times ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'times object is required.' ], 400 );
        }

        $result = YNJ_Prayer::set_jamat_times( (int) $mosque->id, $date, $times );

        if ( ! $result ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to save prayer times.' ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Prayer times updated for ' . $date . '.',
        ] );
    }

    // ================================================================
    // JUMUAH HANDLERS
    // ================================================================

    /**
     * POST /admin/jumuah — Add/edit Jumu'ah slot.
     */
    public static function add_jumuah( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'jumuah_times' );

        $row = [
            'mosque_id'    => (int) $mosque->id,
            'slot_name'    => sanitize_text_field( $data['slot_name'] ?? '' ),
            'khutbah_time' => sanitize_text_field( $data['khutbah_time'] ?? '' ),
            'salah_time'   => sanitize_text_field( $data['salah_time'] ?? '' ),
            'language'     => sanitize_text_field( $data['language'] ?? '' ),
            'enabled'      => isset( $data['enabled'] ) ? absint( $data['enabled'] ) : 1,
        ];

        // If id is provided, update existing slot
        $slot_id = absint( $data['id'] ?? 0 );
        if ( $slot_id ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE id = %d AND mosque_id = %d",
                $slot_id, (int) $mosque->id
            ) );

            if ( ! $existing ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Jumu\'ah slot not found.' ], 404 );
            }

            unset( $row['mosque_id'] );
            $wpdb->update( $table, $row, [ 'id' => $slot_id ] );

            return new \WP_REST_Response( [
                'ok'      => true,
                'id'      => $slot_id,
                'message' => 'Jumu\'ah slot updated.',
            ] );
        }

        $wpdb->insert( $table, $row );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create Jumu\'ah slot.' ], 500 );
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'id'      => $id,
            'message' => 'Jumu\'ah slot added.',
        ], 201 );
    }

    /**
     * DELETE /admin/jumuah/{id} — Delete Jumu'ah slot.
     */
    public static function delete_jumuah( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );

        global $wpdb;
        $table = YNJ_DB::table( 'jumuah_times' );

        $deleted = $wpdb->delete( $table, [
            'id'        => $id,
            'mosque_id' => (int) $mosque->id,
        ] );

        if ( ! $deleted ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Jumu\'ah slot not found.' ], 404 );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Jumu\'ah slot deleted.' ] );
    }

    // ================================================================
    // SUBSCRIBER HANDLERS
    // ================================================================

    /**
     * GET /admin/subscribers — List subscribers with count.
     */
    public static function list_subscribers( \WP_REST_Request $request ) {
        $mosque   = $request->get_param( '_ynj_mosque' );
        $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = min( absint( $request->get_param( 'per_page' ) ?: 50 ), 200 );
        $offset   = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'subscribers' );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE mosque_id = %d AND status = 'active'",
            (int) $mosque->id
        ) );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, email, name, phone, device_type, subscribed_at, last_active_at
             FROM $table
             WHERE mosque_id = %d AND status = 'active'
             ORDER BY subscribed_at DESC
             LIMIT %d OFFSET %d",
            (int) $mosque->id, $per_page, $offset
        ) );

        $subscribers = array_map( function( $row ) {
            return [
                'id'             => (int) $row->id,
                'email'          => $row->email,
                'name'           => $row->name,
                'phone'          => $row->phone,
                'device_type'    => $row->device_type,
                'subscribed_at'  => $row->subscribed_at,
                'last_active_at' => $row->last_active_at,
            ];
        }, $results );

        return new \WP_REST_Response( [
            'ok'          => true,
            'subscribers' => $subscribers,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
        ] );
    }

    // ================================================================
    // ENQUIRY HANDLERS
    // ================================================================

    /**
     * GET /admin/enquiries — List enquiries.
     */
    public static function list_enquiries( \WP_REST_Request $request ) {
        $mosque   = $request->get_param( '_ynj_mosque' );
        $status   = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
        $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
        $per_page = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 100 );
        $offset   = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table = YNJ_DB::table( 'enquiries' );

        $where = $wpdb->prepare( "mosque_id = %d", (int) $mosque->id );

        if ( ! empty( $status ) ) {
            $where .= $wpdb->prepare( " AND status = %s", $status );
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

        $enquiries = array_map( function( $row ) {
            return [
                'id'         => (int) $row->id,
                'name'       => $row->name,
                'email'      => $row->email,
                'phone'      => $row->phone,
                'subject'    => $row->subject,
                'message'    => $row->message,
                'type'       => $row->type,
                'status'     => $row->status,
                'replied_at' => $row->replied_at,
                'created_at' => $row->created_at,
            ];
        }, $results );

        return new \WP_REST_Response( [
            'ok'        => true,
            'enquiries' => $enquiries,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $per_page,
        ] );
    }

    /**
     * PUT /admin/enquiries/{id} — Mark enquiry as read/replied.
     */
    public static function update_enquiry( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'enquiries' );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND mosque_id = %d",
            $id, (int) $mosque->id
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Enquiry not found.' ], 404 );
        }

        $update = [];
        if ( isset( $data['status'] ) ) {
            $allowed_statuses = [ 'new', 'read', 'replied', 'archived' ];
            $new_status = sanitize_text_field( $data['status'] );
            if ( ! in_array( $new_status, $allowed_statuses, true ) ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid status.' ], 400 );
            }
            $update['status'] = $new_status;
            if ( $new_status === 'replied' ) {
                $update['replied_at'] = current_time( 'mysql' );
            }
        }

        if ( empty( $update ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'No fields to update.' ], 400 );
        }

        $wpdb->update( $table, $update, [ 'id' => $id ] );

        return new \WP_REST_Response( [
            'ok'      => true,
            'message' => 'Enquiry updated.',
        ] );
    }

    // ================================================================
    // ROOM HANDLERS
    // ================================================================

    /**
     * POST /admin/rooms — Add room.
     */
    public static function add_room( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $data   = $request->get_json_params();

        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( empty( $name ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Room name is required.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'rooms' );

        $insert = [
            'mosque_id'          => (int) $mosque->id,
            'name'               => $name,
            'description'        => sanitize_textarea_field( $data['description'] ?? '' ),
            'capacity'           => absint( $data['capacity'] ?? 0 ),
            'hourly_rate_pence'  => absint( $data['hourly_rate_pence'] ?? 0 ),
            'daily_rate_pence'   => absint( $data['daily_rate_pence'] ?? 0 ),
            'photo_url'          => esc_url_raw( $data['photo_url'] ?? '' ),
            'availability_notes' => sanitize_textarea_field( $data['availability_notes'] ?? '' ),
            'status'             => 'active',
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create room.' ], 500 );
        }

        $room = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        return new \WP_REST_Response( [
            'ok'   => true,
            'room' => self::format_room( $room ),
        ], 201 );
    }

    /**
     * PUT /admin/rooms/{id} — Update room.
     */
    public static function update_room( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );
        $data   = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'rooms' );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND mosque_id = %d",
            $id, (int) $mosque->id
        ) );

        if ( ! $existing ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Room not found.' ], 404 );
        }

        $update = [];
        if ( isset( $data['name'] ) )               $update['name']               = sanitize_text_field( $data['name'] );
        if ( isset( $data['description'] ) )         $update['description']        = sanitize_textarea_field( $data['description'] );
        if ( isset( $data['capacity'] ) )            $update['capacity']           = absint( $data['capacity'] );
        if ( isset( $data['hourly_rate_pence'] ) )   $update['hourly_rate_pence']  = absint( $data['hourly_rate_pence'] );
        if ( isset( $data['daily_rate_pence'] ) )    $update['daily_rate_pence']   = absint( $data['daily_rate_pence'] );
        if ( isset( $data['photo_url'] ) )           $update['photo_url']          = esc_url_raw( $data['photo_url'] );
        if ( isset( $data['availability_notes'] ) )  $update['availability_notes'] = sanitize_textarea_field( $data['availability_notes'] );
        if ( isset( $data['status'] ) )              $update['status']             = sanitize_text_field( $data['status'] );

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => $id ] );
        }

        $room = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        return new \WP_REST_Response( [
            'ok'   => true,
            'room' => self::format_room( $room ),
        ] );
    }

    /**
     * DELETE /admin/rooms/{id} — Delete room.
     */
    public static function delete_room( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );
        $id     = absint( $request->get_param( 'id' ) );

        global $wpdb;
        $table = YNJ_DB::table( 'rooms' );

        $deleted = $wpdb->delete( $table, [
            'id'        => $id,
            'mosque_id' => (int) $mosque->id,
        ] );

        if ( ! $deleted ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Room not found.' ], 404 );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Room deleted.' ] );
    }

    /**
     * GET /admin/rooms — List rooms.
     */
    public static function list_rooms( \WP_REST_Request $request ) {
        $mosque = $request->get_param( '_ynj_mosque' );

        global $wpdb;
        $table = YNJ_DB::table( 'rooms' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE mosque_id = %d ORDER BY name ASC",
            (int) $mosque->id
        ) );

        $rooms = array_map( [ __CLASS__, 'format_room' ], $results );

        return new \WP_REST_Response( [
            'ok'    => true,
            'rooms' => $rooms,
        ] );
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Format mosque profile for API response.
     */
    private static function format_profile( $mosque ) {
        return [
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
            'created_at'        => $mosque->created_at,
        ];
    }

    /**
     * Format a room row for API response.
     */
    private static function format_room( $row ) {
        return [
            'id'                 => (int) $row->id,
            'name'               => $row->name,
            'description'        => $row->description,
            'capacity'           => (int) $row->capacity,
            'hourly_rate_pence'  => (int) $row->hourly_rate_pence,
            'daily_rate_pence'   => (int) $row->daily_rate_pence,
            'photo_url'          => $row->photo_url,
            'availability_notes' => $row->availability_notes,
            'status'             => $row->status,
            'created_at'         => $row->created_at,
        ];
    }

    /**
     * Auto-geocode a UK postcode via postcodes.io.
     */
    private static function geocode_postcode( $mosque_id, $postcode ) {
        $pc = rawurlencode( str_replace( ' ', '', trim( $postcode ) ) );
        $response = wp_remote_get( "https://api.postcodes.io/postcodes/{$pc}", [ 'timeout' => 5 ] );

        if ( is_wp_error( $response ) ) return;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['result']['latitude'] ) && ! empty( $data['result']['longitude'] ) ) {
            global $wpdb;
            $table = YNJ_DB::table( 'mosques' );
            $wpdb->update( $table, [
                'latitude'  => (float) $data['result']['latitude'],
                'longitude' => (float) $data['result']['longitude'],
            ], [ 'id' => $mosque_id ] );
        }
    }

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
