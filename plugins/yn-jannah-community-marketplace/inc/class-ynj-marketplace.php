<?php
/**
 * Community Marketplace Data Layer — Gumtree-style listings using WordPress CPT.
 *
 * @package YNJ_Marketplace
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Marketplace {

    /**
     * Listing categories.
     */
    public static function get_categories() {
        return [
            'question'  => [ 'label' => 'Question',        'icon' => '❓' ],
            'sale'      => [ 'label' => 'For Sale',         'icon' => '🏷️' ],
            'wanted'    => [ 'label' => 'Wanted',           'icon' => '🔍' ],
            'service'   => [ 'label' => 'Service Offered',  'icon' => '🛠️' ],
            'free'      => [ 'label' => 'Free Item',        'icon' => '🎁' ],
            'help'      => [ 'label' => 'Community Help',   'icon' => '🤝' ],
        ];
    }

    /**
     * Get listings — searchable across ALL mosques for wider community.
     */
    public static function get_listings( $args = [] ) {
        $query_args = [
            'post_type'      => 'ynj_listing',
            'posts_per_page' => (int) ( $args['limit'] ?? 20 ),
            'paged'          => (int) ( $args['page'] ?? 1 ),
            'post_status'    => $args['status'] ?? 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Mosque filter
        $mosque_id = (int) ( $args['mosque_id'] ?? 0 );
        if ( $mosque_id ) {
            $query_args['meta_query'][] = [ 'key' => '_ynj_mosque_id', 'value' => $mosque_id, 'type' => 'NUMERIC' ];
        }

        // Category filter
        $category = $args['category'] ?? '';
        if ( $category ) {
            $query_args['meta_query'][] = [ 'key' => '_ynj_category', 'value' => sanitize_text_field( $category ) ];
        }

        // Search
        $search = $args['search'] ?? '';
        if ( $search ) {
            $query_args['s'] = sanitize_text_field( $search );
        }

        $query = new \WP_Query( $query_args );
        $listings = [];

        foreach ( $query->posts as $post ) {
            $listings[] = self::format( $post );
        }

        return [
            'listings' => $listings,
            'total'    => $query->found_posts,
            'pages'    => $query->max_num_pages,
        ];
    }

    /**
     * Get single listing.
     */
    public static function get_listing( $id ) {
        $post = get_post( (int) $id );
        if ( ! $post || $post->post_type !== 'ynj_listing' ) return null;
        return self::format( $post );
    }

    /**
     * Create a new listing (pending approval by masjid).
     */
    public static function create_listing( $data ) {
        $post_id = wp_insert_post( [
            'post_type'    => 'ynj_listing',
            'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
            'post_content' => sanitize_textarea_field( $data['description'] ?? '' ),
            'post_status'  => 'pending', // Needs masjid approval
        ] );

        if ( is_wp_error( $post_id ) ) return $post_id;

        update_post_meta( $post_id, '_ynj_mosque_id', (int) ( $data['mosque_id'] ?? 0 ) );
        update_post_meta( $post_id, '_ynj_category', sanitize_text_field( $data['category'] ?? 'question' ) );
        update_post_meta( $post_id, '_ynj_author_name', sanitize_text_field( $data['author_name'] ?? '' ) );
        update_post_meta( $post_id, '_ynj_author_email', sanitize_email( $data['author_email'] ?? '' ) );
        update_post_meta( $post_id, '_ynj_price', sanitize_text_field( $data['price'] ?? '' ) );
        update_post_meta( $post_id, '_ynj_location', sanitize_text_field( $data['location'] ?? '' ) );

        do_action( 'ynj_listing_created', $post_id, $data );

        return $post_id;
    }

    /**
     * Approve a listing.
     */
    public static function approve( $id ) {
        return wp_update_post( [ 'ID' => (int) $id, 'post_status' => 'publish' ] );
    }

    /**
     * Reject a listing.
     */
    public static function reject( $id ) {
        return wp_update_post( [ 'ID' => (int) $id, 'post_status' => 'trash' ] );
    }

    /**
     * Cross-community search.
     */
    public static function search( $query, $category = '', $limit = 20 ) {
        $args = [
            'search'   => $query,
            'category' => $category,
            'limit'    => $limit,
            'status'   => 'publish',
        ];
        return self::get_listings( $args );
    }

    /**
     * Get stats.
     */
    public static function get_stats( $mosque_id = 0 ) {
        $base = [ 'post_type' => 'ynj_listing', 'posts_per_page' => -1, 'fields' => 'ids' ];
        if ( $mosque_id ) {
            $base['meta_query'] = [ [ 'key' => '_ynj_mosque_id', 'value' => (int) $mosque_id, 'type' => 'NUMERIC' ] ];
        }

        $pending  = count( get_posts( array_merge( $base, [ 'post_status' => 'pending' ] ) ) );
        $approved = count( get_posts( array_merge( $base, [ 'post_status' => 'publish' ] ) ) );

        return [ 'pending' => $pending, 'approved' => $approved, 'total' => $pending + $approved ];
    }

    /**
     * Format a listing post into a clean array.
     */
    private static function format( $post ) {
        $cats = self::get_categories();
        $cat_key = get_post_meta( $post->ID, '_ynj_category', true ) ?: 'question';
        $cat = $cats[ $cat_key ] ?? $cats['question'];
        $mosque_id = (int) get_post_meta( $post->ID, '_ynj_mosque_id', true );

        // Get mosque name
        $mosque_name = '';
        if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
            global $wpdb;
            $mosque_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
            ) ) ?: '';
        }

        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => $post->post_content,
            'category'    => $cat_key,
            'cat_label'   => $cat['label'],
            'cat_icon'    => $cat['icon'],
            'author_name' => get_post_meta( $post->ID, '_ynj_author_name', true ),
            'author_email'=> get_post_meta( $post->ID, '_ynj_author_email', true ),
            'price'       => get_post_meta( $post->ID, '_ynj_price', true ),
            'location'    => get_post_meta( $post->ID, '_ynj_location', true ),
            'mosque_id'   => $mosque_id,
            'mosque_name' => $mosque_name,
            'status'      => $post->post_status,
            'date'        => $post->post_date,
        ];
    }
}
