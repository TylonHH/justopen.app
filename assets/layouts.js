(function () {
    'use strict';
    document.querySelectorAll('.swatches i').forEach(el => {
        if (el.dataset.color) el.style.background = el.dataset.color;
    });

    const form = document.getElementById('layout-form');
    const preview = document.getElementById('layout-preview');
    if (form && preview) {
        const field = name => form.querySelector('[name="' + name + '"]');
        const value = name => field(name) ? field(name).value : '';
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
            Object.keys(colors).forEach(name => {
                const hex = value(name);
                if (colorPattern.test(hex)) preview.style.setProperty(colors[name], hex);
            });
            preview.style.setProperty('--radius', value('shape') === 'square' ? '4px' : '18px');
            const structure = value('structure');
            preview.className = 'phone-preview lp-' + (['card', 'business', 'private'].includes(structure) ? structure : 'card');
            preview.querySelectorAll('.pv-brand').forEach(el => el.textContent = value('name') || 'Dein Layout');
            const note = document.getElementById('contrast-note');
            if (note) {
                const validColors = Object.keys(colors).every(name => colorPattern.test(value(name)));
                const readable = validColors && contrast(value('text'), value('surface')) >= 4.5 && contrast(value('accent_text'), value('accent')) >= 4.5;
                note.textContent = !validColors ? 'Bitte für jede Farbe einen sechsstelligen Hexcode eingeben, zum Beispiel #176b52.' : readable ? 'Textkontrast passt. Dein Layout ist lesbar.' : 'Kontrast zu gering. Bitte Text- oder Hintergrundfarben ändern (mindestens 4,5:1).';
            }
        }
        Object.keys(colors).forEach(name => {
            const picker = document.getElementById(name);
            const hex = field(name);
            const syncPicker = () => { hex.value = picker.value; };
            picker.addEventListener('input', syncPicker);
            picker.addEventListener('change', syncPicker);
            hex.addEventListener('input', () => { if (colorPattern.test(hex.value)) picker.value = hex.value; });
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
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        update();
    }

    document.querySelectorAll('[data-confirm]').forEach(button => button.addEventListener('click', event => {
        if (!confirm(button.dataset.confirm)) event.preventDefault();
    }));
})();
