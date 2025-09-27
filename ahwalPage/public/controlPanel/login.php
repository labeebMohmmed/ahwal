<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$error   = '';
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT id, UserName, كلمة_المرور AS password_field,
               EmployeeName, نوع_الحساب, RestPAss, نشاط_الحساب
        FROM TableUser
        WHERE UserName = ? AND Purpose = N'احوال شخصية'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (mb_strpos((string)$user['نشاط_الحساب'], 'غير') !== false) {
            $error = "⚠️ الحساب غير نشط حالياً، الرجاء مراجعة المسؤول.";
        } else {
            $dbPassword = $user['password_field'];

            if (password_verify($password, $dbPassword)) {
                session_regenerate_id(true);
                $_SESSION['نوع_الحساب']   = $user['نوع_الحساب'];
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['UserName'];
                $_SESSION['EmployeeName'] = $user['EmployeeName'];

                if ($user['RestPAss'] === '') {
                    header("Location: change_password.php");
                } else {
                    header("Location: controlPanel/dashboard.php");
                }
                exit;
            }

            if (hash_equals($dbPassword, $password)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("
                    UPDATE TableUser 
                    SET كلمة_المرور = ?, RestPAss = 'done' 
                    WHERE id = ?
                ");
                $upd->execute([$newHash, $user['id']]);

                session_regenerate_id(true);
                $_SESSION['نوع_الحساب']   = $user['نوع_الحساب'];
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['UserName'];
                $_SESSION['EmployeeName'] = $user['EmployeeName'];

                header("Location: controlPanel/dashboard.php");
                exit;
            }

            $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
        }
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>نظام الاحوال الشخصية والقنصلية – دخول</title>
  <link rel="stylesheet" href="static/styles.css"/>
  <link rel="stylesheet" href="controlPanel/assets/login.css"/>
</head>
<body>
<div class="auth-container">
  <div class="auth-left">
    <img src="static/ahwalk.png" alt="Logo">
    <h1>نظام الاحوال الشخصية والقنصلية</h1>
  </div>

  <div class="auth-right">
    <div class="card auth-card">

      <!-- Login tab -->
      <div class="tab-content active" id="login">
        <!-- Fake hidden inputs to trap autofill -->
        <!-- Dummy fields to trick password manager -->
        <input type="text" name="fakeusernameremembered" style="display:none" autocomplete="username">
        <input type="password" name="fakepasswordremembered" style="display:none" autocomplete="new-password">


        <form method="post" action="login.php" autocomplete="off">
          <label>اسم المستخدم
            <input type="text" name="username" autocomplete="off" required>
          </label>
          <label>كلمة المرور
            <input type="password" name="password" autocomplete="off" required>
          </label>
          <button type="submit" class="primary">دخول</button>
        </form>
        <?php if ($error): ?>
          <p class="message error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
          <p class="message success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['changed'])): ?>
          <p class="message success">✅ تم تغيير كلمة المرور بنجاح. الرجاء تسجيل الدخول بكلمة المرور الجديدة.</p>
        <?php endif; ?>

        <div class="auth-links">
          <a href="#" data-tab="change">تغيير كلمة المرور</a> ·
          <a href="#" data-tab="register">إنشاء حساب جديد</a>
        </div>
      </div>

      <!-- Change password tab -->
      <div class="tab-content" id="change" style="display:none;">
        <form method="post" action="change_password.php">
          <label>اسم المستخدم
            <input type="text" name="username" required>
          </label>
          <label>كلمة المرور الحالية
            <input type="password" name="old_password" autocomplete="off" required>
          </label>
          <label>كلمة المرور الجديدة
            <input type="password" name="new_password" autocomplete="off" required>
          </label>
          <button type="submit" class="primary">تغيير</button>
        </form>
        <div class="auth-links">
          <a href="#" data-tab="login">رجوع لتسجيل الدخول</a>
        </div>
      </div>

      <!-- Register tab -->
      <div class="tab-content" id="register" style="display:none;">
        <form method="post" action="register.php" enctype="multipart/form-data">
          <label>الاسم بالعربية
            <input type="text" name="EmployeeName" required>
          </label>

          <label>الاسم بالانجليزية
            <input type="text" name="EngEmployeeName" dir="ltr" required>
          </label>

          <fieldset>
            <legend>الجنس</legend>
            <label><input type="radio" name="Gender" value="ذكر" required> ذكر</label>
            <label><input type="radio" name="Gender" value="انثى"> أنثى</label>
          </fieldset>

          <label>المسمى الوظيفي
            <select name="JobPosition" id="JobPosition" required>
              <option value="">-- اختر --</option>
              <option value="السفير رئيس البعثة">السفير رئيس البعثة</option>
              <option value="القنصل العام">القنصل العام</option>
              <option value="القنصل العام بالإنابة">القنصل العام بالإنابة</option>
              <option value="نائب قنصل">نائب قنصل</option>
              <option value="ملحق إداري">ملحق إداري</option>
              <option value="تعين محلي">تعين محلي</option>
              <option value="مندوب جالية">مندوب جالية</option>
              <option value="محاسب">محاسب</option>
              <option value="مدير مالي">مدير مالي</option>
              <option value="اخرى">اخرى</option>
            </select>
          </label>

          <fieldset id="diplomat-fieldset">
            <legend>الدبلوماسيون</legend>
            <label><input type="radio" name="الدبلوماسيون" value="yes"> نعم</label>
            <label><input type="radio" name="الدبلوماسيون" value="no"> لا</label>
          </fieldset>

          <fieldset id="authorized-fieldset">
            <legend>مأذون</legend>
            <label><input type="radio" name="مأذون" value="yes"> نعم</label>
            <label><input type="radio" name="مأذون" value="no"> لا</label>
          </fieldset>

          <fieldset id="headmission-fieldset">
            <legend>رئيس البعثة</legend>
            <label><input type="radio" name="headOfMission" value="yes"> نعم</label>
            <label><input type="radio" name="headOfMission" value="no"> لا</label>
          </fieldset>

          <div id="username-wrapper">
            <label>اسم المستخدم (إنجليزي فقط)
              <input type="text" name="UserName" dir="ltr">
            </label>
          </div>

          <div id="titles-wrapper">
            <label>الصفة بالعربية
              <select name="AuthenticType">
                <option value="">-- اختر --</option>
                <option value="نائب قنصل">نائب قنصل</option>
                <option value="نائبة قنصل">نائبة قنصل</option>
                <option value="القنصل">القنصل</option>
                <option value="نائب القنصل">نائب القنصل</option>
                <option value="السفير">السفير</option>
              </select>
            </label>

            <label>الصفة بالانجليزية
              <select name="AuthenticTypeEng">
                <option value="">-- اختر --</option>
                <option value="The Consul">The Consul</option>
                <option value="Vice Consul">Vice Consul</option>
                <option value="H.E Head of mission">H.E Head of mission</option>
                <option value="Deputy Head of mission">H.E Head of mission</option>
              </select>
            </label>
          </div>

          <label>البريد الإلكتروني
            <input type="email" name="Email" required>
          </label>

          <label>رقم الهاتف
            <input type="text" name="PhoneNo">
          </label>

          <label>كلمة المرور
            <input type="password" name="password" required>
          </label>

          <label>إعادة كلمة المرور
            <input type="password" name="repassword" required>
          </label>

          <button type="submit" class="primary">إنشاء الحساب</button>
        </form>

        <div class="auth-links">
          <a href="#" data-tab="login">رجوع لتسجيل الدخول</a>
        </div>
      </div>

<script>
const jobSelect = document.getElementById("JobPosition");
const diplomatFieldset = document.getElementById("diplomat-fieldset");
const authorizedFieldset = document.getElementById("authorized-fieldset");
const headMissionFieldset = document.getElementById("headmission-fieldset");
const usernameWrapper = document.getElementById("username-wrapper");
const titlesWrapper = document.getElementById("titles-wrapper");
window.addEventListener("load", () => {
  document.querySelectorAll("input[type=password], input[name=username]")
    .forEach(el => el.value = "");
});
function updateFields() {
  const diplomatJobs = ["السفير رئيس البعثة", "القنصل العام", "القنصل العام بالإنابة", "نائب قنصل"];
  const selected = jobSelect.value;

  if (diplomatJobs.includes(selected)) {
    // Diplomat jobs
    diplomatFieldset.style.display = "block";
    authorizedFieldset.style.display = "block";
    headMissionFieldset.style.display = "block";
    usernameWrapper.style.display = "block";
    titlesWrapper.style.display = "block";

    // auto check diplomat = yes
    diplomatFieldset.querySelector("input[value='yes']").checked = true;
    // make required
    usernameWrapper.querySelector("input").required = true;
    titlesWrapper.querySelectorAll("select").forEach(s => s.required = true);

  } else {
    // Non-diplomat jobs
    diplomatFieldset.style.display = "none";
    authorizedFieldset.style.display = "none";
    headMissionFieldset.style.display = "none";
    usernameWrapper.style.display = "none";
    titlesWrapper.style.display = "none";

    // auto check hidden radios to no
    diplomatFieldset.querySelector("input[value='no']").checked = true;
    authorizedFieldset.querySelector("input[value='no']").checked = true;
    headMissionFieldset.querySelector("input[value='no']").checked = true;

    // remove required
    usernameWrapper.querySelector("input").required = false;
    titlesWrapper.querySelectorAll("select").forEach(s => s.required = false);
  }
}
jobSelect.addEventListener("change", updateFields);
updateFields(); 
function switchTab(id) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === id));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.toggle('active', t.id === id));
}
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});
document.querySelectorAll('[data-tab]').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
    const target = document.getElementById(link.dataset.tab);
    if (target) target.style.display = 'block';
  });
});

</script>
</body>
</html>
