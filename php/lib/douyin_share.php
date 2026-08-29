<?php
/**
 * 抖音解析：短链 → ttwid 二次握手 → 分享页 SSR；失败再走公共 API 兜底
 * 握手逻辑对齐 parse.shenzjd.com（video-unwatermark / iesdouyin share）
 */

function douyin_share_parse(string $url): array
{
    $extract = douyin_resolve($url);
    if (!$extract) {
        return ['code' => 400, 'msg' => '无法解析抖音视频 ID，请用 App 分享的完整链接', 'data' => null];
    }
    if (($extract['type'] ?? '') === 'user') {
        return ['code' => 400, 'msg' => '这是用户主页链接，请打开具体视频再分享', 'data' => null];
    }

    $id = $extract['id'];
    $redirectUrl = $extract['redirect'] ?? '';
    $ttwid = $extract['ttwid'] ?? '';

    $sharePath = (($extract['type'] ?? 'video') === 'note') ? 'note' : 'video';
    $shareUrl = $redirectUrl;
    if ($shareUrl === '' || str_contains($shareUrl, 'www.douyin.com')) {
        $q = '';
        if ($shareUrl && str_contains($shareUrl, '?')) {
            $q = substr($shareUrl, strpos($shareUrl, '?'));
        }
        $shareUrl = "https://www.iesdouyin.com/share/{$sharePath}/{$id}{$q}";
    } elseif (str_contains($shareUrl, 'iesdouyin.com') === false && str_contains($shareUrl, 'm.douyin.com') === false) {
        $shareUrl = "https://www.iesdouyin.com/share/{$sharePath}/{$id}";
    }

    $uaSets = [
        [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 aweme/32.7.0 NetType/WIFI Channel/App Store',
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: zh-CN,zh;q=0.9',
        ],
        [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9',
        ],
        [
            'User-Agent: Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9',
        ],
    ];

    $tryUrls = array_values(array_unique([
        $shareUrl,
        "https://www.iesdouyin.com/share/{$sharePath}/{$id}",
        "https://www.iesdouyin.com/share/note/{$id}",
        "https://www.iesdouyin.com/share/video/{$id}",
        "https://m.douyin.com/share/{$sharePath}/{$id}",
        "https://m.douyin.com/share/note/{$id}",
        "https://www.douyin.com/video/{$id}",
        "https://www.douyin.com/note/{$id}",
    ]));

    $cfgCookie = '';
    if (function_exists('app_config')) {
        $cfgCookie = (string)(app_config()['douyin_cookie'] ?? '');
    }

    $start = time();
    $parsed = null;
    for ($round = 0; $round < 2 && !$parsed; $round++) {
        foreach ($uaSets as $uaHeaders) {
            foreach ($tryUrls as $fetchUrl) {
                if ($parsed || (time() - $start) > 22) {
                    break 2;
                }
                $headers = $uaHeaders;
                $cookies = [];
                if ($ttwid !== '' && !str_contains($cfgCookie, 'ttwid=')) {
                    $cookies[] = $ttwid;
                }
                if ($cfgCookie !== '') {
                    $cookies[] = $cfgCookie;
                }
                if ($cookies) {
                    $headers[] = 'Cookie: ' . implode('; ', $cookies);
                }
                $headers[] = 'Referer: https://www.douyin.com/';

                [$html, $newTtwid] = douyin_fetch_html($fetchUrl, $headers);
                if ($newTtwid !== '' && $ttwid === '') {
                    $ttwid = $newTtwid;
                }
                if ($html === '') {
                    continue;
                }
                $router = douyin_parse_embedded($html);
                if (!$router) {
                    continue;
                }
                $item = douyin_item_from_router($router);
                if ($item) {
                    $parsed = douyin_format_item($item);
                    break 2;
                }
            }
        }
    }

    if ($parsed) {
        return ['code' => 200, 'msg' => '解析成功', 'data' => $parsed];
    }

    // 公共 API 兜底
    $fb = douyin_public_fallback($url);
    if ($fb) {
        return ['code' => 200, 'msg' => '解析成功', 'data' => $fb];
    }

    return ['code' => 201, 'msg' => '抖音页面无视频数据（可能被风控）。可在 config.php 配置 douyin_cookie 后重试', 'data' => null];
}

