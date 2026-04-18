<?php
/**
 * Plugin Name: YourJannah
 * Description: Mosque community platform — prayer times, announcements, events, bookings, business directory, donations.
 * Version: 1.0.0
 * Author: YourNiyyah
 */
if (!defined('ABSPATH')) exit;

define('YNJ_VERSION', '2.4.0');
define('YNJ_DIR', plugin_dir_path(__FILE__));
define('YNJ_URL', plugin_dir_url(__FILE__));
if ( ! defined( 'YNJ_TABLE_PREFIX' ) ) {
    define('YNJ_TABLE_PREFIX', 'ynj_');
}

// One-time demo seeder (run via /wp-admin/admin.php?ynj_seed_extra=1)
if ( is_admin() && file_exists( YNJ_DIR . 'seed-demo-extra.php' ) ) {
    require_once YNJ_DIR . 'seed-demo-extra.php';
}

// Autoloader
spl_autoload_register(function($class) {
    $map = [
        'YNJ_DB'              => 'inc/class-ynj-db.php',
        'YNJ_Auth'            => 'inc/class-ynj-auth.php',
        'YNJ_Prayer'          => 'inc/class-ynj-prayer.php',
        'YNJ_Push'            => 'inc/class-ynj-push.php',
        // Archived — replaced by yourjannah-starter theme templates
        // 'YNJ_Router'       => '_archive/class-ynj-router.php',
        // 'YNJ_Renderer'     => '_archive/class-ynj-renderer.php',
        'YNJ_Admin'           => 'inc/class-ynj-admin.php',
        'YNJ_Dashboard'       => 'dashboard/class-ynj-dashboard.php',
        'YNJ_API_Mosques'     => 'api/class-ynj-api-mosques.php',
        'YNJ_API_Prayer'      => 'api/class-ynj-api-prayer.php',
        'YNJ_API_Announcements' => 'api/class-ynj-api-announcements.php',
        'YNJ_API_Events'      => 'api/class-ynj-api-events.php',
        'YNJ_API_Bookings'    => 'api/class-ynj-api-bookings.php',
        'YNJ_API_Directory'   => 'api/class-ynj-api-directory.php',
        'YNJ_API_Subscribe'   => 'api/class-ynj-api-subscribe.php',
        'YNJ_API_Admin'       => 'api/class-ynj-api-admin.php',
        'YNJ_Stripe'          => 'inc/class-ynj-stripe.php',
        'YNJ_API_Stripe'      => 'api/class-ynj-api-stripe.php',
        'YNJ_API_Search'      => 'api/class-ynj-api-search.php',
        'YNJ_Cron'            => 'inc/class-ynj-cron.php',
        'YNJ_Notify'          => 'inc/class-ynj-notify.php',
        'YNJ_User_Auth'       => 'inc/class-ynj-user-auth.php',
        'YNJ_API_User'        => 'api/class-ynj-api-user.php',
        'YNJ_API_Campaigns'   => 'api/class-ynj-api-campaigns.php',
        'YNJ_API_DFM_Webhook' => 'api/class-ynj-api-dfm-webhook.php',
        'YNJ_API_Classes'     => 'api/class-ynj-api-classes.php',
        'YNJ_API_Patrons'     => 'api/class-ynj-api-patrons.php',
        'YNJ_API_Madrassah'   => 'api/class-ynj-api-madrassah.php',
        'YNJ_API_Subscriptions'    => 'api/class-ynj-api-subscriptions.php',
        'YNJ_API_Masjid_Services'  => 'api/class-ynj-api-masjid-services.php',
        'YNJ_WP_Auth'              => 'inc/class-ynj-wp-auth.php',
        'YNJ_Platform_Admin'       => 'inc/class-ynj-platform-admin.php',
        'YNJ_API_Media'            => 'api/class-ynj-api-media.php',
        'YNJ_Cache'                => 'inc/class-ynj-cache.php',
        'YNJ_API_Points'           => 'api/class-ynj-api-points.php',
        'YNJ_API_Intentions'       => 'api/class-ynj-api-intentions.php',
        'YNJ_API_Sponsor_YJ'       => 'api/class-ynj-api-sponsor-yj.php',
        'YNJ_Pool_Ledger'          => 'inc/class-ynj-pool-ledger.php',
        'YNJ_API_Donations'        => 'api/class-ynj-api-donations.php',
        'YNJ_Social_Auth'          => 'inc/class-ynj-social-auth.php',
        'YNJ_Interest_Notify'      => 'inc/class-ynj-interest-notify.php',
    ];
    if (isset($map[$class])) {
        require_once YNJ_DIR . $map[$class];
    }
});

// Install DB + roles on activation
register_activation_hook(__FILE__, ['YNJ_DB', 'install']);
register_activation_hook(__FILE__, ['YNJ_WP_Auth', 'install_roles']);
register_deactivation_hook(__FILE__, ['YNJ_WP_Auth', 'remove_roles']);

// Ensure roles exist (in case plugin was updated without deactivation/reactivation)
add_action('init', function() {
    if ( ! get_role( 'ynj_mosque_admin' ) || ! get_role( 'ynj_imam' ) ) {
        YNJ_WP_Auth::install_roles();
    }
    // Initialize social login OAuth routes
    YNJ_Social_Auth::init();
    // Auto-configure Stripe keys on first load
    YNJ_Stripe::auto_configure();
    // Auto-configure Postmark token (YourJannah server)
    if ( get_option( 'ynj_postmark_token' ) !== '914b09a1-a95f-44ec-9a31-c3058e485198' ) {
        update_option( 'ynj_postmark_token', '914b09a1-a95f-44ec-9a31-c3058e485198' );
    }
    // Run DB migrations if schema version changed (once only, not every page load)
    $db_ver = get_option( 'ynj_db_version', '' );
    if ( $db_ver !== YNJ_DB::SCHEMA_VERSION && ! get_transient( 'ynj_db_migrating' ) ) {
        set_transient( 'ynj_db_migrating', 1, 60 ); // Prevent concurrent migrations
        YNJ_DB::install();
        delete_transient( 'ynj_db_migrating' );
    }
}, 5);

