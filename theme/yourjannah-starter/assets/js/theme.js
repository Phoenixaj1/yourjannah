/**
 * YourJannah Theme — Main JavaScript
 *
 * Uses ynjData (localized via wp_localize_script) for:
 * - ynjData.restUrl   — REST API base URL
 * - ynjData.nonce     — WP REST nonce
 * - ynjData.siteUrl   — Site home URL
 * - ynjData.vapidKey  — VAPID public key for push
 * - ynjData.isLoggedIn — Whether user is logged in
 */

(function() {
    'use strict';

    // ================================================================
    // GPS DETECTION
    // ================================================================

    // ================================================================
    // GPS — works on ALL pages, not just homepage
    // ================================================================

    var _gpsBtn = document.getElementById('gps-btn');
    var _isHomepage = !!document.getElementById('next-prayer-card');

    if (_gpsBtn && !_isHomepage) {
        // On non-homepage pages, GPS finds nearest mosque and redirects
        _gpsBtn.addEventListener('click', function() {
            if (!('geolocation' in navigator)) return;
            _gpsBtn.classList.add('ynj-gps-btn--loading');
            var nameEl = document.getElementById('mosque-name');
            if (nameEl) nameEl.textContent = 'Locating...';

            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    _gpsBtn.classList.remove('ynj-gps-btn--loading');
                    fetch(ynjData.restUrl + 'mosques/nearest?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude + '&limit=1')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.ok && data.mosques && data.mosques[0]) {
                                var m = data.mosques[0];
                                localStorage.setItem('ynj_mosque_slug', m.slug);
                                localStorage.setItem('ynj_mosque_name', m.name);
                                localStorage.removeItem('ynj_cache_date');
                                // Redirect to this mosque's version of current page, or homepage
                                window.location.href = '/mosque/' + m.slug;
                            }
                        })
                        .catch(function() { if (nameEl) nameEl.textContent = 'Not found'; });
                },
                function() {
                    _gpsBtn.classList.remove('ynj-gps-btn--loading');
                    if (nameEl) nameEl.textContent = localStorage.getItem('ynj_mosque_name') || 'Select mosque';
                },
                { timeout: 8000, maximumAge: 300000 }
            );
        });
    }

    // Mosque pill click — open search dropdown on ALL pages
    var _mosquePill = document.getElementById('mosque-selector');
    var _mosqueDropdown = document.getElementById('mosque-dropdown');
    var _mosqueSearch = document.getElementById('mosque-search');
    var _mosqueList = document.getElementById('mosque-list');

    if (_mosquePill && _mosqueDropdown && !_isHomepage) {
        _mosquePill.addEventListener('click', function(e) {
            if (e.target.closest('#gps-btn')) return;
            _mosqueDropdown.style.display = _mosqueDropdown.style.display === 'none' ? '' : 'none';
            if (_mosqueDropdown.style.display !== 'none' && _mosqueSearch) {
                _mosqueSearch.focus();
                _mosqueSearch.value = '';
                _mosqueList.innerHTML = '<p style="padding:12px;color:#6b8fa3;font-size:13px;text-align:center;">Type to search mosques...</p>';
            }
        });
        _mosquePill.style.cursor = 'pointer';

        // Close on click outside
        _mosqueDropdown.addEventListener('click', function(e) {
            if (e.target === _mosqueDropdown) _mosqueDropdown.style.display = 'none';
        });

        // Search handler
        var _searchTimer = null;
        if (_mosqueSearch) {
            _mosqueSearch.addEventListener('input', function() {
                var q = this.value.trim();
                if (q.length < 2) {
                    _mosqueList.innerHTML = '<p style="padding:12px;color:#6b8fa3;font-size:13px;text-align:center;">Type to search mosques...</p>';
                    return;
                }
                clearTimeout(_searchTimer);
                _searchTimer = setTimeout(function() {
                    _mosqueList.innerHTML = '<p style="padding:12px;color:#6b8fa3;font-size:13px;text-align:center;">Searching...</p>';
                    fetch(ynjData.restUrl + 'mosques/search?q=' + encodeURIComponent(q) + '&limit=10')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var mosques = data.mosques || [];
                            if (!mosques.length) {
                                _mosqueList.innerHTML = '<p style="padding:12px;color:#6b8fa3;font-size:13px;text-align:center;">No mosques found.</p>';
                                return;
                            }
                            _mosqueList.innerHTML = mosques.map(function(m) {
                                return '<button class="ynj-dropdown__item" data-slug="' + m.slug + '" data-name="' + (m.name || '').replace(/"/g, '') + '" style="display:block;width:100%;text-align:left;padding:12px 16px;border:none;background:none;cursor:pointer;font-family:inherit;border-bottom:1px solid #f0f0f0;">' +
                                    '<strong style="font-size:14px;display:block;">' + m.name + '</strong>' +
                                    '<span style="font-size:11px;color:#6b8fa3;">' + [m.city, m.postcode].filter(Boolean).join(', ') + '</span>' +
                                    '</button>';
                            }).join('');

                            // Click handler for results
                            _mosqueList.querySelectorAll('.ynj-dropdown__item').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    var s = this.dataset.slug;
                                    var n = this.dataset.name;
                                    localStorage.setItem('ynj_mosque_slug', s);
                                    localStorage.setItem('ynj_mosque_name', n);
                                    localStorage.removeItem('ynj_cache_date');
                                    localStorage.removeItem('ynj_cached_prayers');
                                    localStorage.removeItem('ynj_cached_feed');
                                    _mosqueDropdown.style.display = 'none';
                                    // Redirect to this mosque
                                    window.location.href = '/mosque/' + s;
                                });
                            });
                        })
                        .catch(function() {
                            _mosqueList.innerHTML = '<p style="padding:12px;color:#dc2626;font-size:13px;text-align:center;">Search failed.</p>';
                        });
                }, 300);
            });
        }
    }

    // ================================================================
    // MOSQUE NAME — set from cache on all pages
    // ================================================================

    var _slug = localStorage.getItem('ynj_mosque_slug') || '';
    var _cachedName2 = localStorage.getItem('ynj_mosque_name') || '';
    if (_slug && _cachedName2) {
        var _mnEl = document.getElementById('mosque-name');
        if (_mnEl && (!_mnEl.textContent || _mnEl.textContent === 'Finding...' || _mnEl.textContent === 'Select Mosque')) {
            _mnEl.textContent = _cachedName2;
        }
        // Wire up nav links
        document.querySelectorAll('[data-nav-mosque]').forEach(function(el) {
            el.href = el.dataset.navMosque.replace('{slug}', _slug);
        });
    }

    // ================================================================
    // GLOBAL: Close modals on Escape key
    // ================================================================

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.ynj-modal').forEach(function(m) {
                if (m.style.display !== 'none') m.style.display = 'none';
            });
        }
    });

    // ================================================================
    // SHARE HELPER (Web Share API with fallback)
    // ================================================================

    window.ynjShare = function(title, text, url) {
        if (navigator.share) {
            navigator.share({ title: title, text: text, url: url }).catch(function(){});
        } else {
            // Fallback: copy link
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    var t = document.createElement('div');
                    t.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);padding:10px 20px;border-radius:10px;background:#166534;color:#fff;font-size:13px;font-weight:600;z-index:9999;';
                    t.textContent = 'Link copied!';
                    document.body.appendChild(t);
                    setTimeout(function(){ t.remove(); }, 2000);
                });
            }
        }
    };

    // WhatsApp share helper
    window.ynjWhatsApp = function(title, url) {
        var mosqueName = localStorage.getItem('ynj_mosque_name') || 'our mosque';
        var text = '\ud83d\udd4c ' + mosqueName + '\n\n' + title + '\n\nView: ' + url;
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
    };

    // ================================================================
    // SERVICE WORKER
    // ================================================================

    if ('serviceWorker' in navigator) {
        // Register SW — try /sw.js first, then REST API fallback
        function regSW(url) {
            return navigator.serviceWorker.register(url, { scope: '/' }).then(function(reg) {
                setInterval(function() { reg.update(); }, 30 * 60 * 1000);
            });
        }
        regSW('/sw.js').catch(function() {
            regSW('/wp-json/ynj/v1/sw').catch(function(err) {
                console.warn('SW registration failed:', err);
            });
        });
    }

    // ================================================================
    // INSTANT MOSQUE NAME — set from localStorage cache on every page
    // Prevents 10-15s delay waiting for API response
    // ================================================================

    var _cachedMosqueName = localStorage.getItem('ynj_mosque_name') || '';
    if (_cachedMosqueName) {
        // Map of element IDs → text content patterns
        var _nameTargets = {
            'patron-bar-text':    'Become a Patron of ' + _cachedMosqueName,
            'feed-heading':       "What's Happening at " + _cachedMosqueName,
            'next-prayer-label':  'Next Prayer at ' + _cachedMosqueName,
            'cta-sponsor-help':   'Funds go to supporting ' + _cachedMosqueName,
            'cta-services-help':  'Proceeds help fund ' + _cachedMosqueName,
            'patron-mosque-name': _cachedMosqueName,
            'mp-name':            _cachedMosqueName,
            'pt-mosque-name':     _cachedMosqueName,
            'dn-mosque-name':     _cachedMosqueName,
        };
        for (var _id in _nameTargets) {
            var _el = document.getElementById(_id);
            if (_el) _el.textContent = _nameTargets[_id];
        }
    }

    // ================================================================
    // MEMBERSHIP BAR — inject via JS to bypass page caching
    // Page caches serve same HTML to all users, so PHP topbar
    // only works for logged-in (cache-bypassed) users. This JS
    // bar works for everyone.
    // ================================================================

    (function() {
        // Don't double-render if PHP topbar already exists
        if (document.querySelector('.ynj-topbar')) return;

        var token = localStorage.getItem('ynj_user_token');
        var user = null;
        try { user = JSON.parse(localStorage.getItem('ynj_user')); } catch(e) {}

        var bar = document.createElement('div');
        bar.className = 'ynj-topbar';

        if (token && user && user.name) {
            // Logged in — check patron status
            var isPatron = user.patron && user.patron.tier;
            if (isPatron) {
                var tierNames = {supporter:'Bronze',guardian:'Silver',champion:'Gold',platinum:'Platinum'};
                bar.className += ' ynj-topbar--patron';
                bar.innerHTML = '<span>\uD83C\uDFC5 ' + user.name.split(' ')[0] + ' \u00B7 <strong>' + (tierNames[user.patron.tier] || user.patron.tier) + ' Patron</strong></span><a href="/profile" style="font-size:11px;color:rgba(255,255,255,.9);text-decoration:none;">My Account</a>';
            } else {
                var mosqueSlug = localStorage.getItem('ynj_mosque_slug') || 'yourniyyah-masjid';
                bar.className += ' ynj-topbar--member';
                bar.innerHTML = '<span>\uD83D\uDC4B Salam, ' + user.name.split(' ')[0] + ' \u00B7 <strong>Free Member</strong></span><div class="ynj-topbar__actions"><a href="/mosque/' + mosqueSlug + '/patron" class="ynj-topbar__cta">Become a Patron \u2192</a><a href="/profile">My Account</a></div>';
            }
        } else {
            // Guest
            bar.className += ' ynj-topbar--guest';
            bar.innerHTML = '<span>\uD83D\uDD4C Welcome to YourJannah</span><div class="ynj-topbar__actions"><a href="/login">Sign In</a><a href="/register" class="ynj-topbar__cta">Join Free</a></div>';
        }

        // Insert before header
        var header = document.querySelector('.ynj-header');
        if (header && header.parentNode) {
            header.parentNode.insertBefore(bar, header);
        }
    })();

})();
