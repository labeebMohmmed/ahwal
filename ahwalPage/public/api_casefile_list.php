<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $caseId = isset($_GET['caseId']) ? (int)$_GET['caseId'] : 0;
  if ($caseId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing caseId']); exit; }

  // Use your existing DSN/auth:
  
  $pdo = db('ArchFilesDB');

  // Adjust schema if needed: 'online' → 'dbo'
  $SCHEMA = 'online';

  $stmt = $pdo->prepare("
    SELECT FileID, Label, OriginalName, MimeType, SizeBytes, UploadedAt
    FROM [$SCHEMA].[CaseFiles]
    WHERE CaseID = ?
    ORDER BY UploadedAt DESC, FileID DESC
  ");
  $stmt->execute([$caseId]);
  $rows = $stmt->fetchAll();

  echo json_encode(['ok'=>true, 'items'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('api_casefile_list.php: '.$e->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
