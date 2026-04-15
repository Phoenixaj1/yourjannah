<?php
/**
 * YourJannah — Mosque Admin Dashboard (SPA).
 * Accessed at /dashboard/ on yourjannah.com domain.
 */
if (!defined('ABSPATH')) exit;

class YNJ_Dashboard {

    public static function render() {
        $rest_url = rest_url('ynj/v1/');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mosque Dashboard — YourJannah</title>
<link rel="manifest" href="<?php echo YNJ_URL; ?>manifest.json">
<meta name="theme-color" content="#287e61">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--primary:#287e61;--primary-light:#e6f2ed;--primary-dark:#1c4644;--bg:#FAFAF8;--card:#fff;--border:#e5e7eb;--text:#1a1a1a;--text-dim:#6b7280;--radius:12px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.d-shell{display:flex;min-height:100vh}
.d-sidebar{width:220px;background:var(--card);border-right:1px solid var(--border);position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:50;display:flex;flex-direction:column}
.d-sidebar__logo{display:flex;align-items:center;gap:8px;padding:20px;font-size:16px;font-weight:900;color:var(--primary-dark);text-decoration:none;border-bottom:1px solid var(--border)}
.d-nav{padding:12px;flex:1}
.d-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;color:var(--text-dim);text-decoration:none;font-size:13px;font-weight:600;border-radius:8px;margin-bottom:2px;transition:all .15s}
.d-nav a:hover{background:var(--primary-light);color:var(--text)}
.d-nav a.active{background:var(--primary);color:#fff}
.d-main{margin-left:220px;padding:24px;flex:1;min-width:0}
.d-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.d-header h1{font-size:22px;font-weight:900}
.d-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px}
.d-stat{text-align:center;padding:16px}
.d-stat__num{font-size:28px;font-weight:900;color:var(--primary)}
.d-stat__label{font-size:11px;font-weight:700;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-top:4px}
.d-grid{display:grid;gap:16px}
.d-grid-2{grid-template-columns:1fr 1fr}
.d-grid-3{grid-template-columns:1fr 1fr 1fr}
.d-grid-4{grid-template-columns:repeat(4,1fr)}
.d-btn{padding:10px 20px;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s}
.d-btn--primary{background:var(--primary);color:#fff}
.d-btn--primary:hover{background:#1c5c47}
.d-btn--secondary{background:var(--card);color:var(--text);border:1px solid var(--border)}
.d-btn--sm{padding:6px 14px;font-size:12px}
.d-btn--danger{background:#dc2626;color:#fff}
.d-field{margin-bottom:16px}
.d-field label{display:block;font-size:12px;font-weight:700;color:var(--text-dim);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.d-field input,.d-field textarea,.d-field select{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit}
.d-table{width:100%;border-collapse:collapse;font-size:14px}
.d-table th{text-align:left;font-size:11px;font-weight:700;color:var(--text-dim);text-transform:uppercase;letter-spacing:.5px;padding:10px 12px;border-bottom:2px solid var(--border)}
.d-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.d-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700}
.d-badge--green{background:#dcfce7;color:#166534}
.d-badge--yellow{background:#fef3c7;color:#92400e}
.d-badge--red{background:#fee2e2;color:#991b1b}
.d-badge--blue{background:#dbeafe;color:#1e40af}
.d-badge--gray{background:#f3f4f6;color:#6b7280}
.d-alert{padding:12px 16px;border-radius:8px;font-size:13px;display:flex;align-items:flex-start;gap:8px}
.d-alert--success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.d-alert--info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
.spinner{display:none;width:16px;height:16px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite}
.loading .btn-text{display:none}.loading .spinner{display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:768px){.d-sidebar{display:none}.d-main{margin-left:0}.d-grid-2,.d-grid-3,.d-grid-4{grid-template-columns:1fr}}
</style>
</head>
<body>
<div id="app"></div>
<script>
var API = '<?php echo esc_js($rest_url); ?>';
var token = localStorage.getItem('ynj_token') || '';
var mosque = null;

function $(s) { return document.querySelector(s); }
function esc(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : '\u2014'; }
function fmtTime(t) { return t ? t.substring(0,5) : '\u2014'; }

function toast(msg, type) {
    var el = document.createElement('div');
    el.style.cssText = 'position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999;animation:fadeIn .3s;' + (type==='error' ? 'background:#fee2e2;color:#991b1b' : 'background:#f0fdf4;color:#166534');
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function(){ el.remove(); }, 3000);
}

function btn(sel, loading) {
    var el = typeof sel === 'string' ? $(sel) : sel;
    if (!el) return;
    if (loading) { el.classList.add('loading'); el.disabled = true; }
    else { el.classList.remove('loading'); el.disabled = false; }
}

async function api(endpoint, opts) {
    opts = opts || {};
    var method = opts.method || 'GET';
    var headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = 'Bearer ' + token;
    var config = { method: method, headers: headers };
    if (opts.body && method !== 'GET') config.body = JSON.stringify(opts.body);
    try {
        var res = await fetch(API + endpoint, config);
        var json = await res.json();
        if (res.status === 401) { logout(); return { ok: false, error: 'Session expired.' }; }
        return json;
    } catch(e) { return { ok: false, error: 'Network error.' }; }
}

function render(html) { document.getElementById('app').innerHTML = html; }

function shell(content) {
    var hash = location.hash.slice(1) || '/';
    var nav = [
        { path: '/', icon: '\ud83d\udcca', label: 'Dashboard' },
        { path: '/prayers', icon: '\ud83d\udd4c', label: 'Prayer Times' },
        { path: '/announcements', icon: '\ud83d\udce2', label: 'Announcements' },
        { path: '/events', icon: '\ud83d\udcc5', label: 'Events' },
        { path: '/bookings', icon: '\ud83d\udcd3', label: 'Bookings' },
        { path: '/rooms', icon: '\ud83c\udfe0', label: 'Rooms' },
        { path: '/masjid-services', icon: '\ud83d\udd4c', label: 'Masjid Services' },
        { path: '/enquiries', icon: '\u2709\ufe0f', label: 'Enquiries' },
        { path: '/subscribers', icon: '\ud83d\udc65', label: 'Subscribers' },
        { path: '/classes', icon: '\ud83c\udf93', label: 'Classes' },
        { path: '/campaigns', icon: '\u2764\ufe0f', label: 'Fundraising' },
        { path: '/madrassah', icon: '\ud83d\udcda', label: 'Madrassah' },
        { path: '/patrons', icon: '\ud83c\udfc5', label: 'Patrons' },
        { path: '/settings', icon: '\u2699\ufe0f', label: 'Settings' },
    ];
    var isActive = function(p) { return p === '/' ? hash === '/' : hash.startsWith(p); };
    return '<div class="d-shell">' +
        '<aside class="d-sidebar">' +
        '<a href="/" class="d-sidebar__logo">\ud83d\udd4c YourJannah</a>' +
        '<nav class="d-nav">' +
        nav.map(function(n) { return '<a href="#' + n.path + '" class="' + (isActive(n.path)?'active':'') + '"><span>' + n.icon + '</span> ' + n.label + '</a>'; }).join('') +
        '<div style="border-top:1px solid var(--border);margin:8px 0"></div>' +
        '<a href="#" onclick="event.preventDefault();logout()" style="color:#dc2626">\ud83d\udeaa Logout</a>' +
        '</nav>' +
        (mosque ? '<div style="padding:16px;border-top:1px solid var(--border);font-size:12px;color:var(--text-dim)"><strong>' + esc(mosque.name) + '</strong></div>' : '') +
        '</aside>' +
        '<main class="d-main">' + content + '</main></div>';
}

function navigate(path) { location.hash = path; }

function logout() {
    token = ''; mosque = null;
    localStorage.removeItem('ynj_token');
    navigate('/auth/login');
}

async function loadMosque() {
    var res = await api('admin/me');
    if (res.ok) mosque = res.mosque;
}

// ── Router ──
function route() {
    var hash = location.hash.slice(1) || '/';
    if (!token && !hash.startsWith('/auth')) return navigate('/auth/login');
    if (token && hash.startsWith('/auth')) return navigate('/');

    var routes = {
        '/': renderDashboard,
        '/prayers': renderPrayers,
        '/announcements': renderAnnouncements,
        '/events': renderEvents,
        '/bookings': renderBookings,
        '/rooms': renderRooms,
        '/enquiries': renderEnquiries,
        '/subscribers': renderSubscribers,
        '/classes': renderClasses,
        '/classes/new': renderClassForm,
        '/enrolments': renderEnrolments,
        '/campaigns': renderCampaigns,
        '/campaigns/new': renderCampaignForm,
        '/masjid-services': renderMasjidServices,
        '/masjid-services/new': renderMasjidServiceForm,
        '/masjid-service-enquiries': renderMasjidServiceEnquiries,
        '/madrassah': renderMadrassah,
        '/madrassah/students': renderMadStudents,
        '/madrassah/students/new': renderMadStudentForm,
        '/madrassah/attendance': renderMadAttendance,
        '/madrassah/terms': renderMadTerms,
        '/madrassah/terms/new': renderMadTermForm,
        '/madrassah/reports': renderMadReports,
        '/madrassah/reports/new': renderMadReportForm,
        '/madrassah/fees': renderMadFees,
        '/patrons': renderPatrons,
        '/settings': renderSettings,
        '/auth/login': renderLogin,
        '/auth/register': renderRegister,
    };
    (routes[hash] || renderDashboard)();
}

// ── Auth Pages ──
function renderLogin() {
    render('<div style="max-width:400px;margin:80px auto;padding:0 20px"><div style="text-align:center;margin-bottom:24px"><h1 style="font-size:28px;font-weight:900">\ud83d\udd4c YourJannah</h1><p style="color:var(--text-dim);margin-top:8px">Mosque Admin Dashboard</p></div><div class="d-card"><div class="d-field"><label>Email</label><input type="email" id="l_email" placeholder="admin@masjid.org"></div><div class="d-field"><label>Password</label><input type="password" id="l_pass" placeholder="Min 8 characters"></div><button class="d-btn d-btn--primary" style="width:100%" id="l-btn" onclick="doLogin()"><span class="btn-text">Sign In</span><span class="spinner"></span></button><p style="text-align:center;margin-top:16px;font-size:13px;color:var(--text-dim)">Don\'t have an account? <a href="#/auth/register" style="color:var(--primary);font-weight:700">Register your mosque</a></p></div></div>');
}

async function doLogin() {
    btn('#l-btn', true);
    var res = await api('admin/login', { method: 'POST', body: { email: $('#l_email').value, password: $('#l_pass').value } });
    btn('#l-btn', false);
    if (res.ok) { token = res.token; localStorage.setItem('ynj_token', token); await loadMosque(); navigate('/'); }
    else toast(res.error || 'Login failed.', 'error');
}

function renderRegister() {
    render('<div style="max-width:400px;margin:60px auto;padding:0 20px"><div style="text-align:center;margin-bottom:24px"><h1 style="font-size:28px;font-weight:900">\ud83d\udd4c YourJannah</h1><p style="color:var(--text-dim);margin-top:8px">Register your mosque</p></div><div class="d-card"><div class="d-field"><label>Mosque Name</label><input type="text" id="r_name" placeholder="e.g. East London Masjid"></div><div class="d-grid d-grid-2"><div class="d-field"><label>City</label><input type="text" id="r_city" placeholder="London"></div><div class="d-field"><label>Postcode</label><input type="text" id="r_postcode" placeholder="E1 1JX"></div></div><div class="d-field"><label>Address</label><input type="text" id="r_address" placeholder="46 Whitechapel Rd"></div><div class="d-field"><label>Admin Email</label><input type="email" id="r_email" placeholder="admin@masjid.org"></div><div class="d-field"><label>Password</label><input type="password" id="r_pass" placeholder="Min 8 characters"></div><button class="d-btn d-btn--primary" style="width:100%" id="r-btn" onclick="doRegister()"><span class="btn-text">Register Mosque</span><span class="spinner"></span></button><p style="text-align:center;margin-top:16px;font-size:13px;color:var(--text-dim)">Already registered? <a href="#/auth/login" style="color:var(--primary);font-weight:700">Sign in</a></p></div></div>');
}

async function doRegister() {
    btn('#r-btn', true);
    var res = await api('admin/register', { method: 'POST', body: { name: $('#r_name').value, city: $('#r_city').value, postcode: $('#r_postcode').value, address: $('#r_address').value, email: $('#r_email').value, password: $('#r_pass').value } });
    btn('#r-btn', false);
    if (res.ok) { token = res.token; localStorage.setItem('ynj_token', token); await loadMosque(); navigate('/'); toast('Mosque registered!'); }
    else toast(res.error || 'Registration failed.', 'error');
}

// ── Dashboard ──
async function renderDashboard() {
    render(shell('<div class="d-header"><h1>Dashboard</h1></div><div class="d-card">Loading...</div>'));
    if (!mosque) await loadMosque();
    var subs = await api('admin/subscribers');
    var enquiries = await api('admin/enquiries?status=new');
    var members = await api('admin/members/count');
    var bookings = await api('admin/bookings');
    var campaigns = await api('mosques/' + mosque.id + '/campaigns');
    var patrons = await api('admin/patrons');

    var memberCount = members.count || 0;
    var subCount = subs.total || (subs.subscribers||[]).length;
    var enqCount = (enquiries.enquiries||[]).length;
    var bookCount = (bookings.bookings||[]).length;
    var campCount = (campaigns.campaigns||[]).length;
    var patronCount = patrons.total_active || 0;
    var patronMonthly = patrons.monthly_pence || 0;

    render(shell(
        '<div class="d-header"><h1>Dashboard</h1></div>' +
        '<div class="d-grid d-grid-4" style="margin-bottom:20px">' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + memberCount + '</div><div class="d-stat__label">Members on YourJannah</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + subCount + '</div><div class="d-stat__label">Push Subscribers</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + enqCount + '</div><div class="d-stat__label">New Enquiries</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + bookCount + '</div><div class="d-stat__label">Bookings</div></div>' +
        '</div>' +
        (patronCount > 0 ? '<div class="d-card" style="margin-bottom:16px"><h3 style="margin-bottom:8px">\ud83c\udfc5 Patrons</h3><p style="color:var(--text-dim);font-size:13px">' + patronCount + ' active patron' + (patronCount>1?'s':'') + ' — \u00a3' + (patronMonthly/100).toFixed(2) + '/month</p><button class="d-btn d-btn--secondary d-btn--sm" style="margin-top:8px" onclick="navigate(\'/patrons\')">View Patrons</button></div>' : '') +
        (campCount > 0 ? '<div class="d-card" style="margin-bottom:16px"><h3 style="margin-bottom:8px">Fundraising</h3><p style="color:var(--text-dim);font-size:13px">' + campCount + ' active campaign' + (campCount>1?'s':'') + '</p><button class="d-btn d-btn--secondary d-btn--sm" style="margin-top:8px" onclick="navigate(\'/campaigns\')">Manage Campaigns</button></div>' : '') +
        '<div class="d-card"><h3 style="margin-bottom:12px">Quick Actions</h3>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
        '<button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/announcements\')">New Announcement</button>' +
        '<button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/prayers\')">Update Prayer Times</button>' +
        '<button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/events\')">Add Event</button>' +
        '<button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/campaigns\')">New Campaign</button>' +
        '</div></div>'
    ));
}

// ── Prayer Times (Monthly Grid) ──
var prayerMonth = new Date().toISOString().substring(0,7); // YYYY-MM
var monthlyData = {}; // {date: {fajr, dhuhr, ...}}

async function renderPrayers() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Prayer Times</h1></div><div class="d-card">Loading...</div>'));

    // Jumu'ah
    var jRes = await api('mosques/' + mosque.id + '/jumuah');
    var jSlots = (jRes.slots||[]);
    var jRows = jSlots.map(function(s) {
        return '<tr><td>'+esc(s.slot_name)+'</td><td>'+fmtTime(s.khutbah_time)+'</td><td>'+fmtTime(s.salah_time)+'</td><td>'+esc(s.language)+'</td><td><button class="d-btn d-btn--danger d-btn--sm" onclick="deleteJumuah('+s.id+')">Del</button></td></tr>';
    }).join('');

    // Eid
    var eRes = await api('mosques/' + mosque.id + '/eid?year=' + new Date().getFullYear());
    var eSlots = (eRes.eid_times||[]);
    var eRows = eSlots.map(function(e) {
        var label = e.eid_type === 'eid_ul_fitr' ? 'Fitr' : 'Adha';
        return '<tr><td><span class="d-badge d-badge--green">'+label+'</span></td><td>'+esc(e.slot_name)+'</td><td>'+fmtTime(e.salah_time)+'</td><td>'+esc(e.location_notes||'')+'</td></tr>';
    }).join('');

    render(shell(
        '<div class="d-header"><h1>\ud83d\udd4c Prayer Times</h1></div>' +

        // Step 1: Auto-import adhan times
        '<div class="d-card" style="margin-bottom:16px">' +
        '<h3 style="margin-bottom:8px">Step 1: Import Adhan Times</h3>' +
        '<p style="color:var(--text-dim);font-size:13px;margin-bottom:12px">Automatically fetch adhan (call to prayer) times from the Aladhan API for the whole month. These are calculated from your mosque\'s GPS coordinates.</p>' +
        '<div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">' +
        '<div class="d-field" style="margin-bottom:0"><label>Month</label><div style="display:flex;gap:4px;align-items:center"><button class="d-btn d-btn--secondary d-btn--sm" onclick="shiftMonth(-1)" style="padding:4px 8px">\u25c0</button><input type="month" id="pt_month" value="'+prayerMonth+'" onchange="prayerMonth=this.value;monthlyData={};loadMonthGrid()"><button class="d-btn d-btn--secondary d-btn--sm" onclick="shiftMonth(1)" style="padding:4px 8px">\u25b6</button></div></div>' +
        '<div class="d-field" style="margin-bottom:0"><label>Calculation Method</label><select id="pt_method"><option value="15">Moonsighting Committee (UK)</option><option value="2">ISNA</option><option value="3">Muslim World League</option><option value="4">Umm al-Qura</option><option value="5">Egyptian Authority</option><option value="1">Karachi</option></select></div>' +
        '<button class="d-btn d-btn--primary" id="pt-import" onclick="importAdhanTimes()"><span class="btn-text">\u2b07\ufe0f Import Adhan Times</span><span class="spinner"></span></button>' +
        '</div></div>' +

        // Step 2: Set jamat times
        '<div class="d-card" style="margin-bottom:16px">' +
        '<h3 style="margin-bottom:8px">Step 2: Set Jamat Times</h3>' +
        '<p style="color:var(--text-dim);font-size:13px;margin-bottom:12px">Set the jamat (congregation) times. You can set one time and apply it to the whole month, or adjust individual days in the grid below.</p>' +
        '<div class="d-grid d-grid-3" style="gap:8px">' +
        '<div class="d-field"><label>Fajr Jamat</label><input type="time" id="jt_fajr" value="04:45"></div>' +
        '<div class="d-field"><label>Dhuhr Jamat</label><input type="time" id="jt_dhuhr" value="13:30"></div>' +
        '<div class="d-field"><label>Asr Jamat</label><input type="time" id="jt_asr" value="17:15"></div>' +
        '<div class="d-field"><label>Maghrib Jamat</label><input type="time" id="jt_maghrib" placeholder="Auto: adhan +5min"></div>' +
        '<div class="d-field"><label>Isha Jamat</label><input type="time" id="jt_isha" value="22:00"></div>' +
        '<div class="d-field"><label>Apply to</label><select id="jt_apply_mode">' +
        '<optgroup label="Bulk">' +
        '<option value="month">Whole Month</option>' +
        '<option value="weekdays">Weekdays (Mon\u2013Fri)</option>' +
        '<option value="weekends">Weekends (Sat\u2013Sun)</option>' +
        '</optgroup>' +
        '<optgroup label="By Week">' +
        '<option value="week1">Week 1 (1st\u20137th)</option>' +
        '<option value="week2">Week 2 (8th\u201314th)</option>' +
        '<option value="week3">Week 3 (15th\u201321st)</option>' +
        '<option value="week4">Week 4 (22nd\u201328th)</option>' +
        '<option value="week5">Week 5 (29th+)</option>' +
        '</optgroup>' +
        '<optgroup label="By Day">' +
        '<option value="mon">Every Monday</option><option value="tue">Every Tuesday</option>' +
        '<option value="wed">Every Wednesday</option><option value="thu">Every Thursday</option>' +
        '<option value="fri">Every Friday</option><option value="sat">Every Saturday</option>' +
        '<option value="sun">Every Sunday</option>' +
        '</optgroup>' +
        '<optgroup label="Custom">' +
        '<option value="custom">Select Dates\u2026</option>' +
        '</optgroup>' +
        '</select></div>' +
        '</div>' +
        '<div style="display:flex;gap:8px;margin-top:8px">' +
        '<button class="d-btn d-btn--primary" id="pt-bulk" onclick="bulkApplyJamat()"><span class="btn-text">Apply Jamat Times</span><span class="spinner"></span></button>' +
        '<button class="d-btn d-btn--secondary" onclick="saveGridEdits()">Save Grid Edits</button>' +
        '</div>' +
        '<div id="custom-dates-picker" style="display:none;margin-top:8px;padding:10px;background:#f9fafb;border-radius:8px;border:1px solid var(--border)">' +
        '<p style="font-size:12px;font-weight:600;margin-bottom:6px">Select dates to apply:</p>' +
        '<div id="custom-dates-grid" style="display:flex;flex-wrap:wrap;gap:4px"></div>' +
        '</div>' +
        '<p style="color:var(--text-dim);font-size:11px;margin-top:6px">\ud83d\udca1 Tip: You can also edit jamat times directly in the grid below by clicking on any cell.</p>' +
        '</div>' +

        // Step 3: Monthly grid
        '<div class="d-card" style="margin-bottom:16px;overflow-x:auto">' +
        '<h3 style="margin-bottom:8px">Monthly Timetable</h3>' +
        '<div id="prayer-grid"><p style="color:var(--text-dim)">Import adhan times first, then the timetable will appear here.</p></div>' +
        '</div>' +

        // Jumu'ah
        '<div class="d-card" style="margin-bottom:16px"><h3 style="margin-bottom:12px">Jumu\'ah Slots</h3>' +
        (jRows ? '<table class="d-table"><thead><tr><th>Slot</th><th>Khutbah</th><th>Salah</th><th>Language</th><th></th></tr></thead><tbody>'+jRows+'</tbody></table>' : '<p style="color:var(--text-dim)">No Jumu\'ah slots.</p>') +
        '<div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px"><h4 style="margin-bottom:8px;font-size:13px">Add Jumu\'ah Slot</h4>' +
        '<div class="d-grid d-grid-4"><div class="d-field"><label>Slot Name</label><input id="jm_name" placeholder="First Jumu\'ah"></div><div class="d-field"><label>Khutbah</label><input type="time" id="jm_khutbah"></div><div class="d-field"><label>Salah</label><input type="time" id="jm_salah"></div><div class="d-field"><label>Language</label><select id="jm_lang"><option>English</option><option>Arabic</option><option>Urdu</option><option>Bilingual</option></select></div></div>' +
        '<button class="d-btn d-btn--primary d-btn--sm" onclick="addJumuah()">Add Slot</button></div></div>' +

        // Eid
        '<div class="d-card"><h3 style="margin-bottom:12px">Eid Times \u2014 ' + new Date().getFullYear() + '</h3>' +
        (eRows ? '<table class="d-table"><thead><tr><th>Eid</th><th>Slot</th><th>Time</th><th>Location</th></tr></thead><tbody>'+eRows+'</tbody></table>' : '<p style="color:var(--text-dim)">No Eid times set.</p>') +
        '<div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px"><h4 style="margin-bottom:8px;font-size:13px">Add Eid Slot</h4>' +
        '<div class="d-grid d-grid-4"><div class="d-field"><label>Type</label><select id="eid_type"><option value="eid_ul_fitr">Eid ul-Fitr</option><option value="eid_ul_adha">Eid ul-Adha</option></select></div><div class="d-field"><label>Slot Name</label><input id="eid_name" placeholder="First Prayer"></div><div class="d-field"><label>Time</label><input type="time" id="eid_time"></div><div class="d-field"><label>Location</label><input id="eid_loc" placeholder="Main Hall"></div></div>' +
        '<button class="d-btn d-btn--primary d-btn--sm" onclick="addEid()">Add Eid Slot</button></div></div>'
    ));

    // Try to load existing saved times for this month
    loadMonthGrid();
}

async function importAdhanTimes() {
    if (!mosque || !mosque.latitude) { toast('Mosque has no GPS coordinates. Update in Settings.', 'error'); return; }
    btn('#pt-import', true);
    var month = $('#pt_month').value;
    var parts = month.split('-');
    var year = parts[0], mon = parts[1];
    var method = $('#pt_method').value;

    try {
        var url = 'https://api.aladhan.com/v1/calendar/' + year + '/' + parseInt(mon) + '?latitude=' + mosque.latitude + '&longitude=' + mosque.longitude + '&method=' + method;
        var resp = await fetch(url);
        var data = await resp.json();
        if (!data || !data.data || !data.data.length) { toast('No data returned from Aladhan.', 'error'); btn('#pt-import', false); return; }

        // Parse and save each day
        var dates = [];
        var strip = function(s) { return (s||'').replace(/\s*\(.*\)/, '').substring(0,5); };
        data.data.forEach(function(day) {
            var t = day.timings;
            var dateStr = day.date.gregorian.year + '-' + String(day.date.gregorian.month.number).padStart(2,'0') + '-' + String(day.date.gregorian.day).padStart(2,'0');
            var times = {
                fajr: strip(t.Fajr), sunrise: strip(t.Sunrise), dhuhr: strip(t.Dhuhr),
                asr: strip(t.Asr), maghrib: strip(t.Maghrib), isha: strip(t.Isha)
            };
            monthlyData[dateStr] = times;
            dates.push({ date: dateStr, times: times });
        });

        // Save to server via bulk API
        var res = await api('admin/prayers/bulk', { method: 'PUT', body: { dates: dates } });
        btn('#pt-import', false);
        if (res.ok) {
            toast('Adhan times imported for ' + dates.length + ' days!');
            renderMonthGrid();
        } else {
            toast(res.error || 'Failed to save.', 'error');
        }
    } catch(e) {
        btn('#pt-import', false);
        toast('Failed to fetch from Aladhan API: ' + e.message, 'error');
    }
}

async function loadMonthGrid() {
    // Fetch saved prayer times for this month from our API
    var parts = prayerMonth.split('-');
    var year = parseInt(parts[0]), mon = parseInt(parts[1]);
    var daysInMonth = new Date(year, mon, 0).getDate();

    for (var d = 1; d <= daysInMonth; d++) {
        var dateStr = prayerMonth + '-' + String(d).padStart(2,'0');
        var res = await api('mosques/' + mosque.id + '/prayers?date=' + dateStr);
        if (res.times && res.times.fajr) {
            monthlyData[dateStr] = res.times;
        }
    }
    if (Object.keys(monthlyData).length > 0) renderMonthGrid();
}

function renderMonthGrid() {
    var el = document.getElementById('prayer-grid');
    if (!el) return;

    var parts = prayerMonth.split('-');
    var year = parseInt(parts[0]), mon = parseInt(parts[1]);
    var daysInMonth = new Date(year, mon, 0).getDate();
    var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    var jInput = function(dateStr, prayer, val) {
        var v = val ? String(val).substring(0,5) : '';
        return '<input type="time" data-date="'+dateStr+'" data-prayer="'+prayer+'_jamat" value="'+v+'" style="width:75px;padding:2px 4px;border:1px solid #e5e7eb;border-radius:4px;font-size:11px;">';
    };

    var html = '<table class="d-table" style="font-size:11px;white-space:nowrap"><thead><tr>' +
        '<th>Date</th><th>Day</th>' +
        '<th style="color:#6b8fa3">Fajr</th><th style="color:#6b8fa3">Dhuhr</th><th style="color:#6b8fa3">Asr</th><th style="color:#6b8fa3">Maghrib</th><th style="color:#6b8fa3">Isha</th>' +
        '<th style="color:#16a34a;border-left:2px solid #e5e7eb">Fajr J</th><th style="color:#16a34a">Dhuhr J</th><th style="color:#16a34a">Asr J</th><th style="color:#16a34a">Magh J</th><th style="color:#16a34a">Isha J</th>' +
        '</tr></thead><tbody>';

    for (var d = 1; d <= daysInMonth; d++) {
        var dateStr = prayerMonth + '-' + String(d).padStart(2,'0');
        var dt = new Date(year, mon - 1, d);
        var dayName = dayNames[dt.getDay()];
        var isFri = dt.getDay() === 5;
        var t = monthlyData[dateStr] || {};
        var rowStyle = isFri ? ' style="background:#f0fdf4;font-weight:600"' : (d%2===0 ? ' style="background:#fafafa"' : '');

        html += '<tr' + rowStyle + '>';
        html += '<td><strong>' + d + '</strong></td><td>' + dayName + '</td>';
        // Adhan times (read-only)
        html += '<td>' + fmtTime(t.fajr) + '</td>';
        html += '<td>' + fmtTime(t.dhuhr) + '</td>';
        html += '<td>' + fmtTime(t.asr) + '</td>';
        html += '<td>' + fmtTime(t.maghrib) + '</td>';
        html += '<td>' + fmtTime(t.isha) + '</td>';
        // Jamat times (editable inputs)
        html += '<td style="border-left:2px solid #e5e7eb">' + jInput(dateStr, 'fajr', t.fajr_jamat) + '</td>';
        html += '<td>' + jInput(dateStr, 'dhuhr', t.dhuhr_jamat) + '</td>';
        html += '<td>' + jInput(dateStr, 'asr', t.asr_jamat) + '</td>';
        html += '<td>' + jInput(dateStr, 'maghrib', t.maghrib_jamat) + '</td>';
        html += '<td>' + jInput(dateStr, 'isha', t.isha_jamat) + '</td>';
        html += '</tr>';
    }
    html += '</tbody></table>';
    html += '<p style="margin-top:8px;font-size:11px;color:var(--text-dim)">\ud83d\udfe2 Green = Friday. Left columns = Adhan (calculated). Right columns = Jamat (editable \u2014 click to change any day).</p>';
    el.innerHTML = html;
}

// Month navigation
window.shiftMonth = function(delta) {
    var parts = prayerMonth.split('-');
    var y = parseInt(parts[0]), m = parseInt(parts[1]) + delta;
    if (m > 12) { m = 1; y++; }
    if (m < 1) { m = 12; y--; }
    prayerMonth = y + '-' + String(m).padStart(2,'0');
    var el = document.getElementById('pt_month');
    if (el) el.value = prayerMonth;
    monthlyData = {};
    loadMonthGrid();
};

// Show/hide custom date picker when mode changes
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'jt_apply_mode') {
        var picker = document.getElementById('custom-dates-picker');
        if (!picker) return;
        if (e.target.value === 'custom') {
            picker.style.display = '';
            // Populate date checkboxes
            var parts = prayerMonth.split('-');
            var year = parseInt(parts[0]), mon = parseInt(parts[1]);
            var daysInMonth = new Date(year, mon, 0).getDate();
            var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            var grid = document.getElementById('custom-dates-grid');
            if (grid) {
                var html = '';
                for (var d = 1; d <= daysInMonth; d++) {
                    var dt = new Date(year, mon-1, d);
                    var dn = dayNames[dt.getDay()];
                    var isFri = dt.getDay() === 5;
                    html += '<label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;'+(isFri?'background:#dcfce7;font-weight:600':'background:#fff;border:1px solid #e5e7eb')+'">' +
                        '<input type="checkbox" class="custom-date-cb" value="'+d+'" style="width:14px;height:14px;accent-color:#00ADEF"> ' +
                        d + ' ' + dn + '</label>';
                }
                html += '<div style="margin-top:6px;display:flex;gap:4px"><button class="d-btn d-btn--sm d-btn--secondary" onclick="selectAllCustomDates(true)">Select All</button><button class="d-btn d-btn--sm d-btn--secondary" onclick="selectAllCustomDates(false)">Deselect All</button></div>';
                grid.innerHTML = html;
            }
        } else {
            picker.style.display = 'none';
        }
    }
});

window.selectAllCustomDates = function(val) {
    document.querySelectorAll('.custom-date-cb').forEach(function(cb) { cb.checked = val; });
};

async function bulkApplyJamat() {
    var mode = $('#jt_apply_mode') ? $('#jt_apply_mode').value : 'month';
    var dayMap = {sun:0,mon:1,tue:2,wed:3,thu:4,fri:5,sat:6};
    var weekRanges = {week1:[1,7],week2:[8,14],week3:[15,21],week4:[22,28],week5:[29,31]};

    // Custom date selection
    var customDays = [];
    if (mode === 'custom') {
        document.querySelectorAll('.custom-date-cb:checked').forEach(function(cb) { customDays.push(parseInt(cb.value)); });
        if (!customDays.length) { toast('Select at least one date.', 'error'); return; }
    }

    var modeLabel = mode === 'month' ? 'whole month' :
        mode === 'weekdays' ? 'weekdays' : mode === 'weekends' ? 'weekends' :
        mode.startsWith('week') ? mode.replace('week', 'Week ') + ' (' + weekRanges[mode][0] + '\u2013' + weekRanges[mode][1] + ')' :
        mode === 'custom' ? customDays.length + ' selected days' :
        'every ' + mode.charAt(0).toUpperCase() + mode.slice(1);
    if(!confirm('Apply jamat times to '+modeLabel+' of '+prayerMonth+'?'))return;
    btn('#pt-bulk',true);

    var times = {};
    ['fajr','dhuhr','asr','maghrib','isha'].forEach(function(k) {
        var v = $('#jt_'+k);
        if (v && v.value) times[k+'_jamat'] = v.value + ':00';
    });

    var parts = prayerMonth.split('-');
    var year = parseInt(parts[0]), mon = parseInt(parts[1]);
    var daysInMonth = new Date(year, mon, 0).getDate();
    var dates = [];

    for (var d = 1; d <= daysInMonth; d++) {
        var dateStr = prayerMonth + '-' + String(d).padStart(2,'0');
        var dt = new Date(year, mon - 1, d);
        var dow = dt.getDay(); // 0=Sun, 5=Fri, 6=Sat

        // Filter by apply mode
        if (mode === 'weekdays' && (dow === 0 || dow === 6)) continue;
        if (mode === 'weekends' && dow !== 0 && dow !== 6) continue;
        if (dayMap[mode] !== undefined && dow !== dayMap[mode]) continue;
        if (weekRanges[mode] && (d < weekRanges[mode][0] || d > weekRanges[mode][1])) continue;
        if (mode === 'custom' && customDays.indexOf(d) === -1) continue;

        var dayTimes = Object.assign({}, times);
        // Auto-calc maghrib jamat as adhan +5min if not set
        if (!dayTimes.maghrib_jamat && monthlyData[dateStr] && monthlyData[dateStr].maghrib) {
            var maghStr = String(monthlyData[dateStr].maghrib).substring(0,5);
            var maghParts = maghStr.split(':');
            var mH = parseInt(maghParts[0]), mM = parseInt(maghParts[1]) + 5;
            if (mM >= 60) { mH++; mM -= 60; }
            dayTimes.maghrib_jamat = String(mH).padStart(2,'0') + ':' + String(mM).padStart(2,'0') + ':00';
        }
        dates.push({ date: dateStr, times: dayTimes });
    }

    var res = await api('admin/prayers/bulk', { method: 'PUT', body: { dates: dates } });
    btn('#pt-bulk',false);
    if (res.ok) {
        toast('Jamat times applied to ' + dates.length + ' days (' + modeLabel + ')!');
        await loadMonthGrid();
        renderMonthGrid();
    } else {
        toast(res.error || 'Failed.', 'error');
    }
}

// Save individual grid edits (reads all input values from the grid)
async function saveGridEdits() {
    var inputs = document.querySelectorAll('#prayer-grid input[type="time"]');
    if (!inputs.length) { toast('No edits to save.', 'error'); return; }

    // Group by date
    var dateMap = {};
    inputs.forEach(function(inp) {
        var date = inp.dataset.date;
        var prayer = inp.dataset.prayer;
        if (!date || !prayer || !inp.value) return;
        if (!dateMap[date]) dateMap[date] = {};
        dateMap[date][prayer] = inp.value + ':00';
    });

    var dates = Object.entries(dateMap).map(function(e) {
        return { date: e[0], times: e[1] };
    });

    if (!dates.length) { toast('No changes to save.', 'error'); return; }

    var res = await api('admin/prayers/bulk', { method: 'PUT', body: { dates: dates } });
    if (res.ok) {
        toast('Saved ' + dates.length + ' day(s) of jamat times!');
    } else {
        toast(res.error || 'Failed.', 'error');
    }
}

async function addJumuah() {
    var res=await api('admin/jumuah',{method:'POST',body:{slot_name:$('#jm_name').value,khutbah_time:$('#jm_khutbah').value+':00',salah_time:$('#jm_salah').value+':00',language:$('#jm_lang').value}});
    if(res.ok){toast('Jumu\'ah slot added!');renderPrayers();}else toast(res.error||'Failed.','error');
}
async function deleteJumuah(id){if(!confirm('Delete this slot?'))return;await api('admin/jumuah/'+id,{method:'DELETE'});toast('Deleted.');renderPrayers();}

async function addEid() {
    var res=await api('admin/eid',{method:'POST',body:{eid_type:$('#eid_type').value,year:new Date().getFullYear(),slot_name:$('#eid_name').value,salah_time:$('#eid_time').value+':00',location_notes:$('#eid_loc').value}});
    if(res.ok){toast('Eid slot added!');renderPrayers();}else toast(res.error||'Failed.','error');
}

// ── Announcements ──
async function renderAnnouncements() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Announcements</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/announcements') || {};
    // Fallback: use public endpoint
    if (!res.announcements) { res = await api('mosques/' + mosque.id + '/announcements'); }
    var list = res.announcements || [];
    var rows = list.map(function(a) {
        return '<tr><td><strong>'+esc(a.title)+'</strong></td><td><span class="d-badge d-badge--'+(a.status==='published'?'green':'gray')+'">'+esc(a.status)+'</span></td><td>'+(a.pinned?'📌':'')+'</td><td>'+fmtDate(a.published_at)+'</td><td>'+(a.push_sent?'✅':'—')+'</td><td><button class="d-btn d-btn--danger d-btn--sm" onclick="deleteAnn('+a.id+')">Delete</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Announcements</h1><button class="d-btn d-btn--primary d-btn--sm" onclick="showAnnForm()">+ New</button></div>' +
        '<div class="d-card" id="ann-form" style="display:none"><h3 style="margin-bottom:12px">New Announcement</h3>' +
        '<div class="d-field"><label>Title</label><input type="text" id="ann_title"></div>' +
        '<div class="d-field"><label>Body</label><textarea id="ann_body" rows="4"></textarea></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Type</label><select id="ann_type"><option>general</option><option>urgent</option><option>event</option></select></div><div class="d-field"><label>Pinned</label><select id="ann_pinned"><option value="0">No</option><option value="1">Yes</option></select></div><div class="d-field"><label>Publish</label><select id="ann_publish"><option value="1">Now</option><option value="0">Draft</option></select></div></div>' +
        '<div style="display:flex;gap:8px"><button class="d-btn d-btn--primary" id="ann-save" onclick="saveAnn()"><span class="btn-text">Save & Publish</span><span class="spinner"></span></button><button class="d-btn d-btn--secondary" onclick="$(\'#ann-form\').style.display=\'none\'">Cancel</button></div></div>' +
        '<div class="d-card"><table class="d-table"><thead><tr><th>Title</th><th>Status</th><th>Pin</th><th>Date</th><th>Push</th><th></th></tr></thead><tbody>'+(rows||'<tr><td colspan="6" style="color:var(--text-dim)">No announcements yet.</td></tr>')+'</tbody></table></div>'
    ));
}
function showAnnForm(){$('#ann-form').style.display='';}
async function saveAnn(){
    btn('#ann-save',true);
    var res=await api('admin/announcements',{method:'POST',body:{title:$('#ann_title').value,body:$('#ann_body').value,type:$('#ann_type').value,pinned:parseInt($('#ann_pinned').value),publish:parseInt($('#ann_publish').value)}});
    btn('#ann-save',false);
    if(res.ok){toast('Announcement created!');renderAnnouncements();}else toast(res.error||'Failed.','error');
}
async function deleteAnn(id){if(!confirm('Delete this announcement?'))return;await api('admin/announcements/'+id,{method:'DELETE'});toast('Deleted.');renderAnnouncements();}

// ── Events ──
async function renderEvents() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Events</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/events') || {};
    if (!res.events) { res = await api('mosques/' + mosque.id + '/events'); }
    var list = res.events || [];
    var rows = list.map(function(e) {
        var liveBadge = e.is_online ? (e.is_live ? '<span class="d-badge d-badge--red">LIVE</span>' : '<span class="d-badge d-badge--blue">Online</span>') : '';
        var liveBtn = e.is_online ? (e.is_live ? '<button class="d-btn d-btn--secondary d-btn--sm" onclick="toggleLive('+e.id+',0)">End</button>' : '<button class="d-btn d-btn--primary d-btn--sm" onclick="toggleLive('+e.id+',1)" style="background:#dc2626">Go Live</button>') : '';
        return '<tr><td><strong>'+esc(e.title)+'</strong> '+liveBadge+'</td><td>'+esc(e.event_date||'')+'</td><td>'+fmtTime(e.start_time)+'</td><td>'+esc(e.event_type||'')+'</td><td>'+e.registered_count+'/'+(e.max_capacity||'∞')+'</td><td><span class="d-badge d-badge--'+(e.status==='published'?'green':'gray')+'">'+esc(e.status)+'</span></td><td>'+liveBtn+' <button class="d-btn d-btn--danger d-btn--sm" onclick="deleteEvent('+e.id+')">Del</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Events</h1><button class="d-btn d-btn--primary d-btn--sm" onclick="showEventForm()">+ New Event</button></div>' +
        '<div class="d-card" id="ev-form" style="display:none"><h3 style="margin-bottom:12px">New Event</h3>' +
        '<div class="d-field"><label>Title</label><input type="text" id="ev_title"></div>' +
        '<div class="d-field"><label>Description</label><textarea id="ev_desc" rows="3"></textarea></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Date</label><input type="date" id="ev_date"></div><div class="d-field"><label>Start</label><input type="time" id="ev_start"></div><div class="d-field"><label>End</label><input type="time" id="ev_end"></div></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Location</label><input type="text" id="ev_location"></div><div class="d-field"><label>Type</label><select id="ev_type"><option>talk</option><option>class</option><option>course</option><option>workshop</option><option>community</option><option>sports</option><option>competition</option><option>youth</option></select></div><div class="d-field"><label>Capacity</label><input type="number" id="ev_cap" value="0"></div></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Ticket Price (pence, 0=free)</label><input type="number" id="ev_price" value="0"></div><div class="d-field"><label>Status</label><select id="ev_status"><option>published</option><option>draft</option></select></div></div>' +
        '<div style="border-top:1px solid var(--border);margin:16px 0;padding-top:16px"><h4 style="margin-bottom:12px;font-size:13px">🔴 Online / Live Event (optional)</h4>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Online Event?</label><select id="ev_online"><option value="0">No — In Person</option><option value="1">Yes — Online/Live</option></select></div><div class="d-field"><label>Live Stream URL</label><input id="ev_live_url" placeholder="https://youtube.com/live/..."></div><div class="d-field"><label>Donation Target (£)</label><input type="number" id="ev_don_target" value="0" placeholder="0 = no target"></div></div></div>' +
        '<div style="display:flex;gap:8px"><button class="d-btn d-btn--primary" id="ev-save" onclick="saveEvent()"><span class="btn-text">Create Event</span><span class="spinner"></span></button><button class="d-btn d-btn--secondary" onclick="$(\'#ev-form\').style.display=\'none\'">Cancel</button></div></div>' +
        '<div class="d-card"><table class="d-table"><thead><tr><th>Title</th><th>Date</th><th>Time</th><th>Type</th><th>RSVP</th><th>Status</th><th></th></tr></thead><tbody>'+(rows||'<tr><td colspan="7" style="color:var(--text-dim)">No events.</td></tr>')+'</tbody></table></div>'
    ));
}
function showEventForm(){$('#ev-form').style.display='';}
async function saveEvent(){
    btn('#ev-save',true);
    var res=await api('admin/events',{method:'POST',body:{title:$('#ev_title').value,description:$('#ev_desc').value,event_date:$('#ev_date').value,start_time:$('#ev_start').value+':00',end_time:$('#ev_end').value+':00',location:$('#ev_location').value,event_type:$('#ev_type').value,max_capacity:parseInt($('#ev_cap').value),ticket_price_pence:parseInt($('#ev_price').value),requires_booking:1,status:$('#ev_status').value,is_online:parseInt($('#ev_online').value),live_url:$('#ev_live_url').value,donation_target_pence:parseInt($('#ev_don_target').value||0)*100}});
    btn('#ev-save',false);
    if(res.ok){toast('Event created!');renderEvents();}else toast(res.error||'Failed.','error');
}
async function toggleLive(id, live) {
    var res = await api('admin/events/'+id, {method:'PUT', body:{is_live:live}});
    if (res.ok) { toast(live ? '🔴 Event is now LIVE!' : 'Live ended.'); renderEvents(); }
    else toast(res.error || 'Failed.', 'error');
}
async function deleteEvent(id){if(!confirm('Delete this event?'))return;await api('admin/events/'+id,{method:'DELETE'});toast('Deleted.');renderEvents();}

// ── Bookings ──
async function renderBookings() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Bookings</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/bookings');
    var list = res.bookings || [];
    var rows = list.map(function(b) {
        var type = b.event_id ? 'Event' : (b.room_id ? 'Room' : '—');
        var badge = b.status==='confirmed'?'green':(b.status==='pending'||b.status==='pending_payment'?'yellow':'red');
        return '<tr><td><strong>'+esc(b.user_name)+'</strong><br><span style="font-size:11px;color:var(--text-dim)">'+esc(b.user_email)+'</span></td><td>'+type+'</td><td>'+esc(b.booking_date)+'</td><td>'+fmtTime(b.start_time)+' — '+fmtTime(b.end_time)+'</td><td><span class="d-badge d-badge--'+badge+'">'+esc(b.status)+'</span></td><td>'+(b.status==='pending'?'<button class="d-btn d-btn--primary d-btn--sm" onclick="updateBooking('+b.id+',\'confirmed\')">Approve</button> <button class="d-btn d-btn--danger d-btn--sm" onclick="updateBooking('+b.id+',\'cancelled\')">Reject</button>':'')+'</td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Bookings</h1></div>' +
        '<div class="d-card"><table class="d-table"><thead><tr><th>Guest</th><th>Type</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead><tbody>'+(rows||'<tr><td colspan="6" style="color:var(--text-dim)">No bookings.</td></tr>')+'</tbody></table></div>'
    ));
}
async function updateBooking(id,status){await api('admin/bookings/'+id,{method:'PUT',body:{status:status}});toast('Booking '+status+'.');renderBookings();}

// ── Rooms ──
async function renderRooms() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Rooms</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/rooms');
    var list = res.rooms || [];
    var rows = list.map(function(r) {
        return '<tr><td><strong>'+esc(r.name)+'</strong></td><td>'+r.capacity+'</td><td>£'+(r.hourly_rate_pence/100).toFixed(0)+'/hr</td><td>£'+(r.daily_rate_pence/100).toFixed(0)+'/day</td><td><span class="d-badge d-badge--'+(r.status==='active'?'green':'gray')+'">'+esc(r.status)+'</span></td><td><button class="d-btn d-btn--danger d-btn--sm" onclick="deleteRoom('+r.id+')">Del</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Rooms</h1><button class="d-btn d-btn--primary d-btn--sm" onclick="showRoomForm()">+ Add Room</button></div>' +
        '<div class="d-card" id="rm-form" style="display:none"><h3 style="margin-bottom:12px">Add Room</h3>' +
        '<div class="d-field"><label>Name</label><input type="text" id="rm_name"></div>' +
        '<div class="d-field"><label>Description</label><textarea id="rm_desc" rows="2"></textarea></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Capacity</label><input type="number" id="rm_cap" value="0"></div><div class="d-field"><label>Hourly Rate (pence)</label><input type="number" id="rm_hourly" value="0"></div><div class="d-field"><label>Daily Rate (pence)</label><input type="number" id="rm_daily" value="0"></div></div>' +
        '<div style="display:flex;gap:8px"><button class="d-btn d-btn--primary" id="rm-save" onclick="saveRoom()"><span class="btn-text">Add Room</span><span class="spinner"></span></button><button class="d-btn d-btn--secondary" onclick="$(\'#rm-form\').style.display=\'none\'">Cancel</button></div></div>' +
        '<div class="d-card"><table class="d-table"><thead><tr><th>Name</th><th>Capacity</th><th>Hourly</th><th>Daily</th><th>Status</th><th></th></tr></thead><tbody>'+(rows||'<tr><td colspan="6" style="color:var(--text-dim)">No rooms.</td></tr>')+'</tbody></table></div>'
    ));
}
function showRoomForm(){$('#rm-form').style.display='';}
async function saveRoom(){
    btn('#rm-save',true);
    var res=await api('admin/rooms',{method:'POST',body:{name:$('#rm_name').value,description:$('#rm_desc').value,capacity:parseInt($('#rm_cap').value),hourly_rate_pence:parseInt($('#rm_hourly').value),daily_rate_pence:parseInt($('#rm_daily').value)}});
    btn('#rm-save',false);
    if(res.ok){toast('Room added!');renderRooms();}else toast(res.error||'Failed.','error');
}
async function deleteRoom(id){if(!confirm('Delete this room?'))return;await api('admin/rooms/'+id,{method:'DELETE'});toast('Deleted.');renderRooms();}

