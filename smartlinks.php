<?php
declare(strict_types=1);

const SL_UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];

// A structure is a rendering variant implemented in the landing template and CSS.
// Users combine a structure with colors, texts and buttons into their own brands.
const SL_STRUCTURES = ['card' => 'Karte · klassisch', 'business' => 'Business · sachlich', 'private' => 'Privat · persönlich'];

// Everything a brand supplies to a link. Links follow a brand unless they carry exceptions.
const SL_PRESENTATION = ['brand', 'logo_url', 'primary_label', 'secondary_label', 'secondary_action',
    'secondary_url', 'structure', 'background', 'surface', 'text', 'accent', 'accent_text', 'shape', 'delay'];

function sl_defaults(): array
{
    return [
        'brand' => 'justopen', 'title' => 'Mein Link',
        'description' => '',
        'primary_label' => 'Im Browser öffnen', 'secondary_label' => 'Hier weiter öffnen',
        'secondary_action' => 'current', 'secondary_url' => '', 'logo_url' => '',
        'structure' => 'card', 'brand_id' => '', 'override' => false,
        'background' => '#f4f3ee', 'surface' => '#ffffff', 'text' => '#182b25',
        'accent' => '#176b52', 'accent_text' => '#ffffff', 'shape' => 'rounded',
        'delay' => '', 'forward_utm' => true, 'active' => true,
        'url' => '', 'slug' => '', 'utm' => array_fill_keys(SL_UTM, ''),
    ];
}

function sl_brand_defaults(): array
{
    $config = sl_defaults();
    foreach (['title', 'description', 'url', 'slug', 'active', 'brand_id', 'override'] as $key) unset($config[$key]);
    return $config;
}

function sl_data_dir(): string
{
    return rtrim(getenv('JUSTOPEN_DATA_DIR') ?: dirname(__DIR__) . '/runtime', '/\\');
}

function sl_db(): PDO
{
    // ponytail: single-host SQLite; move to shared storage before adding app replicas.
    static $db;
    if ($db instanceof PDO) return $db;
    $dir = sl_data_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create data directory.');
    }
    $db = new PDO('sqlite:' . $dir . '/justopen.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
    $hasBrands = (bool) $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='brands'")->fetchColumn();
    $hasLayouts = (bool) $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='layouts'")->fetchColumn();
    // Earlier builds stored brand presets as "layouts". Rename in place, never drop data.
    if (!$hasBrands && $hasLayouts) $db->exec('ALTER TABLE layouts RENAME TO brands');
    $db->exec('CREATE TABLE IF NOT EXISTS links (slug TEXT PRIMARY KEY, config TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK(id=1), config TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS brands (id TEXT PRIMARY KEY, name TEXT NOT NULL, config TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS login_attempts (client TEXT PRIMARY KEY, failures INTEGER NOT NULL, expires INTEGER NOT NULL);');
    // Seed only when neither table existed: deliberately emptied installs stay empty.
    if (!$hasBrands && !$hasLayouts) sl_seed_brands($db);
    chmod($dir . '/justopen.sqlite', 0600);
    return $db;
}

function sl_brand_presets(): array
{
    // First-run brands. Texts, structure, palette and button shape; links stay empty.
    $defaults = sl_brand_defaults();
    return [
        ['Standard', array_replace($defaults, ['brand' => 'justopen'])],
        ['Business', array_replace($defaults, [
            'brand' => 'Dein Unternehmen', 'structure' => 'business', 'shape' => 'square',
            'background' => '#eef1f6', 'surface' => '#ffffff', 'text' => '#0f1c2e',
            'accent' => '#12365c', 'accent_text' => '#ffffff',
        ])],
        ['Privat', array_replace($defaults, [
            'brand' => 'Dein Name', 'structure' => 'private',
            'background' => '#f8f2ea', 'surface' => '#fffaf4', 'text' => '#2c241d',
            'accent' => '#8a4b2f', 'accent_text' => '#ffffff',
        ])],
    ];
}

