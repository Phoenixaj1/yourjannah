/**
 * YourJannah Unified Checkout — v3.0 Multi-step checkout.
 *
 * Step 1: Review items + contact info
 * Step 2: Support YourJannah (platform tip)
 * Step 3: Payment (Stripe Elements)
 *
 * Reads from ynjBasket. Expects global: ynjCheckoutData
 *
 * @package YNJ_Unified_Checkout
 */
(function () {
    'use strict';

    var CFG = window.ynjCheckoutData || {};
    var API = CFG.apiUrl || '/wp-json/ynj/v1/';
    var PK  = CFG.stripePk || '';
    var FUNDS = CFG.funds || [];

    var currentStep = 1;
    var tipPence = 30; // default: £0.30 minimum
    var causePence = 0;
    var stripe, elements, paymentElement, cardReady = false;
    var txnId = '';
    var processing = false;

    // ════════════════════════════════════════════
    //  URL PARAM BACKWARDS COMPAT
    // ════════════════════════════════════════════

    function handleUrlParams() {
        var params = new URLSearchParams(window.location.search);
        var type = params.get('type');
        if (!type) return;
        var item = {
            item_type: type,
            item_id: parseInt(params.get('item_id') || '0', 10),
            item_label: params.get('label') || '',
            mosque_id: parseInt(params.get('mosque_id') || '0', 10),
            mosque_name: CFG.mosqueName || '',
            amount_pence: parseInt(params.get('amount') || '0', 10),
            fund_type: params.get('fund') || 'general',
            frequency: params.get('frequency') || 'once'
        };
        if (!ynjBasket.hasItem(item)) ynjBasket.addItem(item);
        history.replaceState(null, '', window.location.pathname);
    }

    // ════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function pence(p) { return '\u00A3' + (p / 100).toFixed(2); }

    function typeLabel(t) {
        var m = { donation:'Donation', sadaqah:'Sadaqah', patron:'Patron', tip:'Support YJ',
            sponsor:'Sponsor', business_sponsor:'Sponsor', store:'Store',
            event_ticket:'Event Ticket', event_donation:'Event Donation',
            room_booking:'Room Booking', class_enrolment:'Class', service:'Service',
            professional_service:'Service', platform_donate:'Platform' };
        return m[t] || t.replace(/_/g, ' ');
    }

    function freqLabel(f) {
        if (!f || f === 'once') return '';
        return f === 'weekly' ? '/week' : f === 'monthly' ? '/month' : '/' + f;
    }

    function getSubtotal() { return ynjBasket.getSubtotal(); }
    function getTotal() { return getSubtotal() + tipPence + causePence; }

    // ════════════════════════════════════════════
    //  STEP NAVIGATION
    // ════════════════════════════════════════════

    function goStep(step) {
        if (step < 1 || step > 3) return;

        // Validate before advancing
        if (step > currentStep) {
            if (currentStep === 1 && !validateStep1()) return;
        }

        currentStep = step;

        // Show/hide step panels
        document.querySelectorAll('.uc-step').forEach(function (el) {
            el.classList.toggle('uc-step--active', el.dataset.step == step);
        });

        // Update step bar
        document.querySelectorAll('.uc-steps__item').forEach(function (el) {
            var s = parseInt(el.dataset.step, 10);
            el.classList.toggle('uc-steps__item--active', s === step);
            el.classList.toggle('uc-steps__item--done', s < step);
            if (s < step) el.querySelector('.uc-steps__num').textContent = '\u2713';
            else el.querySelector('.uc-steps__num').textContent = s;
        });

        // Back button
        var backBtn = document.getElementById('uc-steps-back');
        if (backBtn) backBtn.style.display = step > 1 ? '' : 'none';

        // Show step2 summary lines
        document.querySelectorAll('.uc-summary__step2').forEach(function (el) {
            el.style.display = step >= 2 ? '' : 'none';
        });

        // Mount Stripe on step 3
        if (step === 3 && !paymentElement) initStripe();

        updateSummary();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep1() {
        var email = document.getElementById('uc-email');
        if (!email || !email.value.trim() || email.value.indexOf('@') < 0) {
            if (email) { email.style.borderColor = '#ED1C6C'; email.focus(); }
            return false;
        }
        email.style.borderColor = '';

        var items = ynjBasket.getItems();
        if (!items.length) return false;
        for (var i = 0; i < items.length; i++) {
            if ((items[i].amount_pence || 0) < 100) {
                alert('Each item must be at least \u00A31.');
                return false;
            }
        }
        return true;
    }

    // ════════════════════════════════════════════
    //  RENDER — STEP 1: REVIEW
    // ════════════════════════════════════════════

    function renderStep1() {
        var items = ynjBasket.getItems();
        var $items = document.getElementById('uc-step1-items');
        if (!$items) return;

        if (!items.length) {
            showEmpty();
            return;
        }

        // Show main UI, hide empty
        document.getElementById('uc-checkout-main').style.display = '';
        var emptyEl = document.getElementById('uc-empty-state');
        if (emptyEl) emptyEl.style.display = 'none';

        // Render items in the sidebar summary
        renderSummaryItems();

        // Render items in step 1 "Your Items" card
        var html = '';
        items.forEach(function (item) {
            var isRecurring = item.frequency && item.frequency !== 'once';
            html += '<div class="uc-cart-item" data-cart-id="' + item.id + '">';
            html += '<div class="uc-cart-item__info">';
            html += '<div class="uc-cart-item__label">' + esc(item.item_label || typeLabel(item.item_type)) + '</div>';
            html += '<div class="uc-cart-item__sub">' + esc(item.mosque_name || typeLabel(item.item_type));
            if (isRecurring) html += ' &bull; ' + esc(item.frequency);
            html += '</div></div>';
            html += '<span class="uc-cart-item__price">' + pence(item.amount_pence) + '</span>';
            html += '<button type="button" class="uc-cart-item__remove" data-remove="' + item.id + '">&times;</button>';
            html += '</div>';
        });
        $items.innerHTML = html;

        // Bind remove buttons
        $items.querySelectorAll('.uc-cart-item__remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                ynjBasket.removeItem(this.dataset.remove);
                renderStep1();
                updateSummary();
            });
        });

        // Fund selector (if donation items and funds available)
        var fundEl = document.getElementById('uc-fund-select');
        if (fundEl && FUNDS.length > 1) {
            fundEl.style.display = '';
            var select = fundEl.querySelector('select');
            if (select && !select.options.length) {
                FUNDS.forEach(function (f) {
                    var opt = document.createElement('option');
                    opt.value = f.slug; opt.textContent = f.label;
                    select.appendChild(opt);
                });
            }
        }

        updateSummary();
    }

    function showEmpty() {
        var main = document.getElementById('uc-checkout-main');
        var empty = document.getElementById('uc-empty-state');
        if (main) main.style.display = 'none';
        if (empty) empty.style.display = '';
    }

    // ════════════════════════════════════════════
    //  RENDER — SIDEBAR SUMMARY
    // ════════════════════════════════════════════

    function renderSummaryItems() {
        var items = ynjBasket.getItems();
        var $el = document.getElementById('uc-summary-items');
        if (!$el) return;

        var html = '';
        items.forEach(function (item) {
            html += '<div class="uc-summary__line">';
            html += '<span>' + esc(item.item_label || typeLabel(item.item_type)) + '</span>';
            html += '<span>' + pence(item.amount_pence) + '</span>';
            html += '</div>';
            if (item.mosque_name) {
                html += '<div class="uc-summary__line--sub">&nbsp;&nbsp;' + esc(item.mosque_name) + '</div>';
            }
        });
        $el.innerHTML = html;
    }

    function updateSummary() {
        var sub = getSubtotal();
        var total = getTotal();

        var dueEl = document.getElementById('uc-sum-due');
        var tipEl = document.getElementById('uc-sum-tip');
        var causeEl = document.getElementById('uc-sum-cause');
        var totalEl = document.getElementById('uc-sum-total');

        if (dueEl) dueEl.textContent = pence(sub);
        if (tipEl) tipEl.textContent = pence(tipPence);
        if (causeEl) causeEl.textContent = pence(causePence);
        if (totalEl) totalEl.textContent = pence(total);

        // Update submit button
        var submitBtn = document.getElementById('uc-submit-btn');
        if (submitBtn) submitBtn.textContent = 'Complete Payment \u2014 ' + pence(total);

        // Step 1 continue button
        var s1btn = document.getElementById('uc-s1-continue');
        if (s1btn) {
            var groups = ynjBasket.groupByFrequency();
            var label = 'Continue \u2192';
            if (sub > 0) label = pence(sub) + ' \u2014 Continue \u2192';
            s1btn.textContent = label;
        }
    }

    // ════════════════════════════════════════════
    //  STEP 2: SUPPORT — Platform Tip Tiers
    // ════════════════════════════════════════════

    function initStep2() {
        // Tip tiers
        var tiers = document.querySelectorAll('.uc-tier[data-tip]');
        tiers.forEach(function (tier) {
            tier.addEventListener('click', function () {
                tiers.forEach(function (t) { t.classList.remove('uc-tier--active'); });
                this.classList.add('uc-tier--active');
                tipPence = parseInt(this.dataset.tip, 10);
                updateSummary();
            });
        });

        // Cause buttons (Fund Our Mission)
        var causeBtns = document.querySelectorAll('.uc-tier[data-cause]');
        causeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                causeBtns.forEach(function (b) { b.classList.remove('uc-tier--active'); });
                this.classList.add('uc-tier--active');
                causePence = parseInt(this.dataset.cause, 10);
                updateSummary();
            });
        });
    }

    // ════════════════════════════════════════════
    //  STEP 3: PAYMENT — Stripe
    // ════════════════════════════════════════════

    function initStripe() {
        if (!PK || typeof Stripe === 'undefined') return;

        // Create the intent first
        var email = document.getElementById('uc-email').value.trim();
        var name = document.getElementById('uc-name').value.trim();
        var phone = document.getElementById('uc-phone') ? document.getElementById('uc-phone').value.trim() : '';

        var payload = {
            email: email,
            name: name,
            phone: phone,
            tip_pence: tipPence + causePence,
            items: ynjBasket.toApiPayload(),
            source: 'checkout_page'
        };

        document.getElementById('uc-processing').style.display = '';
        document.getElementById('uc-payment-section').style.display = 'none';

        fetch(API + 'unified-checkout/create-intent', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.getElementById('uc-processing').style.display = 'none';

            if (!data.ok) {
                showError(data.error || 'Failed to set up payment.');
                return;
            }

            if (data.mode === 'redirect') {
                ynjBasket.clear();
                window.location.href = data.url;
                return;
            }

            if (data.mode === 'split') {
                txnId = data.one_off.transaction_id;
                window._ynjRecurringUrl = data.recurring.url;
                mountStripe(data.one_off.client_secret);
                return;
            }

            // elements mode
            txnId = data.transaction_id;
            mountStripe(data.client_secret);
        })
        .catch(function () {
            document.getElementById('uc-processing').style.display = 'none';
            showError('Network error. Please try again.');
        });
    }

    function mountStripe(clientSecret) {
        document.getElementById('uc-payment-section').style.display = '';

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
            cardReady = e.complete;
            var submitBtn = document.getElementById('uc-submit-btn');
            if (submitBtn) submitBtn.disabled = !e.complete;
            if (e.error) showError(e.error.message);
            else hideError();
        });
    }

    function submitPayment() {
        if (!cardReady || processing) return;
        processing = true;

        var submitBtn = document.getElementById('uc-submit-btn');
        var procEl = document.getElementById('uc-processing');
        submitBtn.style.display = 'none';
        procEl.style.display = '';
        hideError();

        stripe.confirmPayment({
            elements: elements,
            confirmParams: { return_url: window.location.origin + '/checkout/?success=1&txn=' + txnId },
            redirect: 'if_required'
        }).then(function (result) {
            if (result.error) {
                showError(result.error.message);
                submitBtn.style.display = '';
                procEl.style.display = 'none';
                processing = false;
                return;
            }

            // Confirm with backend
            procEl.querySelector('span').textContent = 'Confirming...';
            fetch(API + 'unified-checkout/confirm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    transaction_id: txnId,
                    payment_intent_id: result.paymentIntent ? result.paymentIntent.id : ''
                })
            }).then(function () {
                ynjBasket.clear();
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

    function showError(msg) {
        var el = document.getElementById('uc-error');
        if (el) { el.textContent = msg; el.style.display = ''; }
    }
    function hideError() {
        var el = document.getElementById('uc-error');
        if (el) el.style.display = 'none';
    }

    // ════════════════════════════════════════════
    //  INIT
    // ════════════════════════════════════════════

    function init() {
        if (new URLSearchParams(window.location.search).get('success')) return;

        handleUrlParams();

        var items = ynjBasket.getItems();
        if (!items.length && !new URLSearchParams(window.location.search).get('type')) {
            showEmpty();
            return;
        }

        // Pre-fill
        var emailEl = document.getElementById('uc-email');
        var nameEl = document.getElementById('uc-name');
        if (emailEl && CFG.userEmail) emailEl.value = CFG.userEmail;
        if (nameEl && CFG.userName) nameEl.value = CFG.userName;

        // Step 1 continue
        var s1btn = document.getElementById('uc-s1-continue');
        if (s1btn) s1btn.addEventListener('click', function () { goStep(2); });

        // Step 2 continue
        var s2btn = document.getElementById('uc-s2-continue');
        if (s2btn) s2btn.addEventListener('click', function () { goStep(3); });

        // Submit payment
        var submitBtn = document.getElementById('uc-submit-btn');
        if (submitBtn) submitBtn.addEventListener('click', submitPayment);

        // Back button
        var backBtn = document.getElementById('uc-steps-back');
        if (backBtn) backBtn.addEventListener('click', function () { goStep(currentStep - 1); });

        // Init step 2 tier listeners
        initStep2();

        // Render
        renderStep1();
        goStep(1);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