// ── Enquiries ──
async function renderEnquiries() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Enquiries</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/enquiries');
    var list = res.enquiries || [];
    var rows = list.map(function(e) {
        var badge = e.status==='new'?'blue':(e.status==='read'?'yellow':(e.status==='replied'?'green':'gray'));
        return '<tr><td><strong>'+esc(e.name)+'</strong><br><span style="font-size:11px;color:var(--text-dim)">'+esc(e.email)+'</span></td><td>'+esc(e.subject||'—')+'</td><td><span class="d-badge d-badge--'+badge+'">'+esc(e.status)+'</span></td><td>'+fmtDate(e.created_at)+'</td><td>'+(e.status==='new'?'<button class="d-btn d-btn--secondary d-btn--sm" onclick="markEnquiry('+e.id+',\'read\')">Read</button> ':'')+(e.status!=='replied'?'<button class="d-btn d-btn--primary d-btn--sm" onclick="markEnquiry('+e.id+',\'replied\')">Replied</button>':'')+'</td></tr>' +
        '<tr><td colspan="5" style="padding:8px 12px;background:#f9fafb;font-size:13px;color:var(--text-dim)">'+esc(e.message)+'</td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Enquiries</h1></div>' +
        '<div class="d-card"><table class="d-table"><thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>'+(rows||'<tr><td colspan="5" style="color:var(--text-dim)">No enquiries.</td></tr>')+'</tbody></table></div>'
    ));
}
async function markEnquiry(id,status){await api('admin/enquiries/'+id,{method:'PUT',body:{status:status}});toast('Marked as '+status+'.');renderEnquiries();}

