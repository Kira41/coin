<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
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

$sql = "SELECT t.operationNumber, t.user_id, t.type, t.amount, t.status, t.date, t.statusClass
        FROM transactions AS t
        JOIN personal_data AS p ON p.user_id = t.user_id
        WHERE p.linked_to_id = ?
        ORDER BY STR_TO_DATE(t.date, '%Y/%m/%d') DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$adminId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['transactions' => $rows]);
} catch (Throwable $e) {
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
