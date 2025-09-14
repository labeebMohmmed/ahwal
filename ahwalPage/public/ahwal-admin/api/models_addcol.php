<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();
header('Content-Type: application/json; charset=utf-8');

$schema = 'dbo';
$table  = 'TableAddModel';

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$name = trim((string)($in['name'] ?? ''));
$typeSpec = isset($in['typeSpec']) ? trim((string)$in['typeSpec']) : null; // optional override
$length = isset($in['length']) ? (int)$in['length'] : null;                // optional hint for nvarchar

if ($name === '') json_response(['error'=>'Missing name'], 400);

// ---- Whitelist helpers
function allowed_name(string $n): bool {
  // itext1–10 (+Length)
  if (preg_match('/^itext(10|[1-9])(Length)?$/i', $n)) return true;
  // icombo1–5 (+Option/+Length)
  if (preg_match('/^icombo([1-5])(Option|Length)?$/i', $n)) return true;
  // icheck1–5 (+Option)
  if (preg_match('/^icheck([1-5])(Option)?$/i', $n)) return true;
  // itxtDate1–5
  if (preg_match('/^itxtDate([1-5])$/i', $n)) return true;
  // ibtnAdd1 (+Length)
  if (preg_match('/^ibtnAdd1(Length)?$/i', $n)) return true;
  // standalone allowed
  if (in_array($n, ['Lang','altColName','altSubColName','ReqID'], true)) return true;
  return false;
}
function safe_ident(string $n): string {
  return '[' . str_replace(']', ']]', $n) . ']';
}
// Return SQL type for a whitelisted name
function default_type_for(string $n, ?int $length): string {
  $nl = strtolower($n);
  $len = ($length && $length > 0) ? $length : 255;

  if ($nl === 'reqid') return 'INT NULL';
  if ($nl === 'lang') return 'NVARCHAR(10) NULL';
  if ($nl === 'altcolname' || $nl === 'altsubcolname') return 'NVARCHAR(255) NULL';

  if (preg_match('/^itext(10|[1-9])$/', $nl))         return "NVARCHAR($len) NULL";
  if (preg_match('/^itext(10|[1-9])length$/', $nl))   return "INT NULL";

  if (preg_match('/^icombo([1-5])$/', $nl))           return "NVARCHAR($len) NULL";
  if (preg_match('/^icombo([1-5])length$/', $nl))     return "INT NULL";
  if (preg_match('/^icombo([1-5])option$/', $nl))     return "NVARCHAR(MAX) NULL";

  if (preg_match('/^icheck([1-5])$/', $nl))           return "NVARCHAR($len) NULL";
  if (preg_match('/^icheck([1-5])option$/', $nl))     return "NVARCHAR(MAX) NULL";

  if (preg_match('/^itxtdate([1-5])$/', $nl))         return "NVARCHAR(255) NULL";

  if ($nl === 'ibtnadd1')                             return "NVARCHAR($len) NULL";
  if ($nl === 'ibtnadd1length')                       return "INT NULL";

  // fallback (shouldn’t hit due to whitelist)
  return "NVARCHAR(255) NULL";
}
// optional override type guard (allow only a few safe base types)
function sanitize_typespec(?string $ts): ?string {
  if (!$ts) return null;
  $ts = strtoupper(preg_replace('/\s+/', '', $ts));
  if (preg_match('/^(NVARCHAR\(\d+\)|NVARCHAR\(MAX\)|VARCHAR\(\d+\)|INT|BIGINT|BIT|DATE|DATETIME2\(\d+\)|DATETIME2)$/', $ts)) {
    return $ts . ' NULL';
  }
  return null;
}

try {
  if (!allowed_name($name)) {
    json_response(['error'=>'Name not allowed by whitelist'], 400);
  }

  // check exists
  $chk = $pdo->prepare("
    SELECT 1
    FROM sys.columns c
    JOIN sys.objects o ON o.object_id = c.object_id
    JOIN sys.schemas s ON s.schema_id = o.schema_id
    WHERE s.name = :schema AND o.name = :table AND c.name = :name
  ");
  $chk->execute([':schema'=>$schema, ':table'=>$table, ':name'=>$name]);
  if ($chk->fetch()) {
    json_response(['error'=>'Column already exists'], 409);
  }

  $sqlType = sanitize_typespec($typeSpec) ?? default_type_for($name, $length);
  $sql = "ALTER TABLE [$schema].[$table] ADD " . safe_ident($name) . " $sqlType";
  $pdo->exec($sql);

  // special case: ReqID → ensure index exists
  if (strcasecmp($name, 'ReqID') === 0) {
    $idx = "IX_{$table}_ReqID";
    $pdo->exec("
      IF NOT EXISTS (
        SELECT 1 FROM sys.indexes i
        JOIN sys.objects o ON o.object_id = i.object_id
        JOIN sys.schemas s ON s.schema_id = o.schema_id
        WHERE s.name = '$schema' AND o.name = '$table' AND i.name = '$idx'
      )
      CREATE INDEX [$idx] ON [$schema].[$table]([ReqID]);
    ");
  }

  json_response(['ok'=>true, 'added'=>$name, 'type'=>$sqlType]);
} catch (Throwable $e) {
  json_response(['error'=>$e->getMessage()], 500);
}
