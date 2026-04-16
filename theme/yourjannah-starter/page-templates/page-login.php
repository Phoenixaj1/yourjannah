<?php
/**
 * Template: Login Page
 *
 * @package YourJannah
 */

get_header();

// If already logged in via WP session, redirect to profile
if ( is_user_logged_in() ) {
    $redirect = home_url( '/profile' );
    if ( ! empty( $_GET['redirect'] ) ) {
        $redirect = esc_url_raw( $_GET['redirect'] );
    }
    echo '<script>window.location.href = ' . wp_json_encode( $redirect ) . ';</script>';
    echo '<main class="ynj-main" style="padding:40px 20px;text-align:center;"><p>' . esc_html__( 'Already signed in. Redirecting...', 'yourjannah' ) . '</p></main>';
    get_footer();
    return;
}
?>
<main class="ynj-main" style="padding-top:24px;">
    <section class="ynj-card" style="text-align:center;padding:32px 20px 20px;">
        <div style="font-size:36px;margin-bottom:8px;">🕌</div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Welcome Back', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted" style="margin-bottom:24px;"><?php esc_html_e( 'Sign in to see your bookings and get personalised prayer reminders.', 'yourjannah' ); ?></p>
    </section>
    <section class="ynj-card">
        <form id="login-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?></label><input type="email" name="email" required placeholder="your@email.com"></div>
            <div class="ynj-field"><label><?php esc_html_e( 'Password', 'yourjannah' ); ?></label><input type="password" name="password" required placeholder="<?php esc_attr_e( 'Min 6 characters', 'yourjannah' ); ?>"></div>
        </form>
        <button class="ynj-btn" id="login-btn" type="button" style="width:100%;justify-content:center;margin-top:16px;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="login-error" style="margin-top:8px;text-align:center;"></p>
        <p style="text-align:center;margin-top:12px;font-size:13px;"><a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>"><?php esc_html_e( 'Forgot password?', 'yourjannah' ); ?></a></p>
        <p style="text-align:center;margin-top:8px;font-size:13px;"><?php esc_html_e( "Don't have an account?", 'yourjannah' ); ?> <a href="<?php echo esc_url( home_url( '/register' ) ); ?>" style="font-weight:700;"><?php esc_html_e( 'Create one', 'yourjannah' ); ?></a></p>
        <div style="border-top:1px solid #eee;margin-top:16px;padding-top:16px;text-align:center;">
            <p style="font-size:12px;color:#6b8fa3;margin-bottom:8px;"><?php esc_html_e( 'Are you a mosque admin?', 'yourjannah' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#00ADEF;">🕌 <?php esc_html_e( 'Mosque Admin Dashboard', 'yourjannah' ); ?></a>
        </div>
    </section>
</main>

<script>
document.getElementById('login-btn').addEventListener('click', async function() {
    const btn = this;
    const form = document.getElementById('login-form');
    const email = form.querySelector('[name="email"]').value.trim();
    const password = form.querySelector('[name="password"]').value;
    if (!email || !password) {
        document.getElementById('login-error').textContent = '<?php echo esc_js( __( 'Email and password required.', 'yourjannah' ) ); ?>';
        return;
    }
    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Signing in...', 'yourjannah' ) ); ?>';
    try {
        const resp = await fetch(ynjData.restUrl + 'auth/login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': ynjData.nonce},
            body: JSON.stringify({email, password})
        });
        const data = await resp.json();
        if (data.ok && data.token) {
            localStorage.setItem('ynj_user_token', data.token);
            if (data.user) localStorage.setItem('ynj_user', JSON.stringify(data.user));
            // Set WP session via admin-ajax (REST API can't set cookies reliably)
            await fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=ynj_set_session&wp_user_id=' + (data.wp_user_id || '')
            }).catch(function(){});
            // Redirect
            var params = new URLSearchParams(window.location.search);
            var redirect = params.get('redirect');
            if (redirect) { window.location.href = redirect; }
            else { var s = localStorage.getItem('ynj_mosque_slug'); window.location.href = s ? '<?php echo esc_js( home_url( '/mosque/' ) ); ?>' + s : '<?php echo esc_js( home_url( '/' ) ); ?>'; }
        } else {
            document.getElementById('login-error').textContent = data.error || '<?php echo esc_js( __( 'Login failed.', 'yourjannah' ) ); ?>';
            btn.disabled = false;
            btn.textContent = '<?php echo esc_js( __( 'Sign In', 'yourjannah' ) ); ?>';
        }
    } catch(e) {
        document.getElementById('login-error').textContent = '<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>';
        btn.disabled = false;
        btn.textContent = '<?php echo esc_js( __( 'Sign In', 'yourjannah' ) ); ?>';
    }
});
</script>
<?php
get_footer();
