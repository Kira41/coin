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

    function addToWallet(PDO $pdo, int $userId, string $currency, float $amount, float $price): void {
        $currency = strtolower($currency);
        $stmt = $pdo->prepare('SELECT amount,purchase_price FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
        $stmt->execute([$userId, $currency]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $newAmount = $row['amount'] + $amount;
            $avg = ($row['amount'] * $row['purchase_price'] + $amount * $price) / $newAmount;
            $pdo->prepare('UPDATE wallets SET amount=?, purchase_price=? WHERE user_id=? AND currency=?')
                ->execute([$newAmount, $avg, $userId, $currency]);
        } else {
            $pdo->prepare('INSERT INTO wallets (user_id,currency,amount,address,label,purchase_price) VALUES (?,?,?,?,?,?)')
                ->execute([$userId, $currency, $amount, 'local address', $currency, $price]);
        }
    }

    function deductFromWallet(PDO $pdo, int $userId, string $currency, float $amount) {
        $currency = strtolower($currency);
        $stmt = $pdo->prepare('SELECT amount,purchase_price FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
        $stmt->execute([$userId, $currency]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['amount'] < $amount) {
            return false;
        }
        $newAmt = $row['amount'] - $amount;
        if ($newAmt > 0) {
            $pdo->prepare('UPDATE wallets SET amount=? WHERE user_id=? AND currency=?')
                ->execute([$newAmt, $userId, $currency]);
        } else {
            $pdo->prepare('DELETE FROM wallets WHERE user_id=? AND currency=?')
                ->execute([$userId, $currency]);
        }
        return (float)$row['purchase_price'];
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
        $purchase = deductFromWallet($pdo, $userId, $base, $quantity);
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
    $pdo->commit();
    $actionMsg = $side === 'buy'
        ? "Achat de {$quantity} {$base} au prix du march\xC3\xA9 pour {$total} {$quote}"
        : "Vente de {$quantity} {$base} au prix du march\xC3\xA9 pour {$total} {$quote}";
    echo json_encode([
        'status' => 'ok',
        'message' => $actionMsg,
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
