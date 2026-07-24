<?php

declare(strict_types=1);

session_start();

// Internationalization (i18n) - simple key-based loader
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
$lang = in_array($lang, ['en', 'am']) ? $lang : 'en';
$_SESSION['lang'] = $lang;
$langFile = __DIR__ . '/lang/' . $lang . '.php';
$translations = file_exists($langFile) ? (require $langFile) : [];
function t(string $key, string $default = '') {
    global $translations;
    return $translations[$key] ?? $default;
}

$config = require __DIR__ . '/../config/database.php';

function app_config(string $key, $default = null)
{
    global $config;
    return $config[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            app_config('host'),
            app_config('database'),
            app_config('charset', 'utf8mb4')
        );
        $pdo = new PDO($dsn, app_config('username'), app_config('password'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        $dbPath = getenv('SQLITE_PATH') ?: (__DIR__ . '/../database/dems.sqlite');
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0775, true);
        }
        $initDb = !file_exists($dbPath);
        
        $dsn = 'sqlite:' . $dbPath;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        if ($initDb) {
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $sqlFile = __DIR__ . '/../database/dems_sqlite.sql';
            if (file_exists($sqlFile)) {
                // SQLite PDO does not support executing multiple statements containing INSERT in a single query unless we do it block-by-block or through exec
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        }
        return $pdo;
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $baseUrl = app_config('base_url');
    if (php_sapi_name() === 'cli-server') {
        $baseUrl = '';
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $posted)) {
            flash('danger', 'Security token expired. Please try again.');
            redirect('dashboard.php');
        }
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    $stmt = db()->prepare(
        'SELECT users.*, roles.name AS role_name, roles.label AS role_label
         FROM users
         JOIN roles ON roles.id = users.role_id
         WHERE users.id = ? AND users.status = "active"'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('index.php');
    }
    return $user;
}

function role_is($roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    $roles = (array) $roles;
    return in_array($user['role_name'], $roles, true);
}

function require_role($roles): array
{
    $user = require_login();
    if (!role_is($roles)) {
        flash('danger', 'You are not authorized to open that page.');
        redirect('dashboard.php');
    }
    return $user;
}

function starts_with(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function verify_user_password(array $user, string $password): bool
{
    $hash = $user['password_hash'];
    if (starts_with($hash, '$') && password_verify($password, $hash)) {
        return true;
    }

    if (starts_with($hash, 'sha256:')) {
        $legacy = substr($hash, 7);
        if (hash_equals($legacy, hash('sha256', $password))) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$newHash, date('Y-m-d H:i:s'), $user['id']]);
            return true;
        }
    }

    return false;
}

function all_roles(): array
{
    return db()->query('SELECT * FROM roles ORDER BY label')->fetchAll();
}

function options(array $rows, string $valueKey, string $labelKey, $selected = null): string
{
    $html = '';
    foreach ($rows as $row) {
        $selectedAttr = ((string) $row[$valueKey] === (string) $selected) ? ' selected' : '';
        $html .= '<option value="' . e($row[$valueKey]) . '"' . $selectedAttr . '>' . e($row[$labelKey]) . '</option>';
    }
    return $html;
}

function courses(): array
{
    return db()->query(
        'SELECT courses.*, users.full_name AS instructor_name
         FROM courses
         LEFT JOIN users ON users.id = courses.instructor_id
         ORDER BY courses.code'
    )->fetchAll();
}

function students(): array
{
    return db()->query(
        'SELECT students.*, users.full_name, users.username
         FROM students
         JOIN users ON users.id = students.user_id
         ORDER BY users.full_name'
    )->fetchAll();
}

function instructors(): array
{
    return db()->query(
        'SELECT instructors.*, users.full_name
         FROM instructors
         JOIN users ON users.id = instructors.user_id
         ORDER BY users.full_name'
    )->fetchAll();
}

