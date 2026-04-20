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

// Dopamine data (engagement metrics)
$content_stats    = function_exists( 'ynj_get_content_stats' ) ? ynj_get_content_stats( $mosque_id, 7 ) : [ 'views' => 0, 'interested' => 0, 'shares' => 0, 'change_pct' => 0 ];
$top_content      = function_exists( 'ynj_get_top_content' ) ? ynj_get_top_content( $mosque_id, 7, 3 ) : [];
$posting_streak   = function_exists( 'ynj_get_posting_streak' ) ? ynj_get_posting_streak( $mosque_id ) : [ 'streak' => 0, 'this_week' => [] ];
$sub_growth       = function_exists( 'ynj_get_subscriber_growth' ) ? ynj_get_subscriber_growth( $mosque_id ) : [ 'weekly_data' => [0,0,0,0], 'this_week' => 0, 'this_month' => 0 ];
$city_rank        = function_exists( 'ynj_get_mosque_ranking' ) ? ynj_get_mosque_ranking( $mosque_id, $mosque->city ?? '' ) : null;
$activity_feed    = function_exists( 'ynj_get_activity_feed' ) ? ynj_get_activity_feed( $mosque_id, 10 ) : [];

// Donations this week (from old donations table)
$donations_week_old = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE mosque_id=%d AND status='succeeded' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $mosque_id
) );

// Revenue this week from unified transactions (the real source)
$txn_table = YNJ_DB::table( 'transactions' );
$txn_week_total = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(total_pence),0) FROM $txn_table WHERE mosque_id=%d AND status='succeeded' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $mosque_id
) );
$txn_week_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM $txn_table WHERE mosque_id=%d AND status='succeeded' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $mosque_id
) );
$donations_week = $txn_week_total + $donations_week_old;

// Revenue breakdown by type this week
$txn_type_week = $wpdb->get_results( $wpdb->prepare(
    "SELECT item_type, COUNT(*) as cnt, SUM(total_pence) as revenue FROM $txn_table WHERE mosque_id=%d AND status='succeeded' AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY item_type ORDER BY revenue DESC", $mosque_id
) ) ?: [];

// Recent transactions for this mosque
$recent_txns = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $txn_table WHERE mosque_id=%d AND status='succeeded' ORDER BY completed_at DESC LIMIT 10", $mosque_id
) ) ?: [];

// New subscribers this week
$new_subs_week = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM " . YNJ_DB::table( 'user_subscriptions' ) . " WHERE mosque_id=%d AND subscribed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", $mosque_id
) );

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

<!-- Setup Complete Celebration -->
<?php if ( isset( $_GET['setup_complete'] ) && $_GET['setup_complete'] == '1' ) : ?>
<div class="d-card" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:2px solid #22c55e;text-align:center;padding:32px 20px;">
    <div style="font-size:48px;margin-bottom:12px;">🎉</div>
    <h2 style="color:#166534;margin-bottom:8px;"><?php esc_html_e( 'Setup Complete!', 'yourjannah' ); ?></h2>
    <p style="color:#15803d;font-size:14px;"><?php esc_html_e( 'MashAllah! Your mosque is now live on YourJannah. Share your page with the congregation.', 'yourjannah' ); ?></p>
    <a href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug ) ); ?>"
       class="d-btn d-btn--primary" style="margin-top:16px;display:inline-flex;" target="_blank">
       🕌 <?php esc_html_e( 'View Your Mosque Page', 'yourjannah' ); ?>
    </a>
</div>
<?php endif; ?>

<!-- Setup Wizard CTA (first-time admins) -->
<?php
$onboard_complete = (int) get_user_meta( $wp_uid, 'ynj_onboard_complete', true );
if ( ! $onboard_complete && $steps_done < 3 ) :
?>
<div class="d-card" style="background:linear-gradient(135deg,#0a1628,#1a3a5c);color:#fff;text-align:center;padding:32px 20px;border:none;">
    <div style="font-size:40px;margin-bottom:12px;">🚀</div>
    <h2 style="color:#fff;margin-bottom:8px;"><?php esc_html_e( 'Set Up Your Mosque in 5 Minutes', 'yourjannah' ); ?></h2>
    <p style="color:rgba(255,255,255,.7);font-size:14px;margin-bottom:20px;max-width:400px;margin-left:auto;margin-right:auto;">
        <?php esc_html_e( 'Our guided wizard will help you import prayer times, post your first announcement, and get your page ready to share.', 'yourjannah' ); ?>
    </p>
    <a href="<?php echo esc_url( home_url( '/mosque-setup' ) ); ?>"
       class="d-btn" style="display:inline-flex;background:#00ADEF;color:#fff;padding:14px 32px;font-size:16px;">
       <?php esc_html_e( 'Start Setup Wizard', 'yourjannah' ); ?> →
    </a>
