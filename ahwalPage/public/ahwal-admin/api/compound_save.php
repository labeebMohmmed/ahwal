<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$in = json_decode(file_get_contents('php://input'), true) ?? [];

$modelId   = (int)($in['modelId'] ?? 0);
$modelPatch= (array)($in['modelPatch'] ?? []);
$reqIdIn   = $in['reqId'] ?? null;
$reqPatch  = (array)($in['reqPatch'] ?? []);
$createReq = (bool)($in['createReqIfMissing'] ?? false);

if ($modelId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing modelId']); exit; }

try {
  $pdo->beginTransaction();

  // (A) Optionally create a new ProcReq row if requested and none exists but we have reqPatch
  $reqId = (int)$reqIdIn;
  if ($reqId <= 0 && $createReq && $reqPatch) {
    // Insert minimal req row with provided columns (others default to '')
    $cols = array_keys($reqPatch);
    $pairs = [];
    $vals  = [];
    foreach ($cols as $c) {
      $pairs[] = '[' . str_replace(']', ']]', $c) . ']';
      $vals[]  = ':' . md5($c);
    }
    $sql = "INSERT INTO [dbo].[TableProcReq] (" . implode(',', $pairs) . ")
            VALUES (" . implode(',', $vals) . ");
            SELECT CAST(SCOPE_IDENTITY() AS int) AS id;";
    $st  = $pdo->prepare($sql);
    foreach ($reqPatch as $k => $v) {
      $st->bindValue(':' . md5($k), (string)$v);
    }
    $st->execute();
    $reqId = (int)$st->fetchColumn();

    // Also set in modelPatch to link
    $modelPatch['ReqID'] = $reqId;
  }

  // (B) Update TableAddModel if patch present
  if ($modelPatch) {
    // Build SET
    $set = []; $params = [':id' => $modelId];
    foreach ($modelPatch as $k => $v) {
      if ($k === 'ID') continue;
      $p = ':p_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
      $set[] = '[' . str_replace(']', ']]', $k) . "] = $p";
      $params[$p] = $v;
    }
    if ($set) {
      $sql = "UPDATE [dbo].[TableAddModel] SET ".implode(',',$set)." WHERE [ID] = :id";
      $pdo->prepare($sql)->execute($params);
    }
  }

  // (C) Update TableProcReq if we have an ID and a patch
  if ($reqId > 0 && $reqPatch) {
    $set = []; $params = [':id' => $reqId];
    foreach ($reqPatch as $k => $v) {
      $p = ':q_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
      $set[] = '[' . str_replace(']', ']]', $k) . "] = $p";
      $params[$p] = $v;
    }
    if ($set) {
      $sql = "UPDATE [dbo].[TableProcReq] SET ".implode(',',$set)." WHERE [ID] = :id";
      $pdo->prepare($sql)->execute($params);
    }
  }

  // (D) Return refreshed rows
  $stM = $pdo->prepare("SELECT * FROM [dbo].[TableAddModel] WHERE [ID] = :id");
  $stM->execute([':id'=>$modelId]);
  $modelRow = $stM->fetch();

  $reqRow = null;
  if (($modelRow['ReqID'] ?? 0) > 0) {
    $stR = $pdo->prepare("
      SELECT [ID],
             [المطلوب_رقم1],[المطلوب_رقم2],[المطلوب_رقم3],[المطلوب_رقم4],[المطلوب_رقم5],
             [المطلوب_رقم6],[المطلوب_رقم7],[المطلوب_رقم8],[المطلوب_رقم9],
             [توضيح_المعاملة]
      FROM [dbo].[TableProcReq] WHERE [ID] = :id
    ");
    $stR->execute([':id'=>(int)$modelRow['ReqID']]);
    $reqRow = $stR->fetch();
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'model'=>$modelRow, 'req'=>$reqRow], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
