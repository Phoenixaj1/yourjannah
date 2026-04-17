<?php
/**
 * Template: Charity Appeals Portal
 *
 * Public page for charities to submit appeal requests to mosques.
 * PHP-first: POST form handling, Stripe checkout via cURL, no JS API calls.
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ──────────────────────────────────────────────
 * 1. Handle form POST — validate, insert, redirect to Stripe
 * ────────────────────────────────────────────── */
$form_errors  = [];
$form_success = false;
$appeal_id    = 0;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['ynj_appeal_nonce'] ) ) {

    if ( ! wp_verify_nonce( $_POST['ynj_appeal_nonce'], 'ynj_submit_appeal' ) ) {
        $form_errors[] = 'Security check failed. Please try again.';
    } else {

        // Sanitise inputs
        $charity_name   = sanitize_text_field( $_POST['charity_name']   ?? '' );
        $charity_email  = sanitize_email( $_POST['charity_email']       ?? '' );
        $charity_phone  = sanitize_text_field( $_POST['charity_phone']  ?? '' );
        $charity_website = esc_url_raw( $_POST['charity_website']       ?? '' );
        $charity_reg    = sanitize_text_field( $_POST['charity_reg_number'] ?? '' );
        $cause_title    = sanitize_text_field( $_POST['cause_title']    ?? '' );
        $cause_desc     = sanitize_textarea_field( $_POST['cause_description'] ?? '' );
        $cause_category = sanitize_text_field( $_POST['cause_category'] ?? 'general' );
        $appeal_type    = sanitize_text_field( $_POST['appeal_type']    ?? 'in_person' );
        $preferred_dates = sanitize_textarea_field( $_POST['preferred_dates'] ?? '' );
        $budget_note    = sanitize_text_field( $_POST['budget_note']    ?? '' );

        // Validate required fields
        if ( empty( $charity_name ) )  $form_errors[] = 'Charity name is required.';
        if ( empty( $charity_email ) || ! is_email( $charity_email ) ) $form_errors[] = 'A valid email address is required.';
        if ( empty( $charity_phone ) ) $form_errors[] = 'Phone number is required.';
        if ( empty( $charity_reg ) )   $form_errors[] = 'Charity registration number is required.';
        if ( empty( $cause_title ) )   $form_errors[] = 'Cause title is required.';
        if ( empty( $cause_desc ) )    $form_errors[] = 'Cause description is required.';

        $allowed_categories = [ 'education', 'health', 'poverty', 'emergency', 'environment', 'community', 'general' ];
        if ( ! in_array( $cause_category, $allowed_categories, true ) ) {
            $cause_category = 'general';
        }

        $allowed_types = [ 'in_person', 'recorded', 'broadcast' ];
        if ( ! in_array( $appeal_type, $allowed_types, true ) ) {
            $appeal_type = 'in_person';
        }

        // Handle logo upload
        $logo_url = '';
        if ( ! empty( $_FILES['charity_logo']['name'] ) && empty( $_FILES['charity_logo']['error'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $allowed = [ 'image/jpeg', 'image/png', 'image/webp' ];
            if ( ! in_array( $_FILES['charity_logo']['type'], $allowed, true ) ) {
                $form_errors[] = 'Logo must be a JPG, PNG, or WebP file.';
            } elseif ( $_FILES['charity_logo']['size'] > 2 * 1024 * 1024 ) {
                $form_errors[] = 'Logo file must be under 2MB.';
            } else {
                $upload = wp_handle_upload( $_FILES['charity_logo'], [ 'test_form' => false ] );
                if ( isset( $upload['url'] ) ) {
                    $logo_url = $upload['url'];
                } elseif ( isset( $upload['error'] ) ) {
                    $form_errors[] = 'Logo upload failed: ' . $upload['error'];
                }
            }
        }

        // Insert into DB if no errors
        if ( empty( $form_errors ) ) {
            global $wpdb;
            $table = YNJ_DB::table( 'appeal_requests' );

            $inserted = $wpdb->insert( $table, [
                'charity_name'       => $charity_name,
                'charity_email'      => $charity_email,
                'charity_phone'      => $charity_phone,
                'charity_website'    => $charity_website,
                'charity_logo_url'   => $logo_url,
                'charity_reg_number' => $charity_reg,
                'cause_title'        => $cause_title,
                'cause_description'  => $cause_desc,
                'cause_category'     => $cause_category,
                'appeal_type'        => $appeal_type,
                'preferred_dates'    => $preferred_dates,
                'budget_note'        => $budget_note,
                'status'             => 'pending_payment',
                'created_at'         => current_time( 'mysql' ),
            ] );

            if ( ! $inserted ) {
                $form_errors[] = 'Could not save your appeal. Please try again.';
            } else {
                $appeal_id = $wpdb->insert_id;

                // Create Stripe Checkout Session for the platform fee
                $stripe_key = YNJ_Stripe::secret_key();
                if ( empty( $stripe_key ) ) {
                    $form_errors[] = 'Payment system is not configured. Please contact support.';
                } else {
                    $success_url = home_url( '/appeals/?payment=success&appeal_id=' . $appeal_id . '&session_id={CHECKOUT_SESSION_ID}' );
                    $cancel_url  = home_url( '/appeals/?payment=cancelled&appeal_id=' . $appeal_id );

                    $stripe_data = http_build_query( [
                        'mode'                        => 'payment',
                        'currency'                    => 'gbp',
                        'line_items[0][price_data][currency]'     => 'gbp',
                        'line_items[0][price_data][unit_amount]'  => 10000, // £100
                        'line_items[0][price_data][product_data][name]' => 'Mosque Appeal Platform Fee — ' . $cause_title,
                        'line_items[0][quantity]'      => 1,
                        'customer_email'               => $charity_email,
                        'metadata[type]'               => 'appeal_request',
                        'metadata[appeal_id]'          => $appeal_id,
                        'success_url'                  => $success_url,
                        'cancel_url'                   => $cancel_url,
                    ] );

                    $ch = curl_init( 'https://api.stripe.com/v1/checkout/sessions' );
                    curl_setopt_array( $ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $stripe_data,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_USERPWD        => $stripe_key . ':',
                        CURLOPT_HTTPHEADER     => [ 'Stripe-Version: 2024-12-18.acacia' ],
                    ] );
                    $response = curl_exec( $ch );
                    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
                    curl_close( $ch );

                    $session = json_decode( $response, true );

                    if ( $http_code === 200 && ! empty( $session['url'] ) ) {
                        // Store the session ID for later verification
                        $wpdb->update( $table, [
                            'stripe_payment_id' => $session['id'],
                        ], [ 'id' => $appeal_id ] );

                        // Redirect to Stripe
                        wp_redirect( $session['url'] );
                        exit;
                    } else {
                        $err_msg = $session['error']['message'] ?? 'Unknown payment error';
                        error_log( '[YNJ Appeals] Stripe checkout error: ' . $err_msg );
                        $form_errors[] = 'Could not create payment session. Please try again.';
                    }
                }
            }
        }
    }
}

/* ──────────────────────────────────────────────
 * 2. Handle Stripe return — mark as paid
 * ────────────────────────────────────────────── */
$payment_success   = false;
$payment_cancelled = false;

if ( isset( $_GET['payment'] ) ) {
    if ( $_GET['payment'] === 'success' && ! empty( $_GET['appeal_id'] ) ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'appeal_requests' );
        $appeal_id = absint( $_GET['appeal_id'] );
        $appeal    = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d", $appeal_id
        ) );

        if ( $appeal && $appeal->status === 'pending_payment' ) {
            $wpdb->update( $table, [ 'status' => 'active' ], [ 'id' => $appeal_id ] );
            $appeal->status = 'active';
        }

        $payment_success = true;
    } elseif ( $_GET['payment'] === 'cancelled' ) {
        $payment_cancelled = true;
    }
}

