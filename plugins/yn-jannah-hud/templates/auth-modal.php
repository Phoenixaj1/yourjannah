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
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:8px;"><?php esc_html_e( 'Enter your PIN', 'yourjannah' ); ?></label>
            <div style="display:flex;gap:10px;justify-content:center;">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-pin" data-idx="0" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-pin" data-idx="1" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-pin" data-idx="2" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-pin" data-idx="3" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
            </div>
            <input type="hidden" id="ob-pin">
            <a href="<?php echo esc_url( home_url( '/forgot-password' ) ); ?>" style="font-size:11px;color:rgba(255,255,255,.45);margin-top:8px;display:block;text-align:center;"><?php esc_html_e( 'Forgot PIN?', 'yourjannah' ); ?></a>
        </div>

        <!-- Create PIN (new user) -->
        <div id="ob-newpin-row" style="display:none;margin-bottom:14px;text-align:left;">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:8px;"><?php esc_html_e( 'Choose a 4-digit PIN', 'yourjannah' ); ?></label>
            <div style="display:flex;gap:10px;justify-content:center;margin-bottom:12px;">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin" data-idx="0" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin" data-idx="1" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin" data-idx="2" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin" data-idx="3" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
            </div>
            <input type="hidden" id="ob-newpin">
            <label style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);display:block;margin-bottom:8px;"><?php esc_html_e( 'Confirm PIN', 'yourjannah' ); ?></label>
            <div style="display:flex;gap:10px;justify-content:center;">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin2" data-idx="0" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin2" data-idx="1" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin2" data-idx="2" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
                <input type="tel" inputmode="numeric" maxlength="1" class="ob-pin-box" data-pin="ob-newpin2" data-idx="3" style="width:56px;height:64px;border:2px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1);color:#fff;font-size:28px;font-weight:900;text-align:center;font-family:inherit;outline:none;" onfocus="this.style.borderColor='#00ADEF';this.style.background='rgba(0,173,239,.15)'" onblur="this.style.borderColor='rgba(255,255,255,.3)';this.style.background='rgba(255,255,255,.1)'">
            </div>
            <input type="hidden" id="ob-newpin2">
        </div>

        <!-- PIN box auto-advance + auto-process JS -->
        <script>
        function obPinAutoProcess(pinId, currentBox) {
            if (pinId === 'ob-pin' || pinId === 'ob-newpin2') {
                if (currentBox) currentBox.blur();
                if (typeof window.obSubmitEmail === 'function') window.obSubmitEmail();
            } else if (pinId === 'ob-newpin') {
                var confirmFirst = document.querySelector('.ob-pin-box[data-pin="ob-newpin2"][data-idx="0"]');
                if (confirmFirst) confirmFirst.focus();
            }
        }
        document.querySelectorAll('.ob-pin-box').forEach(function(box){
            box.addEventListener('input',function(){
                var v = this.value.replace(/\D/g,'');
                this.value = v.slice(0,1);
                // Sync to hidden field
                var pinId = this.dataset.pin;
                var boxes = document.querySelectorAll('.ob-pin-box[data-pin="'+pinId+'"]');
                var combined = '';
                boxes.forEach(function(b){ combined += b.value; });
                document.getElementById(pinId).value = combined;
                // Auto-advance
                if (v && parseInt(this.dataset.idx) < 3) {
                    var next = document.querySelector('.ob-pin-box[data-pin="'+pinId+'"][data-idx="'+(parseInt(this.dataset.idx)+1)+'"]');
                    if (next) next.focus();
                } else if (v && combined.length === 4) {
                    obPinAutoProcess(pinId, this);
                }
            });
            box.addEventListener('keydown',function(e){
                if (e.key === 'Backspace' && !this.value && parseInt(this.dataset.idx) > 0) {
                    var prev = document.querySelector('.ob-pin-box[data-pin="'+this.dataset.pin+'"][data-idx="'+(parseInt(this.dataset.idx)-1)+'"]');
                    if (prev) { prev.focus(); prev.value = ''; }
                }
            });
            // Prevent pasting more than 1 digit per box (handle full paste into first box)
            box.addEventListener('paste',function(e){
                e.preventDefault();
                var paste = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,4);
                var pinId = this.dataset.pin;
                var boxes = document.querySelectorAll('.ob-pin-box[data-pin="'+pinId+'"]');
                for(var i=0;i<paste.length&&i<4;i++){ boxes[i].value=paste[i]; }
                var combined=''; boxes.forEach(function(b){combined+=b.value;});
                document.getElementById(pinId).value=combined;
                if(paste.length>=4) obPinAutoProcess(pinId, boxes[3]);
                else if(boxes[paste.length]) boxes[paste.length].focus();
            });
        });
        </script>

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
