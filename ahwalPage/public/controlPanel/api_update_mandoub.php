<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = $_SESSION['user_id'] ?? null;
    $accountType = $_SESSION['نوع_الحساب'] ?? null;

    // --- Authorization check ---
    if (!$userId) {
        // User not logged in
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'غير مسجل الدخول',
            'details' => 'يجب تسجيل الدخول للقيام بهذه العملية'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accountType !== 'مدير نظام') {
        // User logged in but not an admin
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'غير مصرح',
            'details' => "نوع حسابك هو '{$accountType}'، يجب أن تكون مدير نظام"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Decode request JSON payload ---
    $data = json_decode(file_get_contents("php://input"), true);
    $id    = (int)($data['id'] ?? 0);
    $field = $data['field'] ?? '';
    $value = $data['value'] ?? '';

    // --- Validate input ---
    if ($id <= 0 || $field === '') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'المدخلات غير مكتملة (المعرف أو الحقل مفقود)'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowed = [
        'MandoubNames',
        'MandoubPhones',
        'MandoubAreas',
        'مواعيد_الحضور',
        'الصفة',
        'وضع_المندوب',
        'comment',
        'رقم_الجواز'
    ];
    if (!in_array($field, $allowed, true)) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'اسم الحقل غير صالح',
            'allowed' => $allowed
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Perform update ---
    $sql = "UPDATE [AhwalDataBase].[dbo].[TableMandoudList]
            SET [$field] = :val
            WHERE ID = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':val' => $value, ':id' => $id]);

    if ($stmt->rowCount() === 0) {
        // No rows updated (maybe invalid ID)
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'error' => 'فشل التحديث، لم يتم العثور على سجل مطابق',
            'id' => $id,
            'field' => $field,
            'value' => $value
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Success response ---
    echo json_encode([
        'ok' => true,
        'updated' => [
            'id' => $id,
            'field' => $field,
            'value' => $value
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Catch unexpected exceptions
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'خطأ في الخادم',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
