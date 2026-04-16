<?php
/**
 * Template: Change Mosque
 *
 * Pure PHP mosque selector — search + nearby mosques.
 * No JS dependency. Links work as standard HTML.
 *
 * @package YourJannah
 */

get_header();

// Handle search
$search_q = sanitize_text_field( $_GET['q'] ?? '' );
$search_results = [];
$nearby = [];

if ( class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $mt = YNJ_DB::table( 'mosques' );

    // Search results
    if ( $search_q && strlen( $search_q ) >= 2 ) {
        $like = '%' . $wpdb->esc_like( $search_q ) . '%';
        $search_results = $wpdb->get_results( $wpdb->prepare(
            "SELECT slug, name, city, postcode FROM $mt WHERE status IN ('active','unclaimed') AND (name LIKE %s OR postcode LIKE %s OR city LIKE %s) ORDER BY name ASC LIMIT 20",
            $like, $like, $like
        ) ) ?: [];
    }

    // Get nearby mosques based on current mosque location
    $current_slug = get_query_var( 'ynj_mosque_slug', '' );
    if ( ! $current_slug ) {
        $current_slug = sanitize_title( $_COOKIE['ynj_mosque_slug'] ?? '' );
    }
    $current = $current_slug ? ynj_get_mosque( $current_slug ) : null;
    if ( $current && $current->latitude ) {
        $nearby = $wpdb->get_results( $wpdb->prepare(
            "SELECT slug, name, city, postcode,
                    ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
             FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
             ORDER BY distance ASC LIMIT 10",
            $current->latitude, $current->longitude, $current->latitude
        ) ) ?: [];
    } else {
        // No current mosque — show popular mosques
        $nearby = $wpdb->get_results(
            "SELECT slug, name, city, postcode FROM $mt WHERE status IN ('active','unclaimed') ORDER BY name ASC LIMIT 20"
        ) ?: [];
    }
}
?>

<main class="ynj-main" style="max-width:500px;margin:0 auto;">
    <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;">🕌 <?php esc_html_e( 'Find Your Mosque', 'yourjannah' ); ?></h2>
    <p class="ynj-text-muted" style="margin-bottom:16px;"><?php esc_html_e( 'Search by name, city, or postcode.', 'yourjannah' ); ?></p>

    <!-- Search form — standard HTML, no JS needed -->
    <form method="get" action="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" style="margin-bottom:20px;">
        <div style="display:flex;gap:8px;">
            <input type="text" name="q" value="<?php echo esc_attr( $search_q ); ?>" placeholder="<?php esc_attr_e( 'Search mosques...', 'yourjannah' ); ?>" autofocus style="flex:1;padding:12px 16px;border:2px solid #d1d5db;border-radius:12px;font-size:15px;font-family:inherit;">
            <button type="submit" style="padding:12px 20px;border:none;border-radius:12px;background:#00ADEF;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;"><?php esc_html_e( 'Search', 'yourjannah' ); ?></button>
        </div>
    </form>

    <?php if ( $search_q ) : ?>
        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;"><?php printf( esc_html__( 'Results for "%s"', 'yourjannah' ), esc_html( $search_q ) ); ?></h3>
        <?php if ( empty( $search_results ) ) : ?>
            <p class="ynj-text-muted" style="padding:20px 0;text-align:center;"><?php esc_html_e( 'No mosques found. Try a different search.', 'yourjannah' ); ?></p>
        <?php else : ?>
            <div style="display:flex;flex-direction:column;gap:4px;margin-bottom:24px;">
            <?php foreach ( $search_results as $m ) : ?>
                <a href="<?php echo esc_url( home_url( '/?ynj_select=' . $m->slug ) ); ?>" style="display:block;padding:14px 16px;border-radius:12px;background:#fff;border:1px solid #e5e7eb;text-decoration:none;color:#0a1628;transition:border-color .15s;">
                    <strong style="font-size:14px;display:block;"><?php echo esc_html( $m->name ); ?></strong>
                    <span style="font-size:12px;color:#6b8fa3;"><?php echo esc_html( implode( ', ', array_filter( [ $m->city, $m->postcode ] ) ) ); ?></span>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">📍 <?php echo $search_q ? esc_html__( 'Nearby Mosques', 'yourjannah' ) : esc_html__( 'Browse Mosques', 'yourjannah' ); ?></h3>
    <div style="display:flex;flex-direction:column;gap:4px;">
    <?php foreach ( $nearby as $m ) :
        $dist = isset( $m->distance ) ? ' · ' . number_format( (float) $m->distance, 1 ) . 'km' : '';
    ?>
        <a href="<?php echo esc_url( home_url( '/?ynj_select=' . $m->slug ) ); ?>" style="display:block;padding:14px 16px;border-radius:12px;background:#fff;border:1px solid #e5e7eb;text-decoration:none;color:#0a1628;transition:border-color .15s;">
            <strong style="font-size:14px;display:block;"><?php echo esc_html( $m->name ); ?></strong>
            <span style="font-size:12px;color:#6b8fa3;"><?php echo esc_html( implode( ', ', array_filter( [ $m->city, $m->postcode ] ) ) ); ?><?php echo esc_html( $dist ); ?></span>
        </a>
    <?php endforeach; ?>
    </div>
</main>

<?php get_footer(); ?>
