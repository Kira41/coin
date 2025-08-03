<?php
function getLivePrice(string $pair): float {
    $symbol = str_replace('/', '', strtoupper($pair));
    if (!preg_match('/USDT$/', $symbol) && preg_match('/USD$/', $symbol)) {
        $symbol = substr($symbol, 0, -3) . 'USDT';
    }
    $url = 'https://api.binance.com/api/v3/ticker/price?symbol=' . $symbol;
    $data = @json_decode(file_get_contents($url), true);
    return isset($data['price']) ? (float)$data['price'] : 0.0;
}

/**
 * Fetch the historical closing price for a currency pair from CryptoCompare.
 * The timestamp should be a Unix epoch (seconds).
 * Returns 0 on failure.
 */
function getHistoricalPrice(string $pair, int $timestamp): float {
    [$base, $quote] = explode('/', strtoupper($pair));
    // CryptoCompare expects USD rather than USDT
    if ($quote === 'USDT') {
        $quote = 'USD';
    }
    $url = sprintf(
        'https://min-api.cryptocompare.com/data/pricehistorical?fsym=%s&tsyms=%s&ts=%d',
        urlencode($base), urlencode($quote), $timestamp
    );
    $json = @file_get_contents($url);
    if ($json === false) return 0.0;
    $data = json_decode($json, true);
    return isset($data[$base][$quote]) ? (float)$data[$base][$quote] : 0.0;
}

function addHistory(PDO $pdo, int $uid, string $opNum, string $pair, string $side,
    float $qty, float $price, string $status, ?float $profit = null): void {
    $typeTxt = $side === 'buy' ? 'Acheter' : 'Vendre';
    $typeClass = $side === 'buy' ? 'bg-success' : 'bg-danger';
    $statutClass = $status === 'complet' ? 'bg-success'
        : ($status === 'annule' ? 'bg-danger' : 'bg-warning');
    $profitClass = $profit === null ? '' : ($profit >= 0 ? 'text-success' : 'text-danger');
    $details = json_encode(['order_id' => ltrim($opNum, 'T')]);
    $stmt = $pdo->prepare('INSERT INTO tradingHistory '
        . '(user_id, operationNumber, temps, paireDevises, type, statutTypeClass,'
        . ' montant, prix, statut, statutClass, profitPerte, profitClass, details) '
        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        . ' ON DUPLICATE KEY UPDATE statut=VALUES(statut), statutClass=VALUES(statutClass),'
        . ' prix=VALUES(prix), profitPerte=VALUES(profitPerte), profitClass=VALUES(profitClass),'
        . ' details=VALUES(details)');
    $stmt->execute([
        $uid,
        $opNum,
        date('Y/m/d H:i'),
        $pair,
        $typeTxt,
        $typeClass,
        $qty,
        $price,
        $status,
        $statutClass,
        $profit,
        $profitClass,
        $details
    ]);
}
?>
