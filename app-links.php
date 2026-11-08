<?php
declare(strict_types=1);

/** Stateless, allowlisted native-app links. Never accept arbitrary schemes or redirect targets. */
function jo_app_resolve(string $app, array $input): array
{
    $result = ['app' => $app, 'search' => '', 'experimental_url' => ''];
    if ($app === 'gmail') {
        $mid = sl_string($input, 'mid', 998);
        $hasMid = $mid !== '';
        if (substr($mid, 0, 1) === '<' && substr($mid, -1) === '>') $mid = substr($mid, 1, -1);
        if ($hasMid && !preg_match('/^[^\s<>@]+@[^\s<>@]+$/uD', $mid)) {
            throw new InvalidArgumentException('Bitte die RFC-Message-ID verwenden, nicht die interne Gmail-ID.');
        }
        $account = sl_string($input, 'account', 254);
        if ($account !== '' && !filter_var($account, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Bitte eine gültige Gmail-Kontoadresse eingeben.');
        }
        $result['search'] = $mid === '' ? '' : 'rfc822msgid:' . $mid;
        $web = 'https://mail.google.com/mail/' . ($account === '' ? '' : '?authuser=' . rawurlencode($account));
        if ($mid !== '') $web .= '#search/' . rawurlencode($result['search']);
        $result += [
            'label' => 'Gmail', 'web_url' => $web, 'ios_url' => 'googlegmail:///',
            // Gmail does not expose a supported Android message/search deep link. Open the inbox only.
            'android_url' => 'intent:#Intent;action=android.intent.action.MAIN;category=android.intent.category.LAUNCHER;'
                . 'package=com.google.android.gm;S.browser_fallback_url=' . rawurlencode($web) . ';end',
            'params' => array_filter(['mid' => $mid, 'account' => $account], static fn($value) => $value !== ''),
        ];
        if ($mid !== '') $result['experimental_url'] = 'googlegmail:///search?q=' . rawurlencode($result['search']);
    } elseif ($app === 'whatsapp') {
        $phone = preg_replace('/^\+/', '', sl_string($input, 'phone', 16));
        if (!preg_match('/^[1-9][0-9]{6,14}$/D', $phone)) {
            throw new InvalidArgumentException('Telefonnummer mit Ländervorwahl eingeben, ohne Leerzeichen, z. B. 491701234567.');
        }
        $text = sl_string($input, 'text', 2000);
        $query = http_build_query(array_filter(['phone' => $phone, 'text' => $text], static fn($value) => $value !== ''), '', '&', PHP_QUERY_RFC3986);
        $result += [
            'label' => 'WhatsApp', 'web_url' => 'https://wa.me/' . $phone . ($text === '' ? '' : '?text=' . rawurlencode($text)),
            'ios_url' => 'whatsapp://send?' . $query, 'android_url' => 'whatsapp://send?' . $query,
            'params' => array_filter(['phone' => $phone, 'text' => $text], static fn($value) => $value !== ''),
        ];
    } elseif ($app === 'telegram') {
        $username = preg_replace('/^@/', '', sl_string($input, 'username', 33));
        if (!preg_match('/^[a-z][a-z0-9_]{4,31}$/iD', $username)
            || in_array(strtolower($username), ['addemoji', 'addlist', 'addstickers', 'addstyle', 'addtheme', 'auction', 'auth', 'boost', 'call', 'confirmphone', 'contact', 'giftcode', 'invoice', 'joinchat', 'login', 'newbot', 'oauth', 'proxy', 'setlanguage', 'share', 'socks', 'web'], true)) {
            throw new InvalidArgumentException('Bitte einen öffentlichen Telegram-Benutzernamen mit 5–32 Zeichen eingeben.');
        }
        $result += [
            'label' => 'Telegram', 'web_url' => 'https://t.me/' . $username,
            'ios_url' => 'tg://resolve?domain=' . rawurlencode($username),
            'android_url' => 'tg://resolve?domain=' . rawurlencode($username), 'params' => ['username' => $username],
        ];
    } elseif ($app === 'maps') {
        $query = sl_string($input, 'q', 1000);
        if ($query === '') throw new InvalidArgumentException('Bitte einen Ort oder eine Adresse eingeben.');
        $result += [
            'label' => 'Google Maps', 'web_url' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query),
            'ios_url' => 'comgooglemaps://?q=' . rawurlencode($query),
            'android_url' => 'geo:0,0?q=' . rawurlencode($query), 'params' => ['q' => $query],
        ];
    } else {
        throw new InvalidArgumentException('Diese App wird nicht unterstützt.');
    }
    return $result;
}

function jo_app_share_url(array $result, string $origin): string
{
    $query = http_build_query($result['params'], '', '&', PHP_QUERY_RFC3986);
    return rtrim($origin, '/') . '/' . $result['app'] . ($query === '' ? '' : '?' . $query);
}

function jo_app_dispatch(array $server, array $get): bool
{
    $path = rtrim(parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
    if (!in_array($path, ['/apps', '/gmail', '/whatsapp', '/telegram', '/maps'], true)) return false;
    if (!in_array(strtoupper($server['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD');
        sl_render('message', ['title' => 'Methode nicht erlaubt', 'message' => 'Bitte den App-Link normal öffnen.'], 405);
        return true;
    }
    try {
        $origin = sl_origin($server);
        if ($path === '/apps') {
            sl_render('apps');
        } else {
            $result = jo_app_resolve(substr($path, 1), $get);
            $platform = jo_detect_platform((string) ($server['HTTP_USER_AGENT'] ?? ''));
            $shareUrl = jo_app_share_url($result, $origin);
            // Keep the landing on desktop too: it lets an operator copy/test the HTTPS link safely.
            sl_render('app', compact('result', 'platform', 'shareUrl'));
        }
    } catch (InvalidArgumentException $e) {
        sl_render('message', ['title' => 'Ungültiger App-Link', 'message' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        error_log('justopen apps: ' . $e->getMessage());
        sl_render('message', ['title' => 'Kurz nicht erreichbar', 'message' => 'Bitte die Serverkonfiguration prüfen.'], 503);
    }
    return true;
}
