<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$col   = $_GET['column'] ?? '';
$limit = (int)($_GET['limit'] ?? 20);

/* السماح فقط بهذين العمودين */
$WL = ['altColName','altSubColName'];
if (!in_array($col, $WL, true)) {
  http_response_code(400);
  echo json_encode(['error'=>'Bad column'], JSON_UNESCAPED_UNICODE); exit;
}
if ($limit <= 0 || $limit > 100) $limit = 20;

try{
  $pdo = db(); // أو db('AhwalDataBase')
  // أعلى القيم تكراراً وحداثة (إن وُجد UpdatedAt)
  $sql = "
    SELECT TOP ($limit) v
    FROM (
      SELECT [$col] AS v, COUNT(*) AS c, MAX(UpdatedAt) AS mx
      FROM dbo.TableAddModel
      WHERE [$col] IS NOT NULL AND LTRIM(RTRIM([$col])) <> ''
      GROUP BY [$col]
    ) t
    ORDER BY c DESC, mx DESC
  ";
  $vals = [];
  foreach ($pdo->query($sql) as $r) { $vals[] = $r['v']; }
  echo json_encode(['values'=>$vals], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
