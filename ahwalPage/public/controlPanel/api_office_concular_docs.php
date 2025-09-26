<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';

try {
    $num   = trim($_GET['officeNumber'] ?? '');
    $name  = trim($_GET['applicantName'] ?? '');
    $from  = trim($_GET['dateFrom'] ?? '');
    $to    = trim($_GET['dateTo'] ?? '');

    $params = [];

    // ---------- TableAuth subquery ----------
    $sqlAuth = "
        SELECT TOP (10)
            N'Auth' AS OfficeTable,
            A.id AS OfficeId,
            A.[رقم_التوكيل] AS OfficeNumber,
            N'توكيل' AS MainGroup,
            12 AS MainGroupId,
            A.[مقدم_الطلب] AS ApplicantName,
            A.[رقم_الهوية] AS IdNumber,
            A.[التاريخ_الميلادي] AS [Date],
            A.[حالة_الارشفة] AS ArchStatus,
            A.[طريقة_الطلب] AS Method,
            CASE 
              WHEN A.[مقدم_الطلب] IS NULL THEN N'معاملة جديدة'
              WHEN A.[مقدم_الطلب] IS NOT NULL AND A.[حالة_الارشفة] <> N'مؤرشف نهائي' THEN N'قيد المعالجة'
              WHEN A.[حالة_الارشفة] = N'مؤرشف نهائي' THEN N'مؤرشف نهائي'
              ELSE N'غير مؤرشف'
            END AS StatusTag,
            CASE 
              WHEN A.[طريقة_الطلب] = N'الكتروني' THEN N'اونلاين'
              WHEN A.[طريقة_الطلب] = N'عن طريق أحد مندوبي القنصلية' THEN N'بواسطة مندوب'
              ELSE N'حضور مباشر'
            END AS MethodTag,
            A.[PayloadJson]
        FROM dbo.TableAuth A
        WHERE 1=1
    ";

    if ($num !== '') {
        $sqlAuth .= " AND CAST(A.[رقم_التوكيل] AS NVARCHAR(255)) COLLATE Arabic_CI_AI LIKE :numAuth";
        $params[':numAuth'] = "%$num%";
    }
    if ($name !== '') {
        $sqlAuth .= " AND CAST(A.[مقدم_الطلب] AS NVARCHAR(255)) COLLATE Arabic_CI_AI LIKE :nameAuth";
        $params[':nameAuth'] = "%$name%";
    }
    if ($from !== '') {
        $sqlAuth .= " AND CAST(A.[التاريخ_الميلادي] AS DATE) >= CAST(:fromAuth AS DATE)";
        $params[':fromAuth'] = $from;
    }
    if ($to !== '') {
        $sqlAuth .= " AND CAST(A.[التاريخ_الميلادي] AS DATE) < DATEADD(DAY,1, CAST(:toAuth AS DATE))";
        $params[':toAuth'] = $to;
    }

    $sqlAuth .= " ORDER BY  A.id DESC";

    // ---------- TableCollection subquery ----------
    $sqlColl = "
        SELECT TOP (10)
            N'Collection' AS OfficeTable,
            C.id AS OfficeId,
            C.[رقم_المعاملة] AS OfficeNumber,
            C.[نوع_المعاملة] AS MainGroup,
            10 AS MainGroupId,
            C.[مقدم_الطلب] AS ApplicantName,
            C.[رقم_الهوية] AS IdNumber,
            C.[التاريخ_الميلادي] AS [Date],
            C.[حالة_الارشفة] AS ArchStatus,
            C.[طريقة_الطلب] AS Method,
            CASE 
              WHEN C.[مقدم_الطلب] IS NULL THEN N'معاملة جديدة'
              WHEN C.[مقدم_الطلب] IS NOT NULL AND C.[حالة_الارشفة] <> N'مؤرشف نهائي' THEN N'قيد المعالجة'
              WHEN C.[حالة_الارشفة] = N'مؤرشف نهائي' THEN N'مؤرشف نهائي'
              ELSE N'غير مؤرشف'
            END AS StatusTag,
            CASE 
              WHEN C.[طريقة_الطلب] = N'الكتروني' THEN N'اونلاين'
              WHEN C.[طريقة_الطلب] = N'عن طريق أحد مندوبي القنصلية' THEN N'بواسطة مندوب'
              ELSE N'حضور مباشر'
            END AS MethodTag,
            C.[PayloadJson]
        FROM dbo.TableCollection C
        WHERE 1=1
    ";

    if ($num !== '') {
        $sqlColl .= " AND CAST(C.[رقم_المعاملة] AS NVARCHAR(255)) COLLATE Arabic_CI_AI LIKE :numColl";
        $params[':numColl'] = "%$num%";
    }
    if ($name !== '') {
        $sqlColl .= " AND CAST(C.[مقدم_الطلب] AS NVARCHAR(255)) COLLATE Arabic_CI_AI LIKE :nameColl";
        $params[':nameColl'] = "%$name%";
    }
    if ($from !== '') {
        $sqlColl .= " AND CAST(C.[التاريخ_الميلادي] AS DATE) >= CAST(:fromColl AS DATE)";
        $params[':fromColl'] = $from;
    }
    if ($to !== '') {
        $sqlColl .= " AND CAST(C.[التاريخ_الميلادي] AS DATE) < DATEADD(DAY,1, CAST(:toColl AS DATE))";
        $params[':toColl'] = $to;
    }

    $sqlColl .= " ORDER BY C.id DESC";

    // ---------- Union both subqueries ----------
    $sql = "
        SELECT * FROM (
            $sqlAuth
        ) AS AuthSub
        UNION ALL
        SELECT * FROM (
            $sqlColl
        ) AS CollSub
        ORDER BY [Date] DESC, OfficeId DESC
    ";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'cases' => $rows,
        'sql' => $sql,
        'params' => $params
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
