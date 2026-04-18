/**
 * YourJannah HUD — Core UI logic
 *
 * Extracted from header.php inline <script>.
 * PHP template literals replaced with window.ynjHudData (set via wp_localize_script).
 *
 * Expected ynjHudData keys:
 *   quranVerse       – "Truly, in the remembrance of Allah do hearts find rest."
 *   quranRef         – "Quran 13:28"
 *   currentRank      – int, current mosque rank (0 = unranked)
 *   mosqueName       – string, current mosque name
 *   rankedUpText     – "ranked up!"
 *   dhikrActionText  – e.g. "Ameen"
 *   dhikrPoints      – int, e.g. 10
 */
var ynjHudData = window.ynjHudData || {};

(function(){
    /* ── Failsafe: if both guest + logged-in HUDs rendered, hide guest ── */
    var allHuds = document.querySelectorAll('.ynj-hud');
    if (allHuds.length > 1) {
        allHuds.forEach(function(h){ if (h.classList.contains('ynj-hud--guest')) h.style.display = 'none'; });
    }

    /* ── Popup toggles ── */
    function closeAllPopups() {
        ['hud-dhikr-popup', 'hud-league-popup', 'hud-info-popup'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }
    window.ynjHudDhikrToggle = function() {
        var popup = document.getElementById('hud-dhikr-popup');
        if (!popup) return;
        var show = popup.style.display === 'none';
        closeAllPopups();
        if (show) popup.style.display = 'flex';
    };
    window.ynjHudLeagueToggle = function() {
        var popup = document.getElementById('hud-league-popup');
        if (!popup) return;
        var show = popup.style.display === 'none';
        closeAllPopups();
        if (show) popup.style.display = 'flex';
    };
    window.ynjHudInfoToggle = function() {
        var popup = document.getElementById('hud-info-popup');
        if (!popup) return;
        var show = popup.style.display === 'none';
        closeAllPopups();
        if (show) popup.style.display = 'flex';
    };

    /* ── Confetti helper (duplicated for header — no dependency on profile page) ── */
    function hudConfetti(origin) {
        var rect = origin ? origin.getBoundingClientRect() : { left: innerWidth/2, top: innerHeight/2, width: 0 };
        var cx = rect.left + rect.width/2, cy = rect.top;
        var colors = ['#f59e0b','#287e61','#7c3aed','#00ADEF','#ef4444','#fbbf24','#34d399'];
        var emojis = ['\u2728','\u2B50','\uD83C\uDF1F','\uD83D\uDCAB','\u2764\uFE0F','\uD83D\uDE4F','\uD83C\uDF89'];
        for (var i = 0; i < 20; i++) {
            var p = document.createElement('div');
            var isE = Math.random() > .5;
            var angle = (Math.PI*2*i/20)+(Math.random()-.5);
            var vel = 50+Math.random()*70;
            var dx = Math.cos(angle)*vel, dy = Math.sin(angle)*vel - 30;
            p.textContent = isE ? emojis[Math.floor(Math.random()*emojis.length)] : '';
            p.style.cssText = 'position:fixed;left:'+cx+'px;top:'+cy+'px;z-index:10005;pointer-events:none;font-size:'+(isE?'14':'7')+'px;'
                + (isE ? '' : 'width:7px;height:7px;border-radius:50%;background:'+colors[Math.floor(Math.random()*colors.length)]+';')
                + 'transition:all '+(0.5+Math.random()*0.5)+'s cubic-bezier(.25,.46,.45,.94);opacity:1;';
            document.body.appendChild(p);
            requestAnimationFrame(function(el,x,y){return function(){el.style.transform='translate('+x+'px,'+y+'px) rotate('+(Math.random()*720-360)+'deg)';el.style.opacity='0';};}(p,dx,dy));
            setTimeout(function(el){return function(){el.remove();};}(p),1300);
        }
    }

    function hudFloatPts(text, origin) {
        var rect = origin ? origin.getBoundingClientRect() : {left:innerWidth/2,top:innerHeight/2,width:0};
        var el = document.createElement('div');
        el.textContent = text;
        el.style.cssText = 'position:fixed;left:'+(rect.left+rect.width/2)+'px;top:'+rect.top+'px;z-index:10006;pointer-events:none;font-size:22px;font-weight:900;color:#f59e0b;text-shadow:0 2px 8px rgba(0,0,0,.15);transform:translateX(-50%);';
        document.body.appendChild(el);
        requestAnimationFrame(function(){el.style.transition='all 1.2s ease-out';el.style.transform='translateX(-50%) translateY(-60px)';el.style.opacity='0';});
        setTimeout(function(){el.remove();},1400);
    }

    function hudAnimateCounter(el, newVal) {
        if (!el) return;
        var old = parseInt(el.textContent.replace(/,/g,''))||0;
        if (old===newVal) return;
        var diff=newVal-old, steps=Math.min(30,Math.abs(diff)), sv=diff/steps, cur=old, i=0;
        // Dramatic golden flash + scale up
        el.style.transition='transform .4s cubic-bezier(.34,1.56,.64,1), color .3s, text-shadow .3s';
        el.style.transform='scale(1.6)';
        el.style.color='#fbbf24';
        el.style.textShadow='0 0 12px rgba(245,158,11,.6)';
        // Roll up the number
        var iv=setInterval(function(){
            i++;cur=i>=steps?newVal:Math.round(old+sv*i);
            el.textContent=cur.toLocaleString();
            if(i>=steps){clearInterval(iv);}
        },25);
        // Settle back
        setTimeout(function(){
            el.style.transform='scale(1)';
            el.style.textShadow='none';
        },600);
        // Also pulse the XP bar fill if it exists
        var xpFill = document.querySelector('.ynj-hud__xp-fill');
        if (xpFill) {
            xpFill.style.boxShadow='0 0 14px rgba(40,126,97,.7)';
            setTimeout(function(){ xpFill.style.boxShadow='0 0 6px rgba(40,126,97,.4)'; },800);
        }
        // Update XP text counter too
        var xpText = document.querySelector('.ynj-hud__xp-text');
        if (xpText) {
            var xpOld = parseInt(xpText.textContent.replace(/,/g,''))||0;
            xpText.textContent = (xpOld + diff).toLocaleString() + ' dhikr';
        }
    }

    function hudToast(text, bg) {
        var t=document.createElement('div');
        t.style.cssText='position:fixed;bottom:80px;left:50%;z-index:10004;max-width:90%;padding:14px 24px;border-radius:14px;font-size:15px;font-weight:800;color:#fff;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.25);background:'+bg+';transform:translateX(-50%) translateY(20px) scale(.9);opacity:0;transition:all .35s cubic-bezier(.34,1.56,.64,1);';
        t.textContent=text;document.body.appendChild(t);
        requestAnimationFrame(function(){requestAnimationFrame(function(){t.style.transform='translateX(-50%) translateY(0) scale(1)';t.style.opacity='1';});});
        setTimeout(function(){t.style.transform='translateX(-50%) translateY(20px) scale(.9)';t.style.opacity='0';setTimeout(function(){t.remove();},400);},4000);
    }

    /* Make helpers available globally for other scripts */
    window.hudConfetti      = hudConfetti;
    window.hudFloatPts      = hudFloatPts;
    window.hudAnimateCounter = hudAnimateCounter;
    window.hudToast         = hudToast;

    /* ════════════════════════════════════════════════
       SACRED AMEEN — The breath, the reflection, the peace
       ════════════════════════════════════════════════ */
    window.ynjHudAmeen = function(btn, index) {
        if (typeof index === 'undefined') index = parseInt(btn.getAttribute('data-index') || '0');
        btn.disabled = true;
        btn.style.opacity = '.6';
        var reward = btn.getAttribute('data-reward') || '';

        // Warm haptic — long, gentle (not sharp buzz)
        if (navigator.vibrate) navigator.vibrate(200);

        var nonce = typeof ynjData !== 'undefined' ? ynjData.nonce : '';
        fetch('/wp-json/ynj/v1/ibadah/dhikr', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            credentials: 'same-origin',
            body: JSON.stringify({ index: index })
        }).then(function(r){return r.json();}).then(function(d){
            if (d.cooldown) {
                btn.disabled = false;
                btn.innerHTML = '<span style="font-size:11px;color:#6b8fa3;">Wait a moment...</span>';
                setTimeout(function(){ btn.innerHTML = '\uD83E\uDD32 Say Again'; btn.disabled = false; }, 2000);
                return;
            }
            if (d.ok && d.points > 0) {
                var item = document.getElementById('hud-dhikr-item-' + index);

                // ── THE BREATH: 2s of stillness ──
                // Card transitions to reflecting state — Arabic glows softly
                if (item) item.classList.add('ynj-dhikr-item--reflecting');

                // Remove the button, show the hadith reward as the reflection
                btn.outerHTML = '<div style="text-align:center;padding:12px 0;animation:ynj-popup-in .5s;">'
                    + '<div style="font-size:12px;color:#287e61;font-style:italic;line-height:1.5;">' + (reward || 'May Allah accept') + '</div>'
                    + '</div>';

                // Points update SILENTLY in the HUD — no golden flash during sacred moment
                var ptsEl = document.getElementById('hud-pts-num');
                if (ptsEl) {
                    var oldPts = parseInt(ptsEl.textContent.replace(/,/g,'')) || 0;
                    ptsEl.textContent = d.total.toLocaleString();
                }
                var heroPts = document.getElementById('hero-pts');
                if (heroPts) heroPts.textContent = d.total.toLocaleString();

                // ── AFTER THE BREATH (2.5s): gentle transition to done ──
                setTimeout(function(){
                    if (item) {
                        item.classList.remove('ynj-dhikr-item--reflecting');
                        item.classList.add('ynj-dhikr-item--done');
                        // Simplify to just the Arabic + "Said"
                        var arabic = item.querySelector('.ynj-dhikr-item__arabic');
                        if (arabic) arabic.style.opacity = '.45';
                        // Remove reward/source text, replace with quiet confirmation
                        var extras = item.querySelectorAll('.ynj-dhikr-item__reward, .ynj-dhikr-item__source, .ynj-dhikr-item__english');
                        extras.forEach(function(el){ el.remove(); });
                        // Add the "Said" marker
                        var doneDiv = item.querySelector('div[style*="animation"]');
                        if (doneDiv) doneDiv.innerHTML = '<span style="color:#287e61;font-size:11px;font-weight:600;">\u2714 Said</span>'
                            + ' <button type="button" onclick="ynjHudDhikrSay(this,' + index + ',\'\',\'\',\'\');return false;" style="margin-left:8px;padding:4px 10px;border:1px solid #287e61;border-radius:8px;background:none;color:#287e61;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;">Say Again</button>';
                    }

                    // Update progress bar
                    var prog = document.getElementById('hud-popup-progress');
                    if (prog && d.done_count) prog.style.width = (d.done_count * 20) + '%';

                    // Update HUD button count
                    var hudBtn = document.getElementById('hud-dhikr-btn');
                    if (hudBtn && d.done_count < 5) {
                        hudBtn.innerHTML = '\uD83D\uDCFF <span>' + d.done_count + '/5</span>';
                    }

                    // Subtle XP bar pulse
                    var xpFill = document.querySelector('.ynj-hud__xp-fill');
                    if (xpFill) { xpFill.style.boxShadow='0 0 12px rgba(40,126,97,.5)'; setTimeout(function(){xpFill.style.boxShadow='';},1000); }
                }, 2500);

                // ── ALL 5 COMPLETE: moment of peace, not victory ──
                if (d.all_five_bonus && d.all_five_bonus > 0) {
                    setTimeout(function(){
                        // Transform the popup into a state of peace
                        var card = document.getElementById('hud-popup-card');
                        if (card) {
                            var header = card.querySelector('.ynj-popup-header');
                            if (header) {
                                header.innerHTML = '<div style="text-align:center;padding:16px 0;animation:ynj-popup-in .6s;">'
                                    + '<div style="font-size:13px;color:#287e61;font-weight:700;margin-bottom:8px;">Alhamdulillah</div>'
                                    + '<div style="font-size:14px;color:#4a3728;font-style:italic;line-height:1.6;">'
                                    + (ynjHudData.quranVerse || 'Truly, in the remembrance of Allah do hearts find rest.')
                                    + '</div>'
                                    + '<div style="font-size:10px;color:rgba(0,0,0,.3);margin-top:4px;">' + (ynjHudData.quranRef || 'Quran 13:28') + '</div>'
                                    + '</div>';
                            }
                        }
                        // Single warm haptic
                        if (navigator.vibrate) navigator.vibrate(300);
                        // Keep dhikr button active — unlimited dhikr
                        if (hudBtn) { hudBtn.innerHTML = '\uD83D\uDCFF <span>5/5 \u2714</span>'; }
                        // Update points with the bonus silently
                        if (ptsEl) ptsEl.textContent = d.total.toLocaleString();
                    }, 4000);
                }
            }
        }).catch(function(){
            btn.disabled = false;
            var actionText = ynjHudData.dhikrActionText || 'Ameen';
            var pts = ynjHudData.dhikrPoints || 0;
            btn.innerHTML = actionText + '<span>+' + pts + ' pts</span>';
        });
    };

    /* ── Rank-up detection (compare localStorage) ── */
    var storedRank = parseInt(localStorage.getItem('ynj_hud_rank') || '0');
    var currentRank = parseInt(ynjHudData.currentRank) || 0;
    if (storedRank > 0 && currentRank > 0 && currentRank < storedRank) {
        // Rank improved! Celebrate!
        var rankEl = document.getElementById('hud-rank');
        if (rankEl) {
            rankEl.classList.add('ynj-hud__rank--up');
            hudConfetti(rankEl);
            var mosqueName = ynjHudData.mosqueName || '';
            var rankedUpText = ynjHudData.rankedUpText || 'ranked up!';
            hudToast('\uD83C\uDF89 ' + mosqueName + ' ' + rankedUpText + ' #' + currentRank, 'linear-gradient(135deg,#7c3aed,#5b21b6)');
        }
    }
    if (currentRank > 0) localStorage.setItem('ynj_hud_rank', currentRank);

    /* ── Close popups on backdrop click ── */
    ['hud-dhikr-popup', 'hud-league-popup', 'hud-info-popup'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', function(e) {
            if (e.target === el) el.style.display = 'none';
        });
    });
})();
