<?php
/**
 * Dashboard Section: Settings
 * Mosque profile update + admin team management. Pure PHP.
 */

$mt = YNJ_DB::table( 'mosques' );

// Handle POST — update profile
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_settings' ) ) {
    $update = [
        'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
        'address'     => sanitize_text_field( $_POST['address'] ?? '' ),
        'city'        => sanitize_text_field( $_POST['city'] ?? '' ),
        'postcode'    => sanitize_text_field( $_POST['postcode'] ?? '' ),
        'phone'       => sanitize_text_field( $_POST['phone'] ?? '' ),
        'website'     => esc_url_raw( $_POST['website'] ?? '' ),
        'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
        'theme'       => sanitize_text_field( $_POST['theme'] ?? 'minimal' ),
    ];
    if ( $update['name'] ) {
        $wpdb->update( $mt, $update, [ 'id' => $mosque_id ] );
        // Refresh mosque data
        $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
        $mosque_name = $mosque->name;
        $success = __( 'Profile updated!', 'yourjannah' );
    }
}
?>

<div class="d-header">
    <h1>⚙️ <?php esc_html_e( 'Settings', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Update your mosque profile and manage your admin team.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>

<div class="d-card">
    <h3>🕌 <?php esc_html_e( 'Mosque Profile', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_settings', '_ynj_nonce' ); ?>

        <div class="d-field">
            <label><?php esc_html_e( 'Mosque Name *', 'yourjannah' ); ?></label>
            <input type="text" name="name" value="<?php echo esc_attr( $mosque->name ?? '' ); ?>" required>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Address', 'yourjannah' ); ?></label>
                <input type="text" name="address" value="<?php echo esc_attr( $mosque->address ?? '' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'City', 'yourjannah' ); ?></label>
                <input type="text" name="city" value="<?php echo esc_attr( $mosque->city ?? '' ); ?>">
            </div>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Postcode', 'yourjannah' ); ?></label>
                <input type="text" name="postcode" value="<?php echo esc_attr( $mosque->postcode ?? '' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label>
                <input type="tel" name="phone" value="<?php echo esc_attr( $mosque->phone ?? '' ); ?>">
            </div>
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Website', 'yourjannah' ); ?></label>
            <input type="url" name="website" value="<?php echo esc_attr( $mosque->website ?? '' ); ?>" placeholder="https://">
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Description', 'yourjannah' ); ?></label>
            <textarea name="description" rows="4"><?php echo esc_textarea( $mosque->description ?? '' ); ?></textarea>
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Page Theme', 'yourjannah' ); ?></label>
            <select name="theme">
                <option value="classic" <?php selected( $mosque->theme ?? '', 'classic' ); ?>>🕌 Classic — current teal gradient design</option>
                <option value="modern" <?php selected( $mosque->theme ?? '', 'modern' ); ?>>✨ Modern — clean white, Apple-inspired, sophisticated</option>
            </select>
            <p style="font-size:11px;color:var(--text-dim);margin-top:4px;"><?php esc_html_e( 'Changes how your mosque page looks to visitors. Classic is the original design. Modern is cleaner with white backgrounds and subtle shadows.', 'yourjannah' ); ?></p>
        </div>

        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Save Profile', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Admin Team -->
<?php
$at = YNJ_DB::table( 'accounts' );
$admins = [];
if ( $wpdb->get_var( "SHOW TABLES LIKE '$at'" ) === $at ) {
    $admins = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, admin_name, admin_email, status, created_at FROM $at WHERE mosque_id=%d ORDER BY created_at ASC",
        $mosque_id
    ) ) ?: [];
}

// Handle invite
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_settings' ) && ( $_POST['action'] ?? '' ) === 'invite_admin' ) {
    $invite_email = sanitize_email( $_POST['invite_email'] ?? '' );
    if ( $invite_email && is_email( $invite_email ) ) {
        wp_mail( $invite_email,
            'You\'ve been invited to manage ' . $mosque_name . ' on YourJannah',
            "Assalamu alaikum,\n\nYou've been invited to help manage " . $mosque_name . " on YourJannah.\n\nRegister here: " . home_url( '/register?claim=' . $mosque_slug ) . "\n\nJazakAllah khair,\n" . $mosque_name,
            [ 'Content-Type: text/html; charset=UTF-8', 'From: YourJannah <noreply@yourjannah.com>' ]
        );
        $success = sprintf( __( 'Invitation sent to %s!', 'yourjannah' ), $invite_email );
    }
}
?>
<div class="d-card">
    <h3>👥 <?php esc_html_e( 'Admin Team', 'yourjannah' ); ?></h3>
    <?php if ( $admins ) : ?>
    <table class="d-table" style="margin-bottom:12px;">
        <thead><tr><th><?php esc_html_e( 'Name', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Email', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $admins as $a ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $a->admin_name ); ?></strong></td>
            <td style="font-size:12px;"><?php echo esc_html( $a->admin_email ); ?></td>
            <td><span class="d-badge d-badge--<?php echo $a->status === 'active' ? 'green' : 'yellow'; ?>"><?php echo esc_html( ucfirst( $a->status ) ); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:8px;"><?php esc_html_e( 'Invite another admin to help manage your mosque:', 'yourjannah' ); ?></p>
    <form method="post" style="display:flex;gap:8px;">
        <?php wp_nonce_field( 'ynj_dash_settings', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="invite_admin">
        <input type="email" name="invite_email" placeholder="admin@email.com" required style="flex:1;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;">
        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Send Invite', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Mosque Page Link -->
<div class="d-card" style="background:var(--primary-light);border-color:var(--primary);">
    <p style="font-size:13px;font-weight:600;color:var(--primary-dark);">🔗 <?php esc_html_e( 'Your Mosque Page', 'yourjannah' ); ?></p>
    <code style="display:block;padding:10px;background:#fff;border-radius:8px;font-size:14px;margin-top:6px;word-break:break-all;"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug ) ); ?></code>
</div>
