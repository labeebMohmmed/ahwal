<?php
declare(strict_types=1);

ini_set('display_errors', '1'); // TEMP
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();

header('Content-Type: application/json; charset=utf-8');

try {
    // 1) ping
    $ping = $pdo->query('SELECT 1 AS ok')->fetch();

    // 2) confirm table exists in this DB/schema
    $row = $pdo->query("
        SELECT TOP 1 TABLE_SCHEMA, TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_NAME = 'TableAddModel'
        ORDER BY TABLE_SCHEMA
    ")->fetch();

    // 3) try read one row (may be empty)
    $one = $pdo->query("SELECT TOP 1 * FROM [dbo].[TableAddModel]")->fetch();

    echo json_encode([
        'php'   => PHP_VERSION,
        'driver'=> $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        'ping'  => $ping,
        'table' => $row ?: 'NOT FOUND',
        'sample'=> $one ?: 'NO ROWS',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