// ── Hide WP admin bar + block /wp-admin for congregation members ──
// Only mosque admins, imams, coordinators, and WP admins see the admin bar.
add_action( 'init', function() {
    if ( ! is_user_logged_in() ) return;
    $user = wp_get_current_user();
    // Only WP administrators see the admin bar — everyone else uses the YNJ dashboard
    $has_admin_role = in_array( 'administrator', (array) $user->roles, true );
    if ( ! $has_admin_role ) {
        show_admin_bar( false );
        // Block /wp-admin access (allow admin-ajax.php for API calls)
        if ( is_admin() && ! wp_doing_ajax() && ! defined( 'DOING_CRON' ) ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }
    }
}, 20 );

// ── Bulletproof auto-login via redirect ──
// JS redirects to: /?ynj_autologin=WP_USER_ID&ynj_token=TOKEN&redirect=DESTINATION
// This sets the WP auth cookie during a real page request (not AJAX), which is 100% reliable.
add_action( 'init', function() {
    if ( ! isset( $_GET['ynj_autologin'] ) ) return;
    $wp_user_id = (int) ( $_GET['ynj_autologin'] ?? 0 );
    $token      = sanitize_text_field( $_GET['ynj_token'] ?? '' );
    $redirect   = wp_validate_redirect( sanitize_text_field( $_GET['redirect'] ?? '' ), home_url( '/' ) );

    if ( ! $wp_user_id || ! $token ) {
        wp_safe_redirect( $redirect );
        exit;
    }

    // Verify token belongs to the requested WP user (prevents session hijacking)
    if ( class_exists( 'YNJ_DB' ) ) {
        global $wpdb;
        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );
        // Token must match AND belong to a ynj_user linked to this WP user
        $ynj_user_id = (int) get_user_meta( $wp_user_id, 'ynj_user_id', true );
        $valid = $ynj_user_id && (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d AND token_hash = %s",
            $ynj_user_id, $token_hash
        ) );

        if ( $valid ) {
            wp_set_current_user( $wp_user_id );
            wp_set_auth_cookie( $wp_user_id, true );
        }
    }

    wp_safe_redirect( $redirect );
    exit;
}, 1 );

// Legacy AJAX session handler REMOVED — was a security vulnerability
// (accepted wp_user_id without any token validation = account takeover)
// All auth now uses the ynj_autologin redirect with token verification.

// Configure wp_mail — Postmark SMTP (reliable delivery)
// yourjannah.com is DKIM + Return-Path verified in Postmark
add_filter('wp_mail_from', function() { return 'noreply@yourjannah.com'; });
add_filter('wp_mail_from_name', function() { return 'YourJannah'; });
add_action('phpmailer_init', function($phpmailer) {
    $token = get_option('ynj_postmark_token', '');
    if ( $token ) {
        // Postmark SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.postmarkapp.com';
        $phpmailer->Port = 587;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $token;
        $phpmailer->Password = $token;
        $phpmailer->SMTPSecure = 'tls';
    } else {
        // Fallback: localhost Postfix
        $phpmailer->isSMTP();
        $phpmailer->Host = 'localhost';
        $phpmailer->Port = 25;
        $phpmailer->SMTPAuth = false;
        $phpmailer->SMTPAutoTLS = false;
    }
});

// Test email endpoint — only available in WP_DEBUG mode
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
add_action('init', function() {
    if (isset($_GET['ynj_test_email']) && isset($_GET['key']) && $_GET['key'] === 'ynj_mail_test_2026') {
        $to = sanitize_email($_GET['ynj_test_email']);
        if (!$to) wp_die('Provide email: ?ynj_test_email=you@email.com&key=ynj_mail_test_2026');
        $sent = wp_mail($to, 'YourJannah Test Email', "Assalamu Alaikum!\n\nThis is a test email from YourJannah.\nIf you received this, email sending is working.\n\nTime: " . current_time('mysql') . "\n\nYourJannah Team");
        if ($sent) {
            wp_die("✅ Email sent to $to. Check inbox + spam folder.");
        } else {
            global $phpmailer;
            $error = '';
            if (isset($phpmailer) && is_object($phpmailer)) {
                $error = $phpmailer->ErrorInfo;
            }
            wp_die("❌ wp_mail() failed. Error: " . ($error ?: 'Unknown') . "<br>Last PHP error: " . print_r(error_get_last(), true));
        }
    }
});
} // end WP_DEBUG test email

