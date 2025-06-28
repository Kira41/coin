<?php
$dsn = 'mysql:host=localhost;dbname=coin_db;charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;

function fetchAll($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$personal = fetchAll($pdo, 'SELECT * FROM personal_data WHERE user_id = ?', [$userId]);
$personal = $personal ? $personal[0] : [];
$bankWithdraw = fetchAll($pdo, 'SELECT * FROM bank_withdrawl_info LIMIT 1');
$bankWithdraw = $bankWithdraw ? $bankWithdraw[0] : [];

$data = [
    'personalData' => $personal,
    'wallets' => fetchAll($pdo, 'SELECT * FROM wallets WHERE user_id = ?', [$userId]),
    'transactions' => fetchAll($pdo, 'SELECT operationNumber,type,amount,date,status,statusClass FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$userId]),
    'notifications' => fetchAll($pdo, 'SELECT type,title,message,time,alertClass FROM notifications WHERE user_id = ? ORDER BY id DESC', [$userId]),
    'deposits' => fetchAll($pdo, 'SELECT date,amount,method,status,statusClass FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$userId]),
    'retraits' => fetchAll($pdo, 'SELECT date,amount,method,status,statusClass FROM retraits WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$userId]),
    'tradingHistory' => fetchAll($pdo, 'SELECT temps,paireDevises,type,statutTypeClass,montant,prix,statut,statutClass,profitPerte,profitClass FROM tradingHistory WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$userId]),
    'loginHistory' => fetchAll($pdo, 'SELECT date,ip,device FROM loginHistory WHERE user_id = ? ORDER BY id DESC', [$userId]),
    'bankWithdrawInfo' => $bankWithdraw,
    // placeholders for front-end
    'formData' => new stdClass(),
    'defaultKYCStatus' => [
        'enregistrementducomptestat' => ['status' => '1', 'date' => date('Y-m-d')],
        'confirmationdeladresseemailstat' => ['status' => '1', 'date' => date('Y-m-d')],
        'telechargerlesdocumentsdidentitestat' => ['status' => '0', 'date' => null],
        'verificationdeladressestat' => ['status' => '0', 'date' => null],
        'revisionfinalestat' => ['status' => '2', 'date' => null],
    ],
];

header('Content-Type: application/json');
echo json_encode($data);
