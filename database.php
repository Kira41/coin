<?php
require_once __DIR__ . '/config.php';

/**
 * Create and return a PDO connection using the configuration values.
 *
 * @return PDO
 */
function db_connect(): PDO {
    $dsn = "mysql:host={$GLOBALS['dbHost']};dbname={$GLOBALS['dbName']};charset=utf8mb4";
    $pdo = new PDO($dsn, $GLOBALS['dbUser'], $GLOBALS['dbPass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
