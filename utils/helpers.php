<?php
function getLivePrice(string $pair): float {
    $symbol = str_replace('/', '', strtoupper($pair));
    $symbol = str_replace('USD', 'USDT', $symbol);
    $url = 'https://api.binance.com/api/v3/ticker/price?symbol=' . $symbol;
    $data = @json_decode(file_get_contents($url), true);
    return isset($data['price']) ? (float)$data['price'] : 0.0;
}

function addToWallet(PDO $pdo, int $uid, string $cur, float $amt, float $price): void {
    $cur = strtolower($cur);
    $st = $pdo->prepare('SELECT amount,purchase_price FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
    $st->execute([$uid, $cur]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $new = $row['amount'] + $amt;
        $avg = ($row['amount'] * $row['purchase_price'] + $amt * $price) / $new;
        $pdo->prepare('UPDATE wallets SET amount=?, purchase_price=? WHERE user_id=? AND currency=?')
            ->execute([$new, $avg, $uid, $cur]);
    } else {
        $pdo->prepare('INSERT INTO wallets (user_id,currency,amount,address,label,purchase_price) VALUES (?,?,?,?,?,?)')
            ->execute([$uid, $cur, $amt, 'local address', strtoupper($cur), $price]);
    }
}

function deductFromWallet(PDO $pdo, int $uid, string $cur, float $amt) {
    $cur = strtolower($cur);
    $st = $pdo->prepare('SELECT amount,purchase_price FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
    $st->execute([$uid, $cur]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['amount'] < $amt) {
        return false;
    }
    $new = $row['amount'] - $amt;
    if ($new > 0) {
        $pdo->prepare('UPDATE wallets SET amount=? WHERE user_id=? AND currency=?')->execute([$new, $uid, $cur]);
    } else {
        $pdo->prepare('DELETE FROM wallets WHERE user_id=? AND currency=?')->execute([$uid, $cur]);
    }
    return (float)$row['purchase_price'];
}

function executeTrade(PDO $pdo, array $order, float $price) {
    [$base] = explode('/', strtoupper($order['pair']));
    $total = $price * $order['quantity'];
    if ($order['side'] === 'buy') {
        $st = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
        $st->execute([$order['user_id']]);
        $bal = $st->fetchColumn();
        if ($bal === false || $bal < $total) return ['ok' => false, 'msg' => 'Solde insuffisant'];
        $pdo->prepare('UPDATE personal_data SET balance=balance-? WHERE user_id=?')->execute([$total, $order['user_id']]);
        addToWallet($pdo, $order['user_id'], $base, $order['quantity'], $price);
        $newBal = $bal - $total;
        $profit = 0;
    } else {
        $st = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
        $st->execute([$order['user_id']]);
        $bal = $st->fetchColumn();
        $purchase = deductFromWallet($pdo, $order['user_id'], $base, $order['quantity']);
        if ($purchase === false) return ['ok' => false, 'msg' => 'Solde insuffisant'];
        $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$total, $order['user_id']]);
        $newBal = $bal + $total;
        $profit = ($price - $purchase) * $order['quantity'];
    }
    $stmt = $pdo->prepare('INSERT INTO trades (user_id,order_id,pair,side,quantity,price,total_value,fee,profit_loss) VALUES (?,?,?,?,?,?,?,0,?)');
    $stmt->execute([$order['user_id'], $order['id'], $order['pair'], $order['side'], $order['quantity'], $price, $total, $profit]);
    $pdo->prepare('UPDATE orders SET status="filled",price_at_execution=?,executed_at=NOW() WHERE id=?')->execute([$price, $order['id']]);
    if (!empty($order['related_order_id'])) {
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status IN ('open','triggered')")->execute([$order['related_order_id']]);
    }
    return ['ok' => true, 'balance' => $newBal, 'price' => $price];
}
?>