function sl_seed_brands(PDO $db): void
{
    if ((int) $db->query('SELECT COUNT(*) FROM brands')->fetchColumn() > 0) return;
    $insert = $db->prepare('INSERT INTO brands(id,name,config,created_at,updated_at) VALUES(?,?,?,?,?)');
    $now = gmdate('Y-m-d H:i:s');
    foreach (sl_brand_presets() as [$name, $config]) {
        $insert->execute([bin2hex(random_bytes(6)), $name,
            json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $now, $now]);
    }
}

function sl_brand_rows(): array
{
    return sl_db()->query('SELECT id,name,config,created_at,updated_at FROM brands ORDER BY name COLLATE NOCASE, id')->fetchAll();
}

function sl_brand(string $id): ?array
{
    if (!preg_match('/^[a-f0-9]{6,32}$/D', $id)) return null;
    $query = sl_db()->prepare('SELECT id,name,config FROM brands WHERE id=?');
    $query->execute([$id]);
    $row = $query->fetch();
    if (!$row) return null;
    $config = array_replace(sl_brand_defaults(), json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR));
    return ['id' => (string) $row['id'], 'name' => (string) $row['name']] + $config;
}

function sl_url(string $url): string
{
    $url = trim($url);
    // Reject browser/PHP parsing differences, credentials, and raw control characters.
    if ($url === '' || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
        || !filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Bitte eine vollständige HTTP-/HTTPS-Adresse ohne Leerzeichen eingeben.');
    }
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('Nur HTTP-/HTTPS-Adressen ohne Zugangsdaten sind erlaubt.');
    }
    return $url;
}

function sl_string(array $input, string $key, int $max = 160): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > $max || !preg_match('//u', $value)
        || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
        throw new InvalidArgumentException('Ungültiger oder zu langer Wert: ' . $key);
    }
    return trim($value);
}

