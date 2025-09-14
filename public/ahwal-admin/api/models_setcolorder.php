<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();
header('Content-Type: application/json; charset=utf-8');

$schema = 'dbo';
$table  = 'TableAddModel';

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$order = (array)($in['order'] ?? []);

if (!$order) { json_response(['error'=>'Missing order'], 400); }

// validate all names exist on the table
$stCols = $pdo->prepare("
  SELECT c.name
  FROM sys.columns c
  JOIN sys.objects o ON o.object_id=c.object_id
  JOIN sys.schemas s ON s.schema_id=o.schema_id
  WHERE s.name=:s AND o.name=:t AND o.type='U'
");
$stCols->execute([':s'=>$schema, ':t'=>$table]);
$existing = array_column($stCols->fetchAll() ?: [], 'name');
$existingSet = array_flip($existing);

// Keep only real columns; de-dup
$filtered = [];
foreach ($order as $n) {
  $n = (string)$n;
  if (isset($existingSet[$n]) && !in_array($n, $filtered, true)) $filtered[] = $n;
}
// Append any columns not listed (so nothing is lost)
foreach ($existing as $n) if (!in_array($n, $filtered, true)) $filtered[] = $n;

try {
  $pdo->beginTransaction();
  $del = $pdo->prepare("DELETE FROM dbo.AdminColumnOrder WHERE TableName = :t");
  $del->execute([':t' => $table]);

  $ins = $pdo->prepare("INSERT INTO dbo.AdminColumnOrder (TableName, ColumnName, Ord) VALUES (:t, :c, :o)");
  foreach ($filtered as $i => $colName) {
    $ins->execute([':t'=>$table, ':c'=>$colName, ':o'=>$i+1]);
  }
  $pdo->commit();

  json_response(['ok'=>true, 'order'=>$filtered]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_response(['error'=>$e->getMessage()], 500);
}
