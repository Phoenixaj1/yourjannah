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

        if ( $patron_status ) : ?>
        <div class="ynj-patron-bar" id="patron-hero" style="background:linear-gradient(135deg,#287e61,#1a5c43) !important;">
            <a href="<?php echo esc_url( $patron_url ); ?>" class="ynj-patron-bar__label">🏅 <strong><?php printf( esc_html__( "You're a %s Patron — JazakAllah Khayr", 'yourjannah' ), esc_html( $patron_tiers[ $patron_status->tier ]['label'] ?? ucfirst( $patron_status->tier ) ) ); ?></strong></a>
            <a href="<?php echo esc_url( $patron_url ); ?>" class="ynj-patron-chip" style="background:rgba(255,255,255,.2);"><?php esc_html_e( 'Manage', 'yourjannah' ); ?></a>
        </div>
        <?php else : ?>
        <div class="ynj-patron-bar" id="patron-hero">
            <a href="<?php echo esc_url( $patron_url ); ?>" class="ynj-patron-bar__label">🏅 <strong id="patron-bar-text"><?php printf( esc_html__( 'Become a Patron of %s', 'yourjannah' ), esc_html( $mosque_name ) ); ?></strong></a>
            <div class="ynj-patron-bar__tiers">
                <a href="<?php echo esc_url( $patron_url ); ?>" class="ynj-patron-chip">£5</a>
                <a href="<?php echo esc_url( $patron_url ); ?>" class="ynj-patron-chip">£10</a>
                <a href="<?php echo esc_url( $patron_url ); ?>" class="ynj-patron-chip ynj-patron-chip--popular"><span class="ynj-patron-chip__pop"><?php esc_html_e( 'Popular', 'yourjannah' ); ?></span>£20</a>
                <a href="<?php echo esc_url( $patron_url ); ?>" class="ynj-patron-chip">£50</a>
            </div>
        </div>
        <?php endif;
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
    public static function render_admin_toolbar( $mosque_slug ) {
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
            <a href="<?php echo esc_url( home_url( '/mosque/' . $mosque_slug ) ); ?>" class="ynj-atb-primary" title="New Post">📢</a>
            <a href="<?php echo esc_url( home_url( '/dashboard' ) ); ?>" class="ynj-atb-outline" title="Dashboard">📊</a>
            <button type="button" onclick="var m=document.getElementById('<?php echo esc_js( $menu_id ); ?>');m.style.display=m.style.display==='block'?'none':'block'" class="ynj-atb-outline" title="Quick Menu">⚡</button>
            <a href="mailto:bugs@yourjannah.com?subject=Bug%20Report%20-%20<?php echo esc_attr( $mosque_slug ); ?>&body=Page:%20<?php echo esc_attr( home_url( $_SERVER['REQUEST_URI'] ?? '/' ) ); ?>%0A%0ADescribe the issue:%0A" class="ynj-atb-bug" title="Report a Bug">🐛</a>
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
        <?php
    }
}
