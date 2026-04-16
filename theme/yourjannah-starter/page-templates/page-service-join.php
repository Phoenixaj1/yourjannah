<?php
/**
 * Template: List Your Service
 *
 * Professional service listing form — plumber, SEO, accountant, etc.
 * Proceeds support the masjid.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
$mosque_name = '';
$mosque = ynj_get_mosque( $slug );
if ( $mosque ) $mosque_name = $mosque->name;
?>

<main class="ynj-main">
    <div style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:16px;padding:24px 20px;margin-bottom:20px;color:#fff;text-align:center;">
        <div style="font-size:32px;margin-bottom:6px;">🤝</div>
        <?php if ( $mosque_name ) : ?>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-bottom:4px;"><?php echo esc_html( $mosque_name ); ?></div>
        <?php endif; ?>
        <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;"><?php esc_html_e( 'List Your Service', 'yourjannah' ); ?></h2>
        <p style="font-size:13px;opacity:.8;"><?php esc_html_e( 'Get found by your local community. A portion of the listing fee goes directly to supporting the masjid.', 'yourjannah' ); ?></p>
    </div>

    <section class="ynj-card">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:14px;"><?php esc_html_e( 'Your Details', 'yourjannah' ); ?></h3>
        <form id="svc-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Your Name', 'yourjannah' ); ?> *</label><input type="text" name="provider_name" required placeholder="<?php esc_attr_e( 'e.g. Ahmed Khan', 'yourjannah' ); ?>"></div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Service Type', 'yourjannah' ); ?> *</label>
                    <select name="service_type" required>
                        <option value=""><?php esc_html_e( 'Select...', 'yourjannah' ); ?></option>
                        <option>Imam / Scholar</option>
                        <option>Quran Teacher</option>
                        <option>Arabic Tutor</option>
                        <option>Counselling</option>
                        <option>Legal Services</option>
                        <option>Accounting</option>
                        <option>Web Development</option>
                        <option>SEO / Marketing</option>
                        <option>IT Support</option>
                        <option>Plumbing</option>
                        <option>Electrician</option>
                        <option>Handyman</option>
                        <option>Catering</option>
                        <option>Photography</option>
                        <option>Tutoring</option>
                        <option>Driving Instructor</option>
                        <option>Personal Training</option>
                        <option>Property</option>
                        <option>Insurance</option>
                        <option>Travel / Hajj</option>
                        <option>Cleaning</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?> *</label><input type="tel" name="phone" required></div>
            </div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?></label><input type="email" name="email"></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Area Covered', 'yourjannah' ); ?></label><input type="text" name="area_covered" placeholder="<?php esc_attr_e( 'e.g. Birmingham, UK-wide, Online', 'yourjannah' ); ?>"></div>
            </div>
            <div class="ynj-field"><label><?php esc_html_e( 'Description', 'yourjannah' ); ?> *</label><textarea name="description" rows="3" required placeholder="<?php esc_attr_e( 'Describe your service, experience, and availability...', 'yourjannah' ); ?>"></textarea></div>
        </form>
        <p id="svc-logged-in-note" style="margin-bottom:8px;font-size:12px;color:#166534;display:none;"><?php esc_html_e( 'Logged in — your details have been pre-filled.', 'yourjannah' ); ?></p>
        <button class="ynj-btn" id="svc-submit" type="button" style="width:100%;justify-content:center;margin-top:16px;"><?php esc_html_e( 'Submit Listing', 'yourjannah' ); ?></button>
        <p id="svc-error" style="margin-top:8px;font-size:13px;color:#dc2626;"></p>
        <p id="svc-success" style="margin-top:8px;font-size:13px;color:#166534;display:none;"></p>
        <p class="ynj-text-muted" style="margin-top:12px;text-align:center;font-size:11px;"><?php esc_html_e( 'Your listing will be reviewed before it appears publicly.', 'yourjannah' ); ?></p>
    </section>
</main>

<script>
(function(){
    var slug = <?php echo wp_json_encode( $slug ); ?>;
    var API = ynjData.restUrl;
    var userToken = localStorage.getItem('ynj_user_token') || '';

    // Auto-fill form for logged-in users.
    try {
        var userData = JSON.parse(localStorage.getItem('ynj_user'));
        if (userData && userToken) {
            var form = document.getElementById('svc-form');
            if (userData.name) form.querySelector('[name="provider_name"]').value = userData.name;
            if (userData.email) form.querySelector('[name="email"]').value = userData.email;
            if (userData.phone) form.querySelector('[name="phone"]').value = userData.phone;
            document.getElementById('svc-logged-in-note').style.display = '';
        }
    } catch(e) {}

    document.getElementById('svc-submit').addEventListener('click', async function() {
        var btn = this;
        var form = document.getElementById('svc-form');
        var name = form.querySelector('[name="provider_name"]').value.trim();
        var type = form.querySelector('[name="service_type"]').value;
        var phone = form.querySelector('[name="phone"]').value.trim();
        var desc = form.querySelector('[name="description"]').value.trim();

        if (!name || !type || !phone || !desc) {
            document.getElementById('svc-error').textContent = '<?php echo esc_js( __( 'Please fill in all required fields.', 'yourjannah' ) ); ?>';
            return;
        }

        var headers = {'Content-Type': 'application/json'};
        if (userToken) headers['Authorization'] = 'Bearer ' + userToken;

        btn.disabled = true; btn.textContent = '<?php echo esc_js( __( 'Submitting...', 'yourjannah' ) ); ?>';
        document.getElementById('svc-error').textContent = '';

        try {
            // Resolve mosque ID
            var mosqueResp = await fetch(API + 'mosques/' + slug).then(r => r.json());
            var mosqueId = (mosqueResp.mosque || mosqueResp).id;

            var resp = await fetch(API + 'services', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    mosque_id: mosqueId,
                    provider_name: name,
                    service_type: type,
                    phone: phone,
                    email: form.querySelector('[name="email"]').value.trim(),
                    area_covered: form.querySelector('[name="area_covered"]').value.trim(),
                    description: desc
                })
            });
            var data = await resp.json();
            if (data.ok) {
                document.getElementById('svc-success').style.display = '';
                document.getElementById('svc-success').textContent = '<?php echo esc_js( __( 'Your listing has been submitted for review. Thank you!', 'yourjannah' ) ); ?>';
                btn.textContent = '<?php echo esc_js( __( 'Submitted ✓', 'yourjannah' ) ); ?>';
                btn.style.background = '#166534';
                form.reset();
            } else {
                document.getElementById('svc-error').textContent = data.error || '<?php echo esc_js( __( 'Could not submit. Please try again.', 'yourjannah' ) ); ?>';
                btn.disabled = false; btn.textContent = '<?php echo esc_js( __( 'Submit Listing', 'yourjannah' ) ); ?>';
            }
        } catch(e) {
            document.getElementById('svc-error').textContent = '<?php echo esc_js( __( 'Network error. Please try again.', 'yourjannah' ) ); ?>';
            btn.disabled = false; btn.textContent = '<?php echo esc_js( __( 'Submit Listing', 'yourjannah' ) ); ?>';
        }
    });
})();
</script>
<?php get_footer(); ?>
