<?php
/**
 * Dashboard Section: Overview
 * Shows KPI stats, action items, and quick links.
 */

$pt = YNJ_DB::table( 'patrons' );
$bt = YNJ_DB::table( 'businesses' );
$st = YNJ_DB::table( 'services' );
$sub = YNJ_DB::table( 'subscribers' );
$bk = YNJ_DB::table( 'bookings' );
$eq = YNJ_DB::table( 'enquiries' );
$ev = YNJ_DB::table( 'events' );
$an = YNJ_DB::table( 'announcements' );
$dt = YNJ_DB::table( 'donations' );

$stats = [
    'patrons'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'patron_mrr'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_pence),0) FROM $pt WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'sponsors'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $bt WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'services'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $st WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'subscribers'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sub WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'pending_bk'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $bk WHERE mosque_id=%d AND status='pending'", $mosque_id ) ),
    'new_enquiries'=> (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $eq WHERE mosque_id=%d AND status='new'", $mosque_id ) ),
    'events'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ev WHERE mosque_id=%d AND event_date >= CURDATE()", $mosque_id ) ),
    'announcements'=> (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $an WHERE mosque_id=%d AND status='published'", $mosque_id ) ),
    'donations'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE mosque_id=%d AND status='succeeded'", $mosque_id ) ),
    'donation_count'=> (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $dt WHERE mosque_id=%d AND status='succeeded'", $mosque_id ) ),
];
?>

<div class="d-header">
    <h1>👋 <?php printf( esc_html__( 'Welcome, %s', 'yourjannah' ), esc_html( wp_get_current_user()->display_name ) ); ?></h1>
    <p><?php echo esc_html( $mosque_name ); ?> — <?php esc_html_e( 'Your mosque at a glance', 'yourjannah' ); ?></p>
</div>

<!-- Stats Grid -->
<div class="d-stats">
    <div class="d-stat">
        <div class="d-stat__label"><?php esc_html_e( 'Monthly Patrons', 'yourjannah' ); ?></div>
        <div class="d-stat__value"><?php echo $stats['patrons']; ?></div>
    </div>
    <div class="d-stat">
        <div class="d-stat__label"><?php esc_html_e( 'Patron Revenue', 'yourjannah' ); ?></div>
        <div class="d-stat__value" style="color:#16a34a;">£<?php echo number_format( $stats['patron_mrr'] / 100, 0 ); ?>/mo</div>
    </div>
    <div class="d-stat">
        <div class="d-stat__label"><?php esc_html_e( 'Subscribers', 'yourjannah' ); ?></div>
        <div class="d-stat__value"><?php echo $stats['subscribers']; ?></div>
    </div>
    <div class="d-stat">
        <div class="d-stat__label"><?php esc_html_e( 'Donations', 'yourjannah' ); ?></div>
        <div class="d-stat__value" style="color:#00ADEF;">£<?php echo number_format( $stats['donations'] / 100, 0 ); ?></div>
    </div>
</div>

<!-- Action Items -->
<?php if ( $stats['pending_bk'] > 0 || $stats['new_enquiries'] > 0 ) : ?>
<div class="d-card" style="border-left:4px solid #f59e0b;">
    <h3>⚡ <?php esc_html_e( 'Action Items', 'yourjannah' ); ?></h3>
    <?php if ( $stats['pending_bk'] > 0 ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;">
        <span>📋 <?php printf( esc_html__( '%d pending bookings need approval', 'yourjannah' ), $stats['pending_bk'] ); ?></span>
        <a href="?section=bookings" class="d-btn d-btn--sm d-btn--primary"><?php esc_html_e( 'Review', 'yourjannah' ); ?></a>
    </div>
    <?php endif; ?>
    <?php if ( $stats['new_enquiries'] > 0 ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;">
        <span>✉️ <?php printf( esc_html__( '%d unanswered enquiries', 'yourjannah' ), $stats['new_enquiries'] ); ?></span>
        <a href="?section=enquiries" class="d-btn d-btn--sm d-btn--primary"><?php esc_html_e( 'Respond', 'yourjannah' ); ?></a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Quick Stats Row -->
<div class="d-card">
    <h3>📊 <?php esc_html_e( 'Activity Summary', 'yourjannah' ); ?></h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;">
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;">
            <div style="font-size:20px;font-weight:800;"><?php echo $stats['events']; ?></div>
            <div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Upcoming Events', 'yourjannah' ); ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;">
            <div style="font-size:20px;font-weight:800;"><?php echo $stats['announcements']; ?></div>
            <div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Announcements', 'yourjannah' ); ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;">
            <div style="font-size:20px;font-weight:800;"><?php echo $stats['sponsors']; ?></div>
            <div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Sponsors', 'yourjannah' ); ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;">
            <div style="font-size:20px;font-weight:800;"><?php echo $stats['donation_count']; ?></div>
            <div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Donations', 'yourjannah' ); ?></div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="d-card">
    <h3>🚀 <?php esc_html_e( 'Quick Actions', 'yourjannah' ); ?></h3>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <a href="?section=announcements" class="d-btn d-btn--outline">📢 <?php esc_html_e( 'New Announcement', 'yourjannah' ); ?></a>
        <a href="?section=events" class="d-btn d-btn--outline">📅 <?php esc_html_e( 'Add Event', 'yourjannah' ); ?></a>
        <a href="?section=broadcast" class="d-btn d-btn--outline">📤 <?php esc_html_e( 'Send Broadcast', 'yourjannah' ); ?></a>
        <a href="?section=campaigns" class="d-btn d-btn--outline">💝 <?php esc_html_e( 'New Campaign', 'yourjannah' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug ) ); ?>" class="d-btn d-btn--outline" target="_blank">🕌 <?php esc_html_e( 'View Mosque Page', 'yourjannah' ); ?></a>
    </div>
</div>

<!-- Mosque Page URL -->
<div class="d-card" style="background:var(--primary-light);border-color:var(--primary);">
    <p style="font-size:13px;font-weight:600;color:var(--primary-dark);margin-bottom:6px;">🔗 <?php esc_html_e( 'Your Mosque Page', 'yourjannah' ); ?></p>
    <code style="display:block;padding:10px 14px;background:#fff;border-radius:8px;font-size:14px;word-break:break-all;color:var(--primary);"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug ) ); ?></code>
    <p style="font-size:12px;color:var(--text-dim);margin-top:6px;"><?php esc_html_e( 'Share this link with your congregation!', 'yourjannah' ); ?></p>
</div>
