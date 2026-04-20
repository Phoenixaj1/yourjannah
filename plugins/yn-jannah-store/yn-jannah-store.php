<?php
/**
 * Plugin Name: YourJannah — Masjid Store
 * Description: Digital community shout-outs — purchasable announcements with images. 95% goes to masjid. Fully admin-managed.
 * Version:     1.1.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_STORE_VERSION', '1.1.0' );
define( 'YNJ_STORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'YNJ_STORE_DB_VERSION', '1.0.0' );

// ── Create table on activation ──
register_activation_hook( __FILE__, function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $t = YNJ_DB::table( 'store_items' );

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( "CREATE TABLE $t (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        item_key varchar(50) NOT NULL DEFAULT '',
        title varchar(255) NOT NULL DEFAULT '',
        description varchar(500) NOT NULL DEFAULT '',
        icon varchar(10) NOT NULL DEFAULT '',
        image_url varchar(500) NOT NULL DEFAULT '',
        price_1 int(11) NOT NULL DEFAULT 300,
        price_2 int(11) NOT NULL DEFAULT 500,
        price_3 int(11) NOT NULL DEFAULT 1000,
        default_price int(11) NOT NULL DEFAULT 500,
        badge_color varchar(20) NOT NULL DEFAULT '#287e61',
        badge_text varchar(100) NOT NULL DEFAULT '',
        announcement_template text NOT NULL,
        sort_order int(11) NOT NULL DEFAULT 0,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY item_key (item_key),
        KEY is_active (is_active)
    ) $charset;" );

    // Seed default items if table is empty
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
    if ( $count === 0 ) {
        $defaults = [
            // ── Glad Tidings (Announcements) ──
            [ 'hajj_completion', 'Announce Hajj Completion', 'Share that someone has returned from Hajj', '🕋', 500, 500, 500, 500, '#92400e', 'Hajj Completion', '🕋 Alhamdulillah! {name} announces a Hajj completion from the congregation of {mosque}. Hajj Mabroor — may Allah accept the pilgrimage and grant Jannatul Firdaus.' ],
            [ 'umrah_completion', 'Announce Umrah Completion', 'Share that someone has completed Umrah', '🕋', 500, 500, 500, 500, '#92400e', 'Umrah Completion', '🕋 Alhamdulillah! {name} announces an Umrah completion from the congregation of {mosque}. Umrah Mubarak — may Allah accept it.' ],
            [ 'quran_khatam', 'Announce Quran Khatam', 'Share that someone has completed the Quran', '📗', 500, 500, 500, 500, '#0369a1', 'Quran Khatam', '📗 MashaAllah! {name} announces a Quran completion from the congregation of {mosque}. May Allah elevate them and make them among the people of the Quran.' ],
            [ 'nikah_announcement', 'Announce a Nikah', 'Share a marriage with the congregation', '💍', 500, 500, 500, 500, '#be185d', 'Nikah Announcement', '💍 Alhamdulillah! {name} announces a Nikah from the congregation of {mosque}. Barakallahu lakuma wa baraka alaikuma — may Allah bless this union.' ],
            [ 'new_baby', 'Announce a New Baby', 'Share the arrival of a newborn', '👶', 500, 500, 500, 500, '#059669', 'New Baby', '👶 MashaAllah! {name} announces the arrival of a new baby from the congregation of {mosque}. May Allah make the child a coolness for the eyes and a source of joy.' ],
            [ 'shahada', 'Announce a New Shahada', 'Welcome a new Muslim into the community', '☪️', 500, 500, 500, 500, '#16a34a', 'New Shahada', '☪️ Allahu Akbar! {name} announces that a new Muslim has taken their Shahada at {mosque}. May Allah keep them steadfast on the straight path.' ],
            [ 'graduation', 'Announce a Graduation', 'Celebrate an academic achievement', '🎓', 500, 500, 500, 500, '#7c3aed', 'Graduation', '🎓 MashaAllah! {name} announces a graduation from the congregation of {mosque}. May Allah put barakah in their knowledge and make it beneficial.' ],

            // ── Seasonal Greetings ──
            [ 'jumuah_mubarak', "Jumu'ah Mubarak", 'Send blessed Friday greetings to the congregation', '🕌', 500, 500, 500, 500, '#287e61', "Jumu'ah Mubarak", "🕌 Jumu'ah Mubarak from {name} to the entire congregation of {mosque}! May Allah accept our prayers and grant us goodness on this blessed day." ],
            [ 'eid_mubarak', 'Eid Mubarak', 'Send Eid greetings to the whole community', '🌙', 500, 500, 500, 500, '#7c3aed', 'Eid Mubarak', '🌙 Eid Mubarak from {name} to the entire congregation of {mosque}! Taqabbal Allahu minna wa minkum — may Allah accept from us and from you.' ],
            [ 'ramadan_mubarak', 'Ramadan Mubarak', 'Send Ramadan greetings to the congregation', '🌙', 500, 500, 500, 500, '#7c3aed', 'Ramadan Mubarak', '🌙 Ramadan Mubarak from {name} to the entire congregation of {mosque}! May this blessed month bring you closer to Allah and fill your home with barakah.' ],

            // ── Requests ──
            [ 'dua_request', 'Request Dua', 'Ask the congregation to make dua', '🤲', 500, 500, 500, 500, '#1e40af', 'Dua Request', '🤲 {name} from the congregation of {mosque} is humbly requesting dua. Please remember them in your prayers.' ],
            [ 'shifa_request', 'Request Shifa', 'Ask the congregation for healing dua', '💚', 500, 500, 500, 500, '#059669', 'Shifa Request', '💚 {name} from the congregation of {mosque} is requesting dua for shifa (healing). Ya Allah, grant them complete recovery and ease their suffering.' ],
            [ 'inna_lillahi', 'Inna Lillahi wa Inna Ilayhi Rajiun', 'Share news of a passing with the congregation', '🕊️', 500, 500, 500, 500, '#4b5563', 'Inna Lillahi', '🕊️ Inna lillahi wa inna ilayhi rajiun. {name} shares news of a passing from the congregation of {mosque}. May Allah forgive the deceased, expand their grave, and grant patience to the family.' ],

            // ── Gratitude ──
            [ 'jazakallah', 'JazakAllah Khayr', 'Thank the masjid and its community', '💖', 500, 500, 500, 500, '#9d174d', 'JazakAllah Khayr', '💖 {name} says JazakAllah Khayr to the community of {mosque}. May Allah reward you all with the best of rewards.' ],
        ];
        foreach ( $defaults as $i => $d ) {
            $wpdb->insert( $t, [
                'item_key' => $d[0], 'title' => $d[1], 'description' => $d[2], 'icon' => $d[3],
                'price_1' => $d[4], 'price_2' => $d[5], 'price_3' => $d[6], 'default_price' => $d[7],
                'badge_color' => $d[8], 'badge_text' => $d[9], 'announcement_template' => $d[10],
                'sort_order' => $i,
            ] );
        }
    }

    update_option( 'ynj_store_db_version', YNJ_STORE_DB_VERSION );
} );

// ── v3: Restructure superchat items for existing installs ──
// Renames items from response-style to announcement-style perspective
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;
    if ( get_option( 'ynj_store_v3_announcements' ) ) return;

    global $wpdb;
    $t = YNJ_DB::table( 'store_items' );
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) ) return;

    // Rename existing items to announcement perspective
    $renames = [
        'hajj_mubarak'    => [ 'hajj_completion', 'Announce Hajj Completion', 'Share that someone has returned from Hajj', 'Hajj Completion', '🕋 Alhamdulillah! {name} announces a Hajj completion from the congregation of {mosque}. Hajj Mabroor — may Allah accept the pilgrimage and grant Jannatul Firdaus.' ],
        'khatam_quran'    => [ 'quran_khatam', 'Announce Quran Khatam', 'Share that someone has completed the Quran', 'Quran Khatam', '📗 MashaAllah! {name} announces a Quran completion from the congregation of {mosque}. May Allah elevate them and make them among the people of the Quran.' ],
        'nikah_mubarak'   => [ 'nikah_announcement', 'Announce a Nikah', 'Share a marriage with the congregation', 'Nikah Announcement', '💍 Alhamdulillah! {name} announces a Nikah from the congregation of {mosque}. Barakallahu lakuma wa baraka alaikuma — may Allah bless this union.' ],
        'new_baby'        => [ 'new_baby', 'Announce a New Baby', 'Share the arrival of a newborn', 'New Baby', '👶 MashaAllah! {name} announces the arrival of a new baby from the congregation of {mosque}. May Allah make the child a coolness for the eyes and a source of joy.' ],
        'umrah_mubarak'   => [ 'umrah_completion', 'Announce Umrah Completion', 'Share that someone has completed Umrah', 'Umrah Completion', '🕋 Alhamdulillah! {name} announces an Umrah completion from the congregation of {mosque}. Umrah Mubarak — may Allah accept it.' ],
        'shahada'         => [ 'shahada', 'Announce a New Shahada', 'Welcome a new Muslim into the community', 'New Shahada', '☪️ Allahu Akbar! {name} announces that a new Muslim has taken their Shahada at {mosque}. May Allah keep them steadfast on the straight path.' ],
        'graduation'      => [ 'graduation', 'Announce a Graduation', 'Celebrate an academic achievement', 'Graduation', '🎓 MashaAllah! {name} announces a graduation from the congregation of {mosque}. May Allah put barakah in their knowledge and make it beneficial.' ],
        'dua_request'     => [ 'dua_request', 'Request Dua', 'Ask the congregation to make dua', 'Dua Request', '🤲 {name} from the congregation of {mosque} is humbly requesting dua. Please remember them in your prayers.' ],
        'get_well'        => [ 'shifa_request', 'Request Shifa', 'Ask the congregation for healing dua', 'Shifa Request', '💚 {name} from the congregation of {mosque} is requesting dua for shifa (healing). Ya Allah, grant them complete recovery and ease their suffering.' ],
        'condolence'      => [ 'inna_lillahi', 'Inna Lillahi wa Inna Ilayhi Rajiun', 'Share news of a passing with the congregation', 'Inna Lillahi', '🕊️ Inna lillahi wa inna ilayhi rajiun. {name} shares news of a passing from the congregation of {mosque}. May Allah forgive the deceased, expand their grave, and grant patience to the family.' ],
        'thank_you'       => [ 'jazakallah', 'JazakAllah Khayr', 'Thank the masjid and its community', 'JazakAllah Khayr', '💖 {name} says JazakAllah Khayr to the community of {mosque}. May Allah reward you all with the best of rewards.' ],
    ];

    foreach ( $renames as $old_key => $new ) {
        $exists = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $t WHERE item_key = %s", $old_key ) );
        if ( $exists ) {
            $wpdb->update( $t, [
                'item_key'              => $new[0],
                'title'                 => $new[1],
                'description'           => $new[2],
                'badge_text'            => $new[3],
                'announcement_template' => $new[4],
                'default_price'         => 500,
                'price_1'               => 500,
                'price_2'               => 500,
                'price_3'               => 500,
            ], [ 'id' => $exists->id ] );
        }
    }

    update_option( 'ynj_store_v3_announcements', 1 );
}, 10 );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    require_once YNJ_STORE_DIR . 'inc/class-ynj-store.php';

    // When a unified checkout payment succeeds for a store item, auto-post
    add_action( 'ynj_unified_payment_succeeded', [ 'YNJ_Store', 'on_payment_succeeded' ], 10, 2 );

    // WP Admin
    if ( is_admin() ) {
        require_once YNJ_STORE_DIR . 'inc/class-ynj-store-admin.php';
        YNJ_Store_Admin::init();
    }
}, 10 );
