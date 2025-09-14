<?php
require __DIR__ . '/db.php';
// api_maingroups.php?lang=ar|en
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin', '*');

$lang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'ar';
if ($lang !== 'ar' && $lang !== 'en') $lang = 'ar';

// Map to your DB values exactly
$dbLang = ($lang === 'en') ? 'الانجليزية' : 'العربية';

try {
    $pdo = db();

    $sql = "
        WITH latest AS (
            SELECT
                [ID], [altColName], [altSubColName], [Lang], [maingroups],
                ROW_NUMBER() OVER (
                    PARTITION BY [altColName], [altSubColName]
                    ORDER BY [ID] DESC
                ) AS rn
            FROM [dbo].[TableAddModel]
            WHERE
                is_active = 1 AND LTRIM(RTRIM([Lang])) = :dbLang
                AND [altColName] IS NOT NULL AND LTRIM(RTRIM([altColName])) <> ''
                AND [altSubColName] IS NOT NULL AND LTRIM(RTRIM([altSubColName])) <> ''
        ),
        tagged AS (
            SELECT
                [altColName], [altSubColName],
                CASE
                    WHEN (COALESCE([maingroups], N'') LIKE N'%إقرار مشفوع%'
                       OR COALESCE([altColName], N'') LIKE N'%إقرار مشفوع%'
                       OR COALESCE([altSubColName], N'') LIKE N'%إقرار مشفوع%')
                    THEN N'إقرار مشفوع باليمين'

                    WHEN (COALESCE([maingroups], N'') LIKE N'%إفادة%'
                       OR COALESCE([altColName], N'') LIKE N'%إفادة%'
                       OR COALESCE([altSubColName], N'') LIKE N'%إفادة%')
                    THEN N'إفادة لمن يهمه الأمر'

                    WHEN (COALESCE([maingroups], N'') LIKE N'%توكيل%'
                       OR COALESCE([altColName], N'') LIKE N'%توكيل%'
                       OR COALESCE([altSubColName], N'') LIKE N'%توكيل%')
                    THEN N'توكيل'

                    WHEN (COALESCE([maingroups], N'') LIKE N'%مخاطبة%'
                       OR COALESCE([altColName], N'') LIKE N'%مخاطبة%'
                       OR COALESCE([altSubColName], N'') LIKE N'%مخاطبة%'
                       OR COALESCE([maingroups], N'') LIKE N'%تأشيرة%'
                       OR COALESCE([altColName], N'') LIKE N'%تأشيرة%'
                       OR COALESCE([altSubColName], N'') LIKE N'%تأشيرة%'
                       OR COALESCE([altColName], N'') LIKE N'%تاشيرة%'
                       OR COALESCE([altSubColName], N'') LIKE N'%تاشيرة%')
                    THEN N'مخاطبة لتاشيرة دخول'

                    WHEN (COALESCE([maingroups], N'') LIKE N'%إقرار%'
                       OR COALESCE([altColName], N'') LIKE N'%إقرار%'
                       OR COALESCE([altSubColName], N'') LIKE N'%إقرار%')
                    THEN N'إقرار'

                    ELSE NULL
                END AS main_group
            FROM latest
            WHERE rn = 1
        )
        SELECT main_group, COUNT(DISTINCT [altSubColName]) AS subCount
        FROM tagged
        WHERE main_group IS NOT NULL
        GROUP BY main_group;
    ";

    $st = $pdo->prepare($sql);
    $st->execute([':dbLang' => $dbLang]);
    $rows = $st->fetchAll();

    // Ensure all five groups always present
    $all = [
        'إفادة لمن يهمه الأمر' => 0,
        'إقرار' => 0,
        'إقرار مشفوع باليمين' => 0,
        'توكيل' => 0,
        'مخاطبة لتاشيرة دخول' => 0,
    ];
    foreach ($rows as $r) {
        $all[$r['main_group']] = (int)$r['subCount'];
    }

    $out = [];
    foreach ($all as $name => $count) {
        $out[] = ['name' => $name, 'subCount' => $count];
    }

    echo json_encode(['maingroups' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
