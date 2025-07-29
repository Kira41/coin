<?php
function getLivePrice(string $pair): float {
    $symbol = str_replace('/', '', strtoupper($pair));
    if (!preg_match('/USDT$/', $symbol) && preg_match('/USD$/', $symbol)) {
        $symbol = substr($symbol, 0, -3) . 'USDT';
    }
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
        $pdo->prepare('UPDATE wallets SET amount=?, purchase_price=?, usd_value=? WHERE user_id=? AND currency=?')
            ->execute([$new, $avg, $new * $price, $uid, $cur]);
    } else {
        $pdo->prepare('INSERT INTO wallets (user_id,currency,amount,address,label,purchase_price,usd_value) VALUES (?,?,?,?,?,?,?)')
            ->execute([$uid, $cur, $amt, 'local address', strtoupper($cur), $price, $amt * $price]);
    }
}

function deductFromWallet(PDO $pdo, int $uid, string $cur, float $amt, float $price) {
    $cur = strtolower($cur);
    $st = $pdo->prepare('SELECT amount,purchase_price FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
    $st->execute([$uid, $cur]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['amount'] < $amt) {
        return false;
    }
    $new = $row['amount'] - $amt;
    if ($new > 0) {
        $pdo->prepare('UPDATE wallets SET amount=?, usd_value=? WHERE user_id=? AND currency=?')
            ->execute([$new, $new * $price, $uid, $cur]);
    } else {
        $pdo->prepare('DELETE FROM wallets WHERE user_id=? AND currency=?')->execute([$uid, $cur]);
    }
    return (float)$row['purchase_price'];
}

function getUserWallets(PDO $pdo, int $uid): array {
    $st = $pdo->prepare('SELECT * FROM wallets WHERE user_id=?');
    $st->execute([$uid]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function addHistory(PDO $pdo, int $uid, string $opNum, string $pair, string $side,
    float $qty, float $price, string $status, ?float $profit = null): void {
    $typeTxt = $side === 'buy' ? 'Acheter' : 'Vendre';
    $typeClass = $side === 'buy' ? 'bg-success' : 'bg-danger';
    $statutClass = $status === 'complet' ? 'bg-success'
        : ($status === 'annule' ? 'bg-danger' : 'bg-warning');
    $profitClass = $profit === null ? '' : ($profit >= 0 ? 'text-success' : 'text-danger');
    $details = json_encode(['order_id' => ltrim($opNum, 'T')]);
    $stmt = $pdo->prepare('INSERT INTO tradingHistory '
        . '(user_id, operationNumber, temps, paireDevises, type, statutTypeClass,'
        . ' montant, prix, statut, statutClass, profitPerte, profitClass, details) '
        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        . ' ON DUPLICATE KEY UPDATE statut=VALUES(statut), statutClass=VALUES(statutClass),'
        . ' prix=VALUES(prix), profitPerte=VALUES(profitPerte), profitClass=VALUES(profitClass),'
        . ' details=VALUES(details)');
    $stmt->execute([
        $uid,
        $opNum,
        date('Y/m/d H:i'),
        $pair,
        $typeTxt,
        $typeClass,
        $qty,
        $price,
        $status,
        $statutClass,
        $profit,
        $profitClass,
        $details
    ]);
}

function executeTrade(PDO $pdo, array $order, float $price) {
    [$base] = explode('/', strtoupper($order['pair']));

    // Avoid executing the same order twice
    if (!empty($order['id'])) {
        $check = $pdo->prepare('SELECT 1 FROM trades WHERE order_id = ?');
        $check->execute([$order['id']]);
        if ($check->fetchColumn()) {
            return ['ok' => false, 'msg' => 'Order already filled'];
        }
    } else {
        $dup = $pdo->prepare(
            'SELECT price, created_at FROM trades
             WHERE user_id=? AND pair=? AND side=? AND quantity=?
             ORDER BY id DESC LIMIT 1'
        );
        $dup->execute([
            $order['user_id'],
            $order['pair'],
            $order['side'],
            $order['quantity']
        ]);
        $ex = $dup->fetch(PDO::FETCH_ASSOC);
        if ($ex && bccomp((string)$ex['price'], (string)$price, 8) === 0 &&
            strtotime($ex['created_at']) >= time() - 5) {
            return ['ok' => false, 'msg' => 'Trade already recorded'];
        }
    }
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
        $purchase = deductFromWallet($pdo, $order['user_id'], $base, $order['quantity'], $price);
        if ($purchase === false) return ['ok' => false, 'msg' => 'Solde insuffisant'];
        $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$total, $order['user_id']]);
        $newBal = $bal + $total;
        $profit = ($price - $purchase) * $order['quantity'];
    }
    $stmt = $pdo->prepare('INSERT INTO trades (user_id,order_id,pair,side,quantity,price,total_value,fee,profit_loss) VALUES (?,?,?,?,?,?,?,0,?)');
    $stmt->execute([$order['user_id'], $order['id'], $order['pair'], $order['side'], $order['quantity'], $price, $total, $profit]);
    $tradeId = $pdo->lastInsertId();
    $pdo->prepare('UPDATE orders SET status="filled",price_at_execution=?,executed_at=NOW() WHERE id=?')->execute([$price, $order['id']]);
    $opNum = 'T' . ($order['id'] ?: $tradeId);
    addHistory($pdo, $order['user_id'], $opNum, $order['pair'], $order['side'], $order['quantity'], $price, 'complet', $profit);
    if (!empty($order['related_order_id'])) {
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status IN ('open','triggered')")->execute([$order['related_order_id']]);
    }
    return [
        'ok' => true,
        'balance' => $newBal,
        'price' => $price,
        'profit' => $profit,
        'operation' => $opNum
    ];
}
?>
