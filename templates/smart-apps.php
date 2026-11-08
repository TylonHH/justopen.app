<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>App-Links · justopen</title><link rel="stylesheet" href="/assets/smart.css"><link rel="stylesheet" href="/assets/app-links.css">
</head>
<body>
<header class="studio-header"><a class="brand" href="/admin"><span class="brand-mark" aria-hidden="true">↗</span> justopen <span class="badge">App-Links</span></a><a href="/admin">Link Studio ↗</a></header>
<main class="app-catalog">
    <p class="eyebrow">VOM LINK IN DIE APP</p><h1>Weniger Umwege.<br>Mehr Möglichkeiten.</h1>
    <p class="app-intro">Klickbare HTTPS-Links für Telegram und andere Messenger. Ohne gespeicherten Link pro Nachricht. App-Öffnung bleibt vom Gerät abhängig; ein Web-Fallback ist immer dabei.</p>
    <div class="app-grid">
        <form class="app-form" action="/gmail" method="get"><span class="badge">MAIL</span><h2>Gmail</h2><p>App öffnen oder eine konkrete Mail im Web suchen.</p><label for="mid">RFC-Message-ID <small>Optional · nicht die interne Gmail-ID</small></label><input id="mid" name="mid" maxlength="998" placeholder="&lt;message@example.org&gt;"><label for="account">Gmail-Konto <small>Optional · für die Websuche</small></label><input id="account" name="account" type="email" maxlength="254" placeholder="du@gmail.com"><button class="button primary">Gmail-Link anzeigen ↗</button></form>
        <form class="app-form" action="/whatsapp" method="get"><span class="badge">CHAT</span><h2>WhatsApp</h2><p>Chat öffnen und auf Wunsch einen Text vorbereiten. Es wird nichts automatisch gesendet.</p><label for="phone">Telefonnummer mit Ländervorwahl</label><input id="phone" name="phone" type="tel" maxlength="16" placeholder="491701234567" required><label for="wa-text">Nachricht <small>Optional</small></label><textarea id="wa-text" name="text" maxlength="2000" rows="2"></textarea><button class="button primary">WhatsApp-Link anzeigen ↗</button></form>
        <form class="app-form" action="/telegram" method="get"><span class="badge">KONTAKT</span><h2>Telegram</h2><p>Ein öffentliches Profil, einen Kanal oder einen Bot öffnen.</p><label for="username">Öffentlicher Benutzername</label><input id="username" name="username" maxlength="33" placeholder="@durov" required><button class="button primary">Telegram-Link anzeigen ↗</button></form>
        <form class="app-form" action="/maps" method="get"><span class="badge">ORTE</span><h2>Google Maps</h2><p>Orte und Adressen suchen. Android verwendet die Karten-App-Auswahl.</p><label for="maps-q">Ort oder Adresse</label><input id="maps-q" name="q" maxlength="1000" placeholder="Brandenburger Tor, Berlin" required><button class="button primary">Karten-Link anzeigen ↗</button></form>
    </div>
    <section class="app-existing"><h2>Schon an Bord.</h2><p>Amazon ist unter <code>/amazon?url=…</code> bereits integriert. YouTube, Instagram, X, Facebook, LinkedIn und Spotify bleiben ebenfalls verfügbar.</p><a class="button secondary" href="/social">Social- und Amazon-Link erstellen ↗</a></section>
    <p class="browser-hint">Datenschutz: Diese App-Links speichern nichts in der Link-Datenbank. IDs, Telefonnummern und Texte können trotzdem im Browser-Verlauf, in Server-Logs oder Messenger-Linkvorschauen auftauchen. Keine Passwörter oder Zugangstokens übergeben.</p>
</main>
</body>
</html>
