<?php
// api_case_details_meta.php — return model meta by modelId only
declare(strict_types=1);

require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $modelId = isset($_GET['modelId']) ? (int)$_GET['modelId'] : 0;
    if ($modelId <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad modelId'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = db();

    // Load the template from TableAddModel
    $rowTpl = $pdo->prepare("SELECT * FROM dbo.TableAddModel WHERE ID = ?");
    $rowTpl->execute([$modelId]);
    $tpl = $rowTpl->fetch(PDO::FETCH_ASSOC);
    if (!$tpl) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'template not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Build field descriptors from columns
    $fields = [];
    foreach ($tpl as $col => $val) {
        if ($val === null || $val === '') continue;

        if (preg_match('/^itext\d+$/i', $col)) {
            $fields[] = [
                'key'    => $col,
                'type'   => 'text',
                'label'  => $val,
                'maxLen' => (int)($tpl[$col.'Length'] ?? 0),
            ];
        }
        elseif (preg_match('/^itxtDate\d+$/i', $col)) {
            $fields[] = [
                'key'   => $col,
                'type'  => 'date',
                'label' => $val,
            ];
        }
        elseif (preg_match('/^icheck\d+$/i', $col)) {
            $optCol = $col.'Option';
            $opts = [];
            if (!empty($tpl[$optCol])) $opts = explode('_', (string)$tpl[$optCol]);
            $fields[] = [
                'key'     => $col,
                'type'    => (count($opts) > 0 && count($opts) <= 3) ? 'radio' : 'select',
                'label'   => $val,
                'options' => $opts,
            ];
        }
        elseif (preg_match('/^icombo\d+$/i', $col)) {
            $optCol = $col.'Option';
            $opts = [];
            if (!empty($tpl[$optCol])) $opts = explode('_', (string)$tpl[$optCol]);
            $fields[] = [
                'key'     => $col,
                'type'    => (count($opts) > 0 && count($opts) <= 3) ? 'radio' : 'select',
                'label'   => $val,
                'options' => $opts,
            ];
        }
    }

    echo json_encode([
        'ok'    => true,
        'model' => [
            'id'         => $modelId,
            'lang'       => $tpl['Lang'] ?? 'ar',
            'mainGroup'  => $tpl['mainGroup'] ?? null,
            'altColName' => $tpl['altColName'] ?? null,
            'altSubColName' => $tpl['altSubColName'] ?? null,
            'fields'     => $fields,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_case_details_meta.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
