<?php
    /**
     * admin/import_csv_action.php
     *
     * Usage:
     * 1) Run local PHP server (from project root):
     *    php -S 127.0.0.1:8000
     *
     * 2) POST CSV file:
     *    curl.exe -F "csv=@Fiches_NA_DB_24_v5b.csv" http://localhost:8000/admin/import_csv_action.php
     */

    declare(strict_types=1);

    require_once __DIR__ . '/../connect_db.php';

    require_once __DIR__ . '/../auth.php';
    require_admin();

    try {
        $pdo = get_naturafrica_pdo();
        // Make PDO throw exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Throwable $e) {
        http_response_code(500);
        echo "DB connection failed: " . $e->getMessage() . "\n";
        exit;
    }

    // GET: simple upload form (useful to demo without curl)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="fr">
        <head>
            <meta charset="utf-8">
            <title>Import CSV — Action</title>
            <style>
                body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:24px}
                .box{max-width:680px;border:1px solid #ddd;border-radius:12px;padding:16px}
                input[type=file]{display:block;margin:12px 0}
                button{padding:8px 12px;border:0;border-radius:8px;cursor:pointer}
                .muted{color:#666}
            </style>
        </head>
        <body>
            <div class="box">
                <h2>Import CSV — table Action</h2>
                <p class="muted">Sélectionne le fichier CSV (ex: Fiches_NA_DB_24_v5b.csv) puis "Importer".</p>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="csv" accept=".csv,text/csv" required>
                    <button type="submit">Importer</button>
                </form>
                <hr>
                <p class="muted">Alternative (curl):<br>
                    <code>curl.exe -F "csv=@Fiches_NA_DB_24_v5b.csv" http://localhost:8000/admin/import_csv_action.php</code>
                </p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // POST only
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Use POST with a file field named 'csv'.\n";
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');


    if (!isset($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo "CSV upload failed. Expected a multipart field named 'csv'.\n";
        exit;
    }

    $tmpPath = $_FILES['csv']['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        http_response_code(400);
        echo "Invalid uploaded file.\n";
        exit;
    }

    /** Remove UTF-8 BOM if present */
    function remove_bom(string $s): string {
        return preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
    }

    /** Normalize header keys for matching */
    function norm_key(string $s): string {
        $s = remove_bom($s);
        $s = trim($s);
        // Collapse multiple spaces (including tabs)
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return mb_strtolower($s, 'UTF-8');
    }

    /** Guess delimiter by looking at the first line */
    function detect_delimiter(string $firstLine): string {
        $comma = substr_count($firstLine, ',');
        $semi  = substr_count($firstLine, ';');
        $tab   = substr_count($firstLine, "\t");

        // pick the most frequent delimiter
        $max = max($comma, $semi, $tab);
        if ($max === $tab) return "\t";
        if ($max === $semi) return ';';
        return ',';
    }

    /** Parse boolean-like cell into a strict boolean */
    function parse_bool(mixed $v): bool {
        $s = mb_strtolower(trim((string)$v), 'UTF-8');
        // treat common truthy tokens as true
        return in_array($s, ['yes', 'y', 'true', '1', 'oui'], true);
    }

    /** Parse budget field (may contain comma decimal, spaces, NBSP, currency symbols) */
    function parse_budget(mixed $v): ?float {
        $s = trim((string)$v);
        if ($s === '') return null;

        // remove non-breaking spaces and regular spaces
        $s = str_replace(["\xC2\xA0", ' '], '', $s);
        // remove common currency suffix/prefix (keep digits, comma, dot, minus)
        $s = preg_replace('/[^0-9,\.\-]/', '', $s) ?? $s;

        if ($s === '' || $s === '-' ) return null;
        // convert comma decimal to dot
        $s = str_replace(',', '.', $s);

        // If multiple dots remain (thousands separators), keep last as decimal separator
        // e.g. "1.234.56" -> "1234.56"
        $parts = explode('.', $s);
        if (count($parts) > 2) {
            $dec = array_pop($parts);
            $s = implode('', $parts) . '.' . $dec;
        }

        return is_numeric($s) ? (float)$s : null;
    }

    /** Parse date to YYYY-MM-DD (accepts many formats). Returns null if empty/unparseable. */
    function parse_date(mixed $v): ?string {
        $s = trim((string)$v);
        if ($s === '') return null;

        // Try strtotime first (handles many formats)
        $ts = strtotime($s);
        if ($ts === false) {
            // Try common FR format dd/mm/yyyy
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
                $dd = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $mm = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $yy = $m[3];
                return "$yy-$mm-$dd";
            }
            return null;
        }
        return date('Y-m-d', $ts);
    }

    $fh = fopen($tmpPath, 'rb');
    if ($fh === false) {
        http_response_code(500);
        echo "Cannot open uploaded file.\n";
        exit;
    }

    // Read first line to detect delimiter
    $firstLine = fgets($fh);
    if ($firstLine === false) {
        http_response_code(400);
        echo "Empty CSV file.\n";
        exit;
    }
    $firstLine = remove_bom($firstLine);
    $delimiter = detect_delimiter($firstLine);

    // rewind and read header with detected delimiter
    rewind($fh);
    $rawHeader = fgetcsv($fh, 0, $delimiter);
    if ($rawHeader === false) {
        http_response_code(400);
        echo "Cannot read CSV header.\n";
        exit;
    }

    // Normalize header
    $headerNorm = array_map(fn($h) => norm_key((string)$h), $rawHeader);
    $idx = array_flip($headerNorm);

    // Expected columns in your CSV (normalized)
    $required = [
        'contract title',
        'funding (m€)',
        'starting date',
        'ending date',
        'conservation',
        'green economy',
        'governance',
    ];

    $missing = [];
    foreach ($required as $col) {
        if (!array_key_exists($col, $idx)) $missing[] = $col;
    }

    if (!empty($missing)) {
        http_response_code(400);
        echo "Missing column(s): " . implode(', ', $missing) . "\n";
        echo "Detected delimiter: " . ($delimiter === "\t" ? 'tab' : $delimiter) . "\n";
        echo "Headers found (normalized):\n- " . implode("\n- ", $headerNorm) . "\n";
        fclose($fh);
        exit;
    }

    // Prepare insert statement (no action_id expected from CSV)
    $sql = '
    INSERT INTO Action
    (title, start_date, end_date, total_budget_EUR,
    biodiversity_flag, green_economy_flag, governance_flag)
    VALUES
    (:title, :start_date, :end_date, :budget,
    :biodiversity, :green, :governance)
    ';
    $stmt = $pdo->prepare($sql);

    function splitActivitiesCell(?string $cell): array {
    if ($cell === null) return [];
    $cell = trim($cell);
    if ($cell === '') return [];

    // normalise les retours ligne
    $cell = str_replace(["\r\n", "\r"], "\n", $cell);

    // sépare par lignes, ignore lignes vides
    $lines = array_values(array_filter(array_map('trim', explode("\n", $cell)), fn($x) => $x !== ''));

    // certaines cellules ont des lignes vides entre chaque item (déjà filtrées)
    return $lines;
}

/**
 * Parse: "C1 - Patrolling and surveillance"
 * Retourne: ['id' => 'C1', 'label' => 'Patrolling and surveillance', 'sector' => 'C']
 */
function parseActivityLine(string $line, string $expectedSector): ?array {
    // Exemple attendu: C1 - XXX
    // On accepte aussi "C1-XXX" ou "C1 – XXX"
    $line = trim($line);

    // remplace tiret long par tiret normal
    $line = str_replace(["–", "—"], "-", $line);

    if (!str_contains($line, '-')) {
        return null;
    }

    [$left, $right] = array_map('trim', explode('-', $line, 2));
    if ($left === '' || $right === '') return null;

    $activityId = strtoupper($left);
    $sector = strtoupper(substr($activityId, 0, 1));

    // sécurité: si incohérence (ex: colonne Conservation mais activité E13)
    if ($sector !== strtoupper($expectedSector)) {
        // on peut soit refuser, soit accepter
        // return null;
    }

    return [
        'id' => $activityId,
        'label' => $right,
        'sector' => $sector,
    ];
}

function upsertActivity(PDO $pdo, string $activityId, string $sectorId, string $label): void {
        $sql = <<<SQL
    INSERT INTO Activity(activity_id, sector_id, activity_label, activity_description)
    VALUES (:id, :sector, :label, NULL)
    ON CONFLICT (activity_id)
    DO UPDATE SET
    sector_id = EXCLUDED.sector_id,
    activity_label = EXCLUDED.activity_label;
    SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $activityId,
            ':sector' => $sectorId,
            ':label' => $label,
        ]);
}

