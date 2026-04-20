<?php
/**
 * Auth Modal — Blue onboard modal with GPS, email, PIN flow.
 * Rendered globally via HUD plugin. Available on all pages.
 *
 * Trigger: ynjAuthModalOpen()  /  Close: ynjAuthModalClose()
 *
 * @package YNJ_HUD
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) return; // Only for guests
?>
<div id="ynj-onboard" style="display:none;position:fixed;inset:0;z-index:10005;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);overflow-y:auto;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)ynjAuthModalClose()">
    <div style="max-width:420px;width:100%;background:linear-gradient(180deg,#0a1628 0%,#1a3a5c 60%,#00ADEF 100%);color:#fff;border-radius:24px;padding:36px 28px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);position:relative;">
        <button type="button" onclick="ynjAuthModalClose()" style="position:absolute;top:12px;right:14px;background:none;border:none;font-size:22px;color:rgba(255,255,255,.5);cursor:pointer;">&times;</button>
        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/logo2.png' ); ?>" alt="YourJannah" style="height:40px;width:auto;margin:0 auto 12px;">
        <h1 style="font-size:20px;font-weight:800;margin-bottom:4px;"><?php esc_html_e( 'Join Your Masjid Community', 'yourjannah' ); ?></h1>
        <p style="font-size:13px;opacity:.6;margin-bottom:20px;">Prayer times, events &amp; community &mdash; all in one place</p>

        <!-- Mosque list: auto-loads from GPS -->
        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:600;opacity:.7;display:block;margin-bottom:6px;">Select Your Masjid</label>
            <div id="ob-mosque-list" style="text-align:left;max-height:200px;overflow-y:auto;margin-bottom:8px;">
                <div style="padding:16px;text-align:center;">
                    <div style="display:inline-block;width:20px;height:20px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:ob-spin 0.6s linear infinite;"></div>
                    <div style="font-size:13px;opacity:.6;margin-top:8px;">Finding mosques near you...</div>
                </div>
            </div>
            <input type="text" id="ob-search-input" placeholder="&#x1F50D; Search mosque by name..." oninput="obSearchMosques(this.value)" style="width:100%;padding:10px 14px;border:1px solid rgba(255,255,255,.3);border-radius:10px;background:rgba(255,255,255,.15);color:#fff;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
        </div>
        <style>@keyframes ob-spin{to{transform:rotate(360deg);}}.ob-search-ph::placeholder{color:rgba(255,255,255,.4);}</style>

        <!-- Email -->
        <div id="ob-email-row" style="text-align:left;margin-bottom:14px;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Your Email', 'yourjannah' ); ?></label>
            <input type="email" id="ob-email" placeholder="your@email.com" autocomplete="email" style="width:100%;padding:13px 16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:16px;font-family:inherit;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='rgba(255,255,255,.6)'" onblur="this.style.borderColor='rgba(255,255,255,.35)'">
        </div>

        <!-- PIN (existing user) -->
        <div id="ob-pin-row" style="display:none;margin-bottom:14px;text-align:left;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Enter your PIN', 'yourjannah' ); ?></label>
            <input type="tel" id="ob-pin" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="&#x2022; &#x2022; &#x2022; &#x2022;" autocomplete="off" style="width:100%;padding:16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:32px;font-weight:900;letter-spacing:14px;text-align:center;font-family:inherit;outline:none;box-sizing:border-box;">
            <a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" style="font-size:11px;color:rgba(255,255,255,.45);margin-top:5px;display:block;"><?php esc_html_e( 'Forgot PIN?', 'yourjannah' ); ?></a>
        </div>

        <!-- Create PIN (new user) -->
        <div id="ob-newpin-row" style="display:none;margin-bottom:14px;text-align:left;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Choose a 4-digit PIN', 'yourjannah' ); ?></label>
            <input type="tel" id="ob-newpin" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="&#x2022; &#x2022; &#x2022; &#x2022;" autocomplete="off" style="width:100%;padding:16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:32px;font-weight:900;letter-spacing:14px;text-align:center;font-family:inherit;outline:none;box-sizing:border-box;margin-bottom:10px;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:5px;"><?php esc_html_e( 'Confirm PIN', 'yourjannah' ); ?></label>
            <input type="tel" id="ob-newpin2" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="&#x2022; &#x2022; &#x2022; &#x2022;" autocomplete="off" style="width:100%;padding:16px;border:1.5px solid rgba(255,255,255,.35);border-radius:10px;background:rgba(255,255,255,.12);color:#fff;font-size:32px;font-weight:900;letter-spacing:14px;text-align:center;font-family:inherit;outline:none;box-sizing:border-box;">
        </div>

        <!-- Action button -->
        <button id="ob-submit" style="display:none;width:100%;padding:14px;border:none;border-radius:12px;background:#fff;color:#0a1628;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;"></button>
        <p id="ob-error" style="color:#fca5a5;font-size:13px;text-align:center;margin-top:8px;"></p>

        <!-- CTA buttons -->
        <div id="ob-cta-buttons" style="margin-top:4px;">
            <div style="display:flex;gap:8px;margin-bottom:10px;">
                <button onclick="obStartSignIn()" style="flex:1;padding:14px;border:none;border-radius:12px;background:#fff;color:#0a1628;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></button>
                <button onclick="obStartSignUp()" style="flex:1;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#287e61,#1a5c43);color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;"><?php esc_html_e( 'Sign Up', 'yourjannah' ); ?></button>
            </div>
            <div style="text-align:center;">
                <a href="#" onclick="ynjAuthModalClose();return false;" style="font-size:13px;color:rgba(255,255,255,.45);text-decoration:none;"><?php esc_html_e( 'Continue as guest', 'yourjannah' ); ?></a>
            </div>
        </div>
    </div>
</div>
