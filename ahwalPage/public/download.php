<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

try {
    $fileId = isset($_GET['fileId']) ? (int)$_GET['fileId'] : 0;
    $preview = isset($_GET['preview']);
    if ($fileId <= 0) {
        http_response_code(400);
        echo "❌ Missing fileId";
        exit;
    }

    $pdo = db('ArchFilesDB');

    $stmt = $pdo->prepare("
        SELECT id, filename, Extension1, Data1
        FROM [ArchFilesDB].[dbo].[TableGeneralArch]
        WHERE id = ?
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        echo "❌ File not found";
        exit;
    }

    $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
    $mime = $file['Extension1'] ?: 'application/octet-stream';
    $data = $file['Data1'];

    if ($preview) {
        // Inline preview
        if (in_array($ext, ['jpg','jpeg','png','gif','bmp','webp'])) {
            header("Content-Type: image/".$ext);
        } elseif ($ext === 'pdf') {
            header("Content-Type: application/pdf");
        } else {
            header("Content-Type: ".$mime);
        }
    } else {
        // Force download
        header("Content-Type: ".$mime);
        header('Content-Disposition: attachment; filename="'.basename($file['filename']).'"');
        header("Content-Length: ".strlen($data));
    }

    echo $data;
    exit;

} catch (Throwable $e) {
    error_log('download.php: '.$e->getMessage());
    http_response_code(500);
    echo "❌ Internal error";
}
