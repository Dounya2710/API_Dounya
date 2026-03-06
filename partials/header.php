<?php
  require_once __DIR__ . '/../auth.php';
  require_once __DIR__ . '/../lang.php';
  require_login();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'KLCD Viewer') ?></title>
  <style>
    body{font-family:system-ui,Arial;margin:0}
    main{padding:16px}
    a{color:#0b57d0;text-decoration:none}
    a:hover{text-decoration:underline}
  </style>
</head>
<body>
<?php require __DIR__ . '/nav.php'; ?>
<main>
