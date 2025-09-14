<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db.php';
$pdo= db();

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($data['id'] ?? 0);
if ($id <= 0) { json_response(['error' => 'Missing id'], 400); }

$schema = 'dbo';
$table  = 'TableAddModel';

try {
    $st = $pdo->prepare("DELETE FROM [$schema].[$table] WHERE [ID] = :id");
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    $affected = $st->rowCount();

    if ($affected < 1) json_response(['error'=>'Not found or not deleted'], 404);
    json_response(['ok' => true, 'deletedId' => $id]);
} catch (Throwable $e) {
    // Often FK constraint — report cleanly
    json_response(['error' => $e->getMessage()], 500);
}
