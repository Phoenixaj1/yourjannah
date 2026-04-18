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
}
