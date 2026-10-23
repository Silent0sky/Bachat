<?php
/** Standalone error page — no DB, no session dependency. Expects $__code, $__title. */
$bodyMap = [404 => 'err.404_body', 403 => 'err.403_body', 419 => 'err.419_body', 500 => 'err.500_body'];
$bodyKey = $bodyMap[(int) ($__code ?? 404)] ?? 'err.404_body';
$csrfVal = (isset($_SESSION)) ? (($_SESSION['_csrf'] ?? '')) : '';
$hasSession = session_status() === PHP_SESSION_ACTIVE;
$authed = false;
if ($hasSession && !empty($_SESSION['user_id'])) { $authed = true; }
$homeHref = $authed ? '/dashboard' : '/';
$homeLabel = $authed ? 'Dashboard' : 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($__title ?? 'Error') ?> · Bachat</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Baloo+2:wght@700&display=swap" rel="stylesheet">
<style>
  :root { --green:#16452F; --fresh:#28A66A; --mint:#EAF7EF; --amber:#F4B942; }
  * { box-sizing: border-box; }
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
         font-family:'Inter',system-ui,sans-serif; background:linear-gradient(160deg,var(--mint) 0%,#FFFDF8 60%); color:var(--green); padding:24px; }
  .card { background:#fff; border-radius:24px; padding:48px 36px; max-width:480px; width:100%; text-align:center;
          box-shadow:0 20px 60px rgba(22,69,47,.12); animation:pop .45s cubic-bezier(.2,.9,.3,1.2); }
  @keyframes pop { from { transform:scale(.92); opacity:0 } to { transform:scale(1); opacity:1 } }
  .code { font-family:'Baloo 2',cursive; font-size:72px; line-height:1; color:var(--fresh); }
  h1 { font-size:22px; margin:12px 0 8px; }
  p { color:#5c6f66; margin:0 0 24px; }
  a.btn { display:inline-block; padding:12px 28px; border-radius:999px; background:var(--fresh); color:#fff;
          text-decoration:none; font-weight:600; transition:transform .2s, box-shadow .2s; }
  a.btn:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(40,166,106,.35); }
  .brand { margin-top:24px; font-size:13px; color:#8aa094; }
</style>
</head>
<body>
  <div class="card" role="alert">
    <div class="code"><?= e($__code ?? 404) ?></div>
    <h1><?= e($__title ?? 'Error') ?></h1>
    <p><?= e(t($bodyKey)) ?></p>
    <a class="btn" href="<?= e($homeHref) ?>"><?= e($homeLabel) ?></a>
    <div class="brand">Bachat · Har Society Ki Smart Bachat</div>
  </div>
</body>
</html>
