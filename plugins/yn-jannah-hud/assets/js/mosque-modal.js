/**
 * YourJannah — Mosque search/select modal
 *
 * Extracted from header.php inline <script>.
 * Expects:
 *   window.ynjNearbyMosques – array, set inline by PHP (json_encode of nearby mosques)
 *   ynjData.restUrl         – REST API base URL (already globally available via wp_localize_script)
 */
(function(){
    var modal = document.getElementById('ynj-mosque-modal');
    if (!modal) return;
    var overlay  = modal.querySelector('.ynj-mosque-modal__overlay');
    var closeBtn = modal.querySelector('.ynj-mosque-modal__close');
    var searchIn = document.getElementById('ynj-mosque-search');
    var gpsBtn   = document.getElementById('ynj-mosque-gps');
    var gpsTxt   = document.getElementById('ynj-mosque-gps-text');
    var listEl   = document.getElementById('ynj-mosque-list');
    var searchTimer = null;
    var gpsTriggered = false;
    var restUrl  = (typeof ynjData !== 'undefined' && ynjData.restUrl) ? ynjData.restUrl : '/wp-json/ynj/v1/';

    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (searchIn) searchIn.value = '';
        var pre = window.ynjNearbyMosques || [];
        if (pre.length) { renderList(pre, true); }
        else { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Tap "Use my location" to find nearby mosques</div>'; }
        if (!gpsTriggered && !pre.length) triggerGps();
        setTimeout(function(){ if (searchIn) searchIn.focus(); }, 200);
    }
    function closeModal() {
        modal.style.display = 'none';
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
    function renderList(mosques, label) {
        if (!mosques || !mosques.length) { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found.</div>'; return; }
        var h = label ? '<p class="ynj-mosque-modal__label">\uD83D\uDCCD Nearby</p>' : '';
        mosques.forEach(function(m){
            var meta = [m.city, m.postcode].filter(Boolean).join(', ');
            if (m.distance != null) meta += (meta ? ' \u00B7 ' : '') + m.distance + 'km';
            h += '<button type="button" class="ynj-mosque-modal__item" data-s="'+(m.slug||'')+'" data-n="'+(m.name||'').replace(/"/g,'&quot;')+'">' +
                '<span class="ynj-mosque-modal__item-name">'+(m.name||'')+'</span>' +
                '<span class="ynj-mosque-modal__item-meta">'+meta+'</span></button>';
        });
        listEl.innerHTML = h;
        listEl.querySelectorAll('.ynj-mosque-modal__item').forEach(function(b){
            b.addEventListener('click', function(){ selectMosque(this.dataset.s, this.dataset.n); });
        });
    }
    function triggerGps() {
        if (!('geolocation' in navigator)) { if (gpsTxt) gpsTxt.textContent = 'GPS not available'; return; }
        gpsTriggered = true;
        if (gpsBtn) gpsBtn.classList.add('ynj-mosque-modal__gps--loading');
        if (gpsTxt) gpsTxt.textContent = 'Locating...';
        listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Finding nearby mosques...</div>';
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                if (gpsBtn) gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                if (gpsTxt) gpsTxt.textContent = 'Use my location';
                fetch(restUrl + 'mosques/nearest?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude + '&limit=5')
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.ok && d.mosques && d.mosques.length) {
                            renderList(d.mosques.map(function(m){ return { slug:m.slug, name:m.name, city:m.city||'', postcode:m.postcode||'', distance:m.distance?parseFloat(m.distance).toFixed(1):null }; }), true);
                        } else { listEl.innerHTML = '<div class="ynj-mosque-modal__empty">No mosques found nearby. Try searching.</div>'; }
                    })
                    .catch(function(){ listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Could not load mosques. Try searching.</div>'; });
            },
            function() {
                if (gpsBtn) gpsBtn.classList.remove('ynj-mosque-modal__gps--loading');
                if (gpsTxt) gpsTxt.textContent = 'Location denied';
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Location denied. Search by name instead.</div>';
            },
            { timeout: 8000, maximumAge: 300000 }
        );
    }

    /* Global function — called by onclick on pill button */
    window.ynjOpenMosqueModal = openModal;

    /* Close handlers */
    if (overlay) overlay.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (gpsBtn) gpsBtn.addEventListener('click', triggerGps);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.style.display !== 'none') closeModal(); });

    /* Search */
    if (searchIn) {
        searchIn.addEventListener('input', function(){
            var q = this.value.trim();
            if (q.length < 2) { var pre = window.ynjNearbyMosques || []; if (pre.length) renderList(pre, true); else listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Type to search...</div>'; return; }
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function(){
                listEl.innerHTML = '<div class="ynj-mosque-modal__empty">Searching...</div>';
                fetch(restUrl + 'mosques/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r){ return r.json(); })
                    .then(function(d){ renderList((d.mosques||[]).map(function(m){ return { slug:m.slug, name:m.name, city:m.city||'', postcode:m.postcode||'', distance:m.distance?parseFloat(m.distance).toFixed(1):null }; }), false); })
                    .catch(function(){ listEl.innerHTML = '<div class="ynj-mosque-modal__empty" style="color:#dc2626;">Search failed.</div>'; });
            }, 300);
        });
    }
})();
