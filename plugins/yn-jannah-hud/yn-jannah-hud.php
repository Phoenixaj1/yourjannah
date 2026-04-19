<?php
/**
 * Plugin Name: YourJannah — HUD
 * Description: The sticky header bar (guest + member), mosque selector modal, dhikr/league/info popups.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core plugin for YNJ_DB)
 *
 * @package YNJ_HUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_HUD_VERSION', '1.0.0' );
define( 'YNJ_HUD_DIR', plugin_dir_path( __FILE__ ) );
define( 'YNJ_HUD_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>YourJannah HUD</strong> requires the <strong>YourJannah</strong> core plugin.</p></div>';
        } );
        return;
    }

    require_once YNJ_HUD_DIR . 'inc/class-ynj-hud.php';

    // ── Render HUD immediately after <body> opens ──
    add_action( 'wp_body_open', [ 'YNJ_HUD', 'render' ], 5 );

    // ── Enqueue assets ──
    add_action( 'wp_enqueue_scripts', function() {
        wp_enqueue_style(
            'ynj-hud',
            YNJ_HUD_URL . 'assets/css/hud.css',
            [],
            YNJ_HUD_VERSION
        );

        wp_enqueue_script(
            'ynj-hud',
            YNJ_HUD_URL . 'assets/js/hud.js',
            [],
            YNJ_HUD_VERSION,
            true
        );

        wp_enqueue_script(
            'ynj-mosque-modal',
            YNJ_HUD_URL . 'assets/js/mosque-modal.js',
            [],
            YNJ_HUD_VERSION,
            true
        );

        // Pass HUD data to JS
        $hud_data = YNJ_HUD::get_js_data();
        wp_localize_script( 'ynj-hud', 'ynjHudData', $hud_data );

        // Cart badge + drawer — syncs HUD badge and renders side cart
        wp_add_inline_script( 'ynj-hud', '
(function(){
    var typeIcons={donation:"\uD83D\uDC9D",sadaqah:"\uD83D\uDCB0",patron:"\uD83C\uDFC5",tip:"\uD83E\uDD32",
        sponsor:"\u2B50",business_sponsor:"\u2B50",store:"\uD83C\uDF81",event_ticket:"\uD83C\uDFAB",
        event_donation:"\u2764\uFE0F",room_booking:"\uD83C\uDFE0",class_enrolment:"\uD83D\uDCDA",
        service:"\uD83D\uDD27",professional_service:"\uD83D\uDD27"};

    function updateCartBadge(){
        var btn=document.getElementById("hud-cart-btn");
        var badge=document.getElementById("hud-cart-badge");
        if(!btn||!badge||typeof ynjBasket==="undefined") return;
        var c=ynjBasket.getCount();
        badge.textContent=c;
        btn.style.display=c>0?"":"none";
    }

    function renderDrawerItems(){
        if(typeof ynjBasket==="undefined") return;
        var items=ynjBasket.getItems();
        var $items=document.getElementById("ynj-cart-items");
        var $empty=document.getElementById("ynj-cart-empty");
        var $sub=document.getElementById("ynj-cart-subtotal");
        var $cta=document.getElementById("ynj-cart-cta");
        var $count=document.getElementById("ynj-cart-count");
        if(!$items) return;

        $count.textContent=items.length;

        if(!items.length){
            $items.style.display="none"; $empty.style.display="";
            $sub.style.display="none"; $cta.style.display="none";
            return;
        }
        $empty.style.display="none"; $items.style.display="";
        $sub.style.display=""; $cta.style.display="";

        var html="";
        items.forEach(function(it){
            var icon=typeIcons[it.item_type]||"\uD83D\uDCCB";
            var lbl=it.item_label||it.item_type.replace(/_/g," ");
            var freq=it.frequency&&it.frequency!=="once"?(" / "+it.frequency):"";
            html+="<div class=\"ynj-cart-drawer__item\">";
            html+="<span class=\"ynj-cart-drawer__item-icon\">"+icon+"</span>";
            html+="<div class=\"ynj-cart-drawer__item-info\">";
            html+="<div class=\"ynj-cart-drawer__item-label\">"+esc(lbl)+"</div>";
            if(it.mosque_name) html+="<div class=\"ynj-cart-drawer__item-sub\">"+esc(it.mosque_name)+freq+"</div>";
            else if(freq) html+="<div class=\"ynj-cart-drawer__item-sub\">"+freq.slice(3)+"</div>";
            html+="</div>";
            html+="<span class=\"ynj-cart-drawer__item-price\">\u00A3"+(it.amount_pence/100).toFixed(2)+"</span>";
            html+="<button type=\"button\" class=\"ynj-cart-drawer__item-remove\" data-rid=\""+it.id+"\">\u00D7</button>";
            html+="</div>";
        });
        $items.innerHTML=html;

        // Subtotal
        document.getElementById("ynj-cart-subtotal-amount").textContent="\u00A3"+(ynjBasket.getSubtotal()/100).toFixed(2);

        // Bind removes
        $items.querySelectorAll("[data-rid]").forEach(function(b){
            b.addEventListener("click",function(){
                ynjBasket.removeItem(this.dataset.rid);
                renderDrawerItems();
            });
        });
    }

    function esc(s){if(!s)return"";var d=document.createElement("div");d.appendChild(document.createTextNode(s));return d.innerHTML;}

    // Toggle drawer
    window.ynjCartDrawerToggle=function(){
        var drawer=document.getElementById("ynj-cart-drawer");
        var backdrop=document.getElementById("ynj-cart-backdrop");
        if(!drawer) return;
        var open=drawer.classList.toggle("ynj-cart-drawer--open");
        backdrop.style.display=open?"":"none";
        document.body.style.overflow=open?"hidden":"";
        if(open) renderDrawerItems();
    };

    document.addEventListener("ynjBasketUpdated",function(e){
        updateCartBadge();
        renderDrawerItems();
        var btn=document.getElementById("hud-cart-btn");
        if(btn&&e.detail&&e.detail.action==="add"){
            btn.classList.remove("ynj-hud__cart--bounce");
            void btn.offsetWidth;
            btn.classList.add("ynj-hud__cart--bounce");
            // Auto-open drawer on add
            var drawer=document.getElementById("ynj-cart-drawer");
            if(drawer&&!drawer.classList.contains("ynj-cart-drawer--open")){
                ynjCartDrawerToggle();
            }
        }
    });

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",updateCartBadge);
    } else {
        updateCartBadge();
    }
})();
' );
    } );

}, 20 ); // After core (10) and gamification (15)
