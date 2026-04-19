<?php
/**
 * Footer template — mobile bottom navigation + wp_footer()
 *
 * @package YourJannah
 */
?>

<?php if ( has_nav_menu( 'mobile' ) ) : ?>
<nav class="ynj-nav">
    <div class="ynj-nav__inner">
        <?php wp_nav_menu( [
            'theme_location' => 'mobile',
            'container'      => false,
            'menu_class'     => '',
            'depth'          => 1,
            'fallback_cb'    => 'ynj_default_mobile_nav',
            'walker'         => new YNJ_Mobile_Nav_Walker(),
        ] ); ?>
    </div>
</nav>
<?php else : ?>
<nav class="ynj-nav">
    <div class="ynj-nav__inner">
        <?php ynj_default_mobile_nav(); ?>
    </div>
</nav>
<?php endif; ?>

<!-- Mosque selector now in header.php as modal -->
<div id="mosque-dropdown" style="display:none;">
    <div class="ynj-dropdown__inner">
        <form method="get" action="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" style="display:flex;gap:8px;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
            <input class="ynj-dropdown__search" name="q" type="text" placeholder="<?php esc_attr_e( 'Search mosques...', 'yourjannah' ); ?>" autocomplete="off" style="flex:1;padding:10px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;">
            <button type="submit" style="padding:10px 16px;border:none;border-radius:10px;background:#00ADEF;color:#fff;font-weight:700;font-size:13px;cursor:pointer;"><?php esc_html_e( 'Search', 'yourjannah' ); ?></button>
        </form>
        <div class="ynj-dropdown__list" id="mosque-list">
            <p style="padding:8px 16px 4px;font-size:11px;font-weight:700;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;">📍 <?php esc_html_e( 'Nearby', 'yourjannah' ); ?></p>
            <?php
            $slug_for_dd = ynj_mosque_slug();
            $mosque_for_dd = ynj_get_mosque( $slug_for_dd );
            $dd_nearby = [];
            if ( $mosque_for_dd && $mosque_for_dd->latitude && class_exists( 'YNJ_DB' ) ) {
                global $wpdb;
                $mt = YNJ_DB::table( 'mosques' );
                $dd_nearby = $wpdb->get_results( $wpdb->prepare(
                    "SELECT slug, name, city, postcode,
                            ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
                     FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
                     ORDER BY distance ASC LIMIT 5",
                    $mosque_for_dd->latitude, $mosque_for_dd->longitude, $mosque_for_dd->latitude
                ) ) ?: [];
            }
            foreach ( $dd_nearby as $nm ) :
                $dist = isset( $nm->distance ) ? number_format( (float) $nm->distance, 1 ) . 'km' : '';
            ?>
            <a href="<?php echo esc_url( home_url( '/?ynj_select=' . $nm->slug ) ); ?>" style="display:block;padding:12px 16px;text-decoration:none;color:#0a1628;border-bottom:1px solid #f0f0f0;">
                <strong style="font-size:14px;display:block;"><?php echo esc_html( $nm->name ); ?></strong>
                <span style="font-size:11px;color:#6b8fa3;"><?php echo esc_html( implode( ', ', array_filter( [ $nm->city, $nm->postcode ] ) ) ); ?><?php if ( $dist ) echo ' · ' . esc_html( $dist ); ?></span>
            </a>
            <?php endforeach; ?>
            <a href="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" style="display:block;padding:12px 16px;text-align:center;font-size:13px;font-weight:700;color:#00ADEF;text-decoration:none;"><?php esc_html_e( 'Browse All Mosques →', 'yourjannah' ); ?></a>
        </div>
    </div>
</div>

