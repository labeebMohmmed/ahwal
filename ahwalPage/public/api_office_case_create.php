<?php
require __DIR__ . '/db.php';
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$in = $_POST;
if (empty($in)) { $raw = file_get_contents('php://input'); $in = json_decode($raw, true) ?: []; }

$caseId    = isset($in['existingCaseId']) ? (int)$in['existingCaseId'] : 0;

$lang      = strtolower(trim((string)($in['lang'] ?? 'ar')));

if ($lang === 'en' || $lang === 'الانجليزية') {
    $lang = 'الانجليزية';
} else $lang = 'العربية';

$userId    = isset($in['userId']) ? (int)$in['userId'] : null;
$mainGroup = trim((string)($in['mainGroup'] ?? ''));
$altCol    = trim((string)($in['altColName'] ?? ''));
$altSub    = trim((string)($in['altSubColName'] ?? ''));

if ( $mainGroup === '' || $altCol === '') {
  http_response_code(400);
  echo json_encode(['error'=>'missing or invalid fields'], JSON_UNESCAPED_UNICODE);
  exit;
}

function generateDocNumber(PDO $pdo, string $table, string $officeCode, string $officeNumber, bool $isAuth): string {
    // current year 2-digit
    $yy = date('y'); 

    // docType: 12 for توكيل, 10 otherwise
    $docType = $isAuth ? '12' : '10';

    // prefix before docId
    $prefix = "{$officeCode}/{$officeNumber}/{$yy}/{$docType}/";

    // 🔹 fetch last 10 matches
    $sql = "SELECT TOP 10 [رقم_المعاملة] AS DocNo FROM $table 
            WHERE [رقم_المعاملة] LIKE :prefix + '%'
            ORDER BY ID DESC";
    if ($isAuth) {
        $sql = "SELECT TOP 10 [رقم_التوكيل] AS DocNo FROM $table 
                WHERE [رقم_التوكيل] LIKE :prefix + '%'
                ORDER BY ID DESC";
    }
    $st = $pdo->prepare($sql);
    $st->execute([':prefix'=>$prefix]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    // get max docId
    $maxId = 0;
    foreach ($rows as $docNo) {
        $parts = explode('/', $docNo);
        if (count($parts) >= 5) {
            $idPart = (int)$parts[4];
            if ($idPart > $maxId) $maxId = $idPart;
        }
    }

    $newId = $maxId + 1;
    return $prefix . $newId;
}


try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // 🔹 Get employee name from TableUser
  $empName = null;
  if ($userId > 0) {
    $st = $pdo->prepare("SELECT EmployeeName FROM TableUser WHERE ID=:uid");
    $st->execute([':uid'=>$userId]);
    $empName = $st->fetchColumn();
  }

  // 🔹 Decide target table + fields
  $isAuth = ($mainGroup === 'توكيل');
  $table  = $isAuth ? 'dbo.TableAuth' : 'dbo.TableCollection';

  // === UPDATE mode ===
  if ($caseId > 0) {
    if ($isAuth) {
      $sql = "UPDATE $table 
              SET [اللغة]=:lang, [اسم_الموظف]=:emp, [نوع_التوكيل]=:altCol
              WHERE ID=:id";
      $ok = $pdo->prepare($sql)->execute([
        ':lang'=>$lang,
        ':emp'=>$empName,
        ':altCol'=>$altCol,
        ':id'=>$caseId
      ]);
    } else {
      $sql = "UPDATE $table 
              SET [اللغة]=:lang, [اسم_الموظف]=:emp, [نوع_المعاملة]=:altCol, [نوع_الإجراء]=:altSub
              WHERE ID=:id";
      $ok = $pdo->prepare($sql)->execute([
        ':lang'=>$lang,
        ':emp'=>$empName,
        ':altCol'=>$altCol,
        ':altSub'=>$altSub,
        ':id'=>$caseId
      ]);
    }

    echo json_encode([
      'ok'=>true,
      'mode'=>'update',
      'caseId'=>$caseId
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // === INSERT mode ===
  if ($isAuth) {
    $newNo = generateDocNumber($pdo, 'dbo.TableAuth', 'ق س ج', '80', true);

    $sql = "INSERT INTO dbo.TableAuth ([رقم_التوكيل],[اللغة],[اسم_الموظف],[نوع_التوكيل])
            OUTPUT INSERTED.ID
            VALUES (:num,:lang,:emp,:altCol)";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':num'=>$newNo,
      ':lang'=>$lang,
      ':emp'=>$empName,
      ':altCol'=>$altCol
    ]);
    $id = $st->fetchColumn();
    echo json_encode(['ok'=>true,'mode'=>'insert','caseId'=>$id,'رقم_التوكيل'=>$newNo], JSON_UNESCAPED_UNICODE);
} else {
    $newNo = generateDocNumber($pdo, 'dbo.TableCollection', 'ق س ج', '80', false);

    $sql = "INSERT INTO dbo.TableCollection ([رقم_المعاملة],[اللغة],[اسم_الموظف],[نوع_المعاملة],[نوع_الإجراء])
            OUTPUT INSERTED.ID
            VALUES (:num,:lang,:emp,:altCol,:altSub)";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':num'=>$newNo,
      ':lang'=>$lang,
      ':emp'=>$empName,
      ':altCol'=>$altCol,
      ':altSub'=>$altSub
    ]);
    $id = $st->fetchColumn();
    echo json_encode(['ok'=>true,'mode'=>'insert','caseId'=>$id,'رقم_المعاملة'=>$newNo], JSON_UNESCAPED_UNICODE);
}


} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
