<?php
/**
 * Template: Business / Service Detail Page
 *
 * Full profile page for a single business sponsor or professional service.
 * URL: /mosque/{slug}/business/{id} or /mosque/{slug}/service/{id}
 *
 * @package YourJannah
 */

get_header();
$slug    = ynj_mosque_slug();
$item_id = (int) get_query_var( 'ynj_item_id', 0 );
$type    = get_query_var( 'ynj_page_type' ); // business_detail or service_detail
$mosque  = ynj_get_mosque( $slug );

$item = null;
$is_business = ( $type === 'business_detail' );

if ( $item_id ) {
    if ( $is_business ) {
        $item = class_exists( 'YNJ_Directory' ) ? YNJ_Directory::get_business( $item_id ) : null;
    } else {
        $item = class_exists( 'YNJ_Directory' ) ? YNJ_Directory::get_service( $item_id ) : null;
    }
}

if ( ! $item ) : ?>
<main class="ynj-main">
    <section class="ynj-card" style="text-align:center;padding:40px 20px;">
        <div style="font-size:40px;margin-bottom:12px;">🔍</div>
        <h2 style="font-size:18px;font-weight:700;margin-bottom:8px;"><?php esc_html_e( 'Not Found', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted"><?php esc_html_e( 'This listing could not be found or has been removed.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors' ) ); ?>" class="ynj-btn" style="margin-top:16px;display:inline-flex;"><?php esc_html_e( '← Back to Directory', 'yourjannah' ); ?></a>
    </section>
</main>
<?php get_footer(); return; endif;

// Extract fields based on type
$name        = $is_business ? $item->business_name : $item->provider_name;
$category    = $is_business ? ( $item->category ?? '' ) : ( $item->service_type ?? '' );
$description = $item->description ?? '';
$phone       = $item->phone ?? '';
$email       = $item->email ?? '';
$website     = $is_business ? ( $item->website ?? '' ) : '';
$address     = $is_business ? ( $item->address ?? '' ) : '';
$postcode    = $is_business ? ( $item->postcode ?? '' ) : '';
$area        = ! $is_business ? ( $item->area_covered ?? '' ) : '';
$logo_url    = $is_business ? ( $item->logo_url ?? '' ) : '';
$owner_name  = $is_business ? ( $item->owner_name ?? '' ) : '';
$verified    = $is_business ? ( (int) ( $item->verified ?? 0 ) ) : 0;
$rate_pence  = ! $is_business ? (int) ( $item->hourly_rate_pence ?? 0 ) : 0;
$initial     = strtoupper( substr( $name ?: '?', 0, 1 ) );
$mosque_name = $mosque ? $mosque->name : '';

// Tier for businesses
$tier_label = '';
if ( $is_business ) {
    $fp = (int) ( $item->featured_position ?? 0 );
    if ( $fp >= 2 ) $tier_label = '🥇 Premium Sponsor';
    elseif ( $fp >= 1 ) $tier_label = '🥈 Featured Sponsor';
    elseif ( (int) ( $item->monthly_fee_pence ?? 0 ) > 0 ) $tier_label = '⭐ Sponsor';
}
?>

<main class="ynj-main">
    <!-- Back link -->
    <a href="<?php echo esc_url( home_url( '/mosque/' . $slug . '/sponsors' ) ); ?>" style="display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:600;color:#00ADEF;text-decoration:none;margin-bottom:12px;">← <?php esc_html_e( 'Back to Directory', 'yourjannah' ); ?></a>

    <!-- Profile Hero Card -->
    <div class="ynj-card" style="overflow:hidden;">
        <?php if ( $tier_label ) : ?>
        <div style="padding:8px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;"><?php echo $tier_label; ?></div>
        <?php endif; ?>

        <div style="padding:20px;">
            <!-- Logo + Name -->
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                <?php if ( $logo_url ) : ?>
                <div style="width:72px;height:72px;border-radius:14px;overflow:hidden;flex-shrink:0;border:1px solid #e5e7eb;">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" style="width:100%;height:100%;object-fit:cover;">
                </div>
                <?php else : ?>
                <div style="width:72px;height:72px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;flex-shrink:0;background:<?php echo $is_business ? 'linear-gradient(135deg,#00ADEF,#0369a1)' : 'linear-gradient(135deg,#7c3aed,#4f46e5)'; ?>;"><?php echo esc_html( $initial ); ?></div>
                <?php endif; ?>
                <div>
                    <h1 style="font-size:20px;font-weight:800;margin:0;line-height:1.3;">
                        <?php echo esc_html( $name ); ?>
                        <?php if ( $verified ) : ?>
                        <span title="<?php esc_attr_e( 'Verified Business', 'yourjannah' ); ?>" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#16a34a;color:#fff;font-size:11px;vertical-align:middle;margin-left:4px;">✓</span>
                        <?php endif; ?>
                    </h1>
                    <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-top:4px;background:<?php echo $is_business ? '#e8f4f8' : '#ede9fe'; ?>;color:<?php echo $is_business ? '#00ADEF' : '#7c3aed'; ?>;"><?php echo esc_html( $category ); ?></span>
                    <?php if ( $owner_name ) : ?>
                    <span style="display:block;font-size:12px;color:#6b8fa3;margin-top:4px;">by <?php echo esc_html( $owner_name ); ?></span>
                    <?php endif; ?>
                    <?php if ( $rate_pence ) : ?>
                    <span style="display:block;font-size:14px;font-weight:700;color:#16a34a;margin-top:4px;">£<?php echo number_format( $rate_pence / 100, 0 ); ?>/hr</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <?php if ( $description ) : ?>
            <div style="margin-bottom:16px;">
                <h3 style="font-size:13px;font-weight:700;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?php esc_html_e( 'About', 'yourjannah' ); ?></h3>
                <p style="font-size:14px;line-height:1.6;color:#333;margin:0;"><?php echo nl2br( esc_html( $description ) ); ?></p>
            </div>
            <?php endif; ?>

            <!-- Contact Details -->
            <div style="margin-bottom:16px;">
                <h3 style="font-size:13px;font-weight:700;color:#6b8fa3;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?php esc_html_e( 'Contact', 'yourjannah' ); ?></h3>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php if ( $phone ) : ?>
                    <a href="tel:<?php echo esc_attr( $phone ); ?>" style="display:flex;align-items:center;gap:10px;font-size:14px;color:#0a1628;text-decoration:none;">
                        <span style="width:36px;height:36px;border-radius:10px;background:#e8f4f8;display:flex;align-items:center;justify-content:center;">📞</span>
                        <?php echo esc_html( $phone ); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ( $email ) : ?>
                    <a href="mailto:<?php echo esc_attr( $email ); ?>" style="display:flex;align-items:center;gap:10px;font-size:14px;color:#0a1628;text-decoration:none;">
                        <span style="width:36px;height:36px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;">✉️</span>
                        <?php echo esc_html( $email ); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ( $website ) : ?>
                    <a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;font-size:14px;color:#00ADEF;text-decoration:none;">
                        <span style="width:36px;height:36px;border-radius:10px;background:#e8f4f8;display:flex;align-items:center;justify-content:center;">🌐</span>
                        <?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', $website ) ); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ( $address || $postcode ) : ?>
                    <div style="display:flex;align-items:center;gap:10px;font-size:14px;color:#0a1628;">
                        <span style="width:36px;height:36px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;">📍</span>
                        <?php echo esc_html( implode( ', ', array_filter( [ $address, $postcode ] ) ) ); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $area ) : ?>
                    <div style="display:flex;align-items:center;gap:10px;font-size:14px;color:#0a1628;">
                        <span style="width:36px;height:36px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;">🗺️</span>
                        <?php esc_html_e( 'Covers:', 'yourjannah' ); ?> <?php echo esc_html( $area ); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ( $phone ) :
                    $wa_num = preg_replace( '/[^0-9+]/', '', $phone );
                    $wa_num = preg_replace( '/^0/', '+44', $wa_num );
                ?>
                <a href="tel:<?php echo esc_attr( $phone ); ?>" class="ynj-btn" style="flex:1;justify-content:center;min-width:100px;">📞 <?php esc_html_e( 'Call', 'yourjannah' ); ?></a>
                <a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $wa_num ) ); ?>" target="_blank" rel="noopener" class="ynj-btn" style="flex:1;justify-content:center;min-width:100px;background:#25D366;border-color:#25D366;">💬 <?php esc_html_e( 'WhatsApp', 'yourjannah' ); ?></a>
                <?php endif; ?>
                <?php if ( $website ) : ?>
                <a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener" class="ynj-btn ynj-btn--outline" style="flex:1;justify-content:center;min-width:100px;">🌐 <?php esc_html_e( 'Website', 'yourjannah' ); ?></a>
                <?php endif; ?>
                <?php if ( $email ) : ?>
                <a href="mailto:<?php echo esc_attr( $email ); ?>" class="ynj-btn ynj-btn--outline" style="flex:1;justify-content:center;min-width:100px;">✉️ <?php esc_html_e( 'Email', 'yourjannah' ); ?></a>
                <?php endif; ?>
            </div>

            <!-- Mosque attribution -->
            <?php if ( $mosque_name ) : ?>
            <p style="margin-top:16px;font-size:12px;color:#6b8fa3;text-align:center;">
                <?php echo $is_business ? '⭐' : '🤝'; ?>
                <?php printf( esc_html__( 'Supports %s through YourJannah', 'yourjannah' ), esc_html( $mosque_name ) ); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Share -->
    <button onclick="ynjShare('<?php echo esc_js( $name ); ?>','Check out <?php echo esc_js( $name ); ?> on YourJannah','<?php echo esc_js( home_url( '/mosque/' . $slug . '/' . ( $is_business ? 'business' : 'service' ) . '/' . $item_id ) ); ?>')" class="ynj-btn ynj-btn--outline" style="width:100%;justify-content:center;margin-top:12px;">↗️ <?php esc_html_e( 'Share this listing', 'yourjannah' ); ?></button>
</main>

<?php get_footer(); ?>
