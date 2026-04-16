<?php
/**
 * Template: Homepage
 *
 * Prayer card, sponsor ticker, travel settings, community feed.
 * JS loaded from assets/js/homepage.js via wp_enqueue_script.
 *
 * @package YourJannah
 */

get_header();

// ── Fetch prayer times from Aladhan in PHP (server-side, always works) ──
$_ynj_prayer = [];
$_ynj_next_prayer = null;
$_ynj_next_time = '';
$_ynj_next_name = '';
$_ynj_walk_leave = '';
$_ynj_drive_leave = '';
$_ynj_prayer_overview = [];
$_ynj_mosque_for_prayer = null;

$_hp_slug = '';
if ( isset( $_COOKIE['ynj_mosque_slug'] ) ) {
    $_hp_slug = sanitize_title( $_COOKIE['ynj_mosque_slug'] );
}
if ( ! $_hp_slug ) {
    $_hp_slug = 'yourniyyah-masjid'; // default
}
$_ynj_mosque_for_prayer = ynj_get_mosque( $_hp_slug );

if ( $_ynj_mosque_for_prayer && $_ynj_mosque_for_prayer->latitude ) {
    $lat = (float) $_ynj_mosque_for_prayer->latitude;
    $lng = (float) $_ynj_mosque_for_prayer->longitude;
    $today = date( 'd-m-Y' );

    // Cache Aladhan response for 6 hours
    $cache_key = 'ynj_aladhan_' . md5( $lat . $lng . $today );
    $aladhan = get_transient( $cache_key );

    if ( ! $aladhan ) {
        $url = "https://api.aladhan.com/v1/timings/{$today}?latitude={$lat}&longitude={$lng}&method=2&school=0";
        $response = wp_remote_get( $url, [ 'timeout' => 5 ] );
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $body['data']['timings'] ) ) {
                $aladhan = $body['data']['timings'];
                set_transient( $cache_key, $aladhan, 6 * HOUR_IN_SECONDS );
            }
        }
    }

    if ( $aladhan ) {
        $prayer_names = [ 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ];
        $prayer_keys = [ 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ];
        $now = current_time( 'H:i' );
        $walk_buffer = 15; // minutes
        $drive_buffer = 5;

        foreach ( $prayer_keys as $p ) {
            $raw = $aladhan[ $p ] ?? '';
            $time = preg_replace( '/\s*\(.*\)/', '', $raw );
            $time = substr( $time, 0, 5 ); // HH:MM
            $_ynj_prayer[ strtolower( $p ) ] = $time;

            $_ynj_prayer_overview[] = [
                'name' => $p,
                'time' => $time,
            ];

            // Find next prayer (skip Sunrise)
            if ( $p !== 'Sunrise' && ! $_ynj_next_prayer && $time > $now ) {
                $_ynj_next_prayer = $p;
                $_ynj_next_time = $time;
                $_ynj_next_name = $p;

                // Calculate leave-by times
                $prayer_ts = strtotime( 'today ' . $time );
                $walk_ts = $prayer_ts - ( $walk_buffer * 60 );
                $drive_ts = $prayer_ts - ( $drive_buffer * 60 );
                $_ynj_walk_leave = date( 'H:i', $walk_ts );
                $_ynj_drive_leave = date( 'H:i', $drive_ts );
            }
        }

        // If all prayers passed
        if ( ! $_ynj_next_prayer ) {
            $_ynj_next_name = 'All prayers completed';
            $_ynj_next_time = 'See you at Fajr tomorrow';
        }
    }
}

$_hp_mosque_name = $_ynj_mosque_for_prayer ? $_ynj_mosque_for_prayer->name : '';
?>

