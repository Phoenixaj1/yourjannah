<?php
/**
 * Template: Patron Membership Page
 *
 * Tier cards (Supporter/Guardian/Champion), checkout, patron wall.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<style>
.ynj-main .ynj-patron-hero{text-align:center;padding:30px 20px 20px;background:none;border-radius:0;margin-bottom:0;}
.ynj-patron-hero h2{font-size:22px;font-weight:800;margin-bottom:6px;}
.ynj-patron-hero p{font-size:14px;color:#6b8fa3;line-height:1.5;}
.ynj-tier{background:rgba(255,255,255,.85);backdrop-filter:blur(8px);border-radius:16px;padding:24px 20px;margin-bottom:12px;border:2px solid transparent;transition:all .2s;cursor:pointer;position:relative;}
.ynj-tier:hover,.ynj-tier.selected{border-color:#00ADEF;box-shadow:0 4px 20px rgba(0,173,239,.15);}
.ynj-tier.selected::after{content:'\2713';position:absolute;top:12px;right:14px;width:24px;height:24px;border-radius:50%;background:#00ADEF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;}
.ynj-tier__badge{display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 10px;border-radius:6px;margin-bottom:10px;}
.ynj-tier__badge--supporter{background:#f0f9ff;color:#0369a1;}
.ynj-tier__badge--guardian{background:#eff6ff;color:#1e40af;}
.ynj-tier__badge--champion{background:#f0fdf4;color:#166534;}
.ynj-tier h3{font-size:18px;font-weight:700;margin-bottom:4px;}
.ynj-tier .ynj-tier__price{font-size:26px;font-weight:800;color:#00ADEF;}
.ynj-tier .ynj-tier__price span{font-size:14px;font-weight:500;color:#6b8fa3;}
.ynj-tier ul{list-style:none;margin:12px 0 0;padding:0;font-size:13px;color:#444;}
.ynj-tier li{padding:4px 0;display:flex;align-items:center;gap:6px;}
.ynj-tier li::before{content:'\2713';color:#16a34a;font-weight:700;font-size:12px;}
.ynj-patron-cta{margin-top:16px;text-align:center;}
.ynj-patron-btn{display:inline-flex;align-items:center;gap:8px;padding:14px 28px;border-radius:12px;background:#00ADEF;color:#fff;font-size:15px;font-weight:700;border:none;cursor:pointer;transition:all .15s;box-shadow:0 4px 16px rgba(0,173,239,.25);}
.ynj-patron-btn:hover{background:#0096d0;box-shadow:0 6px 24px rgba(0,173,239,.35);}
.ynj-patron-btn:disabled{opacity:.6;cursor:not-allowed;}
.ynj-patron-wall{margin-top:20px;}
.ynj-patron-wall h3{font-size:15px;font-weight:700;margin-bottom:10px;}
.ynj-pw-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(0,0,0,.06);}
.ynj-pw-item:last-child{border-bottom:none;}
.ynj-pw-avatar{width:36px;height:36px;border-radius:50%;background:#00ADEF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;}
.ynj-pw-name{font-size:14px;font-weight:600;flex:1;}
.ynj-pw-tier{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b8fa3;}
.ynj-patron-stats{display:flex;gap:12px;justify-content:center;margin-bottom:16px;}
.ynj-patron-stats div{text-align:center;background:rgba(255,255,255,.85);backdrop-filter:blur(8px);border-radius:12px;padding:14px 20px;flex:1;max-width:140px;}
.ynj-patron-stats strong{display:block;font-size:22px;font-weight:800;color:#00ADEF;}
.ynj-patron-stats span{font-size:11px;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
.ynj-login-prompt{background:rgba(255,255,255,.85);border-radius:12px;padding:16px;text-align:center;margin-top:12px;font-size:13px;color:#6b8fa3;}
.ynj-login-prompt a{color:#00ADEF;font-weight:700;text-decoration:none;}
.ynj-status-card{background:rgba(0,173,239,.08);border:1px solid rgba(0,173,239,.2);border-radius:12px;padding:16px;margin-bottom:16px;text-align:center;}
.ynj-status-card h3{font-size:15px;font-weight:700;color:#0369a1;margin-bottom:4px;}
.ynj-status-card p{font-size:13px;color:#6b8fa3;}
.ynj-cancel-link{display:inline-block;margin-top:8px;font-size:12px;color:#dc2626;cursor:pointer;text-decoration:underline;}
</style>

<main class="ynj-main">
    <div class="ynj-patron-hero">
        <div style="font-size:42px;margin-bottom:8px;">&#x1F396;</div>
        <h2 id="patron-title"><?php esc_html_e( 'Become a Patron', 'yourjannah' ); ?></h2>
        <p><?php esc_html_e( 'Support your masjid with a monthly membership. Patrons receive a badge on their profile and help keep the masjid running.', 'yourjannah' ); ?></p>
    </div>

    <!-- Stats -->
    <div class="ynj-patron-stats" id="patron-stats" style="display:none;">
        <div><strong id="stat-patrons">0</strong><span><?php esc_html_e( 'Patrons', 'yourjannah' ); ?></span></div>
        <div><strong id="stat-monthly">&pound;0</strong><span><?php esc_html_e( 'Monthly', 'yourjannah' ); ?></span></div>
    </div>

    <!-- Active patron status (shown if already patron) -->
    <div id="active-status" style="display:none;"></div>

    <!-- Tier cards -->
    <div id="tier-cards">
        <div class="ynj-tier selected" data-tier="supporter" onclick="selectTier('supporter')">
            <span class="ynj-tier__badge ynj-tier__badge--supporter"><?php esc_html_e( 'Bronze', 'yourjannah' ); ?></span>
            <div class="ynj-tier__price">&pound;5 <span><?php esc_html_e( '/month', 'yourjannah' ); ?></span></div>
            <ul>
                <li><?php esc_html_e( 'Patron badge on your profile', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Name on mosque patron wall', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Support masjid running costs', 'yourjannah' ); ?></li>
            </ul>
        </div>
        <div class="ynj-tier" data-tier="guardian" onclick="selectTier('guardian')">
            <span class="ynj-tier__badge ynj-tier__badge--guardian"><?php esc_html_e( 'Silver', 'yourjannah' ); ?></span>
            <div class="ynj-tier__price">&pound;10 <span><?php esc_html_e( '/month', 'yourjannah' ); ?></span></div>
            <ul>
                <li><?php esc_html_e( 'Everything in Bronze', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Silver tier badge', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Priority event booking', 'yourjannah' ); ?></li>
            </ul>
        </div>
        <div class="ynj-tier" data-tier="champion" onclick="selectTier('champion')">
            <span class="ynj-tier__badge ynj-tier__badge--champion"><?php esc_html_e( 'Gold', 'yourjannah' ); ?></span>
            <div class="ynj-tier__price">&pound;20 <span><?php esc_html_e( '/month', 'yourjannah' ); ?></span></div>
            <ul>
                <li><?php esc_html_e( 'Everything in Silver', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Gold tier badge', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Featured on patron wall', 'yourjannah' ); ?></li>
            </ul>
        </div>
        <div class="ynj-tier" data-tier="platinum" onclick="selectTier('platinum')">
            <span class="ynj-tier__badge" style="background:#fef3c7;color:#92400e;"><?php esc_html_e( 'Platinum', 'yourjannah' ); ?></span>
            <div class="ynj-tier__price">&pound;50 <span><?php esc_html_e( '/month', 'yourjannah' ); ?></span></div>
            <ul>
                <li><?php esc_html_e( 'Everything in Gold', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Platinum badge & VIP recognition', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Priority room bookings', 'yourjannah' ); ?></li>
                <li><?php esc_html_e( 'Exclusive masjid updates', 'yourjannah' ); ?></li>
            </ul>
        </div>
    </div>

    <div class="ynj-patron-cta" id="patron-cta">
        <button class="ynj-patron-btn" id="patron-btn" onclick="becomePerson()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
            <?php esc_html_e( 'Become a Patron', 'yourjannah' ); ?> &mdash; &pound;5/mo
        </button>
        <div class="ynj-login-prompt" id="login-prompt" style="display:none;">
            <a href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'Sign in', 'yourjannah' ); ?></a> <?php esc_html_e( 'or', 'yourjannah' ); ?> <a href="<?php echo esc_url( home_url( '/register' ) ); ?>"><?php esc_html_e( 'create an account', 'yourjannah' ); ?></a> <?php esc_html_e( 'to become a patron.', 'yourjannah' ); ?>
        </div>
    </div>

    <!-- Patron wall -->
    <div class="ynj-patron-wall" id="patron-wall" style="display:none;">
        <h3>&#x1F396; <?php esc_html_e( 'Patron Wall', 'yourjannah' ); ?></h3>
        <div id="patron-list"></div>
    </div>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;
    const token = localStorage.getItem('ynj_user_token') || '';
    let selectedTier = 'supporter';
    let mosqueId = 0;

    const tierPrices = { supporter: 5, guardian: 10, champion: 20, platinum: 50 };

    function selectTier(tier) {
        selectedTier = tier;
        document.querySelectorAll('.ynj-tier').forEach(el => {
            el.classList.toggle('selected', el.dataset.tier === tier);
        });
        const btn = document.getElementById('patron-btn');
        if (btn) btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg> <?php echo esc_js( __( 'Become a Patron', 'yourjannah' ) ); ?> \u2014 \u00a3' + tierPrices[tier] + '/mo';
    }
    window.selectTier = selectTier;

    async function becomePerson() {
        if (!token) {
            window.location.href = '<?php echo esc_js( home_url( '/login?redirect=' ) ); ?>' + encodeURIComponent(window.location.pathname);
            return;
        }
        const btn = document.getElementById('patron-btn');
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js( __( 'Redirecting to checkout...', 'yourjannah' ) ); ?>';

        try {
            const res = await fetch(API + 'patrons/checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
                body: JSON.stringify({ mosque_slug: slug, tier: selectedTier })
            });
            const data = await res.json();
            if (data.ok && data.checkout_url) {
                window.location.href = data.checkout_url;
            } else {
                alert(data.error || '<?php echo esc_js( __( 'Something went wrong.', 'yourjannah' ) ); ?>');
                btn.disabled = false;
                selectTier(selectedTier);
            }
        } catch (e) {
            alert('<?php echo esc_js( __( 'Network error. Please try again.', 'yourjannah' ) ); ?>');
            btn.disabled = false;
            selectTier(selectedTier);
        }
    }
    window.becomePerson = becomePerson;

    async function cancelPatron() {
        if (!confirm('<?php echo esc_js( __( 'Cancel your patron membership? You can re-join anytime.', 'yourjannah' ) ); ?>')) return;
        try {
            const res = await fetch(API + 'patrons/cancel', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
                body: JSON.stringify({ mosque_id: mosqueId })
            });
            const data = await res.json();
            if (data.ok) {
                location.reload();
            } else {
                alert(data.error || '<?php echo esc_js( __( 'Could not cancel.', 'yourjannah' ) ); ?>');
            }
        } catch(e) { alert('<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>'); }
    }
    window.cancelPatron = cancelPatron;

    // Get mosque info
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            mosqueId = m.id;
            const title = document.getElementById('patron-title');
            if (title) title.textContent = '<?php echo esc_js( __( 'Become a', 'yourjannah' ) ); ?> ' + (m.name || 'Masjid') + ' <?php echo esc_js( __( 'Patron', 'yourjannah' ) ); ?>';

            // Load patron wall
            return fetch(API + 'mosques/' + m.id + '/patrons');
        })
        .then(r => r.json())
        .then(data => {
            if (data.total_patrons > 0) {
                document.getElementById('patron-stats').style.display = 'flex';
                document.getElementById('stat-patrons').textContent = data.total_patrons;
                document.getElementById('stat-monthly').textContent = '\u00a3' + (data.monthly_pence / 100).toFixed(0);
            }

            if (data.patrons && data.patrons.length) {
                const tierEmoji = { champion: '\ud83c\udfc6', guardian: '\ud83d\udee1\ufe0f', supporter: '\u2b50' };
                document.getElementById('patron-wall').style.display = 'block';
                document.getElementById('patron-list').innerHTML = data.patrons.map(p => {
                    const initial = (p.name || '?')[0].toUpperCase();
                    return '<div class="ynj-pw-item">' +
                        '<div class="ynj-pw-avatar">' + initial + '</div>' +
                        '<div class="ynj-pw-name">' + p.name + ' ' + (tierEmoji[p.tier] || '') + '</div>' +
                        '<div class="ynj-pw-tier">' + p.tier + '</div>' +
                    '</div>';
                }).join('');
            }
        })
        .catch(() => {});

    // Check if user is already a patron
    if (token) {
        fetch(API + 'patrons/me', {
            headers: { 'Authorization': 'Bearer ' + token }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.patrons) return;
            const active = data.patrons.find(p => p.mosque_slug === slug && p.status === 'active');
            if (active) {
                const tierLabel = { supporter: 'Supporter', guardian: 'Guardian', champion: 'Champion' };
                document.getElementById('active-status').style.display = 'block';
                document.getElementById('active-status').innerHTML = '<div class="ynj-status-card"><h3>\ud83c\udfc5 <?php echo esc_js( __( 'You are a', 'yourjannah' ) ); ?> ' + (tierLabel[active.tier] || active.tier) + ' <?php echo esc_js( __( 'Patron', 'yourjannah' ) ); ?></h3><p><?php echo esc_js( __( 'Thank you for supporting this masjid! Your monthly contribution of', 'yourjannah' ) ); ?> \u00a3' + (active.amount_pence/100).toFixed(0) + ' <?php echo esc_js( __( 'makes a difference.', 'yourjannah' ) ); ?></p><span class="ynj-cancel-link" onclick="cancelPatron()"><?php echo esc_js( __( 'Cancel membership', 'yourjannah' ) ); ?></span></div>';
                document.getElementById('tier-cards').style.display = 'none';
                document.getElementById('patron-cta').style.display = 'none';
            }
        })
        .catch(() => {});
    } else {
        // Show login prompt
        document.getElementById('patron-btn').style.display = 'none';
        document.getElementById('login-prompt').style.display = 'block';
    }

    // Check for payment success
    const params = new URLSearchParams(window.location.search);
    if (params.get('payment') === 'success') {
        const hero = document.querySelector('.ynj-patron-hero');
        if (hero) hero.innerHTML = '<div style="font-size:42px;margin-bottom:8px;">\ud83c\udf89</div><h2><?php echo esc_js( __( 'Welcome, Patron!', 'yourjannah' ) ); ?></h2><p><?php echo esc_js( __( 'Your monthly membership is now active. JazakAllahu Khairan for supporting your masjid.', 'yourjannah' ) ); ?></p>';
        document.getElementById('tier-cards').style.display = 'none';
        document.getElementById('patron-cta').style.display = 'none';
    }
})();
</script>
<?php
get_footer();