function sl_luminance(string $hex): float
{
    $rgb = array_map(static function ($channel) {
        $c = hexdec($channel) / 255;
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split(substr($hex, 1), 2));
    return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
}

function sl_contrast(string $a, string $b): float
{
    $x = sl_luminance($a); $y = sl_luminance($b);
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

function sl_palette(array $input, array $config): array
{
    foreach (['background', 'surface', 'text', 'accent', 'accent_text'] as $key) {
        $config[$key] = sl_string($input, $key);
        if (!preg_match('/^#[a-fA-F0-9]{6}$/D', $config[$key])) {
            throw new InvalidArgumentException('Bitte gültige Farben auswählen.');
        }
    }
    if (sl_contrast($config['text'], $config['surface']) < 4.5
        || sl_contrast($config['accent_text'], $config['accent']) < 4.5) {
        throw new InvalidArgumentException('Text und Hintergrund brauchen mindestens 4,5:1 Kontrast. Bitte Farben anpassen.');
    }
    return $config;
}

function sl_presentation(array $input, array $config): array
{
    foreach (['brand', 'primary_label', 'secondary_label'] as $key) {
        $config[$key] = sl_string($input, $key);
        if ($config[$key] === '') throw new InvalidArgumentException('Bitte alle Texte ausfüllen.');
    }
    $config['structure'] = sl_string($input, 'structure');
    if ($config['structure'] === '') $config['structure'] = 'card';
    if (!isset(SL_STRUCTURES[$config['structure']])) throw new InvalidArgumentException('Unbekannter Landingpage-Aufbau.');
    $config['logo_url'] = sl_string($input, 'logo_url', 8192);
    if ($config['logo_url'] !== '') {
        $config['logo_url'] = sl_url($config['logo_url']);
        if (strtolower(parse_url($config['logo_url'], PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException('Das Logo benötigt eine HTTPS-Adresse.');
        }
    }
    $config = sl_palette($input, $config);
    $config['shape'] = sl_string($input, 'shape');
    if (!in_array($config['shape'], ['rounded', 'square'], true)) throw new InvalidArgumentException('Ungültige Buttonform.');
    $config['secondary_action'] = sl_string($input, 'secondary_action');
    if (!in_array($config['secondary_action'], ['current', 'copy', 'custom'], true)) throw new InvalidArgumentException('Ungültige zweite Aktion.');
    $config['secondary_url'] = sl_string($input, 'secondary_url', 8192);
    if ($config['secondary_url'] !== '') $config['secondary_url'] = sl_url($config['secondary_url']);
    if ($config['secondary_action'] === 'custom' && $config['secondary_url'] === '') {
        throw new InvalidArgumentException('Bitte Zieladresse für den zweiten Button eingeben.');
    }
    $config['delay'] = sl_string($input, 'delay');
    if ($config['delay'] !== '' && (!preg_match('/^(0|[1-9][0-9]{0,2})$/D', $config['delay']) || (int) $config['delay'] > 300)) {
        throw new InvalidArgumentException('Countdown: leer zum Ausschalten oder 0 bis 300 Sekunden.');
    }
    return $config;
}

function sl_utm_values(array $input, array $config): array
{
    foreach (SL_UTM as $key) $config['utm'][$key] = sl_string($input, $key, 500);
    return $config;
}

function sl_config(array $input): array
{
    $config = sl_defaults();
    $config['title'] = sl_string($input, 'title');
    if ($config['title'] === '') throw new InvalidArgumentException('Bitte einen Linknamen zur Verwaltung eingeben.');
    $config = sl_presentation($input, $config);
    $config['brand_id'] = sl_string($input, 'brand_id', 64);
    if ($config['brand_id'] !== '' && !preg_match('/^[a-f0-9]{6,32}$/D', $config['brand_id'])) {
        throw new InvalidArgumentException('Unbekannte Marke.');
    }
    $config = sl_utm_values($input, $config);
    $config['forward_utm'] = isset($input['forward_utm']);
    $config['url'] = sl_url(sl_string($input, 'url', 8192));
    $config['slug'] = sl_string($input, 'slug', 64);
    if ($config['slug'] === '') $config['slug'] = bin2hex(random_bytes(5));
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D', $config['slug'])) {
        throw new InvalidArgumentException('Kurzname: 3–64 Zeichen, nur a–z, 0–9, Bindestrich und Unterstrich.');
    }
    $config['active'] = isset($input['active']);
    return $config;
}

function sl_brand_config(array $input): array
{
    $config = sl_brand_defaults();
    $config['name'] = sl_string($input, 'name', 80);
    if ($config['name'] === '') throw new InvalidArgumentException('Bitte einen Namen für die Marke eingeben.');
    $config = sl_presentation($input, $config);
    $config = sl_utm_values($input, $config);
    $config['forward_utm'] = isset($input['forward_utm']);
    return $config;
}

function sl_settings(): array
{
    $raw = sl_db()->query('SELECT config FROM settings WHERE id=1')->fetchColumn();
    return $raw ? array_replace(sl_defaults(), json_decode($raw, true, 32, JSON_THROW_ON_ERROR)) : sl_defaults();
}

function sl_link(string $slug): ?array
{
    $query = sl_db()->prepare('SELECT config FROM links WHERE slug=?');
    $query->execute([$slug]);
    $raw = $query->fetchColumn();
    return $raw ? array_replace(sl_defaults(), json_decode($raw, true, 32, JSON_THROW_ON_ERROR)) : null;
}

function sl_save(array $config, bool $update): void
{
    $now = gmdate('Y-m-d H:i:s');
    $json = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if ($update) {
        $q = sl_db()->prepare('UPDATE links SET config=?, updated_at=? WHERE slug=?');
        $q->execute([$json, $now, $config['slug']]);
    } else {
        $q = sl_db()->prepare('INSERT INTO links(slug,config,created_at,updated_at) VALUES(?,?,?,?)');
        try { $q->execute([$config['slug'], $json, $now, $now]); }
        catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 19) throw new InvalidArgumentException('Dieser Kurzname ist bereits vergeben.');
            throw $e;
        }
    }
}

function sl_delete_link(string $slug): bool
{
    $query = sl_db()->prepare('DELETE FROM links WHERE slug=?');
    $query->execute([$slug]);
    return $query->rowCount() > 0;
}

function sl_brand_save(array $brand, string $id = ''): string
{
    $config = json_encode(array_intersect_key($brand, sl_brand_defaults()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $now = gmdate('Y-m-d H:i:s');
    if ($id === '') {
        $id = bin2hex(random_bytes(6));
        sl_db()->prepare('INSERT INTO brands(id,name,config,created_at,updated_at) VALUES(?,?,?,?,?)')
            ->execute([$id, $brand['name'], $config, $now, $now]);
        return $id;
    }
    sl_db()->prepare('UPDATE brands SET name=?, config=?, updated_at=? WHERE id=?')
        ->execute([$brand['name'], $config, $now, $id]);
    if (sl_brand($id) === null) throw new InvalidArgumentException('Marke nicht gefunden.');
    return $id;
}

function sl_set_default_brand(string $id): void
{
    $settings = sl_settings();
    if ((string) ($settings['brand_id'] ?? '') === $id) return;
    $settings['brand_id'] = $id;
    sl_db()->prepare('INSERT INTO settings(id,config) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET config=excluded.config')
        ->execute([json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
}

function sl_brand_delete(string $id): void
{
    sl_db()->prepare('DELETE FROM brands WHERE id=?')->execute([$id]);
    // Links keep their resolved snapshot; only the default reference is cleared.
    if ((string) (sl_settings()['brand_id'] ?? '') === $id) sl_set_default_brand('');
}

// Links follow their brand live. A link with its own changes keeps them as an exception.
function sl_effective(array $link): array
{
    if (($link['brand_id'] ?? '') === '' || !empty($link['override'])) return $link;
    $brand = sl_brand((string) $link['brand_id']);
    if ($brand === null) return $link;
    foreach (SL_PRESENTATION as $key) $link[$key] = $brand[$key];
    return $link;
}

function sl_presentation_differs(array $left, array $right): bool
{
    foreach (SL_PRESENTATION as $key) {
        if ((string) ($left[$key] ?? '') !== (string) ($right[$key] ?? '')) return true;
    }
    return false;
}

function sl_new_link(): array
{
    $config = sl_defaults();
    $brand = sl_brand((string) (sl_settings()['brand_id'] ?? ''));
    if ($brand === null) {
        $rows = sl_brand_rows();
        $brand = $rows ? sl_brand((string) $rows[0]['id']) : null;
    }
    if ($brand !== null) {
        foreach (array_merge(SL_PRESENTATION, ['utm', 'forward_utm']) as $key) $config[$key] = $brand[$key];
        $config['brand_id'] = $brand['id'];
    }
    return $config;
}

function sl_target(array $config, array $incoming = []): string
{
    $tags = [];
    foreach (SL_UTM as $key) {
        $value = $config['utm'][$key] ?? '';
        if ($value === '' && $config['forward_utm'] && isset($incoming[$key]) && is_string($incoming[$key])
            && strlen($incoming[$key]) <= 500 && preg_match('//u', $incoming[$key])
            && !preg_match('/[\x00-\x1f\x7f]/', $incoming[$key])) {
            $value = trim($incoming[$key]);
        }
        if ($value !== '') $tags[$key] = $value;
    }
    if (!$tags) return $config['url'];
    // Preserve unrelated query parameters byte-for-byte, including duplicates and signatures.
    [$base, $fragment] = array_pad(explode('#', $config['url'], 2), 2, null);
    [$path, $query] = array_pad(explode('?', $base, 2), 2, '');
    $pairs = $query === '' ? [] : explode('&', $query);
    $pairs = array_values(array_filter($pairs, static function ($pair) use ($tags) {
        $key = urldecode(explode('=', $pair, 2)[0]);
        return !array_key_exists($key, $tags);
    }));
    foreach ($tags as $key => $value) $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
    return $path . '?' . implode('&', $pairs) . ($fragment !== null ? '#' . $fragment : '');
}

function sl_origin(array $server): string
{
    $configured = getenv('JUSTOPEN_BASE_URL');
    if ($configured) {
        $url = sl_url($configured);
        $parts = parse_url($url);
        if (isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('JUSTOPEN_BASE_URL must be an origin without a path or query.');
        }
        return preg_replace('#^https?://#i', strtolower($parts['scheme']) . '://', rtrim($url, '/'));
    }
    return jo_base_url($server);
}

function sl_handoff(array $config, array $incoming, string $origin): string
{
    $tags = [];
    foreach (SL_UTM as $key) {
        if (isset($incoming[$key]) && is_string($incoming[$key]) && strlen($incoming[$key]) <= 500) $tags[$key] = $incoming[$key];
    }
    return $origin . '/go/' . $config['slug'] . ($tags ? '?' . http_build_query($tags, '', '&', PHP_QUERY_RFC3986) : '');
}

function sl_credential(): string
{
    $hash = getenv('JUSTOPEN_ADMIN_PASSWORD_HASH') ?: '';
    if ($hash !== '') return $hash;
    $password = getenv('JUSTOPEN_ADMIN_PASSWORD') ?: '';
    // A deployment secret is required; there is no public first-user registration.
    if (strlen($password) < 12) return '';
    return $password;
}

function sl_session(array $server): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $dir = sl_data_dir() . '/sessions';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Cannot create session directory.');
    session_save_path($dir);
    session_name('justopen_admin');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '3600');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/admin',
        'secure' => strpos(sl_origin($server), 'https://') === 0,
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function sl_authenticated(): bool
{
    $ok = isset($_SESSION['auth'], $_SESSION['last_seen'])
        && hash_equals(hash('sha256', sl_credential()), (string) $_SESSION['auth'])
        && sl_credential() !== '' && time() - (int) $_SESSION['last_seen'] < 3600;
    if ($ok) $_SESSION['last_seen'] = time();
    else unset($_SESSION['auth']);
    return $ok;
}

function sl_csrf(array $post): bool
{
    return isset($post['csrf']) && is_string($post['csrf']) && hash_equals($_SESSION['csrf'], $post['csrf']);
}

function sl_login(string $password, string $address): bool
{
    $client = hash('sha256', $address . sl_credential());
    $db = sl_db();
    // Serialize check/increment so parallel attempts cannot bypass the limit.
    $db->exec('BEGIN IMMEDIATE');
    try {
        $db->prepare('DELETE FROM login_attempts WHERE expires < ?')->execute([time()]);
        $q = $db->prepare('SELECT failures FROM login_attempts WHERE client=?'); $q->execute([$client]);
        if ((int) $q->fetchColumn() >= 5) { $db->exec('COMMIT'); return false; }
        $credential = sl_credential();
        $valid = $credential !== '' && strlen($password) <= 4096 && (getenv('JUSTOPEN_ADMIN_PASSWORD_HASH')
            ? password_verify($password, $credential) : hash_equals($credential, $password));
        if (!$valid) {
            $q = $db->prepare('INSERT INTO login_attempts(client,failures,expires) VALUES(?,1,?)
                ON CONFLICT(client) DO UPDATE SET failures=failures+1');
            $q->execute([$client, time() + 900]);
        } else $db->prepare('DELETE FROM login_attempts WHERE client=?')->execute([$client]);
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    if (!$valid) return false;
    session_regenerate_id(true);
    $_SESSION['auth'] = hash('sha256', $credential);
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}

function sl_headers(string $nonce): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'nonce-$nonce'; img-src 'self' https:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
}

function sl_render(string $template, array $view = [], int $status = 200): void
{
    $nonce = bin2hex(random_bytes(16));
    http_response_code($status);
    sl_headers($nonce);
    extract($view, EXTR_SKIP);
    require __DIR__ . '/templates/smart-' . $template . '.php';
}

function sl_redirect(string $path): void
{
    header('Cache-Control: no-store');
    header('Location: ' . $path, true, 303);
}

function sl_dispatch(array $server, array $get, array $post): bool
{
    $path = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
    $public = preg_match('#^/(l|go)/([a-z0-9][a-z0-9_-]{2,63})/?$#D', $path, $match);
    $admin = in_array($path, ['/admin', '/admin/', '/admin/preview'], true);
    if (!$public && !$admin && $path !== '/healthz' && !($path === '/' && in_array($method, ['GET', 'HEAD'], true))) return false;
    try {
        if ($path === '/admin/preview' && $method !== 'POST') {
            header('Allow: POST');
            sl_render('message', ['title' => 'Vorschau bitte über den Editor öffnen', 'message' => 'Die Vorschau benötigt die Eingaben aus der Verwaltung.'], 405); return true;
        }
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true) || (!$admin && $method === 'POST')) {
            header('Allow: ' . ($admin ? 'GET, HEAD, POST' : 'GET, HEAD'));
            sl_render('message', ['title' => 'Methode nicht erlaubt', 'message' => 'Bitte den Link normal öffnen.'], 405); return true;
        }
        if ($path === '/healthz') {
            sl_db()->query('SELECT 1')->fetchColumn();
            header('Content-Type: application/json'); header('Cache-Control: no-store'); echo '{"ok":true}'; return true;
        }
        $origin = sl_origin($server);
        if ($path === '/') { sl_redirect('/admin'); return true; }
        if ($public) {
            $config = sl_link($match[2]);
            if (!$config || !$config['active']) {
                sl_render('message', ['title' => 'Link nicht verfügbar', 'message' => 'Dieser Link existiert nicht oder wurde deaktiviert.'], $config ? 410 : 404); return true;
            }
            // Tracking stays per link; the look is resolved from the brand unless the link has exceptions.
            $target = sl_target($config, $get);
            $config = sl_effective($config);
            if ($match[1] === 'go') {
                sl_headers(bin2hex(random_bytes(16)));
                header('Location: ' . $target, true, 302); return true;
            }
            sl_render('landing', ['config' => $config, 'target' => $target, 'handoff' => sl_handoff($config, $get, $origin), 'preview' => false]); return true;
        }
        if (sl_credential() === '') {
            sl_render('message', ['title' => 'Adminzugang noch nicht eingerichtet',
                'message' => 'Setze JUSTOPEN_ADMIN_PASSWORD (mindestens 12 Zeichen) oder JUSTOPEN_ADMIN_PASSWORD_HASH in der Serverkonfiguration.'], 503); return true;
        }
        sl_session($server);
        $authenticated = sl_authenticated();
        $error = ''; $status = 200; $applied = '';
        if ($method === 'POST' && !sl_csrf($post)) {
            sl_render('message', ['title' => 'Sitzung abgelaufen', 'message' => 'Öffne die Verwaltung erneut und wiederhole die Aktion.'], 403); return true;
        }
        if (!$authenticated) {
            if ($method === 'POST') {
                if (sl_login(is_string($post['password'] ?? null) ? $post['password'] : '', $server['REMOTE_ADDR'] ?? 'local')) {
                    sl_redirect('/admin'); return true;
                }
                $error = 'Anmeldung fehlgeschlagen. Nach fünf Versuchen bitte 15 Minuten warten.'; $status = 401;
            }
            sl_render('login', ['error' => $error], $status); return true;
        }
        $brands = sl_brand_rows();
        $settings = sl_settings();
        $view = is_string($get['view'] ?? null) ? $get['view'] : '';
        // The old separate preset pages now live in the brand manager.
        if ($view === 'defaults' || $view === 'layouts') { sl_redirect('/admin?view=brands'); return true; }

        // Brands bundle mark, texts, structure, colors, buttons, delay and tracking defaults.
        if ($view === 'brands') {
            $brandId = is_string($get['edit'] ?? null) ? $get['edit'] : '';
            $draft = null; $brandError = ''; $brandStatus = 200;
            if ($method === 'POST') {
                $action = (string) ($post['action'] ?? '');
                $brandId = is_string($post['brand_id'] ?? null) ? $post['brand_id'] : '';
                try {
                    if ($action === 'brand_delete') {
                        if ($brandId === '') throw new InvalidArgumentException('Marke nicht gefunden.');
                        sl_brand_delete($brandId);
                        sl_redirect('/admin?view=brands&deleted=1'); return true;
                    }
                    if ($action === 'brand_copy') {
                        $source = sl_brand($brandId);
                        if ($source === null) throw new InvalidArgumentException('Marke nicht gefunden.');
                        $copy = $source;
                        $copy['name'] = strlen($source['name'] . ' (Kopie)') <= 80 ? $source['name'] . ' (Kopie)' : 'Kopie';
                        $newId = sl_brand_save($copy);
                        sl_redirect('/admin?view=brands&edit=' . rawurlencode($newId) . '&copied=1'); return true;
                    }
                    if ($action === 'brand_default') {
                        if (sl_brand($brandId) === null) throw new InvalidArgumentException('Marke nicht gefunden.');
                        sl_set_default_brand($brandId);
                        sl_redirect('/admin?view=brands&edit=' . rawurlencode($brandId) . '&default=1'); return true;
                    }
                    if ($action !== 'brand_save') throw new InvalidArgumentException('Unbekannte Aktion.');
                    $savedId = sl_brand_save(sl_brand_config($post), $brandId);
                    if (isset($post['default'])) sl_set_default_brand($savedId);
                    elseif ((string) ($settings['brand_id'] ?? '') === $savedId) sl_set_default_brand('');
                    sl_redirect('/admin?view=brands&edit=' . rawurlencode($savedId) . '&saved=1'); return true;
                } catch (InvalidArgumentException $e) {
                    $brandError = $e->getMessage(); $brandStatus = 422;
                    $draft = array_replace(sl_brand_defaults(), array_intersect_key($post, sl_brand_defaults()));
                    $draft['name'] = is_string($post['name'] ?? null) ? substr($post['name'], 0, 80) : '';
                    $draft['id'] = $brandId;
                }
            }
            if ($draft === null) {
                if ($brandId !== '') {
                    $draft = sl_brand($brandId);
                    if ($draft === null) {
                        sl_render('message', ['title' => 'Marke nicht gefunden', 'message' => 'Bitte eine vorhandene Marke auswählen.'], 404); return true;
                    }
                } else {
                    $draft = ['id' => '', 'name' => ''] + sl_brand_defaults();
                }
            }
            sl_render('brands', ['brands' => $brands, 'brand' => $draft, 'origin' => $origin,
                'defaultBrandId' => (string) ($settings['brand_id'] ?? ''), 'error' => $brandError,
                'saved' => isset($get['saved']), 'copied' => isset($get['copied']),
                'deleted' => isset($get['deleted']), 'defaulted' => isset($get['default'])], $brandStatus);
            return true;
        }

        $edit = is_string($get['edit'] ?? null) ? $get['edit'] : '';
        $config = $edit !== '' ? sl_link($edit) : sl_new_link();
        if ($config === null) {
            sl_render('message', ['title' => 'Link nicht gefunden', 'message' => 'Bitte einen vorhandenen Link auswählen.'], 404); return true;
        }
        // Show the live brand values; a link with exceptions shows its own.
        if ($edit !== '') $config = sl_effective($config);
        if ($edit === '') { $config['slug'] = ''; $config['url'] = ''; $config['active'] = true; }
        if ($method === 'POST') {
            $action = $post['action'] ?? '';
            if ($action === 'logout') {
                $_SESSION = []; session_destroy();
                setcookie('justopen_admin', '', ['expires' => 1, 'path' => '/admin', 'secure' => strpos($origin, 'https://') === 0, 'httponly' => true, 'samesite' => 'Strict']);
                sl_redirect('/admin'); return true;
            }
            $edit = is_string($post['original_slug'] ?? null) ? $post['original_slug'] : '';
            if ($action === 'delete') {
                if ($edit === '' || sl_link($edit) === null) {
                    sl_render('message', ['title' => 'Link nicht gefunden', 'message' => 'Bitte einen vorhandenen Link auswählen.'], 404); return true;
                }
                sl_delete_link($edit);
                sl_redirect('/admin?deleted=1'); return true;
            }
            try {
                if (!in_array($action, ['save', 'preview', 'apply_brand'], true)) throw new InvalidArgumentException('Unbekannte Aktion.');
                if ($action === 'apply_brand') {
                    $preset = sl_brand(is_string($post['brand_id'] ?? null) ? $post['brand_id'] : '');
                    if ($preset === null) throw new InvalidArgumentException('Marke nicht gefunden. Bitte erneut auswählen.');
                    foreach (SL_PRESENTATION as $key) $post[$key] = $preset[$key];
                    $post['brand_id'] = $preset['id'];
                }
                $config = sl_config($post);
                $preset = $config['brand_id'] !== '' ? sl_brand($config['brand_id']) : null;
                $config['override'] = $preset !== null && sl_presentation_differs($config, $preset);
                if ($path === '/admin/preview' || $action === 'preview') {
                    sl_render('landing', ['config' => $config, 'target' => sl_target($config), 'handoff' => '', 'preview' => true]); return true;
                }
                if ($edit !== '' && ($edit !== $config['slug'] || sl_link($edit) === null)) {
                    throw new InvalidArgumentException('Der Kurzname eines bestehenden Links kann nicht geändert werden.');
                }
                if ($action === 'apply_brand') {
                    // Show the applied preset first; only saving persists it.
                    $applied = 'brand';
                    $config = sl_effective($config);
                } else {
                    sl_save($config, $edit !== '');
                    sl_redirect('/admin?edit=' . rawurlencode($config['slug']) . '&saved=1'); return true;
                }
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage(); $status = 422;
                // Keep user input after validation errors; templates escape every value.
                $config = array_replace(sl_defaults(), array_intersect_key($post, sl_defaults()));
                $config['utm'] = array_fill_keys(SL_UTM, '');
                foreach (SL_UTM as $key) $config['utm'][$key] = is_string($post[$key] ?? null) ? $post[$key] : '';
                foreach ($config as $key => $value) if (is_array($value) && $key !== 'utm') $config[$key] = '';
                $config['active'] = isset($post['active']); $config['forward_utm'] = isset($post['forward_utm']);
                $config['override'] = false;
            }
        }
        $links = sl_db()->query('SELECT slug,config,updated_at FROM links ORDER BY updated_at DESC, slug')->fetchAll();
        sl_render('admin', compact('config', 'brands', 'links', 'edit', 'error', 'origin', 'applied')
            + ['saved' => isset($get['saved']), 'deleted' => isset($get['deleted']), 'defaultBrandId' => (string) ($settings['brand_id'] ?? '')], $status);
    } catch (Throwable $e) {
        error_log('justopen: ' . $e->getMessage());
        sl_render('message', ['title' => 'Kurz nicht erreichbar', 'message' => 'Bitte später erneut versuchen. Die Serverkonfiguration muss geprüft werden.'], 503);
    }
    return true;
}
