<?php
/**
 * Dashboard Section: Prayer Times — Full Management
 *
 * Features:
 * - Aladhan API import (fetch month of prayer times)
 * - Monthly calendar grid (view all days)
 * - Bulk jamat time editing (apply to weekdays/weekends/all)
 * - Jumu'ah slot management (multiple khutbahs)
 * - Eid prayer management
 *
 * All PHP — no JS API calls.
 */

$pt_table = YNJ_DB::table( 'prayer_times' );
$jt = YNJ_DB::table( 'jumuah_times' );
$lat = $mosque->latitude ? (float) $mosque->latitude : null;
$lng = $mosque->longitude ? (float) $mosque->longitude : null;

// Current month
$month = sanitize_text_field( $_GET['month'] ?? date( 'Y-m' ) );
$month_parts = explode( '-', $month );
$year = (int) ( $month_parts[0] ?? date( 'Y' ) );
$mon = (int) ( $month_parts[1] ?? date( 'n' ) );
$month_label = date( 'F Y', mktime( 0, 0, 0, $mon, 1, $year ) );
$days_in_month = cal_days_in_month( CAL_GREGORIAN, $mon, $year );
$prev_month = date( 'Y-m', mktime( 0, 0, 0, $mon - 1, 1, $year ) );
$next_month = date( 'Y-m', mktime( 0, 0, 0, $mon + 1, 1, $year ) );

// Calculation methods for Aladhan
$methods = [
    2 => 'Islamic Society of North America (ISNA)',
    1 => 'University of Islamic Sciences, Karachi',
    3 => 'Muslim World League (MWL)',
    4 => 'Umm Al-Qura University, Makkah',
    5 => 'Egyptian General Authority of Survey',
    7 => 'Institute of Geophysics, University of Tehran',
    15 => 'Moonsighting Committee Worldwide',
];
$selected_method = (int) ( $_GET['method'] ?? 2 );

