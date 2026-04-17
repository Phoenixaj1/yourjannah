<?php
/**
 * Template: Mosque Admin Dashboard (Pure PHP)
 *
 * Replaced JS SPA with server-rendered PHP pages.
 * Each section is a separate include file in dashboard/ directory.
 *
 * @package YourJannah
 */

// ── Auth Gate: WP login + mosque admin role ──
if ( ! is_user_logged_in() ) {
    wp_redirect( home_url( '/login?redirect=' . urlencode( '/dashboard' ) ) );
    exit;
}

$wp_uid = get_current_user_id();
$mosque_id = (int) get_user_meta( $wp_uid, 'ynj_mosque_id', true );
$mosque_ids = get_user_meta( $wp_uid, 'ynj_mosque_ids', true ) ?: [];

// Check roles: admin, mosque admin, or imam
$is_admin = current_user_can( 'manage_options' ) || in_array( 'ynj_mosque_admin', (array) wp_get_current_user()->roles );
$is_imam_user = in_array( 'ynj_imam', (array) wp_get_current_user()->roles, true );

// Imam: get mosque_id from ynj_mosques.imam_user_id
if ( $is_imam_user && ! $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $imam_mosque = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM " . YNJ_DB::table( 'mosques' ) . " WHERE imam_user_id = %d LIMIT 1",
        $wp_uid
    ) );
    if ( $imam_mosque ) {
        $mosque_id = (int) $imam_mosque;
        update_user_meta( $wp_uid, 'ynj_mosque_id', $mosque_id );
    }
}

// WP admins (manage_options) without a mosque_id: auto-assign first mosque
if ( $is_admin && ! $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $first_mosque = $wpdb->get_var( "SELECT id FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY id ASC LIMIT 1" );
    if ( $first_mosque ) {
        $mosque_id = (int) $first_mosque;
        update_user_meta( $wp_uid, 'ynj_mosque_id', $mosque_id );
        update_user_meta( $wp_uid, 'ynj_mosque_ids', [ $mosque_id ] );
    }
}

