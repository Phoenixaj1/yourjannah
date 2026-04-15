<?php
/**
 * Extra seed data — run via WP-CLI: wp eval-file wp-content/plugins/yn-jannah/seed-extra.php
 */
if ( ! defined( 'ABSPATH' ) ) {
    // Allow running from WP-CLI
    if ( php_sapi_name() !== 'cli' ) exit;
}

global $wpdb;
$mid = 1;
$p = $wpdb->prefix . 'ynj_';

// 1. Update mosque with dfm_slug + website
$wpdb->update( $p . 'mosques', [
    'dfm_slug' => 'yourniyyah-masjid',
    'website'  => 'https://yourniyyah-masjid.org.uk',
], [ 'id' => $mid ] );
echo "Mosque: dfm_slug + website set\n";

// 2. Jamat overrides
$wpdb->update( $p . 'prayer_times', [
    'fajr_jamat'    => '04:45:00',
    'dhuhr_jamat'   => '13:30:00',
    'asr_jamat'     => '17:15:00',
    'maghrib_jamat' => '20:11:00',
    'isha_jamat'    => '22:00:00',
    'source'        => 'manual',
], [ 'mosque_id' => $mid ] );
echo "Prayer times: jamat overrides added\n";

// 3. Eid times
$eids = [
    [ 'eid_ul_fitr',  2026, 'First Eid Prayer',  '07:30:00', 'Main prayer hall. Overflow in car park.' ],
    [ 'eid_ul_fitr',  2026, 'Second Eid Prayer', '09:00:00', 'Main prayer hall.' ],
    [ 'eid_ul_adha',  2026, 'First Eid Prayer',  '07:30:00', 'Main prayer hall + outdoor marquee.' ],
    [ 'eid_ul_adha',  2026, 'Second Eid Prayer', '09:00:00', 'Main prayer hall.' ],
];
foreach ( $eids as $e ) {
    $wpdb->insert( $p . 'eid_times', [
        'mosque_id'      => $mid,
        'eid_type'       => $e[0],
        'year'           => $e[1],
        'slot_name'      => $e[2],
        'salah_time'     => $e[3],
        'location_notes' => $e[4],
    ] );
}
echo 'Eid times: ' . count( $eids ) . " slots\n";

// 4. Subscribers
$subs = [
    [ 'ahmed.patel@gmail.com',       'Ahmed Patel',      '07700100001', 'android' ],
    [ 'fatima.khan@outlook.com',     'Fatima Khan',      '07700100002', 'ios' ],
    [ 'mohammed.ali@yahoo.co.uk',   'Mohammed Ali',     '07700100003', 'android' ],
    [ 'aisha.rahman@hotmail.com',   'Aisha Rahman',     '07700100004', 'ios' ],
    [ 'hassan.mahmood@gmail.com',   'Hassan Mahmood',   '07700100005', 'android' ],
    [ 'zainab.hussain@gmail.com',   'Zainab Hussain',   '07700100006', 'ios' ],
    [ 'omar.farooq@proton.me',     'Omar Farooq',      '07700100007', 'web' ],
    [ 'khadijah.ahmed@gmail.com',   'Khadijah Ahmed',   '07700100008', 'android' ],
    [ 'yusuf.ibrahim@outlook.com',  'Yusuf Ibrahim',    '07700100009', 'ios' ],
    [ 'maryam.begum@gmail.com',     'Maryam Begum',     '07700100010', 'web' ],
    [ 'bilal.sharif@yahoo.com',     'Bilal Sharif',     '07700100011', 'android' ],
    [ 'safiya.noor@gmail.com',      'Safiya Noor',      '07700100012', 'ios' ],
];
foreach ( $subs as $s ) {
    $wpdb->insert( $p . 'subscribers', [
        'mosque_id'      => $mid,
        'email'          => $s[0],
        'name'           => $s[1],
        'phone'          => $s[2],
        'device_type'    => $s[3],
        'status'         => 'active',
        'subscribed_at'  => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 30 ) . ' days' ) ),
        'last_active_at' => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 0, 3 ) . ' days' ) ),
    ] );
}
echo 'Subscribers: ' . count( $subs ) . " added\n";

