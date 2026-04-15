<?php
/**
 * YNJ_Theme_Admin — WordPress Admin settings page for YourJannah.
 *
 * Provides a centralized settings UI in WP Admin for:
 * - Branding (logo, colors)
 * - Prayer settings (calculation method)
 * - Tracking (Meta Pixel, Google Analytics)
 * - Social links
 * - Footer content
 *
 * Pattern: Same as YourNiyyah's YN_Admin class.
 * Settings stored in single option: get_option('yourjannah_settings')
 *
 * @package YourJannah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Theme_Admin {

    /** Option key for all settings. */
    private static $option_key = 'yourjannah_settings';

    /** Default values. */
    private static $defaults = [
        // Branding
        'logo_height'        => 48,
        'logo_height_mobile' => 36,
        'header_gradient'    => 'linear-gradient(160deg,#0a1628 0%,#122a4a 35%,#0e4d7a 65%,#00ADEF 100%)',
        'accent_color'       => '#00ADEF',

        // Prayer
        'prayer_method'      => '2', // ISNA
        'aladhan_school'     => '0', // Shafi

        // Tracking
        'meta_pixel_id'      => '',
        'google_analytics'   => '',

        // Social
        'social_instagram'   => '',
        'social_facebook'    => '',
        'social_twitter'     => '',
        'social_youtube'     => '',
        'social_whatsapp'    => '',
        'social_tiktok'      => '',

        // Footer
        'footer_about'       => 'YourJannah connects Muslim communities with their local mosques. Prayer times, events, classes, and more.',
        'footer_copyright'   => '© {year} YourJannah. All rights reserved.',

        // Hadith
        'hadith_1'           => 'Prayer in congregation is twenty-seven times more virtuous than prayer offered alone.',
        'hadith_1_source'    => 'Sahih al-Bukhari 645',
        'hadith_2'           => 'The closest a servant is to his Lord is when he is in prostration.',
        'hadith_2_source'    => 'Sahih Muslim 482',
        'hadith_3'           => 'Whoever builds a mosque for Allah, Allah will build for him a house in Paradise.',
        'hadith_3_source'    => 'Sahih al-Bukhari 450',
    ];

    /**
     * Get a setting value.
     */
    public static function get( $key ) {
        $opts = get_option( self::$option_key, [] );
        $all  = wp_parse_args( $opts, self::$defaults );
        return $all[ $key ] ?? '';
    }

    /**
     * Get all settings.
     */
    public static function get_all() {
        $opts = get_option( self::$option_key, [] );
        return wp_parse_args( $opts, self::$defaults );
    }

    /**
     * Register the admin menu.
     */
    public static function register() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
    }

    /**
     * Add menu page.
     */
    public static function add_menu() {
        add_menu_page(
            'YourJannah Settings',
            'YourJannah',
            'manage_options',
            'yourjannah-settings',
            [ __CLASS__, 'render_page' ],
            'dashicons-building',
            3
        );
    }

    /**
     * Handle form save.
     */
    public static function handle_save() {
        if ( ! isset( $_POST['ynj_settings_save'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_settings_save' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $data = [];
        foreach ( self::$defaults as $key => $default ) {
            if ( isset( $_POST[ $key ] ) ) {
                if ( in_array( $key, [ 'footer_about', 'footer_copyright' ], true ) ) {
                    $data[ $key ] = wp_kses_post( $_POST[ $key ] );
                } elseif ( is_numeric( $default ) ) {
                    $data[ $key ] = absint( $_POST[ $key ] );
                } else {
                    $data[ $key ] = sanitize_text_field( $_POST[ $key ] );
                }
            }
        }

        update_option( self::$option_key, $data );
        add_settings_error( 'ynj_settings', 'saved', 'Settings saved.', 'success' );
    }

    /**
     * Render settings page.
     */
    public static function render_page() {
        $s = self::get_all();
        $tab = sanitize_text_field( $_GET['tab'] ?? 'branding' );
        $tabs = [
            'branding' => 'Branding',
            'prayer'   => 'Prayer',
            'tracking' => 'Tracking',
            'social'   => 'Social',
            'footer'   => 'Footer',
            'hadith'   => 'Hadith',
        ];
        ?>
        <div class="wrap">
            <h1>🕌 YourJannah Settings</h1>
            <?php settings_errors( 'ynj_settings' ); ?>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a href="?page=yourjannah-settings&tab=<?php echo esc_attr( $key ); ?>"
                       class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" style="max-width:700px;margin-top:20px;">
                <?php wp_nonce_field( 'ynj_settings_save', '_ynj_nonce' ); ?>
                <input type="hidden" name="ynj_settings_save" value="1">

                <table class="form-table">
                <?php if ( $tab === 'branding' ) : ?>
                    <tr><th>Logo Height (px)</th><td><input type="number" name="logo_height" value="<?php echo esc_attr( $s['logo_height'] ); ?>" class="small-text"></td></tr>
                    <tr><th>Logo Height Mobile (px)</th><td><input type="number" name="logo_height_mobile" value="<?php echo esc_attr( $s['logo_height_mobile'] ); ?>" class="small-text"></td></tr>
                    <tr><th>Accent Color</th><td><input type="text" name="accent_color" value="<?php echo esc_attr( $s['accent_color'] ); ?>" class="regular-text" placeholder="#00ADEF"></td></tr>
                    <tr><th>Header Gradient CSS</th><td><input type="text" name="header_gradient" value="<?php echo esc_attr( $s['header_gradient'] ); ?>" class="large-text"></td></tr>

                <?php elseif ( $tab === 'prayer' ) : ?>
                    <tr><th>Calculation Method</th><td>
                        <select name="prayer_method">
                            <option value="2" <?php selected( $s['prayer_method'], '2' ); ?>>ISNA</option>
                            <option value="1" <?php selected( $s['prayer_method'], '1' ); ?>>University of Islamic Sciences, Karachi</option>
                            <option value="3" <?php selected( $s['prayer_method'], '3' ); ?>>Muslim World League</option>
                            <option value="4" <?php selected( $s['prayer_method'], '4' ); ?>>Umm al-Qura</option>
                            <option value="5" <?php selected( $s['prayer_method'], '5' ); ?>>Egyptian General Authority</option>
                            <option value="15" <?php selected( $s['prayer_method'], '15' ); ?>>Moonsighting Committee (Recommended UK)</option>
                        </select>
                    </td></tr>
                    <tr><th>Juristic School</th><td>
                        <select name="aladhan_school">
                            <option value="0" <?php selected( $s['aladhan_school'], '0' ); ?>>Shafi'i</option>
                            <option value="1" <?php selected( $s['aladhan_school'], '1' ); ?>>Hanafi</option>
                        </select>
                    </td></tr>

                <?php elseif ( $tab === 'tracking' ) : ?>
                    <tr><th>Meta Pixel ID</th><td><input type="text" name="meta_pixel_id" value="<?php echo esc_attr( $s['meta_pixel_id'] ); ?>" class="regular-text" placeholder="123456789"></td></tr>
                    <tr><th>Google Analytics Tag</th><td><input type="text" name="google_analytics" value="<?php echo esc_attr( $s['google_analytics'] ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX"></td></tr>

                <?php elseif ( $tab === 'social' ) : ?>
                    <?php foreach ( [ 'instagram', 'facebook', 'twitter', 'youtube', 'whatsapp', 'tiktok' ] as $platform ) : ?>
                    <tr><th><?php echo ucfirst( $platform ); ?></th><td><input type="url" name="social_<?php echo $platform; ?>" value="<?php echo esc_attr( $s[ "social_$platform" ] ); ?>" class="large-text" placeholder="https://"></td></tr>
                    <?php endforeach; ?>

                <?php elseif ( $tab === 'footer' ) : ?>
                    <tr><th>About Text</th><td><textarea name="footer_about" rows="3" class="large-text"><?php echo esc_textarea( $s['footer_about'] ); ?></textarea></td></tr>
                    <tr><th>Copyright</th><td><input type="text" name="footer_copyright" value="<?php echo esc_attr( $s['footer_copyright'] ); ?>" class="large-text"><p class="description">Use {year} for dynamic year.</p></td></tr>

                <?php elseif ( $tab === 'hadith' ) : ?>
                    <?php for ( $i = 1; $i <= 3; $i++ ) : ?>
                    <tr><th>Hadith <?php echo $i; ?></th><td><textarea name="hadith_<?php echo $i; ?>" rows="2" class="large-text"><?php echo esc_textarea( $s[ "hadith_$i" ] ); ?></textarea></td></tr>
                    <tr><th>Source <?php echo $i; ?></th><td><input type="text" name="hadith_<?php echo $i; ?>_source" value="<?php echo esc_attr( $s[ "hadith_{$i}_source" ] ); ?>" class="regular-text"></td></tr>
                    <?php endfor; ?>
                <?php endif; ?>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get a random hadith.
     */
    public static function get_random_hadith() {
        $s = self::get_all();
        $hadiths = [];
        for ( $i = 1; $i <= 3; $i++ ) {
            if ( ! empty( $s[ "hadith_$i" ] ) ) {
                $hadiths[] = [
                    'text'   => $s[ "hadith_$i" ],
                    'source' => $s[ "hadith_{$i}_source" ] ?? '',
                ];
            }
        }
        return $hadiths ? $hadiths[ array_rand( $hadiths ) ] : [ 'text' => '', 'source' => '' ];
    }
}
