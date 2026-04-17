<?php
/**
 * Dashboard Section: Madrassah Hub
 * Students, terms, attendance, fees, reports — all in one page with sub-tabs.
 */
$st  = YNJ_DB::table( 'madrassah_students' );
$tt  = YNJ_DB::table( 'madrassah_terms' );
$att = YNJ_DB::table( 'madrassah_attendance' );
$ft  = YNJ_DB::table( 'madrassah_fees' );
$rt  = YNJ_DB::table( 'madrassah_reports' );

$sub_tab = sanitize_text_field( $_GET['tab'] ?? 'students' );
$years   = [ 'Reception', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6', 'Year 7', 'Year 8', 'Year 9', 'Year 10' ];
$success = '';
$error   = '';

// ─── Handle all POST actions ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_dash_mad' ) ) {
    $action = sanitize_text_field( $_POST['action'] ?? '' );

    // ── Students ──
    if ( $action === 'add_student' ) {
        $wpdb->insert( $st, [
            'mosque_id'         => $mosque_id,
            'child_name'        => sanitize_text_field( $_POST['child_name'] ?? '' ),
            'child_dob'         => sanitize_text_field( $_POST['child_dob'] ?? '' ) ?: null,
            'year_group'        => sanitize_text_field( $_POST['year_group'] ?? '' ),
            'parent_name'       => sanitize_text_field( $_POST['parent_name'] ?? '' ),
            'parent_email'      => sanitize_email( $_POST['parent_email'] ?? '' ),
            'parent_phone'      => sanitize_text_field( $_POST['parent_phone'] ?? '' ),
            'emergency_contact' => sanitize_text_field( $_POST['emergency_contact'] ?? '' ),
            'emergency_phone'   => sanitize_text_field( $_POST['emergency_phone'] ?? '' ),
            'medical_notes'     => sanitize_textarea_field( $_POST['medical_notes'] ?? '' ),
            'status'            => 'active',
        ] );
        $success = __( 'Student enrolled!', 'yourjannah' );
    }

    if ( $action === 'update_student' ) {
        $sid = (int) ( $_POST['student_id'] ?? 0 );
        $wpdb->update( $st, [
            'child_name'        => sanitize_text_field( $_POST['child_name'] ?? '' ),
            'child_dob'         => sanitize_text_field( $_POST['child_dob'] ?? '' ) ?: null,
            'year_group'        => sanitize_text_field( $_POST['year_group'] ?? '' ),
            'parent_name'       => sanitize_text_field( $_POST['parent_name'] ?? '' ),
            'parent_email'      => sanitize_email( $_POST['parent_email'] ?? '' ),
            'parent_phone'      => sanitize_text_field( $_POST['parent_phone'] ?? '' ),
            'emergency_contact' => sanitize_text_field( $_POST['emergency_contact'] ?? '' ),
            'emergency_phone'   => sanitize_text_field( $_POST['emergency_phone'] ?? '' ),
            'medical_notes'     => sanitize_textarea_field( $_POST['medical_notes'] ?? '' ),
        ], [ 'id' => $sid, 'mosque_id' => $mosque_id ] );
        $success = __( 'Student updated!', 'yourjannah' );
    }

    if ( $action === 'remove_student' ) {
        $wpdb->update( $st, [ 'status' => 'withdrawn' ], [ 'id' => (int) $_POST['student_id'], 'mosque_id' => $mosque_id ] );
        $success = __( 'Student removed.', 'yourjannah' );
    }

    // ── Terms ──
    if ( $action === 'add_term' ) {
        $name = sanitize_text_field( $_POST['term_name'] ?? '' );
        if ( ! $name ) {
            $error = __( 'Term name is required.', 'yourjannah' );
        } else {
            $wpdb->insert( $tt, [
                'mosque_id'      => $mosque_id,
                'name'           => $name,
                'start_date'     => sanitize_text_field( $_POST['start_date'] ?? '' ),
                'end_date'       => sanitize_text_field( $_POST['end_date'] ?? '' ),
                'fee_pence'      => (int) ( floatval( $_POST['fee_amount'] ?? 0 ) * 100 ),
                'fee_frequency'  => sanitize_text_field( $_POST['fee_frequency'] ?? 'termly' ),
                'enrolment_open' => (int) ( $_POST['enrolment_open'] ?? 0 ),
                'status'         => 'active',
            ] );
            $success = __( 'Term created!', 'yourjannah' );
        }
    }

    if ( $action === 'update_term' ) {
        $tid = (int) ( $_POST['term_id'] ?? 0 );
        $wpdb->update( $tt, [
            'name'           => sanitize_text_field( $_POST['term_name'] ?? '' ),
            'start_date'     => sanitize_text_field( $_POST['start_date'] ?? '' ),
            'end_date'       => sanitize_text_field( $_POST['end_date'] ?? '' ),
            'fee_pence'      => (int) ( floatval( $_POST['fee_amount'] ?? 0 ) * 100 ),
            'fee_frequency'  => sanitize_text_field( $_POST['fee_frequency'] ?? 'termly' ),
            'enrolment_open' => (int) ( $_POST['enrolment_open'] ?? 0 ),
        ], [ 'id' => $tid, 'mosque_id' => $mosque_id ] );
        $success = __( 'Term updated!', 'yourjannah' );
    }

    if ( $action === 'delete_term' ) {
        $tid = (int) ( $_POST['term_id'] ?? 0 );
        $wpdb->delete( $tt, [ 'id' => $tid, 'mosque_id' => $mosque_id ] );
        $success = __( 'Term deleted.', 'yourjannah' );
    }

    // ── Attendance ──
    if ( $action === 'mark_attendance' ) {
        $att_date  = sanitize_text_field( $_POST['attendance_date'] ?? date( 'Y-m-d' ) );
        $marked_by = wp_get_current_user()->display_name ?: 'Admin';
        $statuses  = (array) ( $_POST['att_status'] ?? [] );
        $notes     = (array) ( $_POST['att_notes'] ?? [] );
        $count     = 0;

        foreach ( $statuses as $sid => $status ) {
            $sid    = (int) $sid;
            $status = sanitize_text_field( $status );
            $note   = sanitize_text_field( $notes[ $sid ] ?? '' );

            if ( ! in_array( $status, [ 'present', 'absent', 'late', 'excused' ], true ) ) {
                continue;
            }

            // Upsert: delete any existing record for this student + date then insert
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM $att WHERE student_id = %d AND attendance_date = %s",
                $sid, $att_date
            ) );
            $wpdb->insert( $att, [
                'student_id'      => $sid,
                'class_id'        => 0,
                'attendance_date' => $att_date,
                'status'          => $status,
                'notes'           => $note,
                'marked_by'       => $marked_by,
            ] );
            $count++;
        }
        $success = sprintf( __( 'Attendance marked for %d students.', 'yourjannah' ), $count );
    }

    // ── Fees ──
    if ( $action === 'mark_paid' ) {
        $fee_id = (int) ( $_POST['fee_id'] ?? 0 );
        $wpdb->update( $ft, [
            'status'  => 'paid',
            'paid_at' => current_time( 'mysql' ),
        ], [ 'id' => $fee_id, 'mosque_id' => $mosque_id ] );
        $success = __( 'Fee marked as paid.', 'yourjannah' );
    }

    if ( $action === 'generate_fees' ) {
        $gen_term_id = (int) ( $_POST['term_id'] ?? 0 );
        $term_row    = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $tt WHERE id = %d AND mosque_id = %d", $gen_term_id, $mosque_id
        ) );

        if ( $term_row ) {
            $active_students = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $st WHERE mosque_id = %d AND status = 'active'", $mosque_id
            ) ) ?: [];

            $generated = 0;
            foreach ( $active_students as $s ) {
                // Skip if fee record already exists for this student + term
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM $ft WHERE student_id = %d AND term_id = %d",
                    $s->id, $gen_term_id
                ) );
                if ( $exists ) continue;

                $wpdb->insert( $ft, [
                    'mosque_id'      => $mosque_id,
                    'student_id'     => $s->id,
                    'term_id'        => $gen_term_id,
                    'parent_user_id' => $s->parent_user_id,
                    'amount_pence'   => $term_row->fee_pence,
                    'status'         => 'unpaid',
                    'due_date'       => $term_row->start_date,
                ] );
                $generated++;
            }
            $success = sprintf( __( 'Generated fee records for %d students.', 'yourjannah' ), $generated );
        } else {
            $error = __( 'Term not found.', 'yourjannah' );
        }
    }

    // ── Reports ──
    if ( $action === 'add_report' ) {
        $r_student = (int) ( $_POST['report_student_id'] ?? 0 );
        $r_term    = (int) ( $_POST['report_term_id'] ?? 0 );
        $r_subject = sanitize_text_field( $_POST['subject'] ?? '' );

        if ( ! $r_student || ! $r_subject ) {
            $error = __( 'Student and subject are required.', 'yourjannah' );
        } else {
            $wpdb->insert( $rt, [
                'student_id'     => $r_student,
                'term_id'        => $r_term,
                'class_id'       => 0,
                'subject'        => $r_subject,
                'grade'          => sanitize_text_field( $_POST['grade'] ?? '' ),
                'teacher_notes'  => sanitize_textarea_field( $_POST['teacher_notes'] ?? '' ),
                'quran_progress' => sanitize_text_field( $_POST['quran_progress'] ?? '' ),
                'behaviour'      => sanitize_text_field( $_POST['behaviour'] ?? 'good' ),
                'created_by'     => wp_get_current_user()->display_name ?: 'Admin',
            ] );
            $success = __( 'Report saved!', 'yourjannah' );
        }
    }

    if ( $action === 'delete_report' ) {
        $rpid       = (int) ( $_POST['report_id'] ?? 0 );
        $report_row = $wpdb->get_row( $wpdb->prepare( "SELECT r.* FROM $rt r JOIN $st s ON s.id = r.student_id WHERE r.id = %d AND s.mosque_id = %d", $rpid, $mosque_id ) );
        if ( $report_row ) {
            $wpdb->delete( $rt, [ 'id' => $rpid ] );
            $success = __( 'Report deleted.', 'yourjannah' );
        }
    }
}

