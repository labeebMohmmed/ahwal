<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';
$year = $_GET['year'] ?? null;

try {
    switch ($type) {
        case 'daily':
            // Distinct dates across both tables
            $sql = "
                SELECT DISTINCT CONVERT(varchar(10), TRY_CAST(التاريخ_الميلادي AS DATE), 120) AS d
                FROM (
                  SELECT التاريخ_الميلادي FROM dbo.TableCollection
                  UNION ALL
                  SELECT التاريخ_الميلادي FROM dbo.TableAuth
                ) X
                WHERE TRY_CAST(التاريخ_الميلادي AS DATE) IS NOT NULL
                ORDER BY d DESC
            ";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'ok' => true,
                'dates' => $rows,
                'default' => date('Y-m-d')
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'monthly':
            $years = getAvailableYears($pdo);

            // Arabic month names
            $monthNames = [
                1=>"يناير", 2=>"فبراير", 3=>"مارس", 4=>"أبريل", 5=>"مايو", 6=>"يونيو",
                7=>"يوليو", 8=>"أغسطس", 9=>"سبتمبر", 10=>"أكتوبر", 11=>"نوفمبر", 12=>"ديسمبر"
            ];

            $months = [];
            if ($year) {
                // Only months with records in that year
                $sqlMonths = "
                    SELECT DISTINCT MONTH(TRY_CAST(التاريخ_الميلادي AS DATE)) AS m
                    FROM (
                      SELECT التاريخ_الميلادي FROM dbo.TableCollection
                      UNION ALL
                      SELECT التاريخ_الميلادي FROM dbo.TableAuth
                    ) X
                    WHERE TRY_CAST(التاريخ_الميلادي AS DATE) IS NOT NULL
                      AND YEAR(TRY_CAST(التاريخ_الميلادي AS DATE)) = :y
                    ORDER BY m
                ";
                $st = $pdo->prepare($sqlMonths);
                $st->execute([':y'=>$year]);
                $months = $st->fetchAll(PDO::FETCH_COLUMN);
            }

            echo json_encode([
                'ok' => true,
                'years' => $years,
                'defaultYear' => $year ?: date('Y'),
                'months' => array_map(fn($m)=>[
                    'val'=>$m,
                    'label'=>$monthNames[(int)$m] ?? (string)$m
                ], $months)
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'quarterly':
            $years = getAvailableYears($pdo);
            echo json_encode([
                'ok' => true,
                'years' => $years,
                'defaultYear' => date('Y'),
                'quarters' => [
                    ['val' => 1, 'label' => 'الربع الأول'],
                    ['val' => 2, 'label' => 'الربع الثاني'],
                    ['val' => 3, 'label' => 'الربع الثالث'],
                    ['val' => 4, 'label' => 'الربع الرابع'],
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'biannually':
            $years = getAvailableYears($pdo);
            echo json_encode([
                'ok' => true,
                'years' => $years,
                'defaultYear' => date('Y'),
                'halves' => [
                    ['val' => 1, 'label' => 'النصف الأول'],
                    ['val' => 2, 'label' => 'النصف الثاني']
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'yearly':
            $years = getAvailableYears($pdo);
            echo json_encode([
                'ok' => true,
                'years' => $years,
                'defaultYear' => date('Y')
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown type']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Helper: get available years from both tables
 */
function getAvailableYears(PDO $pdo): array {
    $sql = "
        SELECT DISTINCT YEAR(TRY_CAST(التاريخ_الميلادي AS DATE)) AS y
        FROM (
          SELECT التاريخ_الميلادي FROM dbo.TableCollection
          UNION ALL
          SELECT التاريخ_الميلادي FROM dbo.TableAuth
        ) X
        WHERE TRY_CAST(التاريخ_الميلادي AS DATE) IS NOT NULL
        ORDER BY y DESC
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}
