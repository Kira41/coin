<?php
header('Content-Type: application/json');
set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});

try {
    if($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['status'=>'error','message'=>'Method not allowed']);
        exit;
    }
    $pair = isset($_GET['pair']) ? $_GET['pair'] : '';
    $timestamp = isset($_GET['timestamp']) ? (int)$_GET['timestamp'] : 0;
    if(!$pair || !$timestamp){
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Missing parameters']);
        exit;
    }
    require_once __DIR__.'/../utils/helpers.php';
    $price = getHistoricalPrice($pair, $timestamp);
    echo json_encode(['status'=>'ok','price'=>$price]);
} catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
?>
