<?php
/**
 * Template: Sponsor YourJannah
 *
 * Sadaqah Jariyah — sponsor the platform that helps thousands of masjids.
 *
 * @package YourJannah
 */

get_header();
?>
<style>
.syj-hero{text-align:center;padding:40px 20px 24px;background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 40%,#0e4d3c 100%);color:#fff;border-radius:0 0 24px 24px;margin:-16px -16px 20px;}
.syj-hero h1{font-size:26px;font-weight:900;margin-bottom:6px;line-height:1.2;}
.syj-hero p{font-size:14px;opacity:.85;max-width:420px;margin:0 auto;line-height:1.6;}
.syj-stat-row{display:flex;gap:10px;justify-content:center;margin:16px 0 0;}
.syj-stat{background:rgba(255,255,255,.12);backdrop-filter:blur(8px);border-radius:12px;padding:12px 16px;text-align:center;flex:1;max-width:120px;}
.syj-stat strong{display:block;font-size:22px;font-weight:900;color:#00ADEF;}
.syj-stat span{font-size:10px;text-transform:uppercase;letter-spacing:.5px;opacity:.7;font-weight:600;}
.syj-why{margin-bottom:20px;}
.syj-why h2{font-size:18px;font-weight:800;margin-bottom:12px;}
.syj-feature{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f0f0ec;}
.syj-feature:last-child{border-bottom:none;}
.syj-feature__icon{font-size:24px;flex-shrink:0;width:40px;text-align:center;}
.syj-feature__text h4{font-size:14px;font-weight:700;margin-bottom:2px;}
.syj-feature__text p{font-size:12px;color:#6b8fa3;line-height:1.4;margin:0;}
.syj-tiers{display:flex;flex-direction:column;gap:12px;margin-bottom:20px;}
.syj-tier{background:#fff;border:2px solid #e5e7eb;border-radius:16px;padding:20px;text-align:center;transition:all .2s;cursor:pointer;position:relative;}
.syj-tier:hover,.syj-tier.selected{border-color:#00ADEF;box-shadow:0 4px 20px rgba(0,173,239,.12);}
.syj-tier.selected::after{content:'\2713';position:absolute;top:10px;right:12px;width:24px;height:24px;border-radius:50%;background:#00ADEF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;}
.syj-tier__price{font-size:32px;font-weight:900;color:#0a1628;}
.syj-tier__price span{font-size:14px;font-weight:500;color:#6b8fa3;}
.syj-cta-btn{display:block;width:100%;padding:16px;border:none;border-radius:14px;background:linear-gradient(135deg,#00ADEF,#0090d0);color:#fff;font-size:16px;font-weight:800;cursor:pointer;text-align:center;text-decoration:none;font-family:inherit;transition:all .15s;box-shadow:0 4px 16px rgba(0,173,239,.25);}
.syj-cta-btn:hover{background:linear-gradient(135deg,#0090d0,#0070a8);box-shadow:0 6px 24px rgba(0,173,239,.35);}
.syj-cta-btn:disabled{opacity:.6;cursor:not-allowed;}
.syj-hadith{background:#f8fafc;border-radius:14px;padding:20px;text-align:center;margin:20px 0;border:1px solid #e5e7eb;}
.syj-hadith em{font-size:14px;line-height:1.6;color:#374151;}
.syj-hadith span{display:block;font-size:11px;color:#6b8fa3;margin-top:6px;}
.syj-wall{margin-top:16px;}
.syj-wall h3{font-size:15px;font-weight:700;margin-bottom:10px;}
.syj-wall__item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0ec;}
.syj-wall__item:last-child{border-bottom:none;}
.syj-wall__avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;}
.syj-wall__name{font-size:13px;font-weight:600;flex:1;}
.syj-wall__tier{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b8fa3;}
.syj-impact{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0;}
.syj-impact__card{background:#f0fdf4;border-radius:12px;padding:14px;text-align:center;}
.syj-impact__card strong{display:block;font-size:20px;font-weight:900;color:#166534;}
.syj-impact__card span{font-size:10px;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;font-weight:600;}
@media(min-width:640px){.syj-tiers{flex-direction:row;}.syj-tier{flex:1;}}
</style>

<main class="ynj-main">

    <!-- Hero -->
    <div class="syj-hero">
        <div style="font-size:48px;margin-bottom:8px;">🕌</div>
        <h1><?php esc_html_e( 'Sponsor YourJannah', 'yourjannah' ); ?></h1>
        <p><?php esc_html_e( 'YourJannah is free for every masjid. We believe no mosque should pay for the tools they need to serve their community. But we still have costs. Your sponsorship is sadaqah jariyah.', 'yourjannah' ); ?></p>

        <div class="syj-stat-row" id="syj-stats">
            <div class="syj-stat"><strong id="stat-mosques">1,400+</strong><span><?php esc_html_e( 'Mosques', 'yourjannah' ); ?></span></div>
            <div class="syj-stat"><strong>0%</strong><span><?php esc_html_e( 'Fees to Masjids', 'yourjannah' ); ?></span></div>
            <div class="syj-stat"><strong>&infin;</strong><span><?php esc_html_e( 'Reward', 'yourjannah' ); ?></span></div>
        </div>
    </div>

    <!-- Hadith -->
    <div class="syj-hadith">
        <em>&ldquo;<?php esc_html_e( 'When a person dies, their deeds come to an end except for three: ongoing charity (sadaqah jariyah), beneficial knowledge, or a righteous child who prays for them.', 'yourjannah' ); ?>&rdquo;</em>
        <span>&mdash; Sahih Muslim 1631</span>
    </div>

    <!-- Sponsor amounts -->
    <h2 style="font-size:18px;font-weight:800;margin-bottom:12px;"><?php esc_html_e( 'Sponsor Our Project', 'yourjannah' ); ?></h2>

    <div class="syj-tiers" id="syj-tiers">
        <div class="syj-tier selected" data-tier="tier_50" data-amount="50" onclick="selectSyjTier('tier_50')">
            <div class="syj-tier__price">&pound;50 <span>/month</span></div>
        </div>
        <div class="syj-tier" data-tier="tier_100" data-amount="100" onclick="selectSyjTier('tier_100')">
            <div class="syj-tier__price">&pound;100 <span>/month</span></div>
        </div>
        <div class="syj-tier" data-tier="tier_250" data-amount="250" onclick="selectSyjTier('tier_250')">
            <div class="syj-tier__price">&pound;250 <span>/month</span></div>
        </div>
        <div class="syj-tier" data-tier="tier_500" data-amount="500" onclick="selectSyjTier('tier_500')">
            <div class="syj-tier__price">&pound;500 <span>/month</span></div>
        </div>
    </div>

    <!-- Custom amount -->
    <div style="text-align:center;margin-bottom:16px;">
        <label style="font-size:13px;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Or enter a custom amount', 'yourjannah' ); ?></label>
        <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
            <span style="font-size:18px;font-weight:700;color:#0a1628;">&pound;</span>
            <input type="number" id="syj-custom" placeholder="0" min="1" style="width:80px;padding:10px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:16px;font-weight:700;text-align:center;font-family:inherit;">
            <span style="font-size:13px;color:#6b8fa3;">/month</span>
        </div>
    </div>

    <!-- Name & Email -->
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
        <input type="text" id="syj-name" placeholder="<?php esc_attr_e( 'Your name', 'yourjannah' ); ?>" style="padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:15px;font-family:inherit;">
        <input type="email" id="syj-email" placeholder="<?php esc_attr_e( 'Your email', 'yourjannah' ); ?>" style="padding:12px 16px;border:1px solid #d1d5db;border-radius:10px;font-size:15px;font-family:inherit;">
    </div>

    <!-- CTA -->
    <button class="syj-cta-btn" id="syj-btn" onclick="sponsorYJ()">
        🤲 <?php esc_html_e( 'Sponsor YourJannah', 'yourjannah' ); ?> &mdash; &pound;50/month
    </button>
    <p id="syj-msg" style="text-align:center;font-size:12px;margin-top:8px;display:none;"></p>

    <!-- Where it goes -->
    <div style="margin-top:24px;padding:20px;background:#f8fafc;border-radius:14px;border:1px solid #e5e7eb;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:12px;"><?php esc_html_e( 'Where Does Your Money Go?', 'yourjannah' ); ?></h3>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
            <div style="display:flex;align-items:center;gap:8px;"><span style="font-size:16px;">👨‍💻</span> <strong>40%</strong> <?php esc_html_e( 'Staff & development', 'yourjannah' ); ?></div>
            <div style="display:flex;align-items:center;gap:8px;"><span style="font-size:16px;">🖥️</span> <strong>30%</strong> <?php esc_html_e( 'Servers & running costs', 'yourjannah' ); ?></div>
            <div style="display:flex;align-items:center;gap:8px;"><span style="font-size:16px;">🕌</span> <strong>20%</strong> <?php esc_html_e( 'Mosque onboarding & support', 'yourjannah' ); ?></div>
            <div style="display:flex;align-items:center;gap:8px;"><span style="font-size:16px;">🌍</span> <strong>10%</strong> <?php esc_html_e( 'Expansion to new regions', 'yourjannah' ); ?></div>
        </div>
    </div>

</main>

<script>
(function(){
    var selectedTier = 'tier_50';
    var tierAmounts = { tier_50: 50, tier_100: 100, tier_250: 250, tier_500: 500 };

    window.selectSyjTier = function(tier) {
        selectedTier = tier;
        document.querySelectorAll('.syj-tier').forEach(function(el) {
            el.classList.toggle('selected', el.dataset.tier === tier);
        });
        document.getElementById('syj-custom').value = '';
        updateBtn();
    };

    document.getElementById('syj-custom').addEventListener('input', function() {
        if (this.value) {
            document.querySelectorAll('.syj-tier').forEach(function(el) { el.classList.remove('selected'); });
            selectedTier = 'custom';
        }
        updateBtn();
    });

    function getAmount() {
        var custom = parseInt(document.getElementById('syj-custom').value);
        return custom > 0 ? custom : (tierAmounts[selectedTier] || 10);
    }

    function updateBtn() {
        var amount = getAmount();
        document.getElementById('syj-btn').innerHTML = '\uD83E\uDD32 <?php echo esc_js( __( 'Sponsor YourJannah', 'yourjannah' ) ); ?> \u2014 \u00a3' + amount + '/month';
    }

    window.sponsorYJ = async function() {
        var name = document.getElementById('syj-name').value.trim();
        var email = document.getElementById('syj-email').value.trim();
        var btn = document.getElementById('syj-btn');
        var msg = document.getElementById('syj-msg');

        if (!email) {
            msg.style.display = ''; msg.style.color = '#dc2626';
            msg.textContent = '<?php echo esc_js( __( 'Please enter your email.', 'yourjannah' ) ); ?>';
            return;
        }

        btn.disabled = true;
        btn.textContent = '<?php echo esc_js( __( 'Redirecting to checkout...', 'yourjannah' ) ); ?>';

        var amount = getAmount();
        var tier = selectedTier === 'custom' ? 'custom' : selectedTier;

        try {
            var res = await fetch(ynjData.restUrl + 'sponsor-yj/checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount_pounds: amount, tier: tier, name: name, email: email })
            });
            var data = await res.json();
            if (data.ok && data.checkout_url) {
                window.location.href = data.checkout_url;
            } else {
                msg.style.display = ''; msg.style.color = '#dc2626';
                msg.textContent = data.error || '<?php echo esc_js( __( 'Something went wrong.', 'yourjannah' ) ); ?>';
                btn.disabled = false; updateBtn();
            }
        } catch(e) {
            msg.style.display = ''; msg.style.color = '#dc2626';
            msg.textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>';
            btn.disabled = false; updateBtn();
        }
    };

    // Success check
    if (window.location.search.indexOf('payment=success') > -1) {
        var hero = document.querySelector('.syj-hero');
        if (hero) {
            hero.innerHTML = '<div style="font-size:48px;margin-bottom:12px;">🤲</div><h1><?php echo esc_js( __( 'JazakAllah Khayr!', 'yourjannah' ) ); ?></h1><p><?php echo esc_js( __( 'Your sponsorship is confirmed. May Allah accept it as sadaqah jariyah and multiply your reward. You are now helping mosques across the UK.', 'yourjannah' ) ); ?></p>';
        }
        var tiers = document.getElementById('syj-tiers');
        if (tiers) tiers.style.display = 'none';
        var btn = document.getElementById('syj-btn');
        if (btn) btn.style.display = 'none';
    }
})();
</script>
<?php
get_footer();
