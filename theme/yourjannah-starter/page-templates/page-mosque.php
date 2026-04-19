<?php
/**
 * Template: Mosque Profile Page
 *
 * Replicates homepage layout for a specific mosque (fixed by URL slug).
 * Same prayer card, patron bar, feed, sponsor ticker — all powered by homepage.js.
 *
 * @package YourJannah
 */

$slug = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_name = $mosque ? $mosque->name : '';
$mosque_address = $mosque ? ( $mosque->address ?? '' ) : '';

// Set cookie BEFORE any output so headers can be sent
if ( $slug && $mosque ) {
    setcookie( 'ynj_mosque_slug', $slug, time() + 365 * DAY_IN_SECONDS, '/' );
}

// ── Admin detection for edit shortcuts ──
$_ynj_is_page_admin = false;
$_ynj_is_page_imam  = false;
if ( $mosque && is_user_logged_in() ) {
    $_wp_uid = get_current_user_id();
    $_user_mosque_id = (int) get_user_meta( $_wp_uid, 'ynj_mosque_id', true );
    $_ynj_is_page_admin = current_user_can( 'manage_options' ) ||
                          ( ( $_user_mosque_id === (int) $mosque->id ) && current_user_can( 'ynj_manage_mosque' ) );
    $_ynj_is_page_imam  = ( $_user_mosque_id === (int) $mosque->id ) &&
                          in_array( 'ynj_imam', (array) wp_get_current_user()->roles, true );
}
$_ynj_can_edit = $_ynj_is_page_admin || $_ynj_is_page_imam;

// ── Quick Post handler (PRG — before any output) ──
$_ynj_posted = '';
if ( $mosque && $_ynj_can_edit && $_SERVER['REQUEST_METHOD'] === 'POST'
     && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_quick_post' ) ) {

    $qp_action = sanitize_text_field( $_POST['qp_action'] ?? '' );

    if ( $qp_action === 'announcement' && class_exists( 'YNJ_Events' ) ) {
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        if ( $title ) {
            $ann_data = [
                'mosque_id'       => (int) $mosque->id,
                'title'           => $title,
                'body'            => sanitize_textarea_field( $_POST['body'] ?? '' ),
                'type'            => sanitize_text_field( $_POST['type'] ?? 'general' ),
                'status'          => 'published',
                'publish'         => true,
                'author_user_id'  => $_wp_uid,
                'author_role'     => $_ynj_is_page_imam && ! $_ynj_is_page_admin ? 'imam' : 'admin',
                'approval_status' => 'approved',
            ];
            // Imam without auto-publish → pending (mosque object has imam_auto_publish via SELECT *)
            if ( $_ynj_is_page_imam && ! $_ynj_is_page_admin ) {
                if ( empty( $mosque->imam_auto_publish ) ) {
                    $ann_data['status']          = 'draft';
                    $ann_data['publish']         = false;
                    $ann_data['published_at']    = current_time( 'mysql' );
                    $ann_data['approval_status'] = 'pending';
                }
            }
            YNJ_Events::create_announcement( $ann_data );
            $_ynj_posted = $ann_data['approval_status'] === 'pending' ? 'pending' : 'announcement';
        }
    }

    if ( $qp_action === 'event' && class_exists( 'YNJ_Events' ) ) {
        $title = sanitize_text_field( $_POST['event_title'] ?? '' );
        $date  = sanitize_text_field( $_POST['event_date'] ?? '' );
        if ( $title && $date ) {
            YNJ_Events::create_event( [
                'mosque_id'   => (int) $mosque->id,
                'title'       => $title,
                'description' => sanitize_textarea_field( $_POST['event_description'] ?? '' ),
                'event_date'  => $date,
                'start_time'  => sanitize_text_field( $_POST['event_start'] ?? '' ),
                'end_time'    => sanitize_text_field( $_POST['event_end'] ?? '' ),
                'location'    => sanitize_text_field( $_POST['event_location'] ?? '' ),
                'event_type'  => sanitize_text_field( $_POST['event_type'] ?? 'community' ),
                'status'      => 'published',
            ] );
            $_ynj_posted = 'event';
        }
    }

    // PRG redirect
    if ( $_ynj_posted ) {
        wp_redirect( home_url( '/mosque/' . $slug . '?posted=' . $_ynj_posted ) );
        exit;
    }
}
// Read posted flash from URL
$_ynj_posted = sanitize_text_field( $_GET['posted'] ?? '' );

get_header();

// ── Fetch prayer times from Aladhan in PHP (server-side, always works) ──
$_ynj_prayer = [];
$_ynj_next_prayer = null;
$_ynj_next_time = '';
$_ynj_next_name = '';
$_ynj_walk_leave = '';
$_ynj_drive_leave = '';
$_ynj_prayer_overview = [];

if ( $mosque && $mosque->latitude ) {
    $lat = (float) $mosque->latitude;
    $lng = (float) $mosque->longitude;
    $today = date( 'd-m-Y' );

    $cache_key = 'ynj_aladhan_' . md5( $lat . $lng . $today );
    $aladhan = get_transient( $cache_key );

    if ( false === $aladhan ) {
        $fail_key = $cache_key . '_fail';
        if ( ! get_transient( $fail_key ) ) {
            $url = "https://api.aladhan.com/v1/timings/{$today}?latitude={$lat}&longitude={$lng}&method=2&school=0";
            $response = wp_remote_get( $url, [ 'timeout' => 3, 'sslverify' => true ] );
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $body['data']['timings'] ) ) {
                    $aladhan = $body['data']['timings'];
                    set_transient( $cache_key, $aladhan, 6 * HOUR_IN_SECONDS );
                }
            }
            if ( ! $aladhan ) set_transient( $fail_key, 1, HOUR_IN_SECONDS );
        }
        // Fallback: use prayer_times table via plugin
        if ( ! $aladhan && class_exists( 'YNJ_Mosques' ) ) {
            $db_times = YNJ_Mosques::get_prayer_times( (int) $mosque->id );
            if ( $db_times ) {
                $aladhan = [ 'Fajr' => $db_times->fajr, 'Sunrise' => $db_times->sunrise, 'Dhuhr' => $db_times->dhuhr, 'Asr' => $db_times->asr, 'Maghrib' => $db_times->maghrib, 'Isha' => $db_times->isha ];
            }
        }
    }

    // Load jamat times + Jumu'ah slots
    $_ynj_jamat = [];
    $_ynj_jumuah_slots = [];
    $_ynj_is_friday = ( date( 'N' ) == 5 );
    if ( $mosque && class_exists( 'YNJ_Mosques' ) ) {
        $db_row = YNJ_Mosques::get_prayer_times( (int) $mosque->id );
        if ( $db_row ) {
            foreach ( [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ] as $pk ) {
                $jk = $pk . '_jamat';
                if ( ! empty( $db_row->$jk ) ) $_ynj_jamat[ $pk ] = substr( $db_row->$jk, 0, 5 );
            }
        }
        if ( $_ynj_is_friday ) {
            $_ynj_jumuah_slots = array_values( array_filter(
                YNJ_Mosques::get_jumuah_times( (int) $mosque->id ),
                function( $j ) { return ! empty( $j->enabled ); }
            ) );
        }
    }

    if ( $aladhan ) {
        $prayer_keys = [ 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ];
        $now = current_time( 'H:i' );
        $walk_buffer = 15;
        $drive_buffer = 5;

        foreach ( $prayer_keys as $p ) {
            $raw = $aladhan[ $p ] ?? '';
            $time = preg_replace( '/\s*\(.*\)/', '', $raw );
            $time = substr( $time, 0, 5 );
            $pk = strtolower( $p );
            $_ynj_prayer[ $pk ] = $time;

            $display_time = isset( $_ynj_jamat[ $pk ] ) ? $_ynj_jamat[ $pk ] : $time;
            $jamat_display = isset( $_ynj_jamat[ $pk ] ) ? $_ynj_jamat[ $pk ] : '';

            if ( $_ynj_is_friday && $p === 'Dhuhr' ) {
                // Friday = always Jumu'ah. Use DB slot time if available, otherwise use Dhuhr time
                if ( ! empty( $_ynj_jumuah_slots ) ) {
                    $jumuah_time = substr( $_ynj_jumuah_slots[0]->salah_time, 0, 5 );
                } else {
                    $jumuah_time = $display_time; // Use Dhuhr/jamat time as Jumu'ah time
                }
                $_ynj_prayer_overview[] = [ 'name' => "Jumu'ah", 'time' => $jumuah_time, 'jamat' => $jumuah_time, 'is_jumuah' => true ];
                if ( ! $_ynj_next_prayer && $jumuah_time > $now ) {
                    $_ynj_next_prayer = "Jumu'ah"; $_ynj_next_time = $jumuah_time; $_ynj_next_name = "Jumu'ah Mubarak 🕌";
                    $prayer_ts = strtotime( 'today ' . $jumuah_time );
                    $_ynj_walk_leave = date( 'H:i', $prayer_ts - ( $walk_buffer * 60 ) );
                    $_ynj_drive_leave = date( 'H:i', $prayer_ts - ( $drive_buffer * 60 ) );
                }
            } else {
                $_ynj_prayer_overview[] = [ 'name' => $p, 'time' => $time, 'jamat' => $jamat_display ];
                if ( $p !== 'Sunrise' && ! $_ynj_next_prayer && $display_time > $now ) {
                    $_ynj_next_prayer = $p; $_ynj_next_time = $display_time; $_ynj_next_name = $p;
                    $prayer_ts = strtotime( 'today ' . $display_time );
                    $_ynj_walk_leave = date( 'H:i', $prayer_ts - ( $walk_buffer * 60 ) );
                    $_ynj_drive_leave = date( 'H:i', $prayer_ts - ( $drive_buffer * 60 ) );
                }
            }
        }

        if ( ! $_ynj_next_prayer ) {
            $_ynj_next_name = 'All prayers completed';
            $_ynj_next_time = 'See you at Fajr tomorrow';
        }
    }
}
?>

<?php
// ── Pre-load ALL data in PHP (eliminates JS API calls) ──
$_mp_id = $mosque ? (int) $mosque->id : 0;
$_mp_jumuah = [];
$_mp_sponsors = [];
$_mp_announcements = [];
$_mp_events = [];
$_mp_classes = [];
$_mp_points = [ 'total' => 0 ];

