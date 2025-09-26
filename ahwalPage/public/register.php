<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("❌ Invalid request");
    }

    // Collect posted fields
    $EmployeeName     = trim($_POST['EmployeeName'] ?? '');
    $EngEmployeeName  = trim($_POST['EngEmployeeName'] ?? '');
    $Gender           = trim($_POST['Gender'] ?? '');
    $JobPosition      = trim($_POST['JobPosition'] ?? '');
    $Diplomat         = trim($_POST['الدبلوماسيون'] ?? 'no');
    $Authorized       = trim($_POST['مأذون'] ?? 'no');
    $HeadOfMission    = trim($_POST['headOfMission'] ?? 'no');
    $UserName         = trim($_POST['UserName'] ?? '');
    $Email            = trim($_POST['Email'] ?? '');
    $PhoneNo          = trim($_POST['PhoneNo'] ?? '');
    $AuthenticType    = trim($_POST['AuthenticType'] ?? '');
    $AuthenticTypeEng = trim($_POST['AuthenticTypeEng'] ?? '');
    $password         = $_POST['password'] ?? '';
    $repassword       = $_POST['repassword'] ?? '';

    // === Required validation ===
    if ($EmployeeName === '' || $EngEmployeeName === '' || $Email === '' || $password === '') {
        throw new Exception("⚠️ جميع الحقول الإلزامية يجب تعبئتها");
    }
    if ($password !== $repassword) {
        throw new Exception("⚠️ كلمة المرور وتأكيدها غير متطابقين");
    }

    // === Check for duplicates ===
    $uniqueChecks = [
        'EmployeeName'    => $EmployeeName,
        'EngEmployeeName' => $EngEmployeeName,
        'UserName'        => $UserName,
        'Email'           => $Email,
        'PhoneNo'         => $PhoneNo,
    ];

    foreach ($uniqueChecks as $col => $val) {
        if ($val === '') continue;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM TableUser WHERE [$col] = ?");
        $stmt->execute([$val]);
        if ($stmt->fetchColumn() > 0) {
            // Use friendly labels instead of column names
            $labels = [
                'EmployeeName'    => 'الاسم بالعربية',
                'EngEmployeeName' => 'الاسم بالانجليزية',
                'UserName'        => 'اسم المستخدم',
                'Email'           => 'البريد الإلكتروني',
                'PhoneNo'         => 'رقم الهاتف',
            ];
            $label = $labels[$col] ?? $col;
            throw new Exception("⚠️ {$label} مستخدم بالفعل");
        }
    }

    // === Hash password ===
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // === Insert ===
    $stmt = $pdo->prepare("
        INSERT INTO TableUser
            (EmployeeName, EngEmployeeName, Gender, JobPosition,
             الدبلوماسيون, مأذون, headOfMission,
             UserName, Email, PhoneNo,
             AuthenticType, AuthenticTypeEng,
             كلمة_المرور, RestPAss, نشاط_الحساب, نوع_الحساب, Purpose, RestPAss)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', N'غير نشط', N'مستخدم', N'احوال شخصية', N'done')
    ");
    $stmt->execute([
        $EmployeeName, $EngEmployeeName, $Gender, $JobPosition,
        $Diplomat, $Authorized, $HeadOfMission,
        $UserName, $Email, $PhoneNo,
        $AuthenticType, $AuthenticTypeEng,
        $hash
    ]);

    echo json_encode([
        'ok'  => true,
        'msg' => "✅ تم إنشاء الحساب بنجاح. الحساب غير نشط حالياً حتى يتم اعتماده من المسؤول."
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
