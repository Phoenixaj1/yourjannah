<?php
/**
 * Guest HUD — Sign In + Join bar.
 *
 * @package YNJ_HUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="ynj-hud-wrap">
<div class="ynj-hud" id="ynj-hud">
    <span style="font-size:14px;">&#x1F54C;</span>
    <span style="font-size:13px;font-weight:700;color:#fff;"><?php esc_html_e( 'YourJannah', 'yourjannah' ); ?></span>
    <div style="flex:1;"></div>
    <button type="button" class="ynj-hud__cart" id="hud-cart-btn"
            onclick="window.location.href='<?php echo esc_url( home_url( '/checkout/' ) ); ?>'" style="display:none;">
        &#x1F6D2;<span class="ynj-hud__cart-badge" id="hud-cart-badge">0</span>
    </button>
    <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" onclick="var m=document.getElementById('ynj-onboard')||document.getElementById('ynj-join-modal');if(m){m.style.display='flex';return false;}" style="color:rgba(255,255,255,.6);text-decoration:none;font-size:12px;font-weight:600;white-space:nowrap;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
    <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" onclick="var m=document.getElementById('ynj-onboard')||document.getElementById('ynj-join-modal');if(m){m.style.display='flex';return false;}" style="display:inline-flex;align-items:center;gap:4px;padding:5px 14px;background:linear-gradient(135deg,#287e61,#1a5c43);border:none;border-radius:10px;color:#fff;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;box-shadow:0 2px 10px rgba(40,126,97,.3);"><?php esc_html_e( 'Join', 'yourjannah' ); ?></a>
</div>
</div>
