<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'instructor', 'student', 'department_head', 'registrar', 'academic_vp']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'grade') {
        require_role(['administrator', 'instructor']);
        $stmt = db()->prepare(
            'UPDATE submissions
             SET grade = ?, feedback = ?, graded_by = ?, approval_status = "pending"
             WHERE id = ?'
        );
        $stmt->execute([
            (float) $_POST['grade'],
            trim($_POST['feedback'] ?? ''),
            $user['id'],
            (int) $_POST['submission_id'],
        ]);
        flash('success', 'Grade recorded.');
    } elseif ($action === 'approve') {
        require_role(['administrator', 'department_head']);
        $stmt = db()->prepare(
            'UPDATE submissions
             SET approval_status = "approved", approved_by = ?, approved_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$user['id'], date('Y-m-d H:i:s'), (int) $_POST['submission_id']]);
        flash('success', 'Grade report approved.');
    }
    redirect('grades.php');
}

if (role_is('student')) {
    $stmt = db()->prepare(
        'SELECT submissions.*, assignments.title AS assignment_title, courses.code, courses.title AS course_title
         FROM submissions
         JOIN assignments ON assignments.id = submissions.assignment_id
         JOIN courses ON courses.id = assignments.course_id
         JOIN students ON students.id = submissions.student_id
         WHERE students.user_id = ?
         ORDER BY submissions.submitted_at DESC'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
} else {
    $rows = db()->query(
        'SELECT submissions.*, assignments.title AS assignment_title, courses.code, courses.title AS course_title,
                student_user.full_name AS student_name, grader.full_name AS grader_name
         FROM submissions
         JOIN assignments ON assignments.id = submissions.assignment_id
         JOIN courses ON courses.id = assignments.course_id
         JOIN students ON students.id = submissions.student_id
         JOIN users student_user ON student_user.id = students.user_id
         LEFT JOIN users grader ON grader.id = submissions.graded_by
         ORDER BY submissions.submitted_at DESC'
    )->fetchAll();
}

render_header('Grade Management');
?>
<section class="panel">
    <h2><?= role_is('student') ? 'My Grades' : 'Submission Grades' ?></h2>
    <table>
        <thead>
        <tr>
            <?php if (!role_is('student')): ?><th>Student</th><?php endif; ?>
            <th>Course</th><th>Assignment</th><th>Grade</th><th>Status</th><th>Feedback</th>
            <?php if (role_is(['administrator', 'instructor', 'department_head'])): ?><th>Action</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php if (!role_is('student')): ?><td><?= e($row['student_name']) ?></td><?php endif; ?>
                <td><?= e($row['code']) ?><br><span class="muted"><?= e($row['course_title']) ?></span></td>
                <td><?= e($row['assignment_title']) ?></td>
                <td><?= e($row['grade'] ?? 'Pending') ?></td>
                <td><span class="badge <?= $row['approval_status'] === 'approved' ? 'ok' : 'warn' ?>"><?= e($row['approval_status']) ?></span></td>
                <td><?= e($row['feedback'] ?? '') ?></td>
                <?php if (role_is(['administrator', 'instructor', 'department_head'])): ?>
                    <td>
                        <?php if (role_is(['administrator', 'instructor'])): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="grade">
                                <input type="hidden" name="submission_id" value="<?= e($row['id']) ?>">
                                <input type="number" name="grade" step="0.01" min="0" max="100" value="<?= e($row['grade'] ?? '') ?>" required>
                                <input name="feedback" value="<?= e($row['feedback'] ?? '') ?>" placeholder="Feedback">
                                <button type="submit">Save Grade</button>
                            </form>
                        <?php endif; ?>
                        <?php if (role_is(['administrator', 'department_head']) && $row['approval_status'] !== 'approved'): ?>
                            <form method="post" style="margin-top:8px">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="submission_id" value="<?= e($row['id']) ?>">
                                <button type="submit">Approve</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

