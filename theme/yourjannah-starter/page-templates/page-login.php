<?php
/**
 * Template: Unified Auth — Email-first, PIN-based
 *
 * Flow:
 *   Step 1: Enter email → Next
 *   Step 2a: Email exists → "Enter your PIN"
 *   Step 2b: Email is new → "Create your PIN" + "Confirm PIN"
 *
 * @package YourJannah
 */

get_header();

if ( is_user_logged_in() ) {
    $redirect = isset( $_GET['redirect'] ) ? wp_validate_redirect( sanitize_text_field( $_GET['redirect'] ), home_url( '/' ) ) : home_url( '/profile' );
    echo '<script>window.location.href = ' . wp_json_encode( $redirect ) . ';</script>';
    echo '<main class="ynj-main" style="padding:40px 20px;text-align:center;"><p>' . esc_html__( 'Already signed in. Redirecting...', 'yourjannah' ) . '</p></main>';
    get_footer();
    return;
}

$return_to   = isset( $_GET['redirect'] ) ? wp_validate_redirect( sanitize_text_field( $_GET['redirect'] ), '/' ) : '/';
$join_mosque = isset( $_GET['join_mosque'] ) ? sanitize_text_field( $_GET['join_mosque'] ) : '';
$show_google = class_exists( 'YNJ_Social_Auth' ) && YNJ_Social_Auth::is_google_configured();
$show_fb     = class_exists( 'YNJ_Social_Auth' ) && YNJ_Social_Auth::is_facebook_configured();
?>

