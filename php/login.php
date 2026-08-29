<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    if (attempt_login($password)) {
        header('Location: index.php');
        exit;
    }
    $error = '密码错误';
}

$cfg = app_config();
$name = htmlspecialchars($cfg['site_name'], ENT_QUOTES, 'UTF-8');
$qq = htmlspecialchars($cfg['qq_url'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $name ?> - 登录</title>
  <link rel="stylesheet" href="assets/style.css?v=2026082915" />
  <style>
    .login-actions { display: flex !important; gap: 10px; margin-top: 4px; width: 100%; }
    .login-actions .btn-enter,
    .login-actions .btn-getpwd {
      flex: 1 1 0 !important; min-width: 0;
      display: inline-flex !important; align-items: center; justify-content: center;
      text-align: center; text-decoration: none !important;
      border: 0 !important; cursor: pointer; border-radius: 12px !important;
      padding: 14px 10px !important; font-size: 15px !important; font-weight: 700;
      line-height: 1.2; box-sizing: border-box;
      -webkit-appearance: none; appearance: none; color: #fff !important;
    }
    .login-actions .btn-enter {
      background: linear-gradient(90deg, #22c55e, #16a34a) !important;
    }
    .login-actions .btn-getpwd {
      background: linear-gradient(90deg, #ec4899, #db2777) !important;
      box-shadow: 0 0 18px rgba(236,72,153,.4);
    }
    @keyframes pwd-shake {
      0%,100%{transform:translateX(0)} 20%{transform:translateX(-3px)} 40%{transform:translateX(3px)}
      60%{transform:translateX(-2px)} 80%{transform:translateX(2px)}
    }
    .login-actions .btn-getpwd.shake { animation: pwd-shake 1.1s ease-in-out infinite; }
  </style>
</head>
<body>
  <div class="bg"></div>
  <main class="login-wrap">
    <img class="avatar" src="assets/avatar.jpg" alt="" />
    <h1><?= $name ?></h1>
    <p class="muted">请输入访问密码后继续</p>
    <form method="post" class="card">
      <label>访问密码
        <input type="password" name="password" autofocus required placeholder="输入密码" />
      </label>
      <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <div class="login-actions">
        <button type="submit" class="btn-enter">进入站点</button>
        <a class="btn-getpwd shake" href="<?= $qq ?>" target="_blank" rel="noopener">点我获取密码</a>
      </div>
    </form>
  </main>
</body>
</html>
