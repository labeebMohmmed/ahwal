<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
session_start([
    'cookie_lifetime' => 0,         // session ends when browser closes
    'cookie_httponly' => true,      // prevent JS access
    'cookie_secure'  => isset($_SERVER['HTTPS']), // only HTTPS
    'use_strict_mode' => true,      
]);

// DB connection (PDO recommended)
$pdo = db();
