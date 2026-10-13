<?php
$isDefaults = $mode === 'defaults';
$shortUrl = $edit !== '' ? $origin . '/l/' . $edit : '';
$textFields = ['brand' => 'Marke', 'title' => 'Linkname · nur zur Verwaltung', 'primary_label' => 'Button 1 · Extern öffnen', 'secondary_label' => 'Button 2 · Text'];
$colorFields = ['background' => 'Seitenhintergrund', 'surface' => 'Karte', 'text' => 'Text', 'accent' => 'Button 1', 'accent_text' => 'Button-Text'];
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Link Studio · justopen</title><link rel="stylesheet" href="/assets/smart.css"><script src="/assets/admin.js" defer></script></head>
<body class="admin-theme">
<header class="studio-header">
    <a class="brand" href="/admin"><span class="brand-mark" aria-hidden="true">↗</span> justopen <span class="badge">Link Studio</span></a>
    <form method="post" action="/admin"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><button class="text-button" name="action" value="logout">Abmelden</button></form>
</header>
<div class="studio-shell">
<aside class="sidebar">
    <div class="sidebar-heading"><h2>Deine Links <span class="count"><?= count($links) ?></span></h2><a class="add-link" href="/admin" aria-label="Neuen Link erstellen">+</a></div>
    <label class="sr-only" for="search-links">Links suchen</label><input id="search-links" type="search" placeholder="Links suchen …" autocomplete="off">
    <nav aria-label="Gespeicherte Links" class="link-list">
        <?php foreach ($links as $row): $item = array_replace(sl_defaults(), json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR)); ?>
            <a class="link-item <?= $row['slug'] === $edit ? 'selected' : '' ?>" href="/admin?edit=<?= rawurlencode($row['slug']) ?>" <?= $row['slug'] === $edit ? 'aria-current="page"' : '' ?>>
                <strong><?= jo_escape($item['title']) ?></strong><span>/l/<?= jo_escape($row['slug']) ?></span><small><?= $item['active'] ? 'Aktiv' : 'Deaktiviert' ?></small>
            </a>
        <?php endforeach; ?>
        <?php if (!$links): ?><p class="empty-state">Dein erster Link wartet.<br>Trage rechts eine Zieladresse ein.</p><?php endif; ?>
        <p id="search-empty" class="empty-state" hidden>Kein passender Link.</p>
    </nav>
    <a class="defaults-link <?= $isDefaults ? 'selected' : '' ?>" href="/admin?view=defaults">Vorlage für neue Links <span aria-hidden="true">↗</span></a>
    <p class="sidebar-note">Eigene Links. Eigene Gestaltung.<br>Ohne Abo und ohne Klicklimit.</p>
