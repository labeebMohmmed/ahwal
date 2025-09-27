<?php
// public/api_cases_list.php
declare(strict_types=1);
require __DIR__ . '/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');             // <-- correct usage
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight quickly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

function out(array $d, int $code = 200){ http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function bad(string $m){ out(['ok'=>false,'error'=>$m], 400); }
function svr(string $m){ out(['ok'=>false,'error'=>$m], 500); }

// If you have a db() helper, require it. If not, we'll fallback to direct PDO.

if (is_file($helper)) {
  require $helper; // must expose function db(): PDO
}

try {
  // ---- Inputs ----
  if (!array_key_exists('userId', $_GET)) bad('userId required'); // allow 0; just require the param
  $userId   = (int)$_GET['userId'];
  $page     = max(1, (int)($_GET['page'] ?? 1));
  $pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 20)));

  $caseId   = null; // optional
  $qName    = trim((string)($_GET['name'] ?? ''));                  // contains (first applicant)
  $from     = trim((string)($_GET['dateFrom'] ?? ''));              // YYYY-MM-DD
  $to       = trim((string)($_GET['dateTo'] ?? ''));                // YYYY-MM-DD
  $altCol   = trim((string)($_GET['altColName'] ?? ''));            // contains
  $altSub   = trim((string)($_GET['altSubColName'] ?? ''));         // contains

  
  // ---- WHERE ----
  $w = ["oc.[UserID] = :uid"];
  $p = [':uid' => $userId];

  if ($caseId !== null && $caseId > 0) { $w[] = "oc.[CaseID] = :cid"; $p[':cid'] = $caseId; }
  if ($qName  !== '') { $w[] = "JSON_VALUE(oc.[PartyJson], '$.applicants[0].name') LIKE :qname"; $p[':qname'] = "%$qName%"; }
  if ($from   !== '') { $w[] = "COALESCE(oc.[SubmittedAt], oc.[CreatedAt]) >= :df";              $p[':df']   = $from; }
  if ($to     !== '') { $w[] = "COALESCE(oc.[SubmittedAt], oc.[CreatedAt]) < DATEADD(DAY,1,:dt)";$p[':dt']   = $to; }
  if ($altCol !== '') { $w[] = "JSON_VALUE(oc.[DetailsJson], '$.model.altColName') LIKE :ac";    $p[':ac']   = "%$altCol%"; }
  if ($altSub !== '') { $w[] = "JSON_VALUE(oc.[DetailsJson], '$.model.altSubColName') LIKE :as"; $p[':as']   = "%$altSub%"; }
  $whereSql = 'WHERE '.implode(' AND ', $w);

  // ---- CTE (leading semicolon) ----
  $withBase = <<<SQL
;WITH Base AS (
  SELECT
    oc.[CaseID],
    oc.[UserID],
    COALESCE(oc.[SubmittedAt], oc.[CreatedAt]) AS DisplayDate,
    JSON_VALUE(oc.[PartyJson],  '$.applicants[0].name')           AS FirstName,
    JSON_VALUE(oc.[DetailsJson],'$.model.altColName')             AS AltColName,
    JSON_VALUE(oc.[DetailsJson],'$.model.altSubColName')          AS AltSubColName,
    oc.[PartyJson]
  FROM [AhwalDataBase].[online].[Cases] oc
  $whereSql
),
BestId AS (
  SELECT b.CaseID,
         (SELECT TOP (1) JSON_VALUE(j.value,'$.number')
            FROM OPENJSON(b.PartyJson,'$.applicants[0].ids') j
            ORDER BY
              CASE
                WHEN JSON_VALUE(j.value,'$.number') LIKE N'[PB][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]' THEN 1 -- passport P/B + 8
                WHEN JSON_VALUE(j.value,'$.number') LIKE N'[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]' THEN 2 -- 11-digit
                ELSE 3
              END,
              JSON_VALUE(j.value,'$.number')
         ) AS IdNumber
  FROM Base b
),
Agg AS (
  SELECT b.CaseID, COUNT(*) AS ApplicantsCount
  FROM Base b
  CROSS APPLY OPENJSON(b.PartyJson,'$.applicants') a
  GROUP BY b.CaseID
)
SQL;

  $selectCore = <<<SQL
SELECT
  b.CaseID,
  b.DisplayDate,
  b.FirstName,
  b.AltColName,
  b.AltSubColName,
  bi.IdNumber,
  ag.ApplicantsCount
FROM Base b
LEFT JOIN BestId bi ON bi.CaseID = b.CaseID
LEFT JOIN Agg   ag ON ag.CaseID = b.CaseID
SQL;

  // ---- COUNT ----
  $sqlCount = $withBase . "\nSELECT COUNT(1) AS cnt FROM ( $selectCore ) AS C;";
  $stCount = $pdo->prepare($sqlCount);
  foreach ($p as $k=>$v) $stCount->bindValue($k,$v);
  $stCount->execute();
  $total = (int)$stCount->fetchColumn();

  // ---- PAGE ----
  $off = ($page - 1) * $pageSize;
  $sqlPage = $withBase . "\n" . $selectCore . "\nORDER BY b.DisplayDate DESC, b.CaseID DESC OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY;";
  $st = $pdo->prepare($sqlPage);
  foreach ($p as $k=>$v) $st->bindValue($k,$v);
  $st->bindValue(':off', $off, PDO::PARAM_INT);
  $st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll();

  // ---- Shape for renderCasesTable ----
  $items = [];
  foreach ($rows as $r) {
    $first = (string)($r['FirstName'] ?? '');
    $name  = $first;
    if ((int)($r['ApplicantsCount'] ?? 0) > 1 && $first !== '') $name .= ' وآخرون';

    $items[] = [
      'caseId'        => (int)$r['CaseID'],
      'applicantName' => $name,
      'idNumber'      => (string)($r['IdNumber'] ?? ''),
      'altColName'    => (string)($r['AltColName'] ?? ''),
      'altSubColName' => (string)($r['AltSubColName'] ?? ''),
      'date'          => substr((string)($r['DisplayDate'] ?? ''), 0, 10),
      '_meta'         => ['caseId' => (int)$r['CaseID']],
    ];
  }

  out([
    'ok'       => true,
    'page'     => $page,
    'pageSize' => $pageSize,
    'total'    => $total,
    'items'    => $items,
  ]);

} catch (Throwable $e) {
  svr($e->getMessage());
}
