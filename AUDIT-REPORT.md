# YourJannah Comprehensive Site Audit
**Date:** 17 April 2026
**Auditor:** Claude (automated code + live QA)
**Scope:** All 3 user perspectives (Mosque Admin, Business User, Normal User)

## Executive Summary
**78 issues found** across the entire platform:
- **10 CRITICAL** (security vulnerabilities, data loss)
- **15 HIGH** (broken features, functional bugs)
- **19 MEDIUM** (UX issues, incomplete features)
- **11 LOW** (minor UX, code quality)
- **23 GAPS** (missing features for production readiness)

---

## PRIORITY 1: CRITICAL SECURITY (Fix Immediately)

| # | Issue | File | Line |
|---|-------|------|------|
| 1 | **Session hijacking** -- `ynj_set_session` AJAX lets anyone POST `wp_user_id=1` to become admin | yn-jannah.php | 95-101 |
| 2 | **Stripe secret key hardcoded** in source (base64 is not encryption) | class-ynj-stripe.php | 72 |
| 3 | **Unauthenticated donation confirm** -- `/donate/confirm` marks donations as succeeded without Stripe verification | class-ynj-api-donations.php | 270-303 |
| 4 | **HMAC salt hardcoded** identically across all deployments | class-ynj-auth.php | 342 |
| 5 | **Admin fund endpoints** use `__return_true` permission | class-ynj-api-donations.php | 57-75 |
| 6 | **Claim mosque token** uses wrong hash function (SHA256 vs HMAC) | class-ynj-api-admin.php | 1407 |
| 7 | **SQL injection** in page-madrassah.php (raw interpolation) | page-madrassah.php | 75-76 |
| 8 | **SQL injection** in page-live.php (raw date interpolation) | page-live.php | 31-48 |
| 9 | **XSS in patron wall** -- innerHTML with user-controlled names | page-patron.php | 327-334 |
| 10 | **Open redirect** on login page via unvalidated `?redirect=` param | page-login.php | 13-14 |

## PRIORITY 2: CRITICAL DATA BUGS (Silent Data Loss)

| # | Issue | File | Line |
|---|-------|------|------|
| 11 | **events.php** uses `category` column but DB has `event_type` | dashboard/events.php | 18 |
| 12 | **services.php** uses `name` column but DB has `title` | dashboard/services.php | 12 |
| 13 | **enquiries.php** writes `admin_notes` but column doesn't exist | dashboard/enquiries.php | 13 |
| 14 | **settings.php** references non-existent `accounts` table | dashboard/settings.php | 158 |
| 15 | **prayers.php** Jumu'ah insert uses `status='active'` but column is `enabled` | dashboard/prayers.php | 227 |
| 16 | **admin API** `list_members` uses `created_at` but column is `subscribed_at` | class-ynj-api-admin.php | 875 |

## PRIORITY 3: HIGH SEVERITY BUGS

| # | Issue | File |
|---|-------|------|
| 17 | Test email endpoint with hardcoded key lets anyone send emails | yn-jannah.php:118 |
| 18 | Test user creation with hardcoded password on production | yn-jannah.php:148 |
| 19 | Admin tickets endpoints are fully public (`__return_true`) | class-ynj-api-admin.php:175 |
| 20 | Password sent in plaintext via welcome email | class-ynj-wp-auth.php:410 |
| 21 | Broadcast rate limiting not enforced in PHP dashboard | dashboard/broadcast.php:13 |
| 22 | Broadcast sends emails synchronously (times out >50 subscribers) | dashboard/broadcast.php:20 |
| 23 | CSV export outputs headers after HTML (corrupted downloads) | dashboard/subscribers.php:26 |
| 24 | `page-patron.php` references `#intention-section` that doesn't exist (JS error) | page-patron.php:297 |
| 25 | Register page auto-generates password, never shown to user | page-register.php:302 |
| 26 | `setcookie()` called after output in page-mosque.php | page-mosque.php:19 |
| 27 | `checkout_class` passes wrong argument order to Stripe | class-ynj-api-stripe.php:831 |
| 28 | `sponsor_yj` endpoint calls non-existent `YNJ_Stripe::client()` | class-ynj-api-sponsor-yj.php:57 |
| 29 | Rate limit function returns inverted boolean (allows when should block) | class-ynj-api-admin.php:1330 |
| 30 | SSL verification disabled for Aladhan API | front-page.php:46 |

## PRIORITY 4: MEDIUM UX + FUNCTIONAL ISSUES

