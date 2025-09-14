<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($data['id'] ?? 0);
$patch = (array)($data['patch'] ?? []);

if ($id <= 0)            { json_response(['error' => 'Missing id'], 400); }
if (!$patch)             { json_response(['error' => 'Empty patch'], 400); }

$schema = 'dbo';
$table  = 'TableAddModel';

try {
    // Discover columns & exclude identity
    $cols = $pdo->prepare("
        SELECT c.name AS col, COLUMNPROPERTY(c.object_id, c.name, 'IsIdentity') AS is_identity
        FROM sys.columns c
        JOIN sys.objects o ON o.object_id = c.object_id
        JOIN sys.schemas s ON s.schema_id = o.schema_id
        WHERE s.name = :schema AND o.name = :table AND o.type = 'U'
    ");
    $cols->execute([':schema'=>$schema, ':table'=>$table]);
    $rows = $cols->fetchAll() ?: [];
    $allowed = [];
    foreach ($rows as $r) {
        if ((int)$r['is_identity'] !== 1) $allowed[$r['col']] = true;
    }
    if (!$allowed) json_response(['error'=>'No updatable columns discovered'], 500);

    // Build SET clause from whitelist
    $set = [];
    $params = [':id' => $id];
    foreach ($patch as $k => $v) {
        if (!isset($allowed[$k])) continue; // skip non-allowed or identity
        $param = ':p_' . preg_replace('/[^A-Za-z0-9_]/', '_', $k);
        $set[] = '[' . str_replace(']', ']]', $k) . "] = $param";
        $params[$param] = $v;
    }
    if (!$set) json_response(['error'=>'Nothing to update'], 400);

    $sql = "UPDATE [$schema].[$table] SET " . implode(', ', $set) . " WHERE [ID] = :id";
    $st = $pdo->prepare($sql);
    $ok = $st->execute($params);

    // Return refreshed row
    $st2 = $pdo->prepare("SELECT * FROM [$schema].[$table] WHERE [ID] = :id");
    $st2->bindValue(':id', $id, PDO::PARAM_INT);
    $st2->execute();
    $row = $st2->fetch();

    json_response(['ok' => (bool)$ok, 'row' => $row]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