// ── Subscribers ──
async function renderSubscribers() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Subscribers</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/subscribers');
    var list = res.subscribers || [];
    var total = res.total || list.length;
    var rows = list.map(function(s) {
        return '<tr><td><strong>'+esc(s.name||'—')+'</strong></td><td>'+esc(s.email)+'</td><td>'+esc(s.phone||'—')+'</td><td>'+esc(s.device_type||'—')+'</td><td>'+fmtDate(s.subscribed_at)+'</td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Subscribers</h1><span class="d-badge d-badge--green">'+total+' total</span></div>' +
        '<div class="d-card"><button class="d-btn d-btn--secondary d-btn--sm" onclick="exportCSV()" style="margin-bottom:12px">Export CSV</button>' +
        '<table class="d-table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Device</th><th>Subscribed</th></tr></thead><tbody>'+(rows||'<tr><td colspan="5" style="color:var(--text-dim)">No subscribers yet.</td></tr>')+'</tbody></table></div>'
    ));
}
function exportCSV(){
    var table=$('.d-table');if(!table)return;
    var csv=[];var rows=table.querySelectorAll('tr');
    rows.forEach(function(r){var cols=[];r.querySelectorAll('th,td').forEach(function(c){cols.push('"'+c.textContent.replace(/"/g,'""')+'"');});csv.push(cols.join(','));});
    var blob=new Blob([csv.join('\n')],{type:'text/csv'});var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='subscribers.csv';a.click();
}
// ── Classes ──
async function renderClasses() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Classes</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/classes');
    var list = res.classes || [];
    var rows = list.map(function(c) {
        var price = c.price_pence > 0 ? '£'+(c.price_pence/100) : 'Free';
        var pt = c.price_type === 'per_session' ? '/session' : (c.price_type === 'monthly' ? '/mo' : '');
        var online = c.is_online ? '<span class="d-badge d-badge--blue">Online</span>' : '';
        return '<tr><td><strong>'+esc(c.title)+'</strong> '+online+'</td><td>'+esc(c.category)+'</td><td>'+esc(c.instructor_name||'—')+'</td><td>'+price+pt+'</td><td>'+c.enrolled_count+'/'+(c.max_capacity||'∞')+'</td><td>'+esc(c.schedule_text||c.day_of_week||'—')+'</td><td><button class="d-btn d-btn--danger d-btn--sm" onclick="deleteClass('+c.id+')">Del</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Classes & Courses</h1><div style="display:flex;gap:8px"><button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/classes/new\')">+ New Class</button><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/enrolments\')">Enrolments</button></div></div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Class</th><th>Category</th><th>Instructor</th><th>Price</th><th>Enrolled</th><th>Schedule</th><th></th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No classes yet.</p>') +
        '</div>'
    ));
}
async function renderClassForm() {
    if (!mosque) await loadMosque();
    render(shell(
        '<div class="d-header"><h1>New Class</h1></div><div class="d-card">' +
        '<div class="d-field"><label>Title</label><input id="cl_title" placeholder="e.g. Tajweed for Beginners"></div>' +
        '<div class="d-field"><label>Description</label><textarea id="cl_desc" rows="3"></textarea></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Category</label><select id="cl_cat"><option>Quran</option><option>Arabic</option><option>Tajweed</option><option>Islamic Studies</option><option>Fiqh</option><option>Seerah</option><option>Business</option><option>SEO</option><option>Marketing</option><option>Finance</option><option>Health</option><option>Fitness</option><option>Cooking</option><option>Parenting</option><option>Youth</option><option>Sisters</option></select></div><div class="d-field"><label>Instructor</label><input id="cl_instructor" placeholder="Sheikh Ahmad"></div><div class="d-field"><label>Type</label><select id="cl_type"><option value="course">Course</option><option value="workshop">Workshop</option><option value="drop_in">Drop-in</option><option value="seminar">Seminar</option></select></div></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Day</label><select id="cl_day"><option value="">Any</option><option>Monday</option><option>Tuesday</option><option>Wednesday</option><option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option></select></div><div class="d-field"><label>Start Time</label><input type="time" id="cl_start"></div><div class="d-field"><label>End Time</label><input type="time" id="cl_end"></div></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Start Date</label><input type="date" id="cl_sdate"></div><div class="d-field"><label>Sessions</label><input type="number" id="cl_sessions" value="1"></div><div class="d-field"><label>Capacity (0=unlimited)</label><input type="number" id="cl_cap" value="0"></div></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Price (pence, 0=free)</label><input type="number" id="cl_price" value="0"></div><div class="d-field"><label>Price Type</label><select id="cl_ptype"><option value="one_off">One-off / Full course</option><option value="per_session">Per session</option><option value="monthly">Monthly</option></select></div><div class="d-field"><label>Online?</label><select id="cl_online"><option value="0">In Person</option><option value="1">Online</option></select></div></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Location</label><input id="cl_location" placeholder="Main Hall / Online"></div><div class="d-field"><label>Live URL (if online)</label><input id="cl_url" placeholder="https://zoom.us/..."></div></div>' +
        '<div style="display:flex;gap:8px;margin-top:16px"><button class="d-btn d-btn--primary" id="cl-save" onclick="saveClass()"><span class="btn-text">Create Class</span><span class="spinner"></span></button><button class="d-btn d-btn--secondary" onclick="navigate(\'/classes\')">Cancel</button></div></div>'
    ));
}
async function saveClass() {
    btn('#cl-save',true);
    var res = await api('admin/classes',{method:'POST',body:{
        title:$('#cl_title').value, description:$('#cl_desc').value,
        category:$('#cl_cat').value, instructor_name:$('#cl_instructor').value,
        class_type:$('#cl_type').value, day_of_week:$('#cl_day').value,
        start_time:($('#cl_start').value||'')+':00', end_time:($('#cl_end').value||'')+':00',
        start_date:$('#cl_sdate').value, total_sessions:parseInt($('#cl_sessions').value),
        max_capacity:parseInt($('#cl_cap').value), price_pence:parseInt($('#cl_price').value),
        price_type:$('#cl_ptype').value, is_online:parseInt($('#cl_online').value),
        location:$('#cl_location').value, live_url:$('#cl_url').value
    }});
    btn('#cl-save',false);
    if(res.ok){toast('Class created!');navigate('/classes');}else toast(res.error||'Failed.','error');
}
async function deleteClass(id){if(!confirm('Delete?'))return;await api('admin/classes/'+id,{method:'DELETE'});toast('Deleted.');renderClasses();}
async function renderEnrolments() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Enrolments</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/enrolments');
    var list = res.enrolments || [];
    var rows = list.map(function(e) {
        var badge = e.status==='confirmed'?'green':(e.status==='pending'?'yellow':'gray');
        var paid = e.amount_paid_pence > 0 ? '£'+(e.amount_paid_pence/100) : 'Free';
        return '<tr><td><strong>'+esc(e.user_name)+'</strong><br><span style="font-size:11px;color:var(--text-dim)">'+esc(e.user_email)+'</span></td><td>'+esc(e.class_title)+'</td><td>'+paid+'</td><td><span class="d-badge d-badge--'+badge+'">'+esc(e.status)+'</span></td><td>'+fmtDate(e.enrolled_at)+'</td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Enrolments</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/classes\')">← Back to Classes</button></div>' +
        '<div class="d-card"><table class="d-table"><thead><tr><th>Student</th><th>Class</th><th>Paid</th><th>Status</th><th>Date</th></tr></thead><tbody>'+(rows||'<tr><td colspan="5" style="color:var(--text-dim)">No enrolments.</td></tr>')+'</tbody></table></div>'
    ));
}

// ── Fundraising Campaigns ──
async function renderCampaigns() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Fundraising</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('mosques/' + mosque.id + '/campaigns');
    var list = res.campaigns || [];
    var rows = list.map(function(c) {
        var pct = c.percentage || 0;
        var target = c.target_pence > 0 ? '£' + (c.target_pence/100).toLocaleString() : '';
        var raised = '£' + (c.raised_pence/100).toLocaleString();
        var recur = c.recurring ? '<span class="d-badge d-badge--blue">Monthly</span>' : '';
        return '<tr><td><strong>'+esc(c.title)+'</strong> '+recur+(c.dfm_link?'<br><a href="'+esc(c.dfm_link)+'" target="_blank" style="font-size:11px;color:var(--primary)">DFM link ↗</a>':'')+'</td><td>'+esc(c.category)+'</td><td>'+raised+(target?' / '+target:'')+'</td><td><div style="background:#e5e7eb;border-radius:4px;height:8px;width:80px;display:inline-block;vertical-align:middle"><div style="background:#16a34a;border-radius:4px;height:100%;width:'+pct+'%"></div></div> '+pct+'%</td><td>'+c.donor_count+'</td><td><button class="d-btn d-btn--secondary d-btn--sm" onclick="editCampaign('+c.id+','+c.raised_pence+','+c.donor_count+')">Update</button> <button class="d-btn d-btn--danger d-btn--sm" onclick="deleteCampaign('+c.id+')">Del</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Fundraising Campaigns</h1><button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/campaigns/new\')">+ New Campaign</button></div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Campaign</th><th>Category</th><th>Raised</th><th>Progress</th><th>Donors</th><th></th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No campaigns yet. Create your first fundraising campaign.</p>') +
        '</div>'
    ));
}

async function renderCampaignForm() {
    if (!mosque) await loadMosque();
    render(shell(
        '<div class="d-header"><h1>New Campaign</h1></div>' +
        '<div class="d-card">' +
        '<div class="d-field"><label>Title</label><input id="cp_title" placeholder="e.g. New Roof Fund"></div>' +
        '<div class="d-field"><label>Description</label><textarea id="cp_desc" rows="3" placeholder="Tell donors what this is for and why it matters"></textarea></div>' +
        '<div class="d-grid d-grid-3">' +
        '<div class="d-field"><label>Category</label><select id="cp_cat"><option>general</option><option>welfare</option><option>expansion</option><option>renovation</option><option>education</option><option>youth</option><option>emergency</option><option>equipment</option><option>heating</option><option>parking</option></select></div>' +
        '<div class="d-field"><label>Target (£)</label><input type="number" id="cp_target" placeholder="50000"></div>' +
        '<div class="d-field"><label>Type</label><select id="cp_recur"><option value="0">One-off target</option><option value="1">Monthly target</option></select></div>' +
        '</div>' +
        '<div class="d-field"><label>DonationForMasjid Link (optional)</label><input id="cp_dfm" placeholder="https://donationformasjid.com/your-mosque"></div>' +
        '<div style="display:flex;gap:8px;margin-top:16px"><button class="d-btn d-btn--primary" id="cp-save" onclick="saveCampaign()"><span class="btn-text">Create Campaign</span><span class="spinner"></span></button><button class="d-btn d-btn--secondary" onclick="navigate(\'/campaigns\')">Cancel</button></div>' +
        '</div>'
    ));
}

async function saveCampaign() {
    btn('#cp-save', true);
    var recur = parseInt($('#cp_recur').value);
    var res = await api('admin/campaigns', {method:'POST', body:{
        title: $('#cp_title').value,
        description: $('#cp_desc').value,
        category: $('#cp_cat').value,
        target_pence: parseInt($('#cp_target').value || 0) * 100,
        recurring: recur,
        recurring_interval: recur ? 'monthly' : '',
        dfm_link: $('#cp_dfm').value
    }});
    btn('#cp-save', false);
    if (res.ok) { toast('Campaign created!'); navigate('/campaigns'); }
    else toast(res.error || 'Failed.', 'error');
}

async function editCampaign(id, currentRaised, currentDonors) {
    var raisedPounds = prompt('Raised amount in £ (current: £' + (currentRaised/100).toLocaleString() + '):', (currentRaised/100));
    if (raisedPounds === null) return;
    var donors = prompt('Number of donors (current: ' + currentDonors + '):', currentDonors);
    if (donors === null) return;

    var res = await api('admin/campaigns/' + id, {method:'PUT', body:{
        raised_pence: Math.round(parseFloat(raisedPounds) * 100),
        donor_count: parseInt(donors) || 0
    }});
    if (res.ok) { toast('Campaign updated!'); renderCampaigns(); }
    else toast(res.error || 'Failed.', 'error');
}

async function deleteCampaign(id) {
    if (!confirm('Delete this campaign?')) return;
    await api('admin/campaigns/' + id, {method:'DELETE'});
    toast('Deleted.');
    renderCampaigns();
}

// ── Masjid Services ──
async function renderMasjidServices() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Masjid Services</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/masjid-services');
    var list = res.services || [];
    var catLabels = {nikkah:'Nikkah',funeral:'Funeral',counselling:'Counselling',quran:'Quran Classes',revert:'Revert Support',ruqyah:'Ruqyah',aqiqah:'Aqiqah',circumcision:'Circumcision',walima:'Walima/Catering',hire:'Venue Hire',imam:'Imam Services',certificate:'Certificates',general:'General'};
    var rows = list.map(function(s) {
        var cat = catLabels[s.category] || s.category;
        var price = s.price_pence > 0 ? '\u00a3'+(s.price_pence/100).toFixed(0) : (s.price_label || 'Free/Contact');
        return '<tr><td><strong>'+esc(s.title)+'</strong></td><td><span class="d-badge d-badge--blue">'+esc(cat)+'</span></td><td>'+price+'</td><td>'+(s.requires_approval?'\u2705':'\u274c')+'</td><td>'+esc(s.availability||'\u2014')+'</td><td><button class="d-btn d-btn--danger d-btn--sm" onclick="delMasjidSvc('+s.id+')">Del</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>\ud83d\udd4c Masjid Services</h1><div style="display:flex;gap:8px"><button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/masjid-services/new\')">+ Add Service</button><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/masjid-service-enquiries\')">View Enquiries</button></div></div>' +
        '<div class="d-alert d-alert--info" style="margin-bottom:16px">These are services your mosque offers to the community (nikkah, funeral, counselling, etc). They appear on your Booking page for congregation members to enquire about.</div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Service</th><th>Category</th><th>Price</th><th>Approval</th><th>Availability</th><th></th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No services listed yet. Add your first masjid service.</p>') +
        '</div>'
    ));
}

