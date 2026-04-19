<?php
/**
 * Broadcast Data Layer — live streaming, broadcaster management, history.
 *
 * Architecture: One YourJannah YouTube channel handles all mosques.
 * Each mosque gets a playlist. Broadcasters tap "Go Live" — no YouTube setup needed.
 *
 * MVP: YouTube video ID is manually entered or auto-detected via YouTube API.
 * Future: WebRTC → RTMP relay for direct browser streaming.
 *
 * @package YNJ_Broadcast
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Broadcast {

    /**
     * Stream types.
     */
    public static function get_stream_types() {
        return [
            'fajr'     => [ 'label' => 'Fajr',           'icon' => '🌙' ],
            'dhuhr'    => [ 'label' => 'Dhuhr',           'icon' => '☀️' ],
            'asr'      => [ 'label' => 'Asr',             'icon' => '🌤️' ],
            'maghrib'  => [ 'label' => 'Maghrib',         'icon' => '🌅' ],
            'isha'     => [ 'label' => 'Isha',            'icon' => '🌃' ],
            'jumuah'   => [ 'label' => "Jumu'ah Khutbah", 'icon' => '🕌' ],
            'taraweeh' => [ 'label' => 'Taraweeh',        'icon' => '🌙' ],
            'lecture'  => [ 'label' => 'Lecture / Talk',   'icon' => '🎤' ],
            'event'    => [ 'label' => 'Event',            'icon' => '📅' ],
            'other'    => [ 'label' => 'Other',            'icon' => '📡' ],
        ];
    }

    // ================================================================
    // BROADCASTER MANAGEMENT
    // ================================================================

    /**
     * Check if a user can broadcast for a mosque.
     */
    public static function can_broadcast( $user_id, $mosque_id ) {
        if ( current_user_can( 'manage_options' ) ) return true;

        global $wpdb;
        $t = YNJ_DB::table( 'broadcasters' );
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE user_id = %d AND mosque_id = %d AND status = 'active'",
            absint( $user_id ), absint( $mosque_id )
        ) );
    }

    /**
     * Get broadcasters for a mosque.
     */
    public static function get_broadcasters( $mosque_id ) {
        global $wpdb;
        $t  = YNJ_DB::table( 'broadcasters' );
        $ut = YNJ_DB::table( 'users' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, u.name AS user_name, u.email AS user_email
             FROM $t b LEFT JOIN $ut u ON u.id = b.user_id
             WHERE b.mosque_id = %d ORDER BY b.created_at DESC",
            absint( $mosque_id )
        ) ) ?: [];
    }

    /**
     * Add a broadcaster.
     */
    public static function add_broadcaster( $mosque_id, $user_id, $added_by = 0 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'broadcasters' );

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE mosque_id = %d AND user_id = %d",
            absint( $mosque_id ), absint( $user_id )
        ) );

        if ( $exists ) {
            $wpdb->update( $t, [ 'status' => 'active' ], [ 'id' => (int) $exists ] );
            return (int) $exists;
        }

        $wpdb->insert( $t, [
            'mosque_id' => absint( $mosque_id ),
            'user_id'   => absint( $user_id ),
            'role'      => 'broadcaster',
            'added_by'  => absint( $added_by ),
            'status'    => 'active',
        ] );
        return (int) $wpdb->insert_id;
    }

    /**
     * Remove a broadcaster.
     */
    public static function remove_broadcaster( $mosque_id, $user_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'broadcasters' );
        return $wpdb->update( $t,
            [ 'status' => 'removed' ],
            [ 'mosque_id' => absint( $mosque_id ), 'user_id' => absint( $user_id ) ]
        );
    }

    // ================================================================
    // BROADCAST LIFECYCLE
    // ================================================================

    /**
     * Start a broadcast.
     */
    public static function start( $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'broadcasts' );

        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        $user_id   = absint( $data['user_id'] ?? 0 );

        if ( ! $mosque_id ) return new \WP_Error( 'missing', 'mosque_id required' );

        // Check broadcaster permission
        if ( ! self::can_broadcast( $user_id, $mosque_id ) ) {
            return new \WP_Error( 'forbidden', 'You are not an authorised broadcaster for this mosque.' );
        }

        // Check no existing live stream for this mosque
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE mosque_id = %d AND status = 'live'", $mosque_id
        ) );
        if ( $existing ) {
            return new \WP_Error( 'already_live', 'This mosque already has a live stream.' );
        }

        // Scheduled or live now?
        $scheduled_at = sanitize_text_field( $data['scheduled_at'] ?? '' );
        $is_scheduled = ! empty( $scheduled_at );
        $status_val   = $is_scheduled ? 'scheduled' : 'live';

        $insert = [
            'mosque_id'           => $mosque_id,
            'broadcaster_user_id' => $user_id,
            'title'               => sanitize_text_field( $data['title'] ?? '' ),
            'youtube_video_id'    => sanitize_text_field( $data['youtube_video_id'] ?? '' ),
            'stream_type'         => sanitize_text_field( $data['stream_type'] ?? 'prayer' ),
            'status'              => $status_val,
        ];

        if ( $is_scheduled ) {
            // Store scheduled time — started_at will be set when it actually goes live
            $insert['created_at'] = $scheduled_at;
        } else {
            $insert['started_at'] = current_time( 'mysql' );
        }

        $wpdb->insert( $t, $insert );
        $id = (int) $wpdb->insert_id;

        if ( ! $id ) return new \WP_Error( 'db_error', 'Failed to create broadcast.' );

        // Only fire hooks + auto-post for live (not scheduled)
        if ( $is_scheduled ) {
            return $id;
        }

        // Fire hook for notifications
        do_action( 'ynj_broadcast_started', $id, $mosque_id, $data );

        // Auto-post announcement to feed
        if ( class_exists( 'YNJ_Events' ) ) {
            $mosque_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
            ) ) ?: 'Masjid';
            $types = self::get_stream_types();
            $type_label = $types[ $data['stream_type'] ?? '' ]['label'] ?? 'Live';

            YNJ_Events::create_announcement( [
                'mosque_id' => $mosque_id,
                'title'     => '🔴 LIVE — ' . $type_label . ' at ' . $mosque_name,
                'body'      => 'Watch now on YourJannah.',
                'type'      => 'live',
                'publish'   => true,
            ] );
        }

        return $id;
    }

    /**
     * End a broadcast.
     */
    public static function end( $broadcast_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'broadcasts' );

        $broadcast = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", absint( $broadcast_id ) ) );
        if ( ! $broadcast || $broadcast->status !== 'live' ) {
            return new \WP_Error( 'not_live', 'Broadcast is not currently live.' );
        }

        $started = strtotime( $broadcast->started_at );
        $duration = $started ? time() - $started : 0;

        $wpdb->update( $t, [
            'status'           => 'ended',
            'ended_at'         => current_time( 'mysql' ),
            'duration_seconds' => $duration,
        ], [ 'id' => (int) $broadcast_id ] );

        do_action( 'ynj_broadcast_ended', $broadcast_id, (int) $broadcast->mosque_id, $duration );

        return true;
    }

    /**
     * Get current live broadcast for a mosque.
     */
    public static function get_live( $mosque_id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'broadcasts' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $t WHERE mosque_id = %d AND status = 'live' ORDER BY started_at DESC LIMIT 1",
            absint( $mosque_id )
        ) );
    }

    /**
     * Get broadcast history for a mosque.
     */
    public static function get_history( $mosque_id, $limit = 20 ) {
        global $wpdb;
        $t  = YNJ_DB::table( 'broadcasts' );
        $ut = YNJ_DB::table( 'users' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, u.name AS broadcaster_name
             FROM $t b LEFT JOIN $ut u ON u.id = b.broadcaster_user_id
             WHERE b.mosque_id = %d ORDER BY b.created_at DESC LIMIT %d",
            absint( $mosque_id ), absint( $limit )
        ) ) ?: [];
    }

    /**
     * Get platform-wide stats.
     */
    public static function get_platform_stats() {
        global $wpdb;
        $t = YNJ_DB::table( 'broadcasts' );
        return [
            'total_broadcasts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ),
            'live_now'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'live'" ),
            'total_duration'   => (int) $wpdb->get_var( "SELECT COALESCE(SUM(duration_seconds), 0) FROM $t WHERE status = 'ended'" ),
            'total_viewers'    => (int) $wpdb->get_var( "SELECT COALESCE(SUM(peak_viewers), 0) FROM $t" ),
            'mosques_streaming' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT mosque_id) FROM $t" ),
        ];
    }

    // ================================================================
    // REST API HANDLERS
    // ================================================================

    public static function api_start( \WP_REST_Request $r ) {
        $d = $r->get_json_params();
        $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        $d['user_id'] = $ynj_uid;

        $result = self::start( $d );
        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 400 );
        }
        return new \WP_REST_Response( [ 'ok' => true, 'broadcast_id' => $result ] );
    }

    public static function api_end( \WP_REST_Request $r ) {
        $d = $r->get_json_params();
        $broadcast_id = absint( $d['broadcast_id'] ?? 0 );
        if ( ! $broadcast_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'broadcast_id required' ], 400 );
        }
        $result = self::end( $broadcast_id );
        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $result->get_error_message() ], 400 );
        }
        return new \WP_REST_Response( [ 'ok' => true ] );
    }

    public static function api_get_live( \WP_REST_Request $r ) {
        $mosque_id = absint( $r->get_param( 'mosque_id' ) );
        $live = self::get_live( $mosque_id );
        return new \WP_REST_Response( [
            'ok'   => true,
            'live' => $live ? [
                'id'               => (int) $live->id,
                'title'            => $live->title,
                'youtube_video_id' => $live->youtube_video_id,
                'stream_type'      => $live->stream_type,
                'started_at'       => $live->started_at,
                'viewer_count'     => (int) $live->viewer_count,
            ] : null,
        ] );
    }

    public static function api_history( \WP_REST_Request $r ) {
        $mosque_id = absint( $r->get_param( 'mosque_id' ) );
        $history = self::get_history( $mosque_id );
        $items = array_map( function( $b ) {
            $types = self::get_stream_types();
            return [
                'id'               => (int) $b->id,
                'title'            => $b->title,
                'youtube_video_id' => $b->youtube_video_id,
                'stream_type'      => $b->stream_type,
                'type_label'       => $types[ $b->stream_type ]['label'] ?? $b->stream_type,
                'type_icon'        => $types[ $b->stream_type ]['icon'] ?? '📡',
                'broadcaster_name' => $b->broadcaster_name ?? '',
                'status'           => $b->status,
                'started_at'       => $b->started_at,
                'ended_at'         => $b->ended_at,
                'duration_seconds' => (int) $b->duration_seconds,
                'peak_viewers'     => (int) $b->peak_viewers,
            ];
        }, $history );
        return new \WP_REST_Response( [ 'ok' => true, 'broadcasts' => $items ] );
    }

    public static function api_broadcasters( \WP_REST_Request $r ) {
        $d = $r->get_json_params() ?: [];
        $mosque_id = absint( $d['mosque_id'] ?? $r->get_param( 'mosque_id' ) ?? 0 );

        if ( ! $mosque_id ) {
            // Try from user's mosque
            $mosque_id = (int) get_user_meta( get_current_user_id(), 'ynj_mosque_id', true );
        }
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id required' ], 400 );
        }

        if ( $r->get_method() === 'GET' ) {
            return new \WP_REST_Response( [ 'ok' => true, 'broadcasters' => self::get_broadcasters( $mosque_id ) ] );
        }

        if ( $r->get_method() === 'POST' ) {
            $user_id = absint( $d['user_id'] ?? 0 );
            if ( ! $user_id ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'user_id required' ], 400 );
            }
            $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            $id = self::add_broadcaster( $mosque_id, $user_id, $ynj_uid );
            return new \WP_REST_Response( [ 'ok' => true, 'id' => $id ] );
        }

        if ( $r->get_method() === 'DELETE' ) {
            $user_id = absint( $d['user_id'] ?? 0 );
            if ( ! $user_id ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'user_id required' ], 400 );
            }
            self::remove_broadcaster( $mosque_id, $user_id );
            return new \WP_REST_Response( [ 'ok' => true ] );
        }

        return new \WP_REST_Response( [ 'ok' => false ], 405 );
    }
}
