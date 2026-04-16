<?php
/**
 * Dashboard Section: Prayer Times (Jumu'ah + Eid management)
 */
$jt = YNJ_DB::table( 'jumuah_slots' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_prayers' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    if ( $action === 'add_jumuah' ) {
        $wpdb->insert( $jt, [
            'mosque_id'    => $mosque_id,
            'slot_name'    => sanitize_text_field( $_POST['slot_name'] ?? 'Jumu\'ah' ),
            'khutbah_time' => sanitize_text_field( $_POST['khutbah_time'] ?? '' ),
            'salah_time'   => sanitize_text_field( $_POST['salah_time'] ?? '' ),
            'language'     => sanitize_text_field( $_POST['language'] ?? '' ),
            'status'       => 'active',
        ] );
        $success = __( 'Jumu\'ah slot added!', 'yourjannah' );
    }
    if ( $action === 'delete_jumuah' ) {
        $wpdb->delete( $jt, [ 'id' => (int) $_POST['slot_id'], 'mosque_id' => $mosque_id ] );
        $success = __( 'Slot removed.', 'yourjannah' );
    }
}

$slots = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $jt WHERE mosque_id=%d AND status='active' ORDER BY salah_time ASC", $mosque_id ) ) ?: [];
?>
<div class="d-header"><h1>🕐 <?php esc_html_e( 'Prayer Times', 'yourjannah' ); ?></h1><p><?php esc_html_e( 'Daily prayer times come automatically from Aladhan. Manage Jumu\'ah slots below.', 'yourjannah' ); ?></p></div>
<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>

<div class="d-card">
    <h3>🕌 <?php esc_html_e( 'Jumu\'ah Slots', 'yourjannah' ); ?></h3>
    <?php if ( $slots ) : ?>
    <table class="d-table" style="margin-bottom:16px;">
        <thead><tr><th>Slot</th><th>Khutbah</th><th>Salah</th><th>Language</th><th></th></tr></thead>
        <tbody>
        <?php foreach ( $slots as $s ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $s->slot_name ); ?></strong></td>
            <td><?php echo esc_html( substr( $s->khutbah_time, 0, 5 ) ); ?></td>
            <td><?php echo esc_html( substr( $s->salah_time, 0, 5 ) ); ?></td>
            <td><?php echo esc_html( $s->language ?: '—' ); ?></td>
            <td><form method="post" style="display:inline;" onsubmit="return confirm('Remove?')"><?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?><input type="hidden" name="action" value="delete_jumuah"><input type="hidden" name="slot_id" value="<?php echo (int) $s->id; ?>"><button class="d-btn d-btn--sm d-btn--danger">🗑️</button></form></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p style="color:var(--text-dim);margin-bottom:16px;"><?php esc_html_e( 'No Jumu\'ah slots configured yet.', 'yourjannah' ); ?></p>
    <?php endif; ?>

    <h4 style="margin-bottom:8px;"><?php esc_html_e( 'Add Jumu\'ah Slot', 'yourjannah' ); ?></h4>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="add_jumuah">
        <div class="d-row">
            <div class="d-field"><label>Slot Name</label><input type="text" name="slot_name" value="Jumu'ah" placeholder="e.g. 1st Jumu'ah"></div>
            <div class="d-field"><label>Language</label><input type="text" name="language" placeholder="e.g. English, Arabic, Urdu"></div>
        </div>
        <div class="d-row">
            <div class="d-field"><label>Khutbah Time</label><input type="time" name="khutbah_time" required></div>
            <div class="d-field"><label>Salah Time</label><input type="time" name="salah_time" required></div>
        </div>
        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Add Slot', 'yourjannah' ); ?></button>
    </form>
</div>

<div class="d-card" style="background:var(--primary-light);">
    <p style="font-size:13px;color:var(--primary-dark);">ℹ️ <?php esc_html_e( 'Daily adhan times (Fajr, Dhuhr, Asr, Maghrib, Isha) are automatically calculated from the Aladhan API based on your mosque\'s GPS coordinates. No configuration needed.', 'yourjannah' ); ?></p>
</div>
