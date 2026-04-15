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
        { path: '/enquiries', icon: '\u2709\ufe0f', label: 'Enquiries' },
        { path: '/subscribers', icon: '\ud83d\udc65', label: 'Subscribers' },
        { path: '/classes', icon: '\ud83c\udf93', label: 'Classes' },
        { path: '/campaigns', icon: '\u2764\ufe0f', label: 'Fundraising' },
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

    var memberCount = members.count || 0;
    var subCount = subs.total || (subs.subscribers||[]).length;
    var enqCount = (enquiries.enquiries||[]).length;
    var bookCount = (bookings.bookings||[]).length;
    var campCount = (campaigns.campaigns||[]).length;

    render(shell(
        '<div class="d-header"><h1>Dashboard</h1></div>' +
        '<div class="d-grid d-grid-4" style="margin-bottom:20px">' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + memberCount + '</div><div class="d-stat__label">Members on YourJannah</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + subCount + '</div><div class="d-stat__label">Push Subscribers</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + enqCount + '</div><div class="d-stat__label">New Enquiries</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + bookCount + '</div><div class="d-stat__label">Bookings</div></div>' +
        '</div>' +
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

// ── Prayer Times ──
async function renderPrayers() {
    if (!mosque) await loadMosque();
    var today = new Date().toISOString().split('T')[0];
    render(shell('<div class="d-header"><h1>Prayer Times</h1></div><div class="d-card">Loading...</div>'));
    var res = await api('mosques/' + mosque.id + '/prayers?date=' + today);
    var times = res.times || {};
    var labels = {fajr:'Fajr',dhuhr:'Dhuhr',asr:'Asr',maghrib:'Maghrib',isha:'Isha'};
    var rows = Object.entries(labels).map(function(e) {
        var k=e[0],v=e[1]; return '<tr><td><strong>'+v+'</strong></td><td>'+fmtTime(times[k])+'</td><td><input type="time" id="jt_'+k+'" value="'+(times[k+'_jamat']?times[k+'_jamat'].substring(0,5):'')+'" style="width:120px"></td></tr>';
    }).join('');

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
        '<div class="d-header"><h1>Prayer Times</h1></div>' +

        // Jamat overrides
        '<div class="d-card"><h3 style="margin-bottom:12px">Jamat Time Overrides</h3><p style="margin-bottom:16px;color:var(--text-dim);font-size:13px">Set jamat times for a specific date. Adhan times are calculated automatically.</p>' +
        '<table class="d-table"><thead><tr><th>Prayer</th><th>Adhan</th><th>Jamat Time</th></tr></thead><tbody>'+rows+'</tbody></table>' +
        '<div class="d-grid d-grid-2" style="margin-top:16px"><div class="d-field"><label>Date</label><input type="date" id="pt_date" value="'+today+'"></div><div style="display:flex;align-items:end;gap:8px"><button class="d-btn d-btn--primary" id="pt-save" onclick="savePrayers()"><span class="btn-text">Save for Date</span><span class="spinner"></span></button><button class="d-btn d-btn--secondary" onclick="bulkApply()">Apply to All Month</button></div></div></div>' +

        // Jumu'ah
        '<div class="d-card"><h3 style="margin-bottom:12px">Jumu\'ah Slots</h3>' +
        (jRows ? '<table class="d-table"><thead><tr><th>Slot</th><th>Khutbah</th><th>Salah</th><th>Language</th><th></th></tr></thead><tbody>'+jRows+'</tbody></table>' : '<p style="color:var(--text-dim)">No Jumu\'ah slots.</p>') +
        '<div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px"><h4 style="margin-bottom:8px;font-size:13px">Add Jumu\'ah Slot</h4>' +
        '<div class="d-grid d-grid-4"><div class="d-field"><label>Slot Name</label><input id="jm_name" placeholder="First Jumu\'ah"></div><div class="d-field"><label>Khutbah</label><input type="time" id="jm_khutbah"></div><div class="d-field"><label>Salah</label><input type="time" id="jm_salah"></div><div class="d-field"><label>Language</label><select id="jm_lang"><option>English</option><option>Arabic</option><option>Urdu</option><option>Bilingual</option></select></div></div>' +
        '<button class="d-btn d-btn--primary d-btn--sm" onclick="addJumuah()">Add Slot</button></div></div>' +

        // Eid
        '<div class="d-card"><h3 style="margin-bottom:12px">Eid Times — ' + new Date().getFullYear() + '</h3>' +
        (eRows ? '<table class="d-table"><thead><tr><th>Eid</th><th>Slot</th><th>Time</th><th>Location</th></tr></thead><tbody>'+eRows+'</tbody></table>' : '<p style="color:var(--text-dim)">No Eid times set.</p>') +
        '<div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px"><h4 style="margin-bottom:8px;font-size:13px">Add Eid Slot</h4>' +
        '<div class="d-grid d-grid-4"><div class="d-field"><label>Type</label><select id="eid_type"><option value="eid_ul_fitr">Eid ul-Fitr</option><option value="eid_ul_adha">Eid ul-Adha</option></select></div><div class="d-field"><label>Slot Name</label><input id="eid_name" placeholder="First Prayer"></div><div class="d-field"><label>Time</label><input type="time" id="eid_time"></div><div class="d-field"><label>Location</label><input id="eid_loc" placeholder="Main Hall"></div></div>' +
        '<button class="d-btn d-btn--primary d-btn--sm" onclick="addEid()">Add Eid Slot</button></div></div>'
    ));
}

async function savePrayers() {
    btn('#pt-save',true);
    var times={};['fajr','dhuhr','asr','maghrib','isha'].forEach(function(k){var v=$('#jt_'+k);if(v&&v.value)times[k+'_jamat']=v.value+':00';});
    var res=await api('admin/prayers',{method:'PUT',body:{date:$('#pt_date').value,times:times}});
    btn('#pt-save',false);
    if(res.ok)toast('Jamat times saved!');else toast(res.error||'Failed.','error');
}

async function bulkApply() {
    if(!confirm('Apply current jamat times to every day this month?'))return;
    var times={};['fajr','dhuhr','asr','maghrib','isha'].forEach(function(k){var v=$('#jt_'+k);if(v&&v.value)times[k+'_jamat']=v.value+':00';});
    var dt=$('#pt_date').value;var ym=dt.substring(0,7);
    var daysInMonth=new Date(parseInt(ym.split('-')[0]),parseInt(ym.split('-')[1]),0).getDate();
    var dates=[];for(var d=1;d<=daysInMonth;d++){dates.push({date:ym+'-'+String(d).padStart(2,'0'),times:times});}
    var res=await api('admin/prayers/bulk',{method:'PUT',body:{dates:dates}});
    if(res.ok)toast(res.message||'Month updated!');else toast(res.error||'Failed.','error');
}

async function addJumuah() {
    var res=await api('admin/jumuah',{method:'POST',body:{slot_name:$('#jm_name').value,khutbah_time:$('#jm_khutbah').value+':00',salah_time:$('#jm_salah').value+':00',language:$('#jm_lang').value}});
    if(res.ok){toast('Jumu\'ah slot added!');renderPrayers();}else toast(res.error||'Failed.','error');
}
async function deleteJumuah(id){if(!confirm('Delete this slot?'))return;await api('admin/jumuah/'+id,{method:'DELETE'});toast('Deleted.');renderPrayers();}

async function addEid() {
    // Eid uses a direct DB insert via admin endpoint — we need to add this
    // For now, use the existing admin API pattern
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
