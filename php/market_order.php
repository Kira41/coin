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
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }

    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $pair = $data['pair'] ?? '';
    $quantity = isset($data['quantity']) ? (float)$data['quantity'] : 0.0;
    $side = strtolower($data['side'] ?? 'buy');

    if (!$userId || !$pair || $quantity <= 0 || !in_array($side, ['buy','sell'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        exit;
    }

    require_once __DIR__.'/../config/db_connection.php';
    require_once __DIR__.'/../utils/helpers.php';

    $pdo = db();

    [$base, $quote] = explode('/', strtoupper($pair));
    $price = getLivePrice($pair);
    if ($price <= 0) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch price']);
        exit;
    }

    $total = $price * $quantity;

    $pdo->beginTransaction();

    if ($side === 'buy') {
        $stmt = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();
        if ($balance === false || $balance < $total) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Solde insuffisant']);
            exit;
        }
        $pdo->prepare('UPDATE personal_data SET balance = balance - ? WHERE user_id = ?')
            ->execute([$total, $userId]);
        $newBalance = $balance - $total;
        addToWallet($pdo, $userId, $base, $quantity, $price);
        $profit = 0;
    } else {
        $stmt = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();
        $purchase = deductFromWallet($pdo, $userId, $base, $quantity, $price);
        if ($purchase === false) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Solde insuffisant']);
            exit;
        }
        $pdo->prepare('UPDATE personal_data SET balance = balance + ? WHERE user_id = ?')
            ->execute([$total, $userId]);
        $newBalance = $balance + $total;
        $profit = ($price - $purchase) * $quantity;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO trades (user_id, pair, side, quantity, price, total_value, fee, profit_loss) '
        . 'VALUES (?,?,?,?,?,?,0,?)'
    );
    $stmt->execute([$userId, $pair, $side, $quantity, $price, $total, $profit]);
    $tradeId = $pdo->lastInsertId();
    $opNum = 'T' . $tradeId;
    addHistory($pdo, $userId, $opNum, $pair, $side, $quantity, $price, 'complet', $profit);
    $pdo->commit();

    require_once __DIR__.'/../utils/poll.php';
    pushEvent('balance_updated', ['newBalance' => $newBalance], $userId);
    pushEvent('wallet_updated', [], $userId);
    pushEvent('new_trade', [
        'operation_number' => $opNum,
        'pair' => $pair,
        'side' => $side,
        'quantity' => $quantity,
        'price' => $price,
        'profit_loss' => $profit
    ], $userId);

    $actionMsg = $side === 'buy'
        ? "Achat de {$quantity} {$base} au prix du march\xC3\xA9 pour {$total} {$quote}"
        : "Vente de {$quantity} {$base} au prix du march\xC3\xA9 pour {$total} {$quote}";
    $wallets = getUserWallets($pdo, $userId);
    echo json_encode([
        'status' => 'ok',
        'message' => $actionMsg,
        'price' => $price,
        'new_balance' => $newBalance,
        'wallets' => $wallets
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
