<?php
$isNew = $brand['id'] === '';
$isDefault = $brand['id'] !== '' && $brand['id'] === $defaultBrandId;
$structureLabel = SL_STRUCTURES[$brand['structure']] ?? SL_STRUCTURES['card'];
// The shared presentation partial expects $config.
$config = $brand;
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Marken · justopen</title><link rel="stylesheet" href="/assets/smart.css"><script src="/assets/brands.js" defer></script></head>
<body class="admin-theme">
<header class="studio-header">
    <a class="brand" href="/admin"><span class="brand-mark" aria-hidden="true">↗</span> justopen <span class="badge">Marken</span></a>
    <form method="post" action="/admin"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><button class="text-button" name="action" value="logout">Abmelden</button></form>
</header>
<div class="studio-shell">
<aside class="sidebar">
    <div class="sidebar-heading"><h2>Marken <span class="count"><?= count($brands) ?></span></h2><a class="add-link" href="/admin?view=brands" aria-label="Neue Marke erstellen">+</a></div>
    <nav aria-label="Gespeicherte Marken" class="link-list">
        <?php foreach ($brands as $row): $item = array_replace(sl_brand_defaults(), json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR)); ?>
            <a class="link-item <?= $row['id'] === $brand['id'] ? 'selected' : '' ?>" href="/admin?view=brands&amp;edit=<?= jo_escape($row['id']) ?>" <?= $row['id'] === $brand['id'] ? 'aria-current="page"' : '' ?>>
                <strong><?= jo_escape($row['name']) ?><?= $row['id'] === $defaultBrandId ? ' · Standard' : '' ?></strong>
                <span><?= jo_escape($item['brand']) ?> · <?= jo_escape(SL_STRUCTURES[$item['structure']] ?? SL_STRUCTURES['card']) ?></span>
                <small class="swatches" aria-hidden="true"><?php foreach (['background', 'surface', 'accent'] as $key): ?><i data-color="<?= jo_escape($item[$key]) ?>"></i><?php endforeach; ?></small>
            </a>
        <?php endforeach; ?>
        <?php if (!$brands): ?><p class="empty-state">Noch keine Marke.<br>Erstelle deine erste mit dem Plus.</p><?php endif; ?>
    </nav>
    <a class="defaults-link" href="/admin">Deine Links <span aria-hidden="true">↗</span></a>
    <p class="sidebar-note">Eine Marke bündelt Name, Logo, Texte, Aufbau, Farben und Buttons.<br>Links folgen ihrer Marke.</p>