function douyin_resolve(string $url): ?array
{
    // 已含 ID
    if (preg_match('#/(?:video|note|share/video|share/note)/(\d{15,21})#', $url, $m)) {
        $type = str_contains($url, 'note') ? 'note' : 'video';
        return ['id' => $m[1], 'type' => $type, 'redirect' => $url, 'ttwid' => ''];
    }
    if (preg_match('#modal_id=(\d{15,21})#', $url, $m)) {
        return ['id' => $m[1], 'type' => 'video', 'redirect' => $url, 'ttwid' => ''];
    }

    // 短链跟跳，拿 Location + ttwid
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 aweme/32.7.0 NetType/WIFI Channel/App Store',
            'Accept: text/html',
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!is_string($resp)) {
        return null;
    }

    $ttwid = '';
    if (preg_match('/^Set-Cookie:\s*ttwid=([^;]+)/mi', $resp, $cm)) {
        $ttwid = 'ttwid=' . $cm[1];
    }

    $loc = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $resp, $lm)) {
        $loc = trim($lm[1]);
    }

    // 再跟一次（有的短链二次跳转）
    if ($loc !== '' && (str_contains($loc, 'v.douyin.com') || !preg_match('#/\d{15,}#', $loc))) {
        $ch = curl_init($loc);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 aweme/32.7.0',
            ],
        ]);
        if ($ttwid) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 aweme/32.7.0',
                'Cookie: ' . $ttwid,
            ]);
        }
        $resp2 = curl_exec($ch);
        $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if (is_string($resp2) && preg_match('/^Set-Cookie:\s*ttwid=([^;]+)/mi', $resp2, $cm2)) {
            $ttwid = 'ttwid=' . $cm2[1];
        }
        $loc = $final ?: $loc;
    }

    if ($loc === '') {
        // 用 FOLLOW 拿最终 URL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 aweme/32.7.0',
            CURLOPT_HEADER => true,
        ]);
        $resp = curl_exec($ch);
        $loc = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: '';
        curl_close($ch);
        if (is_string($resp) && preg_match('/^Set-Cookie:\s*ttwid=([^;]+)/mi', $resp, $cm)) {
            $ttwid = 'ttwid=' . $cm[1];
        }
    }

    if (preg_match('#/share/user/#', $loc) || (preg_match('#sec_uid=#', $loc) && !preg_match('#/(?:video|note)/#', $loc))) {
        return ['type' => 'user', 'id' => '', 'redirect' => $loc, 'ttwid' => $ttwid];
    }

    if (preg_match('#/(?:video|note|share/video|share/note)/(\d{15,21})#', $loc, $m)) {
        $type = str_contains($loc, 'note') ? 'note' : 'video';
        return ['id' => $m[1], 'type' => $type, 'redirect' => $loc, 'ttwid' => $ttwid];
    }
    if (preg_match('#(\d{17,19})#', $loc, $m)) {
        return ['id' => $m[1], 'type' => 'video', 'redirect' => $loc, 'ttwid' => $ttwid];
    }
    return null;
}

/** @return array{0:string,1:string} html, ttwidCookie */
function douyin_fetch_html(string $url, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_ENCODING => 'gzip,deflate',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!is_string($resp) || $resp === '') {
        return ['', ''];
    }
    $ttwid = '';
    if (preg_match('/^Set-Cookie:\s*ttwid=([^;\r\n]+)/mi', $resp, $m)) {
        $ttwid = 'ttwid=' . $m[1];
    }
    $parts = explode("\r\n\r\n", $resp, 2);
    if (count($parts) < 2) {
        $parts = explode("\n\n", $resp, 2);
    }
    $html = $parts[1] ?? $resp;
    // 处理多次跳转头
    while (stripos($html, 'HTTP/') === 0 && preg_match('/\r?\n\r?\n/', $html)) {
        $html = preg_split('/\r?\n\r?\n/', $html, 2)[1] ?? $html;
    }
    return [$html, $ttwid];
}

function douyin_parse_embedded(string $html): ?array
{
    if (preg_match('/window\._ROUTER_DATA\s*=\s*(.*?)<\/script>/s', $html, $m)) {
        $j = json_decode(trim($m[1]), true);
        if (is_array($j)) {
            return $j;
        }
    }
    if (preg_match('/<script[^>]+id=["\']RENDER_DATA["\'][^>]*>(.*?)<\/script>/s', $html, $m)) {
        $j = json_decode(urldecode(trim($m[1])), true);
        if (is_array($j)) {
            return $j;
        }
    }
    return null;
}

