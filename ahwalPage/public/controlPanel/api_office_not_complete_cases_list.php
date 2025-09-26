<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/../config.php'; // use shared config.php with $pdo

function jerr(string $msg, array $extra = []): void {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 20)));
    $q        = trim($_GET['q'] ?? '');
    $from     = trim($_GET['dateFrom'] ?? '');
    $to       = trim($_GET['dateTo'] ?? '');
    $mg       = trim($_GET['mainGroup'] ?? '');
    $table    = trim($_GET['officeTable'] ?? '');
    $todayOnly= ($_GET['todayOnly'] ?? '') === '1';
    $debug    = ($_GET['debug'] ?? '') === '1';

    // --- Base SQL from view ---
    $base = "
        SELECT OfficeTable, OfficeId, OfficeNumber, MainGroup, MainGroupId,
               ApplicantName, [Date], ArchStatus, Method,
               StatusTag, MethodTag
        FROM [AhwalDataBase].[office].[vw_OfficeUnified]
        WHERE StatusTag <> N'مؤرشف نهائي'
    ";

    // --- Build filters ---
    $where = [];
    $p = [];

    if ($table !== '') { $where[] = "OfficeTable = :tbl"; $p[':tbl'] = $table; }
    if ($mg    !== '') { $where[] = "MainGroup = :mg";    $p[':mg']  = $mg; }
    if ($q     !== '') {
        $where[] = "(OfficeNumber LIKE :q OR ApplicantName LIKE :q)";
        $p[':q'] = "%$q%";
    }
    if ($from !== '') { $where[] = "[Date] >= :from"; $p[':from'] = $from; }
    if ($to   !== '') { $where[] = "[Date] < DATEADD(DAY,1,:to)"; $p[':to'] = $to; }
    if ($todayOnly)   { $where[] = "CAST([Date] AS date) = CAST(SYSDATETIME() AS date)"; }

    $wsql = $where ? " WHERE " . implode(" AND ", $where) : "";

    // --- Count total ---
    $sqlCount = "SELECT COUNT(*) AS cnt FROM ($base) X $wsql";
    $st = $pdo->prepare($sqlCount);
    foreach ($p as $k => $v) $st->bindValue($k, $v);
    $st->execute();
    $total = (int)$st->fetch()['cnt'];

    // --- Auto today filter when huge & no filters ---
    $autoTodayApplied = false;
    $noFilters = ($q==='' && $mg==='' && $table==='' && $from==='' && $to==='' && !$todayOnly);
    if ($total > 20 && $noFilters) {
        $wsql = ($wsql ? "$wsql AND " : " WHERE ") . "CAST([Date] AS date) = CAST(SYSDATETIME() AS date)";
        $autoTodayApplied = true;
        $st = $pdo->prepare("SELECT COUNT(*) AS cnt FROM ($base) X $wsql");
        foreach ($p as $k => $v) $st->bindValue($k, $v);
        $st->execute();
        $total = (int)$st->fetch()['cnt'];
    }

    // --- Fetch page ---
    $offset = ($page - 1) * $pageSize;
    $sql = "
        SELECT * FROM ($base) X
        $wsql
        ORDER BY [Date] DESC, OfficeId DESC
        OFFSET :off ROWS FETCH NEXT :ps ROWS ONLY
    ";
    $st = $pdo->prepare($sql);
    foreach ($p as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->bindValue(':ps',  $pageSize, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'cases' => $rows,     // matches officeCasesControl()
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $total,
        'autoTodayApplied' => $autoTodayApplied,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    jerr('Server error', [
        'debug' => $debug ? [
            'type' => get_class($e),
            'msg'  => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
        ] : null
    ]);
}
