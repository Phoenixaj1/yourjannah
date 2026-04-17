<?php
/**
 * Cross-Mosque Interest Notifications
 *
 * When a mosque publishes an event or announcement, this class finds users
 * within their interest radius who care about that category and creates
 * in-app notifications for them — but only if they are NOT already
 * subscribed to the publishing mosque (those users get normal push
 * notifications instead).
 *
 * @package YourJannah
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_Interest_Notify {

    /**
     * Map event_type values to interest category slugs.
     */
    private static $event_type_map = [
        'talk'        => 'education',
        'youth'       => 'youth',
        'course'      => 'education',
        'community'   => 'community',
        'workshop'    => 'education',
        'sports'      => 'sports',
        'competition' => 'youth',
        'class'       => 'education',
        'quran'       => 'religious',
    ];

    /**
     * Map announcement type values to interest category slugs.
     */
    private static $announcement_type_map = [
        'general'   => 'community',
        'urgent'    => 'community',
        'event'     => 'community',
        'religious' => 'religious',
    ];

    /**
     * Resolve an event type to an interest category.
     *
     * @param  string $event_type  Raw event type slug.
     * @return string              Interest category slug.
     */
    public static function map_event_category( $event_type ) {
        return self::$event_type_map[ $event_type ] ?? 'community';
    }

    /**
     * Resolve an announcement type to an interest category.
     *
     * @param  string $ann_type  Raw announcement type slug.
     * @return string            Interest category slug.
     */
    public static function map_announcement_category( $ann_type ) {
        return self::$announcement_type_map[ $ann_type ] ?? 'community';
    }

    /**
     * Dispatch notifications to users with matching interests within radius.
     *
     * @param int    $mosque_id   The mosque that published the content.
     * @param string $type        'event' or 'announcement'.
     * @param int    $ref_id      The event/announcement ID.
     * @param string $title       Content title.
     * @param string $body        Short snippet (first 100 chars).
     * @param string $category    Interest category slug (e.g. 'sports', 'youth', 'religious').
     * @param string $url         URL to view the content.
     */
    public static function dispatch( $mosque_id, $type, $ref_id, $title, $body, $category, $url ) {
        global $wpdb;

        $mosque_id = (int) $mosque_id;
        $ref_id    = (int) $ref_id;

        // 1. Get the mosque's lat/lng.
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT latitude, longitude FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d",
            $mosque_id
        ) );

        if ( ! $mosque || ! $mosque->latitude || ! $mosque->longitude ) {
            return; // Mosque has no coordinates — cannot calculate distances.
        }

        $mosque_lat = (float) $mosque->latitude;
        $mosque_lng = (float) $mosque->longitude;

        // 2. Build the query to find matching users.
        //    - Must have interest_categories containing this category
        //    - Must have verified_lat/verified_lng set
        //    - Must be within their own interest_radius_miles of this mosque
        //    - Must NOT already be subscribed to this mosque
        //
        // We use LIKE to match the category inside the JSON array text,
        // e.g. interest_categories LIKE '%"sports"%'.
        //
        // Haversine formula (result in miles):
        //   3959 * acos(
        //     cos(radians(user_lat)) * cos(radians(mosque_lat))
        //       * cos(radians(mosque_lng) - radians(user_lng))
        //     + sin(radians(user_lat)) * sin(radians(mosque_lat))
        //   )

        $users_table = YNJ_DB::table( 'users' );
        $subs_table  = YNJ_DB::table( 'user_subscriptions' );

        $category_like = '%"' . $wpdb->esc_like( $category ) . '"%';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT u.id, u.name
             FROM {$users_table} u
             WHERE u.interest_categories LIKE %s
               AND u.verified_lat IS NOT NULL
               AND u.verified_lng IS NOT NULL
               AND u.status = 'active'
               AND (
                   3959 * ACOS(
                       LEAST(1, GREATEST(-1,
                           COS(RADIANS(u.verified_lat)) * COS(RADIANS(%f))
                             * COS(RADIANS(%f) - RADIANS(u.verified_lng))
                           + SIN(RADIANS(u.verified_lat)) * SIN(RADIANS(%f))
                       ))
                   )
               ) <= u.interest_radius_miles
               AND u.id NOT IN (
                   SELECT s.user_id FROM {$subs_table} s
                   WHERE s.mosque_id = %d AND s.status = 'active'
               )
             LIMIT 500",
            $category_like,
            $mosque_lat,
            $mosque_lng,
            $mosque_lat,
            $mosque_id
        );
        // phpcs:enable

        $users = $wpdb->get_results( $sql );

        if ( empty( $users ) ) {
            return;
        }

        // 3. Insert a notification for each matching user.
        $notif_table = YNJ_DB::table( 'notifications' );
        $now         = current_time( 'mysql' );
        $safe_title  = sanitize_text_field( $title );
        $safe_body   = sanitize_text_field( mb_substr( wp_strip_all_tags( $body ), 0, 100 ) );
        $safe_url    = esc_url_raw( $url );
        $safe_type   = sanitize_text_field( $type );

        foreach ( $users as $user ) {
            $wpdb->insert( $notif_table, [
                'user_id'    => (int) $user->id,
                'mosque_id'  => $mosque_id,
                'type'       => $safe_type,
                'ref_id'     => $ref_id,
                'title'      => $safe_title,
                'body'       => $safe_body,
                'url'        => $safe_url,
                'is_read'    => 0,
                'created_at' => $now,
            ] );
        }
    }
}
