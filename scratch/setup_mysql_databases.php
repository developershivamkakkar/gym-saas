<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `fitcore_master` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `fitcore_shard_01` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "MYSQL_DATABASES_CREATED: Created fitcore_master & fitcore_shard_01 in MySQL Server!\n";
} catch (PDOException $e) {
    echo "MYSQL_CREATE_FAILED: " . $e->getMessage() . "\n";
}