function douyin_item_from_router(array $router): ?array
{
    $ld = $router['loaderData'] ?? null;
    if (!is_array($ld)) {
        // RENDER_DATA 可能是 app.videoDetail
        if (isset($router['app']['videoDetail']) && is_array($router['app']['videoDetail'])) {
            return douyin_adapt_video_detail($router['app']['videoDetail']);
        }
        return null;
    }
    foreach (['video_(id)/page', 'note_(id)/page', 'story_(id)/page'] as $key) {
        $item = $ld[$key]['videoInfoRes']['item_list'][0] ?? null;
        if (is_array($item)) {
            return $item;
        }
    }
    foreach ($ld as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        $item = $value['videoInfoRes']['item_list'][0] ?? null;
        if (is_array($item)) {
            return $item;
        }
    }
    return null;
}

function douyin_adapt_video_detail(array $d): array
{
    // 把 RENDER_DATA 的 videoDetail 转成 item_list 风格
    return [
        'author' => [
            'nickname' => $d['authorInfo']['nickname'] ?? '',
            'unique_id' => $d['authorInfo']['uid'] ?? '',
            'avatar_medium' => ['url_list' => [$d['authorInfo']['avatarUri'] ?? '']],
        ],
        'desc' => $d['desc'] ?? '',
        'video' => [
            'play_addr' => [
                'url_list' => array_filter([(string)($d['video']['playApi'] ?? '')]),
                'uri' => $d['video']['uri'] ?? '',
            ],
            'cover' => ['url_list' => [is_string($d['video']['cover'] ?? null) ? $d['video']['cover'] : '']],
            'duration' => $d['video']['duration'] ?? 0,
        ],
        'images' => $d['images'] ?? [],
        'aweme_type' => empty($d['video']['playApi']) && !empty($d['images']) ? 1 : 0,
    ];
}

function douyin_format_item(array $item): ?array
{
    $play = $item['video']['play_addr'] ?? [];
    $urlList = $play['url_list'] ?? [];
    $uri = (string)($play['uri'] ?? '');
    $duration = (int)($item['video']['duration'] ?? 0);

    // 优先用 video_id 构造官方播放地址（比 playwm→play 更稳）
    $videoUrl = '';
    if ($uri !== '' && !str_starts_with($uri, 'http')) {
        $videoUrl = 'https://www.iesdouyin.com/aweme/v1/play/?video_id=' . rawurlencode($uri) . '&ratio=1080p&line=0';
    } elseif (!empty($urlList[0]) && is_string($urlList[0])) {
        $videoUrl = preg_replace('#/(playwm|play_wm)/#', '/play/', $urlList[0]) ?? $urlList[0];
    }

    // 再从 bit_rate 里找更高清直链
    $bitRates = $item['video']['bit_rate'] ?? $item['video']['bitRateList'] ?? [];
    if (is_array($bitRates) && $bitRates) {
        foreach ($bitRates as $br) {
            $candidates = $br['play_addr']['url_list'] ?? [];
            if (!$candidates && !empty($br['playAddr']) && is_array($br['playAddr'])) {
                foreach ($br['playAddr'] as $pa) {
                    if (!empty($pa['src'])) {
                        $candidates[] = $pa['src'];
                    }
                }
            }
            foreach ($candidates as $c) {
                if (!is_string($c) || $c === '') {
                    continue;
                }
                $c = preg_replace('#/(playwm|play_wm)/#', '/play/', $c) ?? $c;
                if ($videoUrl === '' || str_contains($c, 'v3-web') || str_contains($c, 'douyinvod')) {
                    $videoUrl = $c;
                    break 2;
                }
            }
        }
    }

    $images = [];
    $liveVideos = [];
    foreach (($item['images'] ?? []) as $img) {
        if (!is_array($img)) {
            if (is_string($img) && $img !== '') {
                $images[] = $img;
            }
            continue;
        }
        $imgUrl = douyin_pick_image_url($img);
        if ($imgUrl !== '') {
            $images[] = $imgUrl;
        }
        $live = null;
        $liveUri = (string)($img['video']['play_addr']['uri'] ?? '');
        if ($liveUri !== '' && !str_starts_with($liveUri, 'http') && !str_contains($liveUri, '.mp3')) {
            $live = 'https://www.iesdouyin.com/aweme/v1/play/?video_id=' . rawurlencode($liveUri) . '&ratio=1080p&line=0';
        } elseif (!empty($img['video']['play_addr']['url_list'][0])) {
            $live = $img['video']['play_addr']['url_list'][0];
        } elseif (!empty($img['video']['playAddr'][0]['src'])) {
            $live = $img['video']['playAddr'][0]['src'];
        } elseif (!empty($img['video']['playApi'])) {
            $live = $img['video']['playApi'];
        }
        if (is_string($live) && $live !== '') {
            if (str_contains($live, '.mp3') || str_contains($live, 'ies-music')) {
                continue;
            }
            $live = preg_replace('#/(playwm|play_wm)/#', '/play/', $live) ?? $live;
            $liveVideos[] = $live;
        }
    }

    $awemeType = (int)($item['aweme_type'] ?? 0);
    if ($videoUrl !== '' && (str_contains($videoUrl, '.mp3') || str_contains($uri, 'ies-music') || str_contains($uri, '.mp3'))) {
        $videoUrl = '';
    }

    $isImagePost = in_array($awemeType, [1, 2, 68], true) || count($images) > 0;

    if ($isImagePost) {
        if (!$images && !$liveVideos && $videoUrl === '') {
            return null;
        }
        $type = ($awemeType === 2 || $liveVideos) ? 'live' : 'image';
        return [
            'author' => $item['author']['nickname'] ?? '',
            'authorId' => $item['author']['unique_id'] ?? '',
            'avatar' => $item['author']['avatar_medium']['url_list'][0] ?? '',
            'title' => $item['desc'] ?? '',
            'desc' => $item['desc'] ?? '',
            'cover' => $images[0] ?? ($item['video']['cover']['url_list'][0] ?? ''),
            'url' => '',
            'images' => $images,
            'live_videos' => $liveVideos,
            'type' => $type,
            'duration' => $duration ?: null,
            'aweme_type' => $awemeType,
        ];
    }

    if ($videoUrl === '') {
        return null;
    }

    return [
        'author' => $item['author']['nickname'] ?? '',
        'authorId' => $item['author']['unique_id'] ?? '',
        'avatar' => $item['author']['avatar_medium']['url_list'][0] ?? '',
        'title' => $item['desc'] ?? '',
        'desc' => $item['desc'] ?? '',
        'cover' => $item['video']['cover']['url_list'][0] ?? '',
        'url' => $videoUrl,
        'images' => [],
        'live_videos' => [],
        'type' => 'video',
        'duration' => $duration ?: null,
        'aweme_type' => $awemeType,
    ];
}

