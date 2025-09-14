<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
/**
 * POST multipart/form-data
 *  - caseId   (int, required)
 *  - label    (string, required)
 *  - userId   (int, optional)
 *  - kind     (string, optional)   // not stored; echoed back
 *  - file     (required)           // <= 3MB; pdf/jpg/png/docx
 *
 * Table: [online].[CaseFiles] (adjust schema below if yours is [dbo])
 */


header('Content-Type: application/json; charset=utf-8');

try {
    /* ===== CONFIG (adjust schema only if needed) ===== */
    $SCHEMA = 'online'; // change to 'dbo' if your table is dbo.CaseFiles

    $MAX_BYTES = 3 * 1024 * 1024;
    $ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    $EXT_TO_MIME = [
        'pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
        'png'=>'image/png','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    /* ===== INPUT ===== */
    $caseId = isset($_POST['caseId']) ? (int)$_POST['caseId'] : 0;
    $label  = isset($_POST['label'])  ? trim((string)$_POST['label']) : '';
    $userId = isset($_POST['userId']) ? (int)$_POST['userId'] : null;
    $kind   = isset($_POST['kind'])   ? strtolower(trim((string)$_POST['kind'])) : null; // echoed back only

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
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!$mime || !in_array($mime, $ALLOWED_MIME, true)) $mime = $EXT_TO_MIME[$ext] ?? $mime;
    if (!$mime || !in_array($mime, $ALLOWED_MIME, true)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Unsupported file type.']); exit;
    }

    $content = @file_get_contents($tmpPath);
    if ($content === false) throw new RuntimeException('Unable to read uploaded file.');
    $sha256 = hash('sha256', $content, false);

    /* ===== DB CONNECT (your DSN) ===== */
    $pdo = db('ArchFilesDB');

    $pdo->beginTransaction();

    // Ensure table exists in this DB/schema (avoid “Invalid object name”).
    // Comment out these two lines in production if you prefer:
    // $pdo->exec("IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = N'$SCHEMA') EXEC('CREATE SCHEMA [$SCHEMA]');");
    // $pdo->exec("IF OBJECT_ID(N'[$SCHEMA].[CaseFiles]', N'U') IS NULL RAISERROR('CaseFiles table missing in $SCHEMA schema', 16, 1);");

    // DEDUP: same file already uploaded for this requirement?
    $du = $pdo->prepare("
        SELECT TOP 1 FileID
        FROM [$SCHEMA].[CaseFiles]
        WHERE CaseID = ? AND Label = ? AND Sha256Hex = ?
        ORDER BY UploadedAt DESC
    ");
    $du->execute([$caseId, $label, $sha256]);
    if ($row = $du->fetch()) {
        $pdo->commit();
        echo json_encode([
            'success'=>true,
            'fileId'=>(int)$row['FileID'],
            'dedup'=>true,
            'filename'=>$origName,
            'sizeBytes'=>$size,
            'mimeType'=>$mime,
            'kindEcho'=>$kind
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Replace policy: remove prior row(s) for (CaseID, Label)
    $del = $pdo->prepare("DELETE FROM [$SCHEMA].[CaseFiles] WHERE CaseID = ? AND Label = ?");
    $del->execute([$caseId, $label]);

    // Prepare binary stream to avoid UCS-2 conversion
    $stream = fopen('php://memory', 'r+');
    if ($stream === false) throw new RuntimeException('Failed to open memory stream.');
    fwrite($stream, $content); rewind($stream);

    // INSERT (no fetch here!)
    $ins = $pdo->prepare("
        INSERT INTO [$SCHEMA].[CaseFiles]
        (CaseID, Label, OriginalName, MimeType, SizeBytes, Content, Sha256Hex, UploadedAt, UploadedBy)
        VALUES (?,?,?,?,?,?,?, SYSUTCDATETIME(), ?);
    ");
    $ins->bindValue(1, $caseId, PDO::PARAM_INT);
    $ins->bindValue(2, $label, PDO::PARAM_STR);
    $ins->bindValue(3, $origName, PDO::PARAM_STR);
    $ins->bindValue(4, $mime, PDO::PARAM_STR);
    $ins->bindValue(5, $size, PDO::PARAM_INT);
    // bind binary (critical)
    $ins->bindParam(6, $stream, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
    $ins->bindValue(7, $sha256, PDO::PARAM_STR);
    if ($userId !== null && $userId > 0) $ins->bindValue(8, $userId, PDO::PARAM_INT);
    else                                  $ins->bindValue(8, null, PDO::PARAM_NULL);

    $ins->execute();

    // Get identity safely with a separate SELECT
    $idStmt = $pdo->query("SELECT CAST(SCOPE_IDENTITY() AS int) AS id");
    $newId = (int)($idStmt->fetchColumn() ?? 0);

    fclose($stream);
    $pdo->commit();

    echo json_encode([
        'success'=>true,
        'fileId'=>$newId,
        'filename'=>$origName,
        'sizeBytes'=>$size,
        'mimeType'=>$mime,
        'sha256'=>$sha256,
        'kindEcho'=>$kind
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('api_casefile_upload.php: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Internal error'], JSON_UNESCAPED_UNICODE);
}
