<?php
$colorFields = ['background' => 'Seitenhintergrund', 'surface' => 'Karte', 'text' => 'Text', 'accent' => 'Button 1', 'accent_text' => 'Button-Text'];
?>
<label for="brand">Markenname auf der Landingpage</label><input id="brand" name="brand" maxlength="160" value="<?= jo_escape($config['brand']) ?>" required>
<label for="logo_url">Logo-Adresse <small>Optional · HTTPS-Bild, wird vom Besucher geladen.</small></label><input id="logo_url" name="logo_url" type="url" maxlength="8192" value="<?= jo_escape($config['logo_url']) ?>" placeholder="https://deine-domain.de/logo.png">
<div class="two-columns"><div><label for="primary_label">Button 1 · Extern öffnen</label><input id="primary_label" name="primary_label" maxlength="160" value="<?= jo_escape($config['primary_label']) ?>" required></div><div><label for="secondary_label">Button 2 · Text</label><input id="secondary_label" name="secondary_label" maxlength="160" value="<?= jo_escape($config['secondary_label']) ?>" required></div></div>
<label for="secondary_action">Button 2 · Aktion</label><select id="secondary_action" name="secondary_action"><?php foreach (['current' => 'Ziel im aktuellen Browser öffnen', 'copy' => 'Zieladresse kopieren', 'custom' => 'Andere Adresse öffnen'] as $value => $label): ?><option value="<?= $value ?>" <?= $config['secondary_action'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
<div id="secondary-url-field"><label for="secondary_url">Button 2 · Andere Zieladresse</label><input id="secondary_url" name="secondary_url" type="url" maxlength="8192" value="<?= jo_escape($config['secondary_url']) ?>" placeholder="https://deine-domain.de/"></div>
<div class="colors">
<?php foreach ($colorFields as $key => $label): $color = preg_match('/^#[a-fA-F0-9]{6}$/D', (string) $config[$key]) ? $config[$key] : sl_defaults()[$key]; ?>
<div class="color-field">
<label for="<?= $key ?>"><?= $label ?></label>
<div class="color-inputs">
<input id="<?= $key ?>" type="color" value="<?= jo_escape($color) ?>" aria-label="<?= $label ?> auswählen">
<input id="<?= $key ?>-hex" class="hex-input" name="<?= $key ?>" type="text" value="<?= jo_escape($color) ?>" maxlength="7" pattern="#[a-fA-F0-9]{6}" required spellcheck="false" autocomplete="off" aria-label="<?= $label ?> · Hexcode" aria-describedby="contrast-note">
<button class="color-copy" type="button" data-copy-color="<?= $key ?>" title="Hexcode kopieren" aria-label="<?= $label ?> · Hexcode kopieren"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><rect x="6.5" y="6.5" width="10" height="10" rx="2"></rect><path d="M13.5 6.5v-2a1 1 0 0 0-1-1h-8a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h2"></path></svg></button>
</div>
</div>
<?php endforeach; ?>
</div>
<p class="field-note" id="color-feedback" role="status" aria-live="polite"></p>
<label for="shape">Buttonform</label><select id="shape" name="shape"><option value="rounded" <?= $config['shape'] === 'rounded' ? 'selected' : '' ?>>Abgerundet</option><option value="square" <?= $config['shape'] === 'square' ? 'selected' : '' ?>>Eckig</option></select>
<label for="delay">Automatisches Öffnen nach Sekunden</label><input id="delay" name="delay" type="number" min="0" max="300" step="1" value="<?= jo_escape($config['delay']) ?>" placeholder="Ausgeschaltet">
<p class="field-note">Leer = nur per Button. 0 = sofort versuchen. Apps können automatisches Öffnen blockieren; der Button bleibt verfügbar.</p>
<p class="field-note" id="contrast-note">Farben werden beim Speichern auf lesbaren Kontrast geprüft.</p>
