<?php
header('Content-type: text/plain; charset=utf-8');
// require __DIR__ . '/../config.php';
require_once __DIR__ . '/../config.php';
try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Verbindung steht.\n\n";
} catch (PDOException $e) {
    exit('Verbindung verkackt:' . $e->getMessage() . "\n");
}