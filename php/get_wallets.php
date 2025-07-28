<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__.'/../config/db_connection.php';
    $pdo = db();

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
        $stmt = $pdo->prepare('UPDATE wallets SET address = ?, label = ?, network = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([
            $data['address'] ?? '',
            $data['label'] ?? '',
            $data['network'] ?? '',
            $id,
            $userId
        ]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM wallets WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    echo json_encode(['status' => 'ok']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;
$stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ?');
$stmt->execute([$userId]);
$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['wallets' => $wallets]);
} catch (Throwable $e) {
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
