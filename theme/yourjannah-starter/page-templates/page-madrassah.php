<?php
/**
 * Template: Madrassah Page
 *
 * Parent portal: child enrolment, fees, attendance. Public info for visitors.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();
?>
<style>
.ynj-mad-hero{text-align:center;padding:30px 20px 16px;}
.ynj-mad-hero h2{font-size:20px;font-weight:800;margin-bottom:6px;}
.ynj-mad-hero p{font-size:13px;color:#6b8fa3;line-height:1.5;}
.ynj-mad-card{background:rgba(255,255,255,.85);backdrop-filter:blur(8px);border-radius:14px;padding:18px;margin-bottom:12px;border:1px solid rgba(255,255,255,.6);}
.ynj-mad-card h3{font-size:15px;font-weight:700;margin-bottom:8px;}
.ynj-mad-stat{display:flex;gap:10px;justify-content:center;margin-bottom:14px;}
.ynj-mad-stat div{text-align:center;background:rgba(255,255,255,.85);border-radius:10px;padding:12px 16px;flex:1;max-width:100px;}
.ynj-mad-stat strong{display:block;font-size:18px;font-weight:800;color:#00ADEF;}
.ynj-mad-stat span{font-size:10px;color:#6b8fa3;text-transform:uppercase;font-weight:600;}
.ynj-child-card{background:#fff;border-radius:12px;padding:16px;margin-bottom:10px;border:1px solid #e5e7eb;}
.ynj-child-card h4{font-size:14px;font-weight:700;margin-bottom:4px;}
.ynj-child-meta{font-size:12px;color:#6b8fa3;}
.ynj-att-bar{height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-top:6px;}
.ynj-att-bar div{height:100%;border-radius:4px;background:linear-gradient(90deg,#00ADEF,#16a34a);}
.ynj-fee-item{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0;}
.ynj-fee-item:last-child{border-bottom:none;}
.ynj-fee-badge{font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;}
.ynj-fee-badge--unpaid{background:#fef3c7;color:#92400e;}
.ynj-fee-badge--paid{background:#dcfce7;color:#166534;}
.ynj-mad-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:10px;background:#00ADEF;color:#fff;font-size:13px;font-weight:700;border:none;cursor:pointer;}
.ynj-report-card{background:#f9fafb;border-radius:10px;padding:14px;margin-bottom:8px;}
.ynj-report-card h4{font-size:13px;font-weight:700;margin-bottom:4px;}
</style>

<main class="ynj-main">
    <div class="ynj-mad-hero">
        <div style="font-size:36px;margin-bottom:6px;">&#x1F4DA;</div>
        <h2 id="mad-title"><?php esc_html_e( 'Madrassah', 'yourjannah' ); ?></h2>
        <p><?php esc_html_e( 'Islamic school for children. View your child\'s attendance, reports and pay fees.', 'yourjannah' ); ?></p>
    </div>

    <!-- Public info (always shown) -->
    <div id="mad-public">
        <div class="ynj-mad-stat" id="mad-stats" style="display:none;">
            <div><strong id="ms-students">0</strong><span><?php esc_html_e( 'Students', 'yourjannah' ); ?></span></div>
            <div><strong id="ms-term">&mdash;</strong><span><?php esc_html_e( 'Current Term', 'yourjannah' ); ?></span></div>
        </div>
    </div>

    <!-- Parent section (shown when logged in with children) -->
    <div id="parent-section" style="display:none;">
        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;"><?php esc_html_e( 'Your Children', 'yourjannah' ); ?></h3>
        <div id="children-list"></div>

        <h3 style="font-size:15px;font-weight:700;margin:16px 0 10px;"><?php esc_html_e( 'Outstanding Fees', 'yourjannah' ); ?></h3>
        <div id="fees-list"></div>
    </div>

    <!-- Not logged in prompt -->
    <div id="login-section" style="display:none;">
        <div class="ynj-mad-card" style="text-align:center;">
            <h3><?php esc_html_e( 'Parent Portal', 'yourjannah' ); ?></h3>
            <p style="font-size:13px;color:#6b8fa3;margin-bottom:12px;"><?php esc_html_e( 'Sign in to view your child\'s attendance, reports and pay fees.', 'yourjannah' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/login' ) ); ?>" class="ynj-mad-btn"><?php esc_html_e( 'Sign In', 'yourjannah' ); ?></a>
        </div>
    </div>

    <!-- Enrol prompt -->
    <div class="ynj-mad-card" id="enrol-section" style="display:none;">
        <h3><?php esc_html_e( 'Enrol Your Child', 'yourjannah' ); ?></h3>
        <div style="margin-top:10px;">
            <input id="en_child" placeholder="<?php esc_attr_e( 'Child\'s name', 'yourjannah' ); ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px;font-size:14px;">
            <div style="display:flex;gap:8px;">
                <input type="date" id="en_dob" placeholder="<?php esc_attr_e( 'Date of birth', 'yourjannah' ); ?>" style="flex:1;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                <select id="en_year" style="flex:1;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    <option value=""><?php esc_html_e( 'Year group', 'yourjannah' ); ?></option>
                    <option>Reception</option><option>Year 1</option><option>Year 2</option><option>Year 3</option>
                    <option>Year 4</option><option>Year 5</option><option>Year 6</option>
                </select>
            </div>
            <button class="ynj-mad-btn" style="width:100%;justify-content:center;margin-top:10px;" onclick="enrolChild()"><?php esc_html_e( 'Enrol Child', 'yourjannah' ); ?></button>
        </div>
    </div>
</main>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API  = ynjData.restUrl;
    const token = localStorage.getItem('ynj_user_token') || '';
    let mosqueId = 0;

    // Load public info
    fetch(API + 'mosques/' + slug + '/madrassah')
        .then(r => r.json())
        .then(data => {
            if (data.student_count > 0) {
                document.getElementById('mad-stats').style.display = 'flex';
                document.getElementById('ms-students').textContent = data.student_count;
                if (data.terms && data.terms.length) {
                    document.getElementById('ms-term').textContent = data.terms[0].name;
                }
            }
        }).catch(() => {});

    fetch(API + 'mosques/' + slug)
        .then(r => r.json())
        .then(resp => {
            const m = resp.mosque || resp;
            mosqueId = m.id;
            document.getElementById('mad-title').textContent = (m.name || 'Masjid') + ' <?php echo esc_js( __( 'Madrassah', 'yourjannah' ) ); ?>';
        }).catch(() => {});

    if (!token) {
        document.getElementById('login-section').style.display = 'block';
        return;
    }

    // Parent: load children
    document.getElementById('parent-section').style.display = 'block';
    document.getElementById('enrol-section').style.display = 'block';

    fetch(API + 'madrassah/children', {
        headers: { 'Authorization': 'Bearer ' + token }
    }).then(r => r.json()).then(data => {
        const kids = data.children || [];
        const el = document.getElementById('children-list');
        if (!kids.length) {
            el.innerHTML = '<p style="font-size:13px;color:#999;"><?php echo esc_js( __( 'No children enrolled yet. Use the form below to enrol.', 'yourjannah' ) ); ?></p>';
            return;
        }
        el.innerHTML = kids.filter(k => k.mosque_slug === slug).map(k => {
            return '<div class="ynj-child-card">' +
                '<h4>' + k.child_name + '</h4>' +
                '<div class="ynj-child-meta">' + (k.year_group || '<?php echo esc_js( __( 'No year group', 'yourjannah' ) ); ?>') + ' &middot; <?php echo esc_js( __( 'Enrolled', 'yourjannah' ) ); ?> ' + new Date(k.enrolled_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) + '</div>' +
            '</div>';
        }).join('') || '<p style="font-size:13px;color:#999;"><?php echo esc_js( __( 'No children enrolled at this mosque.', 'yourjannah' ) ); ?></p>';
    }).catch(() => {});

    // Parent: load fees
    fetch(API + 'madrassah/fees', {
        headers: { 'Authorization': 'Bearer ' + token }
    }).then(r => r.json()).then(data => {
        const fees = (data.fees || []).filter(f => f.status === 'unpaid');
        const el = document.getElementById('fees-list');
        if (!fees.length) {
            el.innerHTML = '<p style="font-size:13px;color:#999;"><?php echo esc_js( __( 'No outstanding fees.', 'yourjannah' ) ); ?></p>';
            return;
        }
        el.innerHTML = fees.map(f => {
            return '<div class="ynj-fee-item">' +
                '<div><strong style="font-size:13px;">' + f.child_name + '</strong><br><span style="font-size:11px;color:#999;">' + (f.term_name || '<?php echo esc_js( __( 'Term', 'yourjannah' ) ); ?>') + '</span></div>' +
                '<div style="text-align:right">' +
                '<strong>\u00a3' + (f.amount_pence/100).toFixed(2) + '</strong>' +
                '<button class="ynj-mad-btn" style="padding:6px 14px;font-size:12px;margin-left:8px;" onclick="payFee(' + f.id + ')"><?php echo esc_js( __( 'Pay', 'yourjannah' ) ); ?></button>' +
                '</div></div>';
        }).join('');
    }).catch(() => {});

    window.payFee = async function(feeId) {
        try {
            const res = await fetch(API + 'madrassah/fees/' + feeId + '/pay', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token }
            });
            const data = await res.json();
            if (data.ok && data.checkout_url) window.location.href = data.checkout_url;
            else alert(data.error || '<?php echo esc_js( __( 'Payment error.', 'yourjannah' ) ); ?>');
        } catch(e) { alert('<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>'); }
    };

    window.enrolChild = async function() {
        const name = document.getElementById('en_child').value;
        if (!name) { alert('<?php echo esc_js( __( 'Enter child name.', 'yourjannah' ) ); ?>'); return; }
        try {
            const res = await fetch(API + 'madrassah/enrol', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
                body: JSON.stringify({
                    mosque_slug: slug, child_name: name,
                    child_dob: document.getElementById('en_dob').value || null,
                    year_group: document.getElementById('en_year').value
                })
            });
            const data = await res.json();
            if (data.ok) { alert('<?php echo esc_js( __( 'Enrolled! Welcome to the madrassah.', 'yourjannah' ) ); ?>'); location.reload(); }
            else alert(data.error || '<?php echo esc_js( __( 'Enrolment failed.', 'yourjannah' ) ); ?>');
        } catch(e) { alert('<?php echo esc_js( __( 'Network error.', 'yourjannah' ) ); ?>'); }
    };

    // Payment success message
    const params = new URLSearchParams(window.location.search);
    if (params.get('payment') === 'success') {
        const hero = document.querySelector('.ynj-mad-hero');
        if (hero) hero.innerHTML = '<div style="font-size:36px;margin-bottom:6px;">&#x2705;</div><h2><?php echo esc_js( __( 'Fee Paid!', 'yourjannah' ) ); ?></h2><p><?php echo esc_js( __( 'JazakAllahu Khairan. Your payment has been received.', 'yourjannah' ) ); ?></p>';
    }
})();
</script>
<?php get_footer(); ?>
