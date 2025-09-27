<?php
// api_update_vcindex.php
declare(strict_types=1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $vcIndex = isset($data['VCIndesx']) ? (int)$data['VCIndesx'] : null;
    if ($vcIndex === null) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Missing VCIndesx']);
        exit;
    }

    $sql = "UPDATE [AhwalDataBase].[dbo].[TableSettings]
            SET VCIndesx = :vcIndex
            WHERE ID = 1";   // adjust if multiple rows exist
    $ok = $pdo->prepare($sql)->execute([':vcIndex'=>$vcIndex]);

    echo json_encode(['ok'=>$ok]);
} catch (Throwable $e) {
    error_log("api_update_vcindex.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error']);
}
