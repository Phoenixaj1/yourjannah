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

    // Cache Aladhan response for 6 hours — negative cache for 1 hour on failure
    $cache_key = 'ynj_aladhan_' . md5( $lat . $lng . $today );
    $aladhan = get_transient( $cache_key );

    if ( false === $aladhan ) {
        // Check negative cache — don't retry for 1 hour after failure
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
            if ( ! $aladhan ) {
                // Negative cache: don't try again for 1 hour
                set_transient( $fail_key, 1, HOUR_IN_SECONDS );
            }
        }
        // Fallback: use prayer_times table if imported via dashboard
        if ( ! $aladhan ) {
            $_pt_all = class_exists( 'YNJ_Prayer_Times_Data' ) ? YNJ_Prayer_Times_Data::get_times( (int) $_ynj_mosque_for_prayer->id ) : [];
            $db_times = null;
            if ( is_array( $_pt_all ) ) {
                foreach ( $_pt_all as $_ptr ) { if ( isset( $_ptr->date ) && $_ptr->date === date( 'Y-m-d' ) ) { $db_times = $_ptr; break; } }
            } elseif ( is_object( $_pt_all ) ) { $db_times = $_pt_all; }
            if ( $db_times ) {
                $aladhan = [
                    'Fajr'    => $db_times->fajr,
                    'Sunrise' => $db_times->sunrise,
                    'Dhuhr'   => $db_times->dhuhr,
                    'Asr'     => $db_times->asr,
                    'Maghrib' => $db_times->maghrib,
                    'Isha'    => $db_times->isha,
                ];
            }
        }
    }

    // Load jamat times from DB (override adhan times for countdown)
    $_ynj_jamat = [];
    $_ynj_jumuah_slots = [];
    $_ynj_is_friday = ( date( 'N' ) == 5 );
    if ( $_ynj_mosque_for_prayer ) {
        $_pt_today = class_exists( 'YNJ_Prayer_Times_Data' ) ? YNJ_Prayer_Times_Data::get_times( (int) $_ynj_mosque_for_prayer->id ) : [];
        $db_row = null;
        if ( is_array( $_pt_today ) ) {
            foreach ( $_pt_today as $_ptr ) { if ( isset( $_ptr->date ) && $_ptr->date === date( 'Y-m-d' ) ) { $db_row = $_ptr; break; } }
        } elseif ( is_object( $_pt_today ) ) { $db_row = $_pt_today; }
        if ( $db_row ) {
            foreach ( [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ] as $pk ) {
                $jk = $pk . '_jamat';
                if ( ! empty( $db_row->$jk ) ) $_ynj_jamat[ $pk ] = substr( $db_row->$jk, 0, 5 );
            }
        }
        // Load Jumu'ah slots for Friday
        if ( $_ynj_is_friday ) {
            $_ynj_jumuah_slots = class_exists( 'YNJ_Jumuah_Data' ) ? YNJ_Jumuah_Data::get_times( (int) $_ynj_mosque_for_prayer->id ) : [];
            if ( ! is_array( $_ynj_jumuah_slots ) ) $_ynj_jumuah_slots = [];
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

            // Use jamat time if available (for countdown and leave-by)
            $display_time = isset( $_ynj_jamat[ $pk ] ) ? $_ynj_jamat[ $pk ] : $time;
            $jamat_display = isset( $_ynj_jamat[ $pk ] ) ? $_ynj_jamat[ $pk ] : '';

            // On Friday, ALWAYS replace Dhuhr with Jumu'ah
            if ( $_ynj_is_friday && $p === 'Dhuhr' ) {
                // Use DB slot time if available, otherwise use Dhuhr time
                if ( ! empty( $_ynj_jumuah_slots ) ) {
                    $jumuah_time = substr( $_ynj_jumuah_slots[0]->salah_time, 0, 5 );
                } else {
                    $jumuah_time = $display_time;
                }
                $_ynj_prayer_overview[] = [
                    'name'  => "Jumu'ah",
                    'time'  => $jumuah_time,
                    'jamat' => $jumuah_time,
                    'is_jumuah' => true,
                ];
                if ( ! $_ynj_next_prayer && $jumuah_time > $now ) {
                    $_ynj_next_prayer = "Jumu'ah";
                    $_ynj_next_time = $jumuah_time;
                    $_ynj_next_name = "Jumu'ah Mubarak 🕌";
                    $prayer_ts = strtotime( 'today ' . $jumuah_time );
                    $_ynj_walk_leave = date( 'H:i', $prayer_ts - ( $walk_buffer * 60 ) );
                    $_ynj_drive_leave = date( 'H:i', $prayer_ts - ( $drive_buffer * 60 ) );
                }
            } else {
                $_ynj_prayer_overview[] = [
                    'name'  => $p,
                    'time'  => $time,
                    'jamat' => $jamat_display,
                ];

                // Find next prayer (skip Sunrise), use jamat time for countdown
                if ( $p !== 'Sunrise' && ! $_ynj_next_prayer && $display_time > $now ) {
                    $_ynj_next_prayer = $p;
                    $_ynj_next_time = $display_time;
                    $_ynj_next_name = $p;

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

$_hp_mosque_name = $_ynj_mosque_for_prayer ? $_ynj_mosque_for_prayer->name : '';
$_hp_mosque_id = $_ynj_mosque_for_prayer ? (int) $_ynj_mosque_for_prayer->id : 0;

// ── Pre-load ALL data in PHP (eliminates 7 JS API calls) ──
$_hp_jumuah = [];
$_hp_sponsors = [];
$_hp_announcements = [];
$_hp_events = [];
$_hp_classes = [];
$_hp_points = [ 'total' => 0 ];

if ( $_hp_mosque_id ) {
    // Jumu'ah slots
    $_hp_jumuah = class_exists( 'YNJ_Jumuah_Data' ) ? YNJ_Jumuah_Data::get_times( $_hp_mosque_id ) : [];
    if ( ! is_array( $_hp_jumuah ) ) $_hp_jumuah = [];

    // Sponsor ticker (businesses for this mosque)
    $_hp_sponsors = class_exists( 'YNJ_Directory' ) ? YNJ_Directory::get_businesses( $_hp_mosque_id ) : [];
    if ( ! is_array( $_hp_sponsors ) ) $_hp_sponsors = [];

    // Service listings (people/professionals)
    // TODO: move to plugin — YNJ_Directory::get_services returns all; need RAND() LIMIT 10 variant
    $_hp_services = class_exists( 'YNJ_Directory' ) ? YNJ_Directory::get_services( $_hp_mosque_id ) : [];
    if ( ! is_array( $_hp_services ) ) $_hp_services = [];
    if ( count( $_hp_services ) > 10 ) { shuffle( $_hp_services ); $_hp_services = array_slice( $_hp_services, 0, 10 ); }

    // Announcements — get_announcements returns {announcements:[], total:N}, extract + cast to objects
    if ( class_exists( 'YNJ_Events' ) ) {
        $ann_result = YNJ_Events::get_announcements( $_hp_mosque_id );
        $_hp_announcements = array_map( function( $a ) { return (object) $a; }, $ann_result['announcements'] ?? [] );
    }

    // Upcoming events — get_upcoming_events returns {events:[], total:N}, extract + cast to objects
    if ( class_exists( 'YNJ_Events' ) ) {
        $ev_result = YNJ_Events::get_upcoming_events( $_hp_mosque_id );
        $_hp_events = array_map( function( $e ) { return (object) $e; }, $ev_result['events'] ?? [] );
    }

    // Classes
    $_hp_classes = class_exists( 'YNJ_Madrassah' ) ? YNJ_Madrassah::get_classes( $_hp_mosque_id ) : [];
    if ( ! is_array( $_hp_classes ) ) $_hp_classes = [];

    // User points via plugin
    if ( is_user_logged_in() ) {
        $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        if ( $ynj_uid && class_exists( 'YNJ_People' ) ) {
            $_hp_points = [ 'ok' => true, 'total' => YNJ_People::get_total_points( $ynj_uid ) ];
        }
    }

    // Patron status check
    $_hp_patron_status = null;
    if ( is_user_logged_in() && $_hp_mosque_id ) {
        $_hp_p_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        if ( $_hp_p_uid && class_exists( 'YNJ_Donations' ) ) {
            $_hp_patron_status = YNJ_Donations::get_patron_status( $_hp_p_uid, $_hp_mosque_id );
        }
    }
}
?>

<!-- Auth modal now rendered by HUD plugin (auth-modal.php) -->
<?php /* OLD ONBOARD MODAL REMOVED — now in yn-jannah-hud plugin */ ?>
<?php if ( false ) : /* kept for reference — delete after confirming plugin works */ ?>
<div id="ynj-onboard-old" style="display:none;">
    <div style="max-width:420px;width:100%;background:linear-gradient(180deg,#0a1628 0%,#1a3a5c 60%,#00ADEF 100%);color:#fff;border-radius:24px;padding:36px 28px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);position:relative;">
        <img src="<?php echo esc_url( YNJ_THEME_URI . '/assets/icons/logo2.png' ); ?>" alt="YourJannah" style="height:40px;width:auto;margin:0 auto 12px;">
        <h1 style="font-size:20px;font-weight:800;margin-bottom:4px;"><?php esc_html_e( 'Follow Your Masjid Community', 'yourjannah' ); ?></h1>
        <p style="font-size:13px;opacity:.6;margin-bottom:20px;">Prayer times, events & community — all in one place</p>

        <!-- Mosque list first: auto-loads from GPS with spinner -->
        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:6px;">Select Your Masjid</label>
            <div id="ob-mosque-list" style="text-align:left;max-height:200px;overflow-y:auto;margin-bottom:8px;">
                <div style="padding:16px;text-align:center;">
                    <div style="display:inline-block;width:20px;height:20px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:ob-spin 0.6s linear infinite;"></div>
                    <div style="font-size:13px;opacity:.6;margin-top:8px;">Finding mosques near you...</div>
                </div>
            </div>
            <input type="text" id="ob-search-input" placeholder="🔍 Search mosque by name..." oninput="obSearchMosques(this.value)" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,.3);border-radius:10px;background:rgba(255,255,255,.15);color:#fff;font-size:13px;font-family:inherit;outline:none;" class="ob-search-ph">
        </div>
        <style>@keyframes ob-spin{to{transform:rotate(360deg);}}</style>

        <!-- ═══ Step A: Email field (shared) ═══ -->
        <div id="ob-email-row" style="text-align:left;margin-bottom:14px;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Your Email', 'yourjannah' ); ?></label>
            <input type="email" id="ob-email" placeholder="your@email.com" autocomplete="email" style="width:100%;padding:13px 16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:16px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='rgba(255,255,255,.6)'" onblur="this.style.borderColor='rgba(255,255,255,.35)'">
        </div>

        <!-- ═══ Step B: PIN for sign-in (existing user) ═══ -->
        <div id="ob-pin-row" style="display:none;margin-bottom:14px;text-align:left;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Enter your PIN', 'yourjannah' ); ?></label>
            <input type="tel" id="ob-pin" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="&#x2022; &#x2022; &#x2022; &#x2022;" autocomplete="off" style="width:100%;padding:16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:32px;font-weight:900;letter-spacing:14px;text-align:center;font-family:inherit;outline:none;box-sizing:border-box;">
            <a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" style="font-size:11px;color:rgba(255,255,255,.45);margin-top:5px;display:block;"><?php esc_html_e( 'Forgot PIN?', 'yourjannah' ); ?></a>
        </div>

        <!-- ═══ Step C: Create PIN (new user / migration) ═══ -->
        <div id="ob-newpin-row" style="display:none;margin-bottom:14px;text-align:left;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Choose a 4-digit PIN', 'yourjannah' ); ?></label>
            <input type="tel" id="ob-newpin" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="&#x2022; &#x2022; &#x2022; &#x2022;" autocomplete="off" style="width:100%;padding:16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:32px;font-weight:900;letter-spacing:14px;text-align:center;font-family:inherit;outline:none;box-sizing:border-box;margin-bottom:10px;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Confirm PIN', 'yourjannah' ); ?></label>
            <input type="tel" id="ob-newpin2" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="&#x2022; &#x2022; &#x2022; &#x2022;" autocomplete="off" style="width:100%;padding:16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:32px;font-weight:900;letter-spacing:14px;text-align:center;font-family:inherit;outline:none;box-sizing:border-box;">
        </div>

        <!-- ═══ Action button (changes based on flow) ═══ -->
        <button id="ob-submit" style="display:none;width:100%;padding:14px;border:none;border-radius:12px;background:#fff;color:#0a1628;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;"></button>
        <p id="ob-error" style="color:#fca5a5;font-size:13px;text-align:center;margin-top:8px;"></p>

        <!-- ═══ Three clear CTAs: Sign In / Sign Up / Guest ═══ -->
        <div id="ob-cta-buttons" style="margin-top:4px;">
            <div style="display:flex;gap:8px;margin-bottom:10px;">
                <button onclick="obStartSignIn()" style="flex:1;padding:14px;border:none;border-radius:12px;background:#fff;color:#0a1628;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></button>
                <button onclick="obStartSignUp()" style="flex:1;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#287e61,#1a5c43);color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;border:none;"><?php esc_html_e( 'Sign Up', 'yourjannah' ); ?></button>
            </div>
            <div style="text-align:center;">
                <a href="#" onclick="obSkip();return false;" style="font-size:13px;color:rgba(255,255,255,.45);text-decoration:none;"><?php esc_html_e( 'Continue as guest', 'yourjannah' ); ?></a>
            </div>
        </div>
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
        window.history.replaceState({}, '', '/');
        setTimeout(function(){ window.location.reload(); }, 500);
        return;
    }

    var obSelectedSlug = '';
    var obSelectedName = '';
    var obEmailExists = false;
    var API = '<?php echo esc_url_raw( rest_url( 'ynj/v1/' ) ); ?>';

    // GPS listener: watchPosition waits for user to click Allow, then fires on first fix
    var obGpsWatchId = null;
    var obGotFix = false;
    window.obAutoGps = function() {
        var listEl = document.getElementById('ob-mosque-list');
        listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">📍 Waiting for location... click Allow above</div>';

        if (!navigator.geolocation) {
            listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">Location not supported. Search below.</div>';
            return;
        }

        // watchPosition keeps listening — no timeout, waits for permission + first fix
        obGpsWatchId = navigator.geolocation.watchPosition(
            function(pos) {
                if (obGotFix) return;
                obGotFix = true;
                navigator.geolocation.clearWatch(obGpsWatchId);
                // Got location — fetch nearby mosques via AJAX
                listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">📍 Loading nearby mosques...</div>';
                fetch(API + 'mosques/nearest?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude + '&limit=5')
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.ok && d.mosques && d.mosques.length) {
                            obRenderMosques(d.mosques);
                        } else {
                            listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">No mosques found nearby. Search below.</div>';
                        }
                    })
                    .catch(function(){
                        listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">Could not load mosques. Search below.</div>';
                    });
            },
            function(err) {
                if (obGotFix) return;
                // Only treat as final denial if it's PERMISSION_DENIED (code 1)
                if (err.code === 1) {
                    obGotFix = true;
                    navigator.geolocation.clearWatch(obGpsWatchId);
                    listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">Location denied. Search your mosque below.</div>';
                    document.getElementById('ob-search-input').focus();
                }
                // For POSITION_UNAVAILABLE (2) or TIMEOUT (3), keep listening
            },
            { enableHighAccuracy: false, maximumAge: 300000 }
        );
    };

    var searchTimer;
    window.obSearchMosques = function(q) {
        if (q.length < 2) return;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function(){
            fetch(API + 'mosques/search?q=' + encodeURIComponent(q) + '&limit=8')
                .then(function(r){ return r.json(); })
                .then(function(d){ if (d.ok) obRenderMosques(d.mosques || []); });
        }, 300);
    };

    function obRenderMosques(mosques) {
        var html = '';
        mosques.forEach(function(m, i) {
            var dist = m.distance ? parseFloat(m.distance).toFixed(1) + ' mi' : '';
            var isFirst = (i === 0 && !obSelectedSlug);
            html += '<div class="ob-mosque-item" data-slug="' + m.slug + '" onclick="obSelectMosque(\'' + m.slug + '\',\'' + (m.name||'').replace(/'/g,"\\'") + '\',\'' + (m.city||'').replace(/'/g,"\\'") + '\')" style="padding:8px 12px;background:' + (isFirst ? 'rgba(0,173,239,.2)' : 'rgba(255,255,255,.08)') + ';border-radius:8px;margin-bottom:4px;cursor:pointer;border:2px solid ' + (isFirst ? '#00ADEF' : 'transparent') + ';transition:all .15s;display:flex;justify-content:space-between;align-items:center;">'
                + '<div><div style="font-weight:600;font-size:13px;">' + (m.name||'') + '</div>'
                + '<div style="font-size:11px;opacity:.5;">' + (m.city||'') + '</div></div>'
                + (dist ? '<span style="font-size:11px;opacity:.5;white-space:nowrap;">' + dist + '</span>' : '')
                + '</div>';
            // Auto-select first mosque
            if (isFirst) {
                obSelectedSlug = m.slug;
                obSelectedName = m.name;
                localStorage.setItem('ynj_mosque_slug', m.slug);
                localStorage.setItem('ynj_mosque_name', m.name);
            }
        });
        if (!mosques.length) html = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">No mosques found. Search below.</div>';
        document.getElementById('ob-mosque-list').innerHTML = html;
        if (obSelectedName) document.getElementById('ob-submit').textContent = 'Join ' + obSelectedName;
    }

    // Select mosque — highlight it in the list
    window.obSelectMosque = function(slug, name, city) {
        obSelectedSlug = slug;
        obSelectedName = name;
        localStorage.setItem('ynj_mosque_slug', slug);
        localStorage.setItem('ynj_mosque_name', name);
        // Highlight selected
        document.querySelectorAll('.ob-mosque-item').forEach(function(el) {
            el.style.borderColor = el.dataset.slug === slug ? '#00ADEF' : 'transparent';
            el.style.background = el.dataset.slug === slug ? 'rgba(0,173,239,.2)' : 'rgba(255,255,255,.08)';
        });
        document.getElementById('ob-submit').textContent = 'Join ' + name;
    };

    // Step 3: Email check + register/login
    window.obSubmitEmail = async function() {
        var email = document.getElementById('ob-email').value.trim();
        var pin = document.getElementById('ob-pin').value.trim();
        var newpin = document.getElementById('ob-newpin').value.trim();
        var newpin2 = document.getElementById('ob-newpin2').value.trim();
        var errEl = document.getElementById('ob-error');
        var btn = document.getElementById('ob-submit');
        errEl.textContent = '';

        if (!email || email.indexOf('@') < 1) { errEl.textContent = 'Please enter a valid email.'; return; }

        // If PIN field is showing, this is a login attempt
        if (document.getElementById('ob-pin-row').style.display !== 'none' && pin) {
            if (pin.length < 4) { errEl.textContent = 'PIN must be at least 4 digits.'; return; }
            btn.disabled = true; btn.textContent = 'Signing in...';
            try {
                var resp = await fetch(API + 'auth/login', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({email: email, pin: pin})
                });
                var data = await resp.json();
                if (data.ok && data.token) {
                    localStorage.setItem('ynj_user_token', data.token);
                    localStorage.setItem('ynj_onboard_seen', '1');
                    // Redirect through server-side cookie setter
                    window.location.href = '/?ynj_autologin=' + (data.wp_user_id || '') + '&ynj_token=' + encodeURIComponent(data.token) + '&redirect=' + encodeURIComponent(window.location.pathname);
                } else {
                    errEl.textContent = data.error || 'Incorrect PIN. Try again.';
                    btn.disabled = false; btn.textContent = 'Sign In';
                }
            } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Sign In'; }
            return;
        }

        // If new PIN fields are showing, this is registration
        if (document.getElementById('ob-newpin-row').style.display !== 'none' && newpin) {
            if (newpin.length < 4) { errEl.textContent = 'PIN must be at least 4 digits.'; return; }
            if (!/^\d+$/.test(newpin)) { errEl.textContent = 'PIN must be numbers only.'; return; }
            if (newpin !== newpin2) { errEl.textContent = "PINs don't match. Try again."; document.getElementById('ob-newpin2').value = ''; document.getElementById('ob-newpin2').focus(); return; }

            btn.disabled = true;

            if (window._obSetPinForExisting) {
                // Existing user setting PIN for the first time
                btn.textContent = 'Setting your PIN...';
                try {
                    var setResp = await fetch(API + 'auth/set-pin', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({email: email, pin: newpin})
                    });
                    var setData = await setResp.json();
                    if (setData.ok && setData.token) {
                        localStorage.setItem('ynj_user_token', setData.token);
                        localStorage.setItem('ynj_onboard_seen', '1');
                        // Redirect through server-side cookie setter
                        window.location.href = '/?ynj_autologin=' + (setData.wp_user_id || '') + '&ynj_token=' + encodeURIComponent(setData.token) + '&redirect=' + encodeURIComponent(window.location.pathname);
                    } else {
                        errEl.textContent = setData.error || 'Could not set PIN. Try again.';
                        btn.disabled = false; btn.textContent = 'Set PIN & Sign In';
                    }
                } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Set PIN & Sign In'; }
            } else {
                // New user registration
                btn.textContent = 'Creating your account...';
                var name = email.split('@')[0].replace(/[._]/g, ' ');
                try {
                    var regResp = await fetch(API + 'auth/register', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({name: name, email: email, pin: newpin, mosque_slug: obSelectedSlug})
                    });
                    var regData = await regResp.json();
                    if (regData.ok && regData.token) {
                        localStorage.setItem('ynj_user_token', regData.token);
                        localStorage.setItem('ynj_onboard_seen', '1');
                        // Redirect through server-side cookie setter
                        window.location.href = '/?ynj_autologin=' + (regData.wp_user_id || '') + '&ynj_token=' + encodeURIComponent(regData.token) + '&redirect=' + encodeURIComponent(window.location.pathname);
                    } else {
                        errEl.textContent = regData.error || 'Registration failed. Try again.';
                        btn.disabled = false; btn.textContent = 'Create Account';
                    }
                } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Create Account'; }
            }
            return;
        }

        // First: check if email exists
        btn.disabled = true; btn.textContent = 'Checking...';
        try {
            var checkResp = await fetch(API + 'auth/check-email', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({email: email})
            });
            var checkData = await checkResp.json();

            if (checkData.exists) {
                if (checkData.has_pin) {
                    // Has PIN → enter it
                    document.getElementById('ob-pin-row').style.display = '';
                    document.getElementById('ob-pin').focus();
                    btn.textContent = 'Sign In';
                } else {
                    // Old password account → set a new PIN
                    document.getElementById('ob-newpin-row').style.display = '';
                    document.getElementById('ob-newpin').focus();
                    btn.textContent = 'Set PIN & Sign In';
                    window._obSetPinForExisting = true;
                }
                btn.disabled = false;
                return;
            }

            // New user → show create PIN fields
            window._obSetPinForExisting = false;
            document.getElementById('ob-newpin-row').style.display = '';
            document.getElementById('ob-newpin').focus();
            btn.textContent = 'Create Account';
            btn.disabled = false;
            return;
        } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Continue'; }
    };

    // Helper: auto-join mosque + reload after registration
    async function obJoinAndReload(regData) {
        try {
            await fetch(API + 'auth/join-mosque', {
                method: 'POST', headers: {'Content-Type':'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'},
                credentials: 'same-origin',
                body: JSON.stringify({mosque_slug: obSelectedSlug, set_primary: true})
            });
        } catch(e) {}
        setTimeout(function(){ window.location.reload(); }, 500);
    }

    // ── Sign In flow: email → PIN ──
    window.obStartSignIn = function() {
        var email = document.getElementById('ob-email').value.trim();
        if (!email || email.indexOf('@') < 1) {
            document.getElementById('ob-error').textContent = 'Please enter your email first.';
            document.getElementById('ob-email').focus();
            return;
        }
        document.getElementById('ob-cta-buttons').style.display = 'none';
        document.getElementById('ob-newpin-row').style.display = 'none';
        document.getElementById('ob-pin-row').style.display = '';
        document.getElementById('ob-pin').focus();
        var btn = document.getElementById('ob-submit');
        btn.style.display = ''; btn.textContent = 'Sign In'; btn.onclick = function(){ obSubmitEmail(); };
        window._obSetPinForExisting = false;
        window._obIsSignIn = true;
    };

    // ── Sign Up flow: email → create PIN (twice) ──
    window.obStartSignUp = function() {
        var email = document.getElementById('ob-email').value.trim();
        if (!email || email.indexOf('@') < 1) {
            document.getElementById('ob-error').textContent = 'Please enter your email first.';
            document.getElementById('ob-email').focus();
            return;
        }
        document.getElementById('ob-cta-buttons').style.display = 'none';
        document.getElementById('ob-pin-row').style.display = 'none';
        document.getElementById('ob-newpin-row').style.display = '';
        document.getElementById('ob-newpin').focus();
        var btn = document.getElementById('ob-submit');
        btn.style.display = ''; btn.textContent = 'Create Account'; btn.onclick = function(){ obSubmitEmail(); };
        window._obSetPinForExisting = false;
        window._obIsSignIn = false;
    };

    window.obSkip = function() {
        localStorage.setItem('ynj_onboard_seen', '1');
        document.getElementById('ynj-onboard').style.display = 'none';
    };

    // ---- TRIGGER: show modal + start GPS (all functions defined above) ----
    var wpLoggedIn = document.cookie.indexOf('wordpress_logged_in_') !== -1;
    var hasToken = !!localStorage.getItem('ynj_user_token');
    var hasSeen = !!localStorage.getItem('ynj_onboard_seen');
    if (!wpLoggedIn && !hasToken && !hasSeen) {
        document.getElementById('ynj-onboard').style.display = 'flex';
        obAutoGps();
    }
})();
</script>
<?php endif; ?>

