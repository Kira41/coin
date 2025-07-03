<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }

    $action = $data['action'] ?? '';
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $id = $data['id'] ?? null;

    if (!$userId || !$id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        exit;
    }

    if ($action === 'edit') {
        $stmt = $pdo->prepare('UPDATE wallets SET address = ?, label = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([
            $data['address'] ?? '',
            $data['label'] ?? '',
            $id,
            $userId
        ]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM wallets WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;
$stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ?');
$stmt->execute([$userId]);
$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['wallets' => $wallets]);
