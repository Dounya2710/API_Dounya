<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../connect_db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Public dashboard: anyone logged-in can see charts (no sensitive row-level data).
    // We still require a session to avoid exposing the instance publicly by accident.
    require_login();

    $pdo = get_naturafrica_pdo();

    $by = trim((string)($_GET['by'] ?? ''));

    // Optional filter: KLCD (landscape) id
    $klcd_id = null;
    if (isset($_GET['klcd_id']) && $_GET['klcd_id'] !== '') {
        $klcd_id = (int)$_GET['klcd_id'];
        if ($klcd_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => "Invalid parameter 'klcd_id'"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Return [{name, value}, ...] from a grouped count query.
     */
    $as_pie = function (PDOStatement $stmt): array {
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = (string)($r['name'] ?? '');
            $value = (int)($r['value'] ?? 0);
            if ($name === '' || $value <= 0) {
                continue;
            }
            $out[] = ['name' => $name, 'value' => $value];
        }
        return $out;
    };

    /**
     * Keep the top N slices and group the rest into "Autres".
     */
    $top_with_others = function (array $items, int $topN = 10, string $othersLabel = 'Autres'): array {
        $norm = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $name = (string)($it['name'] ?? '');
            $value = (int)($it['value'] ?? 0);
            if ($name === '' || $value <= 0) continue;
            $norm[] = ['name' => $name, 'value' => $value];
        }
        usort($norm, fn($a, $b) => ($b['value'] <=> $a['value']));
        if (count($norm) <= $topN) return $norm;

        $top = array_slice($norm, 0, $topN);
        $others = 0;
        foreach (array_slice($norm, $topN) as $r) {
            $others += (int)$r['value'];
        }
        if ($others > 0) {
            $top[] = ['name' => $othersLabel, 'value' => $others];
        }
        return $top;
    };

    $run_pie = function (string $sql, array $params = []) use ($pdo, $as_pie): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $as_pie($stmt);
    };

    $run_row = function (string $sql, array $params = []) use ($pdo): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    };

    // ---- KLCD list for selector (client)
    $get_klcd_list = function () use ($run_pie): array {
        // We return "name/value" to reuse $as_pie shape? No.
        // For the selector, return [{id, name}, ...].
        return [];
    };

    // ---- Programme (robust, landscape-aware)
    $get_programme = function (?int $klcd_id) use ($run_pie): array {
        $params = [];
        $join = '';
        $where = '';
        if ($klcd_id !== null) {
            $join = 'JOIN Action_KLCD ak ON ak.action_id = a.action_id';
            $where = 'WHERE ak.klcd_id = :klcd_id';
            $params[':klcd_id'] = $klcd_id;
        }

        $sql = "
            SELECT
                CASE
                    WHEN a.programme_id IS NULL THEN 'Programme non renseigné'
                    ELSE CONCAT('Programme ', a.programme_id::text)
                END AS name,
                COUNT(*)::int AS value
            FROM Action a
            $join
            $where
            GROUP BY 1
            ORDER BY value DESC, name ASC
        ";
        return $run_pie($sql, $params);
    };

    // ---- Pillar distribution (landscape-aware)
    $get_pillar = function (?int $klcd_id) use ($run_pie): array {
        $params = [];
        $join_klcd = '';
        $where = '';
        if ($klcd_id !== null) {
            $join_klcd = 'JOIN Action_KLCD ak ON ak.action_id = aa.action_id';
            $where = 'WHERE ak.klcd_id = :klcd_id';
            $params[':klcd_id'] = $klcd_id;
        }

        $sql = "
            SELECT
                p.pillar_name AS name,
                COUNT(DISTINCT aa.action_id)::int AS value
            FROM Action_Activity aa
            $join_klcd
            JOIN Activity a ON a.activity_id = aa.activity_id
            JOIN ActivitySector s ON s.sector_id = a.sector_id
            JOIN Pillar p ON p.pillar_code = s.pillar_code
            $where
            GROUP BY 1
            ORDER BY value DESC, name ASC
        ";
        return $run_pie($sql, $params);
    };

    // ---- Implementers by institution (compat key 'donor') (landscape-aware)
    $get_implementers = function (?int $klcd_id) use ($run_pie, $top_with_others): array {
        $params = [];
        $join = '';
        $where = '';
        if ($klcd_id !== null) {
            $join = 'JOIN Action_KLCD ak ON ak.action_id = ai.action_id';
            $where = 'WHERE ak.klcd_id = :klcd_id';
            $params[':klcd_id'] = $klcd_id;
        }

        $sql = "
            SELECT
                COALESCE(NULLIF(i.short_name, ''), i.name) AS name,
                COUNT(DISTINCT ai.action_id)::int AS value
            FROM Action_Implementer ai
            $join
            JOIN Institution i ON i.institution_id = ai.institution_id
            $where
            GROUP BY 1
            ORDER BY value DESC, name ASC
        ";
        $items = $run_pie($sql, $params);
        return $top_with_others($items, 10, 'Autres');
    };

    // ---- Flags distribution (landscape-aware)
    $get_flags = function (?int $klcd_id) use ($run_row): array {
        $params = [];
        $join = '';
        $where = '';
        if ($klcd_id !== null) {
            $join = 'JOIN Action_KLCD ak ON ak.action_id = a.action_id';
            $where = 'WHERE ak.klcd_id = :klcd_id';
            $params[':klcd_id'] = $klcd_id;
        }

        $sql = "
            SELECT
                SUM(CASE WHEN a.biodiversity_flag THEN 1 ELSE 0 END)::int AS biodiversity,
                SUM(CASE WHEN a.green_economy_flag THEN 1 ELSE 0 END)::int AS green_economy,
                SUM(CASE WHEN a.governance_flag THEN 1 ELSE 0 END)::int AS governance
            FROM Action a
            $join
            $where
        ";
        $r = $run_row($sql, $params);

        return [
            ['name' => 'Biodiversity', 'value' => (int)($r['biodiversity'] ?? 0)],
            ['name' => 'Green economy', 'value' => (int)($r['green_economy'] ?? 0)],
            ['name' => 'Governance', 'value' => (int)($r['governance'] ?? 0)],
        ];
    };

    // ---- KLCD list endpoint
    $get_klcd_list_rows = function () use ($pdo): array {
        $sql = "
            SELECT
                klcd_id::int AS id,
                COALESCE(NULLIF(klcd_name, ''), CONCAT('KLCD ', klcd_id::text)) AS name
            FROM KLCD
            ORDER BY name ASC
        ";
        $stmt = $pdo->query($sql);
        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }
        return $out;
    };

    // Allowed `by` values (plus klcd_list)
    $allowed = ['', 'pillar', 'programme', 'donor', 'flags', 'klcd_list'];
    if (!in_array($by, $allowed, true)) {
        http_response_code(400);
        echo json_encode([
            'error' => "Invalid parameter 'by'",
            'allowed' => ['pillar', 'programme', 'donor', 'flags', 'klcd_list']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($by === 'klcd_list') {
        echo json_encode(['data' => $get_klcd_list_rows()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($by === '') {
        echo json_encode([
            'filter'    => ['klcd_id' => $klcd_id],
            'programme' => $get_programme($klcd_id),
            'pillar'    => $get_pillar($klcd_id),
            'donor'     => $get_implementers($klcd_id), // compatibility: implementers by institution
            'flags'     => $get_flags($klcd_id),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($by) {
        case 'programme':
            $data = $get_programme($klcd_id);
            break;
        case 'pillar':
            $data = $get_pillar($klcd_id);
            break;
        case 'donor':
            $data = $get_implementers($klcd_id);
            break;
        case 'flags':
            $data = $get_flags($klcd_id);
            break;
        default:
            $data = [];
    }

    echo json_encode(['by' => $by, 'filter' => ['klcd_id' => $klcd_id], 'data' => $data], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
