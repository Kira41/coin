<?php
// Cron job to evaluate pending orders and execute when conditions meet
require_once __DIR__.'/../config/db_connection.php';
require_once __DIR__.'/../utils/helpers.php';

$pdo = db();

function fillOrder(PDO $pdo, array $o, float $price): void {
    $pdo->beginTransaction();
    $res = executeTrade($pdo, $o, $price);
    if ($res['ok']) {
        $pdo->commit();
        require_once __DIR__ . '/../utils/poll.php';
        pushEvent('balance_updated', ['newBalance' => $res['balance']], $o['user_id']);
        pushEvent('order_filled', ['order_id' => $o['id'], 'price' => $res['price']], $o['user_id']);
        if (!empty($o['related_order_id'])) {
            $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")
                ->execute([$o['related_order_id']]);
        }
    } else {
        $pdo->rollBack();
    }
}

// fetch open and triggered orders
$orders = $pdo->query("SELECT * FROM orders WHERE status IN ('open','triggered')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $o) {
    $price = getLivePrice($o['pair']);
    if ($price <= 0) continue;

    switch ($o['type']) {
        case 'limit':
            if (($o['side']=='buy' && $price <= $o['target_price']) || ($o['side']=='sell' && $price >= $o['target_price'])) {
                fillOrder($pdo, $o, $price);
            }
            break;
        case 'percentage_stop':
            $threshold = $o['trail_price'];
            if ($o['side']=='sell') {
                $threshold *= (1 - $o['stop_percentage']/100);
                if ($price <= $threshold) {
                    fillOrder($pdo, $o, $price);
                }
            } else {
                $threshold *= (1 + $o['stop_percentage']/100);
                if ($price >= $threshold) {
                    fillOrder($pdo, $o, $price);
                }
            }
            break;
        case 'time_stop':
            if (strtotime($o['stop_time']) <= time()) {
                fillOrder($pdo, $o, $price);
            }
            break;
        case 'oco':
            if (($o['side']=='buy' && $price <= $o['target_price']) || ($o['side']=='sell' && $price >= $o['target_price'])) {
                fillOrder($pdo, $o, $price);
            }
            break;
        case 'stop':
            if (($o['side']=='buy' && $price >= $o['stop_price']) || ($o['side']=='sell' && $price <= $o['stop_price'])) {
                fillOrder($pdo, $o, $price);
            }
            break;
        case 'stop_limit':
            if ($o['status']=='open') {
                if (($o['side']=='buy' && $price >= $o['stop_price']) || ($o['side']=='sell' && $price <= $o['stop_price'])) {
                    $pdo->prepare("UPDATE orders SET status='triggered' WHERE id=?")->execute([$o['id']]);
                    $o['status']='triggered';
                }
            }
            if ($o['status']=='triggered') {
                if (($o['side']=='buy' && $price <= $o['target_price']) || ($o['side']=='sell' && $price >= $o['target_price'])) {
                    fillOrder($pdo, $o, $price);
                }
            }
            break;
        case 'trailing_stop':
            if ($o['side']=='sell') {
                if ($price > $o['trail_price']) {
                    $pdo->prepare('UPDATE orders SET trail_price=? WHERE id=?')->execute([$price,$o['id']]);
                    $o['trail_price']=$price;
                } elseif ($price <= $o['trail_price']*(1-$o['trailing_percentage']/100)) {
                    fillOrder($pdo, $o, $price);
                }
            } else {
                if ($price < $o['trail_price']) {
                    $pdo->prepare('UPDATE orders SET trail_price=? WHERE id=?')->execute([$price,$o['id']]);
                    $o['trail_price']=$price;
                } elseif ($price >= $o['trail_price']*(1+$o['trailing_percentage']/100)) {
                    fillOrder($pdo, $o, $price);
                }
            }
            break;
    }
}
?>
