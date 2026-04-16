<?php
/**
 * Dashboard Section: Classes CRUD
 */
$ct = YNJ_DB::table( 'classes' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_classes' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    if ( $action === 'create' || $action === 'update' ) {
        $data = [
            'mosque_id'       => $mosque_id,
            'title'           => sanitize_text_field( $_POST['title'] ?? '' ),
            'description'     => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'instructor_name' => sanitize_text_field( $_POST['instructor_name'] ?? '' ),
            'category'        => sanitize_text_field( $_POST['category'] ?? 'Quran' ),
            'day_of_week'     => sanitize_text_field( $_POST['day_of_week'] ?? '' ),
            'start_time'      => sanitize_text_field( $_POST['start_time'] ?? '' ),
            'end_time'        => sanitize_text_field( $_POST['end_time'] ?? '' ),
            'max_capacity'    => (int) ( $_POST['max_capacity'] ?? 0 ),
            'price_pence'     => (int) ( floatval( $_POST['price'] ?? 0 ) * 100 ),
            'status'          => sanitize_text_field( $_POST['status'] ?? 'active' ),
        ];
        if ( ! $data['title'] ) { $error = __( 'Class title required.', 'yourjannah' ); }
        elseif ( $action === 'create' ) {
            $wpdb->insert( $ct, $data );
            $success = __( 'Class created!', 'yourjannah' );
        } else {
            $cid = (int) $_POST['class_id'];
            unset( $data['mosque_id'] );
            $wpdb->update( $ct, $data, [ 'id' => $cid, 'mosque_id' => $mosque_id ] );
            $success = __( 'Class updated!', 'yourjannah' );
        }
    }
    if ( $action === 'delete' ) {
        $wpdb->delete( $ct, [ 'id' => (int) $_POST['class_id'], 'mosque_id' => $mosque_id ] );
        $success = __( 'Class deleted.', 'yourjannah' );
    }
}

$classes = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $ct WHERE mosque_id=%d ORDER BY status ASC, day_of_week ASC, start_time ASC",
    $mosque_id
) ) ?: [];

$editing = null;
$edit_id = (int) ( $_GET['edit'] ?? 0 );
if ( $edit_id ) {
    $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $ct WHERE id=%d AND mosque_id=%d", $edit_id, $mosque_id ) );
}

$categories = [ 'Quran', 'Arabic', 'Tajweed', 'Islamic Studies', 'Fiqh', 'Seerah', 'Hadith', 'Tafsir', 'Business', 'IT', 'Health', 'Fitness', 'Cooking', 'Parenting', 'Youth', 'Sisters', 'Other' ];
$days = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
?>

