/**
 * YourJannah Basket — localStorage cart for unified checkout.
 *
 * Usage:
 *   ynjBasket.addItem({ item_type:'donation', amount_pence:1000, mosque_id:1, mosque_name:'Al-Noor', fund_type:'general', frequency:'once', item_label:'General Donation' })
 *   ynjBasket.removeItem('ynj_cart_xxx')
 *   ynjBasket.getItems()   // => [{...}, {...}]
 *   ynjBasket.getCount()   // => 2
 *   ynjBasket.clear()
 *
 * Events:
 *   document.addEventListener('ynjBasketUpdated', e => { e.detail.action, e.detail.item, e.detail.count })
 *   ynjBasket.onChange(fn)  // fn(action, item, basket)
 *
 * @package YNJ_Unified_Checkout
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'ynj_basket';
    var MAX_ITEMS   = 20;
    var MAX_AGE_MS  = 24 * 60 * 60 * 1000; // 24 hours

    var items     = [];
    var listeners = [];

    // ── Helpers ──────────────────────────────────────────────

    function uid() {
        return 'ynj_cart_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    }

    function clone(arr) {
        try { return JSON.parse(JSON.stringify(arr)); }
        catch (e) { return []; }
    }

    function save() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(items)); }
        catch (e) { /* quota exceeded — silent */ }
    }

    function load() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) { items = []; return; }
            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) { items = []; return; }
            var now = Date.now();
            items = parsed.filter(function (it) {
                return it && it.id && it.addedAt && (now - it.addedAt) < MAX_AGE_MS;
            });
            // If we pruned stale items, persist the cleaned list
            if (items.length !== parsed.length) save();
        } catch (e) {
            items = [];
        }
    }

    function notify(action, item) {
        var detail = { action: action, item: item || null, count: items.length };
        // CustomEvent for HUD badge
        try {
            document.dispatchEvent(new CustomEvent('ynjBasketUpdated', { detail: detail }));
        } catch (e) { /* IE fallback — ignore */ }
        // Registered listeners
        for (var i = 0; i < listeners.length; i++) {
            try { listeners[i](action, item, items); } catch (e) { /* silent */ }
        }
    }

    // ── Public API ──────────────────────────────────────────

    var basket = {

        /**
         * Add an item to the basket.
         * @param {Object} item — must have item_type, amount_pence. Optional: item_id, item_label, mosque_id, mosque_name, fund_type, frequency, meta
         * @returns {Object} the stored item (with generated id + addedAt)
         */
        addItem: function (item) {
            if (!item || !item.item_type) return null;
            if (items.length >= MAX_ITEMS) return null;

            var entry = {
                id:          uid(),
                item_type:   item.item_type   || 'donation',
                item_id:     item.item_id     || 0,
                item_label:  item.item_label  || '',
                mosque_id:   item.mosque_id   || 0,
                mosque_name: item.mosque_name || '',
                amount_pence: Math.max(0, parseInt(item.amount_pence, 10) || 0),
                fund_type:   item.fund_type   || 'general',
                frequency:   item.frequency   || 'once',
                meta:        item.meta        || {},
                addedAt:     Date.now()
            };

            items.push(entry);
            save();
            notify('add', entry);
            return entry;
        },

        /**
         * Remove an item by its cart id.
         * @returns {Object|false} removed item or false
         */
        removeItem: function (id) {
            for (var i = 0; i < items.length; i++) {
                if (items[i].id === id) {
                    var removed = items.splice(i, 1)[0];
                    save();
                    notify('remove', removed);
                    return removed;
                }
            }
            return false;
        },

        /**
         * Update an item by merging partial data.
         * @returns {Object|false} updated item or false
         */
        updateItem: function (id, data) {
            for (var i = 0; i < items.length; i++) {
                if (items[i].id === id) {
                    for (var k in data) {
                        if (data.hasOwnProperty(k) && k !== 'id' && k !== 'addedAt') {
                            items[i][k] = data[k];
                        }
                    }
                    save();
                    notify('update', items[i]);
                    return items[i];
                }
            }
            return false;
        },

        /** Get a copy of all items. */
        getItems: function () { return clone(items); },

        /** Get item count. */
        getCount: function () { return items.length; },

        /** Empty the basket. */
        clear: function () {
            items = [];
            save();
            notify('clear', null);
        },

        // ── Totals ──

        /** Sum of all item amount_pence. */
        getSubtotal: function () {
            var sum = 0;
            for (var i = 0; i < items.length; i++) sum += (items[i].amount_pence || 0);
            return sum;
        },

        // ── Frequency helpers ──

        hasRecurring: function () {
            for (var i = 0; i < items.length; i++) {
                if (items[i].frequency && items[i].frequency !== 'once') return true;
            }
            return false;
        },

        hasOneOff: function () {
            for (var i = 0; i < items.length; i++) {
                if (!items[i].frequency || items[i].frequency === 'once') return true;
            }
            return false;
        },

        isMixed: function () {
            return this.hasOneOff() && this.hasRecurring();
        },

        /** Group items by frequency. */
        groupByFrequency: function () {
            var groups = { once: [], recurring: [] };
            for (var i = 0; i < items.length; i++) {
                var f = items[i].frequency || 'once';
                if (f === 'once') groups.once.push(items[i]);
                else groups.recurring.push(items[i]);
            }
            return groups;
        },

        /** Group items by mosque_id. */
        groupByMosque: function () {
            var groups = {};
            for (var i = 0; i < items.length; i++) {
                var mid = items[i].mosque_id || 0;
                if (!groups[mid]) {
                    groups[mid] = { mosque_id: mid, mosque_name: items[i].mosque_name || '', items: [], subtotal: 0 };
                }
                groups[mid].items.push(items[i]);
                groups[mid].subtotal += (items[i].amount_pence || 0);
            }
            return groups;
        },

        // ── Events ──

        onChange: function (fn) {
            if (typeof fn === 'function') listeners.push(fn);
        },

        // ── Serialisation ──

        /** Format items for the create-intent API payload. */
        toApiPayload: function () {
            return clone(items).map(function (it) {
                return {
                    item_type:    it.item_type,
                    item_id:      it.item_id,
                    item_label:   it.item_label,
                    mosque_id:    it.mosque_id,
                    amount_pence: it.amount_pence,
                    fund_type:    it.fund_type,
                    frequency:    it.frequency,
                    meta:         it.meta
                };
            });
        },

        /**
         * Check if an identical item is already in the basket.
         * Matches on item_type + item_id + mosque_id + amount_pence + frequency.
         */
        hasItem: function (match) {
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                if (it.item_type === match.item_type &&
                    it.mosque_id == match.mosque_id &&
                    it.amount_pence == match.amount_pence &&
                    it.frequency === (match.frequency || 'once') &&
                    ((!match.item_id && !it.item_id) || it.item_id == match.item_id)) {
                    return true;
                }
            }
            return false;
        }
    };

    // ── Init ──
    load();

    // Expose globally
    window.ynjBasket = basket;

})();
