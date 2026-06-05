// Client notifications for UPC Freelance
// Polls the server for new notifications and shows system notifications + plays a short beep.
(function(){
    if (!('fetch' in window)) return;
    const API = '/upc_freelance/app/notifications/api-notifications.php';
    let lastId = parseInt(localStorage.getItem('upc_last_notif_id')||'0',10) || 0;
    let askingPermission = false;
    const PROMPT_KEY = 'upc_notif_prompt_shown';

    function createPermissionBanner() {
        try {
            if (typeof Notification === 'undefined') return null;
            if (Notification.permission !== 'default') return null;
            if (localStorage.getItem(PROMPT_KEY) === 'dismissed') return null;

            const wrap = document.createElement('div');
            wrap.id = 'upc-notif-prompt';
            wrap.style.position = 'fixed';
            wrap.style.right = '18px';
            wrap.style.bottom = '18px';
            wrap.style.zIndex = '99999';
            wrap.style.background = 'white';
            wrap.style.border = '1px solid #e6eefc';
            wrap.style.borderRadius = '12px';
            wrap.style.boxShadow = '0 8px 24px rgba(26,54,93,0.08)';
            wrap.style.padding = '12px 14px';
            wrap.style.fontSize = '14px';
            wrap.style.color = '#0d1c2e';
            wrap.style.display = 'flex';
            wrap.style.gap = '10px';
            wrap.style.alignItems = 'center';

            const txt = document.createElement('div');
            txt.textContent = 'Activer les notifications pour recevoir des alertes en temps réel';
            txt.style.maxWidth = '260px';

            const btnEnable = document.createElement('button');
            btnEnable.textContent = 'Activer';
            btnEnable.style.background = '#0061a5';
            btnEnable.style.color = 'white';
            btnEnable.style.border = 'none';
            btnEnable.style.padding = '8px 12px';
            btnEnable.style.borderRadius = '8px';
            btnEnable.style.cursor = 'pointer';

            const btnNo = document.createElement('button');
            btnNo.textContent = 'Non merci';
            btnNo.style.background = 'transparent';
            btnNo.style.border = 'none';
            btnNo.style.color = '#556';
            btnNo.style.cursor = 'pointer';

            btnEnable.addEventListener('click', async function(){
                try {
                    const p = await Notification.requestPermission();
                    if (p === 'granted') {
                        playBeep();
                        // hide
                        wrap.remove();
                        localStorage.setItem(PROMPT_KEY,'granted');
                        // immediate poll
                        try { setTimeout(()=>{ poll(); }, 200); } catch(e){}
                    } else {
                        localStorage.setItem(PROMPT_KEY,'dismissed');
                        wrap.remove();
                    }
                } catch(e){ localStorage.setItem(PROMPT_KEY,'dismissed'); wrap.remove(); }
            });
            btnNo.addEventListener('click', function(){ localStorage.setItem(PROMPT_KEY,'dismissed'); wrap.remove(); });

            wrap.appendChild(txt);
            const right = document.createElement('div');
            right.style.display = 'flex';
            right.style.gap = '8px';
            right.appendChild(btnNo);
            right.appendChild(btnEnable);
            wrap.appendChild(right);

            document.body.appendChild(wrap);
            return wrap;
        } catch(e){ return null; }
    }

    function playBeep(){
        try {
            const C = window.AudioContext || window.webkitAudioContext;
            const ctx = new C();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = 1000;
            g.gain.value = 0.03;
            o.connect(g); g.connect(ctx.destination);
            o.start();
            setTimeout(()=>{ o.stop(); try{ctx.close()}catch{} }, 150);
        } catch(e){}
    }

    function showSystemNotification(n){
        try {
            const title = n.title || 'UPC Freelance';
            const opts = { body: n.body || '', tag: 'upc-notif-'+n.id, renotify: false };
            const notif = new Notification(title, opts);
            notif.onclick = function(){ window.focus(); if (n.link) location.href = n.link; this.close(); };
        } catch(e){}
    }

    async function poll(){
        try {
            // Request permission once if needed, but don't spam the user
            if (typeof Notification !== 'undefined' && Notification.permission === 'default' && !askingPermission) {
                askingPermission = true;
                Notification.requestPermission().finally(()=>{ askingPermission = false; });
            }

            const res = await fetch(API + '?since=' + lastId, { credentials:'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !Array.isArray(data.notifications)) return;

            if (data.notifications.length > 0) {
                // notifications are ordered DESC by created_at in API, reverse to show oldest-first
                const list = data.notifications.slice().reverse();
                list.forEach(n => {
                    // show system notification if permitted
                    if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                        showSystemNotification(n);
                    }
                    playBeep();
                    // update sidebar badge if present
                    try {
                        const badge = document.getElementById('badge-notif-sidebar');
                        if (badge) {
                            badge.textContent = data.unread > 9 ? '9+' : String(data.unread);
                            badge.classList.remove('hidden');
                        }
                    } catch(e){}
                });
                // persist last id
                const maxId = Math.max(...data.notifications.map(x=>x.id));
                if (maxId) { lastId = Math.max(lastId, maxId); localStorage.setItem('upc_last_notif_id', String(lastId)); }
            }
        } catch(e) {
            // ignore
        }
    }

    // Start polling every 5s only when page is visible
    let pollInterval = null;
    function startPolling(){ if (pollInterval) return; poll(); pollInterval = setInterval(poll, 5000); }
    function stopPolling(){ if (!pollInterval) return; clearInterval(pollInterval); pollInterval = null; }

    document.addEventListener('visibilitychange', function(){
        if (document.visibilityState === 'visible') startPolling(); else stopPolling();
    });

    // Start now if visible
    if (document.visibilityState === 'visible') startPolling();
    else document.addEventListener('visibilitychange', function onv(){ if (document.visibilityState === 'visible'){ startPolling(); document.removeEventListener('visibilitychange', onv); } });
    // Show permission banner when appropriate
    try { createPermissionBanner(); } catch(e){}
})();
