<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = $_SESSION['user_id'] ?? null;
    $accountType = $_SESSION['نوع_الحساب'] ?? null;

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'غير مسجل الدخول'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($accountType !== 'مدير نظام') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'غير مصرح'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Collect fields
    $name     = trim($_POST['MandoubNames'] ?? '');
    $phone    = trim($_POST['MandoubPhones'] ?? '');
    $area     = trim($_POST['MandoubAreas'] ?? '');
    $attend   = trim($_POST['مواعيد_الحضور'] ?? '');
    $role     = trim($_POST['الصفة'] ?? '');
    $status   = trim($_POST['وضع_المندوب'] ?? '');
    $passport = trim($_POST['رقم_الجواز'] ?? '');
    $comment  = trim($_POST['comment'] ?? '');

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'يجب إدخال اسم المندوب'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Insert only existing fields
    $sql = "INSERT INTO [AhwalDataBase].[dbo].[TableMandoudList] 
            (MandoubNames, MandoubPhones, MandoubAreas, [مواعيد_الحضور],
             [الصفة], [وضع_المندوب], [رقم_الجواز], comment)
            VALUES (:name, :phone, :area, :attend, :role, :status, :passport, :comment)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'     => $name,
        ':phone'    => $phone,
        ':area'     => $area,
        ':attend'   => $attend,
        ':role'     => $role,
        ':status'   => $status ?: 'مفعل', // default if empty
        ':passport' => $passport,
        ':comment'  => $comment
    ]);

    echo json_encode([
        'ok' => true,
        'inserted' => [
            'MandoubNames'   => $name,
            'MandoubPhones'  => $phone,
            'MandoubAreas'   => $area,
            'مواعيد_الحضور' => $attend,
            'الصفة'         => $role,
            'وضع_المندوب'   => $status ?: 'مفعل',
            'رقم_الجواز'    => $passport,
            'comment'        => $comment
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'خطأ في الخادم',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
