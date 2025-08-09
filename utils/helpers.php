<?php
function getLivePrice(string $pair): float {
    $symbol = str_replace('/', '', strtoupper($pair));
    if (!preg_match('/USDT$/', $symbol) && preg_match('/USD$/', $symbol)) {
        $symbol = substr($symbol, 0, -3) . 'USDT';
    }
    $url = 'https://api.binance.com/api/v3/ticker/price?symbol=' . $symbol;
    $context = stream_context_create(['http' => ['timeout' => 3]]);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return 0.0;
    }
    $data = json_decode($json, true);
    return isset($data['price']) ? (float)$data['price'] : 0.0;
}

/**
 * Fetch the historical closing price for a currency pair from CryptoCompare.
 * The timestamp should be a Unix epoch (seconds).
 * Returns 0 on failure.
 */
function getHistoricalPrice(string $pair, int $timestamp): float {
    [$base, $quote] = explode('/', strtoupper($pair));
    // CryptoCompare expects USD rather than USDT
    if ($quote === 'USDT') {
        $quote = 'USD';
    }
    $url = sprintf(
        'https://min-api.cryptocompare.com/data/pricehistorical?fsym=%s&tsyms=%s&ts=%d',
        urlencode($base), urlencode($quote), $timestamp
    );
    $json = @file_get_contents($url);
    if ($json === false) return 0.0;
    $data = json_decode($json, true);
    return isset($data[$base][$quote]) ? (float)$data[$base][$quote] : 0.0;
}

