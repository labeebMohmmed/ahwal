<?php
require __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin', '*');

$alt = isset($_GET['altColName']) ? trim($_GET['altColName']) : '';
$sub = isset($_GET['altSubColName']) ? trim($_GET['altSubColName']) : '';

if ($alt === '' || $sub === '') {
    http_response_code(400);
    echo json_encode(['error' => 'altColName and altSubColName are required']);
    exit;
}


try {
    $pdo = db();

    $sql = "
        SELECT TOP (1) *
        FROM [dbo].[TableAddModel]
        WHERE [altColName] = :alt
          AND [altSubColName] = :sub
        ORDER BY [ID] DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':alt' => $alt, ':sub' => $sub]);
    $row = $st->fetch();

    if (!$row) {
        echo json_encode(['row' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['row' => $row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
