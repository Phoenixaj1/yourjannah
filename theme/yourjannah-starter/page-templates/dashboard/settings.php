<?php
/**
 * Dashboard Section: Settings
 * Mosque profile update + admin team management. Pure PHP.
 */

$mt = YNJ_DB::table( 'mosques' );

// Handle POST — update profile
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_settings' ) ) {
    $post_action = sanitize_text_field( $_POST['action'] ?? 'update_profile' );

    if ( $post_action === 'update_profile' ) {
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
            $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
            $mosque_name = $mosque->name;
            $success = __( 'Profile updated!', 'yourjannah' );
        }
    }

    if ( $post_action === 'update_appeals' ) {
        $accept_appeals            = isset( $_POST['accept_appeals'] ) ? 1 : 0;
        $fee_inperson              = (int) ( floatval( $_POST['appeal_fee_inperson'] ?? 0 ) * 100 );
        $fee_recorded              = (int) ( floatval( $_POST['appeal_fee_recorded'] ?? 0 ) * 100 );

        $wpdb->update( $mt, [
            'accept_appeals'             => $accept_appeals,
            'appeal_fee_inperson_pence'  => $fee_inperson,
            'appeal_fee_recorded_pence'  => $fee_recorded,
        ], [ 'id' => $mosque_id ] );

        $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
        $success = __( 'Charity appeal settings saved!', 'yourjannah' );
    }

    if ( $post_action === 'update_imam' ) {
        $imam_email       = sanitize_email( $_POST['imam_email'] ?? '' );
        $imam_auto_publish = isset( $_POST['imam_auto_publish'] ) ? 1 : 0;

        if ( $imam_email && is_email( $imam_email ) ) {
            $imam_user = get_user_by( 'email', $imam_email );
            if ( $imam_user ) {
                $imam_user->add_role( 'ynj_imam' );
                $wpdb->update( $mt, [
                    'imam_user_id'      => $imam_user->ID,
                    'imam_auto_publish' => $imam_auto_publish,
                ], [ 'id' => $mosque_id ] );
                $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
                $success = sprintf( __( 'Imam linked: %s', 'yourjannah' ), $imam_user->display_name );
            } else {
                $error = __( 'No WordPress user found with that email address. They need to register first.', 'yourjannah' );
            }
        } else {
            // Just update auto-publish toggle
            $wpdb->update( $mt, [
                'imam_auto_publish' => $imam_auto_publish,
            ], [ 'id' => $mosque_id ] );
            $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
            $success = __( 'Imam settings updated!', 'yourjannah' );
        }
    }

    if ( $post_action === 'remove_imam' ) {
        $current_imam_id = (int) ( $mosque->imam_user_id ?? 0 );
        if ( $current_imam_id ) {
            $imam_user = get_user_by( 'ID', $current_imam_id );
            if ( $imam_user ) {
                $imam_user->remove_role( 'ynj_imam' );
            }
            $wpdb->update( $mt, [
                'imam_user_id'      => 0,
                'imam_auto_publish' => 0,
            ], [ 'id' => $mosque_id ] );
            $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
            $success = __( 'Imam removed.', 'yourjannah' );
        }
    }
}
?>

