<?php
/**
 * Dashboard Section: Announcements CRUD
 * Create, edit, delete, pin announcements. All PHP.
 * Supports imam submissions with optional admin approval workflow.
 */

$at = YNJ_DB::table( 'announcements' );
$mt = YNJ_DB::table( 'mosques' );

// Get current user role context
$current_wp_user = wp_get_current_user();
$is_imam = in_array( 'ynj_imam', (array) $current_wp_user->roles, true );
$is_admin = current_user_can( 'ynj_manage_mosque' ) || current_user_can( 'manage_options' );

// Get mosque imam settings
$mosque_data = $wpdb->get_row( $wpdb->prepare( "SELECT imam_user_id, imam_auto_publish FROM $mt WHERE id = %d", $mosque_id ) );
$imam_auto_publish = $mosque_data ? (bool) $mosque_data->imam_auto_publish : false;

// Handle POST actions
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_ann' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    if ( $action === 'create' || $action === 'update' ) {
        $requested_status = sanitize_text_field( $_POST['status'] ?? 'published' );

        // Imam approval logic
        $approval_status = 'approved';
        if ( $is_imam && ! $is_admin ) {
            if ( ! $imam_auto_publish && $requested_status === 'published' ) {
                $requested_status = 'draft'; // Keep as draft until approved
                $approval_status = 'pending';
            }
        }

        $data = [
            'mosque_id'       => $mosque_id,
            'title'           => sanitize_text_field( $_POST['title'] ?? '' ),
            'body'            => sanitize_textarea_field( $_POST['body'] ?? '' ),
            'type'            => sanitize_text_field( $_POST['type'] ?? 'general' ),
            'pinned'          => (int) ( $_POST['pinned'] ?? 0 ),
            'author_user_id'  => get_current_user_id(),
            'author_role'     => $is_imam ? 'imam' : 'admin',
            'approval_status' => $approval_status,
            'status'          => $requested_status,
            'published_at'    => current_time( 'mysql' ),
        ];

        // Handle image upload
        if ( ! empty( $_FILES['image']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $upload = wp_handle_upload( $_FILES['image'], [ 'test_form' => false ] );
            if ( ! empty( $upload['url'] ) ) {
                $data['image_url'] = esc_url_raw( $upload['url'] );
            }
        }

        if ( ! $data['title'] ) {
            $error = __( 'Title is required.', 'yourjannah' );
        } else {
            if ( $action === 'create' ) {
                $wpdb->insert( $at, $data );
                if ( $approval_status === 'pending' ) {
                    $success = __( 'Message submitted for admin approval.', 'yourjannah' );
                } else {
                    $success = __( 'Announcement created!', 'yourjannah' );
                }
            } else {
                $ann_id = (int) ( $_POST['ann_id'] ?? 0 );
                unset( $data['mosque_id'], $data['published_at'] );
                // Imam can only edit their own posts
                $where = [ 'id' => $ann_id, 'mosque_id' => $mosque_id ];
                if ( $is_imam && ! $is_admin ) {
                    $where['author_user_id'] = get_current_user_id();
                }
                $wpdb->update( $at, $data, $where );
                $success = __( 'Announcement updated!', 'yourjannah' );
            }
        }
    }

    if ( $action === 'delete' ) {
        $ann_id = (int) ( $_POST['ann_id'] ?? 0 );
        $where = [ 'id' => $ann_id, 'mosque_id' => $mosque_id ];
        if ( $is_imam && ! $is_admin ) {
            $where['author_user_id'] = get_current_user_id();
        }
        $wpdb->delete( $at, $where );
        $success = __( 'Announcement deleted.', 'yourjannah' );
    }

    // Admin approval actions
    if ( $is_admin && $action === 'approve' ) {
        $ann_id = (int) ( $_POST['ann_id'] ?? 0 );
        $wpdb->update( $at, [
            'approval_status' => 'approved',
            'approved_by'     => get_current_user_id(),
            'approved_at'     => current_time( 'mysql' ),
            'status'          => 'published',
            'published_at'    => current_time( 'mysql' ),
        ], [ 'id' => $ann_id, 'mosque_id' => $mosque_id ] );
        $success = __( 'Announcement approved and published!', 'yourjannah' );
    }

    if ( $is_admin && $action === 'reject' ) {
        $ann_id = (int) ( $_POST['ann_id'] ?? 0 );
        $wpdb->update( $at, [
            'approval_status' => 'rejected',
            'approved_by'     => get_current_user_id(),
            'approved_at'     => current_time( 'mysql' ),
        ], [ 'id' => $ann_id, 'mosque_id' => $mosque_id ] );
        $success = __( 'Announcement rejected.', 'yourjannah' );
    }
}

