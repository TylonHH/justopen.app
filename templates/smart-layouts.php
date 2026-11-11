<?php
$isNew = $layout['id'] === '';
$colorFields = ['background' => 'Seitenhintergrund', 'surface' => 'Karte', 'text' => 'Text', 'accent' => 'Button 1', 'accent_text' => 'Button-Text'];
$structureLabel = SL_STRUCTURES[$layout['structure']] ?? SL_STRUCTURES['card'];
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Landingpages · justopen</title><link rel="stylesheet" href="/assets/smart.css"><script src="/assets/layouts.js" defer></script></head>
<body class="admin-theme">
<header class="studio-header">
    <a class="brand" href="/admin"><span class="brand-mark" aria-hidden="true">↗</span> justopen <span class="badge">Landingpages</span></a>
    <form method="post" action="/admin"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><button class="text-button" name="action" value="logout">Abmelden</button></form>
</header>
<div class="studio-shell">
<aside class="sidebar">
    <div class="sidebar-heading"><h2>Layouts <span class="count"><?= count($layouts) ?></span></h2><a class="add-link" href="/admin?view=layouts" aria-label="Neues Layout erstellen">+</a></div>
    <nav aria-label="Gespeicherte Layouts" class="link-list">
        <?php foreach ($layouts as $row): $item = array_replace(sl_layout_defaults(), json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR)); ?>
            <a class="link-item <?= $row['id'] === $layout['id'] ? 'selected' : '' ?>" href="/admin?view=layouts&amp;edit=<?= jo_escape($row['id']) ?>" <?= $row['id'] === $layout['id'] ? 'aria-current="page"' : '' ?>>
                <strong><?= jo_escape($row['name']) ?></strong><span><?= jo_escape(SL_STRUCTURES[$item['structure']] ?? SL_STRUCTURES['card']) ?></span>
                <small class="swatches" aria-hidden="true"><?php foreach (['background', 'surface', 'accent'] as $key): ?><i data-color="<?= jo_escape($item[$key]) ?>"></i><?php endforeach; ?></small>
            </a>
        <?php endforeach; ?>
        <?php if (!$layouts): ?><p class="empty-state">Noch kein Layout.<br>Erstelle dein erstes mit dem Plus.</p><?php endif; ?>
    </nav>
    <a class="defaults-link" href="/admin">Deine Links <span aria-hidden="true">↗</span></a>
    <a class="defaults-link" href="/admin?view=defaults">Vorlage für neue Links <span aria-hidden="true">↗</span></a>
    <p class="sidebar-note">Ein Layout ist ein Aufbau plus Farben.<br>Jeder Link speichert seine eigene Kopie.</p>
