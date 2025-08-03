<?php
// Update wallet USD values using live prices
require_once __DIR__.'/../config/db_connection.php';
$pdo = db();

function getPrice(string $currency): float {
    $symbol = strtoupper($currency);
    if ($symbol === 'USDT' || $symbol === 'USDC') {
        return 1.0; // stablecoins pegged to USD
    }
    $url = 'https://api.binance.com/api/v3/ticker/price?symbol=' . $symbol . 'USDT';
    $data = @json_decode(file_get_contents($url), true);
    return isset($data['price']) ? (float)$data['price'] : 0.0;
}

$currencies = $pdo->query('SELECT DISTINCT currency FROM wallets')->fetchAll(PDO::FETCH_COLUMN);
foreach ($currencies as $cur) {
    $price = getPrice($cur);
    if ($price <= 0) continue;
    $stmt = $pdo->prepare('UPDATE wallets SET usd_value = amount * ? WHERE currency = ?');
    $stmt->execute([$price, $cur]);
}

