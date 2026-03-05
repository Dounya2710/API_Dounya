<?php
  declare(strict_types=1);

  require __DIR__ . '/connect_db.php';
  require __DIR__ . '/auth.php';

  $error = null;

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $email = strtolower(trim($_POST['email'] ?? ''));
      $pass  = (string)($_POST['password'] ?? '');

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $error = "Email invalide.";
      } elseif ($pass === '') {
          $error = "Mot de passe requis.";
      } else {
          try {
              $pdo = get_naturafrica_pdo();

              $stmt = $pdo->prepare("
                  SELECT email, password_hash, role, active
                  FROM app_user
                  WHERE email = :email
                  LIMIT 1
              ");
              $stmt->execute([':email' => $email]);
              $u = $stmt->fetch(PDO::FETCH_ASSOC);

              if (!$u) {
                  $error = "Compte inconnu. Demande à un admin de te créer un accès.";
              } elseif (!(bool)$u['active']) {
                  $error = "Compte désactivé.";
              } elseif (!password_verify($pass, (string)$u['password_hash'])) {
                  $error = "Mot de passe incorrect.";
              } else {
                  // OK login
                  login_user((string)$u['email'], (string)$u['role']);

                  $pdo->prepare("UPDATE app_user SET last_login_at = now() WHERE email = :email")
                      ->execute([':email' => $email]);

                  header('Location: /index.php');
                  exit;
              }
          } catch (Throwable $e) {
              $error = "Erreur login: " . $e->getMessage();
          }
      }
  }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"/>
  <title>Login - KLCD</title>
  <style>
    body{font-family:system-ui,Arial;margin:40px}
    .box{max-width:420px;padding:20px;border:1px solid #ddd;border-radius:10px}
    input{width:90%;padding:10px;margin:8px 0}
    button{padding:10px 14px}
    .err{color:#b00020}
    .muted{color:#666;font-size:.95em}
  </style>
</head>
<body>
  <div class="box">
    <h2>Connexion</h2>
    <p class="muted">Admin si domaine <code>@agreco.be</code> ou <code>@visioterra.fr</code> (ou rôle admin en base).</p>

    <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="post" autocomplete="on">
      <label>Email</label>
      <input name="email" type="email" required placeholder="prenom.nom@..." value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

      <label>Mot de passe</label>
      <input name="password" type="password" required>

      <button type="submit">Se connecter</button>
    </form>
  </div>
</body>
</html>
