<?php
/**
 * Template: Login Page
 *
 * @package YourJannah
 */

get_header();

// If already logged in via WP session, redirect to profile
if ( is_user_logged_in() ) {
    $redirect = isset( $_GET['redirect'] ) ? wp_validate_redirect( sanitize_text_field( $_GET['redirect'] ), home_url( '/' ) ) : home_url( '/profile' );
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
        <?php
        $return_to    = isset( $_GET['redirect'] ) ? wp_validate_redirect( sanitize_text_field( $_GET['redirect'] ), home_url( '/' ) ) : '/';
        $join_mosque  = isset( $_GET['join_mosque'] ) ? sanitize_text_field( $_GET['join_mosque'] ) : '';
        $mosque_slug  = '';
        $show_google  = class_exists( 'YNJ_Social_Auth' ) && YNJ_Social_Auth::is_google_configured();
        $show_fb      = class_exists( 'YNJ_Social_Auth' ) && YNJ_Social_Auth::is_facebook_configured();

        if ( $show_google || $show_fb ) : ?>
            <div style="margin-bottom:16px;">
                <?php if ( $show_google ) :
                    $google_url = YNJ_Social_Auth::get_login_url( 'google', $return_to, $mosque_slug, $join_mosque );
                ?>
                <a href="<?php echo esc_url( $google_url ); ?>" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid #ddd;border-radius:12px;font-size:14px;font-weight:600;color:#333;text-decoration:none;margin-bottom:10px;background:#fff;box-sizing:border-box;">
                    <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    <?php esc_html_e( 'Continue with Google', 'yourjannah' ); ?>
                </a>
                <?php endif; ?>
                <?php if ( $show_fb ) :
                    $fb_url = YNJ_Social_Auth::get_login_url( 'facebook', $return_to, $mosque_slug, $join_mosque );
                ?>
                <a href="<?php echo esc_url( $fb_url ); ?>" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid #ddd;border-radius:12px;font-size:14px;font-weight:600;color:#333;text-decoration:none;margin-bottom:10px;background:#fff;box-sizing:border-box;">
                    <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#1877F2" d="M48 24C48 10.745 37.255 0 24 0S0 10.745 0 24c0 11.979 8.776 21.908 20.25 23.708v-16.77h-6.094V24h6.094v-5.288c0-6.014 3.583-9.337 9.065-9.337 2.625 0 5.372.469 5.372.469v5.906h-3.026c-2.981 0-3.911 1.85-3.911 3.75V24h6.656l-1.064 6.938H27.75v16.77C39.224 45.908 48 35.979 48 24z"/></svg>
                    <?php esc_html_e( 'Continue with Facebook', 'yourjannah' ); ?>
                </a>
                <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="flex:1;height:1px;background:#e5e7eb;"></div>
                <span style="font-size:13px;color:#9ca3af;font-weight:500;"><?php esc_html_e( 'or', 'yourjannah' ); ?></span>
                <div style="flex:1;height:1px;background:#e5e7eb;"></div>
            </div>
        <?php endif; ?>

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
