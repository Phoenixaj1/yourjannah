<?php
/**
 * Unified Checkout Page — renders at /checkout/
 *
 * v2: Multi-item basket checkout. All rendering driven by checkout.js reading from ynjBasket.
 *
 * Accepts URL params (backwards compat — auto-added to basket by JS):
 *   ?type=donation&amount=500&mosque_id=1&fund=general&label=Sadaqah
 *   ?success=1&txn=ynj_xxx  (thank you state)
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

        // Stripe public key
        $pk = class_exists( 'YNJ_Stripe' ) ? YNJ_Stripe::public_key() : '';

        // Pre-fill user data
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

        // Encode funds for JS
        $funds_json = [];
        foreach ( $funds as $f ) {
            $funds_json[] = [ 'slug' => $f->slug, 'label' => $f->label ];
        }

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $mosque_name ? 'Checkout — ' . $mosque_name : 'Checkout — YourJannah' ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( YNJ_UC_URL . 'assets/css/checkout.css?v=' . YNJ_UC_VERSION ); ?>">
<script src="https://js.stripe.com/v3/"></script>
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

    <?php else : ?>
    <!-- ═══ BASKET CHECKOUT ═══ -->
    <a href="<?php echo esc_url( home_url( $mosque_id ? '/mosque/' . ( $mosque_slug ?: '' ) : '/' ) ); ?>" class="uc-back">&larr; Back</a>

    <!-- Cart items (JS-rendered from ynjBasket) -->
    <div id="uc-cart-items"></div>

    <!-- Split mode notice -->
    <div class="uc-split-notice" id="uc-split-notice" style="display:none;">
        <strong>Mixed cart:</strong> Your one-off items will be charged first, then you'll be redirected to set up your recurring payments.
    </div>

    <!-- Your details -->
    <div class="uc-card" id="uc-details-card" style="display:none;">
        <div class="uc-label">Your details</div>
        <input type="email" class="uc-input" id="uc-email" placeholder="Email address" value="<?php echo esc_attr( $user_email ); ?>" required>
        <input type="text" class="uc-input" id="uc-name" placeholder="Your name" value="<?php echo esc_attr( $user_name ); ?>">
    </div>

    <!-- Tip -->
    <div class="uc-card" id="uc-tip-card" style="display:none;">
        <div class="uc-label">Support YourJannah (optional)</div>
        <div class="uc-tip">
            <span style="font-size:13px;">&#x1F932; Tip</span>
            <input type="range" id="uc-tip-range" min="0" max="20" value="5" step="1">
            <span class="uc-tip-val" id="uc-tip-val">5%</span>
        </div>
    </div>

    <!-- Order summary -->
    <div class="uc-card" id="uc-summary-card" style="display:none;">
        <div class="uc-label">Order summary</div>
        <div id="uc-summary-lines"></div>
    </div>

    <!-- Payment (hidden until "Continue") -->
    <div class="uc-card" id="uc-payment-card" style="display:none;">
        <div class="uc-label">Payment</div>
        <div class="uc-stripe" id="uc-payment-element"></div>
        <div class="uc-error" id="uc-error"></div>
        <button type="button" class="uc-pay" id="uc-pay-btn" disabled>&#x1F932; Pay Now</button>
        <div class="uc-secure">&#x1F512; Secured by Stripe. Your card details never touch our servers.</div>
    </div>

    <button type="button" class="uc-pay" id="uc-continue-btn" style="display:none;margin-top:-4px;">Continue to Payment &rarr;</button>
    <?php endif; ?>
</div>

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
