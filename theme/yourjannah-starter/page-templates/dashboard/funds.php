<?php
/**
 * Dashboard Section: Donation Funds
 * Manage funds shown on the niyyah bar. CRUD, reorder, targets.
 */

$ft = YNJ_DB::table( 'mosque_funds' );

// Handle POST
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_funds' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    if ( $action === 'create' ) {
        $label = sanitize_text_field( $_POST['label'] ?? '' );
        $slug  = sanitize_title( $_POST['slug'] ?? $label );
        $desc  = sanitize_text_field( $_POST['description'] ?? '' );
        $target = (int) ( floatval( $_POST['target'] ?? 0 ) * 100 );
        if ( $label ) {
            $max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM $ft WHERE mosque_id=%d", $mosque_id ) );
            $wpdb->insert( $ft, [ 'mosque_id' => $mosque_id, 'slug' => $slug, 'label' => $label, 'description' => $desc, 'target_pence' => $target, 'sort_order' => $max + 1 ] );
            $success = __( 'Fund created!', 'yourjannah' );
        }
    }

    if ( $action === 'update' ) {
        $fund_id = (int) ( $_POST['fund_id'] ?? 0 );
        $wpdb->update( $ft, [
            'label'        => sanitize_text_field( $_POST['label'] ?? '' ),
            'description'  => sanitize_text_field( $_POST['description'] ?? '' ),
            'target_pence' => (int) ( floatval( $_POST['target'] ?? 0 ) * 100 ),
        ], [ 'id' => $fund_id, 'mosque_id' => $mosque_id ] );
        $success = __( 'Fund updated!', 'yourjannah' );
    }

    if ( $action === 'deactivate' ) {
        $fund_id = (int) ( $_POST['fund_id'] ?? 0 );
        $fund = $wpdb->get_row( $wpdb->prepare( "SELECT is_default FROM $ft WHERE id=%d AND mosque_id=%d", $fund_id, $mosque_id ) );
        if ( $fund && ! $fund->is_default ) {
            $wpdb->update( $ft, [ 'is_active' => 0 ], [ 'id' => $fund_id ] );
            $success = __( 'Fund deactivated.', 'yourjannah' );
        } else {
            $error = __( 'Cannot remove the default General Donation fund.', 'yourjannah' );
        }
    }

    if ( $action === 'activate' ) {
        $fund_id = (int) ( $_POST['fund_id'] ?? 0 );
        $wpdb->update( $ft, [ 'is_active' => 1 ], [ 'id' => $fund_id, 'mosque_id' => $mosque_id ] );
        $success = __( 'Fund reactivated.', 'yourjannah' );
    }
}

// Load funds
$active_funds = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $ft WHERE mosque_id=%d AND is_active=1 ORDER BY is_default DESC, sort_order ASC", $mosque_id ) ) ?: [];
$inactive_funds = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $ft WHERE mosque_id=%d AND is_active=0 ORDER BY label ASC", $mosque_id ) ) ?: [];

// Editing?
$editing = null;
$edit_id = (int) ( $_GET['edit'] ?? 0 );
if ( $edit_id ) {
    $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ft WHERE id=%d AND mosque_id=%d", $edit_id, $mosque_id ) );
}
?>