// Pagination
$per_page = 20;
$current_page = max( 1, (int) ( $_GET['pg'] ?? 1 ) );
$offset = ( $current_page - 1 ) * $per_page;

// Load announcements (imam only sees their own)
if ( $is_imam && ! $is_admin ) {
    $total_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $at WHERE mosque_id = %d AND author_user_id = %d",
        $mosque_id, get_current_user_id()
    ) );
    $announcements = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $at WHERE mosque_id = %d AND author_user_id = %d ORDER BY pinned DESC, published_at DESC LIMIT %d OFFSET %d",
        $mosque_id, get_current_user_id(), $per_page, $offset
    ) ) ?: [];
} else {
    $total_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $at WHERE mosque_id = %d",
        $mosque_id
    ) );
    $announcements = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $at WHERE mosque_id = %d ORDER BY pinned DESC, published_at DESC LIMIT %d OFFSET %d",
        $mosque_id, $per_page, $offset
    ) ) ?: [];
}
$total_pages = max( 1, (int) ceil( $total_count / $per_page ) );

// Count pending approvals (admin only)
$pending_count = 0;
if ( $is_admin ) {
    $pending_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $at WHERE mosque_id = %d AND approval_status = 'pending'",
        $mosque_id
    ) );
}

// Editing?
$editing = null;
$edit_id = (int) ( $_GET['edit'] ?? 0 );
if ( $edit_id ) {
    $where_sql = $is_imam && ! $is_admin
        ? $wpdb->prepare( "SELECT * FROM $at WHERE id = %d AND mosque_id = %d AND author_user_id = %d", $edit_id, $mosque_id, get_current_user_id() )
        : $wpdb->prepare( "SELECT * FROM $at WHERE id = %d AND mosque_id = %d", $edit_id, $mosque_id );
    $editing = $wpdb->get_row( $where_sql );
}

// Tab handling
$tab = sanitize_text_field( $_GET['tab'] ?? 'all' );
?>

