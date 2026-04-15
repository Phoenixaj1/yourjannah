<?php
if ( ! defined( 'ABSPATH' ) && php_sapi_name() !== 'cli' ) exit;
global $wpdb;
$p = $wpdb->prefix . 'ynj_';
$mid = 1;

// Announcements
$ann = [
    ['Ramadan Prep Workshop This Saturday', 'Join us this Saturday for a Ramadan preparation workshop covering fasting tips, spiritual goals, and meal planning.', 'general', 0],
    ['New Imam Joining Next Month', 'We are pleased to announce Imam Abdulrahman will be joining us. Welcome him at Friday prayers.', 'general', 0],
    ['Mosque WiFi Now Available', 'Free WiFi is now available. Network: YNMasjid-Guest. No password needed.', 'general', 0],
];
foreach ( $ann as $a ) {
    $wpdb->insert( $p.'announcements', ['mosque_id'=>$mid,'title'=>$a[0],'body'=>$a[1],'type'=>$a[2],'pinned'=>$a[3],'status'=>'published','published_at'=>date('Y-m-d H:i:s',strtotime('-'.rand(1,48).' hours'))]);
}
echo count($ann)." announcements\n";

// Events
$events = [
    ['Jannah Nights Talk Series','Monthly inspirational talk about Jannah. This month: Rivers of Paradise.','+4 days','20:00','21:30','Main Hall','talk',200,0,0,0,0,0,''],
    ['Kids Islamic Art Workshop','Children learn Arabic calligraphy and geometric patterns. Ages 6-12.','+6 days','10:00','12:00','Classroom 1','children',20,0,0,0,0,0,''],
    ['Brothers Gym Session','Weekly fitness for brothers. Cardio and weights.','+1 days','07:00','08:30','Local Gym','sports',30,0,0,0,0,0,''],
    ['Muslim Mums Coffee Morning','Informal meet-up for Muslim mums. Creche available.','+3 days','10:30','12:00','Sisters Room','sisters',25,0,0,0,0,0,''],
    ['Career Networking Evening','Muslim professionals meet. Bring business cards.','+8 days','19:00','21:00','Conference Room','community',40,0,0,0,0,0,''],
    ['Revert Support Group','Safe space for new Muslims. Mentoring and friendship.','+5 days','18:00','19:30','Meeting Room','community',15,0,0,0,0,0,''],
    ['Youth Debate Night','Topic: Is social media harmful? Ages 14-21.','+9 days','19:00','21:00','Main Hall','youth',50,0,0,0,0,0,''],
    // Live events
    ['Friday Khutbah Live Stream','Watch this weeks Friday khutbah live.','+0 days','13:00','14:00','Main Hall','talk',0,1,1,50000,15000,12,'https://www.youtube.com/live/jNQXAC9IVRw'],
    ['Evening Quran Recitation Live','Beautiful Quran recitation by Qari Ibrahim.','+2 days','20:30','21:30','Online','quran',0,1,0,20000,0,0,'https://www.youtube.com/live/xmQP5sSHqOQ'],
    ['Ask the Imam Live Q&A','Submit questions in chat. Imam answers live.','+5 days','20:00','21:00','Online','talk',0,1,0,10000,0,0,'https://www.youtube.com/live/5qap5aO4i9A'],
];
foreach ( $events as $e ) {
    $wpdb->insert( $p.'events', [
        'mosque_id'=>$mid,'title'=>$e[0],'description'=>$e[1],
        'event_date'=>date('Y-m-d',strtotime($e[2])),'start_time'=>$e[3].':00','end_time'=>$e[4].':00',
        'location'=>$e[5],'event_type'=>$e[6],'max_capacity'=>$e[7],
        'ticket_price_pence'=>$e[8],'is_online'=>$e[9],'is_live'=>$e[10],
        'donation_target_pence'=>$e[11],'donation_raised_pence'=>$e[12],'donation_count'=>$e[13],
        'live_url'=>$e[14],'status'=>'published',
    ]);
}
echo count($events)." events\n";

