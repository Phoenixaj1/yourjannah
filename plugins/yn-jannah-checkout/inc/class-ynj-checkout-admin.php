<?php
/**
 * Checkout Admin — analytics dashboard.
 * @package YNJ_Checkout
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Checkout_Admin {

    public static function init() {
        add_action( 'admin_menu', function() {
            add_menu_page( 'Checkout', 'Checkout', 'manage_options', 'ynj-checkout', [ 'YNJ_Checkout_Admin', 'page' ], 'dashicons-cart', 44 );
        } );
    }

    public static function page() {
        global $wpdb;
        $dt = YNJ_DB::table( 'donations' );

        $total = (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE status = 'succeeded'" );
        $donors = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT donor_email) FROM $dt WHERE status = 'succeeded'" );
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt WHERE status = 'succeeded'" );
        $month = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_pence),0) FROM $dt WHERE status = 'succeeded' AND created_at >= %s", date( 'Y-m-01' ) ) );
        $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $dt WHERE status = 'pending'" );

        echo '<div class="wrap"><h1>💝 Checkout Analytics</h1>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;"><div style="font-size:28px;font-weight:900;color:#287e61;">&pound;' . number_format( $total / 100, 2 ) . '</div><div style="font-size:11px;color:#666;">Total Raised</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;"><div style="font-size:28px;font-weight:900;color:#f59e0b;">&pound;' . number_format( $month / 100, 2 ) . '</div><div style="font-size:11px;color:#666;">This Month</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;"><div style="font-size:28px;font-weight:900;color:#00ADEF;">' . $donors . '</div><div style="font-size:11px;color:#666;">Unique Donors</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;"><div style="font-size:28px;font-weight:900;color:#7c3aed;">' . $count . '</div><div style="font-size:11px;color:#666;">Donations</div></div>';
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center;"><div style="font-size:28px;font-weight:900;color:#dc2626;">' . $pending . '</div><div style="font-size:11px;color:#666;">Pending</div></div>';
        echo '</div>';

        // Recent donations
        $recent = $wpdb->get_results( "SELECT * FROM $dt ORDER BY created_at DESC LIMIT 20" );
        if ( $recent ) {
            echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;">';
            echo '<h2 style="margin-top:0;">Recent Donations</h2>';
            echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Donor</th><th>Amount</th><th>Type</th><th>Status</th></tr></thead><tbody>';
            foreach ( $recent as $d ) {
                $sc = $d->status === 'succeeded' ? '#287e61' : ( $d->status === 'pending' ? '#f59e0b' : '#dc2626' );
                echo '<tr>';
                echo '<td>' . esc_html( $d->created_at ) . '</td>';
                echo '<td>' . esc_html( $d->donor_name ?: $d->donor_email ?: '(anonymous)' ) . '</td>';
                echo '<td style="font-weight:700;">&pound;' . number_format( (int) $d->amount_pence / 100, 2 ) . '</td>';
                echo '<td>' . esc_html( $d->payment_type ?: $d->fund_type ?: 'general' ) . '</td>';
                echo '<td style="color:' . $sc . ';font-weight:600;">' . ucfirst( $d->status ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '</div>';
    }
}
