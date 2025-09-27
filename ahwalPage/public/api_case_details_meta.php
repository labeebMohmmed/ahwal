<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $caseId = isset($_GET['caseId']) ? (int)$_GET['caseId'] : 0;
    if ($caseId <= 0) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'bad caseId'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1) Find the ModelID for this case
    $rowCase = $pdo->prepare("SELECT ModelID, DetailsJson FROM online.Cases WHERE CaseID=?");
    $rowCase->execute([$caseId]);
    $case = $rowCase->fetch();
    if (!$case) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'case not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $modelId = (int)$case['ModelID'];
    $answers = [];
    if ($case['DetailsJson']) {
        $dj = json_decode($case['DetailsJson'], true);
        $answers = $dj['answers']['fields'] ?? [];
    }

    // 2) Load the template from TableAddModel
    $rowTpl = $pdo->prepare("SELECT * FROM dbo.TableAddModel WHERE ID=?");
    $rowTpl->execute([$modelId]);
    $tpl = $rowTpl->fetch();
    if (!$tpl) {
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'template not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3) Convert columns into field descriptors
    // 3) Convert columns into field descriptors
$fields = [];
foreach ($tpl as $col=>$val) {
    if ($val===null || $val==='') continue;

    if (preg_match('/^itext\d+$/i',$col)) {
        $fields[] = [
            'key'   => $col,
            'type'  => 'text',
            'label' => $val,
            'maxLen'=> (int)($tpl[$col.'Length'] ?? 0)
        ];
    }
    elseif (preg_match('/^itxtDate\d+$/i',$col)) {
        $fields[] = [
            'key'   => $col,
            'type'  => 'date',
            'label' => $val
        ];
    }
    elseif (preg_match('/^icheck\d+$/i',$col)) {
        // if icheck has options column, use it
        $optCol = $col.'Option';
        $opts = [];
        if (!empty($tpl[$optCol])) {
            $opts = explode('_', $tpl[$optCol]);
        }
        $fields[] = [
            'key'     => $col,
            'type'    => (count($opts) > 0 && count($opts) <= 3) ? 'radio' : 'select',
            'label'   => $val,
            'options' => $opts
        ];
    }
    elseif (preg_match('/^icombo\d+$/i',$col)) {
        $optCol = $col.'Option';
        $opts = [];
        if (!empty($tpl[$optCol])) {
            $opts = explode('_', $tpl[$optCol]);
        }
        $fields[] = [
            'key'     => $col,
            'type'    => (count($opts) > 0 && count($opts) <= 3) ? 'radio' : 'select',
            'label'   => $val,
            'options' => $opts
        ];
    }
}


    // 4) Return JSON
    echo json_encode([
        'ok'=>true,
        'model'=>[
            'id'=>$modelId,
            'lang'=>$tpl['Lang'] ?? 'ar',
            'fields'=>$fields,
        ],
        'answers'=>$answers
    ], JSON_UNESCAPED_UNICODE);

} catch(Throwable $e) {
    error_log('api_case_details_meta.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
