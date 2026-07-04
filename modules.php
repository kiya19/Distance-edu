<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'instructor', 'cde_officer', 'student']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['administrator', 'instructor', 'cde_officer']);
    try {
        $file = save_upload('module_file', 'modules');
        if (!$file) {
            throw new RuntimeException('Choose a module file.');
        }
        $stmt = db()->prepare(
            'INSERT INTO modules (course_id, uploaded_by, title, description, file_path)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $_POST['course_id'],
            $user['id'],
            trim($_POST['title']),
            trim($_POST['description'] ?? ''),
            $file,
        ]);
        flash('success', 'Module uploaded.');
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('modules.php');
}

$modules = db()->query(
    'SELECT modules.*, courses.code, courses.title AS course_title, users.full_name AS uploader
     FROM modules
     JOIN courses ON courses.id = modules.course_id
     JOIN users ON users.id = modules.uploaded_by
     ORDER BY modules.created_at DESC'
)->fetchAll();

render_header('Course Modules');
?>
<?php if (role_is(['administrator', 'instructor', 'cde_officer'])): ?>
<section class="panel">
    <h2>Upload Module</h2>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Course <select name="course_id" required><?= options(courses(), 'id', 'code') ?></select></label>
            <label>Module Title <input name="title" required></label>
            <label>Module File <input type="file" name="module_file" required></label>
        </div>
        <label>Description <textarea name="description"></textarea></label>
        <button type="submit">Upload Module</button>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:16px">
    <h2>Available Modules</h2>
    <table>
        <thead><tr><th>Course</th><th>Module</th><th>Uploaded By</th><th>Date</th><th>Download</th></tr></thead>
        <tbody>
        <?php foreach ($modules as $row): ?>
            <tr>
                <td><?= e($row['code']) ?><br><span class="muted"><?= e($row['course_title']) ?></span></td>
                <td><?= e($row['title']) ?><br><span class="muted"><?= e($row['description']) ?></span></td>
                <td><?= e($row['uploader']) ?></td>
                <td><?= e($row['created_at']) ?></td>
                <td><a class="button secondary" href="<?= e(url('download.php?type=module&id=' . $row['id'])) ?>">Download</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

