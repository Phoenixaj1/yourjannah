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
            [ 'jumuah_mubarak', "Jumu'ah Mubarak", 'Send Jumuah Mubarak to the entire congregation', '🕌', 300, 500, 1000, 500, '#287e61', "Jumu'ah Mubarak", "🕌 Jumu'ah Mubarak from {name} to the entire congregation of {mosque}! May Allah accept our prayers." ],
            [ 'eid_mubarak', 'Eid Mubarak', 'Wish Eid Mubarak to the whole community', '🌙', 500, 1000, 2000, 1000, '#7c3aed', 'Eid Mubarak', '🌙 Eid Mubarak from {name} to the entire congregation of {mosque}! Taqabbal Allahu minna wa minkum.' ],
            [ 'khatam_quran', 'Khatam al-Quran', 'Announce a Quran completion to the congregation', '📗', 1000, 2000, 5000, 2000, '#0369a1', 'Quran Khatam', '📗 MashaAllah! {name} has completed the Quran — Khatam Mubarak! May Allah reward them. Please make dua.' ],
            [ 'hajj_mubarak', 'Hajj Mubarak', 'Congratulate someone who completed Hajj', '🕋', 1000, 2000, 5000, 2000, '#92400e', 'Hajj Mubarak', '🕋 Hajj Mubarak! {name} has completed Hajj. May Allah accept their pilgrimage.' ],
            [ 'nikah_mubarak', 'Nikah Mubarak', 'Announce a marriage blessing to the community', '💍', 1000, 2000, 5000, 2000, '#be185d', 'Nikah Mubarak', '💍 Nikah Mubarak! {name} — may Allah bless this union with love, mercy, and barakah.' ],
            [ 'new_baby', 'New Baby Mubarak', 'Share the joy of a new arrival', '👶', 500, 1000, 2000, 1000, '#059669', 'New Baby', '👶 MashaAllah! {name} has been blessed with a new baby! May Allah make the child a source of joy.' ],
            [ 'dua_request', 'Community Dua Request', 'Ask the entire congregation to make dua for you', '🤲', 300, 500, 1000, 500, '#1e40af', 'Dua Request', '🤲 {name} is asking the congregation of {mosque} for dua. {message}' ],
            [ 'thank_you', 'Thank You Message', 'Thank the masjid and its community publicly', '💖', 300, 500, 1000, 300, '#9d174d', 'Thank You', '💖 {name} says JazakAllah Khayr to the community of {mosque}. {message}' ],
            [ 'umrah_mubarak', 'Umrah Mubarak', 'Congratulate someone who completed Umrah', '🕋', 500, 1000, 2000, 500, '#92400e', 'Umrah Mubarak', '🕋 Umrah Mubarak! {name} has completed Umrah. May Allah accept it and grant them ease.' ],
            [ 'ramadan_mubarak', 'Ramadan Mubarak', 'Send Ramadan greetings to the congregation', '🌙', 300, 500, 1000, 500, '#7c3aed', 'Ramadan Mubarak', '🌙 Ramadan Mubarak from {name} to the entire congregation of {mosque}! May this blessed month bring you closer to Allah.' ],
            [ 'condolence', 'Inna Lillahi', 'Send condolences to a family in the community', '🕊️', 500, 1000, 2000, 500, '#4b5563', 'Condolence', '🕊️ Inna lillahi wa inna ilayhi rajiun. {name} sends condolences. {message} May Allah grant patience and forgive the deceased.' ],
            [ 'shahada', 'Welcome to Islam', 'Celebrate a revert joining the Muslim community', '☪️', 500, 1000, 2000, 500, '#16a34a', 'Shahada', '☪️ Allahu Akbar! {name} celebrates a new Muslim joining the community of {mosque}. {message} May Allah keep them steadfast.' ],
            [ 'get_well', 'Get Well / Shifa', 'Send healing wishes and dua for recovery', '💚', 300, 500, 1000, 500, '#059669', 'Get Well', '💚 {name} is making dua for shifa (healing) for a member of {mosque}. {message} Ya Allah, grant them complete recovery.' ],
            [ 'graduation', 'Graduation Mubarak', 'Celebrate an academic achievement', '🎓', 500, 1000, 2000, 500, '#7c3aed', 'Graduation', '🎓 MashaAllah! {name} celebrates a graduation from the community of {mosque}. {message} May Allah bless their knowledge.' ],
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

// ── Seed new glad tidings for existing installs ──
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;
    if ( get_option( 'ynj_store_glad_tidings_v2' ) ) return;

    global $wpdb;
    $t = YNJ_DB::table( 'store_items' );
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) ) return;

    $new_items = [
        [ 'umrah_mubarak', 'Umrah Mubarak', 'Congratulate someone who completed Umrah', '🕋', 500, '#92400e', 'Umrah Mubarak', '🕋 Umrah Mubarak! {name} has completed Umrah. May Allah accept it and grant them ease.' ],
        [ 'ramadan_mubarak', 'Ramadan Mubarak', 'Send Ramadan greetings to the congregation', '🌙', 500, '#7c3aed', 'Ramadan Mubarak', '🌙 Ramadan Mubarak from {name} to the entire congregation of {mosque}! May this blessed month bring you closer to Allah.' ],
        [ 'condolence', 'Inna Lillahi', 'Send condolences to a family in the community', '🕊️', 500, '#4b5563', 'Condolence', '🕊️ Inna lillahi wa inna ilayhi rajiun. {name} sends condolences. {message} May Allah grant patience and forgive the deceased.' ],
        [ 'shahada', 'Welcome to Islam', 'Celebrate a revert joining the Muslim community', '☪️', 500, '#16a34a', 'Shahada', '☪️ Allahu Akbar! {name} celebrates a new Muslim joining the community of {mosque}. {message} May Allah keep them steadfast.' ],
        [ 'get_well', 'Get Well / Shifa', 'Send healing wishes and dua for recovery', '💚', 500, '#059669', 'Get Well', '💚 {name} is making dua for shifa (healing) for a member of {mosque}. {message} Ya Allah, grant them complete recovery.' ],
        [ 'graduation', 'Graduation Mubarak', 'Celebrate an academic achievement', '🎓', 500, '#7c3aed', 'Graduation', '🎓 MashaAllah! {name} celebrates a graduation from the community of {mosque}. {message} May Allah bless their knowledge.' ],
    ];

    $max_sort = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM $t" );
    foreach ( $new_items as $i => $d ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE item_key = %s", $d[0] ) );
        if ( ! $exists ) {
            $wpdb->insert( $t, [
                'item_key' => $d[0], 'title' => $d[1], 'description' => $d[2], 'icon' => $d[3],
                'price_1' => $d[4], 'price_2' => $d[4], 'price_3' => $d[4], 'default_price' => $d[4],
                'badge_color' => $d[5], 'badge_text' => $d[6], 'announcement_template' => $d[7],
                'sort_order' => $max_sort + $i + 1,
            ] );
        }
    }
    update_option( 'ynj_store_glad_tidings_v2', 1 );
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
