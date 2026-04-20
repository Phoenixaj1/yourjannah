<?php
/**
 * Prayer Times Scraper — finds PDFs on mosque websites, uses Claude AI to extract times.
 *
 * Flow:
 * 1. Scrape mosque website for PDF links (timetable, prayer, salah)
 * 2. Download the PDF
 * 3. Send to Claude API with structured extraction prompt
 * 4. Parse the JSON response
 * 5. Save prayer times + jumuah times to DB
 *
 * @package YNJ_Prayer_Scraper
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Prayer_Scraper {

    /**
     * Get Claude API key from WP options.
     */
    private static function claude_key() {
        return get_option( 'ynj_claude_api_key', '' );
    }

    /**
     * Step 1: Scrape a mosque's website for timetable PDF links.
     */
    public static function find_timetable_urls( $website_url ) {
        if ( ! $website_url || ! filter_var( $website_url, FILTER_VALIDATE_URL ) ) return [];

        // Normalise URL
        if ( strpos( $website_url, 'http' ) !== 0 ) $website_url = 'https://' . $website_url;

        $response = wp_remote_get( $website_url, [
            'timeout'    => 15,
            'user-agent' => 'YourJannah Prayer Times Bot/1.0',
            'sslverify'  => false,
        ] );

        if ( is_wp_error( $response ) ) return [];
        $html = wp_remote_retrieve_body( $response );
        if ( ! $html ) return [];

        $found = [];

        // Find all links
        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches );
        $links = $matches[1] ?? [];

        // Also check for embedded PDFs
        preg_match_all( '/(?:src|data|href)=["\']([^"\']+\.pdf[^"\']*)["\']/', $html, $pdf_matches );
        $links = array_merge( $links, $pdf_matches[1] ?? [] );

        // Keywords that indicate a prayer timetable
        $keywords = [ 'timetable', 'prayer', 'salah', 'salat', 'namaz', 'jumuah', 'jummah', 'jumu', 'ramadan', 'times' ];

        foreach ( $links as $link ) {
            $link_lower = strtolower( $link );

            // Must be a PDF or link text suggests timetable
            $is_pdf = strpos( $link_lower, '.pdf' ) !== false;
            $has_keyword = false;
            foreach ( $keywords as $kw ) {
                if ( strpos( $link_lower, $kw ) !== false ) { $has_keyword = true; break; }
            }

            if ( $is_pdf || $has_keyword ) {
                // Resolve relative URLs
                if ( strpos( $link, 'http' ) !== 0 ) {
                    $parsed = parse_url( $website_url );
                    $base = $parsed['scheme'] . '://' . $parsed['host'];
                    $link = $base . '/' . ltrim( $link, '/' );
                }
                $found[] = $link;
            }
        }

        // Also check common paths that mosques use
        $common_paths = [ '/timetable', '/prayer-times', '/salah-times', '/timetable.pdf', '/wp-content/uploads/' . date('Y') . '/' ];
        $parsed = parse_url( $website_url );
        $base = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );

        foreach ( $common_paths as $path ) {
            $test_url = $base . $path;
            $head = wp_remote_head( $test_url, [ 'timeout' => 5, 'sslverify' => false ] );
            if ( ! is_wp_error( $head ) && wp_remote_retrieve_response_code( $head ) === 200 ) {
                $found[] = $test_url;
            }
        }

        return array_unique( array_slice( $found, 0, 5 ) ); // Max 5 URLs per mosque
    }

    /**
     * Step 2: Download PDF and convert to base64 for Claude API.
     */
    private static function download_pdf( $url ) {
        $response = wp_remote_get( $url, [
            'timeout'    => 30,
            'user-agent' => 'YourJannah Prayer Times Bot/1.0',
            'sslverify'  => false,
        ] );

        if ( is_wp_error( $response ) ) return null;
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) return null;

        $body = wp_remote_retrieve_body( $response );
        if ( ! $body || strlen( $body ) > 10 * 1024 * 1024 ) return null; // Max 10MB

        return base64_encode( $body );
    }

    /**
     * Step 3: Send PDF to Claude API for extraction.
     */
    public static function extract_with_claude( $pdf_base64, $mosque_name ) {
        $api_key = self::claude_key();
        if ( ! $api_key ) return [ 'error' => 'Claude API key not set. Add ynj_claude_api_key in WP options.' ];

        $today = date( 'Y-m-d' );
        $month = date( 'F Y' );

        $prompt = "Extract prayer times from this mosque timetable PDF for {$mosque_name}.\n\n"
            . "Return ONLY valid JSON with this structure (use 24hr HH:MM format, null if not found):\n"
            . "{\n"
            . "  \"prayer_times\": [\n"
            . "    {\n"
            . "      \"date\": \"YYYY-MM-DD\",\n"
            . "      \"fajr\": \"HH:MM\", \"sunrise\": \"HH:MM\", \"dhuhr\": \"HH:MM\",\n"
            . "      \"asr\": \"HH:MM\", \"maghrib\": \"HH:MM\", \"isha\": \"HH:MM\",\n"
            . "      \"fajr_jamat\": \"HH:MM\", \"dhuhr_jamat\": \"HH:MM\",\n"
            . "      \"asr_jamat\": \"HH:MM\", \"maghrib_jamat\": \"HH:MM\", \"isha_jamat\": \"HH:MM\"\n"
            . "    }\n"
            . "  ],\n"
            . "  \"jumuah\": [\n"
            . "    { \"slot_name\": \"1st Jumu'ah\", \"khutbah_time\": \"HH:MM\", \"salah_time\": \"HH:MM\" }\n"
            . "  ]\n"
            . "}\n\n"
            . "Today is {$today}. Extract times for the current month ({$month}) if available, or whatever dates are shown.\n"
            . "If times are the same for a range of dates, generate a row for each day in that range.\n"
            . "If you see 'Jamat' or 'Iqamah' times, those are the _jamat fields.\n"
            . "If you see 'Start' or 'Begins' times, those are the main prayer times (fajr, dhuhr, etc.).\n"
            . "Return ONLY the JSON, no explanation.";

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 120,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version'  => '2023-06-01',
            ],
            'body' => wp_json_encode( [
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 8000,
                'messages'   => [ [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'document',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => 'application/pdf',
                                'data'       => $pdf_base64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => 'Claude API request failed: ' . $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $body || ! isset( $body['content'][0]['text'] ) ) {
            return [ 'error' => 'Claude API returned unexpected response', 'raw' => $body ];
        }

        $text = $body['content'][0]['text'];

        // Extract JSON from response (Claude sometimes wraps in ```json ... ```)
        if ( preg_match( '/```json\s*(.*?)\s*```/s', $text, $m ) ) {
            $text = $m[1];
        }
        $text = trim( $text );

        $data = json_decode( $text, true );
        if ( ! $data ) {
            return [ 'error' => 'Could not parse JSON from Claude response', 'raw_text' => substr( $text, 0, 500 ) ];
        }

        return $data;
    }

    /**
     * Step 4: Save extracted prayer times to DB.
     */
    public static function save_prayer_times( $mosque_id, $data ) {
        if ( ! $mosque_id || empty( $data['prayer_times'] ) ) return 0;

        global $wpdb;
        $pt = YNJ_DB::table( 'prayer_times' );
        $saved = 0;

        foreach ( $data['prayer_times'] as $row ) {
            if ( empty( $row['date'] ) ) continue;

            $fields = [
                'mosque_id' => $mosque_id,
                'date'      => $row['date'],
                'fajr'      => $row['fajr'] ?? null,
                'sunrise'   => $row['sunrise'] ?? null,
                'dhuhr'     => $row['dhuhr'] ?? null,
                'asr'       => $row['asr'] ?? null,
                'maghrib'   => $row['maghrib'] ?? null,
                'isha'      => $row['isha'] ?? null,
                'fajr_jamat'    => $row['fajr_jamat'] ?? null,
                'dhuhr_jamat'   => $row['dhuhr_jamat'] ?? null,
                'asr_jamat'     => $row['asr_jamat'] ?? null,
                'maghrib_jamat' => $row['maghrib_jamat'] ?? null,
                'isha_jamat'    => $row['isha_jamat'] ?? null,
                'source'    => 'scraper',
            ];

            // Upsert: update if exists, insert if not
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $pt WHERE mosque_id = %d AND date = %s", $mosque_id, $row['date']
            ) );

            if ( $exists ) {
                $wpdb->update( $pt, $fields, [ 'id' => $exists ] );
            } else {
                $wpdb->insert( $pt, $fields );
            }
            $saved++;
        }

        // Save Jumuah times
        if ( ! empty( $data['jumuah'] ) ) {
            $jt = YNJ_DB::table( 'jumuah_times' );
            // Clear existing jumuah for this mosque
            $wpdb->delete( $jt, [ 'mosque_id' => $mosque_id ] );

            foreach ( $data['jumuah'] as $j ) {
                $wpdb->insert( $jt, [
                    'mosque_id'    => $mosque_id,
                    'slot_name'    => sanitize_text_field( $j['slot_name'] ?? 'Jumu\'ah' ),
                    'khutbah_time' => $j['khutbah_time'] ?? null,
                    'salah_time'   => $j['salah_time'] ?? null,
                    'enabled'      => 1,
                ] );
            }
        }

        return $saved;
    }

    /**
     * Full pipeline: scrape → extract → save for one mosque.
     */
    public static function process_mosque( $mosque_id ) {
        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, website FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d", $mosque_id
        ) );

        if ( ! $mosque || ! $mosque->website ) {
            return [ 'ok' => false, 'error' => 'No website URL for this mosque' ];
        }

        // Step 1: Find timetable URLs
        $urls = self::find_timetable_urls( $mosque->website );
        if ( empty( $urls ) ) {
            return [ 'ok' => false, 'error' => 'No timetable PDFs found on ' . $mosque->website, 'urls_checked' => $mosque->website ];
        }

        // Step 2: Try each URL until we get a valid PDF
        $pdf_base64 = null;
        $used_url = '';
        foreach ( $urls as $url ) {
            $pdf_base64 = self::download_pdf( $url );
            if ( $pdf_base64 ) { $used_url = $url; break; }
        }

        if ( ! $pdf_base64 ) {
            return [ 'ok' => false, 'error' => 'Could not download any timetable PDF', 'urls_found' => $urls ];
        }

        // Step 3: Extract with Claude
        $data = self::extract_with_claude( $pdf_base64, $mosque->name );
        if ( isset( $data['error'] ) ) {
            return [ 'ok' => false, 'error' => $data['error'], 'pdf_url' => $used_url ];
        }

        // Step 4: Save to DB
        $saved = self::save_prayer_times( $mosque->id, $data );

        // Mark mosque as scraped
        update_post_meta( 0, 'ynj_scraper_' . $mosque->id, [
            'last_scraped' => current_time( 'mysql' ),
            'pdf_url'      => $used_url,
            'days_saved'   => $saved,
            'jumuah_count' => count( $data['jumuah'] ?? [] ),
        ] );

        return [
            'ok'       => true,
            'mosque'   => $mosque->name,
            'pdf_url'  => $used_url,
            'days'     => $saved,
            'jumuah'   => count( $data['jumuah'] ?? [] ),
        ];
    }

    /**
     * REST: Process a single mosque.
     */
    public static function api_process_mosque( \WP_REST_Request $request ) {
        $mosque_id = absint( $request->get_param( 'mosque_id' ) );
        if ( ! $mosque_id ) return new \WP_REST_Response( [ 'ok' => false, 'error' => 'mosque_id required' ], 400 );

        $result = self::process_mosque( $mosque_id );
        return new \WP_REST_Response( $result );
    }

    /**
     * REST: Start batch processing (returns first batch of mosque IDs to process).
     */
    public static function api_batch_start( \WP_REST_Request $request ) {
        $limit  = min( 50, absint( $request->get_param( 'limit' ) ?: 20 ) );
        $offset = absint( $request->get_param( 'offset' ) ?: 0 );

        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $mt WHERE status = 'active' AND website != ''" );
        $mosques = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, website, city FROM $mt WHERE status = 'active' AND website != '' ORDER BY member_count DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ) ) ?: [];

        return new \WP_REST_Response( [
            'ok'      => true,
            'total'   => $total,
            'offset'  => $offset,
            'limit'   => $limit,
            'mosques' => $mosques,
        ] );
    }

    /**
     * WP Admin page.
     */
    public static function render_admin_page() {
        global $wpdb;
        $mt = YNJ_DB::table( 'mosques' );
        $pt = YNJ_DB::table( 'prayer_times' );

        $total_mosques   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $mt WHERE status = 'active'" );
        $with_website    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $mt WHERE status = 'active' AND website != ''" );
        $with_prayers    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT mosque_id) FROM $pt WHERE source = 'scraper'" );
        $total_days      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt WHERE source = 'scraper'" );
        $has_key         = ! empty( self::claude_key() );
        ?>
        <div class="wrap">
            <h1>🕌 Prayer Times Scraper</h1>
            <p>Automatically scrape mosque websites for timetable PDFs and extract prayer times using Claude AI.</p>

            <?php if ( ! $has_key ) : ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;margin:16px 0;">
                <strong style="color:#dc2626;">⚠️ Claude API Key Required</strong>
                <p style="margin:8px 0 0;color:#666;">Add your Anthropic API key to get started:</p>
                <form method="post" style="margin-top:8px;">
                    <?php wp_nonce_field( 'ynj_save_claude_key' ); ?>
                    <input type="text" name="claude_api_key" placeholder="sk-ant-api03-..." style="width:400px;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                    <button type="submit" name="save_key" class="button button-primary" style="margin-left:8px;">Save Key</button>
                </form>
            </div>
            <?php
            if ( isset( $_POST['save_key'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'ynj_save_claude_key' ) ) {
                update_option( 'ynj_claude_api_key', sanitize_text_field( $_POST['claude_api_key'] ) );
                echo '<script>location.reload();</script>';
            }
            ?>
            <?php endif; ?>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px;text-align:center;">
                    <div style="font-size:28px;font-weight:900;"><?php echo number_format( $total_mosques ); ?></div>
                    <div style="font-size:11px;color:#666;">Total Mosques</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px;text-align:center;">
                    <div style="font-size:28px;font-weight:900;color:#00ADEF;"><?php echo number_format( $with_website ); ?></div>
                    <div style="font-size:11px;color:#666;">Have Website</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px;text-align:center;">
                    <div style="font-size:28px;font-weight:900;color:#16a34a;"><?php echo number_format( $with_prayers ); ?></div>
                    <div style="font-size:11px;color:#666;">Scraped</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px;text-align:center;">
                    <div style="font-size:28px;font-weight:900;"><?php echo number_format( $total_days ); ?></div>
                    <div style="font-size:11px;color:#666;">Prayer Days Saved</div>
                </div>
            </div>

            <?php if ( $has_key ) : ?>
            <!-- Batch Controls -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px;">
                <h3 style="margin:0 0 12px;">Batch Scraper</h3>
                <p style="font-size:13px;color:#666;margin-bottom:16px;">Process mosques that have websites. Each mosque costs ~$0.02 in Claude API usage.</p>
                <div style="display:flex;gap:8px;align-items:center;">
                    <select id="scraper-limit" style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;">
                        <option value="5">5 mosques (test)</option>
                        <option value="20" selected>20 mosques</option>
                        <option value="50">50 mosques</option>
                        <option value="100">100 mosques</option>
                    </select>
                    <button type="button" id="scraper-start" class="button button-primary" onclick="startBatchScrape()" style="padding:8px 20px;">🚀 Start Scraping</button>
                    <span id="scraper-status" style="font-size:13px;color:#666;margin-left:8px;"></span>
                </div>
                <div id="scraper-log" style="margin-top:12px;max-height:400px;overflow-y:auto;font-family:monospace;font-size:12px;background:#f9fafb;border-radius:8px;padding:12px;display:none;"></div>
            </div>

            <!-- Single Mosque Test -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;">
                <h3 style="margin:0 0 12px;">Test Single Mosque</h3>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="number" id="test-mosque-id" placeholder="Mosque ID" style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;width:120px;">
                    <button type="button" class="button" onclick="testSingleMosque()">Test</button>
                    <span id="test-status" style="font-size:13px;color:#666;"></span>
                </div>
                <pre id="test-result" style="margin-top:8px;background:#f9fafb;border-radius:8px;padding:12px;font-size:11px;display:none;max-height:300px;overflow-y:auto;"></pre>
            </div>
            <?php endif; ?>
        </div>

        <script>
        var apiBase = '<?php echo esc_url_raw( rest_url( 'ynj/v1/' ) ); ?>';
        var nonce = '<?php echo wp_create_nonce( 'wp_rest' ); ?>';

        async function testSingleMosque() {
            var id = document.getElementById('test-mosque-id').value;
            if (!id) return;
            document.getElementById('test-status').textContent = 'Processing...';
            document.getElementById('test-result').style.display = 'block';
            document.getElementById('test-result').textContent = 'Scraping website, downloading PDF, sending to Claude AI...';

            var res = await fetch(apiBase + 'scraper/process-mosque', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ mosque_id: parseInt(id) })
            }).then(function(r){ return r.json(); });

            document.getElementById('test-status').textContent = res.ok ? '✅ Done!' : '❌ Failed';
            document.getElementById('test-result').textContent = JSON.stringify(res, null, 2);
        }

        var batchRunning = false;
        async function startBatchScrape() {
            if (batchRunning) return;
            batchRunning = true;
            var limit = parseInt(document.getElementById('scraper-limit').value);
            var btn = document.getElementById('scraper-start');
            var log = document.getElementById('scraper-log');
            var status = document.getElementById('scraper-status');
            btn.disabled = true;
            log.style.display = 'block';
            log.innerHTML = '';

            // Get batch of mosques
            status.textContent = 'Loading mosques...';
            var batch = await fetch(apiBase + 'scraper/batch-start', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ limit: limit, offset: 0 })
            }).then(function(r){ return r.json(); });

            if (!batch.ok || !batch.mosques.length) {
                log.innerHTML += '<div style="color:#dc2626;">No mosques to process.</div>';
                btn.disabled = false; batchRunning = false;
                return;
            }

            log.innerHTML += '<div>Found ' + batch.total + ' mosques with websites. Processing ' + batch.mosques.length + '...</div>';
            var success = 0, failed = 0;

            for (var i = 0; i < batch.mosques.length; i++) {
                var m = batch.mosques[i];
                status.textContent = (i+1) + '/' + batch.mosques.length + ' — ' + m.name;
                log.innerHTML += '<div style="color:#999;">[' + (i+1) + '] ' + m.name + ' (' + m.website + ') ...</div>';
                log.scrollTop = log.scrollHeight;

                try {
                    var res = await fetch(apiBase + 'scraper/process-mosque', {
                        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                        body: JSON.stringify({ mosque_id: m.id })
                    }).then(function(r){ return r.json(); });

                    if (res.ok) {
                        success++;
                        log.innerHTML += '<div style="color:#16a34a;">  ✅ ' + res.days + ' days saved, ' + res.jumuah + ' jumuah slots — ' + res.pdf_url + '</div>';
                    } else {
                        failed++;
                        log.innerHTML += '<div style="color:#d97706;">  ⚠️ ' + (res.error || 'Unknown error') + '</div>';
                    }
                } catch(e) {
                    failed++;
                    log.innerHTML += '<div style="color:#dc2626;">  ❌ Network error</div>';
                }
                log.scrollTop = log.scrollHeight;
            }

            status.textContent = 'Done! ✅ ' + success + ' succeeded, ⚠️ ' + failed + ' failed';
            btn.disabled = false; batchRunning = false;
        }
        </script>
        <?php
    }
}
