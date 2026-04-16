<?php
/**
 * Dashboard Section: Madrassah Hub
 * Students, attendance, terms, fees — all in one page with sub-tabs.
 */
$st = YNJ_DB::table( 'madrassah_students' );
$ft = YNJ_DB::table( 'madrassah_fees' );

// Handle POST
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_mad' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );
    if ( $action === 'add_student' ) {
        $wpdb->insert( $st, [
            'mosque_id'         => $mosque_id,
            'child_name'        => sanitize_text_field( $_POST['child_name'] ?? '' ),
            'year_group'        => sanitize_text_field( $_POST['year_group'] ?? '' ),
            'parent_name'       => sanitize_text_field( $_POST['parent_name'] ?? '' ),
            'parent_email'      => sanitize_email( $_POST['parent_email'] ?? '' ),
            'parent_phone'      => sanitize_text_field( $_POST['parent_phone'] ?? '' ),
            'emergency_contact' => sanitize_text_field( $_POST['emergency_contact'] ?? '' ),
            'medical_notes'     => sanitize_textarea_field( $_POST['medical_notes'] ?? '' ),
            'status'            => 'active',
        ] );
        $success = __( 'Student enrolled!', 'yourjannah' );
    }
    if ( $action === 'remove_student' ) {
        $wpdb->update( $st, [ 'status' => 'withdrawn' ], [ 'id' => (int) $_POST['student_id'], 'mosque_id' => $mosque_id ] );
        $success = __( 'Student removed.', 'yourjannah' );
    }
}

$students = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $st WHERE mosque_id=%d AND status='active' ORDER BY child_name ASC",
    $mosque_id
) ) ?: [];

$sub_tab = sanitize_text_field( $_GET['tab'] ?? 'students' );
$years = [ 'Reception', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6', 'Year 7', 'Year 8', 'Year 9', 'Year 10' ];
?>

<div class="d-header">
    <h1>📚 <?php esc_html_e( 'Madrassah', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Manage your Islamic school — students, attendance, and fees.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success">✅ <?php echo esc_html( $success ); ?></div><?php endif; ?>

<!-- Stats -->
<div class="d-stats">
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Active Students', 'yourjannah' ); ?></div><div class="d-stat__value"><?php echo count( $students ); ?></div></div>
</div>

<!-- Sub-tabs -->
<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="?section=madrassah&tab=students" class="d-btn d-btn--<?php echo $sub_tab==='students'?'primary':'outline'; ?> d-btn--sm">👨‍🎓 Students</a>
    <a href="?section=madrassah&tab=enrol" class="d-btn d-btn--<?php echo $sub_tab==='enrol'?'primary':'outline'; ?> d-btn--sm">➕ Enrol Student</a>
</div>

<?php if ( $sub_tab === 'enrol' ) : ?>
<div class="d-card">
    <h3>➕ <?php esc_html_e( 'Enrol New Student', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="add_student">

        <div class="d-row">
            <div class="d-field"><label><?php esc_html_e( 'Child Name *', 'yourjannah' ); ?></label><input type="text" name="child_name" required></div>
            <div class="d-field"><label><?php esc_html_e( 'Year Group', 'yourjannah' ); ?></label><select name="year_group"><option value="">—</option><?php foreach ( $years as $y ) : ?><option value="<?php echo esc_attr( $y ); ?>"><?php echo esc_html( $y ); ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="d-row">
            <div class="d-field"><label><?php esc_html_e( 'Parent Name *', 'yourjannah' ); ?></label><input type="text" name="parent_name" required></div>
            <div class="d-field"><label><?php esc_html_e( 'Parent Email', 'yourjannah' ); ?></label><input type="email" name="parent_email"></div>
        </div>
        <div class="d-row">
            <div class="d-field"><label><?php esc_html_e( 'Parent Phone', 'yourjannah' ); ?></label><input type="tel" name="parent_phone"></div>
            <div class="d-field"><label><?php esc_html_e( 'Emergency Contact', 'yourjannah' ); ?></label><input type="text" name="emergency_contact"></div>
        </div>
        <div class="d-field"><label><?php esc_html_e( 'Medical Notes / Allergies', 'yourjannah' ); ?></label><textarea name="medical_notes" rows="2" placeholder="<?php esc_attr_e( 'Any medical conditions or allergies...', 'yourjannah' ); ?>"></textarea></div>

        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Enrol Student', 'yourjannah' ); ?></button>
    </form>
</div>
<?php else : ?>
<!-- Students list -->
<?php if ( empty( $students ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">📚</div><p><?php esc_html_e( 'No students enrolled yet.', 'yourjannah' ); ?></p><a href="?section=madrassah&tab=enrol" class="d-btn d-btn--primary" style="margin-top:12px;"><?php esc_html_e( 'Enrol First Student', 'yourjannah' ); ?></a></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr><th><?php esc_html_e( 'Student', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Year', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Parent', 'yourjannah' ); ?></th><th><?php esc_html_e( 'Contact', 'yourjannah' ); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ( $students as $s ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $s->child_name ); ?></strong></td>
            <td><?php echo esc_html( $s->year_group ?: '—' ); ?></td>
            <td><?php echo esc_html( $s->parent_name ); ?></td>
            <td style="font-size:12px;">
                <?php if ( $s->parent_email ) : ?><a href="mailto:<?php echo esc_attr( $s->parent_email ); ?>"><?php echo esc_html( $s->parent_email ); ?></a><br><?php endif; ?>
                <?php echo esc_html( $s->parent_phone ?: '' ); ?>
            </td>
            <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Remove this student?', 'yourjannah' ); ?>')">
                    <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="remove_student">
                    <input type="hidden" name="student_id" value="<?php echo (int) $s->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; endif; ?>
