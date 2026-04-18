<?php
/**
 * Mosque Selector Modal — search, GPS, select mosque.
 *
 * @package YNJ_HUD
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Get nearby mosques for pre-populating the modal
$_hud_nearby = [];
$mosque_slug = function_exists( 'ynj_mosque_slug' ) ? ynj_mosque_slug() : '';
$mosque_obj  = function_exists( 'ynj_get_mosque' ) ? ynj_get_mosque( $mosque_slug ) : null;

if ( class_exists( 'YNJ_DB' ) && $mosque_obj && $mosque_obj->latitude ) {
    global $wpdb;
    $mt = YNJ_DB::table( 'mosques' );
    $_hud_nearby = $wpdb->get_results( $wpdb->prepare(
        "SELECT slug, name, city, postcode,
                ( 6371 * acos( cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)) )) AS distance
         FROM $mt WHERE status IN ('active','unclaimed') AND latitude IS NOT NULL
         ORDER BY distance ASC LIMIT 5",
        $mosque_obj->latitude, $mosque_obj->longitude, $mosque_obj->latitude
    ) ) ?: [];
}
?>
<div class="ynj-mosque-modal" id="ynj-mosque-modal" style="display:none">
    <div class="ynj-mosque-modal__overlay"></div>
    <div class="ynj-mosque-modal__box">
        <button type="button" class="ynj-mosque-modal__close">&times;</button>
        <h3 class="ynj-mosque-modal__title">&#x1F54C; <?php esc_html_e( 'Find Your Mosque', 'yourjannah' ); ?></h3>
        <p class="ynj-mosque-modal__subtitle"><?php esc_html_e( 'Select a mosque near you or search by name.', 'yourjannah' ); ?></p>

        <div class="ynj-mosque-modal__search">
            <input type="text" id="ynj-mosque-search" placeholder="<?php esc_attr_e( 'Search by name, city, postcode...', 'yourjannah' ); ?>" autocomplete="off">
        </div>

        <button type="button" class="ynj-mosque-modal__gps" id="ynj-mosque-gps">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
            <span id="ynj-mosque-gps-text"><?php esc_html_e( 'Use my location', 'yourjannah' ); ?></span>
        </button>

        <div class="ynj-mosque-modal__list" id="ynj-mosque-list"></div>

        <a href="<?php echo esc_url( home_url( '/change-mosque' ) ); ?>" class="ynj-mosque-modal__browse"><?php esc_html_e( 'Browse All Mosques →', 'yourjannah' ); ?></a>
    </div>
</div>
<script>
window.ynjNearbyMosques = <?php echo json_encode( array_map( function( $m ) {
    return [
        'slug'     => $m->slug,
        'name'     => $m->name,
        'city'     => $m->city ?? '',
        'postcode' => $m->postcode ?? '',
        'distance' => isset( $m->distance ) ? round( (float) $m->distance, 1 ) : null,
    ];
}, $_hud_nearby ) ); ?>;
</script>
