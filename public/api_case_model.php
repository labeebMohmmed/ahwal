<?php
// api_case_model.php — return only ID by (altColName, altSubColName)
// Adds Unicode-safe compare, fully-qualified table, and ?debug=1 support.

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$altCol = isset($_GET['altCol']) ? (string)$_GET['altCol'] : '';
$altSub = isset($_GET['altSub']) ? (string)$_GET['altSub'] : '';
$debug  = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($altCol === '' || $altSub === '') {
  http_response_code(400);
  echo json_encode(['id'=>0,'error'=>'altCol and altSub are required'], JSON_UNESCAPED_UNICODE);
  exit;
}

$pdo = db(); // ensure db() sets PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8

// Light normalization (helps with accidental double spaces / Arabic comma)
$norm = static function (string $s): string {
  $s = str_replace('،', ',', $s);
  $s = preg_replace('/\s+/u', ' ', $s);
  return trim($s);
};
$altColN = $norm($altCol);
$altSubN = $norm($altSub);

// Fully-qualified table to avoid DB context mismatches
$table = '[AhwalDataBase].[dbo].[TableAddModel]';

// Exact match; convert parameters to NVARCHAR to force Unicode comparison (N'')
$sql = "
  SELECT TOP 1 ID
  FROM $table
  WHERE LTRIM(RTRIM(altColName))    = LTRIM(RTRIM(CONVERT(NVARCHAR(400), :altCol)))
    AND LTRIM(RTRIM(altSubColName)) = LTRIM(RTRIM(CONVERT(NVARCHAR(400), :altSub)))
  ORDER BY ID DESC;
";

try {
  $st = $pdo->prepare($sql);
  $st->execute([':altCol' => $altColN, ':altSub' => $altSubN]);
  $id = (int)($st->fetchColumn() ?: 0);

  if ($id > 0) {
    $out = ['id' => $id];
    if ($debug) {
      $out['debug'] = [
        'altCol'  => $altCol,
        'altSub'  => $altSub,
        'altColN' => $altColN,
        'altSubN' => $altSubN,
        'matched' => 'exact'
      ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Optional LIKE fallback (still Unicode)
  $sql2 = "
    SELECT TOP 1 ID
    FROM $table
    WHERE altColName    LIKE '%' + CONVERT(NVARCHAR(400), :altCol) + '%'
      AND altSubColName LIKE '%' + CONVERT(NVARCHAR(400), :altSub) + '%'
    ORDER BY ID DESC;
  ";
  $st2 = $pdo->prepare($sql2);
  $st2->execute([':altCol' => $altColN, ':altSub' => $altSubN]);
  $id2 = (int)($st2->fetchColumn() ?: 0);

  if ($id2 > 0) {
    $out = ['id' => $id2];
    if ($debug) {
      $out['debug'] = [
        'altCol'  => $altCol,
        'altSub'  => $altSub,
        'altColN' => $altColN,
        'altSubN' => $altSubN,
        'matched' => 'like'
      ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
  } else {
    http_response_code(404);
    echo json_encode([
      'id' => 0,
      'error' => 'not found',
      'debug' => $debug ? ['altCol'=>$altCol,'altSub'=>$altSub,'altColN'=>$altColN,'altSubN'=>$altSubN] : null
    ], JSON_UNESCAPED_UNICODE);
  }

} catch (Throwable $e) {
  error_log('api_case_model.php: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['id'=>0,'error'=>'server error'], JSON_UNESCAPED_UNICODE);
}
