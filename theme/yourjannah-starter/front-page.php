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
            global $wpdb;
            $pt_table = YNJ_DB::table( 'prayer_times' );
            $db_times = $wpdb->get_row( $wpdb->prepare(
                "SELECT fajr, sunrise, dhuhr, asr, maghrib, isha FROM $pt_table WHERE mosque_id = %d AND date = %s",
                (int) $_ynj_mosque_for_prayer->id, date( 'Y-m-d' )
            ) );
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
    if ( $_ynj_mosque_for_prayer && class_exists( 'YNJ_DB' ) ) {
        global $wpdb;
        $pt_table = YNJ_DB::table( 'prayer_times' );
        $db_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $pt_table WHERE mosque_id = %d AND date = %s",
            (int) $_ynj_mosque_for_prayer->id, date( 'Y-m-d' )
        ) );
        if ( $db_row ) {
            foreach ( [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ] as $pk ) {
                $jk = $pk . '_jamat';
                if ( ! empty( $db_row->$jk ) ) $_ynj_jamat[ $pk ] = substr( $db_row->$jk, 0, 5 );
            }
        }
        // Load Jumu'ah slots for Friday
        if ( $_ynj_is_friday ) {
            $jt = YNJ_DB::table( 'jumuah_times' );
            $_ynj_jumuah_slots = $wpdb->get_results( $wpdb->prepare(
                "SELECT slot_name, khutbah_time, salah_time, language FROM $jt WHERE mosque_id = %d AND enabled = 1 ORDER BY salah_time ASC",
                (int) $_ynj_mosque_for_prayer->id
            ) ) ?: [];
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

if ( $_hp_mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;

    // Jumu'ah slots
    $jt = YNJ_DB::table( 'jumuah_times' );
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$jt'" ) === $jt ) {
        $_hp_jumuah = $wpdb->get_results( $wpdb->prepare(
            "SELECT slot_name, khutbah_time, salah_time, language FROM $jt WHERE mosque_id = %d AND enabled = 1 ORDER BY salah_time ASC",
            $_hp_mosque_id
        ) ) ?: [];
    }

    // Sponsor ticker (businesses for this mosque)
    $bt = YNJ_DB::table( 'businesses' );
    $_hp_sponsors = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, business_name, category, monthly_fee_pence FROM $bt WHERE mosque_id = %d AND status = 'active' ORDER BY monthly_fee_pence DESC, business_name ASC LIMIT 20",
        $_hp_mosque_id
    ) ) ?: [];

    // Service listings (people/professionals)
    $svt = YNJ_DB::table( 'services' );
    $_hp_services = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, provider_name, service_type, phone, area_covered, hourly_rate_pence FROM $svt WHERE mosque_id = %d AND status = 'active' ORDER BY RAND() LIMIT 10",
        $_hp_mosque_id
    ) ) ?: [];

    // Announcements
    $at = YNJ_DB::table( 'announcements' );
    $_hp_announcements = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, body, type, pinned, published_at FROM $at WHERE mosque_id = %d AND status = 'published' ORDER BY pinned DESC, published_at DESC LIMIT 20",
        $_hp_mosque_id
    ) ) ?: [];

    // Upcoming events
    $et = YNJ_DB::table( 'events' );
    $_hp_events = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, description, event_date, start_time, end_time, location, category, ticket_price_pence, max_capacity, rsvp_count FROM $et WHERE mosque_id = %d AND status = 'published' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20",
        $_hp_mosque_id
    ) ) ?: [];

    // Classes
    $ct = YNJ_DB::table( 'classes' );
    $_hp_classes = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, description, instructor_name, day_of_week, start_time, end_time, price_pence, category, max_capacity, enrolled_count FROM $ct WHERE mosque_id = %d AND status = 'active' ORDER BY day_of_week ASC, start_time ASC LIMIT 20",
        $_hp_mosque_id
    ) ) ?: [];

    // User points (if logged in)
    if ( is_user_logged_in() ) {
        $ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
        if ( $ynj_uid ) {
            $ut = YNJ_DB::table( 'users' );
            $pts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT total_points FROM $ut WHERE id = %d", $ynj_uid ) );
            $_hp_points = [ 'ok' => true, 'total' => $pts ];
        }
    }
}
?>

