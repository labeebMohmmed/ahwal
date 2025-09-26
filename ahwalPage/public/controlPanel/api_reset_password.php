<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        throw new Exception("Missing user ID");
    }

    // Generate random 6-char alphanumeric password
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $newPassword = '';
    for ($i = 0; $i < 6; $i++) {
        $newPassword .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update كلمة_المرور, mark RestPAss as 'reset', and push comment
    $stmt = $pdo->prepare("
        UPDATE [AhwalDataBase].[dbo].[TableUser]
        SET كلمة_المرور = ?, 
            RestPAss = '',
            comment = CONCAT(ISNULL(comment,''), CHAR(13)+CHAR(10),
              CONVERT(varchar, GETDATE(), 120) + ' | Password reset by admin')
        WHERE ID = ?
    ");
    $stmt->execute([$hash, $id]);

    echo json_encode([
        'ok' => true,
        'newPassword' => $newPassword
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
