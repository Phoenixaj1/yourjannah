<?php
/**
 * Import real UK mosques from exported TSV + geocode postcodes.
 * Run via WP-CLI: wp eval-file wp-content/plugins/yn-jannah/seed-import-mosques.php
 */
if ( ! defined( 'ABSPATH' ) && php_sapi_name() !== 'cli' ) exit;

global $wpdb;
$table = $wpdb->prefix . 'ynj_mosques';

// Show existing count
$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
echo "Existing mosques: $existing\n";
// Allow re-import — will skip existing slugs

// Read TSV
$file = __DIR__ . '/data-mosques.tsv';
if ( ! file_exists( $file ) ) {
    echo "ERROR: data-mosques.tsv not found.\n";
    exit;
}

$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
$header = str_getcsv( array_shift( $lines ), "\t" );
echo "Columns: " . implode( ', ', $header ) . "\n";
echo "Rows to import: " . count( $lines ) . "\n\n";

// Parse all rows
$mosques = [];
$postcodes = [];
foreach ( $lines as $line ) {
    $cols = str_getcsv( $line, "\t" );
    $row = array_combine( $header, $cols );

    // Title case the name
    $name = ucwords( strtolower( trim( $row['name'] ) ) );
    // Fix common words
    $name = str_replace(
        [ ' And ', ' Of ', ' The ', ' For ', ' In ', ' Al-', ' Ul ', ' E ', '(uk)', '(Uk)' ],
        [ ' and ', ' of ', ' the ', ' for ', ' in ', ' al-', ' ul ', ' e ', '(UK)', '(UK)' ],
        $name
    );
    // Capitalize first letter always
    $name = ucfirst( $name );

    $slug = sanitize_title( $row['slug'] ?: $name );
    $postcode = strtoupper( trim( $row['postcode'] ) );

    $mosques[] = [
        'name'              => $name,
        'slug'              => $slug,
        'address'           => trim( $row['address'] ),
        'city'              => trim( $row['city'] ),
        'postcode'          => $postcode,
        'country'           => 'UK',
        'phone'             => trim( $row['phone'] ),
        'email'             => trim( $row['email'] ),
        'website'           => trim( $row['website'] ),
        'capacity'          => (int) $row['capacity'],
        'has_women_section' => (int) $row['has_women_section'],
        'has_parking'       => (int) $row['has_parking'],
        'has_wudu'          => (int) $row['has_wudu'],
        'description'       => trim( $row['description'] ),
        'status'            => 'unclaimed',
    ];

    if ( $postcode ) {
        $clean = str_replace( ' ', '', $postcode );
        $postcodes[ $clean ] = $postcode;
    }
}

// Bulk geocode postcodes via postcodes.io (max 100 per request)
echo "Geocoding " . count( $postcodes ) . " unique postcodes...\n";
$coords = [];
$chunks = array_chunk( array_keys( $postcodes ), 100 );

foreach ( $chunks as $i => $chunk ) {
    $response = wp_remote_post( 'https://api.postcodes.io/postcodes', [
        'timeout' => 30,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'postcodes' => $chunk ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        echo "  Chunk $i failed: " . $response->get_error_message() . "\n";
        continue;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! empty( $data['result'] ) ) {
        foreach ( $data['result'] as $item ) {
            if ( ! empty( $item['result'] ) ) {
                $pc = str_replace( ' ', '', $item['query'] );
                $coords[ $pc ] = [
                    'lat' => (float) $item['result']['latitude'],
                    'lng' => (float) $item['result']['longitude'],
                ];
            }
        }
    }

    echo "  Chunk " . ( $i + 1 ) . "/" . count( $chunks ) . " — " . count( $coords ) . " geocoded so far\n";
    // Small delay to be nice to the API
    if ( $i < count( $chunks ) - 1 ) usleep( 200000 );
}

echo "Total geocoded: " . count( $coords ) . " / " . count( $postcodes ) . "\n\n";

// Insert mosques (skip existing by slug — never overwrite active mosques)
$inserted = 0;
$skipped = 0;
$no_coords = 0;
foreach ( $mosques as $m ) {
    // Look up coordinates
    $pc_clean = str_replace( ' ', '', $m['postcode'] );
    $lat = $coords[ $pc_clean ]['lat'] ?? null;
    $lng = $coords[ $pc_clean ]['lng'] ?? null;

    // Skip if no coordinates (can't show on GPS)
    if ( ! $lat || ! $lng ) {
        $no_coords++;
        continue;
    }

    // Skip if slug already exists (never overwrite active/claimed mosques)
    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $m['slug'] ) );
    if ( $exists ) {
        $skipped++;
        continue;
    }

    $m['latitude']       = $lat;
    $m['longitude']      = $lng;
    $m['timezone']       = 'Europe/London';
    $m['setup_complete'] = 0;

    $wpdb->insert( $table, $m );
    $inserted++;
}

echo "\nDone! Inserted: $inserted, Skipped (existing): $skipped, No coords: $no_coords\n";
echo "Total mosques in DB: " . $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) . "\n";

// Show top cities
$cities = $wpdb->get_results( "SELECT city, COUNT(*) as c FROM $table WHERE latitude IS NOT NULL GROUP BY city ORDER BY c DESC LIMIT 10" );
echo "\nTop cities:\n";
foreach ( $cities as $c ) {
    echo "  " . str_pad( $c->city, 20 ) . " " . $c->c . "\n";
}
