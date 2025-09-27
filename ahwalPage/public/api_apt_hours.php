<?php
require __DIR__ . '/auth.php';
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$date = isset($_GET['date']) ? trim($_GET['date']) : '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  echo json_encode(['hours'=>[]], JSON_UNESCAPED_UNICODE); exit;
}

$HOURS = ['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00'];
$MAX = 3;

$dsn = "sqlsrv:Server=localhost;Database=AhwalDataBase;Encrypt=yes;TrustServerCertificate=yes";
try {
  // Count by hour for the given date
  $st = $pdo->prepare("
    SELECT RIGHT('0'+CAST(DATEPART(HOUR, ApptSlot) AS VARCHAR(2)),2)+':00' AS Hh,
           COUNT(*) AS C
    FROM online.Cases
    WHERE ApptSlot IS NOT NULL
      AND CAST(ApptSlot AS date) = :d
      AND Status IN (N'draft', N'submitted', N'verified', N'promoted')
    GROUP BY DATEPART(HOUR, ApptSlot)
  ");
  $st->execute([':d'=>$date]);
  $counts = [];
  foreach ($st as $r) $counts[$r['Hh']] = (int)$r['C'];

  $out = [];
  foreach ($HOURS as $h) {
    $c = $counts[$h] ?? 0;
    $rem = max(0, $MAX - $c);
    $out[] = ['time'=>$h, 'remaining'=>$rem, 'disabled'=> ($rem <= 0)];
  }
  echo json_encode(['hours'=>$out], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
