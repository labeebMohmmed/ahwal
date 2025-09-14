<?php
require __DIR__ . '/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$lang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'ar';
if ($lang !== 'ar' && $lang !== 'en') $lang = 'ar';
$dbLang = ($lang === 'en') ? 'الانجليزية' : 'العربية';

$group  = isset($_GET['group'])  ? trim($_GET['group'])  : '';
$altcol = isset($_GET['altcol']) ? trim($_GET['altcol']) : '';

$allowedGroups = ['إفادة لمن يهمه الأمر','إقرار','إقرار مشفوع باليمين','توكيل','مخاطبة لتاشيرة دخول'];
if ($group === '' || !in_array($group, $allowedGroups, true) || $altcol === '') {
  echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = db();

  $sql = "
    WITH latest AS (
      SELECT [ID],[altColName],[altSubColName],[Lang],[maingroups],
             ROW_NUMBER() OVER (PARTITION BY [altColName],[altSubColName] ORDER BY [ID] DESC) rn
      FROM [TableAddModel]
      WHERE is_active = 1 AND LTRIM(RTRIM([Lang])) = :dbLang
        AND [altColName] IS NOT NULL AND LTRIM(RTRIM([altColName])) <> ''
        AND [altSubColName] IS NOT NULL AND LTRIM(RTRIM([altSubColName])) <> ''
    ),
    tagged AS (
      SELECT [ID],[altColName],[altSubColName],[maingroups],
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
    SELECT [altSubColName] AS name, MAX([ID]) AS id
    FROM tagged
    WHERE main_group = :mg AND [altColName] = :ac
    GROUP BY [altSubColName]
    ORDER BY [altSubColName];
  ";

  $st = $pdo->prepare($sql);
  $st->execute([':dbLang' => $dbLang, ':mg' => $group, ':ac' => $altcol]);
  $rows = $st->fetchAll();

  echo json_encode(['items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
