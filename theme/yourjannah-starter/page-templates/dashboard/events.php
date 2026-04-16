<?php
/**
 * Dashboard Section: Events CRUD
 */
$et = YNJ_DB::table( 'events' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_events' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    if ( $action === 'create' || $action === 'update' ) {
        $data = [
            'mosque_id'          => $mosque_id,
            'title'              => sanitize_text_field( $_POST['title'] ?? '' ),
            'description'        => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'event_date'         => sanitize_text_field( $_POST['event_date'] ?? '' ),
            'start_time'         => sanitize_text_field( $_POST['start_time'] ?? '' ),
            'end_time'           => sanitize_text_field( $_POST['end_time'] ?? '' ),
            'location'           => sanitize_text_field( $_POST['location'] ?? '' ),
            'category'           => sanitize_text_field( $_POST['category'] ?? 'community' ),
            'max_capacity'       => (int) ( $_POST['max_capacity'] ?? 0 ),
            'ticket_price_pence' => (int) ( floatval( $_POST['ticket_price'] ?? 0 ) * 100 ),
            'status'             => sanitize_text_field( $_POST['status'] ?? 'published' ),
        ];
        if ( ! $data['title'] || ! $data['event_date'] ) { $error = __( 'Title and date required.', 'yourjannah' ); }
        else {
            if ( $action === 'create' ) {
                $wpdb->insert( $et, $data );
                $created = 1;
                // Handle recurrence
                $repeat = sanitize_text_field( $_POST['repeat'] ?? '' );
                if ( $repeat && $data['event_date'] ) {
                    $intervals = [ 'weekly_4' => [ 7, 3 ], 'biweekly_4' => [ 14, 3 ], 'monthly_3' => [ 30, 2 ] ];
                    if ( isset( $intervals[ $repeat ] ) ) {
                        list( $gap_days, $extra ) = $intervals[ $repeat ];
                        $base_date = strtotime( $data['event_date'] );
                        for ( $r = 1; $r <= $extra; $r++ ) {
                            $next_date = date( 'Y-m-d', $base_date + ( $gap_days * $r * 86400 ) );
                            $repeat_data = $data;
                            $repeat_data['event_date'] = $next_date;
                            $wpdb->insert( $et, $repeat_data );
                            $created++;
                        }
                    }
                }
                $success = sprintf( __( '%d event(s) created!', 'yourjannah' ), $created );
            } else {
                $eid = (int) $_POST['event_id']; unset( $data['mosque_id'] );
                $wpdb->update( $et, $data, [ 'id' => $eid, 'mosque_id' => $mosque_id ] );
                $success = __( 'Event updated!', 'yourjannah' );
            }
        }
    }
    if ( $action === 'delete' ) { $wpdb->delete( $et, [ 'id' => (int) $_POST['event_id'], 'mosque_id' => $mosque_id ] ); $success = __( 'Event deleted.', 'yourjannah' ); }
}