// ─── Load shared data ──────────────────────────────────────────────────────
$students = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $st WHERE mosque_id = %d AND status = 'active' ORDER BY child_name ASC",
    $mosque_id
) ) ?: [];

$terms = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM $tt WHERE mosque_id = %d AND status = 'active' ORDER BY start_date DESC",
    $mosque_id
) ) ?: [];

// Current term (latest active whose dates encompass today, or the most recent)
$today        = date( 'Y-m-d' );
$current_term = null;
foreach ( $terms as $t_row ) {
    if ( $t_row->start_date <= $today && $t_row->end_date >= $today ) {
        $current_term = $t_row;
        break;
    }
}
if ( ! $current_term && ! empty( $terms ) ) {
    $current_term = $terms[0];
}

// Editing student?
$editing_student = null;
$edit_student_id = (int) ( $_GET['edit_student'] ?? 0 );
if ( $edit_student_id ) {
    $editing_student = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $st WHERE id = %d AND mosque_id = %d", $edit_student_id, $mosque_id
    ) );
}

// Editing term?
$editing_term = null;
$edit_term_id = (int) ( $_GET['edit_term'] ?? 0 );
if ( $edit_term_id ) {
    $editing_term = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $tt WHERE id = %d AND mosque_id = %d", $edit_term_id, $mosque_id
    ) );
}

