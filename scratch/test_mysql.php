<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    echo "MYSQL_SUCCESS: Connected to MySQL on port 3306!\n";
} catch (PDOException $e) {
    echo "MYSQL_FAILED: " . $e->getMessage() . "\n";
}
