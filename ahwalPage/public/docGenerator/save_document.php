<?php
require __DIR__ . '/../config.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["ok"=>false, "error"=>"No payload"]);
    exit;
}

// 1. Decide which table
$group = $data['نوع_المكاتبة'] ?? '';
$table = 'TableCollection';
$signerCol = 'موقع_المعاملة';
if ($group === 'توكيل' || $group === 'إقرار' || $group === 'إقرار مشفوع باليمين') {
    $table = 'TableAuth';
    $signerCol = 'موقع_التوكيل';
}

// 2. Get caseId (unique per table)
$caseId = (int)($data['caseId'] ?? 0);
if (!$caseId) {
    echo json_encode(["ok"=>false, "error"=>"Missing caseId"]);
    exit;
}

// 3. Prepare comment string
$employeeId = $_SESSION['user_id'] ?? ($data['employee_id'] ?? 'unknown');
$now = date('m-d-Y H:i:s');
$specialMsg = $data['special_comment'] ?? '';

// Fetch existing comment
$stmt = $pdo->prepare("SELECT [تعليق] FROM [dbo].[$table] WHERE [ID] = ?");
$stmt->execute([$caseId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$currentComment = $row['تعليق'] ?? '';

if (trim($currentComment) === '') {
    $newComment = "First print by {$employeeId} on {$now}";
} else {
    $newComment = trim($currentComment) . "\nReprinted by {$employeeId} on {$now}";
}
if ($specialMsg !== '') {
    $newComment .= "\n" . $specialMsg;
}
// Get current date in mm-dd-yyyy
$currentDate = date('m-d-Y');

// 4. Update row
$sql = "
    UPDATE [dbo].[$table]
    SET [التاريخ_الميلادي] = ?,
        [$signerCol] = ?,
        [الخاتمة] = ?,
        [مدة_الاعتماد] = ?,
        [التوثيق] = ?,
        [نص_المعاملة] = ?,        
        [صفة_الموقع] = ?,
        [تعليق] = ?,
        [PayloadJson] = ?,
        [حالة_الارشفة] = N'غير مؤرشف'
        
    WHERE [ID] = ?
";
$stmt = $pdo->prepare($sql);

$ok = $stmt->execute([
    $currentDate,
    $data[$signerCol] ?? $data['signer'] ?? null,
    $data['الخاتمة'] ?? $data['dest'] ?? null,
    $data['مدة_الاعتماد'] ?? '',
    $data['التوثيق'] ?? null,
    $data['نص_المعاملة'] ?? $data['text'] ?? null,    
    $data['صفة_الموقع'] ?? $data['signer_role'] ?? null,
    $newComment,
    json_encode($data, JSON_UNESCAPED_UNICODE),
    $caseId
]);

echo json_encode(["ok"=>$ok]);
