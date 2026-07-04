<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'student', 'cde_officer', 'registrar']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (role_is('student')) {
        $studentStmt = db()->prepare('SELECT id FROM students WHERE user_id = ?');
        $studentStmt->execute([$user['id']]);
        $studentId = (int) $studentStmt->fetchColumn();
        $stmt = db()->prepare('INSERT INTO feedback (student_id, subject, message) VALUES (?, ?, ?)');
        $stmt->execute([$studentId, trim($_POST['subject']), trim($_POST['message'])]);
        flash('success', 'Feedback sent.');
    } else {
        $stmt = db()->prepare('UPDATE feedback SET status = ?, response = ? WHERE id = ?');
        $stmt->execute([$_POST['status'], trim($_POST['response'] ?? ''), (int) $_POST['feedback_id']]);
        flash('success', 'Feedback updated.');
    }
    redirect('feedback.php');
}

if (role_is('student')) {
    $stmt = db()->prepare(
        'SELECT feedback.*, users.full_name AS student_name
         FROM feedback
         JOIN students ON students.id = feedback.student_id
         JOIN users ON users.id = students.user_id
         WHERE students.user_id = ?
         ORDER BY feedback.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
} else {
    $rows = db()->query(
        'SELECT feedback.*, users.full_name AS student_name
         FROM feedback
         JOIN students ON students.id = feedback.student_id
         JOIN users ON users.id = students.user_id
         ORDER BY feedback.created_at DESC'
    )->fetchAll();
}

render_header('Feedback');
?>
<?php if (role_is('student')): ?>
<section class="panel">
    <h2>Send Feedback</h2>
    <form method="post">
        <?= csrf_field() ?>
        <label>Subject <input name="subject" required></label>
        <label>Message <textarea name="message" required></textarea></label>
        <button type="submit">Send Feedback</button>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:16px">
    <h2><?= role_is('student') ? 'My Feedback' : 'Student Feedback' ?></h2>
    <table>
        <thead><tr><th>Student</th><th>Subject</th><th>Message</th><th>Status</th><th>Response</th><?php if (!role_is('student')): ?><th>Action</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['student_name']) ?></td>
                <td><?= e($row['subject']) ?></td>
                <td><?= e($row['message']) ?></td>
                <td><span class="badge"><?= e($row['status']) ?></span></td>
                <td><?= e($row['response'] ?? '') ?></td>
                <?php if (!role_is('student')): ?>
                <td>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="feedback_id" value="<?= e($row['id']) ?>">
                        <select name="status">
                            <option value="open" <?= $row['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="reviewed" <?= $row['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                            <option value="closed" <?= $row['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                        <input name="response" value="<?= e($row['response'] ?? '') ?>" placeholder="Response">
                        <button type="submit">Update</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

