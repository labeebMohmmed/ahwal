<?php
require __DIR__ . '/db.php';
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$in = $_POST;
if (empty($in)) { $raw = file_get_contents('php://input'); $in = json_decode($raw, true) ?: []; }

$caseId = isset($in['caseId']) ? (int)$in['caseId'] : 0;
$date   = isset($in['date']) ? trim((string)$in['date']) : '';
$hour   = isset($in['hour']) ? trim((string)$in['hour']) : '';

if ($caseId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'caseId required'], JSON_UNESCAPED_UNICODE); exit; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $hour)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid date/hour'], JSON_UNESCAPED_UNICODE); exit;
}

$slot = $date . ' ' . $hour . ':00'; // DATETIME2(0) string

try {
  $pdo = db();

  // Enforce capacity with SERIALIZABLE + UPDLOCK|HOLDLOCK (prevents race)
  $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
  $pdo->beginTransaction();

  // Does the case exist and not finalized?
  $probe = $pdo->prepare("SELECT Status FROM online.Cases WITH (UPDLOCK, HOLDLOCK) WHERE CaseID = :id");
  $probe->execute([':id'=>$caseId]);
  $status = $probe->fetchColumn();
  if ($status === false) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'case not found'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if (in_array($status, ['promoted','rejected'], true)) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'case finalized'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Capacity check for the selected slot
  $chk = $pdo->prepare("
    SELECT COUNT(*) AS C
    FROM online.Cases WITH (UPDLOCK, HOLDLOCK)
    WHERE ApptSlot = :slot
      AND Status IN (N'draft', N'submitted', N'verified', N'promoted')
  ");
  $chk->execute([':slot'=>$slot]);
  $count = (int)$chk->fetchColumn();
  if ($count >= 3) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'الساعة ممتلئة، الرجاء اختيار ساعة أخرى'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Save ApptSlot + mark submitted (keep first SubmittedAt)
  $up = $pdo->prepare("
    UPDATE online.Cases
    SET ApptSlot   = :slot,
        Status     = N'submitted',
        SubmittedAt= COALESCE(SubmittedAt, SYSUTCDATETIME()),
        UpdatedAt  = SYSUTCDATETIME()
    WHERE CaseID = :id
      AND Status NOT IN (N'promoted', N'rejected')
  ");
  $up->execute([':slot'=>$slot, ':id'=>$caseId]);

  if ($up->rowCount() === 0) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'status not eligible'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'caseId'=>$caseId, 'apptSlot'=>$slot, 'status'=>'submitted'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo?->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
