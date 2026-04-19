/**
 * YourJannah Unified Checkout — multi-item basket checkout.
 *
 * Reads items from ynjBasket (ynj-basket.js) and renders the checkout experience.
 * Handles: item-type renderers, Stripe Elements, split-mode (one-off + recurring).
 *
 * Expects global: ynjCheckoutData { apiUrl, stripePk, mosqueId, mosqueName, userEmail, userName, homeUrl, funds:[] }
 *
 * @package YNJ_Unified_Checkout
 */
(function () {
    'use strict';

    var CFG = window.ynjCheckoutData || {};
    var API = CFG.apiUrl || '/wp-json/ynj/v1/';
    var PK  = CFG.stripePk || '';
    var FUNDS = CFG.funds || [];

    var stripe, elements, paymentElement;
    var tipPercent = 5;
    var txnId = '';
    var processing = false;

    // ── DOM refs ──
    var $cartItems, $detailsCard, $tipCard, $summaryCard, $paymentCard, $continueBtn, $payBtn, $errorEl;

    // ════════════════════════════════════════════
    //  URL-PARAM BACKWARDS COMPATIBILITY
    // ════════════════════════════════════════════

    function handleUrlParams() {
        var params = new URLSearchParams(window.location.search);
        var type = params.get('type');
        if (!type) return;

        var item = {
            item_type:    type,
            item_id:      parseInt(params.get('item_id') || '0', 10),
            item_label:   params.get('label') || '',
            mosque_id:    parseInt(params.get('mosque_id') || '0', 10),
            mosque_name:  CFG.mosqueName || '',
            amount_pence: parseInt(params.get('amount') || '0', 10),
            fund_type:    params.get('fund') || 'general',
            frequency:    params.get('frequency') || 'once'
        };

        // Don't add duplicates
        if (!ynjBasket.hasItem(item)) {
            ynjBasket.addItem(item);
        }

        // Clean URL
        history.replaceState(null, '', window.location.pathname);
    }

    // ════════════════════════════════════════════
    //  ITEM RENDERERS
    // ════════════════════════════════════════════

    function typeLabel(t) {
        var labels = {
            donation: 'Donation', sadaqah: 'Sadaqah', patron: 'Patron',
            tip: 'Support YJ', sponsor: 'Sponsor', business_sponsor: 'Sponsor',
            store: 'Store', event_ticket: 'Event Ticket', event_donation: 'Event Donation',
            room_booking: 'Room Booking', class_enrolment: 'Class', service: 'Service',
            professional_service: 'Service', platform_donate: 'Platform'
        };
        return labels[t] || t.replace(/_/g, ' ');
    }

    function freqLabel(f) {
        if (!f || f === 'once') return '';
        if (f === 'weekly') return '/week';
        if (f === 'monthly') return '/month';
        if (f === 'daily') return '/day';
        return '/' + f;
    }

    function pence(p) {
        return '\u00A3' + (p / 100).toFixed(2);
    }

    function renderCartItem(item) {
        var isRecurring = item.frequency && item.frequency !== 'once';
        var typeClass = 'uc-cart-item--' + item.item_type;
        var freqBadge = isRecurring ? ' uc-cart-item__type--recurring' : '';

        var html = '<div class="uc-cart-item ' + typeClass + '" data-cart-id="' + item.id + '">';
        html += '<div class="uc-cart-item__header">';
        html += '<span class="uc-cart-item__type' + freqBadge + '">' + esc(typeLabel(item.item_type));
        if (isRecurring) html += ' &bull; ' + esc(item.frequency);
        html += '</span>';
        html += '<button type="button" class="uc-cart-item__remove" data-remove="' + item.id + '" title="Remove">&times;</button>';
        html += '</div>';

        html += '<div class="uc-cart-item__label">' + esc(item.item_label || typeLabel(item.item_type)) + '</div>';
        if (item.mosque_name) {
            html += '<div class="uc-cart-item__mosque">' + esc(item.mosque_name) + '</div>';
        }

        html += '<div class="uc-cart-item__amount">' + pence(item.amount_pence);
        if (isRecurring) html += '<span class="uc-cart-item__freq">' + freqLabel(item.frequency) + '</span>';
        html += '</div>';

        // Type-specific fields
        html += renderTypeFields(item);

        html += '</div>';
        return html;
    }

    function renderTypeFields(item) {
        var html = '';

        switch (item.item_type) {
            case 'donation':
            case 'sadaqah':
                if (FUNDS.length > 1) {
                    html += '<div class="uc-cart-item__field">';
                    html += '<label>Fund</label>';
                    html += '<select data-field="fund_type" data-cart-id="' + item.id + '">';
                    for (var i = 0; i < FUNDS.length; i++) {
                        var sel = FUNDS[i].slug === item.fund_type ? ' selected' : '';
                        html += '<option value="' + esc(FUNDS[i].slug) + '"' + sel + '>' + esc(FUNDS[i].label) + '</option>';
                    }
                    html += '</select></div>';
                }
                break;

            case 'store':
                var msg = (item.meta && item.meta.message) || '';
                html += '<div class="uc-cart-item__field">';
                html += '<label>Personal message (optional)</label>';
                html += '<textarea data-field="meta.message" data-cart-id="' + item.id + '" rows="2" placeholder="Add a message...">' + esc(msg) + '</textarea>';
                html += '</div>';
                html += '<div class="uc-cart-item__meta" style="color:#16a34a;">&#x1F54C; 95% goes directly to the masjid</div>';
                break;

            case 'patron':
                var tier = (item.meta && item.meta.tier) || 'supporter';
                html += '<div class="uc-cart-item__meta">Tier: <strong>' + esc(tier.charAt(0).toUpperCase() + tier.slice(1)) + '</strong></div>';
                break;

            case 'event_ticket':
            case 'event_donation':
                if (item.meta && item.meta.event_date) {
                    html += '<div class="uc-cart-item__meta">Date: ' + esc(item.meta.event_date) + '</div>';
                }
                break;

            case 'room_booking':
                if (item.meta && item.meta.booking_date) {
                    html += '<div class="uc-cart-item__meta">Date: ' + esc(item.meta.booking_date) + '</div>';
                }
                if (item.meta && item.meta.time_slot) {
                    html += '<div class="uc-cart-item__meta">Time: ' + esc(item.meta.time_slot) + '</div>';
                }
                break;

            case 'class_enrolment':
                if (item.meta && item.meta.class_name) {
                    html += '<div class="uc-cart-item__meta">Class: ' + esc(item.meta.class_name) + '</div>';
                }
                break;
        }

        return html;
    }

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    // ════════════════════════════════════════════
    //  RENDER FUNCTIONS
    // ════════════════════════════════════════════

    function renderItems() {
        var items = ynjBasket.getItems();
        if (!items.length) {
            showEmptyState();
            return;
        }

        var html = '';
        for (var i = 0; i < items.length; i++) {
            html += renderCartItem(items[i]);
        }
        $cartItems.innerHTML = html;

        // Show details + tip + summary
        $detailsCard.style.display = '';
        $tipCard.style.display = '';
        $summaryCard.style.display = '';
        $continueBtn.style.display = '';

        // Bind remove buttons
        $cartItems.querySelectorAll('.uc-cart-item__remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ynjBasket.removeItem(this.dataset.remove);
                renderItems();
                updateSummary();
            });
        });

        // Bind editable fields
        $cartItems.querySelectorAll('[data-field]').forEach(function (el) {
            el.addEventListener('change', function () {
                var cartId = this.dataset.cartId;
                var field = this.dataset.field;
                var val = this.value;

                if (field.startsWith('meta.')) {
                    var metaKey = field.split('.')[1];
                    var item = findItem(cartId);
                    if (item) {
                        var meta = item.meta || {};
                        meta[metaKey] = val;
                        ynjBasket.updateItem(cartId, { meta: meta });
                    }
                } else {
                    var update = {};
                    update[field] = val;
                    ynjBasket.updateItem(cartId, update);
                }
            });
        });

        // Show split notice if mixed
        var splitNotice = document.getElementById('uc-split-notice');
        if (splitNotice) {
            splitNotice.style.display = ynjBasket.isMixed() ? '' : 'none';
        }

        updateSummary();
    }

    function findItem(cartId) {
        var items = ynjBasket.getItems();
        for (var i = 0; i < items.length; i++) {
            if (items[i].id === cartId) return items[i];
        }
        return null;
    }

    function showEmptyState() {
        var mosqueId = CFG.mosqueId || 0;
        $cartItems.innerHTML =
            '<div class="uc-empty">' +
            '<div class="uc-empty__icon">&#x1F6D2;</div>' +
            '<div class="uc-empty__text">Your cart is empty</div>' +
            '<div class="uc-empty-grid">' +
            emptyItem('donation', mosqueId, 'Donation', '&#x1F49D;', 'general', '') +
            emptyItem('sadaqah', mosqueId, 'Sadaqah', '&#x1F4B0;', 'sadaqah', '') +
            emptyItem('patron', mosqueId, 'Become Patron', '&#x1F3C5;', '', 'monthly') +
            emptyItem('tip', 0, 'Support YJ', '&#x1F932;', '', '') +
            '</div></div>';

        $detailsCard.style.display = 'none';
        $tipCard.style.display = 'none';
        $summaryCard.style.display = 'none';
        $continueBtn.style.display = 'none';
        $paymentCard.style.display = 'none';
    }

    function emptyItem(type, mosqueId, label, icon, fund, freq) {
        var params = '?type=' + type + '&mosque_id=' + mosqueId + '&label=' + encodeURIComponent(label);
        if (fund) params += '&fund=' + fund;
        if (freq) params += '&frequency=' + freq;
        return '<a href="' + params + '" class="uc-empty-item"><span>' + icon + '</span><strong>' + esc(label) + '</strong></a>';
    }

    // ════════════════════════════════════════════
    //  SUMMARY
    // ════════════════════════════════════════════

    function getTipPence() {
        return Math.round(ynjBasket.getSubtotal() * tipPercent / 100);
    }

    function getTotalPence() {
        return ynjBasket.getSubtotal() + getTipPence();
    }

    function updateSummary() {
        var items = ynjBasket.getItems();
        var $summary = document.getElementById('uc-summary-lines');
        if (!$summary) return;

        var html = '';
        for (var i = 0; i < items.length; i++) {
            var lbl = items[i].item_label || typeLabel(items[i].item_type);
            html += '<div class="uc-summary-row"><span>' + esc(lbl) + '</span><span>' + pence(items[i].amount_pence) + '</span></div>';
        }
        html += '<div class="uc-summary-row"><span>YourJannah tip</span><span id="uc-sum-tip">' + pence(getTipPence()) + '</span></div>';
        html += '<div class="uc-summary-total"><span>Total</span><span id="uc-sum-total">' + pence(getTotalPence()) + '</span></div>';
        $summary.innerHTML = html;

        // Update pay button text
        if ($payBtn) {
            var groups = ynjBasket.groupByFrequency();
            var label = '\uD83E\uDD32 Pay ' + pence(getTotalPence());
            if (groups.recurring.length && !groups.once.length) {
                label += freqLabel(groups.recurring[0].frequency);
            }
            $payBtn.textContent = label;
        }
    }

    // ════════════════════════════════════════════
    //  PAYMENT FLOW
    // ════════════════════════════════════════════

    function continueToPayment() {
        var email = document.getElementById('uc-email').value.trim();
        if (!email || email.indexOf('@') < 0) {
            document.getElementById('uc-email').style.borderColor = '#dc2626';
            document.getElementById('uc-email').focus();
            return;
        }

        var items = ynjBasket.getItems();
        if (!items.length) return;

        for (var i = 0; i < items.length; i++) {
            if (items[i].amount_pence < 100) {
                alert('Each item must be at least \u00A31. Please check your amounts.');
                return;
            }
        }

        if (processing) return;
        processing = true;
        $continueBtn.disabled = true;
        $continueBtn.textContent = 'Setting up payment...';

        var payload = {
            email: email,
            name: document.getElementById('uc-name').value.trim(),
            tip_pence: getTipPence(),
            items: ynjBasket.toApiPayload(),
            source: 'checkout_page'
        };

        fetch(API + 'unified-checkout/create-intent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            processing = false;

            if (!data.ok) {
                $continueBtn.disabled = false;
                $continueBtn.textContent = 'Continue to Payment \u2192';
                alert(data.error || 'Failed to set up payment');
                return;
            }

            if (data.mode === 'redirect') {
                // All recurring: redirect to Stripe Checkout
                ynjBasket.clear();
                window.location.href = data.url;
                return;
            }

            if (data.mode === 'split') {
                // Mixed: handle one-off first, then redirect for recurring
                txnId = data.one_off.transaction_id;
                mountStripeElements(data.one_off.client_secret);
                // Store recurring URL for after one-off completes
                window._ynjRecurringUrl = data.recurring.url;
                window._ynjRecurringTxnId = data.recurring.transaction_id;
                return;
            }

            // All one-off: mount inline Stripe Elements
            txnId = data.transaction_id;
            mountStripeElements(data.client_secret);
        })
        .catch(function () {
            processing = false;
            $continueBtn.disabled = false;
            $continueBtn.textContent = 'Continue to Payment \u2192';
            alert('Network error. Please try again.');
        });
    }

    function mountStripeElements(clientSecret) {
        $continueBtn.style.display = 'none';
        $paymentCard.style.display = '';
        updateSummary();

        stripe = Stripe(PK);
        elements = stripe.elements({
            clientSecret: clientSecret,
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#287e61',
                    fontFamily: 'Inter, system-ui, sans-serif',
                    borderRadius: '10px'
                }
            }
        });
        paymentElement = elements.create('payment', { layout: 'tabs' });
        paymentElement.mount('#uc-payment-element');
        paymentElement.on('change', function (e) {
            $payBtn.disabled = !e.complete;
            if (e.error) {
                $errorEl.textContent = e.error.message;
                $errorEl.style.display = '';
            } else {
                $errorEl.style.display = 'none';
            }
        });
    }

    function payNow() {
        if (processing) return;
        processing = true;
        $payBtn.disabled = true;
        $payBtn.textContent = 'Processing...';
        $errorEl.style.display = 'none';

        stripe.confirmPayment({
            elements: elements,
            confirmParams: { return_url: window.location.origin + '/checkout/?success=1&txn=' + txnId },
            redirect: 'if_required'
        }).then(function (result) {
            if (result.error) {
                $errorEl.textContent = result.error.message;
                $errorEl.style.display = '';
                $payBtn.disabled = false;
                processing = false;
                updateSummary();
                return;
            }

            // Payment succeeded — confirm with backend
            $payBtn.textContent = 'Confirming...';
            fetch(API + 'unified-checkout/confirm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    transaction_id: txnId,
                    payment_intent_id: result.paymentIntent ? result.paymentIntent.id : ''
                })
            }).then(function () {
                ynjBasket.clear();
                // If split mode, redirect to recurring checkout
                if (window._ynjRecurringUrl) {
                    window.location.href = window._ynjRecurringUrl;
                } else {
                    window.location.href = '/checkout/?success=1&txn=' + txnId;
                }
            }).catch(function () {
                ynjBasket.clear();
                window.location.href = '/checkout/?success=1&txn=' + txnId;
            });
        });
    }

    // ════════════════════════════════════════════
    //  INIT
    // ════════════════════════════════════════════

    function init() {
        // Don't init on success page
        if (new URLSearchParams(window.location.search).get('success')) return;

        // Handle URL params (backwards compat)
        handleUrlParams();

        // Get DOM refs
        $cartItems   = document.getElementById('uc-cart-items');
        $detailsCard = document.getElementById('uc-details-card');
        $tipCard     = document.getElementById('uc-tip-card');
        $summaryCard = document.getElementById('uc-summary-card');
        $paymentCard = document.getElementById('uc-payment-card');
        $continueBtn = document.getElementById('uc-continue-btn');
        $payBtn      = document.getElementById('uc-pay-btn');
        $errorEl     = document.getElementById('uc-error');

        if (!$cartItems) return;

        // Pre-fill email/name
        var emailEl = document.getElementById('uc-email');
        var nameEl  = document.getElementById('uc-name');
        if (emailEl && CFG.userEmail) emailEl.value = CFG.userEmail;
        if (nameEl && CFG.userName) nameEl.value = CFG.userName;

        // Tip slider
        var tipRange = document.getElementById('uc-tip-range');
        var tipVal   = document.getElementById('uc-tip-val');
        if (tipRange) {
            tipRange.addEventListener('input', function () {
                tipPercent = parseInt(this.value, 10);
                if (tipVal) tipVal.textContent = tipPercent + '%';
                updateSummary();
            });
        }

        // Continue button
        if ($continueBtn) $continueBtn.addEventListener('click', continueToPayment);

        // Pay button
        if ($payBtn) $payBtn.addEventListener('click', payNow);

        // Initial render
        renderItems();
    }

    // Run
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
