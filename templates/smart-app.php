<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">
    <title><?= jo_escape($result['label']) ?> öffnen · justopen</title>
    <link rel="stylesheet" href="/assets/smart.css"><link rel="stylesheet" href="/assets/app-links.css">
    <script src="/assets/app-links.js" defer></script>
</head>
<body class="landing">
<main class="landing-card app-card" data-app-links>
    <a class="brand" href="/apps"><span class="brand-mark" aria-hidden="true">↗</span> justopen <span class="badge">App-Links</span></a>
    <p class="eyebrow">DEIN NÄCHSTER SCHRITT</p>
    <h1><?= jo_escape($result['label']) ?> öffnen.</h1>
    <p class="app-description">Ein Tipp auf den App-Button fragt dein Gerät. Falls die App fehlt oder der Wechsel blockiert wird, bleibt der Web-Link verfügbar.</p>
    <div class="landing-actions">
        <a id="open-app" class="button primary" href="<?= jo_escape($platform === 'ios' ? $result['ios_url'] : ($platform === 'android' ? $result['android_url'] : $result['web_url'])) ?>"
           data-ios="<?= jo_escape($result['ios_url']) ?>" data-android="<?= jo_escape($result['android_url']) ?>" data-web="<?= jo_escape($result['web_url']) ?>" rel="noreferrer"><?= jo_escape($result['label']) ?>-App öffnen</a>
        <a class="button secondary" href="<?= jo_escape($result['web_url']) ?>" rel="noreferrer">Im Web öffnen</a>
    </div>
    <?php if ($result['app'] === 'gmail' && $result['search'] !== ''): ?>
        <div class="app-notice"><strong>Eine bestimmte Mail öffnen?</strong><p>Gmail bietet dafür keinen verlässlich unterstützten App-Link. Der App-Button öffnet nur Gmail. Suche kopieren und im richtigen Gmail-Konto einfügen – oder die genaue Websuche öffnen.</p></div>
        <label for="gmail-search">Suche für deine Mail</label>
        <input id="gmail-search" value="<?= jo_escape($result['search']) ?>" readonly autocomplete="off">
        <button class="button secondary" type="button" data-copy="gmail-search">Suche kopieren</button>
        <a id="gmail-experimental" class="app-experimental" href="<?= jo_escape($result['experimental_url']) ?>" <?= $platform !== 'ios' ? 'hidden' : '' ?> rel="noreferrer">iOS: Direktsuche versuchen (experimentell)</a>
    <?php endif; ?>
    <p class="browser-hint">Kein App-Wechsel? Im Browser-Menü „In Safari öffnen“ bzw. „Im Browser öffnen“ wählen und dort erneut tippen. Die passende App muss installiert sein.</p>
    <details class="app-share"><summary>HTTPS-Link teilen</summary><label for="app-share-url">Für Telegram, n8n und andere Apps</label><input id="app-share-url" value="<?= jo_escape($shareUrl) ?>" readonly autocomplete="off"><button class="button secondary" type="button" data-copy="app-share-url">Link kopieren</button></details>
    <p id="app-feedback" class="feedback" role="status" aria-live="polite"></p>
    <noscript><p class="browser-hint">Links funktionieren ohne JavaScript. Zum Kopieren das Textfeld auswählen.</p></noscript>
</main>
</body>
</html>
