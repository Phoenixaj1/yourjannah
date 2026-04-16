<?php
/**
 * Dashboard Section: Announcements CRUD
 * Create, edit, delete, pin announcements. All PHP.
 */

$at = YNJ_DB::table( 'announcements' );

// Handle POST actions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_ann' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    if ( $action === 'create' || $action === 'update' ) {
        $data = [
            'mosque_id'    => $mosque_id,
            'title'        => sanitize_text_field( $_POST['title'] ?? '' ),
            'body'         => sanitize_textarea_field( $_POST['body'] ?? '' ),
            'type'         => sanitize_text_field( $_POST['type'] ?? 'general' ),
            'pinned'       => (int) ( $_POST['pinned'] ?? 0 ),
            'status'       => sanitize_text_field( $_POST['status'] ?? 'published' ),
            'published_at' => current_time( 'mysql' ),
        ];
        if ( ! $data['title'] ) {
            $error = __( 'Title is required.', 'yourjannah' );
        } else {
            if ( $action === 'create' ) {
                $wpdb->insert( $at, $data );
                $success = __( 'Announcement created!', 'yourjannah' );
            } else {
                $ann_id = (int) ( $_POST['ann_id'] ?? 0 );
                unset( $data['mosque_id'], $data['published_at'] );
                $wpdb->update( $at, $data, [ 'id' => $ann_id, 'mosque_id' => $mosque_id ] );
                $success = __( 'Announcement updated!', 'yourjannah' );
            }
        }
    }

    if ( $action === 'delete' ) {
        $ann_id = (int) ( $_POST['ann_id'] ?? 0 );
        $wpdb->delete( $at, [ 'id' => $ann_id, 'mosque_id' => $mosque_id ] );
        $success = __( 'Announcement deleted.', 'yourjannah' );
    }
}

// Load announcements
$announcements = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $at WHERE mosque_id = %d ORDER BY pinned DESC, published_at DESC LIMIT 50",
    $mosque_id
) ) ?: [];

// Editing?
$editing = null;
$edit_id = (int) ( $_GET['edit'] ?? 0 );
if ( $edit_id ) {
    $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $at WHERE id = %d AND mosque_id = %d", $edit_id, $mosque_id ) );
}
?>

<div class="d-header">
    <h1>📢 <?php esc_html_e( 'Announcements', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Keep your congregation informed with updates and news.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Create/Edit Form -->
<div class="d-card">
    <h3><?php echo $editing ? '✏️ ' . esc_html__( 'Edit Announcement', 'yourjannah' ) : '➕ ' . esc_html__( 'New Announcement', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_ann', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ( $editing ) : ?><input type="hidden" name="ann_id" value="<?php echo (int) $editing->id; ?>"><?php endif; ?>

        <div class="d-field">
            <label><?php esc_html_e( 'Title *', 'yourjannah' ); ?></label>
            <input type="text" name="title" value="<?php echo esc_attr( $editing->title ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'e.g. Jumu\'ah time change this week', 'yourjannah' ); ?>">
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Message', 'yourjannah' ); ?></label>
            <textarea name="body" rows="4" placeholder="<?php esc_attr_e( 'Write your announcement...', 'yourjannah' ); ?>"><?php echo esc_textarea( $editing->body ?? '' ); ?></textarea>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Type', 'yourjannah' ); ?></label>
                <select name="type">
                    <option value="general" <?php selected( $editing->type ?? '', 'general' ); ?>><?php esc_html_e( 'General', 'yourjannah' ); ?></option>
                    <option value="urgent" <?php selected( $editing->type ?? '', 'urgent' ); ?>><?php esc_html_e( 'Urgent', 'yourjannah' ); ?></option>
                    <option value="event" <?php selected( $editing->type ?? '', 'event' ); ?>><?php esc_html_e( 'Event Related', 'yourjannah' ); ?></option>
                </select>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Status', 'yourjannah' ); ?></label>
                <select name="status">
                    <option value="published" <?php selected( $editing->status ?? '', 'published' ); ?>><?php esc_html_e( 'Published', 'yourjannah' ); ?></option>
                    <option value="draft" <?php selected( $editing->status ?? '', 'draft' ); ?>><?php esc_html_e( 'Draft', 'yourjannah' ); ?></option>
                </select>
            </div>
        </div>

        <div class="d-field">
            <label><input type="checkbox" name="pinned" value="1" <?php checked( $editing->pinned ?? 0, 1 ); ?>> <?php esc_html_e( 'Pin to top', 'yourjannah' ); ?></label>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="d-btn d-btn--primary"><?php echo $editing ? esc_html__( 'Update', 'yourjannah' ) : esc_html__( 'Create Announcement', 'yourjannah' ); ?></button>
            <?php if ( $editing ) : ?>
            <a href="?section=announcements" class="d-btn d-btn--outline"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Announcements List -->
<?php if ( empty( $announcements ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">📢</div><p><?php esc_html_e( 'No announcements yet. Create your first one above!', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th><?php esc_html_e( 'Title', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Type', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Date', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Actions', 'yourjannah' ); ?></th></tr></thead>
        <tbody>
        <?php foreach ( $announcements as $a ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $a->title ); ?></strong><?php if ( $a->pinned ) echo ' <span class="d-badge d-badge--blue">📌 Pinned</span>'; ?></td>
            <td><span class="d-badge d-badge--<?php echo $a->type === 'urgent' ? 'red' : 'gray'; ?>"><?php echo esc_html( ucfirst( $a->type ) ); ?></span></td>
            <td><span class="d-badge d-badge--<?php echo $a->status === 'published' ? 'green' : 'yellow'; ?>"><?php echo esc_html( ucfirst( $a->status ) ); ?></span></td>
            <td style="font-size:12px;"><?php echo esc_html( substr( $a->published_at, 0, 10 ) ); ?></td>
            <td>
                <a href="?section=announcements&edit=<?php echo (int) $a->id; ?>" class="d-btn d-btn--sm d-btn--outline">✏️</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this announcement?')">
                    <?php wp_nonce_field( 'ynj_dash_ann', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="ann_id" value="<?php echo (int) $a->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
