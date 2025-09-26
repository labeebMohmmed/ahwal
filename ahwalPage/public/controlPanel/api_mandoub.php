<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = $_SESSION['user_id'] ?? null;
    $accountType = $_SESSION['نوع_الحساب'] ?? null;

    if (!$userId) {
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    if ($accountType === 'مدير نظام') {
        // Admin: see all mandoub rows
        $stmt = $pdo->query("
            SELECT TOP 1000
                ID,
                MandoubNames,
                MandoubPhones,
                MandoubAreas,
                [مواعيد_الحضور],
                [الصفة],
                [وضع_المندوب],
                comment,
                [رقم_الجواز]
            FROM [AhwalDataBase].[dbo].[TableMandoudList]
            ORDER BY ID ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'mandoub' => $rows,
            'currentUserId' => $userId,
            'accountType' => $accountType
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // Non-admin: just see "active" mandoub list (example restriction)
        $stmt = $pdo->query("
            SELECT 
                ID,
                MandoubNames,
                MandoubPhones,
                MandoubAreas,
                [مواعيد_الحضور],
                [الصفة],
                [وضع_المندوب],
                comment,
                [رقم_الجواز]
            FROM [AhwalDataBase].[dbo].[TableMandoudList]
            
            ORDER BY ID ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'mandoub' => $rows,
            'currentUserId' => $userId,
            'accountType' => $accountType
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
