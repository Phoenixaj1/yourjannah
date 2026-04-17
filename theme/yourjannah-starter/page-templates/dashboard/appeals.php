<?php
/**
 * Dashboard Section: Charity Appeals Inbox
 * View, accept/decline incoming charity appeal requests. Pure PHP.
 */

$art = YNJ_DB::table( 'appeal_requests' );
$arp = YNJ_DB::table( 'appeal_responses' );

// Handle POST actions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_appeals' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    if ( $action === 'accept' ) {
        $appeal_id      = (int) ( $_POST['appeal_id'] ?? 0 );
        $mosque_fee      = (int) ( floatval( $_POST['mosque_fee'] ?? 0 ) * 100 ); // pounds to pence
        $scheduled_date  = sanitize_text_field( $_POST['scheduled_date'] ?? '' );
        $appeal_format   = sanitize_text_field( $_POST['appeal_format'] ?? '' );
        $message         = sanitize_textarea_field( $_POST['message'] ?? '' );

        if ( ! $appeal_id || ! $scheduled_date ) {
            $error = __( 'Please select a scheduled date.', 'yourjannah' );
        } else {
            // Check not already responded
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $arp WHERE appeal_id = %d AND mosque_id = %d",
                $appeal_id, $mosque_id
            ) );
            if ( $existing ) {
                $error = __( 'You have already responded to this appeal.', 'yourjannah' );
            } else {
                $wpdb->insert( $arp, [
                    'appeal_id'        => $appeal_id,
                    'mosque_id'        => $mosque_id,
                    'response'         => 'accepted',
                    'mosque_fee_pence' => $mosque_fee,
                    'message'          => $message,
                    'scheduled_date'   => $scheduled_date,
                    'appeal_format'    => $appeal_format,
                    'status'           => 'active',
                    'created_at'       => current_time( 'mysql' ),
                ] );
                $success = __( 'Appeal accepted! The charity will be notified.', 'yourjannah' );
            }
        }
    }

    if ( $action === 'decline' ) {
        $appeal_id = (int) ( $_POST['appeal_id'] ?? 0 );
        $message   = sanitize_textarea_field( $_POST['decline_message'] ?? '' );

        if ( $appeal_id ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $arp WHERE appeal_id = %d AND mosque_id = %d",
                $appeal_id, $mosque_id
            ) );
            if ( ! $existing ) {
                $wpdb->insert( $arp, [
                    'appeal_id'        => $appeal_id,
                    'mosque_id'        => $mosque_id,
                    'response'         => 'declined',
                    'mosque_fee_pence' => 0,
                    'message'          => $message,
                    'scheduled_date'   => null,
                    'appeal_format'    => '',
                    'status'           => 'declined',
                    'created_at'       => current_time( 'mysql' ),
                ] );
                $success = __( 'Appeal declined.', 'yourjannah' );
            }
        }
    }
}

// ── Stats ──
$pending_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM $art r
     WHERE r.status = 'active'
     AND r.id NOT IN (SELECT appeal_id FROM $arp WHERE mosque_id = %d)",
    $mosque_id
) );

$accepted_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM $arp WHERE mosque_id = %d AND response = 'accepted'",
    $mosque_id
) );

$appeal_revenue = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(mosque_fee_pence), 0) FROM $arp WHERE mosque_id = %d AND response = 'accepted'",
    $mosque_id
) );

// ── Pending appeals (active requests this mosque hasn't responded to) ──
$pending_appeals = $wpdb->get_results( $wpdb->prepare(
    "SELECT r.* FROM $art r
     WHERE r.status = 'active'
     AND r.id NOT IN (SELECT appeal_id FROM $arp WHERE mosque_id = %d)
     ORDER BY r.created_at DESC
     LIMIT 50",
    $mosque_id
) ) ?: [];

// ── Active (accepted) appeals ──
$active_appeals = $wpdb->get_results( $wpdb->prepare(
    "SELECT r.*, resp.mosque_fee_pence, resp.scheduled_date, resp.appeal_format, resp.message AS resp_message, resp.status AS resp_status
     FROM $arp resp
     JOIN $art r ON r.id = resp.appeal_id
     WHERE resp.mosque_id = %d AND resp.response = 'accepted'
     ORDER BY resp.scheduled_date ASC
     LIMIT 50",
    $mosque_id
) ) ?: [];

// Accepting an appeal? Show the accept form
$accepting_id = (int) ( $_GET['accept'] ?? 0 );
$accepting = null;
if ( $accepting_id ) {
    $accepting = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $art WHERE id = %d AND status = 'active'", $accepting_id
    ) );
}

// Appeal type badge colours
$type_badges = [
    'in_person' => 'blue',
    'recorded'  => 'yellow',
    'broadcast' => 'gray',
];
?>

