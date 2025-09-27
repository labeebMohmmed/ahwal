<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');


if (isset($_GET['ping'])) {
    echo json_encode(['ok' => true, 'msg' => 'api_office_party_upsert.php is reachable']);
    exit;
}

try {
    $input   = json_decode(file_get_contents('php://input'), true) ?: [];
    $caseId  = (int)($input['caseId']  ?? 0);
    $section = (string)($input['section'] ?? ''); // 'applicants' | 'authenticated' | 'witnesses'
    $index   = (int)($input['index']   ?? 0);     // 0-based
    $person  = $input['person'] ?? null;
    $mainGroup = trim((string)($input['mainGroup'] ?? $_GET['mainGroup'] ?? 'توكيل'));

    if ($caseId <= 0 || !$section || !is_array($person)) {
        http_response_code(400);
        echo json_encode([
            'ok'=>false,
            'error'=>'bad request',
            'caseId'=>$caseId,
            'section'=>$section,
            'mainGroup'=>$mainGroup
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Decide target table by mainGroup
    $table = ($mainGroup === 'توكيل') ? 'dbo.TableAuth' : 'dbo.TableCollection';

    // Load row
    $st = $pdo->prepare("SELECT TOP 1 * FROM $table WHERE ID = :id");
    $st->execute([':id'=>$caseId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode([
            'ok'=>false,
            'error'=>'not found',
            'table'=>$table,
            'caseId'=>$caseId,
            'mainGroup'=>$mainGroup
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $hasCol = fn(string $c) => array_key_exists($c, $row);

    // ---- Helpers ----
    $split_list = function ($s): array {
        if ($s === null) return [];
        $s = str_replace('،', ',', (string)$s);
        $parts = array_map('trim', explode(',', $s));
        return ($parts === ['']) ? [] : $parts;
    };
    $join_list = function (array $arr): string {
        $clean = array_map(fn($x)=>trim((string)$x), $arr);
        return implode(', ', $clean);
    };
    $ensure_len = function (&$arr, int $n): void { while (count($arr) < $n) $arr[] = ''; };
    $sex_to_ar  = function ($s): string {
        $s = is_string($s) ? trim($s) : $s;
        if ($s === 'M' || $s === 'm') return 'ذكر';
        if ($s === 'F' || $s === 'f') return 'أنثى';
        if ($s === 'ذكر' || $s === 'أنثى') return (string)$s;
        return '';
    };

    $updates = [];

    // ====================== APPLICANTS ======================
    if ($section === 'applicants') {
        $ids0 = (isset($person['ids'][0]) && is_array($person['ids'][0])) ? $person['ids'][0] : [];

        $cols = [
            'name'            => 'مقدم_الطلب',
            'sex'             => 'النوع',
            'job'             => 'المهنة',
            'id_type'         => 'نوع_الهوية',
            'id_number'       => 'رقم_الهوية',
            'issuer'          => 'مكان_الإصدار',
            'expiry'          => 'انتهاء_الصلاحية',
            'dob'             => 'تاريخ_الميلاد',
            'residenceStatus' => 'وضع_الإقامة',
        ];

        $lists = [];
        foreach ($cols as $k => $colName) {
            $lists[$k] = $split_list($hasCol($colName) ? ($row[$colName] ?? null) : null);
        }

        $counts = array_map('count', $lists);
        $maxLen = $counts ? max($counts) : 0;
        $N      = max($index + 1, $maxLen);
        foreach ($lists as &$arr) $ensure_len($arr, $N);
        unset($arr);

        $lists['name'][$index]            = (string)($person['name'] ?? '');
        $lists['sex'][$index]             = $sex_to_ar($person['sex'] ?? '');
        $lists['job'][$index]             = (string)($person['job'] ?? '');
        $lists['id_type'][$index]         = (string)($ids0['type'] ?? '');
        $lists['id_number'][$index]       = (string)($ids0['number'] ?? '');
        $lists['issuer'][$index]          = (string)($ids0['issuer'] ?? '');
        $lists['expiry'][$index]          = (string)($ids0['expiry'] ?? '');
        $lists['dob'][$index]             = (string)($person['dob'] ?? '');
        $lists['residenceStatus'][$index] = (string)($person['residenceStatus'] ?? '');

        foreach ($lists as $k => $arr) {
            $col = $cols[$k];
            if ($hasCol($col)) {
                $updates[$col] = $join_list($arr);
            }
        }
    }

    // ====================== AUTHENTICATED ======================
    elseif ($section === 'authenticated') {
        if ($table !== 'dbo.TableAuth') {
            http_response_code(409);
            echo json_encode([
                'ok'=>false,
                'error'=>'authenticated not supported here',
                'table'=>$table,
                'caseId'=>$caseId,
                'mainGroup'=>$mainGroup
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ids0 = (isset($person['ids'][0]) && is_array($person['ids'][0])) ? $person['ids'][0] : [];

        $cols = [
            'name'        => 'الموكَّل',
            'sex'         => 'جنس_الموكَّل',
            'nationality' => 'جنسية_الموكل',
            'id_number'   => 'هوية_الموكل',
            'id_type'     => 'نوع_هوية',
        ];

        $lists = [];
        foreach ($cols as $k => $colName) {
            $lists[$k] = $split_list($row[$colName] ?? null);
        }

        $counts = array_map('count', $lists);
        $maxLen = $counts ? max($counts) : 0;
        $N      = max($index + 1, $maxLen);
        foreach ($lists as &$arr) $ensure_len($arr, $N);
        unset($arr);

        $lists['name'][$index]        = (string)($person['name'] ?? '');
        $lists['sex'][$index]         = $sex_to_ar($person['sex'] ?? '');
        $lists['nationality'][$index] = (string)($person['nationality'] ?? '');
        $lists['id_number'][$index]   = (string)($ids0['number'] ?? '');
        $lists['id_type'][$index]     = (string)($ids0['type'] ?? '');

        foreach ($lists as $k => $arr) {
            $updates[$cols[$k]] = $join_list($arr);
        }
    }

    // ====================== WITNESSES ======================
    elseif ($section === 'witnesses') {
        $ids0 = (isset($person['ids'][0]) && is_array($person['ids'][0])) ? $person['ids'][0] : [];

        $colNameKey  = ($index === 0) ? 'الشاهد_الأول' : (($index === 1) ? 'الشاهد_الثاني' : null);
        $colNumberKey= ($index === 0) ? 'هوية_الأول'   : (($index === 1) ? 'هوية_الثاني'   : null);

        if ($colNameKey === null || $colNumberKey === null) {
            http_response_code(410);
            echo json_encode([
                'ok'=>false,
                'error'=>'witness index must be 0 or 1',
                'caseId'=>$caseId,
                'table'=>$table
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$hasCol($colNameKey) || !$hasCol($colNumberKey)) {
            http_response_code(409);
            echo json_encode([
                'ok'=>false,
                'error'=>'witness columns missing',
                'caseId'=>$caseId,
                'table'=>$table
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $updates[$colNameKey]   = (string)($person['name'] ?? '');
        $updates[$colNumberKey] = (string)($ids0['number'] ?? '');
    }

    else {
        http_response_code(400);
        echo json_encode([
            'ok'=>false,
            'error'=>'unknown section',
            'caseId'=>$caseId,
            'table'=>$table,
            'mainGroup'=>$mainGroup
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- Execute UPDATE ----
    if ($updates) {
        $sets   = [];
        $params = [':id' => $caseId];
        foreach ($updates as $col => $val) {
            $param = ':c_' . substr(md5($col), 0, 12);
            $sets[] = "[$col] = $param";
            $params[$param] = $val;
        }
        $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE ID = :id";
        $pdo->prepare($sql)->execute($params);
    }

    echo json_encode([
        'ok'=>true,
        'table'=>$table,
        'caseId'=>$caseId,
        'mainGroup'=>$mainGroup,
        'updatedCols'=>array_keys($updates)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'server error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
