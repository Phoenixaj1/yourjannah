<?php
/**
 * YourJannah Prayer Times
 *
 * Fetches, caches, and manages prayer and Jumu'ah times.
 *
 * @package YourJannah
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YNJ_Prayer {

    /**
     * Prayer names in display order.
     */
    const PRAYER_NAMES = [ 'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha' ];

    /**
     * Jama'at prayer names (excludes sunrise).
     */
    const JAMAT_NAMES = [ 'fajr_jamat', 'dhuhr_jamat', 'asr_jamat', 'maghrib_jamat', 'isha_jamat' ];

    /**
     * Fetch prayer times from the Aladhan API.
     *
     * Results are cached in a transient for 24 hours.
     *
     * @param  float  $lat    Latitude.
     * @param  float  $lng    Longitude.
     * @param  string $date   Date in Y-m-d format.
     * @param  int    $method Calculation method (default 2 = ISNA).
     * @return array|WP_Error {fajr, sunrise, dhuhr, asr, maghrib, isha} as H:i:s TIME strings.
     */
    public static function fetch_from_aladhan( $lat, $lng, $date, $method = 2 ) {
        // Check transient cache first.
        $cache_key = 'ynj_aladhan_' . md5( "{$lat}_{$lng}_{$date}_{$method}" );
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        // Convert date to Unix timestamp for Aladhan API.
        $timestamp = strtotime( $date );
        if ( ! $timestamp ) {
            return new WP_Error( 'invalid_date', 'Invalid date format. Use Y-m-d.', [ 'status' => 400 ] );
        }

        $url = add_query_arg(
            [
                'latitude'  => $lat,
                'longitude' => $lng,
                'method'    => (int) $method,
            ],
            "https://api.aladhan.com/v1/timings/{$timestamp}"
        );

        $response = wp_remote_get( $url, [
            'timeout' => 10,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Failed to fetch prayer times from Aladhan.', [ 'status' => 502 ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', "Aladhan API returned status {$code}.", [ 'status' => 502 ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['data']['timings'] ) ) {
            return new WP_Error( 'api_error', 'Unexpected response format from Aladhan.', [ 'status' => 502 ] );
        }

        $timings = $body['data']['timings'];

        // Map Aladhan keys to our format, converting HH:MM to HH:MM:00.
        $mapping = [
            'fajr'    => 'Fajr',
            'sunrise' => 'Sunrise',
            'dhuhr'   => 'Dhuhr',
            'asr'     => 'Asr',
            'maghrib' => 'Maghrib',
            'isha'    => 'Isha',
        ];

        $times = [];
        foreach ( $mapping as $key => $aladhan_key ) {
            $raw = $timings[ $aladhan_key ] ?? '';
            // Aladhan returns "HH:MM (TZ)" — strip timezone portion.
            $raw = trim( preg_replace( '/\s*\(.*\)/', '', $raw ) );
            $times[ $key ] = strlen( $raw ) === 5 ? $raw . ':00' : $raw;
        }

        // Cache for 24 hours.
        set_transient( $cache_key, $times, DAY_IN_SECONDS );

        return $times;
    }

    /**
     * Get prayer times for a mosque on a given date.
     *
     * Checks the database first. If no row exists or source is 'api',
     * fetches fresh times from Aladhan and stores them.
     * Merges API times with any manual jama'at overrides.
     *
     * @param  int    $mosque_id  Mosque ID.
     * @param  string $date       Date in Y-m-d format.
     * @return array|WP_Error     Full times array including jamat times.
     */
    public static function get_times( $mosque_id, $date ) {
        global $wpdb;

        $mosque_id = (int) $mosque_id;
        $date      = sanitize_text_field( $date );

        // Validate date format.
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return new WP_Error( 'invalid_date', 'Date must be in Y-m-d format.', [ 'status' => 400 ] );
        }

        $pt_table = YNJ_DB::table( 'prayer_times' );

        // Check for existing row.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$pt_table} WHERE mosque_id = %d AND date = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
                $mosque_id,
                $date
            ),
            ARRAY_A
        );

        // If we have a manual row, return it directly — admin has set everything.
        if ( $row && $row['source'] === 'manual' ) {
            return self::format_row( $row );
        }

        // Fetch mosque lat/lng for API call.
        $mosque_table = YNJ_DB::table( 'mosques' );
        $mosque       = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT latitude, longitude FROM {$mosque_table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
                $mosque_id
            )
        );

        if ( ! $mosque || empty( $mosque->latitude ) || empty( $mosque->longitude ) ) {
            return new WP_Error( 'no_location', 'Mosque location (lat/lng) not set.', [ 'status' => 400 ] );
        }

        // Fetch from Aladhan.
        $api_times = self::fetch_from_aladhan(
            (float) $mosque->latitude,
            (float) $mosque->longitude,
            $date
        );

        if ( is_wp_error( $api_times ) ) {
            // If we have a stale row, return it rather than failing.
            if ( $row ) {
                return self::format_row( $row );
            }
            return $api_times;
        }

        // Upsert: insert or update the prayer times row.
        $data = [
            'mosque_id'  => $mosque_id,
            'date'       => $date,
            'fajr'       => $api_times['fajr'],
            'sunrise'    => $api_times['sunrise'],
            'dhuhr'      => $api_times['dhuhr'],
            'asr'        => $api_times['asr'],
            'maghrib'    => $api_times['maghrib'],
            'isha'       => $api_times['isha'],
            'source'     => 'api',
            'created_at' => current_time( 'mysql', true ),
        ];

        if ( $row ) {
            // Update API times but preserve manual jamat overrides.
            $wpdb->update(
                $pt_table,
                [
                    'fajr'    => $api_times['fajr'],
                    'sunrise' => $api_times['sunrise'],
                    'dhuhr'   => $api_times['dhuhr'],
                    'asr'     => $api_times['asr'],
                    'maghrib' => $api_times['maghrib'],
                    'isha'    => $api_times['isha'],
                ],
                [
                    'mosque_id' => $mosque_id,
                    'date'      => $date,
                ],
                array_fill( 0, 6, '%s' ),
                [ '%d', '%s' ]
            );

            // Refresh the row to include jamat overrides.
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$pt_table} WHERE mosque_id = %d AND date = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
                    $mosque_id,
                    $date
                ),
                ARRAY_A
            );

            return self::format_row( $row );
        }

        // Insert new row.
        $wpdb->insert( $pt_table, $data, [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );

        // Merge with empty jamat times.
        return array_merge( $api_times, [
            'fajr_jamat'    => null,
            'dhuhr_jamat'   => null,
            'asr_jamat'     => null,
            'maghrib_jamat' => null,
            'isha_jamat'    => null,
            'source'        => 'api',
        ] );
    }

    /**
     * Get a full week of prayer times.
     *
     * @param  int    $mosque_id   Mosque ID.
     * @param  string $start_date  Start date in Y-m-d format.
     * @return array               Keyed by date => times array.
     */
    public static function get_week( $mosque_id, $start_date ) {
        $week = [];

        for ( $i = 0; $i < 7; $i++ ) {
            $date = gmdate( 'Y-m-d', strtotime( $start_date . " +{$i} days" ) );
            $times = self::get_times( $mosque_id, $date );

            if ( is_wp_error( $times ) ) {
                $week[ $date ] = [ 'error' => $times->get_error_message() ];
            } else {
                $week[ $date ] = $times;
            }
        }

        return $week;
    }

    /**
     * Set jama'at times for a mosque on a given date.
     *
     * Creates or updates the prayer_times row and marks source as 'manual'.
     *
     * @param  int    $mosque_id  Mosque ID.
     * @param  string $date       Date in Y-m-d format.
     * @param  array  $times      {fajr_jamat, dhuhr_jamat, asr_jamat, maghrib_jamat, isha_jamat} as H:i:s.
     * @return bool|WP_Error      True on success.
     */
    public static function set_jamat_times( $mosque_id, $date, $times ) {
        global $wpdb;

        $mosque_id = (int) $mosque_id;
        $date      = sanitize_text_field( $date );

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return new WP_Error( 'invalid_date', 'Date must be in Y-m-d format.', [ 'status' => 400 ] );
        }

        // Sanitize jamat times.
        $jamat_data = [];
        foreach ( self::JAMAT_NAMES as $key ) {
            if ( isset( $times[ $key ] ) && ! empty( $times[ $key ] ) ) {
                // Validate time format HH:MM or HH:MM:SS.
                $t = sanitize_text_field( $times[ $key ] );
                if ( preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $t ) ) {
                    $jamat_data[ $key ] = strlen( $t ) === 5 ? $t . ':00' : $t;
                }
            }
        }

        if ( empty( $jamat_data ) ) {
            return new WP_Error( 'no_times', 'No valid jama\'at times provided.', [ 'status' => 400 ] );
        }

        $pt_table = YNJ_DB::table( 'prayer_times' );

        // Check if row exists.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$pt_table} WHERE mosque_id = %d AND date = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
                $mosque_id,
                $date
            )
        );

        if ( $exists ) {
            // Update existing row.
            $jamat_data['source'] = 'manual';
            $wpdb->update(
                $pt_table,
                $jamat_data,
                [ 'mosque_id' => $mosque_id, 'date' => $date ],
                array_fill( 0, count( $jamat_data ), '%s' ),
                [ '%d', '%s' ]
            );
        } else {
            // Insert new row with jamat times only (API times will be fetched on next get_times call).
            $insert_data = array_merge(
                [
                    'mosque_id'  => $mosque_id,
                    'date'       => $date,
                    'source'     => 'manual',
                    'created_at' => current_time( 'mysql', true ),
                ],
                $jamat_data
            );
            $wpdb->insert( $pt_table, $insert_data );
        }

        return true;
    }

    /**
     * Get all enabled Jumu'ah prayer slots for a mosque.
     *
     * @param  int   $mosque_id  Mosque ID.
     * @return array             Array of Jumu'ah slot objects.
     */
    public static function get_jumuah( $mosque_id ) {
        global $wpdb;

        $table = YNJ_DB::table( 'jumuah_times' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, slot_name, khutbah_time, salah_time, language
                 FROM {$table}
                 WHERE mosque_id = %d AND enabled = 1
                 ORDER BY salah_time ASC", // phpcs:ignore WordPress.DB.PreparedSQL
                (int) $mosque_id
            )
        );
    }

    /**
     * Get the next upcoming prayer based on current time.
     *
     * @param  array $times  Prayer times array {fajr, sunrise, dhuhr, asr, maghrib, isha} as H:i:s.
     * @return array          {name, time, countdown_seconds}.
     */
    public static function get_next_prayer( $times ) {
        $now = current_time( 'H:i:s' );

        foreach ( self::PRAYER_NAMES as $name ) {
            if ( empty( $times[ $name ] ) ) {
                continue;
            }

            $prayer_time = $times[ $name ];

            if ( $prayer_time > $now ) {
                $now_ts    = strtotime( 'today ' . $now );
                $prayer_ts = strtotime( 'today ' . $prayer_time );

                return [
                    'name'              => $name,
                    'time'              => $prayer_time,
                    'countdown_seconds' => max( 0, $prayer_ts - $now_ts ),
                ];
            }
        }

        // All prayers have passed today — next is tomorrow's Fajr.
        if ( ! empty( $times['fajr'] ) ) {
            $now_ts    = strtotime( 'today ' . $now );
            $prayer_ts = strtotime( 'tomorrow ' . $times['fajr'] );

            return [
                'name'              => 'fajr',
                'time'              => $times['fajr'],
                'countdown_seconds' => max( 0, $prayer_ts - $now_ts ),
            ];
        }

        return [
            'name'              => 'fajr',
            'time'              => null,
            'countdown_seconds' => 0,
        ];
    }

    /**
     * Calculate travel time between user and mosque using haversine formula.
     *
     * @param  float $user_lat    User latitude.
     * @param  float $user_lng    User longitude.
     * @param  float $mosque_lat  Mosque latitude.
     * @param  float $mosque_lng  Mosque longitude.
     * @return array              {distance_km, walk_minutes, drive_minutes}.
     */
    public static function calculate_travel_time( $user_lat, $user_lng, $mosque_lat, $mosque_lng ) {
        $earth_radius_km = 6371.0;

        $lat1 = deg2rad( (float) $user_lat );
        $lat2 = deg2rad( (float) $mosque_lat );
        $dlat = deg2rad( (float) $mosque_lat - (float) $user_lat );
        $dlng = deg2rad( (float) $mosque_lng - (float) $user_lng );

        $a = sin( $dlat / 2 ) * sin( $dlat / 2 )
           + cos( $lat1 ) * cos( $lat2 )
           * sin( $dlng / 2 ) * sin( $dlng / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        $distance_km = round( $earth_radius_km * $c, 2 );

        // Average walking speed: ~5 km/h, driving speed: ~30 km/h (urban).
        $walk_minutes  = (int) ceil( ( $distance_km / 5.0 ) * 60 );
        $drive_minutes = (int) ceil( ( $distance_km / 30.0 ) * 60 );

        return [
            'distance_km'   => $distance_km,
            'walk_minutes'  => $walk_minutes,
            'drive_minutes' => $drive_minutes,
        ];
    }

    /**
     * Format a database row into a clean times array.
     *
     * @param  array $row  Database row as associative array.
     * @return array       Formatted times.
     */
    private static function format_row( $row ) {
        $result = [ 'source' => $row['source'] ?? 'api' ];

        foreach ( self::PRAYER_NAMES as $name ) {
            $result[ $name ] = $row[ $name ] ?? null;
        }

        foreach ( self::JAMAT_NAMES as $name ) {
            $result[ $name ] = $row[ $name ] ?? null;
        }

        return $result;
    }
}
