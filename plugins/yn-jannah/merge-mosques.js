const fs = require('fs');
const path = require('path');

// Read existing TSV
const tsvFile = path.join(__dirname, 'data-mosques.tsv');
const tsvLines = fs.readFileSync(tsvFile, 'utf8').split('\n').filter(l => l.trim());
const header = tsvLines.shift();

const existingPostcodes = new Set();
tsvLines.forEach(line => {
    const cols = line.split('\t');
    if (cols[4]) existingPostcodes.add(cols[4].replace(/\s/g,'').toUpperCase());
});
console.log('Existing mosques in TSV:', existingPostcodes.size);

// Read DFM CSV
const csvFile = path.resolve('C:/Users/user/Documents/yourniyyah/plugins/yn-charity-marketplace/mosque-contacts.csv');
const csvContent = fs.readFileSync(csvFile, 'utf8');
const csvLines2 = csvContent.split('\n').filter(l => l.trim());
const csvHeader = csvLines2.shift().split(',').map(h => h.replace(/"/g,'').trim());

let added = 0, skipped = 0;
const newLines = [];

csvLines2.forEach(line => {
    // Simple CSV parse (handles quoted fields)
    const cols = [];
    let inQuote = false, current = '';
    for (let i = 0; i < line.length; i++) {
        if (line[i] === '"') { inQuote = !inQuote; continue; }
        if (line[i] === ',' && !inQuote) { cols.push(current.trim()); current = ''; continue; }
        current += line[i];
    }
    cols.push(current.trim());

    const data = {};
    csvHeader.forEach((h, i) => { data[h] = cols[i] || ''; });

    const postcode = (data.postcode || '').toUpperCase().trim();
    const pcClean = postcode.replace(/\s/g, '');
    if (!postcode || existingPostcodes.has(pcClean)) { skipped++; return; }

    // Title case name
    let name = (data.org_name || '').toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
    name = name.replace(/ And /g, ' and ').replace(/ Of /g, ' of ').replace(/ The /g, ' the ')
               .replace(/ For /g, ' for ').replace(/ In /g, ' in ')
               .replace(/ Ltd$/gi, '').replace(/ Limited$/gi, '').trim();
    if (!name) return;

    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    const phone = (data.contact_phone || '').trim();
    const email = (data.contact_email || '').trim();
    const website = (data.org_website || '').trim();

    // TSV: name slug address city postcode phone email website capacity has_women_section has_parking has_wudu description
    newLines.push([name, slug, '', '', postcode, phone, email, website, '0', '0', '0', '1', ''].join('\t'));
    existingPostcodes.add(pcClean);
    added++;
});

// Append to TSV
if (newLines.length) {
    fs.appendFileSync(tsvFile, newLines.join('\n') + '\n');
}

console.log('Added:', added, 'new mosques from DFM CSV');
console.log('Skipped:', skipped, '(duplicate postcode or missing)');
console.log('Total mosques in TSV now:', existingPostcodes.size);
