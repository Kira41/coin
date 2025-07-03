<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$adminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
if (!$adminId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing admin_id']);
    exit;
}

$stmt = $pdo->prepare('SELECT is_admin FROM admins_agents WHERE id = ?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
    exit;
}

$result = [];

if ((int)$admin['is_admin'] === 1) {
    $stmt = $pdo->prepare('SELECT id,email,is_admin,created_by FROM admins_agents WHERE created_by = ?');
    $stmt->execute([$adminId]);
    $result['agents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $pdo->prepare('SELECT * FROM personal_data WHERE linked_to_id = ?');
$stmt->execute([$adminId]);
$result['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($result);