<!-- User onboarding overlay (first-time visitors) -->
<div id="ynj-onboard" style="display:none;position:fixed;inset:0;z-index:500;background:linear-gradient(180deg,#0a1628 0%,#1a3a5c 50%,#00ADEF 100%);color:#fff;overflow-y:auto;">
    <div style="max-width:400px;margin:0 auto;padding:40px 24px;text-align:center;">
        <img src="<?php echo esc_url( YNJ_THEME_URI . '/assets/icons/logo2.png' ); ?>" alt="YourJannah" style="height:48px;width:auto;margin:0 auto 20px;">
        <h1 style="font-size:24px;font-weight:800;margin-bottom:8px;"><?php esc_html_e( 'Welcome to YourJannah', 'yourjannah' ); ?></h1>
        <p style="font-size:14px;opacity:.8;margin-bottom:30px;"><?php esc_html_e( 'Your mosque community app — prayer times, events, donate, and more.', 'yourjannah' ); ?></p>

        <div style="background:rgba(255,255,255,.1);backdrop-filter:blur(8px);border-radius:16px;padding:24px;text-align:left;">
            <div style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:4px;"><?php esc_html_e( 'Your Name', 'yourjannah' ); ?></label>
                <input type="text" id="ob-name" placeholder="<?php esc_attr_e( 'Full name', 'yourjannah' ); ?>" style="width:100%;padding:12px 16px;border:1px solid rgba(255,255,255,.3);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:15px;font-family:inherit;outline:none;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:4px;"><?php esc_html_e( 'Email', 'yourjannah' ); ?></label>
                <input type="email" id="ob-email" placeholder="your@email.com" style="width:100%;padding:12px 16px;border:1px solid rgba(255,255,255,.3);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:15px;font-family:inherit;outline:none;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:4px;"><?php esc_html_e( 'Password (6+ characters)', 'yourjannah' ); ?></label>
                <input type="password" id="ob-pass" placeholder="<?php esc_attr_e( 'Create a password', 'yourjannah' ); ?>" style="width:100%;padding:12px 16px;border:1px solid rgba(255,255,255,.3);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:15px;font-family:inherit;outline:none;">
            </div>
            <button id="ob-submit" onclick="submitOnboard()" style="width:100%;padding:14px;border:none;border-radius:12px;background:#fff;color:#0a1628;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;">
                <?php esc_html_e( 'Get Started', 'yourjannah' ); ?>
            </button>
            <p id="ob-error" style="color:#fca5a5;font-size:13px;text-align:center;margin-top:8px;"></p>
        </div>

        <p style="margin-top:16px;font-size:13px;opacity:.6;">
            <?php esc_html_e( 'Already have an account?', 'yourjannah' ); ?>
            <a href="#" onclick="skipOnboard();return false;" style="color:#fff;font-weight:700;text-decoration:underline;"><?php esc_html_e( 'Skip', 'yourjannah' ); ?></a>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" style="color:#fff;font-weight:700;text-decoration:underline;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
        </p>
    </div>
</div>
<script>
(function(){
    // Handle mosque selection from /change-mosque page
    var params = new URLSearchParams(window.location.search);
    var selectSlug = params.get('ynj_select');
    if (selectSlug) {
        localStorage.setItem('ynj_mosque_slug', selectSlug);
        localStorage.removeItem('ynj_cache_date');
        localStorage.removeItem('ynj_cached_prayers');
        localStorage.removeItem('ynj_cached_feed');
        localStorage.removeItem('ynj_mosque_name');
        // Clean URL and reload
        window.history.replaceState({}, '', '/');
        window.location.reload();
        return;
    }

    // Show onboarding if no token and no "seen" flag
    var hasToken = !!localStorage.getItem('ynj_user_token');
    var hasSeen = !!localStorage.getItem('ynj_onboard_seen');
    if (!hasToken && !hasSeen) {
        document.getElementById('ynj-onboard').style.display = '';
    }

    window.submitOnboard = async function() {
        var name = document.getElementById('ob-name').value.trim();
        var email = document.getElementById('ob-email').value.trim();
        var pass = document.getElementById('ob-pass').value;
        var errEl = document.getElementById('ob-error');

        if (!name || !email) { errEl.textContent = 'Name and email required.'; return; }
        if (pass && pass.length < 6) { errEl.textContent = 'Password must be 6+ characters.'; return; }

        var btn = document.getElementById('ob-submit');
        btn.disabled = true; btn.textContent = 'Creating account...';

        try {
            if (pass) {
                // Full registration
                var resp = await fetch(ynjData.restUrl + 'auth/register', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({name:name, email:email, password:pass, mosque_slug: localStorage.getItem('ynj_mosque_slug') || ''})
                });
                var data = await resp.json();
                if (data.ok && data.token) {
                    localStorage.setItem('ynj_user_token', data.token);
                    if (data.user) localStorage.setItem('ynj_user', JSON.stringify(data.user));
                }
            }
            // Mark as seen regardless
            localStorage.setItem('ynj_onboard_seen', '1');
            localStorage.setItem('ynj_user_name', name);
            localStorage.setItem('ynj_user_email', email);
            document.getElementById('ynj-onboard').style.display = 'none';
        } catch(e) {
            errEl.textContent = 'Could not create account. You can skip and register later.';
            btn.disabled = false; btn.textContent = 'Get Started';
        }
    };

    window.skipOnboard = function() {
        localStorage.setItem('ynj_onboard_seen', '1');
        document.getElementById('ynj-onboard').style.display = 'none';
    };
})();
</script>

