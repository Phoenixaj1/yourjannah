<?php
/**
 * Dashboard Section: Team Management
 *
 * Invite team members, assign roles, manage the mosque admin team.
 * Roles: Admin (full), Imam (announcements+broadcasts), Coordinator (events+classes).
 *
 * @package YourJannah
 * @since   3.9.8
 */

$mt = YNJ_DB::table( 'mosques' );

// Available roles for invite
$available_roles = [
    'ynj_mosque_admin' => [ 'label' => 'Admin',       'desc' => 'Full access to all dashboard sections',         'icon' => '👑' ],
    'ynj_imam'         => [ 'label' => 'Imam',        'desc' => 'Announcements & broadcasts',                    'icon' => '🕌' ],
    'ynj_coordinator'  => [ 'label' => 'Coordinator', 'desc' => 'Events, classes & subscriber management',       'icon' => '📋' ],
];

// Handle POST actions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_team' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    // Invite team member
    if ( $action === 'invite' ) {
        $invite_email = sanitize_email( $_POST['invite_email'] ?? '' );
        $invite_role  = sanitize_text_field( $_POST['invite_role'] ?? 'ynj_coordinator' );
        if ( ! isset( $available_roles[ $invite_role ] ) ) $invite_role = 'ynj_coordinator';

        if ( is_email( $invite_email ) ) {
            // Check if user already exists
            $existing = get_user_by( 'email', $invite_email );
            if ( $existing ) {
                // Add role + mosque meta
                $existing->add_role( $invite_role );
                update_user_meta( $existing->ID, 'ynj_mosque_id', $mosque_id );
                $ids = get_user_meta( $existing->ID, 'ynj_mosque_ids', true ) ?: [];
                if ( ! in_array( $mosque_id, $ids, true ) ) {
                    $ids[] = $mosque_id;
                    update_user_meta( $existing->ID, 'ynj_mosque_ids', $ids );
                }
                $role_label = $available_roles[ $invite_role ]['label'] ?? $invite_role;
                wp_mail( $invite_email,
                    "You've been added as {$role_label} — " . $mosque_name,
                    "Assalamu alaikum,\n\nYou've been added as {$role_label} for {$mosque_name} on YourJannah.\n\nLog in at: " . home_url( '/dashboard' ) . "\n\nJazakAllah khayr."
                );
                $success = sprintf( __( '%s added as %s.', 'yourjannah' ), $invite_email, $role_label );
            } else {
                // Create new user with temp password
                $temp_pass  = wp_generate_password( 12, false );
                $username   = sanitize_user( str_replace( '@', '_', $invite_email ), true );
                $wp_user_id = wp_create_user( $username, $temp_pass, $invite_email );

                if ( ! is_wp_error( $wp_user_id ) ) {
                    $wp_user = new WP_User( $wp_user_id );
                    $wp_user->set_role( $invite_role );
                    update_user_meta( $wp_user_id, 'ynj_mosque_id', $mosque_id );
                    update_user_meta( $wp_user_id, 'ynj_mosque_ids', [ $mosque_id ] );

                    $role_label = $available_roles[ $invite_role ]['label'] ?? $invite_role;
                    wp_mail( $invite_email,
                        "You're invited to manage {$mosque_name} on YourJannah",
                        "Assalamu alaikum,\n\nYou've been invited as {$role_label} for {$mosque_name} on YourJannah.\n\n"
                        . "Log in at: " . home_url( '/dashboard' ) . "\n"
                        . "Email: {$invite_email}\n"
                        . "Temporary password: {$temp_pass}\n\n"
                        . "Please change your password after first login.\n\nJazakAllah khayr."
                    );
                    $success = sprintf( __( 'Invite sent to %s as %s.', 'yourjannah' ), $invite_email, $role_label );
                } else {
                    $error = $wp_user_id->get_error_message();
                }
            }
        } else {
            $error = __( 'Please enter a valid email address.', 'yourjannah' );
        }
    }

    // Remove team member
    if ( $action === 'remove_member' ) {
        $remove_user_id = (int) ( $_POST['remove_user_id'] ?? 0 );
        if ( $remove_user_id && $remove_user_id !== $wp_uid ) {
            $remove_user = get_userdata( $remove_user_id );
            if ( $remove_user ) {
                $remove_user->remove_role( 'ynj_mosque_admin' );
                $remove_user->remove_role( 'ynj_imam' );
                $remove_user->remove_role( 'ynj_coordinator' );
                delete_user_meta( $remove_user_id, 'ynj_mosque_id' );
                delete_user_meta( $remove_user_id, 'ynj_mosque_ids' );
                $success = sprintf( __( '%s removed from team.', 'yourjannah' ), $remove_user->display_name ?: $remove_user->user_email );
            }
        }
    }

    // Change role
    if ( $action === 'change_role' ) {
        $target_user_id = (int) ( $_POST['target_user_id'] ?? 0 );
        $new_role       = sanitize_text_field( $_POST['new_role'] ?? '' );
        if ( $target_user_id && isset( $available_roles[ $new_role ] ) && $target_user_id !== $wp_uid ) {
            $target_user = get_userdata( $target_user_id );
            if ( $target_user ) {
                $target_user->remove_role( 'ynj_mosque_admin' );
                $target_user->remove_role( 'ynj_imam' );
                $target_user->remove_role( 'ynj_coordinator' );
                $target_user->add_role( $new_role );
                $success = sprintf( __( 'Role updated for %s.', 'yourjannah' ), $target_user->display_name ?: $target_user->user_email );
            }
        }
    }
}