// Auto-upgrade DB on version change
add_action('admin_init', function() {
    $installed = get_option('ynj_db_version', '');
    if ($installed !== YNJ_DB::SCHEMA_VERSION || isset($_GET['ynj_force_db'])) {
        YNJ_DB::install();
        update_option('ynj_db_version', YNJ_DB::SCHEMA_VERSION);
        if (isset($_GET['ynj_force_db'])) {
            wp_die('DB upgrade complete. Tables created/updated. <a href="' . admin_url() . '">Back to admin</a>');
        }
    }
    // Admin user already created — code removed to avoid slow init
    // Create test user for patron testing — only in WP_DEBUG mode
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset($_GET['ynj_create_test_user']) ) {
        $email = 'test@yourjannah.com';
        $pass = 'test1234';
        if (!email_exists($email)) {
            $uid = wp_create_user($email, $pass, $email);
            wp_update_user(['ID' => $uid, 'display_name' => 'Test User', 'first_name' => 'Test']);
            $u = new \WP_User($uid);
            $u->add_role('ynj_congregation');
            // Create ynj_users record
            global $wpdb;
            $wpdb->insert(YNJ_DB::table('users'), [
                'name' => 'Test User', 'email' => $email, 'phone' => '',
                'password_hash' => wp_hash_password($pass), 'status' => 'active',
            ]);
            $ynj_id = $wpdb->insert_id;
            update_user_meta($uid, 'ynj_user_id', $ynj_id);
            wp_die("Test user created: $email / $pass (WP ID: $uid, YNJ ID: $ynj_id). <a href='" . admin_url() . "'>Back</a>");
        } else {
            wp_die("User $email already exists. Password: $pass. <a href='" . admin_url() . "'>Back</a>");
        }
    }
    // Fix ynj_users: deduplicate and link WP users to correct records
    if (isset($_GET['ynj_fix_user_links'])) {
        global $wpdb;
        $ut = $wpdb->prefix . 'ynj_users';
        $report = [];
        $fixed = 0;

        // 1. Find all emails with duplicate ynj_users records
        $dupes = $wpdb->get_results("SELECT email, COUNT(*) as cnt, MAX(total_points) as max_pts, GROUP_CONCAT(id ORDER BY total_points DESC) as ids FROM $ut WHERE status = 'active' AND email != '' GROUP BY email HAVING cnt > 1");
        foreach ($dupes as $d) {
            $id_list = explode(',', $d->ids);
            $keep_id = (int) $id_list[0]; // highest points
            $report[] = "DUPE: {$d->email} — {$d->cnt} records (IDs: {$d->ids}), keeping #{$keep_id} ({$d->max_pts} pts)";
            // Merge: sum points from all records into the keeper
            $total_pts = (int) $wpdb->get_var("SELECT SUM(total_points) FROM $ut WHERE email = '{$d->email}' AND status = 'active'");
            $wpdb->update($ut, ['total_points' => $total_pts], ['id' => $keep_id]);
            // Deactivate duplicates
            foreach (array_slice($id_list, 1) as $dup_id) {
                $wpdb->update($ut, ['status' => 'merged_into_' . $keep_id], ['id' => (int) $dup_id]);
                $fixed++;
            }
        }

        // 2. Link ALL WP users to their correct ynj_users record
        $wp_users = get_users(['fields' => ['ID', 'user_email']]);
        foreach ($wp_users as $wu) {
            $ynj_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $ut WHERE email = %s AND status = 'active' ORDER BY total_points DESC LIMIT 1", $wu->user_email));
            if ($ynj_id) {
                $old_link = (int) get_user_meta($wu->ID, 'ynj_user_id', true);
                if ($old_link !== $ynj_id) {
                    update_user_meta($wu->ID, 'ynj_user_id', $ynj_id);
                    $report[] = "RELINK: WP#{$wu->ID} ({$wu->user_email}) — ynj_user_id {$old_link} → {$ynj_id}";
                    $fixed++;
                }
            }
        }

        // 3. Show report
        $html = "<h2>User Account Fix Report</h2><pre>" . implode("\n", $report) . "</pre>";
        $html .= "<p><strong>Fixed {$fixed} issues.</strong></p>";
        // Show current state
        $all = $wpdb->get_results("SELECT u.id, u.email, u.name, u.total_points, u.status, m.meta_value as wp_link FROM $ut u LEFT JOIN {$wpdb->usermeta} m ON m.meta_value = u.id AND m.meta_key = 'ynj_user_id' ORDER BY u.email, u.total_points DESC");
        $html .= "<h3>All ynj_users records:</h3><table border=1 cellpadding=4><tr><th>ID</th><th>Email</th><th>Name</th><th>Points</th><th>Status</th><th>WP Linked</th></tr>";
        foreach ($all as $r) {
            $html .= "<tr><td>{$r->id}</td><td>{$r->email}</td><td>{$r->name}</td><td>{$r->total_points}</td><td>{$r->status}</td><td>" . ($r->wp_link ? 'Yes' : '-') . "</td></tr>";
        }
        $html .= "</table><p><a href='" . admin_url() . "'>Back to admin</a></p>";
        wp_die($html);
    }
    // Fix duplicate jumuah/announcements/events from double-seed
    if (isset($_GET['ynj_fix_dupes'])) {
        global $wpdb;
        $p = $wpdb->prefix . 'ynj_';
        $fixed = 0;
        foreach (['jumuah_times', 'announcements', 'events', 'businesses', 'services', 'rooms', 'subscribers', 'enquiries'] as $t) {
            // Delete duplicates keeping lowest ID
            $dupes = $wpdb->query("DELETE t1 FROM {$p}{$t} t1 INNER JOIN {$p}{$t} t2 WHERE t1.id > t2.id AND t1.mosque_id = t2.mosque_id AND t1." . ($t === 'jumuah_times' ? 'slot_name' : ($t === 'businesses' ? 'business_name' : ($t === 'services' ? 'provider_name' : ($t === 'rooms' ? 'name' : ($t === 'subscribers' ? 'email' : 'title'))))) . " = t2." . ($t === 'jumuah_times' ? 'slot_name' : ($t === 'businesses' ? 'business_name' : ($t === 'services' ? 'provider_name' : ($t === 'rooms' ? 'name' : ($t === 'subscribers' ? 'email' : 'title'))))));
            if ($dupes) $fixed += $dupes;
        }
        wp_die("Removed $fixed duplicate rows. <a href='" . admin_url() . "'>Back</a>");
    }
    // Fix seeded mosques to unclaimed (one-time fix)
    if (isset($_GET['ynj_fix_unclaimed'])) {
        global $wpdb;
        $t = $wpdb->prefix . 'ynj_mosques';
        // Set all mosques without an admin to 'unclaimed' (except the demo mosque)
        $updated = $wpdb->query("UPDATE $t SET status = 'unclaimed' WHERE admin_email = '' AND admin_token_hash = '' AND setup_complete = 0 AND id > 1");
        wp_die("Fixed $updated mosques to 'unclaimed'. <a href='" . admin_url() . "'>Back</a>");
    }
    // Seed full demo data (admin only)
    if (isset($_GET['ynj_seed_full'])) {
        require_once YNJ_DIR . 'seed-full.php';
        echo '</pre><p><a href="' . admin_url() . '">Back to admin</a></p>';
        exit;
    }
    // Seed mosques trigger (admin only)
    if (isset($_GET['ynj_seed_mosques'])) {
        require_once YNJ_DIR . 'seed-import-mosques.php';
        echo '</pre><p><a href="' . admin_url() . '">Back to admin</a></p>';
        exit;
    }
});

// Register REST API routes
add_action('rest_api_init', function() {
    YNJ_API_Mosques::register();
    YNJ_API_Prayer::register();
    YNJ_API_Announcements::register();
    YNJ_API_Events::register();
    YNJ_API_Bookings::register();
    YNJ_API_Directory::register();
    YNJ_API_Subscribe::register();
    YNJ_API_Admin::register();
    YNJ_API_Stripe::register();
    YNJ_API_Search::register();
    YNJ_API_User::register();
    YNJ_API_Campaigns::register();
    YNJ_API_DFM_Webhook::register();
    YNJ_API_Classes::register();
    YNJ_API_Patrons::register();
    YNJ_API_Madrassah::register();
    YNJ_API_Subscriptions::register();
    YNJ_API_Masjid_Services::register();
    YNJ_API_Media::register();
    YNJ_API_Points::register();
    YNJ_API_Intentions::register();
    YNJ_API_Sponsor_YJ::register();
    YNJ_API_Donations::register();
});

