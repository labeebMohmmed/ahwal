<?php
// api_tablechar.php — normalized Tablechar with safe caching (no UpdatedAt column needed)
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  $schema  = 'dbo';
  $table   = 'Tablechar';
  $objName = "$schema.$table";

  // Verify table exists
  $chkTbl = $pdo->prepare("
    SELECT 1
    FROM sys.objects
    WHERE object_id = OBJECT_ID(:objname) AND type = 'U'
  ");
  $chkTbl->execute([':objname' => $objName]);
  if (!$chkTbl->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => "Table $objName not found", 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // List columns
  $colsStmt = $pdo->prepare("
    SELECT name
    FROM sys.columns
    WHERE object_id = OBJECT_ID(:objname)
  ");
  $colsStmt->execute([':objname' => $objName]);
  $allCols = array_map(fn($r) => $r['name'], $colsStmt->fetchAll());

  $hasCol = fn($c) => in_array($c, $allCols, true);

  // Pronoun columns
  $pronCol = null;
  foreach (['الضمير','ضمير','PronounType'] as $c) {
    if ($hasCol($c)) { $pronCol = $c; break; }
  }
  if (!$pronCol) $pronCol = 'الضمير'; // fallback

  $extCol = null;
  foreach (['الضمير2','الضمير٢','ضمير2','ضمير٢','PronounTypeExt'] as $c) {
    if ($hasCol($c)) { $extCol = $c; break; }
  }

  $extSelect = $extCol ? ", [$extCol] AS PronounTypeExt" : ", CAST(NULL AS nvarchar(64)) AS PronounTypeExt";

  $sql = "
    SELECT 
      [ID],
      [الرموز]    AS RootToken,
      [المقابل1]  AS F1,
      [المقابل2]  AS F2,
      [المقابل3]  AS F3,
      [المقابل4]  AS F4,
      [المقابل5]  AS F5,
      [المقابل6]  AS F6,
      [$pronCol]  AS PronounType
      $extSelect
    FROM [$schema].[$table]
    ORDER BY [ID]
  ";
  $st = $pdo->query($sql);
  $rows = $st->fetchAll() ?: [];

  $out = [];
  foreach ($rows as $r) {
    $forms = [];
    for ($i = 1; $i <= 6; $i++) {
      $forms[] = (string)($r["F$i"] ?? '');
    }
    $out[] = [
      'id'             => (int)$r['ID'],
      'root'           => (string)$r['RootToken'],
      'forms'          => $forms,
      'pronounType'    => trim((string)$r['PronounType']),
      'pronounTypeExt' => trim((string)($r['PronounTypeExt'] ?? '')),
    ];
  }

  // Caching: compute hash from result
  $hash = md5(json_encode($out, JSON_UNESCAPED_UNICODE));
  $etag = '"' . $hash . '"';
  $lastModHttp = gmdate('D, d M Y H:i:s') . ' GMT'; // just now, since no UpdatedAt

  header('Cache-Control: public, max-age=86400');
  header('ETag: ' . $etag);
  header('Last-Modified: ' . $lastModHttp);

  $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
  if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
  }

  echo json_encode(['rows' => $out], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage(), 'rows' => []], JSON_UNESCAPED_UNICODE);
}
