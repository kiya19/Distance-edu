<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_role(['administrator', 'cde_officer', 'instructor']);
    $stmt = db()->prepare('INSERT INTO announcements (title, body, audience, posted_by) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        trim($_POST['title']),
        trim($_POST['body']),
        $_POST['audience'],
        $user['id'],
    ]);
    flash('success', 'Announcement posted.');
    redirect('announcements.php');
}

$announcements = db()->query(
    'SELECT announcements.*, users.full_name AS posted_by_name
     FROM announcements
     JOIN users ON users.id = announcements.posted_by
     ORDER BY announcements.created_at DESC'
)->fetchAll();

render_header('Announcements and News');
?>
<?php if (role_is(['administrator', 'cde_officer', 'instructor'])): ?>
<section class="panel">
    <h2>Post Announcement</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Title <input name="title" required></label>
            <label>Audience
                <select name="audience">
                    <option value="all">All</option>
                    <option value="students">Students</option>
                    <option value="staff">Staff</option>
                </select>
            </label>
        </div>
        <label>Message <textarea name="body" required></textarea></label>
        <button type="submit">Post Announcement</button>
    </form>
</section>
<?php endif; ?>

<section class="grid" style="margin-top:16px">
    <?php foreach ($announcements as $item): ?>
        <article class="card">
            <span class="badge"><?= e($item['audience']) ?></span>
            <h3><?= e($item['title']) ?></h3>
            <p><?= e($item['body']) ?></p>
            <p class="muted">Posted by <?= e($item['posted_by_name']) ?> on <?= e($item['created_at']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
<?php render_footer(); ?>

