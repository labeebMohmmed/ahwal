<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';
header('Content-Type: application/json; charset=utf-8');

$schema = 'dbo';
$table  = 'TableAddModel';
$name = trim((string)($_GET['name'] ?? ''));

if ($name === '') { json_response(['error'=>'Missing name'], 400); }

// verify column exists and get its type
$meta = $pdo->prepare("
  SELECT c.name AS col, t.name AS type_name
  FROM sys.columns c
  JOIN sys.types t ON c.user_type_id = t.user_type_id
  JOIN sys.objects o ON o.object_id = c.object_id
  JOIN sys.schemas s ON s.schema_id = o.schema_id
  WHERE s.name = :schema AND o.name = :table AND o.type='U' AND c.name = :name
");
$meta->execute([':schema'=>$schema, ':table'=>$table, ':name'=>$name]);
$col = $meta->fetch();
if (!$col) { json_response(['error'=>'Column not found'], 404); }

$dt = strtolower((string)$col['type_name']);
$text = in_array($dt, ['varchar','nvarchar','char','nchar','text','ntext']);

$ident = '[' . str_replace(']', ']]', $name) . ']';

try {
  // hasData
  $cond = $text
    ? "($ident IS NOT NULL AND LTRIM(RTRIM(CONVERT(nvarchar(max),$ident))) <> '')"
    : "($ident IS NOT NULL)";
  $sqlHas = "SELECT TOP 1 1 FROM [$schema].[$table] WHERE $cond";
  $has = (bool)$pdo->query($sqlHas)->fetchColumn();

  // top 3 unique values
  $valCast = $text
    ? "NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(4000),$ident))), '')"
    : "CONVERT(nvarchar(4000), $ident)";

  $sqlTop = "
    SELECT TOP (3) $valCast AS val, COUNT(*) AS cnt
    FROM [$schema].[$table]
    WHERE $cond
    GROUP BY $valCast
    ORDER BY COUNT(*) DESC
  ";
  $stTop = $pdo->query($sqlTop);
  $rows = $stTop ? $stTop->fetchAll() : [];
  json_response(['hasData'=>$has, 'top'=>array_map(fn($r)=>['value'=>$r['val'], 'count'=>(int)$r['cnt']], $rows)]);
} catch (Throwable $e) {
  json_response(['error'=>$e->getMessage()], 500);
}