<?php
// ============================================================
// FLOATING NIYYAH BAR — Stripe-connected donation widget
// Shows on all pages when a mosque is selected
// ============================================================
$_nb_slug = ynj_mosque_slug();
if ( ! $_nb_slug && isset( $_COOKIE['ynj_mosque_slug'] ) ) {
    $_nb_slug = sanitize_title( $_COOKIE['ynj_mosque_slug'] );
}
if ( ! $_nb_slug ) {
    $_nb_slug = 'yourniyyah-masjid'; // default
}
$_nb_mosque = $_nb_slug ? ynj_get_mosque( $_nb_slug ) : null;
$_nb_name = $_nb_mosque ? $_nb_mosque->name : '';
$_nb_id = $_nb_mosque ? (int) $_nb_mosque->id : 0;
$_nb_pk = class_exists( 'YNJ_Stripe' ) ? YNJ_Stripe::public_key() : '';

// Pre-load mosque funds from DB (instant, no API call)
$_nb_funds = [];
if ( $_nb_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $_nb_ft = YNJ_DB::table( 'mosque_funds' );
    $_nb_funds = $wpdb->get_results( $wpdb->prepare(
        "SELECT slug, label, is_default FROM $_nb_ft WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
        $_nb_id
    ) ) ?: [];
    // If no funds in DB yet, seed them
    if ( empty( $_nb_funds ) ) {
        YNJ_DB::seed_default_funds();
        $_nb_funds = $wpdb->get_results( $wpdb->prepare(
            "SELECT slug, label, is_default FROM $_nb_ft WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
            $_nb_id
        ) ) ?: [];
    }
}
if ( $_nb_id && $_nb_pk ) :
?>
<script src="https://js.stripe.com/v3/" async></script>
<style>
.ynj-niyyah{position:fixed;bottom:60px;left:0;right:0;z-index:199;max-width:500px;margin:0 auto;pointer-events:none;}
.ynj-niyyah__bar,.ynj-niyyah__body{pointer-events:auto;background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 50%,#00ADEF 100%);color:#fff;}
.ynj-niyyah__bar{border-radius:18px 18px 0 0;box-shadow:0 -4px 30px rgba(0,0,0,.3);}
.ynj-niyyah--open .ynj-niyyah__body{border-radius:0 0 0 0;box-shadow:0 -4px 30px rgba(0,0,0,.3);}
@media(min-width:901px){.ynj-niyyah{bottom:0;}}
/* Toggle bar — always visible */
.ynj-niyyah__bar{display:flex;align-items:center;gap:8px;padding:10px 14px;cursor:pointer;}
.ynj-niyyah__bar-label{font-size:13px;font-weight:700;white-space:nowrap;flex-shrink:0;}
.ynj-niyyah__bar-fund{flex:1;padding:7px 28px 7px 10px;border:1px solid rgba(255,255,255,.25);border-radius:8px;background:rgba(255,255,255,.1);color:#fff;font-size:12px;font-weight:600;font-family:inherit;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='white' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;min-width:0;}
.ynj-niyyah__bar-fund option{background:#1a3a5c;color:#fff;}
.ynj-niyyah__toggle{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid rgba(255,255,255,.25);border-radius:8px;background:rgba(255,255,255,.1);color:#fff;cursor:pointer;flex-shrink:0;transition:transform .2s;}
.ynj-niyyah--open .ynj-niyyah__toggle svg{transform:rotate(180deg);}
/* Body — hidden until open */
.ynj-niyyah__body{padding:0 18px 14px;display:none;}
.ynj-niyyah--open .ynj-niyyah__body{display:block;}
.ynj-niyyah__mosque{font-size:11px;color:rgba(255,255,255,.6);text-align:center;margin-bottom:8px;}
.ynj-niyyah__steps{display:flex;justify-content:center;gap:6px;margin-bottom:12px;}
.ynj-niyyah__dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.2);transition:all .2s;}
.ynj-niyyah__dot--active{background:#fff;width:20px;border-radius:4px;}
/* Frequency */
.ynj-nb-freq{display:flex;gap:4px;margin-bottom:10px;background:rgba(0,0,0,.15);border-radius:10px;padding:3px;}
.ynj-nb-freq__btn{flex:1;padding:8px 4px;border:none;border-radius:8px;background:transparent;color:rgba(255,255,255,.7);font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;}
.ynj-nb-freq__btn--active{background:#fff;color:#0a1628;}
/* Amounts */
.ynj-nb-amounts{display:flex;gap:6px;margin-bottom:6px;}
.ynj-nb-amt{flex:1;padding:10px 4px;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.08);color:#fff;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;text-align:center;transition:all .15s;}
.ynj-nb-amt:hover{background:rgba(255,255,255,.15);}
.ynj-nb-amt--active{background:#fff!important;color:#0a1628!important;border-color:#fff!important;box-shadow:0 2px 12px rgba(0,0,0,.2);}
.ynj-nb-other{flex:1;position:relative;}
.ynj-nb-other input{width:100%;padding:10px 8px;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.08);color:#fff;font-size:14px;font-weight:700;font-family:inherit;text-align:center;box-sizing:border-box;}
.ynj-nb-other input::placeholder{color:rgba(255,255,255,.4);font-weight:600;}
.ynj-nb-other input:focus{outline:none;border-color:#fff;background:rgba(255,255,255,.15);}
/* Step 2 */
.ynj-nb-email{width:100%;padding:12px 14px;border:1px solid rgba(255,255,255,.25);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:14px;font-family:inherit;margin-bottom:8px;box-sizing:border-box;}
.ynj-nb-email::placeholder{color:rgba(255,255,255,.4);}
.ynj-nb-email:focus{outline:none;border-color:#fff;background:rgba(255,255,255,.15);}
.ynj-nb-fund{width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,.25);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:13px;font-weight:600;font-family:inherit;margin-bottom:10px;box-sizing:border-box;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='white' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px;}
.ynj-nb-fund option{background:#1a3a5c;color:#fff;}
.ynj-nb-next{width:100%;padding:14px;border:none;border-radius:12px;background:#fff;color:#0a1628;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;transition:all .15s;}
.ynj-nb-next:hover{box-shadow:0 4px 16px rgba(0,0,0,.2);transform:translateY(-1px);}
.ynj-nb-next:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.ynj-nb-back{background:none;border:none;color:rgba(255,255,255,.6);font-size:13px;cursor:pointer;padding:4px 0;margin-bottom:8px;font-family:inherit;}
/* Step 3: Card */
.ynj-nb-card{padding:12px 14px;border:1px solid rgba(255,255,255,.25);border-radius:10px;background:rgba(255,255,255,.1);margin-bottom:10px;}
.ynj-nb-error{color:#fca5a5;font-size:12px;margin-bottom:8px;display:none;}
.ynj-nb-pay{width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit;}
.ynj-nb-pay:disabled{opacity:.5;cursor:not-allowed;}
/* Step 4: Success */
.ynj-nb-success{text-align:center;padding:16px 0;}
.ynj-nb-success__icon{font-size:40px;margin-bottom:8px;}
.ynj-nb-success__title{font-size:18px;font-weight:800;margin-bottom:4px;}
.ynj-nb-success__sub{font-size:13px;color:rgba(255,255,255,.7);line-height:1.5;}
.ynj-nb-secure{text-align:center;font-size:10px;color:rgba(255,255,255,.4);margin-top:8px;}
@media(max-width:500px){.ynj-niyyah{border-radius:14px 14px 0 0;max-width:none;}}
</style>

<div class="ynj-niyyah" id="ynj-niyyah-bar">
    <!-- Toggle bar: fund dropdown + open/close arrow -->
    <div class="ynj-niyyah__bar" onclick="var b=document.getElementById('ynj-niyyah-bar');b.classList.toggle('ynj-niyyah--open');">
        <span class="ynj-niyyah__bar-label">🕌 Donate</span>
        <select class="ynj-niyyah__bar-fund" id="nb-fund" onclick="event.stopPropagation()">
            <?php foreach ( $_nb_funds as $f ) : ?>
            <option value="<?php echo esc_attr( $f->slug ); ?>"><?php echo esc_html( $f->label ); ?></option>
            <?php endforeach; ?>
            <?php if ( empty( $_nb_funds ) ) : ?>
            <option value="general">General Donation</option>
            <?php endif; ?>
        </select>
        <button type="button" class="ynj-niyyah__toggle" id="nb-toggle" onclick="event.stopPropagation();document.getElementById('ynj-niyyah-bar').classList.toggle('ynj-niyyah--open')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 15l6-6 6 6"/></svg>
        </button>
    </div>

    <div class="ynj-niyyah__body">
        <div class="ynj-niyyah__mosque">🕌 <?php echo esc_html( $_nb_name ); ?></div>

        <div class="ynj-niyyah__steps" id="nb-steps">
            <div class="ynj-niyyah__dot ynj-niyyah__dot--active" data-step="1"></div>
            <div class="ynj-niyyah__dot" data-step="2"></div>
            <div class="ynj-niyyah__dot" data-step="3"></div>
        </div>

        <!-- STEP 1: Frequency + Amount -->
        <div id="nb-step1">
            <div class="ynj-nb-freq" id="nb-freq">
                <button type="button" class="ynj-nb-freq__btn ynj-nb-freq__btn--active" data-freq="week">Every Friday</button>
                <button type="button" class="ynj-nb-freq__btn" data-freq="month">Monthly</button>
                <button type="button" class="ynj-nb-freq__btn" data-freq="once">One-off</button>
            </div>
            <div class="ynj-nb-amounts">
                <button type="button" class="ynj-nb-amt" data-amount="500">&pound;5</button>
                <button type="button" class="ynj-nb-amt" data-amount="1000">&pound;10</button>
                <button type="button" class="ynj-nb-amt" data-amount="2500">&pound;25</button>
            </div>
            <div class="ynj-nb-amounts">
                <button type="button" class="ynj-nb-amt" data-amount="5000">&pound;50</button>
                <button type="button" class="ynj-nb-amt" data-amount="10000">&pound;100</button>
                <div class="ynj-nb-other"><input type="number" id="nb-custom" placeholder="Other" min="1"></div>
            </div>
        </div>

        <!-- STEP 2: Email -->
        <div id="nb-step2" style="display:none">
            <button type="button" class="ynj-nb-back" onclick="nbSetStep(1)">&larr; Back</button>
            <input type="email" class="ynj-nb-email" id="nb-email" placeholder="Your email address" autocomplete="email">
            <button type="button" class="ynj-nb-next" id="nb-next" onclick="nbGoStep3()">Make Your Niyyah &rarr;</button>
        </div>

        <!-- STEP 3: Card Payment -->
        <div id="nb-step3" style="display:none">
            <button type="button" class="ynj-nb-back" onclick="nbSetStep(2)">&larr; Back</button>
            <div class="ynj-nb-card" id="nb-card-element"></div>
            <div class="ynj-nb-error" id="nb-card-error"></div>
            <button type="button" class="ynj-nb-pay" id="nb-pay-btn" disabled onclick="nbPay()">Donate</button>
            <div class="ynj-nb-secure">🔒 Secured by Stripe</div>
        </div>

        <!-- STEP 4: Success -->
        <div id="nb-step4" style="display:none">
            <div class="ynj-nb-success">
                <div class="ynj-nb-success__icon">✅</div>
                <div class="ynj-nb-success__title">JazakAllah Khair!</div>
                <p class="ynj-nb-success__sub">Your donation to <?php echo esc_html( $_nb_name ); ?> has been processed. A receipt is on its way.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var bar = document.getElementById('ynj-niyyah-bar');
    if (!bar) return;

    var API = '<?php echo esc_url_raw( rest_url( 'ynj/v1/' ) ); ?>';
    var PK  = '<?php echo esc_js( $_nb_pk ); ?>';
    var mosqueId = <?php echo (int) $_nb_id; ?>;
    var mosqueSlug = '<?php echo esc_js( $_nb_slug ); ?>';

    var selectedFreq = 'week';
    var selectedAmount = 0;
    var donationId = 0;
    var stripe = null;
    var cardElement = null;
    var cardReady = false;

    // Bar starts closed — user taps to open

    // Frequency toggle
    bar.querySelectorAll('.ynj-nb-freq__btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            bar.querySelectorAll('.ynj-nb-freq__btn').forEach(function(b){ b.classList.remove('ynj-nb-freq__btn--active'); });
            btn.classList.add('ynj-nb-freq__btn--active');
            selectedFreq = btn.dataset.freq;
        });
    });

    // Amount buttons
    bar.querySelectorAll('.ynj-nb-amt').forEach(function(btn){
        btn.addEventListener('click', function(){
            bar.querySelectorAll('.ynj-nb-amt').forEach(function(b){ b.classList.remove('ynj-nb-amt--active'); });
            btn.classList.add('ynj-nb-amt--active');
            selectedAmount = parseInt(btn.dataset.amount);
            document.getElementById('nb-custom').value = '';
            setTimeout(function(){ nbSetStep(2); }, 300);
        });
    });

    // Custom amount
    var customIn = document.getElementById('nb-custom');
    customIn.addEventListener('input', function(){
        var v = parseFloat(this.value);
        if (v > 0) {
            bar.querySelectorAll('.ynj-nb-amt').forEach(function(b){ b.classList.remove('ynj-nb-amt--active'); });
            selectedAmount = Math.round(v * 100);
        }
    });
    customIn.addEventListener('keydown', function(e){
        if (e.key === 'Enter' && selectedAmount > 0) nbSetStep(2);
    });

    // Pre-fill email
    var emailIn = document.getElementById('nb-email');
    var saved = localStorage.getItem('ynj_user_email') || localStorage.getItem('yn_user_email') || '';
    if (saved) emailIn.value = saved;

    // Step management
    window.nbSetStep = function(step) {
        ['nb-step1','nb-step2','nb-step3','nb-step4'].forEach(function(id, i){
            document.getElementById(id).style.display = (i+1 === step) ? '' : 'none';
        });
        bar.querySelectorAll('.ynj-niyyah__dot').forEach(function(d, i){
            d.classList.toggle('ynj-niyyah__dot--active', i+1 === step);
        });
        if (step === 2) {
            updateNextBtn();
            setTimeout(function(){ emailIn.focus(); }, 200);
        }
        if (step === 3) initStripe();
    };

    function updateNextBtn() {
        var amt = selectedAmount / 100;
        var label = selectedFreq === 'once' ? 'Donate \u00A3' + amt : (selectedFreq === 'week' ? 'Donate \u00A3' + amt + '/week' : 'Donate \u00A3' + amt + '/month');
        document.getElementById('nb-next').textContent = label + ' \u2192';
    }

    // Step 2 → 3
    window.nbGoStep3 = function() {
        var email = emailIn.value.trim();
        if (!email || !email.includes('@')) { emailIn.style.borderColor = '#fca5a5'; return; }
        emailIn.style.borderColor = '';
        localStorage.setItem('ynj_user_email', email);
        nbSetStep(3);
        updatePayBtn();
    };

    function updatePayBtn() {
        var amt = selectedAmount / 100;
        var freq = selectedFreq === 'once' ? '' : (selectedFreq === 'week' ? '/week' : '/month');
        document.getElementById('nb-pay-btn').textContent = 'Donate \u00A3' + amt + freq + ' \u2192';
    }

    // Init Stripe
    function initStripe() {
        if (stripe) return;
        if (typeof Stripe === 'undefined') { setTimeout(initStripe, 200); return; }
        stripe = Stripe(PK);
        var elements = stripe.elements();
        cardElement = elements.create('card', {
            style: { base: { color:'#fff', fontSize:'15px', fontWeight:'500', '::placeholder':{ color:'rgba(255,255,255,.4)' } }, invalid:{ color:'#fca5a5' } }
        });
        cardElement.mount('#nb-card-element');
        cardElement.on('change', function(e){
            cardReady = e.complete;
            document.getElementById('nb-pay-btn').disabled = !e.complete;
            var err = document.getElementById('nb-card-error');
            if (e.error) { err.textContent = e.error.message; err.style.display = ''; }
            else { err.style.display = 'none'; }
        });
    }

    // Pay
    window.nbPay = async function() {
        if (!cardReady || !selectedAmount) return;
        var email = emailIn.value.trim();
        var fund = document.getElementById('nb-fund').value;
        var payBtn = document.getElementById('nb-pay-btn');
        var errEl = document.getElementById('nb-card-error');
        payBtn.disabled = true;
        payBtn.textContent = 'Processing...';
        errEl.style.display = 'none';

        try {
            var endpoint = selectedFreq === 'once' ? 'donate' : 'donate/recurring';
            var body = {
                mosque_id: mosqueId,
                mosque_slug: mosqueSlug,
                amount_pence: selectedAmount,
                email: email,
                fund_type: fund,
                currency: 'gbp'
            };
            if (selectedFreq !== 'once') body.interval = selectedFreq;

            var resp = await fetch(API + endpoint, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(body)
            });
            var data = await resp.json();

            if (!data.ok || !data.client_secret) {
                errEl.textContent = data.message || data.error || 'Could not process. Try again.';
                errEl.style.display = '';
                payBtn.disabled = false;
                updatePayBtn();
                return;
            }

            donationId = data.donation_id;

            var result = await stripe.confirmCardPayment(data.client_secret, {
                payment_method: { card: cardElement, billing_details: { email: email } }
            });

            if (result.error) {
                errEl.textContent = result.error.message;
                errEl.style.display = '';
                payBtn.disabled = false;
                updatePayBtn();
                return;
            }

            // Confirm with backend
            fetch(API + 'donate/confirm', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ donation_id: donationId })
            }).catch(function(){});

            nbSetStep(4);

        } catch(e) {
            errEl.textContent = 'Payment failed. Please try again.';
            errEl.style.display = '';
            payBtn.disabled = false;
            updatePayBtn();
        }
    };
})();
</script>
<?php endif; // $_nb_id && $_nb_pk ?>

