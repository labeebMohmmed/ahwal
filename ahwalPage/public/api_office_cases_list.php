<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function jerr($msg, $extra = []) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$msg] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $page     = max(1, (int)($_GET['page'] ?? 1));
  $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 20)));
  $q        = trim($_GET['q'] ?? '');
  $from     = trim($_GET['dateFrom'] ?? '');
  $to       = trim($_GET['dateTo'] ?? '');
  $mg       = trim($_GET['mainGroup'] ?? '');       // e.g. 'توكيل' or 'غير ذلك'
  $table    = trim($_GET['officeTable'] ?? '');     // 'Auth'|'Collection'
  $todayOnly= ($_GET['todayOnly'] ?? '') === '1';
  $debug    = ($_GET['debug'] ?? '') === '1';

  $dsn = "sqlsrv:Server=localhost;Database=AhwalDataBase;Encrypt=yes;TrustServerCertificate=yes";
  $pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Check if view exists
  $hasView = false;
  $chk = $pdo->query("
    SELECT 1
    FROM sys.views v
    JOIN sys.schemas s ON s.schema_id = v.schema_id
    WHERE s.name = 'office' AND v.name = 'vw_OfficeCases'
  ")->fetch();
  $hasView = (bool)$chk;

  // Base SQL (view or inline UNION ALL)
  if ($hasView) {
    $base = "SELECT OfficeTable, OfficeId, OfficeNumber, MainGroup, MainGroupId,
                    ApplicantName, IdNumber, [Date], ArchStatus, Method,
                    StatusTag, MethodTag
             FROM office.vw_OfficeCases";
  } else {
    // inline union fallback
    $base = "
      SELECT N'Auth' AS OfficeTable, A.id AS OfficeId, A.[رقم_التوكيل] AS OfficeNumber,
             N'توكيل' AS MainGroup, 12 AS MainGroupId,
             A.[مقدم_الطلب] AS ApplicantName, A.[رقم_الهوية] AS IdNumber,
             A.[التاريخ_الميلادي] AS [Date], A.[حالة_الارشفة] AS ArchStatus, A.[طريقة_الطلب] AS Method,
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
             N'غير ذلك', 10,
             C.[مقدم_الطلب], C.[رقم_الهوية],
             C.[التاريخ_الميلادي], C.[حالة_الارشفة], C.[طريقة_الطلب],
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

  if ($table !== '') { $where[] = "OfficeTable = :tbl"; $p[':tbl'] = $table; }
  if ($mg    !== '') { $where[] = "MainGroup = :mg";    $p[':mg']  = $mg; }
  if ($q     !== '') {
    $where[] = "(OfficeNumber LIKE :q OR ApplicantName LIKE :q)";
    $p[':q'] = "%$q%";
  }
  if ($from !== '') { $where[] = "[Date] >= :from"; $p[':from'] = $from; }
  if ($to   !== '') { $where[] = "[Date] <  DATEADD(DAY,1,:to)"; $p[':to'] = $to; }
  if ($todayOnly)    { $where[] = "CAST([Date] AS date) = CAST(SYSDATETIME() AS date)"; }

  $wsql = $where ? (" WHERE ".implode(" AND ", $where)) : "";

  // Count total
  $sqlCount = "SELECT COUNT(*) AS cnt FROM ($base) X $wsql";
  $st = $pdo->prepare($sqlCount);
  foreach ($p as $k=>$v) $st->bindValue($k, $v);
  $st->execute();
  $total = (int)$st->fetch()['cnt'];

  // Auto today filter when huge & no filters
  $autoTodayApplied = false;
  $noFilters = ($q==='' && $mg==='' && $table==='' && $from==='' && $to==='' && !$todayOnly);
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
