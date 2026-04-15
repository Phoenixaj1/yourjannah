<?php
/**
 * Template: Contact / Enquiry Page
 *
 * Contact form: name, email, subject, message -> POST to /enquiries
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<main class="ynj-main">
    <section class="ynj-card" id="contact-form-card">
        <h2 class="ynj-card__title"><?php esc_html_e( 'Send an Enquiry', 'yourjannah' ); ?></h2>
        <form id="contact-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Your Name *', 'yourjannah' ); ?></label><input type="text" name="name" required></div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Email *', 'yourjannah' ); ?></label><input type="email" name="email" required></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="phone"></div>
            </div>
            <div class="ynj-field"><label><?php esc_html_e( 'Enquiry Type', 'yourjannah' ); ?></label>
                <select name="type">
                    <option value="general"><?php esc_html_e( 'General', 'yourjannah' ); ?></option>
                    <option value="nikah"><?php esc_html_e( 'Nikah / Marriage', 'yourjannah' ); ?></option>
                    <option value="janazah"><?php esc_html_e( 'Funeral / Janazah', 'yourjannah' ); ?></option>
                    <option value="room_booking"><?php esc_html_e( 'Room Booking', 'yourjannah' ); ?></option>
                    <option value="classes"><?php esc_html_e( 'Classes / Education', 'yourjannah' ); ?></option>
                    <option value="volunteer"><?php esc_html_e( 'Volunteering', 'yourjannah' ); ?></option>
                    <option value="other"><?php esc_html_e( 'Other', 'yourjannah' ); ?></option>
                </select>
            </div>
            <div class="ynj-field"><label><?php esc_html_e( 'Subject', 'yourjannah' ); ?></label><input type="text" name="subject" placeholder="<?php esc_attr_e( 'Brief subject line', 'yourjannah' ); ?>"></div>
            <div class="ynj-field"><label><?php esc_html_e( 'Message *', 'yourjannah' ); ?></label><textarea name="message" rows="5" required placeholder="<?php esc_attr_e( 'Your message to the mosque...', 'yourjannah' ); ?>"></textarea></div>
        </form>
        <button class="ynj-btn" id="submit-contact" type="button" style="width:100%;justify-content:center;margin-top:12px;"><?php esc_html_e( 'Send Enquiry', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="contact-error" style="margin-top:8px;"></p>
    </section>

    <section class="ynj-card" id="contact-success" style="display:none;text-align:center;padding:40px 20px;">
        <div style="font-size:48px;margin-bottom:12px;">&#x2705;</div>
        <h2 style="margin-bottom:8px;"><?php esc_html_e( 'Enquiry Sent', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted"><?php esc_html_e( 'The mosque will respond to your email. Jazakallah khayr.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug ) ); ?>" class="ynj-btn" style="margin-top:20px;"><?php esc_html_e( 'Back to Mosque', 'yourjannah' ); ?></a>
    </section>
</main>

<script>
document.getElementById('submit-contact').addEventListener('click', async function() {
    const btn = this;
    const form = document.getElementById('contact-form');
    const name = form.querySelector('[name="name"]').value.trim();
    const email = form.querySelector('[name="email"]').value.trim();
    const message = form.querySelector('[name="message"]').value.trim();
    if (!name || !email || !message) {
        document.getElementById('contact-error').textContent = '<?php echo esc_js( __( 'Name, email, and message required.', 'yourjannah' ) ); ?>';
        return;
    }

    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Sending...', 'yourjannah' ) ); ?>';
    try {
        const slug = <?php echo wp_json_encode( $slug ); ?>;
        const resp = await fetch(ynjData.restUrl + 'enquiries', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                mosque_slug: slug,
                name: name,
                email: email,
                message: message,
                phone: form.querySelector('[name="phone"]').value.trim(),
                type: form.querySelector('[name="type"]').value,
                subject: form.querySelector('[name="subject"]').value.trim()
            })
        });
        const data = await resp.json();
        if (data.ok) {
            document.getElementById('contact-form-card').style.display = 'none';
            document.getElementById('contact-success').style.display = '';
        } else {
            document.getElementById('contact-error').textContent = data.error || '<?php echo esc_js( __( 'Failed to send.', 'yourjannah' ) ); ?>';
            btn.disabled = false;
            btn.textContent = '<?php echo esc_js( __( 'Send Enquiry', 'yourjannah' ) ); ?>';
        }
    } catch(e) {
        document.getElementById('contact-error').textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>';
        btn.disabled = false;
        btn.textContent = '<?php echo esc_js( __( 'Send Enquiry', 'yourjannah' ) ); ?>';
    }
});
</script>
<?php
get_footer();
