<?php
/**
 * Smart Admin Nudges — Contextual prompts to drive admin engagement.
 *
 * Returns an array of nudge cards based on mosque state. Displayed at top of dashboard overview.
 * Each nudge has: icon, title, body, action_label, action_url, color, dismissable.
 *
 * @package YourJannah
 * @since   3.9.8
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get applicable admin nudges for a mosque.
 *
 * @param int    $mosque_id
 * @param object $mosque    Mosque DB row
 * @param array  $stats     KPI stats from overview
 * @return array
 */
function ynj_get_admin_nudges( $mosque_id, $mosque, $stats = [] ) {
    global $wpdb;
    $nudges = [];
    $at = YNJ_DB::table( 'announcements' );
    $ev = YNJ_DB::table( 'events' );
    $bk = YNJ_DB::table( 'bookings' );

    // Check dismissed nudges (stored in user meta)
    $wp_uid = get_current_user_id();
    $dismissed = get_user_meta( $wp_uid, 'ynj_dismissed_nudges', true ) ?: [];

    // 1. No post in 5+ days
    $last_post = $wpdb->get_var( $wpdb->prepare(
        "SELECT MAX(published_at) FROM $at WHERE mosque_id = %d AND status = 'published'", $mosque_id
    ) );
    $days_silent = $last_post ? (int) ( ( time() - strtotime( $last_post ) ) / DAY_IN_SECONDS ) : 999;
    if ( $days_silent >= 5 && ! isset( $dismissed['silent'] ) ) {
        $nudges[] = [
            'key'   => 'silent',
            'icon'  => '📢',
            'title' => sprintf( 'Your congregation hasn\'t heard from you in %d days', $days_silent ),
            'body'  => 'Regular updates keep your community engaged. Even a short reminder helps.',
            'action_label' => 'Post an Update',
            'action_url'   => '?section=announcements',
            'color' => '#f59e0b',
        ];
    }

    // 2. It's Friday (Jumu'ah day)
    if ( date( 'N' ) == 5 && ! isset( $dismissed['jumuah_' . date( 'Y-m-d' )] ) ) {
        $nudges[] = [
            'key'   => 'jumuah_' . date( 'Y-m-d' ),
            'icon'  => '🕌',
            'title' => 'Jumu\'ah Mubarak!',
            'body'  => 'Send a Jumu\'ah reminder to your congregation. One tap with our quick templates.',
            'action_label' => 'Quick Post',
            'action_url'   => home_url( '/mosque/' . ( $mosque->slug ?? '' ) ),
            'color' => '#16a34a',
        ];
    }

    // 3. Event tomorrow with low RSVPs
    $tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
    $low_rsvp_event = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, title, registered_count FROM $ev WHERE mosque_id = %d AND event_date = %s AND status = 'published' AND registered_count < 5 LIMIT 1",
        $mosque_id, $tomorrow
    ) );
    if ( $low_rsvp_event && ! isset( $dismissed['low_rsvp'] ) ) {
        $nudges[] = [
            'key'   => 'low_rsvp',
            'icon'  => '📅',
            'title' => sprintf( '"%s" is tomorrow with only %d RSVPs', $low_rsvp_event->title, $low_rsvp_event->registered_count ),
            'body'  => 'Send a broadcast reminder to boost attendance.',
            'action_label' => 'Send Reminder',
            'action_url'   => '?section=broadcast',
            'color' => '#dc2626',
        ];
    }

    // 4. Pending bookings need attention
    $pending_bk = (int) ( $stats['pending_bk'] ?? 0 );
    if ( $pending_bk > 0 ) {
        $nudges[] = [
            'key'   => 'pending_bk',
            'icon'  => '📋',
            'title' => sprintf( '%d booking%s waiting for your approval', $pending_bk, $pending_bk > 1 ? 's' : '' ),
            'body'  => 'Respond quickly to keep your community happy.',
            'action_label' => 'Review Bookings',
            'action_url'   => '?section=bookings',
            'color' => '#7c3aed',
        ];
    }

    // 5. New enquiries unanswered
    $new_eq = (int) ( $stats['new_enquiries'] ?? 0 );
    if ( $new_eq > 0 ) {
        $nudges[] = [
            'key'   => 'enquiries',
            'icon'  => '✉️',
            'title' => sprintf( '%d unanswered enquir%s', $new_eq, $new_eq > 1 ? 'ies' : 'y' ),
            'body'  => 'People are reaching out. A quick reply goes a long way.',
            'action_label' => 'Respond',
            'action_url'   => '?section=enquiries',
            'color' => '#0369a1',
        ];
    }

    // 6. Revenue milestone celebrations
    $total_mrr = (int) ( $stats['patron_mrr'] ?? 0 ) + (int) ( $stats['sponsor_mrr'] ?? 0 );
    $milestones = [ 50000 => '£500', 100000 => '£1,000', 200000 => '£2,000', 500000 => '£5,000' ];
    foreach ( $milestones as $threshold => $label ) {
        if ( $total_mrr >= $threshold && ! isset( $dismissed[ 'milestone_' . $threshold ] ) ) {
            $nudges[] = [
                'key'   => 'milestone_' . $threshold,
                'icon'  => '🎉',
                'title' => sprintf( 'You\'ve hit %s/month in recurring revenue!', $label ),
                'body'  => 'MashAllah! Your community is supporting the mosque consistently.',
                'action_label' => 'View Revenue',
                'action_url'   => '?section=patrons',
                'color' => '#16a34a',
            ];
            break; // Only show highest milestone reached
        }
    }

    // 7. Subscriber milestone
    $subs = (int) ( $stats['subscribers'] ?? 0 );
    $sub_milestones = [ 10, 50, 100, 250, 500, 1000 ];
    foreach ( $sub_milestones as $sm ) {
        if ( $subs >= $sm && ! isset( $dismissed[ 'subs_' . $sm ] ) ) {
            $nudges[] = [
                'key'   => 'subs_' . $sm,
                'icon'  => '🎊',
                'title' => sprintf( '%d+ subscribers!', $sm ),
                'body'  => 'Your community is growing. Share the milestone!',
                'action_label' => 'View Subscribers',
                'action_url'   => '?section=subscribers',
                'color' => '#0369a1',
            ];
            break;
        }
    }

    // Only show max 3 nudges at a time (most important first)
    return array_slice( $nudges, 0, 3 );
}

