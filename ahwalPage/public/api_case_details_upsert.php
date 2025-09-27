<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $caseId = isset($data['caseId']) ? (int)$data['caseId'] : 0;
    $patch  = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : null;

    if ($caseId <= 0 || $patch === null) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // whitelist keys
    $filtered = [];
    foreach ($patch as $k => $v) {
        $key = (string)$k;
        if (!preg_match('/^(itext\d+|itxtDate\d+|icheck\d+|icombo\d+)$/i', $key)) {
            continue;
        }
        if (is_string($v) && trim($v) === '') $v = null;
        $filtered[$key] = $v;
    }
    if (!count($filtered)) {
        echo json_encode(['ok'=>true, 'noop'=>true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // DB connect
    $SCHEMA = 'online';

    // Load
    $st = $pdo->prepare("SELECT DetailsJson FROM [$SCHEMA].[Cases] WHERE CaseID=?");
    $st->execute([$caseId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'case not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $details = $row['DetailsJson'] ? json_decode($row['DetailsJson'], true) : [];
    if (!is_array($details)) $details = [];
    if (!isset($details['answers']) || !is_array($details['answers'])) $details['answers'] = [];
    if (!isset($details['answers']['fields']) || !is_array($details['answers']['fields'])) $details['answers']['fields'] = [];

    foreach ($filtered as $k => $v) {
        $details['answers']['fields'][$k] = $v;
    }
    $details['answers']['_touchedAt'] = date('c');

    $up = $pdo->prepare("UPDATE [$SCHEMA].[Cases] SET DetailsJson=? WHERE CaseID=?");
    $ok = $up->execute([ json_encode($details, JSON_UNESCAPED_UNICODE), $caseId ]);

    echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_case_details_upsert.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