async function delMasjidSvc(id) {
    if(!confirm('Delete this service?')) return;
    await api('admin/masjid-services/'+id,{method:'DELETE'});
    toast('Deleted.'); renderMasjidServices();
}

async function renderMasjidServiceForm() {
    if (!mosque) await loadMosque();
    var cats = '<option value="nikkah">Nikkah / Marriage</option><option value="funeral">Funeral / Janazah</option><option value="counselling">Counselling</option><option value="quran">Quran Classes</option><option value="revert">Revert Support</option><option value="ruqyah">Ruqyah</option><option value="aqiqah">Aqiqah</option><option value="circumcision">Circumcision</option><option value="walima">Walima / Catering</option><option value="hire">Venue / Hall Hire</option><option value="imam">Imam Services</option><option value="certificate">Certificates</option><option value="general">General</option>';
    render(shell(
        '<div class="d-header"><h1>Add Masjid Service</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/masjid-services\')">\u2190 Back</button></div>' +
        '<div class="d-card">' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Service Name</label><input id="ms_title" placeholder="e.g. Nikkah Ceremony"></div><div class="d-field"><label>Category</label><select id="ms_cat">'+cats+'</select></div></div>' +
        '<div class="d-field"><label>Description</label><textarea id="ms_desc" rows="3" placeholder="Describe what this service includes, requirements, etc."></textarea></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Price (\u00a3) — leave 0 for free/contact</label><input type="number" id="ms_price" value="0"></div><div class="d-field"><label>Price Label (optional)</label><input id="ms_pricelbl" placeholder="e.g. From \u00a3150, Contact for quote"></div><div class="d-field"><label>Availability</label><input id="ms_avail" placeholder="e.g. Mon-Fri 9am-5pm, By appointment"></div></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Contact Phone</label><input id="ms_phone" placeholder="Direct phone for this service"></div><div class="d-field"><label>Contact Email</label><input id="ms_email" placeholder="Direct email for this service"></div></div>' +
        '<div class="d-field"><label><input type="checkbox" id="ms_approval" checked style="margin-right:6px">Requires masjid approval before confirmation</label></div>' +
        '<button class="d-btn d-btn--primary" id="ms-save" onclick="saveMasjidSvc()"><span class="btn-text">Add Service</span><span class="spinner"></span></button>' +
        '</div>'
    ));
}

