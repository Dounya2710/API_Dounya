<?php
    require __DIR__ . '/../auth.php';
    require_login();
    require __DIR__ . '/../connect_db.php';

    header('Content-Type: application/json; charset=utf-8');

    $limit = (int)($_GET['limit'] ?? 200);
    $limit = max(1, min($limit, 1000));

    try {
        $pdo = get_naturafrica_pdo();
        $stmt = $pdo->prepare('SELECT * FROM Action ORDER BY action_id DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['rows' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
?>