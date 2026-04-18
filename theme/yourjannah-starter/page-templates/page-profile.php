<?php
/**
 * Template: Personal Ibadah Hub + Account Dashboard
 *
 * The personal engagement hub — dopamine-rich, habit-forming, tribal.
 * Section 1: Hero (identity + tribe + streak)
 * Section 2: Today's Ibadah Tracker (prayer buttons, Quran, dhikr, etc.)
 * Section 3: Tribal Feedback Loop (mosque rank, impact, motivational messages)
 * Section 4: Streak Dashboard (7-day grid, Fajr/Jumu'ah streaks, heatmap)
 * Section 5: Badges & Progress (17 badges with unlock progress)
 * Section 6: My Dua Requests (personal dua history)
 * Section 7: Account (patron, subscriptions, bookings — collapsed)
 *
 * @package YourJannah
 */

get_header();

// ── Auth Gate ──
if ( ! is_user_logged_in() ) : ?>
<main class="ynj-main">
    <section class="ynj-card" style="text-align:center;padding:48px 20px;">
        <div style="font-size:48px;margin-bottom:12px;">&#x1F512;</div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;"><?php esc_html_e( 'Not Signed In', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted" style="margin-bottom:16px;"><?php esc_html_e( 'Sign in to track your ibadah, earn streaks, and see your impact.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="ynj-btn" style="justify-content:center;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
        <p style="margin-top:12px;font-size:13px;">
            <?php esc_html_e( "Don't have an account?", 'yourjannah' ); ?>
            <a href="<?php echo esc_url( home_url( '/register' ) ); ?>" style="font-weight:700;"><?php esc_html_e( 'Create one', 'yourjannah' ); ?></a>
        </p>
    </section>
</main>
<?php get_footer(); return; endif;

// ── Load ALL data in PHP (server-side — no JS fetch for initial render) ──
$wp_user  = wp_get_current_user();
$wp_uid   = (int) $wp_user->ID;
$ynj_uid  = (int) get_user_meta( $wp_uid, 'ynj_user_id', true );
$phone    = get_user_meta( $wp_uid, 'ynj_phone', true ) ?: '';
$fav_mosque_id = (int) get_user_meta( $wp_uid, 'ynj_favourite_mosque_id', true );

// Auto-link: if WP user exists but ynj_user_id not set
if ( ! $ynj_uid && $wp_uid && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $ut = YNJ_DB::table( 'users' );
    $email = $wp_user->user_email;
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $ut WHERE email = %s LIMIT 1", $email ) );
    if ( $existing ) {
        $ynj_uid = (int) $existing->id;
    } else {
        $token = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );
        $wpdb->insert( $ut, [
            'name' => $wp_user->display_name, 'email' => $email, 'phone' => $phone,
            'password_hash' => '', 'token_hash' => $token_hash, 'status' => 'active',
        ] );
        $ynj_uid = (int) $wpdb->insert_id;
    }
    if ( $ynj_uid ) update_user_meta( $wp_uid, 'ynj_user_id', $ynj_uid );
}

// Defaults
$patron = null; $ynj_user = null; $subscriptions = []; $bookings = [];
$businesses = []; $services = []; $points_total = 0; $points_recent = [];
$fav_mosque = null;

// ── Core user data ──
if ( $ynj_uid && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $users_table   = YNJ_DB::table( 'users' );
    $mosques_table = YNJ_DB::table( 'mosques' );

    $ynj_user = $wpdb->get_row( $wpdb->prepare(
        "SELECT travel_mode, travel_minutes, alert_before_minutes, total_points, favourite_mosque_id FROM $users_table WHERE id = %d", $ynj_uid
    ) );

    if ( ! $fav_mosque_id && $ynj_user && $ynj_user->favourite_mosque_id ) {
        $fav_mosque_id = (int) $ynj_user->favourite_mosque_id;
    }

    // Patron
    $patron_table = YNJ_DB::table( 'patrons' );
    $patron = $wpdb->get_row( $wpdb->prepare(
        "SELECT p.*, m.name AS mosque_name, m.slug AS mosque_slug FROM $patron_table p
         LEFT JOIN $mosques_table m ON m.id = p.mosque_id
         WHERE p.user_id = %d AND p.status = 'active' ORDER BY p.amount_pence DESC LIMIT 1", $ynj_uid
    ) );

    // Subscriptions
    $sub_table = YNJ_DB::table( 'user_subscriptions' );
    $subscriptions = $wpdb->get_results( $wpdb->prepare(
        "SELECT s.*, m.name AS mosque_name, m.city AS mosque_city, m.slug AS mosque_slug
         FROM $sub_table s LEFT JOIN $mosques_table m ON m.id = s.mosque_id
         WHERE s.user_id = %d AND s.status = 'active' ORDER BY s.subscribed_at DESC LIMIT 20", $ynj_uid
    ) ) ?: [];

    // Bookings
    $book_table = YNJ_DB::table( 'bookings' );
    $events_table = YNJ_DB::table( 'events' );
    $rooms_table = YNJ_DB::table( 'rooms' );
    $bookings = $wpdb->get_results( $wpdb->prepare(
        "SELECT b.*, m.name AS mosque_name, e.title AS event_title, r.name AS room_name
         FROM $book_table b LEFT JOIN $mosques_table m ON m.id = b.mosque_id
         LEFT JOIN $events_table e ON e.id = b.event_id LEFT JOIN $rooms_table r ON r.id = b.room_id
         WHERE b.user_email = %s ORDER BY b.created_at DESC LIMIT 20", $wp_user->user_email
    ) ) ?: [];

    // Businesses & Services
    $biz_table = YNJ_DB::table( 'businesses' );
    $businesses = $wpdb->get_results( $wpdb->prepare(
        "SELECT b.*, m.name AS mosque_name, m.slug AS mosque_slug FROM $biz_table b
         LEFT JOIN $mosques_table m ON m.id = b.mosque_id
         WHERE b.email = %s AND b.status IN ('active','pending') ORDER BY b.created_at DESC LIMIT 20", $wp_user->user_email
    ) ) ?: [];
    $svc_table = YNJ_DB::table( 'services' );
    $services = $wpdb->get_results( $wpdb->prepare(
        "SELECT s.*, m.name AS mosque_name, m.slug AS mosque_slug FROM $svc_table s
         LEFT JOIN $mosques_table m ON m.id = s.mosque_id
         WHERE s.email = %s AND s.status IN ('active','pending') ORDER BY s.created_at DESC LIMIT 20", $wp_user->user_email
    ) ) ?: [];

    // Points
    $pts_table = YNJ_DB::table( 'points' );
    $points_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points), 0) FROM $pts_table WHERE user_id = %d", $ynj_uid ) );
    $points_recent = $wpdb->get_results( $wpdb->prepare(
        "SELECT action, points, description, created_at FROM $pts_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 5", $ynj_uid
    ) ) ?: [];

    // Favourite mosque
    if ( $fav_mosque_id ) {
        $fav_mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, slug, city FROM $mosques_table WHERE id = %d", $fav_mosque_id
        ) );
    }
}

// ── Ibadah data (server-side for instant render) ──
$ibadah_today    = null;
$ibadah_streak   = 0;
$fajr_streak     = 0;
$jumuah_streak   = 0;
$ibadah_week     = [ 'prayers' => 0, 'pages' => 0, 'points' => 0, 'days' => 0 ];
$user_badges     = [];
$all_badge_defs  = function_exists( 'ynj_get_badge_definitions' ) ? ynj_get_badge_definitions() : [];
$personal_impact = [ 'my_points' => 0, 'total_points' => 0, 'percentage' => 0 ];
$league_data     = null;
$fajr_count      = 0;
$congregation    = null;
$h2h             = null;
$my_duas         = [];
$heatmap_data    = [];
$badge_stats     = [];
$seven_day_log   = [];
$masjid_dhikr_total = 0; // Total dhikr/remembrances for this masjid (all time)
$masjid_dhikr_today = 0; // How many people said dhikr today

