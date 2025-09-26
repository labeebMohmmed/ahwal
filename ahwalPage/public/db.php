<?php
declare(strict_types=1);

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

    // Read from INI sections if you have them, else env, else sane defaults:
    $def = function_exists('cfg_ini_section') ? cfg_ini_section($GLOBALS['INI'], 'default') : [];
    $sec = [];
    if ($dbname && function_exists('cfg_ini_section')) {
        $sec = cfg_ini_section($GLOBALS['INI'], $dbname) ?: cfg_ini_section($GLOBALS['INI'], strtolower($dbname)) ?: [];
    }

    // --- Defaults aligned with your Python string ---
    $server = $sec['SERVER'] ?? $def['SERVER'] ?? getenv('DB_SERVER') ?: '192.168.100.67,49149';
    $name   = $sec['NAME']   ?? $def['NAME']   ?? getenv('DB_NAME')   ?: ($dbname ?: 'AhwalDataBase');
    $user   = $sec['USER']   ?? $def['USER']   ?? getenv('DB_USER')   ?: 'SQLSerAdmin';
    $pass   = $sec['PASS']   ?? $def['PASS']   ?? getenv('DB_PASS')   ?: 'sqlSER@jed80';

    // Python had Trusted_Connection=no → SQL auth. No direct PDO flag; just pass user/pass.
    // Encryption defaults: Python example didn’t specify, so make it off by default:
    $enc    = $sec['ENCRYPT'] ?? $def['ENCRYPT'] ?? getenv('DB_ENCRYPT') ?: 'no';   // 'yes' or 'no'
    $tsc    = $sec['TSC']     ?? $def['TSC']     ?? getenv('DB_TSC')     ?: 'yes';  // TrustServerCertificate

    // If caller explicitly passed a DB name, prefer that over env:
    if ($dbname) $name = $dbname;

    $key = md5(json_encode([$server,$name,$user,$enc,$tsc], JSON_UNESCAPED_UNICODE));
    if (isset($POOL[$key]) && $POOL[$key] instanceof PDO) return $POOL[$key];

    // Build DSN. sqlsrv accepts "Server=host,port"
    $dsn = "sqlsrv:Server={$server};Database={$name};Encrypt={$enc};TrustServerCertificate={$tsc};";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (defined('PDO::SQLSRV_ATTR_ENCODING') && defined('PDO::SQLSRV_ENCODING_UTF8')) {
        $options[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
    }

    try {
        // SQL authentication (since user/pass provided)
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (Throwable $e) {
        error_log("[DB] Connect failed: server={$server}; db={$name}; user={$user}; enc={$enc}; tsc={$tsc}; err=".$e->getMessage());
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
