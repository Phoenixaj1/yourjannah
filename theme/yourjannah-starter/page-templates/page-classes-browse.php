<?php
/**
 * Template: Browse Classes (Cross-Mosque)
 *
 * Cross-mosque class search with category, location, and text filters.
 *
 * @package YourJannah
 */

get_header();
?>
<style>
.ynj-class-card{background:rgba(255,255,255,.9);border-radius:16px;padding:18px;margin-bottom:14px;border:1px solid rgba(255,255,255,.6);box-shadow:0 2px 10px rgba(0,0,0,.04);}
.ynj-class-card__header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;}
.ynj-class-card__price{font-size:18px;font-weight:800;color:#00ADEF;white-space:nowrap;}
.ynj-class-card__price small{font-size:11px;font-weight:500;color:#6b8fa3;}
.ynj-class-card__instructor{display:flex;align-items:center;gap:8px;font-size:12px;color:#6b8fa3;margin-bottom:8px;}
.ynj-class-card__schedule{display:flex;flex-wrap:wrap;gap:6px;font-size:11px;margin-bottom:10px;}
.ynj-class-card__schedule span{background:#f0f8fc;padding:3px 8px;border-radius:6px;color:#0a1628;}
@media(min-width:900px){.ynj-classes-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}.ynj-class-card{margin-bottom:0;}}
</style>

<main class="ynj-main">
    <div class="ynj-search-bar">
        <input class="ynj-search-bar__input" id="cls-q" type="text" placeholder="<?php esc_attr_e( 'Search classes (e.g. tajweed, business, SEO)...', 'yourjannah' ); ?>" oninput="searchClasses()">
        <div class="ynj-search-bar__filters">
            <select id="cls-cat" class="ynj-search-bar__select" onchange="searchClasses()">
                <option value=""><?php esc_html_e( 'All Categories', 'yourjannah' ); ?></option>
                <option>Quran</option><option>Arabic</option><option>Tajweed</option><option>Islamic Studies</option>
                <option>Business</option><option>SEO</option><option>Marketing</option><option>Finance</option>
                <option>Health</option><option>Fitness</option><option>Cooking</option><option>Youth</option><option>Sisters</option>
            </select>
            <select id="cls-loc" class="ynj-search-bar__select" onchange="searchClasses()">
                <option value=""><?php esc_html_e( 'All locations', 'yourjannah' ); ?></option>
                <option value="online"><?php esc_html_e( 'Online only', 'yourjannah' ); ?></option>
                <option value="5"><?php esc_html_e( 'Within 5 miles', 'yourjannah' ); ?></option>
                <option value="25"><?php esc_html_e( 'Within 25 miles', 'yourjannah' ); ?></option>
                <option value="50"><?php esc_html_e( 'Within 50 miles', 'yourjannah' ); ?></option>
            </select>
        </div>
    </div>
    <div class="ynj-classes-grid" id="browse-list" style="margin-top:14px;">
        <p class="ynj-text-muted" style="padding:20px;text-align:center;"><?php esc_html_e( 'Loading classes...', 'yourjannah' ); ?></p>
    </div>
</main>

<script>
(function(){
    const API = ynjData.restUrl;
    let userLat = null, userLng = null;
    if ('geolocation' in navigator) navigator.geolocation.getCurrentPosition(p => { userLat=p.coords.latitude; userLng=p.coords.longitude; }, ()=>{}, {timeout:5000});
    // Also try saved postcode
    const pc = localStorage.getItem('ynj_user_postcode');
    if (pc && !userLat) {
        fetch('https://api.postcodes.io/postcodes/' + pc.replace(/\s/g,'')).then(r=>r.json()).then(d=>{
            if(d.result){userLat=d.result.latitude;userLng=d.result.longitude;}
        }).catch(()=>{});
    }

    const catIcons = {Quran:'\ud83d\udcd6',Arabic:'\ud83d\udcda',Tajweed:'\ud83c\udf99\ufe0f','Islamic Studies':'\ud83d\udd4c',Business:'\ud83d\udcbc',SEO:'\ud83d\udd0d',Marketing:'\ud83d\udcf1',Finance:'\ud83d\udcb0',Health:'\ud83c\udfe5',Fitness:'\ud83d\udcaa',Cooking:'\ud83c\udf73',Youth:'\ud83d\udc66',Sisters:'\ud83d\udc69'};

    let debounce;
    window.searchClasses = function() {
        clearTimeout(debounce);
        debounce = setTimeout(doSearch, 300);
    };

    function doSearch() {
        const q = document.getElementById('cls-q').value.trim();
        const cat = document.getElementById('cls-cat').value;
        const loc = document.getElementById('cls-loc').value;
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (cat) params.set('category', cat);
        if (loc === 'online') params.set('online', '1');
        else if (loc && userLat) { params.set('lat', userLat); params.set('lng', userLng); params.set('radius_km', parseInt(loc)*1.609); }
        else if (userLat) { params.set('lat', userLat); params.set('lng', userLng); }

        const el = document.getElementById('browse-list');
        el.innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;"><?php echo esc_js( __( 'Searching...', 'yourjannah' ) ); ?></p>';

        fetch(API + 'classes/browse?' + params).then(r=>r.json()).then(data => {
            const classes = data.classes || [];
            if (!classes.length) { el.innerHTML = '<p class="ynj-text-muted" style="padding:20px;text-align:center;"><?php echo esc_js( __( 'No classes found. Try different filters.', 'yourjannah' ) ); ?></p>'; return; }
            el.innerHTML = classes.map(c => {
                const icon = catIcons[c.category] || '\ud83d\udcda';
                const price = c.price_pence > 0 ? '\u00a3' + (c.price_pence/100).toFixed(0) : '<?php echo esc_js( __( 'Free', 'yourjannah' ) ); ?>';
                const priceLabel = c.price_type === 'per_session' ? '/session' : (c.price_type === 'monthly' ? '/month' : '');
                const time = c.start_time ? String(c.start_time).replace(/:\d{2}$/,'') : '';
                const dist = c.distance_km != null && c.distance_km < 9000 ? (c.distance_km < 1.6 ? (c.distance_km*0.621).toFixed(1)+'mi' : Math.round(c.distance_km*0.621)+'mi') : '';
                return '<div class="ynj-class-card">' +
                    '<div class="ynj-class-card__header"><div><span class="ynj-badge">' + icon + ' ' + (c.category||'Class') + '</span><h3 style="font-size:16px;font-weight:700;margin:6px 0 2px;">' + c.title + '</h3></div><div class="ynj-class-card__price">' + price + '<small>' + priceLabel + '</small></div></div>' +
                    (c.instructor_name ? '<div class="ynj-class-card__instructor">\ud83d\udc64 ' + c.instructor_name + '</div>' : '') +
                    '<div class="ynj-class-card__instructor">\ud83d\udd4c ' + (c.mosque_name||'') + (dist ? ' \u00b7 ' + dist : '') + '</div>' +
                    '<p style="font-size:13px;color:#555;margin-bottom:8px;">' + ((c.description||'').slice(0,80)) + '...</p>' +
                    '<div class="ynj-class-card__schedule">' +
                    (c.day_of_week ? '<span>\ud83d\udcc5 ' + c.day_of_week + 's</span>' : '') +
                    (time ? '<span>\ud83d\udd50 ' + time + '</span>' : '') +
                    (c.is_online ? '<span>\ud83c\udf10 Online</span>' : '') +
                    '</div>' +
                    '<a href="' + <?php echo wp_json_encode( home_url( '/mosque/' ) ); ?> + (c.mosque_slug||'') + '/classes" class="ynj-btn ynj-btn--outline" style="width:100%;justify-content:center;"><?php echo esc_js( __( 'View & Book', 'yourjannah' ) ); ?></a>' +
                '</div>';
            }).join('');
        });
    }

    setTimeout(doSearch, 500); // initial load
})();
</script>
<?php get_footer(); ?>
