<?php
    require_once __DIR__ . '/../auth.php';
    require_admin();

    require '../connect_db.php';

    $pdo = get_naturafrica_pdo();

    // paramètres optionnels
    $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 200;
    $table = $_GET['table'] ?? ''; // ex: Action_KLCD
    $op    = $_GET['op'] ?? '';    // INSERT / UPDATE / DELETE

    $sql = "SELECT audit_id, table_name, op, changed_at, changed_by, old_row, new_row
            FROM audit_log
            WHERE 1=1";
    $params = [];

    if ($table !== '') {
        $sql .= " AND table_name = :table";
        $params[':table'] = $table;
    }
    if ($op !== '') {
        $sql .= " AND op = :op";
        $params[':op'] = $op;
    }

    $sql .= " ORDER BY audit_id DESC LIMIT :limit";

    $stmt = $pdo->prepare($sql);

    // bind limit en int (important avec PDO+PG)
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>