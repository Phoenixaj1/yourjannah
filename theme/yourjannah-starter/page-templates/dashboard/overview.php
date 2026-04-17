<?php
/**
 * Dashboard Section: Overview
 * KPI stats, setup checklist, revenue growth, marketing tips.
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
$ft = YNJ_DB::table( 'mosque_funds' );
$it = YNJ_DB::table( 'email_imports' );
$jt = YNJ_DB::table( 'jumuah_times' );
$arpt = YNJ_DB::table( 'appeal_responses' );
$mosq = YNJ_DB::table( 'mosques' );

$s = [
    'patrons'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'patron_mrr'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_pence),0) FROM $pt WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'sponsors'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $bt WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'sponsor_mrr'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(monthly_fee_pence),0) FROM $bt WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'services'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $st WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'subscribers'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sub WHERE mosque_id=%d AND status='active'", $mosque_id ) ),
    'pending_bk'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $bk WHERE mosque_id=%d AND status='pending'", $mosque_id ) ),
    'new_enquiries' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $eq WHERE mosque_id=%d AND status='new'", $mosque_id ) ),
    'events'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ev WHERE mosque_id=%d AND event_date >= CURDATE()", $mosque_id ) ),
    'announcements' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $an WHERE mosque_id=%d AND status='published'", $mosque_id ) ),
    'donations'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE mosque_id=%d AND status='succeeded'", $mosque_id ) ),
    'donation_count'=> (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $dt WHERE mosque_id=%d AND status='succeeded'", $mosque_id ) ),
    'funds'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ft WHERE mosque_id=%d AND is_active=1", $mosque_id ) ),
    'imported'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(imported),0) FROM $it WHERE mosque_id=%d", $mosque_id ) ),
    'jumuah'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $jt WHERE mosque_id=%d AND enabled=1", $mosque_id ) ),
    'has_desc'      => ! empty( $mosque->description ),
    'has_phone'     => ! empty( $mosque->phone ),
    'appeal_rev'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(mosque_fee_pence),0) FROM $arpt WHERE mosque_id=%d AND response='accepted'", $mosque_id ) ),
    'accept_appeals'=> (int) ( $mosque->accept_appeals ?? 0 ),
    'has_imam'      => (int) ( $mosque->imam_user_id ?? 0 ) > 0,
];

$total_mrr = $s['patron_mrr'] + $s['sponsor_mrr'];
$total_yearly = $total_mrr * 12;

// Setup checklist
$setup_steps = [
    [ 'done' => $s['has_desc'] && $s['has_phone'], 'label' => 'Complete mosque profile (name, address, phone, description)', 'link' => '?section=settings', 'icon' => '⚙️' ],
    [ 'done' => $s['jumuah'] > 0,                   'label' => 'Add Jumu\'ah prayer times', 'link' => '?section=prayers', 'icon' => '🕐' ],
    [ 'done' => $s['announcements'] > 0,             'label' => 'Post your first announcement', 'link' => '?section=announcements', 'icon' => '📢' ],
    [ 'done' => $s['events'] > 0,                    'label' => 'Create an upcoming event', 'link' => '?section=events', 'icon' => '📅' ],
    [ 'done' => $s['funds'] > 1,                     'label' => 'Set up donation funds (welfare, maintenance, etc.)', 'link' => '?section=funds', 'icon' => '💰' ],
    [ 'done' => $s['imported'] > 0,                  'label' => 'Import your email list (CSV upload)', 'link' => '?section=broadcast', 'icon' => '📥' ],
    [ 'done' => $s['subscribers'] >= 10,              'label' => 'Get 10+ subscribers', 'link' => '?section=subscribers', 'icon' => '👥' ],
    [ 'done' => $s['patrons'] > 0,                   'label' => 'Get your first patron (monthly supporter)', 'link' => '?section=patrons', 'icon' => '🏅' ],
    [ 'done' => $s['sponsors'] > 0,                  'label' => 'Get your first business sponsor', 'link' => '?section=sponsors', 'icon' => '⭐' ],
    [ 'done' => $s['donation_count'] > 0,            'label' => 'Receive your first donation via niyyah bar', 'link' => '?section=funds', 'icon' => '💝' ],
];
$steps_done = count( array_filter( $setup_steps, function( $s ) { return $s['done']; } ) );
$steps_total = count( $setup_steps );
$progress_pct = round( $steps_done / $steps_total * 100 );
?>

<div class="d-header">
    <h1>👋 <?php printf( esc_html__( 'Welcome, %s', 'yourjannah' ), esc_html( wp_get_current_user()->display_name ) ); ?></h1>
    <p><?php echo esc_html( $mosque_name ); ?></p>
</div>

<!-- Setup Progress -->
<?php if ( $steps_done < $steps_total ) : ?>
<div class="d-card" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #93c5fd;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="color:#1e40af;margin:0;">🚀 <?php esc_html_e( 'Getting Started', 'yourjannah' ); ?></h3>
        <span style="font-size:13px;font-weight:700;color:#1e40af;"><?php echo $steps_done; ?>/<?php echo $steps_total; ?> (<?php echo $progress_pct; ?>%)</span>
    </div>
    <div style="background:#bfdbfe;border-radius:8px;height:8px;margin-bottom:12px;overflow:hidden;">
        <div style="background:#2563eb;height:100%;width:<?php echo $progress_pct; ?>%;border-radius:8px;transition:width .3s;"></div>
    </div>
    <?php foreach ( $setup_steps as $step ) : if ( $step['done'] ) continue; ?>
    <a href="<?php echo esc_url( $step['link'] ); ?>" style="display:flex;align-items:center;gap:10px;padding:8px 12px;margin-bottom:4px;border-radius:8px;background:#fff;border:1px solid #dbeafe;text-decoration:none;color:#1e40af;font-size:13px;font-weight:500;transition:background .15s;">
        <span style="font-size:16px;"><?php echo $step['icon']; ?></span>
        <?php echo esc_html( $step['label'] ); ?>
        <span style="margin-left:auto;font-size:11px;color:#93c5fd;">→</span>
    </a>
    <?php endforeach; ?>
    <?php if ( $steps_done > 0 ) : ?>
    <p style="font-size:11px;color:#60a5fa;margin-top:8px;">✅ <?php printf( esc_html__( '%d steps completed', 'yourjannah' ), $steps_done ); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Revenue Overview -->
<div class="d-card" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;">
    <h3 style="color:#166534;margin-bottom:12px;">💷 <?php esc_html_e( 'Revenue Overview', 'yourjannah' ); ?></h3>
    <div class="d-stats" style="margin-bottom:0;">
        <div class="d-stat" style="border-color:#bbf7d0;">
            <div class="d-stat__label"><?php esc_html_e( 'Monthly Revenue', 'yourjannah' ); ?></div>
            <div class="d-stat__value" style="color:#16a34a;">£<?php echo number_format( $total_mrr / 100, 0 ); ?>/mo</div>
        </div>
        <div class="d-stat" style="border-color:#bbf7d0;">
            <div class="d-stat__label"><?php esc_html_e( 'Yearly Projection', 'yourjannah' ); ?></div>
            <div class="d-stat__value">£<?php echo number_format( $total_yearly / 100, 0 ); ?>/yr</div>
        </div>
        <div class="d-stat" style="border-color:#bbf7d0;">
            <div class="d-stat__label"><?php esc_html_e( 'Donations Received', 'yourjannah' ); ?></div>
            <div class="d-stat__value" style="color:#00ADEF;">£<?php echo number_format( $s['donations'] / 100, 0 ); ?></div>
        </div>
    </div>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #bbf7d0;display:flex;gap:16px;font-size:12px;color:#166534;">
        <span>🏅 <?php echo $s['patrons']; ?> patrons (£<?php echo number_format( $s['patron_mrr'] / 100, 0 ); ?>/mo)</span>
        <span>⭐ <?php echo $s['sponsors']; ?> sponsors (£<?php echo number_format( $s['sponsor_mrr'] / 100, 0 ); ?>/mo)</span>
        <span>💝 <?php echo $s['donation_count']; ?> donations</span>
    </div>
</div>

<!-- Revenue Growth Tips (show when revenue is low) -->
<?php if ( $total_mrr < 50000 ) : // Less than £500/mo ?>
<div class="d-card" style="border-left:4px solid #f59e0b;">
    <h3 style="color:#92400e;">📈 <?php esc_html_e( 'Grow Your Revenue', 'yourjannah' ); ?></h3>
    <p style="font-size:13px;color:#78350f;margin-bottom:12px;"><?php esc_html_e( 'Here are the fastest ways to generate sustainable income for your masjid:', 'yourjannah' ); ?></p>

    <div style="display:grid;gap:10px;">
        <a href="?section=patrons" style="display:flex;gap:12px;padding:12px;background:#fffbeb;border-radius:10px;text-decoration:none;color:#92400e;border:1px solid #fde68a;">
            <span style="font-size:24px;">🏅</span>
            <div>
                <strong><?php esc_html_e( 'Launch Patron Memberships', 'yourjannah' ); ?></strong>
                <p style="font-size:12px;margin:2px 0 0;opacity:.8;"><?php esc_html_e( 'Ask congregants to give £5-£50/month. Just 20 patrons at £10/mo = £200/mo guaranteed income. Announce at Jumu\'ah.', 'yourjannah' ); ?></p>
            </div>
        </a>

        <a href="?section=sponsors" style="display:flex;gap:12px;padding:12px;background:#fffbeb;border-radius:10px;text-decoration:none;color:#92400e;border:1px solid #fde68a;">
            <span style="font-size:24px;">⭐</span>
            <div>
                <strong><?php esc_html_e( 'Get Business Sponsors', 'yourjannah' ); ?></strong>
                <p style="font-size:12px;margin:2px 0 0;opacity:.8;"><?php esc_html_e( 'Local halal restaurants, shops, and professionals pay £30-£100/mo to be listed. Approach them directly or announce at Jumu\'ah.', 'yourjannah' ); ?></p>
            </div>
        </a>

        <a href="?section=broadcast" style="display:flex;gap:12px;padding:12px;background:#fffbeb;border-radius:10px;text-decoration:none;color:#92400e;border:1px solid #fde68a;">
            <span style="font-size:24px;">📥</span>
            <div>
                <strong><?php esc_html_e( 'Import Your Email List', 'yourjannah' ); ?></strong>
                <p style="font-size:12px;margin:2px 0 0;opacity:.8;"><?php esc_html_e( 'Upload your existing congregation emails. Then broadcast: "Join YourJannah to stay connected and support our masjid."', 'yourjannah' ); ?></p>
            </div>
        </a>

        <a href="?section=funds" style="display:flex;gap:12px;padding:12px;background:#fffbeb;border-radius:10px;text-decoration:none;color:#92400e;border:1px solid #fde68a;">
            <span style="font-size:24px;">💰</span>
            <div>
                <strong><?php esc_html_e( 'Set Up Donation Funds', 'yourjannah' ); ?></strong>
                <p style="font-size:12px;margin:2px 0 0;opacity:.8;"><?php esc_html_e( 'Create specific funds (New Roof, Heating, Welfare). People donate more when they see where money goes. The niyyah bar shows these on every page.', 'yourjannah' ); ?></p>
            </div>
        </a>

        <?php if ( ! $s['accept_appeals'] ) : ?>
        <a href="?section=settings" style="display:flex;gap:12px;padding:12px;background:#fffbeb;border-radius:10px;text-decoration:none;color:#92400e;border:1px solid #fde68a;">
            <span style="font-size:24px;">&#128227;</span>
            <div>
                <strong><?php esc_html_e( 'Enable Charity Appeals', 'yourjannah' ); ?></strong>
                <p style="font-size:12px;margin:2px 0 0;opacity:.8;"><?php esc_html_e( 'Earn revenue by hosting charity appeals. Enable in Settings and set your fees for in-person and recorded appeals.', 'yourjannah' ); ?></p>
            </div>
        </a>
        <?php endif; ?>

        <?php if ( ! $s['has_imam'] ) : ?>
        <a href="?section=settings" style="display:flex;gap:12px;padding:12px;background:#fffbeb;border-radius:10px;text-decoration:none;color:#92400e;border:1px solid #fde68a;">
            <span style="font-size:24px;">&#128104;&#8205;&#127891;</span>
            <div>
                <strong><?php esc_html_e( 'Invite your Imam', 'yourjannah' ); ?></strong>
                <p style="font-size:12px;margin:2px 0 0;opacity:.8;"><?php esc_html_e( 'Let your imam send religious messages to the congregation. Set up in Settings to link their account.', 'yourjannah' ); ?></p>
            </div>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Action Items -->
<?php if ( $s['pending_bk'] > 0 || $s['new_enquiries'] > 0 ) : ?>
<div class="d-card" style="border-left:4px solid #dc2626;">
    <h3>⚡ <?php esc_html_e( 'Action Items', 'yourjannah' ); ?></h3>
    <?php if ( $s['pending_bk'] > 0 ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;">
        <span>📋 <?php printf( esc_html__( '%d pending bookings', 'yourjannah' ), $s['pending_bk'] ); ?></span>
        <a href="?section=bookings" class="d-btn d-btn--sm d-btn--primary"><?php esc_html_e( 'Review', 'yourjannah' ); ?></a>
    </div>
    <?php endif; ?>
    <?php if ( $s['new_enquiries'] > 0 ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;">
        <span>✉️ <?php printf( esc_html__( '%d unanswered enquiries', 'yourjannah' ), $s['new_enquiries'] ); ?></span>
        <a href="?section=enquiries" class="d-btn d-btn--sm d-btn--primary"><?php esc_html_e( 'Respond', 'yourjannah' ); ?></a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Interested / Engagement -->
<?php
$mosque_interests = get_transient( 'ynj_interest_' . $mosque_slug ) ?: [];
// Get last 30 days only
$thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
$recent_interests = array_filter( $mosque_interests, function( $e ) use ( $thirty_days_ago ) {
    return ( $e['at'] ?? '' ) >= $thirty_days_ago;
} );
$interest_count = count( $recent_interests );

// Count per item for top interests
$interest_items = [];
foreach ( $recent_interests as $entry ) {
    $key = ( $entry['type'] ?? '' ) . '_' . ( $entry['id'] ?? 0 );
    if ( ! isset( $interest_items[ $key ] ) ) {
        $interest_items[ $key ] = [ 'title' => $entry['title'] ?? '', 'type' => $entry['type'] ?? '', 'count' => 0 ];
    }
    $interest_items[ $key ]['count']++;
}
usort( $interest_items, function( $a, $b ) { return $b['count'] - $a['count']; } );
$top_interests = array_slice( $interest_items, 0, 5 );
?>
<?php if ( $interest_count > 0 ) : ?>
<div class="d-card" style="border-left:4px solid #ec4899;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;">❤️ <?php esc_html_e( 'Congregation Interest', 'yourjannah' ); ?></h3>
        <span style="font-size:13px;font-weight:700;color:#ec4899;"><?php echo $interest_count; ?> <?php echo $interest_count === 1 ? 'tap' : 'taps'; ?> <?php esc_html_e( 'this month', 'yourjannah' ); ?></span>
    </div>
    <p style="font-size:12px;color:#666;margin-bottom:12px;"><?php esc_html_e( 'People tapped "Interested" on these announcements and events:', 'yourjannah' ); ?></p>
    <?php foreach ( $top_interests as $ti ) : ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fdf2f8;border-radius:8px;margin-bottom:6px;">
        <div>
            <span class="d-badge d-badge--<?php echo $ti['type'] === 'event' ? 'blue' : 'gray'; ?>" style="font-size:10px;margin-right:6px;"><?php echo esc_html( ucfirst( $ti['type'] ) ); ?></span>
            <strong style="font-size:13px;"><?php echo esc_html( $ti['title'] ); ?></strong>
        </div>
        <span style="font-size:14px;font-weight:800;color:#ec4899;"><?php echo $ti['count']; ?> ❤️</span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Activity Summary -->
<div class="d-card">
    <h3>📊 <?php esc_html_e( 'Activity', 'yourjannah' ); ?></h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:10px;">
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;"><div style="font-size:20px;font-weight:800;"><?php echo $s['subscribers']; ?></div><div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Members', 'yourjannah' ); ?></div></div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;"><div style="font-size:20px;font-weight:800;"><?php echo $s['events']; ?></div><div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Events', 'yourjannah' ); ?></div></div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;"><div style="font-size:20px;font-weight:800;"><?php echo $s['announcements']; ?></div><div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Announcements', 'yourjannah' ); ?></div></div>
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px;"><div style="font-size:20px;font-weight:800;"><?php echo $s['sponsors']; ?></div><div style="font-size:11px;color:var(--text-dim);"><?php esc_html_e( 'Sponsors', 'yourjannah' ); ?></div></div>
    </div>
</div>

<!-- Quick Actions -->
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

<!-- Mosque URL -->
<div class="d-card" style="background:var(--primary-light);border-color:var(--primary);">
    <p style="font-size:13px;font-weight:600;color:var(--primary-dark);margin-bottom:4px;">🔗 <?php esc_html_e( 'Your Mosque Page — Share this with your congregation!', 'yourjannah' ); ?></p>
    <code style="display:block;padding:10px;background:#fff;border-radius:8px;font-size:14px;word-break:break-all;color:var(--primary);"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug ) ); ?></code>
    <div style="display:flex;gap:8px;margin-top:8px;font-size:12px;">
        <span>📍 Patron page: <code style="font-size:11px;"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug . '/patron' ) ); ?></code></span>
    </div>
</div>
