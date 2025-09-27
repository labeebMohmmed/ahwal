<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
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
    // ---- 3) Query ----
    // We only need المطلوب_رقم1..9 from dbo.TableProcReq where ModelID = ?
    $sql = "
        SELECT p.[المطلوب_رقم1], p.[المطلوب_رقم2], p.[المطلوب_رقم3],
                    p.[المطلوب_رقم4], p.[المطلوب_رقم5], p.[المطلوب_رقم6],
                    p.[المطلوب_رقم7], p.[المطلوب_رقم8], p.[المطلوب_رقم9]
        FROM dbo.TableAddModel AS m
        LEFT JOIN dbo.TableProcReq AS p
        ON p.المعاملة COLLATE Arabic_100_CI_AI_KS
            = (m.altColName COLLATE Arabic_100_CI_AI_KS
                + N'-'
                + m.altSubColName COLLATE Arabic_100_CI_AI_KS)
        where m.id = ?
    ";
    $stmt = $pdo->prepare($sql);
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
