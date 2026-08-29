<?php
declare(strict_types=1);
/**
 * 统一解析入口：自动识别抖音 / B站 / 小红书
 * 解析逻辑来自 jiuhunwl/short_videos（二改封装）
 */
require __DIR__ . '/lib/bootstrap.php';
require_login();

header('Access-Control-Allow-Origin: *');

$text = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    $text = (string)($json['url'] ?? $_POST['url'] ?? '');
} else {
    $text = (string)($_GET['url'] ?? '');
}

$url = extract_url_from_text($text) ?? '';
if ($url === '') {
    json_out(['code' => 400, 'msg' => '请粘贴包含视频链接的文本', 'platform' => null, 'data' => null], 400);
}

$platform = detect_platform($url);
if (!$platform) {
    json_out(['code' => 400, 'msg' => '仅支持抖音 / 哔哩哔哩 / 小红书链接', 'platform' => null, 'data' => null], 400);
}

$cfg = app_config();

try {
    if ($platform === 'douyin') {
        require_once __DIR__ . '/lib/douyin_share.php';
        // 优先无 Cookie 分享页（对短链更稳），失败再走 Cookie 解析器
        $result = douyin_share_parse($url);
        $ok = ((int)($result['code'] ?? 0) === 200);
        if (!$ok) {
            require_once __DIR__ . '/api/douyin/DouyinParser.php';
            $parser = new DouyinParser();
            $parser->setCookie((string)$cfg['douyin_cookie']);
            $raw = $parser->parse($url);
            $fallback = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($fallback) && (int)($fallback['code'] ?? 0) === 200) {
                $result = $fallback;
                $ok = true;
            } elseif (is_array($fallback) && !$ok) {
                $result = $fallback;
            }
        }
        if (!is_array($result)) {
            throw new RuntimeException('抖音解析返回异常');
        }
        $data = $ok ? normalize_media_data($result['data'] ?? null) : null;
        if ($ok && empty($data['url']) && empty($data['images'])) {
            $ok = false;
            $result['msg'] = '解析成功但未拿到可下载地址';
        }
        log_parse('douyin', $url, $ok, (string)($result['msg'] ?? ''));
        json_out([
            'code' => $ok ? 200 : 400,
            'msg' => $result['msg'] ?? ($ok ? '解析成功' : '解析失败'),
            'platform' => 'douyin',
            'data' => $data,
        ], $ok ? 200 : 400);
    }

    if ($platform === 'xhs') {
        require_once __DIR__ . '/api/xiaohongshu/XiaohongshuParser.php';
        $parser = new XiaohongshuParser();
        $parser->setCookie((string)$cfg['xhs_cookie']);
        $raw = $parser->parse($url);
        $result = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($result)) {
            throw new RuntimeException('小红书解析返回异常');
        }
        $ok = ((int)($result['code'] ?? 0) === 200);
        $data = $ok ? normalize_media_data($result['data'] ?? null) : null;
        if ($ok && empty($data['url']) && empty($data['images'])) {
            $ok = false;
            $result['msg'] = '解析成功但未拿到可下载地址，可在 config.php 配置 xhs_cookie';
        }
        log_parse('xhs', $url, $ok, (string)($result['msg'] ?? ''));
        json_out([
            'code' => $ok ? 200 : 400,
            'msg' => $result['msg'] ?? ($ok ? '解析成功' : '解析失败'),
            'platform' => 'xhs',
            'data' => $data,
        ], $ok ? 200 : 400);
    }

    if ($platform === 'bilibili') {
        // 复用 bilibili.php 逻辑：通过输出缓冲捕获其 echo
        $_GET['url'] = $url;
        // 临时写入 cookie 到全局期望：改写 bilibili 使用配置 cookie
        $GLOBALS['XIAOLUO_BILI_COOKIE'] = (string)$cfg['bilibili_cookie'];
        ob_start();
        // 打补丁：include 前替换 cookie 变量不可行，用包装执行
        include_bilibili_parse($url, (string)$cfg['bilibili_cookie']);
        $out = ob_get_clean();
        $result = json_decode($out ?: '', true);
        if (!is_array($result)) {
            log_parse('bilibili', $url, false, '返回非 JSON');
            json_out(['code' => 500, 'msg' => 'B站解析返回异常', 'platform' => 'bilibili', 'data' => null], 500);
        }
        $ok = ((int)($result['code'] ?? 0) === 200 || (int)($result['code'] ?? 0) === 1);
        log_parse('bilibili', $url, $ok, (string)($result['msg'] ?? ''));
        $data = $ok ? normalize_media_data(normalize_bilibili($result)) : null;
        if ($ok && empty($data['url']) && empty($data['videos'])) {
            $ok = false;
            $result['msg'] = '解析成功但未拿到视频地址';
        }
        json_out([
            'code' => $ok ? 200 : 400,
            'msg' => $result['msg'] ?? ($ok ? '解析成功' : '解析失败'),
            'platform' => 'bilibili',
            'data' => $data,
        ], $ok ? 200 : 400);
    }
} catch (Throwable $e) {
    log_parse($platform ?? 'unknown', $url, false, $e->getMessage());
    json_out(['code' => 500, 'msg' => '解析异常：' . $e->getMessage(), 'platform' => $platform, 'data' => null], 500);
}

