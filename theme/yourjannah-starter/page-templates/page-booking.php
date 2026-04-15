<?php
/**
 * Template: Booking Page
 *
 * Masjid Services + Rooms tabs, booking modal, enquiry modal.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<style>
.ynj-book-tabs{display:flex;gap:0;margin-bottom:16px;background:rgba(255,255,255,.6);border-radius:12px;padding:4px;border:1px solid rgba(0,0,0,.06);}
.ynj-book-tab{flex:1;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;text-align:center;cursor:pointer;border:none;background:transparent;color:#6b8fa3;transition:all .15s;}
.ynj-book-tab--active{background:#00ADEF;color:#fff;box-shadow:0 2px 8px rgba(0,173,239,.2);}
.ynj-room-card{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:14px;border:1px solid rgba(255,255,255,.6);padding:18px;margin-bottom:12px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.ynj-room-card h3{font-size:16px;font-weight:700;margin-bottom:4px;}
.ynj-room-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:8px 0 12px;}
.ynj-room-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:6px;}
.ynj-room-badge--cap{background:#e8f4f8;color:#00ADEF;}
.ynj-room-badge--price{background:#dcfce7;color:#166534;font-size:14px;}
.ynj-room-badge--free{background:#f0fdf4;color:#166534;}
.ynj-room-photo{width:100%;height:120px;border-radius:10px;object-fit:cover;margin-bottom:10px;background:#e8f4f8;}
.ynj-svc-card{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:14px;border:1px solid rgba(255,255,255,.6);padding:18px;margin-bottom:12px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.ynj-svc-card h3{font-size:15px;font-weight:700;margin-bottom:3px;}
.ynj-svc-type{display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:2px 8px;border-radius:6px;background:#ede9fe;color:#7c3aed;margin-bottom:6px;}
.ynj-svc-detail{display:flex;align-items:center;gap:6px;font-size:13px;color:#6b8fa3;margin-top:4px;}
.ynj-svc-detail svg{width:14px;height:14px;flex-shrink:0;color:#00ADEF;}
.ynj-book-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;background:#00ADEF;color:#fff;transition:all .15s;}
.ynj-book-btn:hover{opacity:.9;}
.ynj-book-btn--outline{background:transparent;border:1px solid #ddd;color:#0a1628;}
.ynj-share-check{display:flex;align-items:center;gap:8px;margin-top:10px;padding:10px;background:#f0fdf4;border-radius:8px;font-size:12px;color:#166534;}
.ynj-share-check input{width:16px;height:16px;accent-color:#00ADEF;}
</style>

<main class="ynj-main">
    <?php if ( isset( $_GET['payment'] ) && $_GET['payment'] === 'success' ) : ?>
        <section class="ynj-room-card" style="text-align:center;padding:40px 20px;">
            <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
            <h2 style="margin-bottom:8px;"><?php esc_html_e( 'Booking Submitted!', 'yourjannah' ); ?></h2>
            <p class="ynj-text-muted"><?php esc_html_e( "Your booking requires masjid approval. You'll be notified once confirmed.", 'yourjannah' ); ?></p>
        </section>
    <?php else : ?>
    <h2 id="bk-title" style="font-size:18px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Booking', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:14px;"><?php esc_html_e( 'Book masjid services and rooms — all bookings require masjid approval', 'yourjannah' ); ?></p>

    <div class="ynj-book-tabs">
        <button class="ynj-book-tab ynj-book-tab--active" id="tab-msvc" onclick="switchBookTab('msvc')">🕌 <?php esc_html_e( 'Masjid Services', 'yourjannah' ); ?></button>
        <button class="ynj-book-tab" id="tab-rooms" onclick="switchBookTab('rooms')">🏠 <?php esc_html_e( 'Rooms', 'yourjannah' ); ?></button>
    </div>

    <div id="msvc-panel">
        <div id="msvc-list" style="display:grid;grid-template-columns:1fr;gap:14px;"><p class="ynj-text-muted" style="text-align:center;padding:20px;grid-column:1/-1;">Loading masjid services...</p></div>
    </div>

    <div id="rooms-panel" style="display:none;">
        <div id="rooms-list" style="display:grid;grid-template-columns:1fr;gap:14px;"><p class="ynj-text-muted" style="text-align:center;padding:20px;grid-column:1/-1;">Loading rooms...</p></div>
    </div>
    <style>@media(min-width:700px){#rooms-list,#msvc-list{grid-template-columns:1fr 1fr !important;}}</style>

    <!-- Room Booking Modal -->
    <div class="ynj-modal" id="booking-modal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
        <div class="ynj-modal__content">
            <h3 id="modal-room-name" style="margin-bottom:12px;"><?php esc_html_e( 'Book Room', 'yourjannah' ); ?></h3>
            <form id="room-booking-form" class="ynj-form">
                <input type="hidden" name="room_id" id="modal-room-id">
                <div class="ynj-field"><label><?php esc_html_e( 'Date', 'yourjannah' ); ?> *</label><input type="date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>"></div>
                <div class="ynj-field-row">
                    <div class="ynj-field"><label><?php esc_html_e( 'Start Time', 'yourjannah' ); ?> *</label><input type="time" name="start_time" required></div>
                    <div class="ynj-field"><label><?php esc_html_e( 'End Time', 'yourjannah' ); ?> *</label><input type="time" name="end_time" required></div>
                </div>
                <div class="ynj-field"><label><?php esc_html_e( 'Your Name', 'yourjannah' ); ?> *</label><input type="text" name="user_name" required></div>
                <div class="ynj-field-row">
                    <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?> *</label><input type="email" name="user_email" required></div>
                    <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="user_phone"></div>
                </div>
                <div class="ynj-field"><label><?php esc_html_e( 'Purpose / Notes', 'yourjannah' ); ?></label><textarea name="notes" rows="2" placeholder="<?php esc_attr_e( 'What is this booking for?', 'yourjannah' ); ?>"></textarea></div>
                <div class="ynj-share-check">
                    <input type="checkbox" name="share_to_feed" id="share-check" checked>
                    <label for="share-check"><?php esc_html_e( 'Share this event with the mosque community (visible on the feed after approval)', 'yourjannah' ); ?></label>
                </div>
            </form>
            <p id="modal-price" class="ynj-text-muted" style="margin:12px 0;"></p>
            <p class="ynj-text-muted" style="font-size:11px;margin-bottom:8px;"><?php esc_html_e( 'All bookings require masjid approval before confirmation.', 'yourjannah' ); ?></p>
            <div style="display:flex;gap:8px;">
                <button class="ynj-book-btn" id="modal-submit" type="button" style="flex:1;justify-content:center;"><?php esc_html_e( 'Submit Booking Request', 'yourjannah' ); ?></button>
                <button class="ynj-book-btn ynj-book-btn--outline" type="button" onclick="document.getElementById('booking-modal').style.display='none'"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></button>
            </div>
            <p class="ynj-text-muted" id="modal-error" style="margin-top:8px;color:#dc2626;"></p>
        </div>
    </div>

    <!-- Service Enquiry Modal -->
    <div class="ynj-modal" id="svc-enquiry-modal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
        <div class="ynj-modal__content">
            <h3 id="svc-modal-title" style="margin-bottom:12px;"><?php esc_html_e( 'Enquire', 'yourjannah' ); ?></h3>
            <form id="svc-enquiry-form" class="ynj-form">
                <input type="hidden" name="service_id" id="svc-modal-id">
                <div class="ynj-field"><label><?php esc_html_e( 'Your Name', 'yourjannah' ); ?> *</label><input type="text" name="name" required></div>
                <div class="ynj-field-row">
                    <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?> *</label><input type="email" name="email" required></div>
                    <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="phone"></div>
                </div>
                <div class="ynj-field"><label><?php esc_html_e( 'Preferred Date', 'yourjannah' ); ?></label><input type="date" name="preferred_date" min="<?php echo date('Y-m-d'); ?>"></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Message / Details', 'yourjannah' ); ?></label><textarea name="message" rows="3" placeholder="<?php esc_attr_e( 'Any specific requirements or questions?', 'yourjannah' ); ?>"></textarea></div>
            </form>
            <div style="display:flex;gap:8px;">
                <button class="ynj-book-btn" id="svc-modal-submit" type="button" style="flex:1;justify-content:center;"><?php esc_html_e( 'Send Enquiry', 'yourjannah' ); ?></button>
                <button class="ynj-book-btn ynj-book-btn--outline" type="button" onclick="document.getElementById('svc-enquiry-modal').style.display='none'"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></button>
            </div>
            <p class="ynj-text-muted" id="svc-modal-error" style="margin-top:8px;color:#dc2626;"></p>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php if ( ! isset( $_GET['payment'] ) ) : ?>
<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;
    let mosqueId = 0;

    document.querySelectorAll('[data-nav-mosque]').forEach(el => {
        el.href = el.dataset.navMosque.replace('{slug}', slug);
    });

    var svcIcons = {nikkah:'\ud83d\udc8d',funeral:'\ud83d\udd4a\ufe0f',counselling:'\ud83e\udd1d',quran:'\ud83d\udcd6',revert:'\ud83d\udd4c',ruqyah:'\ud83e\udd32',aqiqah:'\ud83d\udc11',walima:'\ud83c\udf7d\ufe0f',hire:'\ud83c\udfe0',imam:'\ud83d\udd4c',certificate:'\ud83d\udcdc',circumcision:'\ud83c\udfe5',general:'\ud83d\udd4c'};

    window.switchBookTab = function(tab) {
        document.getElementById('tab-msvc').classList.toggle('ynj-book-tab--active', tab === 'msvc');
        document.getElementById('tab-rooms').classList.toggle('ynj-book-tab--active', tab === 'rooms');
        document.getElementById('msvc-panel').style.display = tab === 'msvc' ? '' : 'none';
        document.getElementById('rooms-panel').style.display = tab === 'rooms' ? '' : 'none';
    };

    function renderMasjidServices(services) {
        var el = document.getElementById('msvc-list');
        if (!services.length) {
            el.innerHTML = '<div style="text-align:center;padding:30px 20px;grid-column:1/-1;"><div style="font-size:40px;margin-bottom:8px;">\ud83d\udd4c</div><h3 style="font-size:15px;">No Services Listed Yet</h3><p class="ynj-text-muted">This mosque hasn\'t added their bookable services yet.</p></div>';
            return;
        }
        el.innerHTML = services.map(function(s) {
            var icon = svcIcons[s.category] || '\ud83d\udd4c';
            var price = s.price_pence > 0 ? '\u00a3'+(s.price_pence/100).toFixed(0) : (s.price_label || 'Free / Contact');
            return '<div class="ynj-room-card">' +
                '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">' +
                '<div style="width:44px;height:44px;border-radius:12px;background:#e8f4f8;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">' + icon + '</div>' +
                '<div><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#00ADEF;margin-bottom:2px;">' + (s.category||'service').replace(/_/g,' ') + '</div>' +
                '<h3 style="font-size:15px;font-weight:700;margin:0;">' + s.title + '</h3></div></div>' +
                (s.description ? '<p class="ynj-text-muted" style="margin:6px 0;line-height:1.4;font-size:13px;">' + s.description + '</p>' : '') +
                '<div style="display:flex;gap:6px;flex-wrap:wrap;margin:8px 0;">' +
                '<span class="ynj-room-badge ynj-room-badge--price">' + price + '</span>' +
                (s.availability ? '<span style="font-size:12px;color:#6b8fa3;background:#f0f4f8;padding:3px 10px;border-radius:6px;">\ud83d\udcc5 ' + s.availability + '</span>' : '') +
                (s.requires_approval ? '<span style="font-size:11px;color:#92400e;background:#fef3c7;padding:2px 8px;border-radius:6px;">\u23f3 Requires approval</span>' : '') +
                '</div>' +
                '<button class="ynj-book-btn" onclick="enquireMasjidSvc('+s.id+',\''+s.title.replace(/'/g,"\\'")+'\')">\ud83d\udcdd Enquire / Book</button>' +
                '</div>';
        }).join('');
    }

    // Load mosque info + services + rooms
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            mosqueId = m.id;
            document.getElementById('bk-title').textContent = (m.name || 'Mosque') + ' Booking';

            // Load masjid services
            fetch(API + 'mosques/' + slug + '/masjid-services')
                .then(r => r.ok ? r.json() : { services: [] })
                .then(data => renderMasjidServices(data.services || []))
                .catch(() => { document.getElementById('msvc-list').innerHTML = '<p class="ynj-text-muted" style="grid-column:1/-1">Could not load services.</p>'; });

            // Load rooms
            fetch(API + 'mosques/' + m.id + '/rooms')
                .then(r => r.ok ? r.json() : { rooms: [] })
                .then(data => renderRooms(data.rooms || []))
                .catch(() => { document.getElementById('rooms-list').innerHTML = '<p class="ynj-text-muted" style="grid-column:1/-1">Could not load rooms.</p>'; });
        })
        .catch(() => {});

    function renderRooms(rooms) {
        const el = document.getElementById('rooms-list');
        if (!rooms.length) {
            el.innerHTML = '<div style="text-align:center;padding:30px 20px;"><div style="font-size:40px;margin-bottom:8px;">🏠</div><h3 style="font-size:15px;">No Rooms Listed</h3><p class="ynj-text-muted">This mosque hasn\'t listed any rooms yet.</p></div>';
            return;
        }
        el.innerHTML = rooms.map(function(r) {
            var hourly = r.hourly_rate_pence > 0 ? '\u00a3' + (r.hourly_rate_pence/100).toFixed(0) + '/hr' : '';
            var daily = r.daily_rate_pence > 0 ? '\u00a3' + (r.daily_rate_pence/100).toFixed(0) + '/day' : '';
            var isFree = !r.hourly_rate_pence && !r.daily_rate_pence;
            var photo = r.photo_url ? '<img class="ynj-room-photo" src="' + r.photo_url + '" alt="' + r.name + '">' : '';
            return '<div class="ynj-room-card">' + photo +
                '<h3>' + r.name + '</h3>' +
                (r.description ? '<p class="ynj-text-muted" style="margin-bottom:8px;">' + r.description + '</p>' : '') +
                '<div class="ynj-room-meta">' +
                '<span class="ynj-room-badge ynj-room-badge--cap">👥 ' + r.capacity + ' capacity</span>' +
                (isFree ? '<span class="ynj-room-badge ynj-room-badge--free">Free</span>' : '') +
                (hourly ? '<span class="ynj-room-badge ynj-room-badge--price">' + hourly + '</span>' : '') +
                (daily ? '<span style="font-size:12px;color:#6b8fa3;">' + daily + '</span>' : '') +
                '</div>' +
                (r.availability_notes ? '<p style="font-size:12px;color:#6b8fa3;margin-bottom:8px;">\ud83d\udcc5 ' + r.availability_notes + '</p>' : '') +
                '<button class="ynj-book-btn" onclick="openBooking(' + r.id + ',\'' + r.name.replace(/'/g, "\\'") + '\',' + (r.hourly_rate_pence||0) + ')">Book This Room</button>' +
                '</div>';
        }).join('');
    }

    window.openBooking = function(roomId, roomName, hourlyRate) {
        document.getElementById('modal-room-id').value = roomId;
        document.getElementById('modal-room-name').textContent = 'Book: ' + roomName;
        document.getElementById('modal-price').textContent = hourlyRate > 0
            ? 'Rate: \u00a3' + (hourlyRate/100).toFixed(0) + '/hour \u2014 payment after masjid approval'
            : 'This room is free to book.';
        document.getElementById('booking-modal').style.display = '';
        document.getElementById('modal-error').textContent = '';
    };

    document.getElementById('modal-submit').addEventListener('click', async function() {
        const btn = this;
        const form = document.getElementById('room-booking-form');
        const roomId = form.querySelector('[name="room_id"]').value;
        const date = form.querySelector('[name="booking_date"]').value;
        const start = form.querySelector('[name="start_time"]').value;
        const end = form.querySelector('[name="end_time"]').value;
        const name = form.querySelector('[name="user_name"]').value.trim();
        const email = form.querySelector('[name="user_email"]').value.trim();

        if (!date || !start || !end || !name || !email) {
            document.getElementById('modal-error').textContent = 'Please fill in all required fields.';
            return;
        }

        const [sh,sm] = start.split(':').map(Number);
        const [eh,em] = end.split(':').map(Number);
        const hours = Math.max(1, Math.ceil(((eh*60+em) - (sh*60+sm)) / 60));

        btn.disabled = true; btn.textContent = 'Submitting...';

        try {
            const resp = await fetch(API + 'stripe/checkout/room', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    room_id: parseInt(roomId), hours: hours,
                    booking_date: date, start_time: start+':00', end_time: end+':00',
                    user_name: name, user_email: email,
                    user_phone: form.querySelector('[name="user_phone"]').value.trim(),
                    notes: form.querySelector('[name="notes"]').value.trim(),
                    share_to_feed: document.getElementById('share-check').checked
                })
            });
            const data = await resp.json();
            if (data.ok && data.checkout_url) { window.location.href = data.checkout_url; }
            else if (data.ok && data.free) { window.location.href = <?php echo wp_json_encode( home_url( '/mosque/' . $slug . '/rooms?payment=success' ) ); ?>; }
            else { document.getElementById('modal-error').textContent = data.error || 'Booking failed.'; btn.disabled = false; btn.textContent = 'Submit Booking Request'; }
        } catch(e) { document.getElementById('modal-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Submit Booking Request'; }
    });

    // Service enquiry
    window.enquireMasjidSvc = function(svcId, svcTitle) {
        document.getElementById('svc-modal-id').value = svcId;
        document.getElementById('svc-modal-title').textContent = 'Enquire: ' + svcTitle;
        document.getElementById('svc-enquiry-modal').style.display = '';
        document.getElementById('svc-modal-error').textContent = '';
    };

    document.getElementById('svc-modal-submit').addEventListener('click', async function() {
        var btn = this;
        var form = document.getElementById('svc-enquiry-form');
        var svcId = form.querySelector('[name="service_id"]').value;
        var name = form.querySelector('[name="name"]').value.trim();
        var email = form.querySelector('[name="email"]').value.trim();
        if (!name || !email) { document.getElementById('svc-modal-error').textContent = 'Name and email required.'; return; }

        btn.disabled = true; btn.textContent = 'Sending...';
        try {
            var resp = await fetch(API + 'masjid-services/' + svcId + '/enquire', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    name: name, email: email,
                    phone: form.querySelector('[name="phone"]').value.trim(),
                    preferred_date: form.querySelector('[name="preferred_date"]').value,
                    message: form.querySelector('[name="message"]').value.trim()
                })
            });
            var data = await resp.json();
            if (data.ok) {
                document.getElementById('svc-enquiry-modal').style.display = 'none';
                var successEl = document.createElement('div');
                successEl.className = 'ynj-card';
                successEl.style.cssText = 'text-align:center;padding:24px 20px;margin-bottom:14px;';
                successEl.innerHTML = '<div style="font-size:36px;margin-bottom:8px;">&#x2705;</div><h3>Enquiry Sent</h3><p class="ynj-text-muted">The mosque will contact you. Jazakallah khayr.</p>';
                document.getElementById('msvc-list').prepend(successEl);
                setTimeout(function(){ successEl.remove(); }, 5000);
            } else {
                document.getElementById('svc-modal-error').textContent = data.error || 'Failed.';
            }
        } catch(e) { document.getElementById('svc-modal-error').textContent = 'Network error.'; }
        btn.disabled = false; btn.textContent = 'Send Enquiry';
    });
})();
</script>
<?php endif; ?>
<?php get_footer(); ?>
