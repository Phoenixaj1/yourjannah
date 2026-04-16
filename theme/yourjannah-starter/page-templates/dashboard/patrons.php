<?php
/**
 * Dashboard Section: Patrons
 * View monthly patrons, revenue breakdown by tier. Read-only.
 */

$pt = YNJ_DB::table( 'patrons' );

$patrons = $wpdb->get_results( $wpdb->prepare(
    "SELECT user_name, user_email, tier, amount_pence, status, started_at, cancelled_at
     FROM $pt WHERE mosque_id = %d ORDER BY status ASC, amount_pence DESC",
    $mosque_id
) ) ?: [];

$active = array_filter( $patrons, function( $p ) { return $p->status === 'active'; } );
$mrr = array_sum( array_map( function( $p ) { return (int) $p->amount_pence; }, $active ) );
$tier_labels = [ 'supporter' => 'Bronze £5', 'guardian' => 'Silver £10', 'champion' => 'Gold £20', 'platinum' => 'Platinum £50' ];
$tier_icons = [ 'supporter' => '🥉', 'guardian' => '🥈', 'champion' => '🥇', 'platinum' => '💎' ];
?>

<div class="d-header">
    <h1>🏅 <?php esc_html_e( 'Patrons', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Your monthly supporters — recurring revenue for your mosque.', 'yourjannah' ); ?></p>
</div>

<div class="d-stats">
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Active Patrons', 'yourjannah' ); ?></div><div class="d-stat__value"><?php echo count( $active ); ?></div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Monthly Revenue', 'yourjannah' ); ?></div><div class="d-stat__value" style="color:#16a34a;">£<?php echo number_format( $mrr / 100, 0 ); ?></div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Yearly Projection', 'yourjannah' ); ?></div><div class="d-stat__value">£<?php echo number_format( $mrr * 12 / 100, 0 ); ?></div></div>
</div>

<!-- Share patron link -->
<div class="d-card" style="background:var(--primary-light);border-color:var(--primary);">
    <p style="font-size:13px;font-weight:600;color:var(--primary-dark);">📣 <?php esc_html_e( 'Share your patron page to grow monthly supporters:', 'yourjannah' ); ?></p>
    <code style="display:block;padding:10px;background:#fff;border-radius:8px;font-size:14px;margin-top:6px;word-break:break-all;"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug . '/patron' ) ); ?></code>
</div>

<!-- Marketing Tips -->
<div class="d-card" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #f59e0b;">
    <h3 style="color:#92400e;">💡 <?php esc_html_e( 'How to Get More Patrons', 'yourjannah' ); ?></h3>
    <div style="font-size:13px;color:#78350f;line-height:1.8;">
        <p><strong>🎤 <?php esc_html_e( 'At Jumu\'ah:', 'yourjannah' ); ?></strong> <?php esc_html_e( '"Brothers and sisters, you can now support our masjid monthly through YourJannah. From just £5/month, you become a patron. Your consistent support helps us plan ahead. Visit yourjannah.com and search for our mosque."', 'yourjannah' ); ?></p>
        <p style="margin-top:8px;"><strong>📱 <?php esc_html_e( 'WhatsApp message:', 'yourjannah' ); ?></strong> <?php esc_html_e( '"Assalamu alaikum. Our masjid now has a patron programme on YourJannah. For £5-£50/month, you get recognised as a supporter and help keep our masjid running. Join here:"', 'yourjannah' ); ?> <code style="background:#fff;padding:2px 6px;border-radius:4px;font-size:11px;"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug . '/patron' ) ); ?></code></p>
        <p style="margin-top:8px;"><strong>🖨️ <?php esc_html_e( 'Print a poster:', 'yourjannah' ); ?></strong> <?php esc_html_e( 'Put a QR code poster near the shoe rack and entrance. "Become a Patron — support your masjid monthly."', 'yourjannah' ); ?></p>
        <p style="margin-top:12px;padding-top:8px;border-top:1px solid rgba(245,158,11,.3);font-weight:700;"><?php esc_html_e( '🎯 Goal: 50 patrons × £10/mo = £500/mo = £6,000/year guaranteed income.', 'yourjannah' ); ?></p>
    </div>
</div>

<?php if ( empty( $patrons ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">🏅</div><p><?php esc_html_e( 'No patrons yet. Share your patron page to start receiving monthly support!', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th><?php esc_html_e( 'Name', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Email', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Tier', 'yourjannah' ); ?></th><th style="text-align:right;"><?php esc_html_e( 'Monthly', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Since', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $patrons as $p ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $p->user_name ); ?></strong></td>
            <td style="font-size:12px;color:var(--text-dim);"><?php echo esc_html( $p->user_email ); ?></td>
            <td><?php echo ( $tier_icons[ $p->tier ] ?? '' ) . ' ' . esc_html( $tier_labels[ $p->tier ] ?? ucfirst( $p->tier ) ); ?></td>
            <td style="text-align:right;font-weight:700;">£<?php echo number_format( $p->amount_pence / 100, 0 ); ?></td>
            <td><span class="d-badge d-badge--<?php echo $p->status === 'active' ? 'green' : ( $p->status === 'cancelled' ? 'red' : 'yellow' ); ?>"><?php echo esc_html( ucfirst( $p->status ) ); ?></span></td>
            <td style="font-size:12px;"><?php echo $p->started_at ? esc_html( substr( $p->started_at, 0, 10 ) ) : '—'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
