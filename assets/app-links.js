(function () {
    'use strict';
    function platform(ua, ipad) {
        if (/android/i.test(ua)) return 'android';
        if (/iphone|ipad|ipod/i.test(ua) || ipad) return 'ios';
        return 'web';
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = {platform};
    if (typeof document === 'undefined') return;
    const card = document.querySelector('[data-app-links]');
    if (!card) return;
    const device = platform(navigator.userAgent, navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const open = document.getElementById('open-app');
    open.href = open.dataset[device];
    if (device === 'web') open.textContent = 'Im Browser fortfahren';
    const experimental = document.getElementById('gmail-experimental');
    if (experimental) experimental.hidden = device !== 'ios';
    // Deliberately no automatic navigation or timed web fallback: app switches need a user gesture.
    for (const button of card.querySelectorAll('[data-copy]')) {
        button.addEventListener('click', async function () {
            const input = document.getElementById(button.dataset.copy);
            const feedback = document.getElementById('app-feedback');
            try {
                await navigator.clipboard.writeText(input.value);
                feedback.textContent = 'Kopiert.';
            } catch (_) {
                input.focus(); input.select();
                feedback.textContent = 'Bitte den ausgewählten Text manuell kopieren.';
            }
        });
    }
})();
