<?php
/**
 * YourJannah — REST API: Congregation member endpoints.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_User {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /auth/register — user signup
        register_rest_route( self::NS, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_register' ],
            'permission_callback' => '__return_true',
        ] );

        // POST /auth/login — user login
        register_rest_route( self::NS, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_login' ],
            'permission_callback' => '__return_true',
        ] );

        // GET /auth/me — get user profile
        register_rest_route( self::NS, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_profile' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // PUT /auth/me — update profile
        register_rest_route( self::NS, '/auth/me', [
            'methods'             => 'PUT',
            'callback'            => [ __CLASS__, 'update_profile' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // GET /auth/bookings — my bookings
        register_rest_route( self::NS, '/auth/bookings', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'my_bookings' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );

        // POST /auth/push — save push subscription
        register_rest_route( self::NS, '/auth/push', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'save_push' ],
            'permission_callback' => [ 'YNJ_User_Auth', 'user_check' ],
        ] );
    }

    public static function handle_register( \WP_REST_Request $request ) {
        $data   = $request->get_json_params();
        $result = YNJ_User_Auth::register( $data );

        if ( ! $result['ok'] ) {
            return new \WP_REST_Response( $result, 400 );
        }

        return new \WP_REST_Response( [
            'ok'    => true,
            'token' => $result['token'],
            'message' => 'Account created. Welcome to YourJannah!',
        ], 201 );
    }

    public static function handle_login( \WP_REST_Request $request ) {
        $data   = $request->get_json_params();
        $result = YNJ_User_Auth::login( $data );

        if ( ! $result['ok'] ) {
            return new \WP_REST_Response( $result, 401 );
        }

        return new \WP_REST_Response( [
            'ok'    => true,
            'token' => $result['token'],
            'user'  => $result['user'],
        ] );
    }

    public static function get_profile( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );

        // Get favourite mosque name
        $mosque_name = null;
        if ( $user->favourite_mosque_id ) {
            global $wpdb;
            $mosque_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d",
                $user->favourite_mosque_id
            ) );
        }

        $profile = YNJ_User_Auth::format_user( $user );
        $profile['favourite_mosque_name'] = $mosque_name;

        return new \WP_REST_Response( [ 'ok' => true, 'user' => $profile ] );
    }

    public static function update_profile( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        $update = [];
        if ( isset( $data['name'] ) )                 $update['name']                 = sanitize_text_field( $data['name'] );
        if ( isset( $data['phone'] ) )                $update['phone']                = sanitize_text_field( $data['phone'] );
        if ( isset( $data['favourite_mosque_id'] ) )  $update['favourite_mosque_id']  = absint( $data['favourite_mosque_id'] ) ?: null;
        if ( isset( $data['travel_mode'] ) )          $update['travel_mode']          = in_array( $data['travel_mode'], [ 'walk', 'drive' ] ) ? $data['travel_mode'] : 'walk';
        if ( isset( $data['travel_minutes'] ) )       $update['travel_minutes']       = max( 0, min( 120, absint( $data['travel_minutes'] ) ) );
        if ( isset( $data['alert_before_minutes'] ) ) $update['alert_before_minutes'] = max( 5, min( 60, absint( $data['alert_before_minutes'] ) ) );

        if ( ! empty( $update ) ) {
            $wpdb->update( $table, $update, [ 'id' => $user->id ] );
        }

        // Refresh
        $fresh = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $user->id ) );

        return new \WP_REST_Response( [
            'ok'   => true,
            'user' => YNJ_User_Auth::format_user( $fresh ),
            'message' => 'Profile updated.',
        ] );
    }

    public static function my_bookings( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );

        global $wpdb;
        $book_table   = YNJ_DB::table( 'bookings' );
        $event_table  = YNJ_DB::table( 'events' );
        $room_table   = YNJ_DB::table( 'rooms' );
        $mosque_table = YNJ_DB::table( 'mosques' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*,
                    e.title AS event_title, e.event_date AS event_date_raw, e.start_time AS event_start,
                    r.name AS room_name,
                    m.name AS mosque_name, m.slug AS mosque_slug
             FROM $book_table b
             LEFT JOIN $event_table e ON e.id = b.event_id
             LEFT JOIN $room_table r ON r.id = b.room_id
             LEFT JOIN $mosque_table m ON m.id = b.mosque_id
             WHERE b.user_email = %s
             ORDER BY b.created_at DESC
             LIMIT 50",
            $user->email
        ) );

        $bookings = array_map( function( $b ) {
            return [
                'id'           => (int) $b->id,
                'type'         => $b->event_id ? 'event' : 'room',
                'event_title'  => $b->event_title,
                'room_name'    => $b->room_name,
                'mosque_name'  => $b->mosque_name,
                'mosque_slug'  => $b->mosque_slug,
                'booking_date' => $b->booking_date,
                'start_time'   => $b->start_time,
                'end_time'     => $b->end_time,
                'status'       => $b->status,
                'notes'        => $b->notes,
                'created_at'   => $b->created_at,
            ];
        }, $results );

        return new \WP_REST_Response( [ 'ok' => true, 'bookings' => $bookings ] );
    }

    public static function save_push( \WP_REST_Request $request ) {
        $user = $request->get_param( '_ynj_user' );
        $data = $request->get_json_params();

        global $wpdb;
        $table = YNJ_DB::table( 'users' );

        $wpdb->update( $table, [
            'push_endpoint' => sanitize_text_field( $data['endpoint'] ?? '' ),
            'push_p256dh'   => sanitize_text_field( $data['p256dh'] ?? '' ),
            'push_auth'     => sanitize_text_field( $data['auth'] ?? '' ),
        ], [ 'id' => $user->id ] );

        return new \WP_REST_Response( [ 'ok' => true, 'message' => 'Push subscription saved.' ] );
    }
}
