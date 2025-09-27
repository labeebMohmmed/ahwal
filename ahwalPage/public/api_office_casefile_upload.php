<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $SCHEMA = 'dbo';

    $MAX_BYTES = 3 * 1024 * 1024;
    $ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $EXT_TO_MIME = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $MIME_TO_EXT = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    // -------- Input --------
    $caseId = isset($_POST['caseId']) ? (int)$_POST['caseId'] : 0;
    $label  = isset($_POST['label'])  ? trim((string)$_POST['label']) : '';
    $userId = isset($_POST['userId']) ? (int)$_POST['userId'] : null;
    $kind   = isset($_POST['kind'])   ? strtolower(trim((string)$_POST['kind'])) : null;

    if ($caseId <= 0 || $label === '') {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Missing caseId or label.']); exit;
    }
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'No file uploaded.']); exit;
    }
    $f = $_FILES['file'];
    if (!empty($f['error'])) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Upload error code: '.$f['error']]); exit;
    }

    $origName = (string)$f['name'];
    $tmpPath  = (string)$f['tmp_name'];
    $size     = (int)$f['size'];
    $mime     = (string)($f['type'] ?? '');

    if ($size <= 0 || $size > $MAX_BYTES) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'File exceeds 3MB or is empty.']); exit;
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION)); // may be ''
    // Normalize/validate MIME
    if (!$mime || !in_array($mime, $ALLOWED_MIME, true)) {
        if ($ext && isset($EXT_TO_MIME[$ext])) $mime = $EXT_TO_MIME[$ext];
    }
    if (!$mime || !in_array($mime, $ALLOWED_MIME, true)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Unsupported file type.']); exit;
    }

    // Decide what to store in Extension1 (dot + ext, lowercased)
    $extForDb = $ext ?: ($MIME_TO_EXT[$mime] ?? 'bin');
    $extForDb = '.' . ltrim(strtolower($extForDb), '.'); // ".pdf", ".png", ...
    $extForDb = substr($extForDb, 0, 10); // safety if column is narrow

    $content = @file_get_contents($tmpPath);
    if ($content === false) throw new RuntimeException('Unable to read uploaded file.');

    // -------- DB --------
    $pdo = db('ArchFilesDB');
    $pdo->beginTransaction();

    // Dedup existing by (caseId, label)
    $du = $pdo->prepare("
        SELECT TOP 1 id
        FROM [$SCHEMA].[TableGeneralArch]
        WHERE رقم_المرجع = ? AND المستند = ?
        ORDER BY التاريخ DESC, id DESC
    ");
    $du->execute([$caseId, $label]);
    if ($row = $du->fetch()) {
        $pdo->commit();
        echo json_encode([
            'success'=>true,
            'fileId'=>(int)$row['id'],
            'dedup'=>true,
            'filename'=>$origName,
            'sizeBytes'=>$size,
            'mimeType'=>$mime,
            'extension'=>$extForDb,
            'kindEcho'=>$kind
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Replace policy: remove prior rows for (CaseID, Label)
    $del = $pdo->prepare("DELETE FROM [$SCHEMA].[TableGeneralArch] WHERE رقم_المرجع = ? AND المستند = ?");
    $del->execute([$caseId, $label]);

    // Bind binary safely
    $stream = fopen('php://memory', 'r+');
    if ($stream === false) throw new RuntimeException('Failed to open memory stream.');
    fwrite($stream, $content); rewind($stream);

    // Insert row: Extension1 gets ".ext", Data1 gets the blob
    $ins = $pdo->prepare("
        INSERT INTO [$SCHEMA].[TableGeneralArch]
        (رقم_المرجع, المستند, filename, Extension1, Data1, التاريخ, الموظف)
        VALUES (?,?,?,?,?, SYSUTCDATETIME(), ?);
    ");
    $ins->bindValue(1, $caseId, PDO::PARAM_INT);
    $ins->bindValue(2, $label, PDO::PARAM_STR);
    $ins->bindValue(3, $origName, PDO::PARAM_STR);
    $ins->bindValue(4, $extForDb, PDO::PARAM_STR); // <-- ".pdf" / ".png" etc (NOT MIME)
    $ins->bindParam(5, $stream, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
    if ($userId !== null && $userId > 0) {
        $ins->bindValue(6, $userId, PDO::PARAM_INT);
    } else {
        $ins->bindValue(6, null, PDO::PARAM_NULL);
    }
    $ins->execute();

    $idStmt = $pdo->query("SELECT CAST(SCOPE_IDENTITY() AS int) AS id");
    $newId = (int)($idStmt->fetchColumn() ?? 0);

    fclose($stream);
    $pdo->commit();

    echo json_encode([
        'success'=>true,
        'fileId'=>$newId,
        'filename'=>$origName,
        'sizeBytes'=>$size,
        'mimeType'=>$mime,      // useful for UI
        'extension'=>$extForDb, // what we saved in Extension1
        'kindEcho'=>$kind
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('api_office_casefile_upload.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
