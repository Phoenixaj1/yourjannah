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
    <h2 id="fundraising-title" style="font-size:18px;font-weight:700;margin-bottom:4px;"><?php esc_html_e( 'Donate & Fundraise', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:14px;"><?php esc_html_e( 'Support your masjid — every contribution makes a difference', 'yourjannah' ); ?></p>

    <!-- Quick Donate -->
    <div style="background:#fff;border-radius:14px;padding:20px;margin-bottom:16px;border:1px solid rgba(0,0,0,.06);box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:12px;">❤️ <?php esc_html_e( 'Donate Now', 'yourjannah' ); ?></h3>

        <!-- Frequency -->
        <p style="font-size:12px;font-weight:600;color:#6b8fa3;margin-bottom:6px;"><?php esc_html_e( 'How often?', 'yourjannah' ); ?></p>
        <div style="display:flex;gap:6px;margin-bottom:14px;" id="freq-row">
            <button class="ynj-freq-btn ynj-freq-btn--active" onclick="setFreq('friday',this)"><?php esc_html_e( 'Every Friday', 'yourjannah' ); ?></button>
            <button class="ynj-freq-btn" onclick="setFreq('monthly',this)"><?php esc_html_e( 'Monthly', 'yourjannah' ); ?></button>
            <button class="ynj-freq-btn" onclick="setFreq('one-off',this)"><?php esc_html_e( 'One-off', 'yourjannah' ); ?></button>
        </div>

        <!-- Amount -->
        <p style="font-size:12px;font-weight:600;color:#6b8fa3;margin-bottom:6px;"><?php esc_html_e( 'Amount', 'yourjannah' ); ?></p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;" id="amt-row">
            <button class="ynj-amt-btn" onclick="setAmt(5,this)">£5</button>
            <button class="ynj-amt-btn ynj-amt-btn--active" onclick="setAmt(10,this)">£10</button>
            <button class="ynj-amt-btn" onclick="setAmt(20,this)">£20</button>
            <button class="ynj-amt-btn" onclick="setAmt(50,this)">£50</button>
            <button class="ynj-amt-btn" onclick="setAmt(100,this)">£100</button>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">
            <span style="font-size:13px;font-weight:600;color:#6b8fa3;">£</span>
            <input type="number" id="custom-amt" value="10" min="1" placeholder="Other" style="flex:1;padding:10px 14px;border:1px solid #e0e0e0;border-radius:10px;font-size:16px;font-weight:700;font-family:inherit;outline:none;">
        </div>

        <!-- Donate button -->
        <button id="donate-go" onclick="submitDonate()" style="width:100%;padding:14px;border-radius:12px;border:none;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;">
            <?php esc_html_e( 'Donate — opens DonationForMasjid', 'yourjannah' ); ?>
        </button>
        <p class="ynj-text-muted" style="text-align:center;margin-top:8px;font-size:11px;"><?php esc_html_e( '100% reaches your masjid — zero platform fees', 'yourjannah' ); ?></p>
    </div>
    <style>
    .ynj-freq-btn{flex:1;padding:8px 4px;border-radius:8px;border:1px solid #e0e8ed;background:#fff;color:#0a1628;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;text-align:center;transition:all .15s;}
    .ynj-freq-btn--active{background:#16a34a;color:#fff;border-color:#16a34a;}
    .ynj-amt-btn{flex:1;min-width:50px;padding:10px 4px;border-radius:10px;border:2px solid #e0e8ed;background:#fff;color:#0a1628;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;text-align:center;transition:all .15s;}
    .ynj-amt-btn--active{border-color:#16a34a;background:#f0fdf4;color:#166534;}
    </style>

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
        <p class="ynj-text-muted" style="text-align:center;padding:20px;"><?php esc_html_e( 'Loading campaigns...', 'yourjannah' ); ?></p>
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

    var selectedFreq = 'friday';
    var selectedAmt = 10;

    window.setFreq = function(freq, btn) {
        selectedFreq = freq;
        document.querySelectorAll('.ynj-freq-btn').forEach(function(b){ b.classList.remove('ynj-freq-btn--active'); });
        btn.classList.add('ynj-freq-btn--active');
        updateDonateLabel();
    };

    window.setAmt = function(amt, btn) {
        selectedAmt = amt;
        document.getElementById('custom-amt').value = amt;
        document.querySelectorAll('.ynj-amt-btn').forEach(function(b){ b.classList.remove('ynj-amt-btn--active'); });
        btn.classList.add('ynj-amt-btn--active');
        updateDonateLabel();
    };

    // Sync custom input with buttons
    document.getElementById('custom-amt').addEventListener('input', function() {
        selectedAmt = parseInt(this.value) || 0;
        document.querySelectorAll('.ynj-amt-btn').forEach(function(b){ b.classList.remove('ynj-amt-btn--active'); });
        updateDonateLabel();
    });

    function updateDonateLabel() {
        var label = 'Donate';
        if (selectedAmt > 0) label += ' £' + selectedAmt;
        if (selectedFreq !== 'one-off') label += ' ' + selectedFreq;
        document.getElementById('donate-go').textContent = label;
    }

    window.submitDonate = function() {
        var amt = parseInt(document.getElementById('custom-amt').value) || 0;
        var url = 'https://donationformasjid.com';
        var params = [];
        if (amt > 0) params.push('amount=' + amt);
        if (selectedFreq && selectedFreq !== 'one-off') params.push('frequency=' + selectedFreq);
        try { var user = JSON.parse(localStorage.getItem('ynj_user')); if (user && user.email) params.push('email=' + encodeURIComponent(user.email)); } catch(e){}
        if (params.length) url += '?' + params.join('&');
        window.open(url, '_blank');
    };

    // Get mosque name + set donate link
    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            const ftEl = document.getElementById('fundraising-title');
            if (ftEl) ftEl.textContent = (m.name || 'Your Masjid') + ' Fundraising';
        })
        .catch(() => {});

    fetch(API + 'mosques/' + slug + '/campaigns')
        .then(r => r.json())
        .then(data => {
            const campaigns = data.campaigns || [];
            const el = document.getElementById('campaigns-list');

            if (!campaigns.length) {
                el.innerHTML = '<div class="ynj-card" style="text-align:center;padding:40px 20px;"><div style="font-size:48px;margin-bottom:12px;">\ud83d\udd4c</div><h3 style="margin-bottom:8px;"><?php echo esc_js( __( 'No Active Campaigns', 'yourjannah' ) ); ?></h3><p class="ynj-text-muted"><?php echo esc_js( __( 'Your masjid has no fundraising campaigns right now. Check back soon.', 'yourjannah' ) ); ?></p></div>';
                return;
            }

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
        })
        .catch(() => {
            document.getElementById('campaigns-list').innerHTML = '<p class="ynj-text-muted" style="text-align:center;padding:20px;"><?php echo esc_js( __( 'Could not load campaigns.', 'yourjannah' ) ); ?></p>';
        });
})();
</script>
<?php
get_footer();
