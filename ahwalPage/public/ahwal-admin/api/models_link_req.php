<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function norm_digits(string $s): string {
  $s = str_replace(['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
                   ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $s);
  return preg_replace('/\D+/', '', $s) ?? '';
}

$in    = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($in['id'] ?? 0);
$reqId = (int)(norm_digits((string)($in['reqId'] ?? '')) ?: 0);

if ($id <= 0 || $reqId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing id/reqId']); exit; }

try {
  // تأكد أن المتطلبات موجودة
  $chk = $pdo->prepare("SELECT 1 FROM [dbo].[TableProcReq] WHERE [ID] = :rid");
  $chk->execute([':rid'=>$reqId]);
  if (!$chk->fetchColumn()) { http_response_code(404); echo json_encode(['error'=>'ReqID not found']); exit; }

  // حدّث الـ ReqID
  $upd = $pdo->prepare("UPDATE [dbo].[TableAddModel] SET [ReqID] = :rid WHERE [ID] = :id");
  $upd->execute([':rid'=>$reqId, ':id'=>$id]);

  // رجّع الصفّ بعد التحديث
  $get = $pdo->prepare("SELECT * FROM [dbo].[TableAddModel] WHERE [ID] = :id");
  $get->execute([':id'=>$id]);
  $row = $get->fetch();

  echo json_encode(['ok'=>true, 'row'=>$row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
