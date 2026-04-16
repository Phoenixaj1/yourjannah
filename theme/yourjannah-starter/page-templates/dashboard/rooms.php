<?php
/**
 * Dashboard Section: Rooms CRUD
 */
$rt = YNJ_DB::table( 'rooms' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_rooms' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    if ( $action === 'create' || $action === 'update' ) {
        $data = [ 'mosque_id' => $mosque_id, 'name' => sanitize_text_field( $_POST['name'] ?? '' ), 'description' => sanitize_textarea_field( $_POST['description'] ?? '' ), 'capacity' => (int) ( $_POST['capacity'] ?? 0 ), 'hourly_rate_pence' => (int) ( floatval( $_POST['hourly_rate'] ?? 0 ) * 100 ), 'daily_rate_pence' => (int) ( floatval( $_POST['daily_rate'] ?? 0 ) * 100 ) ];
        if ( ! $data['name'] ) { $error = 'Room name required.'; }
        elseif ( $action === 'create' ) { $wpdb->insert( $rt, $data ); $success = 'Room added!'; }
        else { $rid = (int) $_POST['room_id']; unset( $data['mosque_id'] ); $wpdb->update( $rt, $data, [ 'id' => $rid, 'mosque_id' => $mosque_id ] ); $success = 'Room updated!'; }
    }
    if ( $action === 'delete' ) { $wpdb->delete( $rt, [ 'id' => (int) $_POST['room_id'], 'mosque_id' => $mosque_id ] ); $success = 'Room deleted.'; }
}

$rooms = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $rt WHERE mosque_id=%d ORDER BY name ASC", $mosque_id ) ) ?: [];
$editing = null; if ( $eid = (int) ( $_GET['edit'] ?? 0 ) ) $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $rt WHERE id=%d AND mosque_id=%d", $eid, $mosque_id ) );
?>
<div class="d-header"><h1>🏠 <?php esc_html_e( 'Rooms', 'yourjannah' ); ?></h1></div>
<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<div class="d-card">
    <h3><?php echo $editing ? '✏️ Edit Room' : '➕ Add Room'; ?></h3>
    <form method="post"><?php wp_nonce_field( 'ynj_dash_rooms', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ( $editing ) : ?><input type="hidden" name="room_id" value="<?php echo (int) $editing->id; ?>"><?php endif; ?>
        <div class="d-field"><label>Room Name *</label><input type="text" name="name" value="<?php echo esc_attr( $editing->name ?? '' ); ?>" required></div>
        <div class="d-field"><label>Description</label><textarea name="description" rows="2"><?php echo esc_textarea( $editing->description ?? '' ); ?></textarea></div>
        <div class="d-row">
            <div class="d-field"><label>Capacity</label><input type="number" name="capacity" min="0" value="<?php echo esc_attr( $editing->capacity ?? 0 ); ?>"></div>
            <div class="d-field"><label>Hourly Rate (£)</label><input type="number" name="hourly_rate" min="0" step="0.01" value="<?php echo esc_attr( ( $editing->hourly_rate_pence ?? 0 ) / 100 ); ?>"></div>
        </div>
        <div class="d-field"><label>Daily Rate (£)</label><input type="number" name="daily_rate" min="0" step="0.01" value="<?php echo esc_attr( ( $editing->daily_rate_pence ?? 0 ) / 100 ); ?>"></div>
        <div style="display:flex;gap:8px;"><button type="submit" class="d-btn d-btn--primary"><?php echo $editing ? 'Update' : 'Add Room'; ?></button><?php if ( $editing ) : ?><a href="?section=rooms" class="d-btn d-btn--outline">Cancel</a><?php endif; ?></div>
    </form>
</div>

<?php if ( $rooms ) : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th>Room</th><th>Capacity</th><th style="text-align:right;">Hourly</th><th style="text-align:right;">Daily</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ( $rooms as $r ) : ?>
        <tr><td><strong><?php echo esc_html( $r->name ); ?></strong></td><td><?php echo (int) $r->capacity; ?></td><td style="text-align:right;">£<?php echo number_format( $r->hourly_rate_pence / 100, 0 ); ?></td><td style="text-align:right;">£<?php echo number_format( $r->daily_rate_pence / 100, 0 ); ?></td>
        <td><a href="?section=rooms&edit=<?php echo (int) $r->id; ?>" class="d-btn d-btn--sm d-btn--outline">✏️</a>
        <form method="post" style="display:inline;" onsubmit="return confirm('Delete?')"><?php wp_nonce_field( 'ynj_dash_rooms', '_ynj_nonce' ); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="room_id" value="<?php echo (int) $r->id; ?>"><button class="d-btn d-btn--sm d-btn--danger">🗑️</button></form></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
