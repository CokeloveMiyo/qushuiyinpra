# 小罗斯私人解析平台（PHP 虚拟主机版）

适合 **PHP 虚拟主机**（无需 Node / Cloudflare）。演示站：[https://rose.qinrui.p8.ink/](https://rose.qinrui.p8.ink/)

解析能力封装自 [jiuhunwl/short_videos](https://github.com/jiuhunwl/short_videos)；品牌与密码门对齐本仓库 Next.js 二改版。

## 功能

- 全站访问密码 + QQ「联系我」
- 抖音 / 哔哩哔哩 / 小红书：粘贴 → 解析 → 预览
- **PC**：一键下载文件
- **手机**：系统分享 →「存储到照片」保存到相册（图片 / 视频 / 动图）
- HTTPS 下尽量一键粘贴；iOS 不支持静默读剪贴板时弹出粘贴框
- `media_proxy.php` 代理防盗链预览
- MySQL 可选（解析日志）；不配也能用

## 部署

1. 修改 `config.php` 中的 `site_password`、`auth_secret`（可参考 `config.sample.php`）
2. 将本目录（`php/`）全部上传到网站根目录或子目录
3. PHP ≥ 8.1（推荐 8.2+），开启 `curl`、`openssl`、`json`、`mbstring`
4. 开启 HTTPS（手机剪贴板 / 分享存相册需要安全上下文）
5. （可选）导入 `sql/init.sql` 并打开 `mysql.enabled`
6. 打开站点 → 输入密码 → 使用

可选 Cookie：`douyin_cookie` / `bilibili_cookie` / `xhs_cookie`

## 本地预览

```bash
cd php
php -S 127.0.0.1:8080
```

默认密码：`changeme`

## 说明

- 普通虚拟主机即可，不必 Composer
- 平台接口可能变更；失败时可更新 Cookie 或更换出口 IP
- 请遵守平台条款与版权，仅供学习与私人使用
