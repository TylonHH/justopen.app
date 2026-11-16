<?php
$shortUrl = $edit !== '' ? $origin . '/l/' . $edit : '';
$brandNames = [];
$presets = [];
foreach ($brands as $row) {
    $item = array_replace(sl_brand_defaults(), json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR));
    $brandNames[$row['id']] = $row['name'];
    $presets[$row['id']] = [
        'brand' => $item['brand'], 'logo_url' => $item['logo_url'],
        'primary_label' => $item['primary_label'], 'secondary_label' => $item['secondary_label'],
        'secondary_action' => $item['secondary_action'], 'secondary_url' => $item['secondary_url'],
        'structure' => $item['structure'], 'background' => $item['background'], 'surface' => $item['surface'],
        'text' => $item['text'], 'accent' => $item['accent'], 'accent_text' => $item['accent_text'],
        'shape' => $item['shape'], 'delay' => $item['delay'],
        'utm' => $item['utm'], 'forward_utm' => (bool) $item['forward_utm'],
    ];
}
$brandName = $brandNames[$config['brand_id']] ?? '';
$isException = $config['brand_id'] !== '' && !empty($config['override']);
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
                <strong><?= jo_escape($item['title']) ?></strong><span>/l/<?= jo_escape($row['slug']) ?></span><small><?= $item['active'] ? 'Aktiv' : 'Deaktiviert' ?><?= isset($brandNames[$item['brand_id']]) ? ' · ' . jo_escape($brandNames[$item['brand_id']]) : '' ?></small>
            </a>
        <?php endforeach; ?>
        <?php if (!$links): ?><p class="empty-state">Dein erster Link wartet.<br>Trage rechts eine Zieladresse ein.</p><?php endif; ?>
        <p id="search-empty" class="empty-state" hidden>Kein passender Link.</p>
    </nav>
    <a class="defaults-link" href="/admin?view=brands">Marken &amp; Landingpages <span aria-hidden="true">↗</span></a>
    <p class="sidebar-note">Marke einmal pflegen.<br>Pro Link nur noch das Ziel.</p>