<div class="d-header">
    <h1>🎓 <?php esc_html_e( 'Classes', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Create and manage educational classes offered at your mosque.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Form -->
<div class="d-card">
    <h3><?php echo $editing ? '✏️ ' . esc_html__( 'Edit Class', 'yourjannah' ) : '➕ ' . esc_html__( 'New Class', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_classes', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ( $editing ) : ?><input type="hidden" name="class_id" value="<?php echo (int) $editing->id; ?>"><?php endif; ?>

        <div class="d-field">
            <label><?php esc_html_e( 'Class Title *', 'yourjannah' ); ?></label>
            <input type="text" name="title" value="<?php echo esc_attr( $editing->title ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'e.g. Quran Recitation for Beginners', 'yourjannah' ); ?>">
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Description', 'yourjannah' ); ?></label>
            <textarea name="description" rows="3" placeholder="<?php esc_attr_e( 'What will students learn?', 'yourjannah' ); ?>"><?php echo esc_textarea( $editing->description ?? '' ); ?></textarea>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Instructor', 'yourjannah' ); ?></label>
                <input type="text" name="instructor_name" value="<?php echo esc_attr( $editing->instructor_name ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Sheikh Ahmad', 'yourjannah' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Category', 'yourjannah' ); ?></label>
                <select name="category">
                    <?php foreach ( $categories as $c ) : ?>
                    <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $editing->category ?? '', $c ); ?>><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Day of Week', 'yourjannah' ); ?></label>
                <select name="day_of_week">
                    <option value=""><?php esc_html_e( '— Select —', 'yourjannah' ); ?></option>
                    <?php foreach ( $days as $d ) : ?>
                    <option value="<?php echo esc_attr( $d ); ?>" <?php selected( $editing->day_of_week ?? '', $d ); ?>><?php echo esc_html( $d ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Capacity (0=unlimited)', 'yourjannah' ); ?></label>
                <input type="number" name="max_capacity" min="0" value="<?php echo esc_attr( $editing->max_capacity ?? 0 ); ?>">
            </div>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Start Time', 'yourjannah' ); ?></label>
                <input type="time" name="start_time" value="<?php echo esc_attr( $editing->start_time ?? '' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'End Time', 'yourjannah' ); ?></label>
                <input type="time" name="end_time" value="<?php echo esc_attr( $editing->end_time ?? '' ); ?>">
            </div>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Price (£, 0=free)', 'yourjannah' ); ?></label>
                <input type="number" name="price" min="0" step="0.01" value="<?php echo esc_attr( ( $editing->price_pence ?? 0 ) / 100 ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Status', 'yourjannah' ); ?></label>
                <select name="status">
                    <option value="active" <?php selected( $editing->status ?? '', 'active' ); ?>><?php esc_html_e( 'Active', 'yourjannah' ); ?></option>
                    <option value="paused" <?php selected( $editing->status ?? '', 'paused' ); ?>><?php esc_html_e( 'Paused', 'yourjannah' ); ?></option>
                    <option value="completed" <?php selected( $editing->status ?? '', 'completed' ); ?>><?php esc_html_e( 'Completed', 'yourjannah' ); ?></option>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="submit" class="d-btn d-btn--primary"><?php echo $editing ? esc_html__( 'Update Class', 'yourjannah' ) : esc_html__( 'Create Class', 'yourjannah' ); ?></button>
            <?php if ( $editing ) : ?><a href="?section=classes" class="d-btn d-btn--outline"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Classes List -->
<?php if ( empty( $classes ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">🎓</div><p><?php esc_html_e( 'No classes yet. Create your first one above!', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Class', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Instructor', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Schedule', 'yourjannah' ); ?></th>
                <th style="text-align:right;"><?php esc_html_e( 'Price', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Enrolled', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'yourjannah' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $classes as $c ) : ?>
        <tr>
            <td>
                <strong><?php echo esc_html( $c->title ); ?></strong>
                <br><span class="d-badge d-badge--gray" style="font-size:10px;"><?php echo esc_html( $c->category ); ?></span>
            </td>
            <td><?php echo esc_html( $c->instructor_name ?: '—' ); ?></td>
            <td style="font-size:12px;">
                <?php echo esc_html( $c->day_of_week ?: '—' ); ?>
                <?php if ( $c->start_time ) echo '<br>' . esc_html( substr( $c->start_time, 0, 5 ) ); ?>
                <?php if ( $c->end_time ) echo ' – ' . esc_html( substr( $c->end_time, 0, 5 ) ); ?>
            </td>
            <td style="text-align:right;font-weight:700;"><?php echo $c->price_pence > 0 ? '£' . number_format( $c->price_pence / 100, 0 ) : '<span style="color:#16a34a;">Free</span>'; ?></td>
            <td>
                <?php echo (int) $c->enrolled_count; ?>
                <?php if ( $c->max_capacity ) echo '/' . (int) $c->max_capacity; ?>
            </td>
            <td><span class="d-badge d-badge--<?php echo $c->status === 'active' ? 'green' : ( $c->status === 'completed' ? 'blue' : 'yellow' ); ?>"><?php echo esc_html( ucfirst( $c->status ) ); ?></span></td>
            <td>
                <a href="?section=classes&edit=<?php echo (int) $c->id; ?>" class="d-btn d-btn--sm d-btn--outline">✏️</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this class?', 'yourjannah' ); ?>')">
                    <?php wp_nonce_field( 'ynj_dash_classes', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="class_id" value="<?php echo (int) $c->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
