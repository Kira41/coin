<?php
// Simple cron job to finalize open trades using current market price
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$orders = $pdo->query("SELECT * FROM tradingHistory WHERE statut='En cours'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $o) {
    $symbol = str_replace('USD', 'USDT', str_replace('/', '', $o['paireDevises']));
    $data = @json_decode(file_get_contents("https://api.binance.com/api/v3/ticker/price?symbol=$symbol"), true);
    if (!isset($data['price'])) continue;
    $exit = (float)$data['price'];
    $entry = (float)$o['prix'];
    $qty = (float)$o['montant'];
    $profit = ($o['type'] === 'Acheter') ? ($exit - $entry) * $qty : ($entry - $exit) * $qty;
    $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
    $pdo->prepare("UPDATE tradingHistory SET profitPerte=?, profitClass=?, statut='complet', statutClass='bg-success' WHERE id=?")
        ->execute([$profit, $profitClass, $o['id']]);
    $pdo->prepare("UPDATE transactions SET status='complet', statusClass='bg-success' WHERE operationNumber=?")
        ->execute([$o['operationNumber']]);
    $invested = $entry * $qty;
    $pdo->prepare("UPDATE personal_data SET balance = balance + ? WHERE user_id=?")
        ->execute([$invested + $profit, $o['user_id']]);
}