/** 从图集项里挑无水印图片：url_list（无 water）优先于 download_url_list（常带水印） */
function douyin_pick_image_url(array $img): string
{
    $candidates = [];
    foreach (['url_list', 'urlList', 'download_url_list', 'downloadUrlList'] as $key) {
        if (empty($img[$key]) || !is_array($img[$key])) {
            continue;
        }
        foreach ($img[$key] as $u) {
            if (is_string($u) && str_starts_with($u, 'http')) {
                $candidates[] = $u;
            }
        }
    }
    $candidates = array_values(array_unique($candidates));
    if (!$candidates) {
        return '';
    }
    usort($candidates, function ($a, $b) {
        $score = function ($u) {
            $s = 0;
            $path = strtolower((string)(parse_url($u, PHP_URL_PATH) ?? $u));
            // tplv-*-water 是带作者名水印的下载图，必须避开
            if (str_contains($path, 'water')) {
                $s -= 100;
            } else {
                $s += 30;
            }
            if (preg_match('/\.jpe?g$/i', $path)) {
                $s += 10;
            } elseif (str_contains($path, '.webp')) {
                $s += 3;
            }
            if (str_contains($path, 'origin')) {
                $s += 5;
            }
            return $s;
        };
        return $score($b) <=> $score($a);
    });
    return $candidates[0];
}

