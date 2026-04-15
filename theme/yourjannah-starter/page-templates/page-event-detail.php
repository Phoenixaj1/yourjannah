<?php
/**
 * Template: Event Detail Page
 *
 * Single event with RSVP form, ticket purchase, live stream embed, inline donations.
 *
 * @package YourJannah
 */

get_header();
$slug     = ynj_mosque_slug();
$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
?>

<main class="ynj-main">
    <section class="ynj-card" id="event-detail">
        <p class="ynj-text-muted"><?php esc_html_e( 'Loading event...', 'yourjannah' ); ?></p>
    </section>

    <section class="ynj-card" id="rsvp-section" style="display:none;">
        <h3 class="ynj-card__title" id="rsvp-title"><?php esc_html_e( 'RSVP', 'yourjannah' ); ?></h3>
        <form id="rsvp-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Your Name *', 'yourjannah' ); ?></label><input type="text" name="user_name" required></div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Email *', 'yourjannah' ); ?></label><input type="email" name="user_email" required></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="user_phone"></div>
            </div>
        </form>
        <button class="ynj-btn" id="rsvp-btn" type="button" style="width:100%;justify-content:center;margin-top:12px;"><?php esc_html_e( 'RSVP — Free', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="rsvp-error" style="margin-top:8px;"></p>
    </section>

    <section class="ynj-card" id="rsvp-success" style="display:none;text-align:center;padding:30px 20px;">
        <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
        <h3><?php esc_html_e( "You're In!", 'yourjannah' ); ?></h3>
        <p class="ynj-text-muted" id="rsvp-success-msg"><?php esc_html_e( 'See you there.', 'yourjannah' ); ?></p>
    </section>
</main>

<script>
(function(){
    const slug    = <?php echo wp_json_encode( $slug ); ?>;
    const API     = ynjData.restUrl;
    const eventId = <?php echo (int) $event_id; ?>;
    let eventData = null;

    fetch(API + 'events/' + eventId)
        .then(r => r.json())
        .then(resp => {
            if (!resp.ok || !resp.event) {
                document.getElementById('event-detail').innerHTML = '<p class="ynj-text-muted"><?php echo esc_js( __( 'Event not found.', 'yourjannah' ) ); ?></p>';
                return;
            }
            eventData = resp.event;
            const e = eventData;
            const time = e.start_time ? String(e.start_time).replace(/:\d{2}$/,'') : '';
            const endTime = e.end_time ? String(e.end_time).replace(/:\d{2}$/,'') : '';
            const price = e.ticket_price_pence > 0 ? '\u00a3' + (e.ticket_price_pence/100).toFixed(2) : '<?php echo esc_js( __( 'Free', 'yourjannah' ) ); ?>';
            const spots = e.spots_remaining !== null ? e.spots_remaining + ' <?php echo esc_js( __( 'spots remaining', 'yourjannah' ) ); ?>' : '<?php echo esc_js( __( 'Unlimited capacity', 'yourjannah' ) ); ?>';

            const isLive = e.is_live && e.is_online;
            const isOnline = e.is_online;
            const liveBadge = isLive ? '<span style="display:inline-flex;align-items:center;gap:4px;background:#dc2626;color:#fff;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;margin-right:6px;"><span style="width:8px;height:8px;background:#fff;border-radius:50%;animation:livePulse 1.5s ease-in-out infinite;"></span>LIVE</span>' : '';
            const onlineBadge = isOnline && !isLive ? '<span class="ynj-badge" style="background:#dbeafe;color:#1e40af;">\ud83c\udf10 Online</span>' : '';

            // Video embed for live events
            let videoEmbed = '';
            if (isLive && e.live_url) {
                const m = e.live_url.match(/(?:youtube\.com\/(?:watch\?v=|live\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
                videoEmbed = m ? '<div style="width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;margin-bottom:16px;"><iframe src="https://www.youtube.com/embed/' + m[1] + '?autoplay=0" style="width:100%;height:100%;border:none;" allow="autoplay;encrypted-media" allowfullscreen></iframe></div>' : '';
            }

            // Donation section
            const donTarget = e.donation_target_pence > 0 ? '\u00a3' + (e.donation_target_pence/100).toLocaleString() : '';
            const donRaised = '\u00a3' + ((e.donation_raised_pence||0)/100).toLocaleString();
            const donCount = e.donation_count || 0;
            const donPct = e.donation_target_pence > 0 ? Math.min(100, Math.round((e.donation_raised_pence||0) / e.donation_target_pence * 100)) : 0;
            let donateHtml = '';
            if (e.donation_target_pence > 0 || isOnline) {
                donateHtml = '<div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0ec;">' +
                    '<h4 style="font-size:14px;font-weight:600;margin-bottom:8px;">\u2764\ufe0f <?php echo esc_js( __( 'Support This Event', 'yourjannah' ) ); ?></h4>' +
                    (e.donation_target_pence > 0 ? '<div style="height:8px;background:#e8f0f4;border-radius:4px;overflow:hidden;margin-bottom:8px;"><div style="height:100%;width:' + donPct + '%;background:linear-gradient(90deg,#00ADEF,#16a34a);border-radius:4px;"></div></div><div style="display:flex;justify-content:space-between;font-size:12px;color:#6b8fa3;margin-bottom:12px;"><span><strong style="color:#0a1628;">' + donRaised + '</strong> <?php echo esc_js( __( 'raised', 'yourjannah' ) ); ?>' + (donTarget ? ' <?php echo esc_js( __( 'of', 'yourjannah' ) ); ?> ' + donTarget : '') + '</span><span>' + donCount + ' <?php echo esc_js( __( 'donors', 'yourjannah' ) ); ?></span></div>' : '') +
                    '<div style="display:flex;gap:8px;align-items:center;">' +
                    '<select id="event-don-amt" style="padding:8px;border:1px solid #e0e8ed;border-radius:8px;font-size:13px;">' +
                    '<option value="500">\u00a35</option><option value="1000">\u00a310</option><option value="2000" selected>\u00a320</option><option value="5000">\u00a350</option><option value="10000">\u00a3100</option>' +
                    '</select>' +
                    '<button class="ynj-btn" style="flex:1;justify-content:center;" onclick="donateEvent()">\u2764\ufe0f <?php echo esc_js( __( 'Donate', 'yourjannah' ) ); ?></button>' +
                    '</div></div>';
            }

            document.getElementById('event-detail').innerHTML =
                videoEmbed +
                liveBadge + onlineBadge + '<span class="ynj-badge ynj-badge--event">' + (e.event_type || 'Event') + '</span>' +
                '<h2 style="font-size:20px;font-weight:700;margin:8px 0 4px;">' + e.title + '</h2>' +
                '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;font-size:13px;color:#6b8fa3;">' +
                '<span>\ud83d\udcc5 ' + e.event_date + '</span>' +
                '<span>\ud83d\udd50 ' + time + (endTime ? ' \u2014 ' + endTime : '') + '</span>' +
                (e.location ? '<span>\ud83d\udccd ' + e.location + '</span>' : '') +
                '</div>' +
                '<p style="margin:12px 0;line-height:1.6;">' + (e.description || '') + '</p>' +
                (isLive && e.live_url ? '<a href="' + e.live_url + '" target="_blank" rel="noopener" class="ynj-btn" style="width:100%;justify-content:center;background:#dc2626;margin-bottom:12px;">\u25b6 <?php echo esc_js( __( 'Watch Live', 'yourjannah' ) ); ?></a>' : '') +
                (isOnline && !isLive && e.live_url ? '<a href="' + e.live_url + '" target="_blank" rel="noopener" class="ynj-btn ynj-btn--outline" style="width:100%;justify-content:center;margin-bottom:12px;">\ud83d\udd14 <?php echo esc_js( __( 'Set Reminder — Watch Online', 'yourjannah' ) ); ?></a>' : '') +
                '<div style="display:flex;gap:16px;margin-top:12px;">' +
                '<span class="ynj-badge">' + price + '</span>' +
                '<span class="ynj-text-muted">' + spots + '</span>' +
                '</div>' +
                donateHtml;

            // Show RSVP section
            if (e.spots_remaining === 0) {
                document.getElementById('rsvp-section').style.display = '';
                document.getElementById('rsvp-section').innerHTML = '<p style="text-align:center;font-weight:600;color:#dc2626;"><?php echo esc_js( __( 'This event is fully booked.', 'yourjannah' ) ); ?></p>';
            } else {
                document.getElementById('rsvp-section').style.display = '';
                const btnText = e.ticket_price_pence > 0 ? '<?php echo esc_js( __( 'Buy Ticket', 'yourjannah' ) ); ?> \u2014 ' + price : '<?php echo esc_js( __( 'RSVP — Free', 'yourjannah' ) ); ?>';
                document.getElementById('rsvp-btn').textContent = btnText;
                document.getElementById('rsvp-title').textContent = e.ticket_price_pence > 0 ? '<?php echo esc_js( __( 'Buy Ticket', 'yourjannah' ) ); ?>' : '<?php echo esc_js( __( 'RSVP', 'yourjannah' ) ); ?>';
            }
        })
        .catch(() => {
            document.getElementById('event-detail').innerHTML = '<p class="ynj-text-muted"><?php echo esc_js( __( 'Could not load event.', 'yourjannah' ) ); ?></p>';
        });

    window.donateEvent = function() {
        const amt = document.getElementById('event-don-amt').value;
        fetch(API + 'events/' + eventId + '/donate', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({amount_pence: parseInt(amt)})
        }).then(r=>r.json()).then(data => {
            if (data.ok && data.checkout_url) window.location.href = data.checkout_url;
            else alert(data.error || '<?php echo esc_js( __( 'Could not process.', 'yourjannah' ) ); ?>');
        }).catch(() => alert('<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>'));
    };

    document.getElementById('rsvp-btn').addEventListener('click', async function() {
        const btn = this; const form = document.getElementById('rsvp-form');
        const name = form.querySelector('[name="user_name"]').value.trim();
        const email = form.querySelector('[name="user_email"]').value.trim();
        if (!name || !email) { document.getElementById('rsvp-error').textContent = '<?php echo esc_js( __( 'Name and email required.', 'yourjannah' ) ); ?>'; return; }

        btn.disabled = true; btn.textContent = '<?php echo esc_js( __( 'Processing...', 'yourjannah' ) ); ?>';
        try {
            const resp = await fetch(API + 'stripe/checkout/event', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    event_id: eventId, user_name: name, user_email: email,
                    user_phone: form.querySelector('[name="user_phone"]').value.trim()
                })
            });
            const data = await resp.json();
            if (data.ok && data.checkout_url) { window.location.href = data.checkout_url; }
            else if (data.ok && data.free) {
                document.getElementById('rsvp-section').style.display = 'none';
                document.getElementById('rsvp-success').style.display = '';
                document.getElementById('rsvp-success-msg').textContent = data.message || '<?php echo esc_js( __( 'See you there!', 'yourjannah' ) ); ?>';
            }
            else { document.getElementById('rsvp-error').textContent = data.error || '<?php echo esc_js( __( 'Failed.', 'yourjannah' ) ); ?>'; btn.disabled = false; btn.textContent = '<?php echo esc_js( __( 'Try Again', 'yourjannah' ) ); ?>'; }
        } catch(e) { document.getElementById('rsvp-error').textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>'; btn.disabled = false; btn.textContent = '<?php echo esc_js( __( 'Try Again', 'yourjannah' ) ); ?>'; }
    });
})();
</script>
<?php get_footer(); ?>
