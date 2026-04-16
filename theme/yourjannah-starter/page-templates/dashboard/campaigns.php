<?php
/**
 * Dashboard Section: Fundraising Campaigns CRUD
 */
$ct = YNJ_DB::table( 'campaigns' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_camp' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    if ( $action === 'create' || $action === 'update' ) {
        $data = [ 'mosque_id' => $mosque_id, 'title' => sanitize_text_field( $_POST['title'] ?? '' ), 'description' => sanitize_textarea_field( $_POST['description'] ?? '' ), 'category' => sanitize_text_field( $_POST['category'] ?? 'general' ), 'target_pence' => (int) ( floatval( $_POST['target'] ?? 0 ) * 100 ), 'recurring' => (int) ( $_POST['recurring'] ?? 0 ), 'dfm_link' => esc_url_raw( $_POST['dfm_link'] ?? '' ), 'status' => sanitize_text_field( $_POST['status'] ?? 'active' ) ];
        if ( ! $data['title'] ) { $error = 'Title required.'; }
        elseif ( $action === 'create' ) { $wpdb->insert( $ct, $data ); $success = 'Campaign created!'; }
        else { $cid = (int) $_POST['camp_id']; unset( $data['mosque_id'] ); $wpdb->update( $ct, $data, [ 'id' => $cid, 'mosque_id' => $mosque_id ] ); $success = 'Campaign updated!'; }
    }
    if ( $action === 'delete' ) { $wpdb->delete( $ct, [ 'id' => (int) $_POST['camp_id'], 'mosque_id' => $mosque_id ] ); $success = 'Campaign deleted.'; }
}

$campaigns = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $ct WHERE mosque_id=%d ORDER BY id DESC LIMIT 50", $mosque_id ) ) ?: [];
$editing = null; if ( $eid = (int) ( $_GET['edit'] ?? 0 ) ) $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ct WHERE id=%d AND mosque_id=%d", $eid, $mosque_id ) );
$cats = ['general','welfare','expansion','renovation','education','youth','emergency','equipment','roof','heating','parking','sisters'];
?>
<div class="d-header"><h1>💝 <?php esc_html_e( 'Fundraising Campaigns', 'yourjannah' ); ?></h1></div>
<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<div class="d-card">
    <h3><?php echo $editing ? '✏️ Edit Campaign' : '➕ New Campaign'; ?></h3>
    <form method="post"><?php wp_nonce_field( 'ynj_dash_camp', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ( $editing ) : ?><input type="hidden" name="camp_id" value="<?php echo (int) $editing->id; ?>"><?php endif; ?>
        <div class="d-field"><label>Campaign Title *</label><input type="text" name="title" value="<?php echo esc_attr( $editing->title ?? '' ); ?>" required></div>
        <div class="d-field"><label>Description</label><textarea name="description" rows="3"><?php echo esc_textarea( $editing->description ?? '' ); ?></textarea></div>
        <div class="d-row">
            <div class="d-field"><label>Category</label><select name="category"><?php foreach ( $cats as $c ) : ?><option value="<?php echo $c; ?>" <?php selected( $editing->category ?? '', $c ); ?>><?php echo ucfirst( $c ); ?></option><?php endforeach; ?></select></div>
            <div class="d-field"><label>Target (£)</label><input type="number" name="target" min="0" step="1" value="<?php echo esc_attr( ( $editing->target_pence ?? 0 ) / 100 ); ?>"></div>
        </div>
        <div class="d-row">
            <div class="d-field"><label>Status</label><select name="status"><option value="active" <?php selected( $editing->status ?? '', 'active' ); ?>>Active</option><option value="paused" <?php selected( $editing->status ?? '', 'paused' ); ?>>Paused</option><option value="completed" <?php selected( $editing->status ?? '', 'completed' ); ?>>Completed</option></select></div>
            <div class="d-field"><label><input type="checkbox" name="recurring" value="1" <?php checked( $editing->recurring ?? 0, 1 ); ?>> Monthly recurring campaign</label></div>
        </div>
        <div class="d-field"><label>DonationForMasjid Link (optional)</label><input type="url" name="dfm_link" value="<?php echo esc_attr( $editing->dfm_link ?? '' ); ?>" placeholder="https://donationformasjid.com/..."></div>
        <div style="display:flex;gap:8px;"><button type="submit" class="d-btn d-btn--primary"><?php echo $editing ? 'Update' : 'Create Campaign'; ?></button><?php if ( $editing ) : ?><a href="?section=campaigns" class="d-btn d-btn--outline">Cancel</a><?php endif; ?></div>
    </form>
</div>

<?php if ( $campaigns ) : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th>Campaign</th><th>Category</th><th style="text-align:right;">Target</th><th style="text-align:right;">Raised</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ( $campaigns as $c ) : $pct = $c->target_pence > 0 ? min( 100, round( $c->raised_pence / $c->target_pence * 100 ) ) : 0; ?>
        <tr>
            <td><strong><?php echo esc_html( $c->title ); ?></strong><?php if ( $c->recurring ) echo ' <span class="d-badge d-badge--blue">🔄 Monthly</span>'; ?></td>
            <td><span class="d-badge d-badge--gray"><?php echo esc_html( ucfirst( $c->category ) ); ?></span></td>
            <td style="text-align:right;"><?php echo $c->target_pence ? '£' . number_format( $c->target_pence / 100, 0 ) : '—'; ?></td>
            <td style="text-align:right;">
                <strong style="color:#16a34a;">£<?php echo number_format( ( $c->raised_pence ?? 0 ) / 100, 0 ); ?></strong>
                <?php if ( $c->target_pence > 0 ) : ?>
                <div style="background:#e5e7eb;border-radius:4px;height:6px;margin-top:4px;overflow:hidden;"><div style="background:#16a34a;height:100%;width:<?php echo $pct; ?>%;border-radius:4px;"></div></div>
                <span style="font-size:10px;color:var(--text-dim);"><?php echo $pct; ?>%</span>
                <?php endif; ?>
            </td>
            <td><span class="d-badge d-badge--<?php echo $c->status==='active'?'green':($c->status==='completed'?'blue':'yellow'); ?>"><?php echo esc_html( ucfirst( $c->status ) ); ?></span></td>
            <td><a href="?section=campaigns&edit=<?php echo (int) $c->id; ?>" class="d-btn d-btn--sm d-btn--outline">✏️</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete?')"><?php wp_nonce_field( 'ynj_dash_camp', '_ynj_nonce' ); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="camp_id" value="<?php echo (int) $c->id; ?>"><button class="d-btn d-btn--sm d-btn--danger">🗑️</button></form></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