// Tab definitions
$tabs = [
    'students'   => __( 'Students', 'yourjannah' ),
    'terms'      => __( 'Terms', 'yourjannah' ),
    'attendance' => __( 'Attendance', 'yourjannah' ),
    'fees'       => __( 'Fees', 'yourjannah' ),
    'reports'    => __( 'Reports', 'yourjannah' ),
];
?>

<div class="d-header">
    <h1><?php esc_html_e( 'Madrassah', 'yourjannah' ); ?></h1>
    <p><?php esc_html_e( 'Manage your Islamic school — students, terms, attendance, fees, and reports.', 'yourjannah' ); ?></p>
</div>

<?php if ( ! empty( $success ) ) : ?><div class="d-alert d-alert--success"><?php echo esc_html( $success ); ?></div><?php endif; ?>
<?php if ( ! empty( $error ) ) : ?><div class="d-alert d-alert--error"><?php echo esc_html( $error ); ?></div><?php endif; ?>

<!-- Stats -->
<div class="d-stats">
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Active Students', 'yourjannah' ); ?></div><div class="d-stat__value"><?php echo count( $students ); ?></div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Active Terms', 'yourjannah' ); ?></div><div class="d-stat__value"><?php echo count( $terms ); ?></div></div>
    <?php if ( $current_term ) : ?>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Current Term', 'yourjannah' ); ?></div><div class="d-stat__value" style="font-size:14px;"><?php echo esc_html( $current_term->name ); ?></div></div>
    <?php endif; ?>
</div>

<!-- Sub-tabs -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach ( $tabs as $key => $label ) : ?>
    <a href="?section=madrassah&tab=<?php echo esc_attr( $key ); ?>" class="d-btn d-btn--<?php echo $sub_tab === $key ? 'primary' : 'outline'; ?>" style="font-size:13px;">
        <?php echo esc_html( $label ); ?>
    </a>
    <?php endforeach; ?>
</div>

<?php
// ═════════════════════════════════════════════════════════════════════════════
//  TAB 1: STUDENTS
// ═════════════════════════════════════════════════════════════════════════════
if ( $sub_tab === 'students' || $sub_tab === 'enrol' ) : ?>

