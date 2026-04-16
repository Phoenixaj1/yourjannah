<?php
/**
 * Template: Edit Listing (Pure PHP — no JS API calls)
 *
 * Handles both GET (show form) and POST (save changes).
 * URL: /mosque/{slug}/business/{id}/edit or /mosque/{slug}/service/{id}/edit
 *
 * @package YourJannah
 */

get_header();
$slug         = ynj_mosque_slug();
$item_id      = (int) get_query_var( 'ynj_item_id', 0 );
$listing_type = get_query_var( 'ynj_listing_type', 'business' );
$is_business  = ( $listing_type === 'business' );
$mosque       = ynj_get_mosque( $slug );

// Auth check
if ( ! is_user_logged_in() ) : ?>
<main class="ynj-main">
    <section class="ynj-card" style="text-align:center;padding:40px 20px;">
        <h2><?php esc_html_e( 'Login Required', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted"><?php esc_html_e( 'You need to be logged in to edit your listing.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="ynj-btn" style="margin-top:12px;display:inline-flex;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
    </section>
</main>
<?php get_footer(); return; endif;

// Load listing
$item = null;
if ( $item_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $table = $is_business ? YNJ_DB::table( 'businesses' ) : YNJ_DB::table( 'services' );
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $item_id ) );
}

if ( ! $item ) : ?>
<main class="ynj-main">
    <section class="ynj-card" style="text-align:center;padding:40px 20px;">
        <h2><?php esc_html_e( 'Listing Not Found', 'yourjannah' ); ?></h2>
        <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="ynj-btn" style="margin-top:12px;display:inline-flex;"><?php esc_html_e( '← Back to Profile', 'yourjannah' ); ?></a>
    </section>
</main>
<?php get_footer(); return; endif;

// Verify ownership (by email match or user_id)
$current_email = wp_get_current_user()->user_email;
$item_email = $item->email ?? '';
$owns_by_email = ( strtolower( $item_email ) === strtolower( $current_email ) );
$ynj_uid = (int) get_user_meta( get_current_user_id(), 'ynj_user_id', true );
$owns_by_id = ( $ynj_uid && (int) ( $item->user_id ?? 0 ) === $ynj_uid );

if ( ! $owns_by_email && ! $owns_by_id ) : ?>
<main class="ynj-main">
    <section class="ynj-card" style="text-align:center;padding:40px 20px;">
        <h2><?php esc_html_e( 'Access Denied', 'yourjannah' ); ?></h2>
        <p class="ynj-text-muted"><?php esc_html_e( 'You can only edit your own listings.', 'yourjannah' ); ?></p>
        <a href="<?php echo esc_url( home_url( '/profile' ) ); ?>" class="ynj-btn" style="margin-top:12px;display:inline-flex;"><?php esc_html_e( '← Back to Profile', 'yourjannah' ); ?></a>
    </section>
</main>
<?php get_footer(); return; endif;

// Handle POST — save changes
$saved = false;
$error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_edit_nonce'] ?? '', 'ynj_edit_listing' ) ) {
    global $wpdb;
    $table = $is_business ? YNJ_DB::table( 'businesses' ) : YNJ_DB::table( 'services' );
    $update = [];

    if ( $is_business ) {
        $update['business_name'] = sanitize_text_field( $_POST['business_name'] ?? '' );
        $update['category']      = sanitize_text_field( $_POST['category'] ?? '' );
        $update['description']   = sanitize_textarea_field( $_POST['description'] ?? '' );
        $update['phone']         = sanitize_text_field( $_POST['phone'] ?? '' );
        $update['email']         = sanitize_email( $_POST['email'] ?? '' );
        $update['website']       = esc_url_raw( $_POST['website'] ?? '' );
        $update['address']       = sanitize_text_field( $_POST['address'] ?? '' );
        $update['postcode']      = sanitize_text_field( $_POST['postcode'] ?? '' );
    } else {
        $update['provider_name']    = sanitize_text_field( $_POST['provider_name'] ?? '' );
        $update['service_type']     = sanitize_text_field( $_POST['service_type'] ?? '' );
        $update['description']      = sanitize_textarea_field( $_POST['description'] ?? '' );
        $update['phone']            = sanitize_text_field( $_POST['phone'] ?? '' );
        $update['email']            = sanitize_email( $_POST['email'] ?? '' );
        $update['area_covered']     = sanitize_text_field( $_POST['area_covered'] ?? '' );
        $update['hourly_rate_pence'] = (int) ( floatval( $_POST['hourly_rate'] ?? 0 ) * 100 );
    }

    $name_field = $is_business ? 'business_name' : 'provider_name';
    if ( empty( $update[ $name_field ] ) ) {
        $error = __( 'Name is required.', 'yourjannah' );
    } else {
        $wpdb->update( $table, $update, [ 'id' => $item_id ] );
        $saved = true;
        // Refresh item data
        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $item_id ) );
    }
}

