<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
try{
  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $caseId = (int)($in['caseId'] ?? 0);
  $section = (string)($in['section'] ?? '');
  $index = (int)($in['index'] ?? -1);
  $person = $in['person'] ?? null;

  if ($caseId<=0 || !in_array($section, ['applicants','authenticated','witnesses'], true) || !is_array($person)){
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad input']); exit;
  }

  $SCHEMA='online';

  $st = $pdo->prepare("SELECT PartyJson FROM [$SCHEMA].[Cases] WHERE CaseID=?");
  $st->execute([$caseId]);
  $row = $st->fetch();
  if (!$row){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

  $party = $row['PartyJson'] ? json_decode($row['PartyJson'], true) : [];
  foreach(['applicants','authenticated','witnesses'] as $sec){
    if (!isset($party[$sec]) || !is_array($party[$sec])) $party[$sec]=[];
  }

  if ($index>=0 && $index < count($party[$section])){
    $party[$section][$index] = $person; // update
  } else {
    $party[$section][] = $person;       // add
  }

  $json = json_encode($party, JSON_UNESCAPED_UNICODE);
  $up = $pdo->prepare("UPDATE [$SCHEMA].[Cases] SET PartyJson=? WHERE CaseID=?");
  $up->execute([$json, $caseId]);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
  error_log('api_case_party_upsert.php: '.$e->getMessage());
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Internal error']);
}