function douyin_public_fallback(string $url): ?array
{
    $candidates = [];

    // 1) yujn
    $candidates[] = function () use ($url) {
        $body = douyin_http_get('https://api.yujn.cn/api/dy_jx.php?msg=' . urlencode($url));
        $j = json_decode($body ?: '', true);
        $direct = douyin_first_http($j);
        if (!$direct) {
            return null;
        }
        return [
            'author' => (string)($j['author'] ?? $j['name'] ?? ''),
            'title' => (string)($j['title'] ?? $j['desc'] ?? ''),
            'cover' => (string)($j['cover'] ?? $j['img'] ?? ''),
            'url' => $direct,
            'images' => [],
            'type' => 'video',
        ];
    };

    // 2) tenapi
    $candidates[] = function () use ($url) {
        $ch = curl_init('https://tenapi.cn/v2/video');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['url' => $url]),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $j = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($j)) {
            return null;
        }
        $code = $j['code'] ?? null;
        if (!($code === 200 || $code === 0 || $code === '200' || $code === '0')) {
            return null;
        }
        $data = is_array($j['data'] ?? null) ? $j['data'] : $j;
        $imgs = [];
        foreach ((array)($data['images'] ?? $data['pics'] ?? $data['imglist'] ?? []) as $img) {
            if (is_string($img) && str_starts_with($img, 'http')) {
                $imgs[] = $img;
            } elseif (is_array($img)) {
                $u = $img['url'] ?? $img['url_list'][0] ?? '';
                if (is_string($u) && str_starts_with($u, 'http')) {
                    $imgs[] = $u;
                }
            }
        }
        $direct = douyin_first_http($data);
        if (!$direct && !$imgs) {
            return null;
        }
        if ($imgs) {
            return [
                'author' => (string)($data['author'] ?? ''),
                'title' => (string)($data['title'] ?? $data['desc'] ?? ''),
                'cover' => (string)($data['cover'] ?? $imgs[0]),
                'url' => '',
                'images' => $imgs,
                'live_videos' => [],
                'type' => 'image',
            ];
        }
        return [
            'author' => (string)($data['author'] ?? ''),
            'title' => (string)($data['title'] ?? $data['desc'] ?? ''),
            'cover' => (string)($data['cover'] ?? ''),
            'url' => $direct,
            'images' => [],
            'type' => 'video',
        ];
    };

    // 3) 17change
    $candidates[] = function () use ($url) {
        $bodyArr = ['link' => $url];
        $keys = array_keys($bodyArr);
        sort($keys);
        $joined = '';
        foreach ($keys as $k) {
            $joined .= ($joined === '' ? '' : '&') . $k . '=' . $bodyArr[$k];
        }
        $ts = (string)(int)(microtime(true) * 1000);
        $nonce = substr(bin2hex(random_bytes(6)), 0, 10);
        $raw = $joined . '&Timestamp=' . $ts . '&nonce=' . $nonce . '&url=https://17change.cn';
        $sig = md5($raw);
        $ch = curl_init('https://api4.17change.cn/parse/video');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                'Origin: https://17change.cn',
                'Referer: https://17change.cn/fastools/parsevideo',
                'timestamp: ' . $ts,
                'nonce: ' . $nonce,
                'signature: ' . $sig,
            ],
            CURLOPT_POSTFIELDS => json_encode($bodyArr),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $j = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($j)) {
            return null;
        }
        $code = $j['code'] ?? null;
        if (!($code === 200 || $code === 0 || $code === '200')) {
            return null;
        }
        $data = is_array($j['data'] ?? null) ? $j['data'] : $j;
        $imgs = [];
        foreach ((array)($data['images'] ?? $data['pics'] ?? []) as $img) {
            if (is_string($img) && str_starts_with($img, 'http')) {
                $imgs[] = $img;
            }
        }
        $direct = douyin_first_http($data);
        if (!$direct && !$imgs) {
            return null;
        }
        $author = '';
        if (is_array($data['author'] ?? null)) {
            $author = (string)($data['author']['name'] ?? $data['author']['nickname'] ?? '');
        }
        if ($imgs) {
            return [
                'author' => $author,
                'title' => (string)($data['title'] ?? $data['desc'] ?? ''),
                'cover' => (string)(is_string($data['cover'] ?? null) ? $data['cover'] : $imgs[0]),
                'url' => '',
                'images' => $imgs,
                'type' => 'image',
            ];
        }
        return [
            'author' => $author,
            'title' => (string)($data['title'] ?? $data['desc'] ?? ''),
            'cover' => (string)(is_string($data['cover'] ?? null) ? $data['cover'] : ''),
            'url' => $direct,
            'images' => [],
            'type' => 'video',
        ];
    };

    foreach ($candidates as $fn) {
        try {
            $r = $fn();
            if (is_array($r) && !empty($r['url'])) {
                return $r;
            }
        } catch (Throwable $e) {
            // continue
        }
    }
    return null;
}

function douyin_http_get(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return is_string($res) ? $res : '';
}

function douyin_first_http($value): ?string
{
    if (is_string($value) && str_starts_with($value, 'http')) {
        return $value;
    }
    if (is_array($value)) {
        foreach (['url_list', 'urlList', 'nwm_video_url_hq', 'nwm_video_url', 'play', 'video_url', 'play_video', 'url', 'src'] as $key) {
            if (array_key_exists($key, $value)) {
                $got = douyin_first_http($value[$key]);
                if ($got) {
                    return $got;
                }
            }
        }
        foreach ($value as $item) {
            $got = douyin_first_http($item);
            if ($got) {
                return $got;
            }
        }
    }
    return null;
}

// 兼容旧函数名
function douyin_share_extract_id(string $url): ?string
{
    $r = douyin_resolve($url);
    return $r['id'] ?? null;
}

function douyin_share_curl(string $url, array $header): string|false
{
    [$html] = douyin_fetch_html($url, $header);
    return $html !== '' ? $html : false;
}
