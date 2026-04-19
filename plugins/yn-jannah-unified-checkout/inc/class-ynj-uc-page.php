<?php
/**
 * Unified Checkout Page — renders at /checkout/
 *
 * Accepts URL params:
 *   ?type=donation&amount=500&mosque_id=1&fund=general&label=Sadaqah
 *   ?type=patron&mosque_id=1&tier=supporter&amount=500
 *   ?type=tip&amount=1000
 *   ?success=1&txn=ynj_xxx  (thank you state)
 *   (empty = show donation options)
 *
 * @package YNJ_Unified_Checkout
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_UC_Page {

    public static function render() {
        $success = sanitize_text_field( $_GET['success'] ?? '' );
        $txn_id  = sanitize_text_field( $_GET['txn'] ?? '' );

        // Get mosque context
        $mosque_id   = absint( $_GET['mosque_id'] ?? 0 );
        $mosque_slug = sanitize_title( $_GET['mosque'] ?? '' );
        if ( ! $mosque_id && $mosque_slug && function_exists( 'ynj_get_mosque' ) ) {
            $m = ynj_get_mosque( $mosque_slug );
            if ( $m ) $mosque_id = (int) $m->id;
        }
        if ( ! $mosque_id && isset( $_COOKIE['ynj_mosque_slug'] ) && function_exists( 'ynj_get_mosque' ) ) {
            $m = ynj_get_mosque( sanitize_title( $_COOKIE['ynj_mosque_slug'] ) );
            if ( $m ) $mosque_id = (int) $m->id;
        }

        $mosque_name = '';
        if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
            global $wpdb;
            $mosque_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
            ) ) ?: '';
        }

        // Item from URL params
        $item_type   = sanitize_text_field( $_GET['type'] ?? '' );
        $amount      = absint( $_GET['amount'] ?? 0 );
        $fund_type   = sanitize_text_field( $_GET['fund'] ?? 'general' );
        $item_label  = sanitize_text_field( $_GET['label'] ?? '' );
        $frequency   = sanitize_text_field( $_GET['frequency'] ?? 'once' );
        $item_id     = absint( $_GET['item_id'] ?? 0 );

        // Stripe public key
        $pk = class_exists( 'YNJ_Stripe' ) ? YNJ_Stripe::public_key() : '';

        // Pre-fill email
        $user_email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
        $user_name  = is_user_logged_in() ? wp_get_current_user()->display_name : '';

        // Fund types for mosque
        $funds = [];
        if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
            global $wpdb;
            $ft = YNJ_DB::table( 'mosque_funds' );
            $funds = $wpdb->get_results( $wpdb->prepare(
                "SELECT slug, label FROM $ft WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
                $mosque_id
            ) ) ?: [];
        }

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $mosque_name ? 'Checkout — ' . $mosque_name : 'Checkout — YourJannah' ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://js.stripe.com/v3/"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',system-ui,sans-serif;background:#f8f9fa;color:#1a1a1a;min-height:100vh;}
.uc-wrap{max-width:480px;margin:0 auto;padding:20px 16px 40px;}
.uc-header{text-align:center;margin-bottom:24px;}
.uc-header img{height:32px;margin-bottom:8px;}
.uc-header h1{font-size:20px;font-weight:800;}
.uc-header p{font-size:13px;color:#666;margin-top:4px;}
.uc-card{background:#fff;border-radius:16px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);margin-bottom:16px;}
.uc-label{font-size:12px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.uc-amounts{display:flex;gap:8px;margin-bottom:12px;}
.uc-amt{flex:1;padding:14px 0;border:2px solid #e5e7eb;border-radius:12px;background:#fff;font-size:18px;font-weight:800;cursor:pointer;font-family:inherit;text-align:center;transition:all .15s;}
.uc-amt:hover{border-color:#287e61;background:#f0fdf4;}
.uc-amt--active{border-color:#287e61!important;background:#287e61!important;color:#fff!important;}
.uc-custom{width:100%;padding:14px;border:2px solid #e5e7eb;border-radius:12px;font-size:16px;font-weight:600;font-family:inherit;text-align:center;}
.uc-custom:focus{outline:none;border-color:#287e61;}
.uc-freq{display:flex;gap:4px;background:#f3f4f6;border-radius:10px;padding:3px;margin-bottom:12px;}
.uc-freq-btn{flex:1;padding:10px;border:none;border-radius:8px;background:transparent;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;color:#666;}
.uc-freq-btn--active{background:#fff;color:#1a1a1a;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
.uc-input{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:10px;font-size:14px;font-family:inherit;margin-bottom:8px;}
.uc-input:focus{outline:none;border-color:#287e61;}
.uc-fund{width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:10px;font-size:14px;font-family:inherit;margin-bottom:12px;appearance:none;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23666' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 12px center;padding-right:32px;}
.uc-tip{display:flex;align-items:center;gap:12px;padding:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;margin-bottom:12px;}
.uc-tip input[type=range]{flex:1;accent-color:#287e61;}
.uc-tip-val{font-size:14px;font-weight:700;min-width:40px;text-align:right;}
.uc-stripe{min-height:44px;padding:12px;border:1px solid #ddd;border-radius:10px;margin-bottom:12px;}
.uc-summary{border-top:1px solid #eee;padding-top:12px;margin-top:4px;}
.uc-summary-row{display:flex;justify-content:space-between;font-size:13px;padding:4px 0;color:#666;}
.uc-summary-total{display:flex;justify-content:space-between;font-size:18px;font-weight:800;padding:8px 0 0;border-top:1px solid #eee;margin-top:4px;}
.uc-pay{width:100%;padding:16px;background:#287e61;color:#fff;border:none;border-radius:14px;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit;margin-top:12px;transition:all .15s;}
.uc-pay:hover{background:#1a5c43;}
.uc-pay:disabled{opacity:.5;cursor:not-allowed;}
.uc-error{color:#dc2626;font-size:13px;margin-bottom:8px;display:none;}
.uc-secure{text-align:center;font-size:11px;color:#999;margin-top:12px;}
.uc-success{text-align:center;padding:40px 20px;}
.uc-success h2{font-size:24px;margin:12px 0 4px;}
.uc-empty-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.uc-empty-item{display:flex;flex-direction:column;align-items:center;gap:6px;padding:20px 12px;background:#fff;border:2px solid #e5e7eb;border-radius:14px;cursor:pointer;transition:all .15s;text-decoration:none;color:#1a1a1a;}
.uc-empty-item:hover{border-color:#287e61;background:#f0fdf4;}
.uc-empty-item span{font-size:28px;}
.uc-empty-item strong{font-size:13px;font-weight:700;}
.uc-back{display:inline-flex;align-items:center;gap:6px;color:#287e61;font-size:13px;font-weight:600;text-decoration:none;margin-bottom:16px;}
</style>
</head>
<body>
<div class="uc-wrap">

    <div class="uc-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/logo2.png' ); ?>" alt="YourJannah"></a>
        <?php if ( $mosque_name ) : ?>
        <h1><?php echo esc_html( $mosque_name ); ?></h1>
        <?php else : ?>
        <h1>YourJannah Checkout</h1>
        <?php endif; ?>
        <p>Secure payment via Stripe</p>
    </div>

    <?php if ( $success && $txn_id ) : ?>
    <!-- ═══ SUCCESS ═══ -->
    <div class="uc-card uc-success">
        <div style="font-size:48px;">&#x2705;</div>
        <h2>JazakAllah Khair!</h2>
        <p style="color:#666;margin-bottom:20px;">Your payment has been processed successfully.</p>
        <a href="<?php echo esc_url( home_url( $mosque_id ? '/mosque/' . ( $mosque_slug ?: '' ) : '/' ) ); ?>" style="display:inline-block;padding:12px 24px;background:#287e61;color:#fff;border-radius:12px;font-weight:700;text-decoration:none;">Back to Masjid</a>
    </div>

    <?php elseif ( ! $item_type ) : ?>
    <!-- ═══ EMPTY CHECKOUT — Show donation options ═══ -->
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="uc-back">&larr; Back</a>
    <div class="uc-card">
        <div class="uc-label">What would you like to do?</div>
        <div class="uc-empty-grid">
            <a href="?type=donation&mosque_id=<?php echo $mosque_id; ?>&label=Donation" class="uc-empty-item">
                <span>💝</span><strong>Donate</strong>
            </a>
            <a href="?type=sadaqah&mosque_id=<?php echo $mosque_id; ?>&label=Purify+Your+Rizq&fund=sadaqah" class="uc-empty-item">
                <span>💰</span><strong>Sadaqah</strong>
            </a>
            <a href="?type=patron&mosque_id=<?php echo $mosque_id; ?>&label=Patron+Membership" class="uc-empty-item">
                <span>🏅</span><strong>Become Patron</strong>
            </a>
            <a href="?type=tip&label=Support+YourJannah" class="uc-empty-item">
                <span>🤲</span><strong>Support YJ</strong>
            </a>
            <a href="?type=sponsor&mosque_id=<?php echo $mosque_id; ?>&label=Business+Sponsorship&frequency=monthly" class="uc-empty-item">
                <span>⭐</span><strong>Sponsor Masjid</strong>
            </a>
            <a href="?type=donation&mosque_id=<?php echo $mosque_id; ?>&label=Emergency+Fund&fund=emergency" class="uc-empty-item">
                <span>🚨</span><strong>Emergency</strong>
            </a>
        </div>
    </div>

    <?php else : ?>
    <!-- ═══ CHECKOUT FORM ═══ -->
    <a href="<?php echo esc_url( home_url( $mosque_id ? '/mosque/' . ( $mosque_slug ?: '' ) : '/' ) ); ?>" class="uc-back">&larr; Back</a>

    <div class="uc-card">
        <div class="uc-label"><?php echo esc_html( $item_label ?: ucfirst( str_replace( '_', ' ', $item_type ) ) ); ?></div>

        <!-- Frequency -->
        <div class="uc-freq" id="uc-freq">
            <button type="button" class="uc-freq-btn<?php echo $frequency === 'once' ? ' uc-freq-btn--active' : ''; ?>" data-freq="once">One-off</button>
            <button type="button" class="uc-freq-btn<?php echo $frequency === 'weekly' ? ' uc-freq-btn--active' : ''; ?>" data-freq="weekly">Weekly</button>
            <button type="button" class="uc-freq-btn<?php echo $frequency === 'monthly' ? ' uc-freq-btn--active' : ''; ?>" data-freq="monthly">Monthly</button>
        </div>

        <!-- Amount -->
        <div class="uc-amounts" id="uc-amounts">
            <?php foreach ( [ 500, 1000, 2000, 5000 ] as $a ) : ?>
            <button type="button" class="uc-amt<?php echo $amount === $a ? ' uc-amt--active' : ''; ?>" data-amount="<?php echo $a; ?>">&pound;<?php echo $a / 100; ?></button>
            <?php endforeach; ?>
        </div>
        <input type="number" class="uc-custom" id="uc-custom" placeholder="Other amount (£)" min="1" step="1" <?php echo $amount && ! in_array( $amount, [500,1000,2000,5000] ) ? 'value="' . ( $amount / 100 ) . '"' : ''; ?>>

        <?php if ( ! empty( $funds ) && $item_type !== 'tip' ) : ?>
        <!-- Fund type -->
        <select class="uc-fund" id="uc-fund">
            <?php foreach ( $funds as $f ) : ?>
            <option value="<?php echo esc_attr( $f->slug ); ?>" <?php selected( $fund_type, $f->slug ); ?>><?php echo esc_html( $f->label ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <div class="uc-card">
        <div class="uc-label">Your details</div>
        <input type="email" class="uc-input" id="uc-email" placeholder="Email address" value="<?php echo esc_attr( $user_email ); ?>" required>
        <input type="text" class="uc-input" id="uc-name" placeholder="Full name (optional)" value="<?php echo esc_attr( $user_name ); ?>">
    </div>

    <div class="uc-card">
        <div class="uc-label">Support YourJannah (optional)</div>
        <div class="uc-tip">
            <span style="font-size:13px;">🤲 Tip</span>
            <input type="range" id="uc-tip-range" min="0" max="20" value="5" step="1">
            <span class="uc-tip-val" id="uc-tip-val">5%</span>
        </div>
    </div>

    <div class="uc-card" id="uc-payment-card" style="display:none;">
        <div class="uc-label">Payment</div>
        <div class="uc-stripe" id="uc-payment-element"></div>
        <div class="uc-error" id="uc-error"></div>
        <div class="uc-summary">
            <div class="uc-summary-row"><span>Donation</span><span id="uc-sum-amount">£0</span></div>
            <div class="uc-summary-row"><span>YourJannah tip</span><span id="uc-sum-tip">£0</span></div>
            <div class="uc-summary-total"><span>Total</span><span id="uc-sum-total">£0</span></div>
        </div>
        <button type="button" class="uc-pay" id="uc-pay-btn" disabled>🤲 Pay Now</button>
        <div class="uc-secure">🔒 Secured by Stripe. Your card details never touch our servers.</div>
    </div>

    <!-- Initial "Continue to Payment" before Stripe loads -->
    <button type="button" class="uc-pay" id="uc-continue-btn" style="margin-top:-4px;">Continue to Payment &rarr;</button>
    <?php endif; ?>
</div>

<?php if ( $item_type && ! $success ) : ?>
<script>
(function(){
    var API = '<?php echo esc_url_raw( rest_url( 'ynj/v1/' ) ); ?>';
    var PK  = '<?php echo esc_js( $pk ); ?>';
    var MOSQUE_ID = <?php echo (int) $mosque_id; ?>;
    var ITEM_TYPE = '<?php echo esc_js( $item_type ); ?>';
    var ITEM_LABEL = '<?php echo esc_js( $item_label ); ?>';
    var ITEM_ID = <?php echo (int) $item_id; ?>;
    var INIT_AMOUNT = <?php echo (int) $amount; ?>;

    var selectedAmount = INIT_AMOUNT;
    var selectedFreq = '<?php echo esc_js( $frequency ); ?>';
    var tipPercent = 5;
    var stripe, elements, paymentElement;
    var txnId = '';

    // Amount buttons
    document.querySelectorAll('.uc-amt').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.uc-amt').forEach(function(b){b.classList.remove('uc-amt--active');});
            btn.classList.add('uc-amt--active');
            selectedAmount = parseInt(btn.dataset.amount);
            document.getElementById('uc-custom').value = '';
            updateSummary();
        });
    });

    // Custom amount
    document.getElementById('uc-custom').addEventListener('input', function(){
        var v = parseFloat(this.value);
        if (v > 0) {
            document.querySelectorAll('.uc-amt').forEach(function(b){b.classList.remove('uc-amt--active');});
            selectedAmount = Math.round(v * 100);
        } else {
            selectedAmount = 0;
        }
        updateSummary();
    });

    // Frequency buttons
    document.querySelectorAll('.uc-freq-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.uc-freq-btn').forEach(function(b){b.classList.remove('uc-freq-btn--active');});
            btn.classList.add('uc-freq-btn--active');
            selectedFreq = btn.dataset.freq;
        });
    });

    // Tip slider
    document.getElementById('uc-tip-range').addEventListener('input', function(){
        tipPercent = parseInt(this.value);
        document.getElementById('uc-tip-val').textContent = tipPercent + '%';
        updateSummary();
    });

    function getTipPence() { return Math.round(selectedAmount * tipPercent / 100); }
    function getTotalPence() { return selectedAmount + getTipPence(); }

    function updateSummary() {
        document.getElementById('uc-sum-amount').textContent = '£' + (selectedAmount / 100).toFixed(2);
        document.getElementById('uc-sum-tip').textContent = '£' + (getTipPence() / 100).toFixed(2);
        document.getElementById('uc-sum-total').textContent = '£' + (getTotalPence() / 100).toFixed(2);
        var payBtn = document.getElementById('uc-pay-btn');
        if (payBtn) {
            var freqLabel = selectedFreq === 'once' ? '' : '/' + (selectedFreq === 'weekly' ? 'week' : 'month');
            payBtn.textContent = '🤲 Pay £' + (getTotalPence() / 100).toFixed(2) + freqLabel;
        }
    }

    // Continue to Payment — creates intent + mounts Stripe Elements
    document.getElementById('uc-continue-btn').addEventListener('click', function(){
        var email = document.getElementById('uc-email').value.trim();
        if (!email || !email.includes('@')) {
            document.getElementById('uc-email').style.borderColor = '#dc2626';
            document.getElementById('uc-email').focus();
            return;
        }
        if (selectedAmount < 100) { alert('Minimum amount is £1'); return; }

        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Setting up payment...';

        var fundEl = document.getElementById('uc-fund');
        var payload = {
            email: email,
            name: document.getElementById('uc-name').value.trim(),
            amount_pence: selectedAmount,
            tip_pence: getTipPence(),
            mosque_id: MOSQUE_ID,
            item_type: ITEM_TYPE,
            item_id: ITEM_ID,
            item_label: ITEM_LABEL,
            fund_type: fundEl ? fundEl.value : 'general',
            frequency: selectedFreq,
            source: 'checkout_page'
        };

        fetch(API + 'unified-checkout/create-intent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                btn.disabled = false;
                btn.textContent = 'Continue to Payment →';
                alert(data.error || 'Failed to set up payment');
                return;
            }

            txnId = data.transaction_id;

            if (data.mode === 'redirect') {
                // Recurring: redirect to Stripe Checkout
                window.location.href = data.url;
                return;
            }

            // One-off: mount Stripe Elements
            btn.style.display = 'none';
            document.getElementById('uc-payment-card').style.display = '';
            updateSummary();

            stripe = Stripe(PK);
            elements = stripe.elements({ clientSecret: data.client_secret, appearance: {
                theme: 'stripe',
                variables: { colorPrimary: '#287e61', fontFamily: 'Inter, system-ui, sans-serif', borderRadius: '10px' }
            }});
            paymentElement = elements.create('payment', { layout: 'tabs' });
            paymentElement.mount('#uc-payment-element');
            paymentElement.on('change', function(e) {
                document.getElementById('uc-pay-btn').disabled = !e.complete;
                var err = document.getElementById('uc-error');
                if (e.error) { err.textContent = e.error.message; err.style.display = ''; }
                else { err.style.display = 'none'; }
            });
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Continue to Payment →';
            alert('Network error. Please try again.');
        });
    });

    // Pay button — confirm payment
    document.getElementById('uc-pay-btn').addEventListener('click', function(){
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Processing...';
        var errEl = document.getElementById('uc-error');
        errEl.style.display = 'none';

        stripe.confirmPayment({
            elements: elements,
            confirmParams: { return_url: window.location.origin + '/checkout/?success=1&txn=' + txnId },
            redirect: 'if_required'
        }).then(function(result) {
            if (result.error) {
                errEl.textContent = result.error.message;
                errEl.style.display = '';
                btn.disabled = false;
                updateSummary();
                return;
            }

            // Payment succeeded — confirm with backend
            btn.textContent = 'Confirming...';
            fetch(API + 'unified-checkout/confirm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transaction_id: txnId, payment_intent_id: result.paymentIntent ? result.paymentIntent.id : '' })
            }).then(function() {
                window.location.href = '/checkout/?success=1&txn=' + txnId + '&mosque_id=' + MOSQUE_ID;
            }).catch(function() {
                window.location.href = '/checkout/?success=1&txn=' + txnId + '&mosque_id=' + MOSQUE_ID;
            });
        });
    });

    // Init summary
    updateSummary();
})();
</script>
<?php endif; ?>
</body>
</html>
        <?php
    }
}
