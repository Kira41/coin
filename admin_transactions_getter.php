<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$adminId = null;
session_start();
if (isset($_SESSION['admin_id'])) {
    $adminId = (int)$_SESSION['admin_id'];
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) &&
          preg_match('/Bearer\s+(\d+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $adminId = (int)$m[1];
}

if (!$adminId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$sql = 'SELECT t.id, t.user_id, t.type, t.amount, t.status, t.date
        FROM transactions t
        JOIN personal_data p ON t.user_id = p.user_id
        WHERE p.linked_to_id = ?
        ORDER BY STR_TO_DATE(t.date, "%Y/%m/%d") DESC, t.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute([$adminId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['transactions' => $rows]);
