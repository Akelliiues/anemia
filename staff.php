<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

define('ANEMIA_STAFF_BOOT', true);
require __DIR__ . '/_private/icons.php';

$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '';
$staff = null;
$inlineLogin = ($_GET['entry'] ?? '') === 'inline';
$afterLogin = match ($_GET['next'] ?? '') {
    'scan'=>'dashboard.php?view=scan-request',
    'unit-scan'=>'dashboard.php?view=scan-request&app=unit',
    'unit-install'=>'dashboard.php?view=scanner-install',
    'mobile'=>'dashboard.php?view=mobile',
    default=>'dashboard.php',
};
$fault = '';
$message = '';
$token = '';
$sendInlineLogin = static function(array $data): never {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
};
if (!$secure && PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    $fault = 'เปิดหน้าเจ้าหน้าที่ผ่าน HTTPS';
} else {
    ini_set('session.use_strict_mode', '1');
    session_name('anemia_staff_session');
    session_set_cookie_params(['secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    if (isset($_SESSION['until']) && (int)$_SESSION['until'] < time()) $_SESSION = [];
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    $token = $_SESSION['csrf'];
    try {
        $config = require __DIR__ . '/_private/config.php';
        require __DIR__ . '/_private/database.php';
        $db = anemiaDatabase($config);
        initializeAnemia($db, $config);
        if (isset($_SESSION['username'])) {
            $lookup = $db->prepare('SELECT username,role FROM anemia_staff WHERE username=? AND enabled=1');
            $lookup->execute([$_SESSION['username']]);
            $staff = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!is_string($_POST['csrf'] ?? null) || !hash_equals($token, $_POST['csrf'])) {
                http_response_code(403);
                $message = 'คำขอหมดอายุ กรุณาเปิดหน้าใหม่แล้วลองอีกครั้ง';
            } elseif (($_POST['action'] ?? '') === 'logout') {
                $_SESSION = [];
                session_regenerate_id(true);
                $loginPath=match ($_GET['next']??'') {'unit-scan'=>'login?next=unit-scan','mobile'=>'login?next=mobile',default=>'login'};
                header('Location: '.$loginPath, true, 303);
                exit;
            } elseif (($_POST['action'] ?? '') === 'login') {
                $user = is_string($_POST['username'] ?? null) ? strtolower(trim($_POST['username'])) : '';
                $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                if (!consumeAnemiaAttempt($db, $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
                    http_response_code(429);
                    $message = 'ลองเข้าสู่ระบบใหม่ในอีกสักครู่';
                } elseif (strlen($user) <= 40 && strlen($pass) <= 128 && $pass !== '') {
                    $lookup = $db->prepare('SELECT username,salt,password_hash,enabled FROM anemia_staff WHERE username=?');
                    $lookup->execute([$user]);
                    $account = $lookup->fetch(PDO::FETCH_ASSOC);
                    $hash = hash_pbkdf2('sha256', $pass, $account['salt'] ?? str_repeat('0', 48), 600000, 64);
                    if (hash_equals($account['password_hash'] ?? str_repeat('0', 64), $hash) && $account && (int)$account['enabled'] === 1) {
                        session_regenerate_id(true);
                        $_SESSION = ['username' => $account['username'], 'csrf' => bin2hex(random_bytes(32)), 'until' => time() + 28800];
                        if ($inlineLogin) $sendInlineLogin(['destination'=>$afterLogin]);
                        header('Location: '.$afterLogin, true, 303);
                        exit;
                    }
                    $message = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                } else {
                    $message = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                }
            }
        }
        if ($staff && $_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($inlineLogin) $sendInlineLogin(['destination'=>$afterLogin]);
            header('Location: '.$afterLogin, true, 303);
            exit;
        }
    } catch (Throwable $error) {
        http_response_code(503);
        $fault = 'ระบบยังเชื่อมต่อหรือเตรียมฐานข้อมูลไม่ได้ ติดต่อผู้ดูแลโฮสต์เพื่อตรวจสอบสิทธิ์ของบัญชีฐานข้อมูล';
        error_log('Anemia staff startup failed (' . get_class($error) . ')');
    }
}
if ($inlineLogin) $sendInlineLogin(['csrf'=>$fault===''?$token:'','message'=>$fault!==''?$fault:$message]);
function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เจ้าหน้าที่ · Anemia Portal</title>
<script src="theme.js?v=1"></script><script defer src="installed-update.js"></script><link rel="stylesheet" href="style.css?v=portal-neumorphism-v71">
</head>
<body class="staff-access">
<main class="staff-access-main">
  <a class="back floating-back" href="index.html" aria-label="กลับหน้าหลัก" title="กลับหน้าหลัก"><?= workspaceIcon('back') ?></a>
  <div class="staff-access-shell">
    <aside class="staff-access-cover" aria-label="Anemia Portal">
      <div class="staff-cover-copy">
        <span class="staff-cover-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2s7 8 7 13a7 7 0 0 1-14 0c0-5 7-13 7-13Z"/><path d="M9 16a3 3 0 0 0 3 3"/></svg></span>
        <strong>Anemia Portal</strong>
        <p>ระบบประเมินและติดตามภาวะโลหิตจาง</p>
      </div>
      <div class="staff-cover-visual" aria-hidden="true"><i></i><i></i><img src="hero-women.webp" alt="" width="520" height="337"></div>
      <p class="staff-cover-agency">ศูนย์อนามัยที่ ๑๐ จังหวัดอุบลราชธานี</p>
    </aside>

    <section class="staff-access-panel">
        <p class="staff-access-label">สำหรับเจ้าหน้าที่</p>
        <h1>เข้าสู่ระบบ</h1>
        <p class="staff-access-description">ใช้บัญชีเจ้าหน้าที่เพื่อเข้าใช้งาน Anemia Portal</p>
        <?php if (($_GET['entry'] ?? '') === 'portal'): ?>
          <nav class="participant-account-tabs" aria-label="เลือกวิธีเข้าสู่ระบบ">
            <a href="participant-account.php?return=portal">PIN สมาชิก</a>
            <a href="login.php?entry=portal" aria-current="page">รหัสผ่านเจ้าหน้าที่</a>
          </nav>
        <?php endif; ?>
        <?php if ($fault !== ''): ?>
          <p class="staff-access-alert" role="status"><?= esc($fault) ?></p>
        <?php else: ?>
          <?php if ($message !== ''): ?><p class="staff-access-alert" role="alert"><?= esc($message) ?></p><?php endif; ?>
          <form method="post" class="staff-access-form">
            <input type="hidden" name="csrf" value="<?= esc($token) ?>">
            <label for="staff-user">ชื่อผู้ใช้</label>
            <div class="staff-access-field">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 21v-2a5 5 0 0 1 5-5h5a5 5 0 0 1 5 5v2"/></svg>
              <input id="staff-user" name="username" required maxlength="40" autocomplete="username" autocapitalize="off" spellcheck="false" placeholder="กรอกชื่อผู้ใช้">
            </div>
            <label for="staff-pass">รหัสผ่าน</label>
            <div class="staff-access-field">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
              <input id="staff-pass" name="password" type="password" required maxlength="128" autocomplete="current-password" placeholder="กรอกรหัสผ่าน">
            </div>
            <button class="staff-access-submit" name="action" value="login">เข้าสู่ระบบ <span aria-hidden="true">→</span></button>
          </form>
        <?php endif; ?>
    </section>
  </div>
</main>
<div id="staff-login-loading" class="report-loading-overlay staff-login-loading no-print" hidden>
 <div class="report-loading-card staff-loading-card" role="status">
  <span class="report-spinner" aria-hidden="true"></span>
  <div><strong>กำลังเข้าสู่ระบบ</strong><span>กำลังตรวจบัญชีและเตรียมเปิดแดชบอร์ด</span></div>
 </div>
</div>
<script>
(() => {
 const form = document.querySelector('.staff-access-form');
 if (!form) return;
 const overlay = document.getElementById('staff-login-loading');
 const button = form.querySelector('button[value="login"]');
 let submitting = false;
 form.addEventListener('submit', event => {
  if (submitting || event.defaultPrevented) { event.preventDefault(); return; }
  submitting = true;
  form.setAttribute('aria-busy','true');
  button.setAttribute('aria-disabled','true');
  overlay.hidden = false;
 });
 window.addEventListener('pageshow', () => {
  submitting = false;
  overlay.hidden = true;
  form.removeAttribute('aria-busy');
  button.removeAttribute('aria-disabled');
 });
})();
</script>
</body></html>