if ( $ynj_uid && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $ib_table = YNJ_DB::table( 'ibadah_logs' );
    $today = date( 'Y-m-d' );

    // Today's ibadah
    $ibadah_today = $wpdb->get_row( $wpdb->prepare(
        "SELECT fajr, dhuhr, asr, maghrib, isha, quran_pages, dhikr, fasting, charity, good_deed, prayed_at_mosque, points_earned
         FROM $ib_table WHERE user_id = %d AND log_date = %s", $ynj_uid, $today
    ) );

    // General streak (consecutive days with any prayer)
    $streak_dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT log_date FROM $ib_table WHERE user_id = %d AND (fajr=1 OR dhuhr=1 OR asr=1 OR maghrib=1 OR isha=1) ORDER BY log_date DESC LIMIT 120", $ynj_uid
    ) );
    $ibadah_streak = 0;
    $expected = $today;
    foreach ( $streak_dates as $d ) {
        if ( $d === $expected ) { $ibadah_streak++; $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) ); }
        elseif ( $ibadah_streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) { $ibadah_streak = 1; $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) ); }
        else break;
    }

    // Fajr streak (consecutive days with Fajr specifically)
    $fajr_dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT log_date FROM $ib_table WHERE user_id = %d AND fajr = 1 ORDER BY log_date DESC LIMIT 120", $ynj_uid
    ) );
    $fajr_streak = 0;
    $expected = $today;
    foreach ( $fajr_dates as $d ) {
        if ( $d === $expected ) { $fajr_streak++; $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) ); }
        elseif ( $fajr_streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) { $fajr_streak = 1; $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) ); }
        else break;
    }

    // Jumu'ah streak (consecutive Fridays prayed at mosque)
    $friday_dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT log_date FROM $ib_table WHERE user_id = %d AND prayed_at_mosque = 1 AND DAYOFWEEK(log_date) = 6 ORDER BY log_date DESC LIMIT 52", $ynj_uid
    ) );
    $jumuah_streak = 0;
    // Find the most recent Friday
    $last_friday = date( 'N' ) >= 5 ? date( 'Y-m-d', strtotime( 'last friday' ) ) : date( 'Y-m-d', strtotime( 'last friday' ) );
    if ( date( 'N' ) == 5 ) $last_friday = $today; // Today is Friday
    $expected_fri = $last_friday;
    foreach ( $friday_dates as $fd ) {
        if ( $fd === $expected_fri ) { $jumuah_streak++; $expected_fri = date( 'Y-m-d', strtotime( "$expected_fri -7 days" ) ); }
        elseif ( $jumuah_streak === 0 ) continue;
        else break;
    }

    // Week stats
    $week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
    $week_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers, COALESCE(SUM(quran_pages),0) AS pages,
                COALESCE(SUM(points_earned),0) AS points, COUNT(*) AS days_logged
         FROM $ib_table WHERE user_id = %d AND log_date >= %s", $ynj_uid, $week_start
    ) );
    if ( $week_row ) {
        $ibadah_week = [ 'prayers' => (int) $week_row->prayers, 'pages' => (int) $week_row->pages,
                         'points' => (int) $week_row->points, 'days' => (int) $week_row->days_logged ];
    }

    // 7-day log for streak grid (Mon-Sun of this week)
    $seven_day_log = $wpdb->get_results( $wpdb->prepare(
        "SELECT log_date, (fajr+dhuhr+asr+maghrib+isha) AS prayers, points_earned FROM $ib_table
         WHERE user_id = %d AND log_date >= %s ORDER BY log_date ASC", $ynj_uid, $week_start
    ) ) ?: [];
    $seven_day_map = [];
    foreach ( $seven_day_log as $sl ) $seven_day_map[ $sl->log_date ] = $sl;

    // Heatmap (last 35 days for 5-week calendar)
    $heatmap_since = date( 'Y-m-d', strtotime( '-34 days' ) );
    $heatmap_data = $wpdb->get_results( $wpdb->prepare(
        "SELECT log_date, points_earned FROM $ib_table WHERE user_id = %d AND log_date >= %s ORDER BY log_date ASC", $ynj_uid, $heatmap_since
    ) ) ?: [];
    $heatmap_map = [];
    $max_heatmap_pts = 1;
    foreach ( $heatmap_data as $hd ) {
        $heatmap_map[ $hd->log_date ] = (int) $hd->points_earned;
        if ( (int) $hd->points_earned > $max_heatmap_pts ) $max_heatmap_pts = (int) $hd->points_earned;
    }

    // Badges
    if ( function_exists( 'ynj_get_user_badges' ) ) $user_badges = ynj_get_user_badges( $ynj_uid );
    // Check for new badges (also awards them)
    if ( function_exists( 'ynj_check_badges' ) && $fav_mosque_id ) $badge_stats = ynj_check_badges( $ynj_uid, $fav_mosque_id );

    // Badge stats for progress (total prayer count, quran pages, etc.)
    $ibadah_totals = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers, COALESCE(SUM(quran_pages),0) AS quran,
                COALESCE(SUM(dhikr),0) AS dhikr_days, COALESCE(SUM(fasting),0) AS fasting_days,
                COALESCE(SUM(charity),0) AS charity_days,
                COUNT(DISTINCT CASE WHEN good_deed != '' THEN log_date END) AS good_deeds,
                COUNT(DISTINCT CASE WHEN fajr+dhuhr+asr+maghrib+isha = 5 THEN log_date END) AS all_five
         FROM $ib_table WHERE user_id = %d", $ynj_uid
    ) );
    $checkins_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . YNJ_DB::table( 'points' ) . " WHERE user_id = %d AND action = 'check_in'", $ynj_uid
    ) );
    $badge_progress = [
        'prayers' => (int) ( $ibadah_totals->prayers ?? 0 ), 'quran' => (int) ( $ibadah_totals->quran ?? 0 ),
        'dhikr_days' => (int) ( $ibadah_totals->dhikr_days ?? 0 ), 'fasting_days' => (int) ( $ibadah_totals->fasting_days ?? 0 ),
        'charity_days' => (int) ( $ibadah_totals->charity_days ?? 0 ), 'good_deeds' => (int) ( $ibadah_totals->good_deeds ?? 0 ),
        'all_five' => (int) ( $ibadah_totals->all_five ?? 0 ), 'checkins' => $checkins_count, 'streak' => $ibadah_streak,
    ];

    // Personal impact + League data (if mosque set)
    if ( $fav_mosque_id ) {
        if ( function_exists( 'ynj_personal_impact' ) ) $personal_impact = ynj_personal_impact( $ynj_uid, $fav_mosque_id );
        if ( function_exists( 'ynj_get_league_standings' ) ) $league_data = ynj_get_league_standings( $fav_mosque_id, $fav_mosque->city ?? null, 7 );
        if ( function_exists( 'ynj_fajr_counter' ) ) $fajr_count = ynj_fajr_counter( $fav_mosque_id );
        if ( function_exists( 'ynj_get_congregation_points' ) ) $congregation = ynj_get_congregation_points( $fav_mosque_id );
        if ( function_exists( 'ynj_get_h2h_challenge' ) ) $h2h = ynj_get_h2h_challenge( $fav_mosque_id );

        // Masjid-wide dhikr counters
        $masjid_dhikr_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $ib_table WHERE mosque_id = %d AND dhikr = 1", $fav_mosque_id
        ) );
        $masjid_dhikr_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $ib_table WHERE mosque_id = %d AND dhikr = 1 AND log_date = %s", $fav_mosque_id, $today
        ) );
    }

    // My dua requests
    $dua_table = YNJ_DB::table( 'dua_requests' );
    $my_duas = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, request_text, dua_count, status, created_at FROM $dua_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 10", $ynj_uid
    ) ) ?: [];
}

// Derived values
$user_initial  = strtoupper( mb_substr( $wp_user->display_name ?: 'U', 0, 1 ) );
$total_pts     = $ynj_user ? max( $points_total, (int) $ynj_user->total_points ) : $points_total;
$travel_mode   = $ynj_user ? $ynj_user->travel_mode : 'walk';
$travel_mins   = $ynj_user ? (int) $ynj_user->travel_minutes : 0;
$alert_mins    = $ynj_user ? (int) $ynj_user->alert_before_minutes : 20;
$tier_badges   = [ 'supporter' => 'Bronze', 'guardian' => 'Silver', 'champion' => 'Gold', 'platinum' => 'Platinum' ];
$patron_tier   = $patron ? ( $tier_badges[ $patron->tier ] ?? ucfirst( $patron->tier ) ) : '';
$patron_amount = $patron ? number_format( $patron->amount_pence / 100, 0 ) : '';
$earned_keys   = array_column( $user_badges, 'badge_key' );
$today_logged  = (bool) $ibadah_today;
$mosque_name   = $fav_mosque ? $fav_mosque->name : '';

// ── GDPR: Handle account deletion ──
if ( $_SERVER['REQUEST_METHOD'] === 'POST'
     && ( $_POST['action'] ?? '' ) === 'delete_account'
     && wp_verify_nonce( $_POST['_ynj_delete_nonce'] ?? '', 'ynj_delete_account' )
) {
    global $wpdb;
    if ( $ynj_uid && class_exists( 'YNJ_DB' ) ) {
        $wpdb->delete( YNJ_DB::table( 'users' ), [ 'id' => $ynj_uid ] );
        $wpdb->delete( YNJ_DB::table( 'user_subscriptions' ), [ 'user_id' => $ynj_uid ] );
        $wpdb->delete( YNJ_DB::table( 'points' ), [ 'user_id' => $ynj_uid ] );
        $wpdb->delete( YNJ_DB::table( 'notifications' ), [ 'user_id' => $ynj_uid ] );
        $wpdb->delete( YNJ_DB::table( 'ibadah_logs' ), [ 'user_id' => $ynj_uid ] );
        $wpdb->delete( YNJ_DB::table( 'user_badges' ), [ 'user_id' => $ynj_uid ] );
        $patrons_table = YNJ_DB::table( 'patrons' );
        $active_patrons = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, stripe_subscription_id FROM $patrons_table WHERE user_id = %d AND status = 'active'", $wp_uid
        ) );
        if ( $active_patrons ) {
            foreach ( $active_patrons as $ap ) {
                if ( ! empty( $ap->stripe_subscription_id ) ) {
                    try {
                        $stripe_secret = get_option( 'ynj_stripe_secret_key', '' );
                        if ( $stripe_secret ) wp_remote_request( 'https://api.stripe.com/v1/subscriptions/' . $ap->stripe_subscription_id, [
                            'method' => 'DELETE', 'headers' => [ 'Authorization' => 'Bearer ' . $stripe_secret ],
                        ] );
                    } catch ( \Exception $e ) {}
                }
            }
        }
        $wpdb->delete( $patrons_table, [ 'user_id' => $wp_uid ] );
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $wp_uid );
    wp_logout();
    wp_redirect( home_url( '/?account_deleted=1' ) );
    exit;
}
?>

<style>
/* ════════════════════════════════════════════════
   PERSONAL IBADAH HUB — Dopamine-rich, mobile-first
   ════════════════════════════════════════════════ */
.ynj-hub{max-width:600px;margin:0 auto;padding:0 16px 40px;}

