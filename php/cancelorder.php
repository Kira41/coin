<?php
header('Content-Type: application/json');
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});

try{
    $data=json_decode(file_get_contents('php://input'),true);
    if(!is_array($data)){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Invalid JSON']);
        exit;
    }
    $userId=(int)($data['user_id']??0);
    $op=$data['operation']??'';
    if(!$userId||!$op){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Missing parameters']);
        exit;
    }
    require_once __DIR__.'/../config/db_connection.php';
    require_once __DIR__.'/../utils/helpers.php';
    $pdo=db();
    $pdo->beginTransaction();
    $bstmt=$pdo->prepare('SELECT blocked FROM transactions WHERE operationNumber=? AND user_id=? FOR UPDATE');
    $bstmt->execute([$op,$userId]);
    $blocked=$bstmt->fetchColumn();
    if($blocked===false){
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status'=>'error','message'=>'Transaction not found']);
        exit;
    }
    if((int)$blocked===1){
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['status'=>'error','message'=>'Order blocked']);
        exit;
    }
    $id=(int)ltrim($op,'T');
    // check for open trade
    $tstmt=$pdo->prepare('SELECT * FROM trades WHERE user_id=? AND status="open" AND (order_id=? OR id=?) FOR UPDATE');
    $tstmt->execute([$userId,$id,$id]);
    $trade=$tstmt->fetch(PDO::FETCH_ASSOC);
    if($trade){
        $price=getLivePrice($trade['pair']);
        if($price<=0){
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'Failed to fetch price']);
            exit;
        }
        $sideOpp=$trade['side']==='buy'?'sell':'buy';
        $balStmt=$pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
        $balStmt->execute([$userId]);
        $balance=(float)$balStmt->fetchColumn();
        $qty=(float)$trade['quantity'];
        if($trade['side']==='buy'){
            $profit=($price - $trade['price'])*$qty;
            $total=$price*$qty;
            $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$total,$userId]);
        }else{
            $profit=($trade['price'] - $price)*$qty;
            $deposit=$trade['price']*$qty;
            $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$deposit+$profit,$userId]);
        }
        $pdo->prepare('UPDATE trades SET status="closed", close_price=?, closed_at=NOW(), profit_loss=? WHERE id=?')
            ->execute([$price,$profit,$trade['id']]);
        if(!empty($trade['order_id'])){
            $executedAmount=$price*$qty;
            $pdo->prepare('UPDATE orders SET status="filled", price_at_execution=?, executed_at=NOW(), amount=?, profit=? WHERE id=?')
                ->execute([$price,$executedAmount,$executedAmount,$profit,$trade['order_id']]);
        }
        addHistory($pdo,$userId,$op,$trade['pair'],$sideOpp,$qty,$price,'complet',$profit);
        $pdo->prepare('UPDATE transactions SET status=?, statusClass=? WHERE operationNumber=?')
            ->execute(['complet','bg-success',$op]);
        $balStmt->execute([$userId]);
        $newBal=(float)$balStmt->fetchColumn();
        $pdo->commit();
        require_once __DIR__.'/../utils/poll.php';
        pushEvent('balance_updated',['newBalance'=>$newBal],$userId);
        pushEvent('order_filled',[
            'order_id'=>$id,
            'pair'=>$trade['pair'],
            'side'=>$sideOpp,
            'quantity'=>$qty,
            'price'=>$price,
            'profit_loss'=>$profit
        ],$userId);
        echo json_encode(['status'=>'ok','new_balance'=>$newBal,'profit'=>$profit,'closed'=>true]);
        exit;
    }
    // pending order cancellation
    $stmt=$pdo->prepare('SELECT * FROM orders WHERE id=? AND user_id=? FOR UPDATE');
    $stmt->execute([$id,$userId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order || !in_array($order['status'],['open','triggered'])){
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status'=>'error','message'=>'Order not cancellable']);
        exit;
    }
    $pdo->prepare('UPDATE orders SET status="cancelled" WHERE id=?')->execute([$id]);
    if(!empty($order['related_order_id'])){
        $pdo->prepare('UPDATE orders SET status="cancelled" WHERE id=? AND status IN ("open","triggered")')
            ->execute([$order['related_order_id']]);
    }
    $price=isset($order['target_price'])?$order['target_price']:0;
    addHistory($pdo,$userId,$op,$order['pair'],$order['side'],$order['quantity'],$price,'annule');
    $pdo->prepare('UPDATE transactions SET status=?, statusClass=? WHERE operationNumber=?')
        ->execute(['annule','bg-danger',$op]);
    $pdo->commit();
    require_once __DIR__.'/../utils/poll.php';
    pushEvent('order_cancelled',['order_id'=>$id],$userId);
    echo json_encode(['status'=>'ok','cancelled'=>true]);
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