// Handle POST actions
$success = ''; $error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_prayers' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    // Import from Aladhan
    if ( $action === 'import_aladhan' && $lat && $lng ) {
        $import_method = (int) ( $_POST['method'] ?? 2 );
        // Use the month from the form, not the URL
        $import_month = sanitize_text_field( $_POST['import_month'] ?? $month );
        $im_parts = explode( '-', $import_month );
        $im_year = (int) ( $im_parts[0] ?? $year );
        $im_mon = (int) ( $im_parts[1] ?? $mon );
        // Update the page month to match
        $year = $im_year; $mon = $im_mon;
        $month = sprintf( '%04d-%02d', $year, $mon );
        $month_label = date( 'F Y', mktime( 0, 0, 0, $mon, 1, $year ) );
        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $mon, $year );

        $asr_school = (int) ( $_POST['school'] ?? 0 ); // 0=Shafi, 1=Hanafi
        $clean = function( $t ) { return substr( preg_replace( '/\s*\(.*\)/', '', $t ), 0, 5 ); };

        // Data comes from browser JS (Cloudways blocks outbound to Aladhan from PHP)
        $json_data = $_POST['aladhan_data'] ?? '';
        if ( $json_data ) {
            $data = json_decode( stripslashes( $json_data ), true ) ?: [];
            $imported = 0;
            foreach ( $data as $day ) {
                $timings = $day['timings'] ?? [];
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
                $success = sprintf( __( 'Imported %d days of prayer times from Aladhan (%s, %s).', 'yourjannah' ), $imported, $methods[ $import_method ] ?? 'Method ' . $import_method, $asr_school ? 'Hanafi' : 'Shafi' );
            } else {
                $error = __( 'No valid data received. Try again.', 'yourjannah' );
            }
        } else {
            $error = __( 'Fetching data... If this message persists, JavaScript may be blocked.', 'yourjannah' );
        }
    }

    // CSV timetable upload
    if ( $action === 'upload_csv' && ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
        $handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
        if ( $handle ) {
            $header = fgetcsv( $handle );
            $header = array_map( 'strtolower', array_map( 'trim', $header ?: [] ) );
            $date_col = array_search( 'date', $header );
            if ( $date_col === false ) { $error = __( 'CSV must have a "date" column.', 'yourjannah' ); }
            else {
                $prayer_cols = [];
                foreach ( [ 'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha', 'fajr_jamat', 'dhuhr_jamat', 'asr_jamat', 'maghrib_jamat', 'isha_jamat' ] as $p ) {
                    $idx = array_search( $p, $header );
                    if ( $idx !== false ) $prayer_cols[ $p ] = $idx;
                }
                $csv_imported = 0;
                while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                    $date = trim( $row[ $date_col ] ?? '' );
                    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) continue;
                    $data = [ 'mosque_id' => $mosque_id, 'date' => $date ];
                    foreach ( $prayer_cols as $col_name => $col_idx ) {
                        $val = trim( $row[ $col_idx ] ?? '' );
                        if ( $val ) $data[ $col_name ] = substr( $val, 0, 5 );
                    }
                    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $pt_table WHERE mosque_id=%d AND date=%s", $mosque_id, $date ) );
                    if ( $exists ) { $wpdb->update( $pt_table, $data, [ 'id' => $exists ] ); }
                    else { $wpdb->insert( $pt_table, $data ); }
                    $csv_imported++;
                }
                fclose( $handle );
                $success = sprintf( __( 'Uploaded %d days of prayer times from CSV.', 'yourjannah' ), $csv_imported );
            }
        }
    }

    // Bulk apply jamat times
    if ( $action === 'bulk_jamat' ) {
        $prayer = sanitize_text_field( $_POST['prayer'] ?? '' );
        $jamat_time = sanitize_text_field( $_POST['jamat_time'] ?? '' );
        $jamat_mode = sanitize_text_field( $_POST['jamat_mode'] ?? 'fixed' );
        $offset_mins = (int) ( $_POST['offset_minutes'] ?? 0 );
        $apply_to = sanitize_text_field( $_POST['apply_to'] ?? 'all' );

        if ( $prayer && ( $jamat_time || ( $jamat_mode === 'offset' && $offset_mins > 0 ) ) ) {
            $col = $prayer . '_jamat';
            $dates = [];

            for ( $d = 1; $d <= $days_in_month; $d++ ) {
                $date = sprintf( '%04d-%02d-%02d', $year, $mon, $d );
                $dow = date( 'N', strtotime( $date ) ); // 1=Mon, 7=Sun
                $week_num = (int) ceil( $d / 7 ); // Week 1-5 of month

                $match = false;
                if ( $apply_to === 'all' ) $match = true;
                elseif ( $apply_to === 'weekdays' && $dow <= 5 ) $match = true;
                elseif ( $apply_to === 'weekends' && $dow >= 6 ) $match = true;
                elseif ( $apply_to === 'friday' && $dow == 5 ) $match = true;
                elseif ( $apply_to === 'week1' && $week_num === 1 ) $match = true;
                elseif ( $apply_to === 'week2' && $week_num === 2 ) $match = true;
                elseif ( $apply_to === 'week3' && $week_num === 3 ) $match = true;
                elseif ( $apply_to === 'week4' && $week_num === 4 ) $match = true;
                elseif ( $apply_to === 'week5' && $week_num === 5 ) $match = true;

                if ( $match ) $dates[] = $date;
            }

            $updated = 0;
            foreach ( $dates as $date ) {
                $row_data = $wpdb->get_row( $wpdb->prepare( "SELECT id, $prayer FROM $pt_table WHERE mosque_id=%d AND date=%s", $mosque_id, $date ) );
                if ( ! $row_data ) continue;

                if ( $jamat_mode === 'offset' && $offset_mins > 0 ) {
                    // Calculate iqamah as adhan + X minutes
                    $adhan = $row_data->$prayer ?? '';
                    if ( $adhan ) {
                        $adhan_ts = strtotime( 'today ' . $adhan );
                        $calc_time = date( 'H:i', $adhan_ts + ( $offset_mins * 60 ) );
                        $wpdb->update( $pt_table, [ $col => $calc_time ], [ 'id' => $row_data->id ] );
                        $updated++;
                    }
                } elseif ( $jamat_mode === 'min' && $jamat_time ) {
                    // Minimum: only set if adhan is after this time
                    $adhan = $row_data->$prayer ?? '';
                    $use_time = ( $adhan && $adhan < $jamat_time ) ? $jamat_time : $adhan;
                    $wpdb->update( $pt_table, [ $col => $use_time ], [ 'id' => $row_data->id ] );
                    $updated++;
                } else {
                    // Fixed time
                    $wpdb->update( $pt_table, [ $col => $jamat_time ], [ 'id' => $row_data->id ] );
                    $updated++;
                }
            }
            $mode_label = $jamat_mode === 'offset' ? "+{$offset_mins}min after adhan" : $jamat_time;
            $success = sprintf( __( 'Set %s iqamah (%s) for %d days.', 'yourjannah' ), ucfirst( $prayer ), $mode_label, $updated );
        }
    }

    // Save single day edit
    if ( $action === 'save_day' ) {
        $date = sanitize_text_field( $_POST['date'] ?? '' );
        $prayers_list = [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ];
        $update = [];
        foreach ( $prayers_list as $p ) {
            if ( isset( $_POST[ $p . '_jamat' ] ) ) {
                $update[ $p . '_jamat' ] = sanitize_text_field( $_POST[ $p . '_jamat' ] );
            }
        }
        if ( $date && ! empty( $update ) ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $pt_table WHERE mosque_id=%d AND date=%s", $mosque_id, $date ) );
            if ( $exists ) {
                $wpdb->update( $pt_table, $update, [ 'id' => $exists ] );
                $success = sprintf( __( 'Jamat times updated for %s.', 'yourjannah' ), $date );
            }
        }
    }

    // Jumu'ah actions
    if ( $action === 'add_jumuah' ) {
        $wpdb->insert( $jt, [
            'mosque_id'    => $mosque_id,
            'slot_name'    => sanitize_text_field( $_POST['slot_name'] ?? "Jumu'ah" ),
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

// Load this month's prayer times
$monthly_data = [];
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $pt_table WHERE mosque_id=%d AND date BETWEEN %s AND %s ORDER BY date ASC",
    $mosque_id, "$year-$mon-01", "$year-$mon-$days_in_month"
) ) ?: [];
foreach ( $rows as $r ) { $monthly_data[ $r->date ] = $r; }

// Load Jumu'ah slots
$jumuah_slots = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $jt WHERE mosque_id=%d AND status='active' ORDER BY salah_time ASC",
    $mosque_id
) ) ?: [];

