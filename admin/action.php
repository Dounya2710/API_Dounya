<?php
    ini_set('display_errors', '1');
    error_reporting(E_ALL);

    require_once __DIR__ . '/../auth.php';
    require_admin();

    require_once __DIR__ . '/../connect_db.php';
    header('Content-Type: application/json; charset=utf-8');


    try {
        $pdo = get_naturafrica_pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = <<<SQL
    SELECT * FROM Action ORDER BY action_id
    SQL;

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // jsonb_agg arrive parfois en string selon driver => on force decode si besoin
       /* foreach ($rows as &$r) {
            if (is_string($r['activities'])) {
                $r['activities'] = json_decode($r['activities'], true) ?? [];
            }
        }*/

        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // curl.exe -i http://localhost:8000/admin/action.php
?>