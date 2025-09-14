<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();
// TEMP while debugging
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    $skip = max(0, (int)($_GET['skip'] ?? 0));
    $take = (int)($_GET['take'] ?? 10);
    $take = max(1, min($take, 200));
    $q    = trim((string)($_GET['q'] ?? ''));

    $schema = 'dbo';
    $table  = 'TableAddModel';

    // --- Discover columns using sys catalogs (more reliable than INFORMATION_SCHEMA with limited perms)
    $sqlCols = "
      SELECT c.name AS COLUMN_NAME, t.name AS DATA_TYPE, c.column_id
      FROM sys.columns c
      JOIN sys.types t ON c.user_type_id = t.user_type_id
      JOIN sys.objects o ON o.object_id = c.object_id
      JOIN sys.schemas s ON s.schema_id = o.schema_id
      WHERE s.name = :schema AND o.name = :table AND o.type = 'U'
      ORDER BY c.column_id
    ";
    $stmtCols = $pdo->prepare($sqlCols);
    $stmtCols->execute([':schema' => $schema, ':table' => $table]);
    $cols = $stmtCols->fetchAll();

    if (!$cols) {
        json_response(['error' => "Could not read columns for {$schema}.{$table}. Check schema/table name and permissions."], 500);
    }

    $colNames = array_map(fn($c) => $c['COLUMN_NAME'], $cols);

    // --- Choose a safe ORDER BY column
    $orderCol = null;
    if (in_array('ID', $colNames, true)) {
        $orderCol = 'ID';
    } else {
        // avoid text/ntext/image; prefer int/bigint/uniqueidentifier/datetime/nvarchar/varchar
        $preferred = ['bigint','int','uniqueidentifier','datetime','datetime2','date','smalldatetime','nvarchar','varchar','nchar','char'];
        foreach ($cols as $c) {
            $dt = strtolower((string)$c['DATA_TYPE']);
            if (in_array($dt, $preferred, true)) {
                $orderCol = $c['COLUMN_NAME'];
                break;
            }
        }
        // fallback to first column name if nothing matched
        $orderCol ??= $colNames[0];
    }

    // --- Build search WHERE (only on text-like cols; skip if none or q empty)
    $textTypes = ['varchar','nvarchar','char','nchar','text','ntext'];
    $likeCols  = array_values(array_map(fn($c) => $c['COLUMN_NAME'],
        array_filter($cols, fn($c) => in_array(strtolower((string)$c['DATA_TYPE']), $textTypes, true))
    ));

    $where = '';
    $params = [];
    if ($q !== '' && $likeCols) {
        $parts = [];
        foreach ($likeCols as $cn) {
            $parts[] = '[' . str_replace(']', ']]', $cn) . '] LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $where = 'WHERE ' . implode(' OR ', $parts);
    }

    // --- Total count
    $sqlCount = "SELECT COUNT(*) AS cnt FROM [$schema].[$table] $where";
    $stmt = $pdo->prepare($sqlCount);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // --- Page data (SQL Server 2012+)
$sqlRows = "
  SELECT *
  FROM [$schema].[$table]
  $where
  ORDER BY [" . str_replace(']', ']]', $orderCol) . "]
  OFFSET CAST(? AS INT) ROWS FETCH NEXT CAST(? AS INT) ROWS ONLY
";
$paramsPage = $params;
$paramsPage[] = (int)$skip;
$paramsPage[] = (int)$take;

$stmt2 = $pdo->prepare($sqlRows);
$stmt2->execute($paramsPage);
$rows = $stmt2->fetchAll() ?: [];


    json_response([
        'columns' => $colNames,
        'total'   => $total,
        'skip'    => $skip,
        'take'    => $take,
        'rows'    => $rows,
        // TEMP diagnostics (remove later):
        'diag'    => ['orderCol' => $orderCol, 'likeCols' => $likeCols],
    ]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
