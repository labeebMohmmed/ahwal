<?php
// api_case_get.php?caseId=1009
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$caseId = isset($_GET['caseId']) ? (int)$_GET['caseId'] : 0;
if ($caseId <= 0) { echo json_encode(['ok'=>false,'error'=>'missing caseId']); exit; }

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  $st = $pdo->prepare("
    SELECT CaseID, Lang, PartyJson, DetailsJson, Status, ExternalRef
    FROM online.Cases
    WHERE CaseID = :cid
  ");
  $st->execute([':cid'=>$caseId]);
  $row = $st->fetch();

  if (!$row) { echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

  // Always return valid JSON objects
  $party   = json_decode($row['PartyJson']   ?: '{}', true) ?: [];
  $details = json_decode($row['DetailsJson'] ?: '{}', true) ?: [];

  echo json_encode([
    'ok'         => true,
    'caseId'     => (int)$row['CaseID'],
    'lang'       => $row['Lang'],
    'status'     => $row['Status'],
    'externalRef'=> $row['ExternalRef'],
    'party'      => $party,
    'details'    => $details,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
