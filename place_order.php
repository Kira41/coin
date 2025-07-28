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

    if(!$userId || !$pair || $qty <= 0 || !in_array($side,['buy','sell'])){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Missing parameters']);
        exit;
    }

    $dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo = new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    // helper functions copied from market_order.php
    function getLivePrice(string $pair): float {
        $symbol = str_replace('/', '', strtoupper($pair));
        $symbol = str_replace('USD', 'USDT', $symbol);
        $url = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
        $d = @json_decode(file_get_contents($url), true);
        return isset($d['price']) ? (float)$d['price'] : 0.0;
    }
    function addToWallet(PDO $pdo,int $uid,string $cur,float $amt,float $price){
        $cur = strtolower($cur);
        $st=$pdo->prepare('SELECT amount,purchase_price FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
        $st->execute([$uid,$cur]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if($row){
            $new=$row['amount']+$amt;
            $avg=($row['amount']*$row['purchase_price']+$amt*$price)/$new;
            $pdo->prepare('UPDATE wallets SET amount=?, purchase_price=? WHERE user_id=? AND currency=?')->execute([$new,$avg,$uid,$cur]);
        }else{
            $pdo->prepare('INSERT INTO wallets (user_id,currency,amount,address,label,purchase_price) VALUES (?,?,?,?,?,?)')->execute([$uid,$cur,$amt,'local address',strtoupper($cur),$price]);
        }
    }
    function deductFromWallet(PDO $pdo,int $uid,string $cur,float $amt){
        $cur=strtolower($cur);
        $st=$pdo->prepare('SELECT amount,purchase_price FROM wallets WHERE user_id=? AND currency=? FOR UPDATE');
        $st->execute([$uid,$cur]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row || $row['amount']<$amt) return false;
        $new=$row['amount']-$amt;
        if($new>0){
            $pdo->prepare('UPDATE wallets SET amount=? WHERE user_id=? AND currency=?')->execute([$new,$uid,$cur]);
        } else {
            $pdo->prepare('DELETE FROM wallets WHERE user_id=? AND currency=?')->execute([$uid,$cur]);
        }
        return (float)$row['purchase_price'];
    }
    function executeTrade(PDO $pdo,array $order,float $price){
        [$base,$quote]=explode('/',strtoupper($order['pair']));
        $total=$price*$order['quantity'];
        if($order['side']=='buy'){
            $st=$pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
            $st->execute([$order['user_id']]);
            $bal=$st->fetchColumn();
            if($bal===false || $bal<$total) return ['ok'=>false,'msg'=>'Solde insuffisant'];
            $pdo->prepare('UPDATE personal_data SET balance=balance-? WHERE user_id=?')->execute([$total,$order['user_id']]);
            addToWallet($pdo,$order['user_id'],$base,$order['quantity'],$price);
            $newBal=$bal-$total;
            $profit=0;
        } else {
            $st=$pdo->prepare('SELECT balance FROM personal_data WHERE user_id=? FOR UPDATE');
            $st->execute([$order['user_id']]);
            $bal=$st->fetchColumn();
            $purchase=deductFromWallet($pdo,$order['user_id'],$base,$order['quantity']);
            if($purchase===false) return ['ok'=>false,'msg'=>'Solde insuffisant'];
            $pdo->prepare('UPDATE personal_data SET balance=balance+? WHERE user_id=?')->execute([$total,$order['user_id']]);
            $newBal=$bal+$total;
            $profit=($price-$purchase)*$order['quantity'];
        }
        $stmt=$pdo->prepare('INSERT INTO trades (user_id,order_id,pair,side,quantity,price,total_value,fee,profit_loss) VALUES (?,?,?,?,?,?,?,0,?)');
        $stmt->execute([$order['user_id'],$order['id'],$order['pair'],$order['side'],$order['quantity'],$price,$total,$profit]);
        $pdo->prepare('UPDATE orders SET status="filled",price_at_execution=?,executed_at=NOW() WHERE id=?')->execute([$price,$order['id']]);
        return ['ok'=>true,'balance'=>$newBal,'price'=>$price];
    }

    $livePrice = getLivePrice($pair);
    if($livePrice<=0) $livePrice = 0.0;

    if($type==='market'){
        $pdo->beginTransaction();
        $order=['id'=>0,'user_id'=>$userId,'pair'=>$pair,'side'=>$side,'quantity'=>$qty];
        $result = executeTrade($pdo,$order,$livePrice);
        if(!$result['ok']){ $pdo->rollBack(); http_response_code(400); echo json_encode(['status'=>'error','message'=>$result['msg']]); return; }
        $pdo->commit();
        echo json_encode(['status'=>'ok','price'=>$result['price'],'new_balance'=>$result['balance']]);
        return;
    }

    // For pending orders just record
    $stmt=$pdo->prepare('INSERT INTO orders (user_id,pair,type,side,quantity,target_price,stop_price,trailing_percentage,trail_price,related_order_id) VALUES (?,?,?,?,?,?,?,?,?,NULL)');
    $trailPrice = $livePrice>0 ? $livePrice : null;
    $stmt->execute([$userId,$pair,$type,$side,$qty,$limit,$stop,$trailPerc,$trailPrice]);
    $id=$pdo->lastInsertId();

    if($type==='oco'){
        // create second order for stop limit part using provided stop_limit_price
        $stopLimitOrder=$pdo->prepare('INSERT INTO orders (user_id,pair,type,side,quantity,target_price,stop_price,related_order_id) VALUES (?,?,?,?,?,?,?,?)');
        $stopLimitOrder->execute([$userId,$pair,'stop_limit',$side,$qty,$stopLimit,$stop,$id]);
        $secondId=$pdo->lastInsertId();
        $pdo->prepare('UPDATE orders SET related_order_id=? WHERE id=?')->execute([$secondId,$id]);
    }

    echo json_encode(['status'=>'ok','order_id'=>$id]);
} catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
