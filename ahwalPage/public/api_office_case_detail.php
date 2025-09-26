<?php
// ✅ correct CORS/headers at the top of api_office_case_detail.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');                           // <-- no 2nd arg
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Handle preflight quickly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
require __DIR__ . '/db.php';
$pdo = db();

// ---------- helpers ----------
function splitCommaArab(?string $s): array {
  if (!$s) return [];
  $s = str_replace(['،', ' ,', ', '], [',', ',', ','], $s);
  return array_map(fn($x)=>trim($x), explode(',', $s));
}
function at(array $a, int $i) { return array_key_exists($i,$a) ? (strlen($a[$i])? $a[$i] : null) : null; }
function sexToLetter(?string $s) {
  $s = $s ? trim($s) : null;
  if ($s === 'ذكر') return 'M';
  if ($s === 'أنثى') return 'F';
  return null;
}
function normLang(?string $s) {
  if (!$s) return 'العربية';
  $s = trim($s);
  if (stripos($s, 'انجلي') !== false || stripos($s, 'إنجلي') !== false) return 'الانجليزية';
  if (stripos($s, 'عرب') !== false) return 'العربية';
  return $s;
}
function ymdOrRaw(?string $s) {
  if (!$s) return null;
  if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $s)) return $s;
  if (preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$~', $s, $m)) {
    $d = str_pad($m[1],2,'0',STR_PAD_LEFT);
    $m2= str_pad($m[2],2,'0',STR_PAD_LEFT);
    return "{$m[3]}-$m2-$d";
  }
  return $s;
}
function normalizeBool($v) {
  if ($v === null) return null;
  if (is_numeric($v)) return ((int)$v) ? 1 : 0;
  $t = trim((string)$v);
  return in_array($t, ['1','true','TRUE','نعم','Yes','yes'], true) ? 1 : 0;
}

// ---------- input ----------
$id = (int)( $_GET['id'] ?? $_POST['id'] ?? 0 );
$mainGroup = (string)( $_GET['mainGroup'] ?? $_POST['mainGroup'] ?? 'توكيل' );
if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }

