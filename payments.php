<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['administrator', 'finance', 'student', 'registrar']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_receipt') {
        // Student uploads a receipt screenshot which is routed to Registrar for approval
        require_role('student');
        $stmt = db()->prepare('SELECT id FROM students WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $stu = $stmt->fetch();
        if (!$stu) {
            flash('danger', 'Student record not found.');
            redirect('payments.php');
        }
        $studentId = (int) $stu['id'];
        try {
            $receiptPath = save_upload('receipt', 'receipts');
        } catch (Throwable $ex) {
            flash('danger', 'Upload failed: ' . $ex->getMessage());
            redirect('payments.php');
        }
        $amount = (float) ($_POST['amount'] ?? 0);
        $receiptNo = trim($_POST['receipt_no'] ?? '');

        $existing = db()->prepare('SELECT id FROM payments WHERE student_id = ?');
        $existing->execute([$studentId]);
        $existingId = $existing->fetchColumn();

        if ($existingId) {
            $stmt = db()->prepare('UPDATE payments SET amount = ?, status = ?, receipt_no = ?, receipt_path = ?, verified_by = NULL, receipt_uploaded_at = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$amount, 'pending', $receiptNo, $receiptPath, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $existingId]);
        } else {
            $stmt = db()->prepare('INSERT INTO payments (student_id, amount, status, receipt_no, receipt_path, verified_by, receipt_uploaded_at) VALUES (?, ?, ?, ?, ?, NULL, ?)');
            $stmt->execute([$studentId, $amount, 'pending', $receiptNo, $receiptPath, date('Y-m-d H:i:s')]);
        }
        flash('success', t('receipt_uploaded', 'Receipt uploaded and sent to Registrar for approval.'));

    } elseif ($action === 'approve_receipt') {
        // Registrar approves or blocks the uploaded receipt
        require_role(['registrar']);
        $paymentId = (int) $_POST['payment_id'];
        $newStatus = $_POST['status'] === 'blocked' ? 'blocked' : 'paid';
        $stmt = db()->prepare('UPDATE payments SET status = ?, verified_by = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$newStatus, $user['id'], date('Y-m-d H:i:s'), $paymentId]);
        flash('success', 'Payment status updated.');

    } else {
        // Legacy admin/finance flow
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
            $stmt = db()->prepare('UPDATE payments SET amount = ?, status = ?, receipt_no = ?, verified_by = ?, paid_at = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$amount, $status, $receiptNo, $user['id'], $paidAt, date('Y-m-d H:i:s'), $existingId]);
        } else {
            $stmt = db()->prepare('INSERT INTO payments (student_id, amount, status, receipt_no, verified_by, paid_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$studentId, $amount, $status, $receiptNo, $user['id'], $paidAt]);
        }
        flash('success', 'Payment status saved.');
    }

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

<?php if (role_is('student')): ?>
<section class="panel">
    <h2>Upload Payment Receipt</h2>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_receipt">
        <div class="form-grid">
            <label>Amount <input type="number" name="amount" min="0" step="0.01" required></label>
            <label>Receipt Number <input name="receipt_no"></label>
            <label>Receipt Screenshot <input type="file" name="receipt" accept="image/*"></label>
        </div>
        <button type="submit">Upload Receipt</button>
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
                <td>
                    <?php if (!empty($row['receipt_path'])): ?>
                        <a href="<?= e(url('download.php?type=receipt&id=' . $row['id'])) ?>" target="_blank">View Receipt</a>
                    <?php elseif (!empty($row['receipt_no'])): ?>
                        <?= e($row['receipt_no']) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                    <?php if (role_is('registrar') && ($row['status'] === 'pending' || $row['status'] === 'blocked') && !empty($row['receipt_path'])): ?>
                        <form method="post" style="margin-top:8px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve_receipt">
                            <input type="hidden" name="payment_id" value="<?= e($row['id']) ?>">
                            <select name="status">
                                <option value="paid">Approve (Paid)</option>
                                <option value="blocked">Block</option>
                            </select>
                            <button type="submit">Submit</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td><?= e($row['paid_at'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

