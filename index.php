<?php

require_once __DIR__ . '/engine.php';

/**
 * Amazon is handled by the dedicated /amazon smart-link endpoint rather than
 * the generic provider engine because the full destination URL (including
 * Associates tags/query parameters and opaque link.amazon short URLs) must be
 * preserved byte-for-byte.
 */
function jo_index_amazon_result(string $input, string $baseUrl): ?array
{
    $input = trim($input);
    if ($input === '' || strlen($input) > 4096) {
        return null;
    }

    $parts = parse_url($input);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $allowedHosts = [
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

    if (!in_array($host, $allowedHosts, true)) {
        return null;
    }

    return [
        'short_url'      => rtrim($baseUrl, '/') . '/amazon?url=' . rawurlencode($input),
        'canonical_url'  => $input,
        'provider'       => 'amazon',
        'provider_label' => 'Amazon',
    ];
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($requestUri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

// Intercept Amazon submissions before the generic provider parser. This makes
// both the UI form and POST /api/shorten accept regular Amazon URLs as well as
// opaque short links such as https://link.amazon/B08NZwPCe.
if ($method === 'POST' && ($path === '/' || $path === '/api/shorten')) {
    $baseUrl = jo_base_url($_SERVER);
    $amazonResult = jo_index_amazon_result((string) ($_POST['url'] ?? ''), $baseUrl);

    if ($amazonResult !== null) {
        if ($path === '/api/shorten') {
            http_response_code(200);
            foreach (jo_security_headers() as $name => $value) {
                if ($name === 'Content-Type') {
                    continue;
                }
                header($name . ': ' . $value);
            }
            header('Content-Type: application/json; charset=UTF-8');
            echo jo_json([
                'ok'             => true,
                'short_url'      => $amazonResult['short_url'],
                'canonical_url'  => $amazonResult['canonical_url'],
                'provider'       => $amazonResult['provider'],
                'provider_label' => $amazonResult['provider_label'],
            ]);
            exit;
        }

        $langOverride = $_POST['lang'] ?? $_GET['lang'] ?? null;
        $lang = jo_detect_language(
            (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''),
            is_string($langOverride) ? $langOverride : null
        );

        http_response_code(200);
        foreach (jo_security_headers() as $name => $value) {
            header($name . ': ' . $value);
        }
        echo jo_render_home([
            'lang'     => $lang,
            'input'    => (string) ($_POST['url'] ?? ''),
            'result'   => $amazonResult,
            'error'    => null,
            'base_url' => $baseUrl,
        ]);
        exit;
    }
}

$response = jo_route_request($_SERVER, $_GET, $_POST);

http_response_code((int) ($response['status'] ?? 200));

foreach (($response['headers'] ?? []) as $name => $value) {
    header($name . ': ' . $value);
}

if (($response['type'] ?? '') === 'redirect' && isset($response['location'])) {
    header('Location: ' . $response['location'], true, (int) ($response['status'] ?? 302));
    exit;
}

echo (string) ($response['body'] ?? '');
