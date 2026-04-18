<?php
/**
 * Template: Mosque Admin Onboarding Wizard
 *
 * Standalone mobile-first wizard that guides new mosque admins through setup.
 * Step 1: Confirm mosque details
 * Step 2: Prayer times + Jumu'ah slots
 * Step 3: Post first announcement
 * Step 4: Share & grow
 *
 * @package YourJannah
 * @since   3.9.8
 */

// ── Auth Gate ──
if ( ! is_user_logged_in() ) {
    wp_redirect( home_url( '/login?redirect=' . urlencode( '/mosque-setup' ) ) );
    exit;
}

$wp_uid   = get_current_user_id();
$mosque_id = (int) get_user_meta( $wp_uid, 'ynj_mosque_id', true );
$is_admin  = current_user_can( 'manage_options' ) || in_array( 'ynj_mosque_admin', (array) wp_get_current_user()->roles );

if ( ! $is_admin || ! $mosque_id ) {
    wp_redirect( home_url( '/dashboard' ) );
    exit;
}

global $wpdb;
$mt = YNJ_DB::table( 'mosques' );
$mosque = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $mt WHERE id = %d", $mosque_id ) );
if ( ! $mosque ) {
    wp_redirect( home_url( '/dashboard' ) );
    exit;
}

$mosque_name = $mosque->name;
$mosque_slug = $mosque->slug;
$step = max( 1, min( 4, (int) ( $_GET['step'] ?? 1 ) ) );

