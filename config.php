<?php
$dsn = getenv('DB_DSN') ?: 'sqlite:coin_db.db';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';
?>
