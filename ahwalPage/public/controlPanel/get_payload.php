<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$id = $_GET['id'] ?? 0;
$table = $_GET['table'] === 'auth' ? 'TableAuth' : 'TableCollection';

$stmt = $pdo->prepare("SELECT PayloadJson FROM [dbo].[$table] WHERE ID=?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(["ok"=>false, "error"=>"Not found"]);
    exit;
}
echo $row['PayloadJson'];
