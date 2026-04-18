<?php
/**
 * Plugin Name: YourJannah — Donations
 * Description: Fundraising campaigns, donations, patron memberships, fund types, DFM webhook, platform sponsorship.
 * Version:     1.0.0
 * Author:      YourNiyyah
 * Requires:    yn-jannah (core — for YNJ_DB, YNJ_Auth, YNJ_Stripe)
 *
 * @package YNJ_Donations
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'YNJ_DONATIONS_VERSION', '1.0.0' );
define( 'YNJ_DONATIONS_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'YNJ_DB' ) ) return;

    if ( ! class_exists( 'YNJ_API_Donations' ) ) {
        require_once YNJ_DONATIONS_DIR . 'api/class-ynj-api-donations.php';
    }
    if ( ! class_exists( 'YNJ_API_Campaigns' ) ) {
        require_once YNJ_DONATIONS_DIR . 'api/class-ynj-api-campaigns.php';
    }
    if ( ! class_exists( 'YNJ_API_Patrons' ) ) {
        require_once YNJ_DONATIONS_DIR . 'api/class-ynj-api-patrons.php';
    }
    if ( ! class_exists( 'YNJ_API_Intentions' ) ) {
        require_once YNJ_DONATIONS_DIR . 'api/class-ynj-api-intentions.php';
    }
    if ( ! class_exists( 'YNJ_API_Sponsor_YJ' ) ) {
        require_once YNJ_DONATIONS_DIR . 'api/class-ynj-api-sponsor-yj.php';
    }
    if ( ! class_exists( 'YNJ_API_DFM_Webhook' ) ) {
        require_once YNJ_DONATIONS_DIR . 'api/class-ynj-api-dfm-webhook.php';
    }

}, 10 );
