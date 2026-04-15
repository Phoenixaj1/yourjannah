<?php
/**
 * Seed 5 well-known mosques with comprehensive data.
 * Run: wp eval-file wp-content/plugins/yn-jannah/seed-full.php
 */
if ( ! defined( 'ABSPATH' ) && php_sapi_name() !== 'cli' ) exit;

global $wpdb;
$p = $wpdb->prefix . 'ynj_';

// Target mosques — update their profiles first
$targets = [
    2   => [ 'name' => 'London Central Mosque', 'city' => 'London', 'address' => '146 Park Road, Regent\'s Park', 'capacity' => 5000, 'has_women_section' => 1, 'has_parking' => 1 ],
    337 => [ 'name' => 'East London Mosque', 'city' => 'London', 'address' => '82-92 Whitechapel Road', 'capacity' => 7000, 'has_women_section' => 1, 'has_parking' => 0 ],
    7   => [ 'name' => 'Birmingham Central Mosque', 'city' => 'Birmingham', 'address' => '180 Belgrave Middleway, Highgate', 'capacity' => 6000, 'has_women_section' => 1, 'has_parking' => 1 ],
    222 => [ 'name' => 'Manchester Islamic Centre', 'city' => 'Manchester', 'address' => 'Upper Park Road, Victoria Park', 'capacity' => 3000, 'has_women_section' => 1, 'has_parking' => 1 ],
    305 => [ 'name' => 'Newcastle Central Mosque', 'city' => 'Newcastle', 'address' => 'Grainger Park Road', 'capacity' => 2000, 'has_women_section' => 1, 'has_parking' => 1 ],
];

foreach ( $targets as $id => $data ) {
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}mosques WHERE id = %d", $id ) );
    if ( ! $exists ) {
        echo "WARNING: Mosque ID $id not found, skipping.\n";
        continue;
    }
    $wpdb->update( $p . 'mosques', $data, [ 'id' => $id ] );
    echo "Updated: {$data['name']} (ID $id)\n";
}

$mosque_ids = array_keys( $targets );

// ========================
// ANNOUNCEMENTS
// ========================
$announcements_by_mosque = [
    2 => [
        [ 'Ramadan Timetable 2026', 'The Ramadan timetable is now available from the main office and on our website. Taraweeh prayers begin at 9:30pm.', 'general', 1 ],
        [ 'Eid al-Fitr Prayers', 'Eid prayers will be held at 7:30am, 8:30am and 9:30am. Overflow prayers in Regent\'s Park. Please arrive early.', 'urgent', 1 ],
        [ 'Friday Khutbah Change', 'This week\'s khutbah will be delivered by Sheikh Dr. Ahmad Al-Dubayan. Topic: The Muslim Community in Britain Today.', 'general', 0 ],
        [ 'Car Park Maintenance', 'The car park will be closed for resurfacing 20-22 April. Please use public transport or the nearby NCP.', 'general', 0 ],
    ],
    337 => [
        [ 'Madrasa Registration Open', 'Registration for the 2026-27 academic year is now open. Classes for ages 5-16, weekday evenings and weekends.', 'general', 1 ],
        [ 'Community Iftar Every Saturday', 'Free community iftar every Saturday during Ramadan. Sponsored by local businesses. All welcome.', 'general', 1 ],
        [ 'New Wudu Facilities', 'The newly refurbished wudu area on the ground floor is now open. Please keep it clean for everyone.', 'general', 0 ],
        [ 'Parking Reminder', 'Please do not park on Whitechapel Road during prayer times. Wardens are actively ticketing. Use Fieldgate Street car park.', 'urgent', 0 ],
        [ 'Quran Memorisation Programme', 'Spaces available for the full-time Hifz programme starting September. Ages 8-14. Apply at the office.', 'general', 0 ],
    ],
    7 => [
        [ 'Central Mosque Open Day', 'Join us for our annual open day this Saturday 10am-4pm. Tours, exhibitions, talks, and free refreshments.', 'general', 1 ],
        [ 'Youth Football League', 'The mosque youth football league starts next month. Teams forming now. Register at the community centre.', 'general', 0 ],
        [ 'Renovation Phase 3 Complete', 'The new sisters\' prayer hall is now open on the first floor. Jazakallah khayr to all donors.', 'general', 0 ],
        [ 'Free Legal Advice Clinic', 'Every Thursday 6-8pm. Immigration, family law, housing. No appointment needed. Ground floor meeting room.', 'general', 0 ],
    ],
    222 => [
        [ 'Arabic Classes for Adults', 'New beginner Arabic classes starting next Monday. Every Mon/Wed 7-8:30pm. £5 per session.', 'general', 1 ],
        [ 'Mosque Expansion Fundraiser', 'Help us raise £500,000 for the new community hall. Donate via our website or at the mosque office.', 'urgent', 1 ],
        [ 'Sisters Weekly Halaqa', 'Every Tuesday after Dhuhr. Topic this month: Patience in Adversity. All sisters welcome.', 'general', 0 ],
    ],
    305 => [
        [ 'Interfaith Dialogue Evening', 'Join us for a dialogue with St. Nicholas Cathedral representatives. Thursday 7pm. Tea and biscuits provided.', 'general', 1 ],
        [ 'Mosque Heating Appeal', 'The heating system needs urgent replacement before winter. Target: £25,000. Please donate generously.', 'urgent', 1 ],
        [ 'New Imam Appointment', 'We are pleased to welcome Imam Abdur-Rahman to our mosque. He will lead prayers from next Friday.', 'general', 0 ],
    ],
];

