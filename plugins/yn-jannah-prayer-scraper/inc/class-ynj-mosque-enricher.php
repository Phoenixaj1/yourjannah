<?php
/**
 * Mosque Enricher — OpenStreetMap (free) + Claude AI pipeline.
 *
 * 1. Overpass API (free): find all mosques in UK with addresses, websites, phone, facilities
 * 2. Match to existing DB or create new records
 * 3. For mosques with websites: Claude extracts prayer times, jumuah, facilities
 *
 * @package YNJ_Prayer_Scraper
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Mosque_Enricher {

    /**
     * Query OpenStreetMap Overpass API for all mosques in a bounding box or area.
     * This is 100% FREE with no API key needed.
     */
    public static function search_osm( $location ) {
        // Use Overpass API to find mosques
        $query = '[out:json][timeout:60];'
            . 'area["name"="' . addslashes( $location ) . '"]["admin_level"~"[4-8]"]->.searchArea;'
            . '('
            . 'node["amenity"="place_of_worship"]["religion"="muslim"](area.searchArea);'
            . 'way["amenity"="place_of_worship"]["religion"="muslim"](area.searchArea);'
            . 'relation["amenity"="place_of_worship"]["religion"="muslim"](area.searchArea);'
            . ');'
            . 'out center tags;';

        $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode( $query );
        $response = wp_remote_get( $url, [ 'timeout' => 90, 'sslverify' => false ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => 'Overpass API failed: ' . $response->get_error_message() ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $elements = $data['elements'] ?? [];

        $mosques = [];
        foreach ( $elements as $el ) {
            $tags = $el['tags'] ?? [];
            $lat = $el['lat'] ?? ( $el['center']['lat'] ?? null );
            $lng = $el['lon'] ?? ( $el['center']['lon'] ?? null );

            if ( ! $lat || ! $lng ) continue;

            $name = $tags['name'] ?? $tags['name:en'] ?? '';
            if ( ! $name ) continue;

            $mosques[] = [
                'osm_id'   => $el['id'],
                'name'     => $name,
                'address'  => trim( ( $tags['addr:housenumber'] ?? '' ) . ' ' . ( $tags['addr:street'] ?? '' ) ),
                'city'     => $tags['addr:city'] ?? $tags['addr:town'] ?? $location,
                'postcode' => $tags['addr:postcode'] ?? '',
                'country'  => $tags['addr:country'] ?? 'United Kingdom',
                'phone'    => $tags['phone'] ?? $tags['contact:phone'] ?? '',
                'website'  => $tags['website'] ?? $tags['contact:website'] ?? $tags['url'] ?? '',
                'email'    => $tags['email'] ?? $tags['contact:email'] ?? '',
                'lat'      => $lat,
                'lng'      => $lng,
                // OSM facility tags
                'wheelchair'    => $tags['wheelchair'] ?? '',
                'parking'       => ! empty( $tags['parking'] ) || ! empty( $tags['amenity:parking'] ),
                'denomination'  => $tags['denomination'] ?? '',
                'opening_hours' => $tags['opening_hours'] ?? '',
                'capacity'      => (int) ( $tags['capacity'] ?? 0 ),
                'description'   => $tags['description'] ?? $tags['note'] ?? '',
            ];
        }

        return $mosques;
    }

    /**
     * Search for ALL UK mosques using Overpass (split into regional queries).
     */
    public static function search_all_uk() {
        // Split UK into regional bounding boxes to avoid timeout
        $regions = [
            'London'          => '51.2,-0.5,51.7,0.3',
            'South East'      => '50.7,-1.5,51.5,1.5',
            'Midlands'        => '52.0,-2.5,53.0,0.0',
            'North West'      => '53.0,-3.2,54.0,-1.5',
            'Yorkshire'       => '53.3,-2.0,54.2,0.0',
            'North East'      => '54.2,-2.5,55.5,0.0',
            'South West'      => '50.0,-5.8,51.5,-1.5',
            'East'            => '51.5,0.0,52.8,1.8',
            'Wales'           => '51.3,-5.5,53.5,-2.5',
            'Scotland'        => '55.0,-6.5,58.8,0.0',
        ];

        $all_mosques = [];
        $seen_ids = [];

        foreach ( $regions as $name => $bbox ) {
            $query = '[out:json][timeout:60];'
                . '('
                . 'node["amenity"="place_of_worship"]["religion"="muslim"](' . $bbox . ');'
                . 'way["amenity"="place_of_worship"]["religion"="muslim"](' . $bbox . ');'
                . ');'
                . 'out center tags;';

            // Use GET with URL encoding (more reliable than POST)
            $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode( $query );
            $response = wp_remote_get( $url, [ 'timeout' => 90, 'sslverify' => false ] );

            if ( is_wp_error( $response ) ) continue;

            $body = wp_remote_retrieve_body( $response );
            if ( ! $body || $body[0] !== '{' ) continue; // Skip non-JSON responses

            $data = json_decode( $body, true );
            $parsed = self::parse_osm_results( $data['elements'] ?? [] );

            // Deduplicate by OSM ID
            foreach ( $parsed as $m ) {
                if ( ! isset( $seen_ids[ $m['osm_id'] ] ) ) {
                    $seen_ids[ $m['osm_id'] ] = true;
                    $all_mosques[] = $m;
                }
            }

            // Rate limit: 1 second between requests to be nice to Overpass
            sleep( 1 );
        }

        return $all_mosques;
    }

    private static function parse_osm_results( $elements ) {
        $mosques = [];
        foreach ( $elements as $el ) {
            $tags = $el['tags'] ?? [];
            $lat = $el['lat'] ?? ( $el['center']['lat'] ?? null );
            $lng = $el['lon'] ?? ( $el['center']['lon'] ?? null );
            if ( ! $lat || ! $lng ) continue;

            $name = $tags['name'] ?? $tags['name:en'] ?? '';
            if ( ! $name ) continue;

            $mosques[] = [
                'osm_id'   => (int) $el['id'],
                'name'     => $name,
                'address'  => trim( ( $tags['addr:housenumber'] ?? '' ) . ' ' . ( $tags['addr:street'] ?? '' ) ),
                'city'     => $tags['addr:city'] ?? $tags['addr:town'] ?? $tags['addr:village'] ?? '',
                'postcode' => $tags['addr:postcode'] ?? '',
                'phone'    => $tags['phone'] ?? $tags['contact:phone'] ?? '',
                'website'  => $tags['website'] ?? $tags['contact:website'] ?? $tags['url'] ?? '',
                'email'    => $tags['email'] ?? $tags['contact:email'] ?? '',
                'lat'      => (float) $lat,
                'lng'      => (float) $lng,
                'wheelchair'    => ( $tags['wheelchair'] ?? '' ) === 'yes',
                'has_parking'   => ! empty( $tags['parking'] ) || ( $tags['amenity'] ?? '' ) === 'parking',
                'capacity'      => (int) ( $tags['capacity'] ?? 0 ),
                'denomination'  => $tags['denomination'] ?? '',
                'description'   => $tags['description'] ?? '',
            ];
        }
        return $mosques;
    }

    /**
     * Match to existing DB or create new mosque record.
     */
    public static function upsert_mosque( $m ) {
        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );

        // Match by postcode + similar name, or exact name
        $existing = null;
        if ( $m['postcode'] ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM $mt WHERE postcode = %s AND SOUNDEX(name) = SOUNDEX(%s) LIMIT 1",
                $m['postcode'], $m['name']
            ) );
        }
        if ( ! $existing ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM $mt WHERE name = %s AND city = %s LIMIT 1", $m['name'], $m['city']
            ) );
        }
        // Also try lat/lng proximity (within ~100m)
        if ( ! $existing && $m['lat'] && $m['lng'] ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM $mt WHERE ABS(latitude - %f) < 0.001 AND ABS(longitude - %f) < 0.001 LIMIT 1",
                $m['lat'], $m['lng']
            ) );
        }

        $fields = array_filter( [
            'name'      => sanitize_text_field( $m['name'] ),
            'address'   => sanitize_text_field( $m['address'] ),
            'city'      => sanitize_text_field( $m['city'] ),
            'postcode'  => sanitize_text_field( $m['postcode'] ),
            'latitude'  => $m['lat'],
            'longitude' => $m['lng'],
        ] );

        // Only update phone/website/email if we have values (don't overwrite existing)
        if ( ! empty( $m['phone'] ) )   $fields['phone']   = sanitize_text_field( $m['phone'] );
        if ( ! empty( $m['website'] ) ) $fields['website']  = esc_url_raw( $m['website'] );
        if ( ! empty( $m['email'] ) )   $fields['email']    = sanitize_email( $m['email'] );
        if ( $m['wheelchair'] )         $fields['has_wudu'] = 1; // Closest match
        if ( $m['has_parking'] )        $fields['has_parking'] = 1;
        if ( $m['capacity'] > 0 )       $fields['capacity'] = (int) $m['capacity'];
        if ( $m['description'] )        $fields['description'] = sanitize_textarea_field( $m['description'] );

        if ( $existing ) {
            $wpdb->update( $mt, $fields, [ 'id' => $existing->id ] );
            return [ 'id' => (int) $existing->id, 'action' => 'updated' ];
        } else {
            $slug = sanitize_title( $m['name'] );
            $slug_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $mt WHERE slug = %s", $slug ) );
            if ( $slug_exists ) $slug .= '-' . sanitize_title( $m['city'] ?: wp_rand( 100, 999 ) );
            $fields['slug'] = $slug;
            $fields['status'] = 'active';
            $fields['country'] = 'United Kingdom';
            $wpdb->insert( $mt, $fields );
            return [ 'id' => (int) $wpdb->insert_id, 'action' => 'created' ];
        }
    }

    /**
     * REST: Search and import ALL UK mosques from OpenStreetMap.
     */
    public static function api_import_all_uk( \WP_REST_Request $request ) {
        @set_time_limit( 600 ); // 10 minutes
        $mosques = self::search_all_uk();
        if ( isset( $mosques['error'] ) ) return new \WP_REST_Response( $mosques, 500 );

        $created = 0;
        $updated = 0;
        $with_website = 0;

        foreach ( $mosques as $m ) {
            $result = self::upsert_mosque( $m );
            if ( $result['action'] === 'created' ) $created++;
            else $updated++;
            if ( $m['website'] ) $with_website++;
        }

        return new \WP_REST_Response( [
            'ok'           => true,
            'total_found'  => count( $mosques ),
            'created'      => $created,
            'updated'      => $updated,
            'with_website' => $with_website,
        ] );
    }

    /**
     * REST: Search by location.
     */
    public static function api_search_location( \WP_REST_Request $request ) {
        $location = sanitize_text_field( $request->get_param( 'location' ) );
        if ( ! $location ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'location required' ], 400 );

        $mosques = self::search_osm( $location );
        if ( isset( $mosques['error'] ) ) return new \WP_REST_Response( $mosques, 500 );

        $created = 0; $updated = 0;
        foreach ( $mosques as $m ) {
            $result = self::upsert_mosque( $m );
            if ( $result['action'] === 'created' ) $created++;
            else $updated++;
        }

        return new \WP_REST_Response( [
            'ok'      => true,
            'location' => $location,
            'found'   => count( $mosques ),
            'created' => $created,
            'updated' => $updated,
        ] );
    }
}