/**
 * 统一前端字段：author 必须是字符串，url/images 可直接用
 */
function normalize_media_data(?array $data): ?array
{
    if ($data === null) {
        return null;
    }
    $author = $data['author'] ?? '';
    if (is_array($author)) {
        $author = (string)($author['name'] ?? $author['nickname'] ?? '');
    }
    $images = $data['images'] ?? [];
    if (!is_array($images)) {
        $images = [];
    }
    // 图集可能是对象列表
    $flatImages = [];
    foreach ($images as $img) {
        if (is_string($img) && $img !== '') {
            $flatImages[] = $img;
        } elseif (is_array($img)) {
            $u = $img['url'] ?? $img['url_default'] ?? ($img['url_list'][0] ?? '');
            if (is_string($u) && $u !== '') {
                $flatImages[] = $u;
            }
        }
    }
    $url = (string)($data['url'] ?? $data['video'] ?? $data['video_url'] ?? '');
    if ($url === '' && !empty($data['videos'][0]['url'])) {
        $url = (string)$data['videos'][0]['url'];
    }
    // 实况 / 动图视频
    $livePhotos = [];
    foreach ((array)($data['live_videos'] ?? []) as $lv) {
        if (is_string($lv) && $lv !== '') {
            $livePhotos[] = $lv;
        }
    }
    if (!empty($data['live_photo']) && is_array($data['live_photo'])) {
        foreach ($data['live_photo'] as $lp) {
            if (!is_array($lp)) {
                continue;
            }
            $lv = (string)($lp['video'] ?? '');
            $li = (string)($lp['image'] ?? '');
            if ($lv !== '' && !in_array($lv, $livePhotos, true)) {
                $livePhotos[] = $lv;
            }
            if ($li !== '' && !in_array($li, $flatImages, true)) {
                $flatImages[] = $li;
            }
        }
    }

    $type = (string)($data['type'] ?? '');
    $awemeType = (int)($data['aweme_type'] ?? 0);
    $isDouyinImageAweme = in_array($awemeType, [1, 2, 68], true);

    if ($type === 'image') {
        // 照片帖：主资源是图；即便有部分实况也只作附加下载
        $url = '';
    } elseif ($type === 'live') {
        // 动图帖：优先展示封面图；动图视频在 live_videos
        $url = '';
        if (!$flatImages && $livePhotos) {
            $url = $livePhotos[0];
        }
    } elseif ($isDouyinImageAweme && $flatImages) {
        $type = ($awemeType === 2) ? 'live' : 'image';
        $url = '';
    } elseif ($type === '' || $type === 'unknown') {
        if ($flatImages && ($url === '' || $isDouyinImageAweme)) {
            $type = ($awemeType === 2) ? 'live' : 'image';
            $url = '';
        } elseif ($url !== '') {
            $type = 'video';
        } elseif ($flatImages) {
            $type = 'image';
        } else {
            $type = 'video';
        }
    }

    return array_merge($data, [
        'author' => (string)$author,
        'title' => (string)($data['title'] ?? $data['desc'] ?? ''),
        'desc' => (string)($data['desc'] ?? $data['title'] ?? ''),
        'cover' => (string)($data['cover'] ?? $data['imgurl'] ?? ''),
        'url' => $url,
        'images' => $flatImages,
        'live_videos' => $livePhotos,
        'type' => $type,
    ]);
}

function normalize_bilibili(array $result): ?array
{
    if (isset($result['data']) && is_array($result['data']) && isset($result['title'])) {
        $videos = [];
        $list = $result['data'];
        // 旧格式 data 为分P数组
        if (isset($list[0]) || $list === []) {
            foreach ($list as $item) {
                if (!is_array($item)) continue;
                $videos[] = [
                    'title' => $item['title'] ?? $item['part'] ?? '分P',
                    'url' => $item['video_url'] ?? $item['url'] ?? '',
                    'duration' => $item['duration'] ?? 0,
                ];
            }
            return [
                'title' => $result['title'] ?? '',
                'desc' => $result['desc'] ?? '',
                'cover' => $result['imgurl'] ?? $result['cover'] ?? '',
                'author' => $result['user']['name'] ?? $result['author'] ?? '',
                'avatar' => $result['user']['user_img'] ?? '',
                'url' => $videos[0]['url'] ?? '',
                'videos' => $videos,
                'type' => 'video',
            ];
        }
        return $result['data'];
    }
    return $result['data'] ?? null;
}

