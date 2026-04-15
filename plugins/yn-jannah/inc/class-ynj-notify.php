<?php
/**
 * YNJ_Notify — Email notifications for mosque admins.
 *
 * Listens to do_action() hooks and sends email via wp_mail().
 *
 * @package YourJannah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Notify {

    /**
     * Get the admin email for a mosque.
     */
    private static function get_admin_email( $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT admin_email FROM $table WHERE id = %d", $mosque_id
        ) );
    }

    /**
     * Get mosque name.
     */
    private static function get_mosque_name( $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM $table WHERE id = %d", $mosque_id
        ) ) ?: 'Your Mosque';
    }

    /**
     * Send an HTML email to the mosque admin.
     */
    private static function send( $mosque_id, $subject, $body_html ) {
        $to = self::get_admin_email( $mosque_id );
        if ( ! $to || ! is_email( $to ) ) return;

        $mosque_name = self::get_mosque_name( $mosque_id );
        $full_subject = $subject . ' — ' . $mosque_name;

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Inter,system-ui,sans-serif;color:#1a1a1a;max-width:600px;margin:0 auto;padding:20px;">'
            . '<div style="background:linear-gradient(135deg,#0a1628,#00ADEF);color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center;">'
            . '<h2 style="margin:0;font-size:18px;">🕌 YourJannah</h2>'
            . '<p style="margin:4px 0 0;opacity:.8;font-size:13px;">' . esc_html( $mosque_name ) . '</p></div>'
            . '<div style="background:#fff;border:1px solid #e5e5e5;border-top:none;padding:24px;border-radius:0 0 12px 12px;">'
            . $body_html
            . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
            . '<p style="font-size:12px;color:#999;text-align:center;">Manage your mosque at <a href="https://yourjannah.com/dashboard" style="color:#00ADEF;">yourjannah.com/dashboard</a></p>'
            . '</div></body></html>';

        add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
        wp_mail( $to, $full_subject, $html );
        remove_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
    }

    // ================================================================
    // HOOK HANDLERS
    // ================================================================

    /**
     * New enquiry submitted.
     * do_action( 'ynj_new_enquiry', $mosque_id, $enquiry_data )
     */
    public static function on_enquiry( $mosque_id, $data ) {
        $name    = esc_html( $data['name'] ?? 'Someone' );
        $email   = esc_html( $data['email'] ?? '' );
        $subject = esc_html( $data['subject'] ?? 'No subject' );
        $message = nl2br( esc_html( $data['message'] ?? '' ) );
        $type    = esc_html( $data['type'] ?? 'general' );

        self::send( $mosque_id, "New enquiry from {$name}", "
            <h3 style='margin:0 0 12px;'>New Enquiry</h3>
            <table style='font-size:14px;width:100%;'>
                <tr><td style='padding:6px 0;color:#666;width:100px;'>From:</td><td><strong>{$name}</strong> ({$email})</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Type:</td><td>{$type}</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Subject:</td><td>{$subject}</td></tr>
            </table>
            <div style='background:#f9fafb;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;'>{$message}</div>
            <a href='https://yourjannah.com/dashboard#/enquiries' style='display:inline-block;background:#00ADEF;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;'>View in Dashboard</a>
        " );
    }

    /**
     * New booking created.
     * do_action( 'ynj_new_booking', $mosque_id, $booking_data )
     */
    public static function on_booking( $mosque_id, $data ) {
        $name = esc_html( $data['user_name'] ?? 'Someone' );
        $type = $data['event_id'] ? 'Event Booking' : 'Room Booking';
        $date = esc_html( $data['booking_date'] ?? '' );

        self::send( $mosque_id, "New {$type} from {$name}", "
            <h3 style='margin:0 0 12px;'>New {$type}</h3>
            <table style='font-size:14px;width:100%;'>
                <tr><td style='padding:6px 0;color:#666;width:100px;'>Guest:</td><td><strong>{$name}</strong></td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Email:</td><td>" . esc_html( $data['user_email'] ?? '' ) . "</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Date:</td><td>{$date}</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Time:</td><td>" . esc_html( $data['start_time'] ?? '' ) . " — " . esc_html( $data['end_time'] ?? '' ) . "</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Notes:</td><td>" . esc_html( $data['notes'] ?? '' ) . "</td></tr>
            </table>
            <a href='https://yourjannah.com/dashboard#/bookings' style='display:inline-block;background:#00ADEF;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;margin-top:16px;'>View in Dashboard</a>
        " );
    }

    /**
     * Booking status changed — notify the GUEST.
     * do_action( 'ynj_booking_status_changed', $mosque_id, $booking_data )
     */
    public static function on_booking_status_changed( $mosque_id, $data ) {
        $guest_email = $data['user_email'] ?? '';
        if ( ! $guest_email || ! is_email( $guest_email ) ) return;

        $name    = esc_html( $data['user_name'] ?? 'Guest' );
        $status  = $data['status'] ?? '';
        $notes   = esc_html( $data['notes'] ?? '' );
        $date    = esc_html( $data['booking_date'] ?? '' );

        $mosque_name = self::get_mosque_name( $mosque_id );

        if ( $status === 'confirmed' ) {
            $subject = "Booking Confirmed — {$mosque_name}";
            $body_html = "
                <h3 style='margin:0 0 12px;color:#16a34a;'>Booking Confirmed ✅</h3>
                <p>Assalamu Alaikum {$name},</p>
                <p>Your booking at <strong>{$mosque_name}</strong> has been confirmed.</p>
                <table style='font-size:14px;width:100%;'>
                    <tr><td style='padding:6px 0;color:#666;'>Date:</td><td><strong>{$date}</strong></td></tr>
                    " . ( $notes ? "<tr><td style='padding:6px 0;color:#666;'>Notes:</td><td>{$notes}</td></tr>" : '' ) . "
                </table>
                <p style='margin-top:16px;font-size:13px;color:#666;'>JazakAllahu Khairan for using YourJannah.</p>
            ";
        } elseif ( $status === 'cancelled' ) {
            $subject = "Booking Update — {$mosque_name}";
            $body_html = "
                <h3 style='margin:0 0 12px;color:#dc2626;'>Booking Not Approved</h3>
                <p>Assalamu Alaikum {$name},</p>
                <p>Unfortunately your booking at <strong>{$mosque_name}</strong> for {$date} could not be approved.</p>
                " . ( $notes ? "<p style='margin-top:8px;'><strong>Reason:</strong> {$notes}</p>" : '' ) . "
                <p style='margin-top:16px;font-size:13px;color:#666;'>Please contact the mosque for more information.</p>
            ";
        } else {
            return;
        }

        // Send to guest
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Inter,system-ui,sans-serif;color:#1a1a1a;max-width:600px;margin:0 auto;padding:20px;">'
            . '<div style="background:linear-gradient(135deg,#0a1628,#00ADEF);color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center;">'
            . '<h2 style="margin:0;font-size:18px;">🕌 YourJannah</h2>'
            . '<p style="margin:4px 0 0;opacity:.8;font-size:13px;">' . esc_html( $mosque_name ) . '</p></div>'
            . '<div style="background:#fff;border:1px solid #e5e5e5;border-top:none;padding:24px;border-radius:0 0 12px 12px;">'
            . $body_html
            . '</div></body></html>';

        add_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
        wp_mail( $guest_email, $subject, $html );
        remove_filter( 'wp_mail_content_type', function() { return 'text/html'; } );
    }

    /**
     * New business sponsor signed up.
     * do_action( 'ynj_new_sponsor', $mosque_id, $business_data )
     */
    public static function on_sponsor( $mosque_id, $data ) {
        $name = esc_html( $data['business_name'] ?? 'A business' );
        $tier = esc_html( $data['tier'] ?? 'standard' );
        $fee  = isset( $data['monthly_fee_pence'] ) ? '£' . number_format( $data['monthly_fee_pence'] / 100 ) . '/mo' : '';

        self::send( $mosque_id, "New sponsor: {$name}", "
            <h3 style='margin:0 0 12px;'>New Business Sponsor! 🎉</h3>
            <table style='font-size:14px;width:100%;'>
                <tr><td style='padding:6px 0;color:#666;width:100px;'>Business:</td><td><strong>{$name}</strong></td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Tier:</td><td>" . ucfirst( $tier ) . " ({$fee})</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Category:</td><td>" . esc_html( $data['category'] ?? '' ) . "</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Contact:</td><td>" . esc_html( $data['phone'] ?? '' ) . "</td></tr>
            </table>
            <p style='margin:16px 0;font-size:14px;'>Their listing will go live once payment is confirmed via Stripe.</p>
        " );
    }

    /**
     * New professional service listing.
     * do_action( 'ynj_new_service_listing', $mosque_id, $service_data )
     */
    public static function on_service_listing( $mosque_id, $data ) {
        $name = esc_html( $data['provider_name'] ?? 'Someone' );
        $type = esc_html( $data['service_type'] ?? '' );

        self::send( $mosque_id, "New service listing: {$name}", "
            <h3 style='margin:0 0 12px;'>New Professional Service Listing</h3>
            <table style='font-size:14px;width:100%;'>
                <tr><td style='padding:6px 0;color:#666;width:100px;'>Provider:</td><td><strong>{$name}</strong></td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Service:</td><td>{$type}</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Phone:</td><td>" . esc_html( $data['phone'] ?? '' ) . "</td></tr>
                <tr><td style='padding:6px 0;color:#666;'>Area:</td><td>" . esc_html( $data['area_covered'] ?? '' ) . "</td></tr>
            </table>
            <p style='margin:16px 0;font-size:14px;'>Their listing will go live once payment is confirmed (£10/mo).</p>
        " );
    }

    /**
     * New patron membership activated.
     * do_action( 'ynj_new_patron', $mosque_id, $patron_data )
     */
    public static function on_patron( $mosque_id, $data ) {
        $name = esc_html( $data['user_name'] ?? 'Someone' );
        $tier = esc_html( ucfirst( $data['tier'] ?? 'supporter' ) );
        $amount = isset( $data['amount_pence'] ) ? '£' . number_format( $data['amount_pence'] / 100 ) . '/mo' : '';

        self::send( $mosque_id, "New patron: {$name}", "
            <h3 style='margin:0 0 12px;'>New Patron! 🏅</h3>
            <p style='font-size:14px;'><strong>{$name}</strong> has become a <strong>{$tier}</strong> patron of your masjid.</p>
            <table style='font-size:14px;width:100%;margin-top:12px;'>
                <tr><td style='padding:6px 0;color:#666;width:100px;'>Tier:</td><td><strong>{$tier}</strong> ({$amount})</td></tr>
            </table>
            <a href='https://yourjannah.com/dashboard#/patrons' style='display:inline-block;background:#00ADEF;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;margin-top:16px;'>View Patrons</a>
        " );
    }

    /**
     * Payment received via Stripe.
     * do_action( 'ynj_payment_received', $mosque_id, $type, $item_id )
     */
    public static function on_payment( $mosque_id, $type, $item_id ) {
        $labels = [
            'business_sponsor'     => 'Business Sponsor',
            'professional_service' => 'Professional Service',
            'room_booking'         => 'Room Booking',
            'event_ticket'         => 'Event Ticket',
        ];
        $label = $labels[ $type ] ?? $type;

        self::send( $mosque_id, "Payment received: {$label}", "
            <h3 style='margin:0 0 12px;'>Payment Confirmed ✅</h3>
            <p style='font-size:14px;'>A payment has been received for a <strong>{$label}</strong> (ID: {$item_id}).</p>
            <p style='font-size:14px;'>The listing/booking has been automatically activated.</p>
            <a href='https://yourjannah.com/dashboard' style='display:inline-block;background:#00ADEF;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;margin-top:16px;'>View Dashboard</a>
        " );
    }
}