| # | Issue | File |
|---|-------|------|
| 31 | Interest log in wp_options grows unboundedly (performance bomb) | overview.php:201 |
| 32 | `ORDER BY RAND()` on every homepage load (full table scan) | front-page.php:204 |
| 33 | Missing `global $wpdb` causes fatal when Aladhan API fails | front-page.php:62 |
| 34 | Dead links: `/patron` and `/patron/cancel` have no routes | page-profile.php:361 |
| 35 | No pagination on any dashboard section (max 50 records) | ALL dashboard files |
| 36 | Patron + sponsor sections are read-only (no admin actions) | dashboard/patrons.php |
| 37 | Madrassah: attendance, fees, terms, reports not built | dashboard/madrassah.php |
| 38 | No image upload on announcements/events/campaigns forms | ALL dashboard forms |
| 39 | SVG upload allowed in appeals (XSS vector) | page-appeals.php:64 |
| 40 | No unsubscribe link in broadcast emails (CAN-SPAM/GDPR) | class-ynj-api-admin.php |
| 41 | `wp_mail_content_type` filter never removed (all emails become HTML) | class-ynj-api-admin.php:690 |
| 42 | DB migration runs on every page load (init + admin_init) | yn-jannah.php:86 |
| 43 | No CAPTCHA on contact form | page-contact.php |
| 44 | Duplicate `ynj_get_mosque()` function definition | functions.php + template-tags.php |
| 45 | No Post-Redirect-Get pattern (form resubmit on refresh) | ALL dashboard forms |
| 46 | No token expiration -- bearer tokens valid forever | class-ynj-auth.php |
| 47 | Notification bell nonce stale from page cache | header.php:308 |
| 48 | Desktop navigation hidden (`display:none`) with no media query | theme.css:77 |
| 49 | Header max-width 500px even on desktop | theme.css:74 |

## PRIORITY 5: MISSING FEATURES FOR PRODUCTION

### Must-Have (before launch)
1. Email verification on registration
2. Account deletion (GDPR)
3. Unsubscribe link in all emails
4. Donation receipts (PDF/email)
5. Event RSVP confirmation emails
6. Booking confirmation emails
7. Stripe webhook signature verification
8. Error tracking (Sentry or similar)
9. Proper analytics (GA4 or privacy-respecting)
10. SEO: meta descriptions, Open Graph tags, JSON-LD schema

### Should-Have (V1.1)
11. Profile photo/avatar upload
12. Image uploads on announcements + events
13. Rich text editor for descriptions
14. Scheduled announcements (publish at future date)
15. Event calendar view (not just list)
16. Room availability calendar
17. Donation history/transaction list in dashboard
18. Multi-admin role management
19. Activity/audit log
20. Global search across platform
21. Breadcrumb navigation
22. Dark mode toggle

### Nice-to-Have (V2)
23. User-to-user messaging
24. Mosque reviews/ratings
25. Business analytics dashboard for sponsors
26. Business reviews from community
27. Event reminders (push/email)
28. Weekly digest email
29. Community forum/discussion
30. PWA offline support
31. QR code generator for mosque/patron links
32. Multilingual content support (Arabic/Urdu)

## IMPLEMENTATION PLAN

### Sprint 1: Security Hardening (1-2 days)
- Fix session hijacking (#1)
- Move Stripe keys to wp_options only (#2)
- Add Stripe webhook verification to donation confirm (#3)
- Use wp_salt() for HMAC (#4)
- Add proper permission callbacks to all admin endpoints (#5, #19)
- Fix claim mosque token hash (#6)
- Fix SQL injection (#7, #8)
- Fix XSS (#9)
- Add wp_validate_redirect (#10)
- Remove test endpoints (#17, #18)

### Sprint 2: Data Integrity (1 day)
- Fix all wrong column names (#11-16)
- Fix rate limit inversion (#29)
- Fix checkout_class argument order (#27)
- Fix sponsor_yj broken method (#28)

### Sprint 3: Core UX Fixes (2-3 days)
- Add pagination to all dashboard sections
- Fix broadcast rate limiting + queue emails via cron
- Fix CSV export (output buffering)
- Fix dead links in profile
- Add image upload to forms
- Fix setcookie timing
- Add PRG pattern to forms
- Fix patron page JS errors

### Sprint 4: Production Readiness (3-5 days)
- Email verification flow
- GDPR account deletion
- Unsubscribe links in emails
- Donation receipts
- SEO (meta descriptions, OG tags, JSON-LD)
- Error tracking setup
- Analytics integration
- Nonce handling for cached pages

### Sprint 5: Dashboard Completion (5-7 days)
- Madrassah: attendance, fees, terms, reports UI
- Donation history/transaction list
- Event registration list view
- Patron management actions (cancel, refund)
- Sponsor management (approve, remove)
- Room booking calendar view
- Image uploads on all content forms
- Rich text editor

---

## Verification Checklist
After fixes, verify:
- [ ] Cannot hijack session via ynj_set_session
- [ ] Stripe keys not in source code
- [ ] Donation confirm requires Stripe verification
- [ ] All admin endpoints require auth
- [ ] Events save event_type correctly
- [ ] Services save title correctly
- [ ] Enquiry replies persist in DB
- [ ] Jumu'ah slots create with enabled=1
- [ ] No SQL injection in madrassah/live pages
- [ ] Login redirect validates URL
- [ ] Broadcast respects 3/week limit
- [ ] CSV export downloads correctly
- [ ] All dashboard pages paginate at 50+
