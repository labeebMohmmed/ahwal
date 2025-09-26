<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    // --- 1) TableListCombo (only needed columns)
    $sqlCombo = "SELECT MandoubNames, ArabCountries, ForiegnCountries
                 FROM dbo.TableListCombo
                 ORDER BY ID";
    $rowsCombo = $pdo->query($sqlCombo)->fetchAll(PDO::FETCH_ASSOC);

    $cleanCombo = [];
    foreach ($rowsCombo as $r) {
        $entry = [];
        if (!empty($r['MandoubNames'])) {
            $entry['MandoubNames'] = trim((string)$r['MandoubNames']);
        }
        if (!empty($r['ArabCountries'])) {
            $entry['ArabCountries'] = trim((string)$r['ArabCountries']);
        }
        if (!empty($r['ForiegnCountries'])) {
            $entry['ForiegnCountries'] = trim((string)$r['ForiegnCountries']);
        }
        if (!empty($entry)) {
            $cleanCombo[] = $entry;
        }
    }

    //select distinct EmployeeName from TableUser where EmployeeName is not null and الدبلوماسيون = N'yes' and Aproved like N'%أكده%' order by EmployeeName asc
    // --- 2) TableUser (diplomats)
    $sqlDip = "  SELECT DISTINCT EmployeeName, EngEmployeeName, JobPosition as AuthenticType, AuthenticTypeEng
               FROM dbo.TableUser
               WHERE EmployeeName IS NOT NULL
                 AND الدبلوماسيون = N'yes'
                 AND (Aproved LIKE N'%أكده%')
               ORDER BY EmployeeName ASC";
    $rowsDip = $pdo->query($sqlDip)->fetchAll(PDO::FETCH_ASSOC);

    $cleanDip = [];
    foreach ($rowsDip as $r) {
        $entry = [];
        if (!empty($r['EmployeeName'])) {
            $entry['EmployeeName'] = trim((string)$r['EmployeeName']);
        }
        if (!empty($r['EngEmployeeName'])) {
            $entry['EngEmployeeName'] = trim((string)$r['EngEmployeeName']);
        }
        if (!empty($r['AuthenticType'])) {
            $entry['AuthenticType'] = trim((string)$r['AuthenticType']);
        }
        if (!empty($r['AuthenticTypeEng'])) {
            $entry['AuthenticTypeEng'] = trim((string)$r['AuthenticTypeEng']);
        }
        if (!empty($entry)) {
            $cleanDip[] = $entry;
        }
    }

    // --- 3) TableSettings (VCIndesx)
   $sqlSettings = "SELECT ID, VCIndesx, mission_Details FROM dbo.TableSettings ORDER BY ID";
    $rowsSettings = $pdo->query($sqlSettings)->fetchAll(PDO::FETCH_ASSOC);

    $cleanSettings = [];
    foreach ($rowsSettings as $r) {
        $entry = [];

        if ($r['ID'] !== null && $r['ID'] !== '') {
            $entry['ID'] = (int)$r['ID'];
        }

        // ✅ allow 0, skip only if null
        if ($r['VCIndesx'] !== null && $r['VCIndesx'] !== '') {
            $entry['VCIndesx'] = (int)$r['VCIndesx'];
        }

        if ($r['mission_Details'] !== null && $r['mission_Details'] !== '') {
            $entry['mission_Details'] = (string)$r['mission_Details'];
        }

        if (!empty($entry)) {
            $cleanSettings[] = $entry;
        }
    }


    echo json_encode([
        'ok'        => true,
        'comboRows' => $cleanCombo,   // only MandoubNames + countries
        'diplomats' => $cleanDip,
        'settings'  => $cleanSettings
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
