<?php
/**
 * YNJ_Engagement — PHP data layer for engagement features.
 *
 * Dua wall, gratitude posts, content reactions, view tracking, milestones.
 * All methods use $wpdb->prepare() and sanitize inputs.
 *
 * @package YNJ_Engagement
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Engagement {

    // ================================================================
    // DUA WALL
    // ================================================================

    /**
     * Get active dua requests for a mosque.
     *
     * @param  int   $mosque_id  Mosque ID.
     * @param  int   $limit      Max results (default 20, max 50).
     * @param  int   $user_id    Optional YNJ user ID — marks which duas they prayed for.
     * @return array             List of dua objects.
     */
    public static function get_duas( $mosque_id, $limit = 20, $user_id = 0 ) {
        global $wpdb;

        $mosque_id = absint( $mosque_id );
        $limit     = min( absint( $limit ) ?: 20, 50 );

        $dt = YNJ_DB::table( 'dua_requests' );

        $duas = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, user_id, request_text, dua_count, created_at
             FROM $dt
             WHERE mosque_id = %d AND status = 'active'
             ORDER BY created_at DESC
             LIMIT %d",
            $mosque_id, $limit
        ) );

        if ( empty( $duas ) ) {
            return [];
        }

        // Resolve which duas the current user already prayed for
        $user_prayed = [];
        $user_id     = absint( $user_id );
        if ( $user_id ) {
            $ids = implode( ',', array_map( 'intval', array_column( $duas, 'id' ) ) );
            $dr  = YNJ_DB::table( 'dua_responses' );
            $prayed = $wpdb->get_col( $wpdb->prepare(
                "SELECT dua_request_id FROM $dr WHERE user_id = %d AND dua_request_id IN ($ids)",
                $user_id
            ) );
            $user_prayed = array_map( 'intval', $prayed );
        }

        $result = [];
        foreach ( $duas as $d ) {
            $result[] = [
                'id'      => (int) $d->id,
                'user_id' => (int) $d->user_id,
                'text'    => $d->request_text,
                'count'   => (int) $d->dua_count,
                'prayed'  => in_array( (int) $d->id, $user_prayed, true ),
                'created' => $d->created_at,
            ];
        }

        return $result;
    }

    /**
     * Get a user's own dua requests (across all mosques).
     *
     * @param  int   $user_id  YNJ user ID.
     * @param  int   $limit    Max results.
     * @return array           Flat array of dua objects.
     */
    public static function get_user_duas( $user_id, $limit = 10 ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, request_text, dua_count, status, created_at FROM $dt WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            absint( $user_id ), absint( $limit )
        ) ) ?: [];
    }

    /**
     * Create a new dua request.
     *
     * @param  array    $data {
     *     @type int    $mosque_id   Mosque ID.
     *     @type int    $user_id     YNJ user ID.
     *     @type string $text        Dua request text (max 500 chars).
     * }
     * @return int|WP_Error  Inserted row ID or error.
     */
    public static function create_dua( $data ) {
        global $wpdb;

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        $user_id   = absint( $data['user_id']   ?? 0 );
        $text      = sanitize_text_field( $data['text'] ?? '' );

        if ( ! $mosque_id || ! $user_id ) {
            return new \WP_Error( 'missing_params', 'mosque_id and user_id are required.' );
        }
        if ( strlen( $text ) < 5 ) {
            return new \WP_Error( 'too_short', 'Dua request must be at least 5 characters.' );
        }

        // Rate limit: max 3 per user per day
        $dt = YNJ_DB::table( 'dua_requests' );
        $today_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $dt WHERE user_id = %d AND DATE(created_at) = CURDATE()",
            $user_id
        ) );
        if ( $today_count >= 3 ) {
            return new \WP_Error( 'rate_limit', 'Maximum 3 dua requests per day.' );
        }

        $wpdb->insert( $dt, [
            'mosque_id'    => $mosque_id,
            'user_id'      => $user_id,
            'request_text' => mb_substr( $text, 0, 500 ),
            'dua_count'    => 0,
            'status'       => 'active',
        ] );

        $id = $wpdb->insert_id;
        if ( ! $id ) {
            return new \WP_Error( 'db_error', 'Failed to create dua request.' );
        }

        /**
         * Fires after a new dua request is created.
         *
         * @param int   $id        New dua request ID.
         * @param int   $mosque_id Mosque ID.
         * @param int   $user_id   YNJ user ID.
         */
        do_action( 'ynj_dua_created', $id, $mosque_id, $user_id );

        return $id;
    }

    /**
     * Record that a user prayed for a dua. Increments the dua_count.
     *
     * @param  int  $dua_id   Dua request ID.
     * @param  int  $user_id  YNJ user ID.
     * @return array|WP_Error { count: int, already: bool }
     */
    public static function pray_for_dua( $dua_id, $user_id ) {
        global $wpdb;

        $dua_id  = absint( $dua_id );
        $user_id = absint( $user_id );
        if ( ! $dua_id || ! $user_id ) {
            return new \WP_Error( 'missing_params', 'dua_id and user_id are required.' );
        }

        $dr = YNJ_DB::table( 'dua_responses' );
        $dt = YNJ_DB::table( 'dua_requests' );

        // Already prayed?
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $dr WHERE user_id = %d AND dua_request_id = %d",
            $user_id, $dua_id
        ) );

        if ( $exists ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT dua_count FROM $dt WHERE id = %d", $dua_id
            ) );
            return [ 'count' => $count, 'already' => true ];
        }

        $wpdb->insert( $dr, [
            'user_id'        => $user_id,
            'dua_request_id' => $dua_id,
        ] );

        $wpdb->query( $wpdb->prepare(
            "UPDATE $dt SET dua_count = dua_count + 1 WHERE id = %d",
            $dua_id
        ) );

        $new_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT dua_count FROM $dt WHERE id = %d", $dua_id
        ) );

        /**
         * Fires after a user prays for a dua request.
         *
         * @param int $dua_id   Dua request ID.
         * @param int $user_id  YNJ user ID.
         * @param int $count    New prayer count.
         */
        do_action( 'ynj_dua_prayed', $dua_id, $user_id, $new_count );

        return [ 'count' => $new_count, 'already' => false ];
    }

    /**
     * Get individual prayer responses for a dua request.
     *
     * @param  int   $dua_id  Dua request ID.
     * @param  int   $limit   Max results (default 50).
     * @return array          List of response objects with user_id and created_at.
     */
    public static function get_dua_responses( $dua_id, $limit = 50 ) {
        global $wpdb;

        $dua_id = absint( $dua_id );
        $limit  = min( absint( $limit ) ?: 50, 200 );

        $dr = YNJ_DB::table( 'dua_responses' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, user_id, created_at
             FROM $dr
             WHERE dua_request_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $dua_id, $limit
        ) );
    }

    // ================================================================
    // GRATITUDE POSTS
    // ================================================================

    /**
     * Get gratitude posts for a mosque.
     *
     * @param  int   $mosque_id  Mosque ID.
     * @param  int   $limit      Max results (default 10, max 50).
     * @return array             List of gratitude post objects.
     */
    public static function get_gratitude_posts( $mosque_id, $limit = 10 ) {
        global $wpdb;

        $mosque_id = absint( $mosque_id );
        $limit     = min( absint( $limit ) ?: 10, 50 );

        $gt = YNJ_DB::table( 'gratitude_posts' );

        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, user_id, message, created_at
             FROM $gt
             WHERE mosque_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $mosque_id, $limit
        ) );

        $result = [];
        foreach ( $posts as $p ) {
            $result[] = [
                'id'      => (int) $p->id,
                'user_id' => (int) $p->user_id,
                'message' => $p->message,
                'created' => $p->created_at,
            ];
        }

        return $result;
    }

    /**
     * Create a gratitude post.
     *
     * @param  array    $data {
     *     @type int    $mosque_id  Mosque ID.
     *     @type int    $user_id    YNJ user ID.
     *     @type string $message    Gratitude message (max 300 chars).
     * }
     * @return int|WP_Error  Inserted row ID or error.
     */
    public static function create_gratitude( $data ) {
        global $wpdb;

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        $user_id   = absint( $data['user_id']   ?? 0 );
        $message   = sanitize_text_field( $data['message'] ?? '' );

        if ( ! $mosque_id || ! $user_id ) {
            return new \WP_Error( 'missing_params', 'mosque_id and user_id are required.' );
        }
        if ( strlen( $message ) < 3 ) {
            return new \WP_Error( 'too_short', 'Gratitude message must be at least 3 characters.' );
        }

        // Rate limit: 1 per user per day
        $gt = YNJ_DB::table( 'gratitude_posts' );
        $today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $gt WHERE user_id = %d AND DATE(created_at) = CURDATE()",
            $user_id
        ) );
        if ( $today >= 1 ) {
            return new \WP_Error( 'rate_limit', 'You can post one gratitude per day.' );
        }

        $wpdb->insert( $gt, [
            'mosque_id' => $mosque_id,
            'user_id'   => $user_id,
            'message'   => mb_substr( $message, 0, 300 ),
        ] );

        $id = $wpdb->insert_id;
        if ( ! $id ) {
            return new \WP_Error( 'db_error', 'Failed to create gratitude post.' );
        }

        /**
         * Fires after a gratitude post is created.
         *
         * @param int $id        New post ID.
         * @param int $mosque_id Mosque ID.
         * @param int $user_id   YNJ user ID.
         */
        do_action( 'ynj_gratitude_created', $id, $mosque_id, $user_id );

        return $id;
    }

    // ================================================================
    // REACTIONS
    // ================================================================

    /**
     * Allowed content types for reactions and views.
     */
    private static $content_types = [ 'announcement', 'event', 'class' ];

    /**
     * Allowed reaction types.
     */
    private static $reaction_types = [ 'like', 'dua', 'interested', 'share' ];

    /**
     * Add (or toggle) a reaction on content.
     * If the user already has this reaction, it is removed (toggle).
     *
     * @param  array $data {
     *     @type string $content_type  announcement|event|class.
     *     @type int    $content_id    Content row ID.
     *     @type int    $user_id       YNJ user ID.
     *     @type string $reaction      like|dua|interested|share (default: like).
     * }
     * @return array|WP_Error { action: added|removed, counts: { like: int, ... } }
     */
    public static function add_reaction( $data ) {
        global $wpdb;

        $type     = sanitize_text_field( $data['content_type'] ?? '' );
        $id       = absint( $data['content_id'] ?? 0 );
        $user_id  = absint( $data['user_id']    ?? 0 );
        $reaction = sanitize_text_field( $data['reaction'] ?? 'like' );

        if ( ! $id || ! $user_id || ! in_array( $type, self::$content_types, true ) ) {
            return new \WP_Error( 'invalid_params', 'content_type, content_id, and user_id are required.' );
        }
        if ( ! in_array( $reaction, self::$reaction_types, true ) ) {
            $reaction = 'like';
        }

        $rt = YNJ_DB::table( 'reactions' );

        // Toggle: check if reaction exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $rt WHERE user_id = %d AND content_type = %s AND content_id = %d AND reaction = %s",
            $user_id, $type, $id, $reaction
        ) );

        if ( $exists ) {
            $wpdb->delete( $rt, [ 'id' => $exists ] );
            $action = 'removed';
        } else {
            $wpdb->insert( $rt, [
                'user_id'      => $user_id,
                'content_type' => $type,
                'content_id'   => $id,
                'reaction'     => $reaction,
            ] );
            $action = 'added';
        }

        /**
         * Fires after a content reaction is toggled.
         *
         * @param string $action   'added' or 'removed'.
         * @param string $type     Content type.
         * @param int    $id       Content ID.
         * @param int    $user_id  YNJ user ID.
         * @param string $reaction Reaction type.
         */
        do_action( 'ynj_reaction_toggled', $action, $type, $id, $user_id, $reaction );

        $counts = self::get_reactions( $type, $id );

        return [ 'action' => $action, 'counts' => $counts ];
    }

    /**
     * Get reaction counts for a piece of content.
     *
     * @param  string $content_type  announcement|event|class.
     * @param  int    $content_id    Content row ID.
     * @return array  { like: int, dua: int, interested: int, share: int }
     */
    public static function get_reactions( $content_type, $content_id ) {
        global $wpdb;

        $content_type = sanitize_text_field( $content_type );
        $content_id   = absint( $content_id );

        $rt = YNJ_DB::table( 'reactions' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT reaction, COUNT(*) AS cnt
             FROM $rt
             WHERE content_type = %s AND content_id = %d
             GROUP BY reaction",
            $content_type, $content_id
        ), OBJECT_K );

        $counts = [];
        foreach ( self::$reaction_types as $r ) {
            $counts[ $r ] = (int) ( $rows[ $r ]->cnt ?? 0 );
        }

        return $counts;
    }

    /**
     * Get a user's reactions on a specific piece of content.
     *
     * @param  int    $user_id       YNJ user ID.
     * @param  string $content_type  announcement|event|class.
     * @param  int    $content_id    Content row ID.
     * @return array                 List of reaction type strings (e.g. ['like','dua']).
     */
    public static function get_user_reactions( $user_id, $content_type, $content_id ) {
        global $wpdb;

        $user_id      = absint( $user_id );
        $content_type = sanitize_text_field( $content_type );
        $content_id   = absint( $content_id );

        if ( ! $user_id ) return [];

        $rt = YNJ_DB::table( 'reactions' );

        return $wpdb->get_col( $wpdb->prepare(
            "SELECT reaction FROM $rt WHERE user_id = %d AND content_type = %s AND content_id = %d",
            $user_id, $content_type, $content_id
        ) ) ?: [];
    }

    /**
     * Remove a specific reaction by its row ID.
     *
     * @param  int  $id  Reaction row ID.
     * @return bool      True on success.
     */
    public static function remove_reaction( $id ) {
        global $wpdb;

        $id = absint( $id );
        if ( ! $id ) {
            return false;
        }

        $rt = YNJ_DB::table( 'reactions' );

        // Fetch before deleting so we can fire the action with context
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT content_type, content_id, user_id, reaction FROM $rt WHERE id = %d", $id
        ) );

        $deleted = $wpdb->delete( $rt, [ 'id' => $id ] );

        if ( $deleted && $row ) {
            do_action( 'ynj_reaction_toggled', 'removed', $row->content_type, (int) $row->content_id, (int) $row->user_id, $row->reaction );
        }

        return (bool) $deleted;
    }

    // ================================================================
    // VIEW TRACKING
    // ================================================================

    /**
     * Track a content view (announcement, event, class).
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for daily aggregation.
     *
     * @param  string $content_type  announcement|event|class.
     * @param  int    $content_id    Content row ID.
     * @param  array  $viewer_data   Optional { mosque_id: int }. Auto-resolved if empty.
     * @return bool   True on success.
     */
    public static function track_view( $content_type, $content_id, $viewer_data = [] ) {
        global $wpdb;

        $content_type = sanitize_text_field( $content_type );
        $content_id   = absint( $content_id );

        if ( ! $content_id || ! in_array( $content_type, self::$content_types, true ) ) {
            return false;
        }

        // Resolve mosque_id from the content if not provided
        $mosque_id = absint( $viewer_data['mosque_id'] ?? 0 );
        if ( ! $mosque_id ) {
            $table_map = [
                'announcement' => YNJ_DB::table( 'announcements' ),
                'event'        => YNJ_DB::table( 'events' ),
                'class'        => YNJ_DB::table( 'classes' ),
            ];
            $mosque_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT mosque_id FROM {$table_map[$content_type]} WHERE id = %d",
                $content_id
            ) );
            if ( ! $mosque_id ) {
                return false;
            }
        }

        $cv    = YNJ_DB::table( 'content_views' );
        $today = current_time( 'Y-m-d' );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $cv (content_type, content_id, mosque_id, view_count, unique_views, view_date)
             VALUES (%s, %d, %d, 1, 1, %s)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1",
            $content_type, $content_id, $mosque_id, $today
        ) );

        /**
         * Fires after a content view is tracked.
         *
         * @param string $content_type Content type.
         * @param int    $content_id   Content ID.
         * @param int    $mosque_id    Mosque ID.
         */
        do_action( 'ynj_content_viewed', $content_type, $content_id, $mosque_id );

        return true;
    }

    /**
     * Get the total view count for a piece of content (all dates summed).
     *
     * @param  string $content_type  announcement|event|class.
     * @param  int    $content_id    Content row ID.
     * @return int                   Total view count.
     */
    public static function get_view_count( $content_type, $content_id ) {
        global $wpdb;

        $content_type = sanitize_text_field( $content_type );
        $content_id   = absint( $content_id );

        $cv = YNJ_DB::table( 'content_views' );

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(view_count), 0)
             FROM $cv
             WHERE content_type = %s AND content_id = %d",
            $content_type, $content_id
        ) );
    }

    /**
     * Get daily view breakdown for a piece of content.
     *
     * @param  string $content_type  announcement|event|class.
     * @param  int    $content_id    Content row ID.
     * @param  int    $days          Number of recent days to return (default 30).
     * @return array                 [ { date, views, unique_views } ... ]
     */
    public static function get_view_stats( $content_type, $content_id, $days = 30 ) {
        global $wpdb;

        $content_type = sanitize_text_field( $content_type );
        $content_id   = absint( $content_id );
        $days         = min( absint( $days ) ?: 30, 90 );

        $cv = YNJ_DB::table( 'content_views' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT view_date AS date, view_count AS views, unique_views
             FROM $cv
             WHERE content_type = %s AND content_id = %d AND view_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             ORDER BY view_date DESC",
            $content_type, $content_id, $days
        ) );
    }

    // ================================================================
    // MILESTONES (display layer — checking logic lives in gamification)
    // ================================================================

    /**
     * Get all milestones reached by a mosque.
     *
     * @param  int   $mosque_id  Mosque ID.
     * @return array             List of milestone objects.
     */
    public static function get_milestones( $mosque_id ) {
        global $wpdb;

        $mosque_id = absint( $mosque_id );
        $mt = YNJ_DB::table( 'milestones' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, milestone_key, milestone_value, reached_at
             FROM $mt
             WHERE mosque_id = %d
             ORDER BY reached_at DESC",
            $mosque_id
        ) );

        $defs = self::milestone_definitions();
        $result = [];

        foreach ( $rows as $r ) {
            $def = $defs[ $r->milestone_key ] ?? [ 'icon' => '', 'label' => $r->milestone_key ];
            $result[] = [
                'id'      => (int) $r->id,
                'key'     => $r->milestone_key,
                'value'   => (int) $r->milestone_value,
                'icon'    => $def['icon'],
                'label'   => $def['label'],
                'reached' => $r->reached_at,
            ];
        }

        return $result;
    }

    /**
     * Check milestones for a mosque. Delegates to gamification plugin if available,
     * otherwise returns an empty array.
     *
     * @param  int   $mosque_id  Mosque ID.
     * @return array             Newly reached milestones.
     */
    public static function check_milestones( $mosque_id ) {
        if ( function_exists( 'ynj_check_milestones' ) ) {
            return ynj_check_milestones( absint( $mosque_id ) );
        }
        return [];
    }

    /**
     * Milestone key => display metadata mapping.
     * Mirrors the definitions in YNJ_Badges for consistent display.
     *
     * @return array
     */
    private static function milestone_definitions() {
        return [
            'prayers_100'    => [ 'icon' => "\xF0\x9F\xA4\xB2", 'label' => '100 prayers logged' ],
            'prayers_500'    => [ 'icon' => "\xF0\x9F\xA4\xB2", 'label' => '500 prayers logged' ],
            'prayers_1000'   => [ 'icon' => "\xE2\x9C\xA8",     'label' => '1,000 prayers logged' ],
            'prayers_5000'   => [ 'icon' => "\xF0\x9F\x8C\x9F", 'label' => '5,000 prayers logged' ],
            'prayers_10000'  => [ 'icon' => "\xF0\x9F\x92\x8E", 'label' => '10,000 prayers logged' ],
            'quran_100'      => [ 'icon' => "\xF0\x9F\x93\x96", 'label' => '100 Quran pages read' ],
            'quran_500'      => [ 'icon' => "\xF0\x9F\x93\x97", 'label' => '500 Quran pages read' ],
            'quran_1000'     => [ 'icon' => "\xF0\x9F\x93\x9A", 'label' => '1,000 Quran pages read' ],
            'members_10'     => [ 'icon' => "\xF0\x9F\x91\xA5", 'label' => '10 members joined' ],
            'members_50'     => [ 'icon' => "\xF0\x9F\x91\xA5", 'label' => '50 members joined' ],
            'members_100'    => [ 'icon' => "\xF0\x9F\x8E\x8A", 'label' => '100 members joined' ],
            'members_500'    => [ 'icon' => "\xF0\x9F\x8F\x86", 'label' => '500 members joined' ],
            'donations_1000' => [ 'icon' => "\xC2\xA3",         'label' => 'First \xC2\xA31,000' ],
            'donations_5000' => [ 'icon' => "\xF0\x9F\x92\xB0", 'label' => '\xC2\xA35,000 donated' ],
        ];
    }
}