foreach ( $announcements_by_mosque as $mid => $items ) {
    foreach ( $items as $a ) {
        $wpdb->insert( $p . 'announcements', [
            'mosque_id'    => $mid,
            'title'        => $a[0],
            'body'         => $a[1],
            'type'         => $a[2],
            'pinned'       => $a[3],
            'status'       => 'published',
            'published_at' => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 0, 72 ) . ' hours' ) ),
        ] );
    }
}
echo "Announcements seeded.\n";

// ========================
// EVENTS
// ========================
$events_by_mosque = [
    2 => [
        [ 'Sheikh Yasir Qadhi — Seerah Lecture', 'A special lecture on the life of the Prophet (SAW). Limited seating, arrive early.', '+5 days', '19:00', '21:00', 'Main Hall', 'talk', 500, 0, 0 ],
        [ 'Youth Islamic Quiz Night', 'Test your knowledge! Teams of 4. Ages 12-18. Prizes for winners.', '+10 days', '18:00', '20:00', 'Community Room', 'youth', 80, 1, 0 ],
        [ 'Marriage Preparation Course', '6-week course covering Islamic marriage, communication, and finance. Starts this date.', '+14 days', '10:00', '13:00', 'Conference Room', 'course', 30, 1, 2000 ],
        [ 'Community Clean-Up Day', 'Help us beautify the mosque grounds and Regent\'s Park area. Gloves and bags provided.', '+21 days', '09:00', '12:00', 'Front Entrance', 'community', 100, 0, 0 ],
    ],
    337 => [
        [ 'Tafseer Class — Surah Al-Kahf', 'Weekly tafseer with Ustadh Abdul Raheem. Continuing from ayah 45.', '+3 days', '20:00', '21:30', 'Lecture Hall', 'class', 200, 0, 0 ],
        [ 'Sisters Fitness Class', 'Women-only fitness session. Cardio and strength training. Bring your own mat.', '+4 days', '10:00', '11:30', 'Sisters Hall', 'sports', 40, 1, 500 ],
        [ 'Quran Recitation Competition', 'Annual competition for children ages 7-15. Categories: Juz Amma, 5 Juz, 10 Juz.', '+17 days', '10:00', '16:00', 'Main Hall', 'competition', 150, 1, 0 ],
        [ 'Mental Health Awareness Talk', 'Professional counsellors discuss mental health in the Muslim community. Free and confidential.', '+8 days', '19:30', '21:00', 'Community Room', 'talk', 100, 0, 0 ],
        [ 'Eid Party for Children', 'Face painting, bouncy castle, games, and halal sweets! Ages 3-12. Free entry.', '+25 days', '11:00', '15:00', 'Community Garden', 'community', 200, 0, 0 ],
    ],
    7 => [
        [ 'Islamic Finance Workshop', 'Understanding mortgages, investments, and savings from an Islamic perspective.', '+6 days', '14:00', '17:00', 'Conference Room', 'workshop', 50, 1, 1000 ],
        [ 'Nasheed Night', 'An evening of beautiful nasheeds by local artists. Family-friendly.', '+12 days', '19:00', '21:00', 'Main Hall', 'community', 300, 0, 0 ],
        [ 'First Aid Training', 'Free first aid certification course. St John Ambulance instructor. 2-day course.', '+20 days', '10:00', '16:00', 'Training Room', 'course', 25, 1, 0 ],
        [ 'Community BBQ', 'Annual summer BBQ in the mosque grounds. Halal burgers, activities for kids, stalls.', '+30 days', '12:00', '17:00', 'Gardens', 'community', 500, 0, 0 ],
    ],
    222 => [
        [ 'Jumu\'ah Khutbah: Palestine', 'Special extended khutbah on the situation in Palestine. Dua and fundraising after.', '+2 days', '13:00', '14:30', 'Main Hall', 'talk', 400, 0, 0 ],
        [ 'Convert Support Circle', 'Monthly gathering for new Muslims. Mentoring, Q&A, and socialising.', '+9 days', '18:00', '20:00', 'Meeting Room', 'community', 30, 0, 0 ],
        [ 'Charity Football Match', 'Manchester Muslim XI vs Manchester Doctors XI. Funds go to orphan sponsorship.', '+16 days', '14:00', '17:00', 'Platt Fields Park', 'sports', 200, 0, 0 ],
    ],
    305 => [
        [ 'Learn Arabic in 10 Weeks', 'Intensive beginner Arabic course. Every Saturday morning.', '+7 days', '10:00', '12:00', 'Classroom', 'course', 20, 1, 5000 ],
        [ 'Muslim Professionals Networking', 'Monthly networking event for Muslim professionals in the North East.', '+11 days', '19:00', '21:00', 'Main Hall', 'community', 60, 1, 0 ],
        [ 'Janazah Workshop', 'Learn the full janazah process — ghusl, kafan, salah, burial. Practical demonstration.', '+18 days', '14:00', '17:00', 'Training Room', 'workshop', 40, 1, 0 ],
    ],
];