// 5. Bookings
$bookings = [
    [ 1,    null, 'Ahmed Patel',      'ahmed.patel@gmail.com',       '07700100001', '+3 days',  '18:30', '20:00', 'Family of 4',                      'confirmed' ],
    [ 2,    null, 'Fatima Khan',      'fatima.khan@outlook.com',     '07700100002', '+7 days',  '11:00', '13:00', '',                                  'confirmed' ],
    [ 3,    null, 'Mohammed Ali',     'mohammed.ali@yahoo.co.uk',   '07700100003', '+14 days', '10:00', '16:00', 'Team: Solihull Lions',              'pending' ],
    [ null, 2,    'Hassan Mahmood',   'hassan.mahmood@gmail.com',   '07700100005', '+5 days',  '19:00', '21:00', 'Board meeting for charity trustees', 'confirmed' ],
    [ null, 3,    'Zainab Hussain',   'zainab.hussain@gmail.com',   '07700100006', '+10 days', '10:00', '14:00', 'Cooking class for 12 sisters',      'pending' ],
    [ null, 5,    'Khadijah Ahmed',   'khadijah.ahmed@gmail.com',   '07700100008', '+2 days',  '16:00', '18:00', 'Quran homework club',               'confirmed' ],
];
foreach ( $bookings as $b ) {
    $wpdb->insert( $p . 'bookings', [
        'mosque_id'    => $mid,
        'event_id'     => $b[0],
        'room_id'      => $b[1],
        'user_name'    => $b[2],
        'user_email'   => $b[3],
        'user_phone'   => $b[4],
        'booking_date' => date( 'Y-m-d', strtotime( $b[5] ) ),
        'start_time'   => $b[6] . ':00',
        'end_time'     => $b[7] . ':00',
        'notes'        => $b[8],
        'status'       => $b[9],
    ] );
}
// Update registered_count
$wpdb->query( "UPDATE {$p}events e SET registered_count = (SELECT COUNT(*) FROM {$p}bookings b WHERE b.event_id = e.id AND b.status IN ('confirmed','pending'))" );
echo 'Bookings: ' . count( $bookings ) . " added\n";

// 6. Enquiries
$enquiries = [
    [ 'Sarah Williams',   'sarah.w@gmail.com',        '07700200001', 'Nikah Booking',              'I would like to book a nikah ceremony for August 2026. Can you advise on availability and costs?', 'nikah',        'new' ],
    [ 'David Chen',       'd.chen@company.co.uk',     '07700200002', 'Room Hire for Training',     'We are a local charity and would like to hire the conference room for staff training on the 25th.', 'room_booking', 'read' ],
    [ 'Amina Osman',      'amina.osman@outlook.com',  '07700200003', 'Quran Classes Registration', 'I would like to register my two children (ages 7 and 9) for the Saturday Quran classes.',           'general',      'replied' ],
    [ 'Ibrahim Yusuf',    'ibrahim.y@hotmail.com',    '07700200004', 'Funeral Services',           'My father passed away. Can you advise on the janazah prayer arrangements and washing facilities?',  'janazah',      'new' ],
    [ 'Ruqayyah Ahmed',   'ruqayyah.a@gmail.com',    '07700200005', 'Volunteer Opportunity',      'I am a university student looking to volunteer at the mosque during Ramadan. How can I sign up?',   'general',      'new' ],
];
foreach ( $enquiries as $e ) {
    $wpdb->insert( $p . 'enquiries', [
        'mosque_id'  => $mid,
        'name'       => $e[0],
        'email'      => $e[1],
        'phone'      => $e[2],
        'subject'    => $e[3],
        'message'    => $e[4],
        'type'       => $e[5],
        'status'     => $e[6],
        'replied_at' => $e[6] === 'replied' ? date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) : null,
    ] );
}
echo 'Enquiries: ' . count( $enquiries ) . " added\n";

// Final counts
echo "\n=== FINAL COUNTS ===\n";
$tables = [ 'mosques', 'announcements', 'events', 'jumuah_times', 'eid_times', 'rooms', 'businesses', 'services', 'subscribers', 'bookings', 'enquiries', 'prayer_times' ];
foreach ( $tables as $t ) {
    $c = $wpdb->get_var( "SELECT COUNT(*) FROM {$p}{$t}" );
    echo str_pad( $t, 16 ) . ": $c\n";
}