<?php
// ── Homepage membership status check ──
$_hp_is_member = false;
$_hp_is_primary = false;
// Live member count: real subscriptions + 1 (admin)
$_hp_mosque_id = $_ynj_mosque_for_prayer ? (int) $_ynj_mosque_for_prayer->id : 0;
$_hp_member_count = 1;
if ( $_hp_mosque_id ) {
    $_hp_member_count = class_exists( 'YNJ_Mosques' ) ? (int) YNJ_Mosques::get_member_count( $_hp_mosque_id ) : 1;
    if ( $_hp_member_count < 1 ) $_hp_member_count = 1;
}
if ( $_hp_mosque_id && is_user_logged_in() ) {
    // TODO: move to plugin — no YNJ_Mosques::get_user_membership() method yet
    $ynj_uid_hp = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
    if ( $ynj_uid_hp && class_exists( 'YNJ_DB' ) ) {
        global $wpdb;
        $sub_tbl = YNJ_DB::table( 'user_subscriptions' );
        $hp_mem = $wpdb->get_row( $wpdb->prepare(
            "SELECT is_member, is_primary FROM $sub_tbl WHERE user_id = %d AND mosque_id = %d AND status = 'active'",
            $ynj_uid_hp, $_hp_mosque_id
        ) );
        if ( $hp_mem ) {
            $_hp_is_member = (bool) $hp_mem->is_member;
            $_hp_is_primary = (bool) $hp_mem->is_primary;
        }
    }
}
?>

