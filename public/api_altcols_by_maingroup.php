<?php
require __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$lang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'ar';
if ($lang !== 'ar' && $lang !== 'en') $lang = 'ar';
$dbLang = ($lang === 'en') ? 'الانجليزية' : 'العربية';

$group = isset($_GET['group']) ? trim($_GET['group']) : '';
$allowedGroups = [
  'إفادة لمن يهمه الأمر', 'إقرار', 'إقرار مشفوع باليمين', 'توكيل', 'مخاطبة لتاشيرة دخول'
];
if (!in_array($group, $allowedGroups, true)) {
  echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
  exit;
}

$dsn = "sqlsrv:Server=localhost;Database=AhwalDataBase;Encrypt=yes;TrustServerCertificate=yes";

try {
  $pdo = db();
  // 1) latest row per (altColName, altSubColName) for selected Lang
  // 2) tag each row into one of the five main groups
  // 3) filter by chosen main group
  // 4) return altColName + count of distinct altSubColName beneath it
  $sql = "
    WITH latest AS (
      SELECT [ID],[altColName],[altSubColName],[Lang],[maingroups],
             ROW_NUMBER() OVER (PARTITION BY [altColName],[altSubColName] ORDER BY [ID] DESC) rn
      FROM [dbo].[TableAddModel]
      WHERE LTRIM(RTRIM([Lang])) = :dbLang
        AND [altColName] IS NOT NULL AND LTRIM(RTRIM([altColName])) <> ''
        AND [altSubColName] IS NOT NULL AND LTRIM(RTRIM([altSubColName])) <> ''
    ),
    tagged AS (
      SELECT [altColName],[altSubColName],
             CASE
               WHEN (COALESCE([maingroups],N'') LIKE N'%إقرار مشفوع%' OR COALESCE([altColName],N'') LIKE N'%إقرار مشفوع%' OR COALESCE([altSubColName],N'') LIKE N'%إقرار مشفوع%')
                 THEN N'إقرار مشفوع باليمين'
               WHEN (COALESCE([maingroups],N'') LIKE N'%إفادة%' OR COALESCE([altColName],N'') LIKE N'%إفادة%' OR COALESCE([altSubColName],N'') LIKE N'%إفادة%')
                 THEN N'إفادة لمن يهمه الأمر'
               WHEN (COALESCE([maingroups],N'') LIKE N'%توكيل%' OR COALESCE([altColName],N'') LIKE N'%توكيل%' OR COALESCE([altSubColName],N'') LIKE N'%توكيل%')
                 THEN N'توكيل'
               WHEN (COALESCE([maingroups],N'') LIKE N'%مخاطبة%' OR COALESCE([altColName],N'') LIKE N'%مخاطبة%' OR COALESCE([altSubColName],N'') LIKE N'%مخاطبة%'
                     OR COALESCE([maingroups],N'') LIKE N'%تأشيرة%' OR COALESCE([altColName],N'') LIKE N'%تأشيرة%' OR COALESCE([altSubColName],N'') LIKE N'%تأشيرة%'
                     OR COALESCE([altColName],N'') LIKE N'%تاشيرة%' OR COALESCE([altSubColName],N'') LIKE N'%تاشيرة%')
                 THEN N'مخاطبة لتاشيرة دخول'
               WHEN (COALESCE([maingroups],N'') LIKE N'%إقرار%' OR COALESCE([altColName],N'') LIKE N'%إقرار%' OR COALESCE([altSubColName],N'') LIKE N'%إقرار%')
                 THEN N'إقرار'
               ELSE NULL
             END AS main_group
      FROM latest
      WHERE rn = 1
    )
    SELECT [altColName] AS name, COUNT(DISTINCT [altSubColName]) AS subCount
    FROM tagged
    WHERE main_group = :mg
    GROUP BY [altColName]
    ORDER BY [altColName];
  ";

  $st = $pdo->prepare($sql);
  $st->execute([':dbLang' => $dbLang, ':mg' => $group]);
  $rows = $st->fetchAll();

  echo json_encode(['items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
