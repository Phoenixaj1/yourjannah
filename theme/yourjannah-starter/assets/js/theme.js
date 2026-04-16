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
    // MOSQUE NAME — set from cache on all pages
    // ================================================================

    var _slug = localStorage.getItem('ynj_mosque_slug') || '';
    var _cachedName2 = localStorage.getItem('ynj_mosque_name') || '';
    if (_slug && _cachedName2) {
        var _mnEl = document.getElementById('mosque-name');
        if (_mnEl && (!_mnEl.textContent || _mnEl.textContent === 'Finding...' || _mnEl.textContent === 'Select Mosque')) {
            _mnEl.textContent = _cachedName2;
        }
        document.querySelectorAll('[data-nav-mosque]').forEach(function(el) {
            el.href = el.dataset.navMosque.replace('{slug}', _slug);
        });
    }

    // ================================================================
    // MOSQUE SELECTOR MODAL — works on ALL pages
    // ================================================================

    (function() {
        var modal = document.getElementById('ynj-mosque-modal');
        var pill  = document.getElementById('mosque-selector');
        if (!modal || !pill) return;

        var overlay   = modal.querySelector('.ynj-mosque-modal__overlay');
        var closeBtn  = modal.querySelector('.ynj-mosque-modal__close');
        var searchIn  = document.getElementById('ynj-mosque-search');
        var gpsBtn    = document.getElementById('ynj-mosque-gps');
        var gpsTxt    = document.getElementById('ynj-mosque-gps-text');
        var listEl    = document.getElementById('ynj-mosque-list');
        var searchTimer = null;
        var gpsTriggered = false;

        function openModal() {
            modal.style.display = 'flex';
            modal.classList.add('ynj-mosque-modal--open');
            document.body.style.overflow = 'hidden';
            if (searchIn) searchIn.value = '';

            // Show PHP pre-loaded nearby mosques if available
            var preloaded = window.ynjNearbyMosques || [];
            if (preloaded.length) {
                renderList(preloaded, true);
            } else {
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Tap "Use my location" to find nearby mosques</div>';
            }

            // Auto-trigger GPS on first open if no preloaded mosques
            if (!gpsTriggered && !preloaded.length) {
                triggerGps();
            }

            setTimeout(function() { if (searchIn) searchIn.focus(); }, 200);
        }

        function closeModal() {
            modal.style.display = 'none';
            modal.classList.remove('ynj-mosque-modal--open');
            document.body.style.overflow = '';
        }

        function selectMosque(slug, name) {
            localStorage.setItem('ynj_mosque_slug', slug);
            localStorage.setItem('ynj_mosque_name', name);
            localStorage.removeItem('ynj_cache_date');
            localStorage.removeItem('ynj_cached_prayers');
            localStorage.removeItem('ynj_cached_feed');
            window.location.href = '/mosque/' + slug;
        }

        function renderList(mosques, showLabel) {
            if (!mosques || !mosques.length) {
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found.</div>';
                return;
            }
            var html = '';
            if (showLabel) {
                html += '<p class="ynj-mosque-modal__label">\uD83D\uDCCD Nearby</p>';
            }
            mosques.forEach(function(m) {
                var meta = [m.city, m.postcode].filter(Boolean).join(', ');
                if (m.distance !== null && m.distance !== undefined) {
                    meta += (meta ? ' \u00B7 ' : '') + m.distance + 'km';
                }
                html += '<button type="button" class="ynj-mosque-modal__item" data-slug="' + (m.slug || '') + '" data-name="' + (m.name || '').replace(/"/g, '&quot;') + '">' +
                    '<span class="ynj-mosque-modal__item-name">' + (m.name || '') + '</span>' +
                    '<span class="ynj-mosque-modal__item-meta">' + meta + '</span>' +
                    '</button>';
            });
            listEl.innerHTML = html;

            // Attach click handlers via delegation
            listEl.querySelectorAll('.ynj-mosque-modal__item').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    selectMosque(this.dataset.slug, this.dataset.name);
                });
            });
        }

        function triggerGps() {
            if (!('geolocation' in navigator)) {
                gpsTxt.textContent = 'GPS not available';
                return;
            }
            gpsTriggered = true;
            gpsBtn.classList.add('ynj-mosque-modal__gps--loading');
            gpsTxt.textContent = 'Locating...';
            listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Finding nearby mosques...</div>';

            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                    gpsTxt.textContent = 'Use my location';
                    fetch(ynjData.restUrl + 'mosques/nearest?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude + '&limit=5')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.ok && data.mosques && data.mosques.length) {
                                var formatted = data.mosques.map(function(m) {
                                    return {
                                        slug: m.slug,
                                        name: m.name,
                                        city: m.city || '',
                                        postcode: m.postcode || '',
                                        distance: m.distance ? parseFloat(m.distance).toFixed(1) : null
                                    };
                                });
                                renderList(formatted, true);
                            } else {
                                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found nearby. Try searching by name.</div>';
                            }
                        })
                        .catch(function() {
                            listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Could not load mosques. Try searching.</div>';
                        });
                },
                function() {
                    gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                    gpsTxt.textContent = 'Location denied — search below';
                    listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Location access denied. Search by name instead.</div>';
                },
                { timeout: 8000, maximumAge: 300000 }
            );
        }

        // Open modal — pill click (but not GPS button click)
        pill.addEventListener('click', function(e) {
            // If GPS button was clicked inside the pill, trigger GPS + open modal
            var gpsInPill = e.target.closest('#gps-btn');
            if (gpsInPill) {
                e.stopPropagation();
                openModal();
                triggerGps();
                return;
            }
            openModal();
        });

        // Close modal
        if (overlay) overlay.addEventListener('click', closeModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        // GPS button inside modal
        if (gpsBtn) gpsBtn.addEventListener('click', triggerGps);

        // Search — debounced
        if (searchIn) {
            searchIn.addEventListener('input', function() {
                var q = this.value.trim();
                if (q.length < 2) {
                    // Restore preloaded or empty state
                    var preloaded = window.ynjNearbyMosques || [];
                    if (preloaded.length) {
                        renderList(preloaded, true);
                    } else {
                        listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Type to search mosques...</div>';
                    }
                    return;
                }
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Searching...</div>';
                    fetch(ynjData.restUrl + 'mosques/search?q=' + encodeURIComponent(q) + '&limit=10')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var mosques = (data.mosques || []).map(function(m) {
                                return {
                                    slug: m.slug,
                                    name: m.name,
                                    city: m.city || '',
                                    postcode: m.postcode || '',
                                    distance: m.distance ? parseFloat(m.distance).toFixed(1) : null
                                };
                            });
                            renderList(mosques, false);
                        })
                        .catch(function() {
                            listEl.innerHTML = '<div class="ynj-mosque-modal__empty" style="color:#dc2626;">Search failed. Try again.</div>';
                        });
                }, 300);
            });
        }
    })();

    // ================================================================
    // GLOBAL: Close modals on Escape key
    // ================================================================

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var mosqueModal = document.getElementById('ynj-mosque-modal');
            if (mosqueModal && mosqueModal.classList.contains('ynj-mosque-modal--open')) {
                mosqueModal.classList.remove('ynj-mosque-modal--open');
                document.body.style.overflow = '';
            }
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
