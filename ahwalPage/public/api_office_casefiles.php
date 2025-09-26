<?php
// api_office_casefiles.php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $table   = $_GET['table'] ?? '';
    $caseId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$table || $caseId <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'❌ Missing table or id']);
        exit;
    }

    $pdoArch = db('ArchFilesDB');
    $stmt = $pdoArch->prepare("
        SELECT id as FileID, المستند as Label, filename, Extension1
        FROM [ArchFilesDB].[dbo].[TableGeneralArch]
        WHERE رقم_المرجع = ? AND docTable = ?
        ORDER BY التاريخ DESC, id DESC
    ");
    $stmt->execute([$caseId, $table]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok'=>true, 'items'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
