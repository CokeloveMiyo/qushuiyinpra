<?php
declare(strict_types=1);
/**
 * 媒体代理（流式）：解决抖音/小红书/B站 CDN 防盗链，避免整文件进内存
 */
require __DIR__ . '/lib/bootstrap.php';
require_login();

$raw = (string)($_GET['u'] ?? '');
if ($raw === '') {
    http_response_code(400);
    exit('missing u');
}

$padded = strtr($raw, '-_', '+/');
$pad = strlen($padded) % 4;
if ($pad > 0) {
    $padded .= str_repeat('=', 4 - $pad);
}
$url = base64_decode($padded, true);
if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    exit('bad url');
}

$host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
$allowSuffixes = [
    'douyinvod.com', 'douyin.com', 'iesdouyin.com', 'snssdk.com',
    'byteicdn.com', 'byteimg.com', 'ibyteimg.com', 'douyinpic.com',
    'bilivideo.com', 'bilibili.com', 'akamaized.net',
    'xhscdn.com', 'xiaohongshu.com',
];
$okHost = false;
foreach ($allowSuffixes as $suf) {
    if ($host === $suf || str_ends_with($host, '.' . $suf)) {
        $okHost = true;
        break;
    }
}
if (!$okHost) {
    http_response_code(403);
    exit('host not allowed');
}

$referer = 'https://www.douyin.com/';
if (str_contains($host, 'bili') || str_contains($host, 'akamai')) {
    $referer = 'https://www.bilibili.com/';
} elseif (str_contains($host, 'xhs') || str_contains($host, 'xiaohongshu')) {
    $referer = 'https://www.xiaohongshu.com/';
}

$filename = 'media.bin';
$path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
if (preg_match('/\.(mp4|mov|m4v|webm|jpg|jpeg|png|webp|gif)$/i', $path, $m)) {
    $ext = strtolower($m[1]);
    $filename = 'media.' . ($ext === 'jpeg' ? 'jpg' : $ext);
} elseif (str_contains($url, 'play') || str_contains($url, 'video') || str_contains($url, 'stream')) {
    $filename = 'media.mp4';
} elseif (str_contains($url, 'image') || str_contains($url, 'img') || str_contains($url, 'pic')) {
    $filename = 'media.jpg';
}

$reqHeaders = [
    'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
    'Referer: ' . $referer,
    'Accept: */*',
];
if (!empty($_SERVER['HTTP_RANGE'])) {
    $reqHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

$headersSent = false;
$statusSet = false;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_HTTPHEADER => $reqHeaders,
    CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headersSent, &$statusSet, $filename) {
        $len = strlen($line);
        $trim = trim($line);
        if ($trim === '') {
            return $len;
        }
        if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d+)#i', $trim, $m)) {
            http_response_code((int)$m[1]);
            $statusSet = true;
            return $len;
        }
        $lower = strtolower($trim);
        if (str_starts_with($lower, 'content-type:')
            || str_starts_with($lower, 'content-length:')
            || str_starts_with($lower, 'content-range:')
            || str_starts_with($lower, 'accept-ranges:')) {
            header($trim, true);
        }
        return $len;
    },
    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$headersSent, &$statusSet, $filename) {
        if (!$headersSent) {
            if (!$statusSet) {
                http_response_code(200);
            }
            $disp = isset($_GET['dl']) ? 'attachment' : 'inline';
            header('Content-Disposition: ' . $disp . '; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=1800');
            $headersSent = true;
        }
        echo $chunk;
        if (function_exists('fastcgi_finish_request') === false) {
            flush();
        }
        return strlen($chunk);
    },
]);

$ok = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($ok === false) {
    if (!$headersSent) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'proxy failed: ' . $err;
    }
}
