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
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0.0; // USD amount for market orders
    $side = strtolower($input['side'] ?? 'buy');
    $type = strtolower($input['type'] ?? 'market');
    if ($type === 'stoplimit') $type = 'stop_limit';
    $limit = isset($input['limit_price']) ? $input['limit_price'] : null;
    $stop = isset($input['stop_price']) ? $input['stop_price'] : null;
    $stopLimit = isset($input['stop_limit_price']) ? $input['stop_limit_price'] : null;
    $trailPerc = isset($input['trailing_percentage']) ? $input['trailing_percentage'] : null;
    $stopPercent = isset($input['stop_percentage']) ? $input['stop_percentage'] : null;
    $stopTime = isset($input['stop_time']) ? $input['stop_time'] : null;

    $isPositive = static fn($v) => is_numeric($v) && (float)$v > 0;

    $limit = $limit !== null ? (float)$limit : null;
    $stop = $stop !== null ? (float)$stop : null;
    $stopLimit = $stopLimit !== null ? (float)$stopLimit : null;
    $trailPerc = $trailPerc !== null ? (float)$trailPerc : null;
    $stopPercent = $stopPercent !== null ? (float)$stopPercent : null;

    $qtyProvided = $isPositive($qty);
    $amtProvided = $isPositive($amount);
    if(!$userId || !$pair || (!in_array($side,['buy','sell'])) || (!$qtyProvided && !($type==='market' && $amtProvided))){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Missing parameters']);
        exit;
    }

    if(!preg_match('/^[A-Z]{2,10}\/[A-Z]{2,10}$/', strtoupper($pair))){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Invalid pair']);
        exit;
    }

    $allowedTypes = ['market','limit','stop','stop_limit','trailing_stop','percentage_stop','time_stop','oco'];
    if(!in_array($type, $allowedTypes, true)){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Invalid order type']);
        exit;
    }

    switch ($type) {
        case 'limit':
            if (!$isPositive($limit)) {
                http_response_code(400);
                echo json_encode(['status'=>'error','message'=>'limit_price required for limit orders']);
                exit;
            }
            break;
        case 'stop':
            if (!$isPositive($stop)) {
                http_response_code(400);
                echo json_encode(['status'=>'error','message'=>'stop_price required for stop orders']);
                exit;
            }
            break;
        case 'stop_limit':
            if (!$isPositive($limit) || !$isPositive($stop)) {
                http_response_code(400);
                echo json_encode(['status'=>'error','message'=>'stop_price and limit_price required for stop_limit orders']);
                exit;
            }
            break;
        case 'trailing_stop':
            if (!$isPositive($trailPerc)) {
                http_response_code(400);
                echo json_encode(['status'=>'error','message'=>'trailing_percentage required for trailing_stop orders']);
                exit;
            }
            break;
        case 'percentage_stop':
            if (!$isPositive($stopPercent)) {
                http_response_code(400);
                echo json_encode(['status'=>'error','message'=>'stop_percentage required for percentage_stop orders']);
                exit;
            }
            break;
        case 'time_stop':
            if ($stopTime === null || strtotime($stopTime) === false) {
                http_response_code(400);
                echo json_encode(['status'=>'error','message'=>'valid stop_time required for time_stop orders']);
                exit;
            }
            break;
        case 'oco':
            if (!$isPositive($limit) || !$isPositive($stop) || !$isPositive($stopLimit)) {
                http_response_code(400);
                echo json_encode(['status'=>'error','message'=>'oco orders require limit_price, stop_price and stop_limit_price']);
                exit;
            }
            break;
    }

    require_once __DIR__.'/../config/db_connection.php';
    require_once __DIR__.'/../utils/helpers.php';

    $pdo = db();

    $livePrice = getLivePrice($pair);
    if($type==='market' && !$qtyProvided && $amtProvided){
        if($livePrice<=0){
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'Failed to fetch price']);
            return;
        }
        $qty = $amount / $livePrice;
        $qtyProvided = $isPositive($qty);
        if(!$qtyProvided){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Invalid amount']);
            return;
        }
    }
    if($livePrice<=0) $livePrice = 0.0;

    // ensure sufficient balance for pending buy orders
    if($type!=='market' && $side==='buy'){
        $orderPrice = $limit ?? $stop ?? $stopLimit ?? $livePrice;
        if($orderPrice <= 0) $orderPrice = $livePrice;
        $st=$pdo->prepare('SELECT balance FROM personal_data WHERE user_id=?');
        $st->execute([$userId]);
        $balance=$st->fetchColumn();
        $balance=$balance!==false?$balance:0;
        if(bccomp((string)$balance, bcmul((string)$orderPrice, (string)$qty, 8), 8) === -1){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Solde insuffisant']);
            return;
        }
    }

    if($type==='market'){
        $pdo->beginTransaction();
        $order=['id'=>0,'user_id'=>$userId,'pair'=>$pair,'side'=>$side,'quantity'=>$qty,'type'=>'market'];
        $result = executeTrade($pdo,$order,$livePrice);
        if(!$result['ok']){ $pdo->rollBack(); http_response_code(400); echo json_encode(['status'=>'error','message'=>$result['msg']]); return; }
        $pdo->commit();
        require_once __DIR__.'/../utils/poll.php';
        $price   = $result['price'];
        $profit  = $result['profit'];
        $opNum   = $result['operation'];
        $opened  = $result['opened'];
        pushEvent('balance_updated', ['newBalance' => $result['balance']], $userId);
        if($opened){
            pushEvent('new_trade', [
                'operation_number' => $opNum,
                'pair' => $pair,
                'side' => $side,
                'quantity' => $qty,
                'price' => $price,
                'target_price' => $price,
                'profit_loss' => $profit
            ], $userId);
        } else {
            pushEvent('order_filled', [
                'order_id' => ltrim($opNum,'T'),
                'pair' => $pair,
                'side' => $side,
                'quantity' => $qty,
                'price' => $price,
                'profit_loss' => $profit
            ], $userId);
        }
        echo json_encode([
            'status'=>'ok',
            'price'=>$price,
            'new_balance'=>$result['balance']
        ]);
        return;
    }

    // For pending orders just record
    $stmt=$pdo->prepare('INSERT INTO orders (user_id,pair,type,side,quantity,target_price,stop_price,trailing_percentage,stop_percentage,stop_time,trail_price,related_order_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL)');
    $trailPrice = $livePrice>0 ? $livePrice : null;
    $stmt->execute([$userId,$pair,$type,$side,$qty,$limit,$stop,$trailPerc,$stopPercent,$stopTime,$trailPrice]);
    $id=$pdo->lastInsertId();
    $opNum = 'T'.$id;
    addHistory($pdo,$userId,$opNum,$pair,$side,$qty,$limit ?? $livePrice,'En cours',null,$type);

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

    [$base] = explode('/', strtoupper($pair));
    $typeLabel = str_replace('_', ' ', $type);
    $actionMsg = $side === 'buy'
        ? "Ordre {$typeLabel} d'achat de {$qty} {$base} enregist\xC3\xA9"
        : "Ordre {$typeLabel} de vente de {$qty} {$base} enregist\xC3\xA9";

    echo json_encode(['status' => 'ok', 'order_id' => $id, 'message' => $actionMsg]);
} catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
