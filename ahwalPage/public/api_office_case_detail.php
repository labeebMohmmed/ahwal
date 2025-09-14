<?php
/* api_office_case_detail.php
 * Input : ?id=OFFICE_ID   (TableAuth.ID)
 * Output: { case, party, details }  // same shape as api_case_detail.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin', '*');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'id required']); exit; }

$dsn = "sqlsrv:Server=localhost;Database=AhwalDataBase;Encrypt=yes;TrustServerCertificate=yes";
$pdo = new PDO($dsn, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ---- helpers ----
function splitCommaArab(?string $s): array {
  if (!$s) return [];
  // normalize Arabic comma to western, split, trim; keep empty entries if user left double commas
  $s = str_replace(['،', ' ,', ', '], [',', ',', ','], $s);
  $parts = array_map(fn($x)=>trim($x), explode(',', $s));
  // do NOT filter empties here; we need positional alignment across columns
  return $parts;
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
  // already one of the two, or custom
  return $s;
}
function ymdOrRaw(?string $s) {
  if (!$s) return null;
  // accept YYYY-MM-DD already
  if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $s)) return $s;
  // accept DD/MM/YYYY or MM/DD/YYYY → best-effort Y-m-d
  if (preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$~', $s, $m)) {
    $d = str_pad($m[1],2,'0',STR_PAD_LEFT);
    $m2= str_pad($m[2],2,'0',STR_PAD_LEFT);
    return "{$m[3]}-$m2-$d";
  }
  return $s; // leave as-is if unknown
}

// ---- fetch one office row ----
$st = $pdo->prepare("SELECT TOP 1 * FROM dbo.TableAuth WHERE ID = :id");
$st->execute([':id'=>$id]);
$r = $st->fetch();
if (!$r) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }

// ---- split multi-value fields (comma/Arabic-comma) ----
// applicants
$a_name = splitCommaArab($r['مقدم_الطلب'] ?? '');
$a_sex  = splitCommaArab($r['النوع'] ?? '');
$a_job  = splitCommaArab($r['المهنة'] ?? '');
$a_res  = splitCommaArab($r['وضع_الإقامة'] ?? '');
$a_dob  = splitCommaArab($r['تاريخ_الميلاد'] ?? '');
$a_idt  = splitCommaArab($r['نوع_الهوية'] ?? '');
$a_idn  = splitCommaArab($r['رقم_الهوية'] ?? '');
$a_iss  = splitCommaArab($r['مكان_الإصدار'] ?? '');
$a_exp  = splitCommaArab($r['انتهاء_الصلاحية'] ?? '');
$a_resd  = splitCommaArab($r['وضع_الإقامة'] ?? '');

$amax = max(
  count($a_name), count($a_sex), count($a_job), count($a_res),
  count($a_dob), count($a_idt), count($a_idn), count($a_iss), count($a_exp), count($a_resd)
);

// principals (authenticated)
$p_name = splitCommaArab($r['الموكَّل'] ?? '');
$p_sex  = splitCommaArab($r['جنس_الموكَّل'] ?? '');
$p_nat  = splitCommaArab($r['جنسية_الموكل'] ?? '');
$p_idt  = splitCommaArab($r['نوع_هوية'] ?? '');
$p_idn  = splitCommaArab($r['هوية_الموكل'] ?? '');
$pmax = max(count($p_name), count($p_sex), count($p_nat), count($p_idt), count($p_idn));

// ---- build party.applicants[] ----
$applicants = [];
for ($i=0; $i<$amax; $i++) {
  $name = at($a_name,$i);
  $resd = at($a_resd,$i);
  if ($name === null || $name === '') continue; // skip fully empty
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
    'nationality'     => null, // not stored per-applicant in office; leave null
    'residenceStatus' => at($a_res,$i),
    'dob'             => ymdOrRaw(at($a_dob,$i)),
    'ids'             => $ids,
    'resd'            => $resd,
  ];
}

// ---- build party.authenticated[] ----
$authenticated = [];
for ($i=0; $i<$pmax; $i++) {
  $name = at($p_name,$i);
  if (!$name) continue;
  $ids = [];
  $idp = at($p_idn,$i);
  if ($idp) {
    $ids[] = [
      'type'   => at($p_idt,$i),
      'number' => $idp,
    ];
  }
  $authenticated[] = [
    'name'        => $name,
    'sex'         => sexToLetter(at($p_sex,$i)),
    'nationality' => at($p_nat,$i),
    'ids'         => $ids,
  ];
}

// ---- witnesses (two flat columns in office) ----
$witnesses = [];
if (!empty($r['الشاهد_الأول'])) {
  $witnesses[] = [
    'name' => $r['الشاهد_الأول'],
    'ids'  => array_filter([ ['type'=>'جواز سفر', 'number'=>$r['هوية_الأول'] ?? null] ],
                           fn($x)=>!empty($x['number']))
  ];
}
if (!empty($r['الشاهد_الثاني'])) {
  $witnesses[] = [
    'name' => $r['الشاهد_الثاني'],
    'ids'  => array_filter([ ['type'=>'جواز سفر', 'number'=>$r['هوية_الثاني'] ?? null] ],
                           fn($x)=>!empty($x['number']))
  ];
}

// ---- details.model ----
$langLabel = normLang($r['اللغة'] ?? null);
$model = [
  'id'           => null,            // fill from your template lookup if known
  'mainGroup'    => 'توكيل',
  'altColName'   => $r['نوع_التوكيل'] ?? null,
  'altSubColName'=> $r['إجراء_التوكيل'] ?? null,
  'langLabel'    => $langLabel,      // 'العربية' / 'الانجليزية'
];

// ---- details.answers ----
$fields = [
  // dynamic fields
  'itext1' => $r['itext1'] ?? null,
  'itext2' => $r['itext2'] ?? null,
  'itext3' => $r['itext3'] ?? null,
  'itext4' => $r['itext4'] ?? null,
  'itext5' => $r['itext5'] ?? null,
  'itext6' => $r['itext6'] ?? null,
  'itext7' => $r['itext7'] ?? null,
  'itext8' => $r['itext8'] ?? null,
  'itext9' => $r['itext9'] ?? null,
  'itext10'=> $r['itext10'] ?? null,

  'icombo1' => $r['icombo1'] ?? null,
  'icombo2' => $r['icombo2'] ?? null,
  'icheck1' => $r['icheck1'] ?? null,
  'itxtDate1'=> ymdOrRaw($r['itxtDate1'] ?? null),
  'itxtDate2'=> ymdOrRaw($r['itxtDate2'] ?? null),

  // domain fields
  'subject'           => $r['موضوع_التوكيل'] ?? null,
  'subjectExtra'      => $r['اضافة_الموضوع'] ?? null,
  'rightsText'        => $r['حقوق_التوكيل'] ?? null,
  'rightsRaw'         => $r['قائمة_الحقوق'] ?? null,
  'officeLocation'    => $r['موقع_التوكيل'] ?? null,
  'requestMethod'     => $r['طريقة_الطلب'] ?? null,
  'hijriDate'         => $r['التاريخ_الهجري'] ?? null,
  'notes'             => $r['تعليق'] ?? null,
  'archiveStatus'     => $r['حالة_الارشفة'] ?? null,
  'archivedAt1'       => ymdOrRaw($r['تاريخ_الارشفة1'] ?? null),
  'archivedAt2'       => ymdOrRaw($r['تاريخ_الارشفة2'] ?? null),
  'offlineRef'        => $r['رقم_التوكيل'] ?? null,
  'offlineAgencyNo'   => $r['رقم_الوكالة'] ?? null,
  'agencyIssuer'      => $r['جهة_إصدار_الوكالة'] ?? null,
  'agencyIssueDate'   => ymdOrRaw($r['تاريخ_إصدار_الوكالة'] ?? null),
  'principalSignatureName' => $r['اسم_الموكل_بالتوقيع'] ?? null,
  'signerType'        => $r['نوع_الموقع'] ?? null,
  'signerCapacity'    => $r['صفة_الموقع'] ?? null,
  'legalStatusText'   => $r['الصفة_القانونية'] ?? null,
  'paymentStatus'     => $r['حالة_السداد'] ?? null,
  'destination'       => $r['الوجهة'] ?? null,
  'footerText'        => $r['الخاتمة'] ?? null,
  'approvalDuration'  => $r['مدة_الاعتماد'] ?? null,
];
$answers = [
  'fields'     => $fields,
  '_touchedAt' => gmdate('c'),
];

$requirements = [
  'needAuthenticated'     => !empty($authenticated),
  'needWitnesses'         => true,
  'needWitnessesOptional' => false,
];

// ---- final payload (same keys as api_case_detail.php) ----
$payload = [
  'case' => [
    'caseId'      => (int)$r['ID'],                             // use office ID here
    'externalRef' => (string)($r['الرقم_المرجعي'] ?? ''),
    'userId'      => 0,
    'modelId'     => null,                                      // fill if you have mapping
    'lang'        => normLang($r['اللغة'] ?? null),             // "العربية"/"الانجليزية"
    'status'      => 'office',                                  // don’t mix with online workflow
    'createdAt'   => ymdOrRaw($r['التاريخ_الميلادي'] ?? null),
    'submittedAt' => null,
  ],
  'party' => [
    'applicants'   => $applicants,
    'authenticated'=> $authenticated,
    'witnesses'    => $witnesses,
    'contact'      => ['phone'=>'','email'=>''],
  ],
  'details' => [
    'model'        => $model,
    'answers'      => $answers,
    'requirements' => $requirements,
    // include textModel if you store it outside fields:
    // 'textModel'  => $r['نص_المعاملة'] ?? null,
  ],
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
