<?php
// api_office_case_update_type.php
// Update نوع_التوكيل + إجراء_التوكيل (for توكيل)
// or    نوع_المعاملة + نوع_الإجراء (for others)

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');


try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $caseId    = (int)($input['caseId'] ?? 0);
    $mainGroup = trim((string)($input['mainGroup'] ?? ''));
    $altCol    = trim((string)($input['altCol'] ?? ''));
    $altSub    = trim((string)($input['altSub'] ?? ''));

    if ($caseId <= 0 || !$mainGroup || !$altCol || !$altSub) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'bad request',
            'input' => $input
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Decide target table and column names
    if ($mainGroup === 'توكيل') {
        $table   = 'dbo.TableAuth';
        $colType = 'نوع_التوكيل';
        $colSub  = 'إجراء_التوكيل';
    } else {
        $table   = 'dbo.TableCollection';
        $colType = 'نوع_المعاملة';
        $colSub  = 'نوع_الإجراء';
    }

    // Use altCol for نوع, altSub for الإجراء
    $sql = "UPDATE $table
            SET [$colType] = :valType,
                [$colSub]  = :valSub
            WHERE ID = :id";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':valType' => $altCol,
        ':valSub'  => $altSub,
        ':id'      => $caseId
    ]);

    $rows = $st->rowCount();

    echo json_encode([
        'ok'      => true,
        'table'   => $table,
        'caseId'  => $caseId,
        'updated' => $rows,
        'set'     => [$colType => $altCol, $colSub => $altSub]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'server error',
        'msg'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
