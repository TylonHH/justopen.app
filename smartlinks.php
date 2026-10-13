<?php
declare(strict_types=1);

const SL_UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];

function sl_defaults(): array
{
    return [
        'brand' => 'justopen', 'title' => 'Mein Link',
        'description' => '',
        'primary_label' => 'Im Browser öffnen', 'secondary_label' => 'Hier weiter öffnen',
        'secondary_action' => 'current', 'secondary_url' => '', 'logo_url' => '',
        'background' => '#f4f3ee', 'surface' => '#ffffff', 'text' => '#182b25',
        'accent' => '#176b52', 'accent_text' => '#ffffff', 'shape' => 'rounded',
        'delay' => '', 'forward_utm' => true, 'active' => true,
        'url' => '', 'slug' => '', 'utm' => array_fill_keys(SL_UTM, ''),
    ];
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
    $db->exec('CREATE TABLE IF NOT EXISTS links (slug TEXT PRIMARY KEY, config TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY CHECK(id=1), config TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS login_attempts (client TEXT PRIMARY KEY, failures INTEGER NOT NULL, expires INTEGER NOT NULL);');
    chmod($dir . '/justopen.sqlite', 0600);
    return $db;
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

function sl_config(array $input, bool $defaults = false): array
{
    $config = sl_defaults();
    foreach (['brand', 'title', 'primary_label', 'secondary_label'] as $key) {
        $config[$key] = sl_string($input, $key);
        if ($config[$key] === '') throw new InvalidArgumentException('Bitte alle Texte ausfüllen.');
    }
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
    $config['logo_url'] = sl_string($input, 'logo_url', 8192);
    if ($config['logo_url'] !== '') {
        $config['logo_url'] = sl_url($config['logo_url']);
        if (strtolower(parse_url($config['logo_url'], PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException('Das Logo benötigt eine HTTPS-Adresse.');
        }
    }
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
    $config['forward_utm'] = isset($input['forward_utm']);
    $config['active'] = isset($input['active']);
    if (!$defaults) {
        $config['url'] = sl_url(sl_string($input, 'url', 8192));
        $config['slug'] = sl_string($input, 'slug', 64);
        if ($config['slug'] === '') $config['slug'] = bin2hex(random_bytes(5));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D', $config['slug'])) {
            throw new InvalidArgumentException('Kurzname: 3–64 Zeichen, nur a–z, 0–9, Bindestrich und Unterstrich.');
        }
    }
    foreach (SL_UTM as $key) $config['utm'][$key] = sl_string($input, $key, 500);
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
            $target = sl_target($config, $get);
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
        $error = ''; $status = 200; $applied = false;
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
        $defaults = sl_settings();
        $mode = ($get['view'] ?? '') === 'defaults' ? 'defaults' : 'link';
        $edit = is_string($get['edit'] ?? null) ? $get['edit'] : '';
        $config = $edit !== '' ? sl_link($edit) : $defaults;
        if ($config === null) {
            sl_render('message', ['title' => 'Link nicht gefunden', 'message' => 'Bitte einen vorhandenen Link auswählen.'], 404); return true;
        }
        if ($edit === '') { $config['slug'] = ''; $config['url'] = ''; $config['active'] = true; }
        if ($method === 'POST') {
            $action = $post['action'] ?? '';
            if ($action === 'logout') {
                $_SESSION = []; session_destroy();
                setcookie('justopen_admin', '', ['expires' => 1, 'path' => '/admin', 'secure' => strpos($origin, 'https://') === 0, 'httponly' => true, 'samesite' => 'Strict']);
                sl_redirect('/admin'); return true;
            }
            $mode = $action === 'defaults' ? 'defaults' : 'link';
            $edit = is_string($post['original_slug'] ?? null) ? $post['original_slug'] : '';
            try {
                if (!in_array($action, ['save', 'defaults', 'preview', 'apply_defaults'], true)) throw new InvalidArgumentException('Unbekannte Aktion.');
                if ($action === 'apply_defaults') {
                    foreach (['brand', 'logo_url', 'primary_label', 'secondary_label', 'secondary_action', 'secondary_url',
                        'background', 'surface', 'text', 'accent', 'accent_text', 'shape', 'delay'] as $key) {
                        $post[$key] = $defaults[$key];
                    }
                }
                $config = sl_config($post, $mode === 'defaults');
                if ($path === '/admin/preview' || $action === 'preview') {
                    sl_render('landing', ['config' => $config, 'target' => sl_target($config), 'handoff' => '', 'preview' => true]); return true;
                }
                if ($mode === 'defaults') {
                    sl_db()->prepare('INSERT INTO settings(id,config) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET config=excluded.config')
                        ->execute([json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
                    sl_redirect('/admin?view=defaults&saved=1'); return true;
                }
                if ($edit !== '' && ($edit !== $config['slug'] || sl_link($edit) === null)) {
                    throw new InvalidArgumentException('Der Kurzname eines bestehenden Links kann nicht geändert werden.');
                }
                if ($action === 'apply_defaults') {
                    $applied = true;
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
            }
        }
        $links = sl_db()->query('SELECT slug,config,updated_at FROM links ORDER BY updated_at DESC, slug')->fetchAll();
        sl_render('admin', compact('config', 'defaults', 'links', 'mode', 'edit', 'error', 'origin', 'applied') + ['saved' => isset($get['saved'])], $status);
    } catch (Throwable $e) {
        error_log('justopen: ' . $e->getMessage());
        sl_render('message', ['title' => 'Kurz nicht erreichbar', 'message' => 'Bitte später erneut versuchen. Die Serverkonfiguration muss geprüft werden.'], 503);
    }
    return true;
}
