<?php
    require __DIR__ . '/../auth.php';
    require_login();

    require __DIR__ . '/../connect_db.php';
    $pdo = get_naturafrica_pdo();

    $ALLOWED = [
    'KLCD',
    'Location',
    'KLCD_Location',
    'ProtectedArea',
    'Institution',
    'Action',
    'Action_KLCD',
    'Action_Activity',
    'Action_Implementer',
    'Activity',
    'ActivitySector',
    'Pillar',
    'audit_log'
    ];

    $table = $_GET['table'] ?? '';
    if (!in_array($table, $ALLOWED, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid table']);
    exit;
    }

    $limit  = (int)($_GET['limit'] ?? 200);
    $offset = (int)($_GET['offset'] ?? 0);
    $limit  = max(1, min($limit, 2000));
    $offset = max(0, $offset);

    $q = trim((string)($_GET['q'] ?? ''));

    try {
    // Récupère les colonnes (pour le front dynamique)
    $colsStmt = $pdo->prepare("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = :t
        ORDER BY ordinal_position
    ");
    $colsStmt->execute([':t' => strtolower($table)]);
    $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$columns) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['table' => $table, 'columns' => [], 'rows' => [], 'total' => 0]);
        exit;
    }

    // Si q est fourni, on filtre en ILIKE sur les colonnes textuelles (simple & robuste)
    $where = '';
    $params = [];
    if ($q !== '') {
        $likes = [];
        foreach ($columns as $c) {
        // cast texte pour pouvoir chercher partout sans se prendre la tête
        $likes[] = "\"$c\"::text ILIKE :q";
        }
        $where = 'WHERE ' . implode(' OR ', $likes);
        $params[':q'] = '%' . $q . '%';
    }

    // Total
    $countSql = "SELECT COUNT(*) FROM $table $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Rows paginées
    $sql = "SELECT * FROM $table $where OFFSET :offset LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'table' => $table,
        'columns' => $columns,
        'rows' => $rows,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()]);
    }
?>