$name        = $is_business ? ( $item->business_name ?? '' ) : ( $item->provider_name ?? '' );
$detail_url  = home_url( '/mosque/' . $slug . '/' . ( $is_business ? 'business' : 'service' ) . '/' . $item_id );
$profile_url = home_url( '/profile' );
?>

<main class="ynj-main">
    <a href="<?php echo esc_url( $profile_url ); ?>" style="display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:600;color:#00ADEF;text-decoration:none;margin-bottom:12px;">← <?php esc_html_e( 'Back to Profile', 'yourjannah' ); ?></a>

    <div class="ynj-card" style="padding:20px;">
        <h1 style="font-size:18px;font-weight:800;margin:0 0 4px;">✏️ <?php esc_html_e( 'Edit Listing', 'yourjannah' ); ?></h1>
        <p class="ynj-text-muted" style="margin-bottom:16px;"><?php echo esc_html( $name ); ?></p>

        <?php if ( $saved ) : ?>
        <div style="background:#dcfce7;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:600;">
            ✅ <?php esc_html_e( 'Changes saved successfully!', 'yourjannah' ); ?>
            <a href="<?php echo esc_url( $detail_url ); ?>" style="margin-left:8px;color:#166534;font-weight:700;"><?php esc_html_e( 'View listing →', 'yourjannah' ); ?></a>
        </div>
        <?php endif; ?>

        <?php if ( $error ) : ?>
        <div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'ynj_edit_listing', '_ynj_edit_nonce' ); ?>

            <?php if ( $is_business ) : ?>
            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Business Name *', 'yourjannah' ); ?></label>
                <input type="text" name="business_name" value="<?php echo esc_attr( $item->business_name ?? '' ); ?>" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>
            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Category', 'yourjannah' ); ?></label>
                <select name="category" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                    <?php
                    $cats = ['Restaurant','Grocery','Butcher','Clothing','Books & Gifts','Health','Legal','Finance','Insurance','Travel','Education','Automotive','Catering','Property','Technology','Bakery','Cafe','Food','Other'];
                    foreach ( $cats as $c ) : ?>
                    <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $item->category ?? '', $c ); ?>><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else : ?>
            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Provider Name *', 'yourjannah' ); ?></label>
                <input type="text" name="provider_name" value="<?php echo esc_attr( $item->provider_name ?? '' ); ?>" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>
            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Service Type', 'yourjannah' ); ?></label>
                <select name="service_type" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                    <?php
                    $types = ['Imam/Scholar','Quran Teacher','Arabic Tutor','Counselling','Legal Services','Accounting','Web Development','SEO/Marketing','IT Support','Plumbing','Electrician','Handyman','Catering','Photography','Tutoring','Driving Instructor','Personal Training','Property','Insurance','Travel/Hajj','Cleaning','Other'];
                    foreach ( $types as $t ) : ?>
                    <option value="<?php echo esc_attr( $t ); ?>" <?php selected( $item->service_type ?? '', $t ); ?>><?php echo esc_html( $t ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Description', 'yourjannah' ); ?></label>
                <textarea name="description" rows="4" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical;"><?php echo esc_textarea( $item->description ?? '' ); ?></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div class="ynj-field">
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label>
                    <input type="tel" name="phone" value="<?php echo esc_attr( $item->phone ?? '' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div class="ynj-field">
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Email', 'yourjannah' ); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr( $item->email ?? '' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
            </div>

            <?php if ( $is_business ) : ?>
            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Website', 'yourjannah' ); ?></label>
                <input type="url" name="website" value="<?php echo esc_attr( $item->website ?? '' ); ?>" placeholder="https://" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-bottom:12px;">
                <div class="ynj-field">
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Address', 'yourjannah' ); ?></label>
                    <input type="text" name="address" value="<?php echo esc_attr( $item->address ?? '' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div class="ynj-field">
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Postcode', 'yourjannah' ); ?></label>
                    <input type="text" name="postcode" value="<?php echo esc_attr( $item->postcode ?? '' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
            </div>
            <?php else : ?>
            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Area Covered', 'yourjannah' ); ?></label>
                <input type="text" name="area_covered" value="<?php echo esc_attr( $item->area_covered ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Birmingham, West Midlands', 'yourjannah' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>
            <div class="ynj-field" style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Hourly Rate (£)', 'yourjannah' ); ?></label>
                <input type="number" name="hourly_rate" min="0" step="0.01" value="<?php echo esc_attr( ( $item->hourly_rate_pence ?? 0 ) / 100 ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>
            <?php endif; ?>

            <button type="submit" class="ynj-btn" style="width:100%;justify-content:center;margin-top:8px;"><?php esc_html_e( 'Save Changes', 'yourjannah' ); ?></button>
        </form>
    </div>
</main>

<?php get_footer(); ?>
