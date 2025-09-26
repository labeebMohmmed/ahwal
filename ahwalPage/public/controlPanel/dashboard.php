<?php
require __DIR__ . '/__init.php'; // authentication check
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
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
      <a href="/ahwal-admin/admin.php" data-page="admin">إدارة النماذج</a>
      <a href="#" data-page="officeCasesControl">المعاملات غير المكتملة</a>
      <a href="#" data-page="users">المستخدمون</a>      
      <a href="#" data-page="reports">التقارير</a>
      <a href="#" data-page="concularDoc">المعاملات القنصلية</a>
      <a href="#" data-page="settings">الإعدادات</a>
      <a href="#" data-page="mandoubs">مندوبي الجاليات</a>
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

  <script src="../static/app.js"></script>
  <script src="assets/dashboard.js"></script>
</body>
</html>
