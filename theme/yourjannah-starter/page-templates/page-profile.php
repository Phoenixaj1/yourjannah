<?php
/**
 * Template: User Profile / Account Dashboard
 *
 * Pure PHP data loading — zero JS API fetches for primary data.
 * JS only used for: notification toggles (PUT), prayer prefs save (PUT), logout.
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
        <p class="ynj-text-muted" style="margin-bottom:16px;"><?php esc_html_e( 'Sign in to see your profile, bookings, and prayer preferences.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="ynj-btn" style="justify-content:center;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
        <p style="margin-top:12px;font-size:13px;">
            <?php esc_html_e( "Don't have an account?", 'yourjannah' ); ?>
            <a href="<?php echo esc_url( home_url( '/register' ) ); ?>" style="font-weight:700;"><?php esc_html_e( 'Create one', 'yourjannah' ); ?></a>
        </p>
    </section>
</main>
<?php get_footer(); return; endif;

// ── Load ALL data in PHP ──
$wp_user  = wp_get_current_user();
$wp_uid   = (int) $wp_user->ID;
$ynj_uid  = (int) get_user_meta( $wp_uid, 'ynj_user_id', true );
$phone    = get_user_meta( $wp_uid, 'ynj_phone', true ) ?: '';
$fav_mosque_id = (int) get_user_meta( $wp_uid, 'ynj_favourite_mosque_id', true );

// Auto-link: if WP user exists but ynj_user_id not set, find or create the link
if ( ! $ynj_uid && $wp_uid && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $ut = YNJ_DB::table( 'users' );
    $email = $wp_user->user_email;

    // Try to find existing ynj_user by email
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $ut WHERE email = %s LIMIT 1", $email ) );
    if ( $existing ) {
        $ynj_uid = (int) $existing->id;
    } else {
        // Create ynj_user record for this WP user
        $token = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );
        $wpdb->insert( $ut, [
            'name'          => $wp_user->display_name,
            'email'         => $email,
            'phone'         => $phone,
            'password_hash' => '',
            'token_hash'    => $token_hash,
            'status'        => 'active',
        ] );
        $ynj_uid = (int) $wpdb->insert_id;
    }
    if ( $ynj_uid ) {
        update_user_meta( $wp_uid, 'ynj_user_id', $ynj_uid );
    }
}

// Defaults
$patron       = null;
$ynj_user     = null;
$subscriptions = [];
$bookings     = [];
$businesses   = [];
$services     = [];
$points_total = 0;
$points_recent = [];
$fav_mosque   = null;

if ( $ynj_uid && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;

    // YNJ user record (for travel_mode, alert_before_minutes, total_points)
    $users_table = YNJ_DB::table( 'users' );
    $ynj_user = $wpdb->get_row( $wpdb->prepare(
        "SELECT travel_mode, travel_minutes, alert_before_minutes, total_points, favourite_mosque_id FROM $users_table WHERE id = %d",
        $ynj_uid
    ) );

    // Override fav mosque from ynj users table if usermeta is empty
    if ( ! $fav_mosque_id && $ynj_user && $ynj_user->favourite_mosque_id ) {
        $fav_mosque_id = (int) $ynj_user->favourite_mosque_id;
    }

    // Patron record
    $patron_table = YNJ_DB::table( 'patrons' );
    $mosques_table = YNJ_DB::table( 'mosques' );
    $patron = $wpdb->get_row( $wpdb->prepare(
        "SELECT p.*, m.name AS mosque_name, m.slug AS mosque_slug
         FROM $patron_table p
         LEFT JOIN $mosques_table m ON m.id = p.mosque_id
         WHERE p.user_id = %d AND p.status = 'active'
         ORDER BY p.amount_pence DESC LIMIT 1",
        $ynj_uid
    ) );

    // Subscriptions (with mosque names)
    $sub_table = YNJ_DB::table( 'user_subscriptions' );
    $subscriptions = $wpdb->get_results( $wpdb->prepare(
        "SELECT s.*, m.name AS mosque_name, m.city AS mosque_city, m.slug AS mosque_slug
         FROM $sub_table s
         LEFT JOIN $mosques_table m ON m.id = s.mosque_id
         WHERE s.user_id = %d AND s.status = 'active'
         ORDER BY s.subscribed_at DESC LIMIT 20",
        $ynj_uid
    ) ) ?: [];

    // Bookings (by email, last 20)
    $book_table = YNJ_DB::table( 'bookings' );
    $events_table = YNJ_DB::table( 'events' );
    $rooms_table = YNJ_DB::table( 'rooms' );
    $bookings = $wpdb->get_results( $wpdb->prepare(
        "SELECT b.*, m.name AS mosque_name, e.title AS event_title, r.name AS room_name
         FROM $book_table b
         LEFT JOIN $mosques_table m ON m.id = b.mosque_id
         LEFT JOIN $events_table e ON e.id = b.event_id
         LEFT JOIN $rooms_table r ON r.id = b.room_id
         WHERE b.user_email = %s
         ORDER BY b.created_at DESC LIMIT 20",
        $wp_user->user_email
    ) ) ?: [];

    // Businesses (by email)
    $biz_table = YNJ_DB::table( 'businesses' );
    $businesses = $wpdb->get_results( $wpdb->prepare(
        "SELECT b.*, m.name AS mosque_name, m.slug AS mosque_slug
         FROM $biz_table b
         LEFT JOIN $mosques_table m ON m.id = b.mosque_id
         WHERE b.email = %s AND b.status IN ('active','pending')
         ORDER BY b.created_at DESC LIMIT 20",
        $wp_user->user_email
    ) ) ?: [];

    // Services (by email)
    $svc_table = YNJ_DB::table( 'services' );
    $services = $wpdb->get_results( $wpdb->prepare(
        "SELECT s.*, m.name AS mosque_name, m.slug AS mosque_slug
         FROM $svc_table s
         LEFT JOIN $mosques_table m ON m.id = s.mosque_id
         WHERE s.email = %s AND s.status IN ('active','pending')
         ORDER BY s.created_at DESC LIMIT 20",
        $wp_user->user_email
    ) ) ?: [];

    // Points
    $pts_table = YNJ_DB::table( 'points' );
    $points_total = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(points), 0) FROM $pts_table WHERE user_id = %d",
        $ynj_uid
    ) );
    $points_recent = $wpdb->get_results( $wpdb->prepare(
        "SELECT action, points, description, created_at FROM $pts_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 5",
        $ynj_uid
    ) ) ?: [];

    // Favourite mosque lookup
    if ( $fav_mosque_id ) {
        $fav_mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, slug, city FROM $mosques_table WHERE id = %d",
            $fav_mosque_id
        ) );
    }
}

// Derived values
$user_initial  = strtoupper( mb_substr( $wp_user->display_name ?: 'U', 0, 1 ) );
$travel_mode   = $ynj_user ? $ynj_user->travel_mode : 'walk';
$travel_mins   = $ynj_user ? (int) $ynj_user->travel_minutes : 0;
$alert_mins    = $ynj_user ? (int) $ynj_user->alert_before_minutes : 20;
$total_pts     = $ynj_user ? max( $points_total, (int) $ynj_user->total_points ) : $points_total;
$tier_badges   = [ 'supporter' => 'Bronze', 'guardian' => 'Silver', 'champion' => 'Gold', 'platinum' => 'Platinum' ];
$tier_icons    = [ 'supporter' => '&#x1F949;', 'guardian' => '&#x1F948;', 'champion' => '&#x1F947;', 'platinum' => '&#x1F48E;' ];
$patron_tier   = $patron ? ( $tier_badges[ $patron->tier ] ?? ucfirst( $patron->tier ) ) : '';
$patron_icon   = $patron ? ( $tier_icons[ $patron->tier ] ?? '&#x1F3C5;' ) : '';
$patron_since  = $patron && $patron->started_at ? date_i18n( 'M Y', strtotime( $patron->started_at ) ) : '';
$patron_amount = $patron ? number_format( $patron->amount_pence / 100, 0 ) : '';

// Action type labels for points
$action_labels = [
    'check_in'    => 'Check-in',
    'event_rsvp'  => 'Event RSVP',
    'donation'    => 'Donation',
    'class_enrol' => 'Class Enrolment',
    'volunteer'   => 'Volunteer',
];
$action_pts = [
    'check_in'    => 10,
    'event_rsvp'  => 25,
    'donation'    => 50,
    'class_enrol' => 20,
    'volunteer'   => 30,
];
?>

<style>
/* ── Profile Dashboard Styles ── */
.ynj-dash{max-width:600px;margin:0 auto;padding:0 16px 40px;}
.ynj-dash-hero{background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 100%);border-radius:20px;padding:28px 24px 24px;margin-bottom:16px;color:#fff;text-align:center;position:relative;overflow:hidden;}
.ynj-dash-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:120px;height:120px;background:radial-gradient(circle,rgba(0,173,239,.15) 0%,transparent 70%);border-radius:50%;}
.ynj-dash-avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#00ADEF,#0090d0);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 12px;border:3px solid rgba(255,255,255,.2);position:relative;}
.ynj-dash-avatar--patron{border-color:#fbbf24;}
.ynj-dash-name{font-size:20px;font-weight:700;margin-bottom:2px;}
.ynj-dash-email{font-size:13px;opacity:.7;margin-bottom:2px;}
.ynj-dash-phone{font-size:12px;opacity:.55;}
.ynj-patron-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 14px;border-radius:8px;font-size:12px;font-weight:700;background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;margin-top:10px;}