</aside>
<main class="editor">
    <div class="editor-heading"><p class="eyebrow"><?= $isDefaults ? 'DEINE VORLAGE' : ($edit !== '' ? 'LINK BEARBEITEN' : 'NEUER LINK') ?></p><h1><?= $isDefaults ? 'Ein Stil für deinen Start.' : ($edit !== '' ? 'Mach den Link zu deinem.' : 'Ein Link. Zwei Möglichkeiten.') ?></h1><p><?= $isDefaults ? 'Diese Einstellungen gelten für neue Links. Bestehende Links behalten ihre Gestaltung.' : 'Ziel festlegen, Landingpage gestalten und in deiner DM teilen.' ?></p></div>
    <?php if ($error !== ''): ?><p class="alert error" role="alert"><?= jo_escape($error) ?></p><?php endif; ?>
    <?php if ($saved && $error === ''): ?><p class="alert success" role="status"><?= $isDefaults ? 'Vorlage gespeichert.' : 'Link gespeichert. Bereit zum Teilen.' ?></p><?php endif; ?>
    <?php if ($shortUrl !== ''): ?>
        <div class="share-box"><label for="share-url">Dein DM-Link</label><div class="share-row"><input id="share-url" readonly value="<?= jo_escape($shortUrl) ?>"><button type="button" class="button primary compact" id="copy-short">Kopieren</button><a class="button secondary compact" href="<?= jo_escape($shortUrl) ?>" target="_blank" rel="noopener noreferrer">Öffnen ↗</a></div><p id="share-feedback" role="status" aria-live="polite"></p></div>
    <?php endif; ?>
    <form id="editor-form" method="post" action="/admin">
        <input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>">
        <input type="hidden" name="original_slug" value="<?= jo_escape($edit) ?>">
        <?php if (!$isDefaults): ?>
        <fieldset><legend><span>01</span> Das Ziel</legend>
            <label for="url">Zieladresse · beliebiger HTTP-/HTTPS-Link</label><input id="url" name="url" type="url" maxlength="8192" value="<?= jo_escape($config['url']) ?>" placeholder="https://deine-domain.de/beliebige-seite" required>
            <label for="slug">Kurzname <small><?= $edit !== '' ? 'Bleibt unverändert, damit geteilte Links gültig bleiben.' : 'Optional · wird sonst automatisch erzeugt.' ?></small></label><div class="slug-row"><span>/l/</span><input id="slug" name="slug" maxlength="64" pattern="[a-z0-9][a-z0-9_\-]{2,63}" value="<?= jo_escape($config['slug']) ?>" placeholder="hoerbuch" <?= $edit !== '' ? 'readonly' : '' ?>></div>
            <label class="check"><input name="active" type="checkbox" <?= $config['active'] ? 'checked' : '' ?>> Link aktiv</label>
        </fieldset>
        <?php endif; ?>
        <fieldset><legend><span><?= $isDefaults ? '01' : '02' ?></span> Deine Landingpage</legend>
            <?php foreach (['brand', 'title'] as $key): ?><label for="<?= $key ?>"><?= $textFields[$key] ?></label><input id="<?= $key ?>" name="<?= $key ?>" maxlength="160" value="<?= jo_escape($config[$key]) ?>" required><?php endforeach; ?>
            <label for="logo_url">Logo-Adresse <small>Optional · HTTPS-Bild, wird vom Besucher geladen.</small></label><input id="logo_url" name="logo_url" type="url" maxlength="8192" value="<?= jo_escape($config['logo_url']) ?>" placeholder="https://deine-domain.de/logo.png">
            <div class="two-columns"><?php foreach (['primary_label', 'secondary_label'] as $key): ?><div><label for="<?= $key ?>"><?= $textFields[$key] ?></label><input id="<?= $key ?>" name="<?= $key ?>" maxlength="160" value="<?= jo_escape($config[$key]) ?>" required></div><?php endforeach; ?></div>
            <label for="secondary_action">Button 2 · Aktion</label><select id="secondary_action" name="secondary_action"><?php foreach (['current' => 'Ziel im aktuellen Browser öffnen', 'copy' => 'Zieladresse kopieren', 'custom' => 'Andere Adresse öffnen'] as $value => $label): ?><option value="<?= $value ?>" <?= $config['secondary_action'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
            <div id="secondary-url-field"><label for="secondary_url">Button 2 · Andere Zieladresse</label><input id="secondary_url" name="secondary_url" type="url" maxlength="8192" value="<?= jo_escape($config['secondary_url']) ?>" placeholder="https://deine-domain.de/"></div>
            <div class="colors"><?php foreach ($colorFields as $key => $label): ?><label for="<?= $key ?>"><?= $label ?><input id="<?= $key ?>" name="<?= $key ?>" type="color" value="<?= jo_escape(preg_match('/^#[a-fA-F0-9]{6}$/D', (string) $config[$key]) ? $config[$key] : sl_defaults()[$key]) ?>"></label><?php endforeach; ?></div>
            <label for="shape">Buttonform</label><select id="shape" name="shape"><option value="rounded" <?= $config['shape'] === 'rounded' ? 'selected' : '' ?>>Abgerundet</option><option value="square" <?= $config['shape'] === 'square' ? 'selected' : '' ?>>Eckig</option></select>
            <p class="field-note" id="contrast-note">Farben werden beim Speichern auf lesbaren Kontrast geprüft.</p>
        </fieldset>
        <fieldset><legend><span><?= $isDefaults ? '02' : '03' ?></span> Öffnen & Tracking</legend>
            <label for="delay">Automatisches Öffnen nach Sekunden</label><input id="delay" name="delay" type="number" min="0" max="300" step="1" value="<?= jo_escape($config['delay']) ?>" placeholder="Ausgeschaltet">
            <p class="field-note">Leer = nur per Button. 0 = sofort versuchen. Apps können automatisches Öffnen blockieren; der Button bleibt verfügbar.</p>
            <details class="utm-details" <?= array_filter($config['utm']) ? 'open' : '' ?>><summary>UTM-Parameter hinzufügen</summary><p class="field-note">Leere Felder behalten vorhandene Parameter der Zieladresse. Eingetragene Werte ersetzen gleichnamige UTM-Parameter.</p><div class="two-columns"><?php foreach (SL_UTM as $key): ?><div><label for="<?= $key ?>"><?= $key ?></label><input id="<?= $key ?>" name="<?= $key ?>" maxlength="500" value="<?= jo_escape($config['utm'][$key] ?? '') ?>" placeholder="<?= $key === 'utm_source' ? 'instagram' : ($key === 'utm_medium' ? 'dm' : '') ?>"></div><?php endforeach; ?></div></details>
            <label class="check"><input name="forward_utm" type="checkbox" <?= $config['forward_utm'] ? 'checked' : '' ?>> UTM vom geteilten Kurzlink weitergeben</label><p class="field-note">Gespeicherte UTM-Werte haben Vorrang. Andere Parameter der Zieladresse bleiben erhalten. Änderungen an signierten URLs können deren Signatur ungültig machen.</p>
        </fieldset>
        <div class="save-bar"><button class="button primary" name="action" value="<?= $isDefaults ? 'defaults' : 'save' ?>"><?= $isDefaults ? 'Vorlage speichern' : ($edit !== '' ? 'Änderungen speichern' : 'Link erstellen') ?><span aria-hidden="true">↗</span></button><?php if (!$isDefaults): ?><button class="button secondary" name="action" value="preview" formaction="/admin/preview" formtarget="_blank">Vorschau öffnen</button><?php endif; ?></div>
    </form>
</main>
<aside class="preview-column" aria-label="Live-Vorschau">
    <div class="preview-heading"><span class="eyebrow">LIVE-VORSCHAU</span><span class="badge">Mobil</span></div>
    <div class="phone-preview" id="live-preview">
        <div class="landing-card">
            <div class="brand"><img id="preview-logo" class="brand-logo" alt="" referrerpolicy="no-referrer" hidden><span id="preview-mark" class="brand-mark" aria-hidden="true">↗</span><span id="preview-brand"></span></div>
            <div class="destination"><span class="status-dot" aria-hidden="true"></span><span id="preview-host">deine-domain.de</span></div>
            <div class="landing-actions"><button id="preview-primary" class="button primary" type="button" disabled></button><button id="preview-secondary" class="button secondary" type="button" disabled></button></div>
        </div>
    </div>
    <p class="preview-note">Die Vorschau folgt deinen Eingaben.<br>Teste den fertigen Link direkt in Instagram-DMs auf iPhone und Android.</p>
    <details class="help-details"><summary>Wie öffnet sich der Browser?</summary><p>Android versucht Chrome per Intent zu öffnen. Instagram auf iOS nutzt einen undokumentierten Browserwechsel. Andere iOS-Apps zeigen eine Anleitung. Ohne JavaScript führen die Buttons direkt zum Ziel.</p><p>Ein Öffnungsversuch ist keine Erfolgsgarantie. Es gibt keine automatische Weiterleitung zurück in den App-Browser, wenn der Wechsel scheitert.</p></details>
</aside>
</div>
</body>
</html>