<div class="d-header">
    <h1><?php esc_html_e( 'Charity Appeals', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Review incoming appeal requests from charities. Accept to schedule or decline.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success"><?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error"><?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Stats -->
<div class="d-stats">
    <div class="d-stat">
        <div class="d-stat__label"><?php esc_html_e( 'Pending Appeals', 'yourjannah' ); ?></div>
        <div class="d-stat__value" style="color:#f59e0b;"><?php echo $pending_count; ?></div>
    </div>
    <div class="d-stat">
        <div class="d-stat__label"><?php esc_html_e( 'Accepted', 'yourjannah' ); ?></div>
        <div class="d-stat__value" style="color:#16a34a;"><?php echo $accepted_count; ?></div>
    </div>
    <div class="d-stat">
        <div class="d-stat__label"><?php esc_html_e( 'Revenue from Appeals', 'yourjannah' ); ?></div>
        <div class="d-stat__value" style="color:#16a34a;">&pound;<?php echo number_format( $appeal_revenue / 100, 0 ); ?></div>
    </div>
</div>

<?php if ( $accepting ) : ?>
<!-- Accept Form -->
<div class="d-card" style="border-left:4px solid #16a34a;">
    <h3><?php printf( esc_html__( 'Accept Appeal: %s', 'yourjannah' ), esc_html( $accepting->cause_title ) ); ?></h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;">
        <?php printf( esc_html__( 'From %s — %s', 'yourjannah' ), esc_html( $accepting->charity_name ), esc_html( ucwords( str_replace( '_', ' ', $accepting->appeal_type ) ) ) ); ?>
    </p>

    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_appeals', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="accept">
        <input type="hidden" name="appeal_id" value="<?php echo (int) $accepting->id; ?>">

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Your Fee (&pound;)', 'yourjannah' ); ?></label>
                <input type="number" name="mosque_fee" min="0" step="1" value="0" placeholder="0">
                <p style="font-size:11px;color:var(--text-dim);margin-top:2px;"><?php esc_html_e( 'Amount in pounds the charity pays your mosque.', 'yourjannah' ); ?></p>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Scheduled Date *', 'yourjannah' ); ?></label>
                <input type="date" name="scheduled_date" required min="<?php echo date( 'Y-m-d' ); ?>">
            </div>
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Appeal Format', 'yourjannah' ); ?></label>
            <select name="appeal_format">
                <option value="in_person"><?php esc_html_e( 'In Person', 'yourjannah' ); ?></option>
                <option value="recorded"><?php esc_html_e( 'Recorded / Video', 'yourjannah' ); ?></option>
            </select>
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Message to Charity (optional)', 'yourjannah' ); ?></label>
            <textarea name="message" rows="3" placeholder="<?php esc_attr_e( 'e.g. Please arrive 30 minutes before Jumu\'ah...', 'yourjannah' ); ?>"></textarea>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Confirm Acceptance', 'yourjannah' ); ?></button>
            <a href="?section=appeals" class="d-btn d-btn--outline"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Pending Appeals -->
<div class="d-card">
    <h3><?php esc_html_e( 'Incoming Requests', 'yourjannah' ); ?> <span class="d-badge d-badge--yellow"><?php echo $pending_count; ?></span></h3>

    <?php if ( empty( $pending_appeals ) ) : ?>
    <div class="d-empty">
        <div class="d-empty__icon">&#128232;</div>
        <p><?php esc_html_e( 'No pending appeal requests. When charities request a slot at your mosque, they will appear here.', 'yourjannah' ); ?></p>
    </div>
    <?php else : ?>
    <table class="d-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Charity', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Cause', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Type', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Preferred Dates', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Received', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'yourjannah' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $pending_appeals as $a ) :
            $badge_color = $type_badges[ $a->appeal_type ] ?? 'gray';
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html( $a->charity_name ); ?></strong>
                <?php if ( $a->charity_reg_number ) : ?>
                <br><span style="font-size:11px;color:var(--text-dim);">Reg: <?php echo esc_html( $a->charity_reg_number ); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <strong><?php echo esc_html( $a->cause_title ); ?></strong>
                <?php if ( $a->cause_category ) : ?>
                <br><span style="font-size:11px;color:var(--text-dim);"><?php echo esc_html( ucfirst( $a->cause_category ) ); ?></span>
                <?php endif; ?>
            </td>
            <td><span class="d-badge d-badge--<?php echo esc_attr( $badge_color ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $a->appeal_type ) ) ); ?></span></td>
            <td style="font-size:12px;"><?php echo esc_html( $a->preferred_dates ); ?></td>
            <td style="font-size:12px;"><?php echo esc_html( substr( $a->created_at, 0, 10 ) ); ?></td>
            <td style="white-space:nowrap;">
                <a href="?section=appeals&accept=<?php echo (int) $a->id; ?>" class="d-btn d-btn--sm d-btn--primary"><?php esc_html_e( 'Accept', 'yourjannah' ); ?></a>
                <form method="post" style="display:inline;" onsubmit="return confirm('Decline this appeal request?')">
                    <?php wp_nonce_field( 'ynj_dash_appeals', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="decline">
                    <input type="hidden" name="appeal_id" value="<?php echo (int) $a->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger"><?php esc_html_e( 'Decline', 'yourjannah' ); ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Active (Accepted) Appeals -->
<?php if ( ! empty( $active_appeals ) ) : ?>
<div class="d-card" style="border-left:4px solid #16a34a;">
    <h3 style="color:#166534;"><?php esc_html_e( 'Active Appeals', 'yourjannah' ); ?> <span class="d-badge d-badge--green"><?php echo count( $active_appeals ); ?></span></h3>
    <table class="d-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Charity', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Cause', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Format', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Scheduled', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Fee', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $active_appeals as $a ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $a->charity_name ); ?></strong></td>
            <td><?php echo esc_html( $a->cause_title ); ?></td>
            <td><span class="d-badge d-badge--<?php echo $a->appeal_format === 'in_person' ? 'blue' : 'yellow'; ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $a->appeal_format ) ) ); ?></span></td>
            <td style="font-size:12px;"><?php echo esc_html( $a->scheduled_date ); ?></td>
            <td style="font-weight:700;color:#16a34a;">&pound;<?php echo number_format( $a->mosque_fee_pence / 100, 0 ); ?></td>
            <td><span class="d-badge d-badge--green"><?php echo esc_html( ucfirst( $a->resp_status ) ); ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
