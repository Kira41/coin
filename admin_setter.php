<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? '';

try {
    if ($action === 'create_admin') {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $isAdmin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;
        $createdBy = $data['created_by'] ?? null;
        if (!$email || !$password) {
            throw new Exception('Missing parameters');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins_agents (email, password, is_admin, created_by) VALUES (?,?,?,?)');
        $stmt->execute([$email, $hash, $isAdmin, $createdBy]);
        echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]);
    } elseif ($action === 'create_user') {
        $user = $data['user'] ?? [];
        if (!$user || !isset($user['linked_to_id']) || !isset($user['password'])) {
            throw new Exception('Missing parameters');
        }
        $password = $user['password'];
        unset($user['password']);
        $user['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
        if (!isset($user['created_at']) || $user['created_at'] === '') {
            $user['created_at'] = date('Y-m-d');
        }
        $cols = array_keys($user);
        $place = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO personal_data (' . implode(',', $cols) . ') VALUES (' . $place . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($user));
        echo json_encode(['status' => 'ok']);
    } elseif ($action === 'update_user') {
        $user = $data['user'] ?? [];
        if (!$user || !isset($user['user_id'])) {
            throw new Exception('Missing parameters');
        }
        $userId = (int)$user['user_id'];
        unset($user['user_id']);
        $cols = array_keys($user);
        if (!$cols) {
            throw new Exception('No fields to update');
        }
        $set = implode(',', array_map(fn($c) => "$c = ?", $cols));
        $sql = "UPDATE personal_data SET $set WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $values = array_values($user);
        $values[] = $userId;
        $stmt->execute($values);
        echo json_encode(['status' => 'ok']);
    } elseif ($action === 'delete_user') {
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        if (!$userId) {
            throw new Exception('Missing user_id');
        }
        $pdo->prepare('DELETE FROM personal_data WHERE user_id = ?')->execute([$userId]);
        echo json_encode(['status' => 'ok']);
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