// Load team members
$team_roles = [ 'ynj_mosque_admin', 'ynj_imam', 'ynj_coordinator' ];
$team_members = get_users( [
    'meta_key'   => 'ynj_mosque_id',
    'meta_value' => $mosque_id,
    'role__in'   => $team_roles,
] );

// Also check for WP admins associated via mosque_ids array
$wp_admins = get_users( [ 'role' => 'administrator' ] );
foreach ( $wp_admins as $adm ) {
    $adm_mosque_id = (int) get_user_meta( $adm->ID, 'ynj_mosque_id', true );
    if ( $adm_mosque_id === $mosque_id ) {
        $already = false;
        foreach ( $team_members as $tm ) { if ( $tm->ID === $adm->ID ) { $already = true; break; } }
        if ( ! $already ) $team_members[] = $adm;
    }
}
?>

<div class="d-header">
    <h1>👥 <?php esc_html_e( 'Team', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Manage who can access your mosque dashboard and what they can do.', 'yourjannah' ); ?></p>
</div>

<!-- Invite Form -->
<div class="d-card" style="border-left:4px solid var(--primary);">
    <h3>➕ <?php esc_html_e( 'Invite Team Member', 'yourjannah' ); ?></h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
        <?php esc_html_e( 'Enter their email and choose a role. If they don\'t have an account, one will be created with a temporary password.', 'yourjannah' ); ?>
    </p>
    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <?php wp_nonce_field( 'ynj_dash_team', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="invite">
        <div class="d-field" style="margin:0;flex:1;min-width:200px;">
            <label><?php esc_html_e( 'Email', 'yourjannah' ); ?></label>
            <input type="email" name="invite_email" required placeholder="name@example.com">
        </div>
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Role', 'yourjannah' ); ?></label>
            <select name="invite_role" style="padding:10px 12px;">
                <?php foreach ( $available_roles as $role_key => $role_info ) : ?>
                <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_info['icon'] . ' ' . $role_info['label'] . ' — ' . $role_info['desc'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="d-btn d-btn--primary">📨 <?php esc_html_e( 'Send Invite', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Role Guide -->
<div class="d-card">
    <h3>📋 <?php esc_html_e( 'Role Guide', 'yourjannah' ); ?></h3>
    <div style="display:grid;gap:8px;">
        <?php foreach ( $available_roles as $role_key => $role_info ) : ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f9fafb;border:1px solid var(--border);border-radius:10px;">
            <span style="font-size:24px;"><?php echo $role_info['icon']; ?></span>
            <div>
                <strong style="font-size:14px;"><?php echo esc_html( $role_info['label'] ); ?></strong>
                <p style="font-size:12px;color:var(--text-dim);margin:0;"><?php echo esc_html( $role_info['desc'] ); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Team List -->
<div class="d-card">
    <h3>👥 <?php printf( esc_html__( 'Team Members (%d)', 'yourjannah' ), count( $team_members ) ); ?></h3>

    <?php if ( empty( $team_members ) ) : ?>
    <div class="d-empty">
        <div class="d-empty__icon">👥</div>
        <p><?php esc_html_e( 'No team members yet. Invite someone above!', 'yourjannah' ); ?></p>
    </div>
    <?php else : ?>
    <table class="d-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Email', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Role', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Joined', 'yourjannah' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $team_members as $member ) :
            // Determine role
            $member_role = 'unknown';
            $member_role_label = '';
            $member_role_icon = '';
            foreach ( $team_roles as $check_role ) {
                if ( in_array( $check_role, (array) $member->roles, true ) ) {
                    $member_role = $check_role;
                    $member_role_label = $available_roles[ $check_role ]['label'] ?? $check_role;
                    $member_role_icon  = $available_roles[ $check_role ]['icon'] ?? '';
                    break;
                }
            }
            if ( in_array( 'administrator', (array) $member->roles, true ) ) {
                $member_role = 'administrator';
                $member_role_label = 'Platform Admin';
                $member_role_icon = '🔑';
            }
            $is_self = ( $member->ID === $wp_uid );
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html( $member->display_name ?: $member->user_login ); ?></strong>
                <?php if ( $is_self ) : ?><span class="d-badge d-badge--green" style="margin-left:4px;"><?php esc_html_e( 'You', 'yourjannah' ); ?></span><?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php echo esc_html( $member->user_email ); ?></td>
            <td>
                <?php if ( ! $is_self && $member_role !== 'administrator' ) : ?>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field( 'ynj_dash_team', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="target_user_id" value="<?php echo (int) $member->ID; ?>">
                    <select name="new_role" onchange="this.form.submit()" style="padding:4px 8px;font-size:12px;border:1px solid var(--border);border-radius:6px;">
                        <?php foreach ( $available_roles as $rk => $ri ) : ?>
                        <option value="<?php echo esc_attr( $rk ); ?>" <?php selected( $member_role, $rk ); ?>><?php echo esc_html( $ri['icon'] . ' ' . $ri['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php else : ?>
                <span style="font-size:13px;"><?php echo esc_html( $member_role_icon . ' ' . $member_role_label ); ?></span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php echo esc_html( date( 'j M Y', strtotime( $member->user_registered ) ) ); ?></td>
            <td>
                <?php if ( ! $is_self && $member_role !== 'administrator' ) : ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Remove <?php echo esc_js( $member->display_name ); ?> from the team?');">
                    <?php wp_nonce_field( 'ynj_dash_team', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="remove_user_id" value="<?php echo (int) $member->ID; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger">🗑️</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