/**
 * Calculate posting streak (consecutive weeks with at least one post).
 */
function ynj_get_posting_streak( $mosque_id ) {
    global $wpdb;
    $at = YNJ_DB::table( 'announcements' );
    $ev = YNJ_DB::table( 'events' );

    // Get all post dates in last 12 weeks
    $dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT DATE(published_at) AS d FROM $at WHERE mosque_id = %d AND status = 'published' AND published_at >= DATE_SUB(NOW(), INTERVAL 84 DAY)
         UNION
         SELECT DISTINCT DATE(created_at) AS d FROM $ev WHERE mosque_id = %d AND status = 'published' AND created_at >= DATE_SUB(NOW(), INTERVAL 84 DAY)
         ORDER BY d DESC",
        $mosque_id, $mosque_id
    ) );

    if ( empty( $dates ) ) return [ 'streak' => 0, 'this_week' => [] ];

    // Map dates to ISO week numbers
    $weeks_with_posts = [];
    $this_week_days   = [];
    $current_week = date( 'o-W' );

    foreach ( $dates as $d ) {
        $w = date( 'o-W', strtotime( $d ) );
        $weeks_with_posts[ $w ] = true;
        if ( $w === $current_week ) {
            $this_week_days[] = date( 'N', strtotime( $d ) ); // 1=Mon, 7=Sun
        }
    }

    // Count consecutive weeks from current week backwards
    $streak = 0;
    for ( $i = 0; $i < 12; $i++ ) {
        $check_week = date( 'o-W', strtotime( "-{$i} weeks" ) );
        if ( isset( $weeks_with_posts[ $check_week ] ) ) {
            $streak++;
        } else {
            break;
        }
    }

    return [ 'streak' => $streak, 'this_week' => $this_week_days ];
}

