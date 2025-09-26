<?php
// api_office_details_upsert.php — update only the language column (اللغة)

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
    $langValue = isset($data['lang']) ? trim((string)$data['lang']) : null; // <-- expect { lang: "ar"|"en" }
    $mainGroup = trim((string)($data['mainGroup'] ?? 'توكيل'));

    if ($caseId <= 0 || $langValue === null || $langValue === '') {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad payload','input'=>$data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = db();

    // Decide target table
    $table = ($mainGroup === 'توكيل') ? 'dbo.TableAuth' : 'dbo.TableCollection';

    // Perform update
    $sql = "UPDATE $table SET [اللغة] = :lang WHERE ID = :id";
    $ok  = $pdo->prepare($sql)->execute([
        ':lang' => $langValue,
        ':id'   => $caseId
    ]);

    echo json_encode([
        'ok' => (bool)$ok,
        'table' => $table,
        'caseId' => $caseId,
        'lang' => $langValue
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_office_details_upsert.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Internal error','msg'=>$e->getMessage()]);
}