</aside>
<main class="editor">
    <div class="editor-heading"><p class="eyebrow"><?= $edit !== '' ? 'LINK BEARBEITEN' : 'NEUER LINK' ?></p><h1><?= $edit !== '' ? 'Mach den Link zu deinem.' : 'Ein Link. Zwei Möglichkeiten.' ?></h1><p><?= $edit !== '' ? 'Marke wählen, Ziel festlegen, in deiner DM teilen.' : 'Marke wählen, Ziel eintragen, fertig.' ?></p></div>
    <?php if ($error !== ''): ?><p class="alert error" role="alert"><?= jo_escape($error) ?></p><?php endif; ?>
    <?php if ($saved && $error === ''): ?><p class="alert success" role="status">Link gespeichert. Bereit zum Teilen.</p><?php endif; ?>
    <?php if (!empty($deleted) && $error === ''): ?><p class="alert success" role="status">Link gelöscht. Der Kurzlink ist nicht mehr erreichbar.</p><?php endif; ?>
    <?php if ($applied === 'brand' && $error === ''): ?><p class="alert success" role="status">Marke übernommen. Vorschau prüfen und Änderungen speichern.</p><?php endif; ?>
    <?php if ($shortUrl !== ''): ?>
        <div class="share-box"><label for="share-url">Dein DM-Link</label><div class="share-row"><input id="share-url" readonly value="<?= jo_escape($shortUrl) ?>"><button type="button" class="button primary compact" id="copy-short">Kopieren</button><a class="button secondary compact" href="<?= jo_escape($shortUrl) ?>" target="_blank" rel="noopener noreferrer">Öffnen ↗</a></div><p id="share-feedback" role="status" aria-live="polite"></p></div>
    <?php endif; ?>
    <form id="editor-form" method="post" action="/admin" data-unsaved="<?= $applied !== '' ? 'true' : 'false' ?>">
        <input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>">
        <input type="hidden" name="original_slug" value="<?= jo_escape($edit) ?>">
        <fieldset><legend><span>01</span> Das Ziel</legend>
            <label for="url">Zieladresse · beliebiger HTTP-/HTTPS-Link</label><input id="url" name="url" type="url" maxlength="8192" value="<?= jo_escape($config['url']) ?>" placeholder="https://deine-domain.de/beliebige-seite" required>
            <label for="title">Linkname <small>Nur zur Verwaltung · erscheint nicht auf der Landingpage.</small></label><input id="title" name="title" maxlength="160" value="<?= jo_escape($config['title']) ?>" required>
            <label for="slug">Kurzname <small><?= $edit !== '' ? 'Bleibt unverändert, damit geteilte Links gültig bleiben.' : 'Optional · wird sonst automatisch erzeugt.' ?></small></label><div class="slug-row"><span>/l/</span><input id="slug" name="slug" maxlength="64" pattern="[a-z0-9][a-z0-9_\-]{2,63}" value="<?= jo_escape($config['slug']) ?>" placeholder="hoerbuch" <?= $edit !== '' ? 'readonly' : '' ?>></div>
            <label class="check"><input name="active" type="checkbox" <?= $config['active'] ? 'checked' : '' ?>> Link aktiv</label>
        </fieldset>
        <fieldset><legend><span>02</span> Marke &amp; Landingpage</legend>
            <label for="brand_preset">Marke <small>Liefert Markenname, Logo, Texte, Aufbau, Farben und Buttons.</small></label>
            <div class="layout-row">
                <select id="brand_preset" name="brand_id" data-presets="<?= jo_escape(json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>" data-apply-utm="<?= $edit === '' ? 'true' : 'false' ?>">
                    <option value="">Ohne Marke · eigene Gestaltung</option>
                    <?php foreach ($brands as $row): ?>
                        <option value="<?= jo_escape($row['id']) ?>" <?= $config['brand_id'] === $row['id'] ? 'selected' : '' ?>><?= jo_escape($row['name']) ?><?= $row['id'] === $defaultBrandId ? ' · Standard' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button secondary compact" name="action" value="apply_brand"><?= $isException ? 'Marke wieder übernehmen' : 'Marke anwenden' ?></button>
            </div>
            <input type="hidden" name="structure" id="structure" value="<?= jo_escape($config['structure']) ?>">
            <p class="field-note" id="brand-status"><?php if ($brandName !== ''): ?><?= $isException ? 'Dieser Link hat eigene Anpassungen und folgt der Marke „' . jo_escape($brandName) . '“ nicht mehr.' : 'Folgt der Marke „' . jo_escape($brandName) . '“. Änderungen hier machen den Link zur Ausnahme.' ?><?php else: ?>Ohne Marke. Wähle oben eine Marke, um deren Gestaltung zu übernehmen.<?php endif; ?> <a href="/admin?view=brands">Marken verwalten</a></p>
            <details class="presentation-details" <?= ($isException || $config['brand_id'] === '') ? 'open' : '' ?>>
                <summary>Gestaltung anpassen <span>· macht diesen Link zur Ausnahme</span></summary>
                <?php require __DIR__ . '/partials/presentation.php'; ?>
            </details>
        </fieldset>
        <fieldset><legend><span>03</span> Tracking</legend>
            <details class="utm-details" <?= array_filter($config['utm']) ? 'open' : '' ?>><summary>UTM-Parameter hinzufügen</summary><p class="field-note">Leere Felder behalten vorhandene Parameter der Zieladresse. Eingetragene Werte ersetzen gleichnamige UTM-Parameter. Tracking bleibt pro Link, auch wenn die Marke Vorgaben liefert.</p><div class="two-columns"><?php foreach (SL_UTM as $key): ?><div><label for="<?= $key ?>"><?= $key ?></label><input id="<?= $key ?>" name="<?= $key ?>" maxlength="500" value="<?= jo_escape($config['utm'][$key] ?? '') ?>" placeholder="<?= $key === 'utm_source' ? 'instagram' : ($key === 'utm_medium' ? 'dm' : '') ?>"></div><?php endforeach; ?></div></details>
            <label class="check"><input name="forward_utm" type="checkbox" <?= $config['forward_utm'] ? 'checked' : '' ?>> UTM vom geteilten Kurzlink weitergeben</label><p class="field-note">Gespeicherte UTM-Werte haben Vorrang. Andere Parameter der Zieladresse bleiben erhalten. Änderungen an signierten URLs können deren Signatur ungültig machen.</p>
        </fieldset>
        <div class="save-bar"><button class="button primary" name="action" value="save"><?= $edit !== '' ? 'Änderungen speichern' : 'Link erstellen' ?><span aria-hidden="true">↗</span></button><button class="button secondary" name="action" value="preview" formaction="/admin/preview" formtarget="_blank">Vorschau öffnen</button></div>
    </form>
    <?php if ($edit !== ''): ?>
    <div class="layout-actions">
        <form method="post" action="/admin"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><input type="hidden" name="original_slug" value="<?= jo_escape($edit) ?>"><button class="button secondary compact danger" name="action" value="delete" data-confirm="Link „<?= jo_escape($config['title']) ?>“ (<?= jo_escape($origin . '/l/' . $edit) ?>) wirklich löschen? Bereits geteilte Links funktionieren danach nicht mehr.">Link löschen</button></form>
    </div>
    <p class="field-note">Löschen ist endgültig. Wenn du einen Link nur pausieren willst, entferne oben „Link aktiv“.</p>
    <?php endif; ?>
</main>
<aside class="preview-column" aria-label="Live-Vorschau">
    <div class="preview-heading"><span class="eyebrow">LIVE-VORSCHAU</span><span class="badge">Mobil</span></div>
    <div class="phone-preview lp-<?= jo_escape($config['structure']) ?>" id="live-preview">
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
