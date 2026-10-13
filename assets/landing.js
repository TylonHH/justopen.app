(function (root) {
    'use strict';

    function environment(ua, ipad) {
        const android = /android/i.test(ua);
        const ios = /iphone|ipad|ipod/i.test(ua) || Boolean(ipad);
        const instagram = /instagram/i.test(ua);
        const threads = /barcelona|threads/i.test(ua);
        const inApp = instagram || threads || /fban|fbav|fb_iab|messenger|tiktok|bytedance|musical_ly|twitter|linkedinapp|snapchat|micromessenger|line\/|telegram|; wv\)/i.test(ua);
        return {android, ios, instagram, threads, inApp, manual: ios && inApp && !instagram && !threads};
    }

    function externalHref(target, handoff, fallback, env) {
        if (env.android && env.inApp) {
            // Hand off through our stored-link redirect, not an intent containing an arbitrary target fragment.
            const url = new URL(handoff);
            return 'intent://' + url.host + url.pathname + url.search + '#Intent;scheme=' + url.protocol.slice(0, -1)
                + ';action=android.intent.action.VIEW;package=com.android.chrome;S.browser_fallback_url='
                + encodeURIComponent(fallback) + ';end';
        }
        if (env.ios && env.instagram) return 'instagram://extbrowser/?url=' + encodeURIComponent(handoff);
        if (env.ios && env.threads) return 'barcelona://extbrowser/?url=' + encodeURIComponent(handoff);
        return target;
    }

    const api = {environment, externalHref};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (!root || !root.document) return;

    const card = document.getElementById('landing');
    if (!card) return;
    const external = document.getElementById('open-external');
    const secondary = document.getElementById('secondary');
    if (document.body.dataset.preview === 'true') {
        [external, secondary].forEach(button => {
            button.setAttribute('aria-disabled', 'true');
            button.addEventListener('click', event => event.preventDefault());
        });
        return;
    }
    const target = card.dataset.target;
    document.getElementById('copy-fallback').hidden = true;
    const env = environment(navigator.userAgent, navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const hint = document.getElementById('browser-hint');
    const feedback = document.getElementById('feedback');
    const countdown = document.getElementById('countdown');
    const panel = document.getElementById('countdown-panel');
    const cancel = document.getElementById('cancel-countdown');
    let timer = null;
    let attempted = false;

    if (env.inApp) {
        hint.hidden = !env.manual;
    }
    external.href = externalHref(target, card.dataset.handoff, location.href, env);

    function stopCountdown(message) {
        if (timer !== null) clearInterval(timer);
        timer = null;
        if (message) countdown.textContent = message;
        cancel.disabled = true;
    }
    external.addEventListener('click', event => {
        attempted = true;
        stopCountdown('Automatisches Öffnen beendet.');
        if (env.manual) {
            event.preventDefault();
            hint.hidden = false;
        } else if (env.inApp) {
            hint.hidden = false;
            feedback.textContent = 'Browserwechsel angefragt.';
        }
    });
    secondary.addEventListener('click', async event => {
        stopCountdown('Automatisches Öffnen gestoppt.');
        if (card.dataset.secondary !== 'copy') return;
        event.preventDefault();
        try {
            if (!navigator.clipboard) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(target);
            feedback.textContent = 'Link kopiert.';
        } catch (_) {
            const fallback = document.getElementById('copy-fallback');
            fallback.hidden = false;
            fallback.open = true;
            const input = document.getElementById('copy-url');
            input.focus(); input.select();
            feedback.textContent = 'Bitte den ausgewählten Link manuell kopieren.';
        }
    });
    cancel.addEventListener('change', () => stopCountdown('Automatisches Öffnen gestoppt.'));
    window.addEventListener('pagehide', () => stopCountdown());
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopCountdown('Automatisches Öffnen gestoppt.');
    });
    if (card.dataset.delay !== '' && !env.manual) {
        panel.hidden = false;
        const deadline = Date.now() + Number(card.dataset.delay) * 1000;
        function tick() {
            if (document.hidden || attempted) { stopCountdown('Automatisches Öffnen gestoppt.'); return; }
            const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            countdown.textContent = remaining > 0 ? 'Öffnungsversuch in ' + remaining + ' Sekunden.' : 'Browser wird angefragt …';
            if (remaining === 0) external.click();
        }
        timer = setInterval(tick, 250);
        tick();
    } else if (card.dataset.delay !== '' && env.manual) {
        hint.hidden = false;
    }
})(typeof window === 'undefined' ? null : window);
