<?php
/**
 * Template: Classes Page
 *
 * Class cards at mosque level with category filter and course proposal form.
 *
 * @package YourJannah
 */

get_header();
$slug = ynj_mosque_slug();

// Pre-load ALL data server-side — zero API calls for primary data
$mosque    = ynj_get_mosque( $slug );
$mosque_id = $mosque ? (int) $mosque->id : 0;
$mosque_lat = $mosque ? (float) $mosque->latitude : 0;
$mosque_lng = $mosque ? (float) $mosque->longitude : 0;
$mosque_phone = $mosque ? $mosque->phone : '';
$classes = [];
if ( $mosque_id ) {
    $classes = class_exists( 'YNJ_Madrassah' ) ? YNJ_Madrassah::get_classes( $mosque_id ) : [];
    if ( ! is_array( $classes ) ) $classes = [];
}
?>
<style>
.ynj-class-card{background:rgba(255,255,255,.9);border-radius:16px;padding:18px;margin-bottom:14px;border:1px solid rgba(255,255,255,.6);box-shadow:0 2px 10px rgba(0,0,0,.04);}
.ynj-class-card__header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;}
.ynj-class-card__price{font-size:18px;font-weight:800;color:#00ADEF;white-space:nowrap;}
.ynj-class-card__price small{font-size:11px;font-weight:500;color:#6b8fa3;}
.ynj-class-card__instructor{display:flex;align-items:center;gap:8px;font-size:12px;color:#6b8fa3;margin-bottom:8px;}
.ynj-class-card__schedule{display:flex;flex-wrap:wrap;gap:6px;font-size:11px;margin-bottom:10px;}
.ynj-class-card__schedule span{background:#f0f8fc;padding:3px 8px;border-radius:6px;color:#0a1628;}
.ynj-class-card__spots{font-size:12px;color:#6b8fa3;margin-bottom:10px;}
@media(min-width:900px){.ynj-classes-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}.ynj-class-card{margin-bottom:0;}}
</style>

<main class="ynj-main">
    <?php if ( isset( $_GET['enrolled'] ) ) : ?>
        <div class="ynj-card" style="text-align:center;padding:30px 20px;">
            <div style="font-size:48px;margin-bottom:8px;">&#x2705;</div>
            <h2><?php esc_html_e( "You're Enrolled!", 'yourjannah' ); ?></h2>
            <p class="ynj-text-muted"><?php esc_html_e( 'Check your email for class details.', 'yourjannah' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ynj-search-bar" style="margin-bottom:14px;">
        <div class="ynj-search-bar__filters">
            <select id="cls-cat" class="ynj-search-bar__select" onchange="loadClasses()">
                <option value=""><?php esc_html_e( 'All Categories', 'yourjannah' ); ?></option>
                <option>Quran</option><option>Arabic</option><option>Tajweed</option><option>Islamic Studies</option>
                <option>Fiqh</option><option>Seerah</option><option>Business</option><option>SEO</option>
                <option>Marketing</option><option>Finance</option><option>Health</option><option>Fitness</option>
                <option>Cooking</option><option>Parenting</option><option>Youth</option><option>Sisters</option>
            </select>
        </div>
    </div>

    <div class="ynj-classes-grid" id="classes-list">
    <?php if ( empty( $classes ) ) : ?>
        <p class="ynj-text-muted" style="padding:20px;text-align:center;"><?php esc_html_e( 'No classes available. Check back soon.', 'yourjannah' ); ?></p>
    <?php endif; ?>
    </div>

    <!-- Enrolment Modal -->
    <div class="ynj-modal" id="enrol-modal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
        <div class="ynj-modal__content">
            <h3 id="enrol-modal-title"><?php esc_html_e( 'Enrol in Class', 'yourjannah' ); ?></h3>
            <form id="enrol-form" class="ynj-form">
                <input type="hidden" name="class_id" id="enrol-class-id">
                <div class="ynj-field"><label><?php esc_html_e( 'Your Name', 'yourjannah' ); ?> *</label><input type="text" name="user_name" required placeholder="<?php esc_attr_e( 'Full name', 'yourjannah' ); ?>"></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?> *</label><input type="email" name="user_email" required placeholder="your@email.com"></div>
            </form>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button class="ynj-btn" id="enrol-submit" type="button" style="flex:1;justify-content:center;"><?php esc_html_e( 'Enrol Now', 'yourjannah' ); ?></button>
                <button class="ynj-btn ynj-btn--outline" type="button" onclick="document.getElementById('enrol-modal').style.display='none'"><?php esc_html_e( 'Cancel', 'yourjannah' ); ?></button>
            </div>
            <p class="ynj-text-muted" id="enrol-error" style="margin-top:8px;color:#dc2626;"></p>
        </div>
    </div>
</main>

<!-- Teach a Course CTA -->
<div class="ynj-card" style="padding:24px 20px;margin-top:8px;">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;text-align:center;"><?php esc_html_e( 'Want to teach a course?', 'yourjannah' ); ?></h3>
    <p class="ynj-text-muted" style="margin-bottom:14px;text-align:center;"><?php esc_html_e( 'Share your knowledge with the community. Submit a proposal — the masjid will review and get back to you.', 'yourjannah' ); ?></p>

    <div id="propose-form-wrap">
        <form id="propose-form" class="ynj-form">
            <div class="ynj-field"><label><?php esc_html_e( 'Your Name', 'yourjannah' ); ?> *</label><input type="text" name="name" required placeholder="<?php esc_attr_e( 'Full name', 'yourjannah' ); ?>"></div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Email', 'yourjannah' ); ?> *</label><input type="email" name="email" required></div>
                <div class="ynj-field"><label><?php esc_html_e( 'Phone', 'yourjannah' ); ?></label><input type="tel" name="phone"></div>
            </div>
            <div class="ynj-field"><label><?php esc_html_e( 'Course Title', 'yourjannah' ); ?> *</label><input type="text" name="title" required placeholder="<?php esc_attr_e( 'e.g. SEO for Small Businesses', 'yourjannah' ); ?>"></div>
            <div class="ynj-field-row">
                <div class="ynj-field"><label><?php esc_html_e( 'Category', 'yourjannah' ); ?></label>
                    <select name="category">
                        <option>Business</option><option>SEO</option><option>Marketing</option><option>Finance</option>
                        <option>Quran</option><option>Arabic</option><option>Islamic Studies</option><option>Fiqh</option>
                        <option>Health</option><option>Fitness</option><option>Cooking</option><option>Youth</option>
                        <option>Sisters</option><option>Other</option>
                    </select>
                </div>
                <div class="ynj-field"><label><?php esc_html_e( 'Suggested Price', 'yourjannah' ); ?></label><input type="text" name="price" placeholder="<?php esc_attr_e( 'e.g. £10/session, Free', 'yourjannah' ); ?>"></div>
            </div>
            <div class="ynj-field"><label><?php esc_html_e( 'Description', 'yourjannah' ); ?> *</label><textarea name="description" rows="3" required placeholder="<?php esc_attr_e( 'What will students learn? How many sessions? Any prerequisites?', 'yourjannah' ); ?>"></textarea></div>
        </form>
        <button class="ynj-btn" id="propose-btn" type="button" style="width:100%;justify-content:center;margin-top:12px;" onclick="submitProposal()"><?php esc_html_e( 'Submit Proposal', 'yourjannah' ); ?></button>
        <p class="ynj-text-muted" id="propose-status" style="margin-top:8px;text-align:center;"></p>
    </div>

    <div id="propose-success" style="display:none;text-align:center;padding:20px 0;">
        <div style="font-size:36px;margin-bottom:8px;">&#x2705;</div>
        <h4><?php esc_html_e( 'Proposal Submitted!', 'yourjannah' ); ?></h4>
        <p class="ynj-text-muted"><?php esc_html_e( 'The masjid will review your proposal and contact you.', 'yourjannah' ); ?></p>
    </div>

    <div style="text-align:center;margin-top:12px;">
        <a id="whatsapp-teach" href="#" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;color:#25D366;font-size:13px;font-weight:600;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.637-1.467A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-2.09 0-4.034-.656-5.634-1.775l-.403-.262-2.75.87.913-2.684-.287-.442A9.715 9.715 0 012.25 12c0-5.385 4.365-9.75 9.75-9.75S21.75 6.615 21.75 12s-4.365 9.75-9.75 9.75z"/></svg>
            <?php esc_html_e( 'Or contact via WhatsApp', 'yourjannah' ); ?>
        </a>
    </div>
</div>

<script>
(function(){
    const slug = <?php echo wp_json_encode( $slug ); ?>;
    const API = ynjData.restUrl;
    let mosqueLat = <?php echo $mosque_lat ? (float) $mosque_lat : 'null'; ?>;
    let mosqueLng = <?php echo $mosque_lng ? (float) $mosque_lng : 'null'; ?>;
    // Pre-loaded from PHP — instant, no API calls
    let localClasses = <?php echo wp_json_encode( array_map( function( $c ) {
        return [
            'id'              => (int) $c->id,
            'title'           => $c->title,
            'description'     => $c->description,
            'instructor_name' => $c->instructor_name,
            'day_of_week'     => $c->day_of_week,
            'start_time'      => $c->start_time,
            'location'        => $c->location,
            'price_pence'     => (int) $c->price_pence,
            'category'        => $c->category,
            'price_type'      => $c->price_type,
            'max_capacity'    => (int) $c->max_capacity,
            'enrolled_count'  => (int) $c->enrolled_count,
            'total_sessions'  => (int) $c->total_sessions,
            'is_online'       => (int) $c->is_online,
        ];
    }, $classes ) ); ?>;
    let nearbyClasses = [];
    let nearbyLoaded = false;
    document.querySelectorAll('[data-nav-mosque]').forEach(el => el.href = el.dataset.navMosque.replace('{slug}', slug));

    // Submit class proposal as enquiry
    window.submitProposal = async function() {
        const form = document.getElementById('propose-form');
        const name = form.querySelector('[name="name"]').value.trim();
        const email = form.querySelector('[name="email"]').value.trim();
        const title = form.querySelector('[name="title"]').value.trim();
        const desc = form.querySelector('[name="description"]').value.trim();
        if (!name || !email || !title || !desc) {
            document.getElementById('propose-status').textContent = 'Please fill in all required fields.';
            return;
        }
        document.getElementById('propose-btn').disabled = true;
        document.getElementById('propose-btn').textContent = 'Submitting...';

        const category = form.querySelector('[name="category"]').value;
        const price = form.querySelector('[name="price"]').value.trim();
        const phone = form.querySelector('[name="phone"]').value.trim();

        const resp = await fetch(API + 'enquiries', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                mosque_slug: slug,
                name: name, email: email, phone: phone,
                type: 'class_proposal',
                subject: 'Class Proposal: ' + title,
                message: `Course: ${title}\nCategory: ${category}\nSuggested Price: ${price||'TBD'}\n\n${desc}\n\nFrom: ${name} (${email}${phone ? ', '+phone : ''})`
            })
        }).then(r=>r.json()).catch(()=>({ok:false}));

        if (resp.ok) {
            document.getElementById('propose-form-wrap').style.display = 'none';
            document.getElementById('propose-success').style.display = '';
        } else {
            document.getElementById('propose-status').textContent = resp.error || 'Failed to submit.';
            document.getElementById('propose-btn').disabled = false;
            document.getElementById('propose-btn').textContent = 'Submit Proposal';
        }
    };

    // WhatsApp link from PHP pre-loaded phone — no API call
    <?php if ( $mosque_phone ) :
        $wa_phone = preg_replace( '/[^0-9+]/', '', $mosque_phone );
        $wa_phone = preg_replace( '/^0/', '+44', $wa_phone );
    ?>
    document.getElementById('whatsapp-teach').href = 'https://wa.me/<?php echo esc_js( $wa_phone ); ?>?text=' + encodeURIComponent('Assalamu alaikum, I would like to teach a course at your masjid. Can we discuss?');
    <?php endif; ?>

    const catIcons = {Quran:'📖',Arabic:'📚',Tajweed:'🎙️','Islamic Studies':'🕌',Fiqh:'⚖️',Seerah:'📜',Business:'💼',SEO:'🔍',Marketing:'📱',Finance:'💰',Health:'🏥',Fitness:'💪',Cooking:'🍳',Parenting:'👪',Youth:'👦',Sisters:'👩'};

    var allLocalClasses = localClasses.slice(); // keep unfiltered copy
    window.loadClasses = function() {
        const cat = document.getElementById('cls-cat').value;
        if (cat) {
            localClasses = allLocalClasses.filter(function(c){ return c.category === cat; });
        } else {
            localClasses = allLocalClasses.slice();
        }
        renderClassList();
    };

    function renderClassList() {
        const el = document.getElementById('classes-list');
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        const all = radius > 0 ? localClasses.concat(nearbyClasses) : localClasses;
        if (!all.length) { el.innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;">No classes available. Check back soon.</p>'; return; }
        el.innerHTML = all.map(c => {
            let card = renderClassCard(c);
            if (c._mosque_name) card = card.replace('</div>\n', '<div style="font-size:11px;color:#00ADEF;font-weight:600;margin-top:6px;">🕌 ' + c._mosque_name + '</div>\n</div>\n');
            return card;
        }).join('');
    }

    // Radius change
    window.onRadiusChange = function() {
        const radius = parseInt((document.getElementById('ynj-radius')||{}).value) || 0;
        if (radius === 0) { nearbyClasses = []; renderClassList(); return; }
        if (nearbyLoaded) { renderClassList(); return; }
        if (!mosqueLat) { renderClassList(); return; }

        document.getElementById('classes-list').innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;">Loading nearby classes...</p>';
        const radiusKm = radius === 9999 ? 9999 : radius * 1.609;
        fetch(API + 'mosques/nearest?lat=' + mosqueLat + '&lng=' + mosqueLng + '&limit=10&radius_km=' + radiusKm)
            .then(r => r.json())
            .then(data => {
                const mosques = (data.mosques || []).filter(m => m.slug !== slug);
                return Promise.all(mosques.slice(0,8).map(m =>
                    fetch(API + 'mosques/' + m.slug + '/classes').then(r => r.json())
                        .then(d => (d.classes||[]).map(c => Object.assign(c, {_mosque_name:m.name})))
                        .catch(() => [])
                ));
            })
            .then(results => { nearbyClasses = (results||[]).flat(); nearbyLoaded = true; renderClassList(); })
            .catch(() => { nearbyLoaded = true; renderClassList(); });
    };

    function renderClassCard(c) {
        const icon = catIcons[c.category] || '📚';
        const price = c.price_pence > 0 ? '£' + (c.price_pence/100).toFixed(0) : 'Free';
        const priceLabel = c.price_type === 'per_session' ? '/session' : (c.price_type === 'monthly' ? '/month' : '');
        const spots = c.max_capacity > 0 ? (c.max_capacity - c.enrolled_count) + ' spots left' : '';
        const online = c.is_online ? '<span>🌐 Online</span>' : '';
        const time = c.start_time ? String(c.start_time).replace(/:\d{2}$/,'') : '';

        return `<div class="ynj-class-card">
            <div class="ynj-class-card__header">
                <div>
                    <span class="ynj-badge">${icon} ${c.category||'Class'}</span>
                    <h3 style="font-size:16px;font-weight:700;margin:6px 0 2px;">${c.title}</h3>
                </div>
                <div class="ynj-class-card__price">${price}<small>${priceLabel}</small></div>
            </div>
            ${c.instructor_name ? `<div class="ynj-class-card__instructor">👤 ${c.instructor_name}</div>` : ''}
            <p style="font-size:13px;color:#555;margin-bottom:8px;">${(c.description||'').slice(0,100)}${(c.description||'').length>100?'...':''}</p>
            <div class="ynj-class-card__schedule">
                ${c.day_of_week ? `<span>📅 ${c.day_of_week}s</span>` : ''}
                ${time ? `<span>🕐 ${time}</span>` : ''}
                ${c.total_sessions > 1 ? `<span>📋 ${c.total_sessions} sessions</span>` : ''}
                ${c.location ? `<span>📍 ${c.location}</span>` : ''}
                ${online}
            </div>
            ${spots ? `<div class="ynj-class-card__spots">🪑 ${spots}</div>` : ''}
            <button class="ynj-btn" style="width:100%;justify-content:center;" onclick="enrolClass(${c.id},'${c.title.replace(/'/g,"\\'")}',${c.price_pence})">
                ${c.price_pence > 0 ? '🎓 Book — '+price+(priceLabel||'') : '🎓 Enrol — Free'}
            </button>
        </div>`;
    }

    window.enrolClass = function(id, title, price) {
        document.getElementById('enrol-class-id').value = id;
        document.getElementById('enrol-modal-title').textContent = 'Enrol: ' + title;
        document.getElementById('enrol-error').textContent = '';
        document.getElementById('enrol-modal').style.display = '';
    };

    document.getElementById('enrol-submit').addEventListener('click', async function() {
        var btn = this;
        var form = document.getElementById('enrol-form');
        var id = form.querySelector('[name="class_id"]').value;
        var name = form.querySelector('[name="user_name"]').value.trim();
        var email = form.querySelector('[name="user_email"]').value.trim();
        if (!name || !email) { document.getElementById('enrol-error').textContent = 'Name and email required.'; return; }

        btn.disabled = true; btn.textContent = 'Enrolling...';
        try {
            var resp = await fetch(API + 'classes/' + id + '/enrol', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({user_name:name, user_email:email})
            });
            var data = await resp.json();
            if (data.ok && data.checkout_url) window.location.href = data.checkout_url;
            else if (data.ok && data.free) window.location.href = <?php echo wp_json_encode( home_url( '/mosque/' . $slug . '/classes?enrolled=1' ) ); ?>;
            else { document.getElementById('enrol-error').textContent = data.error || 'Could not enrol.'; btn.disabled = false; btn.textContent = 'Enrol Now'; }
        } catch(e) { document.getElementById('enrol-error').textContent = 'Network error.'; btn.disabled = false; btn.textContent = 'Enrol Now'; }
    });

    loadClasses();
})();
</script>
<?php get_footer(); ?>
