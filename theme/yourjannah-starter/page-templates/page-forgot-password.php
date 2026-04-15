<?php
/**
 * Template: Forgot Password Page
 *
 * @package YourJannah
 */

get_header();
?>
<main class="ynj-main" style="padding-top:24px;">
    <section class="ynj-card" style="text-align:center;padding:32px 20px 20px;">
        <div style="font-size:36px;margin-bottom:8px;">🔑</div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Forgot Password', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted" style="margin-bottom:8px;"><?php esc_html_e( "Enter your email and we'll send you a reset link.", 'yourjannah' ); ?></p>
    </section>
    <section class="ynj-card" id="forgot-form-section">
        <form id="forgot-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?></label><input type="email" name="email" required placeholder="your@email.com"></div>
        </form>
        <button class="ynj-btn" id="forgot-btn" type="button" style="width:100%;justify-content:center;margin-top:16px;"><?php esc_html_e( 'Send Reset Link', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="forgot-msg" style="margin-top:8px;text-align:center;"></p>
        <p style="text-align:center;margin-top:16px;font-size:13px;"><?php esc_html_e( 'Remember your password?', 'yourjannah' ); ?> <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" style="font-weight:700;"><?php esc_html_e( 'Sign in', 'yourjannah' ); ?></a></p>
    </section>
</main>

<script>
document.getElementById('forgot-btn').addEventListener('click', async function() {
    const btn = this;
    const form = document.getElementById('forgot-form');
    const email = form.querySelector('[name="email"]').value.trim();
    if (!email) {
        document.getElementById('forgot-msg').textContent = '<?php echo esc_js( __( 'Please enter your email.', 'yourjannah' ) ); ?>';
        return;
    }
    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Sending...', 'yourjannah' ) ); ?>';
    try {
        const resp = await fetch(ynjData.restUrl + 'auth/forgot-password', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': ynjData.nonce},
            body: JSON.stringify({email})
        });
        const data = await resp.json();
        const msg = document.getElementById('forgot-msg');
        if (data.ok) {
            msg.style.color = '#16a34a';
            msg.textContent = data.message || '<?php echo esc_js( __( 'If an account exists, a reset link has been sent.', 'yourjannah' ) ); ?>';
            btn.textContent = '<?php echo esc_js( __( 'Sent', 'yourjannah' ) ); ?>';
        } else {
            msg.style.color = '#dc2626';
            msg.textContent = data.error || '<?php echo esc_js( __( 'Something went wrong.', 'yourjannah' ) ); ?>';
            btn.disabled = false;
            btn.textContent = '<?php echo esc_js( __( 'Send Reset Link', 'yourjannah' ) ); ?>';
        }
    } catch(e) {
        document.getElementById('forgot-msg').textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>';
        btn.disabled = false;
        btn.textContent = '<?php echo esc_js( __( 'Send Reset Link', 'yourjannah' ) ); ?>';
    }
});
</script>
<?php
get_footer();