function addHistory(PDO $pdo, int $uid, string $opNum, string $pair, string $side,
    float $qty, float $price, string $status, ?float $profit = null): void {
    $typeTxt = $side === 'buy' ? 'Acheter' : 'Vendre';
    $typeClass = $side === 'buy' ? 'bg-success' : 'bg-danger';
    $statutClass = $status === 'complet' ? 'bg-success'
        : ($status === 'annule' ? 'bg-danger' : 'bg-warning');
    $profitClass = $profit === null ? '' : ($profit >= 0 ? 'text-success' : 'text-danger');
    $details = json_encode([]);
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
    $st = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
    $st->execute([$order['user_id']]);
    $bal = (float)$st->fetchColumn();
    $total = $price * $order['quantity'];

    // BUY orders either open a long position or close an existing short
    if ($order['side'] === 'buy') {
        // First check for open short positions to close
        $stOpen = $pdo->prepare('SELECT id,price,quantity FROM trades WHERE user_id=? AND pair=? AND side="sell" AND status="open" ORDER BY id ASC LIMIT 1');
        $stOpen->execute([$order['user_id'],$order['pair']]);
        $open = $stOpen->fetch(PDO::FETCH_ASSOC);
        if ($open) {
            $closeQty = min($order['quantity'], $open['quantity']);
            $deposit = $open['price'] * $closeQty;
            $profit  = ($open['price'] - $price) * $closeQty;
            $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$deposit + $profit, $order['user_id']]);
            $bal += $deposit + $profit;
            $remaining = $open['quantity'] - $closeQty;
            if ($remaining > 0) {
                $pdo->prepare('UPDATE trades SET quantity=?, total_value=?, profit_loss=profit_loss+? WHERE id=?')->execute([$remaining, $open['price']*$remaining, $profit, $open['id']]);
            } else {
                $pdo->prepare('UPDATE trades SET status="closed", close_price=?, closed_at=NOW(), profit_loss=? WHERE id=?')->execute([$price,$profit,$open['id']]);
            }
            $opNum = 'T'.$open['id'];
            addHistory($pdo,$order['user_id'],$opNum,$order['pair'],'buy',$closeQty,$price,'complet',$profit);
            $remainingOrder = $order['quantity'] - $closeQty;
            if ($remainingOrder > 0) {
                $totalRemain = $price * $remainingOrder;
                if ($bal < $totalRemain) return ['ok'=>false,'msg'=>'Solde insuffisant'];
                $pdo->prepare('UPDATE personal_data SET balance=balance-? WHERE user_id=?')->execute([$totalRemain, $order['user_id']]);
                $stmt = $pdo->prepare('INSERT INTO trades (user_id,pair,side,quantity,price,total_value,fee,profit_loss,status) VALUES (?,?,?,?,?,?,0,0,"open")');
                $stmt->execute([$order['user_id'],$order['pair'],'buy',$remainingOrder,$price,$totalRemain]);
                $tradeId = $pdo->lastInsertId();
                addHistory($pdo,$order['user_id'],'T'.$tradeId,$order['pair'],'buy',$remainingOrder,$price,'En cours');
                return ['ok'=>true,'balance'=>$bal-$totalRemain,'price'=>$price,'profit'=>$profit,'operation'=>'T'.$tradeId,'opened'=>true];
            }
            return ['ok'=>true,'balance'=>$bal,'price'=>$price,'profit'=>$profit,'operation'=>$opNum,'opened'=>false];
        }

        // No short to close - open a long position
        if ($bal < $total) return ['ok' => false, 'msg' => 'Solde insuffisant'];
        $pdo->prepare('UPDATE personal_data SET balance=balance-? WHERE user_id=?')->execute([$total, $order['user_id']]);
        $stmt = $pdo->prepare('INSERT INTO trades (user_id,pair,side,quantity,price,total_value,fee,profit_loss,status) VALUES (?,?,?,?,?,?,0,0,"open")');
        $stmt->execute([$order['user_id'],$order['pair'],'buy',$order['quantity'],$price,$total]);
        $tradeId = $pdo->lastInsertId();
        $opNum = 'T'.$tradeId;
        // Record this trade as open in the trading history so that the UI can
        // track its profit/loss over time until it is closed.
        addHistory($pdo,$order['user_id'],$opNum,$order['pair'],'buy',$order['quantity'],$price,'En cours');
        return ['ok'=>true,'balance'=>$bal-$total,'price'=>$price,'profit'=>0,'operation'=>$opNum,'opened'=>true];
    }

    // SELL orders either close a long position or open a new short
    $stOpen = $pdo->prepare('SELECT id,price,quantity,side FROM trades WHERE user_id=? AND pair=? AND status="open" ORDER BY id ASC LIMIT 1');
    $stOpen->execute([$order['user_id'],$order['pair']]);
    $open = $stOpen->fetch(PDO::FETCH_ASSOC);

    if ($open && $open['side'] === 'buy') {
        // Closing a long position
        $closeQty   = min($order['quantity'], $open['quantity']);
        $closeTotal = $price * $closeQty;
        $profit = ($price - $open['price']) * $closeQty;
        $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$closeTotal,$order['user_id']]);
        $bal += $closeTotal;
        $remaining = $open['quantity'] - $closeQty;
        if ($remaining > 0) {
            $pdo->prepare('UPDATE trades SET quantity=?, total_value=?, profit_loss=profit_loss+? WHERE id=?')->execute([$remaining, $open['price']*$remaining, $profit, $open['id']]);
        } else {
            $pdo->prepare('UPDATE trades SET status="closed", close_price=?, closed_at=NOW(), profit_loss=? WHERE id=?')->execute([$price,$profit,$open['id']]);
        }
        $opNum = 'T'.$open['id'];
        addHistory($pdo,$order['user_id'],$opNum,$order['pair'],'sell',$closeQty,$price,'complet',$profit);
        $remainingOrder = $order['quantity'] - $closeQty;
        if ($remainingOrder > 0) {
            $totalShort = $price * $remainingOrder;
            if ($bal < $totalShort) return ['ok'=>false,'msg'=>'Solde insuffisant'];
            $pdo->prepare('UPDATE personal_data SET balance=balance-? WHERE user_id=?')->execute([$totalShort, $order['user_id']]);
            $stmt = $pdo->prepare('INSERT INTO trades (user_id,pair,side,quantity,price,total_value,fee,profit_loss,status) VALUES (?,?,?,?,?,?,0,0,"open")');
            $stmt->execute([$order['user_id'],$order['pair'],'sell',$remainingOrder,$price,$totalShort]);
            $tradeId = $pdo->lastInsertId();
            addHistory($pdo,$order['user_id'],'T'.$tradeId,$order['pair'],'sell',$remainingOrder,$price,'En cours');
            return ['ok'=>true,'balance'=>$bal-$totalShort,'price'=>$price,'profit'=>$profit,'operation'=>'T'.$tradeId,'opened'=>true];
        }
        return ['ok'=>true,'balance'=>$bal,'price'=>$price,'profit'=>$profit,'operation'=>$opNum,'opened'=>false];
    }

    // No long position to close - open a short position
    if ($bal < $total) return ['ok' => false, 'msg' => 'Solde insuffisant'];
    $pdo->prepare('UPDATE personal_data SET balance=balance-? WHERE user_id=?')->execute([$total, $order['user_id']]);
    $stmt = $pdo->prepare('INSERT INTO trades (user_id,pair,side,quantity,price,total_value,fee,profit_loss,status) VALUES (?,?,?,?,?,?,0,0,"open")');
    $stmt->execute([$order['user_id'],$order['pair'],'sell',$order['quantity'],$price,$total]);
    $tradeId = $pdo->lastInsertId();
    $opNum = 'T'.$tradeId;
    addHistory($pdo,$order['user_id'],$opNum,$order['pair'],'sell',$order['quantity'],$price,'En cours');
    return ['ok'=>true,'balance'=>$bal-$total,'price'=>$price,'profit'=>0,'operation'=>$opNum,'opened'=>true];
}
?>
