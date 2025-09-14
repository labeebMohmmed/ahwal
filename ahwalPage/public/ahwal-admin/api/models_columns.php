<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();
header('Content-Type: application/json; charset=utf-8');

$schema = 'dbo';
$table  = 'TableAddModel';

try {
  $sql = "
    SELECT c.name AS column_name,
           t.name AS data_type,
           c.max_length,
           COLUMNPROPERTY(c.object_id, c.name, 'IsIdentity') AS is_identity
    FROM sys.columns c
    JOIN sys.types t ON c.user_type_id = t.user_type_id
    JOIN sys.objects o ON o.object_id = c.object_id
    JOIN sys.schemas s ON s.schema_id = o.schema_id
    WHERE s.name = :schema AND o.name = :table AND o.type = 'U'
    ORDER BY c.column_id
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':schema'=>$schema, ':table'=>$table]);
  $cols = $st->fetchAll() ?: [];
  json_response(['columns'=>$cols]);
} catch (Throwable $e) {
  json_response(['error'=>$e->getMessage()], 500);
}