/**
 * Get content view stats for a mosque in a date range.
 */
function ynj_get_content_stats( $mosque_id, $days = 7 ) {
    global $wpdb;
    $cv = YNJ_DB::table( 'content_views' );
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );
    $prev_since = date( 'Y-m-d', strtotime( "-" . ( $days * 2 ) . " days" ) );

    // This period
    $current = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(view_count),0) AS views, COALESCE(SUM(interested_count),0) AS interested, COALESCE(SUM(share_count),0) AS shares
         FROM $cv WHERE mosque_id = %d AND view_date >= %s",
        $mosque_id, $since
    ) );

    // Previous period (for comparison)
    $previous = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(view_count),0) AS views FROM $cv WHERE mosque_id = %d AND view_date >= %s AND view_date < %s",
        $mosque_id, $prev_since, $since
    ) );

    $views_now  = (int) $current->views;
    $views_prev = (int) $previous->views;
    $change_pct = $views_prev > 0 ? round( ( $views_now - $views_prev ) / $views_prev * 100 ) : ( $views_now > 0 ? 100 : 0 );

    return [
        'views'          => $views_now,
        'interested'     => (int) $current->interested,
        'shares'         => (int) $current->shares,
        'views_prev'     => $views_prev,
        'change_pct'     => $change_pct,
    ];
}

/**
 * Get top performing content for a mosque.
 */
function ynj_get_top_content( $mosque_id, $days = 7, $limit = 3 ) {
    global $wpdb;
    $cv = YNJ_DB::table( 'content_views' );
    $at = YNJ_DB::table( 'announcements' );
    $ev = YNJ_DB::table( 'events' );
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );

    $top = $wpdb->get_results( $wpdb->prepare(
        "SELECT cv.content_type, cv.content_id, SUM(cv.view_count) AS total_views, SUM(cv.interested_count) AS total_interested
         FROM $cv cv
         WHERE cv.mosque_id = %d AND cv.view_date >= %s
         GROUP BY cv.content_type, cv.content_id
         ORDER BY total_views DESC
         LIMIT %d",
        $mosque_id, $since, $limit
    ) );

    // Enrich with titles
    foreach ( $top as &$item ) {
        if ( $item->content_type === 'announcement' ) {
            $item->title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM $at WHERE id = %d", $item->content_id ) );
        } elseif ( $item->content_type === 'event' ) {
            $item->title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM $ev WHERE id = %d", $item->content_id ) );
        }
    }
    unset( $item );

    return $top;
}

/**
 * Get recent activity feed for a mosque (last N items across all tables).
 */
function ynj_get_activity_feed( $mosque_id, $limit = 15 ) {
    global $wpdb;
    $bk  = YNJ_DB::table( 'bookings' );
    $sub = YNJ_DB::table( 'user_subscriptions' );
    $ut  = YNJ_DB::table( 'users' );
    $dt  = YNJ_DB::table( 'donations' );
    $eq  = YNJ_DB::table( 'enquiries' );
    $pt  = YNJ_DB::table( 'patrons' );
    $ev  = YNJ_DB::table( 'events' );

    $activities = $wpdb->get_results( $wpdb->prepare(
        "(SELECT 'booking' AS activity_type, b.user_name AS who, CONCAT('booked for ', b.booking_date) AS what, b.created_at AS when_at
          FROM $bk b WHERE b.mosque_id = %d ORDER BY b.created_at DESC LIMIT 5)
         UNION ALL
         (SELECT 'subscriber' AS activity_type, u.name AS who, 'joined as subscriber' AS what, s.subscribed_at AS when_at
          FROM $sub s JOIN $ut u ON u.id = s.user_id WHERE s.mosque_id = %d ORDER BY s.subscribed_at DESC LIMIT 5)
         UNION ALL
         (SELECT 'donation' AS activity_type, COALESCE(d.donor_name,'Anonymous') AS who, CONCAT('donated £', FORMAT(d.amount_pence/100,0)) AS what, d.created_at AS when_at
          FROM $dt d WHERE d.mosque_id = %d AND d.status = 'succeeded' ORDER BY d.created_at DESC LIMIT 5)
         UNION ALL
         (SELECT 'enquiry' AS activity_type, e.name AS who, CONCAT('enquired: \"', LEFT(e.subject,40), '\"') AS what, e.created_at AS when_at
          FROM $eq e WHERE e.mosque_id = %d ORDER BY e.created_at DESC LIMIT 5)
         UNION ALL
         (SELECT 'patron' AS activity_type, COALESCE(p.user_name,p.user_email) AS who, CONCAT('became a ', p.tier, ' patron') AS what, p.created_at AS when_at
          FROM $pt p WHERE p.mosque_id = %d AND p.status = 'active' ORDER BY p.created_at DESC LIMIT 5)
         ORDER BY when_at DESC
         LIMIT %d",
        $mosque_id, $mosque_id, $mosque_id, $mosque_id, $mosque_id, $limit
    ) );

    return $activities;
}

