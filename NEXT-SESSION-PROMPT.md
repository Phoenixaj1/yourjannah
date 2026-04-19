# YourJannah — Next Session Briefing

Copy everything below the --- line into the new session.

---

I'm continuing work on YourJannah — a mosque community platform with gamified dhikr (WordPress + plugin architecture). 

**IMPORTANT: Two separate projects exist:**
- `C:\Users\user\Documents\yourjannah` — YourJannah (mosque platform) — THIS project
- `C:\Users\user\Documents\yourniyyah` — YourNiyyah (charity lead gen) — REFERENCE ONLY for checkout inspiration

Auto-deploys to `yourjannah.com` via push to main.

## Read these memory files first:
1. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\MEMORY.md`
2. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\yourjannah-plugin-architecture.md`
3. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\yourjannah-feature-backlog.md`

## PRIORITY 1: Redesign Unified Checkout Page

The checkout at `/checkout/` (plugin: `yn-jannah-unified-checkout`) works but has bad UX. It needs a complete frontend redesign modelled on the YourNiyyah checkout system.

### What's wrong now:
- Generic form — clicking "Become Patron" shows a donation form, not patron tiers
- No basket/cart — can't add multiple items
- No context — doesn't show which masjid or what you're buying
- No step flow — just a flat form with all fields visible
- Fund dropdown doesn't match item type (store items show donation funds)

### What it should be (study YourNiyyah for inspiration):

**YourNiyyah checkout files to study:**
1. `C:\Users\user\Documents\yourniyyah\plugins\yourniyyah-checkout\inc\class-yn-checkout-page.php` — page render
2. `C:\Users\user\Documents\yourniyyah\plugins\yourniyyah-checkout\assets\js\checkout.js` — frontend state machine
3. `C:\Users\user\Documents\yourniyyah\plugins\yourniyyah-checkout\inc\class-yn-checkout-api.php` — REST API
4. `C:\Users\user\Documents\yourniyyah\plugins\yourniyyah-lead-capture\assets\js\niyyah-cart.js` — cart/basket

**Key patterns from YourNiyyah:**
- **Single-page stepped flow**: Item summary → Email/Name → Payment Element → Confirm
- **Basket mode**: Multiple items in cart, displayed as a list with remove buttons
- **Context-aware**: Shows charity name, product name, amount prominently
- **Tip slider**: Logarithmic suggested fee, draggable
- **Real-time summary**: Updates total as you change amount/tip
- **Gateway-agnostic**: Stripe Payment Element (card, Apple Pay, Google Pay, Link)
- **`create-intent` pattern**: Frontend calls API → gets client_secret → mounts Stripe Elements → confirms

### Checkout should handle ALL item types differently:

| Item Type | What checkout shows |
|-----------|-------------------|
| `donation` | Amount buttons + fund type dropdown + frequency toggle |
| `sadaqah` | Same as donation but fund locked to sadaqah |
| `patron` | Patron tier cards (£5/£10/£20/£50) with tier names (Supporter/Guardian/Champion) |
| `store` | Store item name + image + message field + price options |
| `sponsor` | Business sponsorship tiers with monthly pricing |
| `tip` | Simple amount for supporting YourJannah platform |
| `room` | Room booking details (date, time, room name) |
| `class` | Class enrolment details (class name, instructor, schedule) |
| `event` | Event ticket details (event name, date, location) |

### Backend is solid — only frontend needs redesign:
- `yn-jannah-unified-checkout/inc/class-ynj-uc-api.php` — create-intent + confirm endpoints work
- `yn-jannah-unified-checkout/inc/class-ynj-uc-page.php` — THIS needs rewriting
- Transactions table tracks everything correctly
- Stripe PaymentIntent creation works

## What Was Built This Session (April 19, 2026)

### Massive session — 25+ commits, 6 new plugins:

**New Plugins Created:**
1. `yn-jannah-revenue-share` — 5% charity donation share back to mosques
2. `yn-jannah-live-broadcast` — YouTube streaming, Go Live button, broadcaster roles
3. `yn-jannah-unified-checkout` — Single checkout page at /checkout/ with Stripe Elements
4. `yn-jannah-store` — Digital community shout-outs (Jumuah Mubarak, etc.), 95% to masjid

**Features Built:**
- Rewired page-mosque.php (19 queries → plugin classes, zero $wpdb)
- Rewired page-profile.php (21 queries → plugin classes)
- "Purify Your Rizq" daily sadaqah button
- Imam Message tab in Quick Post modal
- New member notifications (in-app + email)
- Platform sponsors admin + footer strip
- "Follow This Masjid" rename (was "Join")
- Homepage feed format fix
- Facebook-style cover photo repositioning (drag to adjust)
- Image upload endpoint for mosque cover/profile photos
- Arabic prayer names + Hijri date on homepage
- Admin toolbar + Quick Menu on homepage
- Cover photo banner on homepage
- Deploy pipeline fix + cache busting (deploy-time timestamps)
- Cloudflare purge on deploy (needs CF_ZONE_ID + CF_API_TOKEN secrets)
- Go Live modal with 3-step flow + scheduling
- 🤲 Checkout icon in header navigation
- 18 new plugin methods across 8 plugins

**Caching fix applied:**
- Switched Cloudways to Hybrid mode (was Lightning Stack)
- Deploy writes .deploy-time for cache-busted CSS/JS versions
- Cloudflare purge step in GitHub Action (needs secrets)
- Breeze disabled, Varnish off, Object Cache Pro off

## Plugin Count: 34 active

All plugins in `plugins/yn-jannah-*/` auto-deploy via GitHub Action.

## Local Dev
- Docker: `cd C:\Users\user\Documents\yourjannah && docker compose up -d`
- WordPress: http://localhost:8090 (admin/admin)
- Production: yourjannah.com (auto-deploys via push to main)

## Technical Debt (lower priority)
- yn-jannah-core plugin — needs creating + Docker testing
- Retire yn-jannah monolith — blocked on core
- Move 19 dashboard templates from theme to plugins
- Wire ynj_get_mosque() helper to YNJ_Mosques plugin class
