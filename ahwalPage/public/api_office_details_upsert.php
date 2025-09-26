<?php
// api_office_details_upsert.php — save itextN/itxtDateN/icomboN/icheckN
// to dbo.TableAuth (for mainGroup='توكيل') or dbo.TableCollection (else)

declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']);
        exit;
    }

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $caseId    = isset($data['caseId']) ? (int)$data['caseId'] : 0;
    $patch     = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : null;
    $mainGroup = trim((string)($data['mainGroup'] ?? 'توكيل'));

    if ($caseId <= 0 || $patch === null) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad payload','input'=>$data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = db();

    // Decide table + mapping
    if ($mainGroup === 'توكيل') {
        $table = 'dbo.TableAuth';
        $map = fn($k) => $k; // same names
    } else {
        $table = 'dbo.TableCollection';
        $map = function($k) {
            if (preg_match('/^itext(\d+)$/i', $k, $m)) return 'Vitext'.$m[1];
            if (preg_match('/^icombo(\d+)$/i', $k, $m)) return 'Vicombo'.$m[1];
            if (preg_match('/^icheck(\d+)$/i', $k, $m)) return 'Vicheck'.$m[1];
            if (preg_match('/^itxtDate(\d+)$/i', $k, $m)) return 'VitxtDate'.$m[1];
            return null; // ignore
        };
    }

    // Filter + remap keys
    $filtered = [];
    foreach ($patch as $k => $v) {
        $col = $map($k);
        if (!$col) continue;
        if (is_string($v) && trim($v) === '') $v = null;
        $filtered[$col] = $v;
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
    $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE ID = :id";
    $ok  = $pdo->prepare($sql)->execute($params);

    echo json_encode([
        'ok' => (bool)$ok,
        'table' => $table,
        'caseId' => $caseId,
        'updatedCols' => array_keys($filtered)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_office_details_upsert.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Internal error','msg'=>$e->getMessage()]);
}
