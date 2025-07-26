<?php
// Simple cron job to finalize open trades using current market price
require __DIR__ . '/config.php';
$pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$orders = $pdo->query("SELECT * FROM tradingHistory WHERE statut='En cours'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $o) {
    $symbol = str_replace('USD', 'USDT', str_replace('/', '', $o['paireDevises']));
    $data = @json_decode(file_get_contents("https://api.binance.com/api/v3/ticker/price?symbol=$symbol"), true);
    if (!isset($data['price'])) continue;
    $price = (float)$data['price'];
    $entry = (float)$o['prix'];
    $qty = (float)$o['montant'];
    $details = $o['details'] ? json_decode($o['details'], true) : [];

    $exitPrice = null;

    if (isset($details['stopLimit'])) {
        $sl = &$details['stopLimit'];
        if (!empty($sl['activated'])) {
            if (($o['type'] === 'Acheter' && $price <= $sl['limitPrice']) || ($o['type'] === 'Vendre' && $price >= $sl['limitPrice'])) {
                $exitPrice = $sl['limitPrice'];
            }
        } else {
            if (($o['type'] === 'Acheter' && $price >= $sl['stopPrice']) || ($o['type'] === 'Vendre' && $price <= $sl['stopPrice'])) {
                $sl['activated'] = true;
            }
        }
    }

    if ($exitPrice === null && isset($details['stopPrice'])) {
        if (($o['type'] === 'Acheter' && $price >= $details['stopPrice']) || ($o['type'] === 'Vendre' && $price <= $details['stopPrice'])) {
            $exitPrice = $price;
        }
    }

    if ($exitPrice === null && isset($details['takeProfit'])) {
        if (($o['type'] === 'Acheter' && $price >= $details['takeProfit']) || ($o['type'] === 'Vendre' && $price <= $details['takeProfit'])) {
            $exitPrice = $details['takeProfit'];
        }
    }

    if ($exitPrice === null && isset($details['stopLoss'])) {
        $sl = &$details['stopLoss'];
        if ($sl['type'] === 'price') {
            if (($o['type'] === 'Acheter' && $price <= $sl['price']) || ($o['type'] === 'Vendre' && $price >= $sl['price'])) {
                $exitPrice = $price;
            }
        } elseif ($sl['type'] === 'percentage') {
            $diff = (($price - $entry) / $entry) * 100;
            if (($o['type'] === 'Acheter' && $diff <= -$sl['percentage']) || ($o['type'] === 'Vendre' && $diff >= $sl['percentage'])) {
                $exitPrice = $price;
            }
        } elseif ($sl['type'] === 'time') {
            if (time() >= strtotime($sl['time'])) {
                $exitPrice = $price;
            }
        } elseif ($sl['type'] === 'trailing') {
            if ($o['type'] === 'Acheter') {
                $sl['highest'] = max($sl['highest'] ?? $entry, $price);
                $trigger = $sl['highest'] * (1 - $sl['percentage'] / 100);
                if ($price <= $trigger) {
                    $exitPrice = $price;
                }
            } else {
                $sl['lowest'] = min($sl['lowest'] ?? $entry, $price);
                $trigger = $sl['lowest'] * (1 + $sl['percentage'] / 100);
                if ($price >= $trigger) {
                    $exitPrice = $price;
                }
            }
        }
    }

    if ($exitPrice !== null) {
        $profit = ($o['type'] === 'Acheter') ? ($exitPrice - $entry) * $qty : ($entry - $exitPrice) * $qty;
        $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
        $invested = $details['invested'] ?? ($entry * $qty);
        $pdo->prepare("UPDATE tradingHistory SET profitPerte=?, profitClass=?, statut='complet', statutClass='bg-success', details=? WHERE id=?")
            ->execute([$profit, $profitClass, json_encode($details), $o['id']]);
        $pdo->prepare("UPDATE transactions SET status='complet', statusClass='bg-success' WHERE operationNumber=?")
            ->execute([$o['operationNumber']]);
        $pdo->prepare("UPDATE personal_data SET balance = balance + ? WHERE user_id=?")
            ->execute([$invested + $profit, $o['user_id']]);

        if (!empty($details['ocoId'])) {
            $stmt = $pdo->prepare("SELECT id,details FROM tradingHistory WHERE statut='En cours' AND id<>?");
            $stmt->execute([$o['id']]);
            $others = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($others as $other) {
                $d = $other['details'] ? json_decode($other['details'], true) : [];
                if (!empty($d['ocoId']) && $d['ocoId'] == $details['ocoId']) {
                    $pdo->prepare("UPDATE tradingHistory SET statut='annulé', statutClass='bg-secondary', details=? WHERE id=?")
                        ->execute([json_encode($d), $other['id']]);
                }
            }
        }
    } else {
        // save updated trailing data
        $pdo->prepare("UPDATE tradingHistory SET details=? WHERE id=?")
            ->execute([json_encode($details), $o['id']]);
    }
}