/* ── 1. HERO — Identity + Tribe + Streak ── */
.ynj-hub-hero{background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 100%);border-radius:20px;padding:28px 24px 22px;margin-bottom:14px;color:#fff;text-align:center;position:relative;overflow:hidden;}
.ynj-hub-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:120px;height:120px;background:radial-gradient(circle,rgba(0,173,239,.15) 0%,transparent 70%);border-radius:50%;}
.ynj-hub-avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#287e61,#1a5c43);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 6px;border:3px solid rgba(255,255,255,.2);position:relative;}
.ynj-hub-mosque-badge{position:absolute;bottom:-4px;right:-4px;background:#287e61;color:#fff;font-size:9px;padding:2px 6px;border-radius:6px;font-weight:700;border:2px solid #0a1628;white-space:nowrap;max-width:80px;overflow:hidden;text-overflow:ellipsis;}
.ynj-hub-name{font-size:20px;font-weight:700;margin-bottom:2px;}
.ynj-hub-tribe{font-size:12px;color:rgba(255,255,255,.6);margin-bottom:12px;}
.ynj-hub-stats{display:flex;align-items:center;justify-content:center;gap:20px;}
.ynj-hub-streak{display:flex;flex-direction:column;align-items:center;}
.ynj-hub-streak-flame{font-size:32px;line-height:1;animation:ynj-pulse 2s ease-in-out infinite;}
.ynj-hub-streak-count{font-size:20px;font-weight:800;color:#f59e0b;}
.ynj-hub-streak-label{font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.5px;}
.ynj-hub-pts{display:flex;flex-direction:column;align-items:center;}
.ynj-hub-pts-num{font-size:22px;font-weight:800;background:linear-gradient(90deg,#00ADEF,#287e61);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.ynj-hub-pts-label{font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.5px;}
.ynj-hub-impact{font-size:12px;color:rgba(255,255,255,.55);margin-top:10px;font-style:italic;}
@keyframes ynj-pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.1);}}
@keyframes ynj-celebrate-pop{0%{transform:scale(0.3);opacity:0;}50%{transform:scale(1.15);}100%{transform:scale(1);opacity:1;}}
@keyframes ynj-float-up{0%{opacity:1;transform:translateY(0) scale(1);}80%{opacity:1;}100%{opacity:0;transform:translateY(-80px) scale(1.3);}}
@keyframes ynj-glow{0%,100%{box-shadow:0 0 5px rgba(40,126,97,.2);}50%{box-shadow:0 0 25px rgba(40,126,97,.5),0 0 50px rgba(40,126,97,.2);}}
@keyframes ynj-shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-3px);}75%{transform:translateX(3px);}}
@keyframes ynj-confetti-fall{0%{transform:translateY(-10px) rotate(0deg);opacity:1;}100%{transform:translateY(120px) rotate(720deg);opacity:0;}}
@keyframes ynj-counter-bump{0%{transform:scale(1);}30%{transform:scale(1.4);color:#f59e0b;}100%{transform:scale(1);}}

/* ── WELCOME BONUS ── */
.ynj-welcome-bonus{background:linear-gradient(135deg,#287e61,#1a5c43);border-radius:16px;padding:24px;margin-bottom:14px;color:#fff;text-align:center;animation:ynj-fade-in .5s;}

/* ── 2. SUNNAH REMEMBRANCE CARD ── */
.ynj-dhikr-card{background:linear-gradient(135deg,#fefce8,#fef9c3);border:1px solid #fde68a;border-radius:16px;padding:20px;margin-bottom:14px;text-align:center;}
.ynj-dhikr-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#92400e;margin-bottom:14px;}
.ynj-dhikr-arabic{font-size:22px;line-height:1.8;color:#1a1a1a;margin-bottom:10px;font-family:'Amiri','Traditional Arabic',serif;}
.ynj-dhikr-english{font-size:14px;color:#4a3728;line-height:1.5;margin-bottom:12px;font-style:italic;}
.ynj-dhikr-reward{font-size:12px;color:#78350f;background:rgba(120,53,15,.06);border-radius:8px;padding:8px 12px;margin-bottom:8px;line-height:1.4;}
.ynj-dhikr-source{font-size:10px;color:#92400e;opacity:.6;margin-bottom:14px;}
.ynj-dhikr-ameen{display:block;width:100%;padding:16px;border:none;border-radius:14px;background:linear-gradient(135deg,#287e61,#1a5c43);color:#fff;font-size:18px;font-weight:800;cursor:pointer;font-family:inherit;transition:all .2s;box-shadow:0 4px 16px rgba(40,126,97,.3);position:relative;overflow:hidden;}
.ynj-dhikr-ameen:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(40,126,97,.4);}
.ynj-dhikr-ameen:active{transform:scale(.97);}
.ynj-dhikr-ameen-pts{display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,.7);margin-top:2px;}
.ynj-dhikr-done{display:flex;flex-direction:column;align-items:center;gap:4px;padding:16px;background:#f0fdf4;border-radius:14px;font-size:14px;font-weight:600;color:#166534;}
.ynj-dhikr-masjid{font-size:11px;color:#92400e;margin-top:10px;opacity:.7;}
/* ── Masjid Dhikr Counter ── */
.ynj-dhikr-counter{display:flex;align-items:center;justify-content:center;gap:16px;padding:12px 16px;background:linear-gradient(135deg,rgba(40,126,97,.08),rgba(40,126,97,.04));border-radius:12px;margin-bottom:14px;}
.ynj-dhikr-counter-stat{text-align:center;}
.ynj-dhikr-counter-num{font-size:22px;font-weight:900;color:#287e61;}
.ynj-dhikr-counter-label{font-size:9px;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
.ynj-dhikr-counter-sep{width:1px;height:28px;background:#e5e7eb;}
/* ── Invite/Share Section ── */
.ynj-invite{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:16px;padding:18px;margin-bottom:14px;text-align:center;}
.ynj-invite-title{font-size:15px;font-weight:800;color:#166534;margin-bottom:4px;}
.ynj-invite-sub{font-size:12px;color:#15803d;margin-bottom:14px;line-height:1.4;}
.ynj-invite-btns{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;}
.ynj-invite-btn{display:inline-flex;align-items:center;gap:6px;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;text-decoration:none;cursor:pointer;border:none;font-family:inherit;transition:all .2s;min-height:48px;}
.ynj-invite-btn:active{transform:scale(.95);}
.ynj-invite-btn--wa{background:#25D366;color:#fff;box-shadow:0 4px 16px rgba(37,211,102,.3);}
.ynj-invite-btn--wa:hover{box-shadow:0 6px 20px rgba(37,211,102,.4);transform:translateY(-1px);}
.ynj-invite-btn--copy{background:#0a1628;color:#fff;box-shadow:0 4px 16px rgba(10,22,40,.3);}
.ynj-invite-btn--copy:hover{box-shadow:0 6px 20px rgba(10,22,40,.4);transform:translateY(-1px);}
.ynj-invite-btn--sms{background:#00ADEF;color:#fff;box-shadow:0 4px 16px rgba(0,173,239,.3);}
.ynj-invite-btn--sms:hover{box-shadow:0 6px 20px rgba(0,173,239,.4);transform:translateY(-1px);}
.ynj-invite-impact{margin-top:12px;font-size:11px;color:#15803d;font-style:italic;}
/* Legendary tier — gold border, glow, special styling */
.ynj-dhikr-card--legendary{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:2px solid #f59e0b;box-shadow:0 0 20px rgba(245,158,11,.15);}
.ynj-dhikr-card--legendary .ynj-dhikr-label{color:#d97706;font-size:12px;letter-spacing:1.5px;}
.ynj-dhikr-card--legendary .ynj-dhikr-arabic{font-size:24px;}
.ynj-dhikr-ameen--legendary{background:linear-gradient(135deg,#d97706,#b45309);box-shadow:0 4px 20px rgba(217,119,6,.4);font-size:20px;padding:18px;}
.ynj-dhikr-ameen--legendary:hover{box-shadow:0 6px 28px rgba(217,119,6,.5);}

/* ── DAILY SHUKR ── */
.ynj-shukr-card{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #93c5fd;border-radius:16px;padding:16px;margin-bottom:14px;}
.ynj-shukr-btn{display:block;width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#1e40af,#1e3a8a);color:#fff;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit;transition:all .2s;box-shadow:0 4px 16px rgba(30,64,175,.3);}
.ynj-shukr-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(30,64,175,.4);}
.ynj-shukr-btn:active{transform:scale(.97);}

/* (old prayer tracker CSS removed — replaced by dhikr/shukr system) */

/* ── 3. TRIBAL FEEDBACK LOOP ── */
.ynj-tribal{display:block;text-decoration:none;background:linear-gradient(135deg,#78350f,#92400e);border-radius:14px;padding:14px 16px;margin-bottom:14px;color:#fff;}
.ynj-tribal-top{display:flex;align-items:center;justify-content:space-between;}
.ynj-tribal-left{display:flex;align-items:center;gap:10px;}
.ynj-tribal-tier{font-size:24px;}
.ynj-tribal-name{font-size:13px;font-weight:800;}
.ynj-tribal-league{font-size:11px;color:rgba(255,255,255,.6);}
.ynj-tribal-rank{background:rgba(255,255,255,.15);padding:6px 10px;border-radius:8px;text-align:center;}
.ynj-tribal-rank strong{font-size:16px;display:block;}
.ynj-tribal-rank span{font-size:9px;opacity:.6;}
.ynj-tribal-msg{margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.1);font-size:12px;color:rgba(255,255,255,.8);line-height:1.4;}
.ynj-tribal-fajr{margin-top:6px;font-size:11px;color:rgba(255,255,255,.5);}
.ynj-tribal-progress{margin-top:8px;background:rgba(255,255,255,.1);height:4px;border-radius:2px;overflow:hidden;}
.ynj-tribal-progress-fill{height:100%;background:linear-gradient(90deg,#f59e0b,#fbbf24);border-radius:2px;transition:width .5s;}

/* ── 4. STREAK DASHBOARD ── */
.ynj-streaks{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:16px;border:1px solid rgba(255,255,255,.6);padding:16px;margin-bottom:14px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.ynj-streaks h3{font-size:15px;font-weight:800;color:#0a1628;margin:0 0 12px;}
.ynj-streak-warning{background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:#92400e;font-weight:600;display:flex;align-items:center;gap:8px;}
.ynj-7day{display:flex;gap:6px;justify-content:space-between;margin-bottom:14px;}
.ynj-7day-cell{flex:1;text-align:center;}
.ynj-7day-dot{width:32px;height:32px;border-radius:50%;border:2px solid #e5e7eb;margin:0 auto 4px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#6b8fa3;transition:all .3s;}
.ynj-7day-dot--filled{background:#287e61;border-color:#287e61;color:#fff;}
.ynj-7day-dot--today{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.2);}
.ynj-7day-label{font-size:10px;color:#6b8fa3;font-weight:600;}
.ynj-streak-cards{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px;}
.ynj-streak-card{background:#f9fafb;border-radius:10px;padding:10px 8px;text-align:center;border:1px solid #e5e7eb;}
.ynj-streak-card-icon{font-size:20px;margin-bottom:2px;}
.ynj-streak-card-num{font-size:20px;font-weight:800;color:#0a1628;}
.ynj-streak-card-label{font-size:9px;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
.ynj-heatmap{margin-top:4px;}
.ynj-heatmap-title{font-size:11px;font-weight:700;color:#6b8fa3;margin-bottom:6px;}
.ynj-heatmap-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;}
.ynj-heatmap-cell{aspect-ratio:1;border-radius:3px;background:#f0f0f0;position:relative;}
.ynj-heatmap-cell--l1{background:rgba(40,126,97,.15);}
.ynj-heatmap-cell--l2{background:rgba(40,126,97,.35);}
.ynj-heatmap-cell--l3{background:rgba(40,126,97,.55);}
.ynj-heatmap-cell--l4{background:rgba(40,126,97,.8);}
.ynj-heatmap-cell--today{box-shadow:inset 0 0 0 1px #f59e0b;}

/* ── 5. BADGES ── */
.ynj-badges{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:16px;border:1px solid rgba(255,255,255,.6);padding:16px;margin-bottom:14px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.ynj-badges h3{font-size:15px;font-weight:800;color:#0a1628;margin:0 0 4px;}
.ynj-badges-count{font-size:12px;color:#6b8fa3;margin-bottom:12px;}
.ynj-badge-cat{font-size:11px;font-weight:700;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;margin:10px 0 6px;padding-top:8px;border-top:1px solid #f0f0f0;}
.ynj-badge-cat:first-of-type{border-top:none;margin-top:0;padding-top:0;}
.ynj-badge-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.ynj-badge-card{text-align:center;padding:10px 6px;border-radius:10px;background:#f9fafb;border:1px solid #e5e7eb;transition:all .3s;}
.ynj-badge-card--earned{background:#f0fdf4;border-color:#86efac;box-shadow:0 0 8px rgba(40,126,97,.1);}
.ynj-badge-card--locked{opacity:.4;filter:grayscale(.8);}
.ynj-badge-icon{font-size:24px;margin-bottom:4px;}
.ynj-badge-name{font-size:11px;font-weight:700;color:#0a1628;margin-bottom:2px;}
.ynj-badge-desc{font-size:9px;color:#6b8fa3;line-height:1.3;}
.ynj-badge-date{font-size:9px;color:#287e61;font-weight:600;margin-top:2px;}
.ynj-badge-progress{height:3px;background:#e5e7eb;border-radius:2px;margin-top:4px;overflow:hidden;}
.ynj-badge-progress-fill{height:100%;background:#287e61;border-radius:2px;transition:width .5s;}
.ynj-badge-next{font-size:9px;color:#92400e;font-weight:600;margin-top:2px;}

/* ── 6. MY DUAS ── */
.ynj-duas{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:16px;border:1px solid rgba(255,255,255,.6);padding:16px;margin-bottom:14px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.ynj-duas h3{font-size:15px;font-weight:800;color:#0a1628;margin:0 0 12px;display:flex;align-items:center;gap:6px;}
.ynj-dua-item{padding:10px 0;border-bottom:1px solid #f0f0f0;}
.ynj-dua-item:last-child{border-bottom:none;}
.ynj-dua-text{font-size:13px;color:#0a1628;margin-bottom:4px;line-height:1.4;}
.ynj-dua-meta{display:flex;align-items:center;gap:8px;font-size:11px;color:#6b8fa3;}
.ynj-dua-count{color:#287e61;font-weight:700;}

/* ── 7. ACCOUNT SECTIONS ── */
.ynj-acct-section{margin-bottom:14px;}
.ynj-acct-section summary{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:16px;border:1px solid rgba(255,255,255,.6);padding:14px 16px;font-size:14px;font-weight:700;color:#0a1628;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;}
.ynj-acct-section summary::after{content:'\25BC';font-size:10px;color:#6b8fa3;transition:transform .2s;}
.ynj-acct-section[open] summary::after{transform:rotate(180deg);}
.ynj-acct-section summary::-webkit-details-marker{display:none;}
.ynj-acct-inner{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:0 0 16px 16px;border:1px solid rgba(255,255,255,.6);border-top:none;padding:16px;margin-top:-14px;}

/* ── Reused from old profile ── */
.ynj-sub-item{padding:12px 0;border-bottom:1px solid #f0f0f0;}.ynj-sub-item:last-child{border-bottom:none;}
.ynj-sub-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.ynj-sub-name{font-size:14px;font-weight:700;}.ynj-sub-city{font-size:12px;color:#6b8fa3;}
.ynj-sub-unsub{font-size:11px;color:#dc2626;background:none;border:1px solid #fecaca;padding:4px 10px;border-radius:6px;cursor:pointer;}
.ynj-sub-toggles{display:flex;gap:12px;flex-wrap:wrap;}
.ynj-sub-toggle{display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;}
.ynj-sub-toggle input{width:14px;height:14px;accent-color:#00ADEF;}
.ynj-pref-form .ynj-field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
.ynj-pref-form .ynj-field{display:flex;flex-direction:column;gap:4px;}
.ynj-pref-form label{font-size:12px;font-weight:600;color:#6b8fa3;}
.ynj-pref-form select,.ynj-pref-form input{padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.1);font-size:14px;font-family:inherit;background:#fff;outline:none;}
.ynj-btn-save{display:block;width:100%;padding:12px;border-radius:12px;font-size:14px;font-weight:600;border:1px solid #00ADEF;background:transparent;color:#00ADEF;cursor:pointer;text-align:center;transition:all .15s;}
.ynj-btn-save:hover{background:#00ADEF;color:#fff;}
.ynj-btn-logout{display:block;width:100%;padding:12px;border-radius:12px;font-size:14px;font-weight:600;border:1px solid #dc2626;background:transparent;color:#dc2626;cursor:pointer;text-align:center;margin-top:20px;transition:all .15s;}
.ynj-btn-logout:hover{background:#dc2626;color:#fff;}
.ynj-empty{text-align:center;padding:24px 16px;color:#6b8fa3;font-size:13px;}
.ynj-book-item{padding:12px 0;border-bottom:1px solid rgba(0,0,0,.04);}.ynj-book-item:last-child{border-bottom:none;}
.ynj-book-head{display:flex;align-items:center;gap:8px;margin-bottom:4px;}
.ynj-book-type{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.ynj-book-type--event{background:#ede9fe;color:#7c3aed;}.ynj-book-type--room{background:#e0f2fe;color:#0284c7;}
.ynj-book-title{font-size:14px;font-weight:600;}.ynj-book-meta{font-size:12px;color:#6b8fa3;margin-bottom:4px;}
.ynj-badge-status{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;}
.ynj-badge--confirmed{background:#dcfce7;color:#166534;}
.ynj-badge--pending,.ynj-badge--pending_payment{background:#fef3c7;color:#92400e;}
.ynj-badge--cancelled,.ynj-badge--rejected{background:#fee2e2;color:#991b1b;}

@media(max-width:480px){
    .ynj-hub{padding:0 12px 32px;}
    .ynj-hub-hero{padding:24px 16px 18px;}
    .ynj-streak-cards{grid-template-columns:1fr 1fr 1fr;gap:6px;}
    .ynj-badge-grid{grid-template-columns:repeat(3,1fr);gap:6px;}
    .ynj-pref-form .ynj-field-row{grid-template-columns:1fr;}
}
@keyframes ynj-fade-in{from{opacity:0;transform:translateX(-50%) translateY(-10px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}
</style>

<main class="ynj-main">
<div class="ynj-hub">

<!-- ═══════════════════════════════════════════
     1. HERO — Identity + Tribe + Streak
     ═══════════════════════════════════════════ -->
<div class="ynj-hub-hero">
    <div class="ynj-hub-avatar">
        <?php echo esc_html( $user_initial ); ?>
        <?php if ( $fav_mosque ) : ?>
            <span class="ynj-hub-mosque-badge"><?php echo esc_html( mb_strimwidth( $fav_mosque->name, 0, 12, '..' ) ); ?></span>
        <?php endif; ?>
    </div>
    <div class="ynj-hub-name"><?php echo esc_html( $wp_user->display_name ); ?></div>
    <?php if ( $fav_mosque ) : ?>
        <div class="ynj-hub-tribe"><?php printf( esc_html__( 'Member of %s', 'yourjannah' ), esc_html( $fav_mosque->name ) ); ?></div>
    <?php else : ?>
        <div class="ynj-hub-tribe"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#00ADEF;"><?php esc_html_e( 'Select your masjid', 'yourjannah' ); ?></a></div>
    <?php endif; ?>

    <div class="ynj-hub-stats">
        <div class="ynj-hub-streak">
            <span class="ynj-hub-streak-flame"><?php echo $ibadah_streak > 0 ? '&#x1F525;' : '&#x1F9CA;'; ?></span>
            <span class="ynj-hub-streak-count"><?php echo (int) $ibadah_streak; ?></span>
            <span class="ynj-hub-streak-label"><?php esc_html_e( 'day streak', 'yourjannah' ); ?></span>
        </div>
        <div style="width:1px;height:36px;background:rgba(255,255,255,.15);"></div>
        <div class="ynj-hub-pts">
            <span class="ynj-hub-pts-num" id="hero-pts"><?php echo esc_html( number_format( $total_pts ) ); ?></span>
            <span class="ynj-hub-pts-label"><?php esc_html_e( 'total points', 'yourjannah' ); ?></span>
        </div>
    </div>

    <?php if ( $fav_mosque && $personal_impact['percentage'] > 0 ) : ?>
        <div class="ynj-hub-impact">
            <?php printf(
                esc_html__( 'You contributed %s%% of %s\'s ibadah this week', 'yourjannah' ),
                esc_html( number_format( $personal_impact['percentage'], 1 ) ),
                esc_html( $fav_mosque->name )
            ); ?>
        </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     2. WELCOME BONUS (first-time only)
     ═══════════════════════════════════════════ -->
<?php
// Award welcome bonus on first visit
$welcome_awarded = 0;
if ( $ynj_uid && class_exists( 'YNJ_API_Points' ) ) {
    $welcome_awarded = YNJ_API_Points::award_welcome_bonus( $ynj_uid, $fav_mosque_id );
    if ( $welcome_awarded > 0 ) $total_pts += $welcome_awarded;
}
?>
<?php if ( $welcome_awarded > 0 ) : ?>
<div class="ynj-welcome-bonus" id="welcome-bonus">
    <div style="font-size:36px;margin-bottom:8px;">&#x1F38A;</div>
    <div style="font-size:18px;font-weight:800;margin-bottom:4px;"><?php esc_html_e( 'Welcome to YourJannah!', 'yourjannah' ); ?></div>
    <div style="font-size:24px;font-weight:800;color:#f59e0b;margin-bottom:6px;">+50 <?php esc_html_e( 'points', 'yourjannah' ); ?></div>
    <div style="font-size:20px;margin-bottom:4px;" dir="rtl">&#x644;&#x627; &#x625;&#x650;&#x644;&#x640;&#x647;&#x64E; &#x625;&#x650;&#x644;&#x651;&#x627; &#x627;&#x644;&#x644;&#x651;&#x647;&#x64F;</div>
    <div style="font-size:13px;color:rgba(255,255,255,.7);margin-bottom:10px;"><?php esc_html_e( 'There is no god but Allah', 'yourjannah' ); ?></div>
    <div style="font-size:11px;color:rgba(255,255,255,.5);"><?php esc_html_e( 'Your journey of remembrance begins here', 'yourjannah' ); ?></div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     2. TODAY'S SUNNAH REMEMBRANCE
     ═══════════════════════════════════════════ -->
<?php
// Get today's rotating dhikr
$weekly_adhkar = class_exists( 'YNJ_API_Points' ) ? YNJ_API_Points::get_weekly_adhkar() : [];
$dhikr_idx = (int) date( 'z' ) % max( 1, count( $weekly_adhkar ) ); // Daily rotation
$today_dhikr = ! empty( $weekly_adhkar ) ? $weekly_adhkar[ $dhikr_idx ] : null;
$dhikr_done = false;
if ( $ynj_uid ) {
    $dhikr_done = (bool) get_transient( 'ynj_dhikr_' . $ynj_uid . '_' . date( 'Y-m-d' ) );
}
?>
<?php if ( $today_dhikr ) :
    $is_legendary = ( $today_dhikr['tier'] ?? '' ) === 'legendary';
    $tier_label = $is_legendary ? __( 'Legendary Remembrance', 'yourjannah' ) : __( "Today's Remembrance", 'yourjannah' );
?>
<div class="ynj-dhikr-card<?php echo $is_legendary ? ' ynj-dhikr-card--legendary' : ''; ?>" id="ibadah">
    <div class="ynj-dhikr-label"><?php echo esc_html( $tier_label ); ?></div>

    <div class="ynj-dhikr-arabic" dir="rtl"><?php echo esc_html( $today_dhikr['arabic'] ); ?></div>
    <div class="ynj-dhikr-english"><?php echo esc_html( $today_dhikr['english'] ); ?></div>

    <div class="ynj-dhikr-reward">
        <span>&#x2728;</span> <?php echo esc_html( $today_dhikr['reward'] ); ?>
    </div>
    <div class="ynj-dhikr-source"><?php echo esc_html( $today_dhikr['source'] ); ?></div>

    <?php if ( $dhikr_done ) : ?>
        <div class="ynj-dhikr-done">
            <span style="font-size:28px;">&#x2705;</span>
            <span style="font-size:16px;"><?php esc_html_e( 'You said it today! May Allah accept.', 'yourjannah' ); ?></span>
            <span style="color:#f59e0b;font-weight:900;font-size:22px;">+<?php echo (int) $today_dhikr['points']; ?> <?php esc_html_e( 'points', 'yourjannah' ); ?></span>
            <?php if ( $fav_mosque ) : ?>
                <span style="font-size:11px;color:#287e61;"><?php printf( esc_html__( '%s elevated', 'yourjannah' ), esc_html( $fav_mosque->name ) ); ?> &#x1F54C;&#x2728;</span>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <button type="button" class="ynj-dhikr-ameen<?php echo $is_legendary ? ' ynj-dhikr-ameen--legendary' : ''; ?>" id="dhikr-ameen-btn" onclick="ynjSayAmeen(this)">
            <?php echo esc_html( $today_dhikr['action_text'] ); ?>
            <span class="ynj-dhikr-ameen-pts">+<?php echo (int) $today_dhikr['points']; ?> <?php esc_html_e( 'points', 'yourjannah' ); ?> &middot; <?php esc_html_e( 'Elevate your masjid', 'yourjannah' ); ?></span>
        </button>
        <?php if ( $fav_mosque ) : ?>
            <div class="ynj-dhikr-masjid">&#x1F54C; <?php printf( esc_html__( 'Your remembrance elevates %s', 'yourjannah' ), esc_html( $fav_mosque->name ) ); ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     2C. MASJID DHIKR COUNTER + INVITE
     ═══════════════════════════════════════════ -->
<?php if ( $fav_mosque ) : ?>
<!-- Masjid-wide remembrance counter -->
<div class="ynj-dhikr-counter">
    <div class="ynj-dhikr-counter-stat">
        <div class="ynj-dhikr-counter-num" id="masjid-dhikr-total"><?php echo number_format( $masjid_dhikr_total ); ?></div>
        <div class="ynj-dhikr-counter-label"><?php esc_html_e( 'Total remembrances', 'yourjannah' ); ?></div>
    </div>
    <div class="ynj-dhikr-counter-sep"></div>
    <div class="ynj-dhikr-counter-stat">
        <div class="ynj-dhikr-counter-num"><?php echo (int) $masjid_dhikr_today; ?></div>
        <div class="ynj-dhikr-counter-label"><?php esc_html_e( 'Today', 'yourjannah' ); ?></div>
    </div>
    <div class="ynj-dhikr-counter-sep"></div>
    <div class="ynj-dhikr-counter-stat">
        <div class="ynj-dhikr-counter-num"><?php echo (int) ( $congregation ? $congregation['active_members'] : 0 ); ?></div>
        <div class="ynj-dhikr-counter-label"><?php esc_html_e( 'Active members', 'yourjannah' ); ?></div>
    </div>
</div>

<!-- Invite / Share — viral dawah loop -->
<?php
$invite_url = home_url( '/mosque/' . $fav_mosque->slug );
$invite_msg = sprintf(
    __( "Assalamu Alaikum! I just said my daily dhikr for %s on YourJannah. Join us and help elevate our masjid's aura! Every La ilaha illallah counts. Join here:", 'yourjannah' ),
    $fav_mosque->name
) . "\n\n" . $invite_url;
$wa_url = 'https://wa.me/?text=' . rawurlencode( $invite_msg );
$sms_url = 'sms:?body=' . rawurlencode( $invite_msg );
?>
<div class="ynj-invite">
    <div class="ynj-invite-title">&#x1F54C; <?php esc_html_e( 'Invite Your Brothers & Sisters', 'yourjannah' ); ?></div>
    <div class="ynj-invite-sub"><?php printf( esc_html__( 'Every person who joins and says La ilaha illallah elevates %s. Share the blessing.', 'yourjannah' ), '<strong>' . esc_html( $fav_mosque->name ) . '</strong>' ); ?></div>

    <div class="ynj-invite-btns">
        <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener" class="ynj-invite-btn ynj-invite-btn--wa">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.604-1.207A11.927 11.927 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818c-2.168 0-4.177-.693-5.82-1.87l-.418-.248-4.33 1.136 1.156-4.222-.273-.434A9.777 9.777 0 012.182 12c0-5.423 4.395-9.818 9.818-9.818S21.818 6.577 21.818 12 17.423 21.818 12 21.818z"/></svg>
            <?php esc_html_e( 'WhatsApp', 'yourjannah' ); ?>
        </a>
        <button type="button" class="ynj-invite-btn ynj-invite-btn--copy" onclick="ynjCopyInvite(this)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
            <?php esc_html_e( 'Copy Link', 'yourjannah' ); ?>
        </button>
        <a href="<?php echo esc_url( $sms_url ); ?>" class="ynj-invite-btn ynj-invite-btn--sms">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            <?php esc_html_e( 'SMS', 'yourjannah' ); ?>
        </a>
    </div>

    <div class="ynj-invite-impact"><?php
        printf(
            esc_html__( 'Imagine %s people saying La ilaha illallah for %s every single day. Be the one who makes it happen.', 'yourjannah' ),
            '100', esc_html( $fav_mosque->name )
        );
    ?></div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     3. TRIBAL FEEDBACK LOOP
     ═══════════════════════════════════════════ -->
<?php if ( $fav_mosque && $league_data ) : ?>
<a href="<?php echo esc_url( home_url( '/mosque/' . $fav_mosque->slug . '#mosque-league-table' ) ); ?>" class="ynj-tribal">
    <div class="ynj-tribal-top">
        <div class="ynj-tribal-left">
            <span class="ynj-tribal-tier"><?php echo $league_data['tier']['icon']; ?></span>
            <div>
                <div class="ynj-tribal-name"><?php echo esc_html( $fav_mosque->name ); ?></div>
                <div class="ynj-tribal-league"><?php echo esc_html( $league_data['tier']['name'] ); ?> <?php esc_html_e( 'League', 'yourjannah' ); ?></div>
            </div>
        </div>
        <?php if ( $league_data['rank'] > 0 ) : ?>
        <div class="ynj-tribal-rank">
            <strong>#<?php echo (int) $league_data['rank']; ?></strong>
            <span><?php esc_html_e( 'rank', 'yourjannah' ); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Dynamic motivational message -->
    <div class="ynj-tribal-msg">
        <?php
        // Build contextual tribal message
        if ( $league_data['rank'] === 1 ) {
            printf( esc_html__( '%s is leading the league! Your ibadah keeps your masjid at the top.', 'yourjannah' ), esc_html( $fav_mosque->name ) );
        } elseif ( $league_data['rank'] > 0 && $league_data['rank'] <= 3 ) {
            printf( esc_html__( '%s is in the top 3! Keep logging to push for #1.', 'yourjannah' ), esc_html( $fav_mosque->name ) );
        } elseif ( $h2h && ! $h2h['winning'] && ! $h2h['tied'] ) {
            printf( esc_html__( '%s is behind %s in this week\'s challenge. Your ibadah can turn it around!', 'yourjannah' ),
                esc_html( $fav_mosque->name ), esc_html( $h2h['opponent'] ) );
        } elseif ( $h2h && $h2h['winning'] ) {
            printf( esc_html__( '%s is beating %s! Keep the momentum going.', 'yourjannah' ),
                esc_html( $fav_mosque->name ), esc_html( $h2h['opponent'] ) );
        } elseif ( $league_data['rank'] > 0 ) {
            // Close to the mosque above
            $rival = '';
            if ( ! empty( $league_data['top_5'] ) && $league_data['rank'] > 1 ) {
                $above_idx = $league_data['rank'] - 2;
                if ( isset( $league_data['top_5'][ $above_idx ] ) ) $rival = $league_data['top_5'][ $above_idx ]->name;
            }
            if ( $rival ) {
                printf( esc_html__( 'Your ibadah helps %s climb the ranks. Push past %s!', 'yourjannah' ),
                    esc_html( $fav_mosque->name ), esc_html( $rival ) );
            } else {
                printf( esc_html__( 'Every prayer you log strengthens %s in the league.', 'yourjannah' ), esc_html( $fav_mosque->name ) );
            }
        } else {
            printf( esc_html__( 'Start logging ibadah to get %s on the leaderboard!', 'yourjannah' ), esc_html( $fav_mosque->name ) );
        }
        ?>
    </div>

    <?php if ( $fajr_count > 0 ) : ?>
        <div class="ynj-tribal-fajr">&#x1F319; <?php printf( esc_html__( '%d people from your masjid prayed Fajr today', 'yourjannah' ), $fajr_count ); ?></div>
    <?php endif; ?>

    <?php if ( $league_data['rank'] > 1 && $league_data['total'] > 0 ) : ?>
        <div class="ynj-tribal-progress">
            <div class="ynj-tribal-progress-fill" style="width:<?php echo min( 100, round( ( $league_data['total'] - $league_data['rank'] + 1 ) / $league_data['total'] * 100 ) ); ?>%"></div>
        </div>
    <?php endif; ?>
</a>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     2B. DAILY SHUKR — Gratitude points
     ═══════════════════════════════════════════ -->
<?php
$shukr_done = $ynj_uid ? (bool) get_transient( 'ynj_shukr_' . $ynj_uid . '_' . date( 'Y-m-d' ) ) : false;
?>
<?php
// Rotating shukr phrases — different each day
$shukr_phrases = [
    [ 'text' => 'Alhamdulillah', 'meaning' => 'All praise is due to Allah', 'verse' => 'Quran 14:7 — "If you are grateful, I will surely increase you"' ],
    [ 'text' => 'SubhanAllah', 'meaning' => 'Glory be to Allah', 'verse' => 'Quran 17:44 — "Everything in the heavens and earth glorifies Him"' ],
    [ 'text' => 'Allahu Akbar', 'meaning' => 'Allah is the Greatest', 'verse' => 'Quran 29:45 — "The remembrance of Allah is greater"' ],
    [ 'text' => 'MashaAllah', 'meaning' => 'As Allah has willed', 'verse' => 'Quran 18:39 — "Say MashaAllah, there is no power except with Allah"' ],
    [ 'text' => 'Alhamdulillah', 'meaning' => 'All praise is due to Allah', 'verse' => 'Quran 1:2 — "All praise is due to Allah, Lord of all the worlds"' ],
    [ 'text' => 'La ilaha illallah', 'meaning' => 'There is no god but Allah', 'verse' => 'The best dhikr is La ilaha illallah — Tirmidhi 3383' ],
    [ 'text' => 'Alhamdulillah', 'meaning' => 'All praise is due to Allah', 'verse' => 'Quran 34:1 — "Praise be to Allah, to whom belongs all that is in the heavens and earth"' ],
];
$shukr_today = $shukr_phrases[ (int) date( 'z' ) % count( $shukr_phrases ) ];
?>
<div class="ynj-shukr-card" id="daily-shukr">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <span style="font-size:28px;">&#x1F64F;</span>
        <div>
            <div style="font-size:14px;font-weight:800;color:#1a3a5c;"><?php esc_html_e( 'Daily Shukr', 'yourjannah' ); ?></div>
            <div style="font-size:11px;color:#6b8fa3;"><?php echo esc_html( $shukr_today['meaning'] ); ?></div>
        </div>
    </div>
    <div style="font-size:12px;color:#4a3728;margin-bottom:12px;font-style:italic;line-height:1.5;background:rgba(30,64,175,.04);padding:8px 12px;border-radius:8px;">
        <?php echo esc_html( $shukr_today['verse'] ); ?>
    </div>
    <?php if ( $shukr_done ) : ?>
        <div style="padding:16px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:12px;text-align:center;animation:ynj-celebrate-pop .5s;">
            <div style="font-size:28px;margin-bottom:4px;">&#x1F64F;</div>
            <div style="font-size:15px;font-weight:800;color:#1e40af;"><?php echo esc_html( $shukr_today['text'] ); ?>!</div>
            <div style="font-size:18px;font-weight:900;color:#f59e0b;margin-top:4px;">+50 <?php esc_html_e( 'points', 'yourjannah' ); ?></div>
            <?php if ( $fav_mosque ) : ?>
                <div style="font-size:11px;color:#287e61;margin-top:4px;"><?php echo esc_html( $fav_mosque->name ); ?> <?php esc_html_e( 'elevated', 'yourjannah' ); ?> &#x2728;</div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <button type="button" class="ynj-shukr-btn" id="shukr-btn" onclick="ynjLogShukr(this)">
            <?php echo esc_html( $shukr_today['text'] ); ?>
            <span style="display:block;font-size:12px;color:rgba(255,255,255,.7);margin-top:2px;">+50 <?php esc_html_e( 'points', 'yourjannah' ); ?> &middot; <?php esc_html_e( "Elevates your masjid's aura", 'yourjannah' ); ?></span>
        </button>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     4. MASJID STREAK — Your community's streak
     ═══════════════════════════════════════════ -->
<?php
// Calculate MASJID streak (consecutive days where at least 1 member logged)
$masjid_streak = 0;
if ( $fav_mosque_id && $ynj_uid && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $_ms_table = YNJ_DB::table( 'ibadah_logs' );
    $_ms_dates = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT log_date FROM $_ms_table WHERE mosque_id = %d AND (fajr=1 OR dhuhr=1 OR asr=1 OR maghrib=1 OR isha=1 OR dhikr=1) ORDER BY log_date DESC LIMIT 120",
        $fav_mosque_id
    ) );
    $expected = date( 'Y-m-d' );
    foreach ( $_ms_dates as $d ) {
        if ( $d === $expected ) { $masjid_streak++; $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) ); }
        elseif ( $masjid_streak === 0 && $d === date( 'Y-m-d', strtotime( '-1 day' ) ) ) { $masjid_streak = 1; $expected = date( 'Y-m-d', strtotime( "$d -1 day" ) ); }
        else break;
    }
}
?>
<div class="ynj-streaks">
    <?php if ( $fav_mosque ) : ?>
        <h3>&#x1F54C; <?php printf( esc_html__( '%s\'s Streak', 'yourjannah' ), esc_html( $fav_mosque->name ) ); ?></h3>
        <p style="font-size:12px;color:#6b8fa3;margin:-8px 0 12px;"><?php esc_html_e( "Every day someone from your masjid remembers Allah, the streak continues. Don't let it break.", 'yourjannah' ); ?></p>
    <?php else : ?>
        <h3>&#x1F525; <?php esc_html_e( 'Your Streak', 'yourjannah' ); ?></h3>
    <?php endif; ?>

    <!-- Loss aversion warning — masjid streak at risk -->
    <?php if ( $fav_mosque && ! $dhikr_done && ! $shukr_done && $masjid_streak >= 3 ) : ?>
        <div class="ynj-streak-warning">
            &#x26A0;&#xFE0F; <?php printf( esc_html__( '%s has a %d-day streak! Say your dhikr to keep it alive.', 'yourjannah' ), esc_html( $fav_mosque->name ), $masjid_streak ); ?>
            <a href="#ibadah" style="color:#92400e;font-weight:800;margin-left:auto;">&#x2191;</a>
        </div>
    <?php endif; ?>

    <!-- 7-day grid (Mon-Sun) — shows points logged each day -->
    <div class="ynj-7day">
        <?php
        $day_labels = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
        $week_start_ts = strtotime( 'Monday this week' );
        for ( $i = 0; $i < 7; $i++ ) :
            $d = date( 'Y-m-d', $week_start_ts + $i * 86400 );
            $is_today = ( $d === date( 'Y-m-d' ) );
            $is_future = ( $d > date( 'Y-m-d' ) );
            $logged = isset( $seven_day_map[ $d ] );
            $pts_day = $logged ? (int) $seven_day_map[ $d ]->points_earned : 0;
            $dot_class = 'ynj-7day-dot';
            if ( $logged && $pts_day > 0 ) $dot_class .= ' ynj-7day-dot--filled';
            if ( $is_today ) $dot_class .= ' ynj-7day-dot--today';
        ?>
            <div class="ynj-7day-cell">
                <div class="<?php echo esc_attr( $dot_class ); ?>">
                    <?php if ( $logged && $pts_day > 0 ) echo '&#x2713;';
                          elseif ( $is_future ) echo '&middot;';
                          else echo '&ndash;'; ?>
                </div>
                <div class="ynj-7day-label"><?php echo esc_html( $day_labels[ $i ] ); ?></div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Streak counters — community focused -->
    <div class="ynj-streak-cards">
        <div class="ynj-streak-card" style="<?php echo $masjid_streak >= 7 ? 'background:#f0fdf4;border-color:#86efac;' : ''; ?>">
            <div class="ynj-streak-card-icon">&#x1F54C;</div>
            <div class="ynj-streak-card-num"><?php echo (int) $masjid_streak; ?></div>
            <div class="ynj-streak-card-label"><?php esc_html_e( 'Masjid', 'yourjannah' ); ?></div>
        </div>
        <div class="ynj-streak-card">
            <div class="ynj-streak-card-icon">&#x1F525;</div>
            <div class="ynj-streak-card-num"><?php echo (int) $ibadah_streak; ?></div>
            <div class="ynj-streak-card-label"><?php esc_html_e( 'You', 'yourjannah' ); ?></div>
        </div>
        <div class="ynj-streak-card">
            <div class="ynj-streak-card-icon">&#x2728;</div>
            <div class="ynj-streak-card-num"><?php echo (int) $ibadah_week['days']; ?>/7</div>
            <div class="ynj-streak-card-label"><?php esc_html_e( 'This week', 'yourjannah' ); ?></div>
        </div>
    </div>

    <!-- Monthly heatmap — your spiritual aura -->
    <div class="ynj-heatmap">
        <div class="ynj-heatmap-title"><?php esc_html_e( 'Your spiritual aura — last 35 days', 'yourjannah' ); ?></div>
        <div class="ynj-heatmap-grid">
            <?php
            for ( $i = 34; $i >= 0; $i-- ) :
                $d = date( 'Y-m-d', strtotime( "-{$i} days" ) );
                $pts = $heatmap_map[ $d ] ?? 0;
                $is_today = ( $d === date( 'Y-m-d' ) );
                $level = '';
                if ( $pts > 0 ) {
                    $ratio = $pts / $max_heatmap_pts;
                    if ( $ratio >= .75 ) $level = 'ynj-heatmap-cell--l4';
                    elseif ( $ratio >= .5 ) $level = 'ynj-heatmap-cell--l3';
                    elseif ( $ratio >= .25 ) $level = 'ynj-heatmap-cell--l2';
                    else $level = 'ynj-heatmap-cell--l1';
                }
                if ( $is_today ) $level .= ' ynj-heatmap-cell--today';
            ?>
                <div class="ynj-heatmap-cell <?php echo esc_attr( $level ); ?>" title="<?php echo esc_attr( $d . ': ' . $pts . ' pts' ); ?>"></div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     5. BADGES & PROGRESS
     ═══════════════════════════════════════════ -->
<div class="ynj-badges">
    <h3>&#x1F3C5; <?php esc_html_e( 'Badges', 'yourjannah' ); ?></h3>
    <div class="ynj-badges-count"><?php printf( esc_html__( '%d of %d earned', 'yourjannah' ), count( $user_badges ), count( $all_badge_defs ) ); ?></div>

    <?php
    // Group badges by category
    $badge_cats = [
        'Prayer'    => [ 'first_prayer', 'all_five', 'prayer_week', 'prayer_month', 'prayer_100', 'prayer_500' ],
        'Quran'     => [ 'quran_first', 'quran_juz', 'quran_100' ],
        'Habits'    => [ 'dhikr_7', 'fasting_3', 'charity_5', 'good_deeds_10' ],
        'Community' => [ 'checkin_first', 'checkin_10', 'checkin_50' ],
    ];
    $badge_defs_map = [];
    foreach ( $all_badge_defs as $bd ) $badge_defs_map[ $bd['key'] ] = $bd;

    // Map check fields to badge_progress keys for progress bars
    $check_field_map = [
        'prayers' => 'prayers', 'all_five' => 'all_five', 'streak' => 'streak',
        'quran' => 'quran', 'dhikr_days' => 'dhikr_days', 'fasting_days' => 'fasting_days',
        'charity_days' => 'charity_days', 'good_deeds' => 'good_deeds', 'checkins' => 'checkins',
    ];

    foreach ( $badge_cats as $cat_name => $cat_keys ) : ?>
        <div class="ynj-badge-cat"><?php echo esc_html( $cat_name ); ?></div>
        <div class="ynj-badge-grid">
        <?php foreach ( $cat_keys as $bk ) :
            $bd = $badge_defs_map[ $bk ] ?? null;
            if ( ! $bd ) continue;
            $earned = in_array( $bk, $earned_keys, true );
            $earned_date = '';
            foreach ( $user_badges as $ub ) {
                if ( $ub->badge_key === $bk ) { $earned_date = date_i18n( 'j M', strtotime( $ub->earned_at ) ); break; }
            }

            // Calculate progress for locked badges
            $progress_pct = 0;
            $remaining = '';
            if ( ! $earned && preg_match( '/^(\w+)\s*>=\s*(\d+)$/', $bd['check'], $m ) ) {
                $field = $m[1];
                $threshold = (int) $m[2];
                $current = $badge_progress[ $field ] ?? 0;
                $progress_pct = min( 100, round( $current / max( 1, $threshold ) * 100 ) );
                $left = $threshold - $current;
                if ( $left > 0 ) $remaining = $left . ' more';
            }
        ?>
            <div class="ynj-badge-card <?php echo $earned ? 'ynj-badge-card--earned' : 'ynj-badge-card--locked'; ?>">
                <div class="ynj-badge-icon"><?php echo $bd['icon']; ?></div>
                <div class="ynj-badge-name"><?php echo esc_html( $bd['name'] ); ?></div>
                <div class="ynj-badge-desc"><?php echo esc_html( $bd['desc'] ); ?></div>
                <?php if ( $earned ) : ?>
                    <div class="ynj-badge-date"><?php echo esc_html( $earned_date ); ?></div>
                <?php else : ?>
                    <div class="ynj-badge-progress"><div class="ynj-badge-progress-fill" style="width:<?php echo $progress_pct; ?>%"></div></div>
                    <?php if ( $remaining ) : ?><div class="ynj-badge-next"><?php echo esc_html( $remaining ); ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════
     6. MY DUA REQUESTS
     ═══════════════════════════════════════════ -->
<div class="ynj-duas">
    <h3>&#x1F932; <?php esc_html_e( 'My Dua Requests', 'yourjannah' ); ?></h3>
    <?php if ( $fav_mosque ) : ?>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $fav_mosque->slug . '#dua-wall' ) ); ?>" style="display:inline-block;padding:8px 16px;background:#287e61;color:#fff;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;margin-bottom:12px;"><?php esc_html_e( 'New Dua Request', 'yourjannah' ); ?></a>
    <?php endif; ?>

    <?php if ( empty( $my_duas ) ) : ?>
        <p class="ynj-empty"><?php esc_html_e( 'No dua requests yet. Visit your mosque page to submit one.', 'yourjannah' ); ?></p>
    <?php else : ?>
        <?php foreach ( $my_duas as $dua ) : ?>
            <div class="ynj-dua-item">
                <div class="ynj-dua-text"><?php echo esc_html( $dua->request_text ); ?></div>
                <div class="ynj-dua-meta">
                    <span class="ynj-dua-count"><?php printf( esc_html__( '%d people made dua', 'yourjannah' ), (int) $dua->dua_count ); ?></span>
                    <span>&middot;</span>
                    <span><?php echo esc_html( human_time_diff( strtotime( $dua->created_at ) ) ); ?> <?php esc_html_e( 'ago', 'yourjannah' ); ?></span>
                    <span>&middot;</span>
                    <span style="color:<?php echo $dua->status === 'active' ? '#287e61' : '#6b8fa3'; ?>;"><?php echo esc_html( ucfirst( $dua->status ) ); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     7. ACCOUNT (collapsed sections)
     ═══════════════════════════════════════════ -->

<!-- My Masjid -->
<details class="ynj-acct-section">
    <summary>&#x1F54C; <?php esc_html_e( 'My Masjid', 'yourjannah' ); ?></summary>
    <div class="ynj-acct-inner">
        <?php if ( $fav_mosque ) : ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;">
                <div>
                    <a href="<?php echo esc_url( home_url( '/mosque/' . $fav_mosque->slug ) ); ?>" style="font-size:14px;font-weight:600;color:#0a1628;text-decoration:none;"><?php echo esc_html( $fav_mosque->name ); ?></a>
                    <?php if ( $fav_mosque->city ) : ?><div style="font-size:12px;color:#6b8fa3;"><?php echo esc_html( $fav_mosque->city ); ?></div><?php endif; ?>
                </div>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="font-size:12px;color:#00ADEF;font-weight:600;text-decoration:none;"><?php esc_html_e( 'Change', 'yourjannah' ); ?></a>
            </div>
        <?php else : ?>
            <p class="ynj-empty"><?php esc_html_e( 'No favourite mosque set.', 'yourjannah' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#00ADEF;font-weight:600;"><?php esc_html_e( 'Find your masjid', 'yourjannah' ); ?></a></p>
        <?php endif; ?>

        <?php if ( $patron ) : ?>
            <div style="margin-top:12px;padding:12px;background:linear-gradient(135deg,#0a1628,#1a3a5c);border-radius:10px;color:#fff;">
                <div style="font-size:13px;font-weight:700;">&#x1F3C5; <?php echo esc_html( $patron_tier ); ?> <?php esc_html_e( 'Patron', 'yourjannah' ); ?> &middot; &pound;<?php echo esc_html( $patron_amount ); ?>/mo</div>
                <?php if ( $patron->mosque_slug ) : ?>
                <div style="margin-top:6px;display:flex;gap:12px;">
                    <a href="<?php echo esc_url( home_url( '/mosque/' . $patron->mosque_slug . '/patron' ) ); ?>" style="font-size:12px;color:rgba(255,255,255,.7);text-decoration:underline;"><?php esc_html_e( 'Manage', 'yourjannah' ); ?></a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</details>

<!-- Subscriptions -->
<details class="ynj-acct-section" id="subs-section">
    <summary>&#x1F514; <?php esc_html_e( 'Mosque Subscriptions', 'yourjannah' ); ?> (<?php echo count( $subscriptions ); ?>)</summary>
    <div class="ynj-acct-inner" id="subs-list">
        <?php if ( empty( $subscriptions ) ) : ?>
            <p class="ynj-empty"><?php esc_html_e( 'Not subscribed to any mosques yet.', 'yourjannah' ); ?></p>
        <?php else : ?>
            <?php foreach ( $subscriptions as $s ) : ?>
                <div class="ynj-sub-item" id="sub-<?php echo (int) $s->mosque_id; ?>">
                    <div class="ynj-sub-head">
                        <div>
                            <span class="ynj-sub-name"><?php echo esc_html( $s->mosque_name ); ?></span>
                            <?php if ( $s->mosque_city ) : ?><span class="ynj-sub-city"><?php echo esc_html( $s->mosque_city ); ?></span><?php endif; ?>
                        </div>
                        <button class="ynj-sub-unsub" onclick="unsubMosque(<?php echo (int) $s->mosque_id; ?>, this)"><?php esc_html_e( 'Unsubscribe', 'yourjannah' ); ?></button>
                    </div>
                    <div class="ynj-sub-toggles">
                        <?php
                        $toggles = [
                            'notify_events' => [ '&#x1F4C5;', __( 'Events', 'yourjannah' ) ],
                            'notify_classes' => [ '&#x1F393;', __( 'Classes', 'yourjannah' ) ],
                            'notify_announcements' => [ '&#x1F4E2;', __( 'Updates', 'yourjannah' ) ],
                            'notify_live' => [ '&#x1F534;', __( 'Live', 'yourjannah' ) ],
                            'notify_fundraising' => [ '&#x2764;&#xFE0F;', __( 'Fundraise', 'yourjannah' ) ],
                        ];
                        foreach ( $toggles as $key => $t ) :
                            $checked = ! empty( $s->$key ) ? 'checked' : '';
                        ?>
                            <label class="ynj-sub-toggle">
                                <input type="checkbox" data-mosque="<?php echo (int) $s->mosque_id; ?>" data-pref="<?php echo esc_attr( $key ); ?>" <?php echo $checked; ?> onchange="updateSubPref(this)">
                                <?php echo $t[0]; ?> <?php echo esc_html( $t[1] ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</details>

<!-- Bookings -->
<?php if ( ! empty( $bookings ) ) : ?>
<details class="ynj-acct-section">
    <summary>&#x1F4C5; <?php esc_html_e( 'My Bookings', 'yourjannah' ); ?> (<?php echo count( $bookings ); ?>)</summary>
    <div class="ynj-acct-inner">
        <?php foreach ( $bookings as $b ) :
            $is_event = ! empty( $b->event_id );
            $title = $is_event ? ( $b->event_title ?: __( 'Event', 'yourjannah' ) ) : ( $b->room_name ?: __( 'Room', 'yourjannah' ) );
            $status = $b->status;
        ?>
            <div class="ynj-book-item">
                <div class="ynj-book-head">
                    <span class="ynj-book-type <?php echo $is_event ? 'ynj-book-type--event' : 'ynj-book-type--room'; ?>"><?php echo $is_event ? esc_html__( 'Event', 'yourjannah' ) : esc_html__( 'Room', 'yourjannah' ); ?></span>
                    <span class="ynj-book-title"><?php echo esc_html( $title ); ?></span>
                </div>
                <div class="ynj-book-meta"><?php
                    $meta = [];
                    if ( $b->booking_date ) $meta[] = date_i18n( 'j M Y', strtotime( $b->booking_date ) );
                    if ( $b->start_time ) $meta[] = substr( $b->start_time, 0, 5 );
                    if ( $b->mosque_name ) $meta[] = $b->mosque_name;
                    echo esc_html( implode( ' · ', $meta ) );
                ?></div>
                <span class="ynj-badge-status ynj-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<!-- Prayer Preferences -->
<details class="ynj-acct-section">
    <summary>&#x1F54B; <?php esc_html_e( 'Prayer Preferences', 'yourjannah' ); ?></summary>
    <div class="ynj-acct-inner">
        <form id="pref-form" class="ynj-pref-form">
            <div class="ynj-field-row">
                <div class="ynj-field">
                    <label><?php esc_html_e( 'Travel Mode', 'yourjannah' ); ?></label>
                    <select name="travel_mode">
                        <option value="walk" <?php selected( $travel_mode, 'walk' ); ?>><?php esc_html_e( 'Walking', 'yourjannah' ); ?></option>
                        <option value="drive" <?php selected( $travel_mode, 'drive' ); ?>><?php esc_html_e( 'Driving', 'yourjannah' ); ?></option>
                    </select>
                </div>
                <div class="ynj-field">
                    <label><?php esc_html_e( 'Travel Time (min)', 'yourjannah' ); ?></label>
                    <input type="number" name="travel_minutes" value="<?php echo esc_attr( $travel_mins ); ?>" min="0" max="120">
                </div>
            </div>
            <div class="ynj-field" style="margin-bottom:14px;">
                <label><?php esc_html_e( 'Alert Before Prayer', 'yourjannah' ); ?></label>
                <select name="alert_before_minutes">
                    <?php foreach ( [ 10, 15, 20, 30, 45 ] as $m ) : ?>
                        <option value="<?php echo $m; ?>" <?php selected( $alert_mins, $m ); ?>><?php echo $m; ?> <?php esc_html_e( 'minutes', 'yourjannah' ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <button class="ynj-btn-save" id="save-prefs" type="button"><?php esc_html_e( 'Save Preferences', 'yourjannah' ); ?></button>
    </div>
</details>

<!-- Delete Account -->
<details class="ynj-acct-section">
    <summary style="color:#991b1b;border-color:#fecaca;">&#x26A0;&#xFE0F; <?php esc_html_e( 'Delete My Account', 'yourjannah' ); ?></summary>
    <div class="ynj-acct-inner" style="border-color:#fecaca;">
        <p style="font-size:13px;color:#991b1b;margin-bottom:14px;"><?php esc_html_e( 'Permanently delete your account and all data. This cannot be undone.', 'yourjannah' ); ?></p>
        <form method="post" id="delete-account-form">
            <?php wp_nonce_field( 'ynj_delete_account', '_ynj_delete_nonce' ); ?>
            <input type="hidden" name="action" value="delete_account">
            <button type="button" class="ynj-btn-logout" id="btn-delete-account" style="background:#dc2626;color:#fff;border-color:#dc2626;margin-top:0;"><?php esc_html_e( 'Delete My Account', 'yourjannah' ); ?></button>
        </form>
    </div>
</details>

<!-- Logout -->
<button class="ynj-btn-logout" id="btn-logout" type="button"><?php esc_html_e( 'Logout', 'yourjannah' ); ?></button>

</div>
</main>

<script>
(function(){
    var API = ynjData.restUrl;
    var headers = {'Content-Type':'application/json'};
    if (ynjData.nonce) headers['X-WP-Nonce'] = ynjData.nonce;
    var token = localStorage.getItem('ynj_user_token');
    if (token) headers['Authorization'] = 'Bearer ' + token;

    /* ═══════════════════════════════════════════════════════
       CELEBRATION ENGINE — Confetti, floating points, counters
       ═══════════════════════════════════════════════════════ */

    /* ── Confetti burst — fires particles from an element ── */
    function fireConfetti(origin) {
        var rect = origin ? origin.getBoundingClientRect() : { left: window.innerWidth/2, top: window.innerHeight/2, width: 0 };
        var cx = rect.left + rect.width / 2;
        var cy = rect.top;
        var colors = ['#f59e0b','#287e61','#7c3aed','#00ADEF','#ef4444','#fbbf24','#34d399'];
        var emojis = ['\u2728','\u2B50','\uD83C\uDF1F','\uD83D\uDCAB','\u2764\uFE0F','\uD83D\uDE4F','\uD83C\uDF89'];
        for (var i = 0; i < 24; i++) {
            var p = document.createElement('div');
            var isEmoji = Math.random() > 0.6;
            var angle = (Math.PI * 2 * i / 24) + (Math.random() - 0.5);
            var velocity = 60 + Math.random() * 80;
            var dx = Math.cos(angle) * velocity;
            var dy = Math.sin(angle) * velocity - 40;
            p.textContent = isEmoji ? emojis[Math.floor(Math.random()*emojis.length)] : '';
            p.style.cssText = 'position:fixed;left:'+cx+'px;top:'+cy+'px;z-index:10001;pointer-events:none;font-size:'+(isEmoji?'16':'8')+'px;'
                + (isEmoji ? '' : 'width:8px;height:8px;border-radius:50%;background:'+colors[Math.floor(Math.random()*colors.length)]+';')
                + 'transition:all '+(0.6+Math.random()*0.6)+'s cubic-bezier(.25,.46,.45,.94);opacity:1;';
            document.body.appendChild(p);
            requestAnimationFrame(function(el, x, y){ return function(){
                el.style.transform = 'translate('+x+'px,'+y+'px) rotate('+(Math.random()*720-360)+'deg)';
                el.style.opacity = '0';
            }; }(p, dx, dy));
            setTimeout(function(el){ return function(){ el.remove(); }; }(p), 1400);
        }
    }

    /* ── Floating points — "+15 pts" rises from element and fades ── */
    function floatPoints(text, origin, color) {
        var rect = origin ? origin.getBoundingClientRect() : { left: window.innerWidth/2, top: window.innerHeight/2, width: 0 };
        var el = document.createElement('div');
        el.textContent = text;
        el.style.cssText = 'position:fixed;left:'+(rect.left+rect.width/2)+'px;top:'+rect.top+'px;z-index:10002;pointer-events:none;'
            + 'font-size:24px;font-weight:900;color:'+(color||'#f59e0b')+';text-shadow:0 2px 8px rgba(0,0,0,.15);'
            + 'transform:translateX(-50%);animation:ynj-float-up 1.5s ease-out forwards;';
        document.body.appendChild(el);
        setTimeout(function(){ el.remove(); }, 1600);
    }

    /* ── Animate counter — smoothly counts from old to new value ── */
    function animateCounter(el, newVal) {
        if (!el) return;
        var old = parseInt(el.textContent.replace(/,/g,'')) || 0;
        if (old === newVal) return;
        var diff = newVal - old;
        var steps = Math.min(30, Math.abs(diff));
        var stepVal = diff / steps;
        var current = old;
        var i = 0;
        el.style.animation = 'ynj-counter-bump .4s ease-out';
        var interval = setInterval(function(){
            i++;
            current = i >= steps ? newVal : Math.round(old + stepVal * i);
            el.textContent = current.toLocaleString();
            if (i >= steps) clearInterval(interval);
        }, 30);
        setTimeout(function(){ el.style.animation = ''; }, 500);
    }

    /* ── Toast with style — slides in from bottom, glows ── */
    function showToast(text, bg, duration) {
        var t = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:90px;left:50%;z-index:10000;max-width:90%;padding:16px 28px;border-radius:16px;'
            + 'font-size:16px;font-weight:800;color:#fff;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.25);'
            + 'background:'+bg+';transform:translateX(-50%) translateY(20px) scale(0.9);opacity:0;'
            + 'transition:all .35s cubic-bezier(.34,1.56,.64,1);';
        t.textContent = text;
        document.body.appendChild(t);
        requestAnimationFrame(function(){ requestAnimationFrame(function(){
            t.style.transform = 'translateX(-50%) translateY(0) scale(1)'; t.style.opacity = '1';
        }); });
        setTimeout(function(){
            t.style.transform = 'translateX(-50%) translateY(20px) scale(0.9)'; t.style.opacity = '0';
            setTimeout(function(){ t.remove(); }, 400);
        }, duration || 3500);
    }

    /* ── Button celebration — glow + scale on success ── */
    function celebrateButton(btn) {
        btn.style.animation = 'ynj-glow 1s ease-in-out';
        btn.style.transform = 'scale(1.05)';
        setTimeout(function(){ btn.style.transform = ''; btn.style.animation = ''; }, 1000);
    }

    /* ── Haptic feedback (mobile) ── */
    function haptic() {
        if (navigator.vibrate) navigator.vibrate(50);
    }

    /* ════════════════════════════════════════════
       ACTIONS — Ameen, Shukr
       ════════════════════════════════════════════ */

    /* ── Say Ameen / I've said it ── */
    window.ynjSayAmeen = function(btn) {
        btn.disabled = true;
        var origText = btn.innerHTML;
        btn.innerHTML = '<span style="display:inline-block;animation:ynj-pulse .6s infinite;">&#x1F932;</span>';
        haptic();

        fetch('/wp-json/ynj/v1/ibadah/dhikr', {
            method: 'POST', headers: headers, credentials: 'same-origin',
            body: JSON.stringify({})
        }).then(function(r){ return r.json(); }).then(function(d){
            if (d.ok && d.points > 0) {
                // 1. Confetti burst from button
                fireConfetti(btn);
                // 2. Floating points
                floatPoints('+' + d.points + ' pts', btn, '#287e61');
                // 3. Animate hero counter
                animateCounter(document.getElementById('hero-pts'), d.total);
                // 4. Replace button with success + SHARE prompt (peak dopamine moment)
                var waUrl = <?php echo wp_json_encode( isset( $wa_url ) ? $wa_url : '#' ); ?>;
                setTimeout(function(){
                    btn.outerHTML = '<div style="padding:18px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-radius:14px;text-align:center;animation:ynj-celebrate-pop .5s ease-out;">'
                        + '<div style="font-size:32px;margin-bottom:4px;">\u2705</div>'
                        + '<div style="font-size:16px;font-weight:800;color:#166534;"><?php echo esc_js( __( 'May Allah accept', 'yourjannah' ) ); ?></div>'
                        + '<div style="font-size:24px;font-weight:900;color:#f59e0b;margin-top:4px;">+' + d.points + ' pts</div>'
                        + '<?php if ( $fav_mosque ) : ?><div style="font-size:11px;color:#287e61;margin-top:4px;"><?php echo esc_js( $fav_mosque->name ); ?> elevated \u2728</div><?php endif; ?>'
                        + '<div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(0,0,0,.06);">'
                        + '<div style="font-size:12px;font-weight:700;color:#166534;margin-bottom:8px;">\uD83D\uDC9A <?php echo esc_js( __( 'Share the blessing — invite someone to say it too', 'yourjannah' ) ); ?></div>'
                        + '<div style="display:flex;gap:8px;justify-content:center;">'
                        + '<a href="' + waUrl + '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;padding:10px 16px;background:#25D366;color:#fff;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;">\uD83D\uDCF1 WhatsApp</a>'
                        + '<button onclick="ynjCopyInvite(this)" style="display:inline-flex;align-items:center;gap:4px;padding:10px 16px;background:#0a1628;color:#fff;border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;">\uD83D\uDCCB Copy</button>'
                        + '</div></div></div>';
                }, 600);
                // 5. Toast
                showToast('\u2728 <?php echo esc_js( __( 'Your remembrance blesses your masjid!', 'yourjannah' ) ); ?>', 'linear-gradient(135deg,#287e61,#1a5c43)', 4000);
                haptic();
            } else if (d.already_done) {
                btn.outerHTML = '<div style="padding:16px;background:#f0fdf4;border-radius:14px;text-align:center;font-size:14px;font-weight:600;color:#166534;">\u2705 <?php echo esc_js( __( 'Already done today! SubhanAllah.', 'yourjannah' ) ); ?></div>';
            }
        }).catch(function(){ btn.disabled = false; btn.innerHTML = origText; });
    };

    /* ── Daily Shukr — Uses dhikr endpoint with shukr flag ── */
    window.ynjLogShukr = function(btn) {
        btn.disabled = true;
        var origText = btn.innerHTML;
        btn.innerHTML = '<span style="display:inline-block;animation:ynj-pulse .6s infinite;">&#x1F64F;</span>';
        haptic();

        // Use ibadah/log with good_deed to record shukr (50 pts via good_deed + dhikr flags)
        fetch('/wp-json/ynj/v1/ibadah/log', {
            method: 'POST', headers: headers, credentials: 'same-origin',
            body: JSON.stringify({ dhikr: 1, charity: 1, good_deed: 'Daily Shukr - <?php echo esc_js( $shukr_today['text'] ); ?>' })
        }).then(function(r){ return r.json(); }).then(function(d){
            if (d.ok) {
                // 1. MASSIVE confetti burst
                fireConfetti(btn);
                setTimeout(function(){ fireConfetti(btn); }, 300); // Double burst for shukr!
                // 2. Floating points
                floatPoints('+50 pts', btn, '#1e40af');
                // 3. Animate hero counter
                animateCounter(document.getElementById('hero-pts'), d.total_points);
                // 4. Replace with beautiful success state
                setTimeout(function(){
                    btn.outerHTML = '<div style="padding:16px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:12px;text-align:center;animation:ynj-celebrate-pop .5s ease-out;">'
                        + '<div style="font-size:32px;margin-bottom:4px;">\uD83D\uDE4F</div>'
                        + '<div style="font-size:16px;font-weight:800;color:#1e40af;"><?php echo esc_js( $shukr_today['text'] ); ?>!</div>'
                        + '<div style="font-size:22px;font-weight:900;color:#f59e0b;margin-top:4px;">+50 pts</div>'
                        + '<?php if ( $fav_mosque ) : ?><div style="font-size:11px;color:#287e61;margin-top:4px;"><?php echo esc_js( $fav_mosque->name ); ?> elevated \u2728</div><?php endif; ?>'
                        + '</div>';
                }, 600);
                // 5. Toast with masjid elevation message
                showToast('\uD83D\uDE4F <?php echo esc_js( __( 'Your masjid rises! The heavens shake with your gratitude!', 'yourjannah' ) ); ?>', 'linear-gradient(135deg,#1e40af,#1e3a8a)', 5000);
                haptic();
                // 6. Second haptic for emphasis
                setTimeout(function(){ haptic(); }, 400);
            }
        }).catch(function(){ btn.disabled = false; btn.innerHTML = origText; });
    };

    /* ── Welcome bonus animation (auto-fires on page load) ── */
    var welcomeEl = document.getElementById('welcome-bonus');
    if (welcomeEl) {
        setTimeout(function(){ fireConfetti(welcomeEl); }, 500);
        setTimeout(function(){ fireConfetti(welcomeEl); }, 900);
        setTimeout(function(){ floatPoints('+50 pts', welcomeEl, '#f59e0b'); }, 800);
    }

    /* ── Copy invite link ── */
    window.ynjCopyInvite = function(btn) {
        var url = <?php echo wp_json_encode( $fav_mosque ? home_url( '/mosque/' . $fav_mosque->slug ) : home_url( '/' ) ); ?>;
        var msg = <?php echo wp_json_encode( isset( $invite_msg ) ? $invite_msg : '' ); ?>;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(msg).then(function(){
                btn.innerHTML = '\u2705 Copied!';
                haptic();
                showToast('\uD83D\uDCCB <?php echo esc_js( __( 'Invite link copied! Share it with everyone.', 'yourjannah' ) ); ?>', '#287e61', 3000);
                setTimeout(function(){ btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> <?php echo esc_js( __( 'Copy Link', 'yourjannah' ) ); ?>'; }, 3000);
            });
        } else {
            // Fallback for older browsers
            var ta = document.createElement('textarea');
            ta.value = msg; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); ta.remove();
            btn.innerHTML = '\u2705 Copied!';
            setTimeout(function(){ btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> <?php echo esc_js( __( 'Copy Link', 'yourjannah' ) ); ?>'; }, 3000);
        }
    };

    /* ── Save Prayer Preferences ── */
    document.getElementById('save-prefs').addEventListener('click', function() {
        var btn = this;
        var form = document.getElementById('pref-form');
        btn.disabled = true; btn.textContent = <?php echo wp_json_encode( __( 'Saving...', 'yourjannah' ) ); ?>;
        fetch(API + 'auth/me', {
            method: 'PUT', headers: headers,
            body: JSON.stringify({
                travel_mode: form.querySelector('[name="travel_mode"]').value,
                travel_minutes: parseInt(form.querySelector('[name="travel_minutes"]').value) || 0,
                alert_before_minutes: parseInt(form.querySelector('[name="alert_before_minutes"]').value) || 20
            })
        }).then(function(r){ return r.json(); }).then(function(resp){
            btn.disabled = false;
            btn.textContent = resp.ok ? <?php echo wp_json_encode( __( 'Saved', 'yourjannah' ) ); ?> + ' \u2713' : <?php echo wp_json_encode( __( 'Save Preferences', 'yourjannah' ) ); ?>;
            if (resp.ok) setTimeout(function(){ btn.textContent = <?php echo wp_json_encode( __( 'Save Preferences', 'yourjannah' ) ); ?>; }, 2000);
        }).catch(function(){ btn.disabled = false; btn.textContent = <?php echo wp_json_encode( __( 'Save Preferences', 'yourjannah' ) ); ?>; });
    });

    /* ── Subscription Toggle ── */
    window.updateSubPref = function(el) {
        var body = {}; body[el.dataset.pref] = el.checked ? 1 : 0;
        fetch(API + 'auth/subscriptions/' + el.dataset.mosque, { method: 'PUT', headers: headers, body: JSON.stringify(body) });
    };

    /* ── Unsubscribe ── */
    window.unsubMosque = function(mosqueId, btn) {
        if (!confirm(<?php echo wp_json_encode( __( 'Unsubscribe from this mosque?', 'yourjannah' ) ); ?>)) return;
        btn.disabled = true; btn.textContent = '...';
        fetch(API + 'auth/subscriptions/' + mosqueId, { method: 'DELETE', headers: headers }).then(function(){
            var row = document.getElementById('sub-' + mosqueId);
            if (row) row.remove();
            var list = document.getElementById('subs-list');
            if (list && !list.querySelector('.ynj-sub-item')) {
                list.innerHTML = '<p class="ynj-empty"><?php echo esc_js( __( 'Not subscribed to any mosques yet.', 'yourjannah' ) ); ?></p>';
            }
        });
    };

    /* ── Delete Account ── */
    document.getElementById('btn-delete-account').addEventListener('click', function() {
        if (confirm(<?php echo wp_json_encode( __( 'Are you sure you want to permanently delete your account? This CANNOT be undone.', 'yourjannah' ) ); ?>)) {
            var typed = prompt(<?php echo wp_json_encode( __( 'Type DELETE to confirm.', 'yourjannah' ) ); ?>);
            if (typed && typed.toUpperCase() === 'DELETE') document.getElementById('delete-account-form').submit();
        }
    });

    /* ── Logout ── */
    document.getElementById('btn-logout').addEventListener('click', function() {
        localStorage.removeItem('ynj_user_token');
        localStorage.removeItem('ynj_user');
        localStorage.removeItem('ynj_cache_date');
        window.location.href = <?php echo wp_json_encode( wp_logout_url( home_url( '/' ) ) ); ?>;
    });
})();
</script>

<?php get_footer(); ?>