if ( $_mp_id && class_exists( 'YNJ_DB' ) ) {
    // Jumuah times via plugin (filter to enabled only)
    if ( class_exists( 'YNJ_Mosques' ) ) {
        $_mp_jumuah = array_values( array_filter(
            YNJ_Mosques::get_jumuah_times( $_mp_id ),
            function( $j ) { return ! empty( $j->enabled ); }
        ) );
    }
    // Sponsors (businesses) via plugin
    if ( class_exists( 'YNJ_Directory' ) ) {
        $_mp_sponsors = YNJ_Directory::get_businesses( $_mp_id, [ 'limit' => 20 ] );
    }
    // Community services via plugin
    if ( class_exists( 'YNJ_Directory' ) ) {
        $_mp_services = YNJ_Directory::get_services( $_mp_id, [ 'limit' => 10 ] );
    }
    // Announcements via plugin — returns {announcements: array, total: int}
    // format_announcement returns arrays, but template uses object syntax → cast back
    if ( class_exists( 'YNJ_Events' ) ) {
        $ann_result = YNJ_Events::get_announcements( $_mp_id );
        $_mp_announcements = array_map( function( $a ) { return (object) $a; }, $ann_result['announcements'] ?? [] );
    }

    // Enrich announcements with view counts + reaction counts + per-user state
    $_current_ynj_uid = 0;
    if ( is_user_logged_in() ) {
        $_current_ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
    }
    if ( class_exists( 'YNJ_Engagement' ) ) {
        foreach ( $_mp_announcements as &$_ann ) {
            $_ann->views = YNJ_Engagement::get_view_count( 'announcement', (int) $_ann->id );
            $r_counts = YNJ_Engagement::get_reactions( 'announcement', (int) $_ann->id );
            $_ann->reactions = (object) $r_counts;
            $_ann->user_reacted = [];
            if ( $_current_ynj_uid ) {
                $_ann->user_reacted = YNJ_Engagement::get_user_reactions( $_current_ynj_uid, 'announcement', (int) $_ann->id );
            }
        }
        unset( $_ann );
    }

    // Events via plugin — returns {events: array, total: int}
    if ( class_exists( 'YNJ_Events' ) ) {
        $ev_result = YNJ_Events::get_upcoming_events( $_mp_id );
        $_mp_events = array_map( function( $e ) { return (object) $e; }, $ev_result['events'] ?? [] );
    }
    // Classes via plugin (returns raw objects already)
    if ( class_exists( 'YNJ_Madrassah' ) ) {
        $_mp_classes = YNJ_Madrassah::get_classes( $_mp_id, [ 'limit' => 20 ] );
    }
    // User total points via plugin
    if ( is_user_logged_in() ) {
        $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        if ( $ynj_uid && class_exists( 'YNJ_People' ) ) {
            $_mp_points = [ 'ok' => true, 'total' => YNJ_People::get_total_points( $ynj_uid ) ];
        }
    }
}
?>

