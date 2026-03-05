<?php
    declare(strict_types=1);

    require __DIR__ . '/../auth.php';
    require_admin();

    require __DIR__ . '/../connect_db.php';
    $pdo = get_naturafrica_pdo();

    $msg = null;
    $err = null;

    // Create / update user
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $active = isset($_POST['active']) ? true : false;
        $password = (string)($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = "Email invalide.";
        } elseif ($password !== '' && strlen($password) < 8) {
            $err = "Mot de passe trop court (>= 8).";
        } else {
            // si domaine admin => force admin
            if (is_admin_email($email)) {
                $role = 'admin';
            }

            try {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO app_user(email, password_hash, role, active)
                        VALUES (:email, :hash, :role, :active)
                        ON CONFLICT (email) DO UPDATE
                        SET password_hash = EXCLUDED.password_hash,
                            role = EXCLUDED.role,
                            active = EXCLUDED.active
                    ");
                    $stmt->execute([
                        ':email'=>$email, ':hash'=>$hash, ':role'=>$role, ':active'=>$active
                    ]);
                } else {
                    // update sans changer password
                    $stmt = $pdo->prepare("
                        INSERT INTO app_user(email, password_hash, role, active)
                        VALUES (:email, :hash, :role, :active)
                        ON CONFLICT (email) DO UPDATE
                        SET role = EXCLUDED.role,
                            active = EXCLUDED.active
                    ");
                    // si insert, il faut un hash => on met un mot de passe random
                    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                    $stmt->execute([
                        ':email'=>$email, ':hash'=>$hash, ':role'=>$role, ':active'=>$active
                    ]);

                    $msg = "User upserted. (Si nouveau compte: mot de passe aléatoire → renseigne un mot de passe dans le formulaire pour le définir.)";
                }

                if (!$msg) $msg = "User upserted: $email";
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }

    $rows = $pdo->query("
        SELECT user_id, email, role, active, created_at, last_login_at
        FROM app_user
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <title>Admin — Users</title>
  <style>
    body{font-family:system-ui,Arial;margin:20px}
    .box{border:1px solid #ddd;border-radius:10px;padding:14px;margin-bottom:16px}
    input,select{padding:8px;margin:6px 0;width:320px;max-width:100%}
    button{padding:9px 14px}
    table{border-collapse:collapse;width:100%}
    th,td{border-bottom:1px solid #eee;padding:8px;text-align:left;font-size:14px}
    th{background:#f7f7f7;position:sticky;top:0}
    .ok{color:green}
    .err{color:#b00020}
    .muted{color:#666}
  </style>
</head>
<body>
  <?php require __DIR__ . '/../partials/nav.php'; ?>

  <h2>Gestion des users</h2>

  <div class="box">
    <h3>Créer / mettre à jour</h3>
    <p class="muted">Si l’email finit par <code>@visioterra.fr</code> ou <code>@agreco.be</code>, le rôle admin est forcé.</p>

    <?php if ($msg): ?><p class="ok"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <form method="post">
      <div>
        <label>Email</label><br/>
        <input type="email" name="email" required placeholder="prenom.nom@..." />
      </div>
      <div>
        <label>Rôle</label><br/>
        <select name="role">
          <option value="user">user</option>
          <option value="admin">admin</option>
        </select>
      </div>
      <div>
        <label>Mot de passe (optionnel)</label><br/>
        <input type="password" name="password" placeholder="(laisser vide pour ne pas changer)" />
      </div>
      <div>
        <label><input type="checkbox" name="active" checked /> Actif</label>
      </div>
      <button type="submit">Enregistrer</button>
    </form>
  </div>

  <div class="box">
    <h3>Comptes</h3>
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Email</th><th>Rôle</th><th>Actif</th><th>Créé</th><th>Dernier login</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['user_id'] ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['role']) ?></td>
            <td><?= ((bool)$r['active']) ? '✅' : '❌' ?></td>
            <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
            <td><?= htmlspecialchars((string)($r['last_login_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
