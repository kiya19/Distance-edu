<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'department_head', 'registrar', 'instructor', 'college_dean']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['administrator', 'department_head']);
    $instructorId = $_POST['instructor_id'] !== '' ? (int) $_POST['instructor_id'] : null;
    $code = trim($_POST['code']);

    $existing = db()->prepare('SELECT id FROM courses WHERE code = ?');
    $existing->execute([$code]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $stmt = db()->prepare(
            'UPDATE courses SET title = ?, credits = ?, department = ?, instructor_id = ? WHERE id = ?'
        );
        $stmt->execute([
            trim($_POST['title']),
            (int) $_POST['credits'],
            trim($_POST['department']),
            $instructorId,
            $existingId,
        ]);
    } else {
        $stmt = db()->prepare(
            'INSERT INTO courses (code, title, credits, department, instructor_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $code,
            trim($_POST['title']),
            (int) $_POST['credits'],
            trim($_POST['department']),
            $instructorId,
        ]);
    }
    flash('success', 'Course saved.');
    redirect('courses.php');
}

$rows = courses();
$instructorUsers = db()->query(
    'SELECT users.id, users.full_name
     FROM users JOIN roles ON roles.id = users.role_id
     WHERE roles.name = "instructor" AND users.status = "active"
     ORDER BY users.full_name'
)->fetchAll();

render_header('Course Management');
?>
<?php if (role_is(['administrator', 'department_head'])): ?>
<section class="panel">
    <h2>Register or Assign Course</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Course Code <input name="code" required placeholder="CS303"></label>
            <label>Course Title <input name="title" required placeholder="System Analysis and Design"></label>
            <label>Credits <input type="number" name="credits" min="1" max="6" required value="3"></label>
            <label>Department <input name="department" required value="Computer Science"></label>
            <label>Instructor
                <select name="instructor_id">
                    <option value="">Not assigned</option>
                    <?= options($instructorUsers, 'id', 'full_name') ?>
                </select>
            </label>
        </div>
        <button type="submit">Save Course</button>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:16px">
    <h2>Courses</h2>
    <table>
        <thead><tr><th>Code</th><th>Title</th><th>Department</th><th>Credits</th><th>Instructor</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['code']) ?></td>
                <td><?= e($row['title']) ?></td>
                <td><?= e($row['department']) ?></td>
                <td><?= e($row['credits']) ?></td>
                <td><?= e($row['instructor_name'] ?? 'Not assigned') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

