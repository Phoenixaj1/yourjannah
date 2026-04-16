<?php
/**
 * Template: Help & Support — Mosque ticket submission (Pure PHP)
 *
 * URL: /mosque/{slug}/help
 * Mosque admins submit support tickets. Handled entirely in PHP.
 *
 * @package YourJannah
 */

get_header();
$slug   = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$mosque_name = $mosque ? $mosque->name : '';

// Get submitter name
$submitter = '';
if ( is_user_logged_in() ) {
    $submitter = wp_get_current_user()->display_name . ' (' . wp_get_current_user()->user_email . ')';
} else {
    $user_data = null;
    try { $user_data = json_decode( stripslashes( $_COOKIE['ynj_user'] ?? '' ) ); } catch( \Exception $e ) {}
}

// Handle POST — create ticket
$submitted = false;
$error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_ticket_nonce'] ?? '', 'ynj_create_ticket' ) ) {
    $subject  = sanitize_text_field( $_POST['subject'] ?? '' );
    $body     = sanitize_textarea_field( $_POST['body'] ?? '' );
    $category = sanitize_text_field( $_POST['category'] ?? 'general' );
    $priority = sanitize_text_field( $_POST['priority'] ?? 'normal' );
    $name     = sanitize_text_field( $_POST['name'] ?? $submitter );

    if ( ! $subject || ! $body ) {
        $error = __( 'Subject and description are required.', 'yourjannah' );
    } else {
        global $wpdb;
        $tt = YNJ_DB::table( 'support_tickets' );
        $wpdb->insert( $tt, [
            'mosque_id'  => $mosque_id,
            'subject'    => $subject,
            'body'       => $body,
            'category'   => $category,
            'priority'   => $priority,
            'status'     => 'open',
            'created_by' => $name,
        ] );
        $submitted = $wpdb->insert_id ? true : false;
        if ( ! $submitted ) $error = __( 'Could not submit ticket. Please try again.', 'yourjannah' );
    }
}

// Load existing tickets for this mosque
$tickets = [];
if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $tt = YNJ_DB::table( 'support_tickets' );
    $tickets = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, subject, category, priority, status, admin_reply, replied_at, created_at
         FROM $tt WHERE mosque_id = %d ORDER BY created_at DESC LIMIT 20",
        $mosque_id
    ) ) ?: [];
}
?>

<main class="ynj-main">
    <h2 style="font-size:18px;font-weight:700;margin-bottom:4px;">🎫 <?php esc_html_e( 'Help & Support', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:16px;"><?php esc_html_e( 'Need help with your mosque page? Submit a request and we\'ll get back to you.', 'yourjannah' ); ?></p>

    <?php if ( $submitted ) : ?>
    <div style="background:#dcfce7;color:#166534;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-size:14px;font-weight:600;">
        ✅ <?php esc_html_e( 'Your support request has been submitted! We\'ll get back to you soon.', 'yourjannah' ); ?>
    </div>
    <?php endif; ?>

    <?php if ( $error ) : ?>
    <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:16px;font-size:14px;"><?php echo esc_html( $error ); ?></div>
    <?php endif; ?>

    <!-- Submit Ticket -->
    <div class="ynj-card" style="padding:20px;margin-bottom:16px;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:12px;">📝 <?php esc_html_e( 'New Support Request', 'yourjannah' ); ?></h3>
        <form method="post">
            <?php wp_nonce_field( 'ynj_create_ticket', '_ynj_ticket_nonce' ); ?>

            <div style="margin-bottom:10px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Your Name / Email', 'yourjannah' ); ?></label>
                <input type="text" name="name" value="<?php echo esc_attr( $submitter ); ?>" placeholder="<?php esc_attr_e( 'Your name or email', 'yourjannah' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Category', 'yourjannah' ); ?></label>
                    <select name="category" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                        <option value="general"><?php esc_html_e( 'General Help', 'yourjannah' ); ?></option>
                        <option value="technical"><?php esc_html_e( 'Technical Issue / Bug', 'yourjannah' ); ?></option>
                        <option value="feature"><?php esc_html_e( 'Feature Request', 'yourjannah' ); ?></option>
                        <option value="payment"><?php esc_html_e( 'Payment / Billing', 'yourjannah' ); ?></option>
                        <option value="marketing"><?php esc_html_e( 'Marketing Help', 'yourjannah' ); ?></option>
                        <option value="data"><?php esc_html_e( 'Data / Import Help', 'yourjannah' ); ?></option>
                        <option value="design"><?php esc_html_e( 'Design / Branding', 'yourjannah' ); ?></option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Priority', 'yourjannah' ); ?></label>
                    <select name="priority" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                        <option value="low"><?php esc_html_e( 'Low', 'yourjannah' ); ?></option>
                        <option value="normal" selected><?php esc_html_e( 'Normal', 'yourjannah' ); ?></option>
                        <option value="high"><?php esc_html_e( 'High', 'yourjannah' ); ?></option>
                        <option value="urgent"><?php esc_html_e( 'Urgent', 'yourjannah' ); ?></option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:10px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Subject *', 'yourjannah' ); ?></label>
                <input type="text" name="subject" required placeholder="<?php esc_attr_e( 'Brief summary of your issue', 'yourjannah' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Description *', 'yourjannah' ); ?></label>
                <textarea name="body" rows="5" required placeholder="<?php esc_attr_e( 'Describe your issue or request in detail...', 'yourjannah' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical;"></textarea>
            </div>

            <button type="submit" class="ynj-btn" style="width:100%;justify-content:center;">📤 <?php esc_html_e( 'Submit Request', 'yourjannah' ); ?></button>
        </form>
    </div>

    <!-- Existing Tickets -->
    <?php if ( ! empty( $tickets ) ) : ?>
    <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">📋 <?php esc_html_e( 'Your Tickets', 'yourjannah' ); ?></h3>
    <?php foreach ( $tickets as $t ) :
        $status_bg = [ 'open' => '#fee2e2', 'in_progress' => '#fef3c7', 'resolved' => '#dcfce7' ];
        $status_fg = [ 'open' => '#991b1b', 'in_progress' => '#92400e', 'resolved' => '#166534' ];
    ?>
    <div class="ynj-card" style="padding:14px;margin-bottom:8px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <strong style="font-size:14px;">#<?php echo $t->id; ?> — <?php echo esc_html( $t->subject ); ?></strong>
            <span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:<?php echo $status_bg[ $t->status ] ?? '#f3f4f6'; ?>;color:<?php echo $status_fg[ $t->status ] ?? '#374151'; ?>;"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $t->status ) ) ); ?></span>
        </div>
        <div style="font-size:11px;color:#6b8fa3;"><?php echo esc_html( $t->category ); ?> · <?php echo esc_html( substr( $t->created_at, 0, 10 ) ); ?></div>
        <?php if ( $t->admin_reply ) : ?>
        <div style="background:#f0f9ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px;margin-top:8px;">
            <strong style="font-size:11px;color:#1e40af;">YourJannah Reply:</strong>
            <p style="font-size:13px;color:#1e40af;margin:4px 0 0;"><?php echo nl2br( esc_html( $t->admin_reply ) ); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
</main>

<?php get_footer(); ?>
