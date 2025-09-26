<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

try {
    if (!is_array($data)) {
        throw new Exception("Invalid JSON payload: " . $raw);
    }

    // Collect settings
    $settings = [
        'missionNameAr'  => $data['missionNameAr'] ?? '',
        'missionNameEn'  => $data['missionNameEn'] ?? '',
        'missionAddr'    => $data['missionAddr'] ?? '',
        'missionPhone'   => $data['missionPhone'] ?? '',
        'missionFax'     => $data['missionFax'] ?? '',
        'missionPO'      => $data['missionPO'] ?? '',
        'missionPostal'  => $data['missionPostal'] ?? '',
        'refNum'         => $data['refNum'] ?? '',
        'barcodeEnabled' => !empty($data['barcodeEnabled'])
    ];

    $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE);

    // Debug: check JSON length
    if ($settingsJson === false) {
        throw new Exception("Failed to encode JSON: " . json_last_error_msg());
    }

    // Update DB
    $sql = "UPDATE tableSettings
            SET mission_Details = :data
            WHERE ID = 1";

    $st = $pdo->prepare($sql);
    $st->execute([':data' => $settingsJson]);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
        'file'  => basename($e->getFile()),
        'line'  => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