function role_home_cards(string $role): array
{
    $cards = [
        'administrator' => [
            ['Users', 'Create and manage accounts', 'users.php'],
            ['Courses', 'Register courses and instructors', 'courses.php'],
            ['Announcements', 'Post system notices', 'announcements.php'],
            ['Reports', 'View system summaries', 'reports.php'],
        ],
        'student' => [
            ['Modules', 'Download course modules', 'modules.php'],
            ['Assignments', 'Submit assignment answers', 'assignments.php'],
            ['Grades', 'View results and feedback', 'grades.php'],
            ['Announcements', 'Read latest schedules and notices', 'announcements.php'],
            ['Feedback', 'Send questions and comments to staff', 'feedback.php'],
        ],
        'instructor' => [
            ['Modules', 'Upload prepared modules', 'modules.php'],
            ['Assignments', 'Publish tasks and review submissions', 'assignments.php'],
            ['Grades', 'Record course results', 'grades.php'],
            ['Reports', 'View assigned-course summaries', 'reports.php'],
        ],
        'cde_officer' => [
            ['Announcements', 'Post news and schedules', 'announcements.php'],
            ['Modules', 'Upload learning materials', 'modules.php'],
            ['Reports', 'Prepare CDE summaries', 'reports.php'],
        ],
        'registrar' => [
            ['Reports', 'Prepare academic schedules and grade reports', 'reports.php'],
            ['Grades', 'Review grade reports', 'grades.php'],
            ['Courses', 'View course information', 'courses.php'],
        ],
        'finance' => [
            ['Payments', 'Control payment status', 'payments.php'],
            ['Reports', 'View payment summaries', 'reports.php'],
        ],
        'department_head' => [
            ['Courses', 'Assign instructors and register courses', 'courses.php'],
            ['Grades', 'Approve grade reports', 'grades.php'],
            ['Reports', 'Prepare employee worked-time summaries', 'reports.php'],
        ],
        'academic_vp' => [
            ['Reports', 'View generated academic reports', 'reports.php'],
            ['Grades', 'View grade status', 'grades.php'],
        ],
        'college_dean' => [
            ['Reports', 'View academic schedules and loads', 'reports.php'],
            ['Courses', 'View registered courses', 'courses.php'],
        ],
    ];
    return $cards[$role] ?? [];
}