<?php if ( ! $mosque ) : ?>
<main class="ynj-main">
    <section class="ynj-card" style="text-align:center;padding:40px 20px;">
        <h1 style="font-size:20px;font-weight:800;margin-bottom:8px;"><?php esc_html_e( 'Mosque Not Found', 'yourjannah' ); ?></h1>
        <p class="ynj-text-muted"><?php esc_html_e( 'This mosque page doesn\'t exist or has been removed.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" class="ynj-btn" style="margin-top:16px;display:inline-flex;"><?php esc_html_e( 'Find a Mosque', 'yourjannah' ); ?></a>
    </section>
</main>
<?php get_footer(); return; endif; ?>

<!-- Set localStorage to this mosque so homepage.js picks it up -->
<script>
localStorage.setItem('ynj_mosque_slug', <?php echo wp_json_encode( $slug ); ?>);
localStorage.setItem('ynj_mosque_name', <?php echo wp_json_encode( $mosque_name ); ?>);
</script>

<?php
// ── Membership status check ──
$_ynj_is_member = false;
$_ynj_is_primary = false;
// Live member count via plugin (includes +1 for admin)
$_ynj_member_count = 1;
if ( $mosque && class_exists( 'YNJ_Mosques' ) ) {
    $_ynj_member_count = YNJ_Mosques::get_member_count( (int) $mosque->id );
}
if ( $mosque && is_user_logged_in() && class_exists( 'YNJ_Mosques' ) ) {
    $ynj_uid_check = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
    if ( $ynj_uid_check ) {
        $membership = YNJ_Mosques::get_user_subscription( $ynj_uid_check, (int) $mosque->id );
        if ( $membership ) {
            $_ynj_is_member = (bool) $membership->is_member;
            $_ynj_is_primary = (bool) $membership->is_primary;
        }
    }
}
?>

<main class="ynj-main">
  <div class="ynj-desktop-grid">
    <div class="ynj-desktop-grid__left">

    <!-- Follow This Masjid + Follower Count -->
    <div class="ynj-join-bar" style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff;border-radius:14px;padding:12px 16px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:18px;">🕌</span>
            <span style="font-size:14px;font-weight:600;color:#333;">
                <?php echo number_format( $_ynj_member_count ); ?> <?php echo $_ynj_member_count === 1 ? 'follower' : 'followers'; ?>
            </span>
        </div>
        <?php if ( $_ynj_is_member ) : ?>
            <div style="display:flex;align-items:center;gap:8px;">
                <?php if ( $_ynj_is_primary ) : ?>
                    <span style="font-size:11px;color:#666;background:#f0f0f0;padding:2px 8px;border-radius:12px;"><?php esc_html_e( 'Primary', 'yourjannah' ); ?></span>
                <?php else : ?>
                    <button onclick="ynjSetPrimary(<?php echo (int) $mosque->id; ?>)" style="font-size:11px;color:#00ADEF;background:none;border:1px solid #00ADEF;padding:2px 8px;border-radius:12px;cursor:pointer;"><?php esc_html_e( 'Set as Primary', 'yourjannah' ); ?></button>
                <?php endif; ?>
                <span style="color:#27ae60;font-weight:600;font-size:13px;">&#x2713; <?php esc_html_e( 'Following', 'yourjannah' ); ?></span>
                <button onclick="ynjLeaveMosque(<?php echo (int) $mosque->id; ?>)" style="font-size:11px;color:#999;background:none;border:none;cursor:pointer;text-decoration:underline;"><?php esc_html_e( 'Unfollow', 'yourjannah' ); ?></button>
            </div>
        <?php elseif ( is_user_logged_in() ) : ?>
            <button onclick="ynjJoinMosque(<?php echo (int) $mosque->id; ?>)" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;">
                <?php esc_html_e( 'Follow This Masjid', 'yourjannah' ); ?>
            </button>
        <?php else : ?>
            <button onclick="ynjShowJoinLogin()" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;">
                <?php esc_html_e( 'Follow This Masjid', 'yourjannah' ); ?>
            </button>
        <?php endif; ?>
    </div>



    <!-- Ramadan banner (shown automatically during Ramadan) -->
    <div id="ramadan-banner" style="display:none;background:linear-gradient(135deg,#1a1628,#2d1b69);color:#fff;border-radius:14px;padding:14px 18px;margin-bottom:10px;"></div>

    <!-- Patron Membership -->
    <?php
    $_patron_status = null;
    if ( is_user_logged_in() && $mosque && class_exists( 'YNJ_Donations' ) ) {
        $_p_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        if ( $_p_uid ) {
            $_patron_status = YNJ_Donations::get_patron_status( $_p_uid, (int) $mosque->id );
        }
    }
    $patron_tiers = class_exists( 'YNJ_API_Patrons' ) ? YNJ_API_Patrons::get_tiers() : [];
    ?>
    <?php if ( $_patron_status ) : ?>
    <div class="ynj-patron-bar" id="patron-hero" style="background:linear-gradient(135deg,#287e61,#1a5c43) !important;">
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-bar__label">🏅 <strong><?php printf( esc_html__( 'You\'re a %s Patron — JazakAllah Khayr', 'yourjannah' ), esc_html( $patron_tiers[ $_patron_status->tier ]['label'] ?? ucfirst( $_patron_status->tier ) ) ); ?></strong></a>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip" style="background:rgba(255,255,255,.2);"><?php esc_html_e( 'Manage', 'yourjannah' ); ?></a>
    </div>
    <?php else : ?>
    <div class="ynj-patron-bar" id="patron-hero">
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-bar__label">🏅 <strong id="patron-bar-text"><?php printf( esc_html__( 'Become a Patron of %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></strong></a>
        <div class="ynj-patron-bar__tiers">
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip">£5</a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip">£10</a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip ynj-patron-chip--popular"><span class="ynj-patron-chip__pop"><?php esc_html_e( 'Popular', 'yourjannah' ); ?></span>£20</a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip">£50</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sponsor Ticker -->
    <div class="ynj-ticker" id="sponsor-ticker" style="display:none;">
        <span class="ynj-ticker__label">⭐ <?php esc_html_e( 'Sponsors', 'yourjannah' ); ?></span>
        <div class="ynj-ticker__track">
            <div class="ynj-ticker__slide" id="ticker-content"></div>
        </div>
    </div>

    <!-- Travel Settings -->
    <div class="ynj-travel-settings" id="travel-settings" style="display:none;">
        <div class="ynj-travel-settings__row">
            <select id="mode-select" class="ynj-ts-select" onchange="onModeChange()">
                <option value="walk">🚶 <?php esc_html_e( 'Walk', 'yourjannah' ); ?></option>
                <option value="drive">🚗 <?php esc_html_e( 'Drive', 'yourjannah' ); ?></option>
                <option value="bike">🚲 <?php esc_html_e( 'Cycle', 'yourjannah' ); ?></option>
            </select>
            <select id="buffer-select" class="ynj-ts-select" onchange="onBufferChange()">
                <option value="0"><?php esc_html_e( 'No buffer', 'yourjannah' ); ?></option>
                <option value="5">+5 min wudhu</option>
                <option value="10" selected>+10 min wudhu</option>
                <option value="15">+15 min prep</option>
                <option value="20">+20 min prep</option>
            </select>
        </div>
    </div>

    <!-- Next Prayer Hero (PHP-rendered from Aladhan) -->
    <section class="ynj-card ynj-card--hero" id="next-prayer-card" style="position:relative;">
        <?php if ( $_ynj_can_edit ) : ?>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=prayers' ) ); ?>" class="ynj-admin-edit" title="<?php esc_attr_e( 'Edit Prayer Times', 'yourjannah' ); ?>">✏️</a>
        <?php endif; ?>
        <?php
        // Arabic prayer names
        $_arabic_names = [
            'Fajr' => 'الفجر', 'Sunrise' => 'الشروق', 'Dhuhr' => 'الظهر',
            'Asr' => 'العصر', 'Maghrib' => 'المغرب', 'Isha' => 'العشاء',
            "Jumu'ah" => 'الجمعة',
        ];
        $_arabic_prayer = $_arabic_names[ $_ynj_next_name ] ?? '';

        // Hijri date via IntlDateFormatter if available
        $_hijri_date = '';
        if ( class_exists( 'IntlDateFormatter' ) ) {
            $fmt = new IntlDateFormatter( 'ar_SA@calendar=islamic-civil', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'UTC', IntlDateFormatter::TRADITIONAL );
            $_hijri_date = $fmt->format( time() );
        }
        ?>
        <?php if ( $_hijri_date ) : ?>
        <p style="text-align:center;font-size:12px;color:rgba(255,255,255,.5);margin-bottom:4px;font-family:'Amiri',serif;direction:rtl;"><?php echo esc_html( $_hijri_date ); ?></p>
        <?php endif; ?>
        <?php if ( $_ynj_is_friday && strpos( $_ynj_next_name, "Jumu'ah" ) !== false ) : ?>
        <p class="ynj-label" id="next-prayer-label" style="color:#fbbf24;">🕌 <?php echo esc_html( 'It\'s Friday! Jumu\'ah at ' . $mosque_name ); ?></p>
        <?php else : ?>
        <p class="ynj-label" id="next-prayer-label"><?php esc_html_e( 'NEXT PRAYER AT', 'yourjannah' ); ?> <?php echo esc_html( strtoupper( $mosque_name ) ); ?></p>
        <?php endif; ?>
        <h2 class="ynj-hero-prayer" id="next-prayer-name"><?php echo esc_html( $_ynj_next_name ?: '—' ); ?></h2>
        <?php if ( $_arabic_prayer ) : ?>
        <p style="font-family:'Amiri','Traditional Arabic',serif;font-size:20px;color:rgba(255,255,255,.6);margin-top:-4px;direction:rtl;"><?php echo esc_html( $_arabic_prayer ); ?></p>
        <?php endif; ?>
        <p class="ynj-hero-time" id="next-prayer-time"><?php echo esc_html( $_ynj_next_time ?: '—' ); ?></p>
        <?php if ( $_ynj_is_friday && ! empty( $_ynj_jumuah_slots ) && count( $_ynj_jumuah_slots ) > 0 ) : ?>
        <div id="jumuah-slots" style="margin:10px 0;width:100%;">
            <?php foreach ( $_ynj_jumuah_slots as $idx => $js ) :
                $is_active = ( $idx === 0 );
            ?>
            <div class="ynj-jumuah-slot<?php echo $is_active ? ' active' : ''; ?>" onclick="selectJumuahSlot(this,'<?php echo esc_attr( substr( $js->salah_time, 0, 5 ) ); ?>')" data-salah="<?php echo esc_attr( substr( $js->salah_time, 0, 5 ) ); ?>" style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,<?php echo $is_active ? '.2' : '.08'; ?>);border:2px solid <?php echo $is_active ? 'rgba(255,255,255,.4)' : 'transparent'; ?>;border-radius:10px;padding:8px 14px;margin-bottom:6px;cursor:pointer;transition:all .15s;">
                <span style="display:block;font-size:13px;font-weight:600;"><?php echo esc_html( $js->slot_name ?: 'Jumu\'ah' ); ?></span>
                <span style="display:block;font-size:12px;opacity:.7;"><?php if ( $js->khutbah_time ) : ?>Khutbah <?php echo esc_html( substr( $js->khutbah_time, 0, 5 ) ); ?> · <?php endif; ?>Salah <strong style="font-size:14px;"><?php echo esc_html( substr( $js->salah_time, 0, 5 ) ); ?></strong></span>
                <span style="display:block;font-size:11px;opacity:.5;"><?php echo esc_html( $js->language ?: '' ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="ynj-countdown" id="next-prayer-countdown">--:--:--</div>
        <?php if ( $_ynj_walk_leave ) : ?>
        <div class="ynj-hero-travel" id="hero-travel">
            <div style="display:flex;gap:8px;justify-content:center;width:100%;">
                <div class="ynj-leave-by" id="leave-by-walk">
                    <span>🚶</span>
                    <span id="leave-by-walk-text"><?php echo esc_html( 'Leave ' . $_ynj_walk_leave ); ?></span>
                </div>
                <div class="ynj-leave-by" id="leave-by-drive">
                    <span>🚗</span>
                    <span id="leave-by-drive-text"><?php echo esc_html( 'Leave ' . $_ynj_drive_leave ); ?></span>
                </div>
            </div>
        </div>
        <?php else : ?>
        <div class="ynj-hero-travel" id="hero-travel" style="display:none;">
            <div style="display:flex;gap:8px;justify-content:center;width:100%;">
                <div class="ynj-leave-by" id="leave-by-walk"><span>🚶</span><span id="leave-by-walk-text">Leave --:--</span></div>
                <div class="ynj-leave-by" id="leave-by-drive"><span>🚗</span><span id="leave-by-drive-text">Leave --:--</span></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="ynj-hero-actions">
            <div class="ynj-hero-gps" id="hero-gps-prompt">
                <button class="ynj-hero-locate" id="hero-gps-btn" type="button" onclick="requestGps()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                    <?php esc_html_e( 'Detect Location', 'yourjannah' ); ?>
                </button>
            </div>
            <div class="ynj-nav-buttons" id="nav-buttons" style="display:none;">
                <a class="ynj-hero-nav" id="navigate-walk" href="#" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="2"/><path d="M10 22l2-7 3 3v7M14 13l2-3-3-3-2 4"/></svg>
                    <?php esc_html_e( 'Walk', 'yourjannah' ); ?>
                </a>
                <a class="ynj-hero-nav" id="navigate-drive" href="#" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17h14M7 11l2-5h6l2 5M4 17v-3a1 1 0 011-1h14a1 1 0 011 1v3"/><circle cx="7.5" cy="17" r="1.5"/><circle cx="16.5" cy="17" r="1.5"/></svg>
                    <?php esc_html_e( 'Drive', 'yourjannah' ); ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Prayer Overview (PHP-rendered) -->
    <?php if ( ! empty( $_ynj_prayer_overview ) ) : ?>
    <section class="ynj-card ynj-card--compact" id="prayer-overview" style="padding:14px 18px;">
        <div class="ynj-prayer-overview" id="prayer-overview-grid">
        <?php foreach ( $_ynj_prayer_overview as $po ) :
            if ( $po['name'] === 'Sunrise' ) continue;
            $is_next = ( strtolower( $po['name'] ) === strtolower( $_ynj_next_name ) );
            $jamat = $po['jamat'] ?? '';
            $is_jumuah = ! empty( $po['is_jumuah'] );
        ?>
            <div class="ynj-po-item<?php echo $is_next ? ' ynj-po-item--active' : ''; ?>">
                <span class="ynj-po-name"><?php echo esc_html( $po['name'] ); ?></span>
                <span class="ynj-po-time"><?php echo esc_html( $jamat ?: $po['time'] ); ?></span>
                <?php if ( $jamat && ! $is_jumuah && $is_next ) : ?>
                <span style="font-size:9px;color:rgba(255,255,255,.6);display:block;"><?php esc_html_e( 'Iqamah', 'yourjannah' ); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </section>
    <?php else : ?>
    <section class="ynj-card ynj-card--compact" id="prayer-overview" style="display:none;padding:14px 18px;">
        <div class="ynj-prayer-overview" id="prayer-overview-grid"></div>
    </section>
    <?php endif; ?>

    <!-- Jumu'ah Times (always visible if slots exist) -->
    <?php if ( ! empty( $_ynj_jumuah_slots ) ) : ?>
    <section class="ynj-card" style="padding:14px 16px;margin-bottom:10px;">
        <h3 style="font-size:14px;font-weight:800;margin:0 0 10px;color:#0a1628;">🕌 <?php esc_html_e( 'Jumu\'ah', 'yourjannah' ); ?></h3>
        <?php foreach ( $_ynj_jumuah_slots as $js ) : ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f8fafc;border-radius:10px;margin-bottom:6px;">
            <span style="font-size:13px;font-weight:700;color:#0a1628;"><?php echo esc_html( $js->slot_name ?: "Jumu'ah" ); ?></span>
            <div style="text-align:right;">
                <?php if ( $js->khutbah_time ) : ?>
                <span style="font-size:11px;color:#6b8fa3;">Khutbah <?php echo esc_html( substr( $js->khutbah_time, 0, 5 ) ); ?></span>
                <span style="color:#d1d5db;margin:0 4px;">·</span>
                <?php endif; ?>
                <span style="font-size:14px;font-weight:800;color:#287e61;">Salah <?php echo esc_html( substr( $js->salah_time, 0, 5 ) ); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- ═══ DHIKR CTA — Personal progress + urgency ═══ -->
    <?php if ( is_user_logged_in() && $mosque ) :
        $_cta_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        $_cta_streak = 0;
        $_cta_done = 0;
        if ( $_cta_uid ) {
            // Streak via plugin
            if ( class_exists( 'YNJ_Streaks' ) ) {
                $_cta_streak = YNJ_Streaks::get_user_streak( $_cta_uid );
            }
            // Done count today (transient-based, no DB)
            for ( $i = 0; $i < 5; $i++ ) {
                if ( get_transient( 'ynj_dhikr_' . $_cta_uid . '_' . date( 'Y-m-d' ) . '_' . $i ) ) $_cta_done++;
            }
        }
        $_cta_hours = 24 - (int) date( 'G' );
        $_cta_complete = $_cta_done >= 5;
        $_cta_bg = $_cta_complete ? 'background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;'
            : ( $_cta_hours <= 3 ? 'background:linear-gradient(135deg,#fef2f2,#fee2e2);border:2px solid #ef4444;animation:ynj-urgency-pulse 1s infinite;'
            : 'background:linear-gradient(135deg,#fefce8,#fef9c3);border:2px solid #fde68a;' );
    ?>
    <style>.ynj-cta-pulse{animation:ynj-urgency-pulse 2s infinite;}@keyframes ynj-urgency-pulse{0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.2);}50%{box-shadow:0 0 0 6px rgba(245,158,11,.1);}}</style>
    <a href="<?php echo esc_url( home_url( '/profile#ibadah' ) ); ?>" class="ynj-card" style="display:block;text-decoration:none;padding:16px;<?php echo $_cta_bg; ?>">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <?php if ( $_cta_complete ) : ?>
                    <div style="font-size:14px;font-weight:800;color:#166534;">&#x2705; <?php esc_html_e( 'All 5 dhikr done today!', 'yourjannah' ); ?></div>
                    <div style="font-size:12px;color:#15803d;"><?php esc_html_e( 'MashaAllah! Come back tomorrow.', 'yourjannah' ); ?></div>
                <?php elseif ( $_cta_done > 0 ) : ?>
                    <div style="font-size:14px;font-weight:800;color:#92400e;">&#x1F3AF; <?php printf( esc_html__( '%d of 5 done — %d more for +200 bonus!', 'yourjannah' ), $_cta_done, 5 - $_cta_done ); ?></div>
                    <div style="font-size:12px;color:#a16207;"><?php echo (int) $_cta_hours; ?>h <?php esc_html_e( 'left', 'yourjannah' ); ?> <?php if ( $_cta_streak > 0 ) : ?>&middot; &#x1F525; <?php echo (int) $_cta_streak; ?> <?php esc_html_e( 'day streak', 'yourjannah' ); ?><?php endif; ?></div>
                <?php else : ?>
                    <div style="font-size:14px;font-weight:800;color:<?php echo $_cta_hours <= 3 ? '#991b1b' : '#92400e'; ?>;">&#x1F4FF; <?php esc_html_e( 'Say your daily dhikr', 'yourjannah' ); ?></div>
                    <div style="font-size:12px;color:<?php echo $_cta_hours <= 3 ? '#b91c1c' : '#a16207'; ?>;"><?php echo (int) $_cta_hours; ?>h <?php esc_html_e( 'left', 'yourjannah' ); ?> &middot; 5 dhikr = <?php esc_html_e( '+200 bonus pts', 'yourjannah' ); ?></div>
                <?php endif; ?>
            </div>
            <div style="font-size:24px;font-weight:900;color:<?php echo $_cta_complete ? '#166534' : '#d97706'; ?>;"><?php echo $_cta_done; ?>/5</div>
        </div>
        <?php if ( ! $_cta_complete ) : ?>
        <div style="height:4px;background:rgba(0,0,0,.06);border-radius:2px;margin-top:10px;overflow:hidden;">
            <div style="height:100%;width:<?php echo $_cta_done * 20; ?>%;background:linear-gradient(90deg,#287e61,#34d399);border-radius:2px;"></div>
        </div>
        <?php endif; ?>
    </a>
    <?php endif; ?>


    <!-- ═══ HEAD-TO-HEAD CHALLENGE ═══ -->
    <?php if ( $mosque && function_exists( 'ynj_get_h2h_challenge' ) ) :
        $h2h = ynj_get_h2h_challenge( (int) $mosque->id );
        if ( $h2h ) :
    ?>
    <section class="ynj-card" style="padding:16px;background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff;border:none;text-align:center;">
        <p style="font-size:10px;text-transform:uppercase;letter-spacing:1.5px;opacity:.7;margin:0 0 6px;"><?php esc_html_e( 'This Week\'s Challenge', 'yourjannah' ); ?></p>
        <h3 style="font-size:16px;font-weight:800;color:#fff;margin:0 0 12px;">⚔️ <?php echo esc_html( $mosque_name ); ?> vs <?php echo esc_html( $h2h['opponent'] ); ?></h3>
        <div style="display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:8px;">
            <div style="text-align:center;flex:1;">
                <div style="font-size:28px;font-weight:800;"><?php echo number_format( $h2h['my_score'] ); ?></div>
                <div style="font-size:10px;opacity:.7;"><?php esc_html_e( 'Us', 'yourjannah' ); ?></div>
            </div>
            <div style="font-size:20px;font-weight:800;opacity:.5;">vs</div>
            <div style="text-align:center;flex:1;">
                <div style="font-size:28px;font-weight:800;"><?php echo number_format( $h2h['their_score'] ); ?></div>
                <div style="font-size:10px;opacity:.7;"><?php echo esc_html( $h2h['opponent'] ); ?></div>
            </div>
        </div>
        <p style="font-size:12px;margin:0;opacity:.8;">
            <?php if ( $h2h['winning'] ) : ?>
                🏆 <?php esc_html_e( 'We\'re winning!', 'yourjannah' ); ?>
            <?php elseif ( $h2h['tied'] ) : ?>
                ⚖️ <?php esc_html_e( 'It\'s tied!', 'yourjannah' ); ?>
            <?php else : ?>
                💪 <?php esc_html_e( 'We\'re behind — engage more to win!', 'yourjannah' ); ?>
            <?php endif; ?>
            · <?php echo $h2h['days_left']; ?> <?php esc_html_e( 'days left', 'yourjannah' ); ?>
        </p>
    </section>
    <?php endif; endif; ?>



    <!-- ═══ GRATITUDE ═══ -->
    <?php if ( $mosque && is_user_logged_in() ) : ?>
    <button type="button" onclick="ynjPostGratitude()" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;background:linear-gradient(135deg,#fdf2f8,#fce7f3);border:1px solid #f9a8d4;border-radius:14px;font-size:14px;font-weight:700;color:#9d174d;cursor:pointer;font-family:inherit;margin-bottom:10px;">💖 <?php esc_html_e( 'Thank Your Mosque', 'yourjannah' ); ?></button>
    <?php endif; ?>

    <!-- ═══ PURIFY YOUR RIZQ — Daily sadaqah habit ═══ -->
    <?php if ( $mosque && is_user_logged_in() ) :
        $_sadaqah_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        // Check if user donated sadaqah today (donations table)
        $_sadaqah_done_today = false;
        $_sadaqah_streak = 0;
        if ( $_sadaqah_uid && class_exists( 'YNJ_DB' ) ) {
            global $wpdb;
            $dt = YNJ_DB::table( 'donations' );
            $_sadaqah_done_today = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $dt WHERE donor_email = %s AND fund_type = 'sadaqah' AND status = 'succeeded' AND DATE(created_at) = CURDATE()",
                $wp_user->user_email ?? ''
            ) );
            // Sadaqah streak (consecutive days)
            $sadaqah_dates = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT DATE(created_at) AS d FROM $dt WHERE donor_email = %s AND fund_type = 'sadaqah' AND status = 'succeeded' ORDER BY d DESC LIMIT 120",
                $wp_user->user_email ?? ''
            ) );
            $expected = date( 'Y-m-d' );
            foreach ( $sadaqah_dates as $sd ) {
                if ( $sd === $expected ) { $_sadaqah_streak++; $expected = date( 'Y-m-d', strtotime( "$expected -1 day" ) ); }
                elseif ( $_sadaqah_streak === 0 && $sd === date( 'Y-m-d', strtotime( '-1 day' ) ) ) { $_sadaqah_streak = 1; $expected = date( 'Y-m-d', strtotime( "$sd -1 day" ) ); }
                else break;
            }
        }
    ?>
    <div id="purify-rizq" class="ynj-card" style="padding:16px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:2px solid #6ee7b7;<?php if ( $_sadaqah_done_today ) echo 'display:none;'; ?>">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div>
                <div style="font-size:15px;font-weight:800;color:#065f46;">&#x1F4B0; <?php esc_html_e( 'Purify Your Rizq', 'yourjannah' ); ?></div>
                <div style="font-size:12px;color:#047857;"><?php esc_html_e( 'A small sadaqah each day cleanses your wealth', 'yourjannah' ); ?></div>
            </div>
            <?php if ( $_sadaqah_streak > 0 ) : ?>
            <div style="text-align:center;background:#065f46;color:#fff;border-radius:12px;padding:4px 10px;">
                <div style="font-size:16px;font-weight:800;">&#x1F525; <?php echo (int) $_sadaqah_streak; ?></div>
                <div style="font-size:9px;text-transform:uppercase;letter-spacing:.5px;"><?php esc_html_e( 'days', 'yourjannah' ); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;">
            <?php foreach ( [ 100 => '£1', 300 => '£3', 500 => '£5' ] as $pence => $label ) : ?>
            <button onclick="ynjPurifySadaqah(<?php echo $pence; ?>)" style="flex:1;padding:12px 0;border-radius:12px;border:2px solid #10b981;background:#fff;color:#065f46;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit;transition:all .15s;">
                <?php echo esc_html( $label ); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:#047857;margin:8px 0 0;text-align:center;">
            <?php esc_html_e( 'Distributed to dawah, masjid building & international aid', 'yourjannah' ); ?>
        </p>
    </div>
    <?php if ( $_sadaqah_done_today ) : ?>
    <div class="ynj-card" style="padding:14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;text-align:center;">
        <div style="font-size:14px;font-weight:700;color:#166534;">&#x2705; <?php esc_html_e( 'Sadaqah given today — Barakallahu feek!', 'yourjannah' ); ?></div>
        <?php if ( $_sadaqah_streak > 1 ) : ?>
        <div style="font-size:12px;color:#15803d;margin-top:4px;">&#x1F525; <?php printf( esc_html__( '%d day streak — keep it going!', 'yourjannah' ), $_sadaqah_streak ); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <script>
    function ynjPurifySadaqah(amountPence) {
        const btn = event.currentTarget;
        btn.disabled = true;
        btn.textContent = '...';
        fetch('<?php echo esc_url( rest_url( 'ynj/v1/checkout/donate' ) ); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>' },
            body: JSON.stringify({
                amount_pence: amountPence,
                mosque_id: <?php echo (int) $mosque->id; ?>,
                fund_type: 'sadaqah',
                name: <?php echo wp_json_encode( wp_get_current_user()->display_name ); ?>,
                email: <?php echo wp_json_encode( wp_get_current_user()->user_email ); ?>,
                cause: 'sadaqah'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.url) {
                window.location.href = data.url;
            } else {
                btn.disabled = false;
                btn.textContent = '£' + (amountPence / 100);
                alert(data.error || 'Something went wrong');
            }
        })
        .catch(() => { btn.disabled = false; btn.textContent = '£' + (amountPence / 100); });
    }
    </script>

    <!-- Hadith -->
    <p class="ynj-hadith" id="hadith-line">
        <em>&ldquo;<?php esc_html_e( 'Prayer in congregation is twenty-seven times more virtuous than prayer offered alone.', 'yourjannah' ); ?>&rdquo;</em>
        <span>&mdash; Sahih al-Bukhari 645</span>
    </p>

    <!-- Donate button -->
    <a class="ynj-donate-btn" id="donate-btn" href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/fundraising' ) ); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        <?php esc_html_e( 'Donate to Masjid', 'yourjannah' ); ?>
    </a>

    <!-- Check-in + Points (logged-in users only) -->
    <div id="ynj-points-card" style="display:none;">
        <div style="display:flex;gap:8px;align-items:stretch;margin-bottom:10px;">
            <button id="checkin-btn" onclick="doCheckIn()" style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border-radius:12px;border:none;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;">
                📍 <?php esc_html_e( 'Check In', 'yourjannah' ); ?>
            </button>
            <div id="points-display" style="display:flex;align-items:center;gap:6px;padding:12px 16px;border-radius:12px;background:linear-gradient(135deg,#fef3c7,#fde68a);min-width:90px;justify-content:center;">
                <span style="font-size:18px;">⭐</span>
                <div>
                    <div id="points-total" style="font-size:18px;font-weight:900;color:#92400e;line-height:1;">0</div>
                    <div style="font-size:9px;font-weight:600;color:#92400e;text-transform:uppercase;">points</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Timetable link -->
    <a class="ynj-timetable-link" id="timetable-link" href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/prayers' ) ); ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <?php esc_html_e( 'View Full Timetable', 'yourjannah' ); ?>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </a>

    <!-- Support your masjid CTAs -->
    <div class="ynj-support-row">
        <a class="ynj-support-card ynj-support-card--sponsor" id="cta-sponsor" href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors' ) ); ?>">
            <span class="ynj-support-card__icon">⭐</span>
            <strong><?php esc_html_e( 'Sponsor Your Masjid', 'yourjannah' ); ?></strong>
            <span class="ynj-support-card__sub"><?php esc_html_e( 'List your business — reach the community', 'yourjannah' ); ?></span>
            <span class="ynj-support-card__help" id="cta-sponsor-help"><?php printf( esc_html__( 'Funds go to supporting %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></span>
        </a>
        <a class="ynj-support-card ynj-support-card--services" id="cta-services" href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services' ) ); ?>">
            <span class="ynj-support-card__icon">🤝</span>
            <strong><?php esc_html_e( 'Advertise Services', 'yourjannah' ); ?></strong>
            <span class="ynj-support-card__sub"><?php esc_html_e( 'Professionals — get found locally', 'yourjannah' ); ?></span>
            <span class="ynj-support-card__help" id="cta-services-help"><?php printf( esc_html__( 'Proceeds help fund %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></span>
        </a>
    </div>

    <!-- People / Service Listings — rotating 5 -->
    <?php if ( ! empty( $_mp_services ) ) :
        $display_services = array_slice( $_mp_services, 0, 5 );
    ?>
    <div style="margin-top:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h3 style="font-size:13px;font-weight:700;color:#0a1628;margin:0;">🤝 <?php esc_html_e( 'Local Professionals', 'yourjannah' ); ?></h3>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors' ) ); ?>" style="font-size:11px;font-weight:600;color:#00ADEF;text-decoration:none;"><?php esc_html_e( 'View All →', 'yourjannah' ); ?></a>
        </div>
        <?php foreach ( $display_services as $svc ) :
            $rate = $svc->hourly_rate_pence ? '£' . number_format( $svc->hourly_rate_pence / 100, 0 ) . '/hr' : '';
            $initial = strtoupper( substr( $svc->provider_name ?: '?', 0, 1 ) );
        ?>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/service/' . $svc->id ) ); ?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:4px;border-radius:10px;background:#fff;border:1px solid #e5e7eb;text-decoration:none;color:#0a1628;transition:all .15s;">
            <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;"><?php echo esc_html( $initial ); ?></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( $svc->provider_name ); ?></div>
                <div style="font-size:11px;color:#6b8fa3;"><?php echo esc_html( $svc->service_type ); ?><?php if ( $svc->area_covered ) echo ' · ' . esc_html( $svc->area_covered ); ?></div>
            </div>
            <?php if ( $rate ) : ?>
            <div style="font-size:12px;font-weight:700;color:#16a34a;flex-shrink:0;"><?php echo esc_html( $rate ); ?></div>
            <?php endif; ?>
            <?php if ( $svc->phone ) : ?>
            <span onclick="event.preventDefault();event.stopPropagation();window.location.href='tel:<?php echo esc_attr( $svc->phone ); ?>'" style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;background:#e8f4f8;font-size:14px;flex-shrink:0;cursor:pointer;">📞</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/services/join' ) ); ?>" style="display:block;text-align:center;padding:8px;margin-top:4px;border-radius:8px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;font-size:12px;font-weight:700;text-decoration:none;">🤝 <?php esc_html_e( 'List Your Service — from £10/mo', 'yourjannah' ); ?></a>
    </div>
    <?php endif; ?>

    <!-- ═══ MOSQUE LEAGUE TABLE — at bottom of left column ═══ -->
    <?php if ( $mosque && function_exists( 'ynj_get_league_standings' ) ) :
        $league = ynj_get_league_standings( (int) $mosque->id, $mosque->city ?? null, 7 );
        if ( $league['total'] > 0 ) :
    ?>
    <section class="ynj-card" id="mosque-league-table" style="padding:0;overflow:hidden;border:1px solid #d1fae5;border-radius:16px;margin-top:12px;">
        <!-- League Header — Emerald -->
        <div style="padding:18px 20px;background:linear-gradient(135deg,#287e61,#1a5c43);color:#fff;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h3 style="font-size:18px;font-weight:800;color:#fff;margin:0;letter-spacing:-.3px;"><?php echo esc_html( $league['tier']['name'] ); ?> <?php esc_html_e( 'League', 'yourjannah' ); ?></h3>
                    <p style="font-size:12px;color:rgba(255,255,255,.6);margin:4px 0 0;">
                        <?php echo $league['tier']['min']; ?>–<?php echo $league['tier']['max'] < 999999 ? number_format( $league['tier']['max'] ) : '∞'; ?> <?php esc_html_e( 'members', 'yourjannah' ); ?>
                        · <?php esc_html_e( 'This week', 'yourjannah' ); ?>
                        <?php if ( $mosque->city ) : ?> · <?php echo esc_html( $mosque->city ); ?><?php endif; ?>
                    </p>
                </div>
                <?php if ( $league['rank'] > 0 ) : ?>
                <div style="font-size:32px;font-weight:900;color:#34d399;line-height:1;">#<?php echo $league['rank']; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- League Table -->
        <?php if ( ! empty( $league['top_5'] ) ) : ?>
        <div style="padding:4px 0;">
            <?php
            $medals = [ '🥇', '🥈', '🥉' ];
            foreach ( $league['top_5'] as $i => $tm ) :
                $is_me = ( (int) $tm->id === (int) $mosque->id );
                $pos = $i + 1;
                $bar_w = $league['top_5'][0]->per_member > 0 ? min( 100, round( (float) $tm->per_member / (float) $league['top_5'][0]->per_member * 100 ) ) : 0;
            ?>
            <div style="display:flex;align-items:center;gap:12px;padding:12px 20px;<?php echo $is_me ? 'background:#ecfdf5;' : ''; ?>">
                <span style="font-size:18px;min-width:28px;text-align:center;font-weight:800;color:#287e61;"><?php echo $medals[ $i ] ?? $pos; ?></span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:14px;font-weight:<?php echo $is_me ? '800' : '600'; ?>;color:#0a1628;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo esc_html( $tm->name ); ?>
                        <?php if ( $is_me ) : ?><span style="font-size:10px;background:#287e61;color:#fff;padding:2px 8px;border-radius:6px;margin-left:6px;font-weight:700;"><?php esc_html_e( 'YOU', 'yourjannah' ); ?></span><?php endif; ?>
                    </div>
                    <div style="margin-top:6px;background:#e5e7eb;border-radius:4px;height:6px;overflow:hidden;">
                        <div style="background:linear-gradient(90deg,#287e61,#34d399);height:100%;width:<?php echo $bar_w; ?>%;border-radius:4px;transition:width .6s;"></div>
                    </div>
                </div>
                <div style="text-align:right;white-space:nowrap;">
                    <div style="font-size:15px;font-weight:800;color:#287e61;"><?php echo number_format( (float) $tm->per_member, 1 ); ?></div>
                    <div style="font-size:9px;color:#6b8fa3;font-weight:600;"><?php esc_html_e( 'pts/member', 'yourjannah' ); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; endif; ?>

    <!-- ═══ MY BADGES — at bottom of left column ═══ -->
    <?php if ( is_user_logged_in() && $mosque && function_exists( 'ynj_get_user_badges' ) ) :
        $ynj_uid_badges = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        $my_badges = $ynj_uid_badges ? ynj_get_user_badges( $ynj_uid_badges ) : [];
        $all_badges = function_exists( 'ynj_get_badge_definitions' ) ? ynj_get_badge_definitions() : [];
        if ( ! empty( $all_badges ) ) :
    ?>
    <section class="ynj-card" style="padding:16px;margin-top:12px;">
        <h3 style="font-size:14px;font-weight:800;margin:0 0 10px;">🏅 <?php esc_html_e( 'My Badges', 'yourjannah' ); ?>
            <span style="font-size:12px;font-weight:500;color:#6b8fa3;margin-left:4px;"><?php echo count( $my_badges ); ?>/<?php echo count( $all_badges ); ?></span>
        </h3>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php
            $earned_keys = array_column( $my_badges, 'badge_key' );
            foreach ( $all_badges as $bd ) :
                $earned = in_array( $bd['key'], $earned_keys, true );
            ?>
            <div title="<?php echo esc_attr( $bd['name'] . ': ' . $bd['desc'] ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 6px;min-width:56px;border-radius:10px;font-size:10px;font-weight:600;text-align:center;
                background:<?php echo $earned ? '#fff' : '#f3f4f6'; ?>;
                border:1px solid <?php echo $earned ? '#e5e7eb' : '#f3f4f6'; ?>;
                opacity:<?php echo $earned ? '1' : '0.4'; ?>;
                <?php echo $earned ? 'box-shadow:0 1px 3px rgba(0,0,0,.08);' : ''; ?>">
                <span style="font-size:20px;<?php echo $earned ? '' : 'filter:grayscale(1);'; ?>"><?php echo $bd['icon']; ?></span>
                <span style="color:<?php echo $earned ? '#374151' : '#9ca3af'; ?>;"><?php echo esc_html( $bd['name'] ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; endif; ?>

    </div><!-- end left column -->
    <div class="ynj-desktop-grid__right">

    <!-- Feed -->
    <section id="feed-section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <h3 id="feed-heading" style="font-size:16px;font-weight:700;margin:0;"><?php printf( esc_html__( 'What\'s Happening at %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></h3>
            <?php if ( $_ynj_can_edit ) : ?>
            <button type="button" onclick="document.getElementById('ynj-quick-post-modal').style.display='flex'" style="background:#287e61;color:#fff;border:none;border-radius:20px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer;min-height:32px;">+ <?php esc_html_e( 'New Post', 'yourjannah' ); ?></button>
            <?php endif; ?>
        </div>

        <div class="ynj-filter-chips" id="feed-filters">
            <button class="ynj-chip ynj-chip--active" data-filter="all" onclick="filterFeed('all')">All</button>
            <button class="ynj-chip" data-filter="_live" onclick="filterFeed('_live')">🔴 Live</button>
            <button class="ynj-chip" data-filter="_classes" onclick="filterFeed('_classes')">🎓 Classes</button>
            <button class="ynj-chip" data-filter="announcements" onclick="filterFeed('announcements')">📢 Updates</button>
            <button class="ynj-chip" data-filter="talk" onclick="filterFeed('talk')">🎤 Talks</button>
            <button class="ynj-chip" data-filter="youth,kids,children" onclick="filterFeed('youth,kids,children')">👦 Youth</button>
            <button class="ynj-chip" data-filter="sisters" onclick="filterFeed('sisters')">👩 Sisters</button>
            <button class="ynj-chip" data-filter="sports,competition" onclick="filterFeed('sports,competition')">⚽ Sports</button>
            <button class="ynj-chip" data-filter="community,iftar,fundraiser" onclick="filterFeed('community,iftar,fundraiser')">🤝 Community</button>
        </div>

        <div id="feed-list">
            <p class="ynj-text-muted" style="padding:16px;text-align:center;"><?php esc_html_e( 'Loading...', 'yourjannah' ); ?></p>
        </div>
    </section>

    <!-- Push Subscribe -->
    <section class="ynj-card ynj-card--subscribe" id="subscribe-card">
        <button class="ynj-btn ynj-btn--outline" id="subscribe-btn" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            <?php esc_html_e( 'Get Prayer Reminders', 'yourjannah' ); ?>
        </button>
        <p class="ynj-subscribe-status" id="subscribe-status"></p>
    </section>

    </div><!-- end right column -->
  </div><!-- end desktop grid -->
</main>

<script>
window.ynjPreloaded = {
    jumuah: <?php echo wp_json_encode( $_mp_jumuah ); ?>,
    sponsors: <?php echo wp_json_encode( $_mp_sponsors ); ?>,
    announcements: <?php echo wp_json_encode( $_mp_announcements ); ?>,
    events: <?php echo wp_json_encode( $_mp_events ); ?>,
    classes: <?php echo wp_json_encode( $_mp_classes ); ?>,
    points: <?php echo wp_json_encode( $_mp_points ); ?>,
    mosqueId: <?php echo (int) $_mp_id; ?>,
    jumuahSlots: <?php echo wp_json_encode( array_map( function( $s ) { return [ 'slot_name' => $s->slot_name, 'khutbah' => substr( $s->khutbah_time, 0, 5 ), 'salah' => substr( $s->salah_time, 0, 5 ), 'language' => $s->language ]; }, $_ynj_jumuah_slots ?? $_mp_jumuah ) ); ?>
};

// On Friday, override dhuhr with first Jumu'ah salah time so countdown is correct
if (window.ynjPreloaded.jumuahSlots && window.ynjPreloaded.jumuahSlots.length > 0 && new Date().getDay() === 5) {
    // Set the initial selectedJumuahTime for homepage.js to use
    window._ynjFirstJumuahTime = window.ynjPreloaded.jumuahSlots[0].salah;
}

// ── Gratitude button handler ──
(function(){
    var mosqueId = window.ynjPreloaded ? window.ynjPreloaded.mosqueId : 0;
    if (!mosqueId) return;
    var nonce = function(){ return typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : ''; };
    window.ynjPostGratitude = function() {
        var btn = event ? event.target : null;
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
        fetch('/wp-json/ynj/v1/gratitude/create', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce() },
            credentials: 'same-origin', body: JSON.stringify({ message: 'JazakAllah Khayr', mosque_id: mosqueId })
        }).then(function(r){ return r.json(); }).then(function(d){
            if (btn) { btn.textContent = d.ok ? '💖 JazakAllah Khayr sent!' : '💖 Thank Your Mosque'; btn.disabled = false; }
        }).catch(function(){ if (btn) { btn.textContent = '💖 Thank Your Mosque'; btn.disabled = false; } });
    };
})();

// ── Content View Tracking (Intersection Observer) ──
(function(){
    var tracked = JSON.parse(sessionStorage.getItem('ynj_tracked_views') || '{}');
    var nonce = '<?php echo wp_create_nonce("wp_rest"); ?>';

    function trackView(type, id) {
        var key = type + '_' + id;
        if (tracked[key]) return;
        tracked[key] = 1;
        sessionStorage.setItem('ynj_tracked_views', JSON.stringify(tracked));
        fetch('/wp-json/ynj/v1/content/view', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, id: id }),
            keepalive: true
        }).catch(function(){});
    }

    // Observe feed cards when they scroll into view
    if ('IntersectionObserver' in window) {
        var obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var el = entry.target;
                    var type = el.getAttribute('data-ynj-type');
                    var id = el.getAttribute('data-ynj-id');
                    if (type && id) trackView(type, parseInt(id));
                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        // Observe after feed renders (homepage.js renders feed async)
        setTimeout(function() {
            document.querySelectorAll('[data-ynj-type][data-ynj-id]').forEach(function(el) {
                obs.observe(el);
            });
        }, 2000);
    }
})();

