<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'finance', 'student']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['administrator', 'finance']);

    $studentId  = (int) $_POST['student_id'];
    $amount     = (float) $_POST['amount'];
    $status     = $_POST['status'];
    $receiptNo  = trim($_POST['receipt_no'] ?? '');
    $paidAt     = $_POST['paid_at'] ?: null;

    $existing = db()->prepare('SELECT id FROM payments WHERE student_id = ?');
    $existing->execute([$studentId]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        $stmt = db()->prepare(
            'UPDATE payments SET amount = ?, status = ?, receipt_no = ?, verified_by = ?, paid_at = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$amount, $status, $receiptNo, $user['id'], $paidAt, date('Y-m-d H:i:s'), $existingId]);
    } else {
        $stmt = db()->prepare(
            'INSERT INTO payments (student_id, amount, status, receipt_no, verified_by, paid_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$studentId, $amount, $status, $receiptNo, $user['id'], $paidAt]);
    }
    flash('success', 'Payment status saved.');
    redirect('payments.php');
}

if (role_is('student')) {
    $stmt = db()->prepare(
        'SELECT payments.*, users.full_name AS student_name, students.student_no
         FROM payments
         JOIN students ON students.id = payments.student_id
         JOIN users ON users.id = students.user_id
         WHERE students.user_id = ?
         ORDER BY payments.updated_at DESC, payments.id DESC'
    );
    $stmt->execute([$user['id']]);
    $payments = $stmt->fetchAll();
} else {
    $payments = db()->query(
        'SELECT payments.*, users.full_name AS student_name, students.student_no
         FROM payments
         JOIN students ON students.id = payments.student_id
         JOIN users ON users.id = students.user_id
         ORDER BY payments.updated_at DESC, payments.id DESC'
    )->fetchAll();
}

render_header('Payment Control');
?>
<?php if (role_is(['administrator', 'finance'])): ?>
<section class="panel">
    <h2>Record Payment Status</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Student <select name="student_id" required><?= options(students(), 'id', 'full_name') ?></select></label>
            <label>Amount <input type="number" name="amount" min="0" step="0.01" required></label>
            <label>Status
                <select name="status">
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="blocked">Blocked</option>
                </select>
            </label>
            <label>Receipt Number <input name="receipt_no"></label>
            <label>Paid Date <input type="date" name="paid_at"></label>
        </div>
        <button type="submit">Save Payment</button>
    </form>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:16px">
    <h2><?= role_is('student') ? 'My Payment Status' : 'Payment Records' ?></h2>
    <table>
        <thead><tr><th>Student</th><th>Student No.</th><th>Amount</th><th>Status</th><th>Receipt</th><th>Paid Date</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $row): ?>
            <tr>
                <td><?= e($row['student_name']) ?></td>
                <td><?= e($row['student_no']) ?></td>
                <td><?= e(number_format((float) $row['amount'], 2)) ?></td>
                <td><span class="badge <?= $row['status'] === 'paid' ? 'ok' : ($row['status'] === 'blocked' ? 'danger' : 'warn') ?>"><?= e($row['status']) ?></span></td>
                <td><?= e($row['receipt_no'] ?? '') ?></td>
                <td><?= e($row['paid_at'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

