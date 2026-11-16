(function () {
    'use strict';
    const form = document.getElementById('editor-form');
    const preview = document.getElementById('live-preview');
    const field = name => form.querySelector('[name="' + name + '"]');
    const value = name => field(name) ? field(name).value : '';
    const mapping = {brand:'preview-brand', primary_label:'preview-primary', secondary_label:'preview-secondary'};
    const colors = {background:'--page',surface:'--surface',text:'--ink',accent:'--accent',accent_text:'--on-accent'};
    const colorPattern = /^#[a-f\d]{6}$/i;

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
        Object.keys(colors).forEach(name => {
            const hex = value(name);
            if (colorPattern.test(hex)) preview.style.setProperty(colors[name], hex);
        });
        preview.style.setProperty('--radius', value('shape') === 'square' ? '4px' : '18px');
        const structure = value('structure');
        preview.className = 'phone-preview lp-' + (['card', 'business', 'private'].includes(structure) ? structure : 'card');
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
        const validColors = Object.keys(colors).every(name => colorPattern.test(value(name)));
        const readable = validColors && contrast(value('text'), value('surface')) >= 4.5 && contrast(value('accent_text'), value('accent')) >= 4.5;
        document.getElementById('contrast-note').textContent = !validColors ? 'Bitte für jede Farbe einen sechsstelligen Hexcode eingeben, zum Beispiel #176b52.' : readable ? 'Textkontrast passt. Deine Farben sind lesbar.' : 'Kontrast zu gering. Bitte Text- oder Hintergrundfarben ändern (mindestens 4,5:1).';
    }
    Object.keys(colors).forEach(name => {
        const picker = document.getElementById(name);
        const hex = field(name);
        const syncPicker = () => { hex.value = picker.value; };
        picker.addEventListener('input', syncPicker);
        picker.addEventListener('change', syncPicker);
        hex.addEventListener('input', () => { if (colorPattern.test(hex.value)) picker.value = hex.value; });
    });
    const brandSelect = document.getElementById('brand_preset');
    if (brandSelect) brandSelect.addEventListener('change', () => {
        let presets = {};
        try { presets = JSON.parse(brandSelect.dataset.presets || '{}'); } catch (_) { return; }
        const preset = presets[brandSelect.value];
        if (!preset) return;
        // Selecting a brand loads its look. The save step turns deliberate deviations into exceptions.
        ['brand', 'logo_url', 'primary_label', 'secondary_label', 'secondary_action', 'secondary_url', 'delay'].forEach(key => {
            const input = document.getElementById(key);
            if (input && typeof preset[key] === 'string') input.value = preset[key];
        });
        ['structure', 'shape'].forEach(key => {
            const input = document.getElementById(key);
            if (input && typeof preset[key] === 'string') input.value = preset[key];
        });
        Object.keys(colors).forEach(key => {
            if (typeof preset[key] !== 'string') return;
            document.getElementById(key).value = preset[key];
            field(key).value = preset[key];
        });
        // Tracking defaults only seed a new link; existing tracking is never overwritten.
        if (brandSelect.dataset.applyUtm === 'true') {
            ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'].forEach(key => {
                const input = document.getElementById(key);
                if (input && preset.utm && typeof preset.utm[key] === 'string') input.value = preset.utm[key];
            });
            const forward = form.elements.namedItem('forward_utm');
            if (forward) forward.checked = preset.forward_utm !== false;
        }
    });
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();

    document.getElementById('search-links').addEventListener('input', event => {
        const search = event.target.value.toLocaleLowerCase('de');
        const links = Array.from(document.querySelectorAll('.link-item'));
        links.forEach(link => { link.hidden = !link.textContent.toLocaleLowerCase('de').includes(search); });
        document.getElementById('search-empty').hidden = !search || links.some(link => !link.hidden);
    });
    const colorFeedback = document.getElementById('color-feedback');
    document.querySelectorAll('[data-copy-color]').forEach(button => button.addEventListener('click', async () => {
        const input = field(button.dataset.copyColor);
        if (!colorPattern.test(input.value)) {
            input.focus();
            colorFeedback.textContent = 'Bitte zuerst einen gültigen Hexcode eingeben.';
            return;
        }
        try {
            if (!navigator.clipboard) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(input.value);
            colorFeedback.textContent = input.value + ' kopiert.';
        } catch (_) {
            input.focus(); input.select();
            colorFeedback.textContent = 'Bitte den ausgewählten Hexcode manuell kopieren.';
        }
    }));
    document.querySelectorAll('[data-confirm]').forEach(button => button.addEventListener('click', event => {
        if (!confirm(button.dataset.confirm)) event.preventDefault();
    }));
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
    let dirty = form.dataset.unsaved === 'true';
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', event => {
        if (!event.submitter || event.submitter.value !== 'preview') dirty = false;
    });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
})();
