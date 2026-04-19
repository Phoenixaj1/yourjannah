<?php
/**
 * Youth Activities Data Layer.
 * @package YNJ_Youth
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Youth {

    public static function get_categories() {
        return [
            'sports'    => [ 'label' => 'Sports',          'icon' => '⚽' ],
            'talks'     => [ 'label' => 'Talks & Lectures','icon' => '🎤' ],
            'classes'   => [ 'label' => 'Classes',         'icon' => '📚' ],
            'trips'     => [ 'label' => 'Trips & Outings', 'icon' => '🏕️' ],
            'quran'     => [ 'label' => 'Quran Circle',    'icon' => '📖' ],
            'mentoring' => [ 'label' => 'Mentoring',       'icon' => '🤝' ],
            'social'    => [ 'label' => 'Social Events',   'icon' => '🎉' ],
            'other'     => [ 'label' => 'Other',           'icon' => '✨' ],
        ];
    }

    public static function get_activities( $mosque_id, $args = [] ) {
        $query_args = [
            'post_type'      => 'ynj_youth_activity',
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
        foreach ( $query->posts as $post ) { $items[] = self::format( $post ); }
        return [ 'activities' => $items, 'total' => $query->found_posts ];
    }

    public static function create( $data ) {
        $post_id = wp_insert_post( [
            'post_type'    => 'ynj_youth_activity',
            'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
            'post_content' => sanitize_textarea_field( $data['description'] ?? '' ),
            'post_status'  => 'publish',
        ] );
        if ( is_wp_error( $post_id ) ) return $post_id;

        $mosque_id = (int) ( $data['mosque_id'] ?? 0 );
        if ( ! $mosque_id ) $mosque_id = (int) get_user_meta( get_current_user_id(), 'ynj_mosque_id', true );

        update_post_meta( $post_id, '_ynj_mosque_id', $mosque_id );
        update_post_meta( $post_id, '_ynj_category', sanitize_text_field( $data['category'] ?? 'other' ) );
        update_post_meta( $post_id, '_ynj_age_group', sanitize_text_field( $data['age_group'] ?? '8-16' ) );
        update_post_meta( $post_id, '_ynj_day', sanitize_text_field( $data['day'] ?? '' ) );
        update_post_meta( $post_id, '_ynj_time', sanitize_text_field( $data['time'] ?? '' ) );
        update_post_meta( $post_id, '_ynj_contact', sanitize_text_field( $data['contact'] ?? '' ) );

        do_action( 'ynj_youth_activity_created', $post_id, $data );
        return $post_id;
    }

    private static function format( $post ) {
        $cats = self::get_categories();
        $cat_key = get_post_meta( $post->ID, '_ynj_category', true ) ?: 'other';
        $cat = $cats[ $cat_key ] ?? $cats['other'];
        $mid = (int) get_post_meta( $post->ID, '_ynj_mosque_id', true );
        $mn = '';
        if ( $mid && class_exists( 'YNJ_DB' ) ) {
            global $wpdb;
            $mn = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM " . YNJ_DB::table('mosques') . " WHERE id=%d", $mid ) ) ?: '';
        }
        return [
            'id' => $post->ID, 'title' => $post->post_title, 'description' => $post->post_content,
            'category' => $cat_key, 'cat_label' => $cat['label'], 'cat_icon' => $cat['icon'],
            'age_group' => get_post_meta( $post->ID, '_ynj_age_group', true ),
            'day' => get_post_meta( $post->ID, '_ynj_day', true ),
            'time' => get_post_meta( $post->ID, '_ynj_time', true ),
            'contact' => get_post_meta( $post->ID, '_ynj_contact', true ),
            'mosque_id' => $mid, 'mosque_name' => $mn, 'date' => $post->post_date,
        ];
    }
}
