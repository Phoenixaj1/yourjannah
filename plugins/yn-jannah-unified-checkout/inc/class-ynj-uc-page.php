<?php
/**
 * Unified Checkout Page — renders at /checkout/
 *
 * v3: Multi-step checkout (Review → Support → Payment) with 2-column layout.
 * Modeled after YourNiyyah's checkout UX.
 *
 * @package YNJ_Unified_Checkout
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_UC_Page {

    public static function render() {
        $success    = sanitize_text_field( $_GET['success'] ?? '' );
        $txn_id     = sanitize_text_field( $_GET['txn'] ?? '' );
        $cancelled  = sanitize_text_field( $_GET['cancelled'] ?? '' );

        // ── Mosque context ──
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

        $pk         = class_exists( 'YNJ_Stripe' ) ? YNJ_Stripe::public_key() : '';
        $user_email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
        $user_name  = is_user_logged_in() ? wp_get_current_user()->display_name : '';

        // Fund types
        $funds = [];
        if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
            global $wpdb;
            $ft = YNJ_DB::table( 'mosque_funds' );
            $funds = $wpdb->get_results( $wpdb->prepare(
                "SELECT slug, label FROM $ft WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
                $mosque_id
            ) ) ?: [];
        }
        $funds_json = array_map( function( $f ) { return [ 'slug' => $f->slug, 'label' => $f->label ]; }, $funds );

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $mosque_name ? 'Checkout — ' . $mosque_name : 'Checkout — YourJannah' ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( YNJ_UC_URL . 'assets/css/checkout.css?v=' . YNJ_UC_VERSION ); ?>">
<script src="https://js.stripe.com/v3/"></script>
</head>
<body>
<div class="uc-wrap">

    <!-- ═══ HEADER ═══ -->
    <div class="uc-header">
        <h1>Complete Your Checkout</h1>
        <p>Secure checkout &mdash; your donation goes directly to the masjid</p>
    </div>

    <?php if ( $success && $txn_id ) : ?>
    <!-- ═══════════════════════════════════════
         SUCCESS
         ═══════════════════════════════════════ -->
    <div class="uc-success">
        <div class="uc-success__icon">&#x2705;</div>
        <h2>JazakAllah Khair!</h2>
        <p>Your payment has been processed successfully.<br>Transaction: <code style="font-size:12px;color:#999;"><?php echo esc_html( $txn_id ); ?></code></p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="uc-success__btn">Back to YourJannah</a>
    </div>

    <?php else : ?>
    <!-- ═══════════════════════════════════════
         EMPTY STATE (hidden by JS if basket has items)
         ═══════════════════════════════════════ -->
    <div id="uc-empty-state">
        <div class="uc-empty">
            <div class="uc-empty__icon">&#x1F6D2;</div>
            <div class="uc-empty__text">Your cart is empty</div>
            <div class="uc-empty__sub">Browse your masjid to add donations, events, or services.</div>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="uc-empty__btn">&larr; Back to YourJannah</a>
        </div>
    </div>

    <!-- ═══════════════════════════════════════
         CHECKOUT (hidden by JS if basket empty)
         ═══════════════════════════════════════ -->
    <div id="uc-checkout-main" style="display:none;">
    <div class="uc-container">

        <!-- ════════════════════════════════════
             LEFT COLUMN — Steps
             ════════════════════════════════════ -->
        <div class="uc-main">

            <!-- ──── STEP 1: Review ──── -->
            <div class="uc-step uc-step--active" data-step="1">

                <div class="uc-section">
                    <h3>Contact Information</h3>
                    <div class="uc-fields">
                        <div class="uc-field">
                            <label>Full Name</label>
                            <input type="text" class="uc-input" id="uc-name" placeholder="Your full name" value="<?php echo esc_attr( $user_name ); ?>">
                        </div>
                        <div class="uc-field-row">
                            <div class="uc-field">
                                <label>Email Address <span class="uc-req">*</span></label>
                                <input type="email" class="uc-input" id="uc-email" placeholder="you@example.com" value="<?php echo esc_attr( $user_email ); ?>" required>
                            </div>
                            <div class="uc-field">
                                <label>Phone (optional)</label>
                                <input type="tel" class="uc-input" id="uc-phone" placeholder="+44 7XXX XXX XXX">
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ( ! empty( $funds ) ) : ?>
                <div class="uc-section" id="uc-fund-select" style="display:none;">
                    <h3>Fund Allocation</h3>
                    <select class="uc-input" id="uc-fund">
                        <?php foreach ( $funds as $f ) : ?>
                        <option value="<?php echo esc_attr( $f->slug ); ?>"><?php echo esc_html( $f->label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="uc-section">
                    <h3>Your Items</h3>
                    <div id="uc-step1-items"></div>
                </div>

                <button type="button" class="uc-step-btn" id="uc-s1-continue">Continue &rarr;</button>

            </div><!-- /step 1 -->

            <!-- ──── STEP 2: Support YourJannah ──── -->
            <div class="uc-step" data-step="2">

                <div class="uc-section--pink">
                    <h3>&#x1F49C; Platform Contribution</h3>
                    <p>100% of your donation goes to the masjid. Choose how much to cover our running costs.</p>
                    <div class="uc-tiers">
                        <button type="button" class="uc-tier uc-tier--active" data-tip="30">
                            <span class="uc-tier__amount">&pound;0.30</span>
                            <span class="uc-tier__desc">Card processing only</span>
                            <span class="uc-tier__badge">Minimum</span>
                        </button>
                        <button type="button" class="uc-tier" data-tip="500">
                            <span class="uc-tier__amount">&pound;5</span>
                            <span class="uc-tier__desc">Fees + tech costs</span>
                        </button>
                        <button type="button" class="uc-tier" data-tip="1000">
                            <span class="uc-tier__amount">&pound;10</span>
                            <span class="uc-tier__desc">Fees, tech + admin</span>
                        </button>
                        <button type="button" class="uc-tier" data-tip="2000">
                            <span class="uc-tier__amount">&pound;20</span>
                            <span class="uc-tier__desc">Fees, tech, admin + ops</span>
                        </button>
                    </div>
                </div>

                <div class="uc-section--teal">
                    <h3>&#x1F49A; Fund Our Mission</h3>
                    <p>YourJannah connects Muslim communities. Help us grow and serve more masjids across the UK.</p>
                    <div class="uc-tiers">
                        <button type="button" class="uc-tier" data-cause="200">
                            <span class="uc-tier__amount">&pound;2.00</span>
                        </button>
                        <button type="button" class="uc-tier" data-cause="500">
                            <span class="uc-tier__amount">&pound;5.00</span>
                        </button>
                        <button type="button" class="uc-tier" data-cause="2000">
                            <span class="uc-tier__amount">&pound;20.00</span>
                        </button>
                        <button type="button" class="uc-tier" data-cause="0">
                            <span class="uc-tier__amount">Skip</span>
                            <span class="uc-tier__desc">for now</span>
                        </button>
                    </div>
                </div>

                <button type="button" class="uc-step-btn" id="uc-s2-continue">Continue to Payment &rarr;</button>

            </div><!-- /step 2 -->

            <!-- ──── STEP 3: Payment ──── -->
            <div class="uc-step" data-step="3">

                <div class="uc-section">
                    <div class="uc-giftaid">
                        <input type="checkbox" id="uc-giftaid">
                        <div class="uc-giftaid__text">
                            <strong>I am a UK taxpayer &mdash; claim Gift Aid</strong>
                            <span>Gift Aid increases your donation by 25% at no extra cost to you.</span>
                        </div>
                    </div>
                </div>

                <!-- Processing spinner (shown while creating intent) -->
                <div class="uc-processing" id="uc-processing">
                    <span class="uc-spinner"></span>
                    <span>Setting up payment...</span>
                </div>

                <!-- Payment section (shown after intent created) -->
                <div id="uc-payment-section" style="display:none;">
                    <div class="uc-section">
                        <h3>Payment Method</h3>
                        <div class="uc-pay-method">
                            <span class="uc-pay-method__icon">&#x1F4B3;</span>
                            <div class="uc-pay-method__text">
                                <strong>Card / Apple Pay / Google Pay</strong>
                                <span>Instant &mdash; processed securely by Stripe</span>
                            </div>
                        </div>
                        <div class="uc-stripe" id="uc-payment-element"></div>
                        <div class="uc-error" id="uc-error"></div>
                    </div>

                    <button type="button" class="uc-submit" id="uc-submit-btn" disabled>Complete Payment</button>
                    <div class="uc-secure">&#x1F512; 256-bit SSL encrypted. Payments processed by Stripe.</div>
                </div>

            </div><!-- /step 3 -->

        </div><!-- /uc-main -->

        <!-- ════════════════════════════════════
             RIGHT COLUMN — Checkout Summary (sticky)
             ════════════════════════════════════ -->
        <aside class="uc-sidebar">

            <div class="uc-summary-card">
                <h3>Checkout Summary</h3>

                <div class="uc-summary__label">&#x1F4CB; DUE TODAY</div>
                <div class="uc-summary__items" id="uc-summary-items">
                    <!-- JS renders items here -->
                </div>

                <hr class="uc-summary__divider">

                <div class="uc-summary__line uc-summary__line--bold">
                    <span>Due today</span>
                    <span id="uc-sum-due">&pound;0.00</span>
                </div>

                <div class="uc-summary__line uc-summary__step2" style="display:none;">
                    <span>Admin contribution</span>
                    <span id="uc-sum-tip">&pound;0.30</span>
                </div>
                <div class="uc-summary__line--sub uc-summary__step2" style="display:none;">
                    &nbsp;&nbsp;&#x21B3; Covers card fees &amp; platform costs
                </div>
                <div class="uc-summary__line uc-summary__step2" style="display:none;">
                    <span>Fund our mission</span>
                    <span id="uc-sum-cause">&pound;0.00</span>
                </div>

                <div class="uc-summary__total">
                    <span>Total</span>
                    <span id="uc-sum-total">&pound;0.00</span>
                </div>
            </div>

        </aside>

    </div><!-- /uc-container -->
    </div><!-- /uc-checkout-main -->

    <!-- ════════════════════════════════════
         STEP PROGRESS BAR (fixed bottom)
         ════════════════════════════════════ -->
    <div class="uc-steps" id="uc-steps-bar">
        <button type="button" class="uc-steps__back" id="uc-steps-back" style="display:none;">&larr; Back</button>
        <div class="uc-steps__item uc-steps__item--active" data-step="1">
            <span class="uc-steps__num">1</span>
            <span>Review</span>
        </div>
        <div class="uc-steps__item" data-step="2">
            <span class="uc-steps__num">2</span>
            <span>Support</span>
        </div>
        <div class="uc-steps__item" data-step="3">
            <span class="uc-steps__num">3</span>
            <span>Payment</span>
        </div>
    </div>

    <?php endif; ?>

</div><!-- /uc-wrap -->

<?php if ( ! $success ) : ?>
<script src="<?php echo esc_url( YNJ_UC_URL . 'assets/js/ynj-basket.js?v=' . YNJ_UC_VERSION ); ?>"></script>
<script>
window.ynjCheckoutData = {
    apiUrl:     <?php echo wp_json_encode( esc_url_raw( rest_url( 'ynj/v1/' ) ) ); ?>,
    stripePk:   <?php echo wp_json_encode( $pk ); ?>,
    mosqueId:   <?php echo (int) $mosque_id; ?>,
    mosqueName: <?php echo wp_json_encode( $mosque_name ); ?>,
    userEmail:  <?php echo wp_json_encode( $user_email ); ?>,
    userName:   <?php echo wp_json_encode( $user_name ); ?>,
    homeUrl:    <?php echo wp_json_encode( home_url( '/' ) ); ?>,
    funds:      <?php echo wp_json_encode( $funds_json ); ?>
};
</script>
<script src="<?php echo esc_url( YNJ_UC_URL . 'assets/js/checkout.js?v=' . YNJ_UC_VERSION ); ?>"></script>
<?php endif; ?>
</body>
</html>
        <?php
    }
}
