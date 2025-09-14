<?php
require __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$altColName = $_GET['altColName'] ?? '';
$altColName = trim($altColName);
if ($altColName === '') {
    http_response_code(400);
    echo json_encode(['error' => 'altColName is required']);
    exit;
}

$dsn = "sqlsrv:Server=localhost;Database=AhwalDataBase;Encrypt=yes;TrustServerCertificate=yes";

try {
    $pdo = db();

    $sql = "
        SELECT DISTINCT [altSubColName]
        FROM [dbo].[TableAddModel]
        WHERE [altColName] = :ac
          AND [altSubColName] IS NOT NULL
          AND LTRIM(RTRIM([altSubColName])) <> ''
        ORDER BY [altSubColName]
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':ac' => $altColName]);
    $rows = $st->fetchAll();

    $values = array_map(fn($r) => $r['altSubColName'], $rows);
    echo json_encode(['values' => $values], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
