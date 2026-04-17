<?php
/**
 * Dashboard Section: Broadcast + CSV Import
 */
$sub_t = YNJ_DB::table( 'subscribers' );
$it = YNJ_DB::table( 'email_imports' );
$sub_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sub_t WHERE mosque_id=%d AND status='active'", $mosque_id ) );

// Handle broadcast POST
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_broadcast' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    if ( $action === 'broadcast' ) {
        // Rate limit: max 3 broadcasts per week per mosque
        $rl_key = 'ynj_broadcast_count_' . $mosque_id;
        $count = (int) get_transient( $rl_key );
        if ( $count >= 3 ) {
            $error = __( 'You have reached your broadcast limit (3 per week).', 'yourjannah' );
        } else {
        $subject = sanitize_text_field( $_POST['subject'] ?? '' );
        $body = sanitize_textarea_field( $_POST['body'] ?? '' );
        if ( $subject && $body ) {
            // Get subscriber emails
            $emails = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT email FROM $sub_t WHERE mosque_id=%d AND status='active' AND email != ''", $mosque_id ) );
            $sent = 0;
            foreach ( $emails as $email ) {
                wp_mail( $email, $subject, nl2br( $body ), [ 'Content-Type: text/html; charset=UTF-8', 'From: ' . $mosque_name . ' <noreply@yourjannah.com>' ] );
                $sent++;
            }
            $success = sprintf( __( 'Broadcast sent to %d subscribers!', 'yourjannah' ), $sent );
            // Increment broadcast rate limit counter
            set_transient( $rl_key, $count + 1, 7 * DAY_IN_SECONDS );
        } else { $error = __( 'Subject and message required.', 'yourjannah' ); }
        } // end rate limit else
    }

    if ( $action === 'import' && ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
        $handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
        if ( $handle ) {
            $header = fgetcsv( $handle );
            $header = array_map( 'strtolower', array_map( 'trim', $header ?: [] ) );
            $email_col = array_search( 'email', $header ); if ( $email_col === false ) $email_col = array_search( 'e-mail', $header );
            $name_col = array_search( 'name', $header ); if ( $name_col === false ) $name_col = array_search( 'full_name', $header );
            $phone_col = array_search( 'phone', $header ); if ( $phone_col === false ) $phone_col = array_search( 'mobile', $header );

            if ( $email_col === false ) { $error = __( 'CSV must have an "email" column.', 'yourjannah' ); }
            else {
                $imported = 0; $dupes = 0;
                while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                    $email = sanitize_email( trim( $row[$email_col] ?? '' ) );
                    if ( ! is_email( $email ) ) continue;
                    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $sub_t WHERE mosque_id=%d AND email=%s", $mosque_id, $email ) );
                    if ( $exists ) { $dupes++; continue; }
                    $wpdb->insert( $sub_t, [ 'mosque_id' => $mosque_id, 'email' => $email, 'name' => sanitize_text_field( $name_col !== false ? ( $row[$name_col] ?? '' ) : '' ), 'phone' => sanitize_text_field( $phone_col !== false ? ( $row[$phone_col] ?? '' ) : '' ), 'status' => 'active' ] );
                    $imported++;
                }
                fclose( $handle );
                $wpdb->insert( $it, [ 'mosque_id' => $mosque_id, 'filename' => sanitize_file_name( $_FILES['csv_file']['name'] ), 'total_rows' => $imported + $dupes, 'imported' => $imported, 'duplicates' => $dupes, 'status' => 'completed' ] );
                $success = sprintf( __( 'Imported %d contacts (%d duplicates skipped).', 'yourjannah' ), $imported, $dupes );
            }
        }
    }
}

$imports = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $it WHERE mosque_id=%d ORDER BY created_at DESC LIMIT 10", $mosque_id ) ) ?: [];
?>
<div class="d-header"><h1>📢 <?php esc_html_e( 'Broadcast & Import', 'yourjannah' ); ?></h1></div>
<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<?php
// Rate limit check
$rl_key = 'ynj_broadcast_count_' . $mosque_id;
$broadcasts_this_week = (int) get_transient( $rl_key );
$remaining = max( 0, 3 - $broadcasts_this_week );
?>
<div class="d-card" style="background:var(--primary-light);border-color:var(--primary);margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <p style="font-size:14px;font-weight:600;color:var(--primary-dark);margin:0;">📬 <?php printf( esc_html__( 'Reach up to %d subscribers', 'yourjannah' ), $sub_count ); ?></p>
        <span style="font-size:12px;font-weight:700;color:<?php echo $remaining > 0 ? '#166534' : '#991b1b'; ?>;background:<?php echo $remaining > 0 ? '#dcfce7' : '#fee2e2'; ?>;padding:4px 10px;border-radius:6px;">
            <?php printf( esc_html__( '%d/%d broadcasts left this week', 'yourjannah' ), $remaining, 3 ); ?>
        </span>
    </div>
</div>

<div class="d-card">
    <h3>📤 <?php esc_html_e( 'Send Broadcast Email', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_broadcast', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="broadcast">
        <div class="d-field"><label>Subject *</label><input type="text" name="subject" required placeholder="<?php esc_attr_e( 'e.g. Jumu\'ah time change this week', 'yourjannah' ); ?>"></div>
        <div class="d-field"><label>Message *</label><textarea name="body" rows="5" required placeholder="<?php esc_attr_e( 'Write your message...', 'yourjannah' ); ?>"></textarea></div>
        <button type="submit" class="d-btn d-btn--primary" onclick="return confirm('Send to all <?php echo $sub_count; ?> subscribers?')"><?php esc_html_e( '📨 Send Broadcast', 'yourjannah' ); ?></button>
    </form>
</div>

<div class="d-card">
    <h3>📥 <?php esc_html_e( 'Import Email List', 'yourjannah' ); ?></h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;"><?php esc_html_e( 'Upload a CSV with an "email" column. Optionally include "name" and "phone" columns.', 'yourjannah' ); ?></p>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'ynj_dash_broadcast', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="import">
        <div class="d-field"><input type="file" name="csv_file" accept=".csv,.txt" required style="padding:8px;border:1px dashed var(--border);border-radius:8px;width:100%;"></div>
        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( '📤 Upload & Import', 'yourjannah' ); ?></button>
    </form>
</div>

<?php if ( $imports ) : ?>
<div class="d-card">
    <h3><?php esc_html_e( 'Import History', 'yourjannah' ); ?></h3>
    <table class="d-table">
        <thead><tr><th>File</th><th style="text-align:right;">Imported</th><th style="text-align:right;">Duplicates</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ( $imports as $imp ) : ?>
        <tr><td><?php echo esc_html( $imp->filename ); ?></td><td style="text-align:right;color:#16a34a;font-weight:700;"><?php echo (int) $imp->imported; ?></td><td style="text-align:right;"><?php echo (int) $imp->duplicates; ?></td><td style="font-size:12px;"><?php echo esc_html( substr( $imp->created_at, 0, 10 ) ); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