async function saveMasjidSvc() {
    btn('#ms-save',true);
    var res = await api('admin/masjid-services',{method:'POST',body:{
        title:$('#ms_title').value,
        category:$('#ms_cat').value,
        description:$('#ms_desc').value,
        price_pence:Math.round(parseFloat($('#ms_price').value||0)*100),
        price_label:$('#ms_pricelbl').value,
        availability:$('#ms_avail').value,
        contact_phone:$('#ms_phone').value,
        contact_email:$('#ms_email').value,
        requires_approval:$('#ms_approval').checked?1:0
    }});
    btn('#ms-save',false);
    if(res.ok){toast('Service added!');navigate('/masjid-services');}
    else toast(res.error||'Failed.','error');
}

async function renderMasjidServiceEnquiries() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Service Enquiries</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/masjid-service-enquiries');
    var list = res.enquiries || [];
    var statusBadge = function(s){if(s==='pending')return'<span class="d-badge d-badge--yellow">Pending</span>';if(s==='confirmed')return'<span class="d-badge d-badge--green">Confirmed</span>';if(s==='declined')return'<span class="d-badge d-badge--red">Declined</span>';return'<span class="d-badge d-badge--gray">'+esc(s)+'</span>';};
    var rows = list.map(function(e) {
        return '<tr><td><strong>'+esc(e.user_name)+'</strong><br><span style="font-size:11px;color:var(--text-dim)">'+esc(e.user_email)+'</span></td><td>'+esc(e.service_title||'\u2014')+'</td><td>'+(e.preferred_date?fmtDate(e.preferred_date):'\u2014')+'</td><td>'+statusBadge(e.status)+'</td><td style="max-width:200px;font-size:12px;">'+esc(e.message||'')+'</td><td><button class="d-btn d-btn--primary d-btn--sm" onclick="confirmEnquiry('+e.id+')">Confirm</button> <button class="d-btn d-btn--danger d-btn--sm" onclick="declineEnquiry('+e.id+')">Decline</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Service Enquiries</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/masjid-services\')">\u2190 Back</button></div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Person</th><th>Service</th><th>Date</th><th>Status</th><th>Message</th><th></th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No enquiries yet.</p>') +
        '</div>'
    ));
}

