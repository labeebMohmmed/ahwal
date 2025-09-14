<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  // أعمدة المتطلبات التي ذكرتها
  $cols = [
    'المطلوب_رقم1','المطلوب_رقم2','المطلوب_رقم3','المطلوب_رقم4','المطلوب_رقم5',
    'المطلوب_رقم6','المطلوب_رقم7','المطلوب_رقم8','المطلوب_رقم9','توضيح_المعاملة'
  ];

  // أدخل صفًا جديدًا بالقيم المرسلة أو نصوص فارغة
  $payload = json_decode(file_get_contents('php://input'), true) ?? [];
  $values  = (array)($payload['values'] ?? []);

  // ابنِ INSERT ديناميكيًا حسب الأعمدة الموجودة فعليًا
  $existing = $pdo->query("
    SELECT c.name FROM sys.columns c
    JOIN sys.objects o ON o.object_id=c.object_id
    JOIN sys.schemas s ON s.schema_id=o.schema_id
    WHERE s.name='dbo' AND o.name='TableProcReq' AND o.type='U'
  ")->fetchAll(PDO::FETCH_COLUMN);

  $useCols = array_values(array_filter($cols, fn($c)=>in_array($c, $existing, true)));
  if (!$useCols) { http_response_code(500); echo json_encode(['error'=>'No expected columns in TableProcReq'], JSON_UNESCAPED_UNICODE); exit; }

  $params = [];
  $pairs  = [];
  foreach ($useCols as $c) {
    $pairs[] = '[' . str_replace(']', ']]', $c) . ']';
    $params[':'.md5($c)] = isset($values[$c]) ? (string)$values[$c] : '';
  }
  $placeholders = implode(',', array_keys($params));
  $colsSql      = implode(',', $pairs);

  $sql = "INSERT INTO [dbo].[TableProcReq] ($colsSql) VALUES ($placeholders); SELECT CAST(SCOPE_IDENTITY() AS int) AS id;";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $newId = (int)$st->fetchColumn();

  // رجّع الصفّ الجديد
  $row = $pdo->query("SELECT [ID],$colsSql FROM [dbo].[TableProcReq] WHERE [ID] = $newId")->fetch(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true, 'id'=>$newId, 'row'=>$row], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500); echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
