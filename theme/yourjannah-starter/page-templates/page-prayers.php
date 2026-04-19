<?php
/**
 * Template: Prayer Timetable Page (Pure PHP)
 *
 * Full monthly timetable with adhan + jamat times.
 * Uses prayer_times DB table (imported via dashboard).
 * Falls back to Aladhan API data if no DB data.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_name = $mosque ? $mosque->name : '';
$mosque_id = $mosque ? (int) $mosque->id : 0;

// Month navigation
$month = sanitize_text_field( $_GET['month'] ?? date( 'Y-m' ) );
$parts = explode( '-', $month );
$year = (int) ( $parts[0] ?? date( 'Y' ) );
$mon = (int) ( $parts[1] ?? date( 'n' ) );
$month_label = date( 'F Y', mktime( 0, 0, 0, $mon, 1, $year ) );
$days_in_month = cal_days_in_month( CAL_GREGORIAN, $mon, $year );
$prev_month = date( 'Y-m', mktime( 0, 0, 0, $mon - 1, 1, $year ) );
$next_month = date( 'Y-m', mktime( 0, 0, 0, $mon + 1, 1, $year ) );
$today = date( 'Y-m-d' );

// Load prayer times from DB
$monthly_data = [];
if ( $mosque_id ) {
    $rows = class_exists( 'YNJ_Prayer_Times_Data' ) ? YNJ_Prayer_Times_Data::get_times( $mosque_id ) : [];
    if ( ! is_array( $rows ) ) $rows = [];
    foreach ( $rows as $r ) {
        if ( isset( $r->date ) ) $monthly_data[ $r->date ] = $r;
    }

    // Load Jumu'ah slots
    $jumuah_slots = class_exists( 'YNJ_Jumuah_Data' ) ? YNJ_Jumuah_Data::get_times( $mosque_id ) : [];
    if ( ! is_array( $jumuah_slots ) ) $jumuah_slots = [];

    // Today's times for the hero card
    $today_data = $monthly_data[ $today ] ?? null;
}

$clean = function( $v ) { return $v ? substr( preg_replace( '/\s*\(.*\)/', '', $v ), 0, 5 ) : '—'; };
?>

<style>
.ynj-tt-table{width:100%;border-collapse:collapse;font-size:11px;line-height:1.3;}
.ynj-tt-table th{background:#f0f8fc;padding:6px 4px;font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:#6b8fa3;text-align:center;border-bottom:2px solid #e0e8ed;}
.ynj-tt-table td{padding:5px 3px;text-align:center;border-bottom:1px solid #f0f0ec;font-variant-numeric:tabular-nums;}
.ynj-tt-today{background:#e8f7ff !important;font-weight:600;}
.ynj-tt-fri{background:#fef9ee !important;}
.ynj-tt-jamat{color:#00ADEF;font-weight:600;}
.ynj-tt-day{text-align:left !important;font-weight:500;white-space:nowrap;}
@media print{.ynj-header,.ynj-nav,.ynj-niyyah{display:none!important;}body{background:#fff!important;padding:0!important;}}
</style>

<main class="ynj-main">
    <h1 style="font-size:20px;font-weight:800;margin-bottom:4px;"><?php echo esc_html( $mosque_name ); ?> — <?php esc_html_e( 'Prayer Timetable', 'yourjannah' ); ?></h1>
    <p class="ynj-text-muted" style="margin-bottom:14px;"><?php esc_html_e( 'Adhan and Iqamah times', 'yourjannah' ); ?></p>

    <!-- Today's Times -->
    <?php if ( $today_data ) : ?>
    <div class="ynj-card" style="margin-bottom:14px;">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:10px;">📅 <?php esc_html_e( 'Today', 'yourjannah' ); ?> — <?php echo esc_html( date( 'l j F', strtotime( $today ) ) ); ?></h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(80px,1fr));gap:8px;">
        <?php foreach ( [ 'fajr' => 'Fajr', 'sunrise' => 'Sunrise', 'dhuhr' => 'Dhuhr', 'asr' => 'Asr', 'maghrib' => 'Maghrib', 'isha' => 'Isha' ] as $key => $label ) :
            $adhan = $clean( $today_data->$key ?? '' );
            $jamat_col = $key . '_jamat';
            $jamat = isset( $today_data->$jamat_col ) ? $clean( $today_data->$jamat_col ) : '';
            $is_sunrise = ( $key === 'sunrise' );
        ?>
            <div style="text-align:center;padding:10px 6px;border-radius:10px;background:<?php echo $is_sunrise ? '#fffbeb' : '#f8fafc'; ?>;">
                <div style="font-size:10px;font-weight:700;color:#6b8fa3;text-transform:uppercase;margin-bottom:4px;"><?php echo esc_html( $label ); ?></div>
                <div style="font-size:14px;font-weight:600;"><?php echo esc_html( $adhan ); ?></div>
                <?php if ( $jamat && ! $is_sunrise ) : ?>
                <div style="font-size:12px;font-weight:700;color:#00ADEF;margin-top:2px;"><?php echo esc_html( $jamat ); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Jumu'ah -->
    <?php if ( ! empty( $jumuah_slots ) ) : ?>
    <div class="ynj-card" style="margin-bottom:14px;">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">🕌 <?php esc_html_e( "Jumu'ah (Friday Prayer)", 'yourjannah' ); ?></h3>
        <?php foreach ( $jumuah_slots as $js ) : ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0ec;">
            <div>
                <strong style="font-size:13px;"><?php echo esc_html( $js->slot_name ); ?></strong>
                <?php if ( $js->language ) : ?><span style="font-size:11px;color:#6b8fa3;margin-left:6px;"><?php echo esc_html( $js->language ); ?></span><?php endif; ?>
            </div>
            <div style="font-size:13px;">
                <?php if ( $js->khutbah_time ) : ?><span style="color:#6b8fa3;">Khutbah <?php echo esc_html( substr( $js->khutbah_time, 0, 5 ) ); ?></span> · <?php endif; ?>
                <strong style="color:#00ADEF;">Salah <?php echo esc_html( substr( $js->salah_time, 0, 5 ) ); ?></strong>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Monthly Timetable -->
    <div class="ynj-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <a href="?month=<?php echo esc_attr( $prev_month ); ?>" style="padding:6px 14px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-weight:600;color:#0a1628;text-decoration:none;">← <?php esc_html_e( 'Prev', 'yourjannah' ); ?></a>
            <h3 style="font-size:16px;font-weight:700;margin:0;">📅 <?php echo esc_html( $month_label ); ?></h3>
            <a href="?month=<?php echo esc_attr( $next_month ); ?>" style="padding:6px 14px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-weight:600;color:#0a1628;text-decoration:none;"><?php esc_html_e( 'Next', 'yourjannah' ); ?> →</a>
        </div>

        <?php if ( empty( $monthly_data ) ) : ?>
        <div style="text-align:center;padding:30px;color:#6b8fa3;">
            <p><?php esc_html_e( 'No prayer times imported for this month.', 'yourjannah' ); ?></p>
            <p style="font-size:12px;"><?php esc_html_e( 'The mosque admin can import times via Dashboard → Prayer Times.', 'yourjannah' ); ?></p>
        </div>
        <?php else : ?>
        <div style="overflow-x:auto;">
            <table class="ynj-tt-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Day', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Fajr', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'F.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Rise', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Dhuhr', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'D.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Asr', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'A.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Magh', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'M.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Isha', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'I.Jam', 'yourjannah' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php for ( $d = 1; $d <= $days_in_month; $d++ ) :
                    $date = sprintf( '%04d-%02d-%02d', $year, $mon, $d );
                    $row = $monthly_data[ $date ] ?? null;
                    if ( ! $row ) continue;
                    $dow = date( 'D', strtotime( $date ) );
                    $is_today = ( $date === $today );
                    $is_friday = ( date( 'N', strtotime( $date ) ) == 5 );
                ?>
                <tr class="<?php echo $is_today ? 'ynj-tt-today' : ( $is_friday ? 'ynj-tt-fri' : '' ); ?>">
                    <td class="ynj-tt-day"><?php echo esc_html( $d ); ?></td>
                    <td><?php echo esc_html( $dow ); ?></td>
                    <td><?php echo esc_html( $clean( $row->fajr ) ); ?></td>
                    <td class="ynj-tt-jamat"><?php echo esc_html( $clean( $row->fajr_jamat ?? '' ) ); ?></td>
                    <td style="color:#d97706;"><?php echo esc_html( $clean( $row->sunrise ) ); ?></td>
                    <td><?php echo esc_html( $clean( $row->dhuhr ) ); ?></td>
                    <td class="ynj-tt-jamat"><?php echo esc_html( $clean( $row->dhuhr_jamat ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $clean( $row->asr ) ); ?></td>
                    <td class="ynj-tt-jamat"><?php echo esc_html( $clean( $row->asr_jamat ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $clean( $row->maghrib ) ); ?></td>
                    <td class="ynj-tt-jamat"><?php echo esc_html( $clean( $row->maghrib_jamat ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $clean( $row->isha ) ); ?></td>
                    <td class="ynj-tt-jamat"><?php echo esc_html( $clean( $row->isha_jamat ?? '' ) ); ?></td>
                </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:12px;">
            <button onclick="window.print()" style="padding:8px 20px;border:1px solid #00ADEF;color:#00ADEF;background:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">🖨️ <?php esc_html_e( 'Print Timetable', 'yourjannah' ); ?></button>
        </div>
    </div>
</main>

<?php get_footer(); ?>
