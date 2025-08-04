<?php
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../utils/poll.php';

$pdo = db();
$trades = $pdo->query("SELECT id,user_id,pair,side,quantity,price FROM trades WHERE status='open'")
    ->fetchAll(PDO::FETCH_ASSOC);

foreach ($trades as $t) {
    $livePrice = getLivePrice($t['pair']);
    if ($livePrice <= 0) {
        continue;
    }
    if ($t['side'] === 'buy') {
        $profit = ($livePrice - $t['price']) * $t['quantity'];
    } else {
        $profit = ($t['price'] - $livePrice) * $t['quantity'];
    }
    $pdo->prepare('UPDATE trades SET profit_loss=? WHERE id=?')->execute([$profit, $t['id']]);
    pushEvent('trade_update', [
        'operation_number' => 'T' . $t['id'],
        'pair' => $t['pair'],
        'price' => $livePrice,
        'profit_loss' => $profit
    ], $t['user_id']);
}
