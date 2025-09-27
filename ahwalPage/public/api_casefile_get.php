<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

try {
  $fileId = isset($_GET['fileId']) ? (int)$_GET['fileId'] : 0;
  if ($fileId <= 0) { http_response_code(400); exit('bad fileId'); }

  $pdo = db('ArchFilesDB');

  $SCHEMA = 'online';

  $stmt = $pdo->prepare("
    SELECT OriginalName, MimeType, Content
    FROM [$SCHEMA].[CaseFiles]
    WHERE FileID = ?
  ");
  $stmt->execute([$fileId]);
  $row = $stmt->fetch();
  if (!$row) { http_response_code(404); exit('not found'); }

  $name = $row['OriginalName'] ?? 'file';
  $mime = $row['MimeType'] ?? 'application/octet-stream';
  $content = $row['Content'];

  // Stream inline so the browser can preview PDF/images
  header('Content-Type: '.$mime);
  header('Content-Disposition: inline; filename="'.rawurlencode($name).'"');
  header('X-Content-Type-Options: nosniff');

  // Output VARBINARY
  echo is_resource($content) ? stream_get_contents($content) : $content;
} catch (Throwable $e) {
  error_log('api_casefile_get.php: '.$e->getMessage());
  http_response_code(500);
  echo 'error';
}
