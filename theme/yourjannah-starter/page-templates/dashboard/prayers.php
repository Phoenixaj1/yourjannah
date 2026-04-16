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
$jt = YNJ_DB::table( 'jumuah_slots' );
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
        $url = "https://api.aladhan.com/v1/calendar/{$year}/{$mon}?latitude={$lat}&longitude={$lng}&method={$import_method}";
        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $data = $body['data'] ?? [];
            $imported = 0;

            foreach ( $data as $day ) {
                $timings = $day['timings'] ?? [];
                $date_str = $day['date']['gregorian']['date'] ?? ''; // DD-MM-YYYY
                if ( ! $date_str ) continue;
                $parts = explode( '-', $date_str );
                $sql_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0]; // YYYY-MM-DD

                $clean = function( $t ) { return substr( preg_replace( '/\s*\(.*\)/', '', $t ), 0, 5 ); };

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

                // Upsert: insert or update
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $pt_table WHERE mosque_id=%d AND date=%s", $mosque_id, $sql_date ) );
                if ( $exists ) {
                    $wpdb->update( $pt_table, $row, [ 'id' => $exists ] );
                } else {
                    $wpdb->insert( $pt_table, $row );
                }
                $imported++;
            }
            $success = sprintf( __( 'Imported %d days of prayer times from Aladhan (%s).', 'yourjannah' ), $imported, $methods[ $import_method ] ?? 'Method ' . $import_method );
        } else {
            $error = __( 'Failed to fetch from Aladhan API. Check your mosque GPS coordinates in Settings.', 'yourjannah' );
        }
    }

    // Bulk apply jamat times
    if ( $action === 'bulk_jamat' ) {
        $prayer = sanitize_text_field( $_POST['prayer'] ?? '' );
        $jamat_time = sanitize_text_field( $_POST['jamat_time'] ?? '' );
        $apply_to = sanitize_text_field( $_POST['apply_to'] ?? 'all' );

        if ( $prayer && $jamat_time ) {
            $col = $prayer . '_jamat';
            $dates = [];

            for ( $d = 1; $d <= $days_in_month; $d++ ) {
                $date = sprintf( '%04d-%02d-%02d', $year, $mon, $d );
                $dow = date( 'N', strtotime( $date ) ); // 1=Mon, 7=Sun

                if ( $apply_to === 'all' ||
                     ( $apply_to === 'weekdays' && $dow <= 5 ) ||
                     ( $apply_to === 'weekends' && $dow >= 6 ) ||
                     ( $apply_to === 'friday' && $dow == 5 ) ) {
                    $dates[] = $date;
                }
            }

            $updated = 0;
            foreach ( $dates as $date ) {
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $pt_table WHERE mosque_id=%d AND date=%s", $mosque_id, $date ) );
                if ( $exists ) {
                    $wpdb->update( $pt_table, [ $col => $jamat_time ], [ 'id' => $exists ] );
                    $updated++;
                }
            }
            $success = sprintf( __( 'Set %s jamat to %s for %d days.', 'yourjannah' ), ucfirst( $prayer ), $jamat_time, $updated );
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
    <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
        <?php wp_nonce_field( 'ynj_dash_prayers', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="import_aladhan">
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Month', 'yourjannah' ); ?></label>
            <input type="month" name="import_month" value="<?php echo esc_attr( $month ); ?>" style="padding:8px 12px;">
        </div>
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Calculation Method', 'yourjannah' ); ?></label>
            <select name="method" style="padding:8px 12px;max-width:280px;">
                <?php foreach ( $methods as $mid => $mname ) : ?>
                <option value="<?php echo $mid; ?>" <?php selected( $selected_method, $mid ); ?>><?php echo esc_html( $mname ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="d-btn d-btn--primary">📡 <?php esc_html_e( 'Import Month', 'yourjannah' ); ?></button>
    </form>
    <?php endif; ?>
</div>

<!-- Step 2: Bulk Apply Jamat Times -->
<div class="d-card">
    <h3>⏰ <?php esc_html_e( 'Step 2: Set Jamat (Congregation) Times', 'yourjannah' ); ?></h3>
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
            <label><?php esc_html_e( 'Jamat Time', 'yourjannah' ); ?></label>
            <input type="time" name="jamat_time" required style="padding:8px 12px;">
        </div>
        <div class="d-field" style="margin:0;">
            <label><?php esc_html_e( 'Apply To', 'yourjannah' ); ?></label>
            <select name="apply_to" style="padding:8px 12px;">
                <option value="all"><?php echo esc_html( $month_label ); ?> (<?php esc_html_e( 'whole month', 'yourjannah' ); ?>)</option>
                <option value="weekdays"><?php esc_html_e( 'Weekdays only (Mon-Fri)', 'yourjannah' ); ?></option>
                <option value="weekends"><?php esc_html_e( 'Weekends only (Sat-Sun)', 'yourjannah' ); ?></option>
                <option value="friday"><?php esc_html_e( 'Fridays only', 'yourjannah' ); ?></option>
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
                    <th style="min-width:80px;"><?php esc_html_e( 'Date', 'yourjannah' ); ?></th>
                    <th><?php esc_html_e( 'Day', 'yourjannah' ); ?></th>
                    <th style="text-align:center;"><?php esc_html_e( 'Fajr', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:10px;color:var(--text-dim);">Adhan / Jamat</span></th>
                    <th style="text-align:center;"><?php esc_html_e( 'Dhuhr', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:10px;color:var(--text-dim);">Adhan / Jamat</span></th>
                    <th style="text-align:center;"><?php esc_html_e( 'Asr', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:10px;color:var(--text-dim);">Adhan / Jamat</span></th>
                    <th style="text-align:center;"><?php esc_html_e( 'Maghrib', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:10px;color:var(--text-dim);">Adhan / Jamat</span></th>
                    <th style="text-align:center;"><?php esc_html_e( 'Isha', 'yourjannah' ); ?><br><span style="font-weight:400;font-size:10px;color:var(--text-dim);">Adhan / Jamat</span></th>
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
                <td><?php echo esc_html( $date ); ?></td>
                <td><?php echo esc_html( $dow ); ?><?php if ( $is_friday ) echo ' 🕌'; ?><?php if ( $is_today ) echo ' <span class="d-badge d-badge--green" style="font-size:9px;">TODAY</span>'; ?></td>
                <?php foreach ( [ 'fajr', 'dhuhr', 'asr', 'maghrib', 'isha' ] as $p ) :
                    $adhan = $row->$p ?? '—';
                    $jamat_col = $p . '_jamat';
                    $jamat = $row->$jamat_col ?? '';
                ?>
                <td style="text-align:center;">
                    <?php if ( $editing_day ) : ?>
                    <span style="font-size:11px;color:var(--text-dim);"><?php echo esc_html( $adhan ); ?></span><br>
                    <input type="time" form="edit-day-form" name="<?php echo $p; ?>_jamat" value="<?php echo esc_attr( $jamat ); ?>" style="width:80px;padding:2px 4px;font-size:11px;border:1px solid var(--border);border-radius:4px;">
                    <?php else : ?>
                    <?php echo esc_html( $adhan ); ?>
                    <?php if ( $jamat ) : ?><br><strong style="color:var(--primary);"><?php echo esc_html( $jamat ); ?></strong><?php endif; ?>
                    <?php endif; ?>
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