<?php
// ── Mosque Cover + Profile Photo ──
$_hp_cover_url   = $_hp_mosque_id ? get_option( 'ynj_mosque_cover_' . $_hp_mosque_id, '' ) : '';
$_hp_profile_url = $_hp_mosque_id ? get_option( 'ynj_mosque_profile_' . $_hp_mosque_id, '' ) : '';
$_hp_mosque_addr = $_ynj_mosque_for_prayer ? ( $_ynj_mosque_for_prayer->address ?? '' ) : '';
?>
<?php if ( $_hp_mosque_id ) : ?>
<div class="ynj-mosque-banner" style="position:relative;width:100%;max-width:1200px;margin:0 auto 0;">
    <div style="position:relative;width:100%;height:200px;border-radius:0 0 18px 18px;overflow:hidden;background:<?php echo $_hp_cover_url ? 'url(' . esc_url( $_hp_cover_url ) . ') center/cover no-repeat' : 'linear-gradient(135deg,#1a3a2a,#2d6a4f,#40916c)'; ?>;">
    </div>
    <div style="position:absolute;bottom:-36px;left:20px;z-index:2;">
        <div style="width:90px;height:90px;border-radius:50%;border:4px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.15);background:<?php echo $_hp_profile_url ? 'url(' . esc_url( $_hp_profile_url ) . ') center/cover no-repeat' : 'linear-gradient(135deg,#065f46,#10b981)'; ?>;display:flex;align-items:center;justify-content:center;">
            <?php if ( ! $_hp_profile_url ) : ?><span style="font-size:36px;">🕌</span><?php endif; ?>
        </div>
    </div>
