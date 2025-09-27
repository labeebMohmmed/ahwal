<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
try{
  $fileId = isset($_POST['fileId']) ? (int)$_POST['fileId'] : 0;
  $caseId = isset($_POST['caseId']) ? (int)$_POST['caseId'] : 0;
  if ($fileId<=0 || $caseId<=0){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad id']); exit; }

  $pdo = db('ArchFilesDB');
  $SCHEMA = 'online'; // or 'dbo'

  // only delete if it belongs to the same case
  $stmt = $pdo->prepare("DELETE FROM [$SCHEMA].[CaseFiles] WHERE FileID=? AND CaseID=?");
  $stmt->execute([$fileId, $caseId]);

  echo json_encode(['ok'=>true]);
} catch(Throwable $e){
  error_log('api_casefile_delete.php: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Internal error']);
}
