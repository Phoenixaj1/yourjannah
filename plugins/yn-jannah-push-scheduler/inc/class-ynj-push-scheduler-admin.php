<?php
/**
 * Push Scheduler Admin — view schedule, last sent, manual trigger.
 * @package YNJ_Push_Scheduler
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Push_Scheduler_Admin {

    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Push Schedule', 'Push Schedule', 'manage_options', 'ynj-push-scheduler', [ 'YNJ_Push_Scheduler_Admin', 'page' ], 'dashicons-megaphone', 43 );
        } );
        add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
    }

    public static function handle_actions() {
        if ( isset( $_GET['ynj_push_action'] ) && $_GET['ynj_push_action'] === 'send_now' ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ynj_push_send' ) ) return;
            YNJ_Push_Scheduler::send_daily_push();
            wp_redirect( admin_url( 'admin.php?page=ynj-push-scheduler&sent=1' ) );
            exit;
        }
    }

    public static function page() {
        $last = YNJ_Push_Scheduler::get_last_sent();
        $next = YNJ_Push_Scheduler::get_next_scheduled();
        $messages = [
            1 => 'Monday: Start your week with gratitude',
            2 => 'Tuesday: Remember your Shukr today',
            3 => 'Wednesday: Midweek reminder',
            4 => 'Thursday: Prepare for Jumu\'ah',
            5 => 'Friday: Jumu\'ah Mubarak',
        ];

        if ( ! empty( $_GET['sent'] ) ) {
            echo '<div class="notice notice-success"><p>Push notification sent successfully.</p></div>';
        }

        echo '<div class="wrap"><h1>📡 Push Notification Schedule</h1>';
        echo '<p>Automated daily push notifications Mon-Fri at 5pm with community updates + gratitude reminders.</p>';

        // Schedule info
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:20px 0;">';

        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;">';
        echo '<h2 style="margin-top:0;">⏰ Schedule</h2>';
        echo '<p><strong>Frequency:</strong> Monday — Friday</p>';
        echo '<p><strong>Time:</strong> 5:00 PM</p>';
        echo '<p><strong>Next scheduled:</strong> ' . esc_html( $next ?: 'Not scheduled' ) . '</p>';
        echo '</div>';

        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;">';
        echo '<h2 style="margin-top:0;">📊 Last Sent</h2>';
        if ( $last ) {
            echo '<p><strong>Date:</strong> ' . esc_html( $last['date'] ) . '</p>';
            echo '<p><strong>Title:</strong> ' . esc_html( $last['title'] ) . '</p>';
            echo '<p><strong>Users reached:</strong> ' . (int) $last['users'] . '</p>';
        } else {
            echo '<p style="color:#999;">No pushes sent yet.</p>';
        }
        echo '</div>';
        echo '</div>';

        // Weekly messages
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:20px;">';
        echo '<h2 style="margin-top:0;">📋 Weekly Message Rotation</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Day</th><th>Message</th></tr></thead><tbody>';
        foreach ( $messages as $day => $msg ) {
            $today = ( (int) date( 'N' ) === $day ) ? ' style="font-weight:700;color:#287e61;"' : '';
            echo '<tr' . $today . '><td>' . [ '', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ][ $day ] . '</td><td>' . esc_html( $msg ) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        // Manual send
        echo '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=ynj-push-scheduler&ynj_push_action=send_now' ), 'ynj_push_send' ) . '" class="button button-primary" onclick="return confirm(\'Send today\\\'s push to all users now?\')">📡 Send Now (Manual)</a>';

        echo '</div>';
    }
}