/* ──────────────────────────────────────────────
 * 3. Load existing appeals for the "Track my appeals" section
 * ────────────────────────────────────────────── */
$my_appeals  = [];
$track_email = '';

if ( isset( $_GET['track_email'] ) ) {
    $track_email = sanitize_email( $_GET['track_email'] );
} elseif ( is_user_logged_in() ) {
    $track_email = wp_get_current_user()->user_email;
}

if ( $track_email && is_email( $track_email ) ) {
    global $wpdb;
    $table      = YNJ_DB::table( 'appeal_requests' );
    $resp_table = YNJ_DB::table( 'appeal_responses' );
    $my_appeals = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.*, (SELECT COUNT(*) FROM $resp_table r WHERE r.appeal_id = a.id AND r.response != 'pending') AS response_count
         FROM $table a WHERE a.charity_email = %s ORDER BY a.created_at DESC LIMIT 20",
        $track_email
    ) );
}


/* ──────────────────────────────────────────────
 * 4. Render
 * ────────────────────────────────────────────── */
get_header();
?>

<style>
/* Page-specific overrides */
.appeals-main { max-width: 720px; margin: 0 auto; padding: 20px 16px 60px; }
@media(min-width:900px) { .appeals-main { max-width: 760px; padding: 32px 24px 80px; } }

