<?php
declare(strict_types=1);

/* ---------------------------------------------------------
   اتصال موحّد بـ SQL Server مع دعم تعدد قواعد البيانات.
   الاستخدام:
     $pdo = db();                  // القاعدة الافتراضية
     $pdo2 = db('ReportsDB');      // قاعدة أخرى بالاسم
   المزايا:
     - كاش اتصال لكل قاعدة (لا يعاد فتح الاتصال)
     - قراءة الإعدادات من ENV أو ملف INI بأقسام متعددة
---------------------------------------------------------- */

/* تحميل ملف INI اختياري: ضع إعداداتك في أقسام */
$iniFile = __DIR__ . '/config.local.ini';  // لا ترفعه للمستودع
$INI = is_file($iniFile) ? parse_ini_file($iniFile, true, INI_SCANNER_TYPED) : [];

/* دوال مساعدة لقراءة الإعدادات */
function cfg_env(string $k, $def=null){ $v=getenv($k); return ($v!==false && $v!=='')? $v : $def; }
function cfg_ini_section(array $ini, string $section): array {
    return isset($ini[$section]) && is_array($ini[$section]) ? $ini[$section] : [];
}

/* دالة إرجاع PDO حسب اسم القاعدة (مع كاش داخلي) */
function db(?string $dbname = null): PDO {
    static $POOL = [];

    $def = cfg_ini_section($GLOBALS['INI'], 'default');

    $name = $dbname ?: ($def['NAME'] ?? cfg_env('DB_NAME', 'AhwalDataBase'));

    $sec = cfg_ini_section($GLOBALS['INI'], $dbname ?? '');
    if (!$sec && $dbname) { $sec = cfg_ini_section($GLOBALS['INI'], strtolower($dbname)); }

    // إعدادات الاتصال
    $server = $sec['SERVER'] ?? $def['SERVER'] ?? cfg_env('DB_SERVER', 'localhost');  // ← مطابق لقديمك
    $user   = $sec['USER']   ?? $def['USER']   ?? cfg_env('DB_USER',   '');           // ← فارغ = Integrated
    $pass   = $sec['PASS']   ?? $def['PASS']   ?? cfg_env('DB_PASS',   '');           // ← فارغ = Integrated
    $enc    = $sec['ENCRYPT']?? $def['ENCRYPT']?? cfg_env('DB_ENCRYPT','yes');
    $tsc    = $sec['TSC']    ?? $def['TSC']    ?? cfg_env('DB_TSC',    'yes');
    $name   = $sec['NAME']   ?? $name;

    $key = md5(json_encode([$server,$name,$user===''?'#I#':$user,$enc,$tsc], JSON_UNESCAPED_UNICODE));
    if (isset($POOL[$key]) && $POOL[$key] instanceof PDO) return $POOL[$key];

    // DSN مطابق لأسلوبك القديم
    $dsn = "sqlsrv:Server={$server};Database={$name};Encrypt={$enc};TrustServerCertificate={$tsc};";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (defined('PDO::SQLSRV_ATTR_ENCODING') && defined('PDO::SQLSRV_ENCODING_UTF8')) {
        $options[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
    }

    // إذا لم يوجد اسم مستخدم وكلمة مرور → استخدم Integrated (مثل new PDO($dsn, null, null, …))
    $useIntegrated = ($user === '' && $pass === '');

    try {
        $pdo = $useIntegrated
            ? new PDO($dsn, null, null, $options)   // Windows/Integrated auth
            : new PDO($dsn, $user, $pass, $options); // SQL auth (UID/PWD)
    } catch (Throwable $e) {
        // سجل معلومات مفيدة دون كلمة المرور
        error_log("[DB] Connect failed: server={$server}; db={$name}; integrated=" . ($useIntegrated?'yes':'no') . "; err=" . $e->getMessage());
        throw $e;
    }

    return $POOL[$key] = $pdo;
}


/* استجابة JSON موحّدة */
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
