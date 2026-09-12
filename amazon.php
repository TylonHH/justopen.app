<?php

/**
 * Amazon smart-link endpoint.
 *
 * Usage:
 *   /amazon?url=https%3A%2F%2Fwww.amazon.de%2Fdp%2FB0EXAMPLE%3Ftag%3Dexample-21
 *
 * The complete Amazon URL is preserved so Associates tags and other query
 * parameters survive the hop. Only known Amazon hosts are accepted.
 */

const JO_AMAZON_MAX_URL_LENGTH = 4096;

function jo_amazon_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jo_amazon_allowed_hosts(): array
{
    return [
        'amazon.com', 'www.amazon.com', 'smile.amazon.com',
        'amazon.de', 'www.amazon.de',
        'amazon.co.uk', 'www.amazon.co.uk',
        'amazon.fr', 'www.amazon.fr',
        'amazon.it', 'www.amazon.it',
        'amazon.es', 'www.amazon.es',
        'amazon.nl', 'www.amazon.nl',
        'amazon.se', 'www.amazon.se',
        'amazon.pl', 'www.amazon.pl',
        'amazon.ca', 'www.amazon.ca',
        'amazon.com.au', 'www.amazon.com.au',
        'amazon.co.jp', 'www.amazon.co.jp',
        'amazon.in', 'www.amazon.in',
        'amazon.com.br', 'www.amazon.com.br',
        'amzn.to', 'www.amzn.to',
        'link.amazon', 'www.link.amazon',
    ];
}

function jo_amazon_validate_target(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > JO_AMAZON_MAX_URL_LENGTH) {
        throw new InvalidArgumentException('Missing or invalid Amazon URL.');
    }

    $parts = parse_url($raw);
    if (!is_array($parts)) {
        throw new InvalidArgumentException('Invalid Amazon URL.');
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('Only HTTP(S) URLs are allowed.');
    }
    if (!in_array($host, jo_amazon_allowed_hosts(), true)) {
        throw new InvalidArgumentException('Unsupported Amazon host.');
    }

    return $raw;
}

function jo_amazon_detect_platform(string $ua): string
{
    $ua = strtolower($ua);
    if (strpos($ua, 'android') !== false) {
        return 'android';
    }
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false || strpos($ua, 'ipod') !== false) {
        return 'ios';
    }
    return 'desktop';
}

function jo_amazon_detect_in_app(string $ua): bool
{
    $ua = strtolower($ua);
    $needles = [
        'instagram', 'fban', 'fbav', 'fb_iab', 'messenger', 'tiktok',
        'twitter', 'linkedinapp', 'snapchat', 'threads', 'wechat', 'micromessenger',
        'line/', 'telegram', 'whatsapp', 'reddit', 'gsa/',
    ];

    foreach ($needles as $needle) {
        if (strpos($ua, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function jo_amazon_android_intent(string $target): string
{
    $parts = parse_url($target);
    if (!is_array($parts)) {
        return $target;
    }

    $host = (string) ($parts['host'] ?? 'www.amazon.de');
    $path = (string) ($parts['path'] ?? '/');
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

    // Amazon Shopping package on Android. If the app is unavailable Android
    // falls back to the original HTTPS destination.
    return 'intent://' . $host . $path . $query . $fragment
        . '#Intent;scheme=https;package=com.amazon.mShop.android.shopping;'
        . 'S.browser_fallback_url=' . rawurlencode($target) . ';end';
}

function jo_amazon_security_headers(): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
}

try {
    $target = jo_amazon_validate_target((string) ($_GET['url'] ?? ''));
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    jo_amazon_security_headers();
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Invalid Amazon link</title><body style="font-family:system-ui;background:#111;color:#eee;padding:2rem">'
        . '<h1>Invalid Amazon link</h1><p>' . jo_amazon_escape($e->getMessage()) . '</p></body></html>';
    exit;
}

$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$platform = jo_amazon_detect_platform($ua);
$inApp = jo_amazon_detect_in_app($ua);

if ($platform === 'desktop') {
    header('Location: ' . $target, true, 302);
    exit;
}

$androidIntent = jo_amazon_android_intent($target);
jo_amazon_security_headers();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Open Amazon</title>
    <style>
        :root { color-scheme: dark; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #09090b; color: #f4f4f5; display: grid; place-items: center; padding: 24px; }
        main { width: min(100%, 560px); border: 1px solid #3f3f46; background: #18181b; padding: 28px; border-radius: 18px; box-shadow: 0 20px 70px rgba(0,0,0,.35); }
        h1 { margin: 0 0 12px; font-size: 1.7rem; }
        p { color: #d4d4d8; line-height: 1.55; }
        .hint { border-left: 4px solid #f59e0b; padding: 12px 16px; background: #27272a; margin: 20px 0; }
        .actions { display: grid; gap: 12px; margin-top: 24px; }
        a, button { display: block; width: 100%; border-radius: 12px; padding: 14px 16px; text-align: center; font-weight: 700; font-size: 1rem; text-decoration: none; cursor: pointer; }
        .primary { border: 0; background: #f59e0b; color: #18181b; }
        .secondary { border: 1px solid #71717a; background: transparent; color: #fafafa; }
        small { display: block; color: #a1a1aa; margin-top: 20px; word-break: break-all; }
    </style>
</head>
<body>
<main>
    <h1>Open Amazon</h1>
    <?php if ($platform === 'ios' && $inApp): ?>
        <p>This link is currently open inside an in-app browser.</p>
        <div class="hint"><strong>iPhone / iPad:</strong> tap the <strong>•••</strong> menu (or browser/Safari icon) and choose <strong>Open in Safari</strong>. From Safari, Amazon can hand the link to the Amazon Shopping app when supported.</div>
    <?php elseif ($platform === 'android'): ?>
        <p>Opening the Amazon Shopping app. If it is not installed, the link will open in your browser.</p>
    <?php else: ?>
        <p>Continue to Amazon. If the Amazon Shopping app is installed, the device may open it automatically.</p>
    <?php endif; ?>

    <div class="actions">
        <?php if ($platform === 'android'): ?>
            <a id="open-app" class="primary" href="<?= jo_amazon_escape($androidIntent) ?>">Open Amazon app</a>
        <?php else: ?>
            <a id="open-app" class="primary" href="<?= jo_amazon_escape($target) ?>">Continue to Amazon</a>
        <?php endif; ?>
        <a class="secondary" href="<?= jo_amazon_escape($target) ?>">Open website instead</a>
    </div>
    <small><?= jo_amazon_escape($target) ?></small>
</main>
<script>
(function () {
    var platform = <?= json_encode($platform, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var inApp = <?= $inApp ? 'true' : 'false' ?>;
    var target = <?= json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var androidIntent = <?= json_encode($androidIntent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (platform === 'android') {
        window.setTimeout(function () { window.location.href = androidIntent; }, 120);
        window.setTimeout(function () {
            if (!document.hidden) window.location.href = target;
        }, 1800);
        return;
    }

    // On iOS there is no reliable standards-based way to force Safari to open
    // from another app's embedded browser. Do not bounce the user back into the
    // same webview; show the explicit instruction instead.
    if (platform === 'ios' && !inApp) {
        window.setTimeout(function () { window.location.href = target; }, 150);
    }
}());
</script>
</body>
</html>
