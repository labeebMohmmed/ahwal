<?php
// api_groups.php?lang=ar|en
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$lang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'ar';
if ($lang !== 'ar' && $lang !== 'en') $lang = 'ar';

// Map to DB values exactly
$dbLang = ($lang === 'en') ? 'الانجليزية' : 'العربية';

try {
    $pdo = db();

    // Latest row per (altColName, altSubColName), then count subgroups per group
    $sql = "
        WITH latest AS (
            SELECT
                [ID], [altColName], [altSubColName], [Lang],
                ROW_NUMBER() OVER (
                    PARTITION BY [altColName], [altSubColName]
                    ORDER BY [ID] DESC
                ) AS rn
            FROM [dbo].[TableAddModel]
            WHERE
                [altColName] IS NOT NULL AND LTRIM(RTRIM([altColName])) <> '' AND
                [altSubColName] IS NOT NULL AND LTRIM(RTRIM([altSubColName])) <> '' AND
                LTRIM(RTRIM([Lang])) = :dbLang
        )
        SELECT [altColName] AS name, COUNT(*) AS subCount
        FROM latest
        WHERE rn = 1
        GROUP BY [altColName]
        ORDER BY [altColName];
    ";

    $st = $pdo->prepare($sql);
    $st->execute([':dbLang' => $dbLang]);
    $rows = $st->fetchAll();

    echo json_encode(['groups' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
