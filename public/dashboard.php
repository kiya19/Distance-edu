<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/logo.php';
$user = require_login();

$row = db()->query(
    'SELECT
        (SELECT COUNT(*) FROM users)                          AS users,
        (SELECT COUNT(*) FROM courses)                        AS courses,
        (SELECT COUNT(*) FROM modules)                        AS modules,
        (SELECT COUNT(*) FROM assignments)                    AS assignments,
        (SELECT COUNT(*) FROM submissions)                    AS submissions,
        (SELECT COUNT(*) FROM payments WHERE status = "paid") AS payments_paid'
)->fetch();

$counts = [
    'Users'         => (int)$row['users'],
    'Courses'       => (int)$row['courses'],
    'Modules'       => (int)$row['modules'],
    'Assignments'   => (int)$row['assignments'],
    'Submissions'   => (int)$row['submissions'],
    'Payments Paid' => (int)$row['payments_paid'],
];

$announcements = db()->query(
    'SELECT announcements.*, users.full_name AS posted_by_name
     FROM announcements JOIN users ON users.id = announcements.posted_by
     ORDER BY announcements.created_at DESC LIMIT 5'
)->fetchAll();

$statMeta = [
    'Users'         => ['icon'=>'👥','color'=>'blue'],
    'Courses'       => ['icon'=>'📚','color'=>'green'],
    'Modules'       => ['icon'=>'📄','color'=>'amber'],
    'Assignments'   => ['icon'=>'✅','color'=>'purple'],
    'Submissions'   => ['icon'=>'📨','color'=>'rose'],
    'Payments Paid' => ['icon'=>'💳','color'=>'teal'],
];

render_header('Dashboard');
?>

<!-- ══════════════════════════════════════════════════
     DASHBOARD EXTRA STYLES + SCROLL-HIDE TOPBAR
     ══════════════════════════════════════════════════ -->
<style>
/* ── Topbar scroll-hide ── */
.topbar {
  transition: transform .32s cubic-bezier(.4,0,.2,1), box-shadow .32s;
}
.topbar.hidden {
  transform: translateY(-100%);
  box-shadow: none;
}

/* ── Scroll-reveal for sections ── */
.reveal {
  opacity: 0;
  transform: translateY(28px);
  transition: opacity .55s ease, transform .55s ease;
}
.reveal.visible {
  opacity: 1;
  transform: translateY(0);
}

/* ── Stat cards counter animation ── */
.stat-value { transition: color .3s; }

/* ── Vision / Goal / Values cards ── */
.nac-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 18px;
  margin-bottom: 24px;
}

.nac-card {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(0,0,0,.10);
  transition: transform .22s ease, box-shadow .22s ease;
  position: relative;
}
.nac-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 36px rgba(0,0,0,.15);
}