// ---------- loaders ----------
function loadFromAuth(PDO $pdo, int $id): array {
  $st = $pdo->prepare("SELECT TOP 1 * FROM dbo.TableAuth WHERE ID = :id");
  $st->execute([':id'=>$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) throw new RuntimeException('not found');

  // applicants (comma separated columns)
  $a_name = splitCommaArab($r['مقدم_الطلب'] ?? '');
  $a_sex  = splitCommaArab($r['النوع'] ?? '');
  $a_job  = splitCommaArab($r['المهنة'] ?? '');
  $a_res  = splitCommaArab($r['وضع_الإقامة'] ?? '');
  $a_dob  = splitCommaArab($r['تاريخ_الميلاد'] ?? '');
  $a_idt  = splitCommaArab($r['نوع_الهوية'] ?? '');
  $a_idn  = splitCommaArab($r['رقم_الهوية'] ?? '');
  $a_iss  = splitCommaArab($r['مكان_الإصدار'] ?? '');
  $a_exp  = splitCommaArab($r['انتهاء_الصلاحية'] ?? '');
  $amax = max(count($a_name),count($a_sex),count($a_job),count($a_res),count($a_dob),count($a_idt),count($a_idn),count($a_iss),count($a_exp));

  $applicants = [];
  for ($i=0; $i<$amax; $i++) {
    $name = at($a_name,$i);
    if (!$name) continue;
    $ids = [];
    $idnum = at($a_idn,$i);
    if ($idnum) {
      $ids[] = [
        'type'   => at($a_idt,$i),
        'number' => $idnum,
        'issuer' => at($a_iss,$i),
        'expiry' => ymdOrRaw(at($a_exp,$i)),
      ];
    }
    $applicants[] = [
      'role'            => ($i===0 ? 'primary' : 'co'),
      'name'            => $name,
      'sex'             => sexToLetter(at($a_sex,$i)),
      'job'             => at($a_job,$i),
      'nationality'     => null,
      'residenceStatus' => at($a_res,$i),
      'dob'             => ymdOrRaw(at($a_dob,$i)),
      'ids'             => $ids,
    ];
  }

  // principals/authenticated (specific to TableAuth)
  $p_name = splitCommaArab($r['الموكَّل'] ?? '');
  $p_sex  = splitCommaArab($r['جنس_الموكَّل'] ?? '');
  $p_nat  = splitCommaArab($r['جنسية_الموكل'] ?? '');
  $p_idt  = splitCommaArab($r['نوع_هوية'] ?? '');
  $p_idn  = splitCommaArab($r['هوية_الموكل'] ?? '');
  $pmax = max(count($p_name),count($p_sex),count($p_nat),count($p_idt),count($p_idn));
  $authenticated = [];
  for ($i=0;$i<$pmax;$i++){
    $name = at($p_name,$i); if (!$name) continue;
    $ids = [];
    $idp = at($p_idn,$i);
    if ($idp) $ids[] = ['type'=>at($p_idt,$i),'number'=>$idp];
    $authenticated[] = [
      'name'=>$name,
      'sex'=>sexToLetter(at($p_sex,$i)),
      'nationality'=>at($p_nat,$i),
      'ids'=>$ids,
    ];
  }

  // witnesses
  $witnesses = [];
  if (!empty($r['الشاهد_الأول'])) $witnesses[] = ['name'=>$r['الشاهد_الأول'],'ids'=>array_filter([['type'=>'جواز سفر','number'=>$r['هوية_الأول'] ?? null]], fn($x)=>!empty($x['number']))];
  if (!empty($r['الشاهد_الثاني'])) $witnesses[] = ['name'=>$r['الشاهد_الثاني'],'ids'=>array_filter([['type'=>'جواز سفر','number'=>$r['هوية_الثاني'] ?? null]], fn($x)=>!empty($x['number']))];

  $langLabel = normLang($r['اللغة'] ?? null);

  // dynamic fields (TableAuth schema)
  $fields = [
    'itext1'=>$r['itext1'] ?? null,
    'itext2'=>$r['itext2'] ?? null,
    'itext3'=>$r['itext3'] ?? null,
    'itext4'=>$r['itext4'] ?? null,
    'itext5'=>$r['itext5'] ?? null,
    'itext6'=>$r['itext6'] ?? null,
    'itext7'=>$r['itext7'] ?? null,
    'itext8'=>$r['itext8'] ?? null,
    'itext9'=>$r['itext9'] ?? null,
    'itext10'=>$r['itext10'] ?? null,
    'icombo1'=>$r['icombo1'] ?? null,
    'icombo2'=>$r['icombo2'] ?? null,
    'icheck1'=>normalizeBool($r['icheck1'] ?? null),
    'itxtDate1'=>ymdOrRaw($r['itxtDate1'] ?? null),
    'itxtDate2'=>ymdOrRaw($r['itxtDate2'] ?? null),

    // domain-ish
    'subject'      => $r['موضوع_التوكيل'] ?? null,
    'subjectExtra' => $r['اضافة_الموضوع'] ?? null,
    'rightsText'   => $r['حقوق_التوكيل'] ?? null,
    'rightsRaw'    => $r['قائمة_الحقوق'] ?? null,
    'officeLocation' => $r['موقع_التوكيل'] ?? null,
    'requestMethod'  => $r['طريقة_الطلب'] ?? null,
    'hijriDate'      => $r['التاريخ_الهجري'] ?? null,
    'notes'          => $r['تعليق'] ?? null,
    'archiveStatus'  => $r['حالة_الارشفة'] ?? null,
    'archivedAt1'    => ymdOrRaw($r['تاريخ_الارشفة1'] ?? null),
    'archivedAt2'    => ymdOrRaw($r['تاريخ_الارشفة2'] ?? null),
    'offlineRef'     => $r['رقم_التوكيل'] ?? null,
    'offlineAgencyNo'=> $r['رقم_الوكالة'] ?? null,
    'agencyIssuer'   => $r['جهة_إصدار_الوكالة'] ?? null,
    'agencyIssueDate'=> ymdOrRaw($r['تاريخ_إصدار_الوكالة'] ?? null),
    'principalSignatureName' => $r['اسم_الموكل_بالتوقيع'] ?? null,
    'signerType'     => $r['نوع_الموقع'] ?? null,
    'signerCapacity' => $r['صفة_الموقع'] ?? null,
    'legalStatusText'=> $r['الصفة_القانونية'] ?? null,
    'paymentStatus'  => $r['حالة_السداد'] ?? null,
    'destination'    => $r['الوجهة'] ?? null,
    'footerText'     => $r['الخاتمة'] ?? null,
    'approvalDuration'=> $r['مدة_الاعتماد'] ?? null,
  ];

  $payload = [
    'case' => [
      'caseId'      => (int)$r['ID'],
      'externalRef' => (string)($r['الرقم_المرجعي'] ?? ''),
      'userId'      => 0,
      'modelId'     => null,
      'lang'        => $langLabel,
      'status'      => 'office',
      'createdAt'   => ymdOrRaw($r['التاريخ_الميلادي'] ?? null),
      'submittedAt' => null,
      'doc_id'      => $r['رقم_التوكيل'] ?? null,
    ],
    'party' => [
      'applicants'    => $applicants,
      'authenticated' => $authenticated,
      'witnesses'     => $witnesses,
      'contact'       => ['phone'=>'','email'=>''],
    ],
    'details' => [
      'model' => [
        'id'              => null,
        'mainGroup'       => 'توكيل',
        'altColName'      => $r['نوع_التوكيل'] ?? null,
        'altSubColName'   => $r['إجراء_التوكيل'] ?? null,
        'langLabel'       => $langLabel,
      ],
      'answers'      => ['fields'=>$fields, '_touchedAt'=>gmdate('c')],
      'requirements' => [
        'needAuthenticated'     => !empty($authenticated),
        'needWitnesses'         => true,
        'needWitnessesOptional' => false,
      ],
    ],
  ];
  return $payload;
}

function loadFromCollection(PDO $pdo, int $id, string $mainGroup): array {
  $st = $pdo->prepare("SELECT TOP 1 * FROM dbo.TableCollection WHERE ID = :id");
  $st->execute([':id'=>$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) throw new RuntimeException('not found');

  // applicants (same multi-column pattern)
  $a_name = splitCommaArab($r['مقدم_الطلب'] ?? '');
  $a_sex  = splitCommaArab($r['النوع'] ?? '');
  $a_job  = splitCommaArab($r['المهنة'] ?? '');
  $a_res  = splitCommaArab($r['وضع_الإقامة'] ?? '');
  $a_dob  = splitCommaArab($r['تاريخ_الميلاد'] ?? '');
  $a_idt  = splitCommaArab($r['نوع_الهوية'] ?? '');
  $a_idn  = splitCommaArab($r['رقم_الهوية'] ?? '');
  $a_iss  = splitCommaArab($r['مكان_الإصدار'] ?? '');
  $a_exp  = splitCommaArab($r['انتهاء_الصلاحية'] ?? '');
  $amax = max(count($a_name),count($a_sex),count($a_job),count($a_res),count($a_dob),count($a_idt),count($a_idn),count($a_iss),count($a_exp));

  $applicants = [];
  for ($i=0; $i<$amax; $i++) {
    $name = at($a_name,$i);
    if (!$name) continue;
    $ids = [];
    $idnum = at($a_idn,$i);
    if ($idnum) {
      $ids[] = [
        'type'   => at($a_idt,$i),
        'number' => $idnum,
        'issuer' => at($a_iss,$i),
        'expiry' => ymdOrRaw(at($a_exp,$i)),
      ];
    }
    $applicants[] = [
      'role'            => ($i===0 ? 'primary' : 'co'),
      'name'            => $name,
      'sex'             => sexToLetter(at($a_sex,$i)),
      'job'             => at($a_job,$i),
      'nationality'     => null,
      'residenceStatus' => at($a_res,$i),
      'dob'             => ymdOrRaw(at($a_dob,$i)),
      'ids'             => $ids,
    ];
  }

  // witnesses
  $witnesses = [];
  if (!empty($r['الشاهد_الأول'])) $witnesses[] = ['name'=>$r['الشاهد_الأول'],'ids'=>array_filter([['type'=>'جواز سفر','number'=>$r['هوية_الأول'] ?? null]], fn($x)=>!empty($x['number']))];
  if (!empty($r['الشاهد_الثاني'])) $witnesses[] = ['name'=>$r['الشاهد_الثاني'],'ids'=>array_filter([['type'=>'جواز سفر','number'=>$r['هوية_الثاني'] ?? null]], fn($x)=>!empty($x['number']))];

  $langLabel = normLang($r['اللغة'] ?? null);

  // dynamic fields mapping (Vi* then extra i* present in Collection)
  $fields = [
    // map Vi* to itext*/icombo*/icheck*
    'itext1'=>$r['Vitext1'] ?? null,
    'itext2'=>$r['Vitext2'] ?? null,
    'itext3'=>$r['Vitext3'] ?? null,
    'itext4'=>$r['Vitext4'] ?? null,
    'itext5'=>$r['Vitext5'] ?? null,
    // Collection also has later itext6..10, icombo3..5, icheck2..5, itxtDate2..5:
    'itext6'=>$r['itext6'] ?? null,
    'itext7'=>$r['itext7'] ?? null,
    'itext8'=>$r['itext8'] ?? null,
    'itext9'=>$r['itext9'] ?? null,
    'itext10'=>$r['itext10'] ?? null,

    'icombo1'=>$r['Vicombo1'] ?? null,
    'icombo2'=>$r['Vicombo2'] ?? null,
    'icombo3'=>$r['icombo3'] ?? null,
    'icombo4'=>$r['icombo4'] ?? null,
    'icombo5'=>$r['icombo5'] ?? null,

    'icheck1'=>normalizeBool($r['Vicheck1'] ?? null),
    'icheck2'=>normalizeBool($r['icheck2'] ?? null),
    'icheck3'=>normalizeBool($r['icheck3'] ?? null),
    'icheck4'=>normalizeBool($r['icheck4'] ?? null),
    'icheck5'=>normalizeBool($r['icheck5'] ?? null),

    'itxtDate1'=>ymdOrRaw($r['VitxtDate1'] ?? null),
    'itxtDate2'=>ymdOrRaw($r['itxtDate2'] ?? null),
    'itxtDate3'=>ymdOrRaw($r['itxtDate3'] ?? null),
    'itxtDate4'=>ymdOrRaw($r['itxtDate4'] ?? null),
    'itxtDate5'=>ymdOrRaw($r['itxtDate5'] ?? null),

    // domain-ish (mapped names)
    'subject'        => $r['غرض_المعاملة'] ?? null,                    // best-effort
    'subjectExtra'   => null,
    'rightsText'     => null,
    'rightsRaw'      => null,
    'officeLocation' => $r['موقع_المعاملة'] ?? null,
    'requestMethod'  => $r['طريقة_الطلب'] ?? null,
    'hijriDate'      => $r['التاريخ_الهجري'] ?? null,
    'notes'          => $r['تعليق'] ?? null,
    'archiveStatus'  => $r['حالة_الارشفة'] ?? null,
    'archivedAt1'    => ymdOrRaw($r['تاريخ_الارشفة1'] ?? null),
    'archivedAt2'    => ymdOrRaw($r['تاريخ_الارشفة2'] ?? null),
    'offlineRef'     => $r['رقم_المعاملة'] ?? null,                    // acts like doc_id in Collection
    'offlineAgencyNo'=> $r['رقم_الوكالة'] ?? null,
    'agencyIssuer'   => $r['جهة_إصدار_الوكالة'] ?? null,
    'agencyIssueDate'=> ymdOrRaw($r['تاريخ_إصدار_الوكالة'] ?? null),
    'principalSignatureName' => $r['اسم_الموكل_بالتوقيع'] ?? null,
    'signerType'     => $r['نوع_الموقع'] ?? null,
    'signerCapacity' => $r['صفة_الموقع'] ?? null,
    'legalStatusText'=> $r['الصفة_القانونية'] ?? null,
    'paymentStatus'  => $r['حالة_السداد'] ?? null,
    'destination'    => $r['الوجهة'] ?? ($r['وجهة_المعاملة'] ?? null),
    'footerText'     => $r['الخاتمة'] ?? null,
    'approvalDuration'=> $r['مدة_الاعتماد'] ?? null,
    'textModel'      => $r['نص_المعاملة'] ?? null, // if you use it
  ];

  $payload = [
    'case' => [
      'caseId'      => (int)$r['ID'],
      'externalRef' => (string)($r['الرقم_المرجعي'] ?? ''),
      'userId'      => 0,
      'modelId'     => null,
      'lang'        => $langLabel,
      'status'      => 'office',
      'createdAt'   => ymdOrRaw($r['التاريخ_الميلادي'] ?? null),
      'submittedAt' => null,
      'doc_id'      => $r['رقم_المعاملة'] ?? null,
    ],
    'party' => [
      'applicants'    => $applicants,
      'authenticated' => [], // Collection doesn’t carry principals set like TableAuth
      'witnesses'     => $witnesses,
      'contact'       => ['phone'=>$r['رقم_هاتف'] ?? '','email'=>''],
    ],
    'details' => [
      'model' => [
        'id'            => null,
        'mainGroup'     => $mainGroup,
        'altColName'    => $r['نوع_المعاملة'] ?? null,
        'altSubColName' => $r['نوع_الإجراء'] ?? null, // also لديك طريقة_الإجراء
        'langLabel'     => $langLabel,
      ],
      'answers'      => ['fields'=>$fields, '_touchedAt'=>gmdate('c')],
      'requirements' => [
        'needAuthenticated'     => false,
        'needWitnesses'         => true,
        'needWitnessesOptional' => false,
      ],
    ],
  ];
  return $payload;
}

// ---------- dispatch ----------
try {
  if ($mainGroup === 'توكيل') {
    $payload = loadFromAuth($pdo, $id);
  } else {
    $payload = loadFromCollection($pdo, $id, $mainGroup);
  }
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
  http_response_code($e->getMessage()==='not found' ? 404 : 400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