</div>
<?php endif; ?>

<!-- ═══ SMART NUDGES ═══ -->
<?php
$nudges = function_exists( 'ynj_get_admin_nudges' ) ? ynj_get_admin_nudges( $mosque_id, $mosque, $s ) : [];
foreach ( $nudges as $nudge ) : ?>
<div class="d-card" style="border-left:4px solid <?php echo esc_attr( $nudge['color'] ); ?>;display:flex;align-items:center;gap:12px;padding:14px 16px;">
    <span style="font-size:24px;"><?php echo $nudge['icon']; ?></span>
    <div style="flex:1;">
        <strong style="font-size:14px;"><?php echo esc_html( $nudge['title'] ); ?></strong>
        <p style="font-size:12px;color:var(--text-dim);margin:2px 0 0;"><?php echo esc_html( $nudge['body'] ); ?></p>
    </div>
    <a href="<?php echo esc_url( $nudge['action_url'] ); ?>" class="d-btn d-btn--sm d-btn--primary" style="white-space:nowrap;"><?php echo esc_html( $nudge['action_label'] ); ?></a>
</div>
<?php endforeach; ?>

<!-- ═══ THIS WEEK'S HIGHLIGHTS (Dopamine Hero) ═══ -->
<div class="d-card" style="background:linear-gradient(135deg,#0a1628,#1a2d4a);border:none;color:#fff;">
    <h3 style="color:#fff;margin-bottom:14px;">📈 <?php esc_html_e( 'This Week', 'yourjannah' ); ?></h3>
    <div class="d-stats" style="margin-bottom:0;">
        <div class="d-stat" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);">
            <div class="d-stat__label" style="color:rgba(255,255,255,.5);"><?php esc_html_e( 'Content Views', 'yourjannah' ); ?></div>
            <div class="d-stat__value" style="color:#fff;"><?php echo number_format( $content_stats['views'] ); ?></div>
            <?php if ( $content_stats['change_pct'] != 0 ) : ?>
            <div style="font-size:11px;font-weight:700;color:<?php echo $content_stats['change_pct'] > 0 ? '#4ade80' : '#f87171'; ?>;margin-top:2px;">
                <?php echo $content_stats['change_pct'] > 0 ? '↑' : '↓'; ?><?php echo abs( $content_stats['change_pct'] ); ?>% <?php esc_html_e( 'vs last week', 'yourjannah' ); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="d-stat" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);">
            <div class="d-stat__label" style="color:rgba(255,255,255,.5);"><?php esc_html_e( 'New Subscribers', 'yourjannah' ); ?></div>
            <div class="d-stat__value" style="color:#00ADEF;">+<?php echo $new_subs_week; ?></div>
            <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:2px;"><?php echo $s['subscribers']; ?> <?php esc_html_e( 'total', 'yourjannah' ); ?></div>
        </div>
        <div class="d-stat" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);">
            <div class="d-stat__label" style="color:rgba(255,255,255,.5);"><?php esc_html_e( 'Income', 'yourjannah' ); ?></div>
            <div class="d-stat__value" style="color:#4ade80;">£<?php echo number_format( $donations_week / 100, 0 ); ?></div>
            <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:2px;"><?php echo $txn_week_count; ?> <?php esc_html_e( 'transactions', 'yourjannah' ); ?></div>
        </div>
    </div>

    <?php if ( ! empty( $txn_type_week ) ) : ?>
    <div style="margin-top:14px;">
        <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?php esc_html_e( 'Revenue by Type', 'yourjannah' ); ?></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php
            $type_icons = ['donation'=>'💝','sadaqah'=>'💰','patron'=>'🏅','store'=>'💬','event_ticket'=>'🎫','event_donation'=>'❤️','room_booking'=>'🏠','class_enrolment'=>'📚','business_sponsor'=>'⭐','sponsor'=>'⭐','service'=>'🔧'];
            $type_names = ['donation'=>'Donations','sadaqah'=>'Sadaqah','patron'=>'Patrons','store'=>'Superchats','event_ticket'=>'Events','event_donation'=>'Event Donations','room_booking'=>'Bookings','class_enrolment'=>'Classes','business_sponsor'=>'Sponsors','sponsor'=>'Sponsors','service'=>'Services'];
            foreach ( $txn_type_week as $tw ) :
                $icon = $type_icons[ $tw->item_type ] ?? '📋';
                $name = $type_names[ $tw->item_type ] ?? ucfirst( str_replace( '_', ' ', $tw->item_type ) );
            ?>
            <div style="padding:8px 12px;background:rgba(255,255,255,.08);border-radius:8px;border:1px solid rgba(255,255,255,.1);">
                <div style="font-size:14px;font-weight:800;color:#4ade80;"><?php echo $icon; ?> £<?php echo number_format( $tw->revenue / 100, 0 ); ?></div>
                <div style="font-size:10px;color:rgba(255,255,255,.4);"><?php echo esc_html( $name ); ?> (<?php echo (int) $tw->cnt; ?>)</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ RECENT TRANSACTIONS ═══ -->
