<?php
/**
 * One-time seed: demo patrons, madrassah terms/students, mosque profile.
 * Run via: /wp-admin/admin.php?ynj_seed_extra=1
 * Auto-deletes after running.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', function() {
    if ( ! isset( $_GET['ynj_seed_extra'] ) || ! current_user_can( 'manage_options' ) ) return;

    global $wpdb;
    $mosque_id = 1;

    // Update mosque profile
    $wpdb->update( YNJ_DB::table( 'mosques' ), [
        'phone'       => '0121 707 1234',
        'email'       => 'info@yourniyyahmasjid.org.uk',
        'website'     => 'https://yourniyyahmasjid.org.uk',
        'description' => 'YourNiyyah Masjid is a welcoming community mosque in Solihull serving over 500 families. We offer daily prayers, Jumuah khutbahs in English and Arabic, a thriving madrassah for children, regular community events, and a range of services. Our mission is to nurture faith, build community, and serve with excellence.',
        'has_women_section' => 1,
        'has_wudu'    => 1,
        'has_parking'  => 1,
        'capacity'    => 350,
    ], [ 'id' => $mosque_id ] );

    // Seed patrons (if none exist)
    $pt = YNJ_DB::table( 'patrons' );
    if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE mosque_id=%d", $mosque_id ) ) ) {
        $patrons = [
            [ 'Ahmed Khan', 'ahmed.khan@demo.com', 'champion', 2000 ],
            [ 'Fatima Rahman', 'fatima.r@demo.com', 'guardian', 1000 ],
            [ 'Hassan Ali', 'hassan.ali@demo.com', 'supporter', 500 ],
            [ 'Zara Patel', 'zara.patel@demo.com', 'champion', 2000 ],
            [ 'Omar Hussain', 'omar.h@demo.com', 'platinum', 5000 ],
            [ 'Bilal Sheikh', 'bilal.s@demo.com', 'guardian', 1000 ],
            [ 'Nadia Chowdhury', 'nadia.c@demo.com', 'supporter', 500 ],
            [ 'Tariq Mahmood', 'tariq.m@demo.com', 'champion', 2000 ],
        ];
        foreach ( $patrons as $i => $p ) {
            $wpdb->insert( $pt, [
                'mosque_id' => $mosque_id, 'user_id' => 0,
                'user_name' => $p[0], 'user_email' => $p[1],
                'tier' => $p[2], 'amount_pence' => $p[3],
                'status' => 'active', 'started_at' => date( 'Y-m-d', strtotime( "-" . ( $i * 10 ) . " days" ) ),
            ] );
        }
    }

    // Seed madrassah terms (if none exist)
    $tt = YNJ_DB::table( 'madrassah_terms' );
    if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tt WHERE mosque_id=%d", $mosque_id ) ) ) {
        $wpdb->insert( $tt, [ 'mosque_id' => $mosque_id, 'name' => 'Spring Term 2026', 'start_date' => '2026-01-05', 'end_date' => '2026-04-10', 'fee_pence' => 12000, 'status' => 'active' ] );
        $wpdb->insert( $tt, [ 'mosque_id' => $mosque_id, 'name' => 'Summer Term 2026', 'start_date' => '2026-04-21', 'end_date' => '2026-07-18', 'fee_pence' => 12000, 'status' => 'active' ] );
        $wpdb->insert( $tt, [ 'mosque_id' => $mosque_id, 'name' => 'Autumn Term 2026', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'fee_pence' => 12000, 'status' => 'upcoming' ] );
    }

    // Seed madrassah students (if none exist)
    $st = YNJ_DB::table( 'madrassah_students' );
    if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $st WHERE mosque_id=%d", $mosque_id ) ) ) {
        $students = [
            [ 'Yusuf Ahmed', 'Year 4', 'Ahmed Khan', 'ahmed.khan@demo.com', '07700 900001' ],
            [ 'Aisha Rahman', 'Year 3', 'Fatima Rahman', 'fatima.r@demo.com', '07700 900002' ],
            [ 'Ibrahim Hassan', 'Year 5', 'Hassan Ali', 'hassan.ali@demo.com', '07700 900003' ],
            [ 'Maryam Patel', 'Year 2', 'Zara Patel', 'zara.patel@demo.com', '07700 900004' ],
            [ 'Omar Hussain Jr', 'Year 6', 'Omar Hussain', 'omar.h@demo.com', '07700 900005' ],
            [ 'Safiya Malik', 'Year 1', 'Malik Family', 'malik.s@demo.com', '07700 900006' ],
            [ 'Zayd Abdullah', 'Year 4', 'Abdullah Family', 'abdullah.z@demo.com', '07700 900007' ],
            [ 'Khadijah Begum', 'Year 3', 'Begum Family', 'begum.k@demo.com', '07700 900008' ],
        ];
        foreach ( $students as $s ) {
            $wpdb->insert( $st, [
                'mosque_id' => $mosque_id, 'child_name' => $s[0], 'year_group' => $s[1],
                'parent_name' => $s[2], 'parent_email' => $s[3], 'parent_phone' => $s[4],
                'emergency_contact' => 'Emergency - 07700 999999', 'medical_notes' => '', 'status' => 'active',
            ] );
        }
    }

    wp_die( 'Demo data seeded! <a href="' . admin_url() . '">Back to admin</a>' );
} );
