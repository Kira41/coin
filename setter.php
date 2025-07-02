<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$userId = $data['personalData']['user_id'] ?? $data['user_id'] ?? ($_POST['user_id'] ?? 1);

try {
    $pdo->beginTransaction();

    if (isset($data['personalData'])) {
        $personal = $data['personalData'];
        $personal['user_id'] = $userId;
        $wallets = $personal['wallets'] ?? ($data['wallets'] ?? []);
        unset($personal['wallets']);

        $cols = array_keys($personal);
        $place = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'REPLACE INTO personal_data (' . implode(',', $cols) . ') VALUES (' . $place . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($personal));
    } else {
        $wallets = $data['wallets'] ?? [];
    }

    $pdo->prepare('DELETE FROM wallets WHERE user_id = ?')->execute([$userId]);
    if ($wallets) {
        $stmt = $pdo->prepare('INSERT INTO wallets (id,user_id,currency,network,address,label) VALUES (?,?,?,?,?,?)');
        foreach ($wallets as $w) {
            $stmt->execute([
                $w['id'] ?? null,
                $userId,
                $w['currency'] ?? '',
                $w['network'] ?? '',
                $w['address'] ?? '',
                $w['label'] ?? ''
            ]);
        }
    }

    $tables = [
        'transactions' => ['operationNumber','type','amount','date','status','statusClass'],
        'notifications' => ['type','title','message','time','alertClass'],
        'deposits' => ['date','amount','method','status','statusClass'],
        'retraits' => ['date','amount','method','status','statusClass'],
        'tradingHistory' => ['temps','paireDevises','type','statutTypeClass','montant','prix','statut','statutClass','profitPerte','profitClass'],
        'loginHistory' => ['date','ip','device'],
    ];

    foreach ($tables as $table => $cols) {
        $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$userId]);
        if (isset($data[$table]) && is_array($data[$table])) {
            $place = '(' . implode(',', array_fill(0, count($cols)+1, '?')) . ')';
            $sql = "INSERT INTO $table (user_id," . implode(',', $cols) . ") VALUES $place";
            $stmt = $pdo->prepare($sql);
            foreach ($data[$table] as $row) {
                $values = [$userId];
                foreach ($cols as $c) {
                    $values[] = $row[$c] ?? null;
                }
                $stmt->execute($values);
            }
        }
    }

    if (isset($data['bankWithdrawInfo']) && is_array($data['bankWithdrawInfo'])) {
        $bw = $data['bankWithdrawInfo'];
        $cols = ['user_id','widhrawBankName','widhrawAccountName','widhrawAccountNumber','widhrawIban','widhrawSwiftCode'];
        $place = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $pdo->prepare('DELETE FROM bank_withdrawl_info WHERE user_id = ?')->execute([$userId]);
        $sql = 'INSERT INTO bank_withdrawl_info (' . implode(',', $cols) . ') VALUES ' . $place;
        $stmt = $pdo->prepare($sql);
        $values = [$userId];
        foreach (array_slice($cols,1) as $c) {
            $values[] = $bw[$c] ?? null;
        }
        $stmt->execute($values);
    }

    $pdo->commit();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
