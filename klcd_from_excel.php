<?php
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED);

    // --- CONFIG ---
    $csvFile = __DIR__ . '/Fiches_NA_DB_24_v5b.csv';
    $delimiter = ';';

    header('Content-Type: application/json; charset=utf-8');

    // Check if the file exists
    if (!file_exists($csvFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'CSV file not found']);
        exit;
    }

    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to open CSV file']);
        exit;
    }

    // Read header
    $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
    $headers = array_map('trim', $headers);

    $data = [];

    // Read all lines
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {

        if (count(array_filter($row, 'strlen')) === 0) {
            continue; // ignore blank lines
        }

        $item = [];

        foreach ($headers as $i => $key) {
            if ($key === '') continue;
            $item[$key] = $row[$i] ?? null;
        }

        $data[] = $item;
    }

    fclose($handle);

    // JSON response
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>