<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__.'/../config/db_connection.php';
    $pdo = db();

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
} catch (Throwable $e) {
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
