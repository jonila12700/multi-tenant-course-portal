<?php

$localConfigPath = __DIR__ . '/local.php';

if (file_exists($localConfigPath)) {
    $database = require $localConfigPath;
} else {
    $database = [
        'host' => getenv('DB_HOST') ?: '',
        'dbname' => getenv('DB_NAME') ?: '',
        'username' => getenv('DB_USER') ?: '',
        'password' => getenv('DB_PASS') ?: '',
    ];
}

foreach (['host', 'dbname', 'username'] as $requiredKey) {
    if (empty($database[$requiredKey]) && $database[$requiredKey] !== '0') {
        die('Database is not configured. Copy config/local.example.php to config/local.php and update the values.');
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$database['host']};dbname={$database['dbname']};charset=utf8mb4",
        $database['username'],
        $database['password'] ?? ''
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database connection failed. Please check the database configuration.');
}
