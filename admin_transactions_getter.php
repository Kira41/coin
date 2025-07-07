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

$sql = "(
        SELECT t.operationNumber, t.user_id, t.type, t.amount, t.status, t.date, t.statusClass
        FROM transactions t
        WHERE t.admin_id = ?
    )
    UNION ALL
    (
        SELECT d.operationNumber, d.user_id, 'Dépôt' AS type, d.amount, d.status, d.date, d.statusClass
        FROM deposits d
        WHERE d.admin_id = ?
    )
    UNION ALL
    (
        SELECT r.operationNumber, r.user_id, 'Retrait' AS type, r.amount, r.status, r.date, r.statusClass
        FROM retraits r
        WHERE r.admin_id = ?
    )
    ORDER BY STR_TO_DATE(date, '%Y/%m/%d') DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$adminId, $adminId, $adminId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['transactions' => $rows]);