<!-- Smart onboarding: GPS → Masjid → Email → In (always in DOM, JS controls visibility) -->
<div id="ynj-onboard" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);overflow-y:auto;align-items:center;justify-content:center;padding:20px;">
    <div style="max-width:420px;width:100%;background:linear-gradient(180deg,#0a1628 0%,#1a3a5c 60%,#00ADEF 100%);color:#fff;border-radius:24px;padding:36px 28px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);position:relative;">
        <img src="<?php echo esc_url( YNJ_THEME_URI . '/assets/icons/logo2.png' ); ?>" alt="YourJannah" style="height:40px;width:auto;margin:0 auto 12px;">
        <h1 style="font-size:20px;font-weight:800;margin-bottom:4px;">Join Your Masjid Community</h1>
        <p style="font-size:13px;opacity:.6;margin-bottom:20px;">Prayer times, events & community — all in one place</p>

        <!-- Single screen: Email + Mosque list (auto-loaded via GPS) -->
        <div style="text-align:left;margin-bottom:12px;">
            <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:4px;">Your Email</label>
            <input type="email" id="ob-email" placeholder="your@email.com" autocomplete="email" style="width:100%;padding:12px 16px;border:1px solid rgba(255,255,255,.3);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:15px;font-family:inherit;outline:none;">
        </div>
        <div id="ob-pass-row" style="display:none;margin-bottom:12px;text-align:left;">
            <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:4px;">Welcome back! Enter your password</label>
            <input type="password" id="ob-pass" placeholder="Your password" autocomplete="current-password" style="width:100%;padding:12px 16px;border:1px solid rgba(255,255,255,.3);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:15px;font-family:inherit;outline:none;">
            <a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" style="font-size:11px;color:rgba(255,255,255,.5);margin-top:4px;display:block;">Forgot password?</a>
        </div>

        <!-- Mosque list: auto-loads from GPS, or search -->
        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:6px;">Select Your Masjid</label>
            <div id="ob-mosque-list" style="text-align:left;max-height:200px;overflow-y:auto;margin-bottom:8px;">
                <div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">📍 Detecting your location...</div>
            </div>
            <input type="text" id="ob-search-input" placeholder="🔍 Search mosque by name..." oninput="obSearchMosques(this.value)" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.08);color:#fff;font-size:13px;font-family:inherit;outline:none;">
        </div>

        <button id="ob-submit" onclick="obSubmitEmail()" style="width:100%;padding:14px;border:none;border-radius:12px;background:#fff;color:#0a1628;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;">
            Join
        </button>
        <p id="ob-error" style="color:#fca5a5;font-size:13px;text-align:center;margin-top:8px;"></p>

        <p style="margin-top:16px;font-size:11px;opacity:.3;">
            <a href="#" onclick="obSkip();return false;" style="color:#fff;text-decoration:underline;">Skip for now</a>
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
        window.history.replaceState({}, '', '/');
        window.location.reload();
        return;
    }

    // Show onboarding if not logged in (WP or localStorage) and not seen
    var wpLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
    var hasToken = !!localStorage.getItem('ynj_user_token');
    var hasSeen = !!localStorage.getItem('ynj_onboard_seen');
    if (!wpLoggedIn && !hasToken && !hasSeen) {
        document.getElementById('ynj-onboard').style.display = 'flex';
        // Auto-trigger GPS immediately
        obAutoGps();
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
        var pass = document.getElementById('ob-pass').value;
        var errEl = document.getElementById('ob-error');
        var btn = document.getElementById('ob-submit');
        errEl.textContent = '';

        if (!email || email.indexOf('@') < 1) { errEl.textContent = 'Please enter a valid email.'; return; }

        // If password field is showing, this is a login attempt
        if (document.getElementById('ob-pass-row').style.display !== 'none' && pass) {
            btn.disabled = true; btn.textContent = 'Signing in...';
            try {
                var resp = await fetch(API + 'auth/login', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({email: email, password: pass})
                });
                var data = await resp.json();
                if (data.ok && data.token) {
                    localStorage.setItem('ynj_user_token', data.token);
                    localStorage.setItem('ynj_onboard_seen', '1');
                    // Set WP session
                    if (data.wp_user_id) {
                        await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
                            body: 'action=ynj_set_session&wp_user_id=' + data.wp_user_id, credentials: 'same-origin'
                        });
                    }
                    window.location.reload();
                } else {
                    errEl.textContent = data.error || 'Incorrect password. Try again.';
                    btn.disabled = false; btn.textContent = 'Sign In';
                }
            } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Sign In'; }
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
                // Show password field
                document.getElementById('ob-pass-row').style.display = '';
                document.getElementById('ob-pass').focus();
                btn.textContent = 'Sign In';
                btn.disabled = false;
                return;
            }

            // New user: auto-register
            btn.textContent = 'Creating your account...';
            var name = email.split('@')[0].replace(/[._]/g, ' ');
            var autoPass = 'YJ_' + Math.random().toString(36).slice(2, 10) + '!';
            var regResp = await fetch(API + 'auth/register', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({name: name, email: email, password: autoPass, mosque_slug: obSelectedSlug})
            });
            var regData = await regResp.json();
            if (regData.ok && regData.token) {
                localStorage.setItem('ynj_user_token', regData.token);
                localStorage.setItem('ynj_onboard_seen', '1');
                // Join the mosque as member
                if (regData.wp_user_id) {
                    await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: 'action=ynj_set_session&wp_user_id=' + regData.wp_user_id, credentials: 'same-origin'
                    });
                }
                // Auto-join mosque
                try {
                    await fetch(API + 'auth/join-mosque', {
                        method: 'POST', headers: {'Content-Type':'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'},
                        credentials: 'same-origin',
                        body: JSON.stringify({mosque_slug: obSelectedSlug, set_primary: true})
                    });
                } catch(e) {}
                window.location.reload();
            } else {
                errEl.textContent = regData.error || 'Could not create account. Try again.';
                btn.disabled = false; btn.textContent = 'Continue';
            }
        } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Continue'; }
    };

    window.obSkip = function() {
        localStorage.setItem('ynj_onboard_seen', '1');
        document.getElementById('ynj-onboard').style.display = 'none';
    };
})();
</script>

