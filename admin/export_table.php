<?php
    require __DIR__ . '/../auth.php';
    require_login();

    require __DIR__ . '/../connect_db.php';
    $pdo = get_naturafrica_pdo();

    $allowed = ['Action','KLCD','Location','KLCD_Location','ProtectedArea','Institution', 'Pillar', 'Action_Implementer', 'Action_Activity', 'Action_KLCD', 'ActivitySector', 'audit_log'];
    $table = $_GET['table'] ?? '';

    if (!in_array($table, $allowed, true)) {
    http_response_code(400);
    echo "Invalid table.\n";
    exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$table.'_export.csv"');

    $out = fopen('php://output', 'w');

    $sql = "SELECT * FROM $table";
    $stmt = $pdo->query($sql);
    $first = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$first) { exit; }

    fputcsv($out, array_keys($first));
    fputcsv($out, array_values($first));

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, array_values($row));
    }
    fclose($out);
?>