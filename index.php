<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/logo.php';
require_once __DIR__ . '/../app/hero.php';

if (current_user()) { redirect('dashboard.php'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        $stmt = db()->prepare(
            'SELECT users.*, roles.name AS role_name, roles.label AS role_label
             FROM users JOIN roles ON roles.id = users.role_id
             WHERE users.username = ? AND users.status = "active"'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && verify_user_password($user, $password)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect('dashboard.php');
        }
        $error = 'Incorrect username or password. Please try again.';
    } catch (Throwable $ex) {
        $error = 'Database error: ' . $ex->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign In — New Abyssinia College DEMS</title>
  <link rel="stylesheet" href="<?= e(url('styles.css')) ?>">
  <style>
  /* ══════════════════════════════════════════════
     LOGIN PAGE STYLES
     ══════════════════════════════════════════════ */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif;
    background:
      radial-gradient(circle at 15% 15%, rgba(26,122,64,.16), transparent 45%),
      radial-gradient(circle at 85% 85%, rgba(232,176,48,.08), transparent 45%),
      #060f1a;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* Frame is capped so the layout stays balanced on ultra-wide screens
     instead of stretching the green panel edge-to-edge. */
  .login-outer {
    display: flex;
    min-height: 100vh;
    max-width: 1240px;
    margin: 0 auto;
    align-items: stretch;
    box-shadow: 0 0 90px rgba(0,0,0,.55);
  }

  /* ══ LEFT PANEL ══════════════════════════════ */
  .login-left {
    flex: 1;
    background: linear-gradient(160deg, #0d5c2e 0%, #1a7a40 50%, #0a3d20 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 44px 44px 36px;
    position: relative;
    overflow: hidden;
    /* Avoid capturing pointer events which can overlap the login panel on small screens */
    pointer-events: none;
  }

  /* Animated background rings */
  .login-left::before {
    content: '';
    position: absolute; top: -100px; right: -100px;
    width: 500px; height: 500px; border-radius: 50%;
    border: 80px solid rgba(255,255,255,.04);
    animation: ring-pulse 6s ease-in-out infinite;
    pointer-events: none;
  }
  .login-left::after {
    content: '';
    position: absolute; bottom: -120px; left: -80px;
    width: 420px; height: 420px; border-radius: 50%;
    border: 60px solid rgba(255,255,255,.03);
    animation: ring-pulse 8s ease-in-out 2s infinite;
    pointer-events: none;
  }
  @keyframes ring-pulse {
    0%, 100% { transform: scale(1);   opacity: .6; }
    50%       { transform: scale(1.1); opacity: 1;  }
  }

  /* ── 3D Hero photo (graduation image) ── */
  .hero-photo-wrap {
    position: relative;
    z-index: 2;
    margin-bottom: 26px;
    perspective: 1400px;
  }

  .hero-photo-card {
    position: relative;
    width: 100%;
    aspect-ratio: 3 / 2;
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.18);
    box-shadow: 0 22px 50px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.06);
    transform-style: preserve-3d;
    animation: hero-tilt 7s ease-in-out infinite;
    will-change: transform;
  }

  .hero-photo-img {
    position: absolute; inset: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    transform: scale(1.02);
  }

  .hero-photo-shine {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,.16) 0%, rgba(255,255,255,0) 35%);
    pointer-events: none;
  }

  .hero-photo-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(180deg, rgba(5,18,10,.90) 0%, rgba(5,18,10,.62) 32%, rgba(6,20,12,0) 62%);
    pointer-events: none;
  }

  /* Content overlaid on the UPPER part of the photo: logo + welcome */
  .hero-top-content {
    position: absolute; top: 0; left: 0; right: 0; z-index: 2;
    padding: 24px 20px 10px;
    display: flex; flex-direction: column; align-items: center;
    text-align: center;
  }

  /* Old 3D spinning logo ring, scaled down to sit on the photo */
  .logo-3d-wrap {
    display: flex; flex-direction: column; align-items: center;
    position: relative;
    margin-bottom: 14px;
  }
  .logo-3d-ring { position: relative; width: 84px; height: 84px; }
  .logo-3d-ring::before {
    content: '';
    position: absolute; inset: -7px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, #e8b030, #a8e6bc, #0d5c2e, #e8b030, #fff, #e8b030);
    animation: spin-ring 4s linear infinite;
    opacity: .8;
  }
  .logo-3d-ring::after {
    content: '';
    position: absolute; inset: -3px;
    border-radius: 50%;
    background: linear-gradient(145deg, #0d5c2e, #1a7a40);
  }
  @keyframes spin-ring { to { transform: rotate(360deg); } }

  .logo-3d-img {
    position: relative; z-index: 1;
    width: 84px; height: 84px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255,255,255,.4);
    box-shadow: 0 0 0 2px rgba(232,176,48,.45), 0 8px 24px rgba(0,0,0,.55), inset 0 2px 8px rgba(255,255,255,.15);
    animation: logo-float 4s ease-in-out infinite;
    transform-style: preserve-3d;
  }
  @keyframes logo-float {
    0%, 100% { transform: translateY(0px)   rotateY(0deg)   scale(1);    box-shadow: 0 8px 24px rgba(0,0,0,.55), 0 0 0 2px rgba(232,176,48,.45); }
    25%      { transform: translateY(-5px)  rotateY(8deg)   scale(1.03); box-shadow: 0 14px 30px rgba(0,0,0,.6),  0 0 0 3px rgba(232,176,48,.6); }
    50%      { transform: translateY(-8px)  rotateY(0deg)   scale(1.04); box-shadow: 0 18px 36px rgba(0,0,0,.55), 0 0 0 4px rgba(232,176,48,.55); }
    75%      { transform: translateY(-4px)  rotateY(-8deg)  scale(1.02); box-shadow: 0 12px 28px rgba(0,0,0,.55), 0 0 0 3px rgba(232,176,48,.55); }
  }

  .logo-3d-wrap .dot {
    position: absolute; top: 42px; left: 42px;
    width: 5px; height: 5px; border-radius: 50%;
    background: #e8b030;
    animation: dot-orbit 3s linear infinite;
    opacity: .85;
  }
  .logo-3d-wrap .dot:nth-child(2) { animation-delay: -1s; width: 4px; height: 4px; background: #a8e6bc; }
  .logo-3d-wrap .dot:nth-child(3) { animation-delay: -2s; width: 4px; height: 4px; background: #fff; opacity: .55; }
  @keyframes dot-orbit {
    0%   { transform: rotate(0deg)   translateX(50px) rotate(0deg); }
    100% { transform: rotate(360deg) translateX(50px) rotate(-360deg); }
  }

  @keyframes hero-tilt {
    0%, 100% { transform: translateY(0)    rotateX(0deg)    rotateY(0deg); }
    25%       { transform: translateY(-7px) rotateX(1.6deg)  rotateY(-2.4deg); }
    50%       { transform: translateY(-11px) rotateX(0deg)   rotateY(0deg); }
    75%       { transform: translateY(-6px) rotateX(-1.6deg) rotateY(2.4deg); }
  }

  /* ── Animated Welcome (now overlaid on the hero photo) ── */
  .welcome-wrap {
    position: relative; z-index: 2;
    padding: 0 2px;
    display: flex; flex-direction: column; align-items: center;
  }
  .welcome-en {
    font-family: Georgia, serif;
    font-size: 17px; font-weight: 700; color: #fff;
    letter-spacing: .3px; overflow: hidden;
    white-space: nowrap; width: 0;
    animation: typewriter-en 1.8s steps(36, end) .5s forwards;
    text-shadow: 0 2px 10px rgba(0,0,0,.55);
  }
  .welcome-am {
    font-size: 20px; font-weight: 700; color: #e8b030;
    margin-top: 6px; opacity: 0; transform: translateY(10px);
    animation: fadeup-am .9s ease 2.4s forwards;
    text-shadow: 0 2px 12px rgba(0,0,0,.5);
    letter-spacing: 1px;
  }
  .welcome-line {
    height: 3px; margin-top: 10px; width: 0;
    border-radius: 2px;
    background: linear-gradient(90deg, #e8b030, #a8e6bc, #e8b030);
    background-size: 200% 100%;
    animation: draw-line .8s ease 3.2s forwards, shimmer 2.4s linear 4s infinite;
  }

  @keyframes typewriter-en { to { width: 100%; } }
  @keyframes fadeup-am     { to { opacity: 1; transform: translateY(0); } }
  @keyframes draw-line     { to { width: 100%; } }
  @keyframes shimmer       { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

  /* Contact */
  .nac-contact {
    margin-top: 20px; padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,.1);
    display: flex; flex-wrap: wrap; gap: 6px 18px;
    position: relative; z-index: 2;
  }
  .nac-contact span { font-size: 11px; color: rgba(255,255,255,.55); }
  .nac-contact strong { color: rgba(255,255,255,.82); }

  /* ══ RIGHT PANEL ══════════════════════════════ */
  .login-right {
    width: 400px; flex-shrink: 0;
    background: #fff;
    display: flex; flex-direction: column;
    justify-content: center; padding: 48px 40px;
    overflow-y: auto;
    /* Ensure the right panel sits above decorative left-panel elements on small screens */
    position: relative; z-index: 3;
    /* Re-enable pointer events for form controls */
    pointer-events: auto;
  }

  /* Ensure form controls are explicitly interactable in case compositing layers overlap */
  .login-right .form-input,
  .login-right .login-btn {
    position: relative; z-index: 10; pointer-events: auto;
  }

  .login-logo-wrap {
    display: flex; flex-direction: column; align-items: center;
    margin-bottom: 26px; gap: 10px;
  }
  .login-logo-img {
    width: 68px; height: 68px; border-radius: 50%;
    object-fit: cover; border: 3px solid #1a7a40;
    box-shadow: 0 4px 20px rgba(13,92,46,.22);
    animation: logo-float-sm 4s ease-in-out infinite;
  }
  @keyframes logo-float-sm {
    0%,100% { transform: translateY(0);   box-shadow: 0 4px 20px rgba(13,92,46,.22); }
    50%     { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(13,92,46,.32); }
  }
  .login-logo-wrap h2 {
    font-family: Georgia,serif; font-size: 19px;
    color: #0d1b2e; text-align: center; margin: 0; line-height: 1.3;
  }
  .login-logo-wrap p { font-size: 12px; color: #7a90aa; text-align: center; margin: 0; }

  .form-label { display: grid; gap: 5px; font-size: 13px; font-weight: 600; color: #4a5e78; margin-bottom: 13px; }
  .form-input {
    width: 100%; padding: 11px 13px;
    border: 1.5px solid #dce6f0; border-radius: 8px;
    font: 14px 'Segoe UI',sans-serif; color: #0d1b2e;
    transition: border-color .15s, box-shadow .15s;
    -webkit-appearance: none;
  }
  .form-input:focus {
    outline: none; border-color: #1a7a40;
    box-shadow: 0 0 0 3px rgba(26,122,64,.13);
  }
  .login-btn {
    width: 100%; padding: 13px;
    background: #0d5c2e; color: #fff;
    font: 600 15px 'Segoe UI',sans-serif;
    border: none; border-radius: 8px;
    cursor: pointer; margin-top: 4px;
    transition: background .15s, transform .1s;
    touch-action: manipulation;
  }
  .login-btn:hover  { background: #1a7a40; }
  .login-btn:active { transform: scale(.98); }

  .login-hint {
    margin-top: 18px; padding: 13px 15px;
    background: #f4faf6; border-radius: 8px; border: 1px solid #c8e6d2;
  }
  .login-hint p { font-size: 11.5px; color: #4a5e78; margin: 0; line-height: 1.65; }
  .login-hint strong { color: #0d5c2e; }

  .login-error {
    background: #fce8ee; color: #b91c3e;
    border: 1px solid #f5c0cc; border-radius: 8px;
    padding: 10px 13px; font-size: 13px; margin-bottom: 14px;
  }

  /* ══ RESPONSIVE ══════════════════════════════ */
  @media (max-width: 900px) {
    .login-outer  { flex-direction: column; max-width: 640px; box-shadow: none; }
    .login-left   { padding: 32px 24px; }
    .login-right  { width: 100%; padding: 32px 24px; }
    .hero-photo-card { aspect-ratio: 4 / 3; }
  }
  @media (max-width: 480px) {
    .login-left  { padding: 24px 18px; }
    .login-right { padding: 24px 18px; }
    .welcome-en  { font-size: 15px; }
    .welcome-am  { font-size: 18px; }
    .hero-photo-card { aspect-ratio: 3 / 4; border-radius: 16px; }
    .logo-3d-ring, .logo-3d-img { width: 68px; height: 68px; }
    .hero-top-content { padding: 18px 16px 8px; }
  }
  </style>
</head>
<body>
<div class="login-outer">

  <!-- ══ LEFT: College Branding ══ -->
  <div class="login-left">

    <!-- 3D Hero Photo with logo + welcome overlaid on top -->
    <div class="hero-photo-wrap">
      <div class="hero-photo-card">
        <img class="hero-photo-img" src="<?= NAC_HERO_SRC ?>" alt="New Abyssinia College graduates celebrating">
        <div class="hero-photo-shine"></div>
        <div class="hero-photo-overlay"></div>
        <div class="hero-top-content">
          <div class="logo-3d-wrap">
            <div class="logo-3d-ring">
              <img class="logo-3d-img" src="<?= NAC_LOGO_SRC ?>" alt="New Abyssinia College Logo">
            </div>
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
          </div>
          <div class="welcome-wrap">
            <div class="welcome-en">Welcome To New Abyssinia College</div>
            <div class="welcome-am">እንኳን ደህና መጡ</div>
            <div class="welcome-line"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact -->
    <div class="nac-contact">
      <span>📞 <strong>+251 9 60 14 33 33</strong></span>
      <span>📞 <strong>+251 11 8 88 54 21/22</strong></span>
      <span>✉️ <strong>newabyssiniacollege@gmail.com</strong></span>
      <span>📮 P.O.Box 123480, Addis Ababa, Ethiopia</span>
    </div>

  </div>

  <!-- ══ RIGHT: Login Form ══ -->
  <div class="login-right">

    <div class="login-logo-wrap">
      <img class="login-logo-img" src="<?= NAC_LOGO_SRC ?>" alt="NAC Logo">
      <h2>New Abyssinia College</h2>
      <p>Sign in to your DEMS account</p>
    </div>

    <?php if ($error): ?>
      <div class="login-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="" style="display:grid;gap:0">
      <?= csrf_field() ?>
      <label class="form-label">Username
        <input class="form-input" name="username" required autofocus placeholder="e.g. admin" autocomplete="username">
      </label>
      <label class="form-label">Password
        <input class="form-input" type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
      </label>
      <button type="submit" class="login-btn">Sign In →</button>
    </form>

    <div class="login-hint">
      <p>
        <strong>Demo accounts:</strong><br>
        admin · student · instructor · cde<br>
        registrar · finance · depthead · avp · dean<br>
        <strong>Default password:</strong> demo123
      </p>
    </div>

  </div>

</div>
</body>
</html>