// Admin menus
if (is_admin()) {
    add_action('admin_menu', ['YNJ_Admin', 'register_menu']);
    YNJ_Platform_Admin::register();
}

// ── XML Sitemap for all mosques ──
// Serves /sitemap-mosques.xml with all active mosque URLs for SEO indexing
add_action( 'init', function() {
    $uri = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
    if ( $uri !== '/sitemap-mosques.xml' ) return;

    header( 'Content-Type: application/xml; charset=UTF-8' );
    header( 'Cache-Control: public, max-age=3600' ); // Cache 1 hour for sitemaps

    global $wpdb;
    $mosques = $wpdb->get_results(
        "SELECT slug, city, updated_at FROM " . YNJ_DB::table( 'mosques' ) . " WHERE status IN ('active','unclaimed') ORDER BY name ASC"
    );

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // Homepage
    echo '<url><loc>' . home_url( '/' ) . '</loc><changefreq>daily</changefreq><priority>1.0</priority></url>' . "\n";

    // Each mosque page
    foreach ( $mosques as $m ) {
        $lastmod = $m->updated_at ? date( 'Y-m-d', strtotime( $m->updated_at ) ) : date( 'Y-m-d' );
        echo '<url>';
        echo '<loc>' . esc_url( home_url( '/mosque/' . $m->slug ) ) . '</loc>';
        echo '<lastmod>' . $lastmod . '</lastmod>';
        echo '<changefreq>daily</changefreq>';
        echo '<priority>0.8</priority>';
        echo '</url>' . "\n";
    }

    echo '</urlset>';
    exit;
}, 1 );

// Register sitemap in robots.txt
add_filter( 'robots_txt', function( $output ) {
    $output .= "\nSitemap: " . home_url( '/sitemap-mosques.xml' ) . "\n";
    return $output;
}, 99 );

// Frontend routing — now handled by yourjannah-starter theme templates
// Old router archived to _archive/class-ynj-router.php

// Email verification handler
add_action('init', function() {
    if ( isset( $_GET['token'] ) && isset( $_GET['email'] ) && strpos( $_SERVER['REQUEST_URI'], '/verify-email' ) !== false ) {
        global $wpdb;
        $ut    = YNJ_DB::table( 'users' );
        $token = sanitize_text_field( $_GET['token'] );
        $email = sanitize_email( $_GET['email'] );

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $ut WHERE email = %s AND email_verify_token = %s AND email_verified = 0",
            $email, $token
        ) );

        if ( $user ) {
            $wpdb->update( $ut, [ 'email_verified' => 1, 'email_verify_token' => '' ], [ 'id' => $user->id ] );
            wp_redirect( home_url( '/?email_verified=1' ) );
        } else {
            wp_redirect( home_url( '/?email_verified=invalid' ) );
        }
        exit;
    }
}, 1);

// Serve /sw.js — create the physical file if it doesn't exist
add_action('init', function() {
    // Check if sw.js needs serving via PHP (Nginx might block non-existent .js files)
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    if ($uri === '/sw.js' || $uri === '/sw.js/') {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, must-revalidate');
        readfile(YNJ_DIR . 'assets/js/sw.js');
        exit;
    }
}, 1);

// Also create physical sw.js at web root on admin load
add_action('admin_init', function() {
    $root_sw = ABSPATH . 'sw.js';
    $source_sw = YNJ_DIR . 'assets/js/sw.js';
    if (file_exists($source_sw) && (!file_exists($root_sw) || filemtime($source_sw) > filemtime($root_sw))) {
        @copy($source_sw, $root_sw);
    }
}, 5);

