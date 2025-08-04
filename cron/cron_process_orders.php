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

// ---- Order condition helpers ----

function shouldFillLimit(array $o, float $price): bool {
    return ($o['side'] === 'buy' && $price <= $o['target_price'])
        || ($o['side'] === 'sell' && $price >= $o['target_price']);
}

function shouldFillStop(array $o, float $price): bool {
    return ($o['side'] === 'buy' && $price >= $o['stop_price'])
        || ($o['side'] === 'sell' && $price <= $o['stop_price']);
}

function shouldFillStopLimit(PDO $pdo, array &$o, float $price): bool {
    if ($o['status'] === 'open' && shouldFillStop($o, $price)) {
        $pdo->prepare("UPDATE orders SET status='triggered' WHERE id=?")
            ->execute([$o['id']]);
        $o['status'] = 'triggered';
        if (!empty($o['related_order_id'])) {
            $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")
                ->execute([$o['related_order_id']]);
            require_once __DIR__ . '/../utils/poll.php';
            pushEvent('order_cancelled', ['order_id' => $o['related_order_id']], $o['user_id']);
        }
    }
    return $o['status'] === 'triggered' && shouldFillLimit($o, $price);
}

function shouldFillTrailing(PDO $pdo, array &$o, float $price): bool {
    if ($o['side'] === 'sell') {
        if ($price > $o['trail_price']) {
            $pdo->prepare('UPDATE orders SET trail_price=? WHERE id=?')
                ->execute([$price, $o['id']]);
            $o['trail_price'] = $price;
        }
        return $price <= $o['trail_price'] * (1 - $o['trailing_percentage'] / 100);
    }
    if ($price < $o['trail_price']) {
        $pdo->prepare('UPDATE orders SET trail_price=? WHERE id=?')
            ->execute([$price, $o['id']]);
        $o['trail_price'] = $price;
    }
    return $price >= $o['trail_price'] * (1 + $o['trailing_percentage'] / 100);
}

function shouldFillPercentage(array $o, float $price): bool {
    $threshold = $o['trail_price'];
    if ($o['side'] === 'sell') {
        $threshold *= (1 - $o['stop_percentage'] / 100);
        return $price <= $threshold;
    }
    $threshold *= (1 + $o['stop_percentage'] / 100);
    return $price >= $threshold;
}

function shouldFillTime(array $o): bool {
    return strtotime($o['stop_time']) <= time();
}

function shouldFillOco(array $o, float $price): bool {
    // limit part of the OCO pair
    return shouldFillLimit($o, $price);
}

// ---- Main evaluation loop ----

$orders = $pdo->query("SELECT * FROM orders WHERE status IN ('open','triggered')")
    ->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as $o) {
    $price = getLivePrice($o['pair']);
    if ($price <= 0) {
        continue;
    }

    $shouldFill = false;
    switch ($o['type']) {
        case 'limit':
            $shouldFill = shouldFillLimit($o, $price);
            break;
        case 'stop':
            $shouldFill = shouldFillStop($o, $price);
            break;
        case 'stop_limit':
            $shouldFill = shouldFillStopLimit($pdo, $o, $price);
            break;
        case 'trailing_stop':
            $shouldFill = shouldFillTrailing($pdo, $o, $price);
            break;
        case 'percentage_stop':
            $shouldFill = shouldFillPercentage($o, $price);
            break;
        case 'time_stop':
            $shouldFill = shouldFillTime($o);
            break;
        case 'oco':
            $shouldFill = shouldFillOco($o, $price);
            break;
    }

    if ($shouldFill) {
        fillOrder($pdo, $o, $price);
    }
}

?>
