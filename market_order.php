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
        // Binance pairs use USDT instead of USD
        $symbol = str_replace('USD', 'USDT', $symbol);
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

    function deductFromWallet(PDO $pdo, int $userId, string $currency, float $amount): bool {
        $stmt = $pdo->prepare('SELECT amount FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
        $stmt->execute([$userId, strtolower($currency)]);
        $bal = $stmt->fetchColumn();
        if ($bal === false || $bal < $amount) {
            return false;
        }
        $stmt = $pdo->prepare('UPDATE wallets SET amount = amount - ? WHERE user_id=? AND currency=?');
        $stmt->execute([$amount, $userId, strtolower($currency)]);
        return true;
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

    if ($side === 'buy') {
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
        $newBalance = $balance - $total;
        addToWallet($pdo, $userId, $base, $quantity);
    } else { // sell
        if (!deductFromWallet($pdo, $userId, $base, $quantity)) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Solde insuffisant dans le wallet']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();
        $stmt = $pdo->prepare('UPDATE personal_data SET balance = balance + ? WHERE user_id = ?');
        $stmt->execute([$total, $userId]);
        $newBalance = $balance + $total;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO trades (user_id, pair, side, quantity, price, total_value, fee, profit_loss) '
        . 'VALUES (?,?,?,?,?,?,0,0)'
    );
    $stmt->execute([$userId, $pair, $side, $quantity, $price, $total]);

    $pdo->commit();
    $msg = ($side === 'buy')
        ? "تم شراء {$quantity} {$base} بسعر السوق مقابل {$total} {$quote}"
        : "تم بيع {$quantity} {$base} بسعر السوق مقابل {$total} {$quote}";
    echo json_encode([
        'status' => 'ok',
        'message' => $msg,
        'price' => $price,
        'new_balance' => $newBalance
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log(__FILE__ . ' - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
