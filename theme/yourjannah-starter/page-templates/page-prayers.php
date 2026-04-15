<?php
/**
 * Template: Prayer Times Page
 *
 * Full prayer timetable: today's times, monthly view, Jumu'ah, Eid.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<style>
.ynj-tt-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;}
.ynj-tt-nav{display:flex;align-items:center;gap:12px;}
.ynj-tt-nav button{background:none;border:1px solid #ddd;border-radius:8px;padding:6px 12px;font-size:13px;font-weight:600;cursor:pointer;color:#0a1628;}
.ynj-tt-nav button:active{background:#f0f8ff;}
.ynj-tt-month{font-size:15px;font-weight:700;min-width:120px;text-align:center;}
.ynj-tt-print{background:none;border:1px solid #00ADEF;color:#00ADEF;border-radius:8px;padding:6px 14px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;}
.ynj-tt-table{width:100%;border-collapse:collapse;font-size:11px;line-height:1.3;}
.ynj-tt-table th{background:#f0f8fc;padding:6px 4px;font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:#6b8fa3;text-align:center;position:sticky;top:0;border-bottom:2px solid #e0e8ed;}
.ynj-tt-table td{padding:5px 3px;text-align:center;border-bottom:1px solid #f0f0ec;font-variant-numeric:tabular-nums;}
.ynj-tt-table tr.ynj-tt-today{background:#e8f7ff;font-weight:600;}
.ynj-tt-table tr.ynj-tt-fri{background:#fef9ee;}
.ynj-tt-table .ynj-tt-jamat{color:#00ADEF;font-weight:600;}
.ynj-tt-table .ynj-tt-day{text-align:left;font-weight:500;white-space:nowrap;}
.ynj-tt-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -20px;padding:0 20px;}
@media print{
    .ynj-header,.ynj-nav,.ynj-tt-print,.ynj-timetable-link,.ynj-card--subscribe{display:none!important;}
    body{background:#fff!important;padding:0!important;}
    .ynj-main{max-width:100%!important;padding:0!important;}
    .ynj-card{box-shadow:none!important;border:none!important;background:#fff!important;padding:10px!important;}
    .ynj-tt-table{font-size:10px;}
    .ynj-print-header{display:block!important;text-align:center;margin-bottom:12px;}
}
.ynj-print-header{display:none;}
</style>

<main class="ynj-main">
    <div class="ynj-print-header">
        <h1 id="print-mosque" style="font-size:20px;font-weight:900;margin-bottom:4px;"></h1>
        <p id="print-month" style="font-size:14px;color:#666;"></p>
    </div>

    <!-- Today's Times -->
    <section class="ynj-card" id="today-card">
        <h2 class="ynj-card__title" id="pt-mosque-name"><?php esc_html_e( 'Loading...', 'yourjannah' ); ?></h2>
        <div id="today-grid" class="ynj-prayer-grid"></div>
    </section>

    <!-- Jumu'ah Times -->
    <section class="ynj-card" id="jumuah-card" style="display:none;">
        <h3 class="ynj-card__title"><?php esc_html_e( "Jumu'ah (Friday Prayer)", 'yourjannah' ); ?></h3>
        <div id="jumuah-list"></div>
    </section>

    <!-- Eid Times -->
    <section class="ynj-card" id="eid-card" style="display:none;">
        <h3 class="ynj-card__title"><?php esc_html_e( 'Eid Prayer Times', 'yourjannah' ); ?></h3>
        <div id="eid-list"></div>
    </section>

    <!-- Monthly Timetable -->
    <section class="ynj-card">
        <div class="ynj-tt-header">
            <div class="ynj-tt-nav">
                <button onclick="changeMonth(-1)">&#9664;</button>
                <span class="ynj-tt-month" id="month-label"><?php esc_html_e( 'Loading...', 'yourjannah' ); ?></span>
                <button onclick="changeMonth(1)">&#9654;</button>
            </div>
            <button class="ynj-tt-print" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <?php esc_html_e( 'Print', 'yourjannah' ); ?>
            </button>
        </div>
        <div class="ynj-tt-scroll">
            <table class="ynj-tt-table" id="month-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Day', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Fajr', 'yourjannah' ); ?></th><th><?php esc_html_e( 'F.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Rise', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Dhuhr', 'yourjannah' ); ?></th><th><?php esc_html_e( 'D.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Asr', 'yourjannah' ); ?></th><th><?php esc_html_e( 'A.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Magh', 'yourjannah' ); ?></th><th><?php esc_html_e( 'M.Jam', 'yourjannah' ); ?></th>
                        <th><?php esc_html_e( 'Isha', 'yourjannah' ); ?></th><th><?php esc_html_e( 'I.Jam', 'yourjannah' ); ?></th>
                    </tr>
                </thead>
                <tbody id="month-body"><tr><td colspan="13" style="padding:20px;color:#999;"><?php esc_html_e( 'Loading timetable...', 'yourjannah' ); ?></td></tr></tbody>
            </table>
        </div>
    </section>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;
    let currentMonth = new Date().toISOString().slice(0,7); // YYYY-MM
    let mosqueName = '';

    const T = s => s ? String(s).replace(/:\d{2}$/,'').replace(/\s*\(.*\)/,'') : '\u2014';

    // Load mosque + today's times
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            mosqueName = m.name || slug;
            document.getElementById('pt-mosque-name').textContent = mosqueName;
            document.getElementById('print-mosque').textContent = mosqueName;

            if (m.prayer_times && !m.prayer_times.error) {
                const pt = m.prayer_times;
                const labels = [['fajr','Fajr'],['sunrise','Sunrise'],['dhuhr','Dhuhr'],['asr','Asr'],['maghrib','Maghrib'],['isha','Isha']];
                document.getElementById('today-grid').innerHTML = labels.map(([k,v]) => {
                    const adhan = T(pt[k]);
                    const jamat = pt[k+'_jamat'] ? T(pt[k+'_jamat']) : '';
                    return '<div class="ynj-prayer-row">' +
                        '<span class="ynj-prayer-row__name">' + v + '</span>' +
                        '<span class="ynj-prayer-row__time">' + adhan + (jamat ? ' <span style="color:#00ADEF;font-size:12px;">Jam: ' + jamat + '</span>' : '') + '</span>' +
                    '</div>';
                }).join('');
            }
        });

    // Load Jumu'ah
    fetch(API + 'mosques/' + slug + '/jumuah')
        .then(r => r.json())
        .then(data => {
            const slots = data.slots || [];
            if (!slots.length) return;
            document.getElementById('jumuah-card').style.display = '';
            document.getElementById('jumuah-list').innerHTML =
                '<table class="ynj-tt-table" style="font-size:13px;">' +
                '<thead><tr><th style="text-align:left;">Slot</th><th>Khutbah</th><th>Salah</th><th>Language</th></tr></thead>' +
                '<tbody>' + slots.map(s =>
                    '<tr>' +
                    '<td style="text-align:left;font-weight:600;">' + s.slot_name + '</td>' +
                    '<td>' + T(s.khutbah_time) + '</td>' +
                    '<td class="ynj-tt-jamat">' + T(s.salah_time) + '</td>' +
                    '<td>' + (s.language||'\u2014') + '</td>' +
                    '</tr>'
                ).join('') + '</tbody></table>';
        });

    // Load Eid
    fetch(API + 'mosques/' + slug + '/eid?year=' + new Date().getFullYear())
        .then(r => r.json())
        .then(data => {
            const eids = data.eid_times || [];
            if (!eids.length) return;
            document.getElementById('eid-card').style.display = '';
            const grouped = {};
            eids.forEach(e => { (grouped[e.eid_type] = grouped[e.eid_type] || []).push(e); });
            let html = '';
            for (const [type, slots] of Object.entries(grouped)) {
                const label = type === 'eid_ul_fitr' ? 'Eid ul-Fitr' : 'Eid ul-Adha';
                html += '<h4 style="font-size:14px;font-weight:700;margin:12px 0 8px;">' + label + '</h4>';
                html += slots.map(s =>
                    '<div class="ynj-prayer-row">' +
                    '<span class="ynj-prayer-row__name">' + s.slot_name + '</span>' +
                    '<span class="ynj-prayer-row__time">' + T(s.salah_time) + '</span>' +
                    (s.location_notes ? '<span class="ynj-text-muted" style="font-size:11px;display:block;">' + s.location_notes + '</span>' : '') +
                    '</div>'
                ).join('');
            }
            document.getElementById('eid-list').innerHTML = html;
        });

    // Load monthly timetable
    window.changeMonth = function(delta) {
        const [y, m] = currentMonth.split('-').map(Number);
        const d = new Date(y, m - 1 + delta, 1);
        currentMonth = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
        loadMonth();
    };

    function loadMonth() {
        const [y,m] = currentMonth.split('-');
        const months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        document.getElementById('month-label').textContent = months[parseInt(m)] + ' ' + y;
        document.getElementById('print-month').textContent = 'Prayer Timetable \u2014 ' + months[parseInt(m)] + ' ' + y;
        document.getElementById('month-body').innerHTML = '<tr><td colspan="13" style="padding:20px;color:#999;">Loading...</td></tr>';

        fetch(API + 'mosques/' + slug + '/prayers/month?month=' + currentMonth)
            .then(r => r.json())
            .then(data => {
                const days = data.days || [];
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('month-body').innerHTML = days.map(d => {
                    const isToday = d.date === today;
                    const isFri = d.day === 'Fri';
                    const cls = isToday ? ' class="ynj-tt-today"' : (isFri ? ' class="ynj-tt-fri"' : '');
                    const dd = d.date.split('-')[2];
                    return '<tr' + cls + '>' +
                        '<td class="ynj-tt-day">' + dd + '</td><td>' + d.day + '</td>' +
                        '<td>' + T(d.fajr) + '</td><td class="ynj-tt-jamat">' + T(d.fajr_jamat) + '</td>' +
                        '<td>' + T(d.sunrise) + '</td>' +
                        '<td>' + T(d.dhuhr) + '</td><td class="ynj-tt-jamat">' + T(d.dhuhr_jamat) + '</td>' +
                        '<td>' + T(d.asr) + '</td><td class="ynj-tt-jamat">' + T(d.asr_jamat) + '</td>' +
                        '<td>' + T(d.maghrib) + '</td><td class="ynj-tt-jamat">' + T(d.maghrib_jamat) + '</td>' +
                        '<td>' + T(d.isha) + '</td><td class="ynj-tt-jamat">' + T(d.isha_jamat) + '</td>' +
                    '</tr>';
                }).join('');
            })
            .catch(() => {
                document.getElementById('month-body').innerHTML = '<tr><td colspan="13" style="padding:20px;color:#999;"><?php echo esc_js( __( 'Failed to load.', 'yourjannah' ) ); ?></td></tr>';
            });
    }

    loadMonth();
})();
</script>
<?php
get_footer();
