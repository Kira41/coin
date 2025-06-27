<?php
session_start();
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/database.php';
    $pdo = db_connect();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request');
    }
    $action = $input['action'];
    switch ($action) {
        case 'add':
            $id = $input['id'] ?? uniqid();
            $stmt = $pdo->prepare('INSERT INTO wallets (id, user_id, currency, network, address, label) VALUES (?,?,?,?,?,?)');
            $stmt->execute([
                $id,
                $userId,
                $input['currency'] ?? '',
                $input['network'] ?? '',
                $input['address'] ?? '',
                $input['label'] ?? ''
            ]);
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        case 'edit':
            $stmt = $pdo->prepare('UPDATE wallets SET address = ?, label = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([
                $input['address'] ?? '',
                $input['label'] ?? '',
                $input['id'] ?? '',
                $userId
            ]);
            echo json_encode(['success' => true]);
            break;
        case 'delete':
            $stmt = $pdo->prepare('DELETE FROM wallets WHERE id = ? AND user_id = ?');
            $stmt->execute([
                $input['id'] ?? '',
                $userId
            ]);
            echo json_encode(['success' => true]);
            break;
        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
