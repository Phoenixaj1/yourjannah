<?php
/**
 * YNJ_UI — Reusable UI components rendered by the plugin.
 *
 * Call from any template. Changes here apply sitewide.
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_UI {

    /**
     * Render the Patron Membership bar.
     *
     * @param string $slug       Mosque slug
     * @param string $mosque_name Mosque display name
     * @param object|null $patron_status Current user's patron status (or null)
     */
    public static function render_patron_bar( $slug, $mosque_name, $patron_status = null ) {
        $patron_tiers = class_exists( 'YNJ_API_Patrons' ) ? YNJ_API_Patrons::get_tiers() : [];
        $patron_url = home_url( '/mosque/' . $slug . '/patron' );
        $is_logged_in = is_user_logged_in();

        if ( $patron_status ) {
            $tier_label = $patron_tiers[ $patron_status->tier ]['label'] ?? ucfirst( $patron_status->tier ?? 'supporter' );
            echo '<div class="ynj-patron-bar" id="patron-hero" style="background:linear-gradient(135deg,#287e61,#1a5c43) !important;">';
            echo '<a href="' . esc_url( $patron_url ) . '" class="ynj-patron-bar__label">🏅 <strong>' . sprintf( esc_html__( "You're a %s Patron — JazakAllah Khayr", 'yourjannah' ), esc_html( $tier_label ) ) . '</strong></a>';
            echo '<a href="' . esc_url( $patron_url ) . '" class="ynj-patron-chip" style="background:rgba(255,255,255,.2);">' . esc_html__( 'Manage', 'yourjannah' ) . '</a>';
            echo '</div>';
        } else {
            $tiers_list = [
                'supporter' => [ 500,  'Bronze', '£5' ],
                'guardian'  => [ 1000, 'Silver', '£10' ],
                'champion'  => [ 2000, 'Gold',   '£20' ],
                'platinum'  => [ 5000, 'Platinum','£50' ],
            ];
            echo '<div class="ynj-patron-bar" id="patron-hero">';
            echo '<span class="ynj-patron-bar__label">🏅 <strong id="patron-bar-text">' . sprintf( esc_html__( 'Become a Patron of %s', 'yourjannah' ), esc_html( $mosque_name ) ) . '</strong></span>';
            echo '<div class="ynj-patron-bar__tiers">';
            foreach ( $tiers_list as $tk => $tp ) {
                $pop_class = $tk === 'champion' ? ' ynj-patron-chip--popular' : '';
                $pop_badge = $tk === 'champion' ? '<span class="ynj-patron-chip__pop">' . esc_html__( 'Popular', 'yourjannah' ) . '</span>' : '';
                if ( $is_logged_in ) {
                    $onclick = "if(typeof ynjNiyyahBarOpen==='function')ynjNiyyahBarOpen({mode:'patron',item_type:'patron',icon:'🏅',amount_pence:" . $tp[0] . ",item_label:'" . esc_js( $tp[1] . ' Patron — ' . $mosque_name ) . "',frequency:'monthly',meta:{tier:'" . esc_js( $tk ) . "'}})";
                } else {
                    $onclick = "if(typeof ynjAuthModalOpen==='function')ynjAuthModalOpen({mosque_slug:'" . esc_js( $slug ) . "',mosque_name:'" . esc_js( $mosque_name ) . "'})";
                }
                echo '<button type="button" class="ynj-patron-chip' . $pop_class . '" onclick="' . esc_attr( $onclick ) . '">' . $pop_badge . $tp[2] . '</button>';
            }
            echo '</div></div>';
        }
    }

    /**
     * Render the Sponsor Ticker.
     */
    public static function render_sponsor_ticker() {
        ?>
        <div class="ynj-ticker" id="sponsor-ticker" style="display:none;">
            <span class="ynj-ticker__label">⭐ <?php esc_html_e( 'Sponsors', 'yourjannah' ); ?></span>
            <div class="ynj-ticker__track">
                <div class="ynj-ticker__slide" id="ticker-content"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Admin Toolbar (floating bottom-left pill).
     *
     * @param string $mosque_slug Mosque slug for "New Post" link
     */
    /**
     * @param string $mosque_slug
     * @param string $new_post_onclick Optional JS onclick for "New Post" (e.g. open modal). If empty, links to mosque page.
     */
    public static function render_admin_toolbar( $mosque_slug, $new_post_onclick = '' ) {
        if ( ! current_user_can( 'edit_posts' ) ) return;
        $menu_id = 'ynj-admin-menu-' . wp_unique_id();
        ?>
        <style>
        .ynj-admin-toolbar{position:fixed;right:0;top:50%;transform:translateY(-50%);display:flex;flex-direction:column;gap:4px;padding:6px;background:#fff;border:1px solid #e5e7eb;border-radius:14px 0 0 14px;box-shadow:-2px 0 16px rgba(0,0,0,.1);z-index:900;}
        .ynj-admin-toolbar a,.ynj-admin-toolbar button{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-size:16px;text-decoration:none;border:none;cursor:pointer;transition:all .15s;padding:0;}
        .ynj-admin-toolbar a:hover,.ynj-admin-toolbar button:hover{background:#f0fdf4;transform:scale(1.1);}
        .ynj-atb-primary{background:#287e61;color:#fff;}
        .ynj-atb-primary:hover{background:#1a5c43 !important;}
        .ynj-atb-outline{background:#f9fafb;color:#333;border:1px solid #e5e7eb;}
        .ynj-atb-bug{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
        .ynj-atb-bug:hover{background:#fee2e2 !important;}
        </style>
        <div class="ynj-admin-toolbar" title="Admin Tools">
            <?php if ( $new_post_onclick ) : ?>
            <button type="button" onclick="<?php echo esc_attr( $new_post_onclick ); ?>" class="ynj-atb-primary" title="New Post">📢</button>
            <?php else : ?>
            <a href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug ) ); ?>" class="ynj-atb-primary" title="New Post">📢</a>
            <?php endif; ?>
            <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" class="ynj-atb-outline" title="Dashboard">📊</a>
            <button type="button" onclick="var m=document.getElementById('<?php echo esc_js( $menu_id ); ?>');m.style.display=m.style.display==='block'?'none':'block'" class="ynj-atb-outline" title="Quick Menu">⚡</button>
            <button type="button" onclick="ynjBugReportOpen()" class="ynj-atb-bug" title="Report a Bug">🐛</button>
        </div>
        <div id="<?php echo esc_attr( $menu_id ); ?>" style="display:none;position:fixed;right:56px;top:50%;transform:translateY(-50%);background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);padding:12px;z-index:901;width:280px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <a href="<?php echo esc_url( home_url( '/dashboard/announcements' ) ); ?>" style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:12px;font-weight:600;">📢 Posts</a>
                <a href="<?php echo esc_url( home_url( '/dashboard/events' ) ); ?>" style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:12px;font-weight:600;">📅 Events</a>
                <a href="<?php echo esc_url( home_url( '/dashboard/classes' ) ); ?>" style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:12px;font-weight:600;">🎓 Classes</a>
                <a href="<?php echo esc_url( home_url( '/dashboard/sponsors' ) ); ?>" style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:12px;font-weight:600;">⭐ Sponsors</a>
                <a href="<?php echo esc_url( home_url( '/dashboard/patrons' ) ); ?>" style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:12px;font-weight:600;">🏅 Patrons</a>
                <a href="<?php echo esc_url( home_url( '/dashboard/funds' ) ); ?>" style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;text-decoration:none;color:#333;font-size:12px;font-weight:600;">💰 Funds</a>
            </div>
        </div>
        <script>
        document.addEventListener('click', function(e) {
            var menu = document.getElementById('<?php echo esc_js( $menu_id ); ?>');
            if (menu && menu.style.display === 'block' && !menu.contains(e.target) && !e.target.closest('.ynj-admin-toolbar')) {
                menu.style.display = 'none';
            }
        });
        </script>

        <!-- Bug Report Popup -->
        <div id="ynj-bug-popup" style="display:none;position:fixed;inset:0;z-index:10005;background:rgba(10,22,40,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)ynjBugReportClose()">
            <div style="background:#fff;border-radius:20px;padding:24px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:ynj-popup-in .3s ease-out;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="font-size:18px;font-weight:800;color:#1a1a2e;margin:0;">🐛 Report a Bug</h3>
                    <button onclick="ynjBugReportClose()" style="background:none;border:none;font-size:20px;color:#999;cursor:pointer;">&times;</button>
                </div>
                <p style="font-size:12px;color:#666;margin-bottom:14px;">Describe what went wrong and we'll fix it.</p>
                <textarea id="ynj-bug-desc" rows="4" placeholder="What happened? What did you expect?" style="width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:12px;font-size:14px;font-family:inherit;resize:vertical;margin-bottom:10px;box-sizing:border-box;"></textarea>
                <select id="ynj-bug-severity" style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;font-size:13px;font-family:inherit;margin-bottom:14px;box-sizing:border-box;">
                    <option value="low">Low — cosmetic / minor</option>
                    <option value="medium" selected>Medium — something doesn't work right</option>
                    <option value="high">High — can't use a feature</option>
                    <option value="critical">Critical — payments / data affected</option>
                </select>
                <button type="button" id="ynj-bug-submit" onclick="ynjBugReportSend()" style="width:100%;padding:14px;background:#dc2626;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;">Submit Bug Report</button>
                <div id="ynj-bug-msg" style="font-size:12px;text-align:center;margin-top:8px;"></div>
            </div>
        </div>
        <script>
        window.ynjBugReportOpen = function(){
            document.getElementById('ynj-bug-popup').style.display='flex';
            document.getElementById('ynj-bug-desc').value='';
            document.getElementById('ynj-bug-msg').textContent='';
        };
        window.ynjBugReportClose = function(){ document.getElementById('ynj-bug-popup').style.display='none'; };
        window.ynjBugReportSend = function(){
            var desc = document.getElementById('ynj-bug-desc').value.trim();
            if(!desc){document.getElementById('ynj-bug-msg').textContent='Please describe the issue.';return;}
            var btn = document.getElementById('ynj-bug-submit');
            btn.disabled=true; btn.textContent='Sending...';
            var nonce = typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '';
            fetch('/wp-json/ynj/v1/bug-report', {
                method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}, credentials:'same-origin',
                body:JSON.stringify({ description:desc, severity:document.getElementById('ynj-bug-severity').value, page_url:window.location.href, user_agent:navigator.userAgent })
            }).then(function(r){return r.json();}).then(function(d){
                if(d.ok){
                    document.getElementById('ynj-bug-msg').innerHTML='<span style="color:#16a34a;">✅ Bug reported — JazakAllah Khayr!</span>';
                    btn.textContent='Sent!';
                    setTimeout(ynjBugReportClose,1500);
                } else {
                    document.getElementById('ynj-bug-msg').innerHTML='<span style="color:#dc2626;">'+(d.error||'Failed')+'</span>';
                    btn.disabled=false; btn.textContent='Submit Bug Report';
                }
            }).catch(function(){ document.getElementById('ynj-bug-msg').innerHTML='<span style="color:#dc2626;">Network error</span>'; btn.disabled=false; btn.textContent='Submit Bug Report'; });
        };
        </script>
        <?php
    }

    /**
     * Register bug report REST endpoint.
     */
    public static function register_bug_report_api() {
        register_rest_route( 'ynj/v1', '/bug-report', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_bug_report' ],
            'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
    }

    public static function handle_bug_report( \WP_REST_Request $request ) {
        $d = $request->get_json_params();
        $desc     = sanitize_textarea_field( $d['description'] ?? '' );
        $severity = sanitize_text_field( $d['severity'] ?? 'medium' );
        $page_url = esc_url_raw( $d['page_url'] ?? '' );
        $ua       = sanitize_text_field( $d['user_agent'] ?? '' );

        if ( ! $desc ) {
            return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Description required.' ], 400 );
        }

        global $wpdb;
        $user = wp_get_current_user();

        // Store as a WP option (simple — no new table needed)
        $bugs = get_option( 'ynj_bug_reports', [] );
        $bugs[] = [
            'id'          => count( $bugs ) + 1,
            'description' => $desc,
            'severity'    => $severity,
            'page_url'    => $page_url,
            'user_agent'  => $ua,
            'user_name'   => $user->display_name,
            'user_email'  => $user->user_email,
            'status'      => 'open',
            'created_at'  => current_time( 'mysql' ),
        ];
        update_option( 'ynj_bug_reports', $bugs );

        return new \WP_REST_Response( [ 'ok' => true ] );
    }
}