async function confirmEnquiry(id) {
    await api('admin/masjid-service-enquiries/'+id,{method:'PUT',body:{status:'confirmed'}});
    toast('Confirmed!'); renderMasjidServiceEnquiries();
}
async function declineEnquiry(id) {
    if(!confirm('Decline this enquiry?')) return;
    await api('admin/masjid-service-enquiries/'+id,{method:'PUT',body:{status:'declined'}});
    toast('Declined.'); renderMasjidServiceEnquiries();
}

// ── Madrassah ──
async function renderMadrassah() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Madrassah</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/madrassah/stats');

    render(shell(
        '<div class="d-header"><h1>\ud83d\udcda Madrassah</h1></div>' +
        '<div class="d-grid d-grid-4" style="margin-bottom:20px">' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + (res.total_students||0) + '</div><div class="d-stat__label">Students</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + (res.present_today||0) + '</div><div class="d-stat__label">Present Today</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + (res.unpaid_fees||0) + '</div><div class="d-stat__label">Unpaid Fees</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + (res.current_term ? esc(res.current_term.name) : '\u2014') + '</div><div class="d-stat__label">Current Term</div></div>' +
        '</div>' +
        '<div class="d-grid d-grid-3">' +
        '<div class="d-card" style="text-align:center;cursor:pointer" onclick="navigate(\'/madrassah/students\')"><div style="font-size:32px;margin-bottom:8px;">\ud83d\udc66\ud83d\udc67</div><h3 style="font-size:14px;">Students</h3><p style="font-size:12px;color:var(--text-dim)">Manage enrolments</p></div>' +
        '<div class="d-card" style="text-align:center;cursor:pointer" onclick="navigate(\'/madrassah/attendance\')"><div style="font-size:32px;margin-bottom:8px;">\u2705</div><h3 style="font-size:14px;">Attendance</h3><p style="font-size:12px;color:var(--text-dim)">Mark register</p></div>' +
        '<div class="d-card" style="text-align:center;cursor:pointer" onclick="navigate(\'/madrassah/terms\')"><div style="font-size:32px;margin-bottom:8px;">\ud83d\udcc5</div><h3 style="font-size:14px;">Terms</h3><p style="font-size:12px;color:var(--text-dim)">Academic calendar</p></div>' +
        '</div>' +
        '<div class="d-grid d-grid-3" style="margin-top:16px">' +
        '<div class="d-card" style="text-align:center;cursor:pointer" onclick="navigate(\'/madrassah/reports\')"><div style="font-size:32px;margin-bottom:8px;">\ud83d\udcdd</div><h3 style="font-size:14px;">Reports</h3><p style="font-size:12px;color:var(--text-dim)">Progress & grades</p></div>' +
        '<div class="d-card" style="text-align:center;cursor:pointer" onclick="navigate(\'/madrassah/fees\')"><div style="font-size:32px;margin-bottom:8px;">\ud83d\udcb7</div><h3 style="font-size:14px;">Fees</h3><p style="font-size:12px;color:var(--text-dim)">Payments & invoices</p></div>' +
        '<div class="d-card" style="text-align:center;cursor:pointer" onclick="navigate(\'/classes\')"><div style="font-size:32px;margin-bottom:8px;">\ud83c\udf93</div><h3 style="font-size:14px;">Classes</h3><p style="font-size:12px;color:var(--text-dim)">Manage curriculum</p></div>' +
        '</div>'
    ));
}