<main class="ynj-main">
  <div class="ynj-desktop-grid">
    <div class="ynj-desktop-grid__left">

    <!-- Ramadan banner (shown automatically during Ramadan) -->
    <div id="ramadan-banner" style="display:none;background:linear-gradient(135deg,#1a1628,#2d1b69);color:#fff;border-radius:14px;padding:14px 18px;margin-bottom:10px;"></div>

    <!-- Patron Membership CTA -->
    <div class="ynj-patron-bar" id="patron-hero">
        <a href="#" class="ynj-patron-bar__label" data-nav-mosque="/mosque/{slug}/patron">🏅 <strong id="patron-bar-text"><?php esc_html_e( 'Become a Patron', 'yourjannah' ); ?></strong></a>
        <div class="ynj-patron-bar__tiers">
            <a href="#" class="ynj-patron-chip" data-nav-mosque="/mosque/{slug}/patron">£5</a>
            <a href="#" class="ynj-patron-chip" data-nav-mosque="/mosque/{slug}/patron">£10</a>
            <a href="#" class="ynj-patron-chip ynj-patron-chip--popular" data-nav-mosque="/mosque/{slug}/patron"><span class="ynj-patron-chip__pop"><?php esc_html_e( 'Popular', 'yourjannah' ); ?></span>£20</a>
            <a href="#" class="ynj-patron-chip" data-nav-mosque="/mosque/{slug}/patron">£50</a>
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
        <p class="ynj-label" id="next-prayer-label"><?php echo $_hp_mosque_name ? esc_html( 'Next Prayer at ' . $_hp_mosque_name ) : esc_html__( 'Next Prayer', 'yourjannah' ); ?></p>
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

    <!-- Location detected via GPS automatically -->

    <!-- Prayer Overview (PHP-rendered) -->
    <?php if ( ! empty( $_ynj_prayer_overview ) ) : ?>
    <section class="ynj-card ynj-card--compact" id="prayer-overview" style="padding:14px 18px;">
        <div class="ynj-prayer-overview" id="prayer-overview-grid">
        <?php foreach ( $_ynj_prayer_overview as $po ) :
            if ( $po['name'] === 'Sunrise' ) continue;
            $is_next = ( strtolower( $po['name'] ) === strtolower( $_ynj_next_name ) );
        ?>
            <div class="ynj-po-item<?php echo $is_next ? ' ynj-po-item--active' : ''; ?>">
                <span class="ynj-po-name"><?php echo esc_html( $po['name'] ); ?></span>
                <span class="ynj-po-time"><?php echo esc_html( $po['time'] ); ?></span>
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

    <!-- Donate button — always visible, JS upgrades to DFM link -->
    <a class="ynj-donate-btn" id="donate-btn" href="#" data-nav-mosque="/mosque/{slug}/fundraising">
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
    <a class="ynj-timetable-link" id="timetable-link" href="#">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <?php esc_html_e( 'View Full Timetable', 'yourjannah' ); ?>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </a>

    <!-- Support your masjid CTAs -->
    <div class="ynj-support-row">
        <a class="ynj-support-card ynj-support-card--sponsor" id="cta-sponsor" href="#" data-nav-mosque="/mosque/{slug}/sponsors">
            <span class="ynj-support-card__icon">⭐</span>
            <strong><?php esc_html_e( 'Sponsor Your Masjid', 'yourjannah' ); ?></strong>
            <span class="ynj-support-card__sub"><?php esc_html_e( 'List your business — reach the community', 'yourjannah' ); ?></span>
            <span class="ynj-support-card__help" id="cta-sponsor-help"><?php esc_html_e( 'Funds go to supporting the masjid', 'yourjannah' ); ?></span>
        </a>
        <a class="ynj-support-card ynj-support-card--services" id="cta-services" href="#" data-nav-mosque="/mosque/{slug}/services">
            <span class="ynj-support-card__icon">🤝</span>
            <strong><?php esc_html_e( 'Advertise Services', 'yourjannah' ); ?></strong>
            <span class="ynj-support-card__sub"><?php esc_html_e( 'Professionals — get found locally', 'yourjannah' ); ?></span>
            <span class="ynj-support-card__help" id="cta-services-help"><?php esc_html_e( 'Proceeds help fund the masjid', 'yourjannah' ); ?></span>
        </a>
    </div>

    <!-- (Patron CTA moved above sponsor ticker) -->

    </div><!-- end left column -->
    <div class="ynj-desktop-grid__right">

    <!-- Feed -->
    <section id="feed-section">
        <h3 id="feed-heading" style="font-size:16px;font-weight:700;margin:0 0 10px;"><?php esc_html_e( 'What\'s Happening', 'yourjannah' ); ?></h3>

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

<!-- Mosque selector dropdown -->
<div class="ynj-dropdown" id="mosque-dropdown" style="display:none;">
    <div class="ynj-dropdown__inner">
        <input class="ynj-dropdown__search" id="mosque-search" type="text" placeholder="<?php esc_attr_e( 'Search mosques...', 'yourjannah' ); ?>" autocomplete="off">
        <div class="ynj-dropdown__list" id="mosque-list"></div>
    </div>
</div>

<?php
get_footer();
