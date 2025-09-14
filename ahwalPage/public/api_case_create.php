<?php
require __DIR__ . '/db.php';
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$in = $_POST;
if (empty($in)) { $raw = file_get_contents('php://input'); $in = json_decode($raw, true) ?: []; }

$caseId    = isset($in['existingCaseId']) ? (int)$in['existingCaseId'] : 0;            // 👈 new (optional)
$modelId   = isset($in['modelId']) ? (int)$in['modelId'] : 0;
$lang      = strtolower(trim((string)($in['lang'] ?? 'ar')));
$userId    = isset($in['userId']) ? (int)$in['userId'] : null;
$mainGroup = trim((string)($in['mainGroup'] ?? ''));
$altCol    = trim((string)($in['altColName'] ?? ''));
$altSub    = trim((string)($in['altSubColName'] ?? ''));

if ($modelId <= 0 || ($lang !== 'ar' && $lang !== 'en') || $mainGroup === '' || $altCol === '') {
  http_response_code(400);
  echo json_encode(['error'=>'missing or invalid fields'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Base skeletons
$partyBase = [
  'applicants'    => [],
  'authenticated' => [],
  'witnesses'     => [],
  'contact'       => ['phone'=>'', 'email'=>'']
];
$detailsModelBlock = [
  'id'          => $modelId,
  'mainGroup'   => $mainGroup,
  'altColName'  => $altCol,
  'altSubColName' => ($altSub !== '' ? $altSub : null),
  'langLabel'   => ($lang === 'ar' ? 'العربية' : 'الانجليزية')
];
$detailsBase = [
  'model'   => $detailsModelBlock,
  'answers' => ['fields' => new stdClass()]
];

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // === UPDATE path (if caseId provided and exists) ===
  if ($caseId > 0) {
    // Fetch current row
    $st = $pdo->prepare("SELECT [CaseID],[ExternalRef],[PartyJson],[DetailsJson] FROM online.Cases WHERE [CaseID]=:cid");
    $st->execute([':cid' => $caseId]);
    $cur = $st->fetch();

    if (!$cur) {
      http_response_code(404);
      echo json_encode(['error' => 'case not found', 'caseId' => $caseId], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // Decode existing JSON
    $partyJson    = $cur['PartyJson']   ?? null;
    $detailsJson  = $cur['DetailsJson'] ?? null;

    $party   = $partyJson   ? (json_decode($partyJson, true)   ?: $partyBase)   : $partyBase;
    $details = $detailsJson ? (json_decode($detailsJson, true) ?: $detailsBase) : $detailsBase;

    // Ensure structures exist
    if (!is_array($details)) $details = $detailsBase;
    if (!isset($details['answers']) || !is_array($details['answers'])) {
      $details['answers'] = ['fields' => new stdClass()];
    }
    if (!isset($details['answers']['fields'])) {
      $details['answers']['fields'] = new stdClass();
    }

    // 🔁 Merge only the model block (preserve answers & party)
    $details['model'] = $detailsModelBlock;

    // Update record
    $upd = $pdo->prepare("
      UPDATE online.Cases
      SET ModelID = :mid,
          Lang    = :lang,
          DetailsJson = :djson
      WHERE CaseID = :cid
    ");
    $upd->execute([
      ':mid'  => $modelId,
      ':lang' => $lang,
      ':djson'=> json_encode($details, JSON_UNESCAPED_UNICODE),
      ':cid'  => $caseId,
    ]);

    // Return updated external ref
    $st2 = $pdo->prepare("SELECT [ExternalRef] FROM online.Cases WHERE [CaseID]=:cid");
    $st2->execute([':cid' => $caseId]);
    $row = $st2->fetch();

    echo json_encode([
      'ok' => true,
      'mode' => 'update',
      'caseId' => $caseId,
      'externalRef' => $row['ExternalRef'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // === INSERT path (new draft) ===
  $ins = $pdo->prepare("
    INSERT INTO online.Cases (UserID, ModelID, Lang, PartyJson, DetailsJson, Status)
    OUTPUT INSERTED.CaseID, INSERTED.ExternalRef
    VALUES (:uid, :mid, :lang, :pjson, :djson, N'draft');
  ");
  $ins->execute([
    ':uid'  => $userId,
    ':mid'  => $modelId,
    ':lang' => $lang,
    ':pjson'=> json_encode($partyBase,   JSON_UNESCAPED_UNICODE),
    ':djson'=> json_encode($detailsBase, JSON_UNESCAPED_UNICODE),
  ]);
  $row = $ins->fetch();

  echo json_encode([
    'ok' => true,
    'mode' => 'insert',
    'caseId' => $row['CaseID'] ?? null,
    'externalRef' => $row['ExternalRef'] ?? null
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