<?php if ( ! empty( $recent_txns ) ) : ?>
<div class="d-card">
    <h3>💰 <?php esc_html_e( 'Recent Transactions', 'yourjannah' ); ?></h3>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid #e5e7eb;text-align:left;">
                <th style="padding:8px 6px;font-size:11px;font-weight:700;color:#6b7280;">Type</th>
                <th style="padding:8px 6px;font-size:11px;font-weight:700;color:#6b7280;">Item</th>
                <th style="padding:8px 6px;font-size:11px;font-weight:700;color:#6b7280;">Donor</th>
                <th style="padding:8px 6px;font-size:11px;font-weight:700;color:#6b7280;text-align:right;">Amount</th>
                <th style="padding:8px 6px;font-size:11px;font-weight:700;color:#6b7280;">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $recent_txns as $rtx ) :
                $t_icon = $type_icons[ $rtx->item_type ] ?? '📋';
                $t_name = $type_names[ $rtx->item_type ] ?? ucfirst( str_replace('_',' ', $rtx->item_type) );
                $is_cash = strpos( $rtx->stripe_payment_intent ?? '', 'test_' ) === 0;
            ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:8px 6px;white-space:nowrap;"><span style="padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#f3f4f6;"><?php echo $t_icon . ' ' . esc_html( $t_name ); ?></span></td>
                <td style="padding:8px 6px;font-weight:600;"><?php echo esc_html( $rtx->item_label ?: '—' ); ?><?php if ( $rtx->frequency !== 'once' ) : ?> <span style="font-size:10px;color:#7c3aed;font-weight:700;"><?php echo esc_html( ucfirst( $rtx->frequency ) ); ?></span><?php endif; ?></td>
                <td style="padding:8px 6px;color:#6b7280;"><?php echo esc_html( $rtx->donor_name ?: $rtx->donor_email ); ?></td>
                <td style="padding:8px 6px;text-align:right;font-weight:800;color:#16a34a;">£<?php echo number_format( $rtx->total_pence / 100, 2 ); ?><?php if ( $is_cash ) : ?> <span style="font-size:9px;color:#92400e;">💵</span><?php endif; ?></td>
                <td style="padding:8px 6px;font-size:11px;color:#9ca3b8;"><?php echo esc_html( date( 'j M H:i', strtotime( $rtx->completed_at ?: $rtx->created_at ) ) ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- ═══ TOP PERFORMING CONTENT ═══ -->
<?php if ( ! empty( $top_content ) ) : ?>
<div class="d-card">
    <h3>📊 <?php esc_html_e( 'Top Performing Content', 'yourjannah' ); ?></h3>
    <?php foreach ( $top_content as $i => $tc ) : ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;<?php echo $i < count( $top_content ) - 1 ? 'border-bottom:1px solid #f3f4f6;' : ''; ?>">
        <span style="font-size:18px;font-weight:800;color:<?php echo $i === 0 ? '#f59e0b' : ( $i === 1 ? '#94a3b8' : '#cd7f32' ); ?>;min-width:24px;">#<?php echo $i + 1; ?></span>
        <div style="flex:1;min-width:0;">
            <strong style="font-size:13px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $tc->title ?: 'Untitled' ); ?></strong>
            <span style="font-size:11px;color:var(--text-dim);"><?php echo $tc->content_type === 'announcement' ? '📢' : '📅'; ?> <?php echo esc_html( ucfirst( $tc->content_type ) ); ?></span>
        </div>
        <div style="text-align:right;white-space:nowrap;">
            <span style="font-size:13px;font-weight:700;">👁 <?php echo number_format( (int) $tc->total_views ); ?></span>
            <?php if ( (int) $tc->total_interested > 0 ) : ?>
            <span style="font-size:12px;color:#ec4899;margin-left:6px;">❤️ <?php echo (int) $tc->total_interested; ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ POSTING STREAK ═══ -->
<?php if ( $posting_streak['streak'] > 0 ) : ?>
<div class="d-card" style="border-left:4px solid #f59e0b;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;">🔥 <?php printf( esc_html__( '%d-week posting streak!', 'yourjannah' ), $posting_streak['streak'] ); ?></h3>
    </div>
    <div style="display:flex;gap:4px;margin-bottom:8px;">
        <?php
        $day_labels = [ 'M', 'T', 'W', 'T', 'F', 'S', 'S' ];
        for ( $d = 1; $d <= 7; $d++ ) :
            $posted = in_array( (string) $d, $posting_streak['this_week'], true );
            $is_today = ( (int) date( 'N' ) === $d );
        ?>
        <div style="flex:1;text-align:center;padding:6px 0;border-radius:6px;font-size:11px;font-weight:700;
            background:<?php echo $posted ? '#16a34a' : ( $is_today ? '#fef3c7' : '#f3f4f6' ); ?>;
            color:<?php echo $posted ? '#fff' : ( $is_today ? '#92400e' : '#9ca3af' ); ?>;">
            <?php echo $day_labels[ $d - 1 ]; ?>
        </div>
        <?php endfor; ?>
    </div>
    <?php if ( ! in_array( (string) date( 'N' ), $posting_streak['this_week'], true ) ) : ?>
    <p style="font-size:12px;color:#92400e;font-weight:600;"><?php esc_html_e( 'Post today to keep your streak alive!', 'yourjannah' ); ?> <a href="?section=announcements" style="color:#f59e0b;"><?php esc_html_e( 'Quick Post →', 'yourjannah' ); ?></a></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══ SUBSCRIBER GROWTH ═══ -->
<div class="d-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;">👥 <?php esc_html_e( 'Subscriber Growth', 'yourjannah' ); ?></h3>
        <span style="font-size:12px;color:var(--text-dim);"><?php esc_html_e( 'Last 4 weeks', 'yourjannah' ); ?></span>
    </div>
    <?php
    $max_bar = max( 1, max( $sub_growth['weekly_data'] ) );
    ?>
    <div style="display:flex;align-items:end;gap:4px;height:60px;margin-bottom:8px;">
        <?php foreach ( $sub_growth['weekly_data'] as $wi => $wcount ) :
            $h = max( 4, round( $wcount / $max_bar * 56 ) );
        ?>
        <div style="flex:1;background:<?php echo $wi === 3 ? 'var(--primary)' : '#d1d5db'; ?>;height:<?php echo $h; ?>px;border-radius:4px;position:relative;" title="<?php echo $wcount; ?> new">
            <?php if ( $wcount > 0 ) : ?>
            <span style="position:absolute;top:-16px;left:50%;transform:translateX(-50%);font-size:10px;font-weight:700;color:var(--text-dim);"><?php echo $wcount; ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:16px;font-size:12px;color:var(--text-dim);">
        <span><strong style="color:var(--text);"><?php echo $s['subscribers']; ?></strong> <?php esc_html_e( 'total', 'yourjannah' ); ?></span>
        <span><strong style="color:var(--primary);">+<?php echo $sub_growth['this_week']; ?></strong> <?php esc_html_e( 'this week', 'yourjannah' ); ?></span>
        <span><strong>+<?php echo $sub_growth['this_month']; ?></strong> <?php esc_html_e( 'this month', 'yourjannah' ); ?></span>
    </div>
</div>

<!-- ═══ MOSQUE LEAGUE POSITION ═══ -->
<?php
$dash_league = function_exists( 'ynj_get_league_standings' ) ? ynj_get_league_standings( $mosque_id, $mosque->city ?? null, 7 ) : null;
if ( $dash_league && $dash_league['total'] > 0 ) :
?>
<div class="d-card" style="background:linear-gradient(135deg,#78350f,#92400e);color:#fff;border:none;">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
        <span style="font-size:36px;"><?php echo $dash_league['tier']['icon']; ?></span>
        <div style="flex:1;">
            <h3 style="margin:0;color:#fff;"><?php echo esc_html( $dash_league['tier']['name'] ); ?> <?php esc_html_e( 'League', 'yourjannah' ); ?></h3>
            <p style="font-size:12px;color:rgba(255,255,255,.6);margin:2px 0 0;">
                <?php printf( esc_html__( '%d mosques · %s · This week', 'yourjannah' ), $dash_league['total'], esc_html( $mosque->city ?? 'National' ) ); ?>
            </p>
        </div>
        <?php if ( $dash_league['rank'] > 0 ) : ?>
        <div style="text-align:center;background:rgba(255,255,255,.15);padding:10px 16px;border-radius:12px;">
            <div style="font-size:28px;font-weight:800;">#<?php echo $dash_league['rank']; ?></div>
            <div style="font-size:10px;opacity:.7;"><?php esc_html_e( 'rank', 'yourjannah' ); ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php if ( $dash_league['rank'] > 0 && ! empty( $dash_league['breakdown'] ) ) :
        $dbd = $dash_league['breakdown'];
    ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px;font-size:11px;">
        <?php if ( $dbd['page_views'] ) : ?><span style="padding:4px 10px;background:rgba(255,255,255,.12);border-radius:6px;">👁 <?php echo $dbd['page_views']; ?> views</span><?php endif; ?>
        <?php if ( $dbd['reactions'] ) : ?><span style="padding:4px 10px;background:rgba(255,255,255,.12);border-radius:6px;">❤️ <?php echo $dbd['reactions']; ?> reactions</span><?php endif; ?>
        <?php if ( $dbd['rsvps'] ) : ?><span style="padding:4px 10px;background:rgba(255,255,255,.12);border-radius:6px;">📋 <?php echo $dbd['rsvps']; ?> RSVPs</span><?php endif; ?>
        <?php if ( $dbd['checkins'] ) : ?><span style="padding:4px 10px;background:rgba(255,255,255,.12);border-radius:6px;">📍 <?php echo $dbd['checkins']; ?> check-ins</span><?php endif; ?>
        <?php if ( $dbd['new_subs'] ) : ?><span style="padding:4px 10px;background:rgba(255,255,255,.12);border-radius:6px;">👥 +<?php echo $dbd['new_subs']; ?> new</span><?php endif; ?>
        <?php if ( $dbd['content_posted'] ) : ?><span style="padding:4px 10px;background:rgba(255,255,255,.12);border-radius:6px;">📢 <?php echo $dbd['content_posted']; ?> posts</span><?php endif; ?>
    </div>
    <p style="font-size:11px;color:rgba(255,255,255,.5);margin-top:8px;"><?php esc_html_e( 'Score = engagement per member. Post more, get reactions, grow subscribers to climb!', 'yourjannah' ); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══ RECENT ACTIVITY FEED ═══ -->
<?php if ( ! empty( $activity_feed ) ) : ?>
<div class="d-card">
    <h3>🕐 <?php esc_html_e( 'Recent Activity', 'yourjannah' ); ?></h3>
    <?php
    $activity_icons = [ 'booking' => '📋', 'subscriber' => '👥', 'donation' => '💷', 'enquiry' => '✉️', 'patron' => '🏅' ];
    foreach ( $activity_feed as $act ) :
        $icon = $activity_icons[ $act->activity_type ] ?? '📌';
        $ago = human_time_diff( strtotime( $act->when_at ) );
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f9fafb;font-size:13px;">
        <span style="font-size:16px;"><?php echo $icon; ?></span>
        <div style="flex:1;min-width:0;">
            <strong><?php echo esc_html( $act->who ?: 'Someone' ); ?></strong>
            <span style="color:var(--text-dim);"><?php echo esc_html( $act->what ); ?></span>
        </div>
        <span style="font-size:11px;color:var(--text-dim);white-space:nowrap;"><?php echo esc_html( $ago ); ?> ago</span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ COMMUNITY IBADAH STATS ═══ -->
<?php
$ib_table = YNJ_DB::table( 'ibadah_logs' );
$ib_week_start = date( 'Y-m-d', strtotime( 'Monday this week' ) );
$ib_week = $wpdb->get_row( $wpdb->prepare(
    "SELECT COALESCE(SUM(fajr+dhuhr+asr+maghrib+isha),0) AS prayers,
            COALESCE(SUM(quran_pages),0) AS pages,
            COUNT(DISTINCT user_id) AS active_members
     FROM $ib_table WHERE mosque_id = %d AND log_date >= %s",
    $mosque_id, $ib_week_start
) );
$ib_prayers = (int) ( $ib_week->prayers ?? 0 );
$ib_pages   = (int) ( $ib_week->pages ?? 0 );
$ib_active  = (int) ( $ib_week->active_members ?? 0 );
$ch_table   = YNJ_DB::table( 'community_challenges' );
$ib_challenge = $wpdb->get_row( $wpdb->prepare(
    "SELECT title, target_value, current_value, status FROM $ch_table WHERE mosque_id = %d AND status IN ('active','completed') AND end_date >= %s ORDER BY id DESC LIMIT 1",
    $mosque_id, date( 'Y-m-d' )
) );
?>
<?php if ( $ib_prayers > 0 || $ib_pages > 0 || $ib_challenge ) : ?>
<div class="d-card" style="border-left:4px solid #7c3aed;">
    <h3 style="margin-bottom:10px;">🤲 <?php esc_html_e( 'Community Ibadah This Week', 'yourjannah' ); ?></h3>
    <div class="d-stats" style="margin-bottom:8px;">
        <div class="d-stat">
            <div class="d-stat__label"><?php esc_html_e( 'Prayers', 'yourjannah' ); ?></div>
            <div class="d-stat__value" style="color:#7c3aed;"><?php echo number_format( $ib_prayers ); ?></div>
        </div>
        <div class="d-stat">
            <div class="d-stat__label"><?php esc_html_e( 'Quran Pages', 'yourjannah' ); ?></div>
            <div class="d-stat__value"><?php echo number_format( $ib_pages ); ?></div>
        </div>
        <div class="d-stat">
            <div class="d-stat__label"><?php esc_html_e( 'Active Members', 'yourjannah' ); ?></div>
            <div class="d-stat__value" style="color:#0369a1;"><?php echo $ib_active; ?></div>
        </div>
    </div>
    <?php if ( $ib_challenge ) :
        $ch_pct = (int) $ib_challenge->target_value > 0 ? min( 100, round( (int) $ib_challenge->current_value / (int) $ib_challenge->target_value * 100 ) ) : 0;
    ?>
    <div style="padding-top:8px;border-top:1px solid #f3f4f6;">
        <p style="font-size:12px;font-weight:700;color:#7c3aed;margin-bottom:6px;">🏆 <?php echo esc_html( $ib_challenge->title ); ?></p>
        <div style="background:#e5e7eb;border-radius:6px;height:8px;overflow:hidden;margin-bottom:4px;">
            <div style="background:<?php echo $ib_challenge->status === 'completed' ? '#16a34a' : '#7c3aed'; ?>;height:100%;width:<?php echo $ch_pct; ?>%;border-radius:6px;transition:width .3s;"></div>
        </div>
        <p style="font-size:11px;color:var(--text-dim);">
            <?php echo number_format( (int) $ib_challenge->current_value ); ?>/<?php echo number_format( (int) $ib_challenge->target_value ); ?> (<?php echo $ch_pct; ?>%)
            <?php if ( $ib_challenge->status === 'completed' ) : ?>
            — <span style="color:#16a34a;font-weight:700;"><?php esc_html_e( 'Completed!', 'yourjannah' ); ?> 🎉</span>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══ GRATITUDE FROM YOUR COMMUNITY ═══ -->
<?php
$grat_table = YNJ_DB::table( 'gratitude_posts' );
$recent_grat = $wpdb->get_results( $wpdb->prepare(
    "SELECT message, created_at FROM $grat_table WHERE mosque_id = %d ORDER BY created_at DESC LIMIT 5",
    $mosque_id
) );
if ( $recent_grat ) : ?>
<div class="d-card" style="border-left:4px solid #ec4899;">
    <h3 style="margin-bottom:8px;">💖 <?php esc_html_e( 'Your Community Says...', 'yourjannah' ); ?></h3>
    <?php foreach ( $recent_grat as $g ) : ?>
    <div style="padding:8px 12px;margin-bottom:6px;background:#fdf2f8;border-radius:8px;">
        <p style="font-size:13px;color:#831843;margin:0;font-style:italic;">&ldquo;<?php echo esc_html( $g->message ); ?>&rdquo;</p>
        <p style="font-size:10px;color:#be185d;margin:2px 0 0;"><?php echo esc_html( human_time_diff( strtotime( $g->created_at ) ) ); ?> ago</p>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

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
