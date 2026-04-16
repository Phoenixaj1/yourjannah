<?php
/**
 * Dashboard Section: Sponsors (Business listings for this mosque)
 * Read-only view + marketing tips on how to attract sponsors.
 */
$bt = YNJ_DB::table( 'businesses' );
$st = YNJ_DB::table( 'services' );

$businesses = $wpdb->get_results( $wpdb->prepare(
    "SELECT id, business_name, owner_name, category, monthly_fee_pence, featured_position, status, verified, created_at
     FROM $bt WHERE mosque_id=%d ORDER BY status ASC, monthly_fee_pence DESC LIMIT 50",
    $mosque_id
) ) ?: [];

$services_list = $wpdb->get_results( $wpdb->prepare(
    "SELECT id, provider_name, service_type, monthly_fee_pence, status, created_at
     FROM $st WHERE mosque_id=%d ORDER BY status ASC LIMIT 50",
    $mosque_id
) ) ?: [];

$active_biz = array_filter( $businesses, function( $b ) { return $b->status === 'active'; } );
$active_svc = array_filter( $services_list, function( $s ) { return $s->status === 'active'; } );
$biz_revenue = array_sum( array_map( function( $b ) { return (int) $b->monthly_fee_pence; }, $active_biz ) );
$svc_revenue = array_sum( array_map( function( $s ) { return (int) $s->monthly_fee_pence; }, $active_svc ) );
$sponsor_url = home_url( '/mosque/' . $mosque_slug . '/sponsors/join' );
$service_url = home_url( '/mosque/' . $mosque_slug . '/services/join' );
?>

<div class="d-header">
    <h1>⭐ <?php esc_html_e( 'Sponsors & Directory', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Businesses and professionals who support your mosque. This is a key revenue stream.', 'yourjannah' ); ?></p>
</div>

<!-- Revenue Stats -->
<div class="d-stats">
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Active Sponsors', 'yourjannah' ); ?></div><div class="d-stat__value"><?php echo count( $active_biz ); ?></div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Sponsor MRR', 'yourjannah' ); ?></div><div class="d-stat__value" style="color:#16a34a;">£<?php echo number_format( $biz_revenue / 100, 0 ); ?>/mo</div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Service Listings', 'yourjannah' ); ?></div><div class="d-stat__value"><?php echo count( $active_svc ); ?></div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Service MRR', 'yourjannah' ); ?></div><div class="d-stat__value" style="color:#16a34a;">£<?php echo number_format( $svc_revenue / 100, 0 ); ?>/mo</div></div>
</div>

<!-- Marketing Guide -->
<div class="d-card" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #f59e0b;">
    <h3 style="color:#92400e;">💡 <?php esc_html_e( 'How to Grow Sponsor Revenue', 'yourjannah' ); ?></h3>
    <div style="display:grid;gap:12px;margin-top:12px;">

        <div style="display:flex;gap:12px;align-items:start;">
            <span style="font-size:20px;flex-shrink:0;">1️⃣</span>
            <div>
                <strong style="color:#92400e;"><?php esc_html_e( 'Announce at Jumu\'ah', 'yourjannah' ); ?></strong>
                <p style="font-size:13px;color:#78350f;margin:4px 0 0;"><?php esc_html_e( '"If you own a business, you can sponsor our masjid for as little as £30/month. Your business will be featured on our YourJannah page, seen by our entire congregation. Visit yourjannah.com and search for our mosque."', 'yourjannah' ); ?></p>
            </div>
        </div>

        <div style="display:flex;gap:12px;align-items:start;">
            <span style="font-size:20px;flex-shrink:0;">2️⃣</span>
            <div>
                <strong style="color:#92400e;"><?php esc_html_e( 'Print QR Code Posters', 'yourjannah' ); ?></strong>
                <p style="font-size:13px;color:#78350f;margin:4px 0 0;"><?php esc_html_e( 'Place posters with a QR code linking to your sponsor page near the entrance, notice board, and shoe racks. Local businesses walking past will see it.', 'yourjannah' ); ?></p>
                <p style="font-size:12px;margin-top:4px;"><strong><?php esc_html_e( 'Sponsor signup link:', 'yourjannah' ); ?></strong> <code style="background:#fff;padding:2px 6px;border-radius:4px;font-size:11px;word-break:break-all;"><?php echo esc_html( $sponsor_url ); ?></code></p>
            </div>
        </div>

        <div style="display:flex;gap:12px;align-items:start;">
            <span style="font-size:20px;flex-shrink:0;">3️⃣</span>
            <div>
                <strong style="color:#92400e;"><?php esc_html_e( 'WhatsApp Your Community Group', 'yourjannah' ); ?></strong>
                <p style="font-size:13px;color:#78350f;margin:4px 0 0;"><?php esc_html_e( 'Share the message: "Support our masjid by listing your business or services. Halal restaurants, professionals, tradespeople — get seen by our community. £30/month sponsors, £10/month services."', 'yourjannah' ); ?></p>
            </div>
        </div>

        <div style="display:flex;gap:12px;align-items:start;">
            <span style="font-size:20px;flex-shrink:0;">4️⃣</span>
            <div>
                <strong style="color:#92400e;"><?php esc_html_e( 'Email Your Subscriber List', 'yourjannah' ); ?></strong>
                <p style="font-size:13px;color:#78350f;margin:4px 0 0;"><?php esc_html_e( 'Use the Broadcast feature to email all subscribers about sponsorship opportunities. Include the pricing tiers (£30 Standard, £50 Featured, £100 Premium).', 'yourjannah' ); ?></p>
            </div>
        </div>

        <div style="display:flex;gap:12px;align-items:start;">
            <span style="font-size:20px;flex-shrink:0;">5️⃣</span>
            <div>
                <strong style="color:#92400e;"><?php esc_html_e( 'Approach Local Businesses Directly', 'yourjannah' ); ?></strong>
                <p style="font-size:13px;color:#78350f;margin:4px 0 0;"><?php esc_html_e( 'Visit halal restaurants, grocers, and shops near the mosque. Explain: "For £30/month, your business gets listed on our community app. 100+ families will see your listing every week." Bring a printed flyer.', 'yourjannah' ); ?></p>
            </div>
        </div>
    </div>

    <div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(245,158,11,.3);">
        <p style="font-size:13px;font-weight:700;color:#92400e;">💷 <?php esc_html_e( 'Revenue potential:', 'yourjannah' ); ?></p>
        <p style="font-size:13px;color:#78350f;"><?php esc_html_e( '10 businesses × £30/mo = £300/mo (£3,600/yr). 5 Premium sponsors × £100/mo = £500/mo (£6,000/yr). Combined: up to £10,000/year for your masjid from sponsors alone.', 'yourjannah' ); ?></p>
    </div>
