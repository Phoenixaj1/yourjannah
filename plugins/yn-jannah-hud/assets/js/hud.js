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
    // ── Dhikr stepper: one at a time ──
    var _dhikrList = window._ynjDhikrData || [];
    var _dhikrDone = window._ynjDhikrDone || 0;
    var _dhikrPts  = window._ynjDhikrPts || 0;

    function dhikrFindNext() {
        for (var i = 0; i < _dhikrList.length; i++) {
            if (!_dhikrList[i].done) return i;
        }
        return -1; // all done
    }

    function dhikrRenderCard() {
        var card = document.getElementById('dhikr-card');
        if (!card) return;

        var idx = dhikrFindNext();
        if (idx === -1) {
            // All done
            card.innerHTML = '<div style="text-align:center;padding:24px 0;animation:ynj-popup-in .5s;">'
                + '<div style="font-size:40px;margin-bottom:8px;">\u2705</div>'
                + '<div style="font-size:18px;font-weight:800;color:#166534;margin-bottom:6px;">Alhamdulillah</div>'
                + '<div style="font-size:13px;color:#15803d;font-style:italic;line-height:1.6;">Truly, in the remembrance of Allah do hearts find rest.</div>'
                + '<div style="font-size:10px;color:rgba(0,0,0,.3);margin-top:4px;">Quran 13:28</div>'
                + '</div>';
            var hudBtn = document.getElementById('hud-dhikr-btn');
            if (hudBtn) hudBtn.innerHTML = '\u2705 <span>5/5</span>';
            return;
        }

        var hd = _dhikrList[idx];
        var isLegendary = hd.tier === 'legendary';
        var cardClass = isLegendary ? 'ynj-dhikr-item ynj-dhikr-item--legendary' : 'ynj-dhikr-item';

        card.innerHTML = '<div class="' + cardClass + '" style="animation:ynj-popup-in .4s;">'
            + '<div class="ynj-dhikr-item__arabic" dir="rtl">' + esc(hd.arabic) + '</div>'
            + '<div class="ynj-dhikr-item__english">' + esc(hd.english) + '</div>'
            + '<div class="ynj-dhikr-item__reward">' + esc(hd.reward) + '</div>'
            + '<div class="ynj-dhikr-item__source">' + esc(hd.source) + '</div>'
            + '<button type="button" class="ynj-dhikr-item__btn' + (isLegendary ? ' ynj-dhikr-item__btn--legendary' : '') + '" id="dhikr-say-btn">'
            + esc(hd.action_text)
            + '</button>'
            + '</div>';

        document.getElementById('dhikr-say-btn').addEventListener('click', function(){ dhikrSay(idx); });
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function dhikrSay(idx) {
        var btn = document.getElementById('dhikr-say-btn');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        btn.style.opacity = '.6';
        btn.textContent = 'Saving...';

        if (navigator.vibrate) navigator.vibrate(200);

        var nonce = typeof ynjData !== 'undefined' ? ynjData.nonce : '';
        fetch('/wp-json/ynj/v1/ibadah/dhikr', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            credentials: 'same-origin',
            body: JSON.stringify({ index: idx })
        }).then(function(r){ return r.json(); }).then(function(d) {
            if (d.cooldown) {
                btn.textContent = 'Wait a moment...';
                setTimeout(function(){ btn.textContent = _dhikrList[idx].action_text; btn.disabled = false; btn.style.opacity = ''; }, 2000);
                return;
            }
            if (d.ok) {
                _dhikrList[idx].done = true;
                _dhikrDone = d.done_count || (_dhikrDone + 1);
                _dhikrPts = d.total || _dhikrPts;

                // Update progress bar
                var prog = document.getElementById('dhikr-progress-bar');
                var progText = document.getElementById('dhikr-progress-text');
                if (prog) prog.style.width = (_dhikrDone * 20) + '%';
                if (progText) progText.textContent = _dhikrDone + '/5';

                // Live points counter — animate up
                var ptsEl = document.getElementById('dhikr-pts-live');
                if (ptsEl) animatePoints(ptsEl, parseInt(ptsEl.textContent.replace(/,/g,'')) || 0, _dhikrPts);

                // Update HUD points
                var hudPts = document.getElementById('hud-pts-num');
                if (hudPts) hudPts.textContent = _dhikrPts.toLocaleString();
                var hudBtn = document.getElementById('hud-dhikr-btn');
                if (hudBtn && _dhikrDone < 5) hudBtn.innerHTML = '\uD83D\uDCFF <span>' + _dhikrDone + '/5</span>';

                // XP bar pulse
                var xpFill = document.querySelector('.ynj-hud__xp-fill');
                if (xpFill) { xpFill.style.boxShadow='0 0 12px rgba(40,126,97,.5)'; setTimeout(function(){xpFill.style.boxShadow='';},1000); }

                // Show reward briefly, then advance
                var card = document.getElementById('dhikr-card');
                var reward = _dhikrList[idx].reward || 'May Allah accept';
                card.innerHTML = '<div style="text-align:center;padding:20px 0;animation:ynj-popup-in .4s;">'
                    + '<div style="font-size:28px;margin-bottom:8px;">\u2714\uFE0F</div>'
                    + '<div style="font-size:13px;color:#287e61;font-style:italic;line-height:1.5;">' + esc(reward) + '</div>'
                    + '<div style="font-size:12px;color:#92400e;font-weight:800;margin-top:8px;">+' + (d.points || 0) + ' pts</div>'
                    + '</div>';

                // After 2s, show next dhikr or completion
                setTimeout(function(){
                    dhikrRenderCard();
                    if (d.all_five_bonus && d.all_five_bonus > 0) {
                        if (navigator.vibrate) navigator.vibrate(300);
                    }
                }, 2000);
            }
        }).catch(function(){
            btn.disabled = false;
            btn.style.opacity = '';
            btn.textContent = _dhikrList[idx].action_text;
        });
    }

    function animatePoints(el, from, to) {
        var diff = to - from;
        if (diff <= 0) { el.textContent = to.toLocaleString(); return; }
        var steps = 20;
        var step = Math.ceil(diff / steps);
        var current = from;
        var interval = setInterval(function(){
            current += step;
            if (current >= to) { current = to; clearInterval(interval); }
            el.textContent = current.toLocaleString();
        }, 40);
    }

    // Render first card when popup opens
    var _origDhikrToggle = window.ynjHudDhikrToggle;
    window.ynjHudDhikrToggle = function() {
        _origDhikrToggle();
        var popup = document.getElementById('hud-dhikr-popup');
        if (popup && popup.style.display !== 'none') dhikrRenderCard();
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
