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
    <a href="#" onclick="if(typeof ynjAuthModalOpen==='function'){ynjAuthModalOpen();}return false;" style="color:rgba(255,255,255,.6);text-decoration:none;font-size:12px;font-weight:600;white-space:nowrap;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
    <a href="#" onclick="if(typeof ynjAuthModalOpen==='function'){ynjAuthModalOpen();}return false;" style="display:inline-flex;align-items:center;gap:4px;padding:5px 14px;background:linear-gradient(135deg,#287e61,#1a5c43);border:none;border-radius:10px;color:#fff;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;box-shadow:0 2px 10px rgba(40,126,97,.3);"><?php esc_html_e( 'Join', 'yourjannah' ); ?></a>
    <div style="flex:1;"></div>
    <div style="text-align:right;">
        <div style="font-size:16px;font-family:'Amiri','Traditional Arabic',serif;color:#fff;font-weight:700;letter-spacing:.5px;line-height:1.2;">السلام عليكم</div>
        <div style="font-size:9px;color:rgba(255,255,255,.45);font-weight:600;">Peace &amp; Blessings on You</div>
    </div>
</div>
</div>
