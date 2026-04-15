<?php
/**
 * Template: Homepage
 *
 * Prayer card, sponsor ticker, travel settings, community feed.
 * JS loaded from assets/js/homepage.js via wp_enqueue_script.
 *
 * @package YourJannah
 */

get_header();
?>

<main class="ynj-main">
  <div class="ynj-desktop-grid">
    <div class="ynj-desktop-grid__left">

    <!-- Sponsor Ticker -->
    <div class="ynj-ticker" id="sponsor-ticker" style="display:none;">
        <span class="ynj-ticker__label">⭐ <?php esc_html_e( 'Sponsors', 'yourjannah' ); ?></span>
        <div class="ynj-ticker__track">
            <div class="ynj-ticker__slide" id="ticker-content"></div>
        </div>
    </div>

    <!-- Travel Settings -->
    <div class="ynj-travel-settings" id="travel-settings" style="display:none;">
        <div class="ynj-travel-settings__row">
            <select id="mode-select" class="ynj-ts-select" onchange="onModeChange()">
                <option value="walk">🚶 <?php esc_html_e( 'Walk', 'yourjannah' ); ?></option>
                <option value="drive">🚗 <?php esc_html_e( 'Drive', 'yourjannah' ); ?></option>
                <option value="bike">🚲 <?php esc_html_e( 'Cycle', 'yourjannah' ); ?></option>
            </select>
            <select id="buffer-select" class="ynj-ts-select" onchange="onBufferChange()">
                <option value="0"><?php esc_html_e( 'No buffer', 'yourjannah' ); ?></option>
                <option value="5">+5 min wudhu</option>
                <option value="10" selected>+10 min wudhu</option>
                <option value="15">+15 min prep</option>
                <option value="20">+20 min prep</option>
            </select>
        </div>
    </div>

    <!-- Next Prayer Hero -->
    <section class="ynj-card ynj-card--hero" id="next-prayer-card">
        <p class="ynj-label" id="next-prayer-label"><?php esc_html_e( 'Next Prayer', 'yourjannah' ); ?></p>
        <h2 class="ynj-hero-prayer" id="next-prayer-name">&nbsp;</h2>
        <p class="ynj-hero-time" id="next-prayer-time">&nbsp;</p>
        <div class="ynj-countdown" id="next-prayer-countdown">--:--:--</div>
        <div class="ynj-hero-travel" id="hero-travel" style="display:none;">
            <div class="ynj-leave-by" id="leave-by">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <span id="leave-by-text"><?php esc_html_e( 'Leave by --:--', 'yourjannah' ); ?></span>
            </div>
            <span class="ynj-travel-dist" id="travel-dist"></span>
        </div>
        <div class="ynj-hero-actions">
            <div class="ynj-hero-gps" id="hero-gps-prompt">
                <button class="ynj-hero-locate" id="hero-gps-btn" type="button" onclick="requestGps()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                    <?php esc_html_e( 'Detect Location', 'yourjannah' ); ?>
                </button>
            </div>
            <div class="ynj-nav-buttons" id="nav-buttons" style="display:none;">
                <a class="ynj-hero-nav" id="navigate-walk" href="#" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="2"/><path d="M10 22l2-7 3 3v7M14 13l2-3-3-3-2 4"/></svg>
                    <?php esc_html_e( 'Walk', 'yourjannah' ); ?>
                </a>
                <a class="ynj-hero-nav" id="navigate-drive" href="#" target="_blank" rel="noopener">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17h14M7 11l2-5h6l2 5M4 17v-3a1 1 0 011-1h14a1 1 0 011 1v3"/><circle cx="7.5" cy="17" r="1.5"/><circle cx="16.5" cy="17" r="1.5"/></svg>
                    <?php esc_html_e( 'Drive', 'yourjannah' ); ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Location bar -->
    <div class="ynj-location-bar" id="location-bar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:#6b8fa3;"><circle cx="12" cy="10" r="3"/><path d="M12 2C7.6 2 4 5.4 4 9.5 4 14.3 12 22 12 22s8-7.7 8-12.5C20 5.4 16.4 2 12 2z"/></svg>
        <input type="text" id="location-postcode" placeholder="<?php esc_attr_e( 'Your postcode for travel times', 'yourjannah' ); ?>" class="ynj-location-bar__input" maxlength="8">
        <button id="location-update" class="ynj-location-bar__btn" onclick="updatePostcode()"><?php esc_html_e( 'Update', 'yourjannah' ); ?></button>
    </div>

    <!-- Prayer Overview -->
    <section class="ynj-card ynj-card--compact" id="prayer-overview" style="display:none;padding:14px 18px;">
        <div class="ynj-prayer-overview" id="prayer-overview-grid"></div>
    </section>

    <!-- Hadith -->
    <p class="ynj-hadith" id="hadith-line">
        <em>&ldquo;<?php esc_html_e( 'Prayer in congregation is twenty-seven times more virtuous than prayer offered alone.', 'yourjannah' ); ?>&rdquo;</em>
        <span>&mdash; Sahih al-Bukhari 645</span>
    </p>

    <!-- Donate button — always visible, JS upgrades to DFM link -->
    <a class="ynj-donate-btn" id="donate-btn" href="#" data-nav-mosque="/mosque/{slug}/fundraising">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        <?php esc_html_e( 'Donate to Masjid', 'yourjannah' ); ?>
    </a>

    <!-- Full Timetable link -->
    <a class="ynj-timetable-link" id="timetable-link" href="#">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <?php esc_html_e( 'View Full Timetable', 'yourjannah' ); ?>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </a>

    <!-- Support your masjid CTAs -->
    <div class="ynj-support-row">
        <a class="ynj-support-card ynj-support-card--sponsor" id="cta-sponsor" href="#" data-nav-mosque="/mosque/{slug}/sponsors">
            <span class="ynj-support-card__icon">⭐</span>
            <strong><?php esc_html_e( 'Sponsor Your Masjid', 'yourjannah' ); ?></strong>
            <span class="ynj-support-card__sub"><?php esc_html_e( 'List your business — reach the community', 'yourjannah' ); ?></span>
            <span class="ynj-support-card__help" id="cta-sponsor-help"><?php esc_html_e( 'Funds go to supporting the masjid', 'yourjannah' ); ?></span>
        </a>
        <a class="ynj-support-card ynj-support-card--services" id="cta-services" href="#" data-nav-mosque="/mosque/{slug}/services">
            <span class="ynj-support-card__icon">🤝</span>
            <strong><?php esc_html_e( 'Advertise Services', 'yourjannah' ); ?></strong>
            <span class="ynj-support-card__sub"><?php esc_html_e( 'Professionals — get found locally', 'yourjannah' ); ?></span>
            <span class="ynj-support-card__help" id="cta-services-help"><?php esc_html_e( 'Proceeds help fund the masjid', 'yourjannah' ); ?></span>
        </a>
    </div>

    <!-- Patron membership CTA -->
    <a class="ynj-patron-cta" id="patron-cta" href="#" data-nav-mosque="/mosque/{slug}/patron">
        <div class="ynj-patron-cta__left">
            <span style="font-size:20px;">🕌</span>
            <div>
                <strong><?php esc_html_e( 'Become a Patron', 'yourjannah' ); ?></strong>
                <span><?php esc_html_e( 'Support your masjid monthly', 'yourjannah' ); ?></span>
            </div>
        </div>
        <div class="ynj-patron-cta__tiers">
            <span>£5</span><span>£10</span><span>£20</span>
        </div>
    </a>

    </div><!-- end left column -->
    <div class="ynj-desktop-grid__right">

    <!-- Feed -->
    <section id="feed-section">
        <h3 style="font-size:16px;font-weight:700;margin:0 0 10px;"><?php esc_html_e( 'What\'s Happening', 'yourjannah' ); ?></h3>

        <div class="ynj-filter-chips" id="feed-filters">
            <button class="ynj-chip ynj-chip--active" data-filter="all" onclick="filterFeed('all')">All</button>
            <button class="ynj-chip" data-filter="_live" onclick="filterFeed('_live')">🔴 Live</button>
            <button class="ynj-chip" data-filter="_classes" onclick="filterFeed('_classes')">🎓 Classes</button>
            <button class="ynj-chip" data-filter="announcements" onclick="filterFeed('announcements')">📢 Updates</button>
            <button class="ynj-chip" data-filter="talk" onclick="filterFeed('talk')">🎤 Talks</button>
            <button class="ynj-chip" data-filter="youth,kids,children" onclick="filterFeed('youth,kids,children')">👦 Youth</button>
            <button class="ynj-chip" data-filter="sisters" onclick="filterFeed('sisters')">👩 Sisters</button>
            <button class="ynj-chip" data-filter="sports,competition" onclick="filterFeed('sports,competition')">⚽ Sports</button>
            <button class="ynj-chip" data-filter="community,iftar,fundraiser" onclick="filterFeed('community,iftar,fundraiser')">🤝 Community</button>
        </div>

        <div id="feed-list">
            <p class="ynj-text-muted" style="padding:16px;text-align:center;"><?php esc_html_e( 'Loading...', 'yourjannah' ); ?></p>
        </div>
    </section>

    <!-- Push Subscribe -->
    <section class="ynj-card ynj-card--subscribe" id="subscribe-card">
        <button class="ynj-btn ynj-btn--outline" id="subscribe-btn" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            <?php esc_html_e( 'Get Prayer Reminders', 'yourjannah' ); ?>
        </button>
        <p class="ynj-subscribe-status" id="subscribe-status"></p>
    </section>

    </div><!-- end right column -->
  </div><!-- end desktop grid -->
</main>

<!-- Mosque selector dropdown -->
<div class="ynj-dropdown" id="mosque-dropdown" style="display:none;">
    <div class="ynj-dropdown__inner">
        <input class="ynj-dropdown__search" id="mosque-search" type="text" placeholder="<?php esc_attr_e( 'Search mosques...', 'yourjannah' ); ?>" autocomplete="off">
        <div class="ynj-dropdown__list" id="mosque-list"></div>
    </div>
</div>

<?php
get_footer();
