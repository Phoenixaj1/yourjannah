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

    render(shell(
        '<div class="d-header"><h1>Dashboard</h1></div>' +
        '<div class="d-grid d-grid-3" style="margin-bottom:20px">' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + ((subs.subscribers||[]).length) + '</div><div class="d-stat__label">Subscribers</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + ((enquiries.enquiries||[]).length) + '</div><div class="d-stat__label">New Enquiries</div></div>' +
        '<div class="d-card d-stat"><div class="d-stat__num">' + esc(mosque?.city || '') + '</div><div class="d-stat__label">Location</div></div>' +
        '</div>' +
        '<div class="d-card"><h3 style="margin-bottom:12px">Quick Actions</h3>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
        '<button class="d-btn d-btn--primary d-btn--sm" onclick="navigate(\'/announcements\')">New Announcement</button>' +
        '<button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/prayers\')">Update Prayer Times</button>' +
        '<button class="d-btn d-btn--secondary d-btn--sm" onclick="navigate(\'/events\')">Add Event</button>' +
        '</div></div>'
    ));
}

// ── Placeholder pages ──
async function renderPrayers() { render(shell('<div class="d-header"><h1>Prayer Times</h1></div><div class="d-card"><p>Prayer time management coming in next update.</p></div>')); }
async function renderAnnouncements() { render(shell('<div class="d-header"><h1>Announcements</h1></div><div class="d-card"><p>Announcement management coming in next update.</p></div>')); }
async function renderEvents() { render(shell('<div class="d-header"><h1>Events</h1></div><div class="d-card"><p>Event management coming in next update.</p></div>')); }
async function renderBookings() { render(shell('<div class="d-header"><h1>Bookings</h1></div><div class="d-card"><p>Booking management coming in next update.</p></div>')); }
async function renderRooms() { render(shell('<div class="d-header"><h1>Rooms</h1></div><div class="d-card"><p>Room management coming in next update.</p></div>')); }
async function renderEnquiries() { render(shell('<div class="d-header"><h1>Enquiries</h1></div><div class="d-card"><p>Enquiry management coming in next update.</p></div>')); }
async function renderSubscribers() { render(shell('<div class="d-header"><h1>Subscribers</h1></div><div class="d-card"><p>Subscriber management coming in next update.</p></div>')); }
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