foreach ( $events_by_mosque as $mid => $items ) {
    foreach ( $items as $e ) {
        $wpdb->insert( $p . 'events', [
            'mosque_id'          => $mid,
            'title'              => $e[0],
            'description'        => $e[1],
            'event_date'         => date( 'Y-m-d', strtotime( $e[2] ) ),
            'start_time'         => $e[3] . ':00',
            'end_time'           => $e[4] . ':00',
            'location'           => $e[5],
            'event_type'         => $e[6],
            'max_capacity'       => $e[7],
            'requires_booking'   => $e[8],
            'ticket_price_pence' => $e[9],
            'status'             => 'published',
        ] );
    }
}
echo "Events seeded.\n";

// ========================
// JUMU'AH TIMES
// ========================
foreach ( $mosque_ids as $mid ) {
    $wpdb->insert( $p . 'jumuah_times', [ 'mosque_id' => $mid, 'slot_name' => 'First Jumu\'ah', 'khutbah_time' => '12:30:00', 'salah_time' => '13:00:00', 'language' => 'English', 'enabled' => 1 ] );
    $wpdb->insert( $p . 'jumuah_times', [ 'mosque_id' => $mid, 'slot_name' => 'Second Jumu\'ah', 'khutbah_time' => '13:30:00', 'salah_time' => '14:00:00', 'language' => 'Arabic', 'enabled' => 1 ] );
}
echo "Jumu'ah times seeded.\n";

// ========================
// ROOMS
// ========================
$rooms_template = [
    [ 'Main Prayer Hall', 'The main prayer hall for daily and Friday prayers.', 500, 0, 0 ],
    [ 'Conference Room', 'Air-conditioned room with projector, whiteboard, and seating for 30.', 30, 2500, 12000 ],
    [ 'Community Kitchen', 'Fully equipped industrial kitchen for events and community meals.', 20, 2000, 10000 ],
    [ 'Classroom 1', 'Multi-purpose classroom used for Quran classes and workshops.', 25, 1500, 7000 ],
    [ 'Sisters Room', 'Dedicated space for sisters\' events and programmes.', 40, 0, 0 ],
];
foreach ( $mosque_ids as $mid ) {
    foreach ( $rooms_template as $r ) {
        $wpdb->insert( $p . 'rooms', [
            'mosque_id' => $mid, 'name' => $r[0], 'description' => $r[1],
            'capacity' => $r[2], 'hourly_rate_pence' => $r[3], 'daily_rate_pence' => $r[4],
            'status' => 'active',
        ] );
    }
}
echo "Rooms seeded.\n";