/**
 * 独立实现 B 站解析（基于 short_videos/api/bilibili/bilibili.php，避免污染 $_GET 全局脚本）
 */
function include_bilibili_parse(string $urls, string $cookie): void
{
    $headers = ['Content-type: application/json;charset=UTF-8'];
    $useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.81 Safari/537.36';

    $urls = bili_clean_url($urls);
    $array = parse_url($urls);
    if (empty($array)) {
        echo json_encode(['code' => -1, 'msg' => '视频链接不正确'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $host = $array['host'] ?? '';
    if ($host === 'b23.tv') {
        $header = @get_headers($urls, true);
        $loc = $header['Location'] ?? '';
        $redirectUrl = is_array($loc) ? end($loc) : $loc;
        if (!$redirectUrl) {
            echo json_encode(['code' => -1, 'msg' => '短链跳转失败'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $array = parse_url($redirectUrl);
        $bvid = rtrim((string)($array['path'] ?? ''), '/');
    } elseif (in_array($host, ['www.bilibili.com', 'm.bilibili.com'], true)) {
        $bvid = (string)($array['path'] ?? '');
    } else {
        echo json_encode(['code' => -1, 'msg' => '视频链接好像不太对！'], JSON_UNESCAPED_UNICODE);
        return;
    }

    if (strpos($bvid, '/video/') === false) {
        echo json_encode(['code' => -1, 'msg' => '好像不是视频链接'], JSON_UNESCAPED_UNICODE);
        return;
    }

    if (preg_match('#/(?:video|bilibili)/(BV[A-Za-z0-9]+)#', $bvid, $m)) {
        $bvid = $m[1];
    } else {
        $bvid = trim(str_replace('/video/', '', $bvid), '/');
    }

    $json1 = bili_request('https://api.bilibili.com/x/web-interface/view?bvid=' . $bvid, $headers, $useragent, $cookie);
    $info = json_decode($json1 ?: '', true);
    if (!is_array($info) || ($info['code'] ?? -1) != 0) {
        echo json_encode(['code' => 0, 'msg' => '解析失败！请检查链接或配置 BILIBILI Cookie'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $bilijson = [];
    foreach ($info['data']['pages'] as $page) {
        $play = bili_request(
            'https://api.bilibili.com/x/player/playurl?otype=json&fnver=0&fnval=3&player=3&qn=112&bvid=' . $bvid . '&cid=' . $page['cid'] . '&platform=html5&high_quality=1',
            $headers,
            $useragent,
            $cookie
        );
        $playArr = json_decode($play ?: '', true);
        $videoUrl = $playArr['data']['durl'][0]['url'] ?? '';
        if ($videoUrl) {
            $videoUrl = bili_normalize_cdn($videoUrl);
            $bilijson[] = [
                'title' => $page['part'],
                'duration' => $page['duration'],
                'durationFormat' => gmdate('H:i:s', max(0, (int)$page['duration'] - 1)),
                'accept' => $playArr['data']['accept_description'] ?? [],
                'video_url' => $videoUrl,
            ];
        }
    }

    echo json_encode([
        'code' => 1,
        'msg' => '解析成功！',
        'title' => $info['data']['title'],
        'imgurl' => $info['data']['pic'],
        'desc' => $info['data']['desc'],
        'data' => $bilijson,
        'user' => [
            'name' => $info['data']['owner']['name'] ?? '',
            'user_img' => $info['data']['owner']['face'] ?? '',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function bili_clean_url(string $url): string
{
    $p = parse_url($url);
    if (!$p || empty($p['host'])) return $url;
    $path = rtrim($p['path'] ?? '', '/');
    $scheme = $p['scheme'] ?? 'https';
    return $scheme . '://' . $p['host'] . $path;
}

function bili_normalize_cdn(string $url): string
{
    $p = parse_url($url);
    if (!$p || empty($p['host'])) return $url;
    if (str_ends_with($p['host'], '.akamaized.net')) {
        $p['host'] = 'upos-sz-mirrorbd.bilivideo.com';
        return ($p['scheme'] ?? 'https') . '://' . $p['host'] . ($p['path'] ?? '') . (isset($p['query']) ? '?' . $p['query'] : '');
    }
    return $url;
}

function bili_request(string $url, array $headers, string $ua, string $cookie): string
{
    $ch = curl_init($url);
    $hdr = array_merge($headers, ['User-Agent: ' . $ua]);
    if ($cookie !== '') {
        $hdr[] = 'Cookie: ' . $cookie;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return is_string($res) ? $res : '';
}
