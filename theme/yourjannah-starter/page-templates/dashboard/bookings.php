<?php
/**
 * Dashboard Section: Bookings (approve/reject)
 */
$bk = YNJ_DB::table( 'bookings' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_bk' ) ) {
    $bid = (int) ( $_POST['booking_id'] ?? 0 );
    $new_status = sanitize_text_field( $_POST['new_status'] ?? '' );
    if ( $bid && $new_status ) {
        $wpdb->update( $bk, [ 'status' => $new_status ], [ 'id' => $bid, 'mosque_id' => $mosque_id ] );
        $success = __( 'Booking updated.', 'yourjannah' );
    }
}

$filter = sanitize_text_field( $_GET['status'] ?? '' );
$where = $filter ? $wpdb->prepare( "AND status=%s", $filter ) : '';
$rt = YNJ_DB::table( 'rooms' );
$bookings = $wpdb->get_results( $wpdb->prepare(
    "SELECT b.*, r.name AS room_name FROM $bk b LEFT JOIN $rt r ON r.id = b.room_id WHERE b.mosque_id=%d $where ORDER BY b.created_at DESC LIMIT 50",
    $mosque_id
) ) ?: [];
?>
<div class="d-header"><h1>📋 <?php esc_html_e( 'Bookings', 'yourjannah' ); ?></h1></div>
<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="?section=bookings" class="d-btn d-btn--<?php echo !$filter?'primary':'outline'; ?> d-btn--sm">All</a>
    <a href="?section=bookings&status=pending" class="d-btn d-btn--<?php echo $filter==='pending'?'primary':'outline'; ?> d-btn--sm">Pending</a>
    <a href="?section=bookings&status=confirmed" class="d-btn d-btn--<?php echo $filter==='confirmed'?'primary':'outline'; ?> d-btn--sm">Confirmed</a>
    <a href="?section=bookings&status=cancelled" class="d-btn d-btn--<?php echo $filter==='cancelled'?'primary':'outline'; ?> d-btn--sm">Cancelled</a>
</div>

<?php if ( empty( $bookings ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">📋</div><p><?php esc_html_e( 'No bookings yet.', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th>Guest</th><th>Type</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ( $bookings as $b ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $b->user_name ); ?></strong><br><span style="font-size:11px;color:var(--text-dim);"><?php echo esc_html( $b->user_email ); ?></span></td>
            <td><?php echo esc_html( $b->room_name ?: ( $b->booking_type ?? 'Room' ) ); ?></td>
            <td style="font-size:12px;"><?php echo esc_html( $b->booking_date ?? substr( $b->created_at, 0, 10 ) ); ?></td>
            <td><span class="d-badge d-badge--<?php echo $b->status==='confirmed'?'green':($b->status==='pending'?'yellow':'red'); ?>"><?php echo esc_html( ucfirst( $b->status ) ); ?></span></td>
            <td>
                <?php if ( $b->status === 'pending' ) : ?>
                <form method="post" style="display:inline;"><?php wp_nonce_field( 'ynj_dash_bk', '_ynj_nonce' ); ?><input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>"><input type="hidden" name="new_status" value="confirmed"><button class="d-btn d-btn--sm d-btn--primary">✓ Approve</button></form>
                <form method="post" style="display:inline;"><?php wp_nonce_field( 'ynj_dash_bk', '_ynj_nonce' ); ?><input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>"><input type="hidden" name="new_status" value="cancelled"><button class="d-btn d-btn--sm d-btn--danger">✕ Reject</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