</div>
<div style="max-width:1200px;margin:0 auto;padding:8px 16px 0 126px;min-height:44px;display:flex;align-items:center;">
    <div>
        <h1 style="margin:0;font-size:18px;font-weight:800;color:#1a1a1a;"><?php echo esc_html( $_hp_mosque_name ); ?></h1>
        <?php if ( $_hp_mosque_addr ) : ?>
        <p style="margin:2px 0 0;font-size:12px;color:#666;"><?php echo esc_html( $_hp_mosque_addr ); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<main class="ynj-main">
  <div class="ynj-desktop-grid">
    <div class="ynj-desktop-grid__left">

    <!-- Follow This Masjid + Follower Count -->
    <?php if ( $_hp_mosque_id ) : ?>
    <div class="ynj-join-bar" style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff;border-radius:14px;padding:12px 16px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:18px;">🕌</span>
            <span style="font-size:14px;font-weight:600;color:#333;">
                <?php echo number_format( $_hp_member_count ); ?> <?php echo $_hp_member_count === 1 ? 'follower' : 'followers'; ?>
            </span>
        </div>
        <?php if ( $_hp_is_member ) : ?>
            <div style="display:flex;align-items:center;gap:8px;">
                <?php if ( $_hp_is_primary ) : ?>
                    <span style="font-size:11px;color:#666;background:#f0f0f0;padding:2px 8px;border-radius:12px;"><?php esc_html_e( 'Primary', 'yourjannah' ); ?></span>
                <?php endif; ?>
                <span style="color:#27ae60;font-weight:600;font-size:13px;">&#x2713; <?php esc_html_e( 'Following', 'yourjannah' ); ?></span>
            </div>
        <?php elseif ( is_user_logged_in() ) : ?>
            <button onclick="ynjJoinMosqueHP(<?php echo $_hp_mosque_id; ?>)" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;">
                <?php esc_html_e( 'Follow This Masjid', 'yourjannah' ); ?>
            </button>
        <?php else : ?>
            <button onclick="if(typeof ynjAuthModalOpen==='function'){ynjAuthModalOpen({mosque_slug:'<?php echo esc_js($_hp_slug); ?>',mosque_name:'<?php echo esc_js($_hp_mosque_name); ?>'});}" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;">
                <?php esc_html_e( 'Follow This Masjid', 'yourjannah' ); ?>
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Ramadan banner (shown automatically during Ramadan) -->
    <div id="ramadan-banner" style="display:none;background:linear-gradient(135deg,#1a1628,#2d1b69);color:#fff;border-radius:14px;padding:14px 18px;margin-bottom:10px;"></div>

    <!-- Go Live button (broadcasters only) -->
    <?php
    $_hp_live_broadcast = null;
    $_hp_can_broadcast = false;
    if ( $_hp_mosque_id && class_exists( 'YNJ_Broadcast' ) ) {
        $_hp_live_broadcast = YNJ_Broadcast::get_live( $_hp_mosque_id );
        if ( is_user_logged_in() ) {
            $_hp_bc_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            $_hp_can_broadcast = $_hp_bc_uid && YNJ_Broadcast::can_broadcast( $_hp_bc_uid, $_hp_mosque_id );
        }
    }
    ?>
    <?php if ( $_hp_live_broadcast ) : ?>
    <div style="background:linear-gradient(135deg,#dc2626,#991b1b);border-radius:14px;padding:16px;margin-bottom:10px;color:#fff;">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="display:inline-block;width:10px;height:10px;background:#fff;border-radius:50%;animation:ynj-live-pulse 1.5s infinite;"></span>
            <span style="font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:1px;">LIVE NOW</span>
            <span style="font-size:13px;opacity:.8;margin-left:auto;"><?php echo esc_html( $_hp_live_broadcast->title ); ?></span>
        </div>
        <?php if ( $_hp_live_broadcast->youtube_video_id ) : ?>
        <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:10px;margin-top:10px;">
            <iframe src="https://www.youtube.com/embed/<?php echo esc_attr( $_hp_live_broadcast->youtube_video_id ); ?>?autoplay=1&mute=1" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;" allow="autoplay;fullscreen" allowfullscreen></iframe>
        </div>
        <?php endif; ?>
    </div>
    <style>@keyframes ynj-live-pulse{0%,100%{opacity:1;}50%{opacity:.3;}}</style>
    <?php elseif ( $_hp_can_broadcast ) : ?>
    <a href="<?php echo esc_url( home_url( '/mosque/' . $_hp_slug . '#go-live' ) ); ?>" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;background:linear-gradient(135deg,#dc2626,#991b1b);border:none;border-radius:14px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-bottom:10px;text-decoration:none;">
        🔴 <?php esc_html_e( 'Go Live', 'yourjannah' ); ?>
    </a>
    <?php endif; ?>

    <!-- Patron Membership (rendered by plugin) -->
    <?php if ( class_exists( 'YNJ_UI' ) ) YNJ_UI::render_patron_bar( $_hp_slug, $_hp_mosque_name, $_hp_patron_status ?? null ); ?>

    <!-- Sponsor Ticker (rendered by plugin) -->
    <?php if ( class_exists( 'YNJ_UI' ) ) YNJ_UI::render_sponsor_ticker(); ?>

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
        <?php
        // Arabic prayer names + Hijri date (same as mosque page)
        $_arabic_names = [
            'Fajr' => 'الفجر', 'Sunrise' => 'الشروق', 'Dhuhr' => 'الظهر',
            'Asr' => 'العصر', 'Maghrib' => 'المغرب', 'Isha' => 'العشاء',
            "Jumu'ah" => 'الجمعة',
        ];
        $_arabic_prayer = $_arabic_names[ $_ynj_next_name ] ?? '';
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
        <p class="ynj-label" id="next-prayer-label" style="color:#fbbf24;">🕌 <?php echo esc_html( 'It\'s Friday! Jumu\'ah at ' . ( $_hp_mosque_name ?: 'your masjid' ) ); ?></p>
        <?php else : ?>
        <p class="ynj-label" id="next-prayer-label"><?php echo $_hp_mosque_name ? esc_html( 'NEXT PRAYER AT ' . strtoupper( $_hp_mosque_name ) ) : esc_html__( 'NEXT PRAYER', 'yourjannah' ); ?></p>
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

    <!-- Location detected via GPS automatically -->

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
            <div class="ynj-po-item<?php echo $is_next ? ' ynj-po-item--active' : ''; ?><?php echo $is_jumuah ? ' ynj-po-item--jumuah' : ''; ?>">
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

    <!-- ═══ DHIKR CTA ═══ -->
    <?php if ( is_user_logged_in() && $_hp_mosque_id ) :
        $_hp_cta_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        $_hp_cta_streak = 0;
        $_hp_cta_done = 0;
        if ( $_hp_cta_uid ) {
            if ( class_exists( 'YNJ_Streaks' ) ) {
                $_hp_cta_streak = YNJ_Streaks::get_user_streak( $_hp_cta_uid );
            }
            for ( $i = 0; $i < 5; $i++ ) {
                if ( get_transient( 'ynj_dhikr_' . $_hp_cta_uid . '_' . date( 'Y-m-d' ) . '_' . $i ) ) $_hp_cta_done++;
            }
        }
        $_hp_cta_hours = 24 - (int) date( 'G' );
        $_hp_cta_complete = $_hp_cta_done >= 5;
        $_hp_cta_bg = $_hp_cta_complete ? 'background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;'
            : ( $_hp_cta_hours <= 3 ? 'background:linear-gradient(135deg,#fef2f2,#fee2e2);border:2px solid #ef4444;'
            : 'background:linear-gradient(135deg,#fefce8,#fef9c3);border:2px solid #fde68a;' );
    ?>
    <a href="<?php echo esc_url( home_url( '/profile#ibadah' ) ); ?>" class="ynj-card" style="display:block;text-decoration:none;padding:16px;<?php echo $_hp_cta_bg; ?>">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <?php if ( $_hp_cta_complete ) : ?>
                    <div style="font-size:14px;font-weight:800;color:#166534;">&#x2705; <?php esc_html_e( 'All 5 dhikr done today!', 'yourjannah' ); ?></div>
                    <div style="font-size:12px;color:#15803d;"><?php esc_html_e( 'MashaAllah! Come back tomorrow.', 'yourjannah' ); ?></div>
                <?php elseif ( $_hp_cta_done > 0 ) : ?>
                    <div style="font-size:14px;font-weight:800;color:#92400e;">&#x1F3AF; <?php printf( esc_html__( '%d of 5 done — %d more for +200 bonus!', 'yourjannah' ), $_hp_cta_done, 5 - $_hp_cta_done ); ?></div>
                    <div style="font-size:12px;color:#a16207;"><?php echo (int) $_hp_cta_hours; ?>h <?php esc_html_e( 'left', 'yourjannah' ); ?><?php if ( $_hp_cta_streak > 0 ) : ?> &middot; &#x1F525; <?php echo (int) $_hp_cta_streak; ?> <?php esc_html_e( 'day streak', 'yourjannah' ); ?><?php endif; ?></div>
                <?php else : ?>
                    <div style="font-size:14px;font-weight:800;color:#92400e;">&#x1F4FF; <?php esc_html_e( 'Say your daily dhikr', 'yourjannah' ); ?></div>
                    <div style="font-size:12px;color:#a16207;"><?php echo (int) $_hp_cta_hours; ?>h <?php esc_html_e( 'left', 'yourjannah' ); ?> &middot; 5 dhikr = <?php esc_html_e( '+200 bonus pts', 'yourjannah' ); ?></div>
                <?php endif; ?>
            </div>
            <div style="font-size:24px;font-weight:900;color:<?php echo $_hp_cta_complete ? '#166534' : '#d97706'; ?>;"><?php echo $_hp_cta_done; ?>/5</div>
        </div>
    </a>
    <?php endif; ?>

    <!-- ═══ GRATITUDE ═══ -->
    <?php if ( $_hp_mosque_id && is_user_logged_in() ) : ?>
    <button type="button" onclick="ynjPostGratitude()" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;background:linear-gradient(135deg,#fdf2f8,#fce7f3);border:1px solid #f9a8d4;border-radius:14px;font-size:14px;font-weight:700;color:#9d174d;cursor:pointer;font-family:inherit;margin-bottom:10px;">💖 <?php esc_html_e( 'Thank Your Mosque', 'yourjannah' ); ?></button>
    <?php endif; ?>

    <!-- ═══ PURIFY YOUR RIZQ — Daily sadaqah ═══ -->
    <?php if ( $_hp_mosque_id && is_user_logged_in() ) : ?>
    <div class="ynj-card" style="padding:16px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:2px solid #6ee7b7;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div>
                <div style="font-size:15px;font-weight:800;color:#065f46;">&#x1F4B0; <?php esc_html_e( 'Purify Your Rizq', 'yourjannah' ); ?></div>
                <div style="font-size:12px;color:#047857;"><?php esc_html_e( 'A small sadaqah each day cleanses your wealth', 'yourjannah' ); ?></div>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <?php foreach ( [ 100 => '£1', 300 => '£3', 500 => '£5' ] as $pence => $label ) : ?>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $_hp_slug . '/#purify-rizq' ) ); ?>" style="flex:1;padding:12px 0;border-radius:12px;border:2px solid #10b981;background:#fff;color:#065f46;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;">
                <?php echo esc_html( $label ); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <p style="font-size:11px;color:#047857;margin:8px 0 0;text-align:center;">
            <?php esc_html_e( 'Distributed to dawah, masjid building & international aid', 'yourjannah' ); ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Hadith -->
    <p class="ynj-hadith" id="hadith-line">
        <em>&ldquo;<?php esc_html_e( 'Prayer in congregation is twenty-seven times more virtuous than prayer offered alone.', 'yourjannah' ); ?>&rdquo;</em>
        <span>&mdash; Sahih al-Bukhari 645</span>
    </p>

    <!-- Almsgiving button -->
    <a class="ynj-donate-btn" id="donate-btn" href="#" data-nav-mosque="/mosque/{slug}/fundraising">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        <?php esc_html_e( 'Almsgiving', 'yourjannah' ); ?>
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

    <!-- People / Service Listings — rotating 5 at a time -->
    <?php if ( ! empty( $_hp_services ) ) :
        // Randomise and take 5 for this page load
        $display_services = array_slice( $_hp_services, 0, 5 );
    ?>
    <div style="margin-top:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <h3 style="font-size:13px;font-weight:700;color:#0a1628;margin:0;">🤝 <?php esc_html_e( 'Local Professionals', 'yourjannah' ); ?></h3>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $_hp_slug . '/sponsors' ) ); ?>" style="font-size:11px;font-weight:600;color:#00ADEF;text-decoration:none;"><?php esc_html_e( 'View All →', 'yourjannah' ); ?></a>
        </div>
        <?php foreach ( $display_services as $svc ) :
            $rate = $svc->hourly_rate_pence ? '£' . number_format( $svc->hourly_rate_pence / 100, 0 ) . '/hr' : '';
            $initial = strtoupper( substr( $svc->provider_name ?: '?', 0, 1 ) );
        ?>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $_hp_slug . '/service/' . $svc->id ) ); ?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:4px;border-radius:10px;background:#fff;border:1px solid #e5e7eb;text-decoration:none;color:#0a1628;transition:all .15s;">
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
        <a href="<?php echo esc_url( home_url( '/mosque/' . $_hp_slug . '/services/join' ) ); ?>" style="display:block;text-align:center;padding:8px;margin-top:4px;border-radius:8px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;font-size:12px;font-weight:700;text-decoration:none;">🤝 <?php esc_html_e( 'List Your Service — from £10/mo', 'yourjannah' ); ?></a>
    </div>
    <?php endif; ?>

    </div><!-- end left column -->
    <div class="ynj-desktop-grid__right">

    <!-- Superchats (rendered by plugin) -->
    <?php if ( class_exists( 'YNJ_Store' ) ) YNJ_Store::render_superchats( 'scroll' ); ?>

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

