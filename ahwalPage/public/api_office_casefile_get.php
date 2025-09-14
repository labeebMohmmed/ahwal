<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

try {
  $fileId = isset($_GET['fileId']) ? (int)$_GET['fileId'] : 0;
  $download = isset($_GET['dl']) && $_GET['dl'] === '1';
  if ($fileId <= 0) { http_response_code(400); exit('bad fileId'); }

  // If your db() accepts a DB name, keep it. Otherwise, use default and fully qualify below.
  $pdo = db('ArchFilesDB');

  $stmt = $pdo->prepare("
    SELECT
      filename    AS OriginalName,
      Extension1  AS ExtOrMime,  -- may be 'pdf' or 'application/pdf'
      Data1       AS Content
    FROM [dbo].[TableGeneralArch]
    WHERE id = ?
  ");
  $stmt->execute([$fileId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { http_response_code(404); exit('not found'); }

  $name    = (string)($row['OriginalName'] ?? 'file');
  $extMime = (string)($row['ExtOrMime']   ?? '');
  $content = $row['Content'];

  // Normalize to a PHP string of bytes
  $data = is_resource($content) ? stream_get_contents($content) : (string)$content;

  // --- determine MIME ---
  $mime = '';
  if ($extMime !== '') {
    if (strpos($extMime, '/') !== false) {
      $mime = $extMime; // already 'application/pdf', etc.
    } else {
      $ext = strtolower(ltrim($extMime, '.'));
      $mime = mime_from_ext($ext) ?? '';
    }
  }
  if ($mime === '') {
    $extFromName = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($extFromName) $mime = mime_from_ext($extFromName) ?? '';
  }
  if ($mime === '') {
    // last resort: sniff magic bytes
    if (strncmp($data, "%PDF", 4) === 0)           $mime = 'application/pdf';
    elseif (strncmp($data, "\x89PNG", 4) === 0)    $mime = 'image/png';
    elseif (strncmp($data, "\xFF\xD8", 2) === 0)   $mime = 'image/jpeg';
    else                                           $mime = 'application/octet-stream';
  }

  // --- headers ---
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Content-Type');
  header_remove('Content-Disposition');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: private, max-age=600');
  header('Content-Type: '.$mime);

  $disp = $download ? 'attachment' : 'inline';
  $ascii = preg_replace('/[^\x20-\x7E]+/', '_', $name);
  header("Content-Disposition: $disp; filename=\"$ascii\"; filename*=UTF-8''".rawurlencode($name));
  header('Content-Length: '.strlen($data));

  echo $data;
  exit;

} catch (Throwable $e) {
  error_log('api_office_casefile_get.php: '.$e->getMessage());
  http_response_code(500);
  echo 'error';
}

/* --- helpers --- */
function mime_from_ext(string $ext): ?string {
  static $map = [
    'pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
    'gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp','tif'=>'image/tiff','tiff'=>'image/tiff',
    'doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt'=>'text/plain','csv'=>'text/csv','zip'=>'application/zip'
  ];
  return $map[$ext] ?? null;
}
