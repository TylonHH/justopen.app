<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Anmelden · justopen</title><link rel="stylesheet" href="/assets/smart.css"></head>
<body class="landing admin-theme">
<main class="login-card">
    <div class="brand"><span class="brand-mark" aria-hidden="true">↗</span> justopen <span class="badge">Link Studio</span></div>
    <h1>Deine Links.<br>Dein Weg.</h1>
    <p class="description">Melde dich an, um Links und Landingpages zu gestalten.</p>
    <?php if ($error !== ''): ?><p class="alert error" role="alert"><?= jo_escape($error) ?></p><?php endif; ?>
    <form method="post" action="/admin">
        <input type="hidden" name="csrf" value="<?= jo_escape($_SESSION['csrf']) ?>">
        <label for="password">Adminpasswort</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="4096" autofocus>
        <button class="button primary" type="submit">Studio öffnen <span aria-hidden="true">↗</span></button>
    </form>
</main>
</body>
</html>
