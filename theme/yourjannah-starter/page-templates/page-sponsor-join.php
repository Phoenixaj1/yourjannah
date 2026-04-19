<?php
/**
 * Template: Become a Sponsor
 *
 * Business sponsor signup form with tier selection and Stripe checkout.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>

<main class="ynj-main">
    <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Become a Sponsor', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:20px;"><?php esc_html_e( 'List your business and reach the community. 90% of proceeds go directly to supporting the masjid.', 'yourjannah' ); ?></p>

    <!-- Tier Selection -->
    <div class="ynj-tier-grid" id="tier-grid">
        <label class="ynj-tier">
            <input type="radio" name="tier" value="standard" data-price="30" checked>
            <div class="ynj-tier__body">
                <div class="ynj-tier__price">&pound;30<span>/month</span></div>
                <div class="ynj-tier__name"><?php esc_html_e( 'Standard', 'yourjannah' ); ?></div>
                <p class="ynj-text-muted" style="font-size:12px;"><?php esc_html_e( 'Basic listing with business details and contact info', 'yourjannah' ); ?></p>
            </div>
        </label>
        <label class="ynj-tier">
            <input type="radio" name="tier" value="featured" data-price="50">
            <div class="ynj-tier__body">
                <div class="ynj-tier__price">&pound;50<span>/month</span></div>
                <div class="ynj-tier__name"><?php esc_html_e( 'Featured', 'yourjannah' ); ?></div>
                <p class="ynj-text-muted" style="font-size:12px;"><?php esc_html_e( 'Highlighted listing with priority placement', 'yourjannah' ); ?></p>
            </div>
        </label>
        <label class="ynj-tier">
            <input type="radio" name="tier" value="premium" data-price="100">
            <div class="ynj-tier__body">
                <div class="ynj-tier__price">&pound;100<span>/month</span></div>
                <div class="ynj-tier__name"><?php esc_html_e( 'Premium', 'yourjannah' ); ?></div>
                <p class="ynj-text-muted" style="font-size:12px;"><?php esc_html_e( 'Top placement with gold badge — maximum visibility', 'yourjannah' ); ?></p>
            </div>
        </label>
    </div>

    <!-- Business Details Form -->
    <section class="ynj-card" style="margin-top:16px;">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:14px;"><?php esc_html_e( 'Business Details', 'yourjannah' ); ?></h3>
        <form id="sponsor-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Business Name', 'yourjannah' ); ?> *</label><input type="text" name="business_name" required></div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Category', 'yourjannah' ); ?> *</label>
                    <select name="category" required>
                        <option value=""><?php esc_html_e( 'Select...', 'yourjannah' ); ?></option>
                        <option>Restaurant</option><option>Grocery</option><option>Butcher</option>
                        <option>Clothing</option><option>Books &amp; Gifts</option><option>Health</option>
                        <option>Legal</option><option>Finance</option><option>Insurance</option>
                        <option>Travel</option><option>Education</option><option>Automotive</option>
                        <option>Catering</option><option>Property</option><option>Technology</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="phone"></div>
            </div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?> *</label><input type="email" name="email" required></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Website', 'yourjannah' ); ?></label><input type="url" name="website" placeholder="https://..."></div>
            </div>
            <div class="ynj-field"><label><?php esc_html_e( 'Address / Postcode', 'yourjannah' ); ?></label><input type="text" name="address"></div>
            <div class="ynj-field"><label><?php esc_html_e( 'Description', 'yourjannah' ); ?> *</label><textarea name="description" rows="3" required placeholder="<?php esc_attr_e( 'Tell the community about your business...', 'yourjannah' ); ?>"></textarea></div>
        </form>
        <p id="sponsor-logged-in-note" style="margin-bottom:8px;font-size:12px;color:#166534;display:none;"><?php esc_html_e( 'Logged in — your details have been pre-filled.', 'yourjannah' ); ?></p>
        <button class="ynj-btn" id="sponsor-submit" type="button" style="width:100%;justify-content:center;margin-top:16px;"><?php esc_html_e( 'Add to Cart', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="sponsor-error" style="margin-top:8px;color:#dc2626;"></p>
        <p class="ynj-text-muted" style="margin-top:12px;text-align:center;font-size:11px;"><?php esc_html_e( 'You can cancel anytime. 90% goes directly to the masjid.', 'yourjannah' ); ?></p>
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
            var form = document.getElementById('sponsor-form');
            if (userData.email) form.querySelector('[name="email"]').value = userData.email;
            if (userData.phone) form.querySelector('[name="phone"]').value = userData.phone;
            document.getElementById('sponsor-logged-in-note').style.display = '';
        }
    } catch(e) {}

    document.getElementById('sponsor-submit').addEventListener('click', async function() {
        var btn = this;
        var form = document.getElementById('sponsor-form');
        var name = form.querySelector('[name="business_name"]').value.trim();
        var category = form.querySelector('[name="category"]').value;
        var email = form.querySelector('[name="email"]').value.trim();
        var desc = form.querySelector('[name="description"]').value.trim();
        if (!name || !category || !email || !desc) {
            document.getElementById('sponsor-error').textContent = 'Please fill in all required fields.';
            return;
        }

        var tier = document.querySelector('input[name="tier"]:checked');
        var tierValue = tier ? tier.value : 'standard';

        var headers = {'Content-Type': 'application/json'};
        if (userToken) headers['Authorization'] = 'Bearer ' + userToken;

        btn.disabled = true; btn.textContent = 'Processing...';
        try {
            var resp = await fetch(API + 'stripe/checkout/business', {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    mosque_slug: slug,
                    business_name: name,
                    category: category,
                    email: email,
                    phone: form.querySelector('[name="phone"]').value.trim(),
                    website: form.querySelector('[name="website"]').value.trim(),
                    address: form.querySelector('[name="address"]').value.trim(),
                    description: desc,
                    tier: tierValue
                })
            });
            var data = await resp.json();
            if (data.ok && data.cart_item) {
                if (typeof ynjBasket !== 'undefined') ynjBasket.addItem(data.cart_item);
            } else {
                document.getElementById('sponsor-error').textContent = data.error || 'Could not process. Please try again.';
                btn.disabled = false; btn.textContent = 'Add to Cart';
            }
        } catch(e) {
            document.getElementById('sponsor-error').textContent = 'Network error.';
            btn.disabled = false; btn.textContent = 'Add to Cart';
        }
    });
})();
</script>
<?php get_footer(); ?>
