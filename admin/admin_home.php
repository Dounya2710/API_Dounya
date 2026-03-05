<?php
  declare(strict_types=1);
  require __DIR__ . '/../auth.php';
  require_admin();
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <title>Admin — Backoffice</title>
  <style>
    body{font-family:system-ui,Arial;margin:0}
    .wrap{padding:18px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
    .card{border:1px solid #ddd;border-radius:12px;padding:14px}
    a.btn{display:inline-block;margin-top:10px;padding:10px 12px;border:1px solid #ccc;border-radius:10px;text-decoration:none}
  </style>
</head>
<body>
<?php require __DIR__ . '/../partials/nav.php'; ?>

<div class="wrap">
  <h2>Backoffice</h2>

  <div class="grid">
    <div class="card">
      <h3>KLCD</h3>
      <a class="btn" href="/admin/viewer_table.html?table=KLCD">KLCD</a>
      <a class="btn" href="/admin/viewer_table.html?table=Location">Location</a>
      <a class="btn" href="/admin/viewer_table.html?table=KLCD_Location">KLCD_Location</a>
      <a class="btn" href="/admin/viewer_table.html?table=ProtectedArea">ProtectedArea</a>
    </div>

    <div class="card">
      <h3>Pillar</h3>
      <a class="btn" href="/admin/viewer_table.html?table=Pillar">Pillar</a>
    </div>

    <div class="card">
      <h3>Actions</h3>
      <a class="btn" href="/admin/viewer_actions.html">Actions</a>
      <a class="btn" href="/admin/viewer_table.html?table=Action_KLCD">Action_KLCD</a>
      <a class="btn" href="/admin/viewer_table.html?table=Action_Activity">Action_Activity</a>
      <a class="btn" href="/admin/viewer_table.html?table=Action_Implementer">Action_Implementer</a>
      
    </div>

    <div class="card">
      <h3>Activity</h3>
      <a class="btn" href="/admin/viewer_table.html?table=Activity">Activity</a>
      <a class="btn" href="/admin/viewer_table.html?table=ActivitySector">Activity Sector</a>
    </div>

    <div class="card">
      <h3>Institution</h3>
      <a class="btn" href="/admin/viewer_table.html?table=Institution">Institution</a>
    </div>

	    <div class="card">
	      <h3>Imports</h3>
	      <p style="margin:0;color:#555">Actions (CSV officiel) + futurs imports.</p>
	      <a class="btn" href="/admin/import_csv_action.php">Importer Actions (CSV)</a>
	    </div>

    <div class="card">
      <h3>Historique</h3>
      <a class="btn" href="/admin/viewer_audit.html">Audit (HTML)</a>
      <a class="btn" href="/admin/audit_log.php">Audit (JSON)</a>
    </div>

    <div class="card">
      <h3>Gestion des users</h3>
      <a class="btn" href="/admin/users.php">Users</a>
    </div>
  </div>
</div>
</body>
</html>
