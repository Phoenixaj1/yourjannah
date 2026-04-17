<?php
/**
 * Template: Register Page — Multi-step Membership Signup
 *
 * Step 1: Account details (name, email, password, phone)
 * Step 2: Choose membership level (Free or Patron tier)
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
<style>
/* Hero */
.ynj-reg-hero{text-align:center;padding:36px 20px 24px;background:linear-gradient(135deg,#0a1628 0%,#1a3a5c 50%,#0e4d3c 100%);color:#fff;border-radius:0 0 24px 24px;margin:-16px -16px 20px;}
.ynj-reg-hero h2{font-size:22px;font-weight:800;margin-bottom:6px;color:#fff;}
.ynj-reg-hero p{font-size:13px;color:rgba(255,255,255,.7);line-height:1.5;max-width:400px;margin:0 auto;}

/* Step indicator */
.ynj-steps{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:24px;}
.ynj-step-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;border:2px solid #d1d5db;color:#9ca3af;background:#fff;transition:all .25s;}
.ynj-step-dot.active{border-color:#00ADEF;background:#00ADEF;color:#fff;}
.ynj-step-dot.done{border-color:#10b981;background:#10b981;color:#fff;}
.ynj-step-line{width:48px;height:2px;background:#d1d5db;transition:background .25s;}
.ynj-step-line.active{background:#10b981;}

/* Form */
.ynj-reg-card{background:#fff;border-radius:16px;padding:24px 20px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:16px;}
.ynj-reg-card h3{font-size:16px;font-weight:700;margin-bottom:4px;color:#0a1628;}
.ynj-reg-card .ynj-subtitle{font-size:13px;color:#6b8fa3;margin-bottom:18px;}
.ynj-reg-field{margin-bottom:14px;}
.ynj-reg-field label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:5px;}
.ynj-reg-field input{width:100%;padding:12px 14px;border:2px solid #e5e7eb;border-radius:12px;font-size:15px;font-family:inherit;background:#f9fafb;transition:border-color .15s;box-sizing:border-box;}
.ynj-reg-field input:focus{outline:none;border-color:#00ADEF;background:#fff;}
.ynj-reg-field .ynj-optional{font-weight:400;color:#9ca3af;font-size:11px;margin-left:4px;}

/* Buttons */
.ynj-reg-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:16px;border-radius:14px;background:linear-gradient(135deg,#00ADEF,#0090d0);color:#fff;font-size:16px;font-weight:800;border:none;cursor:pointer;transition:all .15s;box-shadow:0 4px 16px rgba(0,173,239,.25);font-family:inherit;margin-top:8px;}
.ynj-reg-btn:hover{box-shadow:0 6px 24px rgba(0,173,239,.35);transform:translateY(-1px);}
.ynj-reg-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.ynj-reg-btn--back{background:none;color:#6b8fa3;font-size:14px;font-weight:600;box-shadow:none;margin-top:12px;padding:10px;}
.ynj-reg-btn--back:hover{color:#0a1628;box-shadow:none;transform:none;}

/* Error */
.ynj-reg-error{font-size:13px;color:#dc2626;text-align:center;margin-top:10px;min-height:18px;}

/* Tier rows */
.ynj-tier-mosque{text-align:center;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b8fa3;margin-bottom:16px;padding:8px 0;border-bottom:1px solid #f0f0f0;}
.ynj-tier-row{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:14px;background:#fff;border:2px solid #e5e7eb;cursor:pointer;transition:all .15s;position:relative;margin-bottom:8px;}
.ynj-tier-row:hover,.ynj-tier-row.selected{border-color:#00ADEF;box-shadow:0 4px 16px rgba(0,173,239,.12);}
.ynj-tier-row.selected::after{content:'\2713';position:absolute;top:50%;right:14px;transform:translateY(-50%);width:24px;height:24px;border-radius:50%;background:#00ADEF;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;}
.ynj-tier-row--free{border-color:#e5e7eb;background:#f9fafb;opacity:.8;}
.ynj-tier-row--free:hover,.ynj-tier-row--free.selected{border-color:#9ca3af;box-shadow:0 2px 8px rgba(0,0,0,.06);opacity:1;}
.ynj-tier-row--free.selected::after{background:#9ca3af;}
.ynj-tier-row--rec{border-color:#f59e0b;box-shadow:0 4px 16px rgba(245,158,11,.15);}
.ynj-tier-row--rec:hover,.ynj-tier-row--rec.selected{border-color:#f59e0b;box-shadow:0 6px 24px rgba(245,158,11,.2);}
.ynj-tier-row--rec.selected::after{background:#f59e0b;}
.ynj-tier-badge{position:absolute;top:-8px;left:50%;transform:translateX(-50%);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;padding:2px 10px;border-radius:6px;background:#f59e0b;color:#fff;white-space:nowrap;}
.ynj-tier-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.ynj-tier-name{flex:1;}
.ynj-tier-name strong{display:block;font-size:15px;font-weight:700;color:#0a1628;}
.ynj-tier-name small{font-size:11px;color:#6b8fa3;}
.ynj-tier-price{font-size:22px;font-weight:900;color:#0a1628;margin-right:30px;}
.ynj-tier-price span{font-size:12px;font-weight:500;color:#6b8fa3;}
.ynj-tier-price--free{color:#9ca3af;}

/* Animations */
.ynj-step-panel{display:none;animation:ynjFadeIn .3s ease;}
.ynj-step-panel.active{display:block;}
@keyframes ynjFadeIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}

/* Sign-in link */
.ynj-signin-link{text-align:center;margin-top:16px;font-size:13px;color:#6b8fa3;}
.ynj-signin-link a{font-weight:700;color:#00ADEF;text-decoration:none;}
</style>

<main class="ynj-main">
    <div class="ynj-reg-hero">
        <div style="font-size:36px;margin-bottom:4px;">&#x1F54C;</div>
        <h2><?php esc_html_e( 'Join YourJannah', 'yourjannah' ); ?></h2>
        <p><?php esc_html_e( 'Create your account and choose how you want to support your masjid.', 'yourjannah' ); ?></p>
    </div>

    <!-- Step Indicator -->
    <div class="ynj-steps">
        <div class="ynj-step-dot active" id="dot-1">1</div>
        <div class="ynj-step-line" id="line-1"></div>
        <div class="ynj-step-dot" id="dot-2">2</div>
    </div>

    <!-- Step 1: Account Details -->
    <div class="ynj-step-panel active" id="step-1">
        <div class="ynj-reg-card">
            <h3><?php esc_html_e( 'Enter your email', 'yourjannah' ); ?></h3>
            <p class="ynj-subtitle"><?php esc_html_e( 'We\'ll email you your password. No hassle.', 'yourjannah' ); ?></p>

            <?php
            $return_to    = isset( $_GET['redirect'] ) ? sanitize_text_field( $_GET['redirect'] ) : '/';
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

            <form id="reg-form-1" autocomplete="on" onsubmit="return false;">
                <input type="hidden" id="reg-name" value="">
                <input type="hidden" id="reg-phone" value="">
                <div class="ynj-reg-field">
                    <label for="reg-email"><?php esc_html_e( 'Email Address', 'yourjannah' ); ?></label>
                    <input type="email" id="reg-email" name="email" required autocomplete="email" placeholder="you@example.com" style="font-size:17px;padding:14px 16px;">
                </div>
            </form>

            <button class="ynj-reg-btn" id="btn-continue" type="button"><?php esc_html_e( 'Continue', 'yourjannah' ); ?> &#8594;</button>
            <div class="ynj-reg-error" id="step1-error"></div>
        </div>

        <div class="ynj-signin-link">
            <?php esc_html_e( 'Already have an account?', 'yourjannah' ); ?>
            <a href="<?php echo esc_url( home_url( '/login' ) ); ?>"><?php esc_html_e( 'Sign in', 'yourjannah' ); ?></a>
        </div>
    </div>

    <!-- Step 2: Choose Level -->
    <div class="ynj-step-panel" id="step-2">
        <div class="ynj-reg-card">
            <h3><?php esc_html_e( 'Choose Your Level', 'yourjannah' ); ?></h3>
            <p class="ynj-subtitle"><?php esc_html_e( 'Support your masjid with a monthly contribution, or join free.', 'yourjannah' ); ?></p>

            <div class="ynj-tier-mosque" id="tier-mosque"></div>

            <div id="tier-list">
                <!-- Free -->
                <div class="ynj-tier-row ynj-tier-row--free" data-tier="free" onclick="selectTier('free')">
                    <div class="ynj-tier-icon" style="background:#e5e7eb;">&#x1F464;</div>
                    <div class="ynj-tier-name">
                        <strong><?php esc_html_e( 'Free Member', 'yourjannah' ); ?></strong>
                        <small><?php esc_html_e( 'Prayer times, bookings, reminders', 'yourjannah' ); ?></small>
                    </div>
                    <div class="ynj-tier-price ynj-tier-price--free">&pound;0</div>
                </div>
                <!-- Bronze -->
                <div class="ynj-tier-row" data-tier="supporter" onclick="selectTier('supporter')">
                    <div class="ynj-tier-icon" style="background:#cd7f32;">&#x1F949;</div>
                    <div class="ynj-tier-name">
                        <strong><?php esc_html_e( 'Bronze Patron', 'yourjannah' ); ?></strong>
                        <small><?php esc_html_e( 'Badge + patron wall', 'yourjannah' ); ?></small>
                    </div>
                    <div class="ynj-tier-price">&pound;5<span>/mo</span></div>
                </div>
                <!-- Silver -->
                <div class="ynj-tier-row" data-tier="guardian" onclick="selectTier('guardian')">
                    <div class="ynj-tier-icon" style="background:#9ca3af;">&#x1F948;</div>
                    <div class="ynj-tier-name">
                        <strong><?php esc_html_e( 'Silver Patron', 'yourjannah' ); ?></strong>
                        <small><?php esc_html_e( 'Badge + patron wall', 'yourjannah' ); ?></small>
                    </div>
                    <div class="ynj-tier-price">&pound;10<span>/mo</span></div>
                </div>
                <!-- Gold (Recommended) -->
                <div class="ynj-tier-row ynj-tier-row--rec selected" data-tier="champion" onclick="selectTier('champion')">
                    <div class="ynj-tier-badge"><?php esc_html_e( 'Recommended', 'yourjannah' ); ?></div>
                    <div class="ynj-tier-icon" style="background:#f59e0b;">&#x1F947;</div>
                    <div class="ynj-tier-name">
                        <strong><?php esc_html_e( 'Gold Patron', 'yourjannah' ); ?></strong>
                        <small><?php esc_html_e( 'Badge + patron wall + priority', 'yourjannah' ); ?></small>
                    </div>
                    <div class="ynj-tier-price">&pound;20<span>/mo</span></div>
                </div>
                <!-- Platinum -->
                <div class="ynj-tier-row" data-tier="platinum" onclick="selectTier('platinum')">
                    <div class="ynj-tier-icon" style="background:#6b21a8;">&#x1F48E;</div>
                    <div class="ynj-tier-name">
                        <strong><?php esc_html_e( 'Platinum Patron', 'yourjannah' ); ?></strong>
                        <small><?php esc_html_e( 'All perks + featured supporter', 'yourjannah' ); ?></small>
                    </div>
                    <div class="ynj-tier-price">&pound;50<span>/mo</span></div>
                </div>
            </div>

            <button class="ynj-reg-btn" id="btn-create" type="button">&#x1F54C; <?php esc_html_e( 'Create Account', 'yourjannah' ); ?></button>
            <button class="ynj-reg-btn ynj-reg-btn--back" id="btn-back" type="button">&#8592; <?php esc_html_e( 'Back', 'yourjannah' ); ?></button>
            <div class="ynj-reg-error" id="step2-error"></div>
        </div>
    </div>
</main>

<script>
(function(){
    var API       = ynjData.restUrl;
    var adminAjax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var homeUrl   = <?php echo wp_json_encode( home_url( '/' ) ); ?>;
    var selectedTier = 'champion';

    var tierLabels = { free:'Free Member', supporter:'Bronze Patron', guardian:'Silver Patron', champion:'Gold Patron', platinum:'Platinum Patron' };
    var tierPrices = { free:0, supporter:5, guardian:10, champion:20, platinum:50 };

    // --- Mosque name from localStorage ---
    var mosqueName = localStorage.getItem('ynj_mosque_name') || '';
    var mosqueEl = document.getElementById('tier-mosque');
    if (mosqueName) {
        mosqueEl.textContent = mosqueName;
    } else {
        mosqueEl.style.display = 'none';
    }

    // --- Step navigation ---
    function showStep(n) {
        document.getElementById('step-1').classList.toggle('active', n === 1);
        document.getElementById('step-2').classList.toggle('active', n === 2);
        document.getElementById('dot-1').className = 'ynj-step-dot ' + (n === 1 ? 'active' : 'done');
        document.getElementById('dot-1').textContent = n === 1 ? '1' : '\u2713';
        document.getElementById('line-1').className = 'ynj-step-line' + (n === 2 ? ' active' : '');
        document.getElementById('dot-2').className = 'ynj-step-dot' + (n === 2 ? ' active' : '');
    }

    // --- Step 1: Continue ---
    document.getElementById('btn-continue').addEventListener('click', function() {
        var email    = document.getElementById('reg-email').value.trim();
        var errEl    = document.getElementById('step1-error');
        errEl.textContent = '';

        if (!email) {
            errEl.textContent = <?php echo wp_json_encode( __( 'Email is required.', 'yourjannah' ) ); ?>;
            return;
        }
        // Use email prefix as display name
        document.getElementById('reg-name').value = email.split('@')[0];
        // Basic email check
        if (email.indexOf('@') < 1 || email.indexOf('.') < 3) {
            errEl.textContent = <?php echo wp_json_encode( __( 'Please enter a valid email address.', 'yourjannah' ) ); ?>;
            return;
        }
        showStep(2);
        updateCreateBtn();
    });

    // --- Step 2: Back ---
    document.getElementById('btn-back').addEventListener('click', function() {
        showStep(1);
    });

    // --- Tier selection ---
    function selectTier(tier) {
        selectedTier = tier;
        document.querySelectorAll('.ynj-tier-row').forEach(function(el) {
            el.classList.toggle('selected', el.dataset.tier === tier);
        });
        updateCreateBtn();
    }
    window.selectTier = selectTier;

    function updateCreateBtn() {
        var btn = document.getElementById('btn-create');
        if (selectedTier === 'free') {
            btn.innerHTML = '&#x1F54C; <?php echo esc_js( __( 'Create Free Account', 'yourjannah' ) ); ?>';
        } else {
            btn.innerHTML = '&#x1F54C; <?php echo esc_js( __( 'Create Account', 'yourjannah' ) ); ?> &mdash; &pound;' + tierPrices[selectedTier] + '/mo';
        }
    }

    // --- Step 2: Create Account ---
    document.getElementById('btn-create').addEventListener('click', async function() {
        var btn   = this;
        var errEl = document.getElementById('step2-error');
        errEl.textContent = '';

        var name     = document.getElementById('reg-name').value.trim();
        var email    = document.getElementById('reg-email').value.trim();
        var password = 'YJ_' + Math.random().toString(36).slice(2, 10) + '!'; // auto-generated
        var phone    = document.getElementById('reg-phone').value.trim();
        var slug     = localStorage.getItem('ynj_mosque_slug') || '';

        btn.disabled = true;
        btn.textContent = <?php echo wp_json_encode( __( 'Creating account...', 'yourjannah' ) ); ?>;

        try {
            // 1. Register
            var resp = await fetch(API + 'auth/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': ynjData.nonce },
                body: JSON.stringify({ name: name, email: email, password: password, phone: phone, mosque_slug: slug })
            });
            var data = await resp.json();

            if (!data.ok || !data.token) {
                errEl.textContent = data.error || <?php echo wp_json_encode( __( 'Registration failed. Please try again.', 'yourjannah' ) ); ?>;
                btn.disabled = false;
                updateCreateBtn();
                return;
            }

            // 2. Store token
            localStorage.setItem('ynj_user_token', data.token);
            if (data.user) localStorage.setItem('ynj_user', JSON.stringify(data.user));

            // 3. Set WP session
            await fetch(adminAjax, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=ynj_set_session&wp_user_id=' + (data.wp_user_id || '')
            }).catch(function(){});

            // 4. Show check-email message then redirect
            var emailMsg = document.getElementById('step2-error');
            emailMsg.style.color = '#166534';
            emailMsg.innerHTML = '<?php echo esc_js( __( '✅ Account created! We\'ve emailed your password to', 'yourjannah' ) ); ?> <strong>' + email + '</strong>. <?php echo esc_js( __( 'Check your spam folder and mark as Not Spam.', 'yourjannah' ) ); ?>';
            btn.textContent = '<?php echo esc_js( __( 'Account Created ✓', 'yourjannah' ) ); ?>';
            btn.style.background = '#166534';

            if (selectedTier === 'free') {
                setTimeout(function() {
                    window.location.href = slug ? homeUrl + 'mosque/' + slug : homeUrl;
                }, 4000);
                return;
            }

            // 5. Patron tier: checkout
            btn.textContent = <?php echo wp_json_encode( __( 'Redirecting to checkout...', 'yourjannah' ) ); ?>;

            var checkResp = await fetch(API + 'patrons/checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + data.token },
                body: JSON.stringify({ mosque_slug: slug, tier: selectedTier })
            });
            var checkData = await checkResp.json();

            if (checkData.ok && checkData.checkout_url) {
                window.location.href = checkData.checkout_url;
            } else {
                errEl.textContent = checkData.error || <?php echo wp_json_encode( __( 'Checkout error. Your account was created — try upgrading from your profile.', 'yourjannah' ) ); ?>;
                btn.disabled = false;
                updateCreateBtn();
            }
        } catch(e) {
            errEl.textContent = <?php echo wp_json_encode( __( 'Network error. Please try again.', 'yourjannah' ) ); ?>;
            btn.disabled = false;
            updateCreateBtn();
        }
    });
})();
</script>
<?php
get_footer();