<?php if ( $sub_tab === 'enrol' || $editing_student ) : ?>
<!-- Enrol / Edit Student Form -->
<div class="d-card">
    <h3><?php echo $editing_student ? esc_html__( 'Edit Student', 'yourjannah' ) : esc_html__( 'Enrol New Student', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing_student ? 'update_student' : 'add_student'; ?>">
        <?php if ( $editing_student ) : ?>
        <input type="hidden" name="student_id" value="<?php echo (int) $editing_student->id; ?>">
        <?php endif; ?>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Child Name *', 'yourjannah' ); ?></label>
                <input type="text" name="child_name" value="<?php echo esc_attr( $editing_student->child_name ?? '' ); ?>" required>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Date of Birth', 'yourjannah' ); ?></label>
                <input type="date" name="child_dob" value="<?php echo esc_attr( $editing_student->child_dob ?? '' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Year Group', 'yourjannah' ); ?></label>
                <select name="year_group">
                    <option value="">--</option>
                    <?php foreach ( $years as $y ) : ?>
                    <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $editing_student->year_group ?? '', $y ); ?>><?php echo esc_html( $y ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Parent Name *', 'yourjannah' ); ?></label>
                <input type="text" name="parent_name" value="<?php echo esc_attr( $editing_student->parent_name ?? '' ); ?>" required>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Parent Email', 'yourjannah' ); ?></label>
                <input type="email" name="parent_email" value="<?php echo esc_attr( $editing_student->parent_email ?? '' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Parent Phone', 'yourjannah' ); ?></label>
                <input type="tel" name="parent_phone" value="<?php echo esc_attr( $editing_student->parent_phone ?? '' ); ?>">
            </div>
        </div>
        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Emergency Contact', 'yourjannah' ); ?></label>
                <input type="text" name="emergency_contact" value="<?php echo esc_attr( $editing_student->emergency_contact ?? '' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Emergency Phone', 'yourjannah' ); ?></label>
                <input type="tel" name="emergency_phone" value="<?php echo esc_attr( $editing_student->emergency_phone ?? '' ); ?>">
            </div>
        </div>
        <div class="d-field">
            <label><?php esc_html_e( 'Medical Notes / Allergies', 'yourjannah' ); ?></label>
            <textarea name="medical_notes" rows="2" placeholder="<?php esc_attr_e( 'Any medical conditions or allergies...', 'yourjannah' ); ?>"><?php echo esc_textarea( $editing_student->medical_notes ?? '' ); ?></textarea>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="d-btn d-btn--primary">
                <?php echo $editing_student ? esc_html__( 'Update Student', 'yourjannah' ) : esc_html__( 'Enrol Student', 'yourjannah' ); ?>
            </button>
            <?php if ( $editing_student ) : ?>
            <a href="?section=madrassah&tab=students" class="d-btn d-btn--outline"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ( $sub_tab === 'students' && ! $editing_student ) : ?>
<!-- Students List -->
<div style="margin-bottom:12px;">
    <a href="?section=madrassah&tab=enrol" class="d-btn d-btn--primary d-btn--sm"><?php esc_html_e( 'Enrol New Student', 'yourjannah' ); ?></a>
</div>

<?php if ( empty( $students ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">&#128218;</div><p><?php esc_html_e( 'No students enrolled yet.', 'yourjannah' ); ?></p><a href="?section=madrassah&tab=enrol" class="d-btn d-btn--primary" style="margin-top:12px;"><?php esc_html_e( 'Enrol First Student', 'yourjannah' ); ?></a></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr>
            <th><?php esc_html_e( 'Student', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Year', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Parent', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Contact', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Enrolled', 'yourjannah' ); ?></th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ( $students as $s ) : ?>
        <tr>
            <td><strong><?php echo esc_html( $s->child_name ); ?></strong><?php if ( $s->child_dob ) : ?><br><span style="font-size:11px;color:var(--text-dim);"><?php echo esc_html( $s->child_dob ); ?></span><?php endif; ?></td>
            <td><?php echo esc_html( $s->year_group ?: '--' ); ?></td>
            <td><?php echo esc_html( $s->parent_name ); ?></td>
            <td style="font-size:12px;">
                <?php if ( $s->parent_email ) : ?><a href="mailto:<?php echo esc_attr( $s->parent_email ); ?>"><?php echo esc_html( $s->parent_email ); ?></a><br><?php endif; ?>
                <?php echo esc_html( $s->parent_phone ?: '' ); ?>
            </td>
            <td style="font-size:12px;"><?php echo esc_html( substr( $s->enrolled_at, 0, 10 ) ); ?></td>
            <td style="white-space:nowrap;">
                <a href="?section=madrassah&tab=students&edit_student=<?php echo (int) $s->id; ?>" class="d-btn d-btn--sm d-btn--outline"><?php esc_html_e( 'Edit', 'yourjannah' ); ?></a>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Remove this student?', 'yourjannah' ); ?>')">
                    <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="remove_student">
                    <input type="hidden" name="student_id" value="<?php echo (int) $s->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger"><?php esc_html_e( 'Remove', 'yourjannah' ); ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
// ═════════════════════════════════════════════════════════════════════════════
//  TAB 2: TERMS
// ═════════════════════════════════════════════════════════════════════════════
elseif ( $sub_tab === 'terms' ) : ?>

<!-- Create / Edit Term Form -->
<div class="d-card">
    <h3><?php echo $editing_term ? esc_html__( 'Edit Term', 'yourjannah' ) : esc_html__( 'Create New Term', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="<?php echo $editing_term ? 'update_term' : 'add_term'; ?>">
        <?php if ( $editing_term ) : ?>
        <input type="hidden" name="term_id" value="<?php echo (int) $editing_term->id; ?>">
        <?php endif; ?>

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Term Name *', 'yourjannah' ); ?></label>
                <input type="text" name="term_name" value="<?php echo esc_attr( $editing_term->name ?? '' ); ?>" required placeholder="<?php esc_attr_e( 'e.g. Autumn Term 2026', 'yourjannah' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Fee Frequency', 'yourjannah' ); ?></label>
                <select name="fee_frequency">
                    <option value="termly" <?php selected( $editing_term->fee_frequency ?? '', 'termly' ); ?>><?php esc_html_e( 'Termly', 'yourjannah' ); ?></option>
                    <option value="monthly" <?php selected( $editing_term->fee_frequency ?? '', 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'yourjannah' ); ?></option>
                    <option value="weekly" <?php selected( $editing_term->fee_frequency ?? '', 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'yourjannah' ); ?></option>
                </select>
            </div>
        </div>
        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Start Date *', 'yourjannah' ); ?></label>
                <input type="date" name="start_date" value="<?php echo esc_attr( $editing_term->start_date ?? '' ); ?>" required>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'End Date *', 'yourjannah' ); ?></label>
                <input type="date" name="end_date" value="<?php echo esc_attr( $editing_term->end_date ?? '' ); ?>" required>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Fee Amount', 'yourjannah' ); ?></label>
                <input type="number" name="fee_amount" step="0.01" min="0" value="<?php echo esc_attr( $editing_term ? number_format( $editing_term->fee_pence / 100, 2, '.', '' ) : '' ); ?>" placeholder="0.00">
            </div>
        </div>
        <div class="d-field">
            <label>
                <input type="checkbox" name="enrolment_open" value="1" <?php checked( $editing_term->enrolment_open ?? 1, 1 ); ?>>
                <?php esc_html_e( 'Enrolment open', 'yourjannah' ); ?>
            </label>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="d-btn d-btn--primary">
                <?php echo $editing_term ? esc_html__( 'Update Term', 'yourjannah' ) : esc_html__( 'Create Term', 'yourjannah' ); ?>
            </button>
            <?php if ( $editing_term ) : ?>
            <a href="?section=madrassah&tab=terms" class="d-btn d-btn--outline"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Terms List -->
<?php if ( empty( $terms ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">&#128197;</div><p><?php esc_html_e( 'No terms created yet. Add your first term above.', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr>
            <th><?php esc_html_e( 'Term Name', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Dates', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Fee', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Frequency', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Enrolment', 'yourjannah' ); ?></th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ( $terms as $t_row ) : ?>
        <tr>
            <td>
                <strong><?php echo esc_html( $t_row->name ); ?></strong>
                <?php if ( $current_term && $current_term->id === $t_row->id ) : ?>
                <span class="d-badge d-badge--blue"><?php esc_html_e( 'Current', 'yourjannah' ); ?></span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php echo esc_html( $t_row->start_date ); ?> &rarr; <?php echo esc_html( $t_row->end_date ); ?></td>
            <td style="font-weight:700;">&pound;<?php echo number_format( $t_row->fee_pence / 100, 2 ); ?></td>
            <td><?php echo esc_html( ucfirst( $t_row->fee_frequency ) ); ?></td>
            <td>
                <?php if ( $t_row->enrolment_open ) : ?>
                <span class="d-badge d-badge--green"><?php esc_html_e( 'Open', 'yourjannah' ); ?></span>
                <?php else : ?>
                <span class="d-badge d-badge--red"><?php esc_html_e( 'Closed', 'yourjannah' ); ?></span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
                <a href="?section=madrassah&tab=terms&edit_term=<?php echo (int) $t_row->id; ?>" class="d-btn d-btn--sm d-btn--outline"><?php esc_html_e( 'Edit', 'yourjannah' ); ?></a>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this term?', 'yourjannah' ); ?>')">
                    <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="delete_term">
                    <input type="hidden" name="term_id" value="<?php echo (int) $t_row->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger"><?php esc_html_e( 'Delete', 'yourjannah' ); ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
// ═════════════════════════════════════════════════════════════════════════════
//  TAB 3: ATTENDANCE
// ═════════════════════════════════════════════════════════════════════════════
elseif ( $sub_tab === 'attendance' ) :

    $att_date = sanitize_text_field( $_GET['date'] ?? date( 'Y-m-d' ) );

    // Get existing attendance for selected date
    $att_records = [];
    if ( ! empty( $students ) ) {
        $student_ids  = implode( ',', array_map( 'intval', wp_list_pluck( $students, 'id' ) ) );
        $att_existing = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $att WHERE student_id IN ($student_ids) AND attendance_date = %s",
            $att_date
        ) ) ?: [];
        foreach ( $att_existing as $ar ) {
            $att_records[ $ar->student_id ] = $ar;
        }
    }

    // Last 7 days summary
    $att_summary = [];
    if ( ! empty( $students ) ) {
        $student_ids = implode( ',', array_map( 'intval', wp_list_pluck( $students, 'id' ) ) );
        $seven_days_ago = date( 'Y-m-d', strtotime( '-7 days' ) );
        $att_summary = $wpdb->get_results( $wpdb->prepare(
            "SELECT attendance_date,
                    SUM(status = 'present') AS present_count,
                    SUM(status = 'absent') AS absent_count,
                    SUM(status = 'late') AS late_count,
                    SUM(status = 'excused') AS excused_count,
                    COUNT(*) AS total
             FROM $att
             WHERE student_id IN ($student_ids) AND attendance_date >= %s
             GROUP BY attendance_date
             ORDER BY attendance_date DESC",
            $seven_days_ago
        ) ) ?: [];
    }
?>

<!-- Date Picker -->
<div class="d-card">
    <h3><?php esc_html_e( 'Mark Attendance', 'yourjannah' ); ?></h3>
    <form method="get" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:16px;">
        <input type="hidden" name="section" value="madrassah">
        <input type="hidden" name="tab" value="attendance">
        <div class="d-field" style="margin-bottom:0;">
            <label><?php esc_html_e( 'Date', 'yourjannah' ); ?></label>
            <input type="date" name="date" value="<?php echo esc_attr( $att_date ); ?>">
        </div>
        <button type="submit" class="d-btn d-btn--outline d-btn--sm"><?php esc_html_e( 'Load', 'yourjannah' ); ?></button>
    </form>

    <?php if ( empty( $students ) ) : ?>
    <div class="d-empty"><div class="d-empty__icon">&#128218;</div><p><?php esc_html_e( 'No active students to mark attendance for.', 'yourjannah' ); ?></p></div>
    <?php else : ?>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="mark_attendance">
        <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $att_date ); ?>">

        <table class="d-table">
            <thead><tr>
                <th><?php esc_html_e( 'Student', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Year', 'yourjannah' ); ?></th>
                <th style="text-align:center;"><?php esc_html_e( 'Present', 'yourjannah' ); ?></th>
                <th style="text-align:center;"><?php esc_html_e( 'Absent', 'yourjannah' ); ?></th>
                <th style="text-align:center;"><?php esc_html_e( 'Late', 'yourjannah' ); ?></th>
                <th style="text-align:center;"><?php esc_html_e( 'Excused', 'yourjannah' ); ?></th>
                <th><?php esc_html_e( 'Notes', 'yourjannah' ); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $students as $s ) :
                $existing = $att_records[ $s->id ] ?? null;
                $cur_status = $existing ? $existing->status : 'present';
                $cur_notes  = $existing ? $existing->notes : '';
            ?>
            <tr>
                <td><strong><?php echo esc_html( $s->child_name ); ?></strong></td>
                <td style="font-size:12px;"><?php echo esc_html( $s->year_group ?: '--' ); ?></td>
                <td style="text-align:center;"><input type="radio" name="att_status[<?php echo (int) $s->id; ?>]" value="present" <?php checked( $cur_status, 'present' ); ?>></td>
                <td style="text-align:center;"><input type="radio" name="att_status[<?php echo (int) $s->id; ?>]" value="absent" <?php checked( $cur_status, 'absent' ); ?>></td>
                <td style="text-align:center;"><input type="radio" name="att_status[<?php echo (int) $s->id; ?>]" value="late" <?php checked( $cur_status, 'late' ); ?>></td>
                <td style="text-align:center;"><input type="radio" name="att_status[<?php echo (int) $s->id; ?>]" value="excused" <?php checked( $cur_status, 'excused' ); ?>></td>
                <td><input type="text" name="att_notes[<?php echo (int) $s->id; ?>]" value="<?php echo esc_attr( $cur_notes ); ?>" style="width:120px;font-size:12px;" placeholder="<?php esc_attr_e( 'Optional', 'yourjannah' ); ?>"></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:12px;">
            <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Save Attendance', 'yourjannah' ); ?></button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- 7-Day Summary -->
