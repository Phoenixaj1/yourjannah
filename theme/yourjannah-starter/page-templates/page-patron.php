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

// Pre-load mosque data server-side — instant mosque name, no API call
$mosque    = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$mosque_name   = $mosque ? $mosque->name : __( 'Masjid', 'yourjannah' );
$mosque_status = $mosque ? $mosque->status : '';
?>
<style>
.ynj-main .ynj-patron-hero{text-align:center;padding:32px 20px 20px;background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 50%,#0e4d3c 100%);color:#fff;border-radius:0 0 24px 24px;margin:-16px -16px 20px;}
.ynj-patron-hero h2{font-size:20px;font-weight:800;margin-bottom:6px;color:#fff;}
.ynj-patron-hero p{font-size:13px;color:rgba(255,255,255,.75);line-height:1.5;max-width:400px;margin:0 auto;}
/* Patron tier rows */
.ynj-ptier{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:14px;background:#fff;border:2px solid #e5e7eb;cursor:pointer;transition:all .15s;position:relative;}
.ynj-ptier:hover,.ynj-ptier.selected{border-color:#00ADEF;box-shadow:0 4px 16px rgba(0,173,239,.12);}
.ynj-ptier.selected::after{content:'\2713';position:absolute;top:50%;right:14px;transform:translateY(-50%);width:24px;height:24px;border-radius:50%;background:#00ADEF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;}
.ynj-ptier--popular{border-color:#f59e0b;box-shadow:0 4px 16px rgba(245,158,11,.15);}
.ynj-ptier--popular:hover,.ynj-ptier--popular.selected{border-color:#f59e0b;box-shadow:0 6px 24px rgba(245,158,11,.2);}
.ynj-ptier__pop{position:absolute;top:-8px;left:50%;transform:translateX(-50%);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;padding:2px 10px;border-radius:6px;background:#f59e0b;color:#fff;white-space:nowrap;}
.ynj-ptier__left{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.ynj-ptier__mid{flex:1;}
.ynj-ptier__mid strong{font-size:15px;font-weight:700;color:#0a1628;}
.ynj-ptier__price{font-size:22px;font-weight:900;color:#0a1628;margin-right:30px;}
.ynj-ptier__price span{font-size:12px;font-weight:500;color:#6b8fa3;}
.ynj-patron-cta{margin-top:16px;text-align:center;}
.ynj-patron-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:16px;border-radius:14px;background:linear-gradient(135deg,#00ADEF,#0090d0);color:#fff;font-size:16px;font-weight:800;border:none;cursor:pointer;transition:all .15s;box-shadow:0 4px 16px rgba(0,173,239,.25);font-family:inherit;}
.ynj-patron-btn:hover{box-shadow:0 6px 24px rgba(0,173,239,.35);transform:translateY(-1px);}
.ynj-patron-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.ynj-patron-wall{margin-top:20px;}
.ynj-patron-wall h3{font-size:15px;font-weight:700;margin-bottom:10px;}
.ynj-pw-item{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;margin-bottom:4px;background:#f8fafc;}
.ynj-pw-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;}
.ynj-pw-name{font-size:13px;font-weight:600;flex:1;}
.ynj-pw-tier{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:2px 8px;border-radius:5px;background:#f0f9ff;color:#0369a1;}
.ynj-patron-stats{display:flex;gap:10px;justify-content:center;margin-bottom:16px;}
.ynj-patron-stats div{text-align:center;background:#fff;border-radius:12px;padding:14px 18px;flex:1;max-width:130px;box-shadow:0 2px 8px rgba(0,0,0,.04);}
.ynj-patron-stats strong{display:block;font-size:22px;font-weight:900;color:#00ADEF;}
.ynj-patron-stats span{font-size:10px;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
.ynj-login-prompt{background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:16px;text-align:center;margin-top:12px;font-size:13px;color:#6b8fa3;}
.ynj-login-prompt a{color:#00ADEF;font-weight:700;text-decoration:none;}
.ynj-status-card{background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;border-radius:14px;padding:20px;margin-bottom:16px;text-align:center;}
.ynj-status-card h3{font-size:16px;font-weight:700;color:#fff;margin-bottom:4px;}
.ynj-status-card p{font-size:13px;color:rgba(255,255,255,.75);}
.ynj-cancel-link{display:inline-block;margin-top:8px;font-size:12px;color:#fca5a5;cursor:pointer;text-decoration:underline;}
/* tier-grid no longer used */
</style>

<main class="ynj-main">
    <div class="ynj-patron-hero">
        <div style="font-size:36px;margin-bottom:4px;">🕌</div>
        <div id="patron-mosque-name" style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-bottom:6px;"><?php echo esc_html( $mosque_name ); ?></div>
        <h2 id="patron-title" style="font-size:24px;"><?php esc_html_e( 'Become a Patron', 'yourjannah' ); ?></h2>
        <p><?php esc_html_e( 'Your monthly support keeps this masjid running. Patrons get a badge and recognition on the patron wall.', 'yourjannah' ); ?></p>
    </div>

    <!-- Stats -->
    <div class="ynj-patron-stats" id="patron-stats" style="display:none;">
        <div><strong id="stat-patrons">0</strong><span><?php esc_html_e( 'Patrons', 'yourjannah' ); ?></span></div>
        <div><strong id="stat-monthly">&pound;0</strong><span><?php esc_html_e( 'Monthly', 'yourjannah' ); ?></span></div>
    </div>

    <!-- Active patron status (shown if already patron) -->
    <div id="active-status" style="display:none;"></div>

    <!-- Choose your level -->
    <h3 style="font-size:15px;font-weight:700;text-align:center;margin-bottom:12px;"><?php esc_html_e( 'Choose your level of support', 'yourjannah' ); ?></h3>

    <div id="tier-cards" style="display:flex;flex-direction:column;gap:8px;">
        <div class="ynj-ptier" data-tier="supporter" onclick="selectTier('supporter')">
            <div class="ynj-ptier__left" style="background:#cd7f32;"><span>🥉</span></div>
            <div class="ynj-ptier__mid"><strong><?php esc_html_e( 'Bronze', 'yourjannah' ); ?></strong></div>
            <div class="ynj-ptier__price">&pound;5<span>/mo</span></div>
        </div>
        <div class="ynj-ptier" data-tier="guardian" onclick="selectTier('guardian')">
            <div class="ynj-ptier__left" style="background:#9ca3af;"><span>🥈</span></div>
            <div class="ynj-ptier__mid"><strong><?php esc_html_e( 'Silver', 'yourjannah' ); ?></strong></div>
            <div class="ynj-ptier__price">&pound;10<span>/mo</span></div>
        </div>
        <div class="ynj-ptier ynj-ptier--popular selected" data-tier="champion" onclick="selectTier('champion')">
            <div class="ynj-ptier__pop"><?php esc_html_e( 'Most Popular', 'yourjannah' ); ?></div>
            <div class="ynj-ptier__left" style="background:#f59e0b;"><span>🥇</span></div>
            <div class="ynj-ptier__mid"><strong><?php esc_html_e( 'Gold', 'yourjannah' ); ?></strong></div>
            <div class="ynj-ptier__price">&pound;20<span>/mo</span></div>
        </div>
        <div class="ynj-ptier" data-tier="platinum" onclick="selectTier('platinum')">
            <div class="ynj-ptier__left" style="background:#6b21a8;"><span>💎</span></div>
            <div class="ynj-ptier__mid"><strong><?php esc_html_e( 'Platinum', 'yourjannah' ); ?></strong></div>
            <div class="ynj-ptier__price">&pound;50<span>/mo</span></div>
        </div>
    </div>

    <div class="ynj-patron-cta" id="patron-cta">
        <button class="ynj-patron-btn" id="patron-btn" onclick="becomePerson()">
            🏅 <?php esc_html_e( 'Become a Gold Patron', 'yourjannah' ); ?> &mdash; &pound;20/mo
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
    let selectedTier = sessionStorage.getItem('ynj_patron_tier') || 'champion';
    let mosqueId = <?php echo $mosque_id; ?>;

    const tierPrices = { supporter: 5, guardian: 10, champion: 20, platinum: 50 };
    const tierNames = { supporter: 'Bronze', guardian: 'Silver', champion: 'Gold', platinum: 'Platinum' };

    function selectTier(tier) {
        selectedTier = tier;
        sessionStorage.setItem('ynj_patron_tier', tier);
        document.querySelectorAll('.ynj-ptier').forEach(el => {
            el.classList.toggle('selected', el.dataset.tier === tier);
        });
        const btn = document.getElementById('patron-btn');
        if (btn) btn.textContent = '\uD83C\uDFC5 <?php echo esc_js( __( 'Become a', 'yourjannah' ) ); ?> ' + (tierNames[tier] || '') + ' <?php echo esc_js( __( 'Patron', 'yourjannah' ) ); ?> \u2014 \u00a3' + tierPrices[tier] + '/mo';
    }
    window.selectTier = selectTier;

    // Apply saved selection on page load
    selectTier(selectedTier);

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

    // Submit intention (pledge)
    async function submitIntention() {
        var name = document.getElementById('int-name').value.trim();
        var email = document.getElementById('int-email').value.trim();
        var phone = document.getElementById('int-phone').value.trim();
        var btn = document.getElementById('int-btn');
        var msg = document.getElementById('int-msg');
        if (!name || !email) { msg.style.display = ''; msg.style.color = '#dc2626'; msg.textContent = '<?php echo esc_js( __( 'Name and email are required.', 'yourjannah' ) ); ?>'; return; }
        btn.disabled = true; btn.textContent = '<?php echo esc_js( __( 'Submitting...', 'yourjannah' ) ); ?>';
        try {
            var res = await fetch(API + 'intentions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mosque_slug: slug, name: name, email: email, phone: phone, tier: selectedTier })
            });
            var data = await res.json();
            if (data.ok) {
                msg.style.display = ''; msg.style.color = '#166534';
                msg.textContent = '<?php echo esc_js( __( 'Your intention has been recorded. We\'ll notify you when this mosque joins!', 'yourjannah' ) ); ?>';
                btn.textContent = '<?php echo esc_js( __( 'Intention Recorded ✓', 'yourjannah' ) ); ?>';
                btn.style.background = '#166534';
                if (data.total) document.getElementById('int-count').textContent = data.total + ' <?php echo esc_js( __( 'people have shown their intention', 'yourjannah' ) ); ?>';
            } else {
                msg.style.display = ''; msg.style.color = '#dc2626'; msg.textContent = data.error || '<?php echo esc_js( __( 'Something went wrong.', 'yourjannah' ) ); ?>';
                btn.disabled = false; btn.textContent = '🤲 <?php echo esc_js( __( 'Register My Intention', 'yourjannah' ) ); ?>';
            }
        } catch(e) {
            msg.style.display = ''; msg.style.color = '#dc2626'; msg.textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>';
            btn.disabled = false; btn.textContent = '🤲 <?php echo esc_js( __( 'Register My Intention', 'yourjannah' ) ); ?>';
        }
    }
    window.submitIntention = submitIntention;

    // Mosque data pre-loaded from PHP — no fetch needed for name/status/id

    // Show intention section for unclaimed mosques (and always for non-logged-in users)
    <?php if ( $mosque_status === 'unclaimed' ) : ?>
    (function(){
        var heroDesc = document.querySelector('.ynj-patron-hero p');
        if (heroDesc) heroDesc.textContent = '<?php echo esc_js( __( 'This mosque hasn\'t claimed their YourJannah page yet. Your support goes through YourJannah — when they join, they receive it directly.', 'yourjannah' ) ); ?>';
    })();
    <?php endif; ?>
    <?php if ( $mosque_status === 'unclaimed' ) : ?>
    document.getElementById('intention-section').style.display = '';
    <?php endif; ?>
    if (!token) {
        document.getElementById('intention-section').style.display = '';
    }
    // Load intention count (lightweight, dynamic data)
    if (document.getElementById('intention-section').style.display !== 'none' && mosqueId) {
        fetch(API + 'mosques/' + mosqueId + '/intentions')
            .then(function(r){ return r.json(); })
            .then(function(iData) {
                if (iData.total > 0) {
                    document.getElementById('int-count').textContent = iData.total + ' <?php echo esc_js( __( 'people have shown their intention', 'yourjannah' ) ); ?>';
                }
            }).catch(function(){});
    }

    // Load patron wall (dynamic data — patrons change frequently)
    if (mosqueId) {
        fetch(API + 'mosques/' + mosqueId + '/patrons')
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.total_patrons > 0) {
                    document.getElementById('patron-stats').style.display = 'flex';
                    document.getElementById('stat-patrons').textContent = data.total_patrons;
                    document.getElementById('stat-monthly').textContent = '\u00a3' + (data.monthly_pence / 100).toFixed(0);
                }

                if (data.patrons && data.patrons.length) {
                    const tierEmoji = { champion: '\ud83c\udfc6', guardian: '\ud83d\udee1\ufe0f', supporter: '\u2b50' };
                    document.getElementById('patron-wall').style.display = 'block';
                    document.getElementById('patron-list').innerHTML = data.patrons.map(function(p) {
                        const initial = (p.name || '?')[0].toUpperCase();
                        return '<div class="ynj-pw-item">' +
                            '<div class="ynj-pw-avatar">' + initial + '</div>' +
                            '<div class="ynj-pw-name">' + p.name + ' ' + (tierEmoji[p.tier] || '') + '</div>' +
                            '<div class="ynj-pw-tier">' + p.tier + '</div>' +
                        '</div>';
                    }).join('');
                }
            })
            .catch(function(){});
    }

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
                const tierLabel = { supporter: 'Bronze', guardian: 'Silver', champion: 'Gold', platinum: 'Platinum' };
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
