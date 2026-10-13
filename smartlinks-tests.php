<?php
declare(strict_types=1);
require __DIR__ . '/engine.php';
require __DIR__ . '/smartlinks.php';
putenv('JUSTOPEN_DATA_DIR=' . sys_get_temp_dir() . '/justopen-test-' . bin2hex(random_bytes(6)));
putenv('JUSTOPEN_ADMIN_PASSWORD=local-test-password-12345');
putenv('JUSTOPEN_ADMIN_PASSWORD_HASH');
putenv('JUSTOPEN_BASE_URL=http://localhost:8088');

$checks = 0;
function check(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($label);
}
function rejects(callable $callback, string $label): void
{
    try { $callback(); } catch (InvalidArgumentException $e) { check(true, $label); return; }
    check(false, $label);
}
function form(array $config): array
{
    $input = $config;
    foreach (SL_UTM as $key) $input[$key] = $config['utm'][$key];
    if (!$config['active']) unset($input['active']);
    if (!$config['forward_utm']) unset($input['forward_utm']);
    return $input;
}

$original = 'https://hoerbuch.warsowerk.de/a%2Fb?x=a+b&x=second&tag=affiliate-21&sig=ABC%2f123&utm_source=old#chapter-2';
check(sl_url($original) === $original, 'Valid target stays byte-identical.');
foreach (['javascript:alert(1)', 'data:text/html,test', '//example.com', 'https://user:secret@example.com/', "https://example.com/\r\nX-Test:yes", 'https://example.com\\@evil.test', 'https://example.com/a b', 'file:///etc/passwd'] as $bad) {
    rejects(static fn() => sl_url($bad), 'Reject unsafe target: ' . $bad);
}
$config = sl_defaults(); $config['url'] = $original; $config['slug'] = 'hoerbuch';
check(sl_target($config) === $original, 'No UTM changes preserve entire URL.');
$config['utm']['utm_source'] = 'instagram'; $config['utm']['utm_campaign'] = 'Hörbuch & mehr';
$target = sl_target($config, ['utm_medium' => 'dm', 'utm_source' => 'untrusted', 'url' => 'https://evil.test']);
check($target === 'https://hoerbuch.warsowerk.de/a%2Fb?x=a+b&x=second&tag=affiliate-21&sig=ABC%2f123&utm_source=instagram&utm_medium=dm&utm_campaign=H%C3%B6rbuch%20%26%20mehr#chapter-2', 'UTM priority, Unicode, duplicate parameters, signature bytes and fragment.');
$config['forward_utm'] = false;
check(strpos(sl_target($config, ['utm_medium' => 'dm']), 'utm_medium') === false, 'Incoming tracking can be disabled.');
$config['forward_utm'] = true;
check(strpos(sl_target($config, ['utm_medium' => ['nested']]), 'utm_medium') === false, 'Nested tracking rejected.');
check(strpos(sl_target($config, ['utm_medium' => "dm\r\nInjected"]), 'utm_medium') === false, 'Control characters in tracking ignored.');
$f = form($config);
$validated = sl_config($f);
check($validated['description'] === '', 'Landing needs no description.');
foreach (['https://example.org/any/page?key=value#section', 'https://www.amazon.de/dp/B012345678?tag=example-21', 'http://another.example:8080/a%2Fb?x=1&x=2'] as $arbitrary) {
    check(sl_config(array_replace($f, ['url' => $arbitrary]))['url'] === $arbitrary, 'Arbitrary host accepted without provider restrictions.');
}
check($validated['slug'] === 'hoerbuch' && $validated['url'] === $original, 'Admin form validation.');
rejects(static fn() => sl_config(array_replace($f, ['url' => ['bad']])), 'Array URL rejected.');
rejects(static fn() => sl_config(array_replace($f, ['slug' => '../escape'])), 'Slug traversal rejected.');
rejects(static fn() => sl_config(array_replace($f, ['accent' => 'red; color:white'])), 'CSS injection rejected.');
rejects(static fn() => sl_config(array_replace($f, ['accent_text' => $f['accent']])), 'Unreadable theme rejected.');
rejects(static fn() => sl_config(array_replace($f, ['logo_url' => 'http://example.com/logo.png'])), 'Mixed-content logo rejected.');
rejects(static fn() => sl_config(array_replace($f, ['delay' => '-1'])), 'Negative countdown rejected.');
rejects(static fn() => sl_config(array_replace($f, ['delay' => '301'])), 'Countdown limit.');
rejects(static fn() => sl_config(array_replace($f, ['delay' => '1.5'])), 'Fractional countdown rejected.');
check(sl_config(array_replace($f, ['delay' => '0']))['delay'] === '0', 'Immediate attempt is not disabled.');
rejects(static fn() => sl_config(array_replace($f, ['secondary_action' => 'custom', 'secondary_url' => ''])), 'Custom second action needs URL.');
$f['brand'] = '<script>alert("test")</script>';
$safe = sl_config($f);
ob_start(); sl_render('landing', ['config' => $safe, 'target' => sl_target($safe), 'handoff' => 'http://localhost:8088/go/hoerbuch', 'preview' => false]); $html = ob_get_clean();
check(strpos($html, '<script>alert(') === false && strpos($html, '&lt;script&gt;') !== false, 'Stored HTML escaped in landing.');
check(substr_count($html, 'id="open-external"') === 1 && substr_count($html, 'id="secondary"') === 1, 'Exactly two main actions.');
sl_save($validated, false);
check(sl_link('hoerbuch')['url'] === $original, 'Persistent link creation.');
rejects(static fn() => sl_save($validated, false), 'Duplicate slug does not overwrite.');
$validated['active'] = false; sl_save($validated, true);
check(sl_link('hoerbuch')['active'] === false, 'Deactivate persists.');
check(sl_link('unknown') === null, 'Unknown link lookup.');
$handoff = sl_handoff($config, ['utm_medium' => 'dm & text', 'url' => 'https://evil.test'], 'https://go.example.com');
check($handoff === 'https://go.example.com/go/hoerbuch?utm_medium=dm%20%26%20text', 'Handoff accepts only UTM.');

