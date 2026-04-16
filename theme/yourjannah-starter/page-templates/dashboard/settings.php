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

        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Save Profile', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Mosque Page Link -->
<div class="d-card" style="background:var(--primary-light);border-color:var(--primary);">
    <p style="font-size:13px;font-weight:600;color:var(--primary-dark);">🔗 <?php esc_html_e( 'Your Mosque Page', 'yourjannah' ); ?></p>
    <code style="display:block;padding:10px;background:#fff;border-radius:8px;font-size:14px;margin-top:6px;word-break:break-all;"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug ) ); ?></code>
</div>
