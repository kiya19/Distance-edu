<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'instructor', 'student']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            require_role(['administrator', 'instructor']);
            $file = save_upload('assignment_file', 'assignments');
            if (!$file) {
                throw new RuntimeException('Choose an assignment question file.');
            }
            $stmt = db()->prepare(
                'INSERT INTO assignments (course_id, uploaded_by, title, description, due_date, file_path)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $_POST['course_id'],
                $user['id'],
                trim($_POST['title']),
                trim($_POST['description'] ?? ''),
                $_POST['due_date'],
                $file,
            ]);
            flash('success', 'Assignment published.');
        } elseif ($action === 'submit') {
            require_role('student');
            $studentStmt = db()->prepare('SELECT id FROM students WHERE user_id = ?');
            $studentStmt->execute([$user['id']]);
            $studentId = (int) $studentStmt->fetchColumn();
            $file = save_upload('submission_file', 'submissions');
            if (!$file) {
                throw new RuntimeException('Choose your answer file.');
            }
            $checkStmt = db()->prepare('SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?');
            $checkStmt->execute([(int) $_POST['assignment_id'], $studentId]);
            $existingId = $checkStmt->fetchColumn();

            $nowStr = date('Y-m-d H:i:s');
            if ($existingId) {
                $stmt = db()->prepare(
                    'UPDATE submissions 
                     SET file_path = ?, submitted_at = ?, approval_status = "pending" 
                     WHERE id = ?'
                );
                $stmt->execute([$file, $nowStr, $existingId]);
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) 
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([(int) $_POST['assignment_id'], $studentId, $file, $nowStr]);
            }
            flash('success', 'Assignment answer submitted.');
        }
    } catch (Throwable $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('assignments.php');
}

$assignments = db()->query(
    'SELECT assignments.*, courses.code, courses.title AS course_title, users.full_name AS uploader
     FROM assignments
     JOIN courses ON courses.id = assignments.course_id
     JOIN users ON users.id = assignments.uploaded_by
     ORDER BY assignments.due_date ASC'
)->fetchAll();

$submissions = db()->query(
    'SELECT submissions.*, assignments.title AS assignment_title, courses.code, users.full_name AS student_name
     FROM submissions
     JOIN assignments ON assignments.id = submissions.assignment_id
     JOIN courses ON courses.id = assignments.course_id
     JOIN students ON students.id = submissions.student_id
     JOIN users ON users.id = students.user_id
     ORDER BY submissions.submitted_at DESC'
)->fetchAll();

render_header('Assignments');
?>
<?php if (role_is(['administrator', 'instructor'])): ?>
<section class="panel">
    <h2>Publish Assignment</h2>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <label>Course <select name="course_id" required><?= options(courses(), 'id', 'code') ?></select></label>
            <label>Title <input name="title" required></label>
            <label>Due Date <input type="date" name="due_date" required></label>
            <label>Question File <input type="file" name="assignment_file" required></label>
        </div>
        <label>Description <textarea name="description"></textarea></label>
        <button type="submit">Publish Assignment</button>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:16px">
    <h2>Published Assignments</h2>
    <table>
        <thead><tr><th>Course</th><th>Assignment</th><th>Due Date</th><th>File</th><?php if (role_is('student')): ?><th>Submit</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($assignments as $row): ?>
            <tr>
                <td><?= e($row['code']) ?><br><span class="muted"><?= e($row['course_title']) ?></span></td>
                <td><?= e($row['title']) ?><br><span class="muted"><?= e($row['description']) ?></span></td>
                <td><?= e($row['due_date']) ?></td>
                <td><a class="button secondary" href="<?= e(url('download.php?type=assignment&id=' . $row['id'])) ?>">Download</a></td>
                <?php if (role_is('student')): ?>
                    <td>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="assignment_id" value="<?= e($row['id']) ?>">
                            <input type="file" name="submission_file" required>
                            <button type="submit">Submit</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if (role_is(['administrator', 'instructor'])): ?>
<section class="panel" style="margin-top:16px">
    <h2>Student Submissions</h2>
    <table>
        <thead><tr><th>Student</th><th>Course</th><th>Assignment</th><th>Submitted</th><th>Grade</th><th>File</th></tr></thead>
        <tbody>
        <?php foreach ($submissions as $row): ?>
            <tr>
                <td><?= e($row['student_name']) ?></td>
                <td><?= e($row['code']) ?></td>
                <td><?= e($row['assignment_title']) ?></td>
                <td><?= e($row['submitted_at']) ?></td>
                <td><?= e($row['grade'] ?? 'Not graded') ?></td>
                <td><a class="button secondary" href="<?= e(url('download.php?type=submission&id=' . $row['id'])) ?>">Download</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
<?php render_footer(); ?>

