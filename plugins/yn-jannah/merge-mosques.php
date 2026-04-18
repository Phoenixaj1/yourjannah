<?php
/**
 * Merge DFM mosque-contacts.csv into data-mosques.tsv
 * Deduplicates by postcode. Run once to expand the dataset.
 */

// Read existing TSV postcodes
$tsv_file = __DIR__ . '/data-mosques.tsv';
$lines = file($tsv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = array_shift($lines);

$existing_postcodes = [];
foreach ($lines as $line) {
    $cols = str_getcsv($line, "\t");
    if (isset($cols[4])) {
        $pc = strtoupper(str_replace(' ', '', trim($cols[4])));
        $existing_postcodes[$pc] = true;
    }
}
echo "Existing mosques in TSV: " . count($existing_postcodes) . "\n";

// Read DFM CSV
$csv_file = 'C:\Users\user\Documents\yourniyyah\plugins\yn-charity-marketplace\mosque-contacts.csv';
$csv = array_map(function($line) { return str_getcsv($line); }, file($csv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$csv_header = array_shift($csv);

$added = 0;
$skipped = 0;
$fp = fopen($tsv_file, 'a');

foreach ($csv as $row) {
    $data = @array_combine($csv_header, $row);
    if (!$data) continue;

    $postcode = strtoupper(trim($data['postcode'] ?? ''));
    $pc_clean = str_replace(' ', '', $postcode);

    // Skip if already in TSV
    if (isset($existing_postcodes[$pc_clean]) || !$postcode) {
        $skipped++;
        continue;
    }

    // Title case name
    $name = ucwords(strtolower(trim($data['org_name'] ?? '')));
    $name = str_replace(
        [' And ', ' Of ', ' The ', ' For ', ' In ', ' Al-', ' Ul ', '(uk)', '(Uk)', ' Ltd', ' Limited'],
        [' and ', ' of ', ' the ', ' for ', ' in ', ' al-', ' ul ', '(UK)', '(UK)', '', ''],
        $name
    );
    $name = ucfirst(trim($name));

    if (!$name) continue;

    // Generate slug
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)));
    $slug = trim($slug, '-');

    // Map to TSV columns: name, slug, address, city, postcode, phone, email, website, capacity, has_women_section, has_parking, has_wudu, description
    $tsv_row = implode("\t", [
        $name,
        $slug,
        '', // address — will be populated from geocoding
        '', // city — will be populated from geocoding
        $postcode,
        trim($data['contact_phone'] ?? ''),
        trim($data['contact_email'] ?? ''),
        trim($data['org_website'] ?? ''),
        0,  // capacity
        0,  // has_women_section
        0,  // has_parking
        1,  // has_wudu (assume yes for mosques)
        '', // description
    ]);

    fwrite($fp, $tsv_row . "\n");
    $existing_postcodes[$pc_clean] = true;
    $added++;
}

fclose($fp);
echo "Added: $added new mosques from DFM CSV\n";
echo "Skipped: $skipped (duplicate postcode or missing)\n";
echo "Total mosques in TSV now: " . (count($existing_postcodes)) . "\n";
