<?php
/**
 * YourJannah Database Schema
 *
 * Creates and manages all plugin database tables.
 *
 * @package YourJannah
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_DB {

    /**
     * Current schema version.
     */
    const SCHEMA_VERSION = '2.1.0';

    /**
     * Return the full table name for a given short name.
     *
     * @param  string $name  Short table name (e.g. 'mosques').
     * @return string        Full prefixed table name.
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . YNJ_TABLE_PREFIX . $name;
    }

    /**
     * Create or update all plugin tables.
     *
     * Uses WordPress dbDelta() for safe, idempotent schema changes.
     */
    public static function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = self::get_schema( $charset_collate );

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        update_option( 'ynj_db_version', self::SCHEMA_VERSION );

        // Add composite indexes for production performance
        self::add_performance_indexes();
    }

    /**
     * Add composite indexes for common query patterns.
     * Safe to call multiple times — checks existence first.
     */
    public static function add_performance_indexes() {
        global $wpdb;

        $indexes = [
            [ self::table( 'events' ),               'idx_events_mosque_status_date',  'mosque_id, status, event_date' ],
            [ self::table( 'announcements' ),         'idx_ann_mosque_status',          'mosque_id, status, published_at' ],
            [ self::table( 'classes' ),               'idx_classes_mosque_status_cat',  'mosque_id, status, category' ],
            [ self::table( 'masjid_services' ),       'idx_msvc_mosque_status',         'mosque_id, status' ],
            [ self::table( 'madrassah_attendance' ),  'idx_att_student_date',           'student_id, attendance_date' ],
            [ self::table( 'madrassah_fees' ),        'idx_fees_mosque_status',         'mosque_id, status' ],
            [ self::table( 'user_subscriptions' ),    'idx_usub_user_status',           'user_id, status' ],
            [ self::table( 'user_subscriptions' ),    'idx_usub_mosque',                'mosque_id' ],
            [ self::table( 'patrons' ),               'idx_patrons_mosque_status',      'mosque_id, status' ],
        ];

        foreach ( $indexes as $idx ) {
            $table = $idx[0];
            $name  = $idx[1];
            $cols  = $idx[2];
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s",
                $table, $name
            ) );
            if ( ! $exists ) {
                $wpdb->query( "ALTER TABLE `$table` ADD INDEX `$name` ($cols)" ); // phpcs:ignore
            }
        }
    }

    /**
     * Return array of CREATE TABLE statements.
     *
     * @param  string $charset_collate  WordPress charset collate string.
     * @return array
     */
    private static function get_schema( $charset_collate ) {
        $t = function ( $name ) {
            return self::table( $name );
        };

        $tables = [];

        // 1. Mosques
        $tables[] = "CREATE TABLE {$t('mosques')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            slug varchar(255) NOT NULL DEFAULT '',
            address varchar(500) NOT NULL DEFAULT '',
            city varchar(100) NOT NULL DEFAULT '',
            postcode varchar(20) NOT NULL DEFAULT '',
            country varchar(100) NOT NULL DEFAULT '',
            latitude decimal(10,7) DEFAULT NULL,
            longitude decimal(10,7) DEFAULT NULL,
            timezone varchar(50) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            website varchar(500) NOT NULL DEFAULT '',
            logo_url varchar(500) NOT NULL DEFAULT '',
            photo_url varchar(500) NOT NULL DEFAULT '',
            description text NOT NULL,
            has_women_section tinyint(1) NOT NULL DEFAULT 0,
            has_wudu tinyint(1) NOT NULL DEFAULT 0,
            has_parking tinyint(1) NOT NULL DEFAULT 0,
            capacity int(11) NOT NULL DEFAULT 0,
            admin_email varchar(255) NOT NULL DEFAULT '',
            admin_password_hash varchar(255) NOT NULL DEFAULT '',
            admin_token_hash varchar(64) NOT NULL DEFAULT '',
            admin_token_last_used datetime DEFAULT NULL,
            dfm_slug varchar(100) NOT NULL DEFAULT '',
            dfm_mosque_id bigint(20) unsigned DEFAULT NULL,
            setup_complete tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY mosque_id (id),
            KEY status (status),
            KEY city (city),
            KEY postcode (postcode),
            KEY admin_email (admin_email),
            KEY admin_token_hash (admin_token_hash)
        ) $charset_collate;";

        // 2. Prayer Times
        $tables[] = "CREATE TABLE {$t('prayer_times')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            date date NOT NULL,
            fajr time DEFAULT NULL,
            sunrise time DEFAULT NULL,
            dhuhr time DEFAULT NULL,
            asr time DEFAULT NULL,
            maghrib time DEFAULT NULL,
            isha time DEFAULT NULL,
            fajr_jamat time DEFAULT NULL,
            dhuhr_jamat time DEFAULT NULL,
            asr_jamat time DEFAULT NULL,
            maghrib_jamat time DEFAULT NULL,
            isha_jamat time DEFAULT NULL,
            taraweeh time DEFAULT NULL,
            suhoor time DEFAULT NULL,
            source varchar(20) NOT NULL DEFAULT 'api',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY mosque_date (mosque_id, date),
            KEY mosque_id (mosque_id),
            KEY status (source)
        ) $charset_collate;";

        // 3. Jumuah Times
        $tables[] = "CREATE TABLE {$t('jumuah_times')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            slot_name varchar(50) NOT NULL DEFAULT '',
            khutbah_time time DEFAULT NULL,
            salah_time time DEFAULT NULL,
            language varchar(30) NOT NULL DEFAULT '',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (enabled)
        ) $charset_collate;";

        // 4. Eid Times
        $tables[] = "CREATE TABLE {$t('eid_times')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            eid_type varchar(20) NOT NULL DEFAULT '',
            year smallint(6) NOT NULL DEFAULT 0,
            slot_name varchar(50) NOT NULL DEFAULT '',
            salah_time time DEFAULT NULL,
            location_notes text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (eid_type)
        ) $charset_collate;";

        // 5. Announcements
        $tables[] = "CREATE TABLE {$t('announcements')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL DEFAULT '',
            body text NOT NULL,
            image_url varchar(500) NOT NULL DEFAULT '',
            type varchar(30) NOT NULL DEFAULT 'general',
            push_sent tinyint(1) NOT NULL DEFAULT 0,
            push_sent_at datetime DEFAULT NULL,
            pinned tinyint(1) NOT NULL DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            published_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY type (type)
        ) $charset_collate;";

        // 6. Events
        $tables[] = "CREATE TABLE {$t('events')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL DEFAULT '',
            description text NOT NULL,
            image_url varchar(500) NOT NULL DEFAULT '',
            event_date date DEFAULT NULL,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            location varchar(255) NOT NULL DEFAULT '',
            event_type varchar(30) NOT NULL DEFAULT '',
            max_capacity int(11) NOT NULL DEFAULT 0,
            registered_count int(11) NOT NULL DEFAULT 0,
            requires_booking tinyint(1) NOT NULL DEFAULT 0,
            ticket_price_pence int(11) NOT NULL DEFAULT 0,
            is_online tinyint(1) NOT NULL DEFAULT 0,
            is_live tinyint(1) NOT NULL DEFAULT 0,
            live_url varchar(500) NOT NULL DEFAULT '',
            live_started_at datetime DEFAULT NULL,
            live_ended_at datetime DEFAULT NULL,
            donation_target_pence bigint(20) NOT NULL DEFAULT 0,
            donation_raised_pence bigint(20) NOT NULL DEFAULT 0,
            donation_count int(11) NOT NULL DEFAULT 0,
            needs_volunteers tinyint(1) NOT NULL DEFAULT 0,
            volunteer_roles varchar(500) NOT NULL DEFAULT '',
            volunteer_count int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY event_date (event_date),
            KEY is_live (is_live)
        ) $charset_collate;";

        // 7. Bookings
        $tables[] = "CREATE TABLE {$t('bookings')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_id bigint(20) unsigned DEFAULT NULL,
            room_id bigint(20) unsigned DEFAULT NULL,
            user_name varchar(255) NOT NULL DEFAULT '',
            user_email varchar(255) NOT NULL DEFAULT '',
            user_phone varchar(50) NOT NULL DEFAULT '',
            booking_date date DEFAULT NULL,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            notes text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY event_id (event_id),
            KEY room_id (room_id),
            KEY booking_date (booking_date)
        ) $charset_collate;";

        // 8. Rooms
        $tables[] = "CREATE TABLE {$t('rooms')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL DEFAULT '',
            description text NOT NULL,
            capacity int(11) NOT NULL DEFAULT 0,
            hourly_rate_pence int(11) NOT NULL DEFAULT 0,
            daily_rate_pence int(11) NOT NULL DEFAULT 0,
            photo_url varchar(500) NOT NULL DEFAULT '',
            availability_notes text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status)
        ) $charset_collate;";

        // 9. Enquiries
        $tables[] = "CREATE TABLE {$t('enquiries')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL DEFAULT '',
            message text NOT NULL,
            type varchar(30) NOT NULL DEFAULT 'general',
            status varchar(20) NOT NULL DEFAULT 'new',
            replied_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY type (type)
        ) $charset_collate;";

        // 10. Businesses
        $tables[] = "CREATE TABLE {$t('businesses')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            business_name varchar(255) NOT NULL DEFAULT '',
            owner_name varchar(255) NOT NULL DEFAULT '',
            category varchar(50) NOT NULL DEFAULT '',
            description text NOT NULL,
            phone varchar(50) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            website varchar(500) NOT NULL DEFAULT '',
            logo_url varchar(500) NOT NULL DEFAULT '',
            address varchar(500) NOT NULL DEFAULT '',
            postcode varchar(20) NOT NULL DEFAULT '',
            monthly_fee_pence int(11) NOT NULL DEFAULT 3000,
            featured_position int(11) NOT NULL DEFAULT 0,
            stripe_customer_id varchar(100) NOT NULL DEFAULT '',
            stripe_subscription_id varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            verified tinyint(1) NOT NULL DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY category (category)
        ) $charset_collate;";

        // 11. Services
        $tables[] = "CREATE TABLE {$t('services')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            provider_name varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            service_type varchar(50) NOT NULL DEFAULT '',
            description text NOT NULL,
            hourly_rate_pence int(11) NOT NULL DEFAULT 0,
            area_covered varchar(255) NOT NULL DEFAULT '',
            monthly_fee_pence int(11) NOT NULL DEFAULT 1000,
            stripe_subscription_id varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY service_type (service_type)
        ) $charset_collate;";

        // 12. Classes
        $tables[] = "CREATE TABLE {$t('classes')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL DEFAULT '',
            description text NOT NULL,
            instructor_name varchar(255) NOT NULL DEFAULT '',
            instructor_bio text NOT NULL,
            category varchar(50) NOT NULL DEFAULT '',
            class_type varchar(20) NOT NULL DEFAULT 'course',
            schedule_text varchar(500) NOT NULL DEFAULT '',
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            day_of_week varchar(20) NOT NULL DEFAULT '',
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            total_sessions int(11) NOT NULL DEFAULT 1,
            is_online tinyint(1) NOT NULL DEFAULT 0,
            live_url varchar(500) NOT NULL DEFAULT '',
            location varchar(255) NOT NULL DEFAULT '',
            max_capacity int(11) NOT NULL DEFAULT 0,
            enrolled_count int(11) NOT NULL DEFAULT 0,
            price_pence int(11) NOT NULL DEFAULT 0,
            price_type varchar(20) NOT NULL DEFAULT 'one_off',
            image_url varchar(500) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY category (category),
            KEY is_online (is_online)
        ) $charset_collate;";

        // 12b. Class Sessions (curriculum)
        $tables[] = "CREATE TABLE {$t('class_sessions')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            class_id bigint(20) unsigned NOT NULL DEFAULT 0,
            session_number int(11) NOT NULL DEFAULT 1,
            title varchar(255) NOT NULL DEFAULT '',
            description text NOT NULL,
            session_date date DEFAULT NULL,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            is_online tinyint(1) NOT NULL DEFAULT 0,
            live_url varchar(500) NOT NULL DEFAULT '',
            recording_url varchar(500) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'scheduled',
            PRIMARY KEY  (id),
            KEY class_id (class_id),
            KEY session_date (session_date)
        ) $charset_collate;";

        // 12c. Class Enrolments
        $tables[] = "CREATE TABLE {$t('enrolments')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            class_id bigint(20) unsigned NOT NULL DEFAULT 0,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_name varchar(255) NOT NULL DEFAULT '',
            user_email varchar(255) NOT NULL DEFAULT '',
            user_phone varchar(50) NOT NULL DEFAULT '',
            amount_paid_pence int(11) NOT NULL DEFAULT 0,
            stripe_session_id varchar(255) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            enrolled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY class_id (class_id),
            KEY mosque_id (mosque_id),
            KEY user_email (user_email),
            KEY status (status)
        ) $charset_collate;";

        // 13. Fundraising Campaigns
        $tables[] = "CREATE TABLE {$t('campaigns')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL DEFAULT '',
            description text NOT NULL,
            image_url varchar(500) NOT NULL DEFAULT '',
            target_pence bigint(20) NOT NULL DEFAULT 0,
            raised_pence bigint(20) NOT NULL DEFAULT 0,
            donor_count int(11) NOT NULL DEFAULT 0,
            category varchar(50) NOT NULL DEFAULT 'general',
            dfm_link varchar(500) NOT NULL DEFAULT '',
            recurring tinyint(1) NOT NULL DEFAULT 0,
            recurring_interval varchar(20) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY category (category)
        ) $charset_collate;";

        // 13. Users (congregation members)
        $tables[] = "CREATE TABLE {$t('users')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            password_hash varchar(255) NOT NULL DEFAULT '',
            favourite_mosque_id bigint(20) unsigned DEFAULT NULL,
            verified_congregation tinyint(1) NOT NULL DEFAULT 0,
            verified_at datetime DEFAULT NULL,
            verified_lat decimal(10,7) DEFAULT NULL,
            verified_lng decimal(10,7) DEFAULT NULL,
            travel_mode varchar(10) NOT NULL DEFAULT 'walk',
            travel_minutes int(11) NOT NULL DEFAULT 0,
            push_endpoint text NOT NULL,
            push_p256dh text NOT NULL,
            push_auth varchar(100) NOT NULL DEFAULT '',
            alert_before_minutes int(11) NOT NULL DEFAULT 20,
            total_points int(11) NOT NULL DEFAULT 0,
            token_hash varchar(64) NOT NULL DEFAULT '',
            token_last_used datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY favourite_mosque_id (favourite_mosque_id),
            KEY token_hash (token_hash),
            KEY status (status)
        ) $charset_collate;";

        // 14. Madrassah Terms
        $tables[] = "CREATE TABLE {$t('madrassah_terms')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL DEFAULT '',
            start_date date NOT NULL,
            end_date date NOT NULL,
            fee_pence int(11) NOT NULL DEFAULT 0,
            fee_frequency varchar(20) NOT NULL DEFAULT 'termly',
            enrolment_open tinyint(1) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status)
        ) $charset_collate;";

        // 15. Madrassah Students (children linked to parent user)
        $tables[] = "CREATE TABLE {$t('madrassah_students')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            parent_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            parent_name varchar(255) NOT NULL DEFAULT '',
            parent_email varchar(255) NOT NULL DEFAULT '',
            parent_phone varchar(50) NOT NULL DEFAULT '',
            child_name varchar(255) NOT NULL DEFAULT '',
            child_dob date DEFAULT NULL,
            year_group varchar(30) NOT NULL DEFAULT '',
            class_id bigint(20) unsigned DEFAULT NULL,
            medical_notes text NOT NULL,
            emergency_contact varchar(255) NOT NULL DEFAULT '',
            emergency_phone varchar(50) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            enrolled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY parent_user_id (parent_user_id),
            KEY class_id (class_id),
            KEY status (status)
        ) $charset_collate;";

        // 16. Madrassah Attendance
        $tables[] = "CREATE TABLE {$t('madrassah_attendance')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            student_id bigint(20) unsigned NOT NULL DEFAULT 0,
            class_id bigint(20) unsigned NOT NULL DEFAULT 0,
            session_id bigint(20) unsigned DEFAULT NULL,
            attendance_date date NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'present',
            notes varchar(255) NOT NULL DEFAULT '',
            marked_by varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY student_date (student_id, attendance_date, class_id),
            KEY student_id (student_id),
            KEY class_id (class_id),
            KEY attendance_date (attendance_date),
            KEY status (status)
        ) $charset_collate;";

        // 17. Madrassah Fee Payments
        $tables[] = "CREATE TABLE {$t('madrassah_fees')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            student_id bigint(20) unsigned NOT NULL DEFAULT 0,
            term_id bigint(20) unsigned NOT NULL DEFAULT 0,
            parent_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            amount_pence int(11) NOT NULL DEFAULT 0,
            stripe_session_id varchar(255) NOT NULL DEFAULT '',
            stripe_payment_intent varchar(255) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'unpaid',
            paid_at datetime DEFAULT NULL,
            due_date date DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY student_id (student_id),
            KEY term_id (term_id),
            KEY parent_user_id (parent_user_id),
            KEY status (status)
        ) $charset_collate;";

        // 18. Madrassah Reports (teacher notes / progress)
        $tables[] = "CREATE TABLE {$t('madrassah_reports')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            student_id bigint(20) unsigned NOT NULL DEFAULT 0,
            term_id bigint(20) unsigned NOT NULL DEFAULT 0,
            class_id bigint(20) unsigned DEFAULT NULL,
            subject varchar(100) NOT NULL DEFAULT '',
            grade varchar(20) NOT NULL DEFAULT '',
            teacher_notes text NOT NULL,
            quran_progress varchar(255) NOT NULL DEFAULT '',
            behaviour varchar(20) NOT NULL DEFAULT 'good',
            created_by varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY student_id (student_id),
            KEY term_id (term_id),
            KEY class_id (class_id)
        ) $charset_collate;";

        // 19. Patrons (mosque memberships)
        $tables[] = "CREATE TABLE {$t('patrons')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_name varchar(255) NOT NULL DEFAULT '',
            user_email varchar(255) NOT NULL DEFAULT '',
            tier varchar(20) NOT NULL DEFAULT 'supporter',
            amount_pence int(11) NOT NULL DEFAULT 500,
            stripe_customer_id varchar(100) NOT NULL DEFAULT '',
            stripe_subscription_id varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            started_at datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY mosque_user (mosque_id, user_id),
            KEY mosque_id (mosque_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY stripe_subscription_id (stripe_subscription_id)
        ) $charset_collate;";

        // 20. Patron Intentions (pledge/waitlist for unclaimed mosques)
        $tables[] = "CREATE TABLE {$t('patron_intentions')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            tier varchar(30) NOT NULL DEFAULT 'supporter',
            amount_pence int(11) NOT NULL DEFAULT 500,
            status varchar(20) NOT NULL DEFAULT 'active',
            notified_at datetime DEFAULT NULL,
            converted_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";

        // 21. Mosque Page Views (lightweight analytics for demand tracking)
        $tables[] = "CREATE TABLE {$t('mosque_views')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            view_date date NOT NULL,
            view_count int NOT NULL DEFAULT 1,
            source varchar(30) NOT NULL DEFAULT 'page',
            PRIMARY KEY  (id),
            UNIQUE KEY mosque_date_source (mosque_id, view_date, source),
            KEY mosque_id (mosque_id),
            KEY view_date (view_date)
        ) $charset_collate;";

        // 15. Masjid Services (mosque-offered bookable services: nikkah, funeral, etc.)
        $tables[] = "CREATE TABLE {$t('masjid_services')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL DEFAULT '',
            category varchar(50) NOT NULL DEFAULT 'general',
            description text NOT NULL,
            price_pence int(11) NOT NULL DEFAULT 0,
            price_label varchar(100) NOT NULL DEFAULT '',
            contact_phone varchar(50) NOT NULL DEFAULT '',
            contact_email varchar(255) NOT NULL DEFAULT '',
            availability varchar(500) NOT NULL DEFAULT '',
            requires_approval tinyint(1) NOT NULL DEFAULT 1,
            image_url varchar(500) NOT NULL DEFAULT '',
            sort_order int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY category (category),
            KEY status (status)
        ) $charset_collate;";

        // 15b. Masjid Service Enquiries (booking requests for masjid services)
        $tables[] = "CREATE TABLE {$t('masjid_service_enquiries')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            service_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_name varchar(255) NOT NULL DEFAULT '',
            user_email varchar(255) NOT NULL DEFAULT '',
            user_phone varchar(50) NOT NULL DEFAULT '',
            preferred_date date DEFAULT NULL,
            message text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            admin_notes text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY service_id (service_id),
            KEY status (status)
        ) $charset_collate;";

        // 15c. User Subscriptions (multi-mosque + notification preferences)
        $tables[] = "CREATE TABLE {$t('user_subscriptions')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            notify_events tinyint(1) NOT NULL DEFAULT 1,
            notify_classes tinyint(1) NOT NULL DEFAULT 1,
            notify_announcements tinyint(1) NOT NULL DEFAULT 1,
            notify_fundraising tinyint(1) NOT NULL DEFAULT 0,
            notify_live tinyint(1) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'active',
            subscribed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_mosque (user_id, mosque_id),
            KEY user_id (user_id),
            KEY mosque_id (mosque_id),
            KEY status (status)
        ) $charset_collate;";

        // 15b. Subscribers (anonymous push — legacy)
        $tables[] = "CREATE TABLE {$t('subscribers')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email varchar(255) NOT NULL DEFAULT '',
            name varchar(255) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            push_endpoint text NOT NULL,
            push_p256dh text NOT NULL,
            push_auth varchar(100) NOT NULL DEFAULT '',
            device_type varchar(20) NOT NULL DEFAULT '',
            subscribed_at datetime DEFAULT NULL,
            last_active_at datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY  (id),
            UNIQUE KEY mosque_email (mosque_id, email),
            KEY mosque_id (mosque_id),
            KEY status (status)
        ) $charset_collate;";

        // 17. Points Ledger
        $tables[] = "CREATE TABLE {$t('points')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(30) NOT NULL DEFAULT '',
            points int(11) NOT NULL DEFAULT 0,
            ref_id bigint(20) unsigned DEFAULT NULL,
            description varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY mosque_id (mosque_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 18. Event Volunteers
        $tables[] = "CREATE TABLE {$t('event_volunteers')} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL DEFAULT 0,
            mosque_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_name varchar(255) NOT NULL DEFAULT '',
            user_email varchar(255) NOT NULL DEFAULT '',
            user_phone varchar(50) NOT NULL DEFAULT '',
            role varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY mosque_id (mosque_id)
        ) $charset_collate;";

        return $tables;
    }

    /**
     * Resolve a mosque slug to its ID.
     *
     * @param  string   $slug  Mosque slug.
     * @return int|null         Mosque ID or null if not found.
     */
    public static function resolve_slug( $slug ) {
        global $wpdb;
        $table = self::table( 'mosques' );

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s AND status IN ('active','unclaimed') LIMIT 1",
            sanitize_text_field( $slug )
        ) );
    }

    /**
     * Drop all plugin tables.
     *
     * Only used during full uninstall.
     */
    public static function uninstall() {
        global $wpdb;

        $table_names = [
            'mosques',
            'prayer_times',
            'jumuah_times',
            'eid_times',
            'announcements',
            'events',
            'bookings',
            'rooms',
            'enquiries',
            'businesses',
            'services',
            'classes',
            'class_sessions',
            'enrolments',
            'campaigns',
            'madrassah_terms',
            'madrassah_students',
            'madrassah_attendance',
            'madrassah_fees',
            'madrassah_reports',
            'patrons',
            'masjid_services',
            'masjid_service_enquiries',
            'user_subscriptions',
            'users',
            'subscribers',
        ];

        foreach ( $table_names as $name ) {
            $wpdb->query( "DROP TABLE IF EXISTS " . self::table( $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL
        }

        delete_option( 'ynj_db_version' );
    }
}