$last_import = $wpdb->get_var( $wpdb->prepare(
    "SELECT MAX(date) FROM $pt_table WHERE mosque_id=%d", $mosque_id
) );
?>

<div class="d-header">
    <h1>🕐 <?php esc_html_e( 'Prayer Times', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Import from Aladhan, set jamat times, manage Jumu\'ah slots.', 'yourjannah' ); ?></p>
</div>

<?php if ( $success ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( $error ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Step 1: Import from Aladhan -->
<div class="d-card">
    <h3>📡 <?php esc_html_e( 'Step 1: Import Adhan Times from Aladhan', 'yourjannah' ); ?></h3>
    <?php if ( ! $lat || ! $lng ) : ?>
    <div class="d-alert d-alert--error">❌ <?php esc_html_e( 'Your mosque has no GPS coordinates. Go to Settings and add your address first.', 'yourjannah' ); ?></div>
    <?php else : ?>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
        <?php esc_html_e( 'Fetch accurate adhan times for any month. These become the base times that your congregation sees.', 'yourjannah' ); ?>
        <?php if ( $last_import ) : ?>
        <br><strong><?php printf( esc_html__( 'Last import covers up to: %s', 'yourjannah' ), esc_html( $last_import ) ); ?></strong>
        <?php endif; ?>
    </p>
    <form method="post" id="aladhan-form" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="import_aladhan">
        <input type="hidden" name="aladhan_data" id="aladhan-data" value="">
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Month', 'yourjannah' ); ?></label>
            <input type="month" name="import_month" id="import-month" value="<?php echo esc_attr( $month ); ?>" style="padding:8px 12px;">
        </div>
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Calculation Method', 'yourjannah' ); ?></label>
            <select name="method" id="import-method" style="padding:8px 12px;max-width:280px;">
                <?php foreach ( $methods as $mid => $mname ) : ?>
                <option value="<?php echo $mid; ?>" <?php selected( $selected_method, $mid ); ?>><?php echo esc_html( $mname ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Asr School', 'yourjannah' ); ?></label>
            <select name="school" id="import-school" style="padding:8px 12px;">
                <option value="0"><?php esc_html_e( 'Shafi / Standard', 'yourjannah' ); ?></option>
                <option value="1"><?php esc_html_e( 'Hanafi', 'yourjannah' ); ?></option>
            </select>
        </div>
        <button type="button" id="import-btn" class="d-btn d-btn--primary" onclick="fetchAladhan()">📡 <?php esc_html_e( 'Import Month', 'yourjannah' ); ?></button>
    </form>
    <div id="import-status" style="margin-top:8px;font-size:13px;"></div>
    <script>
    function fetchAladhan() {
        var btn = document.getElementById('import-btn');
        var status = document.getElementById('import-status');
        var monthVal = document.getElementById('import-month').value;
        var method = document.getElementById('import-method').value;
        var school = document.getElementById('import-school').value;
        var parts = monthVal.split('-');
        var year = parts[0], mon = parts[1];
        var lat = <?php echo json_encode( $lat ); ?>;
        var lng = <?php echo json_encode( $lng ); ?>;

        btn.disabled = true;
        btn.textContent = 'Fetching from Aladhan...';
        status.innerHTML = '<span style="color:var(--primary);">⏳ Fetching prayer times for ' + monthVal + '...</span>';

        fetch('https://api.aladhan.com/v1/calendar/' + year + '/' + parseInt(mon) + '?latitude=' + lat + '&longitude=' + lng + '&method=' + method + '&school=' + school)
            .then(function(r) { return r.json(); })
            .then(function(body) {
                var data = body.data || [];
                if (!data.length) {
                    status.innerHTML = '<span style="color:#dc2626;">❌ No data returned from Aladhan.</span>';
                    btn.disabled = false; btn.textContent = '📡 Import Month';
                    return;
                }
                status.innerHTML = '<span style="color:var(--primary);">✅ Got ' + data.length + ' days. Saving to database...</span>';
                document.getElementById('aladhan-data').value = JSON.stringify(data);
                document.getElementById('aladhan-form').submit();
            })
            .catch(function(e) {
                status.innerHTML = '<span style="color:#dc2626;">❌ Browser fetch failed: ' + e.message + '</span>';
                btn.disabled = false; btn.textContent = '📡 Import Month';
            });
    }
    </script>
    <?php endif; ?>
</div>

<!-- Step 2: Bulk Apply Jamat Times -->
<div class="d-card">
    <h3 style="margin-bottom:4px;">📄 <?php esc_html_e( 'Or: Upload CSV Timetable', 'yourjannah' ); ?></h3>
    <p style="font-size:12px;color:var(--text-dim);margin-bottom:10px;"><?php esc_html_e( 'Upload a CSV with columns: date, fajr, sunrise, dhuhr, asr, maghrib, isha (optionally: fajr_jamat, dhuhr_jamat, asr_jamat, maghrib_jamat, isha_jamat). Date format: YYYY-MM-DD.', 'yourjannah' ); ?></p>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;">
        <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="upload_csv">
        <input type="file" name="csv_file" accept=".csv,.txt" required style="padding:6px;border:1px dashed var(--border);border-radius:8px;flex:1;">
        <button type="submit" class="d-btn d-btn--outline">📄 <?php esc_html_e( 'Upload', 'yourjannah' ); ?></button>
    </form>
</div>

<div class="d-card">
    <h3>⏰ <?php esc_html_e( 'Step 2: Set Iqamah (Congregation) Times', 'yourjannah' ); ?></h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;"><?php esc_html_e( 'Apply jamat times in bulk — set a fixed jamat time for all weekdays, weekends, or the whole month.', 'yourjannah' ); ?></p>
    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="bulk_jamat">
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Prayer', 'yourjannah' ); ?></label>
            <select name="prayer" style="padding:8px 12px;">
                <option value="fajr">Fajr</option>
                <option value="dhuhr">Dhuhr</option>
                <option value="asr">Asr</option>
                <option value="maghrib">Maghrib</option>
                <option value="isha">Isha</option>
            </select>
        </div>
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Mode', 'yourjannah' ); ?></label>
            <select name="jamat_mode" id="jamat-mode" style="padding:8px 12px;" onchange="document.getElementById('jm-fixed').style.display=this.value!=='offset'?'':'none';document.getElementById('jm-offset').style.display=this.value==='offset'?'':'none';">
                <option value="fixed"><?php esc_html_e( 'Fixed time', 'yourjannah' ); ?></option>
                <option value="offset"><?php esc_html_e( 'Adhan + X minutes', 'yourjannah' ); ?></option>
                <option value="min"><?php esc_html_e( 'Minimum (won\'t go below)', 'yourjannah' ); ?></option>
            </select>
        </div>
        <div class="d-field" style="margin:0;" id="jm-fixed">
            <label><?php esc_html_e( 'Iqamah Time', 'yourjannah' ); ?></label>
            <input type="time" name="jamat_time" style="padding:8px 12px;">
        </div>
        <div class="d-field" style="margin:0;display:none;" id="jm-offset">
            <label><?php esc_html_e( 'Minutes after adhan', 'yourjannah' ); ?></label>
            <select name="offset_minutes" style="padding:8px 12px;">
                <?php for ( $m = 5; $m <= 30; $m += 5 ) : ?>
                <option value="<?php echo $m; ?>"<?php echo $m === 10 ? ' selected' : ''; ?>>+<?php echo $m; ?> min</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Apply To', 'yourjannah' ); ?></label>
            <select name="apply_to" style="padding:8px 12px;">
                <option value="all"><?php echo esc_html( $month_label ); ?> (<?php esc_html_e( 'whole month', 'yourjannah' ); ?>)</option>
                <option value="weekdays"><?php esc_html_e( 'Weekdays only (Mon-Fri)', 'yourjannah' ); ?></option>
                <option value="weekends"><?php esc_html_e( 'Weekends only (Sat-Sun)', 'yourjannah' ); ?></option>
                <option value="friday"><?php esc_html_e( 'Fridays only', 'yourjannah' ); ?></option>
                <option value="week1"><?php esc_html_e( 'Week 1 (days 1-7)', 'yourjannah' ); ?></option>
                <option value="week2"><?php esc_html_e( 'Week 2 (days 8-14)', 'yourjannah' ); ?></option>
                <option value="week3"><?php esc_html_e( 'Week 3 (days 15-21)', 'yourjannah' ); ?></option>
                <option value="week4"><?php esc_html_e( 'Week 4 (days 22-28)', 'yourjannah' ); ?></option>
                <option value="week5"><?php esc_html_e( 'Week 5 (days 29+)', 'yourjannah' ); ?></option>
            </select>
        </div>
        <button type="submit" class="d-btn d-btn--primary">⏰ <?php esc_html_e( 'Apply', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Step 3: Monthly Calendar Grid -->
<div class="d-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <a href="?section=prayers&month=<?php echo esc_attr( $prev_month ); ?>" class="d-btn d-btn--sm d-btn--outline">← <?php esc_html_e( 'Prev', 'yourjannah' ); ?></a>
        <h3 style="margin:0;">📅 <?php echo esc_html( $month_label ); ?></h3>
        <a href="?section=prayers&month=<?php echo esc_attr( $next_month ); ?>" class="d-btn d-btn--sm d-btn--outline"><?php esc_html_e( 'Next', 'yourjannah' ); ?> →</a>
    </div>

    <?php if ( empty( $monthly_data ) ) : ?>
    <div class="d-alert d-alert--info">ℹ️ <?php esc_html_e( 'No prayer times for this month yet. Use "Import Month" above to fetch from Aladhan.', 'yourjannah' ); ?></div>
    <?php else : ?>
    <div style="overflow-x:auto;">
        <table class="d-table" style="font-size:12px;min-width:700px;">
            <thead>
                <tr>
                    <th style="font-size:10px;"><?php esc_html_e( 'Day', 'yourjannah' ); ?></th>
                    <th style="font-size:10px;"><?php esc_html_e( 'Date', 'yourjannah' ); ?></th>
                    <th colspan="3" style="text-align:center;background:#e8f4f8;font-size:11px;"><?php esc_html_e( 'FAJR', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:9px;">Start · Jamaat · Sunrise</span></th>
                    <th colspan="2" style="text-align:center;font-size:11px;"><?php esc_html_e( 'DHUHR', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:9px;">Start · Jamaat</span></th>
                    <th colspan="2" style="text-align:center;background:#e8f4f8;font-size:11px;"><?php esc_html_e( 'ASR', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:9px;">Start · Jamaat</span></th>
                    <th colspan="2" style="text-align:center;font-size:11px;"><?php esc_html_e( 'MAGHRIB', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:9px;">Start · Jamaat</span></th>
                    <th colspan="2" style="text-align:center;background:#e8f4f8;font-size:11px;"><?php esc_html_e( 'ISHA', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:9px;">Start · Jamaat</span></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php for ( $d = 1; $d <= $days_in_month; $d++ ) :
                $date = sprintf( '%04d-%02d-%02d', $year, $mon, $d );
                $row = $monthly_data[ $date ] ?? null;
                if ( ! $row ) continue;
                $dow = date( 'D', strtotime( $date ) );
                $is_friday = ( date( 'N', strtotime( $date ) ) == 5 );
                $is_today = ( $date === date( 'Y-m-d' ) );
                $editing_day = ( ( $_GET['edit_day'] ?? '' ) === $date );
            ?>
            <tr style="<?php echo $is_today ? 'background:#f0fdf4;font-weight:600;' : ''; ?><?php echo $is_friday ? 'background:#eff6ff;' : ''; ?>">
                <td style="font-size:11px;"><?php echo esc_html( substr( $date, 5 ) ); ?></td>
                <td><?php echo esc_html( $dow ); ?><?php if ( $is_friday ) echo ' 🕌'; ?><?php if ( $is_today ) echo ' <span class="d-badge d-badge--green" style="font-size:9px;">TODAY</span>'; ?></td>
                <?php
                // Fajr: Start | Jamaat | Sunrise
                $fajr_s = substr( $row->fajr ?? '', 0, 5 );
                $fajr_j = substr( $row->fajr_jamat ?? '', 0, 5 );
                $sunrise = substr( $row->sunrise ?? '', 0, 5 );
                $bg1 = 'background:#f0f9ff;';
                ?>
                <td style="text-align:center;<?php echo $bg1; ?>font-size:12px;"><?php echo esc_html( $fajr_s ?: '—' ); ?></td>
                <td style="text-align:center;<?php echo $bg1; ?>font-size:12px;font-weight:700;color:var(--primary);">
                    <?php if ( $editing_day ) : ?><input type="time" form="edit-day-form" name="fajr_jamat" value="<?php echo esc_attr( $row->fajr_jamat ?? '' ); ?>" style="width:72px;padding:2px;font-size:11px;border:1px solid var(--border);border-radius:4px;">
                    <?php else : echo esc_html( $fajr_j ?: '—' ); endif; ?>
                </td>
                <td style="text-align:center;<?php echo $bg1; ?>font-size:11px;color:#d97706;"><?php echo esc_html( $sunrise ?: '—' ); ?></td>

                <?php
                // Dhuhr, Asr, Maghrib, Isha: Start | Jamaat each
                $alt = false;
                foreach ( [ 'dhuhr', 'asr', 'maghrib', 'isha' ] as $p ) :
                    $s = substr( $row->$p ?? '', 0, 5 );
                    $jc = $p . '_jamat';
                    $j = substr( $row->$jc ?? '', 0, 5 );
                    $bg = $alt ? 'background:#f0f9ff;' : '';
                    $alt = ! $alt;
                ?>
                <td style="text-align:center;<?php echo $bg; ?>font-size:12px;"><?php echo esc_html( $s ?: '—' ); ?></td>
                <td style="text-align:center;<?php echo $bg; ?>font-size:12px;font-weight:700;color:var(--primary);">
                    <?php if ( $editing_day ) : ?><input type="time" form="edit-day-form" name="<?php echo $p; ?>_jamat" value="<?php echo esc_attr( $row->$jc ?? '' ); ?>" style="width:72px;padding:2px;font-size:11px;border:1px solid var(--border);border-radius:4px;">
                    <?php else : echo esc_html( $j ?: '—' ); endif; ?>
                </td>
                <?php endforeach; ?>
                <td>
                    <?php if ( $editing_day ) : ?>
                    <form method="post" id="edit-day-form">
                        <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
                        <input type="hidden" name="action" value="save_day">
                        <input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>">
                        <button type="submit" class="d-btn d-btn--sm d-btn--primary">💾</button>
                    </form>
                    <a href="?section=prayers&month=<?php echo esc_attr( $month ); ?>" class="d-btn d-btn--sm d-btn--outline" style="margin-top:4px;">✕</a>
                    <?php else : ?>
                    <a href="?section=prayers&month=<?php echo esc_attr( $month ); ?>&edit_day=<?php echo esc_attr( $date ); ?>" class="d-btn d-btn--sm d-btn--outline" title="Edit jamat times">✏️</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Jumu'ah Slots -->
<div class="d-card">
    <h3>🕌 <?php esc_html_e( 'Jumu\'ah Slots', 'yourjannah' ); ?></h3>
    <?php if ( $jumuah_slots ) : ?>
    <table class="d-table" style="margin-bottom:16px;">
        <thead><tr><th><?php esc_html_e( 'Slot', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Khutbah', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Salah', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Language', 'yourjannah' ); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ( $jumuah_slots as $js ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $js->slot_name ); ?></strong></td>
            <td><?php echo esc_html( substr( $js->khutbah_time, 0, 5 ) ); ?></td>
            <td><?php echo esc_html( substr( $js->salah_time, 0, 5 ) ); ?></td>
            <td><?php echo esc_html( $js->language ?: '—' ); ?></td>
            <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('Remove this slot?')">
                    <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="delete_jumuah">
                    <input type="hidden" name="slot_id" value="<?php echo (int) $js->id; ?>">
                    <button class="d-btn d-btn--sm d-btn--danger">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p style="color:var(--text-dim);margin-bottom:12px;"><?php esc_html_e( 'No Jumu\'ah slots yet. Add your first one below.', 'yourjannah' ); ?></p>
    <?php endif; ?>

    <h4 style="margin-bottom:8px;font-size:13px;"><?php esc_html_e( 'Add Jumu\'ah Slot', 'yourjannah' ); ?></h4>
    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="add_jumuah">
        <div class="d-field" style="margin:0;"><label><?php esc_html_e( 'Name', 'yourjannah' ); ?></label><input type="text" name="slot_name" value="Jumu'ah" style="padding:8px 12px;width:140px;"></div>
        <div class="d-field" style="margin:0;"><label><?php esc_html_e( 'Khutbah', 'yourjannah' ); ?></label><input type="time" name="khutbah_time" required style="padding:8px 12px;"></div>
        <div class="d-field" style="margin:0;"><label><?php esc_html_e( 'Salah', 'yourjannah' ); ?></label><input type="time" name="salah_time" required style="padding:8px 12px;"></div>
        <div class="d-field" style="margin:0;"><label><?php esc_html_e( 'Language', 'yourjannah' ); ?></label><input type="text" name="language" placeholder="English" style="padding:8px 12px;width:120px;"></div>
        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Add', 'yourjannah' ); ?></button>
    </form>
</div>
