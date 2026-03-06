<?php
  require_once __DIR__ . '/../auth.php';
  require_once __DIR__ . '/../lang.php';

  $email   = current_user_email();
  $isAdmin = current_user_is_admin();
  $role    = current_user_role();
?>

<nav style="padding:12px 16px;border-bottom:1px solid #ddd;display:flex;gap:14px;align-items:center;">

  <a href="/index.php"><?= t('accueil') ?></a>

  <a href="/dashboard.php"><?= t('dashboard') ?></a>

  <?php if ($isAdmin): ?>

    <a href="/admin/admin_home.php"><?= t('backoffice') ?></a>

    <a href="/admin/viewer_audit.html"><?= t('audit') ?></a>

    <a href="/admin/users.php"><?= t('users') ?></a>

  <?php endif; ?>

  <span style="margin-left:auto;color:#555;">
    <?= htmlspecialchars($email ?? 'guest') ?> (<?= htmlspecialchars($role) ?>)
  </span>

  <!-- sélecteur langue -->
  <span style="margin-left:20px;">
    <a href="?lang=fr">FR</a> | <a href="?lang=en">EN</a>
  </span>

  <?php if ($email): ?>
    <a href="/logout.php"><?= t('logout') ?></a>
  <?php else: ?>
    <a href="/login.php"><?= t('login') ?></a>
  <?php endif; ?>

</nav>