</aside>
<main class="editor">
    <div class="editor-heading">
        <p class="eyebrow"><?= $isNew ? 'NEUES LAYOUT' : 'LAYOUT BEARBEITEN' ?></p>
        <h1><?= $isNew ? 'Dein neuer Look.' : 'Passe den Look an.' ?></h1>
        <p>Beim Erstellen eines Links wählst du das Layout aus einer Liste. Aufbau, Farben und Buttonform kommen daraus, Texte und Ziele bleiben pro Link.</p>
    </div>
    <?php if ($error !== ''): ?><p class="alert error" role="alert"><?= jo_escape($error) ?></p><?php endif; ?>
    <?php if (!empty($saved) && $error === ''): ?><p class="alert success" role="status">Layout gespeichert.</p><?php endif; ?>
    <?php if (!empty($copied) && $error === ''): ?><p class="alert success" role="status">Layout kopiert. Du bearbeitest jetzt die Kopie.</p><?php endif; ?>
    <?php if (!empty($deleted) && $error === ''): ?><p class="alert success" role="status">Layout gelöscht. Bereits gespeicherte Links bleiben unverändert.</p><?php endif; ?>
    <form id="layout-form" method="post" action="/admin?view=layouts">
        <input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>">
        <input type="hidden" name="layout_id" value="<?= jo_escape($layout['id']) ?>">
        <fieldset><legend><span>01</span> Name &amp; Aufbau</legend>
            <label for="name">Name des Layouts</label><input id="name" name="name" maxlength="80" value="<?= jo_escape($layout['name']) ?>" placeholder="Business, Privat, Kampagne …" required>
            <label for="structure">Aufbau</label><select id="structure" name="structure"><?php foreach (SL_STRUCTURES as $value => $label): ?><option value="<?= jo_escape($value) ?>" <?= $layout['structure'] === $value ? 'selected' : '' ?>><?= jo_escape($label) ?></option><?php endforeach; ?></select>
            <p class="field-note">Der Aufbau bestimmt Anordnung und Stil der Landingpage. Du kannst beliebig viele Layouts mit demselben Aufbau und unterschiedlichen Farben anlegen.</p>
        </fieldset>
        <fieldset><legend><span>02</span> Farben</legend>
            <div class="colors"><?php foreach ($colorFields as $key => $label): ?><label for="<?= $key ?>"><?= $label ?><input id="<?= $key ?>" name="<?= $key ?>" type="color" value="<?= jo_escape(preg_match('/^#[a-fA-F0-9]{6}$/D', (string) $layout[$key]) ? $layout[$key] : sl_layout_defaults()[$key]) ?>"></label><?php endforeach; ?></div>
            <label for="shape">Buttonform</label><select id="shape" name="shape"><option value="rounded" <?= $layout['shape'] === 'rounded' ? 'selected' : '' ?>>Abgerundet</option><option value="square" <?= $layout['shape'] === 'square' ? 'selected' : '' ?>>Eckig</option></select>
            <p class="field-note" id="contrast-note">Farben werden beim Speichern auf lesbaren Kontrast geprüft.</p>
        </fieldset>
        <div class="save-bar"><button class="button primary" name="action" value="layout_save"><?= $isNew ? 'Layout erstellen' : 'Änderungen speichern' ?><span aria-hidden="true">↗</span></button></div>
    </form>
    <?php if (!$isNew): ?>
    <div class="layout-actions">
        <form method="post" action="/admin?view=layouts"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><input type="hidden" name="layout_id" value="<?= jo_escape($layout['id']) ?>"><button class="button secondary compact" name="action" value="layout_copy">Layout kopieren</button></form>
        <form method="post" action="/admin?view=layouts"><input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>"><input type="hidden" name="layout_id" value="<?= jo_escape($layout['id']) ?>"><button class="button secondary compact danger" name="action" value="layout_delete" data-confirm="Layout „<?= jo_escape($layout['name']) ?>“ wirklich löschen? Bereits gespeicherte Links behalten ihre Gestaltung.">Layout löschen</button></form>
    </div>
    <?php endif; ?>
    <p class="field-note">Änderungen an einem Layout gelten für neue Links und für Links, bei denen du „Layout übernehmen“ wählst. Bestehende Links behalten ihre gespeicherte Gestaltung.</p>
</main>
<aside class="preview-column" aria-label="Live-Vorschau">
    <div class="preview-heading"><span class="eyebrow">LIVE-VORSCHAU</span><span class="badge">Mobil</span></div>
    <div class="phone-preview lp-<?= jo_escape($layout['structure']) ?>" id="layout-preview">
        <div class="landing-card">
            <div class="brand"><span class="brand-mark pv-mark" aria-hidden="true">↗</span><span class="pv-brand"><?= jo_escape($layout['name'] !== '' ? $layout['name'] : 'Dein Layout') ?></span></div>
            <div class="destination"><span class="status-dot" aria-hidden="true"></span><span>deine-domain.de</span></div>
            <div class="landing-actions"><button class="button primary" type="button" disabled><span>Im Browser öffnen</span><span aria-hidden="true">↗</span></button><button class="button secondary" type="button" disabled>Hier weiter öffnen</button></div>
        </div>
    </div>
    <p class="preview-note">Die Vorschau folgt Aufbau und Farben.<br>Texte und Ziele legst du beim Link fest.</p>
</aside>
</div>
</body>
</html>
