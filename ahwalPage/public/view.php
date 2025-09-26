<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

try {
    $docId = $_GET['id'] ?? '';
    if ($docId === '') {
        http_response_code(400);
        echo "❌ Missing id";
        exit;
    }

    $pdo = db();

    // Parse doc_id
    $parts = preg_split('#/#u', $docId);
    if (count($parts) < 4) {
        echo "⚠️ رقم المعاملة غير صالح";
        exit;
    }
    $docType = trim($parts[3]);

    if ($docType === '12') {
        $stmt = $pdo->prepare("
            SELECT id, مقدم_الطلب, رقم_التوكيل AS رقم, التاريخ_الميلادي, نوع_التوكيل AS النوع
            FROM [dbo].[TableAuth]
            WHERE رقم_التوكيل = ?
        ");
    } elseif ($docType === '10') {
        $stmt = $pdo->prepare("
            SELECT id, مقدم_الطلب, رقم_المعاملة AS رقم, التاريخ_الميلادي, نوع_المعاملة AS النوع
            FROM [dbo].[TableCollection]
            WHERE رقم_المعاملة = ?
        ");
    } else {
        echo "⚠️ نوع المعاملة غير مدعوم ($docType)";
        exit;
    }
    $stmt->execute([$docId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "⚠️ لم يتم العثور على المعاملة برقم: ".htmlspecialchars($docId);
        exit;
    }

    // Fetch files from ArchFilesDB
    $pdoArch = db('ArchFilesDB');
    $stmtF = $pdoArch->prepare("
        SELECT id, المستند, filename, Extension1
        FROM [ArchFilesDB].[dbo].[TableGeneralArch]
        WHERE رقم_المرجع = ? AND docTable = ? AND نوع_المستند = N'Data2'
        ORDER BY التاريخ DESC, id DESC
    ");
    $stmtF->execute([$row['id'], ($docType==='12'?'TableAuth':'TableCollection')]);
    $files = $stmtF->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('view.php: '.$e->getMessage());
    http_response_code(500);
    echo "❌ خطأ داخلي";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>عرض المعاملة</title>
  <style>
    body {
      font-family:"Tahoma","Noto Naskh Arabic",sans-serif;
      background:#f9fafb;
      color:#111827;
      display:flex;
      justify-content:center;
      align-items:center;
      min-height:100vh;
      margin:0;
    }
    .container {
      text-align:center;
      background:#fff;
      padding:2rem;
      border-radius:12px;
      box-shadow:0 2px 10px rgba(0,0,0,0.1);
      max-width:800px;
      width:100%;
    }
    .logo { max-width:150px; margin-bottom:1rem; }
    .info h2 { margin:0.5rem 0; font-size:1.5rem; color:#2563eb; }
    .files { margin-top:2rem; }
    .file-card {
      display:inline-block;
      width:180px;
      margin:0.5rem;
      border:1px solid #e5e7eb;
      border-radius:8px;
      padding:0.5rem;
      background:#fdfdfd;
      vertical-align:top;
    }
    .file-preview {
      width:100%;
      height:200px;
      object-fit:contain;
      margin-bottom:0.5rem;
      border:1px solid #ddd;
      border-radius:6px;
      background:#fff;
    }
    .file-title { font-size:0.9rem; margin:0.5rem 0; }
    .btn { display:inline-block; margin:0.25rem; padding:0.4rem 0.8rem; font-size:0.85rem; border-radius:6px; text-decoration:none; color:white; }
    .btn-view { background:#16a34a; }
    .btn-view:hover { background:#15803d; }
    .btn-delete { background:#dc2626; }
    .btn-delete:hover { background:#b91c1c; }
  </style>
</head>
<body>
  <div class="container">
    <img src="static/logo.jpg" class="logo" alt="Logo">
    <div class="info">
      <h2><?= htmlspecialchars($row['مقدم_الطلب']) ?></h2>
      <p>الرقم: <?= htmlspecialchars($row['رقم']) ?></p>
      <p>التاريخ: <?= htmlspecialchars($row['التاريخ_الميلادي']) ?></p>
      <p>النوع: <?= htmlspecialchars($row['النوع']) ?></p>
    </div>

    <div class="files">
      <h3>المستند</h3>
      <?php if ($files): ?>
        <?php foreach ($files as $f): 
          $ext = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
        ?>
          <div class="file-card">
            <?php if (in_array($ext, ['jpg','jpeg','png','gif','bmp','webp'])): ?>
              <img class="file-preview" src="download.php?fileId=<?= urlencode($f['id']) ?>&preview=1" alt="Preview">
            <?php elseif ($ext === 'pdf'): ?>
              <iframe class="file-preview" src="download.php?fileId=<?= urlencode($f['id']) ?>&preview=1"></iframe>
            <?php else: ?>
              <div class="file-preview">📄 <?= htmlspecialchars($ext) ?></div>
            <?php endif; ?>
            <div class="file-title"><?= htmlspecialchars($f['المستند']) ?></div>
            <a class="btn btn-view" target="_blank" href="download.php?fileId=<?= urlencode($f['id']) ?>&preview=1">تحميل الملف</a>
            
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>لا توجد ملفات مرفقة.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
