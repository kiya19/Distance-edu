<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role('administrator');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO users (role_id, full_name, username, email, password_hash, phone, status)
                 VALUES (?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([
                (int) $_POST['role_id'],
                trim($_POST['full_name']),
                trim($_POST['username']),
                trim($_POST['email']),
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                trim($_POST['phone'] ?? ''),
            ]);
            flash('success', 'User account created.');
        } elseif ($action === 'toggle') {
            $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
            $stmt = db()->prepare('UPDATE users SET status = ? WHERE id = ? AND id <> ?');
            $stmt->execute([$newStatus, (int) $_POST['user_id'], $user['id']]);
            flash('success', 'User status updated.');
        }
    } catch (Throwable $ex) {
        flash('danger', 'Could not save user. Check duplicate username/email and required fields.');
    }
    redirect('users.php');
}

$users = db()->query(
    'SELECT users.*, roles.label AS role_label
     FROM users
     JOIN roles ON roles.id = users.role_id
     ORDER BY roles.label, users.full_name'
)->fetchAll();

render_header('User Management');
?>
<section class="panel">
    <h2>Create User Account</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <label>Full Name <input name="full_name" required></label>
            <label>Username <input name="username" required></label>
            <label>Email <input type="email" name="email" required></label>
            <label>Phone <input name="phone"></label>
            <label>Role <select name="role_id" required><?= options(all_roles(), 'id', 'label') ?></select></label>
            <label>Initial Password <input type="password" name="password" required value="demo123"></label>
        </div>
        <button type="submit">Create Account</button>
    </form>
</section>

<section class="panel" style="margin-top:16px">
    <h2>Registered Users</h2>
    <table>
        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['username']) ?></td>
                <td><?= e($row['role_label']) ?></td>
                <td><?= e($row['email']) ?></td>
                <td><span class="badge <?= $row['status'] === 'active' ? 'ok' : 'warn' ?>"><?= e($row['status']) ?></span></td>
                <td>
                    <?php if ((int) $row['id'] !== (int) $user['id']): ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= e($row['id']) ?>">
                            <input type="hidden" name="status" value="<?= e($row['status']) ?>">
                            <button type="submit"><?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    <?php else: ?>
                        <span class="muted">Current user</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>

