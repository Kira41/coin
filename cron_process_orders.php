<?php
// Cron job to evaluate pending orders and execute when conditions meet
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function getLivePrice(string $pair): float {
    $symbol = str_replace('/', '', strtoupper($pair));
    $symbol = str_replace('USD', 'USDT', $symbol);
    $url = 'https://api.binance.com/api/v3/ticker/price?symbol=' . $symbol;
    $data = @json_decode(file_get_contents($url), true);
    return isset($data['price']) ? (float)$data['price'] : 0.0;
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
    if($order['related_order_id']){
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status IN ('open','triggered')")->execute([$order['related_order_id']]);
    }
    return ['ok'=>true];
}

// fetch open and triggered orders
$orders = $pdo->query("SELECT * FROM orders WHERE status IN ('open','triggered')")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $o) {
    $price = getLivePrice($o['pair']);
    if ($price <= 0) continue;

    switch ($o['type']) {
        case 'limit':
            if (($o['side']=='buy' && $price <= $o['target_price']) || ($o['side']=='sell' && $price >= $o['target_price'])) {
                $pdo->beginTransaction();
                $res = executeTrade($pdo, $o, $o['target_price']);
                if ($res['ok']) $pdo->commit(); else $pdo->rollBack();
            }
            break;
        case 'stop':
            if (($o['side']=='buy' && $price >= $o['stop_price']) || ($o['side']=='sell' && $price <= $o['stop_price'])) {
                $pdo->beginTransaction();
                $res = executeTrade($pdo, $o, $price);
                if ($res['ok']) $pdo->commit(); else $pdo->rollBack();
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
                    $res = executeTrade($pdo,$o,$o['target_price']);
                    if ($res['ok']) $pdo->commit(); else $pdo->rollBack();
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
                    if($res['ok']) $pdo->commit(); else $pdo->rollBack();
                }
            } else {
                if ($price < $o['trail_price']) {
                    $pdo->prepare('UPDATE orders SET trail_price=? WHERE id=?')->execute([$price,$o['id']]);
                    $o['trail_price']=$price;
                } elseif ($price >= $o['trail_price']*(1+$o['trailing_percentage']/100)) {
                    $pdo->beginTransaction();
                    $res=executeTrade($pdo,$o,$price);
                    if($res['ok']) $pdo->commit(); else $pdo->rollBack();
                }
            }
            break;
    }
}
?>
