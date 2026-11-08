<?php
declare(strict_types=1);
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/smartlinks.php';
require_once __DIR__ . '/app-links.php';

$checks = 0;
function app_check(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($label);
}
function app_reject(string $app, array $input): void
{
    try { jo_app_resolve($app, $input); }
    catch (InvalidArgumentException $e) { app_check(true, 'rejected invalid input'); return; }
    throw new RuntimeException('Accepted invalid input: ' . $app . ' ' . json_encode($input));
}

$gmail = jo_app_resolve('gmail', ['mid' => '<receipt+2026@example.org>', 'account' => 'me+invoices@gmail.com']);
app_check($gmail['search'] === 'rfc822msgid:receipt+2026@example.org', 'Strip surrounding RFC brackets');
app_check($gmail['ios_url'] === 'googlegmail:///', 'Supported inbox scheme remains separate from experimental search');
app_check($gmail['experimental_url'] === 'googlegmail:///search?q=rfc822msgid%3Areceipt%2B2026%40example.org', 'Requested search scheme is correctly encoded');
app_check($gmail['web_url'] === 'https://mail.google.com/mail/?authuser=me%2Binvoices%40gmail.com#search/rfc822msgid%3Areceipt%2B2026%40example.org', 'Exact web search with account selector');
app_check(strpos($gmail['android_url'], 'action=android.intent.action.MAIN') !== false, 'Android inbox launcher');
app_check(strpos($gmail['android_url'], 'S.browser_fallback_url=' . rawurlencode($gmail['web_url'])) !== false, 'Android fallback encodes the whole web fragment');
app_check(strpos($gmail['android_url'], 'S.query=') === false, 'Do not invent an Android search intent');
app_check(jo_app_share_url($gmail, 'https://go.example.org/') === 'https://go.example.org/gmail?mid=receipt%2B2026%40example.org&account=me%2Binvoices%40gmail.com', 'Stateless share URL');
$inbox = jo_app_resolve('gmail', []);
app_check($inbox['search'] === '' && $inbox['experimental_url'] === '', 'Inbox needs no Message-ID');
app_check(jo_app_share_url($inbox, 'https://go.example.org') === 'https://go.example.org/gmail', 'No unnecessary query string');
foreach (['<>', '1a1bb7de99c66f42', 'a@b> <x@y', "a@b\r\nBcc:x@y", str_repeat('a', 999)] as $mid) app_reject('gmail', ['mid' => $mid]);
app_reject('gmail', ['mid' => ['a@b']]);
app_reject('gmail', ['account' => 'javascript:alert(1)']);
app_reject('gmail', ['account' => "a@b.com\nBcc:x@y.com"]);

$wa = jo_app_resolve('whatsapp', ['phone' => '+491701234567', 'text' => "Grüße & danke!\nNeue Zeile"]);
app_check($wa['params']['phone'] === '491701234567', 'Normalize one leading plus');
app_check($wa['web_url'] === 'https://wa.me/491701234567?text=Gr%C3%BC%C3%9Fe%20%26%20danke%21%0ANeue%20Zeile', 'WhatsApp text roundtrip');
app_check($wa['ios_url'] === $wa['android_url'] && strpos($wa['ios_url'], 'whatsapp://send?') === 0, 'WhatsApp native action on both platforms');
foreach (['00491701234567', '49170;scheme=javascript', '+49 1701234567', '123', '++491701234567'] as $phone) app_reject('whatsapp', ['phone' => $phone]);

$telegram = jo_app_resolve('telegram', ['username' => '@durov']);
app_check($telegram['ios_url'] === 'tg://resolve?domain=durov', 'Telegram native profile');
app_check($telegram['web_url'] === 'https://t.me/durov', 'Telegram web profile');
foreach (['proxy', 'oauth', 'contact', '@@durov', 'a/b', 'test;domain=other', '12345', 'abcd'] as $username) app_reject('telegram', ['username' => $username]);

$maps = jo_app_resolve('maps', ['q' => 'Brandenburger Tor, Berlin & Café']);
app_check($maps['ios_url'] === 'comgooglemaps://?q=Brandenburger%20Tor%2C%20Berlin%20%26%20Caf%C3%A9', 'Google Maps iOS');
app_check($maps['android_url'] === 'geo:0,0?q=Brandenburger%20Tor%2C%20Berlin%20%26%20Caf%C3%A9', 'Android native maps chooser');
app_check(strpos($maps['web_url'], 'https://www.google.com/maps/search/?api=1&query=') === 0, 'Google Maps web API');
app_reject('maps', ['q' => '']);
app_reject('maps', ['q' => ['Berlin']]);
app_reject('maps', ['q' => "Berlin\0"]);
app_reject('custom', ['url' => 'javascript:alert(1)']);

// Unrelated incoming arguments can never replace generated destinations.
$ignored = jo_app_resolve('gmail', ['mid' => 'a@b', 'url' => 'https://evil.example', 'redirect' => 'javascript:alert(1)']);
app_check(strpos($ignored['web_url'], 'evil') === false, 'No arbitrary redirects');
app_check(strpos(jo_app_share_url($ignored, 'https://go.example.org'), 'redirect') === false, 'Only allowlisted query fields are forwarded');
try { sl_url('googlegmail:///search?q=test'); throw new RuntimeException('Editor must keep rejecting arbitrary schemes'); }
catch (InvalidArgumentException $e) { app_check(true, 'Editor URL policy unchanged'); }

putenv('JUSTOPEN_BASE_URL=https://go.example.org');
foreach (['/gmail?mid=a%40b', '/whatsapp?phone=491701234567', '/telegram?username=durov', '/maps?q=Berlin', '/apps'] as $uri) {
    parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $get);
    ob_start();
    $handled = jo_app_dispatch(['REQUEST_URI' => $uri, 'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)', 'REQUEST_METHOD' => 'GET'], $get);
    $body = ob_get_clean();
    app_check($handled && http_response_code() === 200, 'Render route: ' . $uri);
    app_check(strpos($body, '/assets/app-links.css') !== false, 'Local styling');
}
ob_start();
jo_app_dispatch(['REQUEST_URI' => '/gmail', 'REQUEST_METHOD' => 'POST'], []);
ob_end_clean();
app_check(http_response_code() === 405, 'Read-only endpoints reject POST');
app_check(!jo_app_dispatch(['REQUEST_URI' => '/amazon'], []), 'Amazon route remains untouched');
app_check(!jo_app_dispatch(['REQUEST_URI' => '/admin'], []), 'Admin remains private');

// Escaping stays intact even when the ID contains HTML-looking characters.
$result = jo_app_resolve('gmail', ['mid' => '"onfocus="alert(1)@example.org']);
$platform = 'ios'; $shareUrl = jo_app_share_url($result, 'https://go.example.org');
ob_start(); sl_render('app', compact('result', 'platform', 'shareUrl')); $html = ob_get_clean();
app_check(strpos($html, 'value="rfc822msgid:&quot;onfocus=&quot;') !== false, 'Escape the visible search value');
app_check(strpos($html, ' value="rfc822msgid:"onfocus=') === false, 'No attribute injection');
app_check(strpos($html, 'data-ios="googlegmail:///"') !== false, 'Explicit inbox link');
echo "App links: {$checks} checks passed.\n";