<?php if ( ! empty( $att_summary ) ) : ?>
<div class="d-card">
    <h3><?php esc_html_e( 'Last 7 Days', 'yourjannah' ); ?></h3>
    <table class="d-table">
        <thead><tr>
            <th><?php esc_html_e( 'Date', 'yourjannah' ); ?></th>
            <th style="text-align:center;"><?php esc_html_e( 'Present', 'yourjannah' ); ?></th>
            <th style="text-align:center;"><?php esc_html_e( 'Absent', 'yourjannah' ); ?></th>
            <th style="text-align:center;"><?php esc_html_e( 'Late', 'yourjannah' ); ?></th>
            <th style="text-align:center;"><?php esc_html_e( 'Excused', 'yourjannah' ); ?></th>
            <th style="text-align:center;"><?php esc_html_e( 'Total', 'yourjannah' ); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ( $att_summary as $day ) : ?>
        <tr>
            <td>
                <a href="?section=madrassah&tab=attendance&date=<?php echo esc_attr( $day->attendance_date ); ?>" style="font-weight:600;">
                    <?php echo esc_html( date( 'D, j M', strtotime( $day->attendance_date ) ) ); ?>
                </a>
            </td>
            <td style="text-align:center;"><span class="d-badge d-badge--green"><?php echo (int) $day->present_count; ?></span></td>
            <td style="text-align:center;"><span class="d-badge d-badge--red"><?php echo (int) $day->absent_count; ?></span></td>
            <td style="text-align:center;"><span class="d-badge d-badge--yellow"><?php echo (int) $day->late_count; ?></span></td>
            <td style="text-align:center;"><span class="d-badge d-badge--gray"><?php echo (int) $day->excused_count; ?></span></td>
            <td style="text-align:center;"><?php echo (int) $day->total; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