// Serve SW via REST API as ultimate fallback
add_action('rest_api_init', function() {
    // Interest tracking — records when users tap "Interested" on events/announcements
    register_rest_route('ynj/v1', '/interest', [
        'methods' => 'POST',
        'callback' => function( $request ) {
            $data = $request->get_json_params();
            $type = sanitize_text_field( $data['type'] ?? '' );
            $item_id = (int) ( $data['item_id'] ?? 0 );
            $title = sanitize_text_field( $data['title'] ?? '' );
            $mosque_slug = sanitize_title( $data['mosque_slug'] ?? '' );
            if ( ! $type || ! $mosque_slug ) return new WP_REST_Response( [ 'ok' => false ] );

            // Simple: increment a transient counter per item
            $key = 'ynj_interest_' . $type . '_' . $item_id;
            $count = (int) get_transient( $key );
            set_transient( $key, $count + 1, 30 * DAY_IN_SECONDS );

            // Store in per-mosque transient (bounded to last 100 entries)
            $log_key = 'ynj_interest_' . $mosque_slug;
            $log = get_transient( $log_key ) ?: [];
            $log[] = [ 'type' => $type, 'id' => $item_id, 'title' => $title, 'at' => current_time( 'mysql' ) ];
            if ( count( $log ) > 100 ) $log = array_slice( $log, -100 );
            set_transient( $log_key, $log, 30 * DAY_IN_SECONDS );

            return new WP_REST_Response( [ 'ok' => true, 'count' => $count + 1 ] );
        },
        'permission_callback' => '__return_true',
    ]);

    // --- Smart onboarding: check if email exists ---
    register_rest_route('ynj/v1', '/auth/check-email', [
        'methods' => 'POST',
        'callback' => function( $request ) {
            $data = $request->get_json_params();
            $email = sanitize_email( $data['email'] ?? '' );
            if ( ! $email || ! is_email( $email ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Valid email required.' ], 400 );
            }
            // Rate limit: 10 per IP per minute
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $rl_key = 'ynj_check_email_' . md5( $ip );
            $rl_count = (int) get_transient( $rl_key );
            if ( $rl_count >= 10 ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Too many requests.' ], 429 );
            }
            set_transient( $rl_key, $rl_count + 1, 60 );

            // Check WP users + ynj_users
            $exists = (bool) get_user_by( 'email', $email );
            if ( ! $exists ) {
                global $wpdb;
                $ut = YNJ_DB::table( 'users' );
                $exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $ut WHERE email = %s", $email ) );
            }
            return new WP_REST_Response( [ 'ok' => true, 'exists' => $exists ] );
        },
        'permission_callback' => '__return_true',
    ]);

    // --- Notification endpoints (authenticated) ---

    // GET /auth/notifications — Get user's notifications (last 50)
    register_rest_route('ynj/v1', '/auth/notifications', [
        'methods' => 'GET',
        'callback' => function( $request ) {
            global $wpdb;
            $ynj_user_id = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            if ( ! $ynj_user_id ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'No linked account.' ], 403 );

            $nt = YNJ_DB::table( 'notifications' );
            $mt = YNJ_DB::table( 'mosques' );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT n.*, m.name AS mosque_name
                 FROM $nt n
                 LEFT JOIN $mt m ON m.id = n.mosque_id
                 WHERE n.user_id = %d
                 ORDER BY n.created_at DESC
                 LIMIT 50",
                $ynj_user_id
            ) );

            $unread = 0;
            $notifications = [];
            foreach ( $rows as $r ) {
                if ( ! (int) $r->is_read ) $unread++;
                $notifications[] = [
                    'id'          => (int) $r->id,
                    'mosque_id'   => (int) $r->mosque_id,
                    'mosque_name' => $r->mosque_name ?: '',
                    'type'        => $r->type,
                    'ref_id'      => (int) $r->ref_id,
                    'title'       => $r->title,
                    'body'        => $r->body,
                    'url'         => $r->url,
                    'is_read'     => (int) $r->is_read,
                    'created_at'  => $r->created_at,
                ];
            }

            return new WP_REST_Response( [ 'ok' => true, 'notifications' => $notifications, 'unread_count' => $unread ] );
        },
        'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
    ]);

    // POST /auth/notifications/read — Mark notifications as read
    register_rest_route('ynj/v1', '/auth/notifications/read', [
        'methods' => 'POST',
        'callback' => function( $request ) {
            global $wpdb;
            $ynj_user_id = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            if ( ! $ynj_user_id ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'No linked account.' ], 403 );

            $data = $request->get_json_params();
            $nt = YNJ_DB::table( 'notifications' );

            if ( ! empty( $data['notification_id'] ) ) {
                $nid = (int) $data['notification_id'];
                $wpdb->query( $wpdb->prepare(
                    "UPDATE $nt SET is_read = 1 WHERE id = %d AND user_id = %d",
                    $nid, $ynj_user_id
                ) );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE $nt SET is_read = 1 WHERE user_id = %d AND is_read = 0",
                    $ynj_user_id
                ) );
            }

            return new WP_REST_Response( [ 'ok' => true ] );
        },
        'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
    ]);

    // GET /auth/notifications/count — Lightweight unread count (for polling)
    register_rest_route('ynj/v1', '/auth/notifications/count', [
        'methods' => 'GET',
        'callback' => function( $request ) {
            global $wpdb;
            $ynj_user_id = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
            if ( ! $ynj_user_id ) return new WP_REST_Response( [ 'ok' => true, 'count' => 0 ] );

            $nt = YNJ_DB::table( 'notifications' );
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $nt WHERE user_id = %d AND is_read = 0",
                $ynj_user_id
            ) );

            return new WP_REST_Response( [ 'ok' => true, 'count' => $count ] );
        },
        'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
    ]);

    // POST /auth/resend-verify — Resend email verification link (rate limited: 1 per 5 min)
    register_rest_route('ynj/v1', '/auth/resend-verify', [
        'methods' => 'POST',
        'callback' => function( $request ) {
            global $wpdb;
            $wp_user_id  = get_current_user_id();
            $ynj_user_id = (int) get_user_meta( $wp_user_id, 'ynj_user_id', true );
            if ( ! $ynj_user_id ) {
                return new WP_REST_Response( [ 'ok' => false, 'message' => 'No linked account.' ], 403 );
            }

            $ut   = YNJ_DB::table( 'users' );
            $user = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, email, name, email_verified FROM $ut WHERE id = %d", $ynj_user_id
            ) );

            if ( ! $user ) {
                return new WP_REST_Response( [ 'ok' => false, 'message' => 'User not found.' ], 404 );
            }

            if ( (int) $user->email_verified ) {
                return new WP_REST_Response( [ 'ok' => false, 'message' => 'Email already verified.' ] );
            }

            // Rate limit: 1 per 5 minutes
            $rate_key = 'ynj_resend_verify_' . $ynj_user_id;
            if ( get_transient( $rate_key ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'message' => 'Please wait 5 minutes before requesting another verification email.' ] );
            }
            set_transient( $rate_key, 1, 5 * MINUTE_IN_SECONDS );

            // Generate new token
            $verify_token = bin2hex( random_bytes( 32 ) );
            $wpdb->update( $ut, [ 'email_verify_token' => $verify_token ], [ 'id' => $ynj_user_id ] );

            // Send verification email
            $verify_url = home_url( '/verify-email?token=' . $verify_token . '&email=' . urlencode( $user->email ) );
            $name    = $user->name ?: 'there';
            $message = "Assalamu Alaikum " . $name . ",\n\n";
            $message .= "Please verify your email by clicking the link below:\n";
            $message .= $verify_url . "\n\n";
            $message .= "If you did not request this, you can ignore this email.\n\n";
            $message .= "JazakAllah Khayr,\nYourJannah Team";
            $headers = [ 'From: YourJannah <noreply@yourjannah.com>' ];
            wp_mail( $user->email, 'Verify Your Email — YourJannah', $message, $headers );

            return new WP_REST_Response( [ 'ok' => true, 'message' => 'Verification email sent.' ] );
        },
        'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
    ]);

    // PUT + GET /auth/interests — Save/get user's interest preferences
    register_rest_route('ynj/v1', '/auth/interests', [
        [
            'methods' => 'PUT',
            'callback' => function( $request ) {
                global $wpdb;
                $ynj_user_id = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
                if ( ! $ynj_user_id ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'No linked account.' ], 403 );

                $data = $request->get_json_params();
                $allowed = [ 'sports', 'youth', 'women', 'social', 'education', 'religious', 'community' ];
                $categories = [];
                if ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) {
                    $categories = array_values( array_intersect( $data['categories'], $allowed ) );
                }
                $radius = isset( $data['radius_miles'] ) ? max( 1, min( 100, (int) $data['radius_miles'] ) ) : 5;

                $ut = YNJ_DB::table( 'users' );
                $wpdb->update( $ut, [
                    'interest_categories'   => wp_json_encode( $categories ),
                    'interest_radius_miles' => $radius,
                ], [ 'id' => $ynj_user_id ] );

                return new WP_REST_Response( [ 'ok' => true, 'message' => 'Preferences saved.' ] );
            },
            'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
        ],
        [
            'methods' => 'GET',
            'callback' => function( $request ) {
                global $wpdb;
                $ynj_user_id = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
                if ( ! $ynj_user_id ) return new WP_REST_Response( [ 'ok' => true, 'categories' => [], 'radius_miles' => 5 ] );

                $ut = YNJ_DB::table( 'users' );
                $row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT interest_categories, interest_radius_miles FROM $ut WHERE id = %d",
                    $ynj_user_id
                ) );

                $categories = [];
                if ( $row && $row->interest_categories ) {
                    $decoded = json_decode( $row->interest_categories, true );
                    if ( is_array( $decoded ) ) $categories = $decoded;
                }
                $radius = $row ? (int) $row->interest_radius_miles : 5;

                return new WP_REST_Response( [ 'ok' => true, 'categories' => $categories, 'radius_miles' => $radius ] );
            },
            'permission_callback' => [ 'YNJ_WP_Auth', 'congregation_check' ],
        ],
    ]);

    // Platform donation — 100% to YourJannah
    register_rest_route('ynj/v1', '/platform-donate', [
        'methods' => 'POST',
        'callback' => function( $request ) {
            $data = $request->get_json_params();
            $amount = absint( $data['amount_pence'] ?? 0 );
            if ( $amount < 100 ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'Minimum donation is £1.' ], 400 );
            if ( $amount > 100000 ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'Maximum donation is £1,000.' ], 400 );

            $secret = YNJ_Stripe::secret_key();
            if ( ! $secret ) return new WP_REST_Response( [ 'ok' => false, 'error' => 'Stripe not configured.' ], 500 );

            $ch = curl_init( 'https://api.stripe.com/v1/checkout/sessions' );
            curl_setopt_array( $ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_USERPWD        => $secret . ':',
                CURLOPT_POSTFIELDS     => http_build_query( [
                    'mode'                        => 'payment',
                    'success_url'                 => home_url( '/?donation=thankyou' ),
                    'cancel_url'                  => home_url( '/' ),
                    'line_items[0][price_data][currency]'     => 'gbp',
                    'line_items[0][price_data][unit_amount]'  => $amount,
                    'line_items[0][price_data][product_data][name]' => 'Support YourJannah',
                    'line_items[0][price_data][product_data][description]' => 'Thank you for helping us keep YourJannah free for every masjid.',
                    'line_items[0][quantity]'      => 1,
                ] ),
                CURLOPT_TIMEOUT        => 15,
            ] );
            $response = curl_exec( $ch );
            curl_close( $ch );

            $session = json_decode( $response, true );
            if ( ! empty( $session['url'] ) ) {
                return new WP_REST_Response( [ 'ok' => true, 'url' => $session['url'] ] );
            }

            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Could not create checkout session.' ], 500 );
        },
        'permission_callback' => '__return_true',
    ]);

    // --- Stripe Webhook ---
    // POST /ynj/v1/stripe-webhook — handles Stripe webhook events for payment confirmation
    register_rest_route('ynj/v1', '/stripe-webhook', [
        'methods'             => 'POST',
        'callback'            => function( $request ) {
            global $wpdb;

            // 1. Read raw POST body (not JSON-decoded by WP)
            $payload = file_get_contents( 'php://input' );
            if ( empty( $payload ) ) {
                return new WP_REST_Response( [ 'error' => 'Empty payload' ], 400 );
            }

            // 2. Verify webhook signature
            $sig_header     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            $webhook_secret = YNJ_Stripe::webhook_secret();

            if ( ! $webhook_secret ) {
                error_log( '[YNJ Stripe Webhook] WARNING: No webhook secret configured — skipping signature verification.' );
            } else {
                // Parse signature header
                $elements = [];
                foreach ( explode( ',', $sig_header ) as $part ) {
                    $kv = explode( '=', trim( $part ), 2 );
                    if ( count( $kv ) === 2 ) {
                        $elements[ $kv[0] ] = $kv[1];
                    }
                }
                $timestamp = $elements['t']  ?? '';
                $signature = $elements['v1'] ?? '';

                if ( ! $timestamp || ! $signature ) {
                    return new WP_REST_Response( [ 'error' => 'Missing signature components' ], 400 );
                }

                // Verify HMAC
                $signed_payload = $timestamp . '.' . $payload;
                $expected       = hash_hmac( 'sha256', $signed_payload, $webhook_secret );
                if ( ! hash_equals( $expected, $signature ) ) {
                    error_log( '[YNJ Stripe Webhook] Invalid signature.' );
                    return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 400 );
                }

                // Reject if timestamp older than 5 minutes
                if ( abs( time() - (int) $timestamp ) > 300 ) {
                    error_log( '[YNJ Stripe Webhook] Timestamp too old.' );
                    return new WP_REST_Response( [ 'error' => 'Timestamp too old' ], 400 );
                }
            }

            // 3. Parse the event
            $event = json_decode( $payload, true );
            if ( empty( $event['type'] ) ) {
                return new WP_REST_Response( [ 'error' => 'Invalid event' ], 400 );
            }

            $event_type = $event['type'];
            error_log( '[YNJ Stripe Webhook] Received event: ' . $event_type );

            // 4. Handle event types
            switch ( $event_type ) {

                // --- checkout.session.completed: patron subscriptions + appeal payments ---
                case 'checkout.session.completed':
                    $session  = $event['data']['object'] ?? [];
                    $metadata = $session['metadata'] ?? [];

                    // Appeal payment
                    if ( ! empty( $metadata['appeal_id'] ) ) {
                        $wpdb->update(
                            YNJ_DB::table( 'appeal_requests' ),
                            [
                                'status'            => 'active',
                                'stripe_payment_id' => $session['id'] ?? '',
                            ],
                            [ 'id' => (int) $metadata['appeal_id'] ]
                        );
                        error_log( '[YNJ Stripe Webhook] Appeal #' . $metadata['appeal_id'] . ' activated.' );
                    }

                    // Patron subscription (check both type strings for backward compat)
                    if ( ! empty( $metadata['type'] ) && in_array( $metadata['type'], [ 'patron', 'patron_membership' ], true ) ) {
                        $pid = (int) ( $metadata['patron_id'] ?? $metadata['item_id'] ?? 0 );
                        if ( $pid ) {
                            $wpdb->update(
                                YNJ_DB::table( 'patrons' ),
                                [
                                    'status'                 => 'active',
                                    'stripe_subscription_id' => $session['subscription'] ?? '',
                                    'started_at'             => current_time( 'mysql' ),
                                ],
                                [ 'id' => $pid ]
                            );
                            error_log( '[YNJ Stripe Webhook] Patron #' . $pid . ' activated.' );
                        }
                    }
                    break;

                // --- payment_intent.succeeded: one-off donations ---
                case 'payment_intent.succeeded':
                    $pi    = $event['data']['object'] ?? [];
                    $pi_id = $pi['id'] ?? '';
                    if ( $pi_id ) {
                        $dt       = YNJ_DB::table( 'donations' );
                        $donation = $wpdb->get_row( $wpdb->prepare(
                            "SELECT * FROM $dt WHERE stripe_payment_intent = %s", $pi_id
                        ) );

                        if ( $donation && $donation->status !== 'succeeded' ) {
                            $wpdb->update( $dt, [ 'status' => 'succeeded' ], [ 'id' => $donation->id ] );

                            // Record in pool ledger
                            if ( class_exists( 'YNJ_Pool_Ledger' ) ) {
                                $fund_label = YNJ_API_Donations::FUND_TYPES[ $donation->fund_type ] ?? ucfirst( $donation->fund_type );
                                YNJ_Pool_Ledger::record( [
                                    'mosque_id'              => (int) $donation->mosque_id,
                                    'entry_type'             => $donation->is_recurring ? 'recurring' : 'payment',
                                    'payment_type'           => 'donation',
                                    'item_id'                => (int) $donation->id,
                                    'gross_pence'            => (int) $donation->amount_pence,
                                    'currency'               => $donation->currency ?? 'gbp',
                                    'stripe_payment_id'      => $pi_id,
                                    'stripe_subscription_id' => $donation->stripe_subscription_id ?? '',
                                    'payer_name'             => $donation->donor_name ?? '',
                                    'payer_email'            => $donation->donor_email ?? '',
                                    'description'            => 'Donation: ' . $fund_label,
                                ] );
                            }

                            error_log( '[YNJ Stripe Webhook] Donation #' . $donation->id . ' marked succeeded.' );
                        }
                    }
                    break;

                // --- customer.subscription.deleted: patron cancellation ---
                case 'customer.subscription.deleted':
                    $sub    = $event['data']['object'] ?? [];
                    $sub_id = $sub['id'] ?? '';
                    if ( $sub_id ) {
                        $pt = YNJ_DB::table( 'patrons' );
                        $wpdb->update(
                            $pt,
                            [
                                'status'       => 'cancelled',
                                'cancelled_at' => current_time( 'mysql' ),
                            ],
                            [ 'stripe_subscription_id' => $sub_id ]
                        );
                        error_log( '[YNJ Stripe Webhook] Subscription ' . $sub_id . ' cancelled.' );
                    }
                    break;

                default:
                    error_log( '[YNJ Stripe Webhook] Unhandled event type: ' . $event_type );
                    break;
            }

            // 5. Always return 200 to Stripe
            return new WP_REST_Response( [ 'received' => true ], 200 );
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('ynj/v1', '/sw', [
        'methods' => 'GET',
        'callback' => function() {
            header('Content-Type: application/javascript');
            header('Service-Worker-Allowed: /');
            header('Cache-Control: no-cache, must-revalidate');
            readfile(YNJ_DIR . 'assets/js/sw.js');
            exit;
        },
        'permission_callback' => '__return_true',
    ]);
});

