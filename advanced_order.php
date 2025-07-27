<?php
header('Content-Type: application/json');
set_error_handler(function ($s, $m, $f, $l) { throw new ErrorException($m, 0, $s, $f, $l); });

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Invalid JSON']);
        exit;
    }

    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $pair   = $data['pair'] ?? '';
    $quantity = isset($data['quantity']) ? (float)$data['quantity'] : 0.0;
    $side = strtolower($data['side'] ?? 'buy');
    $type = strtolower($data['type'] ?? '');
    $target = isset($data['target_price']) ? (float)$data['target_price'] : null;
    $stop = isset($data['stop_price']) ? (float)$data['stop_price'] : null;

    $validTypes = ['stop','stop_limit','trailing_stop','oco'];
    if(!$userId || !$pair || $quantity<=0 || !in_array($side,['buy','sell']) || !in_array($type,$validTypes)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Missing parameters']);
        exit;
    }

    if($type==='stop' && $stop<=0) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'stop_price required']);
        exit;
    }
    if($type==='stop_limit' && ($stop<=0 || $target<=0)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'stop_price and target_price required']);
        exit;
    }
    if($type==='trailing_stop' && $target<=0) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'trail percentage required']);
        exit;
    }
    if($type==='oco' && ($target<=0 || $stop<=0)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'target_price and stop_price required']);
        exit;
    }

    $dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    function getLivePrice(string $pair): float {
        $symbol = str_replace('/', '', strtoupper($pair));
        $symbol = str_replace('USD', 'USDT', $symbol);
        $url = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
        $data = @json_decode(file_get_contents($url), true);
        return isset($data['price']) ? (float)$data['price'] : 0.0;
    }

    [$base] = explode('/', strtoupper($pair));
    if($side==='buy') {
        $priceCheck = $target ?? $stop;
        if($type==='trailing_stop' || !$priceCheck) {
            $priceCheck = getLivePrice($pair);
        }
        $total = $priceCheck * $quantity;
        $stmt = $pdo->prepare('SELECT balance FROM personal_data WHERE user_id=?');
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();
        if($balance===false || $balance < $total) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Solde insuffisant']);
            exit;
        }
    } else {
        $stmt = $pdo->prepare('SELECT amount FROM wallets WHERE user_id=? AND currency=?');
        $stmt->execute([$userId,$base]);
        $bal = $stmt->fetchColumn();
        $pending = $pdo->prepare("SELECT SUM(quantity) FROM orders WHERE user_id=? AND side='sell' AND status='open' AND pair LIKE ?");
        $pending->execute([$userId, $base.'/%']);
        $reserved = (float)$pending->fetchColumn();
        $available = $bal !== false ? $bal - $reserved : -1;
        if($available < $quantity) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Solde insuffisant']);
            exit;
        }
    }

    if($type==='trailing_stop') {
        $stop = getLivePrice($pair);
        if($stop<=0) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'Failed to fetch price']);
            exit;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO orders (user_id,pair,type,side,quantity,target_price,stop_price,status) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$userId,$pair,$type,$side,$quantity,$target,$stop,'open']);

    echo json_encode(['status'=>'ok','order_id'=>$pdo->lastInsertId()]);
} catch(Throwable $e) {
    error_log(__FILE__.' - '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
