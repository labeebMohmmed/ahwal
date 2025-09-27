<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require __DIR__ . '/auth.php';

function jerr($msg, $extra = []) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$msg] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $page     = max(1, (int)($_GET['page'] ?? 1));
  $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 20)));

  $officeNumber = trim($_GET['officeNumber'] ?? '');
  $applicant    = trim($_GET['applicantName'] ?? '');
  $from         = trim($_GET['dateFrom'] ?? '');
  $to           = trim($_GET['dateTo'] ?? '');
  $mg           = trim($_GET['mg'] ?? '');
  $table        = trim($_GET['table'] ?? '');
  $todayOnly    = ($_GET['todayOnly'] ?? '') === '1';
  $debug        = ($_GET['debug'] ?? '') === '1';


  function normalizeDate(?string $d): ?string {
    if ($d === null || trim($d) === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $d)) {
        [$m,$d2,$y] = explode('-', $d);
        return "$y-$m-$d2";
    }
    if (preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{4}$/', $d)) {
        [$d2,$m,$y] = preg_split('/[-\/]/',$d);
        return "$y-$m-$d2";
    }
    return null;
  }

  // Check if view exists
  $chk = $pdo->query("
    SELECT 1
    FROM sys.views v
    JOIN sys.schemas s ON s.schema_id = v.schema_id
    WHERE s.name = 'office' AND v.name = 'vw_OfficeCases'
  ")->fetch();
  $hasView = (bool)$chk;

  if ($hasView) {
    $base = "SELECT OfficeTable, OfficeId, OfficeNumber, MainGroup, MainGroupId,
                    ApplicantName, IdNumber, TRY_CAST([Date] AS DATE) AS [Date],
                    ArchStatus, Method, StatusTag, MethodTag
             FROM office.vw_OfficeCases";
  } else {
    $base = "
      SELECT N'Auth' AS OfficeTable, A.id AS OfficeId, A.[رقم_التوكيل] AS OfficeNumber,
             N'توكيل' AS MainGroup, 12 AS MainGroupId,
             A.[مقدم_الطلب] AS ApplicantName, A.[رقم_الهوية] AS IdNumber,
             TRY_CAST(A.[التاريخ_الميلادي] AS DATE) AS [Date],
             A.[حالة_الارشفة] AS ArchStatus, A.[طريقة_الطلب] AS Method,
             CASE LTRIM(RTRIM(A.[حالة_الارشفة]))
               WHEN N'جديد' THEN 'status-new'
               WHEN N'قيد المراجعة' THEN 'status-review'
               WHEN N'مؤرشف' THEN 'status-archived'
               WHEN N'مرفوض' THEN 'status-rejected'
               ELSE 'status-unknown' END AS StatusTag,
             CASE LTRIM(RTRIM(A.[طريقة_الطلب]))
               WHEN N'الكتروني' THEN 'method-online'
               WHEN N'حضوري'    THEN 'method-inperson'
               ELSE 'method-other' END AS MethodTag
      FROM dbo.TableAuth A
      UNION ALL
      SELECT N'Collection', C.id, C.[رقم_المعاملة],
             C.[نوع_المعاملة], 10,
             C.[مقدم_الطلب], C.[رقم_الهوية],
             TRY_CAST(C.[التاريخ_الميلادي] AS DATE),
             C.[حالة_الارشفة], C.[طريقة_الطلب],
             CASE LTRIM(RTRIM(C.[حالة_الارشفة]))
               WHEN N'جديد' THEN 'status-new'
               WHEN N'قيد المراجعة' THEN 'status-review'
               WHEN N'مؤرشف' THEN 'status-archived'
               WHEN N'مرفوض' THEN 'status-rejected'
               ELSE 'status-unknown' END,
             CASE LTRIM(RTRIM(C.[طريقة_الطلب]))
               WHEN N'الكتروني' THEN 'method-online'
               WHEN N'حضوري'    THEN 'method-inperson'
               ELSE 'method-other' END
      FROM dbo.TableCollection C
    ";
  }

  // Build filters
  $where = [];
  $p = [];

  if ($table !== '' && $table !== 'both') {
    $where[] = "OfficeTable = :tbl";
    $p[':tbl'] = $table;
  }
  if ($mg !== '') { $where[] = "MainGroup = :mg"; $p[':mg'] = $mg; }
  if ($officeNumber !== '') {
    $where[] = "OfficeNumber LIKE :num";
    $p[':num'] = "%$officeNumber%";
  }
  if ($applicant !== '') {
    $where[] = "ApplicantName LIKE :name";
    $p[':name'] = "%$applicant%";
  }

  $from = normalizeDate($from);
  $to   = normalizeDate($to);

  if ($from !== null) {
      $where[] = "[Date] >= :from";
      $p[':from'] = $from;
  }
  if ($to !== null) {
      $where[] = "[Date] < DATEADD(DAY,1,:to)";
      $p[':to'] = $to;
  }
  if ($todayOnly) {
      $where[] = "CAST([Date] AS date) = CAST(SYSDATETIME() AS date)";
  }

  $wsql = $where ? (" WHERE ".implode(" AND ", $where)) : "";

  // Count
  $sqlCount = "SELECT COUNT(*) AS cnt FROM ($base) X $wsql";
  $st = $pdo->prepare($sqlCount);
  foreach ($p as $k=>$v) $st->bindValue($k, $v);
  $st->execute();
  $total = (int)$st->fetch()['cnt'];

  // Auto today filter
  $autoTodayApplied = false;
  $noFilters = ($officeNumber==='' && $applicant==='' && $mg==='' && $table==='' && $from===null && $to===null && !$todayOnly);
  if ($total > 20 && $noFilters) {
    $wsql = ($wsql ? "$wsql AND " : " WHERE ") . "CAST([Date] AS date) = CAST(SYSDATETIME() AS date)";
    $autoTodayApplied = true;
    $st = $pdo->prepare("SELECT COUNT(*) AS cnt FROM ($base) X $wsql");
    foreach ($p as $k=>$v) $st->bindValue($k, $v);
    $st->execute();
    $total = (int)$st->fetch()['cnt'];
  }

  $offset = ($page - 1) * $pageSize;
  $sql = "
    SELECT * FROM ($base) X
    $wsql
    ORDER BY [Date] DESC, OfficeId DESC
    OFFSET :off ROWS FETCH NEXT :ps ROWS ONLY
  ";
  $st = $pdo->prepare($sql);
  foreach ($p as $k=>$v) $st->bindValue($k, $v);
  $st->bindValue(':off', $offset, PDO::PARAM_INT);
  $st->bindValue(':ps',  $pageSize, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll();

  echo json_encode([
    'ok' => true,
    'rows' => $rows,
    'page' => $page,
    'pageSize' => $pageSize,
    'total' => $total,
    'autoTodayApplied' => $autoTodayApplied,
    'usedView' => $hasView,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  jerr('Server error', [
    'debug' => ($_GET['debug'] ?? '') === '1' ? [
      'type' => get_class($e),
      'msg'  => $e->getMessage(),
      'line' => $e->getLine(),
      'file' => basename($e->getFile()),
    ] : null
  ]);
}