if ( ( ! $is_admin && ! $is_imam_user ) || ! $mosque_id ) {
    get_header(); ?>
    <main class="ynj-main">
        <section class="ynj-card" style="text-align:center;padding:40px 20px;">
            <div style="font-size:48px;margin-bottom:12px;">🔒</div>
            <h2><?php esc_html_e( 'Access Denied', 'yourjannah' ); ?></h2>
            <p class="ynj-text-muted"><?php esc_html_e( 'You need a mosque admin account to access the dashboard.', 'yourjannah' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/register' ) ); ?>" class="ynj-btn" style="margin-top:12px;display:inline-flex;"><?php esc_html_e( 'Register Your Mosque', 'yourjannah' ); ?></a>
        </section>
    </main>
    <?php get_footer(); return;
}

// ── Load mosque data ──
global $wpdb;
$mosque = ynj_get_mosque_by_id( $mosque_id );
if ( ! $mosque && function_exists( 'ynj_get_mosque' ) ) {
    // Fallback: try looking up by ID directly
    $mt = YNJ_DB::table( 'mosques' );
    $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
}
$mosque_name = $mosque ? $mosque->name : 'Your Mosque';
$mosque_slug = $mosque ? $mosque->slug : '';

// ── Section routing ──
$section = sanitize_text_field( $_GET['section'] ?? 'overview' );
$dash_dir = get_template_directory() . '/page-templates/dashboard/';

// ── Sidebar navigation (grouped by purpose) ──
$nav_groups = [
    'main' => [
        'label' => '',
        'items' => [
            [ 'key' => 'overview', 'icon' => '🎯', 'label' => 'Dashboard' ],
        ],
    ],
    'engage' => [
        'label' => 'ENGAGE',
        'items' => [
            [ 'key' => 'announcements',  'icon' => '📢', 'label' => 'Announcements' ],
            [ 'key' => 'events',         'icon' => '📅', 'label' => 'Events' ],
            [ 'key' => 'broadcast',      'icon' => '📤', 'label' => 'Broadcast' ],
            [ 'key' => 'subscribers',    'icon' => '👥', 'label' => 'Subscribers' ],
        ],
    ],
    'revenue' => [
        'label' => 'REVENUE',
        'items' => [
            [ 'key' => 'patrons',   'icon' => '🏅', 'label' => 'Patrons' ],
            [ 'key' => 'funds',     'icon' => '💰', 'label' => 'Donation Funds' ],
            [ 'key' => 'campaigns', 'icon' => '💝', 'label' => 'Fundraising' ],
            [ 'key' => 'sponsors',  'icon' => '⭐', 'label' => 'Sponsors' ],
            [ 'key' => 'appeals',   'icon' => '📨', 'label' => 'Charity Appeals' ],
        ],
    ],
    'manage' => [
        'label' => 'MANAGE',
        'items' => [
            [ 'key' => 'prayers',    'icon' => '🕐', 'label' => 'Prayer Times' ],
            [ 'key' => 'classes',    'icon' => '🎓', 'label' => 'Classes' ],
            [ 'key' => 'rooms',      'icon' => '🏠', 'label' => 'Rooms' ],
            [ 'key' => 'bookings',   'icon' => '📋', 'label' => 'Bookings' ],
            [ 'key' => 'services',   'icon' => '🛎️',  'label' => 'Masjid Services' ],
            [ 'key' => 'enquiries',  'icon' => '✉️',  'label' => 'Enquiries' ],
            [ 'key' => 'madrassah',  'icon' => '📚', 'label' => 'Madrassah' ],
        ],
    ],
    'admin' => [
        'label' => 'ADMIN',
        'items' => [
            [ 'key' => 'settings', 'icon' => '⚙️', 'label' => 'Settings' ],
        ],
    ],
];

// Imam sees limited sidebar
if ( $is_imam_user && ! $is_admin ) {
    $nav_groups = [
        'main' => [
            'label' => '',
            'items' => [
                [ 'key' => 'announcements', 'icon' => '📢', 'label' => 'Announcements' ],
            ],
        ],
        'engage' => [
            'label' => 'ENGAGE',
            'items' => [
                [ 'key' => 'broadcast', 'icon' => '📤', 'label' => 'Broadcast' ],
            ],
        ],
    ];
}

// Flatten for section_help lookup
$nav_items = [];
foreach ( $nav_groups as $g ) { foreach ( $g['items'] as $item ) { $nav_items[] = $item; } }

// Wizard tips for each section
$section_help = [
    'overview'      => 'Your dashboard shows key metrics and action items. Check here for pending bookings, unanswered enquiries, and a snapshot of your mosque\'s activity.',
    'announcements' => 'Post announcements to keep your congregation informed. Pinned announcements stay at the top. Choose "urgent" for time-sensitive messages.',
    'events'        => 'Create events like talks, classes, community gatherings, and sports. Set capacity limits, ticket prices, and even enable live streaming.',
    'prayers'       => 'Manage Jumu\'ah slots (multiple khutbahs) and Eid prayer times. Daily prayer times come automatically from Aladhan based on your mosque\'s location.',
    'bookings'      => 'View and approve/reject room bookings and event registrations from your community.',
    'rooms'         => 'Add rooms available for hire — set hourly and daily rates, capacity, and descriptions.',
    'classes'       => 'Create and manage educational classes — Quran, Arabic, Islamic Studies, etc. Track enrolments and set pricing.',
    'enquiries'     => 'Respond to contact form submissions. Mark as read, reply via email, or resolve.',
    'subscribers'   => 'View everyone subscribed to your mosque. Export as CSV for your records.',
    'broadcast'     => 'Send push notifications and emails to your subscribers. Upload CSV lists to grow your audience. Limit: 3 per week.',
    'campaigns'     => 'Create fundraising campaigns with targets and categories. Track donations and donor counts.',
    'services'      => 'List services your mosque offers — nikkah, funeral, counselling, etc. Manage enquiries for each service.',
    'patrons'       => 'View your monthly patrons (recurring supporters). See total revenue and patron breakdown by tier.',
    'funds'         => 'Manage the donation funds available on your niyyah bar. Add custom funds like "New Roof" or "Kitchen Renovation".',
    'madrassah'     => 'Manage your Islamic school — students, attendance, terms, fees, and progress reports.',
    'appeals'       => 'Review incoming charity appeal requests. Accept to schedule a date and fee, or decline. Enable appeals in Settings to appear in the marketplace.',
    'settings'      => 'Update your mosque profile (name, address, phone, website, description) and manage your admin team.',
];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $mosque_name ); ?> — Dashboard</title>
<style>
:root{--primary:#287e61;--primary-light:#e6f2ed;--primary-dark:#1c4644;--bg:#FAFAF8;--card:#fff;--border:#e5e7eb;--text:#1a1a1a;--text-dim:#6b7280;--radius:12px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}
a{color:var(--primary);text-decoration:none;}

/* Sidebar */
.d-sidebar{width:240px;background:var(--primary-dark);color:#fff;padding:16px 0;flex-shrink:0;position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:100;}
.d-sidebar__logo{padding:12px 20px 20px;font-size:15px;font-weight:800;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:8px;}
.d-sidebar__mosque{padding:0 20px 12px;font-size:12px;color:rgba(255,255,255,.5);border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:8px;}
.d-nav{list-style:none;}
.d-nav__item{display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:13px;font-weight:500;color:rgba(255,255,255,.7);cursor:pointer;transition:all .15s;text-decoration:none;}
.d-nav__item:hover{background:rgba(255,255,255,.08);color:#fff;}
.d-nav__group{padding:16px 20px 4px;font-size:10px;font-weight:700;letter-spacing:1.5px;color:rgba(255,255,255,.35);list-style:none;}
.d-nav__item--active{background:rgba(255,255,255,.12);color:#fff;font-weight:700;border-right:3px solid #fff;}
.d-nav__icon{width:20px;text-align:center;font-size:14px;}
.d-nav__logout{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:8px;}

/* Main content */
.d-main{margin-left:240px;flex:1;padding:24px 32px;max-width:1000px;}
.d-header{margin-bottom:20px;}
.d-header h1{font-size:22px;font-weight:800;margin-bottom:4px;}
.d-header p{font-size:13px;color:var(--text-dim);}

/* Cards */
.d-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;}
.d-card h3{font-size:15px;font-weight:700;margin-bottom:12px;}
.d-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;}
.d-stat{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px;}
.d-stat__label{font-size:11px;color:var(--text-dim);text-transform:uppercase;font-weight:600;letter-spacing:.3px;}
.d-stat__value{font-size:24px;font-weight:800;margin-top:4px;}

/* Help tip */
.d-help{background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;font-size:13px;color:#1e40af;display:flex;align-items:start;gap:8px;}
.d-help__icon{font-size:16px;flex-shrink:0;margin-top:1px;}
.d-help__dismiss{margin-left:auto;background:none;border:none;color:#1e40af;cursor:pointer;font-size:16px;opacity:.5;}

/* Forms */
.d-field{margin-bottom:12px;}
.d-field label{display:block;font-size:12px;font-weight:600;color:var(--text-dim);margin-bottom:4px;}
.d-field input,.d-field textarea,.d-field select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;}
.d-field input:focus,.d-field textarea:focus,.d-field select:focus{outline:none;border-color:var(--primary);}
.d-field textarea{resize:vertical;}
.d-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

/* Buttons */
.d-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .15s;}
.d-btn--primary{background:var(--primary);color:#fff;}
.d-btn--primary:hover{opacity:.9;}
.d-btn--danger{background:#dc2626;color:#fff;}
.d-btn--outline{background:transparent;border:1px solid var(--border);color:var(--text);}
.d-btn--outline:hover{background:#f9fafb;}
.d-btn--sm{padding:6px 12px;font-size:12px;}

/* Tables */
.d-table{width:100%;border-collapse:collapse;font-size:13px;}
.d-table th{text-align:left;padding:10px 12px;background:#f9fafb;border-bottom:1px solid var(--border);font-weight:700;font-size:11px;text-transform:uppercase;color:var(--text-dim);}
.d-table td{padding:10px 12px;border-bottom:1px solid #f3f4f6;vertical-align:top;}
.d-table tr:hover{background:#fafafa;}

/* Badges */
.d-badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;}
.d-badge--green{background:#dcfce7;color:#166534;}
.d-badge--yellow{background:#fef3c7;color:#92400e;}
.d-badge--red{background:#fee2e2;color:#991b1b;}
.d-badge--blue{background:#dbeafe;color:#1e40af;}
.d-badge--gray{background:#f3f4f6;color:#374151;}

/* Alerts */
.d-alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:12px;}
.d-alert--success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.d-alert--error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.d-alert--info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}

/* Empty state */
.d-empty{text-align:center;padding:40px 20px;color:var(--text-dim);}
.d-empty__icon{font-size:40px;margin-bottom:12px;}

/* Mobile */
.d-hamburger{display:none;position:fixed;top:12px;left:12px;z-index:200;background:var(--primary-dark);color:#fff;border:none;border-radius:8px;padding:8px 12px;font-size:18px;cursor:pointer;}
@media(max-width:768px){
    .d-sidebar{transform:translateX(-100%);transition:transform .2s;}
    .d-sidebar--open{transform:translateX(0);}
    .d-main{margin-left:0;padding:16px;padding-top:56px;}
    .d-hamburger{display:block;}
    .d-row{grid-template-columns:1fr;}
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<button class="d-hamburger" onclick="document.getElementById('d-sidebar').classList.toggle('d-sidebar--open')">☰</button>

<nav class="d-sidebar" id="d-sidebar">
    <div class="d-sidebar__logo">🕌 YourJannah</div>
    <div class="d-sidebar__mosque"><?php echo esc_html( $mosque_name ); ?></div>
    <ul class="d-nav">
        <?php foreach ( $nav_groups as $gk => $group ) : ?>
        <?php if ( $group['label'] ) : ?>
        <li class="d-nav__group"><?php echo esc_html( $group['label'] ); ?></li>
        <?php endif; ?>
        <?php foreach ( $group['items'] as $nav ) : ?>
        <li>
            <a class="d-nav__item<?php echo $section === $nav['key'] ? ' d-nav__item--active' : ''; ?>"
               href="<?php echo esc_url( home_url( '/dashboard?section=' . $nav['key'] ) ); ?>">
                <span class="d-nav__icon"><?php echo $nav['icon']; ?></span>
                <?php echo esc_html( $nav['label'] ); ?>
            </a>
        </li>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <li class="d-nav__logout">
            <a class="d-nav__item" href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug ) ); ?>">🕌 <?php esc_html_e( 'View Mosque Page', 'yourjannah' ); ?></a>
            <a class="d-nav__item" href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug . '/help' ) ); ?>">🎫 <?php esc_html_e( 'Get Help', 'yourjannah' ); ?></a>
            <a class="d-nav__item" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">🚪 <?php esc_html_e( 'Logout', 'yourjannah' ); ?></a>
        </li>
    </ul>
</nav>

<main class="d-main">
    <!-- Help tip for current section -->
    <?php if ( isset( $section_help[ $section ] ) ) : ?>
    <div class="d-help" id="d-help-tip">
        <span class="d-help__icon">💡</span>
        <span><?php echo esc_html( $section_help[ $section ] ); ?></span>
        <button class="d-help__dismiss" onclick="this.parentElement.style.display='none'" title="Dismiss">✕</button>
    </div>
    <?php endif; ?>

    <?php
    // Include section template
    $section_file = $dash_dir . $section . '.php';
    if ( file_exists( $section_file ) ) {
        include $section_file;
    } else {
        // Section not yet built — show coming soon
        ?>
        <div class="d-header"><h1><?php echo esc_html( ucfirst( str_replace( '-', ' ', $section ) ) ); ?></h1></div>
        <div class="d-card">
            <div class="d-empty">
                <div class="d-empty__icon">🚧</div>
                <h3><?php esc_html_e( 'Coming Soon', 'yourjannah' ); ?></h3>
                <p><?php esc_html_e( 'This section is being built. Check back soon!', 'yourjannah' ); ?></p>
            </div>
        </div>
        <?php
    }
    ?>
</main>

</body>
</html>
