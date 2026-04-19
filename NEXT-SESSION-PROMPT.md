# YourJannah — Next Session Briefing

Copy everything below the --- line into the new session.

---

I'm continuing work on YourJannah — a mosque community platform with gamified dhikr (WordPress + plugin architecture). Codebase at `C:\Users\user\Documents\yourjannah`, auto-deploys to `yourjannah.com` via push to main.

## Read these memory files first:
1. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\MEMORY.md`
2. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\yourjannah-plugin-architecture.md`
3. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\yourjannah-feature-backlog.md`

## Codebase Structure

```
C:\Users\user\Documents\yourjannah\
├── theme/yourjannah-starter/          ← WordPress theme (presentation)
│   ├── header.php                     (167 lines — HUD via plugin, nav bar only)
│   ├── functions.php                  (v3.15.0)
│   ├── front-page.php                 (wired to plugin classes ✅)
│   ├── footer.php                     (mobile nav + niyyah bar + sponsors strip)
│   ├── page-templates/
│   │   ├── page-mosque.php            (WIRED to plugins ✅ — zero $wpdb)
│   │   ├── page-profile.php           (WIRED to plugins ✅ — only GDPR delete uses $wpdb)
│   │   ├── page-login.php             (4-box PIN input, 10 attempt limit)
│   │   ├── page-patron.php            (fixed: WP cookie auth fallback)
│   │   ├── page-dashboard.php         (plugin-driven nav via apply_filters)
│   │   ├── page-sponsors.php          (wired ✅)
│   │   ├── page-business.php          (wired ✅)
│   │   ├── page-events.php            (wired ✅)
│   │   ├── page-classes.php           (wired ✅)
│   │   ├── page-booking.php           (wired ✅)
│   │   ├── page-prayers.php           (wired ✅)
│   │   └── dashboard/                 (19 section templates — still in theme)
│   └── assets/
│       ├── css/theme.css              (gold/silver/bronze sponsor plaques)
│       └── js/homepage.js             (reactions permanent, WhatsApp share, sponsor frequency)
│
├── plugins/
│   ├── yn-jannah/                     ← MONOLITH (still running, provides YNJ_DB, REST API)
│   │   ├── yn-jannah.php             (has defined() guard on YNJ_TABLE_PREFIX)
│   │   ├── inc/                       (15 classes: DB, Auth, Stripe, Push, Admin + Sponsors page)
│   │   └── api/                       (23 REST API classes)
│   │
│   ├── yn-jannah-hud/                 ← Header bar (guest + member HUD)
│   ├── yn-jannah-gamification/        ← Points, levels, leagues, streaks, badges, api-points.php
│   ├── yn-jannah-mosques/             ← Mosque profiles, prayer calc, search, user subscriptions
│   ├── yn-jannah-prayer-times/        ← Multi-masjid prayer time comparison
│   ├── yn-jannah-jumuah/              ← Jumuah times + dashboard section
│   ├── yn-jannah-checkins/            ← GPS check-ins, 500/2000 pts, most active
│   ├── yn-jannah-events/              ← Announcements, events, bookings, rooms, user bookings
│   ├── yn-jannah-madrassah/           ← Classes, sessions, enrolments
│   ├── yn-jannah-directory/           ← Businesses, services, enquiries, user lookups
│   ├── yn-jannah-services/            ← Bookable mosque services (nikkah, funeral)
│   ├── yn-jannah-donations/           ← Campaigns, donations, fund types, patron status
│   ├── yn-jannah-patrons/             ← Patron analytics, % congregation, user patron lookup
│   ├── yn-jannah-notifications/       ← Push, in-app notifications, email (inc. new member notify)
│   ├── yn-jannah-engagement/          ← Dua wall, gratitude, reactions, views, user reactions/duas
│   ├── yn-jannah-people/              ← Community members, points, user creation/lookup
│   ├── yn-jannah-platform-admin/      ← Super-admin WP dashboard
│   ├── yn-jannah-dua-wall/            ← Dua wall (new plugin, seeded with YourJannah dua)
│   ├── yn-jannah-community-marketplace/ ← Gumtree-style listings (CPT)
│   ├── yn-jannah-celebrations/        ← Quran memorisation, Hajj, marriage, etc (CPT)
│   ├── yn-jannah-imam-messages/       ← Daily imam messages, front-end posting API
│   ├── yn-jannah-youth/               ← Youth activities (sports, talks, trips)
│   ├── yn-jannah-push-scheduler/      ← Mon-Fri 5pm scheduled pushes
│   ├── yn-jannah-checkout/            ← Donation checkout, recurring, causes, tips, sadaqah
│   └── yn-jannah-core/                ← DOES NOT EXIST (crashed, removed — needs rebuilding)
│
├── .github/workflows/deploy.yml       ← Auto-deploys ALL plugins + theme via rsync
├── docker-compose.yml                 ← Local dev: localhost:8090
└── twa/                               ← Android TWA (APK/AAB)
```

## What Works
- **Frontend**: Homepage, mosque page, all nav items, HUD (guest + member), prayer times, sponsor plaques, reactions (permanent), WhatsApp share, dhikr
- **Admin**: 29 plugins active, each with WP Admin pages + mosque filter dropdowns
- **Dashboard**: Plugin-driven sidebar via `apply_filters('ynj_dashboard_sections')`
- **Deploy**: Push to main → GitHub Action → rsync to Cloudways (auto-deploys ALL plugins)
- **Auth**: PIN login (4 separate boxes), 10 attempt limit, WP cookie + Bearer token
- **Sponsors**: WP Admin → YourJannah → Sponsors — 5 charity logos, footer strip sitewide
- **Follow**: "Follow This Masjid" (was "Join") — users can follow multiple masjids

## What Was COMPLETED This Session (April 19, 2026)

### 1. page-mosque.php — FULLY WIRED ✅
All 19 `$wpdb` queries replaced with plugin class calls. Zero direct DB queries remain.
Uses: YNJ_Events, YNJ_Mosques, YNJ_Directory, YNJ_Madrassah, YNJ_Engagement, YNJ_Donations, YNJ_Streaks, YNJ_People.

### 2. page-profile.php — FULLY WIRED ✅
21 read queries replaced with plugin calls. Only GDPR account deletion cascade still uses $wpdb (intentionally — destructive multi-table + Stripe).

### 3. "Purify Your Rizq" sadaqah button ✅
Daily sadaqah habit card on mosque page. Preset £1/£3/£5, streak counter, Stripe checkout.

### 4. Imam Message tab in Quick Post ✅
Imam/committee can post messages from mosque page. REST API, 6 categories.

### 5. New member notifications ✅
In-app + email notification to mosque admin when someone follows. Fires `ynj_new_member` hook.

### 6. Platform sponsors admin ✅
WP Admin → YourJannah → Sponsors. 5 charity logo uploads. Footer strip sitewide.

### 7. "Follow This Masjid" rename ✅
Join → Follow, Joined → Following, Leave → Unfollow, members → followers.

### 8. New plugin methods added (18 total):
- YNJ_Streaks: 9 ibadah methods (today, 3 streak types, week, 7day, heatmap, totals, masjid dhikr)
- YNJ_People: find_by_email, create_user, get_points_sum, get_recent_points, get_total_points
- YNJ_Engagement: get_user_reactions, get_user_duas
- YNJ_Mosques: get_user_subscription
- YNJ_Events: get_user_bookings, approval_status in create_announcement
- YNJ_Directory: get_user_businesses, get_user_services
- YNJ_Checkins_Data: get_user_checkin_count
- YNJ_Patrons_Data: get_user_patron
- YNJ_Notify: new_member

## What's Still Broken / Incomplete

### 1. yn-jannah-core — DOES NOT EXIST
Root cause: WordPress loads plugins alphabetically. `yn-jannah-core` loads BEFORE `yn-jannah` and defines `YNJ_TABLE_PREFIX`. Monolith then fatals on duplicate constant. Fix already applied (monolith has `defined()` guard). Core plugin needs creating + Docker testing before production. DO NOT deploy to production without testing locally first.

### 2. Retire yn-jannah monolith
NOW POSSIBLE since page-mosque.php and page-profile.php are wired. But monolith still provides:
- `YNJ_DB` class (all plugins depend on this)
- 23 REST API endpoints
- Schema/table creation
- Stripe SDK initialization
- Push notification keys
Can't retire until yn-jannah-core takes over YNJ_DB + shared utilities.

### 3. Move 19 dashboard templates from theme to plugins
Each plugin should own its dashboard section template. Currently in `theme/page-templates/dashboard/`.

### 4. Guest HUD
Ghost bars were from old service worker caches. SW nuke in header.php. Current code is clean.

## Key Technical Notes

### Plugin architecture pattern
Each plugin follows:
```
plugins/yn-jannah-{name}/
├── yn-jannah-{name}.php          (entry: loads at plugins_loaded, class_exists guards)
├── inc/
│   ├── class-ynj-{name}.php      (PHP data layer: direct $wpdb, sanitized)
│   └── class-ynj-{name}-admin.php (WP Admin: WP_List_Table, mosque filter)
└── api/                           (REST endpoints if needed)
```

### Deploy pipeline
`.github/workflows/deploy.yml` — loops over `plugins/*/` and deploys each via rsync. Also has orphan cleanup.

### Database
All plugins share the same tables via `YNJ_DB::table('name')` which returns `{$wpdb->prefix}ynj_{name}`.

### Hooks
- `ynj_dashboard_sections` (filter) — plugins register dashboard nav items
- `ynj_hud_gamification_data` (filter) — gamification provides HUD data
- `ynj_dhikr_completed`, `ynj_user_checked_in`, `ynj_new_member` (actions)

## Local Dev
- Docker: `cd C:\Users\user\Documents\yourjannah && docker compose up -d`
- WordPress: http://localhost:8090 (admin/admin)
- Production: yourjannah.com (auto-deploys via push to main)