<!-- Pre-loaded data for homepage.js (eliminates 7 API calls) -->
<script>
window.ynjPreloaded = {
    jumuah: <?php echo wp_json_encode( $_hp_jumuah ); ?>,
    sponsors: <?php echo wp_json_encode( $_hp_sponsors ); ?>,
    announcements: <?php echo wp_json_encode( $_hp_announcements ); ?>,
    events: <?php echo wp_json_encode( $_hp_events ); ?>,
    classes: <?php echo wp_json_encode( $_hp_classes ); ?>,
    points: <?php echo wp_json_encode( $_hp_points ); ?>,
    mosqueId: <?php echo (int) $_hp_mosque_id; ?>,
    jumuahSlots: <?php echo wp_json_encode( array_map( function( $s ) { return [ 'slot_name' => $s->slot_name, 'khutbah' => substr( $s->khutbah_time, 0, 5 ), 'salah' => substr( $s->salah_time, 0, 5 ), 'language' => $s->language ]; }, $_ynj_jumuah_slots ) ); ?>
};

// On Friday, set first Jumu'ah time for homepage.js countdown
if (window.ynjPreloaded.jumuahSlots && window.ynjPreloaded.jumuahSlots.length > 0 && new Date().getDay() === 5) {
    window._ynjFirstJumuahTime = window.ynjPreloaded.jumuahSlots[0].salah;
}

