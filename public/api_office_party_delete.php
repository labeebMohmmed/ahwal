<?php
declare(strict_types=1);

// api_office_party_delete.php
// Remove one applicant/authenticated/witness from dbo.TableAuth,
// honoring comma/Arabic-comma lists and alignment rules.

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $in      = json_decode(file_get_contents('php://input'), true) ?: [];
    $caseId  = (int)($in['caseId']  ?? 0);                       // TableAuth.ID
    $section = (string)($in['section'] ?? '');                   // 'applicants' | 'authenticated' | 'witnesses'
    $index   = (int)($in['index']   ?? -1);                      // 0-based

    if ($caseId <= 0 || !in_array($section, ['applicants','authenticated','witnesses'], true) || $index < 0) {
        http_response_code(402);
        echo json_encode(['ok'=>false,'error'=>'bad input'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = db();

    // Helpers
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
    $ensure_len = function (&$arr, int $n): void { while (count($arr) < $n) { $arr[] = ''; } };

    // Load row
    $st = $pdo->prepare("SELECT TOP 1 * FROM dbo.TableAuth WHERE ID = :id");
    $st->execute([':id' => $caseId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $updates = []; // [columnName => newValue]

    if ($section === 'applicants') {
        // Columns for applicants (aligned lists)
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

        // Current lists
        $lists = [
            'name'            => $split_list($row[$cols['name']]            ?? null),
            'sex'             => $split_list($row[$cols['sex']]             ?? null),
            'job'             => $split_list($row[$cols['job']]             ?? null),
            'id_type'         => $split_list($row[$cols['id_type']]         ?? null),
            'id_number'       => $split_list($row[$cols['id_number']]       ?? null),
            'issuer'          => $split_list($row[$cols['issuer']]          ?? null),
            'expiry'          => $split_list($row[$cols['expiry']]          ?? null),
            'dob'             => $split_list($row[$cols['dob']]             ?? null),
            'residenceStatus' => $split_list($row[$cols['residenceStatus']] ?? null),
        ];

        // Determine current max length across lists
        $counts = array_map('count', $lists);
        $maxLen = $counts ? max($counts) : 0;

        if ($maxLen === 0) {
            // Nothing to remove; ensure single empty slot
            foreach ($lists as $k => &$arr) { $arr = ['']; }
            unset($arr);
        } elseif ($maxLen === 1) {
            // Single entry: just clear it (set index 0 to '')
            foreach ($lists as &$arr) {
                $arr = [''];
            }
            unset($arr);
        } else {
            // Multi entries: remove at index across all lists
            if ($index >= $maxLen) {
                http_response_code(400);
                echo json_encode(['ok'=>false,'error'=>'index out of range'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            // Pad all to uniform length then splice
            foreach ($lists as &$arr) { $ensure_len($arr, $maxLen); array_splice($arr, $index, 1); }
            unset($arr);
        }

        // Build updates
        foreach ($lists as $k => $arr) {
            $updates[$cols[$k]] = $join_list($arr);
        }

    } elseif ($section === 'authenticated') {
        // Columns for principals (aligned lists)
        $cols = [
            'name'        => 'الموكَّل',
            'sex'         => 'جنس_الموكَّل',
            'nationality' => 'جنسية_الموكل',
            'id_number'   => 'هوية_الموكل',
            'id_type'     => 'نوع_هوية',
        ];

        $lists = [
            'name'        => $split_list($row[$cols['name']]        ?? null),
            'sex'         => $split_list($row[$cols['sex']]         ?? null),
            'nationality' => $split_list($row[$cols['nationality']] ?? null),
            'id_number'   => $split_list($row[$cols['id_number']]   ?? null),
            'id_type'     => $split_list($row[$cols['id_type']]     ?? null),
        ];

        $counts = array_map('count', $lists);
        $maxLen = $counts ? max($counts) : 0;

        if ($maxLen === 0) {
            foreach ($lists as $k => &$arr) { $arr = ['']; }
            unset($arr);
        } elseif ($maxLen === 1) {
            foreach ($lists as &$arr) { $arr = ['']; }
            unset($arr);
        } else {
            if ($index >= $maxLen) {
                http_response_code(400);
                echo json_encode(['ok'=>false,'error'=>'index out of range'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            foreach ($lists as &$arr) { $ensure_len($arr, $maxLen); array_splice($arr, $index, 1); }
            unset($arr);
        }

        foreach ($lists as $k => $arr) {
            $updates[$cols[$k]] = $join_list($arr);
        }

    } else { // witnesses
        if ($index > 1) {
            http_response_code(401);
            echo json_encode(['ok'=>false,'error'=>'witness index must be 0 or 1'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $w1n = (string)($row['الشاهد_الأول'] ?? '');
        $w1i = (string)($row['هوية_الأول']   ?? '');
        $w2n = (string)($row['الشاهد_الثاني'] ?? '');
        $w2i = (string)($row['هوية_الثاني']   ?? '');

        if ($index === 0) {
            if ($w2n !== '' || $w2i !== '') {
                // shift second → first, clear second
                $updates['الشاهد_الأول'] = $w2n;
                $updates['هوية_الأول']   = $w2i;
                $updates['الشاهد_الثاني'] = '';
                $updates['هوية_الثاني']   = '';
            } else {
                // only first exists → clear it
                $updates['الشاهد_الأول'] = '';
                $updates['هوية_الأول']   = '';
            }
        } else { // index === 1
            // clear the second
            $updates['الشاهد_الثاني'] = '';
            $updates['هوية_الثاني']   = '';
        }
    }

    // Apply UPDATE
    if ($updates) {
        $sets   = [];
        $params = [':id' => $caseId];
        foreach ($updates as $col => $val) {
            $p = ':c_' . substr(md5($col), 0, 12);
            $sets[] = "[$col] = $p";
            $params[$p] = $val;
        }
        $sql = "UPDATE dbo.TableAuth SET " . implode(', ', $sets) . " WHERE ID = :id";
        $pdo->prepare($sql)->execute($params);
    }

    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_office_party_delete.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