$events = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $et WHERE mosque_id=%d ORDER BY event_date DESC LIMIT 50", $mosque_id ) ) ?: [];
$editing = null; $edit_id = (int) ( $_GET['edit'] ?? 0 );
if ( $edit_id ) $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $et WHERE id=%d AND mosque_id=%d", $edit_id, $mosque_id ) );
$cats = ['talk','class','course','workshop','community','sports','competition','youth','sisters','fundraiser','eid','quran','nikah','janazah','other'];
?>
<div class="d-header"><h1>📅 <?php esc_html_e( 'Events', 'yourjannah' ); ?></h1></div>
<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<div class="d-card">
    <h3><?php echo $editing ? '✏️ Edit Event' : '➕ New Event'; ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_events', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ( $editing ) : ?><input type="hidden" name="event_id" value="<?php echo (int) $editing->id; ?>"><?php endif; ?>
        <div class="d-field"><label>Title *</label><input type="text" name="title" value="<?php echo esc_attr( $editing->title ?? '' ); ?>" required></div>
        <div class="d-field"><label>Description</label><textarea name="description" rows="3"><?php echo esc_textarea( $editing->description ?? '' ); ?></textarea></div>
        <div class="d-row">
            <div class="d-field"><label>Date *</label><input type="date" name="event_date" value="<?php echo esc_attr( $editing->event_date ?? '' ); ?>" required></div>
            <div class="d-field"><label>Category</label><select name="category"><?php foreach ( $cats as $c ) : ?><option value="<?php echo $c; ?>" <?php selected( $editing->category ?? '', $c ); ?>><?php echo ucfirst( $c ); ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="d-row">
            <div class="d-field"><label>Start Time</label><input type="time" name="start_time" value="<?php echo esc_attr( $editing->start_time ?? '' ); ?>"></div>
            <div class="d-field"><label>End Time</label><input type="time" name="end_time" value="<?php echo esc_attr( $editing->end_time ?? '' ); ?>"></div>
        </div>
        <div class="d-row">
            <div class="d-field"><label>Location</label><input type="text" name="location" value="<?php echo esc_attr( $editing->location ?? '' ); ?>"></div>
            <div class="d-field"><label>Capacity (0=unlimited)</label><input type="number" name="max_capacity" min="0" value="<?php echo esc_attr( $editing->max_capacity ?? 0 ); ?>"></div>
        </div>
        <div class="d-row">
            <div class="d-field"><label>Ticket Price (£, 0=free)</label><input type="number" name="ticket_price" min="0" step="0.01" value="<?php echo esc_attr( ( $editing->ticket_price_pence ?? 0 ) / 100 ); ?>"></div>
            <div class="d-field"><label>Status</label><select name="status"><option value="published" <?php selected( $editing->status ?? '', 'published' ); ?>>Published</option><option value="draft" <?php selected( $editing->status ?? '', 'draft' ); ?>>Draft</option></select></div>
        </div>
        <?php if ( ! $editing ) : ?>
        <div class="d-field">
            <label><?php esc_html_e( 'Repeat', 'yourjannah' ); ?></label>
            <select name="repeat">
                <option value=""><?php esc_html_e( 'No repeat (one-off)', 'yourjannah' ); ?></option>
                <option value="weekly_4"><?php esc_html_e( 'Weekly for 4 weeks', 'yourjannah' ); ?></option>
                <option value="biweekly_4"><?php esc_html_e( 'Every 2 weeks for 4 times', 'yourjannah' ); ?></option>
                <option value="monthly_3"><?php esc_html_e( 'Monthly for 3 months', 'yourjannah' ); ?></option>
            </select>
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;"><button type="submit" class="d-btn d-btn--primary"><?php echo $editing ? 'Update' : 'Create Event'; ?></button><?php if ( $editing ) : ?><a href="?section=events" class="d-btn d-btn--outline">Cancel</a><?php endif; ?></div>
    </form>
</div>

<?php if ( ! empty( $events ) ) : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th>Event</th><th>Date</th><th>Time</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ( $events as $e ) : $past = $e->event_date < date( 'Y-m-d' ); ?>
        <tr<?php echo $past ? ' style="opacity:.5;"' : ''; ?>>
            <td><strong><?php echo esc_html( $e->title ); ?></strong><?php if ( $e->ticket_price_pence > 0 ) echo ' <span class="d-badge d-badge--green">£' . number_format( $e->ticket_price_pence / 100, 0 ) . '</span>'; ?></td>
            <td><?php echo esc_html( $e->event_date ); ?></td>
            <td><?php echo $e->start_time ? esc_html( substr( $e->start_time, 0, 5 ) ) : '—'; ?></td>
            <td><span class="d-badge d-badge--gray"><?php echo esc_html( ucfirst( $e->category ) ); ?></span></td>
            <td><span class="d-badge d-badge--<?php echo $e->status === 'published' ? 'green' : 'yellow'; ?>"><?php echo esc_html( ucfirst( $e->status ) ); ?></span></td>
            <td>
                <a href="?section=events&edit=<?php echo (int) $e->id; ?>" class="d-btn d-btn--sm d-btn--outline">✏️</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete?')">
                    <?php wp_nonce_field( 'ynj_dash_events', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?php echo (int) $e->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
