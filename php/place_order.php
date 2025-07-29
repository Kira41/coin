<?php
header('Content-Type: application/json');
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Invalid JSON']);
        exit;
    }
    $userId = (int)($input['user_id'] ?? 0);
    $pair = $input['pair'] ?? '';
    $qty = isset($input['quantity']) ? (float)$input['quantity'] : 0.0;
    $side = strtolower($input['side'] ?? 'buy');
    $type = strtolower($input['type'] ?? 'market');
    $limit = isset($input['limit_price']) ? (float)$input['limit_price'] : null;
    $stop = isset($input['stop_price']) ? (float)$input['stop_price'] : null;
    $stopLimit = isset($input['stop_limit_price']) ? (float)$input['stop_limit_price'] : null;
    $trailPerc = isset($input['trailing_percentage']) ? (float)$input['trailing_percentage'] : null;
    $stopPercent = isset($input['stop_percentage']) ? (float)$input['stop_percentage'] : null;
    $stopTime = isset($input['stop_time']) ? $input['stop_time'] : null;

    if(!$userId || !$pair || $qty <= 0 || !in_array($side,['buy','sell'])){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Missing parameters']);
        exit;
    }

    require_once __DIR__.'/../config/db_connection.php';
    require_once __DIR__.'/../utils/helpers.php';

    $pdo = db();

    $livePrice = getLivePrice($pair);
    if($livePrice<=0) $livePrice = 0.0;

    // prevent overselling by checking pending sell orders
    if($type!=='market' && $side==='sell'){
        [$base] = explode('/', strtoupper($pair));
        $st=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM orders WHERE user_id=? AND side='sell' AND status IN ('open','triggered') AND pair LIKE ?");
        $st->execute([$userId,$base.'/%']);
        $pending=$st->fetchColumn();
        $pending=$pending!==false?$pending:0;
        $st=$pdo->prepare('SELECT amount FROM wallets WHERE user_id=? AND currency=?');
        $st->execute([$userId,strtolower($base)]);
        $available=$st->fetchColumn();
        $available=$available!==false?$available:0;
        if(bccomp(bcsub((string)$available,(string)$pending,8),(string)$qty,8)==-1){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Solde insuffisant']);
            return;
        }
    }

    if($type==='market'){
        $pdo->beginTransaction();
        $order=['id'=>0,'user_id'=>$userId,'pair'=>$pair,'side'=>$side,'quantity'=>$qty];
        $result = executeTrade($pdo,$order,$livePrice);
        if(!$result['ok']){ $pdo->rollBack(); http_response_code(400); echo json_encode(['status'=>'error','message'=>$result['msg']]); return; }
        $pdo->commit();
        require_once __DIR__.'/../utils/poll.php';
        pushEvent('balance_updated', ['newBalance' => $result['balance']], $userId);
        pushEvent('wallet_updated', [], $userId);
        pushEvent('order_filled', [
            'pair' => $pair,
            'side' => $side,
            'quantity' => $qty,
            'price' => $result['price']
        ], $userId);
        require_once __DIR__.'/../cron/cron_process_orders.php';
        require_once __DIR__.'/../cron/cron_wallet_usd.php';
        echo json_encode(['status'=>'ok','price'=>$result['price'],'new_balance'=>$result['balance']]);
        return;
    }

    // For pending orders just record
    $stmt=$pdo->prepare('INSERT INTO orders (user_id,pair,type,side,quantity,target_price,stop_price,trailing_percentage,stop_percentage,stop_time,trail_price,related_order_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL)');
    $trailPrice = $livePrice>0 ? $livePrice : null;
    $stmt->execute([$userId,$pair,$type,$side,$qty,$limit,$stop,$trailPerc,$stopPercent,$stopTime,$trailPrice]);
    $id=$pdo->lastInsertId();
    $opNum = 'T'.$id;
    addHistory($pdo,$userId,$opNum,$pair,$side,$qty,$limit ?? $livePrice,'En cours');

    require_once __DIR__.'/../utils/poll.php';
    pushEvent('new_order', [
        'order_id' => $id,
        'operation_number' => $opNum,
        'pair' => $pair,
        'type' => $type,
        'side' => $side,
        'quantity' => $qty,
        'target_price' => $limit,
        'stop_price' => $stop,
        'stop_percentage' => $stopPercent,
        'stop_time' => $stopTime
    ], $userId);

    if($type==='oco'){
        // create second order for stop limit part using provided stop_limit_price
        $stopLimitOrder=$pdo->prepare('INSERT INTO orders (user_id,pair,type,side,quantity,target_price,stop_price,related_order_id) VALUES (?,?,?,?,?,?,?,?)');
        $stopLimitOrder->execute([$userId,$pair,'stop_limit',$side,$qty,$stopLimit,$stop,$id]);
        $secondId=$pdo->lastInsertId();
        $pdo->prepare('UPDATE orders SET related_order_id=? WHERE id=?')->execute([$secondId,$id]);
    }

    require_once __DIR__.'/../cron/cron_process_orders.php';
    require_once __DIR__.'/../cron/cron_wallet_usd.php';

    echo json_encode(['status'=>'ok','order_id'=>$id]);
} catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