// ═════════════════════════════════════════════════════════════════════════════
//  TAB 4: FEES
// ═════════════════════════════════════════════════════════════════════════════
elseif ( $sub_tab === 'fees' ) :

    // Fee records for this mosque, joined with students and terms
    $fee_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT f.*, s.child_name, s.parent_name, s.parent_email, t.name AS term_name
         FROM $ft f
         JOIN $st s ON s.id = f.student_id
         LEFT JOIN $tt t ON t.id = f.term_id
         WHERE f.mosque_id = %d
         ORDER BY f.status ASC, f.due_date DESC
         LIMIT 200",
        $mosque_id
    ) ) ?: [];

    // Summaries
    $total_owed     = 0;
    $total_paid     = 0;
    $total_outstanding = 0;
    foreach ( $fee_rows as $f ) {
        if ( $f->status === 'paid' ) {
            $total_paid += $f->amount_pence;
        } else {
            $total_outstanding += $f->amount_pence;
        }
        $total_owed += $f->amount_pence;
    }
?>

<!-- Generate Fees -->
<?php if ( ! empty( $terms ) ) : ?>
<div class="d-card">
    <h3><?php esc_html_e( 'Generate Fee Records', 'yourjannah' ); ?></h3>
    <p style="font-size:13px;color:var(--text-dim);margin-bottom:12px;"><?php esc_html_e( 'Create unpaid fee records for all active students for a term. Skips students who already have a fee record for that term.', 'yourjannah' ); ?></p>
    <form method="post" style="display:flex;gap:12px;align-items:flex-end;">
        <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="generate_fees">
        <div class="d-field" style="margin-bottom:0;">
            <label><?php esc_html_e( 'Term', 'yourjannah' ); ?></label>
            <select name="term_id" required>
                <option value="">-- <?php esc_html_e( 'Select Term', 'yourjannah' ); ?> --</option>
                <?php foreach ( $terms as $t_row ) : ?>
                <option value="<?php echo (int) $t_row->id; ?>"><?php echo esc_html( $t_row->name ); ?> (&pound;<?php echo number_format( $t_row->fee_pence / 100, 2 ); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="d-btn d-btn--primary d-btn--sm"><?php esc_html_e( 'Generate', 'yourjannah' ); ?></button>
    </form>
</div>
<?php endif; ?>

<!-- Fee Summary -->
<div class="d-stats">
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Total Owed', 'yourjannah' ); ?></div><div class="d-stat__value">&pound;<?php echo number_format( $total_owed / 100, 2 ); ?></div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Collected', 'yourjannah' ); ?></div><div class="d-stat__value" style="color:#16a34a;">&pound;<?php echo number_format( $total_paid / 100, 2 ); ?></div></div>
    <div class="d-stat"><div class="d-stat__label"><?php esc_html_e( 'Outstanding', 'yourjannah' ); ?></div><div class="d-stat__value" style="color:#e74c3c;">&pound;<?php echo number_format( $total_outstanding / 100, 2 ); ?></div></div>
