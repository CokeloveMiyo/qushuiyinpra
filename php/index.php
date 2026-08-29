<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require_login();
$cfg = app_config();
$name = htmlspecialchars($cfg['site_name'], ENT_QUOTES, 'UTF-8');
$qq = htmlspecialchars($cfg['qq_url'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $name ?> - 短视频解析</title>
  <link rel="icon" href="assets/avatar.jpg" />
  <link rel="stylesheet" href="assets/style.css?v=2026082919" />
  <style>
    .row-actions { display: inline-flex; gap: 8px; align-items: center; }
    .btn-clear {
      border: 0 !important; cursor: pointer; border-radius: 10px !important;
      font-weight: 600; padding: 8px 14px; font-size: 13px;
      background: rgba(248,113,113,.15) !important; color: #f87171 !important;
      -webkit-appearance: none; appearance: none;
    }
    .toast-tip { display: inline-block; margin-top: 8px; color: #fbbf24; font-size: 13px; }
    .save-hint { font-size: 12px !important; margin: 6px 0 0; }
    .btn-save {
      flex: 1; text-align: center; border: 0; cursor: pointer;
      color: #fff !important;
      background: linear-gradient(90deg, #22c55e, #16a34a) !important;
      padding: 12px; border-radius: 12px; font-weight: 600; font-size: 14px;
      -webkit-appearance: none; appearance: none; width: 100%;
    }
    .btn-save:disabled { opacity: .6; }
    .album-sheet .sheet-panel { text-align: center; }
    .album-sheet .album-tip { margin: 0 0 16px; font-size: 13px; line-height: 1.6; color: #c4c4d0; }
  </style>
</head>
<body>
  <div class="bg"></div>
  <header class="top">
    <a class="brand" href="index.php">
      <img src="assets/avatar.jpg" alt="" />
      <span><?= $name ?></span>
    </a>
    <a class="nav-mid" href="index.php">视频解析</a>
    <div class="top-right">
      <a class="qq" href="<?= $qq ?>" target="_blank" rel="noopener">
        <img src="assets/qq.svg" alt="" />
        <span>联系我</span>
      </a>
      <a class="logout" href="logout.php">退出登录</a>
    </div>
  </header>

  <div class="wrap">
    <section class="hero">
      <h1><?= $name ?></h1>
      <p class="muted">支持抖音 · 哔哩哔哩 · 小红书 · 粘贴链接即可解析预览下载</p>
      <div class="tags">
        <span>✓ 免安装</span>
        <span>✓ 无水印</span>
        <span>✓ 密码保护</span>
      </div>
    </section>

    <div class="platforms">
      <span class="on">自动识别</span>
      <span>抖音</span>
      <span>哔哩哔哩</span>
      <span>小红书</span>
    </div>

    <div class="card">
      <div class="row">
        <label>视频链接或分享文本</label>
        <div class="row-actions">
          <button type="button" class="btn-ghost" id="btnPaste">粘贴</button>
          <button type="button" class="btn-clear" id="btnClear">清空</button>
        </div>
      </div>
      <textarea id="input" placeholder="粘贴包含视频链接的文本，或点击粘贴按钮..."></textarea>
      <button type="button" class="btn-green btn-wide" id="btnParse">开始解析</button>
      <p class="muted" style="margin-top:10px;text-align:center;font-size:12px">
        聚合解析：粘贴抖音 / B站 / 小红书分享链接，自动识别并去水印
      </p>
    </div>

    <div id="msg"></div>
    <div id="result" class="result"></div>

    <aside class="tip">
      <p class="muted">如果好用，扫码支持一下</p>
      <img src="assets/tip-qr.png" alt="赞赏码" />
    </aside>
  </div>

  <div class="sheet" id="pasteSheet" hidden>
    <div class="sheet-mask" data-close="1"></div>
    <div class="sheet-panel" role="dialog" aria-label="粘贴链接">
      <h3>粘贴分享内容</h3>
      <p class="muted">手机：点下方框 → 选<strong>粘贴</strong><br/>电脑：按 <strong>Ctrl + V</strong>（粘贴后会自动开始解析）</p>
      <textarea id="pasteBox" placeholder="在这里粘贴…"></textarea>
      <div class="sheet-btns">
        <button type="button" class="btn-ghost" id="btnPasteCancel">取消</button>
        <button type="button" class="btn-green" id="btnPasteOk">确认粘贴</button>
      </div>
    </div>
  </div>

  <div class="toast" id="successToast" hidden>
    <div class="toast-mask" data-close="1"></div>
    <div class="toast-card" role="alertdialog" aria-label="解析成功">
      <div class="toast-icon">✓</div>
      <h3>解析成功</h3>
      <p>内容已准备好啦<br/>请往下滑，点击绿色按钮保存<br/><span class="toast-tip">请等视频 / 图片加载出来后再保存</span></p>
      <button type="button" class="btn-green toast-ok" id="btnToastOk">知道了，去保存</button>
    </div>
  </div>

  <!-- 手机端：拉取完成后二次确认，才能用有效手势调起「存储到照片」 -->
  <div class="sheet album-sheet" id="albumSheet" hidden>
    <div class="sheet-mask" data-close="1"></div>
    <div class="sheet-panel" role="dialog" aria-label="保存到相册">
      <h3 id="albumSheetTitle">保存到相册</h3>
      <p class="album-tip" id="albumSheetTip">文件已就绪。请再点一次下方按钮，在系统菜单里选择「存储到照片 / 保存视频」。</p>
      <div class="sheet-btns">
        <button type="button" class="btn-ghost" id="btnAlbumCancel">取消</button>
        <button type="button" class="btn-green" id="btnAlbumShare">保存到相册</button>
      </div>
    </div>
  </div>

  <script>
    const input = document.getElementById('input');
    const msg = document.getElementById('msg');
    const result = document.getElementById('result');
    const btnParse = document.getElementById('btnParse');
    const pasteSheet = document.getElementById('pasteSheet');
    const pasteBox = document.getElementById('pasteBox');
    const successToast = document.getElementById('successToast');
    const albumSheet = document.getElementById('albumSheet');
    let skipNextPasteParse = false;
    let pendingAlbum = null;
    const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '')
      || (navigator.maxTouchPoints > 1 && /Mac|Linux/i.test(navigator.platform || ''));

    function showMsg(text, ok) {
      msg.innerHTML = '<div class="card"><p class="' + (ok ? 'ok' : 'err') + '">' + text + '</p></div>';
    }

    function openPasteSheet() {
      pasteSheet.hidden = false;
      document.body.style.overflow = 'hidden';
      pasteBox.value = '';
      setTimeout(() => {
        pasteBox.focus();
        try { pasteBox.click(); } catch (e) {}
      }, 60);
    }
    function closePasteSheet() {
      pasteSheet.hidden = true;
      document.body.style.overflow = '';
    }
    function applyPastedText(t) {
      const text = (t || '').trim();
      if (!text) {
        showMsg('没有检测到内容，请再试一次', false);
        return false;
      }
      skipNextPasteParse = true;
      input.value = text;
      closePasteSheet();
      msg.innerHTML = '';
      doParse();
      return true;
    }

    function openSuccessToast() {
      successToast.hidden = false;
      document.body.style.overflow = 'hidden';
    }
    function closeSuccessToast() {
      successToast.hidden = true;
      document.body.style.overflow = '';
      const box = document.getElementById('result');
      if (box) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function readClipboardText() {
      if (!window.isSecureContext) return null;
      try {
        if (navigator.permissions && navigator.permissions.query) {
          await navigator.permissions.query({ name: 'clipboard-read' }).catch(function () { return null; });
        }
      } catch (e) {}

      if (navigator.clipboard && navigator.clipboard.readText) {
        try {
          const t = await navigator.clipboard.readText();
          if (t && String(t).trim()) return String(t);
        } catch (e) {}
      }
      if (navigator.clipboard && navigator.clipboard.read) {
        try {
          const items = await navigator.clipboard.read();
          for (let i = 0; i < items.length; i++) {
            const types = items[i].types || [];
            for (let j = 0; j < types.length; j++) {
              if (String(types[j]).indexOf('text') === 0) {
                const blob = await items[i].getType(types[j]);
                const t = await blob.text();
                if (t && t.trim()) return t;
              }
            }
          }
        } catch (e) {}
      }
      if (window.clipboardData && window.clipboardData.getData) {
        try {
          const t = window.clipboardData.getData('Text');
          if (t && String(t).trim()) return String(t);
        } catch (e) {}
      }
      return null;
    }

    function proxyUrl(u, asDownload) {
      if (!u) return '';
      let bin = '';
      const bytes = new TextEncoder().encode(u);
      bytes.forEach(function (b) { bin += String.fromCharCode(b); });
      const b64 = btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
      return 'media_proxy.php?u=' + encodeURIComponent(b64) + (asDownload ? '&dl=1' : '');
    }

    function guessExt(kind, url) {
      if (kind === 'image') {
        if (/\.png(\?|$)/i.test(url)) return 'png';
        if (/\.webp(\?|$)/i.test(url)) return 'webp';
        if (/\.gif(\?|$)/i.test(url)) return 'gif';
        return 'jpg';
      }
      return 'mp4';
    }
    function guessMime(kind, ext) {
      if (kind === 'image') {
        if (ext === 'png') return 'image/png';
        if (ext === 'webp') return 'image/webp';
        if (ext === 'gif') return 'image/gif';
        return 'image/jpeg';
      }
      return 'video/mp4';
    }

    function downloadBlob(blob, fileName) {
      const obj = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = obj;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function () { URL.revokeObjectURL(obj); }, 2000);
    }

    function openAlbumSheet(file, kind) {
      pendingAlbum = { file: file, kind: kind };
      const isVid = kind === 'video';
      document.getElementById('albumSheetTitle').textContent = isVid ? '保存视频到相册' : '保存图片到相册';
      document.getElementById('albumSheetTip').innerHTML = isVid
        ? '视频已就绪。请再点一次下方按钮，在弹出的系统菜单里选择<strong>「存储到照片 / 保存视频」</strong>。'
        : '图片已就绪。请再点一次下方按钮，在弹出的系统菜单里选择<strong>「存储到照片」</strong>。';
      albumSheet.hidden = false;
      document.body.style.overflow = 'hidden';
    }
    function closeAlbumSheet() {
      albumSheet.hidden = true;
      document.body.style.overflow = '';
      pendingAlbum = null;
    }

    async function saveMedia(rawUrl, kind, btn) {
      const label = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = '准备中…'; }
      try {
        const res = await fetch(proxyUrl(rawUrl, false), { credentials: 'same-origin' });
        if (!res.ok) throw new Error('拉取失败 ' + res.status);
        let blob = await res.blob();
        const ext = guessExt(kind, rawUrl);
        let mime = blob.type || guessMime(kind, ext);
        if (!mime || mime === 'application/octet-stream') {
          mime = guessMime(kind, ext);
          blob = new Blob([blob], { type: mime });
        }
        const fileName = (kind === 'image' ? 'xiaoluo_image_' : 'xiaoluo_video_') + Date.now() + '.' + ext;

        // PC：直接下载文件
        if (!isMobile) {
          downloadBlob(blob, fileName);
          showMsg('已开始下载到本地', true);
          return;
        }

        // 手机（图片 / 视频 / 动图）：两步确认后再 share，才能进相册
        const file = new File([blob], fileName, { type: mime });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          openAlbumSheet(file, kind);
          showMsg('文件已就绪，请在弹窗中再点「保存到相册」', true);
          return;
        }

        downloadBlob(blob, fileName);
        showMsg('当前浏览器不支持直接存相册。已开始下载：打开「文件」→ 找到该文件 → 分享 → 存储到照片', true);
      } catch (e) {
        if (e && e.name === 'AbortError') return;
        showMsg('保存失败：' + ((e && e.message) || e) + '。可长按预览图/视频另存', false);
        window.open(proxyUrl(rawUrl, true), '_blank');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = label; }
      }
    }

    function saveBtn(label, rawUrl, kind) {
      return '<button type="button" class="btn-save" data-url="' + attr(rawUrl) + '" data-kind="' + attr(kind) + '">' +
        escapeHtml(label) + '</button>';
    }

    function renderData(platform, data) {
      if (!data) { result.innerHTML = ''; return; }
      const title = data.title || data.desc || '解析结果';
      const author = typeof data.author === 'object' && data.author
        ? (data.author.name || data.author.nickname || '')
        : (data.author || '');
      const cover = data.cover || '';
      const type = data.type || '';
      const typeLabel = ({ video: '视频', image: '照片', live: '动图' })[type] || type;
      let videoUrl = data.url || (data.videos && data.videos[0] && data.videos[0].url) || '';
      if (type === 'image' || type === 'live') videoUrl = '';
      const images = (data.images || []).map(function (u) {
        return typeof u === 'string' ? u : (u && (u.url || u.url_default) || '');
      }).filter(Boolean);
      const liveVideos = (data.live_videos || []).filter(function (u) { return u && u !== videoUrl; });
      let html = '<div class="card">';
      html += '<p class="ok">解析成功 · ' + platform + (typeLabel ? (' · ' + typeLabel) : '') + '</p>';
      html += '<h3 style="margin:8px 0">' + escapeHtml(title) + '</h3>';
      if (author) html += '<p class="muted">作者：' + escapeHtml(author) + '</p>';
      html += '<p class="muted save-hint">' + (isMobile
        ? '提示：请等预览加载完成 → 点「保存到相册」→ 再点弹窗确认 → 系统菜单选「存储到照片」'
        : '提示：请等预览加载完成后再点下载，文件会直接保存到电脑') + '</p>';

      if (images.length) {
        html += images.map(function (u) {
          return '<img class="cover" style="margin-top:8px" src="' + attr(proxyUrl(u, false)) + '" />';
        }).join('');
        html += '<div class="actions">' + images.map(function (u, i) {
          return saveBtn((isMobile ? '保存到相册 ' : '下载图片 ') + (i + 1), u, 'image');
        }).join('') + '</div>';
      }

      if (videoUrl) {
        html += '<video controls playsinline webkit-playsinline style="margin-top:8px" src="' +
          attr(proxyUrl(videoUrl, false)) + '" poster="' + attr(cover ? proxyUrl(cover, false) : '') + '"></video>';
        html += '<div class="actions">' + saveBtn(isMobile ? '保存视频到相册' : '下载视频', videoUrl, 'video') + '</div>';
      }

      if (liveVideos.length) {
        html += '<p class="muted" style="margin-top:12px">实况 / 动图视频：</p>';
        liveVideos.forEach(function (u, i) {
          html += '<video controls playsinline webkit-playsinline style="margin-top:8px" src="' +
            attr(proxyUrl(u, false)) + '"></video>';
          html += '<div class="actions">' +
            saveBtn((isMobile ? '保存动图到相册 ' : '下载动图 ') + (i + 1), u, 'video') +
            '</div>';
        });
      }

      if (!images.length && !videoUrl && !liveVideos.length && cover) {
        html += '<img class="cover" src="' + attr(proxyUrl(cover, false)) + '" />';
        showMsg('已拿到封面，但无直链（可能需 Cookie）', false);
      }

      if (data.videos && data.videos.length > 1) {
        html += '<div style="margin-top:12px">';
        data.videos.forEach(function (v, i) {
          if (!v.url) return;
          html += '<div class="actions" style="margin-top:8px">' +
            saveBtn((isMobile ? '保存分P到相册 ' : '下载分P ') + (i + 1) + '：' + (v.title || ''), v.url, 'video') +
            '</div>';
        });
        html += '</div>';
      }
      html += '</div>';
      result.innerHTML = html;
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
      });
    }
    function attr(s) { return String(s).replace(/"/g, '&quot;'); }

    async function doParse() {
      const text = input.value.trim();
      if (!text) { showMsg('请先粘贴链接', false); return; }
      btnParse.disabled = true;
      btnParse.textContent = '解析中...';
      msg.innerHTML = '';
      result.innerHTML = '';
      try {
        const res = await fetch('parse.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ url: text })
        });
        const data = await res.json();
        if (data.code === 200) {
          showMsg(data.msg || '解析成功', true);
          renderData(data.platform, data.data);
          openSuccessToast();
        } else {
          showMsg(data.msg || '解析失败', false);
        }
      } catch (e) {
        showMsg('网络错误：' + e.message, false);
      } finally {
        btnParse.disabled = false;
        btnParse.textContent = '开始解析';
      }
    }

    document.getElementById('btnPaste').onclick = async function () {
      showMsg('正在读取剪贴板…', true);
      let t = await readClipboardText();
      if (!t) {
        await new Promise(function (r) { setTimeout(r, 120); });
        t = await readClipboardText();
      }
      if (t) {
        applyPastedText(t);
        return;
      }
      // iOS 等系统不允许网页静默读剪贴板：立刻弹出面板，用户点一下「粘贴」即可自动填入
      openPasteSheet();
      showMsg(
        isMobile
          ? '请在弹出框内点一下 → 选「粘贴」，会自动填入并解析（部分手机系统禁止网页直接读剪贴板）'
          : '请允许剪贴板权限，或在弹出框按 Ctrl+V',
        true
      );
    };

    document.getElementById('btnClear').onclick = function () {
      input.value = '';
      msg.innerHTML = '';
      result.innerHTML = '';
      input.focus();
    };

    document.getElementById('btnPasteCancel').onclick = closePasteSheet;
    document.getElementById('btnPasteOk').onclick = function () { applyPastedText(pasteBox.value); };
    pasteSheet.addEventListener('click', function (e) {
      if (e.target && e.target.getAttribute('data-close') === '1') closePasteSheet();
    });
    pasteBox.addEventListener('paste', function (e) {
      const clip = e.clipboardData || window.clipboardData;
      const text = clip ? clip.getData('text') : '';
      if (text) {
        e.preventDefault();
        applyPastedText(text);
      }
    });
    pasteBox.addEventListener('input', function () {
      if (pasteBox.value.trim().length > 8) applyPastedText(pasteBox.value);
    });

    document.getElementById('btnToastOk').onclick = closeSuccessToast;
    successToast.addEventListener('click', function (e) {
      if (e.target && e.target.getAttribute('data-close') === '1') closeSuccessToast();
    });

    document.getElementById('btnAlbumCancel').onclick = closeAlbumSheet;
    albumSheet.addEventListener('click', function (e) {
      if (e.target && e.target.getAttribute('data-close') === '1') closeAlbumSheet();
    });
    document.getElementById('btnAlbumShare').onclick = async function () {
      if (!pendingAlbum || !pendingAlbum.file) {
        closeAlbumSheet();
        return;
      }
      const file = pendingAlbum.file;
      const kind = pendingAlbum.kind || 'video';
      try {
        await navigator.share({
          files: [file],
          title: kind === 'image' ? '保存图片到相册' : '保存视频到相册'
        });
        showMsg('已打开系统菜单，请选择「存储到照片 / 保存视频」', true);
        closeAlbumSheet();
      } catch (err) {
        if (err && err.name === 'AbortError') return;
        downloadBlob(file, file.name || ('xiaoluo_' + Date.now() + '.bin'));
        showMsg('系统分享失败，已改为下载。请到「文件」里分享 → 存储到照片', false);
        closeAlbumSheet();
      }
    };

    result.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-save');
      if (!btn) return;
      e.preventDefault();
      const url = btn.getAttribute('data-url');
      const kind = btn.getAttribute('data-kind') || 'video';
      if (url) saveMedia(url, kind, btn);
    });

    btnParse.onclick = doParse;
    input.addEventListener('paste', function () {
      setTimeout(function () {
        if (skipNextPasteParse) { skipNextPasteParse = false; return; }
        doParse();
      }, 50);
    });
  </script>
</body>
</html>