</aside>
<main class="editor">
    <div class="editor-heading">
        <p class="eyebrow"><?= $isNew ? 'NEUE MARKE' : 'MARKE BEARBEITEN' ?></p>
        <h1><?= $isNew ? 'Deine Marke, dein Look.' : 'Passe Marke und Landingpage an.' ?></h1>
        <p>Lege Marke, Texte, Aufbau, Farben und Buttons einmal fest. Beim Link gibst du dann nur noch das Ziel an.</p>
    </div>
    <?php if ($error !== ''): ?><p class="alert error" role="alert"><?= jo_escape($error) ?></p><?php endif; ?>
    <?php if (!empty($saved) && $error === ''): ?><p class="alert success" role="status">Marke gespeichert. Alle Links dieser Marke folgen der Änderung.</p><?php endif; ?>
    <?php if (!empty($copied) && $error === ''): ?><p class="alert success" role="status">Marke kopiert. Du bearbeitest jetzt die Kopie.</p><?php endif; ?>
    <?php if (!empty($deleted) && $error === ''): ?><p class="alert success" role="status">Marke gelöscht. Bereits gespeicherte Links behalten ihre Gestaltung.</p><?php endif; ?>
    <?php if (!empty($defaulted) && $error === ''): ?><p class="alert success" role="status">Diese Marke ist jetzt der Standard für neue Links.</p><?php endif; ?>
    <form id="brand-form" method="post" action="/admin?view=brands">
        <input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>">
        <input type="hidden" name="brand_id" value="<?= jo_escape($brand['id']) ?>">
        <fieldset><legend><span>01</span> Marke</legend>
            <label for="name">Interner Name der Marke</label><input id="name" name="name" maxlength="80" value="<?= jo_escape($brand['name']) ?>" placeholder="Business, Privat, Kampagne …" required>
            <label class="check"><input name="default" type="checkbox" <?= $isDefault ? 'checked' : '' ?>> Standard für neue Links</label>
            <p class="field-note">Der interne Name erscheint nur in der Verwaltung. Was Besucher sehen, steht im Feld „Markenname auf der Landingpage“.</p>
        </fieldset>
        <fieldset><legend><span>02</span> Landingpage</legend>
            <label for="structure">Aufbau</label><select id="structure" name="structure"><?php foreach (SL_STRUCTURES as $value => $label): ?><option value="<?= jo_escape($value) ?>" <?= $brand['structure'] === $value ? 'selected' : '' ?>><?= jo_escape($label) ?></option><?php endforeach; ?></select>
            <p class="field-note">Der Aufbau bestimmt Anordnung und Stil. Du kannst mehrere Marken mit demselben Aufbau und unterschiedlichen Farben anlegen.</p>
            <?php require __DIR__ . '/partials/presentation.php'; ?>
        </fieldset>
        <fieldset><legend><span>03</span> Tracking-Vorgaben</legend>
            <details class="utm-details" <?= array_filter($brand['utm']) ? 'open' : '' ?>><summary>UTM-Parameter für neue Links</summary><p class="field-note">Neue Links starten mit diesen Werten. Am Link bleiben sie änderbar; bestehende Links werden nicht umgeschrieben.</p><div class="two-columns"><?php foreach (SL_UTM as $key): ?><div><label for="<?= $key ?>"><?= $key ?></label><input id="<?= $key ?>" name="<?= $key ?>" maxlength="500" value="<?= jo_escape($brand['utm'][$key] ?? '') ?>" placeholder="<?= $key === 'utm_source' ? 'instagram' : ($key === 'utm_medium' ? 'dm' : '') ?>"></div><?php endforeach; ?></div></details>
            <label class="check"><input name="forward_utm" type="checkbox" <?= $brand['forward_utm'] ? 'checked' : '' ?>> UTM vom geteilten Kurzlink weitergeben</label>
        </fieldset>
        <div class="save-bar"><button class="button primary" name="action" value="brand_save"><?= $isNew ? 'Marke erstellen' : 'Änderungen speichern' ?><span aria-hidden="true">↗</span></button></div>
    </form>
    <?php if (!$isNew): ?>
    <div class="layout-actions">
        <form method="post" action="/admin?view=brands"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><input type="hidden" name="brand_id" value="<?= jo_escape($brand['id']) ?>"><button class="button secondary compact" name="action" value="brand_copy">Marke kopieren</button></form>
        <form method="post" action="/admin?view=brands"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><input type="hidden" name="brand_id" value="<?= jo_escape($brand['id']) ?>"><button class="button secondary compact danger" name="action" value="brand_delete" data-confirm="Marke „<?= jo_escape($brand['name']) ?>“ wirklich löschen? Links, die diese Marke nutzen, behalten ihre aktuelle Gestaltung.">Marke löschen</button></form>
    </div>
    <?php endif; ?>
    <p class="field-note">Änderungen hier gelten sofort für alle Links, die dieser Marke folgen. Ein Link mit eigenen Anpassungen bleibt unverändert.</p>
</main>
<aside class="preview-column" aria-label="Live-Vorschau">
    <div class="preview-heading"><span class="eyebrow">LIVE-VORSCHAU</span><span class="badge">Mobil</span></div>
    <div class="phone-preview lp-<?= jo_escape($brand['structure']) ?>" id="brand-preview">
        <div class="landing-card">
            <div class="brand"><span class="brand-mark pv-mark" aria-hidden="true">↗</span><span class="pv-brand"><?= jo_escape($brand['brand'] !== '' ? $brand['brand'] : 'Dein Name') ?></span></div>
            <div class="destination"><span class="status-dot" aria-hidden="true"></span><span>deine-domain.de</span></div>
            <div class="landing-actions"><button class="button primary" type="button" disabled><span class="pv-primary"><?= jo_escape($brand['primary_label'] !== '' ? $brand['primary_label'] : 'Im Browser öffnen') ?></span><span aria-hidden="true">↗</span></button><button class="button secondary" type="button" disabled><span class="pv-secondary"><?= jo_escape($brand['secondary_label'] !== '' ? $brand['secondary_label'] : 'Hier weiter öffnen') ?></span></button></div>
        </div>
    </div>
    <p class="preview-note">Die Vorschau folgt Marke, Aufbau und Farben.<br>Ziele legst du beim Link fest.</p>
</aside>
</div>
</body>
</html>