// ── Membership functions ──
async function ynjJoinMosque(mosqueId) {
    try {
        const res = await fetch('/wp-json/ynj/v1/auth/join-mosque', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
            credentials: 'same-origin',
            body: JSON.stringify({ mosque_id: mosqueId })
        });
        const data = await res.json();
        if (data.ok) {
            location.reload();
        } else {
            alert(data.error || 'Failed to follow. Please try again.');
        }
    } catch(e) { alert('Network error. Please try again.'); }
}

async function ynjLeaveMosque(mosqueId) {
    if (!confirm('Are you sure you want to unfollow this masjid?')) return;
    try {
        const res = await fetch('/wp-json/ynj/v1/auth/leave-mosque', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
            credentials: 'same-origin',
            body: JSON.stringify({ mosque_id: mosqueId })
        });
        const data = await res.json();
        if (data.ok) location.reload();
    } catch(e) { alert('Network error.'); }
}

async function ynjSetPrimary(mosqueId) {
    try {
        const res = await fetch('/wp-json/ynj/v1/auth/primary-mosque', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
            credentials: 'same-origin',
            body: JSON.stringify({ mosque_id: mosqueId })
        });
        const data = await res.json();
        if (data.ok) location.reload();
    } catch(e) { alert('Network error.'); }
}