<?php
// ── Homepage membership status check ──
$_hp_is_member = false;
$_hp_is_primary = false;
$_hp_member_count = $_ynj_mosque_for_prayer ? (int) ( $_ynj_mosque_for_prayer->member_count ?? 0 ) : 0;
$_hp_mosque_id = $_ynj_mosque_for_prayer ? (int) $_ynj_mosque_for_prayer->id : 0;
// Social proof: under 20 real members show a seeded number (5-20)
if ( $_hp_member_count < 20 && $_ynj_mosque_for_prayer ) {
    $_hp_member_count = ( crc32( $_ynj_mosque_for_prayer->slug ?? '' ) % 16 ) + 5;
}
if ( $_hp_mosque_id && is_user_logged_in() ) {
    $ynj_uid_hp = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
    if ( $ynj_uid_hp ) {
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

<main class="ynj-main">
  <div class="ynj-desktop-grid">
    <div class="ynj-desktop-grid__left">

    <!-- Join This Masjid + Member Count -->
    <?php if ( $_hp_mosque_id ) : ?>
    <div class="ynj-join-bar" style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fff;border-radius:14px;padding:12px 16px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:18px;">🕌</span>
            <span style="font-size:14px;font-weight:600;color:#333;">
                <?php echo number_format( $_hp_member_count ); ?> <?php echo $_hp_member_count === 1 ? 'member' : 'members'; ?>
            </span>
        </div>
        <?php if ( $_hp_is_member ) : ?>
            <div style="display:flex;align-items:center;gap:8px;">
                <?php if ( $_hp_is_primary ) : ?>
                    <span style="font-size:11px;color:#666;background:#f0f0f0;padding:2px 8px;border-radius:12px;">Primary</span>
                <?php endif; ?>
                <span style="color:#27ae60;font-weight:600;font-size:13px;">✓ Joined</span>
            </div>
        <?php elseif ( is_user_logged_in() ) : ?>
            <button onclick="ynjJoinMosqueHP(<?php echo $_hp_mosque_id; ?>)" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;">
                Join This Masjid
            </button>
        <?php else : ?>
            <a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="ynj-btn" style="background:#27ae60;color:#fff;padding:8px 20px;border-radius:24px;font-size:13px;font-weight:700;border:none;cursor:pointer;text-decoration:none;">
                Join This Masjid
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
        <?php if ( $_ynj_is_friday && strpos( $_ynj_next_name, "Jumu'ah" ) !== false ) : ?>
        <p class="ynj-label" id="next-prayer-label" style="color:#fbbf24;">🕌 <?php echo esc_html( 'It\'s Friday! Jumu\'ah at ' . ( $_hp_mosque_name ?: 'your masjid' ) ); ?></p>
        <?php else : ?>
        <p class="ynj-label" id="next-prayer-label"><?php echo $_hp_mosque_name ? esc_html( 'Next Prayer at ' . $_hp_mosque_name ) : esc_html__( 'Next Prayer', 'yourjannah' ); ?></p>
        <?php endif; ?>
        <h2 class="ynj-hero-prayer" id="next-prayer-name"><?php echo esc_html( $_ynj_next_name ?: '—' ); ?></h2>
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
get_footer();
