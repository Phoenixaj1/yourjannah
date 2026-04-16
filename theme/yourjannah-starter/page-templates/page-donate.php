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

// --- Server-side pre-load: mosque data ---
$mosque = ynj_get_mosque( $slug );
$mosque_name = $mosque ? $mosque->name : __( 'Your Masjid', 'yourjannah' );
$dfm_slug = $mosque && ! empty( $mosque->dfm_slug ) ? $mosque->dfm_slug : $slug;
$has_dfm = $mosque && ! empty( $mosque->dfm_slug );
?>

<main class="ynj-main">
    <section class="ynj-card ynj-card--hero" style="text-align:center;">
        <h2 id="dn-mosque-name" style="margin-bottom:8px;"><?php echo esc_html( $mosque_name ); ?></h2>
        <p style="opacity:.8;margin-bottom:20px;"><?php esc_html_e( '100% of your donation reaches your masjid. Zero platform fees.', 'yourjannah' ); ?></p>
        <div class="ynj-donate-badge">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#287e61" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
            <span><?php esc_html_e( 'Verified', 'yourjannah' ); ?> &middot; <?php esc_html_e( 'Secure', 'yourjannah' ); ?> &middot; <?php esc_html_e( 'Direct', 'yourjannah' ); ?></span>
        </div>
    </section>
    <section class="ynj-card" id="dn-embed-card">
        <div id="dn-iframe-wrap" style="min-height:400px;">
            <?php if ( $has_dfm ) : ?>
                <iframe
                    src="<?php echo esc_url( 'https://donationformasjid.com/' . $dfm_slug . '?embed=1' ); ?>"
                    style="width:100%;min-height:500px;border:none;border-radius:12px;"
                    loading="lazy"
                    title="<?php echo esc_attr( sprintf( __( 'Donate to %s', 'yourjannah' ), $mosque_name ) ); ?>"
                ></iframe>
            <?php else : ?>
                <a href="<?php echo esc_url( 'https://donationformasjid.com/' . $dfm_slug ); ?>" target="_blank" rel="noopener" class="ynj-btn" style="display:block;text-align:center;margin:40px auto;">
                    <?php esc_html_e( 'Donate on DonationForMasjid', 'yourjannah' ); ?>
                </a>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php get_footer(); ?>