.appeals-hero {
    background: linear-gradient(160deg, #0a1628 0%, #122a4a 35%, #0e4d7a 65%, #00ADEF 100%);
    color: #fff; text-align: center; padding: 40px 24px 36px; border-radius: 20px;
    position: relative; overflow: hidden; margin-bottom: 28px;
    box-shadow: 0 8px 32px rgba(0,20,40,.25);
}
.appeals-hero::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(circle at 30% 80%, rgba(0,173,239,.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,.06) 0%, transparent 40%);
    pointer-events: none;
}
.appeals-hero h1 { font-size: 26px; font-weight: 800; margin-bottom: 8px; position: relative; z-index: 1; }
.appeals-hero p  { font-size: 14px; opacity: .85; max-width: 520px; margin: 0 auto; position: relative; z-index: 1; line-height: 1.5; }

/* How it works */
.appeals-steps {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 28px;
}
@media(max-width:600px) { .appeals-steps { grid-template-columns: 1fr; } }
.appeals-step {
    background: rgba(255,255,255,.85); backdrop-filter: blur(12px);
    border-radius: 14px; padding: 20px 16px; text-align: center;
    border: 1px solid rgba(255,255,255,.6); box-shadow: 0 2px 12px rgba(0,173,239,.08);
}
.appeals-step__num {
    width: 36px; height: 36px; border-radius: 50%; margin: 0 auto 10px;
    background: linear-gradient(135deg, #00ADEF, #0090d0); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800;
}
.appeals-step__title { font-size: 14px; font-weight: 700; margin-bottom: 4px; color: #0a1628; }
.appeals-step__desc  { font-size: 12px; color: #6b8fa3; line-height: 1.4; }

/* Form sections */
.appeals-section-title {
    font-size: 15px; font-weight: 700; color: #0a1628; margin-bottom: 14px;
    padding-bottom: 8px; border-bottom: 2px solid rgba(0,173,239,.15);
}

/* Radio group */
.appeals-radio-group { display: flex; flex-wrap: wrap; gap: 10px; }
.appeals-radio-label {
    display: flex; align-items: center; gap: 8px; padding: 10px 16px;
    border: 1.5px solid #e0e0e0; border-radius: 10px; cursor: pointer;
    font-size: 13px; font-weight: 600; color: #0a1628; transition: all .15s;
    background: #fff;
}
.appeals-radio-label:hover { border-color: #00ADEF; background: rgba(0,173,239,.04); }
.appeals-radio-label input[type="radio"] { accent-color: #00ADEF; }
.appeals-radio-label input[type="radio"]:checked ~ span { color: #00ADEF; }

/* Error list */
.appeals-errors {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
    padding: 14px 18px; margin-bottom: 18px; color: #991b1b; font-size: 13px;
}
.appeals-errors ul { list-style: disc; margin: 6px 0 0 18px; }
.appeals-errors li { margin-bottom: 2px; }

/* Success card */
.appeals-success {
    background: rgba(255,255,255,.9); border-radius: 18px; padding: 40px 24px;
    text-align: center; box-shadow: 0 4px 20px rgba(0,173,239,.1);
    border: 1px solid rgba(255,255,255,.6); margin-bottom: 28px;
}
.appeals-success__icon { font-size: 52px; margin-bottom: 12px; }
.appeals-success h2 { font-size: 22px; font-weight: 800; color: #166534; margin-bottom: 8px; }
.appeals-success p  { font-size: 14px; color: #6b8fa3; max-width: 420px; margin: 0 auto 20px; line-height: 1.5; }

/* Track section */
.appeals-track-form {
    display: flex; gap: 10px; margin-bottom: 18px;
}
.appeals-track-form input {
    flex: 1; padding: 10px 14px; border: 1px solid #e0e0e0; border-radius: 10px;
    font-size: 14px; font-family: inherit; outline: none;
}
.appeals-track-form input:focus { border-color: #00ADEF; }

/* Appeal list */
.appeals-list-item {
    display: flex; align-items: center; gap: 14px; padding: 14px;
    border-bottom: 1px solid #f0f0f0; transition: background .1s;
}
.appeals-list-item:last-child { border-bottom: none; }
.appeals-list-item:hover { background: #f8fafc; }
.appeals-list-item__info { flex: 1; min-width: 0; }
.appeals-list-item__title { font-size: 14px; font-weight: 700; color: #0a1628; }
.appeals-list-item__meta  { font-size: 12px; color: #6b8fa3; margin-top: 2px; }

/* Status badges */
.appeals-badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}
.appeals-badge--pending_payment { background: #fef3c7; color: #92400e; }
.appeals-badge--active          { background: #d1fae5; color: #065f46; }
.appeals-badge--completed       { background: #dbeafe; color: #1e40af; }
.appeals-badge--cancelled       { background: #fee2e2; color: #991b1b; }

/* Logo upload preview */
.appeals-logo-hint { font-size: 11px; color: #6b8fa3; margin-top: 4px; }

/* Fee note */
.appeals-fee-note {
    display: flex; align-items: center; gap: 10px; padding: 14px 18px;
    background: rgba(0,173,239,.06); border: 1px solid rgba(0,173,239,.15);
    border-radius: 12px; margin-top: 18px; font-size: 13px; color: #0a1628;
}
.appeals-fee-note strong { color: #00ADEF; }
</style>

<main class="appeals-main">

<?php if ( $payment_success ) : ?>
    <!-- ───── Payment Success ───── -->
    <div class="appeals-success">
        <div class="appeals-success__icon">&#x2705;</div>
        <h2>Appeal Submitted Successfully</h2>
        <p>Your appeal has been broadcast to all mosques accepting charity appeals. You will receive responses at your registered email address.</p>
        <a href="<?php echo esc_url( home_url( '/appeals/' ) ); ?>" class="ynj-btn" style="margin-top:12px;">Submit Another Appeal</a>
    </div>

<?php elseif ( $payment_cancelled ) : ?>
    <!-- ───── Payment Cancelled ───── -->
    <div class="appeals-errors">
        <strong>Payment was cancelled.</strong> Your appeal has been saved but will not be broadcast until payment is completed.
        You can re-submit or contact us for assistance.
    </div>

<?php else : ?>
    <!-- ───── Hero ───── -->
    <div class="appeals-hero">
        <h1>Request a Mosque Appeal</h1>
        <p>Connect your charity with mosques across the UK. Submit your cause details and we will broadcast your appeal to hundreds of mosques ready to support.</p>
    </div>

    <!-- ───── How It Works ───── -->
    <div class="appeals-steps">
        <div class="appeals-step">
            <div class="appeals-step__num">1</div>
            <div class="appeals-step__title">Submit Your Appeal</div>
            <div class="appeals-step__desc">Fill in your charity details and cause information below.</div>
        </div>
        <div class="appeals-step">
            <div class="appeals-step__num">2</div>
            <div class="appeals-step__title">Mosques Respond</div>
            <div class="appeals-step__desc">Your appeal is sent to all accepting mosques. They respond with availability.</div>
        </div>
        <div class="appeals-step">
            <div class="appeals-step__num">3</div>
            <div class="appeals-step__title">Go Live</div>
            <div class="appeals-step__desc">Coordinate dates and deliver your appeal to the congregation.</div>
        </div>
    </div>

    <!-- ───── Errors ───── -->
    <?php if ( ! empty( $form_errors ) ) : ?>
        <div class="appeals-errors">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ( $form_errors as $err ) : ?>
                    <li><?php echo esc_html( $err ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ───── Appeal Submission Form ───── -->
    <form method="POST" enctype="multipart/form-data" class="ynj-form">
        <?php wp_nonce_field( 'ynj_submit_appeal', 'ynj_appeal_nonce' ); ?>

        <!-- Charity Details -->
        <section class="ynj-card">
            <div class="appeals-section-title">Charity Details</div>

            <div class="ynj-field">
                <label>Charity Name *</label>
                <input type="text" name="charity_name" required
                       value="<?php echo esc_attr( $_POST['charity_name'] ?? '' ); ?>"
                       placeholder="e.g. Islamic Relief UK">
            </div>

            <div class="ynj-field-row" style="margin-top:14px;">
                <div class="ynj-field">
                    <label>Email *</label>
                    <input type="email" name="charity_email" required
                           value="<?php echo esc_attr( $_POST['charity_email'] ?? '' ); ?>"
                           placeholder="appeals@charity.org">
                </div>
                <div class="ynj-field">
                    <label>Phone *</label>
                    <input type="tel" name="charity_phone" required
                           value="<?php echo esc_attr( $_POST['charity_phone'] ?? '' ); ?>"
                           placeholder="07xxx xxx xxx">
                </div>
            </div>

            <div class="ynj-field-row" style="margin-top:14px;">
                <div class="ynj-field">
                    <label>Website</label>
                    <input type="url" name="charity_website"
                           value="<?php echo esc_attr( $_POST['charity_website'] ?? '' ); ?>"
                           placeholder="https://...">
                </div>
                <div class="ynj-field">
                    <label>Charity Reg. Number *</label>
                    <input type="text" name="charity_reg_number" required
                           value="<?php echo esc_attr( $_POST['charity_reg_number'] ?? '' ); ?>"
                           placeholder="e.g. 1234567">
                </div>
            </div>

            <div class="ynj-field" style="margin-top:14px;">
                <label>Charity Logo</label>
                <input type="file" name="charity_logo" accept="image/jpeg,image/png,image/webp"
                       style="padding:8px;border:1px dashed #d1d5db;border-radius:10px;background:#f9fafb;cursor:pointer;">
                <div class="appeals-logo-hint">JPG, PNG, or WebP. Max 2MB.</div>
            </div>
        </section>

        <!-- Cause Details -->
        <section class="ynj-card">
            <div class="appeals-section-title">Cause Details</div>

            <div class="ynj-field">
                <label>Cause Title *</label>
                <input type="text" name="cause_title" required
                       value="<?php echo esc_attr( $_POST['cause_title'] ?? '' ); ?>"
                       placeholder="e.g. Gaza Emergency Relief Fund">
            </div>

            <div class="ynj-field" style="margin-top:14px;">
                <label>Description *</label>
                <textarea name="cause_description" rows="4" required
                          placeholder="Describe your cause, what funds will be used for, and the impact..."><?php
                    echo esc_textarea( $_POST['cause_description'] ?? '' );
                ?></textarea>
            </div>

            <div class="ynj-field" style="margin-top:14px;">
                <label>Category</label>
                <select name="cause_category">
                    <?php
                    $categories = [
                        'education'   => 'Education',
                        'health'      => 'Health',
                        'poverty'     => 'Poverty',
                        'emergency'   => 'Emergency',
                        'environment' => 'Environment',
                        'community'   => 'Community',
                        'general'     => 'General',
                    ];
                    $selected_cat = $_POST['cause_category'] ?? 'general';
                    foreach ( $categories as $val => $label ) :
                    ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $selected_cat, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <!-- Appeal Preferences -->
        <section class="ynj-card">
            <div class="appeals-section-title">Appeal Preferences</div>

            <div class="ynj-field">
                <label>Appeal Type</label>
                <div class="appeals-radio-group" style="margin-top:6px;">
                    <?php
                    $types = [
                        'in_person'  => 'In Person',
                        'recorded'   => 'Recorded Video',
                        'broadcast'  => 'Live Broadcast',
                    ];
                    $selected_type = $_POST['appeal_type'] ?? 'in_person';
                    foreach ( $types as $val => $label ) :
                    ?>
                        <label class="appeals-radio-label">
                            <input type="radio" name="appeal_type" value="<?php echo esc_attr( $val ); ?>"
                                   <?php checked( $selected_type, $val ); ?>>
                            <span><?php echo esc_html( $label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ynj-field" style="margin-top:14px;">
                <label>Preferred Dates</label>
                <textarea name="preferred_dates" rows="2"
                          placeholder="e.g. Any Friday in May 2026, or last 10 days of Ramadan..."><?php
                    echo esc_textarea( $_POST['preferred_dates'] ?? '' );
                ?></textarea>
            </div>

            <div class="ynj-field" style="margin-top:14px;">
                <label>Budget Note <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
                <input type="text" name="budget_note"
                       value="<?php echo esc_attr( $_POST['budget_note'] ?? '' ); ?>"
                       placeholder="e.g. Can cover travel expenses for speaker">
            </div>
        </section>

        <!-- Fee & Submit -->
        <section class="ynj-card" style="text-align:center;">
            <div class="appeals-fee-note">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#00ADEF" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <span>A one-time platform fee of <strong>&pound;100</strong> applies. Your appeal will be broadcast to all mosques accepting appeals.</span>
            </div>

            <button type="submit" class="ynj-btn" style="width:100%;justify-content:center;margin-top:18px;padding:16px;">
                Submit Appeal &amp; Pay &pound;100
            </button>

            <p class="ynj-text-muted" style="margin-top:10px;font-size:11px;">
                Secure payment via Stripe. You will be redirected to complete payment.
            </p>
        </section>
    </form>
<?php endif; ?>

<!-- ───── Track My Appeals ───── -->
<section class="ynj-card" style="margin-top:10px;">
    <div class="appeals-section-title">Track My Appeals</div>
    <p class="ynj-text-muted" style="margin-bottom:12px;">Enter the email you used when submitting to view your appeals and mosque responses.</p>

    <form method="GET" action="<?php echo esc_url( home_url( '/appeals/' ) ); ?>" class="appeals-track-form">
        <input type="email" name="track_email" required
               value="<?php echo esc_attr( $track_email ); ?>"
               placeholder="your@charity.org">
        <button type="submit" class="ynj-btn" style="white-space:nowrap;">Track</button>
    </form>

    <?php if ( $track_email && ! empty( $my_appeals ) ) : ?>
        <div>
            <?php foreach ( $my_appeals as $appeal ) :
                $status_label = str_replace( '_', ' ', $appeal->status );
                $badge_class  = 'appeals-badge appeals-badge--' . esc_attr( $appeal->status );
                $date_display = date( 'j M Y', strtotime( $appeal->created_at ) );
            ?>
                <div class="appeals-list-item">
                    <div class="appeals-list-item__info">
                        <div class="appeals-list-item__title"><?php echo esc_html( $appeal->cause_title ); ?></div>
                        <div class="appeals-list-item__meta">
                            <?php echo esc_html( ucfirst( str_replace( '_', ' ', $appeal->appeal_type ) ) ); ?>
                            &middot; <?php echo esc_html( $date_display ); ?>
                            <?php if ( (int) $appeal->response_count > 0 ) : ?>
                                &middot; <?php echo (int) $appeal->response_count; ?> mosque response<?php echo $appeal->response_count > 1 ? 's' : ''; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="<?php echo $badge_class; ?>"><?php echo esc_html( ucwords( $status_label ) ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ( $track_email ) : ?>
        <p class="ynj-text-muted" style="text-align:center;padding:16px 0;">No appeals found for this email address.</p>
    <?php endif; ?>
</section>

</main>

<?php get_footer(); ?>
