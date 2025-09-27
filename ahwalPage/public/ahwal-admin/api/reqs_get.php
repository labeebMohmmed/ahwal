<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['error'=>'Missing id'], JSON_UNESCAPED_UNICODE); http_response_code(400); exit; }

try {
  $st = $pdo->prepare("SELECT المطلوب_رقم1, المطلوب_رقم2, المطلوب_رقم3, المطلوب_رقم4, المطلوب_رقم5, المطلوب_رقم7, المطلوب_رقم8, المطلوب_رقم9, توضيح_المعاملة FROM [dbo].[TableProcReq] WHERE [ID] = :id");
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  $row = $st->fetch();
  if (!$row) { echo json_encode(['error'=>'Not found'], JSON_UNESCAPED_UNICODE); http_response_code(404); exit; }
  echo json_encode(['row'=>$row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