.ynj-dash-card{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:16px;border:1px solid rgba(255,255,255,.6);padding:20px;margin-bottom:14px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.ynj-dash-card h3{font-size:15px;font-weight:700;margin-bottom:12px;color:#0a1628;display:flex;align-items:center;gap:6px;}
.ynj-dash-card--patron{background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;border:none;}
.ynj-dash-card--patron h3{color:#fff;}
.ynj-dash-card--upgrade{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;}

.ynj-dash-patron-row{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:12px;background:rgba(255,255,255,.1);margin-bottom:8px;}
.ynj-dash-patron-mosque{font-size:14px;font-weight:700;display:block;}
.ynj-dash-patron-since{font-size:11px;opacity:.7;}
.ynj-dash-patron-tier{display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;}
.ynj-dash-patron-amount{font-size:13px;font-weight:700;margin-top:2px;}
.ynj-dash-patron-links{display:flex;gap:12px;margin-top:10px;justify-content:center;}
.ynj-dash-patron-links a{font-size:12px;color:rgba(255,255,255,.7);text-decoration:underline;}

.ynj-dash-mosque-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;}
.ynj-dash-mosque-name{font-size:14px;font-weight:600;}
.ynj-dash-mosque-city{font-size:12px;color:#6b8fa3;}
.ynj-dash-change{font-size:12px;color:#00ADEF;font-weight:600;text-decoration:none;}

/* Points */
.ynj-pts-total{display:flex;align-items:center;gap:12px;padding:14px 16px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:12px;margin-bottom:12px;}
.ynj-pts-num{font-size:28px;font-weight:800;color:#00ADEF;}
.ynj-pts-label{font-size:12px;color:#6b8fa3;font-weight:600;}
.ynj-pts-breakdown{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}
.ynj-pts-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:600;background:#f0f9ff;color:#0369a1;}
.ynj-pts-activity{padding:8px 0;border-bottom:1px solid rgba(0,0,0,.04);display:flex;align-items:center;justify-content:space-between;}
.ynj-pts-activity:last-child{border-bottom:none;}
.ynj-pts-desc{font-size:13px;color:#0a1628;}
.ynj-pts-date{font-size:11px;color:#6b8fa3;}
.ynj-pts-val{font-size:13px;font-weight:700;color:#00ADEF;}

/* Subscriptions */
.ynj-sub-item{padding:12px 0;border-bottom:1px solid #f0f0f0;}
.ynj-sub-item:last-child{border-bottom:none;}
.ynj-sub-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;}
.ynj-sub-name{font-size:14px;font-weight:700;}
.ynj-sub-city{font-size:12px;color:#6b8fa3;}
.ynj-sub-unsub{font-size:11px;color:#dc2626;background:none;border:1px solid #fecaca;padding:4px 10px;border-radius:6px;cursor:pointer;}
.ynj-sub-toggles{display:flex;gap:12px;flex-wrap:wrap;}
.ynj-sub-toggle{display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;}
.ynj-sub-toggle input{width:14px;height:14px;accent-color:#00ADEF;}

/* Bookings */
.ynj-book-item{padding:12px 0;border-bottom:1px solid rgba(0,0,0,.04);}
.ynj-book-item:last-child{border-bottom:none;}
.ynj-book-head{display:flex;align-items:center;gap:8px;margin-bottom:4px;}
.ynj-book-type{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.ynj-book-type--event{background:#ede9fe;color:#7c3aed;}
.ynj-book-type--room{background:#e0f2fe;color:#0284c7;}
.ynj-book-title{font-size:14px;font-weight:600;}
.ynj-book-meta{font-size:12px;color:#6b8fa3;margin-bottom:4px;}
.ynj-badge-status{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;}
.ynj-badge--confirmed{background:#dcfce7;color:#166534;}
.ynj-badge--pending,.ynj-badge--pending_payment{background:#fef3c7;color:#92400e;}
.ynj-badge--cancelled,.ynj-badge--rejected{background:#fee2e2;color:#991b1b;}

/* Listings */
.ynj-listing-card{padding:14px;border-radius:12px;background:#f8fafc;border:1px solid rgba(0,0,0,.04);margin-bottom:10px;}
.ynj-listing-card:last-child{margin-bottom:0;}
.ynj-listing-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;}
.ynj-listing-name{font-size:14px;font-weight:700;}
.ynj-listing-cat{font-size:11px;color:#6b8fa3;}
.ynj-listing-mosque{font-size:12px;color:#6b8fa3;margin-bottom:4px;}
.ynj-listing-status{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;}
.ynj-listing-status--active{background:#dcfce7;color:#166534;}
.ynj-listing-status--pending{background:#fef3c7;color:#92400e;}

/* Prayer Preferences */
.ynj-pref-form .ynj-field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
.ynj-pref-form .ynj-field{display:flex;flex-direction:column;gap:4px;}
.ynj-pref-form label{font-size:12px;font-weight:600;color:#6b8fa3;}
.ynj-pref-form select,.ynj-pref-form input{padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.1);font-size:14px;font-family:inherit;background:#fff;outline:none;}
.ynj-pref-form select:focus,.ynj-pref-form input:focus{border-color:#00ADEF;}

.ynj-btn-save{display:block;width:100%;padding:12px;border-radius:12px;font-size:14px;font-weight:600;border:1px solid #00ADEF;background:transparent;color:#00ADEF;cursor:pointer;text-align:center;transition:all .15s;}
.ynj-btn-save:hover{background:#00ADEF;color:#fff;}
.ynj-btn-logout{display:block;width:100%;padding:12px;border-radius:12px;font-size:14px;font-weight:600;border:1px solid #dc2626;background:transparent;color:#dc2626;cursor:pointer;text-align:center;margin-top:20px;transition:all .15s;}
.ynj-btn-logout:hover{background:#dc2626;color:#fff;}
.ynj-empty{text-align:center;padding:24px 16px;color:#6b8fa3;font-size:13px;}

/* Interest Preferences */
.ynj-interests-note{font-size:13px;color:#6b8fa3;margin-bottom:14px;line-height:1.4;}
.ynj-interest-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}
.ynj-interest-chip{
    display:inline-flex;align-items:center;gap:5px;
    padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;
    border:1.5px solid #e0e8ed;background:#fff;color:#0a1628;
    cursor:pointer;font-family:inherit;transition:all .15s;user-select:none;
}
.ynj-interest-chip:active{transform:scale(.95);}
.ynj-interest-chip--active{background:#00ADEF;color:#fff;border-color:#00ADEF;}
.ynj-interest-chip--active:hover{background:#0090d0;border-color:#0090d0;}
.ynj-interest-chip:not(.ynj-interest-chip--active):hover{border-color:#00ADEF;color:#00ADEF;}
.ynj-radius-row{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
.ynj-radius-row label{font-size:12px;font-weight:600;color:#6b8fa3;white-space:nowrap;}
.ynj-radius-row select{padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.1);font-size:14px;font-family:inherit;background:#fff;outline:none;flex:1;max-width:200px;}
.ynj-radius-row select:focus{border-color:#00ADEF;}

@media(max-width:480px){
    .ynj-pref-form .ynj-field-row{grid-template-columns:1fr;}
    .ynj-dash{padding:0 12px 32px;}
    .ynj-dash-hero{padding:24px 16px 20px;}
    .ynj-interest-chips{gap:6px;}
    .ynj-interest-chip{padding:6px 10px;font-size:12px;}
}
</style>

<main class="ynj-main">
<div class="ynj-dash">

    <!-- 1. Profile Card (Hero) -->
    <div class="ynj-dash-hero">
        <div class="ynj-dash-avatar<?php echo $patron ? ' ynj-dash-avatar--patron' : ''; ?>">
            <?php echo esc_html( $user_initial ); ?>
        </div>
        <div class="ynj-dash-name"><?php echo esc_html( $wp_user->display_name ); ?></div>
        <div class="ynj-dash-email"><?php echo esc_html( $wp_user->user_email ); ?></div>
        <?php if ( $phone ) : ?>
            <div class="ynj-dash-phone"><?php echo esc_html( $phone ); ?></div>
        <?php endif; ?>
        <?php if ( $patron ) : ?>
            <span class="ynj-patron-badge"><?php echo $patron_icon; ?> <?php echo esc_html( $patron_tier ); ?> Patron</span>
        <?php endif; ?>
    </div>

    <!-- 2. Membership Status Card -->
    <?php if ( $patron ) : ?>
        <div class="ynj-dash-card ynj-dash-card--patron">
            <h3>&#x1F3C5; <?php esc_html_e( 'Patron Membership', 'yourjannah' ); ?></h3>
            <div class="ynj-dash-patron-row">
                <div>
                    <span class="ynj-dash-patron-mosque"><?php echo esc_html( $patron->mosque_name ?: __( 'Mosque', 'yourjannah' ) ); ?></span>
                    <span class="ynj-dash-patron-since"><?php
                        /* translators: %s: date like "Apr 2026" */
                        printf( esc_html__( 'Since %s', 'yourjannah' ), esc_html( $patron_since ) );
                    ?></span>
                </div>
                <div style="text-align:right;">
                    <span class="ynj-dash-patron-tier" style="background:rgba(255,255,255,.15);color:#fff;">
                        <?php echo $patron_icon; ?> <?php echo esc_html( $patron_tier ); ?>
                    </span>
                    <div class="ynj-dash-patron-amount">&pound;<?php echo esc_html( $patron_amount ); ?>/mo</div>
                </div>
            </div>
            <div class="ynj-dash-patron-links">
                <?php if ( $patron->mosque_slug ) : ?>
                    <a href="<?php echo esc_url( home_url( '/mosque/' . $patron->mosque_slug . '/patron' ) ); ?>"><?php esc_html_e( 'Manage', 'yourjannah' ); ?></a>
                <?php endif; ?>
                <?php if ( $patron->mosque_slug ) : ?>
                    <a href="<?php echo esc_url( home_url( '/mosque/' . $patron->mosque_slug . '/patron' ) ); ?>"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php else : ?>
        <div class="ynj-dash-card ynj-dash-card--upgrade">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <h3 style="color:#92400e;margin-bottom:4px;">&#x2B50; <?php esc_html_e( 'Free Member', 'yourjannah' ); ?></h3>
                    <p style="font-size:13px;color:#92400e;opacity:.8;margin:0;"><?php esc_html_e( 'Upgrade to support your masjid with a monthly patronage.', 'yourjannah' ); ?></p>
                </div>
                <a href="<?php echo esc_url( home_url( $fav_mosque ? '/mosque/' . $fav_mosque->slug . '/patron' : '/' ) ); ?>" style="display:inline-flex;align-items:center;gap:4px;padding:8px 16px;border-radius:10px;background:#92400e;color:#fff;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;">
                    <?php esc_html_e( 'Upgrade', 'yourjannah' ); ?> &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. My Masjid -->
    <div class="ynj-dash-card">
        <h3>&#x1F54C; <?php esc_html_e( 'My Masjid', 'yourjannah' ); ?></h3>
        <?php if ( $fav_mosque ) : ?>
            <div class="ynj-dash-mosque-row">
                <div>
                    <a href="<?php echo esc_url( home_url( '/mosque/' . $fav_mosque->slug ) ); ?>" class="ynj-dash-mosque-name" style="color:#0a1628;text-decoration:none;">
                        <?php echo esc_html( $fav_mosque->name ); ?>
                    </a>
                    <?php if ( $fav_mosque->city ) : ?>
                        <div class="ynj-dash-mosque-city"><?php echo esc_html( $fav_mosque->city ); ?></div>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ynj-dash-change"><?php esc_html_e( 'Change', 'yourjannah' ); ?></a>
            </div>
        <?php else : ?>
            <p class="ynj-empty"><?php esc_html_e( 'No favourite mosque set yet.', 'yourjannah' ); ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#00ADEF;font-weight:600;"><?php esc_html_e( 'Find your masjid', 'yourjannah' ); ?></a>
            </p>
        <?php endif; ?>
    </div>

    <!-- 4. Gamification Points -->
    <div class="ynj-dash-card">
        <h3>&#x2B50; <?php esc_html_e( 'My Points', 'yourjannah' ); ?></h3>
        <div class="ynj-pts-total">
            <span class="ynj-pts-num"><?php echo esc_html( number_format( $total_pts ) ); ?></span>
            <span class="ynj-pts-label"><?php esc_html_e( 'Total Points', 'yourjannah' ); ?></span>
        </div>
        <div class="ynj-pts-breakdown">
            <?php foreach ( $action_pts as $act => $pts ) : ?>
                <span class="ynj-pts-chip"><?php echo esc_html( $action_labels[ $act ] ?? $act ); ?>: +<?php echo (int) $pts; ?></span>
            <?php endforeach; ?>
        </div>
        <?php if ( ! empty( $points_recent ) ) : ?>
            <h4 style="font-size:12px;font-weight:700;color:#6b8fa3;margin-bottom:6px;"><?php esc_html_e( 'Recent Activity', 'yourjannah' ); ?></h4>
            <?php foreach ( $points_recent as $pt ) : ?>
                <div class="ynj-pts-activity">
                    <div>
                        <span class="ynj-pts-desc"><?php echo esc_html( $pt->description ?: ( $action_labels[ $pt->action ] ?? $pt->action ) ); ?></span>
                        <span class="ynj-pts-date"><?php echo esc_html( date_i18n( 'j M Y', strtotime( $pt->created_at ) ) ); ?></span>
                    </div>
                    <span class="ynj-pts-val">+<?php echo (int) $pt->points; ?></span>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="ynj-empty"><?php esc_html_e( 'No points earned yet. Check in at your mosque to start!', 'yourjannah' ); ?></p>
        <?php endif; ?>
    </div>

    <!-- 5. My Subscriptions -->
    <div class="ynj-dash-card" id="subs-section">
        <h3>&#x1F514; <?php esc_html_e( 'My Mosque Subscriptions', 'yourjannah' ); ?></h3>
        <div id="subs-list">
        <?php if ( empty( $subscriptions ) ) : ?>
            <p class="ynj-empty"><?php esc_html_e( 'Not subscribed to any mosques yet. Visit a mosque page and tap Subscribe.', 'yourjannah' ); ?></p>
        <?php else : ?>
            <?php foreach ( $subscriptions as $s ) : ?>
                <div class="ynj-sub-item" id="sub-<?php echo (int) $s->mosque_id; ?>">
                    <div class="ynj-sub-head">
                        <div>
                            <span class="ynj-sub-name"><?php echo esc_html( $s->mosque_name ); ?></span>
                            <?php if ( $s->mosque_city ) : ?>
                                <span class="ynj-sub-city"><?php echo esc_html( $s->mosque_city ); ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="ynj-sub-unsub" onclick="unsubMosque(<?php echo (int) $s->mosque_id; ?>, this)">
                            <?php esc_html_e( 'Unsubscribe', 'yourjannah' ); ?>
                        </button>
                    </div>
                    <div class="ynj-sub-toggles">
                        <?php
                        $toggles = [
                            'notify_events'        => [ '&#x1F4C5;', __( 'Events', 'yourjannah' ) ],
                            'notify_classes'       => [ '&#x1F393;', __( 'Classes', 'yourjannah' ) ],
                            'notify_announcements' => [ '&#x1F4E2;', __( 'Updates', 'yourjannah' ) ],
                            'notify_live'          => [ '&#x1F534;', __( 'Live', 'yourjannah' ) ],
                            'notify_fundraising'   => [ '&#x2764;&#xFE0F;', __( 'Fundraise', 'yourjannah' ) ],
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
    </div>

    <!-- 6. My Bookings -->
    <div class="ynj-dash-card">
        <h3>&#x1F4C5; <?php esc_html_e( 'My Bookings', 'yourjannah' ); ?> (<?php echo count( $bookings ); ?>)</h3>
        <?php if ( empty( $bookings ) ) : ?>
            <p class="ynj-empty"><?php esc_html_e( 'No bookings yet. Browse events and rooms to get started.', 'yourjannah' ); ?></p>
        <?php else : ?>
            <?php foreach ( $bookings as $b ) :
                $is_event = ! empty( $b->event_id );
                $title = $is_event ? ( $b->event_title ?: __( 'Event', 'yourjannah' ) ) : ( $b->room_name ?: __( 'Room', 'yourjannah' ) );
                $time_str = $b->start_time ? substr( $b->start_time, 0, 5 ) : '';
                $status = $b->status;
                $status_class = 'ynj-badge--' . $status;
            ?>
                <div class="ynj-book-item">
                    <div class="ynj-book-head">
                        <span class="ynj-book-type <?php echo $is_event ? 'ynj-book-type--event' : 'ynj-book-type--room'; ?>">
                            <?php echo $is_event ? esc_html__( 'Event', 'yourjannah' ) : esc_html__( 'Room', 'yourjannah' ); ?>
                        </span>
                        <span class="ynj-book-title"><?php echo esc_html( $title ); ?></span>
                    </div>
                    <div class="ynj-book-meta">
                        <?php
                        $meta_parts = [];
                        if ( $b->booking_date ) $meta_parts[] = date_i18n( 'j M Y', strtotime( $b->booking_date ) );
                        if ( $time_str ) $meta_parts[] = $time_str;
                        if ( $b->mosque_name ) $meta_parts[] = $b->mosque_name;
                        echo esc_html( implode( ' &middot; ', $meta_parts ) );
                        ?>
                    </div>
                    <span class="ynj-badge-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 7. My Business Listings -->
    <?php if ( ! empty( $businesses ) ) : ?>
    <div class="ynj-dash-card">
        <h3>&#x1F3E2; <?php esc_html_e( 'My Business Listings', 'yourjannah' ); ?></h3>
        <?php foreach ( $businesses as $biz ) :
            $biz_initial = strtoupper( mb_substr( $biz->business_name ?: '?', 0, 1 ) );
        ?>
            <div class="ynj-listing-card">
                <div class="ynj-listing-head">
                    <div>
                        <span class="ynj-listing-name"><?php echo esc_html( $biz->business_name ); ?></span>
                        <?php if ( $biz->category ) : ?>
                            <span class="ynj-listing-cat"><?php echo esc_html( $biz->category ); ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="ynj-listing-status ynj-listing-status--<?php echo esc_attr( $biz->status ); ?>">
                        <?php echo esc_html( ucfirst( $biz->status ) ); ?>
                    </span>
                </div>
                <?php if ( $biz->mosque_name ) : ?>
                    <div class="ynj-listing-mosque"><?php echo esc_html( $biz->mosque_name ); ?></div>
                <?php endif; ?>
                <?php if ( $biz->description ) : ?>
                    <p style="font-size:13px;color:#6b8fa3;margin:4px 0 0;"><?php echo esc_html( mb_strimwidth( $biz->description, 0, 140, '...' ) ); ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:6px;margin-top:8px;">
                    <a href="<?php echo esc_url( home_url( '/mosque/' . ( $biz->mosque_slug ?: $slug ) . '/business/' . $biz->id ) ); ?>" style="font-size:12px;font-weight:600;color:#00ADEF;text-decoration:none;">View →</a>
                    <a href="<?php echo esc_url( home_url( '/mosque/' . ( $biz->mosque_slug ?: $slug ) . '/business/' . $biz->id . '/edit' ) ); ?>" style="font-size:12px;font-weight:600;color:#7c3aed;text-decoration:none;">✏️ Edit</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 8. My Service Listings -->
    <?php if ( ! empty( $services ) ) : ?>
    <div class="ynj-dash-card">
        <h3>&#x1F527; <?php esc_html_e( 'My Service Listings', 'yourjannah' ); ?></h3>
        <?php foreach ( $services as $svc ) : ?>
            <div class="ynj-listing-card">
                <div class="ynj-listing-head">
                    <div>
                        <span class="ynj-listing-name"><?php echo esc_html( $svc->provider_name ); ?></span>
                        <?php if ( $svc->service_type ) : ?>
                            <span class="ynj-listing-cat"><?php echo esc_html( ucfirst( $svc->service_type ) ); ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="ynj-listing-status ynj-listing-status--<?php echo esc_attr( $svc->status ); ?>">
                        <?php echo esc_html( ucfirst( $svc->status ) ); ?>
                    </span>
                </div>
                <?php if ( $svc->mosque_name ) : ?>
                    <div class="ynj-listing-mosque"><?php echo esc_html( $svc->mosque_name ); ?></div>
                <?php endif; ?>
                <?php if ( $svc->area_covered ) : ?>
                    <p style="font-size:12px;color:#6b8fa3;margin:4px 0 0;"><?php esc_html_e( 'Area:', 'yourjannah' ); ?> <?php echo esc_html( $svc->area_covered ); ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:6px;margin-top:8px;">
                    <a href="<?php echo esc_url( home_url( '/mosque/' . ( $svc->mosque_slug ?: $slug ) . '/service/' . $svc->id ) ); ?>" style="font-size:12px;font-weight:600;color:#00ADEF;text-decoration:none;">View →</a>
                    <a href="<?php echo esc_url( home_url( '/mosque/' . ( $svc->mosque_slug ?: $slug ) . '/service/' . $svc->id . '/edit' ) ); ?>" style="font-size:12px;font-weight:600;color:#7c3aed;text-decoration:none;">✏️ Edit</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 9. Prayer Preferences -->
    <div class="ynj-dash-card">
        <h3>&#x1F54B; <?php esc_html_e( 'Prayer Preferences', 'yourjannah' ); ?></h3>
        <form id="pref-form" class="ynj-pref-form">
            <div class="ynj-field-row">
                <div class="ynj-field">
                    <label for="pref-travel-mode"><?php esc_html_e( 'Travel Mode', 'yourjannah' ); ?></label>
                    <select name="travel_mode" id="pref-travel-mode">
                        <option value="walk" <?php selected( $travel_mode, 'walk' ); ?>><?php esc_html_e( 'Walking', 'yourjannah' ); ?></option>
                        <option value="drive" <?php selected( $travel_mode, 'drive' ); ?>><?php esc_html_e( 'Driving', 'yourjannah' ); ?></option>
                    </select>
                </div>
                <div class="ynj-field">
                    <label for="pref-travel-mins"><?php esc_html_e( 'Travel Time (min)', 'yourjannah' ); ?></label>
                    <input type="number" name="travel_minutes" id="pref-travel-mins" value="<?php echo esc_attr( $travel_mins ); ?>" placeholder="e.g. 15" min="0" max="120">
                </div>
            </div>
            <div class="ynj-field" style="margin-bottom:14px;">
                <label for="pref-alert"><?php esc_html_e( 'Alert Before Prayer (minutes)', 'yourjannah' ); ?></label>
                <select name="alert_before_minutes" id="pref-alert">
                    <option value="10" <?php selected( $alert_mins, 10 ); ?>><?php esc_html_e( '10 minutes', 'yourjannah' ); ?></option>
                    <option value="15" <?php selected( $alert_mins, 15 ); ?>><?php esc_html_e( '15 minutes', 'yourjannah' ); ?></option>
                    <option value="20" <?php selected( $alert_mins, 20 ); ?>><?php esc_html_e( '20 minutes (default)', 'yourjannah' ); ?></option>
                    <option value="30" <?php selected( $alert_mins, 30 ); ?>><?php esc_html_e( '30 minutes', 'yourjannah' ); ?></option>
                    <option value="45" <?php selected( $alert_mins, 45 ); ?>><?php esc_html_e( '45 minutes', 'yourjannah' ); ?></option>
                </select>
            </div>
        </form>
        <button class="ynj-btn-save" id="save-prefs" type="button"><?php esc_html_e( 'Save Preferences', 'yourjannah' ); ?></button>
    </div>

    <!-- 10. Interest Preferences -->
    <div class="ynj-dash-card" id="interests-section">
        <h3>&#x1F4CC; <?php esc_html_e( 'My Interests', 'yourjannah' ); ?></h3>
        <p class="ynj-interests-note"><?php esc_html_e( 'Get notified about events and announcements matching your interests from mosques near you.', 'yourjannah' ); ?></p>
        <div class="ynj-interest-chips" id="interest-chips">
            <?php
            $interest_cats = [
                'sports'      => [ "\xF0\x9F\x8F\x83", __( 'Sports & Fitness', 'yourjannah' ) ],
                'social'      => [ "\xF0\x9F\x91\xA5", __( 'Social & Community', 'yourjannah' ) ],
                'women'       => [ "\xF0\x9F\x91\xA9", __( "Women's Events", 'yourjannah' ) ],
                'youth'       => [ "\xF0\x9F\xA7\x92", __( 'Youth & Children', 'yourjannah' ) ],
                'education'   => [ "\xF0\x9F\x93\x9A", __( 'Education & Courses', 'yourjannah' ) ],
                'religious'   => [ "\xF0\x9F\x95\x8C", __( 'Religious / Hadith', 'yourjannah' ) ],
                'community'   => [ "\xF0\x9F\x8E\x89", __( 'Community Events', 'yourjannah' ) ],
                'fundraising' => [ "\xF0\x9F\x92\x9D", __( 'Fundraising & Charity', 'yourjannah' ) ],
                'bookings'    => [ "\xF0\x9F\x8F\xA0", __( 'Room Bookings', 'yourjannah' ) ],
                'live'        => [ "\xF0\x9F\x93\xA1", __( 'Live Events', 'yourjannah' ) ],
            ];
            foreach ( $interest_cats as $slug => $cat ) : ?>
                <button type="button" class="ynj-interest-chip" data-interest="<?php echo esc_attr( $slug ); ?>" onclick="toggleInterest(this)">
                    <span><?php echo $cat[0]; ?></span> <?php echo esc_html( $cat[1] ); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="ynj-radius-row">
            <label for="interest-radius"><?php esc_html_e( 'Notification Radius', 'yourjannah' ); ?></label>
            <select id="interest-radius">
                <option value="5"><?php esc_html_e( '5 miles', 'yourjannah' ); ?></option>
                <option value="10"><?php esc_html_e( '10 miles', 'yourjannah' ); ?></option>
                <option value="25"><?php esc_html_e( '25 miles', 'yourjannah' ); ?></option>
                <option value="nationwide"><?php esc_html_e( 'Nationwide', 'yourjannah' ); ?></option>
            </select>
        </div>
        <button class="ynj-btn-save" id="save-interests" type="button"><?php esc_html_e( 'Save Interests', 'yourjannah' ); ?></button>
    </div>

    <!-- 11. Logout -->
    <button class="ynj-btn-logout" id="btn-logout" type="button"><?php esc_html_e( 'Logout', 'yourjannah' ); ?></button>

</div>
</main>

<script>
(function(){
    var API = ynjData.restUrl;
    var token = localStorage.getItem('ynj_user_token');
    var headers = {'Content-Type':'application/json'};
    if (token) headers['Authorization'] = 'Bearer ' + token;
    if (ynjData.nonce) headers['X-WP-Nonce'] = ynjData.nonce;

    /* ── Save Prayer Preferences (PUT) ── */
    document.getElementById('save-prefs').addEventListener('click', function() {
        var btn = this;
        var form = document.getElementById('pref-form');
        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Saving...', 'yourjannah' ) ); ?>;
        fetch(API + 'auth/me', {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify({
                travel_mode: form.querySelector('[name="travel_mode"]').value,
                travel_minutes: parseInt(form.querySelector('[name="travel_minutes"]').value) || 0,
                alert_before_minutes: parseInt(form.querySelector('[name="alert_before_minutes"]').value) || 20
            })
        }).then(function(r){ return r.json(); }).then(function(resp){
            btn.disabled = false;
            if (resp.ok) {
                btn.textContent = <?php echo wp_json_encode( __( 'Saved', 'yourjannah' ) ); ?> + ' \u2713';
                setTimeout(function(){ btn.textContent = <?php echo wp_json_encode( __( 'Save Preferences', 'yourjannah' ) ); ?>; }, 2000);
            } else {
                btn.textContent = <?php echo wp_json_encode( __( 'Save Preferences', 'yourjannah' ) ); ?>;
            }
        }).catch(function(){
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Save Preferences', 'yourjannah' ) ); ?>;
        });
    });

    /* ── Subscription Toggle (PUT) ── */
    window.updateSubPref = function(el) {
        var mosqueId = el.dataset.mosque;
        var pref = el.dataset.pref;
        var body = {};
        body[pref] = el.checked ? 1 : 0;
        fetch(API + 'auth/subscriptions/' + mosqueId, {
            method: 'PUT',
            headers: headers,
            body: JSON.stringify(body)
        });
    };

    /* ── Unsubscribe (DELETE) ── */
    window.unsubMosque = function(mosqueId, btn) {
        if (!confirm(<?php echo wp_json_encode( __( 'Unsubscribe from this mosque?', 'yourjannah' ) ); ?>)) return;
        btn.disabled = true;
        btn.textContent = '...';
        fetch(API + 'auth/subscriptions/' + mosqueId, {
            method: 'DELETE',
            headers: headers
        }).then(function(){
            var row = document.getElementById('sub-' + mosqueId);
            if (row) row.remove();
            /* If no subs left, show empty message */
            var list = document.getElementById('subs-list');
            if (list && !list.querySelector('.ynj-sub-item')) {
                list.innerHTML = '<p class="ynj-empty">' + <?php echo wp_json_encode( __( 'Not subscribed to any mosques yet. Visit a mosque page and tap Subscribe.', 'yourjannah' ) ); ?> + '</p>';
            }
        });
    };

    /* ── Interest Preferences ── */
    window.toggleInterest = function(el) {
        el.classList.toggle('ynj-interest-chip--active');
    };

    /* Load saved interests on page load */
    (function loadInterests() {
        fetch(API + 'auth/interests', {
            method: 'GET',
            headers: headers,
            credentials: 'same-origin'
        }).then(function(r){ return r.json(); }).then(function(resp) {
            if (!resp || !resp.ok) return;
            var data = resp.data || resp;
            /* Pre-check interest chips */
            var cats = data.categories || [];
            cats.forEach(function(slug) {
                var chip = document.querySelector('.ynj-interest-chip[data-interest="' + slug + '"]');
                if (chip) chip.classList.add('ynj-interest-chip--active');
            });
            /* Set radius dropdown */
            if (data.radius) {
                var sel = document.getElementById('interest-radius');
                if (sel) sel.value = String(data.radius);
            }
        }).catch(function(){});
    })();

    /* Save interests */
    document.getElementById('save-interests').addEventListener('click', function() {
        var btn = this;
        var activeChips = document.querySelectorAll('.ynj-interest-chip--active');
        var categories = [];
        activeChips.forEach(function(c){ categories.push(c.dataset.interest); });
        var radius = document.getElementById('interest-radius').value;

        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Saving...', 'yourjannah' ) ); ?>;

        fetch(API + 'auth/interests', {
            method: 'PUT',
            headers: headers,
            credentials: 'same-origin',
            body: JSON.stringify({ categories: categories, radius: radius })
        }).then(function(r){ return r.json(); }).then(function(resp) {
            btn.disabled = false;
            if (resp.ok) {
                btn.textContent = <?php echo wp_json_encode( __( 'Saved', 'yourjannah' ) ); ?> + ' \u2713';
                setTimeout(function(){ btn.textContent = <?php echo wp_json_encode( __( 'Save Interests', 'yourjannah' ) ); ?>; }, 2000);
            } else {
                btn.textContent = <?php echo wp_json_encode( __( 'Save Interests', 'yourjannah' ) ); ?>;
            }
        }).catch(function() {
            btn.disabled = false;
            btn.textContent = <?php echo wp_json_encode( __( 'Save Interests', 'yourjannah' ) ); ?>;
        });
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

<?php
get_footer();