// Classes
$classes = [
    ['Quran Reading for Sisters','Quran reading sessions for sisters. Beginners welcome.','Ustadha Khadijah','Quran','drop_in','Sunday','10:00:00','12:00:00',0,0,'','Sisters Room',15,0,'one_off'],
    ['Business Basics for Muslims','Start your halal business. Business plans, funding, legal, marketing.','Imran Ali','Business','course','Thursday','19:00:00','21:00:00',8,0,'','Conference Room',20,3000,'one_off'],
    ['Arabic Calligraphy','Beautiful art of Arabic calligraphy. Materials provided.','Br. Yasser','Arabic','workshop','Saturday','14:00:00','16:00:00',4,0,'','Classroom 1',12,1500,'per_session'],
    ['First Aid Certification','St John Ambulance certified. 2-day programme.','St John Instructor','Health','course','','10:00:00','16:00:00',2,0,'','Main Hall',25,5000,'one_off'],
    ['Fiqh of Salah Hanafi','Comprehensive fiqh of prayer. Hanafi school.','Mufti Ahmad','Fiqh','course','Wednesday','20:00:00','21:00:00',12,0,'','Lecture Hall',40,0,'one_off'],
    ['Digital Marketing Beginners','Social media, SEO, Google My Business. Practical.','Zara Ahmed','Marketing','workshop','Saturday','10:00:00','13:00:00',3,1,'https://zoom.us/j/987654','Online',30,2000,'per_session'],
    ['South Asian Cooking Class','Biryani, curry, roti from scratch. Ingredients provided.','Aunty Nasreen','Cooking','workshop','','14:00:00','17:00:00',1,0,'','Community Kitchen',10,2000,'one_off'],
    ['Youth Coding Club','Learn Python. Ages 12-18. Bring laptop.','Br. Tariq','Youth','course','Sunday','14:00:00','16:00:00',10,0,'','IT Room',15,0,'one_off'],
];
foreach ( $classes as $c ) {
    $wpdb->insert( $p.'classes', [
        'mosque_id'=>$mid,'title'=>$c[0],'description'=>$c[1],
        'instructor_name'=>$c[2],'category'=>$c[3],'class_type'=>$c[4],
        'day_of_week'=>$c[5],'start_time'=>$c[6],'end_time'=>$c[7],
        'total_sessions'=>$c[8],'is_online'=>$c[9],'live_url'=>$c[10],
        'location'=>$c[11],'max_capacity'=>$c[12],'price_pence'=>$c[13],
        'price_type'=>$c[14],'status'=>'active',
        'start_date'=>date('Y-m-d',strtotime('+'.rand(3,30).' days')),
    ]);
}
echo count($classes)." classes\n";

// Extra businesses
$biz = [
    ['Sunnah Gym','Khalid Raza','Health','Brothers-only gym. Monthly from 20.',5000],
    ['Barakah Tutoring','Sister Amina','Education','GCSE and A-Level tutoring. DBS checked.',3000],
    ['Halal Bites Takeaway','Mohammed Hussain','Restaurant','Burgers, wraps, milkshakes. Student discount.',3000],
];
foreach ( $biz as $b ) {
    $wpdb->insert( $p.'businesses', ['mosque_id'=>$mid,'business_name'=>$b[0],'owner_name'=>$b[1],'category'=>$b[2],'description'=>$b[3],'monthly_fee_pence'=>$b[4],'status'=>'active','verified'=>1]);
}
echo count($biz)." businesses\n";

// Extra services
$svc = [
    ['Solihull Web Design','Web Development','WordPress for mosques and Muslim businesses. From 500.','07700 400001','Solihull & Birmingham',1000],
    ['Sister Fatima Counsellor','Counselling','CBT and Islamic counselling for women.','07700 400002','West Midlands',1000],
    ['Br. Hassan Plumber','Plumbing','Emergency plumbing, boiler repairs.','07700 400003','Solihull area',1000],
];
foreach ( $svc as $s ) {
    $wpdb->insert( $p.'services', ['mosque_id'=>$mid,'provider_name'=>$s[0],'service_type'=>$s[1],'description'=>$s[2],'phone'=>$s[3],'area_covered'=>$s[4],'monthly_fee_pence'=>$s[5],'status'=>'active']);
}
echo count($svc)." services\n";

// Print final counts
echo "\n=== MOSQUE 1 FINAL ===\n";
$counts = ['announcements','events','classes','campaigns','businesses','services','rooms','subscribers'];
foreach ( $counts as $t ) {
    echo str_pad($t,16).": ".$wpdb->get_var("SELECT COUNT(*) FROM {$p}{$t} WHERE mosque_id = 1")."\n";
}
echo "Live events: ".$wpdb->get_var("SELECT COUNT(*) FROM {$p}events WHERE mosque_id = 1 AND is_online = 1")."\n";