// ========================
// SERVICES (Masjid + Professional)
// ========================
$services_by_mosque = [
    2 => [
        [ 'Imam Office', 'Imam / Scholar', 'Nikah ceremonies, funeral services, Islamic counselling, shahada witnessing.', '020 7725 2152', 'London NW8', 0 ],
        [ 'Quran Academy', 'Quran Teacher', 'Weekday evening and weekend Quran classes for children and adults. Tajweed and Hifz programmes.', '020 7725 2152', 'On-site', 0 ],
        [ 'Marriage Counselling Service', 'Counselling', 'Confidential Islamic marriage counselling by trained professionals.', '020 7725 2160', 'London', 0 ],
        [ 'Zara Digital Marketing', 'Digital Marketing', 'Social media management, SEO, and Google Ads for Muslim businesses.', '07911 234567', 'London & Remote', 1000 ],
        [ 'Khalid Web Design', 'Web Development', 'Custom websites and e-commerce for mosques and Muslim organisations.', '07922 345678', 'UK-wide (Remote)', 1000 ],
    ],
    337 => [
        [ 'Imam Services', 'Imam / Scholar', 'Nikah, janazah, ruqyah, counselling. By appointment.', '020 7650 3000', 'Tower Hamlets', 0 ],
        [ 'East London Islamic School', 'Quran Teacher', 'Full-time and part-time Islamic education. Ages 5-16.', '020 7650 3010', 'On-site', 0 ],
        [ 'Janazah Services', 'Funeral', 'Complete janazah service including ghusl, kafan, transportation, and burial coordination.', '020 7650 3020', 'East London', 0 ],
        [ 'Rashid Solicitors', 'Legal Services', 'Islamic wills, immigration, family law, employment disputes.', '020 8123 4567', 'Whitechapel', 1000 ],
        [ 'Halima Therapy', 'Counselling', 'CBT and Islamic counselling for anxiety, depression, and trauma. Female therapist.', '07933 456789', 'East London & Online', 1000 ],
    ],
    7 => [
        [ 'Central Mosque Imam', 'Imam / Scholar', 'All religious services including nikah, janazah, conversion certificates, and counselling.', '0121 440 5355', 'Birmingham', 0 ],
        [ 'Quran & Arabic Institute', 'Quran Teacher', 'Quranic Arabic, Tajweed, and Hifz classes. All levels welcome.', '0121 440 5355', 'On-site', 0 ],
        [ 'Ahmed Accountancy', 'Accounting', 'Tax returns, bookkeeping, and company accounts. Specialist in charity accounting.', '07944 567890', 'Birmingham', 1000 ],
    ],
    222 => [
        [ 'Imam Muhammad', 'Imam / Scholar', 'Nikah, janazah, Islamic advice and mediation.', '0161 248 9981', 'Manchester', 0 ],
        [ 'Arabic Language Centre', 'Arabic Tutor', 'Classical and Modern Standard Arabic for all levels.', '0161 248 9982', 'On-site', 0 ],
        [ 'Idris Tech Solutions', 'IT Support', 'Computer repairs, network setup, CCTV installation for mosques and businesses.', '07955 678901', 'Greater Manchester', 1000 ],
    ],
    305 => [
        [ 'Imam Abdur-Rahman', 'Imam / Scholar', 'Religious services, marriage ceremonies, funeral prayers, counselling.', '0191 273 2816', 'Newcastle', 0 ],
        [ 'Sisters Quran Circle', 'Quran Teacher', 'One-to-one and group Quran lessons for sisters. Flexible timings.', '07966 789012', 'Newcastle & Gateshead', 0 ],
    ],
];

