<?php
  declare(strict_types=1);
  require __DIR__ . '/auth.php';
  require_login();
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <title>KLCD Viewer</title>
  <style>
    body{font-family:system-ui,Arial;margin:0}
    .wrap{padding:18px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
    .card{border:1px solid #ddd;border-radius:12px;padding:14px}
    a.btn{display:inline-block;margin-top:10px;padding:10px 12px;border:1px solid #ccc;border-radius:10px;text-decoration:none}
    .muted{color:#666}
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/nav.php'; ?>
  <div class="wrap">
    <h2>Accueil</h2>
    <p class="muted">
      Mode <b><?= htmlspecialchars(current_user_role()) ?></b>.
      Les users voient uniquement le dashboard. Les admins ont accès au backoffice.
    </p>

    <div class="grid">
      <div class="card">
        <h3>Dashboard</h3>
        <p>Camemberts + indicateurs (sans données sensibles).</p>
        <a class="btn" href="/dashboard.php">Ouvrir</a>
      </div>

      <?php if (current_user_is_admin()): ?>
      <div class="card">
        <h3>Backoffice</h3>
        <p>Visualisation des tables + import/export + audit.</p>
        <a class="btn" href="/admin/admin_home.php">Ouvrir</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
