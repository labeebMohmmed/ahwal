<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Select all users
$stmt = $pdo->query("SELECT id, UserName, كلمة_المرور FROM TableUser");
$users = $stmt->fetchAll();

$updated = 0;
$skipped = 0;

foreach ($users as $user) {
    $id = $user['id'];
    $username = $user['UserName'];
    $dbPass = $user['كلمة_المرور'];

    if (empty($dbPass)) {
        // No password set
        $skipped++;
        echo "SKIP (empty) → User: {$username}\n";
        continue;
    }

    // Detect if already hashed → bcrypt hashes always start with $2y$ or $2a$
    if (preg_match('/^\$2y\$/', $dbPass) || preg_match('/^\$2a\$/', $dbPass)) {
        $skipped++;
        echo "SKIP (already hashed) → User: {$username}\n";
        continue;
    }

    // Otherwise → treat as plain text, hash it
    $newHash = password_hash($dbPass, PASSWORD_DEFAULT);

    $upd = $pdo->prepare("UPDATE TableUser SET كلمة_المرور = ? WHERE id = ?");
    $upd->execute([$newHash, $id]);

    $updated++;
    echo "UPDATED → User: {$username}\n";
}

echo "Done. Updated: {$updated}, Skipped: {$skipped}\n";