foreach ( $services_by_mosque as $mid => $items ) {
    foreach ( $items as $s ) {
        $wpdb->insert( $p . 'services', [
            'mosque_id'        => $mid,
            'provider_name'    => $s[0],
            'service_type'     => $s[1],
            'description'      => $s[2],
            'phone'            => $s[3],
            'area_covered'     => $s[4],
            'monthly_fee_pence' => $s[5],
            'status'           => 'active',
        ] );
    }
}
echo "Services seeded.\n";

// ========================
// BUSINESSES (Sponsors)
// ========================
$businesses_by_mosque = [
    2 => [
        [ 'Regent\'s Halal Kitchen', 'Ahmed Ibrahim', 'Restaurant', 'Authentic Middle Eastern cuisine. Catering for events available.', '020 7723 8899', '', 'NW8 7RG', 10000 ],
        [ 'Crescent Insurance Brokers', 'Yusuf Patel', 'Insurance', 'Sharia-compliant home, car, and business insurance.', '020 7935 1234', 'https://crescentinsurance.co.uk', 'NW1 5LR', 5000 ],
        [ 'Noor Bookshop', 'Khadijah Ahmed', 'Books & Gifts', 'Islamic books, prayer mats, attar, Qurans, and gifts.', '020 7724 5678', '', 'NW8 8AE', 3000 ],
        [ 'Al-Amanah Travel', 'Hamza Khan', 'Travel', 'Hajj and Umrah packages. ATOL protected.', '020 7262 9876', 'https://alamanahtravel.co.uk', 'NW1 6XE', 10000 ],
        [ 'Park Pharmacy', 'Dr. Amina Begum', 'Health', 'NHS prescriptions, health checks, travel vaccinations.', '020 7722 3344', '', 'NW8 7HA', 3000 ],
    ],
    337 => [
        [ 'Whitechapel Halal Meats', 'Rafiq Ali', 'Butcher', 'Hand-slaughtered HMC certified halal meat. Wholesale available.', '020 7247 1234', '', 'E1 1BJ', 5000 ],
        [ 'Tayyab\'s Restaurant', 'Mohammed Tayyab', 'Restaurant', 'Famous Punjabi cuisine since 1972. BYO.', '020 7247 9543', 'https://tayyabs.co.uk', 'E1 1HJ', 10000 ],
        [ 'East End Solicitors', 'Barrister Hussain', 'Legal', 'Immigration, criminal, family, and commercial law.', '020 7377 5678', 'https://eastendlaw.co.uk', 'E1 1DT', 5000 ],
        [ 'Ummah Clothing', 'Safiya Begum', 'Clothing', 'Modest fashion for men, women, and children. Thobes, abayas, hijabs.', '020 7247 8899', '', 'E1 1HP', 3000 ],
    ],
    7 => [
        [ 'Al-Madina Supermarket', 'Tariq Hussain', 'Grocery', 'Halal groceries, spices, fresh produce. Open 7 days.', '0121 449 1234', '', 'B12 0YA', 5000 ],
        [ 'Birmingham Islamic Mortgages', 'Farooq Shah', 'Finance', 'Sharia-compliant home purchase plans. FCA regulated.', '0121 440 5678', 'https://bim.co.uk', 'B5 5SE', 10000 ],
        [ 'Crescent Catering Birmingham', 'Nasreen Akhtar', 'Catering', 'Wedding and event catering. Up to 1000 guests.', '07912 345678', '', 'B8 1RJ', 5000 ],
        [ 'Highgate Dental Practice', 'Dr. Zubair Khan', 'Health', 'NHS and private dentistry. Female dentist available.', '0121 440 9012', '', 'B12 0XS', 3000 ],
    ],
    222 => [
        [ 'Curry Mile Halal', 'Imran Malik', 'Restaurant', 'The best biryani on the Curry Mile. Delivery available.', '0161 248 1234', '', 'M14 5LH', 5000 ],
        [ 'Northern Islamic Finance', 'Amir Hassan', 'Finance', 'Halal savings, investments, and home finance.', '0161 248 5678', 'https://nif.co.uk', 'M1 3HZ', 10000 ],
        [ 'Rusholme Motors', 'Ali Raza', 'Automotive', 'MOT, servicing, and repairs. Free courtesy car.', '0161 224 3456', '', 'M14 5TP', 3000 ],
    ],
    305 => [
        [ 'Biryani Express Newcastle', 'Kashif Ahmed', 'Restaurant', 'Authentic Hyderabadi biryani. Dine-in and delivery.', '0191 273 1234', '', 'NE4 8RQ', 5000 ],
        [ 'Newcastle Halal Meats', 'Idrees Khan', 'Butcher', 'Premium HMC halal meat. Free delivery over £50.', '0191 273 5678', '', 'NE4 8DQ', 3000 ],
    ],
];