<!-- Love YourJannah donate button removed — was getting in the way -->
<?php if ( false ) : /* Disabled — re-enable later via profile page */ ?>
        <p style="font-size:13px;color:#666;margin-bottom:16px;">Help us with our running costs.<br>100% of your donation goes to YourJannah.</p>

        <div class="ynj-love-amounts" id="ynj-love-amounts">
            <button onclick="ynjLoveSelect(500,this)">£5</button>
            <button onclick="ynjLoveSelect(1000,this)" class="active">£10</button>
            <button onclick="ynjLoveSelect(2000,this)">£20</button>
            <button onclick="ynjLoveSelect(0,this)">Other</button>
        </div>
        <input type="number" class="ynj-love-custom" id="ynj-love-custom" placeholder="Enter amount (£)" min="1" step="1" oninput="ynjLoveCustom(this.value)">

        <button class="ynj-love-pay" id="ynj-love-pay" onclick="ynjLovePay()">Donate £10</button>
        <p style="font-size:11px;color:#999;margin-top:12px;">Secure payment via Stripe. JazakAllah Khayr 🤲</p>
    </div>
</div>

<script>
(function(){
    var loveAmount = 1000; // pence
    window.ynjLoveSelect = function(pence, btn) {
        document.querySelectorAll('#ynj-love-amounts button').forEach(function(b){b.classList.remove('active');});
        btn.classList.add('active');
        var customInput = document.getElementById('ynj-love-custom');
        if (pence === 0) {
            customInput.style.display = 'block';
            customInput.focus();
            loveAmount = 0;
            document.getElementById('ynj-love-pay').textContent = 'Donate';
            document.getElementById('ynj-love-pay').disabled = true;
        } else {
            customInput.style.display = 'none';
            loveAmount = pence;
            document.getElementById('ynj-love-pay').textContent = 'Donate £' + (pence/100);
            document.getElementById('ynj-love-pay').disabled = false;
        }
    };
    window.ynjLoveCustom = function(val) {
        var pounds = parseFloat(val);
        if (pounds && pounds >= 1) {
            loveAmount = Math.round(pounds * 100);
            document.getElementById('ynj-love-pay').textContent = 'Donate £' + pounds;
            document.getElementById('ynj-love-pay').disabled = false;
        } else {
            document.getElementById('ynj-love-pay').disabled = true;
        }
    };
    window.ynjLovePay = function() {
        if (!loveAmount) return;
        var btn = document.getElementById('ynj-love-pay');
        btn.disabled = true; btn.textContent = 'Redirecting...';
        fetch('/wp-json/ynj/v1/platform-donate', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({amount_pence: loveAmount})
        }).then(function(r){return r.json();}).then(function(data){
            if (data.url) {
                window.location.href = data.url;
            } else {
                btn.disabled = false; btn.textContent = 'Donate £' + (loveAmount/100);
                alert(data.error || 'Something went wrong. Please try again.');
            }
        }).catch(function(){
            btn.disabled = false; btn.textContent = 'Donate £' + (loveAmount/100);
            alert('Network error. Please try again.');
        });
    };

    // Move fab up/down with niyyah bar
    var fab = document.getElementById('ynj-love-fab');
    if (fab) {
        var observer = new MutationObserver(function(){
            var niyyah = document.getElementById('ynj-niyyah-bar');
            if (niyyah && niyyah.classList.contains('open')) {
                fab.style.bottom = '200px';
            } else {
                fab.style.bottom = '';
            }
        });
        var niyyah = document.getElementById('ynj-niyyah-bar');
        if (niyyah) observer.observe(niyyah, {attributes:true, attributeFilter:['class']});
    }
})();
</script>
<?php endif; /* end disabled Love YourJannah block */ ?>

