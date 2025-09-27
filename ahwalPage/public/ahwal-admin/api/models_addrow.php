<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$values = (array)($data['values'] ?? []);

$schema = 'dbo';
$table  = 'TableAddModel';

try {
    // Discover writeable columns (exclude identity)
    $stmtCols = $pdo->prepare("
        SELECT c.name AS col, COLUMNPROPERTY(c.object_id, c.name, 'IsIdentity') AS is_identity
        FROM sys.columns c
        JOIN sys.objects o ON o.object_id = c.object_id
        JOIN sys.schemas s ON s.schema_id = o.schema_id
        WHERE s.name = :schema AND o.name = :table AND o.type = 'U'
        ORDER BY c.column_id
    ");
    $stmtCols->execute([':schema'=>$schema, ':table'=>$table]);
    $cols = $stmtCols->fetchAll() ?: [];

    $writable = [];
    foreach ($cols as $c) {
        if ((int)$c['is_identity'] !== 1) $writable[] = $c['col'];
    }

    // Intersect provided values with writable list
    $insCols = [];
    $params  = [];
    foreach ($values as $k => $v) {
        if (in_array($k, $writable, true)) {
            $insCols[] = $k;
            $params[":$k"] = $v;
        }
    }

    if ($insCols) {
        // Build INSERT with columns
        $colsSql = implode('],[', array_map(fn($c)=>str_replace(']', ']]', $c), $insCols));
        $valsSql = implode(',', array_map(fn($c)=>":$c", $insCols));
        $sql = "INSERT INTO [$schema].[$table] ([$colsSql]) VALUES ($valsSql); SELECT CAST(SCOPE_IDENTITY() AS int) AS id;";
        $st = $pdo->prepare($sql);
        $st->execute($params);
    } else {
        // No values provided — attempt DEFAULT VALUES
        $sql = "INSERT INTO [$schema].[$table] DEFAULT VALUES; SELECT CAST(SCOPE_IDENTITY() AS int) AS id;";
        $st = $pdo->query($sql);
    }

    $newId = (int)($st->fetch()['id'] ?? 0);
    if ($newId <= 0) {
        // Fallback if no identity (rare): try grabbing MAX(ID)
        $newId = (int)$pdo->query("SELECT MAX([ID]) FROM [$schema].[$table]")->fetchColumn();
    }

    // Return the new row
    $st2 = $pdo->prepare("SELECT * FROM [$schema].[$table] WHERE [ID] = :id");
    $st2->bindValue(':id', $newId, PDO::PARAM_INT);
    $st2->execute();
    $row = $st2->fetch();

    json_response(['ok'=>true, 'id'=>$newId, 'row'=>$row]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
