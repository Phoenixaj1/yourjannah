<?php
/**
 * Dua Wall Data Layer — share duas, pray for others, community supplication.
 *
 * @package YNJ_Dua_Wall
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Dua_Wall {

    /**
     * Get duas for a mosque (most recent + most prayed for).
     */
    public static function get_duas( $mosque_id, $args = [] ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );
        $ut = YNJ_DB::table( 'users' );

        $limit  = (int) ( $args['limit'] ?? 20 );
        $offset = (int) ( $args['offset'] ?? 0 );
        $status = $args['status'] ?? 'approved';

        $where = $wpdb->prepare( "WHERE d.mosque_id = %d AND d.status = %s", (int) $mosque_id, $status );

        $rows = $wpdb->get_results(
            "SELECT d.*, u.display_name AS author_name
             FROM $dt d LEFT JOIN $ut u ON u.id = d.user_id
             $where ORDER BY d.pinned DESC, d.created_at DESC
             LIMIT $limit OFFSET $offset"
        ) ?: [];

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt d $where" );

        return [ 'duas' => $rows, 'total' => $total ];
    }

    /**
     * Get all duas across all mosques.
     */
    public static function get_all_duas( $args = [] ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );
        $ut = YNJ_DB::table( 'users' );
        $mt = YNJ_DB::table( 'mosques' );

        $limit   = (int) ( $args['limit'] ?? 20 );
        $offset  = (int) ( $args['offset'] ?? 0 );
        $status  = $args['status'] ?? 'approved';
        $mosque  = (int) ( $args['mosque_id'] ?? 0 );

        $where = "WHERE d.status = " . $wpdb->prepare( "%s", $status );
        if ( $mosque ) $where .= $wpdb->prepare( " AND d.mosque_id = %d", $mosque );

        $rows = $wpdb->get_results(
            "SELECT d.*, u.display_name AS author_name, m.name AS mosque_name
             FROM $dt d
             LEFT JOIN $ut u ON u.id = d.user_id
             LEFT JOIN $mt m ON m.id = d.mosque_id
             $where ORDER BY d.created_at DESC
             LIMIT $limit OFFSET $offset"
        ) ?: [];

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt d $where" );

        return [ 'duas' => $rows, 'total' => $total ];
    }

    /**
     * Create a new dua.
     */
    public static function create( $data ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );

        // Rate limit: 3 duas per day per user
        $user_id = (int) ( $data['user_id'] ?? 0 );
        if ( $user_id ) {
            $today_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $dt WHERE user_id = %d AND DATE(created_at) = CURDATE()", $user_id
            ) );
            if ( $today_count >= 3 ) {
                return new \WP_Error( 'rate_limited', 'You can share up to 3 duas per day.' );
            }
        }

        $result = $wpdb->insert( $dt, [
            'mosque_id'  => (int) ( $data['mosque_id'] ?? 0 ),
            'user_id'    => $user_id,
            'dua_text'   => sanitize_textarea_field( $data['dua_text'] ?? '' ),
            'is_anonymous' => (int) ( $data['is_anonymous'] ?? 0 ),
            'status'     => 'approved', // Auto-approve for now
        ] );

        if ( $result ) {
            do_action( 'ynj_dua_created', $wpdb->insert_id, $data );
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Pray for a dua (increment prayer count).
     */
    public static function pray( $dua_id, $user_id ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );
        $rt = YNJ_DB::table( 'dua_responses' );

        // Check if already prayed
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $rt WHERE dua_id = %d AND user_id = %d", (int) $dua_id, (int) $user_id
        ) );
        if ( $exists ) return [ 'already' => true ];

        // Record the prayer
        $wpdb->insert( $rt, [
            'dua_id'  => (int) $dua_id,
            'user_id' => (int) $user_id,
        ] );

        // Increment count
        $wpdb->query( $wpdb->prepare(
            "UPDATE $dt SET prayer_count = prayer_count + 1 WHERE id = %d", (int) $dua_id
        ) );

        do_action( 'ynj_dua_prayed', $dua_id, $user_id );

        $new_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT prayer_count FROM $dt WHERE id = %d", (int) $dua_id
        ) );

        return [ 'ok' => true, 'count' => $new_count ];
    }

    /**
     * Get prayer count for a dua.
     */
    public static function get_prayer_count( $dua_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT prayer_count FROM " . YNJ_DB::table( 'dua_requests' ) . " WHERE id = %d", (int) $dua_id
        ) );
    }

    /**
     * Check if user has prayed for a specific dua.
     */
    public static function has_prayed( $dua_id, $user_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . YNJ_DB::table( 'dua_responses' ) . " WHERE dua_id = %d AND user_id = %d",
            (int) $dua_id, (int) $user_id
        ) );
    }

    /**
     * Moderate a dua (approve/reject/pin).
     */
    public static function moderate( $dua_id, $action ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );

        switch ( $action ) {
            case 'approve':
                return $wpdb->update( $dt, [ 'status' => 'approved' ], [ 'id' => (int) $dua_id ] );
            case 'reject':
                return $wpdb->update( $dt, [ 'status' => 'rejected' ], [ 'id' => (int) $dua_id ] );
            case 'pin':
                return $wpdb->update( $dt, [ 'pinned' => 1 ], [ 'id' => (int) $dua_id ] );
            case 'unpin':
                return $wpdb->update( $dt, [ 'pinned' => 0 ], [ 'id' => (int) $dua_id ] );
            case 'delete':
                return $wpdb->delete( $dt, [ 'id' => (int) $dua_id ] );
        }
        return false;
    }

    /**
     * Get stats for a mosque.
     */
    public static function get_stats( $mosque_id = 0 ) {
        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );
        $mw = $mosque_id ? $wpdb->prepare( " AND mosque_id = %d", (int) $mosque_id ) : '';

        return [
            'total'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt WHERE status = 'approved' $mw" ),
            'total_prayers' => (int) $wpdb->get_var( "SELECT COALESCE(SUM(prayer_count), 0) FROM $dt WHERE status = 'approved' $mw" ),
            'today'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt WHERE status = 'approved' AND DATE(created_at) = CURDATE() $mw" ),
        ];
    }

    /**
     * Seed the dua wall with the first dua from YourJannah.
     */
    public static function seed() {
        if ( ! class_exists( 'YNJ_DB' ) ) return;

        global $wpdb;
        $dt = YNJ_DB::table( 'dua_requests' );

        // Only seed if empty
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt" );
        if ( $count > 0 ) return;

        // Get the first mosque
        $mosque_id = (int) $wpdb->get_var(
            "SELECT id FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY id ASC LIMIT 1"
        );
        if ( ! $mosque_id ) return;

        $wpdb->insert( $dt, [
            'mosque_id'    => $mosque_id,
            'user_id'      => 0,
            'dua_text'     => "Ya Allah, we ask You to bring the hearts of our Ummah together through YourJannah. Let this platform be a means of unity, remembrance, and strength for every Muslim who visits it. Grant that as many people as possible find their way here, so that our communities grow stronger, stay grounded in Your remembrance in times of confusion, and find safety in Your protection in times of chaos. Ya Rabb, make YourJannah a source of good for every masjid, every family, and every soul that connects through it. Ameen.",
            'is_anonymous' => 0,
            'status'       => 'approved',
            'pinned'       => 1,
            'prayer_count' => 1,
        ] );
    }
}
