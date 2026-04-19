<?php
/**
 * Push Scheduler — sends daily push notifications Mon-Fri at 5pm.
 *
 * Content: community updates count + gratitude reminder.
 * Rotates through 5 daily messages for variety.
 *
 * @package YNJ_Push_Scheduler
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Push_Scheduler {

    /**
     * Daily gratitude messages — one for each weekday.
     */
    private static function get_daily_messages() {
        return [
            1 => [ // Monday
                'title' => '🤲 Start your week with gratitude',
                'body'  => 'Thank Allah ﷻ for our opportunities and sustenance. Remember to purify your rizq through alms.',
            ],
            2 => [ // Tuesday
                'title' => '📿 Remember your Shukr today',
                'body'  => 'Alhamdulillah for every blessing. "If you are grateful, I will surely increase you" — Quran 14:7',
            ],
            3 => [ // Wednesday
                'title' => '🕌 Midweek reminder',
                'body'  => 'Thank Allah ﷻ for our rizq and remember to purify it through charity. Even a smile is sadaqah.',
            ],
            4 => [ // Thursday
                'title' => '✨ Prepare for Jumu\'ah',
                'body'  => 'Tomorrow is the best day of the week. Thank Allah ﷻ for bringing you to another Friday. Send salawat upon the Prophet ﷺ.',
            ],
            5 => [ // Friday
                'title' => '📿 Jumu\'ah Mubarak',
                'body'  => 'Remember Allah ﷻ abundantly today. Thank Him for our sustenance and opportunities. Purify your wealth through alms.',
            ],
        ];
    }

    /**
     * Send the daily push to all subscribers across all mosques.
     */
    public static function send_daily_push() {
        $day = (int) date( 'N' );
        if ( $day > 5 ) return; // Safety check

        $messages = self::get_daily_messages();
        $msg = $messages[ $day ] ?? $messages[1];

        global $wpdb;

        // Get unread notification count per user (new community content today)
        $nt = YNJ_DB::table( 'notifications' );
        $today = date( 'Y-m-d' );

        // Count new content today for the push body
        $at = YNJ_DB::table( 'announcements' );
        $new_announcements = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $at WHERE status = 'published' AND DATE(published_at) = %s", $today
        ) );

        if ( $new_announcements > 0 ) {
            $msg['body'] = $new_announcements . ' new update' . ( $new_announcements > 1 ? 's' : '' ) . ' from your community today. ' . $msg['body'];
        }

        // Send push via YNJ_Push if available
        if ( class_exists( 'YNJ_Push' ) && method_exists( 'YNJ_Push', 'send_to_all' ) ) {
            YNJ_Push::send_to_all( $msg['title'], $msg['body'], home_url( '/' ) );
        }

        // Also create in-app notifications for all active users
        $sub = YNJ_DB::table( 'user_subscriptions' );
        $user_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM $sub WHERE status = 'active'" );

        if ( class_exists( 'YNJ_Notifications' ) ) {
            foreach ( $user_ids as $uid ) {
                YNJ_Notifications::create( [
                    'user_id' => (int) $uid,
                    'title'   => $msg['title'],
                    'body'    => $msg['body'],
                    'url'     => home_url( '/' ),
                    'type'    => 'daily_reminder',
                ] );
            }
        }

        // Log
        update_option( 'ynj_push_last_sent', [
            'date'  => date( 'Y-m-d H:i:s' ),
            'day'   => $day,
            'title' => $msg['title'],
            'users' => count( $user_ids ),
        ] );
    }

    /**
     * Get last push log.
     */
    public static function get_last_sent() {
        return get_option( 'ynj_push_last_sent', null );
    }

    /**
     * Get scheduled next run.
     */
    public static function get_next_scheduled() {
        $ts = wp_next_scheduled( 'ynj_daily_push_5pm' );
        return $ts ? date( 'Y-m-d H:i:s', $ts ) : null;
    }
}
