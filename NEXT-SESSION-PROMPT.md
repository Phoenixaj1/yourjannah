# Prompt for Next Session

Copy everything below this line and paste as your first message:

---

I'm continuing work on YourJannah — a mosque community SaaS platform (WordPress + custom plugin). The codebase is at `C:\Users\user\Documents\yourjannah` and auto-deploys to `yourjannah.com` via GitHub push to main.

## Read these memory files first:
1. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\yourjannah-next-session.md`
2. `C:\Users\user\.claude\projects\C--Users-user-Documents-yourniyyah\memory\MEMORY.md`

## What was built in the last session (massive feature release):

### Features shipped:
- Onboarding wizard (/mosque-setup), mobile dashboard (bottom nav, FAB)
- Team management, quick post templates (12), front-end posting modal
- Content scheduling, admin edit shortcuts, admin toolbar
- Dopamine dashboard (nudges, streaks, rankings, activity feed)
- Reaction buttons (like/dua/interested/share) + view counts on feed
- Ibadah tracker (5 prayers, Quran, dhikr, fasting, charity, good deeds)
- 27x mosque prayer multiplier (Hadith-based)
- Community stats, auto-generated weekly challenges
- Badge system (17 badges), mosque leagues (4 tiers)
- Head-to-head mosque challenges, personal impact score
- Variable reward surprises (Duolingo-style)
- Dua Wall, Gratitude Wall, Fajr Counter, Milestones
- Section nav chips, "Our Masjid" score bar on homepage

### DB Schema: v3.7.0
### Theme: v3.10.1
### Key tables added: content_views, reactions, ibadah_logs, community_challenges, user_badges, h2h_challenges, dua_requests, dua_responses, gratitude_posts, milestones

## What to build next: SEPARATION OF PERSONAL vs COMMUNITY

### The user's vision:
"The users themselves need their own profile section. Dua requests, ibadah tracking, check-in, streaks — the masjid page should just show the overall count, overall points, and leagues. But there needs to be a personal aspect with a profile button in the header and a good flow from homepage to profile. They can focus on themselves and what they're doing, but it also impacts the community's total masjid points. They need a feedback loop so they can see their masjid points go up and push past other masjids."

### Architecture change needed:
1. **Move personal features FROM mosque page TO profile page:**
   - Ibadah tracker (prayer checkboxes, Quran pages, dhikr, fasting)
   - Personal streaks, badges
   - Personal impact score
   - "At Mosque" toggle
   - Dua requests I've made
   
2. **Mosque page keeps ONLY community/collective:**
   - "Our Masjid" score bar + league
   - Community stats (anonymous aggregates)
   - H2H challenges
   - Feed with reactions + views
   - Dua Wall (community view — make dua for others)
   - Gratitude Wall

3. **Profile page redesign (`page-profile.php`):**
   - Add ibadah tracker as the hero section
   - Show personal stats (total prayers, streak, badges earned)
   - "My contribution to [Mosque Name]: X%"
   - Live feedback: "Your prayers helped push [Mosque] to #2!"
   - Badge collection with progress
   - Personal dua request history
   - Check-in history

4. **Header: Add "My Ibadah" button/link**
   - Quick access from any page to log prayers
   - Shows streak count as badge: "🔥 14"

5. **Design cohesion:**
   - Consistent colour palette (dark navy for community, green for personal)
   - Reduce card count on mosque page
   - Better badge visibility (greyed-out badges too hard to read)
   - Mobile-first flow testing

### Key files:
- `theme/yourjannah-starter/page-templates/page-mosque.php` — remove personal, keep community
- `theme/yourjannah-starter/page-templates/page-profile.php` — add personal ibadah hub
- `theme/yourjannah-starter/header.php` — add "My Ibadah" nav link
- `plugins/yn-jannah/api/class-ynj-api-points.php` — ibadah API (already built)
- `theme/yourjannah-starter/inc/community-engagement.php` — league/badge/impact logic
- `theme/yourjannah-starter/inc/community-cards.php` — shared score bar include

Start by reading the profile page and mosque page, then plan the restructure.
