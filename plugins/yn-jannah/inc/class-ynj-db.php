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
    const SCHEMA_VERSION = '1.0.0';

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
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY mosque_id (mosque_id),
            KEY status (status),
            KEY event_date (event_date)
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

        // 12. Subscribers
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

        return $tables;
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
            'subscribers',
        ];

        foreach ( $table_names as $name ) {
            $wpdb->query( "DROP TABLE IF EXISTS " . self::table( $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL
        }

        delete_option( 'ynj_db_version' );
    }
}
