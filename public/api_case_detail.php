<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require __DIR__ . '/db.php';
$caseId = (int)($_GET['caseId'] ?? 0);
if ($caseId <= 0) { http_response_code(400); echo json_encode(['error'=>'caseId required']); exit; }


$pdo = db();

$st = $pdo->prepare("
  SELECT [CaseID],[ExternalRef],[UserID],[ModelID],[Lang],[Status],
         [PartyJson],[DetailsJson],[CreatedAt],[UpdatedAt],[SubmittedAt]
  FROM [AhwalDataBase].[online].[Cases]
  WHERE [CaseID] = :id
");
$st->execute([':id'=>$caseId]);
$row = $st->fetch();
if (!$row) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }

$party   = json_decode($row['PartyJson']   ?? '[]', true) ?: [];
$details = json_decode($row['DetailsJson'] ?? '[]', true) ?: [];

echo json_encode([
  'case' => [
    'caseId'      => (int)$row['CaseID'],
    'externalRef' => (string)($row['ExternalRef'] ?? ''),
    'userId'      => (int)$row['UserID'],
    'modelId'     => (int)$row['ModelID'],
    'lang'        => (string)($row['Lang'] ?? 'ar'),
    'status'      => (string)($row['Status'] ?? 'draft'),
    'createdAt'   => $row['CreatedAt'],
    'submittedAt' => $row['SubmittedAt'],
  ],
  'party'   => $party,
  'details' => $details
], JSON_UNESCAPED_UNICODE);
