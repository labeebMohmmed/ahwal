<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');


try {
    $caseId = isset($_GET['caseId']) ? (int)$_GET['caseId'] : 0;
    if ($caseId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad caseId'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Schema (adjust if needed)
    $SCHEMA = 'online';

    // Load row
    $st = $pdo->prepare("SELECT PartyJson, DetailsJson FROM [$SCHEMA].[Cases] WHERE CaseID=?");
    $st->execute([$caseId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $party   = $row['PartyJson']   ? json_decode($row['PartyJson'], true)   : [];
    $details = $row['DetailsJson'] ? json_decode($row['DetailsJson'], true) : [];

    // ---- derive requirements from main group ----
    $mainGroup = '';
    if (isset($details['model']['mainGroup'])) {
        $mainGroup = (string)$details['model']['mainGroup'];
    } elseif (isset($details['mainGroup'])) {
        $mainGroup = (string)$details['mainGroup'];
    } elseif (isset($details['meta']['mainGroup'])) {
        $mainGroup = (string)$details['meta']['mainGroup'];
    } elseif (isset($details['MainGroup'])) {
        $mainGroup = (string)$details['MainGroup'];
    }

    $mg   = trim($mainGroup);
    $mg_n = str_replace(['إ','أ','آ','ة'], ['ا','ا','ا','ه'], $mg);

    $needAuthenticated = false;
    $needWitnesses = false;
    $needWitnessesOptional = false;

    if ($mg !== '') {
        if (strpos($mg, 'توكيل') !== false || strpos($mg_n, 'توكيل') !== false) {
            $needAuthenticated = true;
            $needWitnesses = true;
        } elseif (strpos($mg, 'إقرار مشفوع باليمين') !== false
               || strpos($mg_n, 'اقرار مشفوع باليمين') !== false) {
            $needWitnessesOptional = true;
        }
    }

    $requirements = [
        'needAuthenticated'     => $needAuthenticated,
        'needWitnesses'         => $needWitnesses,
        'needWitnessesOptional' => $needWitnessesOptional,
    ];

    // ---- cache back into DetailsJson if changed ----
    $existing = $details['requirements'] ?? null;
    $shouldUpdate = !$existing
        || (bool)($existing['needAuthenticated']     ?? null) !== $requirements['needAuthenticated']
        || (bool)($existing['needWitnesses']         ?? null) !== $requirements['needWitnesses']
        || (bool)($existing['needWitnessesOptional'] ?? null) !== $requirements['needWitnessesOptional'];

    if ($shouldUpdate) {
        $details['requirements'] = $requirements;
        $up = $pdo->prepare("UPDATE [$SCHEMA].[Cases] SET DetailsJson=? WHERE CaseID=?");
        $up->execute([ json_encode($details, JSON_UNESCAPED_UNICODE), $caseId ]);
    }

    echo json_encode(['ok' => true, 'party' => $party, 'requirements' => $requirements], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_case_party_get.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error'], JSON_UNESCAPED_UNICODE);
}
