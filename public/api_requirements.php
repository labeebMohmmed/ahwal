<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // ---- 1) Input ----
    $modelId = $_GET['modelId'] ?? $_GET['templateId'] ?? null;
    if ($modelId === null || !preg_match('/^\d+$/', $modelId)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing or invalid modelId/templateId']);
        exit;
    }
    $modelId = (int)$modelId;

    // ---- 2) Connect (SQLSRV PDO) ----
    // Adjust server, DB, auth as needed
    $conn = db();

    // ---- 3) Query ----
    // We only need المطلوب_رقم1..9 from dbo.TableProcReq where ModelID = ?
    $sql = "
        SELECT
            [المطلوب_رقم1], [المطلوب_رقم2], [المطلوب_رقم3],
            [المطلوب_رقم4], [المطلوب_رقم5], [المطلوب_رقم6],
            [المطلوب_رقم7], [المطلوب_رقم8], [المطلوب_رقم9]
        FROM [dbo].[TableProcReq]
        WHERE [ModelID] = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$modelId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }

    // ---- 4) Filter non-empty & not 'غير مدرج' ----
    $items = [];
    foreach ($row as $k => $v) {
        if ($v === null) continue;
        $s = trim((string)$v);
        if ($s === '' || $s === 'غير مدرج') continue;
        $items[] = $s;
    }

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Log internal error; send safe message to client
    error_log('api_requirements.php error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error'], JSON_UNESCAPED_UNICODE);
}
