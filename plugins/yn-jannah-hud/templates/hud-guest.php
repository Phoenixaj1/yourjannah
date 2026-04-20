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
    <a href="#" onclick="if(typeof ynjAuthModalOpen==='function'){ynjAuthModalOpen();}return false;" style="color:rgba(255,255,255,.55);text-decoration:none;font-size:11px;font-weight:600;white-space:nowrap;padding:6px 12px;border:1px solid rgba(255,255,255,.15);border-radius:20px;transition:all .2s;letter-spacing:.3px;" onmouseover="this.style.borderColor='rgba(255,255,255,.35)';this.style.color='#fff'" onmouseout="this.style.borderColor='rgba(255,255,255,.15)';this.style.color='rgba(255,255,255,.55)'"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
    <a href="#" onclick="if(typeof ynjAuthModalOpen==='function'){ynjAuthModalOpen();}return false;" style="display:inline-flex;align-items:center;gap:5px;padding:7px 18px;background:linear-gradient(135deg,#00ADEF,#0088cc);border:none;border-radius:20px;color:#fff;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap;letter-spacing:.3px;transition:all .2s;box-shadow:0 2px 12px rgba(0,173,239,.25);" onmouseover="this.style.boxShadow='0 4px 18px rgba(0,173,239,.4)';this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,173,239,.25)';this.style.transform=''">☪ <?php esc_html_e( 'Join Community', 'yourjannah' ); ?></a>
    <div style="flex:1;"></div>
    <div style="text-align:right;">
        <div style="font-size:16px;font-family:'Amiri','Traditional Arabic',serif;color:#fff;font-weight:700;letter-spacing:.5px;line-height:1.2;">السلام عليكم</div>
        <div style="font-size:9px;color:rgba(255,255,255,.45);font-weight:600;">Peace &amp; Blessings on You</div>
    </div>
</div>
</div>
