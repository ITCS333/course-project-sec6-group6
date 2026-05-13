<?php
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host   = getenv('DB_HOST')     ?: 'localhost';
        $dbname = getenv('DB_NAME')     ?: 'course';
        $user   = getenv('DB_USER')     ?: 'root';
        $pass   = getenv('DB_PASSWORD') ?: '';

        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}
