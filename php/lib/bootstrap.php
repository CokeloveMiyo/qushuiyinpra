<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_config(): array
{
    static $cfg;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
    }
    return $cfg;
}

function is_logged_in(): bool
{
    $cfg = app_config();
    $token = $_SESSION['site_gate'] ?? '';
    if ($token === '' || empty($_SESSION['site_gate_exp'])) {
        return false;
    }
    if ((int)$_SESSION['site_gate_exp'] < time()) {
        unset($_SESSION['site_gate'], $_SESSION['site_gate_exp']);
        return false;
    }
    $expect = hash_hmac('sha256', 'ok.' . $_SESSION['site_gate_exp'], $cfg['auth_secret']);
    return hash_equals($expect, $token);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function attempt_login(string $password): bool
{
    $cfg = app_config();
    if (!hash_equals((string)$cfg['site_password'], $password)) {
        return false;
    }
    $exp = time() + 7 * 24 * 3600;
    $_SESSION['site_gate_exp'] = $exp;
    $_SESSION['site_gate'] = hash_hmac('sha256', 'ok.' . $exp, $cfg['auth_secret']);
    return true;
}

function logout_user(): void
{
    unset($_SESSION['site_gate'], $_SESSION['site_gate_exp']);
}

function db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    $m = app_config()['mysql'] ?? [];
    if (empty($m['enabled'])) {
        $pdo = null;
        return null;
    }
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $m['host'],
            (int)$m['port'],
            $m['dbname'],
            $m['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $m['user'], $m['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        $pdo = null;
        return null;
    }
}

function log_parse(string $platform, string $url, bool $ok, string $msg = ''): void
{
    $pdo = db();
    if (!$pdo) {
        return;
    }
    try {
        $pdo->prepare(
            'INSERT INTO parse_logs (platform, url, success, message, created_at) VALUES (?,?,?,?,NOW())'
        )->execute([$platform, mb_substr($url, 0, 500), $ok ? 1 : 0, mb_substr($msg, 0, 255)]);
    } catch (Throwable $e) {
        // ignore
    }
}

function extract_url_from_text(string $text): ?string
{
    if (preg_match(
        '#https?://(?:v\.douyin\.com|www\.douyin\.com|www\.iesdouyin\.com|www\.bilibili\.com|b23\.tv|m\.bilibili\.com|www\.xiaohongshu\.com|xhslink\.com|xhslink\.cn)/[^\s\x{4e00}-\x{9fff}]*#u',
        $text,
        $m
    )) {
        return rtrim($m[0], '.,;!?)】》"\'');
    }
    if (preg_match('#https?://[^\s\x{4e00}-\x{9fff}]+#u', $text, $m)) {
        return rtrim($m[0], '.,;!?)】》"\'');
    }
    return null;
}

function detect_platform(string $url): ?string
{
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    if (str_contains($host, 'douyin') || str_contains($host, 'iesdouyin')) {
        return 'douyin';
    }
    if (str_contains($host, 'bilibili') || $host === 'b23.tv') {
        return 'bilibili';
    }
    if (str_contains($host, 'xiaohongshu') || str_contains($host, 'xhslink')) {
        return 'xhs';
    }
    return null;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