<div class="d-header">
    <h1>💰 <?php esc_html_e( 'Donation Funds', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Manage the funds shown on your niyyah bar. Donors choose which fund to give to.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Add/Edit Form -->
<div class="d-card">
    <h3><?php echo $editing ? '✏️ ' . esc_html__( 'Edit Fund', 'yourjannah' ) : '➕ ' . esc_html__( 'Add New Fund', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_funds', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ( $editing ) : ?><input type="hidden" name="fund_id" value="<?php echo (int) $editing->id; ?>"><?php endif; ?>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Fund Name *', 'yourjannah' ); ?></label>
                <input type="text" name="label" value="<?php echo esc_attr( $editing->label ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'e.g. New Roof Fund', 'yourjannah' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Target Amount (£)', 'yourjannah' ); ?></label>
                <input type="number" name="target" min="0" step="1" value="<?php echo esc_attr( $editing ? ( $editing->target_pence / 100 ) : '' ); ?>" placeholder="<?php esc_attr_e( '0 = no target', 'yourjannah' ); ?>">
            </div>
        </div>
        <?php if ( ! $editing ) : ?>
        <div class="d-field">
            <label><?php esc_html_e( 'Slug (URL-safe ID)', 'yourjannah' ); ?></label>
            <input type="text" name="slug" placeholder="<?php esc_attr_e( 'auto-generated from name', 'yourjannah' ); ?>">
        </div>
        <?php endif; ?>
        <div class="d-field">
            <label><?php esc_html_e( 'Description (optional)', 'yourjannah' ); ?></label>
            <input type="text" name="description" value="<?php echo esc_attr( $editing->description ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Brief description shown to donors', 'yourjannah' ); ?>">
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="d-btn d-btn--primary"><?php echo $editing ? esc_html__( 'Update Fund', 'yourjannah' ) : esc_html__( 'Add Fund', 'yourjannah' ); ?></button>
            <?php if ( $editing ) : ?><a href="?section=funds" class="d-btn d-btn--outline"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Active Funds -->
<div class="d-card">
    <h3><?php esc_html_e( 'Active Funds', 'yourjannah' ); ?> (<?php echo count( $active_funds ); ?>)</h3>
    <?php if ( empty( $active_funds ) ) : ?>
    <p class="d-empty"><?php esc_html_e( 'No funds configured.', 'yourjannah' ); ?></p>
    <?php else : ?>
    <table class="d-table">
        <thead><tr><th><?php esc_html_e( 'Fund', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Slug', 'yourjannah' ); ?></th><th style="text-align:right;"><?php esc_html_e( 'Target', 'yourjannah' ); ?></th><th style="text-align:right;"><?php esc_html_e( 'Raised', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Actions', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $active_funds as $f ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $f->label ); ?></strong><?php if ( $f->is_default ) echo ' <span class="d-badge d-badge--blue">Default</span>'; ?><?php if ( $f->description ) echo '<br><span style="font-size:11px;color:var(--text-dim);">' . esc_html( $f->description ) . '</span>'; ?></td>
            <td><code style="font-size:11px;"><?php echo esc_html( $f->slug ); ?></code></td>
            <td style="text-align:right;"><?php echo $f->target_pence ? '£' . number_format( $f->target_pence / 100, 0 ) : '—'; ?></td>
            <td style="text-align:right;font-weight:700;color:#16a34a;"><?php echo $f->raised_pence ? '£' . number_format( $f->raised_pence / 100, 0 ) : '£0'; ?></td>
            <td>
                <a href="?section=funds&edit=<?php echo (int) $f->id; ?>" class="d-btn d-btn--sm d-btn--outline">✏️</a>
                <?php if ( ! $f->is_default ) : ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Deactivate this fund?')">
                    <?php wp_nonce_field( 'ynj_dash_funds', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="fund_id" value="<?php echo (int) $f->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger">✕</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Inactive Funds -->
<?php if ( ! empty( $inactive_funds ) ) : ?>
<div class="d-card">
    <h3 style="color:var(--text-dim);"><?php esc_html_e( 'Inactive Funds', 'yourjannah' ); ?></h3>
    <?php foreach ( $inactive_funds as $f ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;">
        <span style="color:var(--text-dim);"><?php echo esc_html( $f->label ); ?></span>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field( 'ynj_dash_funds', '_ynj_nonce' ); ?>
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="fund_id" value="<?php echo (int) $f->id; ?>">
            <button type="submit" class="d-btn d-btn--sm d-btn--outline"><?php esc_html_e( 'Reactivate', 'yourjannah' ); ?></button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
