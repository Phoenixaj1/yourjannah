<?php
/**
 * YourJannah — REST API: Media upload via WordPress Media Library.
 *
 * Mosque admins can upload photos/logos which get stored in WP media library
 * with proper thumbnails, metadata, and CDN-ready URLs.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_API_Media {

    const NS = 'ynj/v1';

    public static function register() {

        // POST /admin/upload — Upload image (mosque admin auth)
        register_rest_route( self::NS, '/admin/upload', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'upload' ],
            'permission_callback' => [ 'YNJ_Auth', 'bearer_check' ],
        ] );

        // POST /mosques/{id}/cover-position — Save cover photo vertical position
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/cover-position', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'save_cover_position' ],
            'permission_callback' => function() {
                return is_user_logged_in() && (
                    current_user_can( 'manage_options' ) ||
                    in_array( 'ynj_mosque_admin', (array) wp_get_current_user()->roles, true )
                );
            },
        ] );

        // POST /mosques/{id}/image — Upload mosque cover/profile photo (WP cookie auth)
        register_rest_route( self::NS, '/mosques/(?P<id>\d+)/image', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'upload_mosque_image' ],
            'permission_callback' => function() {
                return is_user_logged_in() && (
                    current_user_can( 'manage_options' ) ||
                    in_array( 'ynj_mosque_admin', (array) wp_get_current_user()->roles, true )
                );
            },
        ] );
    }

    /**
     * POST /admin/upload — Upload an image to WP Media Library.
     *
     * Accepts multipart/form-data with a 'file' field.
     * Returns the attachment URL for use in mosque/event/room images.
     */
    public static function upload( \WP_REST_Request $request ) {
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'No file uploaded. Send as multipart/form-data with field name "file".' ], 400 );
        }

        $file = $files['file'];

        // Validate file type
        $allowed = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
        if ( ! in_array( $file['type'], $allowed, true ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Only JPEG, PNG, WebP, and GIF images allowed.' ], 400 );
        }

        // Max 5MB
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'File too large. Maximum 5MB.' ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Use WordPress media handling
        $upload = wp_handle_upload( $file, [ 'test_form' => false ] );

        if ( isset( $upload['error'] ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => $upload['error'] ], 500 );
        }

        // Create attachment in media library
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment( $attachment, $upload['file'] );

        if ( is_wp_error( $attach_id ) ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Failed to create media record.' ], 500 );
        }

        // Generate thumbnails
        $metadata = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $metadata );

        // Get various sizes
        $sizes = [
            'full'      => wp_get_attachment_url( $attach_id ),
            'thumbnail' => wp_get_attachment_image_url( $attach_id, 'thumbnail' ),
            'medium'    => wp_get_attachment_image_url( $attach_id, 'medium' ),
            'large'     => wp_get_attachment_image_url( $attach_id, 'large' ),
        ];

        return new \WP_REST_Response( [
            'ok'            => true,
            'attachment_id' => $attach_id,
            'url'           => $sizes['full'],
            'sizes'         => $sizes,
        ], 201 );
    }

    /**
     * POST /mosques/{id}/cover-position — Save cover photo vertical position (0-100%).
     */
    public static function save_cover_position( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        $data = $request->get_json_params();
        $position = max( 0, min( 100, (int) ( $data['position'] ?? 50 ) ) );
        update_option( 'ynj_mosque_cover_pos_' . $mosque_id, $position );
        return new \WP_REST_Response( [ 'ok' => true, 'position' => $position ] );
    }

    /**
     * POST /mosques/{id}/image — Upload cover or profile image for a mosque.
     *
     * Expects multipart/form-data with 'file' and 'type' (cover|profile).
     * Updates the mosque record with the new image URL.
     */
    public static function upload_mosque_image( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'id' ) );
        if ( ! $mosque_id ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Invalid mosque ID.' ], 400 );
        }

        // Verify mosque exists
        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $mosque = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $mt WHERE id = %d", $mosque_id ) );
        if ( ! $mosque ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Mosque not found.' ], 404 );
        }

        // Check admin owns this mosque (unless super-admin)
        if ( ! current_user_can( 'manage_options' ) ) {
            $wp_uid = get_current_user_id();
            $user_mosque = (int) get_user_meta( $wp_uid, 'ynj_mosque_id', true );
            if ( $user_mosque !== $mosque_id ) {
                return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Not your mosque.' ], 403 );
            }
        }

        // Upload the file using the existing upload method
        $upload_response = self::upload( $request );
        $data = $upload_response->get_data();

        if ( empty( $data['ok'] ) || empty( $data['url'] ) ) {
            return $upload_response;
        }

        // Determine type (cover or profile)
        $params = $request->get_body_params();
        $type = sanitize_text_field( $params['type'] ?? 'profile' );

        // Store in wp_options (matches template's get_option pattern)
        $option_key = $type === 'cover'
            ? 'ynj_mosque_cover_' . $mosque_id
            : 'ynj_mosque_profile_' . $mosque_id;
        update_option( $option_key, $data['url'] );

        // Also update mosque table column if it exists
        $column = $type === 'cover' ? 'cover_photo_url' : 'photo_url';
        $wpdb->update( $mt, [ $column => $data['url'] ], [ 'id' => $mosque_id ] );

        // Clear mosque cache
        $slug = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM $mt WHERE id = %d", $mosque_id ) );
        if ( $slug ) wp_cache_delete( 'ynj_mosque_' . $slug, 'ynj' );

        return new \WP_REST_Response( [
            'ok'   => true,
            'url'  => $data['url'],
            'type' => $type,
        ] );
    }
}
