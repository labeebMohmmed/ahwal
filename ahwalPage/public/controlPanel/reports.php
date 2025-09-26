<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$type    = $_GET['type']    ?? '';
$date    = $_GET['date']    ?? null;
$year    = $_GET['year']    ?? null;
$month   = $_GET['month']   ?? null;
$quarter = $_GET['quarter'] ?? null;
$half    = $_GET['half']    ?? null;

try {
    // Case types fixed (from DOCX template)
    $caseTypes = [
        "إقرار",
        "إقرار مشفوع باليمين",
        "شهادة لمن يهمه الأمر",
        "مذكرة لسفارة عربية",
        "مذكرة لسفارة أجنبية",
        "توكيل",
        "التوثيق",
        "وثيقة زواج",
        "وثيقة طلاق"
    ];

    // helpers
    function getQuarterRange(int $q): array {
        $start = (($q-1) * 3) + 1;
        return [$start, $start + 2];
    }
    function getHalfRange(int $h): array {
        return $h === 1 ? [1,6] : [7,12];
    }

    // === DAILY (raw details) ===
    if ($type === 'daily' && $date) {
        $sqlColl = "
            SELECT رقم_المعاملة, نوع_المعاملة, مقدم_الطلب, التاريخ_الميلادي
            FROM TableCollection
            WHERE حالة_الارشفة = N'مؤرشف نهائي'
              AND TRY_CAST(التاريخ_الميلادي AS DATE) = :date
            ORDER BY رقم_المعاملة
        ";
        $st = $pdo->prepare($sqlColl);
        $st->execute([':date'=>$date]);
        $rowsColl = $st->fetchAll(PDO::FETCH_ASSOC);

        $sqlAuth = "
            SELECT رقم_التوكيل, نوع_التوكيل, مقدم_الطلب, التاريخ_الميلادي, الموكَّل
            FROM TableAuth
            WHERE حالة_الارشفة = N'مؤرشف نهائي'
              AND TRY_CAST(التاريخ_الميلادي AS DATE) = :date
            ORDER BY رقم_التوكيل
        ";
        $st = $pdo->prepare($sqlAuth);
        $st->execute([':date'=>$date]);
        $rowsAuth = $st->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'collection' => $rowsColl,
            'auth' => $rowsAuth
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

        // === PERIODIC (monthly / quarterly / biannually / yearly) ===
    $where = "حالة_الارشفة = N'مؤرشف نهائي'";
    $params = [];
    $periodExpr = "";

    if ($type === 'monthly' && $year && $month) {
        $where .= " AND YEAR(TRY_CAST(التاريخ_الميلادي AS DATE)) = :year
                    AND MONTH(TRY_CAST(التاريخ_الميلادي AS DATE)) = :month";
        $params = [':year'=>$year, ':month'=>$month];
        $periodExpr = "CONVERT(varchar(10), TRY_CAST(التاريخ_الميلادي AS DATE), 23)"; // full date
    }
    elseif ($type === 'quarterly' && $year && $quarter) {
        [$m1,$m2] = getQuarterRange((int)$quarter);
        $where .= " AND YEAR(TRY_CAST(التاريخ_الميلادي AS DATE)) = :year
                    AND MONTH(TRY_CAST(التاريخ_الميلادي AS DATE)) BETWEEN :m1 AND :m2";
        $params = [':year'=>$year, ':m1'=>$m1, ':m2'=>$m2];
        $periodExpr = "MONTH(TRY_CAST(التاريخ_الميلادي AS DATE))"; // month num
    }
    elseif ($type === 'biannually' && $year && $half) {
        [$m1,$m2] = getHalfRange((int)$half);
        $where .= " AND YEAR(TRY_CAST(التاريخ_الميلادي AS DATE)) = :year
                    AND MONTH(TRY_CAST(التاريخ_الميلادي AS DATE)) BETWEEN :m1 AND :m2";
        $params = [':year'=>$year, ':m1'=>$m1, ':m2'=>$m2];
        $periodExpr = "MONTH(TRY_CAST(التاريخ_الميلادي AS DATE))";
    }
    elseif ($type === 'yearly' && $year) {
        $where .= " AND YEAR(TRY_CAST(التاريخ_الميلادي AS DATE)) = :year";
        $params = [':year'=>$year];
        $periodExpr = "MONTH(TRY_CAST(التاريخ_الميلادي AS DATE))";
    }
    else {
        echo json_encode(['ok'=>false,'error'=>'Unsupported type or missing params'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // query builder
    function fetchData(PDO $pdo, string $table, string $periodExpr, string $caseField, string $where, array $params): array {
        $sql = "
            SELECT 
                $periodExpr AS Period,
                $caseField AS CaseType,
                COUNT(*) AS Cnt
            FROM $table
            WHERE $where
            GROUP BY $periodExpr, $caseField
            ORDER BY $periodExpr
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $rowsColl = fetchData($pdo, "TableCollection", $periodExpr, "نوع_المعاملة", $where, $params);
    $rowsAuth = fetchData($pdo, "TableAuth", $periodExpr, "نوع_التوكيل", $where, $params);

    // merge
    $merged = [];
    foreach ([$rowsColl, $rowsAuth] as $rows) {
        foreach ($rows as $r) {
            $p = $r['Period'];
            $ct = $r['CaseType'];
            $cnt = (int)$r['Cnt'];
            if (!isset($merged[$p])) $merged[$p] = [];
            if (!isset($merged[$p][$ct])) $merged[$p][$ct] = 0;
            $merged[$p][$ct] += $cnt;
        }
    }

    // normalize rows
    $finalRows = [];
    $grandTotals = array_fill_keys($caseTypes, 0);
    $grandSum = 0;

    $monthNames = [
        1=>"يناير",2=>"فبراير",3=>"مارس",4=>"أبريل",5=>"مايو",6=>"يونيو",
        7=>"يوليو",8=>"أغسطس",9=>"سبتمبر",10=>"أكتوبر",11=>"نوفمبر",12=>"ديسمبر"
    ];

    foreach ($merged as $period => $row) {
        $out = ['Period'=>$period];

        // for summary table only: change month numbers → month names
        if ($type !== 'daily' && $type !== 'monthly' && is_numeric($period)) {
            $out['Period'] = $monthNames[(int)$period] ?? $period;
        }

        $rowSum = 0;
        foreach ($caseTypes as $ct) {
            $val = $row[$ct] ?? 0;
            $out[$ct] = $val;
            $rowSum += $val;
            $grandTotals[$ct] += $val;
        }
        $out['Total'] = $rowSum;
        $grandSum += $rowSum;
        $finalRows[] = $out;
    }

    // add totals
    $totalsRow = ['Period'=>'الإجمالي'];
    foreach ($caseTypes as $ct) $totalsRow[$ct] = $grandTotals[$ct];
    $totalsRow['Total'] = $grandSum;
    $finalRows[] = $totalsRow;

    echo json_encode([
        'ok' => true,
        'caseTypes' => $caseTypes,
        'rows' => array_values($finalRows),
        'grandTotal' => $grandSum
    ], JSON_UNESCAPED_UNICODE);


} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'=>false,
        'error'=>$e->getMessage(),
        'file'=>basename($e->getFile()),
        'line'=>$e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
