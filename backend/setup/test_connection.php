<?php
try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;charset=utf8mb4',
        'root',
        'SAInithin@24',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "DB Connection: OK" . PHP_EOL;
    echo "MySQL Version: " . $pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
