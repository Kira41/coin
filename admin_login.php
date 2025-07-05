<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing credentials']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, password FROM admins_agents WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Passwords are stored as MD5 hashes. The client sends the already hashed value.
if ($row && hash_equals($row['password'], $password)) {
    session_start();
    $_SESSION['admin_id'] = $row['id'];
    echo json_encode(['status' => 'ok', 'admin_id' => $row['id']]);
    exit;
}

http_response_code(401);
echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);

