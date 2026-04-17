<?php
/**
 * Template: Madrassah — Islamic School (Public + Parent Portal)
 *
 * Pure PHP. No JS API calls.
 * Shows: term dates, class schedule, enrolment form, parent portal.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
$mosque = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$mosque_name = $mosque ? $mosque->name : '';

// ── Load all data server-side ──
$terms = [];
$classes = [];
$students = [];
$fees = [];
$current_term = null;

if ( $mosque_id && class_exists( 'YNJ_DB' ) ) {
    global $wpdb;
    $tt = YNJ_DB::table( 'madrassah_terms' );
    $ct = YNJ_DB::table( 'classes' );
    $st = YNJ_DB::table( 'madrassah_students' );
    $ft = YNJ_DB::table( 'madrassah_fees' );

    // Terms (current + upcoming)
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$tt'" ) === $tt ) {
        $terms = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $tt WHERE mosque_id = %d ORDER BY start_date DESC LIMIT 5", $mosque_id
        ) ) ?: [];
        foreach ( $terms as $t ) {
            if ( $t->start_date <= date( 'Y-m-d' ) && ( ! $t->end_date || $t->end_date >= date( 'Y-m-d' ) ) ) {
                $current_term = $t; break;
            }
        }
    }

    // Madrassah classes
    $classes = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $ct WHERE mosque_id = %d AND status = 'active' AND (category LIKE '%%madrassah%%' OR category LIKE '%%Quran%%' OR category LIKE '%%Arabic%%' OR category LIKE '%%Tajweed%%' OR category LIKE '%%Islamic%%' OR category LIKE '%%Hifz%%') ORDER BY day_of_week ASC, start_time ASC",
        $mosque_id
    ) ) ?: [];

    // If no madrassah-specific classes, show all classes as fallback
    if ( empty( $classes ) ) {
        $classes = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $ct WHERE mosque_id = %d AND status = 'active' ORDER BY day_of_week ASC, start_time ASC LIMIT 20",
            $mosque_id
        ) ) ?: [];
    }

    // Student count
    $student_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $st WHERE mosque_id = %d AND status = 'active'", $mosque_id
    ) );

    // Parent's children (if logged in)
    if ( is_user_logged_in() ) {
        $email = wp_get_current_user()->user_email;
        $students = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $st WHERE mosque_id = %d AND parent_email = %s AND status = 'active' ORDER BY child_name ASC",
            $mosque_id, $email
        ) ) ?: [];

        // Outstanding fees for parent's children
        if ( ! empty( $students ) ) {
            $child_ids = array_map( function( $s ) { return (int) $s->id; }, $students );
            $ids_str = implode( ',', $child_ids );
            $fees = $wpdb->get_results(
                "SELECT f.*, s.child_name FROM $ft f JOIN $st s ON s.id = f.student_id WHERE f.student_id IN ($ids_str) AND f.status = 'unpaid' ORDER BY f.due_date ASC"
            ) ?: [];
        }
    }
}

// Handle enrolment POST
$enrol_success = ''; $enrol_error = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_ynj_nonce'] ?? '', 'ynj_madrassah_enrol' ) && is_user_logged_in() ) {
    $wp_user = wp_get_current_user();
    $child_name = sanitize_text_field( $_POST['child_name'] ?? '' );
    if ( ! $child_name ) {
        $enrol_error = __( 'Child name is required.', 'yourjannah' );
    } else {
        global $wpdb;
        $st = YNJ_DB::table( 'madrassah_students' );
        $wpdb->insert( $st, [
            'mosque_id'         => $mosque_id,
            'child_name'        => $child_name,
            'child_dob'         => sanitize_text_field( $_POST['child_dob'] ?? '' ),
            'year_group'        => sanitize_text_field( $_POST['year_group'] ?? '' ),
            'class_id'          => (int) ( $_POST['class_id'] ?? 0 ),
            'parent_name'       => sanitize_text_field( $_POST['parent_name'] ?? $wp_user->display_name ),
            'parent_email'      => $wp_user->user_email,
            'parent_phone'      => sanitize_text_field( $_POST['parent_phone'] ?? '' ),
            'emergency_contact' => sanitize_text_field( $_POST['emergency_contact'] ?? '' ),
            'medical_notes'     => sanitize_textarea_field( $_POST['medical_notes'] ?? '' ),
            'status'            => 'active',
        ] );
        if ( $wpdb->insert_id ) {
            $enrol_success = __( 'Enrolled successfully! Welcome to the madrassah.', 'yourjannah' );
            // Refresh students list
            $students = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $st WHERE mosque_id = %d AND parent_email = %s AND status = 'active' ORDER BY child_name ASC",
                $mosque_id, $wp_user->user_email
            ) ) ?: [];
        } else {
            $enrol_error = __( 'Could not enrol. Please try again.', 'yourjannah' );
        }
    }
}

$days_order = [ 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7 ];
?>

<main class="ynj-main">
    <!-- Hero -->
    <div style="text-align:center;padding:24px 16px 16px;">
        <div style="font-size:36px;margin-bottom:6px;">📚</div>
        <h1 style="font-size:22px;font-weight:800;margin-bottom:4px;"><?php echo esc_html( $mosque_name ); ?> <?php esc_html_e( 'Madrassah', 'yourjannah' ); ?></h1>
        <p class="ynj-text-muted"><?php esc_html_e( 'Islamic education for children — Quran, Arabic, Islamic Studies', 'yourjannah' ); ?></p>

        <div style="display:flex;gap:10px;justify-content:center;margin-top:14px;">
            <div style="text-align:center;background:#fff;border-radius:10px;padding:12px 16px;border:1px solid #e5e7eb;">
                <strong style="font-size:20px;color:#00ADEF;"><?php echo $student_count ?? 0; ?></strong>
                <span style="display:block;font-size:10px;color:#6b8fa3;text-transform:uppercase;font-weight:600;"><?php esc_html_e( 'Students', 'yourjannah' ); ?></span>
            </div>
            <?php if ( $current_term ) : ?>
            <div style="text-align:center;background:#fff;border-radius:10px;padding:12px 16px;border:1px solid #e5e7eb;">
                <strong style="font-size:14px;color:#0a1628;"><?php echo esc_html( $current_term->name ); ?></strong>
                <span style="display:block;font-size:10px;color:#6b8fa3;text-transform:uppercase;font-weight:600;"><?php esc_html_e( 'Current Term', 'yourjannah' ); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $enrol_success ) : ?><div style="background:#dcfce7;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:12px;font-size:13px;font-weight:600;">✅ <?php echo esc_html( $enrol_success ); ?></div><?php endif; ?>
    <?php if ( $enrol_error ) : ?><div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:12px;font-size:13px;">❌ <?php echo esc_html( $enrol_error ); ?></div><?php endif; ?>

    <!-- Term Dates -->
    <?php if ( ! empty( $terms ) ) : ?>
    <div class="ynj-card" style="margin-bottom:12px;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">📅 <?php esc_html_e( 'Term Dates', 'yourjannah' ); ?></h3>
        <?php foreach ( $terms as $t ) :
            $is_current = ( $current_term && $current_term->id === $t->id );
            $fee_display = ( $t->fee_pence ?? 0 ) > 0 ? '£' . number_format( $t->fee_pence / 100, 0 ) : '';
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;margin-bottom:6px;border-radius:8px;background:<?php echo $is_current ? '#f0fdf4' : '#f9fafb'; ?>;border:1px solid <?php echo $is_current ? '#86efac' : '#e5e7eb'; ?>;">
            <div>
                <strong style="font-size:14px;"><?php echo esc_html( $t->name ); ?></strong>
                <?php if ( $is_current ) : ?><span style="font-size:10px;padding:2px 6px;border-radius:4px;background:#16a34a;color:#fff;margin-left:6px;">NOW</span><?php endif; ?>
                <div style="font-size:12px;color:#6b8fa3;margin-top:2px;">
                    <?php echo esc_html( date( 'j M Y', strtotime( $t->start_date ) ) ); ?> — <?php echo $t->end_date ? esc_html( date( 'j M Y', strtotime( $t->end_date ) ) ) : esc_html__( 'Ongoing', 'yourjannah' ); ?>
                </div>
            </div>
            <?php if ( $fee_display ) : ?>
            <div style="text-align:right;">
                <strong style="color:#16a34a;"><?php echo esc_html( $fee_display ); ?></strong>
                <div style="font-size:10px;color:#6b8fa3;"><?php esc_html_e( 'per term', 'yourjannah' ); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Class Schedule -->
    <?php if ( ! empty( $classes ) ) : ?>
    <div class="ynj-card" style="margin-bottom:12px;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">🎓 <?php esc_html_e( 'Class Schedule', 'yourjannah' ); ?></h3>
        <?php foreach ( $classes as $c ) :
            $time = $c->start_time ? substr( $c->start_time, 0, 5 ) : '';
            $end = $c->end_time ? substr( $c->end_time, 0, 5 ) : '';
            $price = $c->price_pence > 0 ? '£' . number_format( $c->price_pence / 100, 0 ) : __( 'Free', 'yourjannah' );
            $spots = $c->max_capacity > 0 ? ( $c->max_capacity - (int) $c->enrolled_count ) : null;
        ?>
        <div style="display:flex;gap:12px;padding:12px;margin-bottom:8px;border-radius:10px;background:#f9fafb;border:1px solid #e5e7eb;">
            <div style="width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,#00ADEF,#0369a1);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">📖</div>
            <div style="flex:1;min-width:0;">
                <strong style="font-size:14px;"><?php echo esc_html( $c->title ); ?></strong>
                <span style="display:inline-block;font-size:10px;padding:2px 6px;border-radius:4px;background:#e8f4f8;color:#00ADEF;margin-left:4px;"><?php echo esc_html( $c->category ); ?></span>
                <div style="font-size:12px;color:#6b8fa3;margin-top:2px;">
                    <?php if ( $c->day_of_week ) echo esc_html( $c->day_of_week ); ?>
                    <?php if ( $time ) echo ' · ' . esc_html( $time ); ?>
                    <?php if ( $end ) echo '–' . esc_html( $end ); ?>
                    <?php if ( $c->instructor_name ) echo ' · ' . esc_html( $c->instructor_name ); ?>
                </div>
                <div style="font-size:12px;margin-top:4px;">
                    <span style="font-weight:700;color:#16a34a;"><?php echo esc_html( $price ); ?></span>
                    <?php if ( $spots !== null ) : ?>
                    <span style="margin-left:8px;color:<?php echo $spots > 0 ? '#6b8fa3' : '#dc2626'; ?>;"><?php echo $spots > 0 ? sprintf( esc_html__( '%d spots left', 'yourjannah' ), $spots ) : esc_html__( 'Full', 'yourjannah' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Parent Portal (logged in) -->
    <?php if ( is_user_logged_in() && ! empty( $students ) ) : ?>
    <div class="ynj-card" style="margin-bottom:12px;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">👨‍👩‍👧 <?php esc_html_e( 'Your Children', 'yourjannah' ); ?></h3>
        <?php foreach ( $students as $s ) : ?>
        <div style="background:#f9fafb;border-radius:10px;padding:14px;margin-bottom:8px;border:1px solid #e5e7eb;">
            <strong style="font-size:14px;"><?php echo esc_html( $s->child_name ); ?></strong>
            <div style="font-size:12px;color:#6b8fa3;margin-top:2px;">
                <?php echo esc_html( $s->year_group ?: __( 'No year group', 'yourjannah' ) ); ?>
                · <?php printf( esc_html__( 'Enrolled %s', 'yourjannah' ), esc_html( date( 'j M Y', strtotime( $s->created_at ) ) ) ); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Outstanding Fees -->
    <?php if ( ! empty( $fees ) ) : ?>
    <div class="ynj-card" style="margin-bottom:12px;border-left:4px solid #f59e0b;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">💷 <?php esc_html_e( 'Outstanding Fees', 'yourjannah' ); ?></h3>
        <?php foreach ( $fees as $f ) : ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0;">
            <div>
                <strong style="font-size:13px;"><?php echo esc_html( $f->child_name ); ?></strong>
                <div style="font-size:11px;color:#6b8fa3;"><?php echo esc_html( $f->description ?? __( 'Term fee', 'yourjannah' ) ); ?></div>
            </div>
            <div style="text-align:right;">
                <strong style="color:#dc2626;">£<?php echo number_format( $f->amount_pence / 100, 2 ); ?></strong>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Enrolment Form -->
    <div class="ynj-card" style="margin-bottom:12px;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:4px;">✏️ <?php esc_html_e( 'Enrol Your Child', 'yourjannah' ); ?></h3>
        <p style="font-size:12px;color:#6b8fa3;margin-bottom:12px;"><?php esc_html_e( 'Fill in the form below to register your child for the madrassah.', 'yourjannah' ); ?></p>

        <?php if ( ! is_user_logged_in() ) : ?>
        <div style="text-align:center;padding:20px;">
            <p style="font-size:13px;color:#6b8fa3;margin-bottom:12px;"><?php esc_html_e( 'Please sign in to enrol your child.', 'yourjannah' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/login?redirect=' . urlencode( '/mosque/' . $slug . '/madrassah' ) ) ); ?>" class="ynj-btn" style="display:inline-flex;"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
        </div>
        <?php else : ?>
        <form method="post">
            <?php wp_nonce_field( 'ynj_madrassah_enrol', '_ynj_nonce' ); ?>

            <div style="margin-bottom:10px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Child\'s Full Name *', 'yourjannah' ); ?></label>
                <input type="text" name="child_name" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Date of Birth', 'yourjannah' ); ?></label>
                    <input type="date" name="child_dob" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Year Group', 'yourjannah' ); ?></label>
                    <select name="year_group" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                        <option value=""><?php esc_html_e( '— Select —', 'yourjannah' ); ?></option>
                        <?php foreach ( [ 'Reception', 'Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6', 'Year 7', 'Year 8', 'Year 9', 'Year 10' ] as $yg ) : ?>
                        <option value="<?php echo esc_attr( $yg ); ?>"><?php echo esc_html( $yg ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ( ! empty( $classes ) ) : ?>
            <div style="margin-bottom:10px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Preferred Class', 'yourjannah' ); ?></label>
                <select name="class_id" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                    <option value="0"><?php esc_html_e( '— Any available —', 'yourjannah' ); ?></option>
                    <?php foreach ( $classes as $c ) : ?>
                    <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html( $c->title . ' (' . ( $c->day_of_week ?: '' ) . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Parent Name', 'yourjannah' ); ?></label>
                    <input type="text" name="parent_name" value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Parent Phone', 'yourjannah' ); ?></label>
                    <input type="tel" name="parent_phone" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:10px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Emergency Contact (name + phone)', 'yourjannah' ); ?></label>
                <input type="text" name="emergency_contact" placeholder="<?php esc_attr_e( 'e.g. Grandma — 07700 900000', 'yourjannah' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;">
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:#6b8fa3;display:block;margin-bottom:4px;"><?php esc_html_e( 'Medical Notes / Allergies', 'yourjannah' ); ?></label>
                <textarea name="medical_notes" rows="2" placeholder="<?php esc_attr_e( 'Any allergies, conditions, or special requirements...', 'yourjannah' ); ?>" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;resize:vertical;"></textarea>
            </div>

            <button type="submit" class="ynj-btn" style="width:100%;justify-content:center;">📝 <?php esc_html_e( 'Enrol Child', 'yourjannah' ); ?></button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Contact -->
    <?php if ( $mosque->phone || $mosque->email ) : ?>
    <div class="ynj-card" style="margin-bottom:12px;text-align:center;">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">📞 <?php esc_html_e( 'Madrassah Enquiries', 'yourjannah' ); ?></h3>
        <?php if ( $mosque->phone ) : ?>
        <a href="tel:<?php echo esc_attr( $mosque->phone ); ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:600;color:#00ADEF;text-decoration:none;">📞 <?php echo esc_html( $mosque->phone ); ?></a>
        <?php endif; ?>
        <?php if ( $mosque->email ) : ?>
        <br><a href="mailto:<?php echo esc_attr( $mosque->email ); ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#00ADEF;text-decoration:none;margin-top:4px;">✉️ <?php echo esc_html( $mosque->email ); ?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
