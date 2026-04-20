<?php
/**
 * Masjid Store — reads items from DB, handles payment success.
 * @package YNJ_Store
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Store {

    const MASJID_SHARE = 95;
    const PLATFORM_SHARE = 5;

    /**
     * Get all active store items from DB.
     */
    public static function get_items() {
        global $wpdb;
        $t = YNJ_DB::table( 'store_items' );
        return $wpdb->get_results(
            "SELECT * FROM $t WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
        ) ?: [];
    }

    /**
     * Get a single item by key.
     */
    public static function get_item( $key ) {
        global $wpdb;
        $t = YNJ_DB::table( 'store_items' );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $t WHERE item_key = %s", sanitize_text_field( $key )
        ) );
    }

    /**
     * Get item by ID.
     */
    public static function get_item_by_id( $id ) {
        global $wpdb;
        $t = YNJ_DB::table( 'store_items' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", absint( $id ) ) );
    }

    /**
     * Create or update an item.
     */
    public static function save_item( $data, $id = 0 ) {
        global $wpdb;
        $t = YNJ_DB::table( 'store_items' );

        $fields = [
            'item_key'              => sanitize_title( $data['item_key'] ?? '' ),
            'title'                 => sanitize_text_field( $data['title'] ?? '' ),
            'description'           => sanitize_text_field( $data['description'] ?? '' ),
            'icon'                  => sanitize_text_field( $data['icon'] ?? '' ),
            'image_url'             => esc_url_raw( $data['image_url'] ?? '' ),
            'price_1'               => absint( $data['price_1'] ?? 300 ),
            'price_2'               => absint( $data['price_2'] ?? 500 ),
            'price_3'               => absint( $data['price_3'] ?? 1000 ),
            'default_price'         => absint( $data['default_price'] ?? 500 ),
            'badge_color'           => sanitize_hex_color( $data['badge_color'] ?? '#287e61' ) ?: '#287e61',
            'badge_text'            => sanitize_text_field( $data['badge_text'] ?? '' ),
            'announcement_template' => sanitize_textarea_field( $data['announcement_template'] ?? '' ),
            'sort_order'            => (int) ( $data['sort_order'] ?? 0 ),
            'is_active'             => absint( $data['is_active'] ?? 1 ),
        ];

        if ( $id ) {
            $wpdb->update( $t, $fields, [ 'id' => absint( $id ) ] );
            return absint( $id );
        } else {
            $wpdb->insert( $t, $fields );
            return (int) $wpdb->insert_id;
        }
    }

    /**
     * Delete an item.
     */
    public static function delete_item( $id ) {
        global $wpdb;
        return $wpdb->delete( YNJ_DB::table( 'store_items' ), [ 'id' => absint( $id ) ] );
    }

    /** Private item keys — announcements for these go to admin only, not community feed. */
    const PRIVATE_ITEMS = [ 'dua_request', 'condolence' ];

    /**
     * When a store item payment succeeds, auto-post announcement.
     * Amounts are NEVER shown publicly. Private items (dua_request) only notify admin.
     */
    public static function on_payment_succeeded( $txn_row_id, $txn ) {
        // Handle both single-item (item_type=store) and multi-item (items_json array)
        $store_items_to_process = [];

        if ( ( $txn->item_type ?? '' ) === 'store' ) {
            $store_items_to_process[] = [
                'fund_type' => $txn->fund_type ?? '',
                'items_json' => $txn->items_json,
            ];
        }

        // Check items_json for multi-item carts
        if ( $txn->items_json ) {
            $decoded = json_decode( $txn->items_json, true );
            if ( is_array( $decoded ) && isset( $decoded[0]['item_type'] ) ) {
                foreach ( $decoded as $cart_item ) {
                    if ( ( $cart_item['item_type'] ?? '' ) === 'store' ) {
                        $meta = $cart_item['meta'] ?? [];
                        $store_items_to_process[] = [
                            'fund_type' => $cart_item['fund_type'] ?? '',
                            'items_json' => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
                        ];
                    }
                }
            }
        }

        if ( empty( $store_items_to_process ) ) return;

        $mosque_id = (int) ( $txn->mosque_id ?? 0 );
        if ( ! $mosque_id ) return;

        global $wpdb;
        $mosque_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) ) ?: 'the masjid';

        $donor_name = $txn->donor_name ?: 'A community member';

        foreach ( $store_items_to_process as $si ) {
            $item = self::get_item( $si['fund_type'] );
            if ( ! $item ) continue;

            $custom_msg = '';
            if ( $si['items_json'] ) {
                $d = json_decode( $si['items_json'], true );
                $custom_msg = is_array( $d ) ? ( $d['message'] ?? '' ) : '';
            }

            $body = str_replace(
                [ '{name}', '{mosque}', '{message}' ],
                [ $donor_name, $mosque_name, $custom_msg ?: '' ],
                $item->announcement_template
            );
            $body = trim( preg_replace( '/\s+/', ' ', $body ) );

            $is_private = in_array( $item->item_key, self::PRIVATE_ITEMS, true );

            if ( class_exists( 'YNJ_Events' ) ) {
                if ( $is_private ) {
                    // Private items: only visible to mosque admin, not community feed
                    YNJ_Events::create_announcement( [
                        'mosque_id' => $mosque_id,
                        'title'     => $item->badge_text . ' — ' . $donor_name,
                        'body'      => $body,
                        'type'      => 'admin',
                        'publish'   => true,
                        'pinned'    => 0,
                    ] );
                } else {
                    // Public items: post to community feed (amount NOT included)
                    YNJ_Events::create_announcement( [
                        'mosque_id' => $mosque_id,
                        'title'     => $item->badge_text . ' — ' . $donor_name,
                        'body'      => $body,
                        'type'      => 'community',
                        'publish'   => true,
                        'pinned'    => 1,
                    ] );
                }
            }
        }

        // Revenue share: 95% to masjid (on total store amount)
        $amount = (int) ( $txn->amount_pence ?? 0 );
        $masjid_share = (int) floor( $amount * self::MASJID_SHARE / 100 );
        if ( $amount > 0 && class_exists( 'YNJ_Revenue_Share' ) ) {
            $wpdb->insert( YNJ_DB::table( 'revenue_shares' ), [
                'mosque_id'             => $mosque_id,
                'donation_id'           => (int) $txn_row_id,
                'donation_amount_pence' => $amount,
                'share_amount_pence'    => $masjid_share,
                'cause'                 => 'store',
                'status'                => 'pending',
            ] );
        }
    }
}
