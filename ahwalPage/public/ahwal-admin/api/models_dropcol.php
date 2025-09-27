<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';
header('Content-Type: application/json; charset=utf-8');

$schema = 'dbo';
$table  = 'TableAddModel';

$in  = json_decode(file_get_contents('php://input'), true) ?? [];
$col = trim((string)($in['name'] ?? ''));

if ($col === '') { echo json_encode(['error'=>'Missing name']); http_response_code(400); exit; }

try {
  // one-liner drop (bracket-escape ])
  $ident = '[' . str_replace(']', ']]', $col) . ']';
  $sql = "ALTER TABLE [$schema].[$table] DROP COLUMN $ident";
  $pdo->exec($sql);
  echo json_encode(['ok'=>true, 'dropped'=>$col], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  // if it fails due to default/index, report the message (run the T-SQL prep above once, then retry)
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
