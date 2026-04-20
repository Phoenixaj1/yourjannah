/**
 * Auth Modal — GPS mosque finder, email, PIN sign-in/sign-up.
 * Loaded globally by HUD plugin for guest users.
 *
 * Trigger: ynjAuthModalOpen()
 * Close:   ynjAuthModalClose()
 *
 * @package YNJ_HUD
 */
(function(){
    var obSelectedSlug = '';
    var obSelectedName = '';
    var API = (typeof ynjHudData !== 'undefined' && ynjHudData.apiUrl) ? ynjHudData.apiUrl : '/wp-json/ynj/v1/';

    // ── Open / Close ──
    // opts: { mosque_slug, mosque_name } — prefill mosque, skip GPS
    window.ynjAuthModalOpen = function(opts) {
        var el = document.getElementById('ynj-onboard');
        if (!el) return;
        el.style.display = 'flex';
        // Reset state
        document.getElementById('ob-pin-row').style.display = 'none';
        document.getElementById('ob-newpin-row').style.display = 'none';
        document.getElementById('ob-submit').style.display = 'none';
        document.getElementById('ob-cta-buttons').style.display = '';
        document.getElementById('ob-error').textContent = '';

        if (opts && opts.mosque_slug && opts.mosque_name) {
            // Prefill mosque — skip GPS
            obSelectedSlug = opts.mosque_slug;
            obSelectedName = opts.mosque_name;
            localStorage.setItem('ynj_mosque_slug', opts.mosque_slug);
            localStorage.setItem('ynj_mosque_name', opts.mosque_name);
            var listEl = document.getElementById('ob-mosque-list');
            if (listEl) {
                listEl.innerHTML = '<div style="padding:10px 12px;background:rgba(0,173,239,.2);border-radius:8px;border:2px solid #00ADEF;display:flex;justify-content:space-between;align-items:center;">'
                    + '<div><div style="font-weight:600;font-size:13px;">' + opts.mosque_name + '</div>'
                    + '<div style="font-size:11px;opacity:.5;">Your selected masjid</div></div>'
                    + '<span style="font-size:11px;opacity:.5;">&#x2705;</span></div>';
            }
            // Focus email
            setTimeout(function(){ var e = document.getElementById('ob-email'); if(e) e.focus(); }, 200);
        } else {
            obAutoGps();
        }
    };
    window.ynjAuthModalClose = function() {
        var el = document.getElementById('ynj-onboard');
        if (el) el.style.display = 'none';
    };

    // ── GPS ──
    var obGpsWatchId = null;
    var obGotFix = false;
    window.obAutoGps = function() {
        var listEl = document.getElementById('ob-mosque-list');
        if (!listEl) return;
        obGotFix = false;
        listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">\uD83D\uDCCD Waiting for location...</div>';
        if (!navigator.geolocation) {
            listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">Location not supported. Search below.</div>';
            return;
        }
        obGpsWatchId = navigator.geolocation.watchPosition(
            function(pos) {
                if (obGotFix) return;
                obGotFix = true;
                navigator.geolocation.clearWatch(obGpsWatchId);
                listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">\uD83D\uDCCD Loading nearby mosques...</div>';
                fetch(API + 'mosques/nearest?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude + '&limit=5')
                    .then(function(r){ return r.json(); })
                    .then(function(d){ if (d.ok && d.mosques && d.mosques.length) obRenderMosques(d.mosques); else listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">No mosques found nearby. Search below.</div>'; })
                    .catch(function(){ listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">Could not load mosques. Search below.</div>'; });
            },
            function(err) {
                if (obGotFix) return;
                if (err.code === 1) {
                    obGotFix = true;
                    navigator.geolocation.clearWatch(obGpsWatchId);
                    listEl.innerHTML = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">Location denied. Search your mosque below.</div>';
                    var si = document.getElementById('ob-search-input');
                    if (si) si.focus();
                }
            },
            { enableHighAccuracy: false, maximumAge: 300000 }
        );
    };

    // ── Search ──
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
            html += '<div class="ob-mosque-item" data-slug="' + m.slug + '" onclick="obSelectMosque(\'' + m.slug + '\',\'' + (m.name||'').replace(/'/g,"\\'") + '\')" style="padding:8px 12px;background:' + (isFirst ? 'rgba(0,173,239,.2)' : 'rgba(255,255,255,.08)') + ';border-radius:8px;margin-bottom:4px;cursor:pointer;border:2px solid ' + (isFirst ? '#00ADEF' : 'transparent') + ';transition:all .15s;display:flex;justify-content:space-between;align-items:center;">'
                + '<div><div style="font-weight:600;font-size:13px;">' + (m.name||'') + '</div>'
                + '<div style="font-size:11px;opacity:.5;">' + (m.city||'') + '</div></div>'
                + (dist ? '<span style="font-size:11px;opacity:.5;white-space:nowrap;">' + dist + '</span>' : '')
                + '</div>';
            if (isFirst) {
                obSelectedSlug = m.slug;
                obSelectedName = m.name;
                localStorage.setItem('ynj_mosque_slug', m.slug);
                localStorage.setItem('ynj_mosque_name', m.name);
            }
        });
        if (!mosques.length) html = '<div style="padding:12px;opacity:.5;font-size:13px;text-align:center;">No mosques found. Search below.</div>';
        document.getElementById('ob-mosque-list').innerHTML = html;
    }

    window.obSelectMosque = function(slug, name) {
        obSelectedSlug = slug;
        obSelectedName = name;
        localStorage.setItem('ynj_mosque_slug', slug);
        localStorage.setItem('ynj_mosque_name', name);
        document.querySelectorAll('.ob-mosque-item').forEach(function(el) {
            el.style.borderColor = el.dataset.slug === slug ? '#00ADEF' : 'transparent';
            el.style.background = el.dataset.slug === slug ? 'rgba(0,173,239,.2)' : 'rgba(255,255,255,.08)';
        });
    };

    // ── Auth flow ──
    window.obSubmitEmail = async function() {
        var email = document.getElementById('ob-email').value.trim();
        var pin = document.getElementById('ob-pin').value.trim();
        var newpin = document.getElementById('ob-newpin').value.trim();
        var newpin2 = document.getElementById('ob-newpin2').value.trim();
        var errEl = document.getElementById('ob-error');
        var btn = document.getElementById('ob-submit');
        errEl.textContent = '';

        if (!email || email.indexOf('@') < 1) { errEl.textContent = 'Please enter a valid email.'; return; }

        // Login with PIN
        if (document.getElementById('ob-pin-row').style.display !== 'none' && pin) {
            if (pin.length < 4) { errEl.textContent = 'PIN must be at least 4 digits.'; return; }
            btn.disabled = true; btn.textContent = 'Signing in...';
            try {
                var resp = await fetch(API + 'auth/login', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({email:email, pin:pin}) });
                var data = await resp.json();
                if (data.ok && data.token) {
                    localStorage.setItem('ynj_user_token', data.token);
                    window.location.href = '/?ynj_autologin=' + (data.wp_user_id||'') + '&ynj_token=' + encodeURIComponent(data.token) + '&redirect=' + encodeURIComponent(window.location.pathname);
                } else { errEl.textContent = data.error || 'Incorrect PIN.'; btn.disabled = false; btn.textContent = 'Sign In'; }
            } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Sign In'; }
            return;
        }

        // Register / set PIN
        if (document.getElementById('ob-newpin-row').style.display !== 'none' && newpin) {
            if (newpin.length < 4) { errEl.textContent = 'PIN must be at least 4 digits.'; return; }
            if (!/^\d+$/.test(newpin)) { errEl.textContent = 'PIN must be numbers only.'; return; }
            if (newpin !== newpin2) { errEl.textContent = "PINs don't match."; document.getElementById('ob-newpin2').value = ''; document.getElementById('ob-newpin2').focus(); return; }
            btn.disabled = true;

            var endpoint = window._obSetPinForExisting ? 'auth/set-pin' : 'auth/register';
            btn.textContent = window._obSetPinForExisting ? 'Setting your PIN...' : 'Creating your account...';
            var body = window._obSetPinForExisting ? {email:email, pin:newpin} : {name:email.split('@')[0].replace(/[._]/g,' '), email:email, pin:newpin, mosque_slug:obSelectedSlug};
            try {
                var resp = await fetch(API + endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
                var data = await resp.json();
                if (data.ok && data.token) {
                    localStorage.setItem('ynj_user_token', data.token);
                    window.location.href = '/?ynj_autologin=' + (data.wp_user_id||'') + '&ynj_token=' + encodeURIComponent(data.token) + '&redirect=' + encodeURIComponent(window.location.pathname);
                } else { errEl.textContent = data.error || 'Failed. Try again.'; btn.disabled = false; btn.textContent = window._obSetPinForExisting ? 'Set PIN & Sign In' : 'Create Account'; }
            } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; }
            return;
        }

        // Check email first
        btn.disabled = true; btn.textContent = 'Checking...';
        try {
            var resp = await fetch(API + 'auth/check-email', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({email:email}) });
            var data = await resp.json();
            if (data.exists) {
                if (data.has_pin) {
                    document.getElementById('ob-pin-row').style.display = '';
                    document.getElementById('ob-pin').focus();
                    btn.textContent = 'Sign In';
                } else {
                    document.getElementById('ob-newpin-row').style.display = '';
                    document.getElementById('ob-newpin').focus();
                    btn.textContent = 'Set PIN & Sign In';
                    window._obSetPinForExisting = true;
                }
            } else {
                window._obSetPinForExisting = false;
                document.getElementById('ob-newpin-row').style.display = '';
                document.getElementById('ob-newpin').focus();
                btn.textContent = 'Create Account';
            }
            btn.disabled = false;
        } catch(e) { errEl.textContent = 'Network error.'; btn.disabled = false; }
    };

    window.obStartSignIn = function() {
        var email = document.getElementById('ob-email').value.trim();
        if (!email || email.indexOf('@') < 1) { document.getElementById('ob-error').textContent = 'Please enter your email first.'; document.getElementById('ob-email').focus(); return; }
        document.getElementById('ob-cta-buttons').style.display = 'none';
        document.getElementById('ob-newpin-row').style.display = 'none';
        document.getElementById('ob-pin-row').style.display = '';
        document.getElementById('ob-pin').focus();
        var btn = document.getElementById('ob-submit');
        btn.style.display = ''; btn.textContent = 'Sign In'; btn.onclick = function(){ obSubmitEmail(); };
    };

    window.obStartSignUp = function() {
        var email = document.getElementById('ob-email').value.trim();
        if (!email || email.indexOf('@') < 1) { document.getElementById('ob-error').textContent = 'Please enter your email first.'; document.getElementById('ob-email').focus(); return; }
        document.getElementById('ob-cta-buttons').style.display = 'none';
        document.getElementById('ob-pin-row').style.display = 'none';
        document.getElementById('ob-newpin-row').style.display = '';
        document.getElementById('ob-newpin').focus();
        var btn = document.getElementById('ob-submit');
        btn.style.display = ''; btn.textContent = 'Create Account'; btn.onclick = function(){ obSubmitEmail(); };
        window._obSetPinForExisting = false;
    };

    // Auto-show for first-time visitors (only on homepage)
    if (document.body.classList.contains('home')) {
        var wpLoggedIn = document.cookie.indexOf('wordpress_logged_in_') !== -1;
        var hasToken = !!localStorage.getItem('ynj_user_token');
        var hasSeen = !!localStorage.getItem('ynj_onboard_seen');
        if (!wpLoggedIn && !hasToken && !hasSeen) {
            ynjAuthModalOpen();
            localStorage.setItem('ynj_onboard_seen', '1');
        }
    }
})();