</div>

<!-- Fee Records -->
<?php if ( empty( $fee_rows ) ) : ?>
<div class="d-card"><div class="d-empty"><div class="d-empty__icon">&#128176;</div><p><?php esc_html_e( 'No fee records yet. Generate fees from a term above.', 'yourjannah' ); ?></p></div></div>
<?php else : ?>
<div class="d-card">
    <table class="d-table">
        <thead><tr>
            <th><?php esc_html_e( 'Student', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Parent', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Term', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Amount', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Due Date', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Status', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Paid Date', 'yourjannah' ); ?></th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ( $fee_rows as $f ) :
            $status_class = 'gray';
            if ( $f->status === 'paid' )    $status_class = 'green';
            if ( $f->status === 'unpaid' )  $status_class = 'yellow';
            if ( $f->status === 'overdue' ) $status_class = 'red';
        ?>
        <tr>
            <td><strong><?php echo esc_html( $f->child_name ); ?></strong></td>
            <td style="font-size:12px;"><?php echo esc_html( $f->parent_name ); ?></td>
            <td style="font-size:12px;"><?php echo esc_html( $f->term_name ?: '--' ); ?></td>
            <td style="font-weight:700;">&pound;<?php echo number_format( $f->amount_pence / 100, 2 ); ?></td>
            <td style="font-size:12px;"><?php echo esc_html( $f->due_date ?: '--' ); ?></td>
            <td><span class="d-badge d-badge--<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $f->status ) ); ?></span></td>
            <td style="font-size:12px;"><?php echo $f->paid_at ? esc_html( substr( $f->paid_at, 0, 10 ) ) : '--'; ?></td>
            <td>
                <?php if ( $f->status !== 'paid' ) : ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Mark this fee as paid?', 'yourjannah' ); ?>')">
                    <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="fee_id" value="<?php echo (int) $f->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--primary"><?php esc_html_e( 'Mark Paid', 'yourjannah' ); ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
// ═════════════════════════════════════════════════════════════════════════════
//  TAB 5: REPORTS
// ═════════════════════════════════════════════════════════════════════════════
elseif ( $sub_tab === 'reports' ) :

    $filter_student = (int) ( $_GET['student_id'] ?? 0 );
    $filter_term    = (int) ( $_GET['term_id'] ?? ( $current_term ? $current_term->id : 0 ) );

    // Build WHERE clause for reports — join through student table to scope by mosque
    $where_sql = "1=1";
    $where_args = [];
    if ( $filter_student ) {
        $where_sql .= " AND r.student_id = %d";
        $where_args[] = $filter_student;
    }
    if ( $filter_term ) {
        $where_sql .= " AND r.term_id = %d";
        $where_args[] = $filter_term;
    }

    $report_query = "SELECT r.*, s.child_name, s.year_group, t.name AS term_name
                     FROM $rt r
                     JOIN $st s ON s.id = r.student_id AND s.mosque_id = %d
                     LEFT JOIN $tt t ON t.id = r.term_id
                     WHERE $where_sql
                     ORDER BY r.created_at DESC
                     LIMIT 100";

    $query_args = array_merge( [ $mosque_id ], $where_args );
    $reports = $wpdb->get_results( $wpdb->prepare( $report_query, ...$query_args ) ) ?: [];

    $grades     = [ 'A*', 'A', 'B', 'C', 'D', 'E', 'N/A' ];
    $behaviours = [ 'excellent', 'good', 'needs_improvement' ];
?>