<div class="d-header">
    <h1>⚙️ <?php esc_html_e( 'Settings', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Update your mosque profile and manage your admin team.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error"><?php echo esc_html( $error ); ?></div><?php endif; ?>

<div class="d-card">
    <h3>🕌 <?php esc_html_e( 'Mosque Profile', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_settings', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="update_profile">

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
$admin_users = get_users([
    'meta_key' => 'ynj_mosque_id',
    'meta_value' => $mosque_id,
    'role__in' => ['ynj_mosque_admin', 'administrator'],
]);

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
    <?php if ( $admin_users ) : ?>
    <table class="d-table" style="margin-bottom:12px;">
        <thead><tr><th><?php esc_html_e( 'Name', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Email', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $admin_users as $a ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $a->display_name ); ?></strong></td>
            <td style="font-size:12px;"><?php echo esc_html( $a->user_email ); ?></td>
            <td><span class="d-badge d-badge--green"><?php esc_html_e( 'Active', 'yourjannah' ); ?></span></td>
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

<!-- Charity Appeals Settings -->
<div class="d-card">
    <h3><?php esc_html_e( 'Charity Appeals', 'yourjannah' ); ?></h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;"><?php esc_html_e( 'Allow charities to request appeal slots at your mosque. Earn revenue from hosting in-person or recorded appeals.', 'yourjannah' ); ?></p>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_settings', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="update_appeals">

        <div class="d-field">
            <label>
                <input type="checkbox" name="accept_appeals" value="1" <?php checked( $mosque->accept_appeals ?? 0, 1 ); ?>>
                <?php esc_html_e( 'Accept Charity Appeals', 'yourjannah' ); ?>
            </label>
            <p style="font-size:11px;color:var(--text-dim);margin-top:2px;"><?php esc_html_e( 'When enabled, your mosque appears in the charity appeals marketplace.', 'yourjannah' ); ?></p>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'In-Person Appeal Fee (&pound;)', 'yourjannah' ); ?></label>
                <input type="number" name="appeal_fee_inperson" min="0" step="1" value="<?php echo esc_attr( ( $mosque->appeal_fee_inperson_pence ?? 0 ) / 100 ); ?>">
                <p style="font-size:11px;color:var(--text-dim);margin-top:2px;"><?php esc_html_e( 'Default fee for in-person charity appeals.', 'yourjannah' ); ?></p>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Recorded Appeal Fee (&pound;)', 'yourjannah' ); ?></label>
                <input type="number" name="appeal_fee_recorded" min="0" step="1" value="<?php echo esc_attr( ( $mosque->appeal_fee_recorded_pence ?? 0 ) / 100 ); ?>">
                <p style="font-size:11px;color:var(--text-dim);margin-top:2px;"><?php esc_html_e( 'Default fee for recorded/video charity appeals.', 'yourjannah' ); ?></p>
            </div>
        </div>

        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Save Appeal Settings', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Imam Management -->
<?php
$imam_user_id = (int) ( $mosque->imam_user_id ?? 0 );
$imam_user    = $imam_user_id ? get_user_by( 'ID', $imam_user_id ) : null;
?>
<div class="d-card">
    <h3><?php esc_html_e( 'Imam Management', 'yourjannah' ); ?></h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;"><?php esc_html_e( 'Link your imam so they can send religious messages and announcements to the congregation.', 'yourjannah' ); ?></p>

    <?php if ( $imam_user ) : ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-bottom:12px;">
        <span style="font-size:24px;">&#128104;&#8205;&#127891;</span>
        <div style="flex:1;">
            <strong><?php echo esc_html( $imam_user->display_name ); ?></strong>
            <br><span style="font-size:12px;color:var(--text-dim);"><?php echo esc_html( $imam_user->user_email ); ?></span>
        </div>
        <form method="post" onsubmit="return confirm('Remove this imam?')">
            <?php wp_nonce_field( 'ynj_dash_settings', '_ynj_nonce' ); ?>
            <input type="hidden" name="action" value="remove_imam">
            <button type="submit" class="d-btn d-btn--sm d-btn--danger"><?php esc_html_e( 'Remove Imam', 'yourjannah' ); ?></button>
        </form>
    </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_settings', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="update_imam">

        <?php if ( ! $imam_user ) : ?>
        <div class="d-field">
            <label><?php esc_html_e( 'Imam Email', 'yourjannah' ); ?></label>
            <input type="email" name="imam_email" placeholder="imam@example.com">
            <p style="font-size:11px;color:var(--text-dim);margin-top:2px;"><?php esc_html_e( 'Enter the imam\'s email to link their account. They must have a WordPress account.', 'yourjannah' ); ?></p>
        </div>
        <?php endif; ?>

        <div class="d-field">
            <label>
                <input type="checkbox" name="imam_auto_publish" value="1" <?php checked( $mosque->imam_auto_publish ?? 0, 1 ); ?>>
                <?php esc_html_e( 'Imam can publish without approval', 'yourjannah' ); ?>
            </label>
            <p style="font-size:11px;color:var(--text-dim);margin-top:2px;"><?php esc_html_e( 'When enabled, the imam\'s announcements and messages go live immediately without admin review.', 'yourjannah' ); ?></p>
        </div>

        <button type="submit" class="d-btn d-btn--primary"><?php echo $imam_user ? esc_html__( 'Update Imam Settings', 'yourjannah' ) : esc_html__( 'Link Imam', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Social Login Notice -->
<div class="d-card" style="background:#f9fafb;">
    <h3><?php esc_html_e( 'Social Login', 'yourjannah' ); ?></h3>
    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
        <span style="font-size:20px;">&#128274;</span>
        <p style="font-size:13px;color:#1e40af;margin:0;"><?php esc_html_e( 'Social login (Google & Facebook) is configured at the platform level. Contact YourJannah support to enable.', 'yourjannah' ); ?></p>
    </div>
</div>