// ── POST handler (PRG pattern) ──
$success = '';
$error   = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_onboard' ) ) {
    $post_step = (int) ( $_POST['step'] ?? 1 );

    // Step 1: Update mosque profile
    if ( $post_step === 1 ) {
        $update = [
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'address'     => sanitize_text_field( $_POST['address'] ?? '' ),
            'city'        => sanitize_text_field( $_POST['city'] ?? '' ),
            'postcode'    => sanitize_text_field( $_POST['postcode'] ?? '' ),
            'phone'       => sanitize_text_field( $_POST['phone'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
        ];
        if ( $update['name'] ) {
            $wpdb->update( $mt, $update, [ 'id' => $mosque_id ] );
            update_user_meta( $wp_uid, 'ynj_onboard_step', 2 );
            wp_redirect( home_url( '/mosque-setup?step=2' ) );
            exit;
        }
        $error = __( 'Mosque name is required.', 'yourjannah' );
    }

    // Step 2a: Import Aladhan prayer times
    if ( $post_step === 2 && ( $_POST['action'] ?? '' ) === 'import_aladhan' ) {
        $pt_table = YNJ_DB::table( 'prayer_times' );
        $json_data = $_POST['aladhan_data'] ?? '';
        if ( $json_data ) {
            $data = json_decode( stripslashes( $json_data ), true ) ?: [];
            $clean = function( $t ) { return substr( preg_replace( '/\s*\(.*\)/', '', $t ), 0, 5 ); };
            $imported = 0;
            foreach ( $data as $day ) {
                $timings  = $day['timings'] ?? [];
                $date_str = $day['date']['gregorian']['date'] ?? '';
                if ( ! $date_str ) continue;
                $parts = explode( '-', $date_str );
                if ( count( $parts ) !== 3 ) continue;
                $sql_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];

                $row = [
                    'mosque_id' => $mosque_id,
                    'date'      => $sql_date,
                    'fajr'      => $clean( $timings['Fajr'] ?? '' ),
                    'sunrise'   => $clean( $timings['Sunrise'] ?? '' ),
                    'dhuhr'     => $clean( $timings['Dhuhr'] ?? '' ),
                    'asr'       => $clean( $timings['Asr'] ?? '' ),
                    'maghrib'   => $clean( $timings['Maghrib'] ?? '' ),
                    'isha'      => $clean( $timings['Isha'] ?? '' ),
                ];
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $pt_table WHERE mosque_id=%d AND date=%s", $mosque_id, $sql_date ) );
                if ( $exists ) { $wpdb->update( $pt_table, $row, [ 'id' => $exists ] ); }
                else { $wpdb->insert( $pt_table, $row ); }
                $imported++;
            }
            if ( $imported > 0 ) {
                set_transient( 'ynj_onboard_flash_' . $wp_uid, [ 'success' => sprintf( __( 'Imported %d days of prayer times!', 'yourjannah' ), $imported ) ], 30 );
            }
        }
        wp_redirect( home_url( '/mosque-setup?step=2&imported=1' ) );
        exit;
    }

    // Step 2b: Add Jumu'ah slot
    if ( $post_step === 2 && ( $_POST['action'] ?? '' ) === 'add_jumuah' ) {
        $jt = YNJ_DB::table( 'jumuah_times' );
        $wpdb->insert( $jt, [
            'mosque_id'    => $mosque_id,
            'slot_name'    => sanitize_text_field( $_POST['slot_name'] ?? "Jumu'ah" ),
            'khutbah_time' => sanitize_text_field( $_POST['khutbah_time'] ?? '' ),
            'salah_time'   => sanitize_text_field( $_POST['salah_time'] ?? '' ),
            'language'     => sanitize_text_field( $_POST['language'] ?? '' ),
            'enabled'      => 1,
        ] );
        set_transient( 'ynj_onboard_flash_' . $wp_uid, [ 'success' => __( 'Jumu\'ah slot added!', 'yourjannah' ) ], 30 );
        wp_redirect( home_url( '/mosque-setup?step=2' ) );
        exit;
    }

    // Step 2: Move to next step
    if ( $post_step === 2 && ( $_POST['action'] ?? '' ) === 'next_step' ) {
        update_user_meta( $wp_uid, 'ynj_onboard_step', 3 );
        wp_redirect( home_url( '/mosque-setup?step=3' ) );
        exit;
    }

    // Step 3: Create announcement
    if ( $post_step === 3 ) {
        $at = YNJ_DB::table( 'announcements' );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        if ( $title ) {
            $ann_data = [
                'mosque_id'       => $mosque_id,
                'title'           => $title,
                'body'            => sanitize_textarea_field( $_POST['body'] ?? '' ),
                'type'            => 'general',
                'status'          => 'published',
                'author_user_id'  => $wp_uid,
                'author_role'     => 'admin',
                'approval_status' => 'approved',
                'published_at'    => current_time( 'mysql' ),
            ];
            // Handle image upload
            if ( ! empty( $_FILES['image']['name'] ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                $upload = wp_handle_upload( $_FILES['image'], [ 'test_form' => false ] );
                if ( ! empty( $upload['url'] ) ) {
                    $ann_data['image_url'] = esc_url_raw( $upload['url'] );
                }
            }
            $wpdb->insert( $at, $ann_data );
            update_user_meta( $wp_uid, 'ynj_onboard_step', 4 );
            wp_redirect( home_url( '/mosque-setup?step=4' ) );
            exit;
        }
        $error = __( 'Title is required.', 'yourjannah' );
    }

    // Step 4: Mark complete
    if ( $post_step === 4 ) {
        update_user_meta( $wp_uid, 'ynj_onboard_complete', 1 );
        update_user_meta( $wp_uid, 'ynj_onboard_step', 4 );
        wp_redirect( home_url( '/dashboard?setup_complete=1' ) );
        exit;
    }
}

// Read flash message for step 2
$flash = get_transient( 'ynj_onboard_flash_' . $wp_uid );
if ( $flash ) {
    delete_transient( 'ynj_onboard_flash_' . $wp_uid );
    if ( ! empty( $flash['success'] ) ) $success = $flash['success'];
    if ( ! empty( $flash['error'] ) )   $error   = $flash['error'];
}

// Load data needed for steps
$lat = $mosque->latitude ? (float) $mosque->latitude : null;
$lng = $mosque->longitude ? (float) $mosque->longitude : null;

// Jumu'ah slots for step 2
$jt = YNJ_DB::table( 'jumuah_times' );
$jumuah_slots = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $jt WHERE mosque_id=%d AND enabled=1 ORDER BY salah_time ASC", $mosque_id
) ) ?: [];

