<?php
require_once __DIR__.'/../config/db_connection.php';
require_once __DIR__.'/../utils/helpers.php';
require_once __DIR__.'/../utils/poll.php';

$pdo = db();

$orders = $pdo->query("SELECT * FROM trades WHERE type_order='limit' AND status='pending'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $o) {
    $price = getLivePrice($o['pair']);
    if ($price <= 0) { continue; }
    $trigger = ($o['side'] === 'buy' && $price >= $o['price']) ||
               ($o['side'] === 'sell' && $price <= $o['price']);
    if (!$trigger) continue;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM trades WHERE id=? FOR UPDATE");
        $stmt->execute([$o['id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) { $pdo->rollBack(); continue; }
        $price = getLivePrice($order['pair']);
        $trigger = ($order['side'] === 'buy' && $price >= $order['price']) ||
                   ($order['side'] === 'sell' && $price <= $order['price']);
        if (!$trigger) { $pdo->rollBack(); continue; }
        $tradeOrder = [
            'user_id' => $order['user_id'],
            'pair' => $order['pair'],
            'side' => $order['side'],
            'quantity' => $order['quantity']
        ];
        $result = executeTrade($pdo, $tradeOrder, $price);
        if (!$result['ok']) { $pdo->rollBack(); continue; }
        $pdo->prepare('DELETE FROM trades WHERE id=?')->execute([$order['id']]);
        addHistory($pdo, $order['user_id'], 'L'.$order['id'], $order['pair'], $order['side'], $order['quantity'], $price, 'complet', $result['profit']);
        $pdo->commit();
        pushEvent('balance_updated', ['newBalance' => $result['balance']], $order['user_id']);
        if ($result['opened']) {
            pushEvent('new_trade', [
                'operation_number' => $result['operation'],
                'pair' => $order['pair'],
                'side' => $order['side'],
                'quantity' => $order['quantity'],
                'price' => $price,
                'target_price' => $price,
                'profit_loss' => $result['profit']
            ], $order['user_id']);
        } else {
            pushEvent('order_cancelled', ['order_id' => ltrim($result['operation'], 'T')], $order['user_id']);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}
