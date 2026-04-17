<?php
/**
 * Template: Mosque Profile Page
 *
 * Replicates homepage layout for a specific mosque (fixed by URL slug).
 * Same prayer card, patron bar, feed, sponsor ticker — all powered by homepage.js.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_name = $mosque ? $mosque->name : '';
$mosque_address = $mosque ? ( $mosque->address ?? '' ) : '';

// Set cookie so mosque pill and other pages remember this mosque
if ( $slug && $mosque ) {
    setcookie( 'ynj_mosque_slug', $slug, time() + 365 * DAY_IN_SECONDS, '/' );
}

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
            $response = wp_remote_get( $url, [ 'timeout' => 3, 'sslverify' => false ] );
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $body['data']['timings'] ) ) {
                    $aladhan = $body['data']['timings'];
                    set_transient( $cache_key, $aladhan, 6 * HOUR_IN_SECONDS );
                }
            }
            if ( ! $aladhan ) set_transient( $fail_key, 1, HOUR_IN_SECONDS );
        }
        // Fallback: use prayer_times table
        if ( ! $aladhan ) {
            global $wpdb;
            $pt_table = YNJ_DB::table( 'prayer_times' );
            $db_times = $wpdb->get_row( $wpdb->prepare(
                "SELECT fajr, sunrise, dhuhr, asr, maghrib, isha FROM $pt_table WHERE mosque_id = %d AND date = %s",
                (int) $mosque->id, date( 'Y-m-d' )
            ) );
            if ( $db_times ) {
                $aladhan = [ 'Fajr' => $db_times->fajr, 'Sunrise' => $db_times->sunrise, 'Dhuhr' => $db_times->dhuhr, 'Asr' => $db_times->asr, 'Maghrib' => $db_times->maghrib, 'Isha' => $db_times->isha ];
            }
        }
    }

    // Load jamat times + Jumu'ah slots
    $_ynj_jamat = [];
    $_ynj_jumuah_slots = [];
    $_ynj_is_friday = ( date( 'N' ) == 5 );
    if ( $mosque && class_exists( 'YNJ_DB' ) ) {
        global $wpdb;
        $pt_table = YNJ_DB::table( 'prayer_times' );
        $db_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $pt_table WHERE mosque_id = %d AND date = %s", (int) $mosque->id, date( 'Y-m-d' ) ) );
        if ( $db_row ) {
            foreach ( [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ] as $pk ) {
                $jk = $pk . '_jamat';
                if ( ! empty( $db_row->$jk ) ) $_ynj_jamat[ $pk ] = substr( $db_row->$jk, 0, 5 );
            }
        }
        if ( $_ynj_is_friday ) {
            $jt = YNJ_DB::table( 'jumuah_slots' );
            $_ynj_jumuah_slots = $wpdb->get_results( $wpdb->prepare( "SELECT slot_name, khutbah_time, salah_time, language FROM $jt WHERE mosque_id = %d AND status = 'active' ORDER BY salah_time ASC", (int) $mosque->id ) ) ?: [];
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

            if ( $_ynj_is_friday && $p === 'Dhuhr' && ! empty( $_ynj_jumuah_slots ) ) {
                $first_jumuah = $_ynj_jumuah_slots[0];
                $jumuah_time = substr( $first_jumuah->salah_time, 0, 5 );
                $_ynj_prayer_overview[] = [ 'name' => "Jumu'ah", 'time' => $jumuah_time, 'jamat' => $jumuah_time, 'is_jumuah' => true ];
                if ( ! $_ynj_next_prayer && $jumuah_time > $now ) {
                    $_ynj_next_prayer = "Jumu'ah"; $_ynj_next_time = $jumuah_time; $_ynj_next_name = "Jumu'ah";
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
    global $wpdb;
    $jt = YNJ_DB::table( 'jumuah_slots' );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$jt'" ) === $jt ) {
        $_mp_jumuah = $wpdb->get_results( $wpdb->prepare( "SELECT slot_name, khutbah_time, salah_time, language FROM $jt WHERE mosque_id = %d AND status = 'active' ORDER BY salah_time ASC", $_mp_id ) ) ?: [];
    }
    $bt = YNJ_DB::table( 'businesses' );
    $_mp_sponsors = $wpdb->get_results( $wpdb->prepare( "SELECT id, business_name, category, monthly_fee_pence FROM $bt WHERE mosque_id = %d AND status = 'active' ORDER BY monthly_fee_pence DESC LIMIT 20", $_mp_id ) ) ?: [];
    $svt = YNJ_DB::table( 'services' );
    $_mp_services = $wpdb->get_results( $wpdb->prepare( "SELECT id, provider_name, service_type, phone, area_covered, hourly_rate_pence FROM $svt WHERE mosque_id = %d AND status = 'active' ORDER BY RAND() LIMIT 10", $_mp_id ) ) ?: [];
    $at = YNJ_DB::table( 'announcements' );
    $_mp_announcements = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, body, type, pinned, published_at FROM $at WHERE mosque_id = %d AND status = 'published' ORDER BY pinned DESC, published_at DESC LIMIT 20", $_mp_id ) ) ?: [];
    $et = YNJ_DB::table( 'events' );
    $_mp_events = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, description, event_date, start_time, end_time, location, category, ticket_price_pence, max_capacity, rsvp_count FROM $et WHERE mosque_id = %d AND status = 'published' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20", $_mp_id ) ) ?: [];
    $ct = YNJ_DB::table( 'classes' );
    $_mp_classes = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, description, instructor_name, day_of_week, start_time, end_time, price_pence, category, max_capacity, enrolled_count FROM $ct WHERE mosque_id = %d AND status = 'active' ORDER BY day_of_week ASC LIMIT 20", $_mp_id ) ) ?: [];
    if ( is_user_logged_in() ) {
        $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        if ( $ynj_uid ) {
            $ut = YNJ_DB::table( 'users' );
            $_mp_points = [ 'ok' => true, 'total' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT total_points FROM $ut WHERE id = %d", $ynj_uid ) ) ];
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

<main class="ynj-main">
  <div class="ynj-desktop-grid">
    <div class="ynj-desktop-grid__left">

    <!-- Ramadan banner (shown automatically during Ramadan) -->
    <div id="ramadan-banner" style="display:none;background:linear-gradient(135deg,#1a1628,#2d1b69);color:#fff;border-radius:14px;padding:14px 18px;margin-bottom:10px;"></div>

    <!-- Patron Membership CTA -->
    <div class="ynj-patron-bar" id="patron-hero">
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-bar__label">🏅 <strong id="patron-bar-text"><?php printf( esc_html__( 'Become a Patron of %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></strong></a>
        <div class="ynj-patron-bar__tiers">
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip">£5</a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip">£10</a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip ynj-patron-chip--popular"><span class="ynj-patron-chip__pop"><?php esc_html_e( 'Popular', 'yourjannah' ); ?></span>£20</a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" class="ynj-patron-chip">£50</a>
        </div>
    </div>

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
    <section class="ynj-card ynj-card--hero" id="next-prayer-card">
        <p class="ynj-label" id="next-prayer-label"><?php echo esc_html( 'Next Prayer at ' . $mosque_name ); ?></p>
        <h2 class="ynj-hero-prayer" id="next-prayer-name"><?php echo esc_html( $_ynj_next_name ?: '—' ); ?></h2>
        <p class="ynj-hero-time" id="next-prayer-time"><?php echo esc_html( $_ynj_next_time ?: '—' ); ?></p>
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

    <!-- Jumu'ah Card -->
    <section class="ynj-jumuah-card" id="jumuah-card" style="display:none;">
        <div class="ynj-jumuah-card__header">🕌 <?php esc_html_e( 'Jumu\'ah', 'yourjannah' ); ?></div>
        <div id="jumuah-slots"></div>
    </section>

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

    </div><!-- end left column -->
    <div class="ynj-desktop-grid__right">

    <!-- Feed -->
    <section id="feed-section">
        <h3 id="feed-heading" style="font-size:16px;font-weight:700;margin:0 0 10px;"><?php printf( esc_html__( 'What\'s Happening at %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></h3>

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
    mosqueId: <?php echo (int) $_mp_id; ?>
};
</script>

<?php
get_footer();
