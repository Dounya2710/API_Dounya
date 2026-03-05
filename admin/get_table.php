<?php
    declare(strict_types=1);

    require __DIR__ . '/../auth.php';
    require_admin();

    require __DIR__ . '/../connect_db.php';
    $pdo = get_naturafrica_pdo();

    $allowed = [
    'KLCD','Location','KLCD_Location','ProtectedArea',
    'Action','Action_KLCD','Action_Activity', 'Action_Implementer',
    'Institution','EU_Programme','MemberState',
    'audit_log'
    ];

    $table = $_GET['table'] ?? '';
    if (!in_array($table, $allowed, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'invalid table']);
    exit;
    }

    $limit = (int)($_GET['limit'] ?? 1000);
    $limit = max(1, min($limit, 5000));

    $sql = 'SELECT * FROM "' . $table . '" LIMIT ' . $limit;
    $stmt = $pdo->query($sql);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['table'=>$table,'limit'=>$limit,'rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
?>