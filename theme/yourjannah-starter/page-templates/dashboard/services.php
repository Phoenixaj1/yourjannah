<?php
/**
 * Dashboard Section: Masjid Services (nikkah, funeral, counselling, etc.)
 */
$st = YNJ_DB::table( 'masjid_services' );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_services' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    if ( $action === 'create' || $action === 'update' ) {
        $data = [
            'mosque_id'         => $mosque_id,
            'name'              => sanitize_text_field( $_POST['name'] ?? '' ),
            'category'          => sanitize_text_field( $_POST['category'] ?? 'general' ),
            'description'       => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'price_pence'       => (int) ( floatval( $_POST['price'] ?? 0 ) * 100 ),
            'price_label'       => sanitize_text_field( $_POST['price_label'] ?? '' ),
            'availability'      => sanitize_text_field( $_POST['availability'] ?? '' ),
            'contact_phone'     => sanitize_text_field( $_POST['contact_phone'] ?? '' ),
            'contact_email'     => sanitize_email( $_POST['contact_email'] ?? '' ),
            'requires_approval' => (int) ( $_POST['requires_approval'] ?? 0 ),
            'status'            => sanitize_text_field( $_POST['status'] ?? 'active' ),
        ];
        if ( ! $data['name'] ) { $error = __( 'Service name required.', 'yourjannah' ); }
        elseif ( $action === 'create' ) {
            $wpdb->insert( $st, $data );
            $success = __( 'Service added!', 'yourjannah' );
        } else {
            $sid = (int) $_POST['svc_id'];
            unset( $data['mosque_id'] );
            $wpdb->update( $st, $data, [ 'id' => $sid, 'mosque_id' => $mosque_id ] );
            $success = __( 'Service updated!', 'yourjannah' );
        }
    }
    if ( $action === 'delete' ) {
        $wpdb->delete( $st, [ 'id' => (int) $_POST['svc_id'], 'mosque_id' => $mosque_id ] );
        $success = __( 'Service removed.', 'yourjannah' );
    }
}

$services = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $st WHERE mosque_id=%d ORDER BY status ASC, name ASC",
    $mosque_id
) ) ?: [];

$editing = null;
$edit_id = (int) ( $_GET['edit'] ?? 0 );
if ( $edit_id ) {
    $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $st WHERE id=%d AND mosque_id=%d", $edit_id, $mosque_id ) );
}

$svc_cats = [ 'nikkah', 'funeral', 'counselling', 'quran', 'revert', 'ruqyah', 'aqiqah', 'circumcision', 'walima', 'hire', 'imam', 'certificate', 'general' ];
$svc_icons = [ 'nikkah' => '💍', 'funeral' => '🕊️', 'counselling' => '🤝', 'quran' => '📖', 'revert' => '🕌', 'ruqyah' => '🤲', 'aqiqah' => '🐑', 'circumcision' => '🏥', 'walima' => '🍽️', 'hire' => '🏠', 'imam' => '🕌', 'certificate' => '📜', 'general' => '🕌' ];
?>

<div class="d-header">
    <h1>🛎️ <?php esc_html_e( 'Masjid Services', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Services your mosque offers — nikkah, funeral, counselling, room hire, etc.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error">❌ <?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Form -->
<div class="d-card">
    <h3><?php echo $editing ? '✏️ ' . esc_html__( 'Edit Service', 'yourjannah' ) : '➕ ' . esc_html__( 'Add Service', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_services', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <?php if ( $editing ) : ?><input type="hidden" name="svc_id" value="<?php echo (int) $editing->id; ?>"><?php endif; ?>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Service Name *', 'yourjannah' ); ?></label>
                <input type="text" name="name" value="<?php echo esc_attr( $editing->name ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'e.g. Nikkah Ceremony', 'yourjannah' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Category', 'yourjannah' ); ?></label>
                <select name="category">
                    <?php foreach ( $svc_cats as $c ) : ?>
                    <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $editing->category ?? '', $c ); ?>><?php echo ( $svc_icons[ $c ] ?? '' ) . ' ' . esc_html( ucfirst( $c ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-field">
            <label><?php esc_html_e( 'Description', 'yourjannah' ); ?></label>
            <textarea name="description" rows="3" placeholder="<?php esc_attr_e( 'Describe the service, requirements, what\'s included...', 'yourjannah' ); ?>"><?php echo esc_textarea( $editing->description ?? '' ); ?></textarea>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Price (£, 0=contact for price)', 'yourjannah' ); ?></label>
                <input type="number" name="price" min="0" step="0.01" value="<?php echo esc_attr( ( $editing->price_pence ?? 0 ) / 100 ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Price Label', 'yourjannah' ); ?></label>
                <input type="text" name="price_label" value="<?php echo esc_attr( $editing->price_label ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. From £150, Contact us, Free', 'yourjannah' ); ?>">
            </div>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Contact Phone', 'yourjannah' ); ?></label>
                <input type="tel" name="contact_phone" value="<?php echo esc_attr( $editing->contact_phone ?? '' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Contact Email', 'yourjannah' ); ?></label>
                <input type="email" name="contact_email" value="<?php echo esc_attr( $editing->contact_email ?? '' ); ?>">
            </div>
        </div>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Availability', 'yourjannah' ); ?></label>
                <input type="text" name="availability" value="<?php echo esc_attr( $editing->availability ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Mon-Fri 9am-5pm, By appointment', 'yourjannah' ); ?>">
            </div>
            <div class="d-field">
                <label>
                    <input type="checkbox" name="requires_approval" value="1" <?php checked( $editing->requires_approval ?? 0, 1 ); ?>>
                    <?php esc_html_e( 'Requires approval before booking', 'yourjannah' ); ?>
                </label>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="submit" class="d-btn d-btn--primary"><?php echo $editing ? esc_html__( 'Update Service', 'yourjannah' ) : esc_html__( 'Add Service', 'yourjannah' ); ?></button>
            <?php if ( $editing ) : ?><a href="?section=services" class="d-btn d-btn--outline"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a><?php endif; ?>
        </div>
    </form>
</div>

<!-- Services List -->
<?php if ( empty( $services ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">🛎️</div><p><?php esc_html_e( 'No services configured yet. Add one above!', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Service', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Category', 'yourjannah' ); ?></th>
                <th style="text-align:right;"><?php esc_html_e( 'Price', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Approval', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'yourjannah' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $services as $s ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $s->name ); ?></strong></td>
            <td><?php echo ( $svc_icons[ $s->category ] ?? '' ) . ' ' . esc_html( ucfirst( $s->category ) ); ?></td>
            <td style="text-align:right;"><?php echo $s->price_pence > 0 ? '£' . number_format( $s->price_pence / 100, 0 ) : esc_html( $s->price_label ?: __( 'Contact', 'yourjannah' ) ); ?></td>
            <td><?php echo $s->requires_approval ? '✓ Yes' : '—'; ?></td>
            <td><span class="d-badge d-badge--<?php echo $s->status === 'active' ? 'green' : 'yellow'; ?>"><?php echo esc_html( ucfirst( $s->status ) ); ?></span></td>
            <td>
                <a href="?section=services&edit=<?php echo (int) $s->id; ?>" class="d-btn d-btn--sm d-btn--outline">✏️</a>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this service?', 'yourjannah' ); ?>')">
                    <?php wp_nonce_field( 'ynj_dash_services', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="svc_id" value="<?php echo (int) $s->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
