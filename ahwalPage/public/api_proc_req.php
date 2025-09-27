<?php
// api_proc_req.php?templateId=123
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$templateId = isset($_GET['templateId']) ? (int)$_GET['templateId'] : 0;
if ($templateId <= 0) { echo json_encode(['row'=>null], JSON_UNESCAPED_UNICODE); exit; }

try {  
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // 1) Get ReqID from TableAddModel
  $sqlReq = "SELECT TOP (1) [ReqID] FROM [dbo].[TableAddModel] WHERE [ID] = :tid";
  $st = $pdo->prepare($sqlReq);
  $st->execute([':tid' => $templateId]);
  $reqRow = $st->fetch();

  $reqId = $reqRow['ReqID'] ?? null;

  // 2) If ReqID exists, fetch from TableProcReq by ID
  if ($reqId) {
    $sql = "
      SELECT TOP (1)
        PR.[ID]        AS ReqID,
        PR.[ModelID]   AS ModelID,
        PR.[توضيح_المعاملة]
      FROM [dbo].[TableProcReq] PR
      WHERE PR.[ID] = :rid
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':rid' => $reqId]);
    $row = $st->fetch();

    echo json_encode(['row' => ($row ?: null)], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 3) Fallback (if ReqID is null): try old logic by ModelID = templateId
  $sqlFallback = "
    SELECT TOP (1)
        p.id, p.ModelID, p.توضيح_المعاملة
    FROM dbo.TableAddModel AS m
    LEFT JOIN dbo.TableProcReq AS p
      ON p.المعاملة COLLATE Arabic_100_CI_AI_KS
        = (m.altColName COLLATE Arabic_100_CI_AI_KS
            + N'-'
            + m.altSubColName COLLATE Arabic_100_CI_AI_KS)
    where m.id = :mid AND p.[توضيح_المعاملة] IS NOT NULL
      ";
  $st = $pdo->prepare($sqlFallback);
  $st->execute([':mid' => $templateId]);
  $row = $st->fetch();

  echo json_encode(['row' => ($row ?: null), 'note' => 'fallback_by_ModelID'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
