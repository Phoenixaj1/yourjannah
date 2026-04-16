<?php
/**
 * Dashboard Section: Subscribers (read-only list + CSV export)
 */
$sub_t = YNJ_DB::table( 'subscribers' );
$usr_t = YNJ_DB::table( 'user_subscriptions' );
$ut = YNJ_DB::table( 'users' );

$subscribers = $wpdb->get_results( $wpdb->prepare(
    "SELECT email, name, phone, 'push' AS type, subscribed_at FROM $sub_t WHERE mosque_id=%d AND status='active'
     UNION
     SELECT u.email, u.name, u.phone, 'member' AS type, s.subscribed_at FROM $usr_t s JOIN $ut u ON u.id=s.user_id WHERE s.mosque_id=%d AND s.status='active'
     ORDER BY subscribed_at DESC", $mosque_id, $mosque_id
) ) ?: [];

// Dedupe by email
$seen = []; $unique = [];
foreach ( $subscribers as $s ) {
    $key = strtolower( $s->email );
    if ( isset( $seen[$key] ) ) continue;
    $seen[$key] = true; $unique[] = $s;
}
?>
<?php
// Handle CSV export
if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) {
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=subscribers-' . $mosque_slug . '-' . date( 'Y-m-d' ) . '.csv' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, [ 'Name', 'Email', 'Phone', 'Type', 'Joined' ] );
    foreach ( $unique as $s ) {
        fputcsv( $out, [ $s->name, $s->email, $s->phone, $s->type, $s->subscribed_at ] );
    }
    fclose( $out );
    exit;
}
?>
<div class="d-header">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>👥 <?php esc_html_e( 'Subscribers', 'yourjannah' ); ?> (<?php echo count( $unique ); ?>)</h1>
        <?php if ( ! empty( $unique ) ) : ?>
        <a href="?section=subscribers&export=csv" class="d-btn d-btn--outline d-btn--sm">📥 <?php esc_html_e( 'Export CSV', 'yourjannah' ); ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ( empty( $unique ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">👥</div><p><?php esc_html_e( 'No subscribers yet. Share your mosque page to grow your audience!', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Type</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ( $unique as $s ) : ?>
        <tr>
            <td><?php echo esc_html( $s->name ?: '—' ); ?></td>
            <td style="font-size:12px;"><?php echo esc_html( $s->email ); ?></td>
            <td style="font-size:12px;"><?php echo esc_html( $s->phone ?: '—' ); ?></td>
            <td><span class="d-badge d-badge--<?php echo $s->type==='member'?'blue':'gray'; ?>"><?php echo esc_html( ucfirst( $s->type ) ); ?></span></td>
            <td style="font-size:12px;"><?php echo $s->subscribed_at ? esc_html( substr( $s->subscribed_at, 0, 10 ) ) : '—'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