function save_upload(string $field, string $folder): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }
    $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'txt', 'jpg', 'jpeg', 'png'];
    $original = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Unsupported file type.');
    }
    $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($original, PATHINFO_FILENAME));
    $fileName = $safeBase . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $relative = trim($folder, '/') . '/' . $fileName;
    $targetDir = rtrim(app_config('upload_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($folder, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    $target = $targetDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return $relative;
}

function download_file(string $relative): void
{
    $base = realpath(app_config('upload_path'));
    $path = realpath($base . DIRECTORY_SEPARATOR . $relative);
    if (!$base || !$path || !starts_with($path, $base) || !is_file($path)) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function render_header(string $title): void
{
    $user = current_user();
    require_once __DIR__ . '/logo.php';

    // Nav items: [label, icon SVG path d="…", file]
    $navItems = [
        'dashboard.php'    => ['Dashboard',     'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        'users.php'        => ['Users',         'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        'courses.php'      => ['Courses',       'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        'modules.php'      => ['Modules',       'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        'assignments.php'  => ['Assignments',   'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        'grades.php'       => ['Grades',        'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z'],
        'announcements.php'=> ['Announcements', 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
        'payments.php'     => ['Payments',      'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        'feedback.php'     => ['Feedback',      'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
        'reports.php'      => ['Reports',       'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ];

    $allowed = [
        'administrator'  => ['dashboard.php','users.php','courses.php','modules.php','assignments.php','grades.php','announcements.php','payments.php','feedback.php','reports.php'],
        'student'        => ['dashboard.php','modules.php','assignments.php','grades.php','announcements.php','payments.php','feedback.php'],
        'instructor'     => ['dashboard.php','courses.php','modules.php','assignments.php','grades.php','reports.php'],
        'cde_officer'    => ['dashboard.php','modules.php','announcements.php','feedback.php','reports.php'],
        'registrar'      => ['dashboard.php','courses.php','grades.php','feedback.php','reports.php'],
        'finance'        => ['dashboard.php','payments.php','reports.php'],
        'department_head'=> ['dashboard.php','courses.php','grades.php','reports.php'],
        'academic_vp'    => ['dashboard.php','grades.php','reports.php'],
        'college_dean'   => ['dashboard.php','courses.php','reports.php'],
    ];

    $role       = $user['role_name']  ?? '';
    $roleLabel  = $user['role_label'] ?? '';
    $fullName   = $user['full_name']  ?? '';
    $initials   = strtoupper(implode('', array_map(fn($p) => $p[0] ?? '', explode(' ', trim($fullName)))));
    $initials   = substr($initials, 0, 2);
    $currentFile = basename($_SERVER['PHP_SELF']);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> — DEMS</title>
        <link rel="stylesheet" href="<?= e(url('styles.css')) ?>"><?php /* no external fonts - instant offline load */ ?>
    </head>
    <body>
    <?php if ($user): ?>
    <div class="shell">
      <!-- ── Sidebar ── -->
      <aside class="sidebar" id="sidebar">
        <!-- Close button — mobile only -->
        <button class="sidebar-close" onclick="closeSidebar()" aria-label="Close menu">✕</button>

        <div class="sidebar-brand">
          <img src="<?= NAC_LOGO_SRC ?>" alt="NAC Logo" style="width:52px;height:52px;border-radius:50%;border:2px solid rgba(200,151,42,.5);object-fit:cover;margin-bottom:10px;display:block;">
          <div class="brand-name">New Abyssinia College</div>
          <div class="brand-sub">Distance Education Management</div>
        </div>

        <nav class="sidebar-nav">
          <?php
            $sections = [
                'MAIN'   => ['dashboard.php'],
                'MANAGE' => ['users.php','courses.php','modules.php','assignments.php','grades.php'],
                'COMMS'  => ['announcements.php','feedback.php'],
                'FINANCE'=> ['payments.php'],
                'SYSTEM' => ['reports.php'],
            ];
            foreach ($sections as $sLabel => $pages):
                $visible = array_filter($pages, fn($p) => in_array($p, $allowed[$role] ?? ['dashboard.php'], true));
                if (!$visible) continue;
          ?>
          <div class="nav-section"><?= e($sLabel) ?></div>
          <?php foreach ($visible as $page):
            [$lbl, $iconPath] = $navItems[$page];
            $isActive = ($currentFile === $page);
          ?>
          <a href="<?= e(url($page)) ?>" class="nav-link<?= $isActive ? ' active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="<?= e($iconPath) ?>"/>
            </svg>
            <?= e($lbl) ?>
          </a>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </nav>

        <div class="sidebar-user">
          <div class="user-avatar"><?= e($initials) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($fullName) ?></div>
            <div class="user-role"><?= e($roleLabel) ?></div>
          </div>
        </div>
      </aside>

      <!-- ── Main ── -->
      <!-- Mobile sidebar overlay -->
      <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

      <div class="main-wrapper">
        <header class="topbar">
          <!-- Hamburger button — mobile only -->
          <button class="topbar-menu-btn" id="menuBtn" onclick="openSidebar()" aria-label="Open menu">
            <span></span><span></span><span></span>
          </button>

          <div class="topbar-left">
            <img src="<?= NAC_LOGO_SRC ?>" alt="NAC Logo">
            <span class="topbar-title">New Abyssinia College — DEMS</span>
          </div>
          <div class="topbar-right">
            <div class="topbar-avatar"><?= e($initials) ?></div>
            <span class="topbar-name"><?= e($fullName) ?></span>
            <a href="<?= e(url('profile.php')) ?>" class="btn-outline">Profile</a>
            <a href="<?= e(url('logout.php')) ?>" class="btn-outline btn-danger-outline">Logout</a>
          </div>
        </header>

        <main class="container">
          <?php foreach (flashes() as $flash): ?>
            <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
          <?php endforeach; ?>
          <div class="page-header">
            <div>
              <div class="page-eyebrow"><?= e(strtoupper($roleLabel)) ?></div>
              <h1><?= e($title) ?></h1>
            </div>
          </div>
    <?php else: ?>
    <main class="container">
      <?php foreach (flashes() as $flash): ?>
        <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php
}

function render_footer(): void
{
    $user = current_user();
    if ($user): ?>
        </main>
        <footer class="footer">
          New Abyssinia College · Distance Education Management System · <?= date('Y') ?>
        </footer>
      </div><!-- /.main-wrapper -->
    </div><!-- /.shell -->

    <!-- Mobile sidebar JS — zero dependencies -->
    <script>
    function openSidebar(){
      document.querySelector('.sidebar').classList.add('open');
      document.getElementById('sidebarOverlay').classList.add('active');
      document.body.style.overflow='hidden';
    }
    function closeSidebar(){
      document.querySelector('.sidebar').classList.remove('open');
      document.getElementById('sidebarOverlay').classList.remove('active');
      document.body.style.overflow='';
    }
    // Close on Escape key
    document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeSidebar(); });
    // Close sidebar when a nav link is clicked (mobile navigation)
    document.querySelectorAll('.nav-link').forEach(function(l){
      l.addEventListener('click',function(){ if(window.innerWidth<=900) closeSidebar(); });
    });
    </script>

    <?php else: ?>
    </main>
    <?php endif; ?>
    </body>
    </html>
    <?php
}
