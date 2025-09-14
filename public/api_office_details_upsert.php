<?php
// api_office_details_upsert.php — save itextN/itxtDateN/icomboN/icheckN to dbo.TableAuth
declare(strict_types=1);

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    // Keep same key name "caseId" (maps to TableAuth.ID)
    $caseId = isset($data['caseId']) ? (int)$data['caseId'] : 0;
    $patch  = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : null;

    if ($caseId <= 0 || $patch === null) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = db();

    // Whitelist: itext#, itxtDate#, icombo#, icheck#
    $filtered = [];
    foreach ($patch as $k => $v) {
        $key = (string)$k;
        if (!preg_match('/^(itext\d+|itxtDate\d+|icombo\d+|icheck\d+)$/i', $key)) continue;
        if (is_string($v) && trim($v) === '') $v = null; // normalize empty → NULL
        $filtered[$key] = $v;
    }
    if (!count($filtered)) {
        echo json_encode(['ok'=>true,'noop'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Optional: ensure columns actually exist on TableAuth (defensive)
    $colsStmt = $pdo->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='TableAuth'
    ");
    $existingCols = array_flip(array_map('strval', array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME')));
    foreach (array_keys($filtered) as $c) {
        if (!isset($existingCols[$c])) unset($filtered[$c]);
    }
    if (!count($filtered)) {
        echo json_encode(['ok'=>true,'noop'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Build UPDATE
    $sets   = [];
    $params = [':id' => $caseId];
    foreach ($filtered as $col => $val) {
        $p = ':c_' . substr(md5($col), 0, 12);
        $sets[] = "[$col] = $p";
        $params[$p] = $val;
    }
    $sql = "UPDATE dbo.TableAuth SET " . implode(', ', $sets) . " WHERE ID = :id";
    $ok  = $pdo->prepare($sql)->execute($params);

    echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_office_details_upsert.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
