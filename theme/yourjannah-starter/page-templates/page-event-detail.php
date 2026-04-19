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

// Pre-load event data server-side — zero API calls for primary data
$event_data = null;
if ( $event_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $ev_table = YNJ_DB::table( 'events' );
    $event_data = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, title, description, event_date, start_time, end_time, location, event_type,
                ticket_price_pence, is_live, is_online, live_url, recording_url,
                max_capacity, registered_count, requires_booking,
                donation_target_pence, donation_raised_pence, donation_count
         FROM $ev_table WHERE id = %d AND status = 'active'", $event_id
    ) );
}
$ev_spots = null;
if ( $event_data && (int) $event_data->max_capacity > 0 ) {
    $ev_spots = max( 0, (int) $event_data->max_capacity - (int) $event_data->registered_count );
}
?>

<main class="ynj-main">
    <section class="ynj-card" id="event-detail">
    <?php if ( ! $event_data ) : ?>
        <p class="ynj-text-muted"><?php esc_html_e( 'Event not found.', 'yourjannah' ); ?></p>
    <?php else :
        $ev = $event_data;
        $ev_time = $ev->start_time ? preg_replace( '/:\d{2}$/', '', $ev->start_time ) : '';
        $ev_end  = $ev->end_time ? preg_replace( '/:\d{2}$/', '', $ev->end_time ) : '';
        $ev_price = (int) $ev->ticket_price_pence > 0 ? '£' . number_format( $ev->ticket_price_pence / 100, 2 ) : __( 'Free', 'yourjannah' );
        $ev_is_live = (int) $ev->is_live && (int) $ev->is_online;
        $ev_is_online = (int) $ev->is_online;
        $ev_spots_text = $ev_spots !== null ? sprintf( __( '%d spots remaining', 'yourjannah' ), $ev_spots ) : __( 'Unlimited capacity', 'yourjannah' );
    ?>
        <?php if ( $ev_is_live && $ev->live_url ) :
            preg_match( '/(?:youtube\.com\/(?:watch\?v=|live\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $ev->live_url, $yt_match );
            if ( ! empty( $yt_match[1] ) ) : ?>
                <div style="width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;margin-bottom:16px;"><iframe src="https://www.youtube.com/embed/<?php echo esc_attr( $yt_match[1] ); ?>?autoplay=0" style="width:100%;height:100%;border:none;" allow="autoplay;encrypted-media" allowfullscreen></iframe></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ( $ev_is_live ) : ?>
            <span style="display:inline-flex;align-items:center;gap:4px;background:#dc2626;color:#fff;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;margin-right:6px;">🔴 LIVE</span>
        <?php elseif ( $ev_is_online ) : ?>
            <span class="ynj-badge" style="background:#dbeafe;color:#1e40af;">🌐 Online</span>
        <?php endif; ?>
        <span class="ynj-badge ynj-badge--event"><?php echo esc_html( $ev->event_type ?: 'Event' ); ?></span>
        <h2 style="font-size:20px;font-weight:700;margin:8px 0 4px;"><?php echo esc_html( $ev->title ); ?></h2>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0;font-size:13px;color:#6b8fa3;">
            <span>📅 <?php echo esc_html( $ev->event_date ); ?></span>
            <span>🕐 <?php echo esc_html( $ev_time ); ?><?php echo $ev_end ? ' — ' . esc_html( $ev_end ) : ''; ?></span>
            <?php if ( $ev->location ) : ?><span>📍 <?php echo esc_html( $ev->location ); ?></span><?php endif; ?>
        </div>
        <p style="margin:12px 0;line-height:1.6;"><?php echo esc_html( $ev->description ); ?></p>
        <?php if ( $ev_is_live && $ev->live_url ) : ?>
            <a href="<?php echo esc_url( $ev->live_url ); ?>" target="_blank" rel="noopener" class="ynj-btn" style="width:100%;justify-content:center;background:#dc2626;margin-bottom:12px;">▶ <?php esc_html_e( 'Watch Live', 'yourjannah' ); ?></a>
        <?php elseif ( $ev_is_online && ! $ev_is_live && $ev->live_url ) : ?>
            <a href="<?php echo esc_url( $ev->live_url ); ?>" target="_blank" rel="noopener" class="ynj-btn ynj-btn--outline" style="width:100%;justify-content:center;margin-bottom:12px;">🔔 <?php esc_html_e( 'Set Reminder — Watch Online', 'yourjannah' ); ?></a>
        <?php endif; ?>
        <div style="display:flex;gap:16px;margin-top:12px;">
            <span class="ynj-badge"><?php echo esc_html( $ev_price ); ?></span>
            <span class="ynj-text-muted"><?php echo esc_html( $ev_spots_text ); ?></span>
        </div>
        <?php if ( (int) $ev->donation_target_pence > 0 || $ev_is_online ) :
            $don_target = (int) $ev->donation_target_pence > 0 ? number_format( $ev->donation_target_pence / 100 ) : '';
            $don_raised = number_format( ( (int) $ev->donation_raised_pence ) / 100 );
            $don_count  = (int) $ev->donation_count;
            $don_pct    = (int) $ev->donation_target_pence > 0 ? min( 100, round( (int) $ev->donation_raised_pence / (int) $ev->donation_target_pence * 100 ) ) : 0;
        ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0ec;">
                <h4 style="font-size:14px;font-weight:600;margin-bottom:8px;">&#x2764;&#xfe0f; <?php esc_html_e( 'Support This Event', 'yourjannah' ); ?></h4>
                <?php if ( (int) $ev->donation_target_pence > 0 ) : ?>
                    <div style="height:8px;background:#e8f0f4;border-radius:4px;overflow:hidden;margin-bottom:8px;"><div style="height:100%;width:<?php echo $don_pct; ?>%;background:linear-gradient(90deg,#00ADEF,#16a34a);border-radius:4px;"></div></div>
                    <div style="display:flex;justify-content:space-between;font-size:12px;color:#6b8fa3;margin-bottom:12px;">
                        <span><strong style="color:#0a1628;">&pound;<?php echo $don_raised; ?></strong> <?php esc_html_e( 'raised', 'yourjannah' ); ?><?php echo $don_target ? ' ' . esc_html__( 'of', 'yourjannah' ) . ' &pound;' . $don_target : ''; ?></span>
                        <span><?php echo $don_count; ?> <?php esc_html_e( 'donors', 'yourjannah' ); ?></span>
                    </div>
                <?php endif; ?>
                <div style="display:flex;gap:8px;align-items:center;">
                    <select id="event-don-amt" style="padding:8px;border:1px solid #e0e8ed;border-radius:8px;font-size:13px;">
                        <option value="500">&pound;5</option><option value="1000">&pound;10</option><option value="2000" selected>&pound;20</option><option value="5000">&pound;50</option><option value="10000">&pound;100</option>
                    </select>
                    <button class="ynj-btn" style="flex:1;justify-content:center;" onclick="donateEvent()">&#x2764;&#xfe0f; <?php esc_html_e( 'Donate', 'yourjannah' ); ?></button>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </section>

    <section class="ynj-card" id="rsvp-section" style="<?php echo $event_data ? '' : 'display:none;'; ?>">
    <?php if ( $event_data && $ev_spots === 0 ) : ?>
        <p style="text-align:center;font-weight:600;color:#dc2626;"><?php esc_html_e( 'This event is fully booked.', 'yourjannah' ); ?></p>
    <?php else : ?>
        <h3 class="ynj-card__title" id="rsvp-title"><?php
            if ( $event_data && (int) $event_data->ticket_price_pence > 0 ) {
                esc_html_e( 'Buy Ticket', 'yourjannah' );
            } else {
                esc_html_e( 'RSVP', 'yourjannah' );
            }
        ?></h3>
        <form id="rsvp-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Your Name *', 'yourjannah' ); ?></label><input type="text" name="user_name" required></div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Email *', 'yourjannah' ); ?></label><input type="email" name="user_email" required></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="user_phone"></div>
            </div>
        </form>
        <button class="ynj-btn" id="rsvp-btn" type="button" style="width:100%;justify-content:center;margin-top:12px;"><?php
            if ( $event_data && (int) $event_data->ticket_price_pence > 0 ) {
                printf( esc_html__( 'Buy Ticket — £%s', 'yourjannah' ), number_format( $event_data->ticket_price_pence / 100, 2 ) );
            } else {
                esc_html_e( 'RSVP — Free', 'yourjannah' );
            }
        ?></button>
        <p class="ynj-text-muted" id="rsvp-error" style="margin-top:8px;"></p>
    <?php endif; ?>
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
    // Event data pre-loaded from PHP — instant, no API call
    let eventData = <?php echo $event_data ? wp_json_encode( [
        'id'                     => (int) $event_data->id,
        'title'                  => $event_data->title,
        'ticket_price_pence'     => (int) $event_data->ticket_price_pence,
        'donation_target_pence'  => (int) $event_data->donation_target_pence,
        'donation_raised_pence'  => (int) $event_data->donation_raised_pence,
        'donation_count'         => (int) $event_data->donation_count,
        'is_online'              => (int) $event_data->is_online,
    ] ) : 'null'; ?>;

    window.donateEvent = function() {
        const amt = document.getElementById('event-don-amt').value;
        fetch(API + 'events/' + eventId + '/donate', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({amount_pence: parseInt(amt)})
        }).then(r=>r.json()).then(data => {
            if (data.ok && data.cart_item) { if (typeof ynjBasket !== 'undefined') ynjBasket.addItem(data.cart_item); }
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
            if (data.ok && data.cart_item) { if (typeof ynjBasket !== 'undefined') ynjBasket.addItem(data.cart_item); }
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
