<?php
/**
 * Template: Reset Password Page
 *
 * @package YourJannah
 */

get_header();
?>
<main class="ynj-main" style="padding-top:24px;">
    <section class="ynj-card" style="text-align:center;padding:32px 20px 20px;">
        <div style="font-size:36px;margin-bottom:8px;">🔒</div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Reset Password', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted" style="margin-bottom:8px;"><?php esc_html_e( 'Enter your new password below.', 'yourjannah' ); ?></p>
    </section>
    <section class="ynj-card" id="reset-form-section">
        <form id="reset-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'New Password', 'yourjannah' ); ?></label><input type="password" name="password" required placeholder="<?php esc_attr_e( 'Min 6 characters', 'yourjannah' ); ?>"></div>
            <div class="ynj-field"><label><?php esc_html_e( 'Confirm Password', 'yourjannah' ); ?></label><input type="password" name="password_confirm" required placeholder="<?php esc_attr_e( 'Repeat password', 'yourjannah' ); ?>"></div>
        </form>
        <button class="ynj-btn" id="reset-btn" type="button" style="width:100%;justify-content:center;margin-top:16px;"><?php esc_html_e( 'Reset Password', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="reset-msg" style="margin-top:8px;text-align:center;"></p>
    </section>
</main>

<script>
document.getElementById('reset-btn').addEventListener('click', async function() {
    const btn = this;
    const form = document.getElementById('reset-form');
    const password = form.querySelector('[name="password"]').value;
    const confirm = form.querySelector('[name="password_confirm"]').value;
    const msg = document.getElementById('reset-msg');

    if (!password || password.length < 6) {
        msg.textContent = '<?php echo esc_js( __( 'Password must be at least 6 characters.', 'yourjannah' ) ); ?>';
        return;
    }
    if (password !== confirm) {
        msg.textContent = '<?php echo esc_js( __( 'Passwords do not match.', 'yourjannah' ) ); ?>';
        return;
    }

    // Read key and email from URL params
    const params = new URLSearchParams(window.location.search);
    const key = params.get('key');
    const email = params.get('email');

    if (!key || !email) {
        msg.textContent = '<?php echo esc_js( __( 'Invalid reset link. Please request a new one.', 'yourjannah' ) ); ?>';
        return;
    }

    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Resetting...', 'yourjannah' ) ); ?>';
    try {
        const resp = await fetch(ynjData.restUrl + 'auth/reset-password', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': ynjData.nonce},
            body: JSON.stringify({email, key, password})
        });
        const data = await resp.json();
        if (data.ok) {
            msg.style.color = '#16a34a';
            msg.textContent = data.message || '<?php echo esc_js( __( 'Password reset. Redirecting to login...', 'yourjannah' ) ); ?>';
            btn.textContent = '<?php echo esc_js( __( 'Done', 'yourjannah' ) ); ?>';
            setTimeout(() => { window.location.href = '<?php echo esc_js( home_url( '/login' ) ); ?>'; }, 2000);
        } else {
            msg.style.color = '#dc2626';
            msg.textContent = data.error || '<?php echo esc_js( __( 'Reset failed.', 'yourjannah' ) ); ?>';
            btn.disabled = false;
            btn.textContent = '<?php echo esc_js( __( 'Reset Password', 'yourjannah' ) ); ?>';
        }
    } catch(e) {
        msg.textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>';
        btn.disabled = false;
        btn.textContent = '<?php echo esc_js( __( 'Reset Password', 'yourjannah' ) ); ?>';
    }
});
</script>
<?php
get_footer();