</div>

<!-- Pricing Reference -->
<div class="d-card">
    <h3>💷 <?php esc_html_e( 'Pricing Tiers', 'yourjannah' ); ?></h3>
    <table class="d-table">
        <thead><tr><th><?php esc_html_e( 'Tier', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Price', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Benefits', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
            <tr><td><strong>⭐ Standard Sponsor</strong></td><td>£30/mo</td><td><?php esc_html_e( 'Listed in mosque directory, visible to all visitors', 'yourjannah' ); ?></td></tr>
            <tr><td><strong>🥈 Featured Sponsor</strong></td><td>£50/mo</td><td><?php esc_html_e( 'Blue highlight, shown higher in listings', 'yourjannah' ); ?></td></tr>
            <tr><td><strong>🥇 Premium Sponsor</strong></td><td>£100/mo</td><td><?php esc_html_e( 'Gold highlight, top position, sponsor ticker on homepage', 'yourjannah' ); ?></td></tr>
            <tr><td><strong>🤝 Service Listing</strong></td><td>£10/mo</td><td><?php esc_html_e( 'Professionals (plumber, tutor, imam) listed in directory', 'yourjannah' ); ?></td></tr>
        </tbody>
    </table>
    <p style="font-size:12px;color:var(--text-dim);margin-top:8px;"><?php esc_html_e( '95% of all revenue goes directly to your mosque. YourJannah takes just 5%.', 'yourjannah' ); ?></p>
</div>

<!-- Current Sponsors -->
<?php if ( $businesses ) : ?>
<div class="d-card">
    <h3><?php esc_html_e( 'Business Sponsors', 'yourjannah' ); ?> (<?php echo count( $businesses ); ?>)</h3>
    <table class="d-table">
        <thead><tr><th><?php esc_html_e( 'Business', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Category', 'yourjannah' ); ?></th><th style="text-align:right;"><?php esc_html_e( 'Monthly', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $businesses as $b ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $b->business_name ); ?></strong><?php if ( $b->verified ) echo ' <span style="color:#16a34a;">✓</span>'; ?><?php if ( $b->owner_name ) echo '<br><span style="font-size:11px;color:var(--text-dim);">by ' . esc_html( $b->owner_name ) . '</span>'; ?></td>
            <td><span class="d-badge d-badge--gray"><?php echo esc_html( $b->category ); ?></span></td>
            <td style="text-align:right;font-weight:700;">£<?php echo number_format( $b->monthly_fee_pence / 100, 0 ); ?></td>
            <td><span class="d-badge d-badge--<?php echo $b->status === 'active' ? 'green' : 'yellow'; ?>"><?php echo esc_html( ucfirst( $b->status ) ); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ( $services_list ) : ?>
<div class="d-card">
    <h3><?php esc_html_e( 'Service Listings', 'yourjannah' ); ?> (<?php echo count( $services_list ); ?>)</h3>
    <table class="d-table">
        <thead><tr><th><?php esc_html_e( 'Provider', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Type', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $services_list as $s ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $s->provider_name ); ?></strong></td>
            <td><span class="d-badge d-badge--gray"><?php echo esc_html( $s->service_type ); ?></span></td>
            <td><span class="d-badge d-badge--<?php echo $s->status === 'active' ? 'green' : 'yellow'; ?>"><?php echo esc_html( ucfirst( $s->status ) ); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
