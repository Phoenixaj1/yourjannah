<?php
/**
 * Imam Messages Data Layer — daily imam messages with front-end posting.
 * @package YNJ_Imam_Messages
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Imam_Messages {

    /**
     * Get messages for a mosque.
     */
    public static function get_messages( $mosque_id, $limit = 10 ) {
        $args = [
            'post_type'      => 'ynj_imam_message',
            'posts_per_page' => absint( $limit ),
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [ [ 'key' => '_ynj_mosque_id', 'value' => (int) $mosque_id, 'type' => 'NUMERIC' ] ],
        ];

        $query = new \WP_Query( $args );
        $messages = [];
        foreach ( $query->posts as $post ) {
            $messages[] = self::format( $post );
        }
        return $messages;
    }

    /**
     * Get today's message for a mosque.
     */
    public static function get_todays_message( $mosque_id ) {
        $args = [
            'post_type'      => 'ynj_imam_message',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'date_query'     => [ [ 'after' => 'today' ] ],
            'meta_query'     => [ [ 'key' => '_ynj_mosque_id', 'value' => (int) $mosque_id, 'type' => 'NUMERIC' ] ],
        ];
        $query = new \WP_Query( $args );
        return $query->have_posts() ? self::format( $query->posts[0] ) : null;
    }

    /**
     * Create a message (front-end or admin).
     */
    public static function create( $data ) {
        $post_id = wp_insert_post( [
            'post_type'    => 'ynj_imam_message',
            'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
            'post_content' => sanitize_textarea_field( $data['body'] ?? '' ),
            'post_status'  => 'publish',
        ] );
        if ( is_wp_error( $post_id ) ) return $post_id;

        $mosque_id = (int) ( $data['mosque_id'] ?? 0 );
        if ( ! $mosque_id ) {
            $mosque_id = (int) get_user_meta( get_current_user_id(), 'ynj_mosque_id', true );
        }
        update_post_meta( $post_id, '_ynj_mosque_id', $mosque_id );
        update_post_meta( $post_id, '_ynj_author_name', sanitize_text_field( $data['author_name'] ?? wp_get_current_user()->display_name ) );
        update_post_meta( $post_id, '_ynj_category', sanitize_text_field( $data['category'] ?? 'daily' ) );

        do_action( 'ynj_imam_message_created', $post_id, $data );
        return $post_id;
    }

    /**
     * REST: Create message (front-end posting).
     */
    public static function api_create( \WP_REST_Request $request ) {
        $data = $request->get_json_params();
        $id = self::create( $data );
        if ( is_wp_error( $id ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $id->get_error_message() ], 400 );
        }
        return new \WP_REST_Response( [ 'ok' => true, 'id' => $id ] );
    }

    /**
     * REST: List messages.
     */
    public static function api_list( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'mosque_id' ) );
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id required' ], 400 );
        }
        return new \WP_REST_Response( [ 'ok' => true, 'messages' => self::get_messages( $mosque_id ) ] );
    }

    /**
     * Get categories.
     */
    public static function get_categories() {
        return [
            'daily'    => [ 'label' => 'Daily Reminder', 'icon' => '🕌' ],
            'friday'   => [ 'label' => 'Friday Message',  'icon' => '📿' ],
            'dua'      => [ 'label' => 'Dua',             'icon' => '🤲' ],
            'hadith'   => [ 'label' => 'Hadith',           'icon' => '📖' ],
            'quran'    => [ 'label' => 'Quran Reflection', 'icon' => '📗' ],
            'notice'   => [ 'label' => 'Important Notice', 'icon' => '📢' ],
        ];
    }

    private static function format( $post ) {
        $cats = self::get_categories();
        $cat_key = get_post_meta( $post->ID, '_ynj_category', true ) ?: 'daily';
        $cat = $cats[ $cat_key ] ?? $cats['daily'];

        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'body'        => $post->post_content,
            'category'    => $cat_key,
            'cat_label'   => $cat['label'],
            'cat_icon'    => $cat['icon'],
            'author_name' => get_post_meta( $post->ID, '_ynj_author_name', true ) ?: 'Imam',
            'mosque_id'   => (int) get_post_meta( $post->ID, '_ynj_mosque_id', true ),
            'date'        => $post->post_date,
        ];
    }
}
