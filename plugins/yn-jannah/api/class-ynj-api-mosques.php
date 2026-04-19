<?php
/**
 * YourJannah — REST API: Mosque discovery endpoints.
 * Namespace: ynj/v1
 *
 * @package YourJannah
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Mosques {

    const NS = 'ynj/v1';

    /**
     * Register all mosque discovery routes.
     */
    public static function register() {

        // GET /mosques/nearest?lat=&lng=&limit=20
        register_rest_route( self::NS, '/mosques/nearest', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'nearest' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/search?q=&limit=20
        register_rest_route( self::NS, '/mosques/search', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'search' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /mosques/{slug}
        register_rest_route( self::NS, '/mosques/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // POST /mosques/{id}/view — Track a page view (lightweight, fire-and-forget)
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/view', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'track_view' ],
            'permission_callback' => '__return_true',
        ]);

        // POST /content/view — Track announcement/event/class view (fire-and-forget)
        register_rest_route( self::NS, '/content/view', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'track_content_view' ],
            'permission_callback' => '__return_true',
        ]);

        // POST /content/react — Add/remove reaction (requires login)
        register_rest_route( self::NS, '/content/react', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'toggle_reaction' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        // ── Dua Wall ──
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/duas', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_duas' ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route( self::NS, '/duas/create', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create_dua' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);
        register_rest_route( self::NS, '/duas/(?P<id>\d+)/pray', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'pray_for_dua' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        // ── Gratitude ──
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/gratitude', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_gratitude' ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route( self::NS, '/gratitude/create', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'create_gratitude' ],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);
    }

    // ================================================================
    // HANDLERS
    // ================================================================

    /**
     * GET /mosques/nearest — Haversine distance search.
     */
    public static function nearest( \WP_REST_Request $request ) {
        $lat   = (float) $request->get_param( 'lat' );
        $lng   = (float) $request->get_param( 'lng' );
        $limit = min( absint( $request->get_param( 'limit' ) ?: 20 ), 100 );

        if ( ! $lat || ! $lng ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'lat and lng are required.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, slug, city, postcode, address, latitude, longitude, logo_url, status,
                    ( 6371 * acos(
                        cos( radians(%f) ) * cos( radians(latitude) )
                        * cos( radians(longitude) - radians(%f) )
                        + sin( radians(%f) ) * sin( radians(latitude) )
                    )) AS distance
             FROM $table
             WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY distance ASC
             LIMIT %d",
            $lat, $lng, $lat, $limit
        ) );

        $mosques = array_map( function( $row ) {
            return [
                'id'        => (int) $row->id,
                'name'      => $row->name,
                'slug'      => $row->slug,
                'city'      => $row->city,
                'postcode'  => $row->postcode,
                'address'   => $row->address,
                'latitude'  => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'logo_url'  => $row->logo_url,
                'distance'  => round( (float) $row->distance, 2 ),
                'status'    => $row->status,
            ];
        }, $results );

        return new \WP_REST_Response( [ 'ok' => true, 'mosques' => $mosques ] );
    }

    /**
     * GET /mosques/search — LIKE search on name and postcode.
     */
    public static function search( \WP_REST_Request $request ) {
        $q     = sanitize_text_field( $request->get_param( 'q' ) ?? '' );
        $limit = min( absint( $request->get_param( 'limit' ) ?: 20 ), 100 );

        if ( empty( $q ) || strlen( $q ) < 2 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Search query must be at least 2 characters.' ], 400 );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        $like  = '%' . $wpdb->esc_like( $q ) . '%';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, slug, city, postcode, address, latitude, longitude, logo_url
             FROM $table
             WHERE status IN ('active','unclaimed') AND ( name LIKE %s OR postcode LIKE %s )
             ORDER BY name ASC
             LIMIT %d",
            $like, $like, $limit
        ) );

        $mosques = array_map( function( $row ) {
            return [
                'id'        => (int) $row->id,
                'name'      => $row->name,
                'slug'      => $row->slug,
                'city'      => $row->city,
                'postcode'  => $row->postcode,
                'address'   => $row->address,
                'latitude'  => $row->latitude ? (float) $row->latitude : null,
                'longitude' => $row->longitude ? (float) $row->longitude : null,
                'logo_url'  => $row->logo_url,
            ];
        }, $results );

        return new \WP_REST_Response( [ 'ok' => true, 'mosques' => $mosques ] );
    }

    /**
     * GET /mosques/{slug} — Full mosque profile + today's prayer times.
     */
    public static function get_by_slug( \WP_REST_Request $request ) {
        $slug = sanitize_text_field( $request->get_param( 'slug' ) );

        // Cache mosque profile for 5 minutes — biggest speed win
        $cache_key = 'ynj_mosque_' . md5( $slug );
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new \WP_REST_Response( $cached );
        }

        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );

        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s AND status IN ('active','unclaimed') LIMIT 1",
            $slug
        ) );

        if ( ! $mosque ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        $profile = [
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
            'setup_complete'    => (bool) ( $mosque->setup_complete ?? false ),
        ];

        // Attach today's prayer times
        $profile['prayer_times'] = YNJ_Prayer::get_times( $mosque->id, date( 'Y-m-d' ) );

        $response = [ 'ok' => true, 'mosque' => $profile ];
        set_transient( $cache_key, $response, 300 ); // 5 min cache
        return new \WP_REST_Response( $response );
    }

    /**
     * POST /mosques/{id}/view — Track a page view (fire-and-forget, no auth).
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for daily aggregation.
     */
    public static function track_view( \WP_REST_Request $request ) {
        $mosque_id = (int) $request->get_param( 'id' );
        $source    = sanitize_text_field( $request->get_param( 'source' ) ?: 'page' );

        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false ], 400 );
        }

        // Only allow known sources
        if ( ! in_array( $source, [ 'page', 'gps', 'search', 'ad' ], true ) ) {
            $source = 'page';
        }

        global $wpdb;
        $table = YNJ_DB::table( 'mosque_views' );
        $today = date( 'Y-m-d' );

        // Upsert: increment today's count for this mosque+source
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $table (mosque_id, view_date, view_count, source)
             VALUES (%d, %s, 1, %s)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1",
            $mosque_id, $today, $source
        ) );

        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    /**
     * POST /content/view — Track content view (announcement, event, class).
     * Fire-and-forget, no auth needed. Aggregated daily.
     */
    public static function track_content_view( \WP_REST_Request $request ) {
        $type = sanitize_text_field( $request->get_param( 'type' ) ?: '' );
        $id   = (int) $request->get_param( 'id' );

        if ( ! $id || ! in_array( $type, [ 'announcement', 'event', 'class' ], true ) ) {
            return new \WP_REST_Response( [ 'ok' => false ], 400 );
        }

        // Look up mosque_id from the content
        global $wpdb;
        $table_map = [
            'announcement' => YNJ_DB::table( 'announcements' ),
            'event'        => YNJ_DB::table( 'events' ),
            'class'        => YNJ_DB::table( 'classes' ),
        ];
        $mosque_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT mosque_id FROM {$table_map[$type]} WHERE id = %d", $id
        ) );
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false ], 404 );
        }

        $cv = YNJ_DB::table( 'content_views' );
        $today = date( 'Y-m-d' );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $cv (content_type, content_id, mosque_id, view_count, unique_views, view_date)
             VALUES (%s, %d, %d, 1, 1, %s)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1",
            $type, $id, $mosque_id, $today
        ) );

        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    /**
     * POST /content/react — Toggle a reaction (like, dua, share) on content.
     * Requires login. Returns new counts.
     */
    public static function toggle_reaction( \WP_REST_Request $request ) {
        $type     = sanitize_text_field( $request->get_param( 'type' ) ?: '' );
        $id       = (int) $request->get_param( 'id' );
        $reaction = sanitize_text_field( $request->get_param( 'reaction' ) ?: 'like' );
        $user_id  = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );

        if ( ! $id || ! $user_id || ! in_array( $type, [ 'announcement', 'event', 'class' ], true ) ) {
            return new \WP_REST_Response( [ 'ok' => false ], 400 );
        }
        if ( ! in_array( $reaction, [ 'like', 'dua', 'interested', 'share' ], true ) ) {
            $reaction = 'like';
        }

        global $wpdb;
        $rt = YNJ_DB::table( 'reactions' );

        // Check if already reacted
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $rt WHERE user_id = %d AND content_type = %s AND content_id = %d AND reaction = %s",
            $user_id, $type, $id, $reaction
        ) );

        if ( $exists ) {
            // Reaction already exists — keep it (reactions are permanent, not toggleable)
            $action = 'already';
        } else {
            $wpdb->insert( $rt, [
                'user_id'      => $user_id,
                'content_type' => $type,
                'content_id'   => $id,
                'reaction'     => $reaction,
            ] );
            $action = 'added';
        }

        // Return updated counts
        $counts = $wpdb->get_results( $wpdb->prepare(
            "SELECT reaction, COUNT(*) AS cnt FROM $rt WHERE content_type = %s AND content_id = %d GROUP BY reaction",
            $type, $id
        ), OBJECT_K );

        $result = [];
        foreach ( [ 'like', 'dua', 'interested', 'share' ] as $r ) {
            $result[ $r ] = (int) ( $counts[ $r ]->cnt ?? 0 );
        }

        return new \WP_REST_Response( [ 'ok' => true, 'action' => $action, 'counts' => $result ] );
    }

    // ================================================================
    // DUA WALL
    // ================================================================

    public static function get_duas( \WP_REST_Request $request ) {
        global $wpdb;
        $mosque_id = (int) $request->get_param( 'id' );
        $dt = YNJ_DB::table( 'dua_requests' );

        $duas = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, request_text, dua_count, created_at FROM $dt WHERE mosque_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 20",
            $mosque_id
        ) );

        // Check which ones current user has prayed for
        $user_prayed = [];
        if ( is_user_logged_in() ) {
            $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            if ( $ynj_uid && ! empty( $duas ) ) {
                $ids = implode( ',', array_map( 'intval', array_column( $duas, 'id' ) ) );
                $dr = YNJ_DB::table( 'dua_responses' );
                $prayed = $wpdb->get_col( $wpdb->prepare(
                    "SELECT dua_request_id FROM $dr WHERE user_id = %d AND dua_request_id IN ($ids)", $ynj_uid
                ) );
                $user_prayed = array_map( 'intval', $prayed );
            }
        }

        $result = [];
        foreach ( $duas as $d ) {
            $result[] = [
                'id'      => (int) $d->id,
                'text'    => $d->request_text,
                'count'   => (int) $d->dua_count,
                'prayed'  => in_array( (int) $d->id, $user_prayed, true ),
                'ago'     => human_time_diff( strtotime( $d->created_at ) ),
            ];
        }

        return new \WP_REST_Response( [ 'ok' => true, 'duas' => $result ] );
    }

    public static function create_dua( \WP_REST_Request $request ) {
        $text = sanitize_text_field( $request->get_param( 'text' ) ?: '' );
        if ( ! $text || strlen( $text ) < 5 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Please write your dua request' ], 400 );
        }

        $ynj_uid   = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        $mosque_id = (int) ( $request->get_param( 'mosque_id' ) ?: get_user_meta( get_current_user_id(), 'ynj_mosque_id', true ) );
        if ( ! $ynj_uid ) return new \WP_REST_Response( [ 'ok' => false ], 400 );

        // Rate limit: max 3 per day
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );
        $today_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $dt WHERE user_id = %d AND DATE(created_at) = CURDATE()", $ynj_uid
        ) );
        if ( $today_count >= 3 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Maximum 3 dua requests per day' ], 429 );
        }

        $wpdb->insert( $dt, [
            'mosque_id'    => $mosque_id,
            'user_id'      => $ynj_uid,
            'request_text' => mb_substr( $text, 0, 500 ),
            'dua_count'    => 0,
            'status'       => 'active',
        ] );

        return new \WP_REST_Response( [ 'ok' => true, 'id' => $wpdb->insert_id ] );
    }

    public static function pray_for_dua( \WP_REST_Request $request ) {
        $dua_id  = (int) $request->get_param( 'id' );
        $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        if ( ! $dua_id || ! $ynj_uid ) return new \WP_REST_Response( [ 'ok' => false ], 400 );

        global $wpdb;
        $dr = YNJ_DB::table( 'dua_responses' );
        $dt = YNJ_DB::table( 'dua_requests' );

        // Check if already prayed
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $dr WHERE user_id = %d AND dua_request_id = %d", $ynj_uid, $dua_id
        ) );

        if ( $exists ) {
            return new \WP_REST_Response( [ 'ok' => true, 'already' => true ] );
        }

        $wpdb->insert( $dr, [ 'user_id' => $ynj_uid, 'dua_request_id' => $dua_id ] );
        $wpdb->query( $wpdb->prepare( "UPDATE $dt SET dua_count = dua_count + 1 WHERE id = %d", $dua_id ) );

        $new_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT dua_count FROM $dt WHERE id = %d", $dua_id ) );

        return new \WP_REST_Response( [ 'ok' => true, 'count' => $new_count ] );
    }

    // ================================================================
    // GRATITUDE POSTS
    // ================================================================

    public static function get_gratitude( \WP_REST_Request $request ) {
        global $wpdb;
        $mosque_id = (int) $request->get_param( 'id' );
        $gt = YNJ_DB::table( 'gratitude_posts' );

        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT message, created_at FROM $gt WHERE mosque_id = %d ORDER BY created_at DESC LIMIT 10",
            $mosque_id
        ) );

        $result = [];
        foreach ( $posts as $p ) {
            $result[] = [
                'message' => $p->message,
                'ago'     => human_time_diff( strtotime( $p->created_at ) ),
            ];
        }

        return new \WP_REST_Response( [ 'ok' => true, 'posts' => $result, 'total' => count( $result ) ] );
    }

    public static function create_gratitude( \WP_REST_Request $request ) {
        $message = sanitize_text_field( $request->get_param( 'message' ) ?: '' );
        if ( ! $message || strlen( $message ) < 3 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Please write a message' ], 400 );
        }

        $ynj_uid   = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        $mosque_id = (int) ( $request->get_param( 'mosque_id' ) ?: get_user_meta( get_current_user_id(), 'ynj_mosque_id', true ) );
        if ( ! $ynj_uid ) return new \WP_REST_Response( [ 'ok' => false ], 400 );

        // Rate limit: 1 per day
        global $wpdb;
        $gt = YNJ_DB::table( 'gratitude_posts' );
        $today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $gt WHERE user_id = %d AND DATE(created_at) = CURDATE()", $ynj_uid
        ) );
        if ( $today >= 1 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'You can post one gratitude per day' ], 429 );
        }

        $wpdb->insert( $gt, [
            'mosque_id' => $mosque_id,
            'user_id'   => $ynj_uid,
            'message'   => mb_substr( $message, 0, 300 ),
        ] );

        return new \WP_REST_Response( [ 'ok' => true ] );
    }
}
