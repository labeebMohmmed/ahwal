<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { json_response(['error' => 'Missing id'], 400); }

$schema = 'dbo';
$table  = 'TableAddModel';

try {
    $sql = "SELECT * FROM [$schema].[$table] WHERE [ID] = :id";
    $st = $pdo->prepare($sql);
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch();

    if (!$row) json_response(['error' => 'Not found'], 404);
    json_response(['row' => $row]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 500);
}
