<?php
/**
 * Template: Donate Page
 *
 * DFM (DonationForMasjid) iframe embed with mosque branding.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>

<main class="ynj-main">
    <section class="ynj-card ynj-card--hero" style="text-align:center;">
        <h2 id="dn-mosque-name" style="margin-bottom:8px;"><?php esc_html_e( 'Your Masjid', 'yourjannah' ); ?></h2>
        <p style="opacity:.8;margin-bottom:20px;"><?php esc_html_e( '100% of your donation reaches your masjid. Zero platform fees.', 'yourjannah' ); ?></p>
        <div class="ynj-donate-badge">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#287e61" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
            <span><?php esc_html_e( 'Verified', 'yourjannah' ); ?> &middot; <?php esc_html_e( 'Secure', 'yourjannah' ); ?> &middot; <?php esc_html_e( 'Direct', 'yourjannah' ); ?></span>
        </div>
    </section>
    <section class="ynj-card" id="dn-embed-card">
        <div id="dn-iframe-wrap" style="min-height:400px;">
            <p class="ynj-text-muted" style="text-align:center;padding:40px 0;"><?php esc_html_e( 'Loading donation page...', 'yourjannah' ); ?></p>
        </div>
    </section>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;

    fetch(API + 'mosques/' + slug).then(r=>r.json()).then(resp=>{
        const data = resp.mosque||resp;
        document.getElementById('dn-mosque-name').textContent = data.name||'<?php echo esc_js( __( 'Your Masjid', 'yourjannah' ) ); ?>';
        const dfmSlug = data.dfm_slug||slug;
        const wrap = document.getElementById('dn-iframe-wrap');
        if (data.dfm_slug) {
            const iframe = document.createElement('iframe');
            iframe.src = 'https://donationformasjid.com/' + dfmSlug + '?embed=1';
            iframe.style.cssText = 'width:100%;min-height:500px;border:none;border-radius:12px;';
            iframe.setAttribute('loading','lazy');
            iframe.setAttribute('title','<?php echo esc_js( __( 'Donate to', 'yourjannah' ) ); ?> ' + (data.name||'mosque'));
            wrap.innerHTML = '';
            wrap.appendChild(iframe);
        } else {
            wrap.innerHTML = '<a href="https://donationformasjid.com/' + dfmSlug + '" target="_blank" rel="noopener" class="ynj-btn" style="display:block;text-align:center;margin:40px auto;"><?php echo esc_js( __( 'Donate on DonationForMasjid', 'yourjannah' ) ); ?></a>';
        }
    }).catch(()=>{
        document.getElementById('dn-iframe-wrap').innerHTML = '<p class="ynj-text-muted" style="text-align:center;"><?php echo esc_js( __( 'Could not load donation page.', 'yourjannah' ) ); ?></p>';
    });
})();
</script>
<?php get_footer(); ?>
