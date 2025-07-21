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

    $dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    function getLivePrice(string $pair): float {
        $symbol = str_replace('/', '', strtoupper($pair));
        $url = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
        $data = @json_decode(file_get_contents($url), true);
        return isset($data['price']) ? (float)$data['price'] : 0.0;
    }

    function addToWallet(PDO $pdo, int $userId, string $currency, float $amount): void {
        $stmt = $pdo->prepare(
            'INSERT INTO wallets (user_id,currency,amount,address,label) '
            . 'VALUES (?,?,?,?,?) '
            . 'ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)'
        );
        $stmt->execute([$userId, strtolower($currency), $amount, 'local address', $currency]);
    }

    [$base, $quote] = explode('/', strtoupper($pair));
    $price = getLivePrice($pair);
    if ($price <= 0) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch price']);
        exit;
    }

    $total = $price * $quantity;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    $balance = $stmt->fetchColumn();
    if ($balance === false || $balance < $total) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'رصيد غير كافٍ']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE personal_data SET balance = balance - ? WHERE user_id = ?');
    $stmt->execute([$total, $userId]);

    addToWallet($pdo, $userId, $base, $quantity);

    $stmt = $pdo->prepare(
        'INSERT INTO trades (user_id, pair, side, quantity, price, total_value, fee, profit_loss) '
        . 'VALUES (?,?,?,?,?,?,0,0)'
    );
    $stmt->execute([$userId, $pair, $side, $quantity, $price, $total]);

    $pdo->commit();
    echo json_encode([
        'status' => 'ok',
        'message' => "تم شراء {$quantity} {$base} بسعر السوق مقابل {$total} {$quote}",
        'price' => $price
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
