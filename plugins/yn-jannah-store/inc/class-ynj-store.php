<?php
/**
 * Masjid Store — community shout-out items.
 *
 * Users purchase a "shout-out" → it posts as a special announcement in the feed.
 * 95% of the price goes to the masjid, 5% to YourJannah.
 *
 * @package YNJ_Store
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Store {

    const MASJID_SHARE = 95; // %
    const PLATFORM_SHARE = 5; // %

    /**
     * Get available store items.
     */
    public static function get_items() {
        return [
            'jumuah_mubarak' => [
                'icon'        => '🕌',
                'title'       => "Jumu'ah Mubarak",
                'description' => 'Send Jumuah Mubarak to the entire congregation',
                'prices'      => [ 300, 500, 1000 ], // pence
                'default'     => 500,
                'badge_color' => '#287e61',
                'badge_text'  => "Jumu'ah Mubarak",
                'template'    => '🕌 Jumu\'ah Mubarak from {name} to the entire congregation of {mosque}! May Allah accept our prayers.',
            ],
            'eid_mubarak' => [
                'icon'        => '🌙',
                'title'       => 'Eid Mubarak',
                'description' => 'Wish Eid Mubarak to the whole community',
                'prices'      => [ 500, 1000, 2000 ],
                'default'     => 1000,
                'badge_color' => '#7c3aed',
                'badge_text'  => 'Eid Mubarak',
                'template'    => '🌙 Eid Mubarak from {name} to the entire congregation of {mosque}! Taqabbal Allahu minna wa minkum.',
            ],
            'khatam_quran' => [
                'icon'        => '📗',
                'title'       => 'Khatam al-Quran',
                'description' => 'Announce a Quran completion to the congregation',
                'prices'      => [ 1000, 2000, 5000 ],
                'default'     => 2000,
                'badge_color' => '#0369a1',
                'badge_text'  => 'Quran Khatam',
                'template'    => '📗 MashaAllah! {name} has completed the Quran — Khatam Mubarak! May Allah reward them and accept it. Please make dua for them.',
            ],
            'hajj_mubarak' => [
                'icon'        => '🕋',
                'title'       => 'Hajj Mubarak',
                'description' => 'Congratulate someone who completed Hajj',
                'prices'      => [ 1000, 2000, 5000 ],
                'default'     => 2000,
                'badge_color' => '#92400e',
                'badge_text'  => 'Hajj Mubarak',
                'template'    => '🕋 Hajj Mubarak! {name} has completed Hajj. May Allah accept their pilgrimage. Please make dua for them.',
            ],
            'nikah_mubarak' => [
                'icon'        => '💍',
                'title'       => 'Nikah Mubarak',
                'description' => 'Announce a marriage blessing to the community',
                'prices'      => [ 1000, 2000, 5000 ],
                'default'     => 2000,
                'badge_color' => '#be185d',
                'badge_text'  => 'Nikah Mubarak',
                'template'    => '💍 Nikah Mubarak! {name} — may Allah bless this union with love, mercy, and barakah. The congregation of {mosque} sends their warmest congratulations.',
            ],
            'new_baby' => [
                'icon'        => '👶',
                'title'       => 'New Baby Mubarak',
                'description' => 'Share the joy of a new arrival',
                'prices'      => [ 500, 1000, 2000 ],
                'default'     => 1000,
                'badge_color' => '#059669',
                'badge_text'  => 'New Baby',
                'template'    => '👶 MashaAllah! {name} has been blessed with a new baby! May Allah make the child a source of joy and raise them upon goodness.',
            ],
            'dua_request' => [
                'icon'        => '🤲',
                'title'       => 'Community Dua Request',
                'description' => 'Ask the entire congregation to make dua for you',
                'prices'      => [ 300, 500, 1000 ],
                'default'     => 500,
                'badge_color' => '#1e40af',
                'badge_text'  => 'Dua Request',
                'template'    => '🤲 {name} is asking the congregation of {mosque} for dua. {message}',
            ],
            'thank_you' => [
                'icon'        => '💖',
                'title'       => 'Thank You Message',
                'description' => 'Thank the masjid and its community publicly',
                'prices'      => [ 300, 500, 1000 ],
                'default'     => 300,
                'badge_color' => '#9d174d',
                'badge_text'  => 'Thank You',
                'template'    => '💖 {name} says JazakAllah Khayr to the community of {mosque}. {message}',
            ],
        ];
    }

    /**
     * When a store item payment succeeds, auto-post announcement to the mosque feed.
     */
    public static function on_payment_succeeded( $txn_row_id, $txn ) {
        // Only handle store items
        if ( ( $txn->item_type ?? '' ) !== 'store' ) return;

        $items = self::get_items();
        $item_key = $txn->fund_type ?? '';
        $item = $items[ $item_key ] ?? null;
        if ( ! $item ) return;

        $mosque_id = (int) ( $txn->mosque_id ?? 0 );
        if ( ! $mosque_id ) return;

        // Get mosque name
        global $wpdb;
        $mosque_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) ) ?: 'the masjid';

        $donor_name = $txn->donor_name ?: 'A community member';

        // Get custom message from items_json
        $custom_msg = '';
        if ( $txn->items_json ) {
            $items_data = json_decode( $txn->items_json, true );
            $custom_msg = $items_data['message'] ?? '';
        }

        // Build announcement body from template
        $body = str_replace(
            [ '{name}', '{mosque}', '{message}' ],
            [ $donor_name, $mosque_name, $custom_msg ?: '' ],
            $item['template']
        );
        $body = trim( preg_replace( '/\s+/', ' ', $body ) );

        // Post as a pinned announcement
        if ( class_exists( 'YNJ_Events' ) ) {
            YNJ_Events::create_announcement( [
                'mosque_id' => $mosque_id,
                'title'     => $item['badge_text'] . ' — ' . $donor_name,
                'body'      => $body,
                'type'      => 'community',
                'publish'   => true,
                'pinned'    => 1,
            ] );
        }

        // Record revenue split (95% masjid, 5% platform)
        $amount = (int) ( $txn->amount_pence ?? 0 );
        $masjid_share = (int) floor( $amount * self::MASJID_SHARE / 100 );

        // Credit to revenue share if plugin active
        if ( class_exists( 'YNJ_Revenue_Share' ) ) {
            $rs_table = YNJ_DB::table( 'revenue_shares' );
            $wpdb->insert( $rs_table, [
                'mosque_id'             => $mosque_id,
                'donation_id'           => (int) $txn_row_id,
                'donation_amount_pence' => $amount,
                'share_amount_pence'    => $masjid_share,
                'cause'                 => 'store_' . $item_key,
                'status'                => 'pending',
            ] );
        }

        error_log( "[YNJ Store] Posted '{$item['badge_text']}' announcement for mosque #{$mosque_id} from {$donor_name}" );
    }
}