// Serve /.well-known/assetlinks.json for Android TWA verification
add_action('init', function() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/.well-known/assetlinks.json') !== false) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo '[{"relation":["delegate_permission/common.handle_all_urls"],"target":{"namespace":"android_app","package_name":"com.yourjannah.app","sha256_cert_fingerprints":["5E:26:90:48:E0:77:EB:8F:92:66:6B:98:E6:CD:7A:91:2C:D3:27:95:DA:3E:95:30:35:A4:5A:2D:03:F6:C3:C9"]}}]';
        exit;
    }
}, 1);

// PWA headers
add_action('wp_head', function() {
    echo '<link rel="manifest" href="' . YNJ_URL . 'manifest.json">' . "\n";
    echo '<meta name="theme-color" content="#287e61">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
});

// Register VAPID keys on first run
add_action('init', function() {
    if (!get_option('ynj_vapid_public')) {
        YNJ_Push::generate_vapid_keys();
    }
});

// Prayer reminder cron — auto-schedule if not already running
add_action('ynj_prayer_reminder_cron', ['YNJ_Cron', 'check_prayers']);
register_activation_hook(__FILE__, ['YNJ_Cron', 'schedule']);
register_deactivation_hook(__FILE__, ['YNJ_Cron', 'unschedule']);
add_action('init', function() {
    if ( ! wp_next_scheduled( 'ynj_prayer_reminder_cron' ) ) {
        YNJ_Cron::schedule();
    }
}, 20);

