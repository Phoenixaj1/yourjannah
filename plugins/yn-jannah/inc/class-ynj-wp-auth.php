<?php
/**
 * YNJ_WP_Auth — WordPress-native authentication for YourJannah.
 *
 * Replaces the custom token system with WordPress users, roles, and nonces.
 * Mosque admins get role 'ynj_mosque_admin', congregation members get 'ynj_congregation'.
 *
 * @package YourJannah
 * @since   2.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_WP_Auth {

    // ================================================================
    // ROLES & CAPABILITIES
    // ================================================================

    /**
     * Register custom roles and capabilities on plugin activation.
     */
    public static function install_roles() {
        // Mosque admin role
        add_role( 'ynj_mosque_admin', 'Mosque Admin', [
            'read'                  => true,
            'ynj_manage_mosque'     => true,
            'ynj_manage_events'     => true,
            'ynj_manage_classes'    => true,
            'ynj_manage_services'   => true,
            'ynj_manage_rooms'      => true,
            'ynj_manage_campaigns'  => true,
            'ynj_view_enquiries'    => true,
            'ynj_view_subscribers'  => true,
            'ynj_view_patrons'      => true,
            'ynj_manage_madrassah'  => true,
        ] );

        // Imam role (limited mosque management)
        add_role( 'ynj_imam', 'Mosque Imam', [
            'read'                      => true,
            'ynj_create_announcements'  => true,
            'ynj_send_broadcasts'       => true,
        ] );

        // Congregation member role
        add_role( 'ynj_congregation', 'Congregation Member', [
            'read'                => true,
            'ynj_book_services'   => true,
            'ynj_subscribe'       => true,
            'ynj_become_patron'   => true,
            'ynj_enrol_madrassah' => true,
        ] );

        // Also grant mosque admin caps to administrator
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'ynj_manage_mosque' );
            $admin->add_cap( 'ynj_manage_events' );
            $admin->add_cap( 'ynj_manage_classes' );
            $admin->add_cap( 'ynj_manage_services' );
            $admin->add_cap( 'ynj_manage_rooms' );
            $admin->add_cap( 'ynj_manage_campaigns' );
            $admin->add_cap( 'ynj_view_enquiries' );
            $admin->add_cap( 'ynj_view_subscribers' );
            $admin->add_cap( 'ynj_view_patrons' );
            $admin->add_cap( 'ynj_manage_madrassah' );
        }
    }

    /**
     * Remove custom roles on plugin deactivation.
     */
    public static function remove_roles() {
        remove_role( 'ynj_mosque_admin' );
        remove_role( 'ynj_imam' );
        remove_role( 'ynj_congregation' );
    }

    // ================================================================
    // MOSQUE ADMIN: REGISTER
    // ================================================================

    /**
     * Register a new mosque admin. Creates a WP user + mosque record.
     *
     * @param array $data  Registration data.
     * @return array|WP_Error  {ok, token, mosque_id, wp_user_id} on success.
     */
    public static function register_mosque_admin( $data ) {
        $name     = sanitize_text_field( $data['name'] ?? '' );
        $email    = sanitize_email( $data['email'] ?? '' );
        $password = $data['password'] ?? '';
        $postcode = sanitize_text_field( $data['postcode'] ?? '' );
        $city     = sanitize_text_field( $data['city'] ?? '' );
        $address  = sanitize_text_field( $data['address'] ?? '' );

        if ( empty( $name ) || ! is_email( $email ) || strlen( $password ) < 8 ) {
            return new WP_Error( 'invalid_input', 'Name, valid email, and password (8+ chars) required.', [ 'status' => 400 ] );
        }

        // Check if WP user exists
        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', 'An account with this email already exists.', [ 'status' => 409 ] );
        }

        // Create WP user
        $username = sanitize_user( str_replace( '@', '_', $email ), true );
        $wp_user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $wp_user_id ) ) {
            return new WP_Error( 'registration_failed', $wp_user_id->get_error_message(), [ 'status' => 500 ] );
        }

        // Set role and display name
        $wp_user = new WP_User( $wp_user_id );
        $wp_user->set_role( 'ynj_mosque_admin' );
        wp_update_user( [
            'ID'           => $wp_user_id,
            'display_name' => $name,
            'first_name'   => $name,
        ] );

        // Create mosque record (still in custom table for mosque-specific data)
        global $wpdb;
        $slug = sanitize_title( $name );

        // Ensure unique slug
        $base_slug = $slug;
        $i = 1;
        while ( YNJ_DB::resolve_slug( $slug ) ) {
            $slug = $base_slug . '-' . $i++;
        }

        $wpdb->insert( YNJ_DB::table( 'mosques' ), [
            'name'          => $name,
            'slug'          => $slug,
            'address'       => $address,
            'city'          => $city,
            'postcode'      => $postcode,
            'country'       => 'UK',
            'admin_email'   => $email,
            'status'        => 'active',
        ] );

        $mosque_id = (int) $wpdb->insert_id;
        if ( ! $mosque_id ) {
            wp_delete_user( $wp_user_id );
            return new WP_Error( 'db_error', 'Failed to create mosque record.', [ 'status' => 500 ] );
        }

        // Store mosque_id in usermeta
        update_user_meta( $wp_user_id, 'ynj_mosque_id', $mosque_id );
        update_user_meta( $wp_user_id, 'ynj_mosque_ids', [ $mosque_id ] ); // Array for multi-mosque support

        // Auto-login and generate application password for API access
        $app_pass = self::create_app_password( $wp_user_id, 'YourJannah Dashboard' );

        do_action( 'ynj_mosque_registered', $mosque_id, $wp_user_id, $data );

        return [
            'ok'         => true,
            'token'      => $app_pass,
            'mosque_id'  => $mosque_id,
            'slug'       => $slug,
            'wp_user_id' => $wp_user_id,
        ];
    }

    /**
     * Invite an additional admin to an existing mosque.
     */
    public static function invite_admin( $mosque_id, $email, $mosque_name ) {
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Valid email required.', [ 'status' => 400 ] );
        }

        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            // User exists — add mosque admin role + mosque_id
            $existing->add_role( 'ynj_mosque_admin' );
            update_user_meta( $existing->ID, 'ynj_mosque_id', $mosque_id );
            $ids = get_user_meta( $existing->ID, 'ynj_mosque_ids', true ) ?: [];
            if ( ! in_array( $mosque_id, $ids, true ) ) {
                $ids[] = $mosque_id;
                update_user_meta( $existing->ID, 'ynj_mosque_ids', $ids );
            }

            wp_mail( $email,
                'You\'ve been added as an admin — ' . $mosque_name,
                'Assalamu alaikum,\n\nYou\'ve been added as an admin for ' . $mosque_name . ' on YourJannah.\n\nLog in at: ' . home_url( '/dashboard' ) . '\n\nJazakallah khayr.'
            );

            return [ 'ok' => true, 'message' => 'Existing user added as admin.' ];
        }

        // New user — create with temporary password
        $temp_pass = wp_generate_password( 12, false );
        $username = sanitize_user( str_replace( '@', '_', $email ), true );
        $wp_user_id = wp_create_user( $username, $temp_pass, $email );

        if ( is_wp_error( $wp_user_id ) ) {
            return new WP_Error( 'invite_failed', $wp_user_id->get_error_message(), [ 'status' => 500 ] );
        }

        $wp_user = new WP_User( $wp_user_id );
        $wp_user->set_role( 'ynj_mosque_admin' );
        update_user_meta( $wp_user_id, 'ynj_mosque_id', $mosque_id );
        update_user_meta( $wp_user_id, 'ynj_mosque_ids', [ $mosque_id ] );

        wp_mail( $email,
            'You\'re invited to manage ' . $mosque_name . ' on YourJannah',
            'Assalamu alaikum,\n\nYou\'ve been invited to manage ' . $mosque_name . ' on YourJannah.\n\n'
            . 'Log in at: ' . home_url( '/dashboard' ) . '\n'
            . 'Email: ' . $email . '\n'
            . 'Temporary password: ' . $temp_pass . '\n\n'
            . 'Please change your password after first login.\n\nJazakallah khayr.'
        );

        return [ 'ok' => true, 'message' => 'Invite sent to ' . $email ];
    }

    // ================================================================
    // MOSQUE ADMIN: LOGIN
    // ================================================================

    /**
     * Authenticate a mosque admin. Tries WP auth first, then falls back to
     * old custom auth and auto-migrates the password to WP on success.
     *
     * @param string $email    Admin email.
     * @param string $password Admin password.
     * @return array|WP_Error  {ok, token, mosque_id} on success.
     */
    public static function login_mosque_admin( $email, $password ) {
        if ( empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', 'Email and password are required.', [ 'status' => 400 ] );
        }

        // Try WP auth first
        $user = wp_authenticate( $email, $password );

        if ( is_wp_error( $user ) ) {
            // Fallback: try old custom auth (password may not be migrated yet)
            $old_result = YNJ_Auth::login( $email, $password );

            if ( is_wp_error( $old_result ) ) {
                return new WP_Error( 'invalid_credentials', 'Invalid email or password.', [ 'status' => 401 ] );
            }

            // Old auth succeeded — migrate password to WP
            $wp_user = get_user_by( 'email', $email );
            if ( $wp_user ) {
                // Update WP password so next login uses WP auth directly
                wp_set_password( $password, $wp_user->ID );
                // Re-authenticate to get a proper WP user object
                $user = wp_authenticate( $email, $password );
                if ( is_wp_error( $user ) ) {
                    // Shouldn't happen but fallback to old token
                    return $old_result;
                }
            } else {
                // No WP user yet — create one
                $username = sanitize_user( str_replace( '@', '_', $email ), true );
                $wp_user_id = wp_create_user( $username, $password, $email );
                if ( is_wp_error( $wp_user_id ) ) {
                    return $old_result; // Return old token as fallback
                }
                $user = new WP_User( $wp_user_id );
                $user->set_role( 'ynj_mosque_admin' );

                // Get mosque name for display name
                global $wpdb;
                $mosque_name = $wpdb->get_var( $wpdb->prepare(
                    "SELECT name FROM " . YNJ_DB::table( 'mosques' ) . " WHERE admin_email = %s",
                    $email
                ) );
                wp_update_user( [ 'ID' => $wp_user_id, 'display_name' => $mosque_name ?: $email ] );

                $mosque_id = (int) ( $old_result['mosque_id'] ?? 0 );
                if ( $mosque_id ) {
                    update_user_meta( $wp_user_id, 'ynj_mosque_id', $mosque_id );
                    update_user_meta( $wp_user_id, 'ynj_mosque_ids', [ $mosque_id ] );
                }
            }
        }

        // Check role
        if ( ! user_can( $user, 'ynj_manage_mosque' ) ) {
            // Auto-assign role if they have a mosque
            global $wpdb;
            $has_mosque = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . YNJ_DB::table( 'mosques' ) . " WHERE admin_email = %s AND status = 'active'",
                $email
            ) );
            if ( $has_mosque ) {
                $user->add_role( 'ynj_mosque_admin' );
                update_user_meta( $user->ID, 'ynj_mosque_id', (int) $has_mosque );
            } else {
                return new WP_Error( 'not_mosque_admin', 'This account is not a mosque admin.', [ 'status' => 403 ] );
            }
        }

        $mosque_id = (int) get_user_meta( $user->ID, 'ynj_mosque_id', true );

        // Generate old-style Bearer token for dashboard backward compat
        $token = YNJ_Auth::generate_token( $mosque_id );

        return [
            'ok'         => true,
            'token'      => $token,
            'mosque_id'  => $mosque_id,
            'wp_user_id' => $user->ID,
        ];
    }

    // ================================================================
    // CONGREGATION: REGISTER
    // ================================================================

    /**
     * Register a congregation member.
     *
     * @param array $data Registration data.
     * @return array|WP_Error
     */
    public static function register_congregation( $data ) {
        $name     = sanitize_text_field( $data['name'] ?? '' );
        $email    = sanitize_email( $data['email'] ?? '' );
        $password = $data['password'] ?? '';
        $phone    = sanitize_text_field( $data['phone'] ?? '' );

        if ( empty( $name ) || ! is_email( $email ) || strlen( $password ) < 6 ) {
            return new WP_Error( 'invalid_input', 'Name, valid email, and password (6+ chars) required.', [ 'status' => 400 ] );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', 'An account with this email already exists.', [ 'status' => 409 ] );
        }

        $username = sanitize_user( str_replace( '@', '_', $email ), true );
        $wp_user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $wp_user_id ) ) {
            return new WP_Error( 'registration_failed', $wp_user_id->get_error_message(), [ 'status' => 500 ] );
        }

        $wp_user = new WP_User( $wp_user_id );
        $wp_user->set_role( 'ynj_congregation' );
        wp_update_user( [
            'ID'           => $wp_user_id,
            'display_name' => $name,
            'first_name'   => $name,
        ] );

        // Store phone and preferences in usermeta
        if ( $phone ) update_user_meta( $wp_user_id, 'ynj_phone', $phone );
        update_user_meta( $wp_user_id, 'ynj_travel_mode', 'walk' );
        update_user_meta( $wp_user_id, 'ynj_travel_minutes', 0 );
        update_user_meta( $wp_user_id, 'ynj_alert_before_minutes', 20 );

        // Also create record in ynj_users for backward compat (push, verification, etc.)
        global $wpdb;
        $wpdb->insert( YNJ_DB::table( 'users' ), [
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'password_hash' => '', // Not needed — WP handles auth
            'status'        => 'active',
        ] );
        $ynj_user_id = (int) $wpdb->insert_id;
        update_user_meta( $wp_user_id, 'ynj_user_id', $ynj_user_id );

        // Generate old-style user token for frontend backward compat
        $token = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );
        $wpdb->update( YNJ_DB::table( 'users' ), [
            'token_hash'      => $token_hash,
            'token_last_used' => current_time( 'mysql', true ),
        ], [ 'id' => $ynj_user_id ] );

        do_action( 'ynj_user_registered', $wp_user_id, $data );

        // Auto-subscribe to mosque if slug provided
        $mosque_slug = sanitize_title( $data['mosque_slug'] ?? '' );
        if ( $mosque_slug ) {
            $mosque = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM " . YNJ_DB::table( 'mosques' ) . " WHERE slug = %s AND status = 'active'",
                $mosque_slug
            ) );
            if ( $mosque ) {
                $wpdb->replace( YNJ_DB::table( 'user_subscriptions' ), [
                    'user_id'   => $ynj_user_id,
                    'mosque_id' => $mosque->id,
                    'notify_events'        => 1,
                    'notify_classes'       => 1,
                    'notify_announcements' => 1,
                    'notify_live'          => 1,
                    'notify_fundraising'   => 1,
                ] );
                update_user_meta( $wp_user_id, 'ynj_favourite_mosque_id', $mosque->id );
            }
        }

        // Set WP auth cookie so is_user_logged_in() works on next page load
        wp_set_auth_cookie( $wp_user_id, true );

        // Send welcome email with password
        $password = $data['password'] ?? '';
        if ( $password && $email ) {
            $subject = 'Welcome to YourJannah — Your Account Details';
            $message = "Assalamu Alaikum " . $name . ",\n\n";
            $message .= "Welcome to YourJannah! Your account has been created.\n\n";
            $message .= "Email: " . $email . "\n";
            $message .= "Password: " . $password . "\n\n";
            $message .= "Sign in: " . home_url( '/login' ) . "\n\n";
            $message .= "If this email landed in spam, please mark it as 'Not Spam' so you receive future updates from your masjid.\n\n";
            $message .= "JazakAllah Khayr,\nYourJannah Team";
            $headers = [ 'From: YourJannah <noreply@yourjannah.com>' ];
            wp_mail( $email, $subject, $message, $headers );
        }

        return [
            'ok'         => true,
            'token'      => $token,
            'wp_user_id' => $wp_user_id,
            'user_id'    => $ynj_user_id,
        ];
    }

    // ================================================================
    // CONGREGATION: LOGIN
    // ================================================================

    /**
     * Authenticate a congregation member.
     *
     * @param string $email    Email.
     * @param string $password Password.
     * @return array|WP_Error
     */
    public static function login_congregation( $email, $password ) {
        if ( empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_fields', 'Email and password are required.', [ 'status' => 400 ] );
        }

        // Try WP auth first
        $user = wp_authenticate( $email, $password );

        if ( is_wp_error( $user ) ) {
            // Fallback: try old custom auth and auto-migrate
            $old_result = YNJ_User_Auth::login( [ 'email' => $email, 'password' => $password ] );

            if ( ! isset( $old_result['ok'] ) || ! $old_result['ok'] ) {
                return new WP_Error( 'invalid_credentials', 'Invalid email or password.', [ 'status' => 401 ] );
            }

            // Old auth succeeded — migrate to WP
            $wp_user = get_user_by( 'email', $email );
            if ( $wp_user ) {
                wp_set_password( $password, $wp_user->ID );
                $user = wp_authenticate( $email, $password );
                if ( is_wp_error( $user ) ) {
                    // Return old token as fallback
                    return $old_result;
                }
            } else {
                $username = sanitize_user( str_replace( '@', '_', $email ), true );
                $wp_user_id = wp_create_user( $username, $password, $email );
                if ( is_wp_error( $wp_user_id ) ) {
                    return $old_result;
                }
                $user = new WP_User( $wp_user_id );
                $user->set_role( 'ynj_congregation' );
                wp_update_user( [ 'ID' => $wp_user_id, 'display_name' => $old_result['user']['name'] ?? $email ] );
            }
        }

        // Get ynj_user_id from usermeta
        $ynj_user_id = (int) get_user_meta( $user->ID, 'ynj_user_id', true );

        if ( ! $ynj_user_id ) {
            global $wpdb;
            $ynj_user_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . YNJ_DB::table( 'users' ) . " WHERE email = %s",
                $email
            ) );
            if ( $ynj_user_id ) {
                update_user_meta( $user->ID, 'ynj_user_id', $ynj_user_id );
            }
        }

        // Generate old-style user token for frontend backward compat
        $token = bin2hex( random_bytes( 32 ) );
        $token_hash = hash_hmac( 'sha256', $token, 'ynj_user_salt_2024' );
        if ( $ynj_user_id ) {
            global $wpdb;
            $wpdb->update( YNJ_DB::table( 'users' ), [
                'token_hash'      => $token_hash,
                'token_last_used' => current_time( 'mysql', true ),
            ], [ 'id' => $ynj_user_id ] );
        }

        // Set WP auth cookie so is_user_logged_in() works on next page load
        wp_set_auth_cookie( $user->ID, true );

        return [
            'ok'         => true,
            'token'      => $token,
            'wp_user_id' => $user->ID,
            'user_id'    => $ynj_user_id,
            'user'       => self::format_congregation_user( $user ),
        ];
    }

    // ================================================================
    // APPLICATION PASSWORDS (WP 5.6+)
    // ================================================================

    /**
     * Create an application password for API access.
     * Cleans up old ones to avoid accumulation.
     *
     * @param int    $user_id WP user ID.
     * @param string $name    App name.
     * @return string         Base64-encoded app password for use as Bearer token.
     */
    private static function create_app_password( $user_id, $name ) {
        // Remove old YourJannah app passwords (keep only latest)
        $existing = WP_Application_Passwords::get_user_application_passwords( $user_id );
        foreach ( $existing as $pass ) {
            if ( strpos( $pass['name'], 'YourJannah' ) !== false ) {
                WP_Application_Passwords::delete_application_password( $user_id, $pass['uuid'] );
            }
        }

        // Create new
        $result = WP_Application_Passwords::create_new_application_password(
            $user_id,
            [ 'name' => $name ]
        );

        if ( is_wp_error( $result ) ) {
            // Fallback: generate a custom token stored in usermeta
            $token = bin2hex( random_bytes( 32 ) );
            update_user_meta( $user_id, 'ynj_api_token', wp_hash( $token ) );
            return $token;
        }

        // $result[0] is the unhashed password (shown only once)
        return $result[0];
    }

    // ================================================================
    // PERMISSION CALLBACKS (Replace old YNJ_Auth::bearer_check)
    // ================================================================

    /**
     * Permission callback for mosque admin endpoints.
     * Works with BOTH old Bearer tokens AND new WP application passwords.
     */
    public static function mosque_admin_check( \WP_REST_Request $request ) {
        // Try WordPress native auth first (cookie/nonce or application password)
        $wp_user_id = get_current_user_id();

        if ( ! $wp_user_id ) {
            // Fallback: try old Bearer token system
            return YNJ_Auth::bearer_check( $request );
        }

        if ( ! user_can( $wp_user_id, 'ynj_manage_mosque' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission.', [ 'status' => 403 ] );
        }

        // Inject mosque data into request (same as old system)
        $mosque_id = (int) get_user_meta( $wp_user_id, 'ynj_mosque_id', true );
        if ( ! $mosque_id ) {
            return new WP_Error( 'no_mosque', 'No mosque linked to this account.', [ 'status' => 404 ] );
        }

        global $wpdb;
        $mosque = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . YNJ_DB::table( 'mosques' ) . " WHERE id = %d AND status = 'active'",
            $mosque_id
        ) );

        if ( ! $mosque ) {
            return new WP_Error( 'mosque_not_found', 'Mosque not found.', [ 'status' => 404 ] );
        }

        $request->set_param( '_ynj_mosque', $mosque );
        return true;
    }

    /**
     * Permission callback for congregation member endpoints.
     * Works with BOTH old Bearer tokens AND new WP application passwords.
     */
    public static function congregation_check( \WP_REST_Request $request ) {
        // Try WordPress native auth first
        $wp_user_id = get_current_user_id();

        if ( ! $wp_user_id ) {
            // Fallback: try old Bearer token system
            return YNJ_User_Auth::user_check( $request );
        }

        // Get or create the ynj_user record
        $ynj_user_id = (int) get_user_meta( $wp_user_id, 'ynj_user_id', true );

        if ( ! $ynj_user_id ) {
            // Try to find by email
            global $wpdb;
            $wp_user = get_userdata( $wp_user_id );
            $ynj_user_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM " . YNJ_DB::table( 'users' ) . " WHERE email = %s",
                $wp_user->user_email
            ) );
            if ( $ynj_user_id ) {
                update_user_meta( $wp_user_id, 'ynj_user_id', $ynj_user_id );
            }
        }

        if ( $ynj_user_id ) {
            global $wpdb;
            $user = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d",
                $ynj_user_id
            ) );
            $request->set_param( '_ynj_user', $user );
        } else {
            // Create a minimal ynj_user record for this WP user
            $wp_user = get_userdata( $wp_user_id );
            global $wpdb;
            $wpdb->insert( YNJ_DB::table( 'users' ), [
                'name'   => $wp_user->display_name,
                'email'  => $wp_user->user_email,
                'status' => 'active',
            ] );
            $ynj_user_id = (int) $wpdb->insert_id;
            update_user_meta( $wp_user_id, 'ynj_user_id', $ynj_user_id );

            $user = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . YNJ_DB::table( 'users' ) . " WHERE id = %d",
                $ynj_user_id
            ) );
            $request->set_param( '_ynj_user', $user );
        }

        return true;
    }

    // ================================================================
    // FORMAT USER
    // ================================================================

    /**
     * Format a WP user for congregation API response.
     */
    public static function format_congregation_user( $wp_user ) {
        $user_id = $wp_user->ID;
        $ynj_user_id = (int) get_user_meta( $user_id, 'ynj_user_id', true );

        $data = [
            'id'                    => $ynj_user_id ?: $user_id,
            'wp_user_id'            => $user_id,
            'name'                  => $wp_user->display_name,
            'email'                 => $wp_user->user_email,
            'phone'                 => get_user_meta( $user_id, 'ynj_phone', true ) ?: '',
            'favourite_mosque_id'   => (int) get_user_meta( $user_id, 'ynj_favourite_mosque_id', true ) ?: null,
            'travel_mode'           => get_user_meta( $user_id, 'ynj_travel_mode', true ) ?: 'walk',
            'travel_minutes'        => (int) get_user_meta( $user_id, 'ynj_travel_minutes', true ),
            'alert_before_minutes'  => (int) ( get_user_meta( $user_id, 'ynj_alert_before_minutes', true ) ?: 20 ),
            'verified_congregation' => (bool) get_user_meta( $user_id, 'ynj_verified_congregation', true ),
            'created_at'            => $wp_user->user_registered,
        ];

        return $data;
    }

    // ================================================================
    // MIGRATION: Move existing custom table users to wp_users
    // ================================================================

    /**
     * Migrate existing mosque admins from ynj_mosques to wp_users.
     *
     * @return array  {migrated: int, skipped: int, errors: int}
     */
    public static function migrate_mosque_admins() {
        global $wpdb;
        $table = YNJ_DB::table( 'mosques' );
        $mosques = $wpdb->get_results( "SELECT id, name, admin_email, admin_password_hash FROM $table WHERE admin_email != '' AND status = 'active'" );

        $stats = [ 'migrated' => 0, 'skipped' => 0, 'errors' => 0 ];

        foreach ( $mosques as $m ) {
            if ( email_exists( $m->admin_email ) ) {
                // Already in WP — just link
                $wp_user = get_user_by( 'email', $m->admin_email );
                if ( $wp_user ) {
                    $wp_user->add_role( 'ynj_mosque_admin' );
                    update_user_meta( $wp_user->ID, 'ynj_mosque_id', (int) $m->id );
                    $mosque_ids = get_user_meta( $wp_user->ID, 'ynj_mosque_ids', true ) ?: [];
                    if ( ! in_array( (int) $m->id, $mosque_ids ) ) {
                        $mosque_ids[] = (int) $m->id;
                        update_user_meta( $wp_user->ID, 'ynj_mosque_ids', $mosque_ids );
                    }
                    $stats['skipped']++;
                }
                continue;
            }

            // Create WP user with the existing password hash
            $username = sanitize_user( str_replace( '@', '_', $m->admin_email ), true );
            $wp_user_id = wp_insert_user( [
                'user_login'   => $username,
                'user_email'   => $m->admin_email,
                'user_pass'    => wp_generate_password( 24 ), // Temp pass — we'll set the hash directly
                'display_name' => $m->name,
                'role'         => 'ynj_mosque_admin',
            ] );

            if ( is_wp_error( $wp_user_id ) ) {
                $stats['errors']++;
                error_log( '[YNJ Migration] Failed to create WP user for ' . $m->admin_email . ': ' . $wp_user_id->get_error_message() );
                continue;
            }

            // Overwrite the password hash with the existing one so old passwords still work
            if ( $m->admin_password_hash ) {
                $wpdb->update( $wpdb->users, [ 'user_pass' => $m->admin_password_hash ], [ 'ID' => $wp_user_id ] );
                wp_cache_delete( $wp_user_id, 'users' );
            }

            update_user_meta( $wp_user_id, 'ynj_mosque_id', (int) $m->id );
            update_user_meta( $wp_user_id, 'ynj_mosque_ids', [ (int) $m->id ] );

            $stats['migrated']++;
        }

        return $stats;
    }

    /**
     * Migrate existing congregation members from ynj_users to wp_users.
     *
     * @return array  {migrated: int, skipped: int, errors: int}
     */
    public static function migrate_congregation_members() {
        global $wpdb;
        $table = YNJ_DB::table( 'users' );
        $users = $wpdb->get_results( "SELECT * FROM $table WHERE email != '' AND status = 'active'" );

        $stats = [ 'migrated' => 0, 'skipped' => 0, 'errors' => 0 ];

        foreach ( $users as $u ) {
            if ( email_exists( $u->email ) ) {
                $wp_user = get_user_by( 'email', $u->email );
                if ( $wp_user ) {
                    $wp_user->add_role( 'ynj_congregation' );
                    update_user_meta( $wp_user->ID, 'ynj_user_id', (int) $u->id );
                    if ( $u->phone ) update_user_meta( $wp_user->ID, 'ynj_phone', $u->phone );
                    if ( $u->favourite_mosque_id ) update_user_meta( $wp_user->ID, 'ynj_favourite_mosque_id', (int) $u->favourite_mosque_id );
                    update_user_meta( $wp_user->ID, 'ynj_travel_mode', $u->travel_mode ?: 'walk' );
                    update_user_meta( $wp_user->ID, 'ynj_travel_minutes', (int) $u->travel_minutes );
                    update_user_meta( $wp_user->ID, 'ynj_alert_before_minutes', (int) $u->alert_before_minutes ?: 20 );
                    $stats['skipped']++;
                }
                continue;
            }

            $username = sanitize_user( str_replace( '@', '_', $u->email ), true );
            $wp_user_id = wp_insert_user( [
                'user_login'   => $username,
                'user_email'   => $u->email,
                'user_pass'    => wp_generate_password( 24 ),
                'display_name' => $u->name,
                'role'         => 'ynj_congregation',
            ] );

            if ( is_wp_error( $wp_user_id ) ) {
                $stats['errors']++;
                continue;
            }

            // Copy password hash
            if ( $u->password_hash ) {
                $wpdb->update( $wpdb->users, [ 'user_pass' => $u->password_hash ], [ 'ID' => $wp_user_id ] );
                wp_cache_delete( $wp_user_id, 'users' );
            }

            update_user_meta( $wp_user_id, 'ynj_user_id', (int) $u->id );
            if ( $u->phone ) update_user_meta( $wp_user_id, 'ynj_phone', $u->phone );
            if ( $u->favourite_mosque_id ) update_user_meta( $wp_user_id, 'ynj_favourite_mosque_id', (int) $u->favourite_mosque_id );
            update_user_meta( $wp_user_id, 'ynj_travel_mode', $u->travel_mode ?: 'walk' );
            update_user_meta( $wp_user_id, 'ynj_travel_minutes', (int) $u->travel_minutes );
            update_user_meta( $wp_user_id, 'ynj_alert_before_minutes', (int) $u->alert_before_minutes ?: 20 );

            $stats['migrated']++;
        }

        return $stats;
    }
}