sl_session(['HTTP_HOST' => 'localhost:8088']);
check(!sl_authenticated(), 'Anonymous session is unauthenticated.');
check(!sl_csrf(['csrf' => 'wrong']) && !sl_csrf(['csrf' => []]), 'CSRF mismatch rejected.');
check(sl_csrf(['csrf' => $_SESSION['csrf']]), 'Valid CSRF accepted.');
for ($i = 0; $i < 5; $i++) check(!sl_login('wrong', 'test-ip'), 'Wrong password rejected.');
check(!sl_login('local-test-password-12345', 'test-ip'), 'Rate limit blocks sixth attempt including correct password.');
check(sl_login('local-test-password-12345', 'another-ip') && sl_authenticated(), 'Correct password authenticates.');
$_SESSION['last_seen'] = time() - 3601;
check(!sl_authenticated(), 'Idle session expires.');
check(sl_login('local-test-password-12345', 'another-ip'), 'Login after timeout.');
putenv('JUSTOPEN_ADMIN_PASSWORD=changed-password-12345');
check(!sl_authenticated(), 'Secret rotation invalidates sessions.');
putenv('JUSTOPEN_ADMIN_PASSWORD_HASH=' . password_hash('hash-password-12345', PASSWORD_DEFAULT));
check(sl_login('hash-password-12345', 'hash-ip'), 'Password hash deployment supported.');
putenv('JUSTOPEN_ADMIN_PASSWORD_HASH'); putenv('JUSTOPEN_ADMIN_PASSWORD=short');
check(sl_credential() === '' && !sl_authenticated(), 'Missing/weak deployment credential fails closed.');
session_write_close();
echo "$checks smart-link checks passed.\n";