// Homepage join function
async function ynjJoinMosqueHP(mosqueId) {
    try {
        const res = await fetch('/wp-json/ynj/v1/auth/join-mosque', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
            credentials: 'same-origin',
            body: JSON.stringify({ mosque_id: mosqueId })
        });
        const data = await res.json();
        if (data.ok) location.reload();
        else alert(data.error || 'Failed to join.');
    } catch(e) { alert('Network error.'); }
}
</script>

<?php
// ── Admin toolbar (same as mosque page) ──
$_hp_can_edit = false;
if ( $_hp_mosque_id && is_user_logged_in() ) {
    $_hp_wp_uid = get_current_user_id();
    $_hp_user_mosque = (int) get_user_meta( $_hp_wp_uid, 'ynj_mosque_id', true );
    $_hp_can_edit = ( $_hp_user_mosque === $_hp_mosque_id ) &&
                    ( current_user_can( 'ynj_manage_mosque' ) || current_user_can( 'manage_options' ) );
}
if ( $_hp_can_edit ) :
?>
<?php
$_hp_admin_slug = $_ynj_mosque_for_prayer ? $_ynj_mosque_for_prayer->slug : '';
if ( class_exists( 'YNJ_UI' ) ) YNJ_UI::render_admin_toolbar( $_hp_admin_slug );
?>
<?php endif; ?>

<?php
get_footer();
