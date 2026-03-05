<?php
    require 'connect_db.php';

    $pdo = get_naturafrica_pdo();
    $env = getenv('APP_ENV') ?: 'dev';

    $sqlFiles = [
    "BDD_NaturAfrica.sql",
    "BDD_NaturAfrica_audit.sql",
    "BDD_NaturAfrica_indexes.sql",
    "BDD_NaturAfrica_users.sql",
    "roles.sql",
    ];

    try {
    $pdo->beginTransaction();

    if ($env === 'dev') {
        echo "[1/4] Reset schema public...\n";
        $pdo->exec('DROP SCHEMA public CASCADE;');
        $pdo->exec('CREATE SCHEMA public;');
    }

    foreach ($sqlFiles as $idx => $file) {
        if (!file_exists($file)) {
        throw new Exception("Missing SQL file: $file");
        }
        echo "[2/4] Executing $file...\n";
        $sql = file_get_contents($file);
        $pdo->exec($sql);
    }

    echo "[3/4] Databases [OK]\n";

    $pdo->commit();
    echo "[4/4] [OK] Import completed.\n";

    } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error importing SQL: " . $e->getMessage() . "\n";
    }
?>