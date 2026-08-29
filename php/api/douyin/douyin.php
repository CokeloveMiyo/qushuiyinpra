<?php
/**
 * @Author: JH-Ahua
 * @CreateTime: 2026/1/19 下午8:59
 * @email: admin@bugpk.com
 * @blog: www.jiuhunwl.cn
 * @Api: api.bugpk.com
 * @tip: 抖音解析统一接口,支持短视频、图集、实况
 */

require_once 'DouyinParser.php';

header("Access-Control-Allow-Origin: *");
header('Content-type: application/json');

// 获取请求参数
$url = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fullUrl = $_SERVER['REQUEST_URI'];
    $urlParamPos = strpos($fullUrl, 'url=');
    if ($urlParamPos !== false) {
        $encodedUrl = substr($fullUrl, $urlParamPos + 4);
        $url = urldecode($encodedUrl);
    }
} else {
    $url = $_POST['url'] ?? null;
}

if (!$url && isset($_GET['url'])) {
    $url = $_GET['url'];
}

// 配置Cookie (建议定期更新)
$cookie = "";

$parser = new DouyinParser();
$parser->setCookie($cookie);
echo $parser->parse($url);