function ynjShowJoinLogin() {
    document.getElementById('ynj-join-modal').style.display = 'flex';
}
function ynjCloseJoinModal() {
    document.getElementById('ynj-join-modal').style.display = 'none';
}
</script>

<!-- Social Login Modal (for non-logged-in users) -->
<?php if ( ! is_user_logged_in() && $mosque ) :
    $return_to = '/mosque/' . $slug;
    $google_url = class_exists('YNJ_Social_Auth') && YNJ_Social_Auth::is_google_configured() ? YNJ_Social_Auth::get_login_url( 'google', $return_to, $slug, $slug ) : '';
    $facebook_url = class_exists('YNJ_Social_Auth') && YNJ_Social_Auth::is_facebook_configured() ? YNJ_Social_Auth::get_login_url( 'facebook', $return_to, $slug, $slug ) : '';
?>
<div id="ynj-join-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:20px;padding:28px 24px;max-width:380px;width:100%;text-align:center;position:relative;">
        <button onclick="ynjCloseJoinModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#999;">&times;</button>
        <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;"><?php printf( esc_html__( 'Follow %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></h2>
        <p style="font-size:13px;color:#666;margin-bottom:20px;"><?php esc_html_e( 'Sign in to follow this masjid and get updates', 'yourjannah' ); ?></p>

        <?php if ( $google_url ) : ?>
        <a href="<?php echo esc_url( $google_url ); ?>" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid #ddd;border-radius:12px;font-size:14px;font-weight:600;color:#333;text-decoration:none;margin-bottom:10px;background:#fff;">
            <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
            Continue with Google
        </a>
        <?php endif; ?>

        <?php if ( $facebook_url ) : ?>
        <a href="<?php echo esc_url( $facebook_url ); ?>" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid #ddd;border-radius:12px;font-size:14px;font-weight:600;color:#333;text-decoration:none;margin-bottom:10px;background:#fff;">
            <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#1877F2" d="M48 24C48 10.745 37.255 0 24 0S0 10.745 0 24c0 11.979 8.776 21.908 20.25 23.708v-16.77h-6.094V24h6.094v-5.288c0-6.014 3.583-9.337 9.065-9.337 2.625 0 5.372.469 5.372.469v5.906h-3.026c-2.981 0-3.911 1.85-3.911 3.75V24h6.656l-1.064 6.938H27.75v16.77C39.224 45.908 48 35.979 48 24z"/></svg>
            Continue with Facebook
        </a>
        <?php endif; ?>

        <div style="display:flex;align-items:center;gap:12px;margin:16px 0;">
            <div style="flex:1;height:1px;background:#e0e0e0;"></div>
            <span style="font-size:12px;color:#999;">or</span>
            <div style="flex:1;height:1px;background:#e0e0e0;"></div>
        </div>

        <a href="<?php echo esc_url( home_url( '/register/?redirect=' . urlencode( '/mosque/' . $slug ) . '&join_mosque=' . $slug ) ); ?>" style="display:block;width:100%;padding:12px;background:#00ADEF;color:#fff;border-radius:12px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;">
            Sign up with Email
        </a>

        <p style="font-size:12px;color:#999;margin-top:16px;">
            Already have an account? <a href="<?php echo esc_url( home_url( '/login/?redirect=' . urlencode( '/mosque/' . $slug ) ) ); ?>" style="color:#00ADEF;font-weight:600;">Sign in</a>
        </p>
    </div>
