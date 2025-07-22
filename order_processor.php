<?php
// Basic order processing script using orders, wallets and trades tables
// Run periodically (e.g. via cron) to execute open orders based on market prices

$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

/**
 * Fetch live price for a trading pair using Binance.
 */
function getLivePrice(string $pair): float {
    $symbol = str_replace('/', '', strtoupper($pair));
    // Binance symbols use USDT instead of USD
    $symbol = str_replace('USD', 'USDT', $symbol);
    $url = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
    $data = @json_decode(file_get_contents($url), true);
    return isset($data['price']) ? (float)$data['price'] : 0.0;
}

/**
 * Increase amount of a currency in user's wallet or create a new row.
 */
function addToWallet(PDO $pdo, int $userId, string $currency, float $amount): void {
    $stmt = $pdo->prepare(
        'INSERT INTO wallets (user_id,currency,amount,address,label)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)'
    );
    $stmt->execute([$userId, $currency, $amount, 'local address', $currency]);
}

/**
 * Decrease amount of a currency in user's wallet.
 */
function deductFromWallet(PDO $pdo, int $userId, string $currency, float $amount): bool {
    $stmt = $pdo->prepare('SELECT amount FROM wallets WHERE user_id=? AND currency=?');
    $stmt->execute([$userId, $currency]);
    $bal = $stmt->fetchColumn();
    if ($bal === false || $bal < $amount) {
        return false; // insufficient funds
    }
    $stmt = $pdo->prepare('UPDATE wallets SET amount = amount - ? WHERE user_id=? AND currency=?');
    $stmt->execute([$amount, $userId, $currency]);
    return true;
}

/**
 * Record executed trade and mark order filled.
 */
function recordTrade(PDO $pdo, array $order, float $price): void {
    $total = $order['quantity'] * $price;
    $stmt = $pdo->prepare(
        'INSERT INTO trades (user_id,order_id,pair,side,quantity,price,total_value,profit_loss)
         VALUES (?,?,?,?,?,?,?,0)'
    );
    $stmt->execute([
        $order['user_id'],
        $order['id'],
        $order['pair'],
        $order['side'],
        $order['quantity'],
        $price,
        $total
    ]);
    $pdo->prepare('UPDATE orders SET status="filled" WHERE id=?')->execute([$order['id']]);
}

/**
 * Execute a market trade updating wallets and recording the trade.
 */
function executeOrder(PDO $pdo, array $order, float $price): void {
    [$base, $quote] = explode('/', strtoupper($order['pair']));
    $qty = (float)$order['quantity'];
    $total = $price * $qty;

    if ($order['side'] === 'buy') {
        if (!deductFromWallet($pdo, $order['user_id'], $quote, $total)) {
            // insufficient balance, cancel order
            $pdo->prepare('UPDATE orders SET status="cancelled" WHERE id=?')->execute([$order['id']]);
            return;
        }
        addToWallet($pdo, $order['user_id'], $base, $qty);
    } else { // sell
        if (!deductFromWallet($pdo, $order['user_id'], $base, $qty)) {
            $pdo->prepare('UPDATE orders SET status="cancelled" WHERE id=?')->execute([$order['id']]);
            return;
        }
        addToWallet($pdo, $order['user_id'], $quote, $total);
    }
    recordTrade($pdo, $order, $price);
}

/**
 * Determine if an order should execute at the given price.
 */
function shouldExecute(array &$order, float $price): bool {
    switch ($order['type']) {
        case 'market':
            return true;
        case 'limit':
            if ($order['side'] === 'buy') return $price <= (float)$order['target_price'];
            return $price >= (float)$order['target_price'];
        case 'stop':
            if ($order['side'] === 'buy') return $price >= (float)$order['stop_price'];
            return $price <= (float)$order['stop_price'];
        case 'stop_limit':
            if ($order['side'] === 'buy') {
                return $price >= (float)$order['stop_price'] && $price <= (float)$order['target_price'];
            }
            return $price <= (float)$order['stop_price'] && $price >= (float)$order['target_price'];
        case 'trailing_stop':
            $trail = (float)$order['target_price']; // percentage
            $highest = (float)$order['stop_price'];
            if ($price > $highest) {
                $highest = $price;
                // store new highest price
                $order['stop_price'] = $highest;
            }
            $trigger = $highest * (1 - $trail/100);
            return $price <= $trigger;
        default:
            return false;
    }
}

// main loop for cron usage
$orders = $pdo->query("SELECT * FROM orders WHERE status='open'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $o) {
    $current = getLivePrice($o['pair']);
    if ($current <= 0) continue;
    if (shouldExecute($o, $current)) {
        // update highest price for trailing stop before executing
        if ($o['type'] === 'trailing_stop') {
            $pdo->prepare('UPDATE orders SET stop_price=? WHERE id=?')->execute([$o['stop_price'], $o['id']]);
        }
        executeOrder($pdo, $o, $current);
    } elseif ($o['type'] === 'trailing_stop') {
        // persist highest price change even if not executed
        $pdo->prepare('UPDATE orders SET stop_price=? WHERE id=?')->execute([$o['stop_price'], $o['id']]);
    }
}

