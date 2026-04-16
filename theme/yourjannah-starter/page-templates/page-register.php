<?php
/**
 * Template: Register Page
 *
 * @package YourJannah
 */

get_header();

if ( is_user_logged_in() ) {
    echo '<script>window.location.href = ' . wp_json_encode( home_url( '/profile' ) ) . ';</script>';
    echo '<main class="ynj-main" style="padding:40px 20px;text-align:center;"><p>' . esc_html__( 'Already signed in. Redirecting...', 'yourjannah' ) . '</p></main>';
    get_footer();
    return;
}
?>
<main class="ynj-main" style="padding-top:24px;">
    <section class="ynj-card" style="text-align:center;padding:32px 20px 20px;">
        <div style="font-size:36px;margin-bottom:8px;">🕌</div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Join YourJannah', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted" style="margin-bottom:8px;"><?php esc_html_e( 'Get personalised prayer reminders, save your mosque, and manage your bookings.', 'yourjannah' ); ?></p>
    </section>
    <section class="ynj-card">
        <form id="reg-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Your Name *', 'yourjannah' ); ?></label><input type="text" name="name" required placeholder="<?php esc_attr_e( 'Full name', 'yourjannah' ); ?>"></div>
            <div class="ynj-field"><label><?php esc_html_e( 'Email *', 'yourjannah' ); ?></label><input type="email" name="email" required placeholder="your@email.com"></div>
            <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="phone" placeholder="07xxx xxxxxx"></div>
            <div class="ynj-field"><label><?php esc_html_e( 'Password *', 'yourjannah' ); ?></label><input type="password" name="password" required placeholder="<?php esc_attr_e( 'Min 6 characters', 'yourjannah' ); ?>"></div>
        </form>
        <button class="ynj-btn" id="reg-btn" type="button" style="width:100%;justify-content:center;margin-top:16px;"><?php esc_html_e( 'Create Account', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="reg-error" style="margin-top:8px;text-align:center;"></p>
        <p style="text-align:center;margin-top:16px;font-size:13px;"><?php esc_html_e( 'Already have an account?', 'yourjannah' ); ?> <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" style="font-weight:700;"><?php esc_html_e( 'Sign in', 'yourjannah' ); ?></a></p>
    </section>
</main>

<script>
document.getElementById('reg-btn').addEventListener('click', async function() {
    const btn = this;
    const form = document.getElementById('reg-form');
    const name = form.querySelector('[name="name"]').value.trim();
    const email = form.querySelector('[name="email"]').value.trim();
    const password = form.querySelector('[name="password"]').value;
    if (!name || !email || !password) {
        document.getElementById('reg-error').textContent = '<?php echo esc_js( __( 'Name, email, and password required.', 'yourjannah' ) ); ?>';
        return;
    }
    if (password.length < 6) {
        document.getElementById('reg-error').textContent = '<?php echo esc_js( __( 'Password must be at least 6 characters.', 'yourjannah' ) ); ?>';
        return;
    }
    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Creating account...', 'yourjannah' ) ); ?>';
    try {
        const resp = await fetch(ynjData.restUrl + 'auth/register', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': ynjData.nonce},
            body: JSON.stringify({name, email, password, phone: form.querySelector('[name="phone"]').value.trim(), mosque_slug: localStorage.getItem('ynj_mosque_slug') || ''})
        });
        const data = await resp.json();
        if (data.ok && data.token) {
            localStorage.setItem('ynj_user_token', data.token);
            if (data.user) localStorage.setItem('ynj_user', JSON.stringify(data.user));
            // Redirect to mosque homepage (not empty profile)
            var savedSlug = localStorage.getItem('ynj_mosque_slug');
            window.location.href = savedSlug ? '<?php echo esc_js( home_url( '/mosque/' ) ); ?>' + savedSlug : '<?php echo esc_js( home_url( '/' ) ); ?>';
        } else {
            document.getElementById('reg-error').textContent = data.error || '<?php echo esc_js( __( 'Registration failed.', 'yourjannah' ) ); ?>';
            btn.disabled = false;
            btn.textContent = '<?php echo esc_js( __( 'Create Account', 'yourjannah' ) ); ?>';
        }
    } catch(e) {
        document.getElementById('reg-error').textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>';
        btn.disabled = false;
        btn.textContent = '<?php echo esc_js( __( 'Create Account', 'yourjannah' ) ); ?>';
    }
});
</script>
<?php
get_footer();
