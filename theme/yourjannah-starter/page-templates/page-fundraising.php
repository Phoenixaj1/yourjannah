<?php
/**
 * Template: Fundraising Campaigns Page
 *
 * Campaign cards with progress bars and donate links.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<style>
.ynj-campaign{background:rgba(255,255,255,.85);backdrop-filter:blur(8px);border-radius:16px;padding:0;margin-bottom:14px;overflow:hidden;border:1px solid rgba(255,255,255,.6);box-shadow:0 2px 12px rgba(0,0,0,.04);}
.ynj-campaign__img{width:100%;height:140px;object-fit:cover;background:#e8f4f8;display:flex;align-items:center;justify-content:center;font-size:48px;}
.ynj-campaign__body{padding:16px;}
.ynj-campaign__body h3{font-size:16px;font-weight:700;margin-bottom:4px;}
.ynj-campaign__body p{font-size:13px;color:#555;margin-bottom:12px;line-height:1.4;}
.ynj-progress{height:10px;background:#e8f0f4;border-radius:5px;overflow:hidden;margin-bottom:8px;}
.ynj-progress__bar{height:100%;border-radius:5px;background:linear-gradient(90deg,#00ADEF,#16a34a);transition:width .6s ease;}
.ynj-campaign__stats{display:flex;justify-content:space-between;font-size:12px;color:#6b8fa3;margin-bottom:12px;}
.ynj-campaign__stats strong{color:#0a1628;font-size:14px;}
.ynj-campaign__cat{display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:6px;background:#e8f4f8;color:#00ADEF;margin-bottom:8px;}
</style>

<main class="ynj-main">
    <?php
    $mosque      = ynj_get_mosque( $slug );
    $mosque_id   = $mosque ? (int) $mosque->id : 0;
    $mosque_name = $mosque ? $mosque->name : __( 'Your Masjid', 'yourjannah' );
    $campaigns   = [];
    if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
        global $wpdb;
        $camp_table = YNJ_DB::table( 'campaigns' );
        $campaigns = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, description, target_pence, raised_pence, status, end_date, category,
                    donor_count, percentage, recurring, dfm_link
             FROM $camp_table WHERE mosque_id = %d AND status = 'active'
             ORDER BY id DESC LIMIT 50", $mosque_id
        ) ) ?: [];
    }
    ?>
    <h2 id="fundraising-title" style="font-size:18px;font-weight:700;margin-bottom:4px;"><?php echo esc_html( $mosque_name ); ?> — <?php esc_html_e( 'Donate & Fundraise', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:14px;"><?php printf( esc_html__( 'Support %s — every contribution makes a difference', 'yourjannah' ), esc_html( $mosque_name ) ); ?></p>

    <!-- Patron CTA -->
    <a id="patron-link" href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/patron' ) ); ?>" style="display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#0a1628,#1a3a5c);border-radius:14px;padding:16px 20px;margin-bottom:16px;text-decoration:none;color:#fff;">
        <div>
            <div style="font-size:14px;font-weight:700;">🕌 <?php esc_html_e( 'Become a Monthly Patron', 'yourjannah' ); ?></div>
            <div style="font-size:12px;opacity:.7;margin-top:2px;"><?php esc_html_e( 'Steady support for your masjid', 'yourjannah' ); ?></div>
        </div>
        <div style="display:flex;gap:6px;">
            <span style="padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);">£5</span>
            <span style="padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);">£10</span>
            <span style="padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);">£20</span>
        </div>
    </a>

    <h3 style="font-size:15px;font-weight:700;margin-bottom:12px;"><?php esc_html_e( 'Active Campaigns', 'yourjannah' ); ?></h3>
    <div id="campaigns-list">
    <?php if ( empty( $campaigns ) ) : ?>
        <div class="ynj-card" style="text-align:center;padding:40px 20px;"><div style="font-size:48px;margin-bottom:12px;">🕌</div><h3 style="margin-bottom:8px;"><?php esc_html_e( 'No Active Campaigns', 'yourjannah' ); ?></h3><p class="ynj-text-muted"><?php esc_html_e( 'Your masjid has no fundraising campaigns right now. Check back soon.', 'yourjannah' ); ?></p></div>
    <?php endif; ?>
    </div>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;

    const catIcons = {
        'general':'\ud83d\udd4c','welfare':'\ud83e\udd32','expansion':'\ud83c\udfd7\ufe0f','renovation':'\ud83d\udd28',
        'education':'\ud83d\udcd6','youth':'\ud83d\udc66','sisters':'\ud83d\udc69','emergency':'\ud83d\udea8',
        'equipment':'\ud83d\udee0\ufe0f','roof':'\ud83c\udfe0','heating':'\ud83d\udd25','parking':'\ud83c\udd7f\ufe0f'
    };

    // Donations handled by floating niyyah bar — no inline donate form needed

    // Pre-loaded from PHP — instant, no API calls
    var preloadedCampaigns = <?php echo wp_json_encode( array_map( function( $c ) {
        return [
            'id'           => (int) $c->id,
            'title'        => $c->title,
            'description'  => $c->description,
            'target_pence' => (int) $c->target_pence,
            'raised_pence' => (int) $c->raised_pence,
            'category'     => $c->category,
            'donor_count'  => (int) $c->donor_count,
            'percentage'   => (int) $c->percentage,
            'recurring'    => (int) $c->recurring,
            'dfm_link'     => $c->dfm_link,
        ];
    }, $campaigns ) ); ?>;

    (function(){
            const campaigns = preloadedCampaigns;
            const el = document.getElementById('campaigns-list');

            if (!campaigns.length) return; // Already rendered empty state from PHP

            el.innerHTML = campaigns.map(c => {
                const icon = catIcons[c.category] || '\ud83d\udd4c';
                const target = c.target_pence > 0 ? '\u00a3' + (c.target_pence/100).toLocaleString() : '';
                const raised = '\u00a3' + (c.raised_pence/100).toLocaleString();
                const pct = c.percentage || 0;
                const donors = c.donor_count || 0;
                const snippet = (c.description||'').length > 120 ? c.description.slice(0,120)+'...' : (c.description||'');
                // Build donate URL: DFM link with campaign ref for tracking
                const campaignRef = c.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                let donateUrl = c.dfm_link || 'https://donationformasjid.com/' + slug;
                const separator = donateUrl.includes('?') ? '&' : '?';
                donateUrl += separator + 'fund=' + encodeURIComponent(c.category) + '&campaign_ref=' + encodeURIComponent(campaignRef) + '&campaign_id=' + c.id;
                const donateTarget = ' target="_blank" rel="noopener"';

                const isRecurring = c.recurring || ['welfare','general'].includes(c.category);
                const recurBadge = isRecurring
                    ? '<span style="display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px;background:#dbeafe;color:#1e40af;margin-left:6px;">\ud83d\udd04 Monthly</span>'
                    : '';
                const targetLabel = isRecurring ? '/month' : '';

                return '<div class="ynj-campaign">' +
                    '<div class="ynj-campaign__img">' + icon + '</div>' +
                    '<div class="ynj-campaign__body">' +
                        '<span class="ynj-campaign__cat">' + c.category + '</span>' + recurBadge +
                        '<h3>' + c.title + '</h3>' +
                        '<p>' + snippet + '</p>' +
                        (target ? '<div class="ynj-progress"><div class="ynj-progress__bar" style="width:' + pct + '%"></div></div>' : '') +
                        '<div class="ynj-campaign__stats">' +
                            '<div><strong>' + raised + '</strong> raised' + (target ? ' of ' + target + targetLabel : '') + '</div>' +
                            '<div><strong>' + donors + '</strong> donors</div>' +
                            (pct ? '<div><strong>' + pct + '%</strong></div>' : '') +
                        '</div>' +
                        '<div style="display:flex;gap:8px;">' +
                        '<a href="' + donateUrl + '"' + donateTarget + ' class="ynj-btn" style="flex:1;justify-content:center;">' +
                            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>' +
                            (isRecurring ? ' Donate Monthly' : ' Donate Now') +
                        '</a>' +
                        '<button class="ynj-btn ynj-btn--outline" onclick="ynjShare(\'' + c.title.replace(/'/g,"\\\\'") + '\',' + '\'Help fund ' + c.title.replace(/'/g,"\\\\'") + '\',' + '\'' + donateUrl + '\')">↗</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            }).join('');
    })();
})();
</script>
<?php
get_footer();
