<?php
// api_case_model.php — return ID by (altColName, altSubColName), soft-fail JSON

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$altCol = isset($_GET['altCol']) ? (string)$_GET['altCol'] : '';
$altSub = isset($_GET['altSub']) ? (string)$_GET['altSub'] : '';
$debug  = isset($_GET['debug']) && $_GET['debug'] === '1';

// If inputs missing: return manageable JSON (200) instead of 400
if ($altCol === '' || $altSub === '') {
  echo json_encode([
    'ok'     => false,
    'id'     => 0,
    'found'  => false,
    'error'  => 'altCol and altSub are required',
    'debug'  => $debug ? ['altCol' => $altCol, 'altSub' => $altSub] : null
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$pdo = db(); // ensure UTF-8 for SQLSRV

// Normalize (Arabic comma + whitespace)
$norm = static function (string $s): string {
  $s = str_replace('،', ',', $s);
  $s = preg_replace('/\s+/u', ' ', $s);
  return trim($s);
};
$altColN = $norm($altCol);
$altSubN = $norm($altSub);

// Fully-qualified table
$table = '[AhwalDataBase].[dbo].[TableAddModel]';

// Exact, Unicode-safe match
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
    echo json_encode([
      'ok'    => true,
      'id'    => $id,
      'found' => true,
      'debug' => $debug ? [
        'altCol'  => $altCol,  'altSub'  => $altSub,
        'altColN' => $altColN, 'altSubN' => $altSubN,
        'matched' => 'exact'
      ] : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Not found → still 200 with a manageable body
  echo json_encode([
    'ok'    => true,
    'id'    => 0,
    'found' => false,
    'debug' => $debug ? [
      'altCol'  => $altCol,  'altSub'  => $altSub,
      'altColN' => $altColN, 'altSubN' => $altSubN
    ] : null
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  error_log('api_case_model.php: '.$e->getMessage());
  // Server error → you can keep 500, but still return structured JSON
  http_response_code(500);
  echo json_encode([
    'ok'    => false,
    'id'    => 0,
    'found' => false,
    'error' => 'server error'
  ], JSON_UNESCAPED_UNICODE);
}