/**
 * Get subscriber growth data for sparkline (last 4 weeks).
 */
function ynj_get_subscriber_growth( $mosque_id ) {
    global $wpdb;
    $sub = YNJ_DB::table( 'user_subscriptions' );
    $weeks = [];
    for ( $i = 3; $i >= 0; $i-- ) {
        $start = date( 'Y-m-d', strtotime( "-{$i} weeks Monday" ) );
        $end   = date( 'Y-m-d', strtotime( "-{$i} weeks Sunday" ) );
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND subscribed_at BETWEEN %s AND %s",
            $mosque_id, $start . ' 00:00:00', $end . ' 23:59:59'
        ) );
        $weeks[] = $count;
    }

    // This week
    $this_week = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND subscribed_at >= %s",
        $mosque_id, date( 'Y-m-d', strtotime( 'Monday this week' ) ) . ' 00:00:00'
    ) );

    // This month
    $this_month = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $sub WHERE mosque_id = %d AND subscribed_at >= %s",
        $mosque_id, date( 'Y-m-01' ) . ' 00:00:00'
    ) );

    return [
        'weekly_data' => $weeks,
        'this_week'   => $this_week,
        'this_month'  => $this_month,
    ];
}

/**
 * Get mosque ranking in its city.
 */
function ynj_get_mosque_ranking( $mosque_id, $city ) {
    if ( ! $city ) return null;
    global $wpdb;
    $mt = YNJ_DB::table( 'mosques' );
    $cv = YNJ_DB::table( 'content_views' );
    $at = YNJ_DB::table( 'announcements' );
    $sub = YNJ_DB::table( 'user_subscriptions' );
    $since = date( 'Y-m-d', strtotime( '-30 days' ) );

    // Score = content posts (x10) + views (x1) + subscribers (x5)
    $rankings = $wpdb->get_results( $wpdb->prepare(
        "SELECT m.id, m.name,
                (SELECT COUNT(*) FROM $at WHERE mosque_id = m.id AND published_at >= %s) * 10 +
                COALESCE((SELECT SUM(view_count) FROM $cv WHERE mosque_id = m.id AND view_date >= %s), 0) +
                (SELECT COUNT(*) FROM $sub WHERE mosque_id = m.id AND status = 'active') * 5 AS score
         FROM $mt m
         WHERE m.city = %s AND m.status = 'active'
         ORDER BY score DESC
         LIMIT 50",
        $since, $since, $city
    ) );

    $rank = 0;
    $my_score = 0;
    $total_in_city = count( $rankings );
    foreach ( $rankings as $i => $r ) {
        if ( (int) $r->id === $mosque_id ) {
            $rank = $i + 1;
            $my_score = (int) $r->score;
            break;
        }
    }

    if ( ! $rank ) return null;

    return [
        'rank'  => $rank,
        'total' => $total_in_city,
        'score' => $my_score,
        'city'  => $city,
    ];
}
