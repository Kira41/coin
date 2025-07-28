<?php
header('Content-Type: application/json');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'JSON invalide']);
        exit;
    }

    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $pair = $data['pair'] ?? '';
    $quantity = isset($data['quantity']) ? (float)$data['quantity'] : 0.0;
    $side = strtolower($data['side'] ?? 'buy');
    $target = isset($data['target_price']) ? (float)$data['target_price'] : 0.0;

    if (!$userId || !$pair || $quantity <= 0 || $target <= 0 || !in_array($side, ['buy','sell'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants']);
        exit;
    }

    $dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    [$base, $quote] = explode('/', strtoupper($pair));

    if ($side === 'buy') {
        $total = $target * $quantity;
        $stmt = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();
        if ($balance === false || $balance < $total) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Solde insuffisant']);
            exit;
        }
    } else { // sell
        $stmt = $pdo->prepare('SELECT amount FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
        $stmt->execute([$userId, $base]);
        $bal = $stmt->fetchColumn();
        $pending = $pdo->prepare("SELECT SUM(quantity) FROM orders WHERE user_id=? AND side='sell' AND status='open' AND pair LIKE ?");
        $pending->execute([$userId, $base.'/%']);
        $reserved = (float)$pending->fetchColumn();
        $available = $bal !== false ? $bal - $reserved : -1;
        if ($available < $quantity) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Solde insuffisant']);
            exit;
        }
    }
    $stmt = $pdo->prepare("INSERT INTO orders (user_id,pair,type,side,quantity,target_price,status) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$userId, $pair, 'limit', $side, $quantity, $target, 'open']);

    $orderId = $pdo->lastInsertId();
    $opNum = 'T' . $orderId;
    $date = date('Y/m/d');
    $adm = $pdo->prepare('SELECT linked_to_id FROM personal_data WHERE user_id=?');
    $adm->execute([$userId]);
    $adminId = $adm->fetchColumn() ?: null;
    $pdo->prepare(
        'INSERT INTO transactions (user_id,admin_id,operationNumber,type,amount,date,status,statusClass) '
        . 'VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $userId,
        $adminId,
        $opNum,
        'Trading',
        $target * $quantity,
        $date,
        'En cours',
        'bg-warning'
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => "Ordre limite de {$quantity} {$base} au prix de {$target} {$quote} créé",
        'order_id' => $orderId
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Une erreur est survenue: ' . $e->getMessage()]);
}
?>
