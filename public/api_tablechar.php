<?php
// api_tablechar.php — normalized Tablechar with safe optional UpdatedAt + caching
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
    WHERE object_id = OBJECT_ID(:objname) AND type IN ('U','V')
  ");
  $chkTbl->execute([':objname' => $objName]);
  if (!$chkTbl->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => "Table $objName not found", 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // List columns to find the correct names
  $colsStmt = $pdo->prepare("
    SELECT name
    FROM sys.columns
    WHERE object_id = OBJECT_ID(:objname)
  ");
  $colsStmt->execute([':objname' => $objName]);
  $allCols = array_map(fn($r) => $r['name'], $colsStmt->fetchAll());

  // Helpers
  $hasCol = function($name) use ($allCols) {
    return in_array($name, $allCols, true);
  };

  // Primary pronoun (الضمير) — allow a few variants just in case
  $pronCol = null;
  foreach (['الضمير','ضمير','PronounType'] as $c) {
    if ($hasCol($c)) { $pronCol = $c; break; }
  }
  if (!$pronCol) $pronCol = 'الضمير'; // fallback; SELECT will fail loudly if truly missing

  // Extended pronoun — try multiple spellings & digits (ASCII '2' and Arabic-Indic '٢')
  $extCandidates = ['الضمير2','الضمير٢','ضمير2','ضمير٢','PronounTypeExt','pronounTypeExt','Pronoun2','pronoun2'];
  $extCol = null;
  foreach ($extCandidates as $c) {
    if ($hasCol($c)) { $extCol = $c; break; }
  }

  // UpdatedAt optional
  $hasUpdatedAt = $hasCol('UpdatedAt');
  $updatedAtExpr = $hasUpdatedAt ? "CONVERT(varchar(33), [UpdatedAt], 126)" : "NULL";

  // Build SELECT with the detected ext column
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
      $extSelect,
      $updatedAtExpr AS UpdatedAt
    FROM [$schema].[$table]
    ORDER BY [ID]
  ";
  $st = $pdo->query($sql);
  $rows = $st->fetchAll() ?: [];

  // Normalize
  $out = [];
  $lastModTs = null;

  foreach ($rows as $r) {
    $forms = [];
    for ($i = 1; $i <= 6; $i++) {
      $forms[] = (string)($r["F$i"] ?? '');
    }

    $out[] = [
      'id'             => (int)$r['ID'],
      'root'           => (string)$r['RootToken'],
      'forms'          => $forms,                               // [0..5] == المقابل1..6
      'pronounType'    => trim((string)$r['PronounType']),      // keep as string
      'pronounTypeExt' => trim((string)($r['PronounTypeExt'] ?? '')), // keep CSV/pipe/space
      'updatedAt'      => $r['UpdatedAt'] ?? null,
    ];

    if ($hasUpdatedAt && !empty($r['UpdatedAt'])) {
      $ts = strtotime($r['UpdatedAt']);
      if ($ts && ($lastModTs === null || $ts > $lastModTs)) $lastModTs = $ts;
    }
  }

  // Caching headers
  if ($lastModTs === null) {
    $hash = md5(json_encode($out, JSON_UNESCAPED_UNICODE));
    $etag = '"' . $hash . '"';
    $lastModHttp = gmdate('D, d M Y H:i:s', time()) . ' GMT';
  } else {
    $etag = '"' . md5("tablechar|" . count($out) . '|' . $lastModTs) . '"';
    $lastModHttp = gmdate('D, d M Y H:i:s', $lastModTs) . ' GMT';
  }

  header('Cache-Control: public, max-age=86400'); // 24h
  header('ETag: ' . $etag);
  header('Last-Modified: ' . $lastModHttp);

  $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
  $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
  if ($ifNoneMatch === $etag || $ifModifiedSince === $lastModHttp) {
    http_response_code(304);
    exit;
  }

  echo json_encode(['rows' => $out], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage(), 'rows' => []], JSON_UNESCAPED_UNICODE);
}
