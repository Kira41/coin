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
    if (!empty($order['id'])) {
        $check = $pdo->prepare('SELECT 1 FROM trades WHERE order_id = ?');
        $check->execute([$order['id']]);
        if ($check->fetchColumn()) {
            return ['ok' => false, 'msg' => 'Order already filled'];
        }
    }

    $st = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
    $st->execute([$order['user_id']]);
    $bal = (float)$st->fetchColumn();
    $total = $price * $order['quantity'];

    if ($order['side'] === 'buy') {
        if ($bal < $total) return ['ok' => false, 'msg' => 'Solde insuffisant'];
        $pdo->prepare('UPDATE personal_data SET balance=balance-? WHERE user_id=?')->execute([$total, $order['user_id']]);
        $orderId = empty($order['id']) ? null : $order['id'];
        $stmt = $pdo->prepare('INSERT INTO trades (user_id,order_id,pair,side,quantity,price,total_value,fee,profit_loss,status) VALUES (?,?,?,?,?,?,?,0,0,"open")');
        $stmt->execute([$order['user_id'],$orderId,$order['pair'],$order['side'],$order['quantity'],$price,$total]);
        $tradeId = $pdo->lastInsertId();
        if ($orderId !== null) {
            $pdo->prepare('UPDATE orders SET status="filled",price_at_execution=?,executed_at=NOW() WHERE id=?')->execute([$price,$orderId]);
        }
        $opNum = 'T'.($order['id'] ?: $tradeId);
        addHistory($pdo,$order['user_id'],$opNum,$order['pair'],$order['side'],$order['quantity'],$price,'complet');
        return ['ok'=>true,'balance'=>$bal-$total,'price'=>$price,'profit'=>0,'operation'=>$opNum];
    }

    $stOpen=$pdo->prepare('SELECT id,price,quantity FROM trades WHERE user_id=? AND pair=? AND status="open" ORDER BY id ASC LIMIT 1');
    $stOpen->execute([$order['user_id'],$order['pair']]);
    $open=$stOpen->fetch(PDO::FETCH_ASSOC);
    if(!$open || $open['quantity'] < $order['quantity']) return ['ok'=>false,'msg'=>'Position insuffisante'];
    $profit=($price-$open['price'])*$order['quantity'];
    $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$total,$order['user_id']]);
    $remaining=$open['quantity']-$order['quantity'];
    if($remaining>0){
        $pdo->prepare('UPDATE trades SET quantity=?, total_value=?, profit_loss=profit_loss+? WHERE id=?')->execute([$remaining,$remaining*$open['price'],$profit,$open['id']]);
    }else{
        $pdo->prepare('UPDATE trades SET status="closed", close_price=?, closed_at=NOW(), profit_loss=? WHERE id=?')->execute([$price,$profit,$open['id']]);
    }
    $opNum='T'.$open['id'];
    addHistory($pdo,$order['user_id'],$opNum,$order['pair'],'sell',$order['quantity'],$price,'complet',$profit);
    return ['ok'=>true,'balance'=>$bal+$total,'price'=>$price,'profit'=>$profit,'operation'=>$opNum];
}
?>
