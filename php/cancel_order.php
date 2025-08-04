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
    $userId=(int)($data['user_id'] ?? 0);
    $orderId=(int)($data['order_id'] ?? 0);
    if(!$userId || !$orderId){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Missing parameters']);
        exit;
    }
    require_once __DIR__.'/../config/db_connection.php';
    require_once __DIR__.'/../utils/helpers.php';
    $pdo=db();
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT * FROM orders WHERE id=? AND user_id=? FOR UPDATE');
    $stmt->execute([$orderId,$userId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order || !in_array($order['status'],['open','triggered'])){
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status'=>'error','message'=>'Order not cancellable']);
        exit;
    }
    $pdo->prepare('UPDATE orders SET status="cancelled" WHERE id=?')->execute([$orderId]);
    if (!empty($order['related_order_id'])) {
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status IN ('open','triggered')")
            ->execute([$order['related_order_id']]);
    }
    $price = isset($order['target_price']) ? $order['target_price'] : 0;
    addHistory($pdo,$userId,'T'.$orderId,$order['pair'],$order['side'],$order['quantity'],$price,'annule');
    $pdo->prepare('UPDATE transactions SET status=?, statusClass=? WHERE operationNumber=? AND user_id=?')
        ->execute(['annule','bg-danger','T'.$orderId,$userId]);
    $pdo->commit();
    require_once __DIR__.'/../utils/poll.php';
    pushEvent('order_cancelled',['order_id'=>$orderId],$userId);
    echo json_encode(['status'=>'ok']);
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