foreach ( $businesses_by_mosque as $mid => $items ) {
    foreach ( $items as $i => $b ) {
        $wpdb->insert( $p . 'businesses', [
            'mosque_id'        => $mid,
            'business_name'    => $b[0],
            'owner_name'       => $b[1],
            'category'         => $b[2],
            'description'      => $b[3],
            'phone'            => $b[4],
            'website'          => $b[5],
            'postcode'         => $b[6],
            'monthly_fee_pence' => $b[7],
            'featured_position' => $i + 1,
            'status'           => 'active',
            'verified'         => 1,
        ] );
    }
}
echo "Businesses seeded.\n";

// ========================
// SUBSCRIBERS (10 per mosque)
// ========================
$first_names = [ 'Ahmed', 'Fatima', 'Mohammed', 'Aisha', 'Hassan', 'Zainab', 'Omar', 'Khadijah', 'Yusuf', 'Maryam' ];
$last_names = [ 'Ali', 'Khan', 'Patel', 'Begum', 'Hussain', 'Ahmed', 'Rahman', 'Malik', 'Shah', 'Ibrahim' ];
$domains = [ 'gmail.com', 'outlook.com', 'yahoo.co.uk', 'hotmail.com', 'proton.me' ];
$devices = [ 'android', 'ios', 'web' ];

foreach ( $mosque_ids as $mid ) {
    for ( $i = 0; $i < 10; $i++ ) {
        $fn = $first_names[ $i ];
        $ln = $last_names[ $i ];
        $email = strtolower( $fn . '.' . $ln . $mid ) . '@' . $domains[ $i % 5 ];
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$p}subscribers (mosque_id, email, name, phone, device_type, status, subscribed_at, last_active_at)
             VALUES (%d, %s, %s, %s, %s, 'active', %s, %s)",
            $mid, $email, "$fn $ln", '077' . str_pad( rand( 0, 99999999 ), 8, '0', STR_PAD_LEFT ),
            $devices[ $i % 3 ],
            date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 60 ) . ' days' ) ),
            date( 'Y-m-d H:i:s', strtotime( '-' . rand( 0, 5 ) . ' days' ) )
        ) );
    }
}
echo "Subscribers seeded.\n";

// ========================
// ENQUIRIES
// ========================
$enquiries_template = [
    [ 'Sarah Williams', 'sarah.w@gmail.com', '07700200001', 'Nikah Booking', 'We would like to book a nikah ceremony for August. Can you advise on costs and availability?', 'nikah', 'new' ],
    [ 'James Thompson', 'j.thompson@company.co.uk', '07700200002', 'Room Hire', 'We are a local charity and would like to hire the conference room for a fundraising event.', 'room_booking', 'read' ],
    [ 'Amina Hassan', 'amina.h@outlook.com', '07700200003', 'Quran Classes', 'I would like to register my children for weekend Quran classes. What is the process?', 'general', 'replied' ],
];

foreach ( $mosque_ids as $mid ) {
    foreach ( $enquiries_template as $e ) {
        $wpdb->insert( $p . 'enquiries', [
            'mosque_id' => $mid, 'name' => $e[0], 'email' => $e[1], 'phone' => $e[2],
            'subject' => $e[3], 'message' => $e[4], 'type' => $e[5], 'status' => $e[6],
            'replied_at' => $e[6] === 'replied' ? date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) : null,
        ] );
    }
}
echo "Enquiries seeded.\n";

// ========================
// FINAL COUNTS
// ========================
echo "\n=== FINAL COUNTS ===\n";
$tables = [ 'mosques', 'announcements', 'events', 'jumuah_times', 'rooms', 'businesses', 'services', 'subscribers', 'bookings', 'enquiries' ];
foreach ( $tables as $t ) {
    $c = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}{$t}" );
    echo str_pad( $t, 18 ) . ": $c\n";
}
echo "\nDone!\n";
