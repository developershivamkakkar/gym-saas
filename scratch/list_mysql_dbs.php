<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $stmt = $pdo->query("SHOW DATABASES LIKE 'fitcore%'");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "======================================================\n";
    echo "       ALL FITCORE DATABASES IN MYSQL SERVER          \n";
    echo "======================================================\n";
    foreach ($dbs as $db) {
        echo "  • " . $db . "\n";
    }
    echo "======================================================\n";
} catch (PDOException $e) {
    echo "MYSQL_ERROR: " . $e->getMessage() . "\n";
}
