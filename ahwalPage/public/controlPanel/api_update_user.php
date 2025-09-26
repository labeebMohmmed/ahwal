<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request");
    }

    $data = $_POST ?: json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception("No data provided");
    }

    $id = $data['id'] ?? null;
    if (!$id) {
        throw new Exception("Missing user ID");
    }

    // === Required fields ===
    $required = [
        'EmployeeName', 'EngEmployeeName', 'UserName',
        'مأذون', 'الدبلوماسيون',
        'AuthenticType', 'AuthenticTypeEng',
        'JobPosition'
    ];

    $isSingleField = isset($data['field'], $data['value']); // inline edit

    if ($isSingleField) {
        // Inline update
        $field = $data['field'];
        $value = trim((string)$data['value']);

        if (in_array($field, $required) && $value === '') {
            throw new Exception("Field $field cannot be empty");
        }

        $sql = "UPDATE [AhwalDataBase].[dbo].[TableUser] SET [$field] = ? WHERE ID = ?";
        $pdo->prepare($sql)->execute([$value, $id]);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === Full form update (self account) ===
    foreach ($required as $f) {
        if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
            throw new Exception("Field $f is required");
        }
    }

    // === Sensitive fields list ===
    $sensitiveFields = [
        'EmployeeName', 'EngEmployeeName', 'الدبلوماسيون', 'مأذون',
        'AuthenticTypeEng', 'AuthenticType', 'headOfMission'
    ];

    $sensitiveChanged = false;
    foreach ($sensitiveFields as $f) {
        if (isset($data[$f])) {
            $sensitiveChanged = true;
            break;
        }
    }

    if ($sensitiveChanged && empty($data['confirmSensitive'])) {
        echo json_encode([
            'ok' => false,
            'requireConfirm' => true,
            'msg' => '⚠️ تعديل الحقول الحساسة سيؤدي إلى تعطيل الحساب فوراً وتسجيل خروجك. هل ترغب بالمتابعة؟'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // === Allowed fields ===
    $allowedFields = [
        'EmployeeName', 'EngEmployeeName', 'UserName', 'Email', 'PhoneNo',
        'Aproved', 'JobPosition', 'headOfMission',
        'AuthenticType', 'AuthenticTypeEng', 'الدبلوماسيون', 'مأذون',
        'Data1', 'نشاط_الحساب'
    ];

    $updates = [];
    $params = [];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "[$field] = ?";
            $params[] = $data[$field];
        }
    }

    // Force disable account if sensitive changed
    if ($sensitiveChanged) {
        $updates[] = "[نشاط_الحساب] = ?";
        $params[] = "غير نشط";
    }

    if (!$updates) {
        throw new Exception("No valid fields provided");
    }

    $params[] = $id;
    $sql = "UPDATE [AhwalDataBase].[dbo].[TableUser] 
            SET " . implode(", ", $updates) . " 
            WHERE ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // === Log update into comment field ===
    $log = sprintf("[%s] user:%s updated fields: %s",
        date("Y-m-d H:i:s"),
        $_SESSION['user_id'] ?? 'unknown',
        implode(", ", array_keys($data))
    );
    $pdo->prepare("UPDATE [AhwalDataBase].[dbo].[TableUser] 
                   SET comment = ISNULL(comment,'') + ? 
                   WHERE ID = ?")
        ->execute(["\n" . $log, $id]);

    // === If sensitive fields updated → logout immediately ===
    if ($sensitiveChanged) {
        session_destroy();
        echo json_encode([
            'ok' => true,
            'disabled' => true,
            'logout' => true,
            'msg' => '✅ تم تحديث البيانات. ⚠️ الحساب غير نشط الآن، الرجاء إعادة تسجيل الدخول بعد اعتماد المسؤول.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true, 'disabled' => false], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
