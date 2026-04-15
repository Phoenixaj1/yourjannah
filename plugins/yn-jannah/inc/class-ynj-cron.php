<?php
/**
 * YNJ_Cron — Automated prayer reminders via wp_cron.
 *
 * Checks every 5 minutes if any mosque has a prayer coming up in ~20 minutes.
 * Sends push notification to all subscribers of that mosque.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Cron {

    /** Minutes before prayer to send reminder. */
    const LEAD_MINUTES = 20;

    /** Window size — cron fires every 5 min, so check 15-25 min window. */
    const WINDOW_MIN = 15;
    const WINDOW_MAX = 25;

    /**
     * Schedule the cron event on plugin activation.
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( 'ynj_prayer_reminder_cron' ) ) {
            wp_schedule_event( time(), 'ynj_five_minutes', 'ynj_prayer_reminder_cron' );
        }

        // Register custom interval
        add_filter( 'cron_schedules', [ __CLASS__, 'add_interval' ] );
    }

    /**
     * Remove the cron event on plugin deactivation.
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( 'ynj_prayer_reminder_cron' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'ynj_prayer_reminder_cron' );
        }
    }

    /**
     * Add 5-minute interval to cron schedules.
     */
    public static function add_interval( $schedules ) {
        $schedules['ynj_five_minutes'] = [
            'interval' => 300,
            'display'  => 'Every 5 minutes (YourJannah)',
        ];
        return $schedules;
    }

    /**
     * Main cron callback — check all mosques with subscribers for upcoming prayers.
     */
    public static function check_prayers() {
        global $wpdb;

        $mosque_table = YNJ_DB::table( 'mosques' );
        $sub_table    = YNJ_DB::table( 'subscribers' );
        $pt_table     = YNJ_DB::table( 'prayer_times' );

        // Get mosques that have at least one active push subscriber
        $mosques = $wpdb->get_results(
            "SELECT DISTINCT m.id, m.name, m.latitude, m.longitude
             FROM {$mosque_table} m
             INNER JOIN {$sub_table} s ON s.mosque_id = m.id
             WHERE m.status = 'active'
               AND s.status = 'active'
               AND s.push_endpoint != ''
               AND m.latitude IS NOT NULL"
        );

        if ( empty( $mosques ) ) return;

        $today    = current_time( 'Y-m-d' );
        $now_time = current_time( 'H:i:s' );
        $prayers  = [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ];
        $labels   = [ 'fajr' => 'Fajr', 'dhuhr' => 'Dhuhr', 'asr' => 'Asr', 'maghrib' => 'Maghrib', 'isha' => 'Isha' ];

        $sent = 0;

        foreach ( $mosques as $mosque ) {
            // Get today's prayer times for this mosque
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$pt_table} WHERE mosque_id = %d AND date = %s LIMIT 1",
                $mosque->id, $today
            ), ARRAY_A );

            if ( ! $row ) continue;

            foreach ( $prayers as $prayer ) {
                $time = $row[ $prayer ] ?? null;
                if ( ! $time ) continue;

                // Use jamat time if available, otherwise adhan time
                $jamat = $row[ $prayer . '_jamat' ] ?? null;
                $target = $jamat ?: $time;

                // Calculate minutes until this prayer
                $now_ts    = strtotime( 'today ' . $now_time );
                $prayer_ts = strtotime( 'today ' . $target );
                $diff_min  = ( $prayer_ts - $now_ts ) / 60;

                // Check if prayer is within our reminder window (15-25 min from now)
                if ( $diff_min < self::WINDOW_MIN || $diff_min > self::WINDOW_MAX ) continue;

                // Dedup: check if we already sent for this mosque+prayer+date
                $dedup_key = "ynj_reminder_{$mosque->id}_{$prayer}_{$today}";
                if ( get_transient( $dedup_key ) ) continue;

                // Send push notification
                $prayer_label = $labels[ $prayer ];
                $prayer_time  = substr( $target, 0, 5 ); // HH:MM
                $mins_left    = round( $diff_min );

                $title = "{$prayer_label} in {$mins_left} minutes";
                $body  = "Time to get ready for prayer at {$mosque->name}";

                $result = YNJ_Push::send_to_mosque(
                    (int) $mosque->id,
                    $title,
                    $body,
                    '/'
                );

                // Set dedup transient (expires after 2 hours)
                set_transient( $dedup_key, 1, 7200 );

                $sent++;
                error_log( "[YNJ Cron] Sent {$prayer_label} reminder for {$mosque->name}: {$result['sent']} sent, {$result['failed']} failed" );
            }
        }

        if ( $sent > 0 ) {
            error_log( "[YNJ Cron] Prayer reminders: {$sent} notifications sent across all mosques." );
        }
    }
}

// Register the custom cron interval (must be done early)
add_filter( 'cron_schedules', [ 'YNJ_Cron', 'add_interval' ] );
