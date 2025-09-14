<?php
// db.php — shared PDO connection + helpers (PHP 8.2)


declare(strict_types=1);


ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');


function db(): PDO {
static $pdo = null;
if ($pdo) return $pdo;

$dsn = "sqlsrv:Server=localhost;Database=AhwalDataBase;Encrypt=yes;TrustServerCertificate=yes";

$pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);


return $pdo;
}


function json_input(): array {
$raw = file_get_contents('php://input');
if ($raw === '' || $raw === false) return [];
$data = json_decode($raw, true);
return is_array($data) ? $data : [];
}


function respond($ok, $data = null, $status = 200): void {
http_response_code($status);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => $ok, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
}


function require_admin(): void {
// TODO: Replace with your real auth/session check.
// Example: if (!($_SESSION['is_admin'] ?? false)) respond(false, 'Unauthorized', 401);
}


function fetch_columns(string $table): array {
$sql = "SELECT c.name AS name, t.name AS type_name, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON c.user_type_id = t.user_type_id
WHERE c.object_id = OBJECT_ID(?) ORDER BY c.column_id";
$st = db()->prepare($sql);
$st->execute([$table]);
return $st->fetchAll();
}


function has_column(string $table, string $col): bool {
$st = db()->prepare("SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(?) AND name = ?");
$st->execute([$table, $col]);
return (bool)$st->fetchColumn();
}


function quote_ident(string $name): string {
// Basic identifier quoting; caller must validate name first
return '[' . str_replace(']', ']]', $name) . ']';
}