// Admin email notifications
add_action('ynj_new_enquiry', ['YNJ_Notify', 'on_enquiry'], 10, 2);
add_action('ynj_new_booking', ['YNJ_Notify', 'on_booking'], 10, 2);
add_action('ynj_new_sponsor', ['YNJ_Notify', 'on_sponsor'], 10, 2);
add_action('ynj_new_service_listing', ['YNJ_Notify', 'on_service_listing'], 10, 2);
add_action('ynj_payment_received', ['YNJ_Notify', 'on_payment'], 10, 3);
add_action('ynj_new_patron', ['YNJ_Notify', 'on_patron'], 10, 2);
add_action('ynj_booking_status_changed', ['YNJ_Notify', 'on_booking_status_changed'], 10, 2);

// Push notifications to subscribed users on new content
add_action('ynj_new_announcement', function($mosque_id, $data) {
    $subs = YNJ_API_Subscriptions::get_subscribers_for($mosque_id, 'notify_announcements');
    if (empty($subs)) return;
    $payload = wp_json_encode([
        'title' => $data['title'] ?? 'New Announcement',
        'body'  => $data['body'] ?? '',
        'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        'url'   => '/',
    ]);
    foreach ($subs as $u) {
        YNJ_Push::send_push($u->push_endpoint, $u->push_p256dh, $u->push_auth, $payload);
    }
}, 10, 2);

