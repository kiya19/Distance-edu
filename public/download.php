<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_login();

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($type === 'module') {
    require_role(['administrator', 'student', 'instructor', 'cde_officer']);
    $stmt = db()->prepare('SELECT file_path FROM modules WHERE id = ?');
} elseif ($type === 'assignment') {
    require_role(['administrator', 'student', 'instructor']);
    $stmt = db()->prepare('SELECT file_path FROM assignments WHERE id = ?');
} elseif ($type === 'submission') {
    require_role(['administrator', 'instructor']);
    $stmt = db()->prepare('SELECT file_path FROM submissions WHERE id = ?');
} else {
    http_response_code(404);
    echo 'Unknown download type.';
    exit;
}

$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo 'File record not found.';
    exit;
}
download_file($row['file_path']);

