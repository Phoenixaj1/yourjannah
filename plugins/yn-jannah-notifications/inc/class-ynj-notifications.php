<?php
/**
 * Notifications Data Layer — in-app notifications CRUD.
 *
 * Handles creating, fetching, and marking notifications as read.
 * Push (VAPID) and email sending are handled by YNJ_Push and YNJ_Notify.
 *
 * @package YNJ_Notifications
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Notifications {

    /**
     * Create an in-app notification.
     *
     * @param array $data { mosque_id, user_id, title, body, url, type }
     * @return int|false Notification ID or false
     */
    public static function create( $data ) {
        global $wpdb;
        $t = YNJ_DB::table( 'notifications' );

        $result = $wpdb->insert( $t, [
            'mosque_id' => (int) ( $data['mosque_id'] ?? 0 ),
            'user_id'   => (int) ( $data['user_id'] ?? 0 ),
            'title'     => sanitize_text_field( $data['title'] ?? '' ),
            'body'      => sanitize_textarea_field( $data['body'] ?? '' ),
            'url'       => esc_url_raw( $data['url'] ?? '' ),
            'type'      => sanitize_text_field( $data['type'] ?? 'general' ),
        ] );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Broadcast a notification to all subscribers of a mosque.
     */
    public static function broadcast( $mosque_id, $title, $body, $url = '', $type = 'announcement' ) {
        global $wpdb;
        $sub_t = YNJ_DB::table( 'user_subscriptions' );
        $not_t = YNJ_DB::table( 'notifications' );

        $subscribers = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM $sub_t WHERE mosque_id = %d AND status = 'active'",
            $mosque_id
        ) );

        $count = 0;
        foreach ( $subscribers as $uid ) {
            $wpdb->insert( $not_t, [
                'mosque_id' => $mosque_id,
                'user_id'   => (int) $uid,
                'title'     => sanitize_text_field( $title ),
                'body'      => sanitize_textarea_field( $body ),
                'url'       => esc_url_raw( $url ),
                'type'      => $type,
            ] );
            $count++;
        }

        return $count;
    }

    /**
     * Get notifications for a user.
     */
    public static function get_for_user( $user_id, $args = [] ) {
        global $wpdb;
        $t = YNJ_DB::table( 'notifications' );

        $limit  = (int) ( $args['limit'] ?? 20 );
        $offset = (int) ( $args['offset'] ?? 0 );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT n.*, m.name AS mosque_name
             FROM $t n
             LEFT JOIN " . YNJ_DB::table( 'mosques' ) . " m ON m.id = n.mosque_id
             WHERE n.user_id = %d
             ORDER BY n.created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) ) ?: [];

        $unread = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $t WHERE user_id = %d AND read_at IS NULL",
            $user_id
        ) );

        return [
            'notifications' => $rows,
            'unread_count'  => $unread,
        ];
    }

    /**
     * Mark a notification as read.
     */
    public static function mark_read( $notification_id, $user_id ) {
        global $wpdb;
        return $wpdb->update(
            YNJ_DB::table( 'notifications' ),
            [ 'read_at' => current_time( 'mysql' ) ],
            [ 'id' => (int) $notification_id, 'user_id' => (int) $user_id ]
        );
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function mark_all_read( $user_id ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare(
            "UPDATE " . YNJ_DB::table( 'notifications' ) . " SET read_at = %s WHERE user_id = %d AND read_at IS NULL",
            current_time( 'mysql' ), $user_id
        ) );
    }

    /**
     * Get unread count for a user.
     */
    public static function unread_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . YNJ_DB::table( 'notifications' ) . " WHERE user_id = %d AND read_at IS NULL",
            $user_id
        ) );
    }

    /**
     * Delete old read notifications (cleanup cron).
     */
    public static function cleanup( $days = 30 ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare(
            "DELETE FROM " . YNJ_DB::table( 'notifications' ) . " WHERE read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
    }
}