// Cross-mosque interest notifications for announcements
add_action('ynj_new_announcement', function($mosque_id, $data) {
    global $wpdb;
    $slug = $wpdb->get_var($wpdb->prepare(
        "SELECT slug FROM " . YNJ_DB::table('mosques') . " WHERE id = %d", $mosque_id
    ));
    if (!$slug) return;
    $ann_type = $data['type'] ?? 'general';
    $category = YNJ_Interest_Notify::map_announcement_category($ann_type);
    $ann_id   = $data['ann_id'] ?? 0;
    YNJ_Interest_Notify::dispatch(
        $mosque_id,
        'announcement',
        $ann_id,
        $data['title'] ?? '',
        $data['body'] ?? '',
        $category,
        '/mosque/' . $slug
    );
}, 20, 2);

add_action('ynj_new_event', function($mosque_id, $data) {
    $subs = YNJ_API_Subscriptions::get_subscribers_for($mosque_id, 'notify_events');
    if (empty($subs)) return;
    global $wpdb;
    $mosque_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . YNJ_DB::table('mosques') . " WHERE id = %d", $mosque_id)) ?: '';
    $payload = wp_json_encode([
        'title' => 'New Event: ' . ($data['title'] ?? ''),
        'body'  => ($data['event_date'] ?? '') . ' at ' . $mosque_name,
        'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        'url'   => '/',
    ]);
    foreach ($subs as $u) {
        YNJ_Push::send_push($u->push_endpoint, $u->push_p256dh, $u->push_auth, $payload);
    }
}, 10, 2);

// Cross-mosque interest notifications for events
add_action('ynj_new_event', function($mosque_id, $data) {
    global $wpdb;
    $slug = $wpdb->get_var($wpdb->prepare(
        "SELECT slug FROM " . YNJ_DB::table('mosques') . " WHERE id = %d", $mosque_id
    ));
    if (!$slug) return;
    $event_type = $data['event_type'] ?? '';
    $category   = YNJ_Interest_Notify::map_event_category($event_type);
    $event_id   = $data['event_id'] ?? 0;
    YNJ_Interest_Notify::dispatch(
        $mosque_id,
        'event',
        $event_id,
        $data['title'] ?? '',
        ($data['event_date'] ?? '') . ' — ' . ($data['title'] ?? ''),
        $category,
        '/mosque/' . $slug . '/events/' . $event_id
    );
}, 20, 2);

add_action('ynj_new_class', function($mosque_id, $data) {
    $subs = YNJ_API_Subscriptions::get_subscribers_for($mosque_id, 'notify_classes');
    if (empty($subs)) return;
    global $wpdb;
    $mosque_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . YNJ_DB::table('mosques') . " WHERE id = %d", $mosque_id)) ?: '';
    $payload = wp_json_encode([
        'title' => 'New Class: ' . ($data['title'] ?? ''),
        'body'  => 'at ' . $mosque_name,
        'icon'  => '/wp-content/plugins/yn-jannah/assets/icons/icon-192.png',
        'url'   => '/',
    ]);
    foreach ($subs as $u) {
        YNJ_Push::send_push($u->push_endpoint, $u->push_p256dh, $u->push_auth, $payload);
    }
}, 10, 2);

// Points: auto-award on booking/donation
add_action('ynj_new_booking', function($mosque_id, $data) {
    if (!empty($data['user_email'])) {
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . YNJ_DB::table('users') . " WHERE email = %s", $data['user_email']));
        if ($user) YNJ_API_Points::award($user->id, $mosque_id, 'event_rsvp', null, $data['notes'] ?? 'Event booking');
    }
}, 10, 2);

add_action('ynj_payment_received', function($mosque_id, $data, $type) {
    if (!empty($data['user_email']) && in_array($type, ['event_donation', 'patron_membership'])) {
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . YNJ_DB::table('users') . " WHERE email = %s", $data['user_email']));
        if ($user) YNJ_API_Points::award($user->id, $mosque_id, 'donation', null, 'Donation/patron payment');
    }
}, 10, 3);

// WP Site Health checks
add_filter('site_status_tests', function($tests) {
    $tests['direct']['ynj_db_version'] = [
        'label' => 'YourJannah Database',
        'test'  => function() {
            $installed = get_option('ynj_db_version', '');
            $expected = YNJ_DB::SCHEMA_VERSION;
            $pass = ($installed === $expected);
            return [
                'label'       => $pass ? 'YourJannah database is up to date' : 'YourJannah database needs updating',
                'status'      => $pass ? 'good' : 'critical',
                'badge'       => ['label' => 'YourJannah', 'color' => $pass ? 'blue' : 'red'],
                'description' => '<p>Database version: ' . esc_html($installed) . ' (expected: ' . esc_html($expected) . ')</p>',
                'test'        => 'ynj_db_version',
            ];
        },
    ];
    $tests['direct']['ynj_stripe'] = [
        'label' => 'YourJannah Stripe',
        'test'  => function() {
            $configured = YNJ_Stripe::is_configured();
            return [
                'label'       => $configured ? 'Stripe is configured' : 'Stripe is not configured',
                'status'      => $configured ? 'good' : 'recommended',
                'badge'       => ['label' => 'YourJannah', 'color' => 'blue'],
                'description' => '<p>Stripe payment processing ' . ($configured ? 'is active.' : 'is not set up. Paid bookings and subscriptions will not work.') . '</p>',
                'test'        => 'ynj_stripe',
            ];
        },
    ];
    $tests['direct']['ynj_vapid'] = [
        'label' => 'YourJannah Push Notifications',
        'test'  => function() {
            $key = get_option('ynj_vapid_public', '');
            return [
                'label'       => $key ? 'VAPID keys configured' : 'VAPID keys missing',
                'status'      => $key ? 'good' : 'recommended',
                'badge'       => ['label' => 'YourJannah', 'color' => 'blue'],
                'description' => '<p>Web Push notification ' . ($key ? 'keys are set up.' : 'keys are missing. Push notifications will not work.') . '</p>',
                'test'        => 'ynj_vapid',
            ];
        },
    ];
    return $tests;
});
// deploy trigger