<style>
.ynj-auth{max-width:420px;margin:0 auto;padding:24px 16px 40px;}
.ynj-auth-hero{text-align:center;margin-bottom:24px;}
.ynj-auth-hero h2{font-size:22px;font-weight:800;color:#0a1628;margin-bottom:4px;}
.ynj-auth-hero p{font-size:13px;color:#6b8fa3;}
.ynj-auth-card{background:#fff;border-radius:16px;padding:24px 20px;box-shadow:0 2px 16px rgba(0,0,0,.06);}
.ynj-auth-step{display:none;animation:ynjAuthIn .3s ease;}
.ynj-auth-step.active{display:block;}
@keyframes ynjAuthIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
.ynj-auth-field{margin-bottom:16px;}
.ynj-auth-field label{display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;}
.ynj-auth-field input{width:100%;padding:14px 16px;border:2px solid #e5e7eb;border-radius:14px;font-size:16px;font-family:inherit;background:#f9fafb;transition:border-color .2s;box-sizing:border-box;}
.ynj-auth-field input:focus{outline:none;border-color:#287e61;background:#fff;}
.ynj-auth-pin{font-size:32px !important;letter-spacing:14px;text-align:center;font-weight:900 !important;padding:18px 16px !important;}
.ynj-auth-hint{font-size:11px;color:#6b8fa3;text-align:center;margin-top:6px;}
.ynj-auth-btn{display:flex;align-items:center;justify-content:center;width:100%;padding:16px;border-radius:14px;background:linear-gradient(135deg,#287e61,#1a5c43);color:#fff;font-size:16px;font-weight:800;border:none;cursor:pointer;transition:all .15s;box-shadow:0 4px 16px rgba(40,126,97,.25);font-family:inherit;margin-top:8px;}
.ynj-auth-btn:hover{box-shadow:0 6px 24px rgba(40,126,97,.35);transform:translateY(-1px);}
.ynj-auth-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.ynj-auth-btn--back{background:none;color:#6b8fa3;font-size:13px;font-weight:600;box-shadow:none;padding:10px;margin-top:8px;}
.ynj-auth-btn--back:hover{color:#0a1628;box-shadow:none;transform:none;}
.ynj-auth-error{font-size:13px;color:#dc2626;text-align:center;margin-top:10px;min-height:18px;}
.ynj-auth-email-show{text-align:center;padding:10px 14px;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;font-size:14px;font-weight:700;color:#166534;margin-bottom:16px;word-break:break-all;}
.ynj-auth-footer{text-align:center;margin-top:16px;font-size:13px;color:#6b8fa3;}
.ynj-auth-footer a{font-weight:700;color:#287e61;text-decoration:none;}
</style>

<main class="ynj-main">
<div class="ynj-auth">
    <div class="ynj-auth-hero">
        <div style="font-size:40px;margin-bottom:6px;">&#x1F54C;</div>
        <h2 id="auth-title"><?php esc_html_e( 'Welcome to YourJannah', 'yourjannah' ); ?></h2>
        <p id="auth-subtitle"><?php esc_html_e( 'Enter your email to get started', 'yourjannah' ); ?></p>
    </div>

    <div class="ynj-auth-card">

        <?php if ( $show_google || $show_fb ) : ?>
        <div id="social-auth" style="margin-bottom:16px;">
            <?php if ( $show_google ) :
                $google_url = YNJ_Social_Auth::get_login_url( 'google', $return_to, '', $join_mosque );
            ?>
            <a href="<?php echo esc_url( $google_url ); ?>" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid #ddd;border-radius:12px;font-size:14px;font-weight:600;color:#333;text-decoration:none;margin-bottom:10px;background:#fff;box-sizing:border-box;">
                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                <?php esc_html_e( 'Continue with Google', 'yourjannah' ); ?>
            </a>
            <?php endif; ?>
            <?php if ( $show_fb ) :
                $fb_url = YNJ_Social_Auth::get_login_url( 'facebook', $return_to, '', $join_mosque );
            ?>
            <a href="<?php echo esc_url( $fb_url ); ?>" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border:1px solid #ddd;border-radius:12px;font-size:14px;font-weight:600;color:#333;text-decoration:none;background:#fff;box-sizing:border-box;">
                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#1877F2" d="M48 24C48 10.745 37.255 0 24 0S0 10.745 0 24c0 11.979 8.776 21.908 20.25 23.708v-16.77h-6.094V24h6.094v-5.288c0-6.014 3.583-9.337 9.065-9.337 2.625 0 5.372.469 5.372.469v5.906h-3.026c-2.981 0-3.911 1.85-3.911 3.75V24h6.656l-1.064 6.938H27.75v16.77C39.224 45.908 48 35.979 48 24z"/></svg>
                <?php esc_html_e( 'Continue with Facebook', 'yourjannah' ); ?>
            </a>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:12px;margin-top:14px;">
                <div style="flex:1;height:1px;background:#e5e7eb;"></div>
                <span style="font-size:13px;color:#9ca3af;font-weight:500;"><?php esc_html_e( 'or', 'yourjannah' ); ?></span>
                <div style="flex:1;height:1px;background:#e5e7eb;"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ STEP 1: Email ═══ -->
        <div class="ynj-auth-step active" id="step-email">
            <div class="ynj-auth-field">
                <label><?php esc_html_e( 'Email Address', 'yourjannah' ); ?></label>
                <input type="email" id="auth-email" required placeholder="you@example.com" autofocus>
            </div>
            <button type="button" class="ynj-auth-btn" id="btn-email-next"><?php esc_html_e( 'Next', 'yourjannah' ); ?> &#8594;</button>
            <div class="ynj-auth-error" id="email-error"></div>
        </div>

        <!-- ═══ STEP 2a: Enter PIN (existing user) ═══ -->
        <div class="ynj-auth-step" id="step-pin-login">
            <div class="ynj-auth-email-show" id="login-email-show"></div>
            <div class="ynj-auth-field">
                <label><?php esc_html_e( 'Enter your PIN', 'yourjannah' ); ?></label>
                <input type="tel" id="auth-pin-login" class="ynj-auth-pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="&#x2022;&#x2022;&#x2022;&#x2022;" autocomplete="off">
            </div>
            <button type="button" class="ynj-auth-btn" id="btn-pin-login"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></button>
            <div class="ynj-auth-error" id="pin-login-error"></div>
            <p style="text-align:center;margin-top:10px;font-size:12px;"><a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" style="color:#6b8fa3;"><?php esc_html_e( 'Forgot PIN?', 'yourjannah' ); ?></a></p>
            <button type="button" class="ynj-auth-btn ynj-auth-btn--back" onclick="showStep('email')">&#8592; <?php esc_html_e( 'Change email', 'yourjannah' ); ?></button>
        </div>

        <!-- ═══ STEP 2b: Create PIN (new user) ═══ -->
        <div class="ynj-auth-step" id="step-pin-create">
            <div class="ynj-auth-email-show" id="create-email-show"></div>
            <p style="text-align:center;font-size:13px;color:#6b8fa3;margin-bottom:16px;"><?php esc_html_e( "You're new! Set a 4-digit PIN to secure your account.", 'yourjannah' ); ?></p>
            <div class="ynj-auth-field">
                <label><?php esc_html_e( 'Choose a PIN', 'yourjannah' ); ?></label>
                <input type="tel" id="auth-pin-new" class="ynj-auth-pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="&#x2022;&#x2022;&#x2022;&#x2022;" autocomplete="off">
                <div class="ynj-auth-hint"><?php esc_html_e( '4-6 digits, like a bank PIN', 'yourjannah' ); ?></div>
            </div>
            <div class="ynj-auth-field">
                <label><?php esc_html_e( 'Confirm PIN', 'yourjannah' ); ?></label>
                <input type="tel" id="auth-pin-confirm" class="ynj-auth-pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="&#x2022;&#x2022;&#x2022;&#x2022;" autocomplete="off">
            </div>
            <button type="button" class="ynj-auth-btn" id="btn-pin-create">&#x1F54C; <?php esc_html_e( 'Create Account', 'yourjannah' ); ?></button>
            <div class="ynj-auth-error" id="pin-create-error"></div>
            <button type="button" class="ynj-auth-btn ynj-auth-btn--back" onclick="showStep('email')">&#8592; <?php esc_html_e( 'Change email', 'yourjannah' ); ?></button>
        </div>

    </div>

    <div class="ynj-auth-footer">
        <div style="border-top:1px solid #eee;margin-top:16px;padding-top:16px;">
            <span style="font-size:12px;color:#6b8fa3;"><?php esc_html_e( 'Mosque admin?', 'yourjannah' ); ?></span>
            <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>">&#x1F54C; <?php esc_html_e( 'Admin Dashboard', 'yourjannah' ); ?></a>
        </div>
    </div>
</div>
</main>

<script>
(function(){
    var API = ynjData.restUrl;
    var nonce = ynjData.nonce;
    var savedEmail = '';

    function showStep(step) {
        document.querySelectorAll('.ynj-auth-step').forEach(function(el){ el.classList.remove('active'); });
        document.getElementById('step-' + step).classList.add('active');
        // Hide social auth on PIN steps
        var social = document.getElementById('social-auth');
        if (social) social.style.display = step === 'email' ? 'block' : 'none';
        // Update title
        var title = document.getElementById('auth-title');
        var sub = document.getElementById('auth-subtitle');
        if (step === 'email') { title.textContent = <?php echo wp_json_encode( __( 'Welcome to YourJannah', 'yourjannah' ) ); ?>; sub.textContent = <?php echo wp_json_encode( __( 'Enter your email to get started', 'yourjannah' ) ); ?>; }
        if (step === 'pin-login') { title.textContent = <?php echo wp_json_encode( __( 'Welcome back!', 'yourjannah' ) ); ?>; sub.textContent = <?php echo wp_json_encode( __( 'Enter your PIN to sign in', 'yourjannah' ) ); ?>; }
        if (step === 'pin-create') { title.textContent = <?php echo wp_json_encode( __( 'Create your account', 'yourjannah' ) ); ?>; sub.textContent = <?php echo wp_json_encode( __( 'Set a PIN to get started', 'yourjannah' ) ); ?>; }
    }
    window.showStep = showStep;

    // ── STEP 1: Check email ──
    document.getElementById('btn-email-next').addEventListener('click', async function() {
        var btn = this;
        var email = document.getElementById('auth-email').value.trim();
        var err = document.getElementById('email-error');
        err.textContent = '';

        if (!email || email.indexOf('@') < 1) {
            err.textContent = <?php echo wp_json_encode( __( 'Please enter a valid email.', 'yourjannah' ) ); ?>;
            return;
        }

        btn.disabled = true; btn.textContent = <?php echo wp_json_encode( __( 'Checking...', 'yourjannah' ) ); ?>;

        try {
            var resp = await fetch(API + 'auth/check-email', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
                body: JSON.stringify({email: email})
            });
            var data = await resp.json();

            savedEmail = email;

            if (data.exists) {
                // Existing user → enter PIN
                document.getElementById('login-email-show').textContent = email;
                showStep('pin-login');
                document.getElementById('auth-pin-login').focus();
            } else {
                // New user → create PIN
                document.getElementById('create-email-show').textContent = email;
                showStep('pin-create');
                document.getElementById('auth-pin-new').focus();
            }
        } catch(e) {
            err.textContent = <?php echo wp_json_encode( __( 'Network error. Please try again.', 'yourjannah' ) ); ?>;
        }
        btn.disabled = false; btn.textContent = <?php echo wp_json_encode( __( 'Next', 'yourjannah' ) ); ?> + ' \u2192';
    });

    // Allow Enter key on email field
    document.getElementById('auth-email').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-email-next').click();
    });

    // ── STEP 2a: Login with PIN ──
    document.getElementById('btn-pin-login').addEventListener('click', async function() {
        var btn = this;
        var pin = document.getElementById('auth-pin-login').value.trim();
        var err = document.getElementById('pin-login-error');
        err.textContent = '';

        if (!pin || pin.length < 4) {
            err.textContent = <?php echo wp_json_encode( __( 'PIN must be at least 4 digits.', 'yourjannah' ) ); ?>;
            return;
        }

        btn.disabled = true; btn.textContent = <?php echo wp_json_encode( __( 'Signing in...', 'yourjannah' ) ); ?>;

        try {
            var resp = await fetch(API + 'auth/login', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
                body: JSON.stringify({email: savedEmail, pin: pin})
            });
            var data = await resp.json();

            if (data.ok && data.token) {
                localStorage.setItem('ynj_user_token', data.token);
                if (data.user) localStorage.setItem('ynj_user', JSON.stringify(data.user));
                // Set WP session
                await fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=ynj_set_session&wp_user_id=' + (data.wp_user_id || '')
                }).catch(function(){});
                // Redirect
                var params = new URLSearchParams(window.location.search);
                var redirect = params.get('redirect');
                if (redirect) { window.location.href = redirect; }
                else { var s = localStorage.getItem('ynj_mosque_slug'); window.location.href = s ? <?php echo wp_json_encode( home_url( '/mosque/' ) ); ?> + s : <?php echo wp_json_encode( home_url( '/' ) ); ?>; }
            } else {
                err.textContent = data.error || <?php echo wp_json_encode( __( 'Invalid PIN. Please try again.', 'yourjannah' ) ); ?>;
                btn.disabled = false; btn.textContent = <?php echo wp_json_encode( __( 'Sign In', 'yourjannah' ) ); ?>;
            }
        } catch(e) {
            err.textContent = <?php echo wp_json_encode( __( 'Network error.', 'yourjannah' ) ); ?>;
            btn.disabled = false; btn.textContent = <?php echo wp_json_encode( __( 'Sign In', 'yourjannah' ) ); ?>;
        }
    });

    // Allow Enter key on PIN login field
    document.getElementById('auth-pin-login').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-pin-login').click();
    });

    // ── STEP 2b: Create account with PIN ──
    document.getElementById('btn-pin-create').addEventListener('click', async function() {
        var btn = this;
        var pin = document.getElementById('auth-pin-new').value.trim();
        var pinConfirm = document.getElementById('auth-pin-confirm').value.trim();
        var err = document.getElementById('pin-create-error');
        err.textContent = '';

        if (!pin || pin.length < 4) {
            err.textContent = <?php echo wp_json_encode( __( 'PIN must be at least 4 digits.', 'yourjannah' ) ); ?>;
            document.getElementById('auth-pin-new').focus();
            return;
        }
        if (!/^\d+$/.test(pin)) {
            err.textContent = <?php echo wp_json_encode( __( 'PIN must be numbers only.', 'yourjannah' ) ); ?>;
            document.getElementById('auth-pin-new').focus();
            return;
        }
        if (pin !== pinConfirm) {
            err.textContent = <?php echo wp_json_encode( __( "PINs don't match. Try again.", 'yourjannah' ) ); ?>;
            document.getElementById('auth-pin-confirm').value = '';
            document.getElementById('auth-pin-confirm').focus();
            return;
        }

        btn.disabled = true; btn.textContent = <?php echo wp_json_encode( __( 'Creating account...', 'yourjannah' ) ); ?>;

        try {
            var name = savedEmail.split('@')[0];
            var slug = localStorage.getItem('ynj_mosque_slug') || '';

            var resp = await fetch(API + 'auth/register', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
                body: JSON.stringify({name: name, email: savedEmail, pin: pin, mosque_slug: slug})
            });
            var data = await resp.json();

            if (data.ok && data.token) {
                localStorage.setItem('ynj_user_token', data.token);
                if (data.user) localStorage.setItem('ynj_user', JSON.stringify(data.user));
                // Set WP session
                await fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=ynj_set_session&wp_user_id=' + (data.wp_user_id || '')
                }).catch(function(){});
                // Success! Redirect
                btn.textContent = <?php echo wp_json_encode( __( 'Account Created!', 'yourjannah' ) ); ?> + ' \u2713';
                btn.style.background = '#166534';
                var s = localStorage.getItem('ynj_mosque_slug');
                setTimeout(function(){
                    window.location.href = s ? <?php echo wp_json_encode( home_url( '/mosque/' ) ); ?> + s : <?php echo wp_json_encode( home_url( '/profile' ) ); ?>;
                }, 1500);
            } else {
                err.textContent = data.error || <?php echo wp_json_encode( __( 'Registration failed. Try again.', 'yourjannah' ) ); ?>;
                btn.disabled = false; btn.textContent = '\uD83D\uDD4C ' + <?php echo wp_json_encode( __( 'Create Account', 'yourjannah' ) ); ?>;
            }
        } catch(e) {
            err.textContent = <?php echo wp_json_encode( __( 'Network error.', 'yourjannah' ) ); ?>;
            btn.disabled = false; btn.textContent = '\uD83D\uDD4C ' + <?php echo wp_json_encode( __( 'Create Account', 'yourjannah' ) ); ?>;
        }
    });

    // Allow Enter on confirm PIN field
    document.getElementById('auth-pin-confirm').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-pin-create').click();
    });
})();
</script>

<?php get_footer(); ?>
