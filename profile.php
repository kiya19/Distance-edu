<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $stmt = db()->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->execute([
            trim($_POST['full_name']),
            trim($_POST['email']),
            trim($_POST['phone'] ?? ''),
            $user['id'],
        ]);
        flash('success', 'Profile updated.');
    } elseif ($action === 'password') {
        if (!verify_user_password($user, $_POST['current_password'] ?? '')) {
            flash('danger', 'Current password is incorrect.');
            redirect('profile.php');
        }
        if (strlen($_POST['new_password'] ?? '') < 6) {
            flash('danger', 'New password must be at least 6 characters.');
            redirect('profile.php');
        }
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user['id']]);
        flash('success', 'Password changed.');
    }
    redirect('profile.php');
}

render_header('Profile');
?>
<section class="grid">
    <div class="panel">
        <h2>Update Profile</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <label>Full Name <input name="full_name" value="<?= e($user['full_name']) ?>" required></label>
            <label>Email <input type="email" name="email" value="<?= e($user['email']) ?>" required></label>
            <label>Phone <input name="phone" value="<?= e($user['phone'] ?? '') ?>"></label>
            <button type="submit">Save Profile</button>
        </form>
    </div>
    <div class="panel">
        <h2>Change Password</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label>Current Password <input type="password" name="current_password" required></label>
            <label>New Password <input type="password" name="new_password" required></label>
            <button type="submit">Change Password</button>
        </form>
    </div>
</section>
<?php render_footer(); ?>

