<?php
/**
 * Celebrations Data Layer — share joy with your community.
 * @package YNJ_Celebrations
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Celebrations {

    public static function get_categories() {
        return [
            'quran_memorisation' => [ 'label' => 'Quran Memorisation', 'icon' => '📖' ],
            'hajj'               => [ 'label' => 'Hajj / Umrah',       'icon' => '🕋' ],
            'marriage'           => [ 'label' => 'Marriage',            'icon' => '💍' ],
            'new_baby'           => [ 'label' => 'New Baby',            'icon' => '👶' ],
            'revert'             => [ 'label' => 'New Muslim',          'icon' => '☪️' ],
            'community'          => [ 'label' => 'Community Achievement','icon' => '🤝' ],
            'other'              => [ 'label' => 'Other',               'icon' => '✨' ],
        ];
    }

    public static function get_celebrations( $mosque_id, $args = [] ) {
        $query_args = [
            'post_type'      => 'ynj_celebration',
            'posts_per_page' => (int) ( $args['limit'] ?? 20 ),
            'paged'          => (int) ( $args['page'] ?? 1 ),
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ( $mosque_id ) {
            $query_args['meta_query'][] = [ 'key' => '_ynj_mosque_id', 'value' => (int) $mosque_id, 'type' => 'NUMERIC' ];
        }
        $cat = $args['category'] ?? '';
        if ( $cat ) {
            $query_args['meta_query'][] = [ 'key' => '_ynj_category', 'value' => sanitize_text_field( $cat ) ];
        }

        $query = new \WP_Query( $query_args );
        $items = [];
        foreach ( $query->posts as $post ) {
            $items[] = self::format( $post );
        }
        return [ 'celebrations' => $items, 'total' => $query->found_posts ];
    }

    public static function get_all_celebrations( $args = [] ) {
        return self::get_celebrations( 0, $args );
    }

    public static function create( $data ) {
        $post_id = wp_insert_post( [
            'post_type'    => 'ynj_celebration',
            'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
            'post_content' => sanitize_textarea_field( $data['description'] ?? '' ),
            'post_status'  => 'publish', // Celebrations are positive — auto-approve
        ] );
        if ( is_wp_error( $post_id ) ) return $post_id;

        update_post_meta( $post_id, '_ynj_mosque_id', (int) ( $data['mosque_id'] ?? 0 ) );
        update_post_meta( $post_id, '_ynj_category', sanitize_text_field( $data['category'] ?? 'other' ) );
        update_post_meta( $post_id, '_ynj_author_name', sanitize_text_field( $data['author_name'] ?? '' ) );
        update_post_meta( $post_id, '_ynj_author_user_id', (int) ( $data['author_user_id'] ?? 0 ) );

        do_action( 'ynj_celebration_created', $post_id, $data );
        return $post_id;
    }

    public static function get_stats( $mosque_id = 0 ) {
        $base = [ 'post_type' => 'ynj_celebration', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ];
        if ( $mosque_id ) {
            $base['meta_query'] = [ [ 'key' => '_ynj_mosque_id', 'value' => (int) $mosque_id, 'type' => 'NUMERIC' ] ];
        }

        $all = get_posts( $base );
        $month = get_posts( array_merge( $base, [ 'date_query' => [ [ 'after' => '30 days ago' ] ] ] ) );

        // By category
        $by_cat = [];
        $cats = self::get_categories();
        foreach ( $all as $pid ) {
            $c = get_post_meta( $pid, '_ynj_category', true ) ?: 'other';
            $by_cat[ $c ] = ( $by_cat[ $c ] ?? 0 ) + 1;
        }

        return [ 'total' => count( $all ), 'this_month' => count( $month ), 'by_category' => $by_cat ];
    }

    private static function format( $post ) {
        $cats = self::get_categories();
        $cat_key = get_post_meta( $post->ID, '_ynj_category', true ) ?: 'other';
        $cat = $cats[ $cat_key ] ?? $cats['other'];
        $mosque_id = (int) get_post_meta( $post->ID, '_ynj_mosque_id', true );
        $mosque_name = '';
        if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
            global $wpdb;
            $mosque_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id=%d", $mosque_id ) ) ?: '';
        }

        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => $post->post_content,
            'category'    => $cat_key,
            'cat_label'   => $cat['label'],
            'cat_icon'    => $cat['icon'],
            'author_name' => get_post_meta( $post->ID, '_ynj_author_name', true ),
            'mosque_id'   => $mosque_id,
            'mosque_name' => $mosque_name,
            'date'        => $post->post_date,
        ];
    }
}
