(function () {
    'use strict';
    const form = document.getElementById('editor-form');
    const preview = document.getElementById('live-preview');
    const field = name => form.elements.namedItem(name);
    const value = name => field(name) ? field(name).value : '';
    const mapping = {brand:'preview-brand', title:'preview-title', description:'preview-description', primary_label:'preview-primary', secondary_label:'preview-secondary'};

    function luminance(hex) {
        const c = hex.match(/[a-f\d]{2}/gi).map(part => parseInt(part, 16) / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
        return .2126 * c[0] + .7152 * c[1] + .0722 * c[2];
    }
    function contrast(a, b) {
        const x = luminance(a), y = luminance(b);
        return (Math.max(x, y) + .05) / (Math.min(x, y) + .05);
    }
    function update() {
        Object.keys(mapping).forEach(name => document.getElementById(mapping[name]).textContent = value(name));
        const colors = {background:'--page',surface:'--surface',text:'--ink',accent:'--accent',accent_text:'--on-accent'};
        Object.keys(colors).forEach(name => preview.style.setProperty(colors[name], value(name)));
        preview.style.setProperty('--radius', value('shape') === 'square' ? '4px' : '18px');
        const logo = document.getElementById('preview-logo');
        const logoUrl = value('logo_url');
        let validLogo = false;
        try { validLogo = new URL(logoUrl).protocol === 'https:'; } catch (_) { /* Incomplete form input. */ }
        logo.hidden = !validLogo;
        document.getElementById('preview-mark').hidden = validLogo;
        if (validLogo && logo.getAttribute('src') !== logoUrl) logo.src = logoUrl;
        if (!validLogo) logo.removeAttribute('src');
        try { document.getElementById('preview-host').textContent = new URL(value('url')).host; }
        catch (_) { document.getElementById('preview-host').textContent = 'deine-domain.de'; }
        const custom = value('secondary_action') === 'custom';
        document.getElementById('secondary-url-field').hidden = !custom;
        field('secondary_url').required = custom;
        const readable = contrast(value('text'), value('surface')) >= 4.5 && contrast(value('accent_text'), value('accent')) >= 4.5;
        document.getElementById('contrast-note').textContent = readable ? 'Textkontrast passt. Deine Farben sind lesbar.' : 'Kontrast zu gering. Bitte Text- oder Hintergrundfarben ändern (mindestens 4,5:1).';
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();

    document.getElementById('search-links').addEventListener('input', event => {
        const search = event.target.value.toLocaleLowerCase('de');
        const links = Array.from(document.querySelectorAll('.link-item'));
        links.forEach(link => { link.hidden = !link.textContent.toLocaleLowerCase('de').includes(search); });
        document.getElementById('search-empty').hidden = !search || links.some(link => !link.hidden);
    });
    const copy = document.getElementById('copy-short');
    if (copy) copy.addEventListener('click', async () => {
        const input = document.getElementById('share-url');
        const feedback = document.getElementById('share-feedback');
        try {
            if (!navigator.clipboard) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(input.value);
            feedback.textContent = 'Kopiert. Bereit für deine DM.';
        } catch (_) {
            input.focus(); input.select();
            feedback.textContent = 'Bitte den ausgewählten Link manuell kopieren.';
        }
    });
    // Never flag preview submissions as saved; the editor remains in this tab.
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', event => {
        if (!event.submitter || event.submitter.value !== 'preview') dirty = false;
    });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
})();