function linkActionActivity(PDO $pdo, int $actionId, string $activityId, bool $isPrimary=false): void {
        $sql = <<<SQL
    INSERT INTO Action_Activity(action_id, activity_id, is_primary)
    VALUES (:action_id, :activity_id, :is_primary)
    ON CONFLICT (action_id, activity_id) DO NOTHING;
    SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':action_id' => $actionId,
            ':activity_id' => $activityId,
            ':is_primary' => $isPrimary ? 't' : 'f',
        ]);
}

    $map = [
    'C' => 'Conservation',
    'E' => 'Green Economy',
    'G' => 'Governance',
    ];

    foreach ($map as $sector => $colName) {
        $cell = $row[$colName] ?? null;
        $lines = splitActivitiesCell($cell);

        $first = true;
        foreach ($lines as $line) {
            $parsed = parseActivityLine($line, $sector);
            if ($parsed === null) continue;

            $activityId = $parsed['id'];      // ex: C1
            $label = $parsed['label'];        // ex: Patrolling and surveillance
            $sectorId = $parsed['sector'];    // C / E / G

            upsertActivity($pdo, $activityId, $sectorId, $label);
            linkActionActivity($pdo, $actionId, $activityId, $first); // première activité = primary
            $first = false;
        }
    }

    $imported = 0;
    $skipped = 0;
    $lineNo = 1; // header is line 1

    // Optional: wrap import in a transaction for speed and rollback on failure
    $pdo->beginTransaction();

    try {
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $lineNo++;

            // Guard: if row is empty array or first col empty
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                $skipped++;
                continue;
            }

            $title = trim((string)($row[$idx['contract title']] ?? ''));
            if ($title === '') {
                $skipped++;
                continue;
            }

            $budget = parse_budget($row[$idx['funding (m€)']] ?? null);
            $start_date = parse_date($row[$idx['starting date']] ?? null);
            $end_date   = parse_date($row[$idx['ending date']] ?? null);

            $biodiversity = parse_bool($row[$idx['conservation']] ?? '');
            $green        = parse_bool($row[$idx['green economy']] ?? '');
            $governance   = parse_bool($row[$idx['governance']] ?? '');

            // Bind with correct types (avoid '' -> boolean issues)
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);

            if ($start_date === null) $stmt->bindValue(':start_date', null, PDO::PARAM_NULL);
            else $stmt->bindValue(':start_date', $start_date, PDO::PARAM_STR);

            if ($end_date === null) $stmt->bindValue(':end_date', null, PDO::PARAM_NULL);
            else $stmt->bindValue(':end_date', $end_date, PDO::PARAM_STR);

            if ($budget === null) $stmt->bindValue(':budget', null, PDO::PARAM_NULL);
            else $stmt->bindValue(':budget', $budget);

            $stmt->bindValue(':biodiversity', $biodiversity, PDO::PARAM_BOOL);
            $stmt->bindValue(':green', $green, PDO::PARAM_BOOL);
            $stmt->bindValue(':governance', $governance, PDO::PARAM_BOOL);

            $stmt->execute();
            $imported++;
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo "Import failed at CSV line {$lineNo}.\n";
        echo "SQLSTATE: " . ($e->errorInfo[0] ?? 'n/a') . "\n";
        echo "Message: " . $e->getMessage() . "\n";
        fclose($fh);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo "Import failed at CSV line {$lineNo}: " . $e->getMessage() . "\n";
        fclose($fh);
        exit;
    }

    fclose($fh);

    echo "Import successful.\n";
    echo "Detected delimiter: " . ($delimiter === "\t" ? 'tab' : $delimiter) . "\n";
    echo "Rows inserted into Action: {$imported}\n";
    echo "Rows skipped (empty title/empty line): {$skipped}\n";
?>