<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'registrar', 'academic_vp', 'college_dean', 'department_head', 'finance', 'instructor', 'cde_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'schedule') {
        require_role(['administrator', 'registrar', 'cde_officer']);
        $stmt = db()->prepare('INSERT INTO schedules (title, event_type, event_date, details, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            trim($_POST['title']),
            $_POST['event_type'],
            $_POST['event_date'],
            trim($_POST['details'] ?? ''),
            $user['id'],
        ]);
        flash('success', 'Schedule saved.');
    } elseif ($action === 'load') {
        require_role(['administrator', 'department_head', 'college_dean']);
        $stmt = db()->prepare('INSERT INTO employee_loads (user_id, period_label, hours_worked, status, submitted_by) VALUES (?, ?, ?, "submitted", ?)');
        $stmt->execute([
            (int) $_POST['user_id'],
            trim($_POST['period_label']),
            (float) $_POST['hours_worked'],
            $user['id'],
        ]);
        flash('success', 'Employee load submitted.');
    }
    redirect('reports.php');
}

$summary = [
    'Active users' => (int) db()->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn(),
    'Courses' => (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'Published modules' => (int) db()->query('SELECT COUNT(*) FROM modules')->fetchColumn(),
    'Assignments' => (int) db()->query('SELECT COUNT(*) FROM assignments')->fetchColumn(),
    'Approved grades' => (int) db()->query('SELECT COUNT(*) FROM submissions WHERE approval_status = "approved"')->fetchColumn(),
    'Paid students' => (int) db()->query('SELECT COUNT(DISTINCT student_id) FROM payments WHERE status = "paid"')->fetchColumn(),
];

$gradeRows = db()->query(
    'SELECT courses.code, assignments.title, student_user.full_name AS student_name, submissions.grade, submissions.approval_status
     FROM submissions
     JOIN assignments ON assignments.id = submissions.assignment_id
     JOIN courses ON courses.id = assignments.course_id
     JOIN students ON students.id = submissions.student_id
     JOIN users student_user ON student_user.id = students.user_id
     ORDER BY courses.code, student_user.full_name'
)->fetchAll();

$paymentRows = db()->query(
    'SELECT users.full_name, students.student_no, payments.amount, payments.status, payments.receipt_no
     FROM payments
     JOIN students ON students.id = payments.student_id
     JOIN users ON users.id = students.user_id
     ORDER BY users.full_name'
)->fetchAll();

$schedules = db()->query(
    'SELECT schedules.*, users.full_name AS created_by_name
     FROM schedules
     JOIN users ON users.id = schedules.created_by
     ORDER BY schedules.event_date ASC'
)->fetchAll();

$staff = db()->query(
    'SELECT users.id, users.full_name
     FROM users
     JOIN roles ON roles.id = users.role_id
     WHERE roles.name IN ("instructor","cde_officer","registrar","department_head")
     ORDER BY users.full_name'
)->fetchAll();

$loads = db()->query(
    'SELECT employee_loads.*, users.full_name AS employee_name, submitter.full_name AS submitted_by_name
     FROM employee_loads
     JOIN users ON users.id = employee_loads.user_id
     JOIN users submitter ON submitter.id = employee_loads.submitted_by
     ORDER BY employee_loads.created_at DESC'
)->fetchAll();

render_header('Reports and Schedules');
?>
<section class="grid">
    <?php foreach ($summary as $label => $value): ?>
        <div class="card">
            <div class="metric"><?= e($value) ?></div>
            <p><?= e($label) ?></p>
        </div>
    <?php endforeach; ?>
</section>

<?php if (role_is(['administrator', 'registrar', 'cde_officer'])): ?>
<section class="panel" style="margin-top:16px">
    <h2>Prepare Academic Schedule</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="schedule">
        <div class="form-grid">
            <label>Title <input name="title" required></label>
            <label>Event Type
                <select name="event_type">
                    <option value="registration">Registration</option>
                    <option value="module">Module</option>
                    <option value="assignment">Assignment</option>
                    <option value="exam">Exam</option>
                    <option value="academic">Academic</option>
                </select>
            </label>
            <label>Date <input type="date" name="event_date" required></label>
        </div>
        <label>Details <textarea name="details"></textarea></label>
        <button type="submit">Save Schedule</button>
    </form>
</section>
<?php endif; ?>

<?php if (role_is(['administrator', 'department_head', 'college_dean'])): ?>
<section class="panel" style="margin-top:16px">
    <h2>Submit Employee Load Time</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="load">
        <div class="form-grid">
            <label>Employee <select name="user_id" required><?= options($staff, 'id', 'full_name') ?></select></label>
            <label>Period <input name="period_label" required value="March 2026"></label>
            <label>Hours Worked <input type="number" name="hours_worked" min="0" step="0.5" required></label>
        </div>
        <button type="submit">Submit Load</button>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:16px">
    <h2>Academic Schedule</h2>
    <table>
        <thead><tr><th>Title</th><th>Type</th><th>Date</th><th>Details</th><th>Created By</th></tr></thead>
        <tbody>
        <?php foreach ($schedules as $row): ?>
            <tr>
                <td><?= e($row['title']) ?></td>
                <td><span class="badge"><?= e($row['event_type']) ?></span></td>
                <td><?= e($row['event_date']) ?></td>
                <td><?= e($row['details']) ?></td>
                <td><?= e($row['created_by_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="grid" style="margin-top:16px">
    <div class="panel">
        <h2>Grade Report</h2>
        <table>
            <thead><tr><th>Course</th><th>Assignment</th><th>Student</th><th>Grade</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($gradeRows as $row): ?>
                <tr>
                    <td><?= e($row['code']) ?></td>
                    <td><?= e($row['title']) ?></td>
                    <td><?= e($row['student_name']) ?></td>
                    <td><?= e($row['grade'] ?? 'Pending') ?></td>
                    <td><span class="badge <?= $row['approval_status'] === 'approved' ? 'ok' : 'warn' ?>"><?= e($row['approval_status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h2>Payment Report</h2>
        <table>
            <thead><tr><th>Student</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php foreach ($paymentRows as $row): ?>
                <tr>
                    <td><?= e($row['full_name']) ?><br><span class="muted"><?= e($row['student_no']) ?></span></td>
                    <td><?= e(number_format((float) $row['amount'], 2)) ?></td>
                    <td><span class="badge <?= $row['status'] === 'paid' ? 'ok' : 'warn' ?>"><?= e($row['status']) ?></span></td>
                    <td><?= e($row['receipt_no']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:16px">
    <h2>Employee Load Summary</h2>
    <table>
        <thead><tr><th>Employee</th><th>Period</th><th>Hours</th><th>Status</th><th>Submitted By</th></tr></thead>
        <tbody>
        <?php foreach ($loads as $row): ?>
            <tr>
                <td><?= e($row['employee_name']) ?></td>
                <td><?= e($row['period_label']) ?></td>
                <td><?= e($row['hours_worked']) ?></td>
                <td><span class="badge"><?= e($row['status']) ?></span></td>
                <td><?= e($row['submitted_by_name']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

