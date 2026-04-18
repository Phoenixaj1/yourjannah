<?php
/**
 * YourJannah Donations — Data Layer
 *
 * PHP-first data class with direct $wpdb queries for campaigns,
 * donations, patrons, and mosque funds.
 *
 * @package YNJ_Donations
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class YNJ_Donations {

    // ================================================================
    // CAMPAIGNS
    // ================================================================

    /**
     * Get active campaigns for a mosque.
     *
     * @param  int    $mosque_id
     * @param  string $status   Filter by status (default 'active', use 'all' for everything).
     * @return array
     */
    public static function get_campaigns( $mosque_id, $status = 'active' ) {
        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );
        $mosque_id = absint( $mosque_id );

        if ( $status === 'all' ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table WHERE mosque_id = %d ORDER BY created_at DESC",
                $mosque_id
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE mosque_id = %d AND status = %s ORDER BY created_at DESC",
            $mosque_id,
            sanitize_text_field( $status )
        ) );
    }

    /**
     * Get a single campaign by ID.
     *
     * @param  int $id Campaign ID.
     * @return object|null
     */
    public static function get_campaign( $id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            absint( $id )
        ) );
    }

    /**
     * Create a campaign.
     *
     * @param  array $data {
     *     @type int    $mosque_id
     *     @type string $title
     *     @type string $description
     *     @type string $image_url
     *     @type int    $target_pence
     *     @type string $category
     *     @type string $dfm_link
     *     @type int    $recurring       0 or 1
     *     @type string $recurring_interval  'week'|'month'|''
     *     @type string $start_date
     *     @type string $end_date
     * }
     * @return int|false  Inserted ID or false on failure.
     */
    public static function create_campaign( $data ) {
        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );

        $insert = [
            'mosque_id'          => absint( $data['mosque_id'] ?? 0 ),
            'title'              => sanitize_text_field( $data['title'] ?? '' ),
            'description'        => wp_kses_post( $data['description'] ?? '' ),
            'image_url'          => esc_url_raw( $data['image_url'] ?? '' ),
            'target_pence'       => absint( $data['target_pence'] ?? 0 ),
            'category'           => sanitize_text_field( $data['category'] ?? 'general' ),
            'dfm_link'           => esc_url_raw( $data['dfm_link'] ?? '' ),
            'recurring'          => absint( $data['recurring'] ?? 0 ),
            'recurring_interval' => sanitize_text_field( $data['recurring_interval'] ?? '' ),
            'status'             => 'active',
            'start_date'         => sanitize_text_field( $data['start_date'] ?? date( 'Y-m-d' ) ),
            'end_date'           => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
        ];

        if ( empty( $insert['title'] ) || empty( $insert['mosque_id'] ) ) {
            return false;
        }

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        if ( $id ) {
            /**
             * Fires after a new fundraising campaign is created.
             *
             * @param int   $id        Campaign ID.
             * @param array $insert    Inserted data.
             */
            do_action( 'ynj_campaign_created', $id, $insert );
        }

        return $id ?: false;
    }

    /**
     * Update a campaign.
     *
     * @param  int   $id        Campaign ID.
     * @param  int   $mosque_id Mosque ID (ownership check).
     * @param  array $data      Fields to update.
     * @return bool
     */
    public static function update_campaign( $id, $mosque_id, $data ) {
        global $wpdb;
        $table = YNJ_DB::table( 'campaigns' );
        $id        = absint( $id );
        $mosque_id = absint( $mosque_id );

        // Verify ownership
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND mosque_id = %d",
            $id, $mosque_id
        ) );
        if ( ! $existing ) return false;

        $update = [];
        if ( isset( $data['title'] ) )              $update['title']              = sanitize_text_field( $data['title'] );
        if ( isset( $data['description'] ) )        $update['description']        = wp_kses_post( $data['description'] );
        if ( isset( $data['image_url'] ) )          $update['image_url']          = esc_url_raw( $data['image_url'] );
        if ( isset( $data['target_pence'] ) )       $update['target_pence']       = absint( $data['target_pence'] );
        if ( isset( $data['raised_pence'] ) )       $update['raised_pence']       = absint( $data['raised_pence'] );
        if ( isset( $data['donor_count'] ) )        $update['donor_count']        = absint( $data['donor_count'] );
        if ( isset( $data['category'] ) )           $update['category']           = sanitize_text_field( $data['category'] );
        if ( isset( $data['dfm_link'] ) )           $update['dfm_link']           = esc_url_raw( $data['dfm_link'] );
        if ( isset( $data['status'] ) )             $update['status']             = sanitize_text_field( $data['status'] );
        if ( isset( $data['recurring'] ) )          $update['recurring']          = absint( $data['recurring'] );
        if ( isset( $data['recurring_interval'] ) ) $update['recurring_interval'] = sanitize_text_field( $data['recurring_interval'] );
        if ( isset( $data['end_date'] ) )           $update['end_date']           = sanitize_text_field( $data['end_date'] );

        if ( empty( $update ) ) return true;

        $result = $wpdb->update( $table, $update, [ 'id' => $id ] );

        if ( $result !== false ) {
            /**
             * Fires after a campaign is updated.
             *
             * @param int   $id     Campaign ID.
             * @param array $update Updated fields.
             */
            do_action( 'ynj_campaign_updated', $id, $update );
        }

        return $result !== false;
    }

    // ================================================================
    // DONATIONS
    // ================================================================

    /**
     * Get donations for a mosque, with optional filters.
     *
     * @param  int    $mosque_id
     * @param  array  $args {
     *     @type string $status     Filter by status (default 'succeeded').
     *     @type string $fund_type  Filter by fund type slug.
     *     @type int    $limit      Max rows (default 50).
     *     @type int    $offset     Offset for pagination (default 0).
     * }
     * @return array
     */
    public static function get_donations( $mosque_id, $args = [] ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'donations' );
        $mosque_id = absint( $mosque_id );

        $status    = sanitize_text_field( $args['status'] ?? 'succeeded' );
        $fund_type = sanitize_text_field( $args['fund_type'] ?? '' );
        $limit     = absint( $args['limit'] ?? 50 );
        $offset    = absint( $args['offset'] ?? 0 );

        $where  = [ 'mosque_id = %d' ];
        $params = [ $mosque_id ];

        if ( $status !== 'all' ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ( $fund_type ) {
            $where[]  = 'fund_type = %s';
            $params[] = $fund_type;
        }

        $where_sql = implode( ' AND ', $where );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge( $params, [ $limit, $offset ] )
        ) );
    }

    /**
     * Record a donation (inserts with pending status).
     *
     * @param  array $data {
     *     @type int    $mosque_id
     *     @type string $donor_name
     *     @type string $donor_email
     *     @type int    $amount_pence
     *     @type string $currency      Default 'gbp'.
     *     @type string $fund_type     Default 'welfare'.
     *     @type string $frequency     'once'|'week'|'month'.
     *     @type bool   $is_recurring
     * }
     * @return int|false  Donation ID or false on failure.
     */
    public static function record_donation( $data ) {
        global $wpdb;
        $table = YNJ_DB::table( 'donations' );

        $mosque_id    = absint( $data['mosque_id'] ?? 0 );
        $amount_pence = absint( $data['amount_pence'] ?? 0 );
        $email        = sanitize_email( $data['donor_email'] ?? '' );

        if ( ! $mosque_id || $amount_pence < 100 || ! is_email( $email ) ) {
            return false;
        }

        $insert = [
            'mosque_id'    => $mosque_id,
            'donor_name'   => sanitize_text_field( $data['donor_name'] ?? '' ),
            'donor_email'  => $email,
            'amount_pence' => $amount_pence,
            'currency'     => strtolower( sanitize_text_field( $data['currency'] ?? 'gbp' ) ),
            'fund_type'    => sanitize_text_field( $data['fund_type'] ?? 'welfare' ),
            'frequency'    => sanitize_text_field( $data['frequency'] ?? 'once' ),
            'is_recurring' => ! empty( $data['is_recurring'] ) ? 1 : 0,
            'status'       => 'pending',
        ];

        $wpdb->insert( $table, $insert );
        $id = (int) $wpdb->insert_id;

        return $id ?: false;
    }

    /**
     * Mark a donation as succeeded and fire hooks.
     *
     * @param  int $donation_id
     * @return bool
     */
    public static function mark_succeeded( $donation_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'donations' );
        $donation_id = absint( $donation_id );

        $donation = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $donation_id
        ) );

        if ( ! $donation || $donation->status === 'succeeded' ) {
            return false;
        }

        $wpdb->update( $table, [ 'status' => 'succeeded' ], [ 'id' => $donation_id ] );

        // Update raised_pence on the fund
        self::increment_fund_raised( (int) $donation->mosque_id, $donation->fund_type, (int) $donation->amount_pence );

        /**
         * Fires when a donation payment is confirmed as succeeded.
         *
         * @param int    $donation_id  Donation row ID.
         * @param object $donation     Full donation row object.
         */
        do_action( 'ynj_donation_succeeded', $donation_id, $donation );

        return true;
    }

    /**
     * Mark a donation as failed.
     *
     * @param  int $donation_id
     * @return bool
     */
    public static function mark_failed( $donation_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'donations' );

        return $wpdb->update(
            $table,
            [ 'status' => 'failed' ],
            [ 'id' => absint( $donation_id ) ]
        ) !== false;
    }

    /**
     * Update Stripe IDs on a donation record.
     *
     * @param  int   $donation_id
     * @param  array $stripe_data {
     *     @type string $stripe_payment_intent
     *     @type string $stripe_customer_id
     *     @type string $stripe_subscription_id
     * }
     * @return bool
     */
    public static function update_stripe_ids( $donation_id, $stripe_data ) {
        global $wpdb;
        $table = YNJ_DB::table( 'donations' );

        $update = [];
        if ( isset( $stripe_data['stripe_payment_intent'] ) ) {
            $update['stripe_payment_intent'] = sanitize_text_field( $stripe_data['stripe_payment_intent'] );
        }
        if ( isset( $stripe_data['stripe_customer_id'] ) ) {
            $update['stripe_customer_id'] = sanitize_text_field( $stripe_data['stripe_customer_id'] );
        }
        if ( isset( $stripe_data['stripe_subscription_id'] ) ) {
            $update['stripe_subscription_id'] = sanitize_text_field( $stripe_data['stripe_subscription_id'] );
        }

        if ( empty( $update ) ) return true;

        return $wpdb->update( $table, $update, [ 'id' => absint( $donation_id ) ] ) !== false;
    }

    /**
     * Get a single donation by ID.
     *
     * @param  int $id
     * @return object|null
     */
    public static function get_donation( $id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'donations' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            absint( $id )
        ) );
    }

    /**
     * Find a donation by its Stripe PaymentIntent ID.
     *
     * @param  string $pi_id  Stripe PaymentIntent ID.
     * @return object|null
     */
    public static function get_by_payment_intent( $pi_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'donations' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE stripe_payment_intent = %s",
            sanitize_text_field( $pi_id )
        ) );
    }

    /**
     * Get donation stats for a mosque.
     *
     * Returns total raised, donor count, and per-fund breakdown.
     *
     * @param  int $mosque_id
     * @return array {
     *     @type int   $total_raised_pence   Total succeeded amount.
     *     @type int   $total_donors         Unique donor emails.
     *     @type int   $donation_count       Total succeeded donations.
     *     @type int   $recurring_count      Active recurring donors.
     *     @type int   $recurring_monthly_pence  Monthly recurring value.
     *     @type array $by_fund              Per-fund breakdown [ slug => [ raised, count ] ].
     * }
     */
    public static function get_donation_stats( $mosque_id ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'donations' );
        $mosque_id = absint( $mosque_id );

        // Totals
        $totals = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE(SUM(amount_pence), 0) AS total_raised_pence,
                COUNT(*) AS donation_count,
                COUNT(DISTINCT donor_email) AS total_donors
             FROM $table
             WHERE mosque_id = %d AND status = 'succeeded'",
            $mosque_id
        ) );

        // Recurring stats
        $recurring = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) AS recurring_count,
                COALESCE(SUM(amount_pence), 0) AS recurring_monthly_pence
             FROM $table
             WHERE mosque_id = %d AND status = 'succeeded' AND is_recurring = 1",
            $mosque_id
        ) );

        // Per-fund breakdown
        $funds_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT fund_type, SUM(amount_pence) AS raised, COUNT(*) AS cnt
             FROM $table
             WHERE mosque_id = %d AND status = 'succeeded'
             GROUP BY fund_type",
            $mosque_id
        ) );

        $by_fund = [];
        foreach ( $funds_raw as $f ) {
            $by_fund[ $f->fund_type ] = [
                'raised_pence' => (int) $f->raised,
                'count'        => (int) $f->cnt,
            ];
        }

        return [
            'total_raised_pence'    => (int) $totals->total_raised_pence,
            'total_donors'          => (int) $totals->total_donors,
            'donation_count'        => (int) $totals->donation_count,
            'recurring_count'       => (int) $recurring->recurring_count,
            'recurring_monthly_pence' => (int) $recurring->recurring_monthly_pence,
            'by_fund'               => $by_fund,
        ];
    }

    // ================================================================
    // PATRONS
    // ================================================================

    /**
     * Get all patrons for a mosque.
     *
     * @param  int    $mosque_id
     * @param  string $status  Filter: 'active', 'all', etc.
     * @return array
     */
    public static function get_patrons( $mosque_id, $status = 'all' ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'patrons' );
        $mosque_id = absint( $mosque_id );

        if ( $status === 'all' ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table WHERE mosque_id = %d ORDER BY status ASC, amount_pence DESC, created_at DESC",
                $mosque_id
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE mosque_id = %d AND status = %s ORDER BY amount_pence DESC, created_at DESC",
            $mosque_id,
            sanitize_text_field( $status )
        ) );
    }

    /**
     * Get patron status for a specific user at a specific mosque.
     *
     * @param  int $user_id
     * @param  int $mosque_id
     * @return object|null  Patron row or null if no record exists.
     */
    public static function get_patron_status( $user_id, $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND mosque_id = %d",
            absint( $user_id ),
            absint( $mosque_id )
        ) );
    }

    /**
     * Check if a user is an active patron of a mosque.
     *
     * @param  int $user_id
     * @param  int $mosque_id
     * @return bool
     */
    public static function is_patron( $user_id, $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );

        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM $table WHERE user_id = %d AND mosque_id = %d",
            absint( $user_id ),
            absint( $mosque_id )
        ) );

        return $status === 'active';
    }

    /**
     * Create or update a patron record.
     *
     * Upserts based on the unique (mosque_id, user_id) key.
     *
     * @param  array $data {
     *     @type int    $mosque_id
     *     @type int    $user_id
     *     @type string $user_name
     *     @type string $user_email
     *     @type string $tier          'supporter'|'guardian'|'champion'|'platinum'
     *     @type int    $amount_pence
     * }
     * @return int|false  Patron ID or false.
     */
    public static function upsert_patron( $data ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'patrons' );
        $mosque_id = absint( $data['mosque_id'] ?? 0 );
        $user_id   = absint( $data['user_id'] ?? 0 );

        if ( ! $mosque_id || ! $user_id ) return false;

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM $table WHERE mosque_id = %d AND user_id = %d",
            $mosque_id, $user_id
        ) );

        $fields = [
            'tier'         => sanitize_text_field( $data['tier'] ?? 'supporter' ),
            'amount_pence' => absint( $data['amount_pence'] ?? 500 ),
            'user_name'    => sanitize_text_field( $data['user_name'] ?? '' ),
            'user_email'   => sanitize_email( $data['user_email'] ?? '' ),
            'status'       => 'pending_payment',
        ];

        if ( $existing ) {
            $wpdb->update( $table, $fields, [ 'id' => $existing->id ] );
            return (int) $existing->id;
        }

        $wpdb->insert( $table, array_merge( $fields, [
            'mosque_id' => $mosque_id,
            'user_id'   => $user_id,
        ] ) );

        return (int) $wpdb->insert_id ?: false;
    }

    /**
     * Activate a patron (after payment confirmed).
     *
     * @param  int    $patron_id
     * @param  string $stripe_customer_id
     * @param  string $stripe_subscription_id
     * @return bool
     */
    public static function activate_patron( $patron_id, $stripe_customer_id = '', $stripe_subscription_id = '' ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'patrons' );
        $patron_id = absint( $patron_id );

        $patron = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $patron_id
        ) );

        if ( ! $patron ) return false;

        $update = [
            'status'     => 'active',
            'started_at' => current_time( 'mysql', true ),
        ];
        if ( $stripe_customer_id ) {
            $update['stripe_customer_id'] = sanitize_text_field( $stripe_customer_id );
        }
        if ( $stripe_subscription_id ) {
            $update['stripe_subscription_id'] = sanitize_text_field( $stripe_subscription_id );
        }

        $wpdb->update( $table, $update, [ 'id' => $patron_id ] );

        /**
         * Fires when a new patron membership becomes active.
         *
         * @param int    $patron_id  Patron row ID.
         * @param object $patron     Full patron row (pre-update values).
         */
        do_action( 'ynj_new_patron', $patron_id, $patron );

        return true;
    }

    /**
     * Cancel a patron membership.
     *
     * @param  int $patron_id
     * @return bool
     */
    public static function cancel_patron( $patron_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'patrons' );

        return $wpdb->update( $table, [
            'status'       => 'cancelled',
            'cancelled_at' => current_time( 'mysql', true ),
        ], [ 'id' => absint( $patron_id ) ] ) !== false;
    }

    /**
     * Get patron summary stats for a mosque.
     *
     * @param  int $mosque_id
     * @return array { total_active, monthly_pence }
     */
    public static function get_patron_stats( $mosque_id ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'patrons' );
        $mosque_id = absint( $mosque_id );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS total_active, COALESCE(SUM(amount_pence), 0) AS monthly_pence
             FROM $table
             WHERE mosque_id = %d AND status = 'active'",
            $mosque_id
        ) );

        return [
            'total_active'  => (int) $row->total_active,
            'monthly_pence' => (int) $row->monthly_pence,
        ];
    }

    // ================================================================
    // FUNDS
    // ================================================================

    /**
     * Get fund types for a mosque (active only).
     *
     * Seeds defaults if the mosque has none.
     *
     * @param  int $mosque_id
     * @return array
     */
    public static function get_fund_types( $mosque_id ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'mosque_funds' );
        $mosque_id = absint( $mosque_id );

        $funds = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, slug, label, description, target_pence, raised_pence, is_default, sort_order
             FROM $table WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
            $mosque_id
        ) );

        // Seed defaults if none exist
        if ( empty( $funds ) ) {
            foreach ( YNJ_DB::default_fund_types() as $fund ) {
                $wpdb->insert( $table, array_merge( $fund, [ 'mosque_id' => $mosque_id ] ) );
            }
            $funds = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, slug, label, description, target_pence, raised_pence, is_default, sort_order
                 FROM $table WHERE mosque_id = %d AND is_active = 1 ORDER BY is_default DESC, sort_order ASC",
                $mosque_id
            ) );
        }

        return $funds;
    }

    /**
     * Get the balance (raised_pence) for a specific fund type at a mosque.
     *
     * @param  int    $mosque_id
     * @param  string $fund_slug  Fund slug (e.g. 'welfare', 'general').
     * @return int    Raised amount in pence.
     */
    public static function get_fund_balance( $mosque_id, $fund_slug ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'mosque_funds' );
        $mosque_id = absint( $mosque_id );

        $raised = $wpdb->get_var( $wpdb->prepare(
            "SELECT raised_pence FROM $table WHERE mosque_id = %d AND slug = %s AND is_active = 1",
            $mosque_id,
            sanitize_text_field( $fund_slug )
        ) );

        return (int) $raised;
    }

    /**
     * Create a custom fund for a mosque.
     *
     * @param  int    $mosque_id
     * @param  array  $data { label, slug, description, target_pence }
     * @return int|false  Fund ID or false.
     */
    public static function create_fund( $mosque_id, $data ) {
        global $wpdb;
        $table     = YNJ_DB::table( 'mosque_funds' );
        $mosque_id = absint( $mosque_id );

        $label = sanitize_text_field( $data['label'] ?? '' );
        if ( ! $label || ! $mosque_id ) return false;

        $max_order = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sort_order) FROM $table WHERE mosque_id = %d",
            $mosque_id
        ) );

        $wpdb->insert( $table, [
            'mosque_id'    => $mosque_id,
            'slug'         => sanitize_title( $data['slug'] ?? $label ),
            'label'        => $label,
            'description'  => sanitize_text_field( $data['description'] ?? '' ),
            'target_pence' => absint( $data['target_pence'] ?? 0 ),
            'is_default'   => 0,
            'sort_order'   => $max_order + 1,
        ] );

        $id = (int) $wpdb->insert_id;

        if ( $id ) {
            do_action( 'ynj_fund_created', $id, $mosque_id );
        }

        return $id ?: false;
    }

    /**
     * Update a fund.
     *
     * @param  int   $fund_id
     * @param  int   $mosque_id  Ownership check.
     * @param  array $data       Fields to update.
     * @return bool
     */
    public static function update_fund( $fund_id, $mosque_id, $data ) {
        global $wpdb;
        $table   = YNJ_DB::table( 'mosque_funds' );
        $fund_id = absint( $fund_id );

        $fund = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            $fund_id, absint( $mosque_id )
        ) );
        if ( ! $fund ) return false;

        $update = [];
        if ( isset( $data['label'] ) )        $update['label']        = sanitize_text_field( $data['label'] );
        if ( isset( $data['description'] ) )  $update['description']  = sanitize_text_field( $data['description'] );
        if ( isset( $data['target_pence'] ) ) $update['target_pence'] = absint( $data['target_pence'] );
        if ( isset( $data['is_active'] ) )    $update['is_active']    = absint( $data['is_active'] );
        if ( isset( $data['sort_order'] ) )   $update['sort_order']   = absint( $data['sort_order'] );

        if ( empty( $update ) ) return true;

        return $wpdb->update( $table, $update, [ 'id' => $fund_id ] ) !== false;
    }

    /**
     * Deactivate a fund (soft delete). Cannot deactivate the default fund.
     *
     * @param  int $fund_id
     * @param  int $mosque_id  Ownership check.
     * @return bool|WP_Error
     */
    public static function deactivate_fund( $fund_id, $mosque_id ) {
        global $wpdb;
        $table = YNJ_DB::table( 'mosque_funds' );

        $fund = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND mosque_id = %d",
            absint( $fund_id ), absint( $mosque_id )
        ) );

        if ( ! $fund ) return false;

        if ( $fund->is_default ) {
            return new \WP_Error( 'cannot_delete', 'Cannot remove the default General Donation fund.' );
        }

        return $wpdb->update( $table, [ 'is_active' => 0 ], [ 'id' => $fund->id ] ) !== false;
    }

    /**
     * Increment raised_pence on a mosque fund row.
     *
     * @param  int    $mosque_id
     * @param  string $fund_slug
     * @param  int    $amount_pence
     * @return bool
     */
    public static function increment_fund_raised( $mosque_id, $fund_slug, $amount_pence ) {
        global $wpdb;
        $table        = YNJ_DB::table( 'mosque_funds' );
        $mosque_id    = absint( $mosque_id );
        $amount_pence = absint( $amount_pence );

        if ( ! $amount_pence ) return false;

        $result = $wpdb->query( $wpdb->prepare(
            "UPDATE $table SET raised_pence = raised_pence + %d WHERE mosque_id = %d AND slug = %s",
            $amount_pence,
            $mosque_id,
            sanitize_text_field( $fund_slug )
        ) );

        return $result !== false;
    }
}
