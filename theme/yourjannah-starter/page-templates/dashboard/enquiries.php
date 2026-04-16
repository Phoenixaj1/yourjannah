<?php
/**
 * Dashboard Section: Enquiries
 */
$eq = YNJ_DB::table( 'enquiries' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_enq' ) ) {
    $eid = (int) ( $_POST['enq_id'] ?? 0 );
    $new_status = sanitize_text_field( $_POST['new_status'] ?? '' );
    $reply = sanitize_textarea_field( $_POST['admin_reply'] ?? '' );
    if ( $eid && $new_status ) {
        $update = [ 'status' => $new_status ];
        if ( $reply ) $update['admin_notes'] = $reply;
        $wpdb->update( $eq, $update, [ 'id' => $eid, 'mosque_id' => $mosque_id ] );
        $success = __( 'Enquiry updated.', 'yourjannah' );
    }
}

$filter = sanitize_text_field( $_GET['status'] ?? '' );
$where = $filter ? $wpdb->prepare( "AND status=%s", $filter ) : '';
$enquiries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $eq WHERE mosque_id=%d $where ORDER BY created_at DESC LIMIT 50", $mosque_id ) ) ?: [];
?>
<div class="d-header"><h1>✉️ <?php esc_html_e( 'Enquiries', 'yourjannah' ); ?></h1></div>
<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="?section=enquiries" class="d-btn d-btn--<?php echo !$filter ? 'primary' : 'outline'; ?> d-btn--sm">All</a>
    <a href="?section=enquiries&status=new" class="d-btn d-btn--<?php echo $filter==='new' ? 'primary' : 'outline'; ?> d-btn--sm">🔴 New</a>
    <a href="?section=enquiries&status=read" class="d-btn d-btn--<?php echo $filter==='read' ? 'primary' : 'outline'; ?> d-btn--sm">Read</a>
    <a href="?section=enquiries&status=replied" class="d-btn d-btn--<?php echo $filter==='replied' ? 'primary' : 'outline'; ?> d-btn--sm">Replied</a>
    <a href="?section=enquiries&status=resolved" class="d-btn d-btn--<?php echo $filter==='resolved' ? 'primary' : 'outline'; ?> d-btn--sm">✅ Resolved</a>
</div>

<?php if ( empty( $enquiries ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">✉️</div><p><?php esc_html_e( 'No enquiries yet.', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<?php foreach ( $enquiries as $e ) : ?>
<div class="d-card" style="border-left:3px solid <?php echo $e->status==='new' ? '#dc2626' : ($e->status==='replied' ? '#16a34a' : '#6b7280'); ?>;">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
        <div><strong><?php echo esc_html( $e->name ); ?></strong> <span style="color:var(--text-dim);font-size:12px;">&lt;<?php echo esc_html( $e->email ); ?>&gt;</span></div>
        <span class="d-badge d-badge--<?php echo $e->status==='new'?'red':($e->status==='replied'?'green':'gray'); ?>"><?php echo esc_html( ucfirst( $e->status ) ); ?></span>
    </div>
    <?php if ( $e->subject ) : ?><p style="font-weight:600;margin-bottom:4px;"><?php echo esc_html( $e->subject ); ?></p><?php endif; ?>
    <p style="font-size:13px;color:#333;margin-bottom:8px;"><?php echo nl2br( esc_html( $e->message ) ); ?></p>
    <p style="font-size:11px;color:var(--text-dim);"><?php echo esc_html( $e->created_at ); ?></p>
    <?php if ( $e->admin_notes ) : ?><div style="background:#f0f9ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px;margin-top:8px;"><strong style="font-size:11px;color:#1e40af;">Your reply:</strong><p style="font-size:13px;color:#1e40af;margin:4px 0 0;"><?php echo nl2br( esc_html( $e->admin_notes ) ); ?></p></div><?php endif; ?>
    <form method="post" style="display:flex;gap:8px;margin-top:8px;align-items:end;">
        <?php wp_nonce_field( 'ynj_dash_enq', '_ynj_nonce' ); ?>
        <input type="hidden" name="enq_id" value="<?php echo (int) $e->id; ?>">
        <textarea name="admin_reply" rows="2" placeholder="Reply..." style="flex:1;padding:8px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;"></textarea>
        <select name="new_status" style="padding:8px;border:1px solid var(--border);border-radius:8px;font-size:12px;">
            <option value="read">Read</option><option value="replied" selected>Replied</option><option value="resolved">Resolved</option>
        </select>
        <button type="submit" class="d-btn d-btn--sm d-btn--primary">Send</button>
    </form>
</div>
<?php endforeach; endif; ?>