/* Vision — deep green */
.nac-card.vision {
  background: linear-gradient(145deg, #0d5c2e 0%, #1a7a40 60%, #0a3d20 100%);
  color: #fff;
}
/* Goal — rich navy-gold */
.nac-card.goal {
  background: linear-gradient(145deg, #0d1b2e 0%, #1a3354 60%, #0a1020 100%);
  color: #fff;
}
/* Values — gold warm */
.nac-card.values {
  background: linear-gradient(145deg, #7a4e0a 0%, #c8972a 60%, #5a3600 100%);
  color: #fff;
}

.nac-card-inner { padding: 26px 24px; position: relative; z-index: 1; }

/* Decorative circle */
.nac-card::before {
  content: '';
  position: absolute; top: -40px; right: -40px;
  width: 160px; height: 160px; border-radius: 50%;
  background: rgba(255,255,255,.06);
  pointer-events: none;
}
.nac-card::after {
  content: '';
  position: absolute; bottom: -30px; left: -30px;
  width: 120px; height: 120px; border-radius: 50%;
  background: rgba(255,255,255,.04);
  pointer-events: none;
}

.nac-card-badge {
  display: inline-flex; align-items: center; gap: 7px;
  background: rgba(255,255,255,.15);
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 999px;
  padding: 5px 14px;
  font-size: 11px; font-weight: 700;
  letter-spacing: 1px; text-transform: uppercase;
  color: rgba(255,255,255,.9);
  margin-bottom: 14px;
}

.nac-card h3 {
  font-family: Georgia, serif;
  font-size: 20px; font-weight: 700;
  color: #fff; margin: 0 0 12px;
  line-height: 1.25;
}

.nac-card p {
  font-size: 13.5px;
  color: rgba(255,255,255,.88);
  line-height: 1.7; margin: 0;
}

/* Values list */
.nac-values-list {
  list-style: none; display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 7px 14px; margin-top: 4px;
}
.nac-values-list li {
  font-size: 12px;
  color: rgba(255,255,255,.88);
  line-height: 1.45;
  padding-left: 14px; position: relative;
}
.nac-values-list li::before {
  content: '✦';
  position: absolute; left: 0;
  font-size: 9px; top: 2px;
  color: rgba(255,255,255,.6);
}

/* ── Announcements panel ── */
.panel { transition: box-shadow .2s; }
.panel:hover { box-shadow: 0 6px 24px rgba(13,27,46,.10); }

@media (max-width: 700px) {
  .nac-values-list { grid-template-columns: 1fr; }
  .nac-card-inner  { padding: 20px 18px; }
  .nac-card h3     { font-size: 17px; }
}
</style>

<!-- ══ STAT CARDS ══ -->
<div class="stats-grid reveal">
<?php foreach ($counts as $label => $value):
    $meta = $statMeta[$label] ?? ['icon'=>'📊','color'=>'blue'];
?>
  <div class="stat-card">
    <div class="stat-icon <?= e($meta['color']) ?>"><?= $meta['icon'] ?></div>
    <div class="stat-value" data-target="<?= $value ?>">0</div>
    <div class="stat-label"><?= e($label) ?></div>
  </div>
<?php endforeach; ?>
</div>

<!-- ══ QUICK ACCESS CARDS ══ -->
<?php $cards = role_home_cards($user['role_name']); if ($cards): ?>
<div class="cards-grid reveal" style="transition-delay:.1s">
<?php
$cardIcons=['Users'=>'👥','Courses'=>'📚','Modules'=>'📄','Assignments'=>'✅','Grades'=>'📊','Announcements'=>'📢','Payments'=>'💳','Reports'=>'📈','Feedback'=>'💬'];
foreach ($cards as $card):
  $icon = $cardIcons[$card[0]] ?? '🔗';
?>
  <div class="quick-card">
    <div class="quick-card-icon"><?= $icon ?></div>
    <h3><?= e($card[0]) ?></h3>
    <p><?= e($card[1]) ?></p>
    <a class="btn-primary" href="<?= e(url($card[2])) ?>">Open →</a>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ VISION / GOAL / VALUES — Coloured Cards ══ -->
<div class="nac-info-grid reveal" style="transition-delay:.2s">

  <!-- VISION -->
  <div class="nac-card vision">
    <div class="nac-card-inner">
      <div class="nac-card-badge">🎯 Vision</div>
      <h3>Our Vision</h3>
      <p>New Abyssinia College envisions becoming a prominent center of excellence in delivering professional education and training services — responding to the current and emerging demands of Ethiopia's labor market by 2032.</p>
    </div>
  </div>

  <!-- GOAL -->
  <div class="nac-card goal">
    <div class="nac-card-inner">
      <div class="nac-card-badge">🏆 Goal</div>
      <h3>Our Goal</h3>
      <p>To be the pioneer Excellency center — the most respectful and progressive private higher learning institution in Ethiopia, producing graduates who lead change and innovation across every sector.</p>
    </div>
  </div>

  <!-- VALUES -->
  <div class="nac-card values">
    <div class="nac-card-inner">
      <div class="nac-card-badge">⭐ Values</div>
      <h3>Our Values</h3>
      <ul class="nac-values-list">
        <li>Quality &amp; relevance of education</li>
        <li>Teamwork &amp; collaboration</li>
        <li>Academic freedom &amp; debate</li>
        <li>Education as life-long pursuit</li>
        <li>Client-centred service delivery</li>
        <li>Transparency &amp; accountability</li>
        <li>Cultural &amp; religious respect</li>
        <li>Staff growth &amp; integrity</li>
      </ul>
    </div>
  </div>

</div>

<!-- ══ LATEST ANNOUNCEMENTS ══ -->
<div class="panel reveal" style="transition-delay:.3s">
  <div class="panel-header">
    <h2>📢 Latest Announcements</h2>
    <?php if (in_array($user['role_name'],['administrator','cde_officer'],true)): ?>
      <a class="btn-primary" href="<?= e(url('announcements.php')) ?>">View All →</a>
    <?php endif; ?>
  </div>
  <div class="panel-body">
    <?php if ($announcements): ?>
    <table>
      <thead><tr><th>Title</th><th>Audience</th><th>Posted By</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($announcements as $item): ?>
        <tr>
          <td>
            <strong><?= e($item['title']) ?></strong><br>
            <span class="text-muted"><?= e(mb_substr($item['body'],0,90)) ?><?= strlen($item['body'])>90?'…':'' ?></span>
          </td>
          <td><span class="badge info"><?= e($item['audience']) ?></span></td>
          <td><?= e($item['posted_by_name']) ?></td>
          <td class="text-muted"><?= e(date('M j, Y',strtotime($item['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div style="padding:24px 18px;color:var(--text-muted);font-size:13.5px;">No announcements yet.</div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ DASHBOARD JAVASCRIPT ══ -->
<script>
(function(){

  /* ── 1. Topbar auto-hide on scroll down, show on scroll up ── */
  var topbar   = document.querySelector('.topbar');
  var lastY    = window.scrollY;
  var ticking  = false;

  window.addEventListener('scroll', function(){
    if(!ticking){
      requestAnimationFrame(function(){
        var y = window.scrollY;
        if(y > lastY && y > 80){
          topbar.classList.add('hidden');
        } else {
          topbar.classList.remove('hidden');
        }
        lastY = y;
        ticking = false;
      });
      ticking = true;
    }
  }, {passive:true});

  /* ── 2. Scroll-reveal for .reveal sections ── */
  var reveals = document.querySelectorAll('.reveal');
  var observer = new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
      if(entry.isIntersecting){
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08 });
  reveals.forEach(function(el){ observer.observe(el); });

  /* ── 3. Animated number counter for stat cards ── */
  var counters = document.querySelectorAll('.stat-value[data-target]');
  var cObserver = new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
      if(!entry.isIntersecting) return;
      var el     = entry.target;
      var target = parseInt(el.dataset.target, 10);
      var start  = 0;
      var dur    = 900;
      var step   = 16;
      var inc    = target / (dur / step);
      var timer  = setInterval(function(){
        start += inc;
        if(start >= target){
          el.textContent = target;
          clearInterval(timer);
        } else {
          el.textContent = Math.floor(start);
        }
      }, step);
      cObserver.unobserve(el);
    });
  }, { threshold: 0.5 });
  counters.forEach(function(el){ cObserver.observe(el); });

})();
</script>

<?php render_footer(); ?>
