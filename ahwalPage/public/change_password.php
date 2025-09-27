<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($newPass !== $confirm) {
        $error = "كلمتا المرور غير متطابقتين";
    } elseif (strlen($newPass) < 6) {
        $error = "كلمة المرور يجب أن تكون 6 أحرف/أرقام على الأقل";
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE [AhwalDataBase].[dbo].[TableUser]
            SET كلمة_المرور = ?, RestPAss = 'done',
                comment = CONCAT(ISNULL(comment,''), CHAR(13)+CHAR(10),
                  CONVERT(varchar, GETDATE(), 120) + ' | User changed temp password')
            WHERE ID = ?
        ");
        $stmt->execute([$hash, $_SESSION['user_id']]);

        session_unset();
        session_destroy();
        header("Location: login.php?changed=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>تغيير كلمة المرور – الأنظمة القنصلية</title>
  <link rel="stylesheet" href="static/styles.css"/>
  <link rel="stylesheet" href="controlPanel/assets/login.css"/>
</head>
<body>
<div class="auth-container">
  <!-- Left: Logo/branding -->
  <div class="auth-left">
    <img src="static/ahwalk.png" alt="Logo">
    <h1>نظام الاحوال الشخصية والقنصلية</h1>
    <p>يرجى تعيين كلمة مرور جديدة لمتابعة الدخول</p>
  </div>

  <!-- Right: Form -->
  <div class="auth-right">
    <div class="card auth-card">
      <h2 class="card-title">تغيير كلمة المرور</h2>

      <form method="post">
        <label>كلمة المرور الجديدة
          <input type="password" name="new_password" autocomplete="off" required>
        </label>

        <label>تأكيد كلمة المرور
          <input type="password" name="confirm_password" autocomplete="new-password" required>
        </label>

        <button type="submit" class="primary">تحديث</button>
      </form>

      <?php if ($error): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <div class="auth-links">
        <a href="login.php">رجوع لتسجيل الدخول</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