async function renderMadStudents() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Students</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/madrassah/students');
    var list = res.students || [];
    var rows = list.map(function(s) {
        return '<tr><td><strong>'+esc(s.child_name)+'</strong></td><td>'+esc(s.year_group||'\u2014')+'</td><td>'+esc(s.class_title||'\u2014')+'</td><td>'+esc(s.parent_name)+'</td><td>'+esc(s.parent_email)+'</td><td>'+esc(s.parent_phone)+'</td><td><button class="d-btn d-btn--danger d-btn--sm" onclick="deleteMadStudent('+s.id+')">Remove</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Students</h1><div style="display:flex;gap:8px"><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah\')">\u2190 Back</button><button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/madrassah/students/new\')">+ Add Student</button></div></div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Child</th><th>Year</th><th>Class</th><th>Parent</th><th>Email</th><th>Phone</th><th></th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No students enrolled yet.</p>') +
        '</div>'
    ));
}

async function deleteMadStudent(id) {
    if (!confirm('Remove this student?')) return;
    await api('admin/madrassah/students/'+id, {method:'DELETE'});
    toast('Student removed.'); renderMadStudents();
}

async function renderMadStudentForm() {
    if (!mosque) await loadMosque();
    var clRes = await api('admin/classes');
    var classes = (clRes.classes||[]).filter(function(c){return c.status==='active';});
    var classOpts = '<option value="">No class assigned</option>' + classes.map(function(c){return '<option value="'+c.id+'">'+esc(c.title)+'</option>';}).join('');

    render(shell(
        '<div class="d-header"><h1>Add Student</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah/students\')">\u2190 Back</button></div>' +
        '<div class="d-card">' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Child Name</label><input id="ms_child" placeholder="e.g. Muhammad Ali"></div><div class="d-field"><label>Date of Birth</label><input type="date" id="ms_dob"></div></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Year Group</label><select id="ms_year"><option value="">Select</option><option>Reception</option><option>Year 1</option><option>Year 2</option><option>Year 3</option><option>Year 4</option><option>Year 5</option><option>Year 6</option><option>Year 7</option><option>Year 8</option><option>Year 9</option><option>Year 10</option></select></div><div class="d-field"><label>Class</label><select id="ms_class">'+classOpts+'</select></div></div>' +
        '<hr style="border:none;border-top:1px solid var(--border);margin:16px 0"><h4 style="margin-bottom:12px;font-size:13px">Parent / Guardian</h4>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Parent Name</label><input id="ms_parent"></div><div class="d-field"><label>Email</label><input type="email" id="ms_email"></div><div class="d-field"><label>Phone</label><input id="ms_phone"></div></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Emergency Contact</label><input id="ms_emerg"></div><div class="d-field"><label>Emergency Phone</label><input id="ms_emergph"></div></div>' +
        '<div class="d-field"><label>Medical Notes</label><textarea id="ms_medical" rows="2" placeholder="Allergies, conditions, etc."></textarea></div>' +
        '<button class="d-btn d-btn--primary" id="ms-save" onclick="saveMadStudent()"><span class="btn-text">Add Student</span><span class="spinner"></span></button>' +
        '</div>'
    ));
}

async function saveMadStudent() {
    btn('#ms-save',true);
    var res = await api('admin/madrassah/students',{method:'POST',body:{
        child_name:$('#ms_child').value, child_dob:$('#ms_dob').value||null,
        year_group:$('#ms_year').value, class_id:parseInt($('#ms_class').value)||0,
        parent_name:$('#ms_parent').value, parent_email:$('#ms_email').value,
        parent_phone:$('#ms_phone').value,
        emergency_contact:$('#ms_emerg').value, emergency_phone:$('#ms_emergph').value,
        medical_notes:$('#ms_medical').value
    }});
    btn('#ms-save',false);
    if(res.ok){toast('Student added!');navigate('/madrassah/students');}
    else toast(res.error||'Failed.','error');
}

async function renderMadAttendance() {
    if (!mosque) await loadMosque();
    var today = new Date().toISOString().split('T')[0];
    render(shell('<div class="d-header"><h1>Attendance</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/madrassah/attendance?date='+today);
    var list = res.attendance || [];

    var rows = list.map(function(s,i) {
        var checked = {present:s.status==='present',absent:s.status==='absent',late:s.status==='late',excused:s.status==='excused'};
        return '<tr><td><strong>'+esc(s.child_name)+'</strong></td><td>'+esc(s.year_group||'')+'</td>' +
            '<td><select id="att_'+s.student_id+'" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border)">' +
            '<option value="present"'+(checked.present?' selected':'')+'>Present</option>' +
            '<option value="absent"'+(checked.absent?' selected':'')+'>Absent</option>' +
            '<option value="late"'+(checked.late?' selected':'')+'>Late</option>' +
            '<option value="excused"'+(checked.excused?' selected':'')+'>Excused</option>' +
            '</select></td><td><input id="attn_'+s.student_id+'" value="'+esc(s.notes)+'" placeholder="Notes" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border);width:120px"></td></tr>';
    }).join('');

    render(shell(
        '<div class="d-header"><h1>Attendance</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah\')">\u2190 Back</button></div>' +
        '<div class="d-card">' +
        '<div class="d-field" style="max-width:200px"><label>Date</label><input type="date" id="att_date" value="'+today+'" onchange="loadAttendance()"></div>' +
        (rows ? '<table class="d-table"><thead><tr><th>Student</th><th>Year</th><th>Status</th><th>Notes</th></tr></thead><tbody>'+rows+'</tbody></table>' +
        '<button class="d-btn d-btn--primary" style="margin-top:16px" id="att-save" onclick="saveAttendance()"><span class="btn-text">Save Attendance</span><span class="spinner"></span></button>'
        : '<p style="color:var(--text-dim)">No students to mark. <a href="#/madrassah/students/new" style="color:var(--primary)">Add students first</a>.</p>') +
        '</div>'
    ));
    window._attStudents = list;
}

async function loadAttendance() {
    var date = $('#att_date').value;
    var res = await api('admin/madrassah/attendance?date='+date);
    window._attStudents = res.attendance || [];
    renderMadAttendance();
}

async function saveAttendance() {
    btn('#att-save',true);
    var records = (window._attStudents||[]).map(function(s){
        var sel = document.getElementById('att_'+s.student_id);
        var note = document.getElementById('attn_'+s.student_id);
        return { student_id: s.student_id, status: sel?sel.value:'present', notes: note?note.value:'' };
    });
    var res = await api('admin/madrassah/attendance',{method:'POST',body:{date:$('#att_date').value,records:records,class_id:0}});
    btn('#att-save',false);
    if(res.ok) toast('Attendance saved! '+res.marked+' records.');
    else toast(res.error||'Failed.','error');
}

async function renderMadTerms() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Terms</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/madrassah/terms');
    var list = res.terms || [];
    var rows = list.map(function(t) {
        var fee = t.fee_pence > 0 ? '\u00a3'+(t.fee_pence/100).toFixed(0)+' '+t.fee_frequency : 'Free';
        var badge = t.status==='active' ? '<span class="d-badge d-badge--green">Active</span>' : '<span class="d-badge d-badge--gray">'+esc(t.status)+'</span>';
        return '<tr><td><strong>'+esc(t.name)+'</strong></td><td>'+fmtDate(t.start_date)+' \u2014 '+fmtDate(t.end_date)+'</td><td>'+fee+'</td><td>'+badge+'</td><td>'+(t.enrolment_open?'\u2705':'\u274c')+'</td><td><button class="d-btn d-btn--secondary d-btn--sm" onclick="genFees('+t.id+')">Generate Fees</button> <button class="d-btn d-btn--danger d-btn--sm" onclick="delTerm('+t.id+')">Del</button></td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Academic Terms</h1><div style="display:flex;gap:8px"><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah\')">\u2190 Back</button><button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/madrassah/terms/new\')">+ New Term</button></div></div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Term</th><th>Dates</th><th>Fee</th><th>Status</th><th>Enrol</th><th></th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No terms created yet.</p>') +
        '</div>'
    ));
}

async function genFees(termId) {
    if(!confirm('Generate fee invoices for all students for this term?')) return;
    var res = await api('admin/madrassah/fees/generate',{method:'POST',body:{term_id:termId}});
    if(res.ok) toast(res.message);
    else toast(res.error||'Failed.','error');
}

async function delTerm(id) {
    if(!confirm('Delete this term?')) return;
    await api('admin/madrassah/terms/'+id,{method:'DELETE'});
    toast('Deleted.'); renderMadTerms();
}

async function renderMadTermForm() {
    if (!mosque) await loadMosque();
    render(shell(
        '<div class="d-header"><h1>New Term</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah/terms\')">\u2190 Back</button></div>' +
        '<div class="d-card">' +
        '<div class="d-field"><label>Term Name</label><input id="mt_name" placeholder="e.g. Autumn 2026"></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Start Date</label><input type="date" id="mt_start"></div><div class="d-field"><label>End Date</label><input type="date" id="mt_end"></div></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Fee (\u00a3)</label><input type="number" id="mt_fee" placeholder="150"></div><div class="d-field"><label>Fee Frequency</label><select id="mt_freq"><option value="termly">Per Term</option><option value="monthly">Monthly</option><option value="annual">Annual</option></select></div></div>' +
        '<button class="d-btn d-btn--primary" id="mt-save" onclick="saveMadTerm()"><span class="btn-text">Create Term</span><span class="spinner"></span></button>' +
        '</div>'
    ));
}

