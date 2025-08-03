<?php
// Cron job to evaluate pending orders and execute when conditions meet
require_once __DIR__.'/../config/db_connection.php';
require_once __DIR__.'/../utils/helpers.php';

$pdo = db();

// fetch open and triggered orders
$orders = $pdo->query("SELECT * FROM orders WHERE status IN ('open','triggered')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $o) {
    $price = getLivePrice($o['pair']);
    if ($price <= 0) continue;

    switch ($o['type']) {
        case 'limit':
            if (($o['side']=='buy' && $price <= $o['target_price']) || ($o['side']=='sell' && $price >= $o['target_price'])) {
                $pdo->beginTransaction();
                $res = executeTrade($pdo, $o, $price);
                if ($res['ok']) {
                    $pdo->commit();
                    require_once __DIR__.'/../utils/poll.php';
                    pushEvent('balance_updated',[ 'newBalance'=>$res['balance'] ],$o['user_id']);
                    pushEvent('wallet_updated',[],$o['user_id']);
                    pushEvent('order_filled',[ 'order_id'=>$o['id'],'price'=>$price ],$o['user_id']);
                } else {
                    $pdo->rollBack();
                }
            }
            break;
        case 'percentage_stop':
            $threshold = $o['trail_price'];
            if ($o['side']=='sell') {
                $threshold *= (1 - $o['stop_percentage']/100);
                if ($price <= $threshold) {
                    $pdo->beginTransaction();
                    $res=executeTrade($pdo,$o,$price);
                    if($res['ok']) {
                        $pdo->commit();
                        require_once __DIR__.'/../utils/poll.php';
                        pushEvent('balance_updated',[ 'newBalance'=>$res['balance'] ],$o['user_id']);
                        pushEvent('wallet_updated',[],$o['user_id']);
                        pushEvent('order_filled',[ 'order_id'=>$o['id'],'price'=>$price ],$o['user_id']);
                    } else {
                        $pdo->rollBack();
                    }
                }
            } else {
                $threshold *= (1 + $o['stop_percentage']/100);
                if ($price >= $threshold) {
                    $pdo->beginTransaction();
                    $res=executeTrade($pdo,$o,$price);
                    if($res['ok']) {
                        $pdo->commit();
                        require_once __DIR__.'/../utils/poll.php';
                        pushEvent('balance_updated',[ 'newBalance'=>$res['balance'] ],$o['user_id']);
                        pushEvent('wallet_updated',[],$o['user_id']);
                        pushEvent('order_filled',[ 'order_id'=>$o['id'],'price'=>$price ],$o['user_id']);
                    } else {
                        $pdo->rollBack();
                    }
                }
            }
            break;
        case 'time_stop':
            if (strtotime($o['stop_time']) <= time()) {
                $pdo->beginTransaction();
                $res=executeTrade($pdo,$o,$price);
                if($res['ok']) {
                    $pdo->commit();
                    require_once __DIR__.'/../utils/poll.php';
                    pushEvent('balance_updated',[ 'newBalance'=>$res['balance'] ],$o['user_id']);
                    pushEvent('wallet_updated',[],$o['user_id']);
                    pushEvent('order_filled',[ 'order_id'=>$o['id'],'price'=>$price ],$o['user_id']);
                } else {
                    $pdo->rollBack();
                }
            }
            break;
        case 'oco':
            if (($o['side']=='buy' && $price <= $o['target_price']) || ($o['side']=='sell' && $price >= $o['target_price'])) {
                $pdo->beginTransaction();
                $res = executeTrade($pdo, $o, $price);
                if ($res['ok']) {
                    $pdo->commit();
                    require_once __DIR__.'/../utils/poll.php';
                    pushEvent('balance_updated',['newBalance'=>$res['balance']],$o['user_id']);
                    pushEvent('wallet_updated',[],$o['user_id']);
                    pushEvent('order_filled',[ 'order_id'=>$o['id'],'price'=>$price ],$o['user_id']);
                } else {
                    $pdo->rollBack();
                }
            }
            break;
        case 'stop':
            if (($o['side']=='buy' && $price >= $o['stop_price']) || ($o['side']=='sell' && $price <= $o['stop_price'])) {
                $pdo->beginTransaction();
                $res = executeTrade($pdo, $o, $price);
                if ($res['ok']) {
                    $pdo->commit();
                    require_once __DIR__.'/../utils/poll.php';
                    pushEvent('balance_updated',['newBalance'=>$res['balance']],$o['user_id']);
                    pushEvent('wallet_updated',[],$o['user_id']);
                    pushEvent('order_filled',['order_id'=>$o['id'],'price'=>$price],$o['user_id']);
                } else {
                    $pdo->rollBack();
                }
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
                    $pdo->beginTransaction();
                    $res = executeTrade($pdo,$o,$price);
                    if ($res['ok']) {
                        $pdo->commit();
                        require_once __DIR__.'/../utils/poll.php';
                        pushEvent('balance_updated',['newBalance'=>$res['balance']],$o['user_id']);
                        pushEvent('wallet_updated',[],$o['user_id']);
                        pushEvent('order_filled',[ 'order_id'=>$o['id'],'price'=>$price ],$o['user_id']);
                    } else {
                        $pdo->rollBack();
                    }
                }
            }
            break;
        case 'trailing_stop':
            if ($o['side']=='sell') {
                if ($price > $o['trail_price']) {
                    $pdo->prepare('UPDATE orders SET trail_price=? WHERE id=?')->execute([$price,$o['id']]);
                    $o['trail_price']=$price;
                } elseif ($price <= $o['trail_price']*(1-$o['trailing_percentage']/100)) {
                    $pdo->beginTransaction();
                    $res=executeTrade($pdo,$o,$price);
                    if($res['ok']) {
                        $pdo->commit();
                        require_once __DIR__.'/../utils/poll.php';
                        pushEvent('balance_updated',['newBalance'=>$res['balance']],$o['user_id']);
                        pushEvent('wallet_updated',[],$o['user_id']);
                        pushEvent('order_filled',['order_id'=>$o['id'],'price'=>$price],$o['user_id']);
                    } else {
                        $pdo->rollBack();
                    }
                }
            } else {
                if ($price < $o['trail_price']) {
                    $pdo->prepare('UPDATE orders SET trail_price=? WHERE id=?')->execute([$price,$o['id']]);
                    $o['trail_price']=$price;
                } elseif ($price >= $o['trail_price']*(1+$o['trailing_percentage']/100)) {
                    $pdo->beginTransaction();
                    $res=executeTrade($pdo,$o,$price);
                    if($res['ok']) {
                        $pdo->commit();
                        require_once __DIR__.'/../utils/poll.php';
                        pushEvent('balance_updated',['newBalance'=>$res['balance']],$o['user_id']);
                        pushEvent('wallet_updated',[],$o['user_id']);
                        pushEvent('order_filled',['order_id'=>$o['id'],'price'=>$price],$o['user_id']);
                    } else {
                        $pdo->rollBack();
                    }
                }
            }
            break;
    }
}
?>