<div class="d-header">
    <h1>📢 <?php esc_html_e( 'Announcements', 'yourjannah' ); ?></h1>
    <p>
        <?php if ( $is_imam && ! $is_admin ) : ?>
            <?php esc_html_e( 'Post religious messages and reminders to the congregation.', 'yourjannah' ); ?>
            <?php if ( ! $imam_auto_publish ) : ?>
                <br><small style="color:#e67e22;">⚠️ <?php esc_html_e( 'Your posts require admin approval before publishing.', 'yourjannah' ); ?></small>
            <?php endif; ?>
        <?php else : ?>
            <?php esc_html_e( 'Keep your congregation informed with updates and news.', 'yourjannah' ); ?>
        <?php endif; ?>
    </p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Tabs (admin only — imam doesn't need tabs) -->
<?php if ( $is_admin && $pending_count > 0 ) : ?>
<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="?section=announcements&tab=all" class="d-btn <?php echo $tab === 'all' ? 'd-btn--primary' : 'd-btn--outline'; ?>" style="font-size:13px;">
        📋 <?php esc_html_e( 'All', 'yourjannah' ); ?>
    </a>
    <a href="?section=announcements&tab=pending" class="d-btn <?php echo $tab === 'pending' ? 'd-btn--primary' : 'd-btn--outline'; ?>" style="font-size:13px;">
        ⏳ <?php esc_html_e( 'Pending Approval', 'yourjannah' ); ?>
        <span style="background:#e74c3c;color:#fff;border-radius:50%;min-width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-left:4px;"><?php echo $pending_count; ?></span>
    </a>
</div>
<?php endif; ?>

<?php if ( $is_admin && $tab === 'pending' ) : ?>
<!-- Pending Approval Queue -->
<?php
$pending_items = $wpdb->get_results( $wpdb->prepare(
    "SELECT a.*, u.display_name AS author_name FROM $at a LEFT JOIN {$wpdb->users} u ON u.ID = a.author_user_id WHERE a.mosque_id = %d AND a.approval_status = 'pending' ORDER BY a.created_at DESC",
    $mosque_id
) ) ?: [];
?>
<?php if ( empty( $pending_items ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">✅</div><p><?php esc_html_e( 'No pending approvals!', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<?php foreach ( $pending_items as $p ) : ?>
<div class="d-card" style="margin-bottom:12px;">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
        <div>
            <h3 style="margin:0 0 4px;"><?php echo esc_html( $p->title ); ?></h3>
            <span class="d-badge d-badge--blue"><?php echo esc_html( ucfirst( $p->type ) ); ?></span>
            <span style="font-size:12px;color:#666;margin-left:8px;">
                <?php esc_html_e( 'by', 'yourjannah' ); ?> <strong><?php echo esc_html( $p->author_name ?: 'Imam' ); ?></strong>
                &middot; <?php echo esc_html( human_time_diff( strtotime( $p->created_at ) ) ); ?> <?php esc_html_e( 'ago', 'yourjannah' ); ?>
            </span>
        </div>
    </div>
    <?php if ( $p->body ) : ?>
    <p style="color:#444;margin-bottom:12px;white-space:pre-wrap;"><?php echo esc_html( $p->body ); ?></p>
    <?php endif; ?>
    <div style="display:flex;gap:8px;">
        <form method="post" style="display:inline;">
            <?php wp_nonce_field( 'ynj_dash_ann', '_ynj_nonce' ); ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="ann_id" value="<?php echo (int) $p->id; ?>">
            <button type="submit" class="d-btn d-btn--primary" style="background:#27ae60;">✅ <?php esc_html_e( 'Approve & Publish', 'yourjannah' ); ?></button>
        </form>
        <a href="?section=announcements&edit=<?php echo (int) $p->id; ?>" class="d-btn d-btn--outline">✏️ <?php esc_html_e( 'Edit First', 'yourjannah' ); ?></a>
        <form method="post" style="display:inline;" onsubmit="return confirm('Reject this message?')">
            <?php wp_nonce_field( 'ynj_dash_ann', '_ynj_nonce' ); ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="ann_id" value="<?php echo (int) $p->id; ?>">
            <button type="submit" class="d-btn d-btn--sm d-btn--danger">❌ <?php esc_html_e( 'Reject', 'yourjannah' ); ?></button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php else : ?>

<!-- Create/Edit Form -->
<div class="d-card">
    <h3><?php echo $editing ? '✏️ ' . esc_html__( 'Edit Announcement', 'yourjannah' ) : '➕ ' . esc_html__( 'New Announcement', 'yourjannah' ); ?></h3>
    <form method="post" enctype="multipart/form-data">
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

        <div class="d-field">
            <label><?php esc_html_e( 'Image (optional)', 'yourjannah' ); ?></label>
            <?php if ( $editing && ! empty( $editing->image_url ) ) : ?>
            <div style="margin-bottom:8px;">
                <img src="<?php echo esc_url( $editing->image_url ); ?>" alt="" style="max-width:200px;max-height:120px;border-radius:6px;border:1px solid #e5e7eb;">
            </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Type', 'yourjannah' ); ?></label>
                <select name="type">
                    <option value="general" <?php selected( $editing->type ?? '', 'general' ); ?>><?php esc_html_e( 'General', 'yourjannah' ); ?></option>
                    <option value="urgent" <?php selected( $editing->type ?? '', 'urgent' ); ?>><?php esc_html_e( 'Urgent', 'yourjannah' ); ?></option>
                    <option value="event" <?php selected( $editing->type ?? '', 'event' ); ?>><?php esc_html_e( 'Event Related', 'yourjannah' ); ?></option>
                    <option value="religious" <?php selected( $editing->type ?? '', 'religious' ); ?>><?php esc_html_e( 'Religious / Hadith', 'yourjannah' ); ?></option>
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
            <button type="submit" class="d-btn d-btn--primary">
                <?php
                if ( $editing ) {
                    esc_html_e( 'Update', 'yourjannah' );
                } elseif ( $is_imam && ! $is_admin && ! $imam_auto_publish ) {
                    esc_html_e( 'Submit for Approval', 'yourjannah' );
                } else {
                    esc_html_e( 'Create Announcement', 'yourjannah' );
                }
                ?>
            </button>
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
        <thead><tr>
            <th><?php esc_html_e( 'Title', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Type', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th>
            <?php if ( $is_admin ) : ?><th><?php esc_html_e( 'Author', 'yourjannah' ); ?></th><?php endif; ?>
            <th><?php esc_html_e( 'Date', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'yourjannah' ); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ( $announcements as $a ) :
            $author_role = $a->author_role ?? 'admin';
            $approval = $a->approval_status ?? 'approved';
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html( $a->title ); ?></strong>
                <?php if ( $a->pinned ) echo ' <span class="d-badge d-badge--blue">📌 Pinned</span>'; ?>
                <?php if ( $approval === 'pending' ) echo ' <span class="d-badge d-badge--yellow">⏳ Pending</span>'; ?>
                <?php if ( $approval === 'rejected' ) echo ' <span class="d-badge d-badge--red">❌ Rejected</span>'; ?>
            </td>
            <td><span class="d-badge d-badge--<?php echo $a->type === 'urgent' ? 'red' : ( $a->type === 'religious' ? 'blue' : 'gray' ); ?>"><?php echo esc_html( ucfirst( $a->type ) ); ?></span></td>
            <td><span class="d-badge d-badge--<?php echo $a->status === 'published' ? 'green' : 'yellow'; ?>"><?php echo esc_html( ucfirst( $a->status ) ); ?></span></td>
            <?php if ( $is_admin ) : ?>
            <td>
                <span class="d-badge d-badge--<?php echo $author_role === 'imam' ? 'blue' : 'gray'; ?>" style="font-size:11px;">
                    <?php echo $author_role === 'imam' ? '🕌 Imam' : '👤 Admin'; ?>
                </span>
            </td>
            <?php endif; ?>
            <td style="font-size:12px;"><?php echo esc_html( substr( $a->published_at ?: $a->created_at, 0, 10 ) ); ?></td>
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
<?php if ( $total_pages > 1 ) :
    $tab_param = $tab && $tab !== 'all' ? '&tab=' . urlencode( $tab ) : '';
?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:16px;">
    <?php if ( $current_page > 1 ) : ?>
    <a href="?section=announcements<?php echo $tab_param; ?>&pg=<?php echo $current_page - 1; ?>" class="d-btn d-btn--outline d-btn--sm">&larr; Prev</a>
    <?php endif; ?>
    <span style="padding:6px 12px;font-size:13px;color:var(--text-dim);">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    <?php if ( $current_page < $total_pages ) : ?>
    <a href="?section=announcements<?php echo $tab_param; ?>&pg=<?php echo $current_page + 1; ?>" class="d-btn d-btn--outline d-btn--sm">Next &rarr;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php endif; /* end tab check */ ?>
