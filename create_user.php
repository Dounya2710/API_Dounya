<?php
    require_once __DIR__ . '/connect_db.php';
    require_once __DIR__ . '/auth.php';

    if (php_sapi_name() !== 'cli') {
        http_response_code(403);
        exit("CLI only\n");
    }

    $pdo = get_naturafrica_pdo();

    $email = strtolower(trim($argv[1] ?? ''));
    $pass  = (string)($argv[2] ?? '');

    if ($email === '' || $pass === '') {
        echo "Usage: php create_user.php email password\n";
        exit(1);
    }

    $role = is_admin_email($email) ? 'admin' : 'user';
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
    INSERT INTO app_user (email, password_hash, role)
    VALUES (:email, :hash, :role)
    ON CONFLICT (email) DO UPDATE
    SET password_hash = EXCLUDED.password_hash, role = EXCLUDED.role, active=true
    ");
    $stmt->execute(['email'=>$email, 'hash'=>$hash, 'role'=>$role]);

    echo "OK user upserted: $email role=$role\n";
?>