<?php
require __DIR__ . '/__init.php'; // authentication check
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if (session_status() === PHP_SESSION_NONE) session_start();

// role from session (already set at login)
// e.g. $_SESSION['نوع_الحساب'], $_SESSION['EmployeeName'], $_SESSION['user_id']
$accountType = trim($_SESSION['نوع_الحساب'] ?? '');

// server-side allowed pages map (edit to match your policy)
$roleMap = [
  'مدير نظام' => ['admin','users','reports','concularDoc','settings','mandoubs','headDepartment'],
  'مدير قسم'      => ['reports','concularDoc','settings','mandoubs','headDepartment'],
  'مستخدم'      => ['reports','concularDoc','settings','headDepartment'],
];

// default fallback (safe)
$allowedPages = $roleMap[$accountType] ?? ['reports','concularDoc'];

$pub = json_encode([
  'accountType'  => $accountType,
  'employeeName' => $_SESSION['EmployeeName'] ?? '',
  'allowedPages' => $allowedPages
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>الرئيسية</title>
  <!-- Link to global stylesheet -->
  <link rel="stylesheet" href="../static/styles.css"/>
  <!-- Dashboard-specific -->
  <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body>
  <header class="contain">
    <div id="notif-box"></div>
    <h1>
      مرحباً، <?= htmlspecialchars(explode(' ', trim($_SESSION['EmployeeName']))[0]) ?>
    </h1>

    <nav id="main-nav">
      <a href="/ahwal-admin/admin.php" data-page="admin" target="_blank" rel="noopener noreferrer" hidden>إدارة النماذج</a>      
      <a href="#" data-page="users" hidden>المستخدمون</a>      
      <a href="#" data-page="reports">التقارير</a>
      <a href="#" data-page="concularDoc">المعاملات القنصلية</a>
      <a href="#" data-page="settings" hidden>الإعدادات</a>
      <a href="#" data-page="mandoubs" hidden>مندوبي الجاليات</a>
      <a href="#" data-page="headDepartment">مدير القسم</a>
      <a href="../logout.php">تسجيل الخروج</a>
    </nav>
</header>

  <main id="dashboard-main" class="container" >
    <!-- Default content shown when nothing is selected -->
    <div class="card">
      <h2 class="card-title">قائمة التطبيقات</h2>
      <p class="muted">سيتم عرض قائمة التطبيقات هنا (أنت ستوفرها لاحقاً).</p>
    </div>
  </main>

  <!-- JS -->
   <script>
  window.currentUserId = <?= json_encode($_SESSION['user_id']) ?>;
  window.currentUserName = <?= json_encode($_SESSION['username']) ?>;
  window.currentEmployeeName = <?= json_encode($_SESSION['EmployeeName']) ?>;
</script>
  <script>
  // exported by server: contains allowedPages array
  window.APP_PERMISSIONS = <?= $pub ?>;

  (function () {
    const data = window.APP_PERMISSIONS || {};
    const allowed = Array.isArray(data.allowedPages) ? data.allowedPages : [];

    // find nav and show/hide based on data-page
    const nav = document.getElementById('main-nav');
    if (!nav) return;

    nav.querySelectorAll('a[data-page]').forEach(a => {
      const page = a.dataset.page;
      // default: hide unless explicitly allowed
      if (allowed.includes(page)) {
        a.hidden = false;
      } else {
        a.hidden = true;
      }
    });

    // optional: put employee name somewhere if you have #whoami
    if (data.employeeName) {
      const who = document.getElementById('whoami');
      if (who) who.textContent = data.employeeName;
    }
  })();
</script>

  <script src="../static/app.js"></script>
  <script src="assets/dashboard.js"></script>
</body>
</html>
