<?php
  require_once __DIR__ . '/../auth.php';
  $email   = current_user_email();
  $isAdmin = current_user_is_admin();
  $role    = current_user_role();
?>
<nav style="padding:12px 16px;border-bottom:1px solid #ddd;display:flex;gap:14px;align-items:center;">
  <a href="/index.php">Accueil</a>
  <a href="/dashboard.php">Dashboard</a>

  <?php if ($isAdmin): ?>
    <a href="/admin/admin_home.php">Backoffice</a>
    <a href="/admin/viewer_audit.html">Audit</a>
    <a href="/admin/users.php">Users</a>
  <?php endif; ?>

  <span style="margin-left:auto;color:#555;">
    <?= htmlspecialchars($email ?? 'guest') ?> (<?= htmlspecialchars($role) ?>)
  </span>

  <?php if ($email): ?>
    <a href="/logout.php">Logout</a>
  <?php else: ?>
    <a href="/login.php">Login</a>
  <?php endif; ?>
</nav>
