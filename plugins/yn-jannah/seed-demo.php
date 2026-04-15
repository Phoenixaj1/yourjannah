<?php
/**
 * YourJannah Demo Seeder
 *
 * Creates multiple realistic UK mosques with full data for demonstration.
 * Run via WP Admin → YourJannah → Seed Tool, or via CLI:
 *   docker compose exec -T wordpress php /var/www/html/wp-content/plugins/yn-jannah/seed-demo.php
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) {
    // CLI mode
    require_once dirname( __DIR__, 3 ) . '/wp-load.php';
}

global $wpdb;

echo "=== YourJannah Demo Seeder ===\n\n";

// ================================================================
// MOSQUES (5 diverse UK mosques)
// ================================================================

$mosques = [
    [
        'name'    => 'East London Mosque',
        'slug'    => 'east-london-mosque',
        'address' => '82-92 Whitechapel Road',
        'city'    => 'London',
        'postcode' => 'E1 1JX',
        'latitude' => 51.5176,
        'longitude' => -0.0658,
        'phone'   => '020 7650 3000',
        'email'   => 'admin@eastlondonmosque.org.uk',
        'website' => 'https://www.eastlondonmosque.org.uk',
        'description' => 'One of the largest mosques in Western Europe, serving Tower Hamlets and surrounding communities since 1985.',
        'capacity' => 7000,
        'has_women_section' => 1,
        'has_wudu' => 1,
        'has_parking' => 0,
    ],
    [
        'name'    => 'Birmingham Central Mosque',
        'slug'    => 'birmingham-central-mosque',
        'address' => '180 Belgrave Middleway',
        'city'    => 'Birmingham',
        'postcode' => 'B12 0XS',
        'latitude' => 52.4688,
        'longitude' => -1.8806,
        'phone'   => '0121 440 5355',
        'email'   => 'admin@centralmosque.org.uk',
        'website' => 'https://centralmosque.org.uk',
        'description' => 'The largest purpose-built mosque in Birmingham, serving the diverse Muslim community of the Midlands.',
        'capacity' => 3000,
        'has_women_section' => 1,
        'has_wudu' => 1,
        'has_parking' => 1,
    ],
    [
        'name'    => 'Manchester Islamic Centre',
        'slug'    => 'manchester-islamic-centre',
        'address' => 'Victoria Park, Upper Park Road',
        'city'    => 'Manchester',
        'postcode' => 'M14 5RU',
        'latitude' => 53.4489,
        'longitude' => -2.2156,
        'phone'   => '0161 224 4119',
        'email'   => 'admin@manchestermosque.org',
        'website' => 'https://manchestermosque.org',
        'description' => 'Also known as Victoria Park Mosque, a pillar of the Manchester Muslim community.',
        'capacity' => 1200,
        'has_women_section' => 1,
        'has_wudu' => 1,
        'has_parking' => 1,
    ],
    [
        'name'    => 'Edinburgh Central Mosque',
        'slug'    => 'edinburgh-central-mosque',
        'address' => '50 Potterrow',
        'city'    => 'Edinburgh',
        'postcode' => 'EH8 9BT',
        'latitude' => 55.9459,
        'longitude' => -3.1874,
        'phone'   => '0131 667 0140',
        'email'   => 'admin@edinburghmosque.org',
        'website' => 'https://edinburghmosque.org',
        'description' => 'The principal mosque in Edinburgh, serving Muslims across Scotland\'s capital.',
        'capacity' => 1000,
        'has_women_section' => 1,
        'has_wudu' => 1,
        'has_parking' => 0,
    ],
    [
        'name'    => 'Cardiff Islamic Centre',
        'slug'    => 'cardiff-islamic-centre',
        'address' => '9 Alice Street, Butetown',
        'city'    => 'Cardiff',
        'postcode' => 'CF10 5LQ',
        'latitude' => 51.4700,
        'longitude' => -3.1700,
        'phone'   => '029 2048 1818',
        'email'   => 'admin@cardiffmosque.org',
        'website' => 'https://cardiffmosque.org',
        'description' => 'The first purpose-built mosque in Wales, serving the Muslim community of Cardiff and South Wales.',
        'capacity' => 600,
        'has_women_section' => 1,
        'has_wudu' => 1,
        'has_parking' => 1,
    ],
];

$mosque_ids = [];
$mt = YNJ_DB::table( 'mosques' );

foreach ( $mosques as $m ) {
    // Check if already exists
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $mt WHERE slug = %s", $m['slug'] ) );
    if ( $exists ) {
        $mosque_ids[] = (int) $exists;
        echo "Mosque '{$m['name']}' already exists (ID: $exists)\n";
        continue;
    }

    // Create admin password
    $password = 'Admin2024!';
    $token = bin2hex( random_bytes( 32 ) );
    $token_hash = hash_hmac( 'sha256', $token, 'ynj_salt_2024' );

    $wpdb->insert( $mt, array_merge( $m, [
        'country'            => 'UK',
        'timezone'           => 'Europe/London',
        'admin_email'        => $m['email'],
        'admin_password_hash' => password_hash( $password, PASSWORD_DEFAULT ),
        'admin_token_hash'   => $token_hash,
        'status'             => 'active',
    ] ) );
    $id = (int) $wpdb->insert_id;
    $mosque_ids[] = $id;
    echo "Created mosque '{$m['name']}' (ID: $id)\n";

    // Create WP user for admin
    if ( class_exists( 'YNJ_WP_Auth' ) ) {
        $username = sanitize_user( str_replace( '@', '_', $m['email'] ), true );
        if ( ! email_exists( $m['email'] ) ) {
            $wp_uid = wp_create_user( $username, $password, $m['email'] );
            if ( ! is_wp_error( $wp_uid ) ) {
                $u = new WP_User( $wp_uid );
                $u->set_role( 'ynj_mosque_admin' );
                update_user_meta( $wp_uid, 'ynj_mosque_id', $id );
                update_user_meta( $wp_uid, 'ynj_mosque_ids', [ $id ] );
                wp_update_user( [ 'ID' => $wp_uid, 'display_name' => $m['name'] . ' Admin' ] );
            }
        }
    }
}

echo "\n";

// ================================================================
// SEED DATA FOR EACH MOSQUE
// ================================================================

$event_templates = [
    [ 'title' => 'Friday Khutbah', 'type' => 'talk', 'time' => '13:00', 'desc' => 'Weekly Friday sermon and prayer. All welcome.' ],
    [ 'title' => 'Sisters Halaqa', 'type' => 'sisters', 'time' => '10:00', 'desc' => 'Weekly sisters circle with Quran recitation and discussion.' ],
    [ 'title' => 'Youth Football Tournament', 'type' => 'sports', 'time' => '14:00', 'desc' => 'Monthly football tournament for brothers aged 14-25.' ],
    [ 'title' => 'Community Iftar', 'type' => 'community', 'time' => '18:30', 'desc' => 'Open iftar for the community. All welcome.' ],
    [ 'title' => 'Quran Tafsir Circle', 'type' => 'talk', 'time' => '19:30', 'desc' => 'Weekly Quran study and tafsir with the imam.' ],
    [ 'title' => 'New Muslim Support Group', 'type' => 'community', 'time' => '11:00', 'desc' => 'Monthly meetup for new Muslims and those exploring Islam.' ],
    [ 'title' => 'Marriage Seminar', 'type' => 'talk', 'time' => '19:00', 'desc' => 'Guidance on Islamic marriage, rights and responsibilities.' ],
    [ 'title' => 'Charity Fundraiser Dinner', 'type' => 'fundraiser', 'time' => '19:00', 'desc' => 'Annual charity dinner to support local welfare projects.' ],
];

$announcement_templates = [
    [ 'title' => 'Ramadan Timetable Available', 'body' => 'The Ramadan timetable for this year is now available. Pick up your copy from the mosque reception or download from our app.', 'type' => 'general', 'pinned' => 1 ],
    [ 'title' => 'Car Park Resurfacing', 'body' => 'The mosque car park will be closed this Saturday 10am-2pm for resurfacing. Please use street parking.', 'type' => 'general', 'pinned' => 0 ],
    [ 'title' => 'Zakat Collection Open', 'body' => 'Zakat can now be paid at the mosque office or online. Speak to the treasurer for guidance on calculation.', 'type' => 'general', 'pinned' => 1 ],
    [ 'title' => 'New Imam Appointment', 'body' => 'We are pleased to announce the appointment of our new imam who will be leading prayers from next month.', 'type' => 'general', 'pinned' => 0 ],
];

$class_templates = [
    [ 'title' => 'Quran Reading for Beginners', 'cat' => 'quran', 'instructor' => 'Sheikh Ahmad', 'price' => 0, 'schedule' => 'Saturdays 10am-12pm', 'max' => 20 ],
    [ 'title' => 'Arabic Language Course', 'cat' => 'arabic', 'instructor' => 'Ustadha Fatima', 'price' => 5000, 'schedule' => 'Tuesdays & Thursdays 7-8pm', 'max' => 15 ],
    [ 'title' => 'Islamic History', 'cat' => 'islamic_studies', 'instructor' => 'Dr. Hassan', 'price' => 0, 'schedule' => 'Sundays 2-4pm', 'max' => 30 ],
    [ 'title' => 'Tajweed Course', 'cat' => 'quran', 'instructor' => 'Qari Ibrahim', 'price' => 3000, 'schedule' => 'Wednesdays 6-7:30pm', 'max' => 12 ],
];

$service_templates = [
    [ 'title' => 'Nikkah Ceremony', 'cat' => 'nikkah', 'price' => 15000, 'label' => 'From £150', 'avail' => 'Weekends by appointment', 'desc' => 'Full nikkah ceremony with imam, witnesses and certificate.' ],
    [ 'title' => 'Funeral / Janazah', 'cat' => 'funeral', 'price' => 0, 'label' => 'Free - donations welcome', 'avail' => '24/7 emergency', 'desc' => 'Ghusl, kafan, janazah prayer and burial arrangement.' ],
    [ 'title' => 'Islamic Counselling', 'cat' => 'counselling', 'price' => 0, 'label' => 'Free', 'avail' => 'Mon-Fri 10am-4pm', 'desc' => 'Confidential counselling for individuals and families.' ],
    [ 'title' => 'Revert Support', 'cat' => 'revert', 'price' => 0, 'label' => 'Free', 'avail' => 'By appointment', 'desc' => 'Support and guidance for new Muslims. Shahada, resources, mentoring.' ],
];

$campaign_templates = [
    [ 'title' => 'New Roof Fund', 'cat' => 'renovation', 'target' => 5000000, 'raised' => 3200000, 'donors' => 88 ],
    [ 'title' => 'Monthly Welfare Fund', 'cat' => 'welfare', 'target' => 200000, 'raised' => 142000, 'donors' => 34, 'recurring' => 1 ],
    [ 'title' => 'Youth Centre Extension', 'cat' => 'expansion', 'target' => 10000000, 'raised' => 2500000, 'donors' => 156 ],
];

foreach ( $mosque_ids as $mid ) {
    $mosque_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $mt WHERE id = %d", $mid ) );
    echo "Seeding data for '$mosque_name' (ID: $mid)...\n";

    // Events
    $et = YNJ_DB::table( 'events' );
    $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $et WHERE mosque_id = %d", $mid ) );
    if ( $existing < 3 ) {
        $base_date = new DateTime();
        foreach ( $event_templates as $i => $ev ) {
            $date = clone $base_date;
            $date->modify( '+' . ( $i * 2 + 1 ) . ' days' );
            $wpdb->insert( $et, [
                'mosque_id'   => $mid,
                'title'       => $ev['title'],
                'description' => $ev['desc'],
                'event_date'  => $date->format( 'Y-m-d' ),
                'start_time'  => $ev['time'] . ':00',
                'end_time'    => date( 'H:i:s', strtotime( $ev['time'] ) + 7200 ),
                'event_type'  => $ev['type'],
                'max_capacity' => rand( 20, 200 ),
                'status'      => 'published',
            ] );
        }
        echo "  + " . count( $event_templates ) . " events\n";
    }

    // Announcements
    $at = YNJ_DB::table( 'announcements' );
    $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $at WHERE mosque_id = %d", $mid ) );
    if ( $existing < 2 ) {
        foreach ( $announcement_templates as $ann ) {
            $wpdb->insert( $at, [
                'mosque_id'    => $mid,
                'title'        => $ann['title'],
                'body'         => $ann['body'],
                'type'         => $ann['type'],
                'pinned'       => $ann['pinned'],
                'status'       => 'published',
                'published_at' => current_time( 'mysql' ),
            ] );
        }
        echo "  + " . count( $announcement_templates ) . " announcements\n";
    }

    // Classes
    $ct = YNJ_DB::table( 'classes' );
    $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ct WHERE mosque_id = %d", $mid ) );
    if ( $existing < 2 ) {
        foreach ( $class_templates as $cls ) {
            $wpdb->insert( $ct, [
                'mosque_id'       => $mid,
                'title'           => $cls['title'],
                'category'        => $cls['cat'],
                'instructor_name' => $cls['instructor'],
                'price_pence'     => $cls['price'],
                'schedule_text'   => $cls['schedule'],
                'max_capacity'    => $cls['max'],
                'enrolled_count'  => rand( 0, $cls['max'] ),
                'status'          => 'active',
            ] );
        }
        echo "  + " . count( $class_templates ) . " classes\n";
    }

    // Masjid Services
    $mst = YNJ_DB::table( 'masjid_services' );
    $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $mst WHERE mosque_id = %d", $mid ) );
    if ( $existing < 2 ) {
        foreach ( $service_templates as $svc ) {
            $wpdb->insert( $mst, [
                'mosque_id'         => $mid,
                'title'             => $svc['title'],
                'category'          => $svc['cat'],
                'description'       => $svc['desc'],
                'price_pence'       => $svc['price'],
                'price_label'       => $svc['label'],
                'availability'      => $svc['avail'],
                'requires_approval' => 1,
                'status'            => 'active',
            ] );
        }
        echo "  + " . count( $service_templates ) . " masjid services\n";
    }

    // Campaigns
    $cpt = YNJ_DB::table( 'campaigns' );
    $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $cpt WHERE mosque_id = %d", $mid ) );
    if ( $existing < 2 ) {
        foreach ( $campaign_templates as $c ) {
            $wpdb->insert( $cpt, [
                'mosque_id'    => $mid,
                'title'        => $c['title'],
                'category'     => $c['cat'],
                'target_pence' => $c['target'],
                'raised_pence' => $c['raised'],
                'donor_count'  => $c['donors'],
                'recurring'    => $c['recurring'] ?? 0,
                'status'       => 'active',
            ] );
        }
        echo "  + " . count( $campaign_templates ) . " campaigns\n";
    }

    // Rooms (if none)
    $rt = YNJ_DB::table( 'rooms' );
    $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $rt WHERE mosque_id = %d", $mid ) );
    if ( $existing < 1 ) {
        $rooms = [
            [ 'name' => 'Main Hall', 'desc' => 'Large prayer hall for events and gatherings.', 'cap' => 500, 'hourly' => 5000, 'daily' => 25000 ],
            [ 'name' => 'Classroom A', 'desc' => 'Multi-purpose classroom for Quran classes.', 'cap' => 25, 'hourly' => 1000, 'daily' => 5000 ],
            [ 'name' => 'Meeting Room', 'desc' => 'Conference room with projector.', 'cap' => 15, 'hourly' => 2000, 'daily' => 10000 ],
        ];
        foreach ( $rooms as $r ) {
            $wpdb->insert( $rt, [
                'mosque_id'        => $mid,
                'name'             => $r['name'],
                'description'      => $r['desc'],
                'capacity'         => $r['cap'],
                'hourly_rate_pence' => $r['hourly'],
                'daily_rate_pence'  => $r['daily'],
                'status'           => 'active',
            ] );
        }
        echo "  + " . count( $rooms ) . " rooms\n";
    }
}

echo "\n=== Seeding complete! ===\n";
echo "Mosques: " . count( $mosque_ids ) . "\n";
echo "Login to any demo mosque with password: Admin2024!\n";
echo "Emails: " . implode( ', ', array_column( $mosques, 'email' ) ) . "\n";
