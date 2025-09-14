<?php
// api_office_party_upsert.php
// Upserts applicants/authenticated/witnesses into dbo.TableAuth (office table)
// using comma / Arabic comma separated lists per column.

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require __DIR__ . '/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $caseId  = (int)($input['caseId'] ?? 0);          // TableAuth.ID
    $section = (string)($input['section'] ?? '');     // 'applicants' | 'authenticated' | 'witnesses'
    $index   = (int)($input['index'] ?? 0);           // 0-based
    $person  = $input['person'] ?? null;

    if ($caseId <= 0 || !$section || !is_array($person)) {
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'bad request'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- DB ---
    $pdo = db();

    // --- Helpers ---
    function split_list($s) {
        if ($s === null) return [];
        $s = str_replace('،', ',', (string)$s);
        $parts = array_map('trim', explode(',', $s));
        return ($parts === ['']) ? [] : $parts;
    }
    function join_list($arr) {
        $clean = array_map(fn($x)=>trim((string)$x), $arr);
        return implode(', ', $clean);
    }
    function ensure_len(&$arr, $n) { while (count($arr) < $n) $arr[] = ''; }
    function sex_to_ar($s) {
        $s = is_string($s) ? trim($s) : $s;
        if ($s === 'M' || $s === 'm') return 'ذكر';
        if ($s === 'F' || $s === 'f') return 'أنثى';
        if ($s === 'ذكر' || $s === 'أنثى') return $s;
        return '';
    }

    // Load row
    $st = $pdo->prepare("SELECT TOP 1 * FROM dbo.TableAuth WHERE ID = :id");
    $st->execute([':id'=>$caseId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updates = []; // [columnName => newValue]

    // ============ APPLICANTS ============
    if ($section === 'applicants') {
        // $person: { name, sex, job, nationality?, residenceStatus, dob, ids:[{type,number,issuer,expiry}] }
        $ids0 = (isset($person['ids'][0]) && is_array($person['ids'][0])) ? $person['ids'][0] : [];

        $cols = [
            'name'              => 'مقدم_الطلب',
            'sex'               => 'النوع',
            'job'               => 'المهنة',
            'id_type'           => 'نوع_الهوية',
            'id_number'         => 'رقم_الهوية',
            'issuer'            => 'مكان_الإصدار',
            'expiry'            => 'انتهاء_الصلاحية',
            'dob'               => 'تاريخ_الميلاد',
            'residenceStatus'   => 'وضع_الإقامة',
        ];
        $lists = [
            'name'              => split_list($row[$cols['name']]              ?? null),
            'sex'               => split_list($row[$cols['sex']]               ?? null),
            'job'               => split_list($row[$cols['job']]               ?? null),
            'id_type'           => split_list($row[$cols['id_type']]           ?? null),
            'id_number'         => split_list($row[$cols['id_number']]         ?? null),
            'issuer'            => split_list($row[$cols['issuer']]            ?? null),
            'expiry'            => split_list($row[$cols['expiry']]            ?? null),
            'dob'               => split_list($row[$cols['dob']]               ?? null),
            'residenceStatus'   => split_list($row[$cols['residenceStatus']]   ?? null),
        ];

        $counts = array_map('count', $lists);
        $maxLen = $counts ? max($counts) : 0;
        $N      = max($index + 1, $maxLen);
        foreach ($lists as &$arr) ensure_len($arr, $N);
        unset($arr);

        // Assign at index
        $lists['name'][$index]              = (string)($person['name'] ?? '');
        $lists['sex'][$index]               = sex_to_ar($person['sex'] ?? '');
        $lists['job'][$index]               = (string)($person['job'] ?? '');
        $lists['id_type'][$index]           = (string)($ids0['type'] ?? '');
        $lists['id_number'][$index]         = (string)($ids0['number'] ?? '');
        $lists['issuer'][$index]            = (string)($ids0['issuer'] ?? '');
        $lists['expiry'][$index]            = (string)($ids0['expiry'] ?? '');
        $lists['dob'][$index]               = (string)($person['dob'] ?? '');               // ← FIX
        $lists['residenceStatus'][$index]   = (string)($person['residenceStatus'] ?? '');   // ← FIX

        foreach ($lists as $k => $arr) {
            $col = $cols[$k];
            $updates[$col] = join_list($arr);
        }
    }
    // ============ AUTHENTICATED (Principal) ============
    elseif ($section === 'authenticated') {
        // $person: { name, sex, nationality, ids:[{type,number}] }
        $ids0 = (isset($person['ids'][0]) && is_array($person['ids'][0])) ? $person['ids'][0] : [];

        $cols = [
            'name'        => 'الموكَّل',
            'sex'         => 'جنس_الموكَّل',
            'nationality' => 'جنسية_الموكل',
            'id_number'   => 'هوية_الموكل',
            'id_type'     => 'نوع_هوية',
        ];
        $lists = [
            'name'        => split_list($row[$cols['name']]        ?? null),
            'sex'         => split_list($row[$cols['sex']]         ?? null),
            'nationality' => split_list($row[$cols['nationality']] ?? null),
            'id_number'   => split_list($row[$cols['id_number']]   ?? null),
            'id_type'     => split_list($row[$cols['id_type']]     ?? null),
        ];

        $counts = array_map('count', $lists);
        $maxLen = $counts ? max($counts) : 0;
        $N      = max($index + 1, $maxLen);
        foreach ($lists as &$arr) ensure_len($arr, $N);
        unset($arr);

        $lists['name'][$index]        = (string)($person['name'] ?? '');
        $lists['sex'][$index]         = sex_to_ar($person['sex'] ?? '');
        $lists['nationality'][$index] = (string)($person['nationality'] ?? '');
        $lists['id_number'][$index]   = (string)($ids0['number'] ?? '');
        $lists['id_type'][$index]     = (string)($ids0['type'] ?? '');

        foreach ($lists as $k => $arr) {
            $col = $cols[$k];
            $updates[$col] = join_list($arr);
        }
    }
    // ============ WITNESSES ============
    elseif ($section === 'witnesses') {
        // $person: { name, ids:[{number, type?}] } — office stores only name + number
        $ids0 = (isset($person['ids'][0]) && is_array($person['ids'][0])) ? $person['ids'][0] : [];

        if ($index === 0) {
            $updates['الشاهد_الأول'] = (string)($person['name'] ?? '');
            $updates['هوية_الأول']   = (string)($ids0['number'] ?? '');
        } elseif ($index === 1) {
            $updates['الشاهد_الثاني'] = (string)($person['name'] ?? '');
            $updates['هوية_الثاني']   = (string)($ids0['number'] ?? '');
        } else {
            http_response_code(410);
            echo json_encode(['ok'=>false,'error'=>'witness index must be 0 or 1'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    else {
        http_response_code(402);
        echo json_encode(['ok'=>false,'error'=>'unknown section'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Execute UPDATE if needed ---
    if ($updates) {
        $sets = [];
        $params = [':id' => $caseId];
        foreach ($updates as $col => $val) {
            $param = ':c_' . substr(md5($col), 0, 12);
            $sets[] = "[$col] = $param";
            $params[$param] = $val;
        }
        $sql = "UPDATE dbo.TableAuth SET " . implode(', ', $sets) . " WHERE ID = :id";
        $pdo->prepare($sql)->execute($params);
    }

    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'server error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