<!-- Create Report -->
<div class="d-card">
    <h3><?php esc_html_e( 'Add Student Report', 'yourjannah' ); ?></h3>
    <form method="post">
        <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
        <input type="hidden" name="action" value="add_report">

        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Student *', 'yourjannah' ); ?></label>
                <select name="report_student_id" required>
                    <option value="">-- <?php esc_html_e( 'Select Student', 'yourjannah' ); ?> --</option>
                    <?php foreach ( $students as $s ) : ?>
                    <option value="<?php echo (int) $s->id; ?>"><?php echo esc_html( $s->child_name ); ?> (<?php echo esc_html( $s->year_group ?: '--' ); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Term', 'yourjannah' ); ?></label>
                <select name="report_term_id">
                    <option value="0">-- <?php esc_html_e( 'No Term', 'yourjannah' ); ?> --</option>
                    <?php foreach ( $terms as $t_row ) : ?>
                    <option value="<?php echo (int) $t_row->id; ?>" <?php selected( $current_term ? $current_term->id : 0, $t_row->id ); ?>><?php echo esc_html( $t_row->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="d-row">
            <div class="d-field">
                <label><?php esc_html_e( 'Subject *', 'yourjannah' ); ?></label>
                <input type="text" name="subject" required placeholder="<?php esc_attr_e( 'e.g. Quran, Arabic, Islamic Studies', 'yourjannah' ); ?>">
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Grade', 'yourjannah' ); ?></label>
                <select name="grade">
                    <option value="">--</option>
                    <?php foreach ( $grades as $g ) : ?>
                    <option value="<?php echo esc_attr( $g ); ?>"><?php echo esc_html( $g ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-field">
                <label><?php esc_html_e( 'Behaviour', 'yourjannah' ); ?></label>
                <select name="behaviour">
                    <?php foreach ( $behaviours as $b ) : ?>
                    <option value="<?php echo esc_attr( $b ); ?>" <?php selected( $b, 'good' ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $b ) ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="d-field">
            <label><?php esc_html_e( 'Quran Progress', 'yourjannah' ); ?></label>
            <input type="text" name="quran_progress" placeholder="<?php esc_attr_e( 'e.g. Completed Juz 3, working on Surah An-Nisa', 'yourjannah' ); ?>">
        </div>
        <div class="d-field">
            <label><?php esc_html_e( 'Teacher Notes', 'yourjannah' ); ?></label>
            <textarea name="teacher_notes" rows="3" placeholder="<?php esc_attr_e( 'Progress notes, areas for improvement, praise...', 'yourjannah' ); ?>"></textarea>
        </div>

        <button type="submit" class="d-btn d-btn--primary"><?php esc_html_e( 'Save Report', 'yourjannah' ); ?></button>
    </form>
</div>

<!-- Filter Reports -->
<div class="d-card">
    <h3><?php esc_html_e( 'View Reports', 'yourjannah' ); ?></h3>
    <form method="get" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap;">
        <input type="hidden" name="section" value="madrassah">
        <input type="hidden" name="tab" value="reports">
        <div class="d-field" style="margin-bottom:0;">
            <label><?php esc_html_e( 'Student', 'yourjannah' ); ?></label>
            <select name="student_id">
                <option value="0"><?php esc_html_e( 'All Students', 'yourjannah' ); ?></option>
                <?php foreach ( $students as $s ) : ?>
                <option value="<?php echo (int) $s->id; ?>" <?php selected( $filter_student, $s->id ); ?>><?php echo esc_html( $s->child_name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-field" style="margin-bottom:0;">
            <label><?php esc_html_e( 'Term', 'yourjannah' ); ?></label>
            <select name="term_id">
                <option value="0"><?php esc_html_e( 'All Terms', 'yourjannah' ); ?></option>
                <?php foreach ( $terms as $t_row ) : ?>
                <option value="<?php echo (int) $t_row->id; ?>" <?php selected( $filter_term, $t_row->id ); ?>><?php echo esc_html( $t_row->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="d-btn d-btn--outline d-btn--sm"><?php esc_html_e( 'Filter', 'yourjannah' ); ?></button>
    </form>

    <?php if ( empty( $reports ) ) : ?>
    <div class="d-empty"><div class="d-empty__icon">&#128203;</div><p><?php esc_html_e( 'No reports found. Add one above.', 'yourjannah' ); ?></p></div>
    <?php else : ?>
    <table class="d-table">
        <thead><tr>
            <th><?php esc_html_e( 'Student', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Term', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Subject', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Grade', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Behaviour', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'Quran Progress', 'yourjannah' ); ?></th>
            <th><?php esc_html_e( 'By', 'yourjannah' ); ?></th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ( $reports as $rp ) :
            $beh_class = 'gray';
            if ( $rp->behaviour === 'excellent' )          $beh_class = 'green';
            if ( $rp->behaviour === 'good' )               $beh_class = 'blue';
            if ( $rp->behaviour === 'needs_improvement' )  $beh_class = 'yellow';
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html( $rp->child_name ); ?></strong>
                <?php if ( $rp->year_group ) : ?><br><span style="font-size:11px;color:var(--text-dim);"><?php echo esc_html( $rp->year_group ); ?></span><?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php echo esc_html( $rp->term_name ?: '--' ); ?></td>
            <td><?php echo esc_html( $rp->subject ); ?></td>
            <td style="font-weight:700;"><?php echo esc_html( $rp->grade ?: '--' ); ?></td>
            <td><span class="d-badge d-badge--<?php echo esc_attr( $beh_class ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $rp->behaviour ) ) ); ?></span></td>
            <td style="font-size:12px;"><?php echo esc_html( $rp->quran_progress ?: '--' ); ?></td>
            <td style="font-size:12px;"><?php echo esc_html( $rp->created_by ?: '--' ); ?><br><span style="color:var(--text-dim);"><?php echo esc_html( substr( $rp->created_at, 0, 10 ) ); ?></span></td>
            <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this report?', 'yourjannah' ); ?>')">
                    <?php wp_nonce_field( 'ynj_dash_mad', '_ynj_nonce' ); ?>
                    <input type="hidden" name="action" value="delete_report">
                    <input type="hidden" name="report_id" value="<?php echo (int) $rp->id; ?>">
                    <button type="submit" class="d-btn d-btn--sm d-btn--danger"><?php esc_html_e( 'Delete', 'yourjannah' ); ?></button>
                </form>
            </td>
        </tr>
        <?php if ( $rp->teacher_notes ) : ?>
        <tr>
            <td colspan="8" style="padding-top:0;border-top:none;font-size:12px;color:#555;">
                <em><?php echo esc_html( $rp->teacher_notes ); ?></em>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php endif; /* end tab switch */ ?>
