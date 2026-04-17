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
            $jt = YNJ_DB::table( 'jumuah_times' );
            $_ynj_jumuah_slots = $wpdb->get_results( $wpdb->prepare( "SELECT slot_name, khutbah_time, salah_time, language FROM $jt WHERE mosque_id = %d AND enabled = 1 ORDER BY salah_time ASC", (int) $mosque->id ) ) ?: [];
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
    global $wpdb;
    $jt = YNJ_DB::table( 'jumuah_times' );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$jt'" ) === $jt ) {
        $_mp_jumuah = $wpdb->get_results( $wpdb->prepare( "SELECT slot_name, khutbah_time, salah_time, language FROM $jt WHERE mosque_id = %d AND enabled = 1 ORDER BY salah_time ASC", $_mp_id ) ) ?: [];
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

<?php
// ── Membership status check ──
$_ynj_is_member = false;
$_ynj_is_primary = false;
$_ynj_member_count = $mosque ? (int) ( $mosque->member_count ?? 0 ) : 0;
// Social proof: mosques under 20 real members show a seeded number (5-20)
if ( $_ynj_member_count < 20 && $mosque ) {
    $_ynj_member_count = ( crc32( $mosque->slug ?? '' ) % 16 ) + 5;
}
if ( $mosque && is_user_logged_in() ) {
    $ynj_uid_check = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
    if ( $ynj_uid_check ) {
        global $wpdb;
        $sub_table = YNJ_DB::table( 'user_subscriptions' );
        $membership = $wpdb->get_row( $wpdb->prepare(
            "SELECT is_member, is_primary FROM $sub_table WHERE user_id = %d AND mosque_id = %d AND status = 'active'",
            $ynj_uid_check, (int) $mosque->id
        ) );
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

    <!-- Join This Masjid + Member Count -->
    <div class="ynj-join-bar" style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff;border-radius:14px;padding:12px 16px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:18px;">🕌</span>
            <span style="font-size:14px;font-weight:600;color:#333;">
                <?php echo number_format( $_ynj_member_count ); ?> <?php echo $_ynj_member_count === 1 ? 'member' : 'members'; ?>
            </span>
        </div>
        <?php if ( $_ynj_is_member ) : ?>
            <div style="display:flex;align-items:center;gap:8px;">
                <?php if ( $_ynj_is_primary ) : ?>
                    <span style="font-size:11px;color:#666;background:#f0f0f0;padding:2px 8px;border-radius:12px;">Primary</span>
                <?php else : ?>
                    <button onclick="ynjSetPrimary(<?php echo (int) $mosque->id; ?>)" style="font-size:11px;color:#00ADEF;background:none;border:1px solid #00ADEF;padding:2px 8px;border-radius:12px;cursor:pointer;">Set as Primary</button>
                <?php endif; ?>
                <span style="color:#27ae60;font-weight:600;font-size:13px;">✓ Joined</span>
                <button onclick="ynjLeaveMosque(<?php echo (int) $mosque->id; ?>)" style="font-size:11px;color:#999;background:none;border:none;cursor:pointer;text-decoration:underline;">Leave</button>
            </div>
        <?php elseif ( is_user_logged_in() ) : ?>
            <button onclick="ynjJoinMosque(<?php echo (int) $mosque->id; ?>)" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;">
                Join This Masjid
            </button>
        <?php else : ?>
            <button onclick="ynjShowJoinLogin()" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;">
                Join This Masjid
            </button>
        <?php endif; ?>
    </div>

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
        <?php if ( $_ynj_is_friday && strpos( $_ynj_next_name, "Jumu'ah" ) !== false ) : ?>
        <p class="ynj-label" id="next-prayer-label" style="color:#fbbf24;">🕌 <?php echo esc_html( 'It\'s Friday! Jumu\'ah at ' . $mosque_name ); ?></p>
        <?php else : ?>
        <p class="ynj-label" id="next-prayer-label"><?php echo esc_html( 'Next Prayer at ' . $mosque_name ); ?></p>
        <?php endif; ?>
        <h2 class="ynj-hero-prayer" id="next-prayer-name"><?php echo esc_html( $_ynj_next_name ?: '—' ); ?></h2>
        <p class="ynj-hero-time" id="next-prayer-time"><?php echo esc_html( $_ynj_next_time ?: '—' ); ?></p>
        <?php if ( $_ynj_is_friday && ! empty( $_ynj_jumuah_slots ) && count( $_ynj_jumuah_slots ) > 0 ) : ?>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin:8px 0;" id="jumuah-slots">
            <?php foreach ( $_ynj_jumuah_slots as $idx => $js ) : ?>
            <div class="ynj-jumuah-slot<?php echo $idx === 0 ? ' active' : ''; ?>" onclick="selectJumuahSlot(this, '<?php echo esc_attr( substr( $js->salah_time, 0, 5 ) ); ?>')" data-salah="<?php echo esc_attr( substr( $js->salah_time, 0, 5 ) ); ?>" style="background:rgba(255,255,255,<?php echo $idx === 0 ? '.25' : '.12'; ?>);border-radius:10px;padding:6px 12px;text-align:center;cursor:pointer;border:2px solid <?php echo $idx === 0 ? 'rgba(255,255,255,.5)' : 'transparent'; ?>;transition:all .15s;">
                <div style="font-size:11px;opacity:.7;"><?php echo esc_html( $js->slot_name ?: 'Jumu\'ah' ); ?></div>
                <?php if ( $js->khutbah_time ) : ?><div style="font-size:11px;opacity:.6;">Khutbah <?php echo esc_html( substr( $js->khutbah_time, 0, 5 ) ); ?></div><?php endif; ?>
                <div style="font-size:15px;font-weight:700;">Salah <?php echo esc_html( substr( $js->salah_time, 0, 5 ) ); ?></div>
                <?php if ( $js->language ) : ?><div style="font-size:10px;opacity:.5;"><?php echo esc_html( $js->language ); ?></div><?php endif; ?>
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
            alert(data.error || 'Failed to join. Please try again.');
        }
    } catch(e) { alert('Network error. Please try again.'); }
}

async function ynjLeaveMosque(mosqueId) {
    if (!confirm('Are you sure you want to leave this masjid?')) return;
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
        <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;">Join <?php echo esc_html( $mosque_name ); ?></h2>
        <p style="font-size:13px;color:#666;margin-bottom:20px;">Sign in to become a member of this masjid</p>

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

<?php
get_footer();
