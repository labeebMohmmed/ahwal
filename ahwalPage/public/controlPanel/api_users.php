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
        $stmt = $pdo->query("
            SELECT TOP 10 
                ID, UserName, EmployeeName, JobPosition, نشاط_الحساب, headOfMission, نوع_الحساب
            FROM [AhwalDataBase].[dbo].[TableUser]
            ORDER BY ID ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'ok' => true,
            'users' => $rows,
            'currentUserId' => $userId,
            'accountType' => $accountType
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                ID,
                UserName,
                EmployeeName,
                EngEmployeeName,
                JobPosition,
                Gender,
                Email,
                PhoneNo,
                Aproved,
                AuthenticType,
                AuthenticTypeEng,
                headOfMission,
                [الدبلوماسيون],
                [مأذون],
                [نشاط_الحساب],
                Data1,
                نوع_الحساب,
                RestPAss,
                comment
            FROM [AhwalDataBase].[dbo].[TableUser]
            WHERE ID = ?

        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'ok' => true,
            'users' => $row ? [$row] : [],
            'currentUserId' => $userId,
            'accountType' => $accountType
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