</div>
<?php endif; ?>

<?php // ── Admin Tools: FAB, Quick Post Modal, Edit Shortcuts, Toast ── ?>
<?php if ( $mosque && $_ynj_can_edit ) :
    $qp_templates = function_exists( 'ynj_get_quick_templates' ) ? ynj_get_quick_templates( $mosque_name ) : [];
?>

<!-- Admin edit shortcut CSS -->
<style>
.ynj-admin-edit{position:absolute;top:8px;right:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.9);border-radius:50%;font-size:14px;text-decoration:none;z-index:5;box-shadow:0 1px 4px rgba(0,0,0,.15);-webkit-tap-highlight-color:transparent;}
.ynj-admin-edit:hover{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.2);}

/* Admin floating toolbar */
.ynj-admin-toolbar{position:fixed;bottom:0;left:0;right:0;display:flex;justify-content:center;gap:8px;padding:10px 16px;background:#fff;border-top:1px solid #e5e7eb;z-index:900;padding-bottom:max(10px,env(safe-area-inset-bottom));}
.ynj-admin-toolbar a,.ynj-admin-toolbar button{display:flex;align-items:center;gap:6px;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:none;cursor:pointer;min-height:44px;font-family:inherit;}
.ynj-atb-primary{background:#287e61;color:#fff;}
.ynj-atb-outline{background:#f3f4f6;color:#1a1a1a;border:1px solid #e5e7eb;}

/* Quick Post Modal */
.ynj-qp-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:flex-end;justify-content:center;-webkit-tap-highlight-color:transparent;}
.ynj-qp-modal{background:#fff;border-radius:20px 20px 0 0;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;padding:0;animation:ynj-slide-up .25s ease-out;}
@keyframes ynj-slide-up{from{transform:translateY(100%)}to{transform:translateY(0)}}
.ynj-qp-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;border-bottom:1px solid #f3f4f6;position:sticky;top:0;background:#fff;z-index:1;}
.ynj-qp-header h3{font-size:17px;font-weight:800;margin:0;}
.ynj-qp-close{background:none;border:none;font-size:24px;cursor:pointer;color:#999;padding:4px 8px;min-height:44px;min-width:44px;display:flex;align-items:center;justify-content:center;}
.ynj-qp-body{padding:16px 20px 24px;}
.ynj-qp-tabs{display:flex;gap:0;margin-bottom:16px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;}
.ynj-qp-tab{flex:1;padding:10px;text-align:center;font-size:13px;font-weight:700;cursor:pointer;background:#f9fafb;color:#6b7280;border:none;min-height:44px;font-family:inherit;}
.ynj-qp-tab--active{background:#287e61;color:#fff;}

/* Template grid */
.ynj-tpl-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;}
.ynj-tpl-card{display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 6px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;cursor:pointer;font-size:11px;font-weight:600;color:#374151;text-align:center;min-height:44px;-webkit-tap-highlight-color:transparent;transition:border-color .15s;}
.ynj-tpl-card:active,.ynj-tpl-card--selected{border-color:#287e61;background:#e6f2ed;}
.ynj-tpl-icon{font-size:22px;}
.ynj-tpl-more{grid-column:1/-1;padding:8px;text-align:center;color:#287e61;font-size:12px;font-weight:700;cursor:pointer;}

/* Quick post form */
.ynj-qp-field{margin-bottom:12px;}
.ynj-qp-field label{display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px;}
.ynj-qp-field input,.ynj-qp-field textarea,.ynj-qp-field select{width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:10px;font-size:15px;font-family:inherit;min-height:44px;}
.ynj-qp-field textarea{resize:vertical;min-height:80px;}
.ynj-qp-submit{display:block;width:100%;padding:14px;border-radius:12px;font-size:15px;font-weight:700;border:none;cursor:pointer;background:#287e61;color:#fff;min-height:48px;font-family:inherit;}

/* Toast */
.ynj-toast{position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#166534;color:#fff;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:600;z-index:10000;box-shadow:0 4px 16px rgba(0,0,0,.2);animation:ynj-fade-in .3s ease-out;}
@keyframes ynj-fade-in{from{opacity:0;transform:translateX(-50%) translateY(-10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}

@media(min-width:769px){
    .ynj-qp-overlay{align-items:center;}
    .ynj-qp-modal{border-radius:20px;max-height:85vh;}
}
</style>

<!-- Success Toast -->
<?php if ( $_ynj_posted === 'announcement' ) : ?>
<div class="ynj-toast" id="ynj-toast">Announcement posted!</div>
<?php elseif ( $_ynj_posted === 'event' ) : ?>
<div class="ynj-toast" id="ynj-toast">Event created!</div>
<?php elseif ( $_ynj_posted === 'pending' ) : ?>
<div class="ynj-toast" id="ynj-toast" style="background:#92400e;">Submitted for admin approval</div>
<?php endif; ?>

<!-- Admin Floating Toolbar -->
<div class="ynj-admin-toolbar">
    <button type="button" onclick="document.getElementById('ynj-quick-post-modal').style.display='flex'" class="ynj-atb-primary">📢 <?php esc_html_e( 'New Post', 'yourjannah' ); ?></button>
    <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" class="ynj-atb-outline">📊 <?php esc_html_e( 'Dashboard', 'yourjannah' ); ?></a>
    <button type="button" onclick="document.getElementById('ynj-admin-menu').style.display=document.getElementById('ynj-admin-menu').style.display==='block'?'none':'block'" class="ynj-atb-outline">⚡ <?php esc_html_e( 'Quick Menu', 'yourjannah' ); ?></button>
</div>

<!-- Admin Quick Menu (expandable) -->
<div id="ynj-admin-menu" style="display:none;position:fixed;bottom:64px;left:50%;transform:translateX(-50%);background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);padding:12px;z-index:901;width:90%;max-width:400px;">
    <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;padding:0 4px;">Quick Menu</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;">
        <a href="<?php echo esc_url( home_url( '/dashboard?section=announcements' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">📢</span>Posts
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=events' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">📅</span>Events
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=prayers' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">🕐</span>Prayers
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=subscribers' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">👥</span>Followers
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=patrons' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">🏅</span>Patrons
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=broadcast' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">📤</span>Broadcast
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=classes' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">🎓</span>Classes
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=bookings' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">📋</span>Bookings
        </a>
        <a href="<?php echo esc_url( home_url( '/dashboard?section=settings' ) ); ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:12px 4px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:11px;font-weight:600;text-align:center;">
            <span style="font-size:20px;">⚙️</span>Settings
        </a>
    </div>
</div>
<script>
// Close admin menu when clicking outside
document.addEventListener('click', function(e) {
    var menu = document.getElementById('ynj-admin-menu');
    if (!menu) return;
    if (menu.style.display === 'block' && !menu.contains(e.target) && !e.target.closest('.ynj-admin-toolbar')) {
        menu.style.display = 'none';
    }
});
</script>

<!-- Quick Post Modal -->
<div class="ynj-qp-overlay" id="ynj-quick-post-modal" onclick="if(event.target===this)this.style.display='none'">
    <div class="ynj-qp-modal">
        <div class="ynj-qp-header">
            <h3>📢 <?php esc_html_e( 'Quick Post', 'yourjannah' ); ?></h3>
            <button class="ynj-qp-close" onclick="document.getElementById('ynj-quick-post-modal').style.display='none'">&times;</button>
        </div>
        <div class="ynj-qp-body">
            <!-- Tabs -->
            <div class="ynj-qp-tabs">
                <button class="ynj-qp-tab ynj-qp-tab--active" id="qp-tab-ann" onclick="ynjQpTab('ann')">📢 <?php esc_html_e( 'Announcement', 'yourjannah' ); ?></button>
                <button class="ynj-qp-tab" id="qp-tab-event" onclick="ynjQpTab('event')">📅 <?php esc_html_e( 'Event', 'yourjannah' ); ?></button>
                <?php if ( $_ynj_is_page_imam || $_ynj_is_page_admin ) : ?>
                <button class="ynj-qp-tab" id="qp-tab-imam" onclick="ynjQpTab('imam')">🕌 <?php esc_html_e( 'Imam Message', 'yourjannah' ); ?></button>
                <?php endif; ?>
            </div>

            <!-- Announcement Form -->
            <div id="qp-form-ann">
                <!-- Template Picker -->
                <div class="ynj-tpl-grid" id="ynj-tpl-grid">
                    <?php foreach ( array_slice( $qp_templates, 0, 6 ) as $i => $tpl ) : ?>
                    <div class="ynj-tpl-card" onclick="ynjPickTemplate(<?php echo $i; ?>)">
                        <span class="ynj-tpl-icon"><?php echo $tpl['icon']; ?></span>
                        <?php echo esc_html( $tpl['label'] ); ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if ( count( $qp_templates ) > 6 ) : ?>
                    <div class="ynj-tpl-more" id="ynj-tpl-more" onclick="ynjShowAllTemplates()">▼ <?php printf( esc_html__( 'Show all %d templates', 'yourjannah' ), count( $qp_templates ) ); ?></div>
                    <?php endif; ?>
                </div>
                <div id="ynj-tpl-grid-all" style="display:none;">
                    <div class="ynj-tpl-grid">
                        <?php foreach ( $qp_templates as $i => $tpl ) : ?>
                        <div class="ynj-tpl-card" onclick="ynjPickTemplate(<?php echo $i; ?>)">
                            <span class="ynj-tpl-icon"><?php echo $tpl['icon']; ?></span>
                            <?php echo esc_html( $tpl['label'] ); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form method="post" id="qp-ann-form">
                    <?php wp_nonce_field( 'ynj_quick_post', '_ynj_nonce' ); ?>
                    <input type="hidden" name="qp_action" value="announcement">
                    <div class="ynj-qp-field">
                        <label><?php esc_html_e( 'Title', 'yourjannah' ); ?></label>
                        <input type="text" name="title" id="qp-ann-title" required placeholder="<?php esc_attr_e( 'What do you want to announce?', 'yourjannah' ); ?>">
                    </div>
                    <div class="ynj-qp-field">
                        <label><?php esc_html_e( 'Message', 'yourjannah' ); ?></label>
                        <textarea name="body" id="qp-ann-body" rows="3" placeholder="<?php esc_attr_e( 'Add details...', 'yourjannah' ); ?>"></textarea>
                    </div>
                    <div class="ynj-qp-field">
                        <label><?php esc_html_e( 'Type', 'yourjannah' ); ?></label>
                        <select name="type" id="qp-ann-type">
                            <option value="general"><?php esc_html_e( 'General', 'yourjannah' ); ?></option>
                            <option value="urgent"><?php esc_html_e( 'Urgent', 'yourjannah' ); ?></option>
                            <option value="religious"><?php esc_html_e( 'Religious', 'yourjannah' ); ?></option>
                            <option value="event"><?php esc_html_e( 'Event', 'yourjannah' ); ?></option>
                        </select>
                    </div>
                    <button type="submit" class="ynj-qp-submit">📢 <?php esc_html_e( 'Post Announcement', 'yourjannah' ); ?></button>
                </form>
            </div>

            <!-- Event Form -->
            <div id="qp-form-event" style="display:none;">
                <form method="post">
                    <?php wp_nonce_field( 'ynj_quick_post', '_ynj_nonce' ); ?>
                    <input type="hidden" name="qp_action" value="event">
                    <div class="ynj-qp-field">
                        <label><?php esc_html_e( 'Event Title', 'yourjannah' ); ?></label>
                        <input type="text" name="event_title" required placeholder="<?php esc_attr_e( 'e.g. Community BBQ', 'yourjannah' ); ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div class="ynj-qp-field">
                            <label><?php esc_html_e( 'Date', 'yourjannah' ); ?></label>
                            <input type="date" name="event_date" required min="<?php echo date( 'Y-m-d' ); ?>">
                        </div>
                        <div class="ynj-qp-field">
                            <label><?php esc_html_e( 'Type', 'yourjannah' ); ?></label>
                            <select name="event_type">
                                <option value="community"><?php esc_html_e( 'Community', 'yourjannah' ); ?></option>
                                <option value="talk"><?php esc_html_e( 'Talk', 'yourjannah' ); ?></option>
                                <option value="class"><?php esc_html_e( 'Class', 'yourjannah' ); ?></option>
                                <option value="sports"><?php esc_html_e( 'Sports', 'yourjannah' ); ?></option>
                                <option value="youth"><?php esc_html_e( 'Youth', 'yourjannah' ); ?></option>
                                <option value="sisters"><?php esc_html_e( 'Sisters', 'yourjannah' ); ?></option>
                                <option value="fundraiser"><?php esc_html_e( 'Fundraiser', 'yourjannah' ); ?></option>
                                <option value="eid"><?php esc_html_e( 'Eid', 'yourjannah' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div class="ynj-qp-field">
                            <label><?php esc_html_e( 'Start Time', 'yourjannah' ); ?></label>
                            <input type="time" name="event_start">
                        </div>
                        <div class="ynj-qp-field">
                            <label><?php esc_html_e( 'End Time', 'yourjannah' ); ?></label>
                            <input type="time" name="event_end">
                        </div>
                    </div>
                    <div class="ynj-qp-field">
                        <label><?php esc_html_e( 'Location', 'yourjannah' ); ?></label>
                        <input type="text" name="event_location" placeholder="<?php esc_attr_e( 'e.g. Main Hall', 'yourjannah' ); ?>">
                    </div>
                    <div class="ynj-qp-field">
                        <label><?php esc_html_e( 'Description', 'yourjannah' ); ?></label>
                        <textarea name="event_description" rows="2" placeholder="<?php esc_attr_e( 'Add details...', 'yourjannah' ); ?>"></textarea>
                    </div>
                    <button type="submit" class="ynj-qp-submit">📅 <?php esc_html_e( 'Create Event', 'yourjannah' ); ?></button>
                </form>
            </div>

            <?php if ( $_ynj_is_page_imam || $_ynj_is_page_admin ) : ?>
            <!-- Imam Message Form (uses REST API) -->
            <div id="qp-form-imam" style="display:none;">
                <div class="ynj-qp-field">
                    <label><?php esc_html_e( 'Category', 'yourjannah' ); ?></label>
                    <select id="qp-imam-cat">
                        <option value="daily">🕌 <?php esc_html_e( 'Daily Reminder', 'yourjannah' ); ?></option>
                        <option value="friday">📿 <?php esc_html_e( 'Friday Message', 'yourjannah' ); ?></option>
                        <option value="dua">🤲 <?php esc_html_e( 'Dua', 'yourjannah' ); ?></option>
                        <option value="hadith">📖 <?php esc_html_e( 'Hadith', 'yourjannah' ); ?></option>
                        <option value="quran">📗 <?php esc_html_e( 'Quran Reflection', 'yourjannah' ); ?></option>
                        <option value="notice">📢 <?php esc_html_e( 'Important Notice', 'yourjannah' ); ?></option>
                    </select>
                </div>
                <div class="ynj-qp-field">
                    <label><?php esc_html_e( 'Title', 'yourjannah' ); ?></label>
                    <input type="text" id="qp-imam-title" required placeholder="<?php esc_attr_e( 'e.g. Patience in Hardship', 'yourjannah' ); ?>">
                </div>
                <div class="ynj-qp-field">
                    <label><?php esc_html_e( 'Message', 'yourjannah' ); ?></label>
                    <textarea id="qp-imam-body" rows="4" placeholder="<?php esc_attr_e( 'Your message to the congregation...', 'yourjannah' ); ?>"></textarea>
                </div>
                <button type="button" class="ynj-qp-submit" onclick="ynjPostImamMessage()">🕌 <?php esc_html_e( 'Publish Message', 'yourjannah' ); ?></button>
                <div id="qp-imam-status" style="display:none;text-align:center;padding:8px;margin-top:8px;border-radius:8px;font-size:13px;font-weight:600;"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Templates data
var ynjTemplates = <?php echo wp_json_encode( $qp_templates ); ?>;

// Tab switching
function ynjQpTab(tab) {
    document.getElementById('qp-form-ann').style.display = tab === 'ann' ? '' : 'none';
    document.getElementById('qp-form-event').style.display = tab === 'event' ? '' : 'none';
    var imamForm = document.getElementById('qp-form-imam');
    if (imamForm) imamForm.style.display = tab === 'imam' ? '' : 'none';
    document.getElementById('qp-tab-ann').className = 'ynj-qp-tab' + (tab === 'ann' ? ' ynj-qp-tab--active' : '');
    document.getElementById('qp-tab-event').className = 'ynj-qp-tab' + (tab === 'event' ? ' ynj-qp-tab--active' : '');
    var imamTab = document.getElementById('qp-tab-imam');
    if (imamTab) imamTab.className = 'ynj-qp-tab' + (tab === 'imam' ? ' ynj-qp-tab--active' : '');
}

// Post Imam Message via REST API
function ynjPostImamMessage() {
    var title = document.getElementById('qp-imam-title').value.trim();
    var body = document.getElementById('qp-imam-body').value.trim();
    var cat = document.getElementById('qp-imam-cat').value;
    var status = document.getElementById('qp-imam-status');
    if (!title) { alert('Please enter a title'); return; }
    var btn = event.currentTarget;
    btn.disabled = true;
    btn.textContent = 'Publishing...';
    fetch(<?php echo wp_json_encode( rest_url( 'ynj/v1/imam-messages' ) ); ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> },
        body: JSON.stringify({
            title: title,
            body: body,
            category: cat,
            mosque_id: <?php echo (int) $mosque->id; ?>
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            status.style.display = '';
            status.style.background = '#dcfce7';
            status.style.color = '#166534';
            status.textContent = 'Message published — JazakAllah Khayr!';
            document.getElementById('qp-imam-title').value = '';
            document.getElementById('qp-imam-body').value = '';
            btn.textContent = '🕌 Publish Message';
            btn.disabled = false;
            setTimeout(function() { location.reload(); }, 1500);
        } else {
            status.style.display = '';
            status.style.background = '#fee2e2';
            status.style.color = '#991b1b';
            status.textContent = data.error || 'Failed to post';
            btn.textContent = '🕌 Publish Message';
            btn.disabled = false;
        }
    })
    .catch(function() {
        btn.textContent = '🕌 Publish Message';
        btn.disabled = false;
    });
}

// Template picker
function ynjPickTemplate(idx) {
    var t = ynjTemplates[idx];
    if (!t) return;
    document.getElementById('qp-ann-title').value = t.title;
    document.getElementById('qp-ann-body').value = t.body;
    document.getElementById('qp-ann-type').value = t.type;
    // Highlight selected card
    document.querySelectorAll('.ynj-tpl-card').forEach(function(c) { c.classList.remove('ynj-tpl-card--selected'); });
    event.currentTarget.classList.add('ynj-tpl-card--selected');
    // Focus body so admin can edit
    document.getElementById('qp-ann-body').focus();
}

// Show all templates
function ynjShowAllTemplates() {
    document.getElementById('ynj-tpl-grid').style.display = 'none';
    document.getElementById('ynj-tpl-more').style.display = 'none';
    document.getElementById('ynj-tpl-grid-all').style.display = '';
}

// Auto-hide toast after 4s
var toast = document.getElementById('ynj-toast');
if (toast) { setTimeout(function() { toast.style.opacity = '0'; toast.style.transition = 'opacity .5s'; setTimeout(function(){ toast.remove(); }, 500); }, 4000); }
</script>

<?php endif; // end admin tools ?>

<?php
get_footer();
