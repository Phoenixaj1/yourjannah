<?php
/**
 * Plugin Name: YourJannah — Push Scheduler
 * Description: Scheduled push notifications — 5 per week (Mon-Fri 5pm). Community updates + daily gratitude reminders.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Push)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_PUSH_SCHEDULER_VERSION', '1.0.0' );
define( 'YNJ_PUSH_SCHEDULER_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_PUSH_SCHEDULER_DIR . 'inc/class-ynj-push-scheduler.php';

    if ( is_admin() ) {
        require_once YNJ_PUSH_SCHEDULER_DIR . 'inc/class-ynj-push-scheduler-admin.php';
        YNJ_Push_Scheduler_Admin::init();
    }
}, 10 );

// Schedule cron on activation
register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'ynj_daily_push_5pm' ) ) {
        // Schedule for 5pm UK time (17:00 BST/GMT)
        $next_5pm = strtotime( 'today 17:00' );
        if ( $next_5pm < time() ) $next_5pm = strtotime( 'tomorrow 17:00' );
        wp_schedule_event( $next_5pm, 'daily', 'ynj_daily_push_5pm' );
    }
} );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'ynj_daily_push_5pm' );
} );

// Cron handler
add_action( 'ynj_daily_push_5pm', function() {
    // Only Mon-Fri (day 1-5)
    $day = (int) date( 'N' );
    if ( $day > 5 ) return; // Skip weekends

    if ( class_exists( 'YNJ_Push_Scheduler' ) ) {
        YNJ_Push_Scheduler::send_daily_push();
    }
} );
