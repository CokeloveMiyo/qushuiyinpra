<?php
/**
 * 小罗斯私人解析平台 - 配置文件
 * 上传虚拟主机后请修改下方密码与可选 MySQL / Cookie
 */
return [
    // 网站访问密码（必填）
    'site_password' => 'changeme',

    // Cookie 签名用密钥（随便一长串）
    'auth_secret' => 'change-this-to-a-long-random-string',

    // 站点信息
    'site_name' => '小罗斯私人解析平台',
    'qq_url' => 'https://qm.qq.com/q/3PZqoU1Tra',

    // 可选：提高解析成功率（在 App/网页登录后复制 Cookie）
    'douyin_cookie' => '',
    'bilibili_cookie' => '',
    'xhs_cookie' => '',

    // 可选 MySQL（不配也能用；配了可记解析日志）
    'mysql' => [
        'enabled' => false,
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'xiaoluo_parse',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