<?php
// ── "YourJannah is sponsored by" — 5 charity logos from admin ──
$_sp_logos = [];
for ( $i = 1; $i <= 5; $i++ ) {
    $url = get_option( "ynj_sponsor_logo_{$i}", '' );
    if ( $url ) $_sp_logos[] = $url;
}
if ( ! empty( $_sp_logos ) ) : ?>
<div style="background:#f8f9fa;border-top:1px solid #e5e7eb;padding:16px 12px;text-align:center;margin-bottom:60px;">
    <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">YourJannah is sponsored by</div>
    <div style="display:flex;align-items:center;justify-content:center;gap:20px;flex-wrap:wrap;">
        <?php foreach ( $_sp_logos as $logo ) : ?>
        <img src="<?php echo esc_url( $logo ); ?>" alt="" style="height:36px;width:auto;object-fit:contain;filter:grayscale(30%);opacity:.8;transition:all .2s;" onmouseover="this.style.opacity='1';this.style.filter='none'" onmouseout="this.style.opacity='.8';this.style.filter='grayscale(30%)'">
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
<?php

/**
 * Default mobile nav when no WP menu is assigned.
 * This ensures the bottom nav works out of the box.
 */
function ynj_default_mobile_nav() {
    $slug = ynj_mosque_slug() ?: 'yourniyyah-masjid';
    $tabs = [
        [ 'label' => 'Home',      'href' => '/',                                     'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>' ],
        [ 'label' => 'Masjid',    'href' => '/mosque/' . $slug . '/hub',               'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-4h6v4"/></svg>' ],
        [ 'label' => 'Ibadah',    'href' => '/profile#ibadah',                       'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c1 0 3-2 3-6V8c0-2-1-3-3-3S9 6 9 8v8c0 4 2 6 3 6z"/><path d="M7 12c-2 0-4 1-4 3s2 3 4 3"/><path d="M17 12c2 0 4 1 4 3s-2 3-4 3"/></svg>' ],
        [ 'label' => 'Fundraise', 'href' => '/mosque/' . $slug . '/fundraising',     'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="none"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" fill="#ef4444"/></svg>' ],
        [ 'label' => 'More',      'href' => '#',                                     'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>', 'is_more' => true ],
    ];

    $current_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );

    foreach ( $tabs as $tab ) {
        if ( ! empty( $tab['is_more'] ) ) {
            echo '<button class="ynj-nav__item" onclick="document.getElementById(\'ynj-more-drawer\').classList.toggle(\'open\')" type="button">' . $tab['icon'] . '<span>' . esc_html( $tab['label'] ) . '</span></button>';
            continue;
        }
        $is_active = ( $tab['href'] === '/' && $current_path === '/' ) ||
                     ( $tab['href'] !== '/' && str_starts_with( $current_path, $tab['href'] ) );
        $class = 'ynj-nav__item' . ( $is_active ? ' ynj-nav__item--active' : '' );
        printf(
            '<a class="%s" href="%s">%s<span>%s</span></a>',
            esc_attr( $class ),
            esc_url( home_url( $tab['href'] ) ),
            $tab['icon'],
            esc_html( $tab['label'] )
        );
    }

    // More drawer
    $more_links = [
        [ 'label' => 'Dashboard',  'href' => '/dashboard',                          'icon' => '🎯' ],
        [ 'label' => 'Classes',    'href' => '/mosque/' . $slug . '/classes',       'icon' => '🎓' ],
        [ 'label' => 'Live',       'href' => '/live',                               'icon' => '📡' ],
        [ 'label' => 'Prayers',    'href' => '/mosque/' . $slug . '/prayers',       'icon' => '🕐' ],
        [ 'label' => 'Booking',    'href' => '/mosque/' . $slug . '/rooms',         'icon' => '🏠' ],
        [ 'label' => 'Masjid Info','href' => '/mosque/' . $slug,                    'icon' => '🕌' ],
        [ 'label' => 'Madrassah',  'href' => '/mosque/' . $slug . '/madrassah',      'icon' => '📚' ],
        [ 'label' => 'Patron',     'href' => '/mosque/' . $slug . '/patron',        'icon' => '🏅' ],
        [ 'label' => 'Donate',     'href' => '/mosque/' . $slug . '/donate',        'icon' => '💝' ],
        [ 'label' => 'Profile',    'href' => '/profile',                            'icon' => '👤' ],
        [ 'label' => 'Login',      'href' => '/login',                              'icon' => '🔑' ],
        [ 'label' => 'Sponsor YourJannah','href' => '/sponsor-yourjannah',           'icon' => '🤲' ],
        [ 'label' => 'Charity Appeals', 'href' => '/appeals',                         'icon' => '📨' ],
    ];
    echo '<div class="ynj-more-drawer" id="ynj-more-drawer" onclick="if(event.target===this)this.classList.remove(\'open\')">';
    echo '<div class="ynj-more-drawer__sheet">';
    echo '<div class="ynj-more-drawer__handle"></div>';
    foreach ( $more_links as $link ) {
        printf(
            '<a class="ynj-more-drawer__link" href="%s"><span>%s</span>%s</a>',
            esc_url( home_url( $link['href'] ) ),
            $link['icon'],
            esc_html( $link['label'] )
        );
    }
    echo '</div></div>';
}

/**
 * Custom walker for mobile nav menu items (adds SVG icons).
 */
class YNJ_Mobile_Nav_Walker extends Walker_Nav_Menu {
    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes = implode( ' ', $item->classes ?? [] );
        $is_active = in_array( 'current-menu-item', $item->classes ?? [], true );
        $class = 'ynj-nav__item' . ( $is_active ? ' ynj-nav__item--active' : '' );

        // Use the description field for SVG icon (set via menu editor)
        $icon = $item->description ?: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>';

        $output .= sprintf(
            '<a class="%s" href="%s">%s<span>%s</span></a>',
            esc_attr( $class ),
            esc_url( $item->url ),
            $icon,
            esc_html( $item->title )
        );
    }
    public function end_el( &$output, $item, $depth = 0, $args = null ) {}
    public function start_lvl( &$output, $depth = 0, $args = null ) {}
    public function end_lvl( &$output, $depth = 0, $args = null ) {}
}
