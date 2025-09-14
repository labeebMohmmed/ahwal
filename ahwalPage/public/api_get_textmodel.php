<?php
// api_get_textmodel.php?template_id=123&lang=ar
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$templateId = isset($_GET['template_id']) ? (int)$_GET['template_id']
           : (isset($_GET['templateId']) ? (int)$_GET['templateId'] : 0);

$lang = strtolower(trim((string)($_GET['lang'] ?? 'ar')));
if ($lang !== 'ar' && $lang !== 'en') $lang = 'ar';

if ($templateId <= 0) {
  echo json_encode([
    'textModel'   => null,
    'rights'      => null,
    'legalStatus' => null,
    'templateId'  => 0,
    'lang'        => $lang,
    'source'      => null
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // Pull the whole row; we’ll pick columns in PHP.
  $st = $pdo->prepare("SELECT TOP (1) * FROM [dbo].[TableAddModel] WHERE [ID] = :id");
  $st->execute([':id' => $templateId]);
  $row = $st->fetch();

  $text = null;
  $rights = null;
  $legStat = null;

  $srcTxt = null;
  $srcRgt = null;
  $srcLeg = null;

  if ($row) {
    // Prefer language-specific TextModel if present
    $candidatesAr = ['TextModel_ar', 'TextModelAR', 'TextModelArabic', 'TextModel'];
    $candidatesEn = ['TextModel_en', 'TextModelEN', 'TextModel'];
    $candidates   = ($lang === 'ar') ? $candidatesAr : $candidatesEn;

    foreach ($candidates as $col) {
      if (array_key_exists($col, $row) && $row[$col]) {
        $text  = (string)$row[$col];
        $srcTxt = "TableAddModel.$col";
        break;
      }
    }

    // الأهلية (legal status)
    $legCols = ['الأهلية', 'Eligibility', 'LegalStatus', 'Ahliyya'];
    foreach ($legCols as $col) {
      if (array_key_exists($col, $row) && $row[$col]) {
        $legStat   = (string)$row[$col];
        $srcLeg = "TableAddModel.$col";
        break;
      }
    }

    // Rights / قائمة الحقوق
    $rightsCandidates = ['قائمة_الحقوق', 'Rights_ar', 'RightsAR', 'Rights', 'Rights_en', 'RightsEN'];
    foreach ($rightsCandidates as $col) {
      if (array_key_exists($col, $row) && $row[$col]) {
        $rights = (string)$row[$col];
        $srcRgt = "TableAddModel.$col";
        break;
      }
    }
  }

  echo json_encode([
    'textModel'   => $text,
    'rights'      => $rights,
    'legalStatus' => $legStat,
    'templateId'  => $templateId,
    'lang'        => $lang,
    'source'      => [
      'text'        => $srcTxt,
      'rights'      => $srcRgt,
      'legalStatus' => $srcLeg
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'error'       => $e->getMessage(),
    'textModel'   => null,
    'rights'      => null,
    'legalStatus' => null,
    'templateId'  => $templateId,
    'lang'        => $lang
  ], JSON_UNESCAPED_UNICODE);
}
