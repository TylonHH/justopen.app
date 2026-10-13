<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title><?= jo_escape(parse_url($target, PHP_URL_HOST)) ?> · <?= jo_escape($config['brand']) ?></title>
    <link rel="stylesheet" href="/assets/smart.css">
    <style nonce="<?= jo_escape($nonce) ?>">
        :root { --page:<?= $config['background'] ?>; --surface:<?= $config['surface'] ?>; --ink:<?= $config['text'] ?>; --accent:<?= $config['accent'] ?>; --on-accent:<?= $config['accent_text'] ?>; --radius:<?= $config['shape'] === 'square' ? '4px' : '18px' ?>; }
    </style>
    <script src="/assets/landing.js" defer></script>
</head>
<body class="landing" data-preview="<?= $preview ? 'true' : 'false' ?>">
    <?php if ($preview): ?><div class="preview-banner">Vorschau · Buttons und Countdown sind deaktiviert. <a href="/admin">Zur Verwaltung</a></div><?php endif; ?>
    <main class="landing-card" id="landing" data-target="<?= jo_escape($target) ?>"
        data-handoff="<?= jo_escape($handoff) ?>" data-delay="<?= jo_escape($config['delay']) ?>"
        data-secondary="<?= jo_escape($config['secondary_action']) ?>">
        <div class="brand">
            <?php if ($config['logo_url'] !== ''): ?><img class="brand-logo" src="<?= jo_escape($config['logo_url']) ?>" alt="" referrerpolicy="no-referrer"><?php else: ?><span class="brand-mark" aria-hidden="true">↗</span><?php endif; ?>
            <span><?= jo_escape($config['brand']) ?></span>
        </div>
        <div class="destination"><span class="status-dot" aria-hidden="true"></span><?= jo_escape(parse_url($target, PHP_URL_HOST)) ?></div>
        <h1 class="sr-only">Link öffnen: <?= jo_escape(parse_url($target, PHP_URL_HOST)) ?></h1>
        <div class="landing-actions">
            <a id="open-external" class="button primary" href="<?= jo_escape($preview ? '#' : $target) ?>" <?= $preview ? 'aria-disabled="true"' : '' ?> rel="noreferrer"><span><?= jo_escape($config['primary_label']) ?></span><span aria-hidden="true">↗</span></a>
            <?php if ($config['secondary_action'] === 'copy'): ?>
                <button id="secondary" class="button secondary" type="button" <?= $preview ? 'disabled' : '' ?>><?= jo_escape($config['secondary_label']) ?></button>
            <?php else: ?>
                <a id="secondary" class="button secondary" href="<?= jo_escape($preview ? '#' : ($config['secondary_action'] === 'custom' ? $config['secondary_url'] : $target)) ?>" <?= $preview ? 'aria-disabled="true"' : '' ?> rel="noreferrer"><?= jo_escape($config['secondary_label']) ?></a>
            <?php endif; ?>
        </div>
        <p class="browser-hint" id="browser-hint" hidden>Kein Wechsel? Im App-Menü „Im Browser öffnen“ wählen.</p>
        <div id="countdown-panel" class="countdown-panel" hidden>
            <p id="countdown" role="status" aria-live="polite"></p>
            <label><input id="cancel-countdown" type="checkbox"> Automatisches Öffnen stoppen</label>
        </div>
        <p id="feedback" class="feedback" role="status" aria-live="polite"></p>
        <details id="copy-fallback" <?= $config['secondary_action'] !== 'copy' || $preview ? 'hidden' : '' ?>><summary>Link auswählen und kopieren</summary><label class="sr-only" for="copy-url">Zieladresse</label><textarea id="copy-url" readonly><?= jo_escape($target) ?></textarea></details>
        <noscript><p class="browser-hint">Extern öffnen: im App-Menü „Im Browser öffnen“ wählen.</p></noscript>
    </main>
</body>
</html>
