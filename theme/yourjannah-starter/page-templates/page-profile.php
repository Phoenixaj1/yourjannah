<?php
/**
 * Template: User Profile Page
 *
 * @package YourJannah
 */

get_header();
?>
<main class="ynj-main" id="profile-main">
    <p class="ynj-text-muted" style="text-align:center;padding:40px 0;" id="profile-loading"><?php esc_html_e( 'Loading...', 'yourjannah' ); ?></p>
</main>

<script>
(function(){
    const API = ynjData.restUrl;
    const token = localStorage.getItem('ynj_user_token');

    if (!token) {
        document.getElementById('profile-main').innerHTML = '<section class="ynj-card" style="text-align:center;padding:40px 20px;"><div style="font-size:48px;margin-bottom:12px;">🔒</div><h2><?php echo esc_js( __( 'Not Signed In', 'yourjannah' ) ); ?></h2><p class="ynj-text-muted" style="margin-bottom:16px;"><?php echo esc_js( __( 'Sign in to see your profile, bookings, and prayer preferences.', 'yourjannah' ) ); ?></p><a href="<?php echo esc_js( home_url( '/login' ) ); ?>" class="ynj-btn" style="justify-content:center;"><?php echo esc_js( __( 'Sign In', 'yourjannah' ) ); ?></a><p style="margin-top:12px;font-size:13px;"><?php echo esc_js( __( "Don't have an account?", 'yourjannah' ) ); ?> <a href="<?php echo esc_js( home_url( '/register' ) ); ?>" style="font-weight:700;"><?php echo esc_js( __( 'Create one', 'yourjannah' ) ); ?></a></p></section>';
        return;
    }

    const headers = {'Content-Type':'application/json','Authorization':'Bearer '+token, 'X-WP-Nonce': ynjData.nonce};

    async function load() {
        const main = document.getElementById('profile-main');

        try {
        // Fetch profile
        const profileResp = await fetch(API + 'auth/me', {headers}).then(r=>r.json()).catch(()=>({ok:false}));
        if (!profileResp.ok) { localStorage.removeItem('ynj_user_token'); window.location.href = '<?php echo esc_js( home_url( '/login' ) ); ?>'; return; }
        const user = profileResp.user;

        // Fetch bookings + patron status
        const [bookingsResp, patronResp] = await Promise.all([
            fetch(API + 'auth/bookings', {headers}).then(r=>r.json()).catch(()=>({bookings:[]})),
            fetch(API + 'patrons/me', {headers}).then(r=>r.json()).catch(()=>({patrons:[]}))
        ]);
        const bookings = bookingsResp.bookings || [];
        const patrons = (patronResp.patrons || []).filter(p => p.status === 'active');

        const tierBadge = {supporter:'🥉 Bronze', guardian:'🥈 Silver', champion:'🥇 Gold', platinum:'💎 Platinum'};
        const tierColor = {supporter:'#f0f9ff', guardian:'#f0f0ff', champion:'#fef3c7', platinum:'#ede9fe'};
        const tierFg    = {supporter:'#0369a1', guardian:'#4338ca', champion:'#92400e', platinum:'#6b21a8'};

        const patronSection = patrons.length ? `
            <section class="ynj-card" style="background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">🏅 <?php echo esc_js( __( 'Patron Memberships', 'yourjannah' ) ); ?></h3>
                ${patrons.map(p => `
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:10px;background:rgba(255,255,255,.1);margin-bottom:8px;">
                        <div>
                            <strong style="font-size:14px;display:block;">${p.mosque_name || 'Mosque'}</strong>
                            <span style="font-size:11px;opacity:.7;">Since ${p.started_at ? new Date(p.started_at).toLocaleDateString('en-GB',{month:'short',year:'numeric'}) : 'recently'}</span>
                        </div>
                        <div style="text-align:right;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;background:${tierColor[p.tier]||'#f0f9ff'};color:${tierFg[p.tier]||'#0369a1'};">${tierBadge[p.tier] || p.tier}</span>
                            <div style="font-size:12px;font-weight:700;margin-top:2px;">£${(p.amount_pence/100).toFixed(0)}/mo</div>
                        </div>
                    </div>
                `).join('')}
            </section>` : '';

        main.innerHTML = `
            <section class="ynj-card">
                <div style="text-align:center;margin-bottom:16px;">
                    <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#00ADEF,#0090d0);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;margin:0 auto 8px;">${user.name.charAt(0).toUpperCase()}</div>
                    <h2 style="font-size:18px;font-weight:700;">${user.name}</h2>
                    <p class="ynj-text-muted">${user.email}</p>
                    ${patrons.length ? '<div style="margin-top:8px;"><span style="display:inline-block;padding:4px 12px;border-radius:8px;font-size:12px;font-weight:700;background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;">🏅 Patron</span></div>' : ''}
                </div>
            </section>

            ${patronSection}

            <section class="ynj-card">
                <h3 class="ynj-card__title"><?php echo esc_js( __( 'Prayer Preferences', 'yourjannah' ) ); ?></h3>
                <form id="pref-form" class="ynj-form">
                    <div class="ynj-field-row">
                        <div class="ynj-field">
                            <label><?php echo esc_js( __( 'Travel Mode', 'yourjannah' ) ); ?></label>
                            <select name="travel_mode">
                                <option value="walk" ${user.travel_mode==='walk'?'selected':''}><?php echo esc_js( __( 'Walking', 'yourjannah' ) ); ?></option>
                                <option value="drive" ${user.travel_mode==='drive'?'selected':''}><?php echo esc_js( __( 'Driving', 'yourjannah' ) ); ?></option>
                            </select>
                        </div>
                        <div class="ynj-field">
                            <label><?php echo esc_js( __( 'Travel Time (min)', 'yourjannah' ) ); ?></label>
                            <input type="number" name="travel_minutes" value="${user.travel_minutes||''}" placeholder="e.g. 15" min="0" max="120">
                        </div>
                    </div>
                    <div class="ynj-field">
                        <label><?php echo esc_js( __( 'Alert Before Prayer (minutes)', 'yourjannah' ) ); ?></label>
                        <select name="alert_before_minutes">
                            <option value="10" ${user.alert_before_minutes===10?'selected':''}><?php echo esc_js( __( '10 minutes', 'yourjannah' ) ); ?></option>
                            <option value="15" ${user.alert_before_minutes===15?'selected':''}><?php echo esc_js( __( '15 minutes', 'yourjannah' ) ); ?></option>
                            <option value="20" ${user.alert_before_minutes===20?'selected':''}><?php echo esc_js( __( '20 minutes (default)', 'yourjannah' ) ); ?></option>
                            <option value="30" ${user.alert_before_minutes===30?'selected':''}><?php echo esc_js( __( '30 minutes', 'yourjannah' ) ); ?></option>
                            <option value="45" ${user.alert_before_minutes===45?'selected':''}><?php echo esc_js( __( '45 minutes', 'yourjannah' ) ); ?></option>
                        </select>
                    </div>
                </form>
                <button class="ynj-btn ynj-btn--outline" id="save-prefs" type="button" style="width:100%;justify-content:center;"><?php echo esc_js( __( 'Save Preferences', 'yourjannah' ) ); ?></button>
            </section>

            <section class="ynj-card" id="subs-section">
                <h3 class="ynj-card__title"><?php echo esc_js( __( 'My Mosque Subscriptions', 'yourjannah' ) ); ?></h3>
                <div id="subs-list"><p class="ynj-text-muted"><?php echo esc_js( __( 'Loading...', 'yourjannah' ) ); ?></p></div>
            </section>

            <section class="ynj-card">
                <h3 class="ynj-card__title"><?php echo esc_js( __( 'My Bookings', 'yourjannah' ) ); ?> (${bookings.length})</h3>
                <div class="ynj-feed" id="bookings-list">
                    ${bookings.length ? bookings.map(b => {
                        const badge = b.status==='confirmed'?'green':(b.status==='pending'||b.status==='pending_payment'?'yellow':'red');
                        const title = b.type==='event' ? (b.event_title||'Event') : (b.room_name||'Room');
                        const time = b.start_time ? b.start_time.substring(0,5) : '';
                        return \`<div class="ynj-feed-item">
                            <div class="ynj-feed-item__head">
                                <span class="ynj-badge ynj-badge--\${b.type==='event'?'event':''}">\${b.type==='event'?'Event':'Room'}</span>
                                <h4>\${title}</h4>
                            </div>
                            <span class="ynj-feed-meta">\${b.booking_date||''} · \${time}\${b.mosque_name ? ' · '+b.mosque_name : ''}</span>
                            <span class="ynj-badge" style="margin-top:4px;background:\${badge==='green'?'#dcfce7':badge==='yellow'?'#fef3c7':'#fee2e2'};color:\${badge==='green'?'#166534':badge==='yellow'?'#92400e':'#991b1b'}">\${b.status}</span>
                        </div>\`;
                    }).join('') : '<p class="ynj-text-muted"><?php echo esc_js( __( 'No bookings yet. Browse events and rooms to get started.', 'yourjannah' ) ); ?></p>'}
                </div>
            </section>

            <div style="text-align:center;padding:16px 0;">
                <button class="ynj-btn ynj-btn--outline" onclick="localStorage.removeItem('ynj_user_token');localStorage.removeItem('ynj_user');localStorage.removeItem('ynj_cache_date');window.location.href='<?php echo esc_js( wp_logout_url( home_url( '/' ) ) ); ?>';" style="color:#dc2626;border-color:#dc2626;"><?php echo esc_js( __( 'Logout', 'yourjannah' ) ); ?></button>
            </div>
        `;

        // Save preferences handler
        document.getElementById('save-prefs').addEventListener('click', async function() {
            const btn = this; const form = document.getElementById('pref-form');
            btn.disabled = true; btn.textContent = '<?php echo esc_js( __( 'Saving...', 'yourjannah' ) ); ?>';
            const resp = await fetch(API + 'auth/me', {
                method: 'PUT', headers,
                body: JSON.stringify({
                    travel_mode: form.querySelector('[name="travel_mode"]').value,
                    travel_minutes: parseInt(form.querySelector('[name="travel_minutes"]').value) || 0,
                    alert_before_minutes: parseInt(form.querySelector('[name="alert_before_minutes"]').value) || 20,
                })
            }).then(r=>r.json());
            btn.disabled = false; btn.textContent = '<?php echo esc_js( __( 'Save Preferences', 'yourjannah' ) ); ?>';
            if (resp.ok) { btn.textContent = '<?php echo esc_js( __( 'Saved', 'yourjannah' ) ); ?> ✓'; setTimeout(()=>{ btn.textContent = '<?php echo esc_js( __( 'Save Preferences', 'yourjannah' ) ); ?>'; }, 2000); }
        });

        // Load subscriptions
        loadSubscriptions();
    }

    async function loadSubscriptions() {
        const resp = await fetch(API + 'auth/subscriptions', {headers}).then(r=>r.json()).catch(()=>({subscriptions:[]}));
        const subs = resp.subscriptions || [];
        const el = document.getElementById('subs-list');
        if (!subs.length) {
            el.innerHTML = '<p class="ynj-text-muted"><?php echo esc_js( __( 'Not subscribed to any mosques yet. Visit a mosque page and tap Subscribe.', 'yourjannah' ) ); ?></p>';
            return;
        }
        el.innerHTML = subs.map(s => {
            const prefToggles = [
                {key:'notify_events', label:'<?php echo esc_js( __( 'Events', 'yourjannah' ) ); ?>', icon:'📅', val:s.notify_events},
                {key:'notify_classes', label:'<?php echo esc_js( __( 'Classes', 'yourjannah' ) ); ?>', icon:'🎓', val:s.notify_classes},
                {key:'notify_announcements', label:'<?php echo esc_js( __( 'Updates', 'yourjannah' ) ); ?>', icon:'📢', val:s.notify_announcements},
                {key:'notify_live', label:'<?php echo esc_js( __( 'Live', 'yourjannah' ) ); ?>', icon:'🔴', val:s.notify_live},
                {key:'notify_fundraising', label:'<?php echo esc_js( __( 'Fundraise', 'yourjannah' ) ); ?>', icon:'❤️', val:s.notify_fundraising},
            ];
            const toggles = prefToggles.map(p =>
                '<label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">' +
                '<input type="checkbox" data-mosque="'+s.mosque_id+'" data-pref="'+p.key+'" '+(p.val?'checked':'')+' onchange="updateSubPref(this)" style="width:14px;height:14px;accent-color:#00ADEF;">' +
                p.icon + ' ' + p.label + '</label>'
            ).join('');
            return '<div style="padding:12px 0;border-bottom:1px solid #f0f0f0;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
                '<div><strong style="font-size:14px;">' + s.mosque_name + '</strong>' +
                (s.mosque_city ? '<br><span class="ynj-text-muted" style="font-size:12px;">' + s.mosque_city + '</span>' : '') + '</div>' +
                '<button onclick="unsubMosque('+s.mosque_id+',this)" style="font-size:11px;color:#dc2626;background:none;border:1px solid #fecaca;padding:4px 10px;border-radius:6px;cursor:pointer;"><?php echo esc_js( __( 'Unsubscribe', 'yourjannah' ) ); ?></button>' +
                '</div>' +
                '<div style="display:flex;gap:12px;flex-wrap:wrap;">' + toggles + '</div>' +
                '</div>';
        }).join('');
    }

    window.updateSubPref = async function(el) {
        const mosqueId = el.dataset.mosque;
        const pref = el.dataset.pref;
        const body = {}; body[pref] = el.checked ? 1 : 0;
        await fetch(API + 'auth/subscriptions/' + mosqueId, {
            method: 'PUT', headers, body: JSON.stringify(body)
        });
    };

    window.unsubMosque = async function(mosqueId, btn) {
        if (!confirm('<?php echo esc_js( __( 'Unsubscribe from this mosque?', 'yourjannah' ) ); ?>')) return;
        btn.disabled = true; btn.textContent = '...';
        await fetch(API + 'auth/subscriptions/' + mosqueId, {method:'DELETE', headers});
        loadSubscriptions();
    };

    load().catch(function(err) {
        console.error('Profile load error:', err);
        document.getElementById('profile-main').innerHTML = '<section class="ynj-card" style="text-align:center;padding:40px 20px;"><div style="font-size:48px;margin-bottom:12px;">😕</div><h2>Could Not Load Profile</h2><p class="ynj-text-muted">Please try again or <a href="<?php echo esc_js( home_url( '/login' ) ); ?>">sign in again</a>.</p></section>';
    });
})();
</script>
<?php
get_footer();