// Prayer times imported?
$pt_table = YNJ_DB::table( 'prayer_times' );
$has_prayer_times = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM $pt_table WHERE mosque_id=%d", $mosque_id
) );

// Step labels
$step_labels = [
    1 => __( 'Mosque Details', 'yourjannah' ),
    2 => __( 'Prayer Times', 'yourjannah' ),
    3 => __( 'First Announcement', 'yourjannah' ),
    4 => __( 'Share & Grow', 'yourjannah' ),
];
$step_icons = [ 1 => '🕌', 2 => '🕐', 3 => '📢', 4 => '🚀' ];

// Calculation methods for Aladhan
$methods = [
    15 => 'Moonsighting Committee',
    2  => 'ISNA (North America)',
    3  => 'Muslim World League',
    4  => 'Umm Al-Qura, Makkah',
    1  => 'Karachi University',
    5  => 'Egyptian Survey',
];
// Default to Moonsighting for UK mosques
$default_method = 15;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $step_labels[ $step ] ?? '' ); ?> — <?php esc_html_e( 'Mosque Setup', 'yourjannah' ); ?></title>
<style>
:root{--primary:#287e61;--primary-light:#e6f2ed;--primary-dark:#1c4644;--bg:#FAFAF8;--card:#fff;--border:#e5e7eb;--text:#1a1a1a;--text-dim:#6b7280;--radius:16px;--accent:#00ADEF;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased;}
a{color:var(--primary);text-decoration:none;}

/* Wizard layout */
.ob-wrap{max-width:520px;margin:0 auto;padding:16px;padding-bottom:40px;}
.ob-logo{text-align:center;padding:16px 0 8px;font-size:15px;font-weight:800;color:var(--primary-dark);}

/* Progress bar */
.ob-progress{display:flex;align-items:center;gap:6px;margin-bottom:24px;padding:0 4px;}
.ob-progress__step{flex:1;height:6px;border-radius:3px;background:#e5e7eb;transition:background .3s;}
.ob-progress__step--done{background:var(--primary);}
.ob-progress__step--current{background:var(--primary);opacity:.5;}

/* Step header */
.ob-step-header{text-align:center;margin-bottom:20px;}
.ob-step-header__icon{font-size:36px;margin-bottom:8px;}
.ob-step-header h1{font-size:20px;font-weight:800;margin-bottom:4px;}
.ob-step-header p{font-size:14px;color:var(--text-dim);max-width:360px;margin:0 auto;}
.ob-step-label{display:inline-block;font-size:11px;font-weight:700;color:var(--primary);background:var(--primary-light);padding:3px 10px;border-radius:20px;margin-bottom:12px;}

/* Cards */
.ob-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:24px 20px;margin-bottom:16px;}
.ob-card h3{font-size:16px;font-weight:700;margin-bottom:12px;}
.ob-card--success{background:#f0fdf4;border-color:#86efac;}

/* Forms */
.ob-field{margin-bottom:16px;}
.ob-field label{display:block;font-size:13px;font-weight:600;color:var(--text-dim);margin-bottom:6px;}
.ob-field input,.ob-field textarea,.ob-field select{width:100%;padding:14px 16px;border:1px solid var(--border);border-radius:12px;font-size:16px;font-family:inherit;min-height:48px;background:#fff;}
.ob-field input:focus,.ob-field textarea:focus,.ob-field select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(40,126,97,.1);}
.ob-field textarea{resize:vertical;min-height:100px;}
.ob-field input[type="file"]{padding:12px;border-style:dashed;background:#fafafa;cursor:pointer;}

/* Buttons */
.ob-btn{display:block;width:100%;padding:16px;border-radius:12px;font-size:16px;font-weight:700;border:none;cursor:pointer;font-family:inherit;min-height:52px;text-align:center;transition:opacity .15s;}
.ob-btn--primary{background:var(--primary);color:#fff;}
.ob-btn--primary:hover{opacity:.9;}
.ob-btn--accent{background:var(--accent);color:#fff;}
.ob-btn--accent:hover{opacity:.9;}
.ob-btn--outline{background:transparent;border:2px solid var(--border);color:var(--text);}
.ob-btn--outline:hover{background:#f9fafb;}
.ob-btn--sm{display:inline-flex;align-items:center;justify-content:center;width:auto;padding:12px 20px;font-size:14px;min-height:44px;gap:6px;}
.ob-skip{display:block;text-align:center;padding:14px;color:var(--text-dim);font-size:14px;font-weight:500;min-height:44px;}
.ob-skip:hover{color:var(--text);}
.ob-btn-row{display:flex;gap:8px;margin-top:16px;}
.ob-btn-row .ob-btn{flex:1;}

/* Alerts */
.ob-alert{padding:14px 16px;border-radius:12px;font-size:14px;margin-bottom:16px;font-weight:500;}
.ob-alert--success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.ob-alert--error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}

/* Jumu'ah slot cards */
.ob-slot{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#f9fafb;border:1px solid var(--border);border-radius:12px;margin-bottom:8px;}
.ob-slot__info{font-size:14px;}
.ob-slot__info strong{display:block;font-size:15px;margin-bottom:2px;}
.ob-slot__info span{color:var(--text-dim);font-size:13px;}

/* Share URL box */
.ob-url-box{position:relative;padding:16px;background:#f0fdf4;border:2px solid var(--primary);border-radius:12px;margin-bottom:16px;}
.ob-url-box code{display:block;font-size:14px;word-break:break-all;color:var(--primary);font-weight:600;margin-bottom:8px;}
.ob-url-box button{background:var(--primary);color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;min-height:44px;width:100%;}

/* Encouragement */
.ob-encourage{text-align:center;padding:12px;font-size:13px;color:var(--primary);font-weight:600;}

@media(max-width:768px){
    .ob-wrap{padding:12px;padding-bottom:32px;}
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="ob-wrap">
    <div class="ob-logo">🕌 YourJannah</div>

    <!-- Progress Bar -->
    <div class="ob-progress">
        <?php for ( $i = 1; $i <= 4; $i++ ) :
            $cls = '';
            if ( $i < $step ) $cls = 'ob-progress__step--done';
            elseif ( $i === $step ) $cls = 'ob-progress__step--current';
        ?>
        <div class="ob-progress__step <?php echo $cls; ?>"></div>
        <?php endfor; ?>
    </div>

    <?php if ( $success ) : ?><div class="ob-alert ob-alert--success"><?php echo esc_html( $success ); ?></div><?php endif; ?>
    <?php if ( $error ) : ?><div class="ob-alert ob-alert--error"><?php echo esc_html( $error ); ?></div><?php endif; ?>

    <!-- ================================ -->
    <!-- STEP 1: Confirm Mosque Details   -->
    <!-- ================================ -->
    <?php if ( $step === 1 ) : ?>
    <div class="ob-step-header">
        <div class="ob-step-header__icon"><?php echo $step_icons[1]; ?></div>
        <span class="ob-step-label"><?php esc_html_e( 'Step 1 of 4', 'yourjannah' ); ?></span>
        <h1><?php esc_html_e( 'Confirm Your Mosque Details', 'yourjannah' ); ?></h1>
        <p><?php esc_html_e( "Let's make sure your mosque information is correct. This is what your congregation will see.", 'yourjannah' ); ?></p>
    </div>

    <form method="post" class="ob-card">
        <?php wp_nonce_field( 'ynj_onboard', '_ynj_nonce' ); ?>
        <input type="hidden" name="step" value="1">

        <div class="ob-field">
            <label><?php esc_html_e( 'Mosque Name', 'yourjannah' ); ?></label>
            <input type="text" name="name" value="<?php echo esc_attr( $mosque->name ); ?>" required>
        </div>
        <div class="ob-field">
            <label><?php esc_html_e( 'Address', 'yourjannah' ); ?></label>
            <input type="text" name="address" value="<?php echo esc_attr( $mosque->address ?? '' ); ?>" placeholder="123 High Street">
        </div>
        <div class="ob-field">
            <label><?php esc_html_e( 'City', 'yourjannah' ); ?></label>
            <input type="text" name="city" value="<?php echo esc_attr( $mosque->city ?? '' ); ?>">
        </div>
        <div class="ob-field">
            <label><?php esc_html_e( 'Postcode', 'yourjannah' ); ?></label>
            <input type="text" name="postcode" value="<?php echo esc_attr( $mosque->postcode ?? '' ); ?>">
        </div>
        <div class="ob-field">
            <label><?php esc_html_e( 'Phone Number', 'yourjannah' ); ?></label>
            <input type="tel" name="phone" value="<?php echo esc_attr( $mosque->phone ?? '' ); ?>" placeholder="020 1234 5678">
        </div>
        <div class="ob-field">
            <label><?php esc_html_e( 'Description (optional)', 'yourjannah' ); ?></label>
            <textarea name="description" placeholder="<?php esc_attr_e( 'Tell your community what makes your mosque special...', 'yourjannah' ); ?>"><?php echo esc_textarea( $mosque->description ?? '' ); ?></textarea>
        </div>

        <button type="submit" class="ob-btn ob-btn--primary"><?php esc_html_e( 'Save & Continue', 'yourjannah' ); ?> →</button>
    </form>
    <a href="<?php echo esc_url( home_url( '/mosque-setup?step=2' ) ); ?>" class="ob-skip"><?php esc_html_e( 'Skip for now →', 'yourjannah' ); ?></a>

    <?php endif; ?>

    <!-- ================================ -->
    <!-- STEP 2: Prayer Times + Jumu'ah   -->
    <!-- ================================ -->
    <?php if ( $step === 2 ) : ?>
    <div class="ob-step-header">
        <div class="ob-step-header__icon"><?php echo $step_icons[2]; ?></div>
        <span class="ob-step-label"><?php esc_html_e( 'Step 2 of 4', 'yourjannah' ); ?></span>
        <h1><?php esc_html_e( 'Set Up Prayer Times', 'yourjannah' ); ?></h1>
        <p><?php esc_html_e( 'This is the most important step. Your congregation needs accurate prayer and Jumu\'ah times.', 'yourjannah' ); ?></p>
    </div>

    <!-- Import Aladhan -->
    <div class="ob-card">
        <h3>📡 <?php esc_html_e( 'Import Adhan Times', 'yourjannah' ); ?></h3>
        <?php if ( ! $lat || ! $lng ) : ?>
        <div class="ob-alert ob-alert--error"><?php esc_html_e( 'Your mosque has no GPS coordinates yet. Go back to Step 1 and enter your address, or contact support.', 'yourjannah' ); ?></div>
        <?php elseif ( $has_prayer_times > 0 ) : ?>
        <div class="ob-alert ob-alert--success"><?php printf( esc_html__( 'Prayer times imported (%d days). You can re-import or continue below.', 'yourjannah' ), $has_prayer_times ); ?></div>
        <?php endif; ?>

        <?php if ( $lat && $lng ) : ?>
        <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
            <?php esc_html_e( 'We\'ll fetch accurate adhan times based on your mosque\'s location. One tap and you\'re done.', 'yourjannah' ); ?>
        </p>
        <form method="post" id="aladhan-form">
            <?php wp_nonce_field( 'ynj_onboard', '_ynj_nonce' ); ?>
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="action" value="import_aladhan">
            <input type="hidden" name="aladhan_data" id="aladhan-data" value="">

            <div class="ob-field">
                <label><?php esc_html_e( 'Calculation Method', 'yourjannah' ); ?></label>
                <select name="method" id="import-method">
                    <?php foreach ( $methods as $mid => $mname ) : ?>
                    <option value="<?php echo $mid; ?>" <?php selected( $default_method, $mid ); ?>><?php echo esc_html( $mname ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" id="import-btn" class="ob-btn ob-btn--accent" onclick="fetchAladhan()">
                📡 <?php esc_html_e( 'Import This Month\'s Prayer Times', 'yourjannah' ); ?>
            </button>
        </form>
        <div id="import-status" style="margin-top:8px;font-size:13px;text-align:center;"></div>

        <script>
        function fetchAladhan() {
            var btn = document.getElementById('import-btn');
            var status = document.getElementById('import-status');
            var method = document.getElementById('import-method').value;
            var now = new Date();
            var year = now.getFullYear(), mon = now.getMonth() + 1;
            var lat = <?php echo json_encode( $lat ); ?>;
            var lng = <?php echo json_encode( $lng ); ?>;

            btn.disabled = true;
            btn.textContent = 'Fetching prayer times...';
            status.innerHTML = '<span style="color:var(--accent);">Loading from Aladhan...</span>';

            fetch('https://api.aladhan.com/v1/calendar/' + year + '/' + mon + '?latitude=' + lat + '&longitude=' + lng + '&method=' + method + '&school=1')
                .then(function(r) { return r.json(); })
                .then(function(body) {
                    var data = body.data || [];
                    if (!data.length) {
                        status.innerHTML = '<span style="color:#dc2626;">No data returned. Try again.</span>';
                        btn.disabled = false; btn.textContent = '📡 Import This Month\'s Prayer Times';
                        return;
                    }
                    status.innerHTML = '<span style="color:var(--primary);">Got ' + data.length + ' days. Saving...</span>';
                    document.getElementById('aladhan-data').value = JSON.stringify(data);
                    document.getElementById('aladhan-form').submit();
                })
                .catch(function(e) {
                    status.innerHTML = '<span style="color:#dc2626;">Failed: ' + e.message + '</span>';
                    btn.disabled = false; btn.textContent = '📡 Import This Month\'s Prayer Times';
                });
        }
        </script>
        <?php endif; ?>
    </div>

    <!-- Jumu'ah Slots -->
    <div class="ob-card">
        <h3>🕌 <?php esc_html_e( 'Jumu\'ah Times', 'yourjannah' ); ?></h3>
        <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
            <?php esc_html_e( 'Add your Jumu\'ah khutbah and salah times. You can add multiple slots if you have more than one congregation.', 'yourjannah' ); ?>
        </p>

        <?php if ( $jumuah_slots ) : ?>
        <?php foreach ( $jumuah_slots as $js ) : ?>
        <div class="ob-slot">
            <div class="ob-slot__info">
                <strong><?php echo esc_html( $js->slot_name ); ?></strong>
                <span><?php esc_html_e( 'Khutbah', 'yourjannah' ); ?>: <?php echo esc_html( substr( $js->khutbah_time, 0, 5 ) ); ?> · <?php esc_html_e( 'Salah', 'yourjannah' ); ?>: <?php echo esc_html( substr( $js->salah_time, 0, 5 ) ); ?><?php echo $js->language ? ' · ' . esc_html( $js->language ) : ''; ?></span>
            </div>
            <span style="color:var(--primary);font-size:18px;">✓</span>
        </div>
        <?php endforeach; ?>
        <div class="ob-encourage"><?php esc_html_e( "You're doing great! Add more slots or continue.", 'yourjannah' ); ?></div>
        <?php endif; ?>

        <form method="post" style="margin-top:8px;">
            <?php wp_nonce_field( 'ynj_onboard', '_ynj_nonce' ); ?>
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="action" value="add_jumuah">
            <div class="ob-field">
                <label><?php esc_html_e( 'Slot Name', 'yourjannah' ); ?></label>
                <input type="text" name="slot_name" value="Jumu'ah" placeholder="e.g. 1st Jumu'ah">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div class="ob-field">
                    <label><?php esc_html_e( 'Khutbah Time', 'yourjannah' ); ?></label>
                    <input type="time" name="khutbah_time" required>
                </div>
                <div class="ob-field">
                    <label><?php esc_html_e( 'Salah Time', 'yourjannah' ); ?></label>
                    <input type="time" name="salah_time" required>
                </div>
            </div>
            <div class="ob-field">
                <label><?php esc_html_e( 'Language (optional)', 'yourjannah' ); ?></label>
                <input type="text" name="language" placeholder="English">
            </div>
            <button type="submit" class="ob-btn ob-btn--sm ob-btn--outline" style="width:100%;">
                + <?php esc_html_e( 'Add Jumu\'ah Slot', 'yourjannah' ); ?>
            </button>
        </form>
    </div>

    <!-- Next / Skip -->
    <form method="post">
        <?php wp_nonce_field( 'ynj_onboard', '_ynj_nonce' ); ?>
        <input type="hidden" name="step" value="2">
        <input type="hidden" name="action" value="next_step">
        <button type="submit" class="ob-btn ob-btn--primary"><?php esc_html_e( 'Continue to Step 3', 'yourjannah' ); ?> →</button>
    </form>
    <a href="<?php echo esc_url( home_url( '/mosque-setup?step=3' ) ); ?>" class="ob-skip"><?php esc_html_e( 'Skip for now →', 'yourjannah' ); ?></a>

    <?php endif; ?>

    <!-- ================================ -->
    <!-- STEP 3: First Announcement       -->
    <!-- ================================ -->
    <?php if ( $step === 3 ) : ?>
    <div class="ob-step-header">
        <div class="ob-step-header__icon"><?php echo $step_icons[3]; ?></div>
        <span class="ob-step-label"><?php esc_html_e( 'Step 3 of 4', 'yourjannah' ); ?></span>
        <h1><?php esc_html_e( 'Post Your First Announcement', 'yourjannah' ); ?></h1>
        <p><?php esc_html_e( 'Let your congregation know you\'re on YourJannah. This will appear on your mosque page.', 'yourjannah' ); ?></p>
    </div>

    <form method="post" enctype="multipart/form-data" class="ob-card">
        <?php wp_nonce_field( 'ynj_onboard', '_ynj_nonce' ); ?>
        <input type="hidden" name="step" value="3">

        <div class="ob-field">
            <label><?php esc_html_e( 'Title', 'yourjannah' ); ?></label>
            <input type="text" name="title" required
                   placeholder="<?php esc_attr_e( 'e.g. Welcome to our YourJannah page!', 'yourjannah' ); ?>">
        </div>
        <div class="ob-field">
            <label><?php esc_html_e( 'Message', 'yourjannah' ); ?></label>
            <textarea name="body" placeholder="<?php esc_attr_e( "Assalamu alaikum! We're excited to connect with you through YourJannah. Stay updated with prayer times, events, and community news.", 'yourjannah' ); ?>"></textarea>
        </div>
        <div class="ob-field">
            <label><?php esc_html_e( 'Image (optional)', 'yourjannah' ); ?></label>
            <input type="file" name="image" accept="image/*">
        </div>

        <button type="submit" class="ob-btn ob-btn--primary"><?php esc_html_e( 'Publish & Continue', 'yourjannah' ); ?> →</button>
    </form>
    <a href="<?php echo esc_url( home_url( '/mosque-setup?step=4' ) ); ?>" class="ob-skip"><?php esc_html_e( 'Skip for now →', 'yourjannah' ); ?></a>
    <div class="ob-encourage"><?php esc_html_e( 'Almost there! Just one more step.', 'yourjannah' ); ?></div>

    <?php endif; ?>

    <!-- ================================ -->
    <!-- STEP 4: Share & Grow             -->
    <!-- ================================ -->
    <?php if ( $step === 4 ) : ?>
    <div class="ob-step-header">
        <div class="ob-step-header__icon"><?php echo $step_icons[4]; ?></div>
        <span class="ob-step-label"><?php esc_html_e( 'Step 4 of 4', 'yourjannah' ); ?></span>
        <h1><?php esc_html_e( 'Share Your Mosque Page', 'yourjannah' ); ?></h1>
        <p><?php esc_html_e( 'Your mosque page is ready! Share it with your congregation so they can subscribe and stay connected.', 'yourjannah' ); ?></p>
    </div>

    <!-- Mosque URL -->
    <div class="ob-card">
        <h3>🔗 <?php esc_html_e( 'Your Mosque Page', 'yourjannah' ); ?></h3>
        <div class="ob-url-box">
            <code id="mosque-url"><?php echo esc_html( home_url( '/mosque/' . $mosque_slug ) ); ?></code>
            <button type="button" onclick="copyUrl()">
                <?php esc_html_e( 'Copy Link', 'yourjannah' ); ?>
            </button>
        </div>
        <p style="font-size:13px;color:var(--text-dim);text-align:center;">
            <?php esc_html_e( 'Share this at Jumu\'ah, in your WhatsApp groups, or print it on leaflets.', 'yourjannah' ); ?>
        </p>

        <script>
        function copyUrl() {
            var url = document.getElementById('mosque-url').textContent;
            navigator.clipboard.writeText(url).then(function() {
                var btn = document.querySelector('.ob-url-box button');
                btn.textContent = 'Copied!';
                btn.style.background = '#16a34a';
                setTimeout(function() {
                    btn.textContent = '<?php esc_html_e( 'Copy Link', 'yourjannah' ); ?>';
                    btn.style.background = '';
                }, 2000);
            });
        }
        </script>
    </div>

    <!-- Quick links -->
    <div class="ob-card">
        <h3>📎 <?php esc_html_e( 'Useful Links', 'yourjannah' ); ?></h3>
        <div style="display:grid;gap:8px;">
            <a href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug ) ); ?>" target="_blank"
               style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#f9fafb;border:1px solid var(--border);border-radius:12px;color:var(--text);font-weight:500;min-height:44px;">
                🕌 <?php esc_html_e( 'View Mosque Page', 'yourjannah' ); ?>
                <span style="margin-left:auto;color:var(--text-dim);">→</span>
            </a>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug . '/patron' ) ); ?>" target="_blank"
               style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#f9fafb;border:1px solid var(--border);border-radius:12px;color:var(--text);font-weight:500;min-height:44px;">
                🏅 <?php esc_html_e( 'Patron Page', 'yourjannah' ); ?>
                <span style="margin-left:auto;color:var(--text-dim);">→</span>
            </a>
            <a href="<?php echo esc_url( home_url( '/dashboard?section=broadcast' ) ); ?>"
               style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#f9fafb;border:1px solid var(--border);border-radius:12px;color:var(--text);font-weight:500;min-height:44px;">
                📥 <?php esc_html_e( 'Import Email List (CSV)', 'yourjannah' ); ?>
                <span style="margin-left:auto;color:var(--text-dim);">→</span>
            </a>
        </div>
    </div>

    <!-- Complete -->
    <form method="post">
        <?php wp_nonce_field( 'ynj_onboard', '_ynj_nonce' ); ?>
        <input type="hidden" name="step" value="4">
        <button type="submit" class="ob-btn ob-btn--primary" style="font-size:18px;">
            🎉 <?php esc_html_e( 'Go to Dashboard', 'yourjannah' ); ?>
        </button>
    </form>

    <div class="ob-encourage" style="margin-top:16px;font-size:15px;">
        <?php esc_html_e( 'MashAllah! You\'ve set up your mosque. May Allah bless your community.', 'yourjannah' ); ?>
    </div>

    <?php endif; ?>
</div>

</body>
</html>
