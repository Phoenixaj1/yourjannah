<?php
/**
 * Template: Mosque Profile Page
 *
 * Prayer times, announcements, events, subscribe button.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();

// --- Server-side pre-load: mosque, announcements, events ---
$mosque = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$mosque_name = $mosque ? $mosque->name : '';
$mosque_address = $mosque ? ( $mosque->address ?? '' ) : '';
$mosque_status = $mosque ? ( $mosque->status ?? '' ) : '';

// Pre-load announcements
$announcements = [];
if ( $mosque_id ) {
    global $wpdb;
    $ann_table = YNJ_DB::table( 'announcements' );
    $announcements = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $ann_table WHERE mosque_id = %d AND status = 'published' ORDER BY pinned DESC, published_at DESC LIMIT 20", $mosque_id
    ) );
}

// Pre-load events
$events = [];
if ( $mosque_id ) {
    global $wpdb;
    $evt_table = YNJ_DB::table( 'events' );
    $events = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $evt_table WHERE mosque_id = %d AND status = 'published' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20", $mosque_id
    ) );
}

// Encode for JS consumption
$mosque_json = wp_json_encode( $mosque ? $mosque : null );
$announcements_json = wp_json_encode( $announcements );
$events_json = wp_json_encode( $events );
?>
<main class="ynj-main" id="mosque-profile">
    <section class="ynj-card ynj-card--hero">
        <h1 class="ynj-mosque-name" id="mp-name"><?php echo $mosque ? esc_html( $mosque_name ) : esc_html__( 'Mosque not found', 'yourjannah' ); ?></h1>
        <p class="ynj-text-muted" id="mp-address" style="color:rgba(255,255,255,.7);"><?php echo esc_html( $mosque_address ); ?></p>
        <div id="mp-subscribe" style="margin-top:12px;display:none;">
            <button id="mp-sub-btn" class="ynj-btn" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;justify-content:center;width:100%;" onclick="toggleSubscribe()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <span id="mp-sub-text"><?php esc_html_e( 'Subscribe for Updates', 'yourjannah' ); ?></span>
            </button>
            <p id="mp-sub-count" class="ynj-text-muted" style="text-align:center;font-size:11px;margin-top:4px;color:rgba(255,255,255,.6);"></p>
        </div>
    </section>

    <section class="ynj-card" id="mp-prayer-card">
        <h3 class="ynj-card__title"><?php esc_html_e( 'Prayer Times', 'yourjannah' ); ?></h3>
        <div class="ynj-prayer-grid" id="mp-prayer-grid"></div>
    </section>

    <section class="ynj-card" id="mp-announcements">
        <h3 class="ynj-card__title"><?php esc_html_e( 'Announcements', 'yourjannah' ); ?></h3>
        <div id="mp-feed" class="ynj-feed"><p class="ynj-text-muted"><?php esc_html_e( 'No announcements yet.', 'yourjannah' ); ?></p></div>
    </section>

    <section class="ynj-card" id="mp-events">
        <h3 class="ynj-card__title"><?php esc_html_e( 'Upcoming Events', 'yourjannah' ); ?></h3>
        <div id="mp-events-list" class="ynj-feed"><p class="ynj-text-muted"><?php esc_html_e( 'No upcoming events.', 'yourjannah' ); ?></p></div>
    </section>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;
    function esc(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

    const token = localStorage.getItem('ynj_user_token') || '';
    let isSubscribed = false;
    let mosqueId = 0;

    // Check subscription status
    function checkSub() {
        if (!token) {
            document.getElementById('mp-subscribe').style.display = '';
            document.getElementById('mp-sub-btn').onclick = function() {
                window.location.href = '<?php echo esc_js( home_url( '/login?redirect=' ) ); ?>' + encodeURIComponent(window.location.pathname);
            };
            return;
        }
        document.getElementById('mp-subscribe').style.display = '';
        fetch(API + 'auth/subscriptions', {
            headers: {'Authorization': 'Bearer ' + token}
        }).then(r=>r.json()).then(data => {
            const subs = data.subscriptions || [];
            isSubscribed = subs.some(s => s.mosque_slug === slug);
            updateSubBtn();
        }).catch(() => {});
    }

    function updateSubBtn() {
        const btn = document.getElementById('mp-sub-btn');
        const text = document.getElementById('mp-sub-text');
        if (isSubscribed) {
            btn.style.background = 'rgba(255,255,255,.9)';
            btn.style.color = '#00ADEF';
            text.textContent = '<?php echo esc_js( __( 'Subscribed', 'yourjannah' ) ); ?>';
        } else {
            btn.style.background = 'rgba(255,255,255,.2)';
            btn.style.color = '#fff';
            text.textContent = '<?php echo esc_js( __( 'Subscribe for Updates', 'yourjannah' ) ); ?>';
        }
    }

    window.toggleSubscribe = async function() {
        if (!token) {
            window.location.href = '<?php echo esc_js( home_url( '/login?redirect=' ) ); ?>' + encodeURIComponent(window.location.pathname);
            return;
        }
        const btn = document.getElementById('mp-sub-btn');
        btn.disabled = true;
        if (isSubscribed) {
            await fetch(API + 'auth/subscriptions/' + mosqueId, {
                method: 'DELETE', headers: {'Content-Type':'application/json','Authorization':'Bearer '+token}
            });
            isSubscribed = false;
        } else {
            await fetch(API + 'auth/subscriptions', {
                method: 'POST', headers: {'Content-Type':'application/json','Authorization':'Bearer '+token},
                body: JSON.stringify({mosque_slug: slug})
            });
            isSubscribed = true;
        }
        btn.disabled = false;
        updateSubBtn();
    };

    // Subscriber count
    function loadSubCount() {
        if (!mosqueId) return;
        fetch(API + 'mosques/' + mosqueId + '/subscriber-count')
            .then(r=>r.json()).then(data => {
                if (data.total > 0) {
                    document.getElementById('mp-sub-count').textContent = data.total + ' subscriber' + (data.total !== 1 ? 's' : '');
                }
            }).catch(() => {});
    }

    // --- Pre-loaded data from PHP (no API fetch needed) ---
    const mosqueData = <?php echo $mosque_json; ?>;
    const preAnnouncements = <?php echo $announcements_json; ?>;
    const preEvents = <?php echo $events_json; ?>;

    if (mosqueData) {
        mosqueId = mosqueData.id;

        // Track page view (fire-and-forget, fine as async)
        if (mosqueData.id) {
            fetch(API + 'mosques/' + mosqueData.id + '/view', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({source: 'page'})
            }).catch(function(){});
        }

        // Unclaimed mosque — show claim + patron CTA
        if (mosqueData.status === 'unclaimed') {
            var claimHtml = '<div style="margin-top:16px;">' +
                '<a href="/mosque/' + slug + '/patron" class="ynj-btn" style="background:#00ADEF;color:#fff;justify-content:center;width:100%;margin-bottom:8px;">🏅 <?php echo esc_js( __( 'Become a Patron', 'yourjannah' ) ); ?></a>' +
                '<p style="text-align:center;font-size:12px;color:rgba(255,255,255,.7);margin-bottom:12px;"><?php echo esc_js( __( 'Support this mosque monthly through YourJannah', 'yourjannah' ) ); ?></p>' +
                '<a href="<?php echo esc_js( home_url( '/register?claim=' ) ); ?>' + slug + '" style="display:block;text-align:center;font-size:12px;color:#fbbf24;font-weight:700;text-decoration:underline;"><?php echo esc_js( __( 'Are you the mosque admin? Claim this page', 'yourjannah' ) ); ?></a>' +
                '</div>';
            document.getElementById('mp-subscribe').insertAdjacentHTML('afterend', claimHtml);
            document.getElementById('mp-announcements').style.display = 'none';
            document.getElementById('mp-events').style.display = 'none';
        }

        checkSub();
        loadSubCount();

        if (mosqueData.prayer_times && !mosqueData.prayer_times.error) {
            const grid = document.getElementById('mp-prayer-grid');
            grid.innerHTML = '';
            ['fajr','sunrise','dhuhr','asr','maghrib','isha'].forEach(p => {
                if (!mosqueData.prayer_times[p]) return;
                const t = String(mosqueData.prayer_times[p]).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'');
                grid.innerHTML += '<div class="ynj-prayer-row"><span class="ynj-prayer-row__name">' + p.charAt(0).toUpperCase()+p.slice(1) + '</span><span class="ynj-prayer-row__time">' + t + '</span></div>';
            });
        }
    }

    // Render pre-loaded announcements
    if (preAnnouncements && preAnnouncements.length) {
        document.getElementById('mp-feed').innerHTML = preAnnouncements.map(a =>
            '<div class="ynj-feed-item"><h4>' + esc(a.title) + '</h4><p>' + esc(a.body) + '</p><time>' + esc(a.published_at||'') + '</time></div>'
        ).join('');
    }

    // Render pre-loaded events
    if (preEvents && preEvents.length) {
        document.getElementById('mp-events-list').innerHTML = preEvents.map(e => {
            const t = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
            return '<div class="ynj-feed-item"><h4>' + esc(e.title) + '</h4><p>' + esc(e.event_date||'') + ' &middot; ' + t + '</p></div>';
        }).join('');
    }
})();
</script>
<?php
get_footer();
