<?php
/**
 * Cart Drawer — slide-in side panel showing basket items.
 * Rendered after HUD bar. Toggled by ynjCartDrawerToggle().
 *
 * @package YNJ_HUD
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!-- Cart Drawer Backdrop -->
<div class="ynj-cart-backdrop" id="ynj-cart-backdrop" onclick="ynjCartDrawerToggle()" style="display:none;"></div>

<!-- Cart Drawer Panel -->
<div class="ynj-cart-drawer" id="ynj-cart-drawer">

    <!-- Header -->
    <div class="ynj-cart-drawer__header">
        <h3>Your Cart (<span id="ynj-cart-count">0</span>)</h3>
        <button type="button" class="ynj-cart-drawer__close" onclick="ynjCartDrawerToggle()">&times;</button>
    </div>

    <!-- Empty state -->
    <div class="ynj-cart-drawer__empty" id="ynj-cart-empty">
        <div style="font-size:40px;margin-bottom:12px;">&#x1F6D2;</div>
        <p>Your cart is empty</p>
        <p style="font-size:12px;color:#999;">Browse your masjid to add items</p>
    </div>

    <!-- Items (JS-rendered) -->
    <div class="ynj-cart-drawer__items" id="ynj-cart-items" style="display:none;"></div>

    <!-- Subtotal -->
    <div class="ynj-cart-drawer__subtotal" id="ynj-cart-subtotal" style="display:none;">
        <span>Due today:</span>
        <span id="ynj-cart-subtotal-amount">&pound;0.00</span>
    </div>

    <!-- Checkout CTA -->
    <div class="ynj-cart-drawer__cta" id="ynj-cart-cta" style="display:none;">
        <a href="<?php echo esc_url( home_url( '/checkout/' ) ); ?>" class="ynj-cart-drawer__btn">
            Checkout &rarr;
        </a>
    </div>

</div>
