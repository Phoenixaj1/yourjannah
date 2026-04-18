# Prompt for Next Session

Copy everything below this line and paste as your first message:

---

I'm continuing work on YourJannah — a mosque community SaaS platform (WordPress + custom plugin). The codebase is at `C:\Users\user\Documents\yourjannah` and auto-deploys to `yourjannah.com` via GitHub push to main.

## Read these memory files first:
1. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\yourjannah-next-session.md` — full context on what to build next, session summary, current state
2. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\yourjannah-audit-plan.md` — completed audit plan (7 sprints done)
3. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\MEMORY.md` — project overview and environment

## What to build now: Mobile-First Mosque Admin Onboarding + Dashboard

### The vision:
I go to masjids giving talks about community. Imams and mosque committees sign up. Their onboarding needs to be AS SIMPLE as the user onboarding we built (GPS → select masjid → email → done). Then a step-by-step wizard guides them through setup. Mobile is primary — most mosque admins will manage everything from their phone.

### Three things to build:

**1. Mosque Admin Onboarding Wizard**
- New page at `/mosque-setup` — standalone wizard, not inside the dashboard
- Step 1: Confirm mosque details (name, address, phone)
- Step 2: Set prayer times + Jumu'ah slots (THIS IS THE MOST IMPORTANT)
- Step 3: Post first announcement
- Step 4: Import email list or share mosque page link
- Progress bar, big mobile-friendly buttons, one thing per step
- On completion → redirect to dashboard with celebration

**2. Mobile-Responsive Dashboard**
- Current dashboard: `theme/yourjannah-starter/page-templates/page-dashboard.php` with 240px fixed sidebar
- Need: hamburger menu on mobile, collapsible sidebar, bottom nav for key actions
- All 18 section files in `theme/yourjannah-starter/page-templates/dashboard/`
- Forms need to stack vertically on mobile, big touch targets (44px min)
- Quick actions: "New Announcement", "Add Event", "Add Class" as floating buttons

**3. Easy Admin Assignment**
- Platform admin (me) needs one-click "Make Admin" for any user+mosque
- In `plugins/yn-jannah/inc/class-ynj-platform-admin.php` — add mosque admin assignment UI
- When someone registers for a mosque → I get notified → one tap to approve as admin

### Key files:
- Plugin: `plugins/yn-jannah/` (yn-jannah.php, inc/, api/)
- Theme: `theme/yourjannah-starter/` (functions.php, header.php, footer.php)
- Dashboard: `theme/yourjannah-starter/page-templates/page-dashboard.php`
- Dashboard sections: `theme/yourjannah-starter/page-templates/dashboard/*.php` (18 files)
- DB schema: `plugins/yn-jannah/inc/class-ynj-db.php` (v3.2.0)
- Auth: `plugins/yn-jannah/inc/class-ynj-wp-auth.php` (roles: ynj_mosque_admin, ynj_imam, ynj_congregation)

### Current versions:
- Theme: 3.9.7 (`YNJ_THEME_VERSION` in functions.php)
- DB Schema: 3.2.0
- Plugin: 2.4.0 (`YNJ_VERSION` in yn-jannah.php)

### Design principles:
- Mobile FIRST — design for 375px, scale up
- Big touch targets (min 44px)
- One action per screen in wizard
- Progress indicators throughout
- Encouraging copy ("You're doing great", "Almost there")

Start by reading the memory files, then plan the implementation, then build it.