async function saveMadTerm() {
    btn('#mt-save',true);
    var res = await api('admin/madrassah/terms',{method:'POST',body:{
        name:$('#mt_name').value, start_date:$('#mt_start').value, end_date:$('#mt_end').value,
        fee_pence:Math.round(parseFloat($('#mt_fee').value||0)*100),
        fee_frequency:$('#mt_freq').value
    }});
    btn('#mt-save',false);
    if(res.ok){toast('Term created!');navigate('/madrassah/terms');}
    else toast(res.error||'Failed.','error');
}

async function renderMadReports() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Reports</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/madrassah/reports');
    var list = res.reports || [];
    var gradeColor = function(g){if(g==='A'||g==='Excellent')return'green';if(g==='B'||g==='Good')return'blue';if(g==='C'||g==='Satisfactory')return'yellow';return'gray';};
    var rows = list.map(function(r) {
        return '<tr><td><strong>'+esc(r.child_name)+'</strong></td><td>'+esc(r.subject)+'</td><td><span class="d-badge d-badge--'+gradeColor(r.grade)+'">'+esc(r.grade||'\u2014')+'</span></td><td>'+esc(r.quran_progress||'\u2014')+'</td><td>'+esc(r.behaviour)+'</td><td style="font-size:12px;max-width:200px">'+esc(r.teacher_notes||'')+'</td></tr>';
    }).join('');
    render(shell(
        '<div class="d-header"><h1>Student Reports</h1><div style="display:flex;gap:8px"><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah\')">\u2190 Back</button><button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/madrassah/reports/new\')">+ New Report</button></div></div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Student</th><th>Subject</th><th>Grade</th><th>Quran</th><th>Behaviour</th><th>Notes</th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No reports yet.</p>') +
        '</div>'
    ));
}

async function renderMadReportForm() {
    if (!mosque) await loadMosque();
    var stRes = await api('admin/madrassah/students');
    var tmRes = await api('admin/madrassah/terms');
    var students = stRes.students||[];
    var terms = tmRes.terms||[];
    var stOpts = students.map(function(s){return '<option value="'+s.id+'">'+esc(s.child_name)+' ('+esc(s.year_group||'no year')+')</option>';}).join('');
    var tmOpts = terms.map(function(t){return '<option value="'+t.id+'">'+esc(t.name)+'</option>';}).join('');

    render(shell(
        '<div class="d-header"><h1>New Report</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah/reports\')">\u2190 Back</button></div>' +
        '<div class="d-card">' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Student</label><select id="mr_student">'+stOpts+'</select></div><div class="d-field"><label>Term</label><select id="mr_term">'+tmOpts+'</select></div></div>' +
        '<div class="d-grid d-grid-3"><div class="d-field"><label>Subject</label><input id="mr_subject" value="General"></div><div class="d-field"><label>Grade</label><select id="mr_grade"><option>Excellent</option><option>Good</option><option>Satisfactory</option><option>Needs Improvement</option></select></div><div class="d-field"><label>Behaviour</label><select id="mr_behave"><option value="excellent">Excellent</option><option value="good" selected>Good</option><option value="satisfactory">Satisfactory</option><option value="needs_improvement">Needs Improvement</option></select></div></div>' +
        '<div class="d-field"><label>Quran Progress</label><input id="mr_quran" placeholder="e.g. Completed Juz 3, memorising Surah Baqarah"></div>' +
        '<div class="d-field"><label>Teacher Notes</label><textarea id="mr_notes" rows="3" placeholder="Overall observations, areas for improvement..."></textarea></div>' +
        '<button class="d-btn d-btn--primary" id="mr-save" onclick="saveMadReport()"><span class="btn-text">Save Report</span><span class="spinner"></span></button>' +
        '</div>'
    ));
}

async function saveMadReport() {
    btn('#mr-save',true);
    var res = await api('admin/madrassah/reports',{method:'POST',body:{
        student_id:parseInt($('#mr_student').value),
        term_id:parseInt($('#mr_term').value),
        subject:$('#mr_subject').value,
        grade:$('#mr_grade').value,
        behaviour:$('#mr_behave').value,
        quran_progress:$('#mr_quran').value,
        teacher_notes:$('#mr_notes').value
    }});
    btn('#mr-save',false);
    if(res.ok){toast('Report saved!');navigate('/madrassah/reports');}
    else toast(res.error||'Failed.','error');
}

async function renderMadFees() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Fees</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/madrassah/fees');
    var list = res.fees || [];
    var statusBadge = function(s){if(s==='paid')return'<span class="d-badge d-badge--green">Paid</span>';if(s==='unpaid')return'<span class="d-badge d-badge--yellow">Unpaid</span>';return'<span class="d-badge d-badge--gray">'+esc(s)+'</span>';};
    var rows = list.map(function(f) {
        return '<tr><td><strong>'+esc(f.child_name)+'</strong></td><td>'+esc(f.parent_name)+'</td><td>'+esc(f.term_name||'\u2014')+'</td><td>\u00a3'+(f.amount_pence/100).toFixed(2)+'</td><td>'+statusBadge(f.status)+'</td><td>'+(f.paid_at?fmtDate(f.paid_at):(f.due_date?'Due '+fmtDate(f.due_date):'\u2014'))+'</td></tr>';
    }).join('');

    var paidGBP = ((res.paid_pence||0)/100).toFixed(2);
    var outGBP = ((res.outstanding_pence||0)/100).toFixed(2);

    render(shell(
        '<div class="d-header"><h1>Madrassah Fees</h1><button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/madrassah\')">\u2190 Back</button></div>' +
        '<div class="d-grid d-grid-3" style="margin-bottom:16px">' +
        '<div class="d-card d-stat"><div class="d-stat__num">'+(res.paid_count||0)+'</div><div class="d-stat__label">Fees Paid</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">\u00a3'+paidGBP+'</div><div class="d-stat__label">Collected</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">\u00a3'+outGBP+'</div><div class="d-stat__label">Outstanding</div></div>' +
        '</div>' +
        '<div class="d-card">' +
        (rows ? '<table class="d-table"><thead><tr><th>Student</th><th>Parent</th><th>Term</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>'+rows+'</tbody></table>'
        : '<p style="color:var(--text-dim)">No fees generated yet. <a href="#/madrassah/terms" style="color:var(--primary)">Create a term</a> and generate fees.</p>') +
        '</div>'
    ));
}

async function renderPatrons() {
    if (!mosque) await loadMosque();
    render(shell('<div class="d-header"><h1>Patrons</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('admin/patrons');
    var list = res.patrons || [];
    var totalActive = res.total_active || 0;
    var monthlyPence = res.monthly_pence || 0;
    var monthlyGBP = (monthlyPence / 100).toFixed(2);
    var yearlyGBP = (monthlyPence * 12 / 100).toFixed(2);

    var tierBadge = function(t) {
        if (t === 'champion') return '<span class="d-badge d-badge--green">Champion</span>';
        if (t === 'guardian') return '<span class="d-badge d-badge--blue">Guardian</span>';
        return '<span class="d-badge d-badge--gray">Supporter</span>';
    };
    var statusBadge = function(s) {
        if (s === 'active') return '<span class="d-badge d-badge--green">Active</span>';
        if (s === 'cancelled') return '<span class="d-badge d-badge--red">Cancelled</span>';
        if (s === 'payment_failed') return '<span class="d-badge d-badge--yellow">Payment Failed</span>';
        return '<span class="d-badge d-badge--gray">' + esc(s) + '</span>';
    };

    var rows = list.map(function(p) {
        return '<tr><td><strong>' + esc(p.user_name) + '</strong><br><span style="font-size:11px;color:var(--text-dim)">' + esc(p.user_email) + '</span></td><td>' + tierBadge(p.tier) + '</td><td>\u00a3' + (p.amount_pence/100).toFixed(2) + '/mo</td><td>' + statusBadge(p.status) + '</td><td>' + fmtDate(p.started_at) + '</td></tr>';
    }).join('');

    render(shell(
        '<div class="d-header"><h1>Patrons</h1></div>' +
        '<div class="d-grid d-grid-3" style="margin-bottom:20px">' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + totalActive + '</div><div class="d-stat__label">Active Patrons</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">\u00a3' + monthlyGBP + '</div><div class="d-stat__label">Monthly Revenue</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">\u00a3' + yearlyGBP + '</div><div class="d-stat__label">Projected Yearly</div></div>' +
        '</div>' +
        '<div class="d-card">' +
        '<h3 style="margin-bottom:12px">All Patrons</h3>' +
        (rows ? '<table class="d-table"><thead><tr><th>Member</th><th>Tier</th><th>Amount</th><th>Status</th><th>Since</th></tr></thead><tbody>' + rows + '</tbody></table>'
        : '<p style="color:var(--text-dim)">No patrons yet. Share the patron page with your congregation to start receiving monthly support.</p>') +
        '</div>' +
        '<div class="d-card" style="margin-top:16px"><h3 style="margin-bottom:8px">Share Patron Page</h3><p style="color:var(--text-dim);font-size:13px">Members can become patrons at:</p><p style="margin-top:8px"><a href="/mosque/' + esc(mosque?.slug||'') + '/patron" target="_blank" style="color:var(--primary);font-weight:700">yourjannah.com/mosque/' + esc(mosque?.slug||'') + '/patron</a></p></div>'
    ));
}

async function renderSettings() {
    if (!mosque) await loadMosque();
    render(shell(
        '<div class="d-header"><h1>Settings</h1></div>' +
        '<div class="d-grid d-grid-2"><div>' +
        '<div class="d-card"><h3 style="margin-bottom:16px">Mosque Profile</h3>' +
        '<div class="d-field"><label>Name</label><input type="text" id="s_name" value="' + esc(mosque?.name||'') + '"></div>' +
        '<div class="d-field"><label>Address</label><input type="text" id="s_address" value="' + esc(mosque?.address||'') + '"></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>City</label><input type="text" id="s_city" value="' + esc(mosque?.city||'') + '"></div><div class="d-field"><label>Postcode</label><input type="text" id="s_postcode" value="' + esc(mosque?.postcode||'') + '"></div></div>' +
        '<div class="d-grid d-grid-2"><div class="d-field"><label>Phone</label><input type="text" id="s_phone" value="' + esc(mosque?.phone||'') + '"></div><div class="d-field"><label>Website</label><input type="text" id="s_website" value="' + esc(mosque?.website||'') + '"></div></div>' +
        '<div class="d-field"><label>Description</label><textarea id="s_desc" rows="3">' + esc(mosque?.description||'') + '</textarea></div>' +
        '<button class="d-btn d-btn--primary" id="s-save" onclick="saveSettings()"><span class="btn-text">Save Profile</span><span class="spinner"></span></button>' +
        '</div></div>' +
        '<div><div class="d-card"><h3>Mosque Page</h3><p style="margin-top:8px"><a href="/mosque/' + esc(mosque?.slug||'') + '" target="_blank" style="color:var(--primary);font-weight:700">yourjannah.com/mosque/' + esc(mosque?.slug||'') + '</a></p></div></div></div>'
    ));
}

async function saveSettings() {
    btn('#s-save', true);
    var res = await api('admin/me', { method: 'PUT', body: {
        name: $('#s_name')?.value, address: $('#s_address')?.value,
        city: $('#s_city')?.value, postcode: $('#s_postcode')?.value,
        phone: $('#s_phone')?.value, website: $('#s_website')?.value,
        description: $('#s_desc')?.value
    }});
    btn('#s-save', false);
    if (res.ok) { await loadMosque(); toast('Saved!'); }
    else toast(res.error || 'Failed.', 'error');
}

// ── Init ──
async function init() {
    if (token) { await loadMosque(); if (!mosque) { logout(); return; } }
    window.addEventListener('hashchange', route);
    route();
}
init();
</script>
</body>
</html>
        <?php
        exit;
    }
}
